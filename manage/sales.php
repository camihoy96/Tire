<?php
// manage/sales.php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';
require_once '../includes/Security.php';

$db = Database::getInstance()->getConnection();
$auth = new Auth();
$auth->requireLogin();

$user = $auth->getUser();
$errors = [];
$success = '';

// Get currency symbol
$currencySymbol = '₱';
$currencySetting = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'currency_symbol'");
if ($currencySetting && $currencySetting->num_rows > 0) {
    $currencySymbol = $currencySetting->fetch_assoc()['setting_value'];
}

// Handle sale void - ONLY admin and manager
if (isset($_GET['void']) && is_numeric($_GET['void']) && ($auth->isAdmin() || ($user['user_type'] ?? '') === 'manager')) {
    $saleId = intval($_GET['void']);
    
    $stmt = $db->prepare("SELECT s.* FROM sales s WHERE s.sale_id = ?");
    $stmt->bind_param("i", $saleId);
    $stmt->execute();
    $saleData = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($saleData) {
        $db->begin_transaction();
        try {
            $db->query("UPDATE products SET quantity = quantity + {$saleData['quantity']} WHERE product_id = {$saleData['product_id']}");
            $voidNote = " [VOIDED on " . date('Y-m-d H:i:s') . " by " . $user['fullname'] . "]";
            $db->query("UPDATE sales SET notes = CONCAT(IFNULL(notes,''), '{$voidNote}') WHERE sale_id = {$saleId}");
            $db->commit();
            $success = "Sale #{$saleId} has been voided and stock restored.";
        } catch (Exception $e) {
            $db->rollback();
            $errors[] = "Failed to void sale: " . $e->getMessage();
        }
    }
}

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$product_filter = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
$category_filter = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
$vehicle_filter = isset($_GET['vehicle_type']) ? $_GET['vehicle_type'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'date_desc';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 25;

// Build WHERE clause
$where = "WHERE s.sale_date BETWEEN '{$date_from} 00:00:00' AND '{$date_to} 23:59:59'";

if (!empty($search)) {
    $searchEsc = $db->real_escape_string($search);
    $where .= " AND (p.name LIKE '%{$searchEsc}%' OR p.barcode LIKE '%{$searchEsc}%' OR u.fullname LIKE '%{$searchEsc}%' OR p.vehicle_type LIKE '%{$searchEsc}%')";
}

if ($product_filter > 0) {
    $where .= " AND s.product_id = {$product_filter}";
}

if ($category_filter > 0) {
    $where .= " AND p.category_id = {$category_filter}";
}

if (!empty($vehicle_filter)) {
    $vehicleEsc = $db->real_escape_string($vehicle_filter);
    $where .= " AND p.vehicle_type = '{$vehicleEsc}'";
}

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM sales s JOIN products p ON s.product_id = p.product_id LEFT JOIN users u ON s.sold_by = u.user_id {$where}";
$countResult = $db->query($countQuery);
$totalRecords = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $per_page);
$offset = ($page - 1) * $per_page;

// Sort order
switch ($sort) {
    case 'date_asc': $orderBy = "ORDER BY s.sale_date ASC"; break;
    case 'total_desc': $orderBy = "ORDER BY s.total_price DESC"; break;
    case 'total_asc': $orderBy = "ORDER BY s.total_price ASC"; break;
    case 'qty_desc': $orderBy = "ORDER BY s.quantity DESC"; break;
    case 'profit_desc': $orderBy = "ORDER BY s.profit DESC"; break;
    default: $orderBy = "ORDER BY s.sale_date DESC";
}

// Main query
$query = "
    SELECT s.*, p.name as product_name, p.barcode, p.tire_size, p.vehicle_type,
           c.cname as category_name,
           u.fullname as sold_by_name
    FROM sales s 
    JOIN products p ON s.product_id = p.product_id 
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN users u ON s.sold_by = u.user_id 
    {$where}
    {$orderBy}
    LIMIT {$per_page} OFFSET {$offset}
";

$sales = $db->query($query);

// Get sales summary
$summaryQuery = "
    SELECT 
        COUNT(*) as total_sales,
        COALESCE(SUM(s.quantity), 0) as total_items,
        COALESCE(SUM(s.total_price), 0) as total_revenue,
        COALESCE(SUM(s.profit), 0) as total_profit,
        COALESCE(AVG(s.total_price), 0) as avg_sale
    FROM sales s 
    WHERE s.sale_date BETWEEN '{$date_from} 00:00:00' AND '{$date_to} 23:59:59'
";
$summaryResult = $db->query($summaryQuery);
$summary = $summaryResult->fetch_assoc();

// Get products for filter
$productsList = $db->query("SELECT product_id, name FROM products ORDER BY name ASC");

// Get categories for filter
$categoriesList = $db->query("SELECT category_id, cname FROM categories WHERE is_active = 1 ORDER BY cname ASC");

// Get vehicle types for filter
$vehicleTypesList = $db->query("SELECT DISTINCT vehicle_type FROM products WHERE vehicle_type IS NOT NULL AND vehicle_type != '' AND is_active = 1 ORDER BY vehicle_type ASC");

// Get today's summary
$todayStart = date('Y-m-d 00:00:00');
$todayEnd = date('Y-m-d 23:59:59');
$todayResult = $db->query("SELECT COUNT(*) as count, COALESCE(SUM(total_price), 0) as total, COALESCE(SUM(profit), 0) as profit FROM sales WHERE sale_date BETWEEN '{$todayStart}' AND '{$todayEnd}'");
$todaySummary = $todayResult->fetch_assoc();

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $companyName = APP_NAME;
    $reportTitle = "Sales Report";
    $dateRange = date('M d, Y', strtotime($date_from)) . ' to ' . date('M d, Y', strtotime($date_to));
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $companyName . '_Sales_Report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM for Excel UTF-8
    
    // Company header
    fputcsv($output, [strtoupper($companyName)]);
    fputcsv($output, [$reportTitle]);
    fputcsv($output, ['Period: ' . $dateRange]);
    fputcsv($output, ['Generated: ' . date('F d, Y h:i A')]);
    fputcsv($output, ['']);
    
    // Column headers - use "sep=|" trick for Excel to auto-detect separators
    fputcsv($output, ['Date', 'Time', 'Product', 'Barcode', 'Size', 'Category', 'Vehicle Type', 'Quantity', 'Unit Price', 'Total', 'Profit', 'Sold By', 'Notes']);
    
    $exportQuery = "SELECT s.*, p.name as product_name, p.barcode, p.tire_size, p.vehicle_type, c.cname as category_name, u.fullname as sold_by_name FROM sales s JOIN products p ON s.product_id = p.product_id LEFT JOIN categories c ON p.category_id = c.category_id LEFT JOIN users u ON s.sold_by = u.user_id {$where} {$orderBy}";
    $exportResult = $db->query($exportQuery);
    
    $totalRevenue = 0;
    $totalProfit = 0;
    $totalItems = 0;
    
    while ($row = $exportResult->fetch_assoc()) {
        // Format numbers as plain numbers (not with commas) so Excel recognizes them
        fputcsv($output, [
            date('Y-m-d', strtotime($row['sale_date'])),
            date('H:i:s', strtotime($row['sale_date'])),
            $row['product_name'],
            $row['barcode'] ?? '',
            $row['tire_size'] ?? '',
            $row['category_name'] ?? '',
            $row['vehicle_type'] ?? '',
            $row['quantity'],                                              // Plain number
            number_format($row['unit_price'], 2, '.', ''),                // 1500.00 format
            number_format($row['total_price'], 2, '.', ''),               // Plain number
            number_format($row['profit'], 2, '.', ''),                    // Plain number
            $row['sold_by_name'] ?? '',
            $row['notes'] ?? ''
        ]);
        $totalRevenue += $row['total_price'];
        $totalProfit += $row['profit'];
        $totalItems += $row['quantity'];
    }
    
    // Summary
    fputcsv($output, ['']);
    fputcsv($output, ['SUMMARY']);
    fputcsv($output, ['Total Records', $totalRecords]);
    fputcsv($output, ['Total Items Sold', $totalItems]);
    fputcsv($output, ['Total Revenue', number_format($totalRevenue, 2, '.', '')]);
    fputcsv($output, ['Total Profit', number_format($totalProfit, 2, '.', '')]);
    
    fclose($output);
    exit;
}
// Handle XLSX export
if (isset($_GET['export']) && $_GET['export'] === 'xlsx') {
    $companyName = APP_NAME;
    $dateRange = date('M d, Y', strtotime($date_from)) . ' to ' . date('M d, Y', strtotime($date_to));
    
    // Create an HTML table that Excel can open
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $companyName . '_Sales_Report_' . date('Y-m-d') . '.xls"');
    
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta charset="UTF-8">';
    echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Sales Report</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
    echo '<style>';
    echo 'table { border-collapse: collapse; }';
    echo 'th, td { border: 1px solid #000; padding: 5px 10px; }';
    echo 'th { background-color: #4472C4; color: white; font-weight: bold; text-align: center; }';
    echo '.header { background-color: #1F4E79; color: white; font-size: 14pt; font-weight: bold; text-align: center; padding: 10px; }';
    echo '.subheader { background-color: #D6E4F0; font-weight: bold; text-align: center; }';
    echo '.summary { background-color: #FFF2CC; font-weight: bold; }';
    echo '.number { text-align: right; }';
    echo '.currency { text-align: right; }';
    echo '</style>';
    echo '</head><body>';
    
    // Header
    echo '<table width="100%">';
    echo '<tr><td colspan="13" class="header">' . strtoupper($companyName) . '</td></tr>';
    echo '<tr><td colspan="13" class="subheader">Sales Report</td></tr>';
    echo '<tr><td colspan="13" class="subheader">Period: ' . $dateRange . ' | Generated: ' . date('F d, Y h:i A') . '</td></tr>';
    echo '<tr><td colspan="13" style="height:10px;"></td></tr>';
    
    // Column headers
    echo '<tr>';
    echo '<th>Date</th><th>Time</th><th>Product</th><th>Barcode</th><th>Size</th><th>Category</th><th>Vehicle Type</th><th>Qty</th><th>Unit Price</th><th>Total</th><th>Profit</th><th>Sold By</th><th>Notes</th>';
    echo '</tr>';
    
    $exportQuery = "SELECT s.*, p.name as product_name, p.barcode, p.tire_size, p.vehicle_type, c.cname as category_name, u.fullname as sold_by_name FROM sales s JOIN products p ON s.product_id = p.product_id LEFT JOIN categories c ON p.category_id = c.category_id LEFT JOIN users u ON s.sold_by = u.user_id {$where} {$orderBy}";
    $exportResult = $db->query($exportQuery);
    
    $totalRevenue = 0;
    $totalProfit = 0;
    $totalItems = 0;
    
    while ($row = $exportResult->fetch_assoc()) {
        $isVoided = (stripos($row['notes'] ?? '', 'VOIDED') !== false);
        $rowStyle = $isVoided ? ' style="color:#999;"' : '';
        
        echo '<tr' . $rowStyle . '>';
        echo '<td>' . date('Y-m-d', strtotime($row['sale_date'])) . '</td>';
        echo '<td>' . date('H:i:s', strtotime($row['sale_date'])) . '</td>';
        echo '<td>' . htmlspecialchars($row['product_name']) . ($isVoided ? ' [VOIDED]' : '') . '</td>';
        echo '<td>' . ($row['barcode'] ?? '') . '</td>';
        echo '<td>' . ($row['tire_size'] ?? '') . '</td>';
        echo '<td>' . ($row['category_name'] ?? '') . '</td>';
        echo '<td>' . ($row['vehicle_type'] ?? '') . '</td>';
        echo '<td class="number">' . $row['quantity'] . '</td>';
        echo '<td class="currency">' . number_format($row['unit_price'], 2) . '</td>';
        echo '<td class="currency">' . number_format($row['total_price'], 2) . '</td>';
        echo '<td class="currency">' . number_format($row['profit'], 2) . '</td>';
        echo '<td>' . ($row['sold_by_name'] ?? '') . '</td>';
        echo '<td>' . ($row['notes'] ?? '') . '</td>';
        echo '</tr>';
        
        $totalRevenue += $row['total_price'];
        $totalProfit += $row['profit'];
        $totalItems += $row['quantity'];
    }
    
    // Summary
    echo '<tr><td colspan="13" style="height:10px;"></td></tr>';
    echo '<tr class="summary"><td colspan="7"><strong>SUMMARY</strong></td><td class="number"><strong>' . $totalItems . '</strong></td><td></td><td class="currency"><strong>' . number_format($totalRevenue, 2) . '</strong></td><td class="currency"><strong>' . number_format($totalProfit, 2) . '</strong></td><td colspan="2"></td></tr>';
    echo '<tr><td colspan="7">Total Records: <strong>' . $totalRecords . '</strong></td></tr>';
    
    echo '</table>';
    echo '</body></html>';
    exit;
}
// Helper function for sort links
function sortLink($sortKey, $currentSort, $label) {
    $params = $_GET;
    unset($params['export']);
    $newSort = ($currentSort == $sortKey . '_asc') ? $sortKey . '_desc' : $sortKey . '_asc';
    if (strpos($currentSort, $sortKey) === 0) {
        $newSort = strpos($currentSort, '_desc') !== false ? $sortKey . '_asc' : $sortKey . '_desc';
    }
    $params['sort'] = $newSort;
    $arrow = '';
    if (strpos($currentSort, $sortKey) === 0) {
        $arrow = strpos($currentSort, '_desc') !== false ? ' ▼' : ' ▲';
    }
    return '<a href="?' . http_build_query($params) . '" class="sort-link">' . $label . $arrow . '</a>';
}

// Check if current user is admin or manager
$isAdminOrManager = ($auth->isAdmin() || ($user['user_type'] ?? '') === 'manager');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 20px; }
        .stat-card { background: white; border-radius: 10px; padding: 15px 18px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .stat-value { font-size: 1.3rem; font-weight: 700; }
        .stat-label { font-size: 0.7rem; color: #64748b; text-transform: uppercase; }
        .stat-icon { font-size: 1.8rem; }
        .stat-primary { border-left: 4px solid #3b82f6; }
        .stat-success { border-left: 4px solid #10b981; }
        .stat-warning { border-left: 4px solid #f59e0b; }
        .stat-info { border-left: 4px solid #06b6d4; }
        
        .filters-grid { display: flex; gap: 12px; flex-wrap: wrap; align-items: end; }
        .filters-grid .form-group { min-width: 140px; }
        
        .table { font-size: 0.85rem; }
        .table td { vertical-align: middle; padding: 8px 10px; }
        .text-success { color: #10b981; font-weight: 600; }
        .text-danger { color: #ef4444; font-weight: 600; }
        .text-muted { color: #94a3b8; }
        
        .voided-sale { opacity: 0.5; background: #fef2f2; }
        .voided-badge { display: inline-block; background: #ef4444; color: white; padding: 1px 6px; border-radius: 3px; font-size: 0.65rem; font-weight: 600; }
        
        .pagination { display: flex; gap: 4px; justify-content: center; margin-top: 20px; flex-wrap: wrap; }
        .pagination a, .pagination span { padding: 6px 12px; border: 1px solid #e2e8f0; border-radius: 5px; text-decoration: none; color: #374151; font-size: 0.85rem; }
        .pagination a:hover { background: #f1f5f9; }
        .pagination .active { background: #3b82f6; color: white; border-color: #3b82f6; }
        .pagination .disabled { color: #cbd5e1; }
        
        .sort-link { color: #374151; text-decoration: none; white-space: nowrap; font-size: 0.8rem; }
        .sort-link:hover { color: #3b82f6; }
        
        .btn-void { padding: 3px 8px; font-size: 0.7rem; border: none; border-radius: 4px; cursor: pointer; background: #ef4444; color: white; }
        .btn-void:hover { background: #dc2626; }
        
        .badge-cat { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 0.7rem; background: #f1f5f9; color: #475569; }
        .badge-vehicle { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 0.7rem; background: #eff6ff; color: #3b82f6; }
        
        .header-actions { display: flex; gap: 8px; }
        .btn-export, .btn-print { padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; font-size: 0.85rem; font-weight: 500; text-decoration: none;}
        .btn-export { background: #059669; color: white; }
        .btn-export:hover { background: #047857; }
        .btn-print { background: #3b82f6; color: white; }
        .btn-print:hover { background: #2563eb; }
        
       @media print {
    @page {
        size: landscape;
        margin: 10mm;
    }
    
    body * { visibility: hidden; }
    #printArea, #printArea * { visibility: visible; }
    #printArea { 
        position: absolute; 
        left: 0; 
        top: 0; 
        width: 100%; 
    }
    .no-print { display: none !important; }
    .btn-void { display: none !important; }
    
    .print-header { 
        display: block !important; 
        text-align: center; 
        margin-bottom: 15px; 
        padding-bottom: 10px;
        border-bottom: 2px solid #000;
    }
    .print-header h2 { 
        font-size: 16pt; 
        margin: 0 0 5px 0; 
    }
    .print-header p { 
        font-size: 11pt; 
        margin: 2px 0; 
        color: #333;
    }
    
    table { 
        width: 100%; 
        border-collapse: collapse; 
        font-size: 9pt;
    }
    th { 
        background-color: #f0f0f0 !important; 
        font-weight: bold; 
        text-align: left; 
        padding: 6px 8px;
        border: 1px solid #999;
    }
    td { 
        padding: 5px 8px; 
        border: 1px solid #ccc;
    }
    
    .print-footer {
        display: block !important;
        text-align: center;
        margin-top: 20px;
        font-size: 9pt;
        color: #666;
    }
}
        
        @media (max-width: 768px) {
            .filters-grid { flex-direction: column; }
            .filters-grid .form-group { width: 100%; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include '../includes/sidebar.php'; ?>
        <main class="main-content">
            <?php include '../includes/topbar.php'; ?>
            <div class="content-wrapper no-print">
                <div class="breadcrumb">
                    <a href="<?php echo BASE_URL; ?>index.php">Dashboard</a>
                    <span class="separator">/</span>
                    <span>Sales</span>
                </div>
                
                <div class="page-header">
                    <div class="page-header-content">
                        <div>
                            <h1>💰 Sales Records</h1>
                            <p>View and manage your sales transactions</p>
                        </div>
                        <div class="header-actions">
    <button onclick="printTable()" class="btn-print">🖨️ Print</button>
    <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'xlsx'])); ?>" class="btn-export">📥 Export Excel</a>
    <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" class="btn-export" style="background:#6366f1;">📄 Export CSV</a>
</div>
                    </div>
                </div>
                
                <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible">✅ <?php echo htmlspecialchars($success); ?><button class="alert-close" onclick="this.parentElement.remove()">&times;</button></div>
                <?php endif; ?>
                <?php if (!empty($errors)): ?>
                <div class="alert alert-error alert-dismissible">❌ <?php echo implode('<br>', $errors); ?><button class="alert-close" onclick="this.parentElement.remove()">&times;</button></div>
                <?php endif; ?>
                
                <!-- Today's Summary -->
                <div class="card" style="margin-bottom: 5px; background: linear-gradient(135deg, #eff6ff, #f0fdf4);">
                    <div class="card-body" style="display: flex; justify-content: space-around; text-align: center; flex-wrap: wrap; gap: 10px; padding: 15px;">
                        <div><div style="font-size:0.7rem;color:#64748b;">TODAY'S SALES</div><div style="font-size:1.3rem;font-weight:700;"><?php echo number_format($todaySummary['count']); ?></div></div>
                        <div><div style="font-size:0.7rem;color:#64748b;">TODAY'S REVENUE</div><div style="font-size:1.3rem;font-weight:700;color:#059669;"><?php echo $currencySymbol; ?><?php echo number_format($todaySummary['total'] ?? 0, 2); ?></div></div>
                        <div><div style="font-size:0.7rem;color:#64748b;">TODAY'S PROFIT</div><div style="font-size:1.3rem;font-weight:700;color:#f59e0b;"><?php echo $currencySymbol; ?><?php echo number_format($todaySummary['profit'] ?? 0, 2); ?></div></div>
                    </div>
                </div>
                
                <!-- Period Summary -->
                <div class="stats-grid">
                    <div class="stat-card stat-primary">
                        <div><div class="stat-value"><?php echo number_format($summary['total_sales'] ?? 0); ?></div><div class="stat-label">Transactions</div></div>
                        <div class="stat-icon">📊</div>
                    </div>
                    <div class="stat-card stat-info">
                        <div><div class="stat-value"><?php echo number_format($summary['total_items'] ?? 0); ?></div><div class="stat-label">Items Sold</div></div>
                        <div class="stat-icon">📦</div>
                    </div>
                    <div class="stat-card stat-success">
                        <div><div class="stat-value"><?php echo $currencySymbol; ?><?php echo number_format($summary['total_revenue'] ?? 0, 2); ?></div><div class="stat-label">Revenue</div></div>
                        <div class="stat-icon">💵</div>
                    </div>
                    <div class="stat-card stat-warning">
                        <div><div class="stat-value"><?php echo $currencySymbol; ?><?php echo number_format($summary['total_profit'] ?? 0, 2); ?></div><div class="stat-label">Profit</div></div>
                        <div class="stat-icon">📈</div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="card">
                    <div class="card-body">
                        <form method="GET" class="filters-form">
                            <div class="filters-grid">
                                <div class="form-group"><label>Date From</label><input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>"></div>
                                <div class="form-group"><label>Date To</label><input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>"></div>
                                <div class="form-group">
                                    <label>Category</label>
                                    <select name="category_id" class="form-control">
                                        <option value="0">All Categories</option>
                                        <?php if ($categoriesList && $categoriesList->num_rows > 0): $categoriesList->data_seek(0);
                                            while($cat = $categoriesList->fetch_assoc()): ?>
                                            <option value="<?php echo $cat['category_id']; ?>" <?php echo $category_filter == $cat['category_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['cname']); ?></option>
                                        <?php endwhile; endif; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Vehicle Type</label>
                                    <select name="vehicle_type" class="form-control">
                                        <option value="">All Vehicles</option>
                                        <?php if ($vehicleTypesList && $vehicleTypesList->num_rows > 0): $vehicleTypesList->data_seek(0);
                                            while($vt = $vehicleTypesList->fetch_assoc()): ?>
                                            <option value="<?php echo htmlspecialchars($vt['vehicle_type']); ?>" <?php echo $vehicle_filter == $vt['vehicle_type'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($vt['vehicle_type']); ?></option>
                                        <?php endwhile; endif; ?>
                                    </select>
                                </div>
                                <div class="form-group"><label>Search</label><input type="text" name="search" class="form-control" placeholder="Product, seller..." value="<?php echo htmlspecialchars($search); ?>"></div>
                                <div class="form-group"><label>&nbsp;</label><div style="display:flex;gap:8px;"><button type="submit" class="btn btn-primary">🔍 Filter</button><a href="sales.php" class="btn btn-secondary">🔄 Reset</a></div></div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
         <!-- PRINT AREA - Only the table -->
<div id="printArea">
    <div class="print-header" style="display:none;">
        <h2><?php echo APP_NAME; ?></h2>
        <p><strong>Sales Report</strong></p>
        <p>Period: <?php echo date('F d, Y', strtotime($date_from)); ?> to <?php echo date('F d, Y', strtotime($date_to)); ?></p>
        <p>Generated: <?php echo date('F d, Y h:i A'); ?></p>
    </div>
    
    <!-- Sales Table -->
    <div class="card">
        <div class="card-header no-print"><h2>Sales Records</h2><span class="badge badge-primary"><?php echo $totalRecords; ?> Records</span></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table" id="salesTable">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Product</th>
                            <th>Size</th>
                            <th>Category</th>
                            <th>Vehicle</th>
                            <th>Qty</th>
                            <th>Price</th>
                            <th>Total</th>
                            <th>Profit</th>
                            <th>Seller</th>
                            <th class="no-print">Act</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($sales && $sales->num_rows > 0): 
                            while ($row = $sales->fetch_assoc()): 
                                $isVoided = (stripos($row['notes'] ?? '', 'VOIDED') !== false);
                        ?>
                        <tr class="<?php echo $isVoided ? 'voided-sale' : ''; ?>">
                            <td><strong><?php echo date('M d', strtotime($row['sale_date'])); ?></strong><br><small class="text-muted"><?php echo date('h:i A', strtotime($row['sale_date'])); ?></small></td>
                            <td><strong><?php echo htmlspecialchars($row['product_name']); ?></strong><?php if ($isVoided): ?><br><span class="voided-badge">VOIDED</span><?php endif; ?></td>
                            <td><?php echo htmlspecialchars($row['tire_size'] ?? '—'); ?></td>
                            <td><span class="badge-cat"><?php echo htmlspecialchars($row['category_name'] ?? '—'); ?></span></td>
                            <td><?php if (!empty($row['vehicle_type'])): ?><span class="badge-vehicle"><?php echo htmlspecialchars($row['vehicle_type']); ?></span><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
                            <td><strong><?php echo $row['quantity']; ?></strong></td>
                            <td><?php echo $currencySymbol; ?><?php echo number_format($row['unit_price'], 2); ?></td>
                            <td><strong><?php echo $currencySymbol; ?><?php echo number_format($row['total_price'], 2); ?></strong></td>
                            <td><span class="<?php echo $row['profit'] >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo $currencySymbol; ?><?php echo number_format($row['profit'], 2); ?></span></td>
                            <td><?php echo htmlspecialchars($row['sold_by_name'] ?? '—'); ?></td>
                            <td class="no-print">
                                <?php if (!$isVoided && $isAdminOrManager): ?>
                                    <button onclick="confirmVoid(<?php echo $row['sale_id']; ?>, '<?php echo htmlspecialchars(addslashes($row['product_name'])); ?>', <?php echo $row['quantity']; ?>)" class="btn-void" title="Void Sale">↩️</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="11" class="text-center p-4"><div class="empty-state"><div class="empty-icon">💰</div><h3>No sales records found</h3></div></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($totalPages > 1): $p = $_GET; unset($p['export']); ?>
            <div class="pagination no-print">
                <?php if ($page > 1): ?><a href="?<?php echo http_build_query(array_merge($p, ['page' => 1])); ?>">«</a><a href="?<?php echo http_build_query(array_merge($p, ['page' => $page-1])); ?>">‹</a><?php else: ?><span class="disabled">«</span><span class="disabled">‹</span><?php endif; ?>
                <?php for ($i = max(1,$page-2); $i <= min($totalPages,$page+2); $i++): ?>
                    <a href="?<?php echo http_build_query(array_merge($p, ['page' => $i])); ?>" class="<?php echo $i==$page?'active':''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?><a href="?<?php echo http_build_query(array_merge($p, ['page' => $page+1])); ?>">›</a><a href="?<?php echo http_build_query(array_merge($p, ['page' => $totalPages])); ?>">»</a><?php else: ?><span class="disabled">›</span><span class="disabled">»</span><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="print-footer" style="display:none;">
        <p>Total Records: <?php echo $totalRecords; ?> | Total Revenue: <?php echo $currencySymbol; ?><?php echo number_format($summary['total_revenue'] ?? 0, 2); ?> | Total Profit: <?php echo $currencySymbol; ?><?php echo number_format($summary['total_profit'] ?? 0, 2); ?></p>
        <p>Generated by <?php echo APP_NAME; ?> System</p>
    </div>
</div>
    
    <!-- Alert Modal -->
    <div id="alertModal" class="modal-overlay" style="display:none;">
        <div class="modal" style="max-width:420px;">
            <div class="modal-header" id="alertModalHeader"><h3 id="alertModalTitle"></h3></div>
            <div class="modal-body" id="alertModalBody"></div>
            <div class="modal-footer" id="alertModalFooter"></div>
        </div>
    </div>
    
<script>
// Print function - only prints the table in landscape
function printTable() {
    var printContent = document.getElementById('printArea').innerHTML;
    var originalContent = document.body.innerHTML;
    
    // Show print header and footer
    var tempDiv = document.createElement('div');
    tempDiv.innerHTML = printContent;
    var header = tempDiv.querySelector('.print-header');
    var footer = tempDiv.querySelector('.print-footer');
    if (header) header.style.display = 'block';
    if (footer) footer.style.display = 'block';
    
    // Remove no-print elements from the clone
    var noPrints = tempDiv.querySelectorAll('.no-print');
    noPrints.forEach(function(el) { el.remove(); });
    
    // Remove void buttons
    var voidBtns = tempDiv.querySelectorAll('.btn-void');
    voidBtns.forEach(function(el) { el.remove(); });
    
    // Add landscape style
    var style = document.createElement('style');
    style.textContent = '@page { size: landscape; margin: 10mm; }' +
        'body * { visibility: hidden; }' +
        '#printArea, #printArea * { visibility: visible; }' +
        '#printArea { position: absolute; left: 0; top: 0; width: 100%; }' +
        '.no-print { display: none !important; }' +
        '.btn-void { display: none !important; }' +
        '.print-header { display: block !important; text-align: center; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #000; }' +
        '.print-header h2 { font-size: 16pt; margin: 0 0 5px 0; }' +
        '.print-header p { font-size: 11pt; margin: 2px 0; color: #333; }' +
        '.print-footer { display: block !important; text-align: center; margin-top: 20px; padding-top: 10px; border-top: 2px solid #000; font-size: 10pt; color: #333; }' +
        'table { width: 100%; border-collapse: collapse; font-size: 9pt; }' +
        'th { background-color: #f0f0f0 !important; font-weight: bold; text-align: left; padding: 6px 8px; border: 1px solid #999; }' +
        'td { padding: 5px 8px; border: 1px solid #ccc; }';
    document.head.appendChild(style);
    
    document.body.innerHTML = '<div id="printArea">' + tempDiv.innerHTML + '</div>';
    
    window.print();
    
    // Restore original content
    document.body.innerHTML = originalContent;
    location.reload();
}

// Modal functions
function showModal(title, message, type) {
    var modal = document.getElementById('alertModal');
    var header = document.getElementById('alertModalHeader');
    var titleEl = document.getElementById('alertModalTitle');
    var body = document.getElementById('alertModalBody');
    var footer = document.getElementById('alertModalFooter');
    
    var bgColor = type === 'error' ? '#fef2f2' : type === 'warning' ? '#fffbeb' : '#ecfdf5';
    var borderColor = type === 'error' ? '#fee2e2' : type === 'warning' ? '#fef3c7' : '#d1fae5';
    var textColor = type === 'error' ? '#991b1b' : type === 'warning' ? '#92400e' : '#065f46';
    var icon = type === 'error' ? '❌' : type === 'warning' ? '⚠️' : '✅';
    
    header.style.cssText = 'padding:20px 20px 10px;border-bottom:1px solid ' + borderColor + ';background:' + bgColor + ';border-radius:12px 12px 0 0;';
    titleEl.style.cssText = 'margin:0;font-size:1.1rem;color:' + textColor + ';';
    titleEl.textContent = icon + ' ' + title;
    body.innerHTML = '<div style="padding:20px;font-size:0.95rem;line-height:1.6;">' + message + '</div>';
    footer.innerHTML = '<div style="padding:15px 20px;border-top:1px solid #e5e7eb;text-align:right;"><button onclick="closeModal()" style="padding:8px 24px;background:#3b82f6;color:white;border:none;border-radius:6px;cursor:pointer;font-weight:600;">OK</button></div>';
    modal.style.display = 'flex';
}

function closeModal() {
    document.getElementById('alertModal').style.display = 'none';
}

// Void confirmation with modal
function confirmVoid(saleId, productName, quantity) {
    showModal('Void Sale #' + saleId + '?', 
        '<p><strong>Product:</strong> ' + productName + '</p>' +
        '<p><strong>Quantity:</strong> ' + quantity + '</p>' +
        '<p style="color:#ef4444;font-weight:600;">⚠️ This will restore stock and mark the sale as voided.</p>' +
        '<p>This action cannot be undone!</p>',
        'warning');
    
    var footer = document.getElementById('alertModalFooter');
    footer.innerHTML = '<div style="padding:15px 20px;border-top:1px solid #e5e7eb;display:flex;gap:8px;justify-content:flex-end;">' +
        '<button onclick="closeModal()" style="padding:8px 16px;background:#6b7280;color:white;border:none;border-radius:6px;cursor:pointer;">Cancel</button>' +
        '<button onclick="proceedVoid(' + saleId + ')" style="padding:8px 24px;background:#dc2626;color:white;border:none;border-radius:6px;cursor:pointer;font-weight:600;">Yes, Void Sale</button>' +
    '</div>';
}

function proceedVoid(saleId) {
    closeModal();
    window.location.href = 'sales.php?void=' + saleId;
}

// Close modal on overlay click
document.getElementById('alertModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

// Show success/error messages in modal if present
<?php if ($success): ?>
    document.addEventListener('DOMContentLoaded', function() {
        showModal('Success', '<?php echo addslashes($success); ?>', 'success');
    });
<?php endif; ?>
<?php if (!empty($errors)): ?>
    document.addEventListener('DOMContentLoaded', function() {
        showModal('Error', '<?php echo addslashes(implode('<br>', $errors)); ?>', 'error');
    });
<?php endif; ?>
</script>
</body>
</html>