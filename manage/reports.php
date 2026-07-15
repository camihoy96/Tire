<?php
// manage/reports.php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';
require_once '../includes/Security.php';

$db = Database::getInstance()->getConnection();
$auth = new Auth();
if (!$auth->isLoggedIn() || ($auth->getUser()['user_type'] !== 'admin' && $auth->getUser()['user_type'] !== 'manager')) {
    Security::redirect(BASE_URL . 'login.php');
}

$user = $auth->getUser();

// Get currency symbol
$currencySymbol = '₱';
$currencySetting = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'currency_symbol'");
if ($currencySetting && $currencySetting->num_rows > 0) {
    $currencySymbol = $currencySetting->fetch_assoc()['setting_value'];
}

// Get filter - date range
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// Key Metrics
$totalProducts = $db->query("SELECT COUNT(*) as count FROM products WHERE is_active = 1")->fetch_assoc()['count'];
$outOfStock = $db->query("SELECT COUNT(*) as count FROM products WHERE is_active = 1 AND quantity = 0")->fetch_assoc()['count'];
$lowStock = $db->query("SELECT COUNT(*) as count FROM products WHERE is_active = 1 AND quantity > 0 AND quantity <= min_quantity")->fetch_assoc()['count'];
$totalCategories = $db->query("SELECT COUNT(*) as count FROM categories WHERE is_active = 1")->fetch_assoc()['count'];
$totalVehicleTypes = $db->query("SELECT COUNT(*) as count FROM vehicle_types WHERE is_active = 1")->fetch_assoc()['count'];

// Total sales summary for period
$salesSummary = $db->query("
    SELECT 
        COUNT(*) as total_transactions,
        COALESCE(SUM(quantity), 0) as total_items,
        COALESCE(SUM(total_price), 0) as total_revenue,
        COALESCE(SUM(profit), 0) as total_profit
    FROM sales 
    WHERE sale_date BETWEEN '{$date_from} 00:00:00' AND '{$date_to} 23:59:59'
")->fetch_assoc();

// Top selling products
$topProducts = $db->query("
    SELECT p.name, p.tire_size, p.vehicle_type, c.cname as category_name,
           SUM(s.quantity) as total_sold, 
           SUM(s.total_price) as revenue,
           SUM(s.profit) as profit
    FROM sales s 
    JOIN products p ON s.product_id = p.product_id 
    LEFT JOIN categories c ON p.category_id = c.category_id
    WHERE s.sale_date BETWEEN '{$date_from} 00:00:00' AND '{$date_to} 23:59:59'
    GROUP BY s.product_id 
    ORDER BY total_sold DESC 
    LIMIT 10
");

// Monthly sales
$monthlySales = $db->query("
    SELECT DATE_FORMAT(sale_date, '%Y-%m') as month, 
           COUNT(*) as transactions,
           SUM(quantity) as items,
           SUM(total_price) as revenue,
           SUM(profit) as profit
    FROM sales 
    GROUP BY month 
    ORDER BY month DESC 
    LIMIT 12
");

// Sales by category
$salesByCategory = $db->query("
    SELECT c.cname, 
           COUNT(DISTINCT s.sale_id) as transactions,
           SUM(s.quantity) as items_sold,
           SUM(s.total_price) as revenue,
           SUM(s.profit) as profit
    FROM categories c 
    LEFT JOIN products p ON c.category_id = p.category_id AND p.is_active = 1
    LEFT JOIN sales s ON p.product_id = s.product_id AND s.sale_date BETWEEN '{$date_from} 00:00:00' AND '{$date_to} 23:59:59'
    WHERE c.is_active = 1 
    GROUP BY c.category_id 
    ORDER BY revenue DESC
");

// Sales by vehicle type
$salesByVehicle = $db->query("
    SELECT p.vehicle_type,
           COUNT(DISTINCT s.sale_id) as transactions,
           SUM(s.quantity) as items_sold,
           SUM(s.total_price) as revenue,
           SUM(s.profit) as profit
    FROM products p 
    LEFT JOIN sales s ON p.product_id = s.product_id AND s.sale_date BETWEEN '{$date_from} 00:00:00' AND '{$date_to} 23:59:59'
    WHERE p.vehicle_type IS NOT NULL AND p.vehicle_type != '' AND p.is_active = 1
    GROUP BY p.vehicle_type 
    ORDER BY revenue DESC
");

// Inventory value by category
$categoryValue = $db->query("
    SELECT c.cname, 
           COUNT(p.product_id) as products,
           SUM(p.quantity) as stock,
           SUM(p.price * p.quantity) as value,
           SUM(p.cost_price * p.quantity) as cost_value
    FROM categories c 
    LEFT JOIN products p ON c.category_id = p.category_id AND p.is_active = 1
    WHERE c.is_active = 1 
    GROUP BY c.category_id 
    ORDER BY value DESC
");

// Today's quick stats
$todayStart = date('Y-m-d 00:00:00');
$todayEnd = date('Y-m-d 23:59:59');
$todaySales = $db->query("SELECT COUNT(*) as count, COALESCE(SUM(total_price), 0) as total, COALESCE(SUM(profit), 0) as profit FROM sales WHERE sale_date BETWEEN '{$todayStart}' AND '{$todayEnd}'")->fetch_assoc();

// Expiring soon products
$expiringProducts = $db->query("
    SELECT name, expiration_date, quantity, 
           DATEDIFF(expiration_date, CURDATE()) as days_left
    FROM products 
    WHERE is_active = 1 
    AND expiration_date IS NOT NULL 
    AND expiration_date >= CURDATE()
    AND DATEDIFF(expiration_date, CURDATE()) <= 90
    ORDER BY days_left ASC 
    LIMIT 10
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .reports-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .reports-grid .full-width { grid-column: 1 / -1; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-bottom: 20px; }
        .stat-card { background: white; border-radius: 10px; padding: 15px 18px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .stat-value { font-size: 1.4rem; font-weight: 700; }
        .stat-label { font-size: 0.7rem; color: #64748b; text-transform: uppercase; }
        .stat-icon { font-size: 1.8rem; }
        .stat-primary { border-left: 4px solid #3b82f6; }
        .stat-success { border-left: 4px solid #10b981; }
        .stat-warning { border-left: 4px solid #f59e0b; }
        .stat-danger { border-left: 4px solid #ef4444; }
        .stat-info { border-left: 4px solid #06b6d4; }
        
        .table { font-size: 0.85rem; }
        .table td { vertical-align: middle; padding: 8px 10px; }
        .text-success { color: #10b981; font-weight: 600; }
        .text-danger { color: #ef4444; font-weight: 600; }
        .text-warning { color: #f59e0b; font-weight: 600; }
        .text-muted { color: #94a3b8; }
        
        .filter-bar { display: flex; gap: 12px; align-items: end; margin-bottom: 20px; flex-wrap: wrap; }
        .filter-bar .form-group { min-width: 150px; }
        
        .expiry-warning { background: #fffbeb; border-left: 3px solid #f59e0b; padding: 8px 12px; margin-bottom: 4px; border-radius: 4px; }
        .expiry-critical { background: #fef2f2; border-left: 3px solid #ef4444; padding: 8px 12px; margin-bottom: 4px; border-radius: 4px; }
        
        .badge-cat { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 0.7rem; background: #f1f5f9; color: #475569; }
        
        @media (max-width: 768px) {
            .reports-grid { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .filter-bar { flex-direction: column; }
        }
        
        @media print {
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <?php include '../includes/topbar.php'; ?>
            
            <div class="content-wrapper">
                <div class="breadcrumb no-print">
                    <a href="<?php echo BASE_URL; ?>index.php">Dashboard</a>
                    <span class="separator">/</span>
                    <span>Reports</span>
                </div>
                
                <div class="page-header no-print">
                    <div class="page-header-content">
                        <div>
                            <h1>📈 Reports & Analytics</h1>
                            <p>Comprehensive overview of your tire business</p>
                        </div>
                        <div style="display:flex;gap:8px;">
                            <button onclick="window.print()" class="btn btn-secondary">🖨️ Print</button>
                        </div>
                    </div>
                </div>
                
                <!-- Date Filter -->
                <div class="card no-print">
                    <div class="card-body">
                        <form method="GET" class="filter-bar">
                            <div class="form-group"><label>Date From</label><input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>"></div>
                            <div class="form-group"><label>Date To</label><input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>"></div>
                            <div class="form-group"><label>&nbsp;</label><button type="submit" class="btn btn-primary">🔍 Apply Filter</button></div>
                        </form>
                    </div>
                </div>
                
                <!-- Today's Quick Stats -->
                <div class="card" style="margin-bottom: 15px; background: linear-gradient(135deg, #eff6ff, #f0fdf4);">
                    <div class="card-body" style="display: flex; justify-content: space-around; text-align: center; flex-wrap: wrap; gap: 10px; padding: 15px;">
                        <div><div style="font-size:0.7rem;color:#64748b;">TODAY'S SALES</div><div style="font-size:1.3rem;font-weight:700;"><?php echo number_format($todaySales['count']); ?></div></div>
                        <div><div style="font-size:0.7rem;color:#64748b;">TODAY'S REVENUE</div><div style="font-size:1.3rem;font-weight:700;color:#059669;"><?php echo $currencySymbol; ?><?php echo number_format($todaySales['total'] ?? 0, 2); ?></div></div>
                        <div><div style="font-size:0.7rem;color:#64748b;">TODAY'S PROFIT</div><div style="font-size:1.3rem;font-weight:700;color:#f59e0b;"><?php echo $currencySymbol; ?><?php echo number_format($todaySales['profit'] ?? 0, 2); ?></div></div>
                    </div>
                </div>
                
                <!-- Key Metrics -->
                <div class="stats-grid">
                    <div class="stat-card stat-primary">
                        <div><div class="stat-value"><?php echo $totalProducts; ?></div><div class="stat-label">Total Products</div></div>
                        <div class="stat-icon">📦</div>
                    </div>
                    <div class="stat-card stat-info">
                        <div><div class="stat-value"><?php echo $totalCategories; ?></div><div class="stat-label">Categories</div></div>
                        <div class="stat-icon">📂</div>
                    </div>
                    <div class="stat-card stat-info">
                        <div><div class="stat-value"><?php echo $totalVehicleTypes; ?></div><div class="stat-label">Vehicle Types</div></div>
                        <div class="stat-icon">🚗</div>
                    </div>
                    <div class="stat-card stat-danger">
                        <div><div class="stat-value"><?php echo $outOfStock; ?></div><div class="stat-label">Out of Stock</div></div>
                        <div class="stat-icon">❌</div>
                    </div>
                    <div class="stat-card stat-warning">
                        <div><div class="stat-value"><?php echo $lowStock; ?></div><div class="stat-label">Low Stock</div></div>
                        <div class="stat-icon">⚠️</div>
                    </div>
                </div>
                
                <!-- Period Sales Summary -->
                <div class="stats-grid">
                    <div class="stat-card stat-success">
                        <div><div class="stat-value"><?php echo number_format($salesSummary['total_transactions'] ?? 0); ?></div><div class="stat-label">Transactions (<?php echo date('M d', strtotime($date_from)); ?> - <?php echo date('M d', strtotime($date_to)); ?>)</div></div>
                        <div class="stat-icon">📊</div>
                    </div>
                    <div class="stat-card stat-primary">
                        <div><div class="stat-value"><?php echo number_format($salesSummary['total_items'] ?? 0); ?></div><div class="stat-label">Items Sold</div></div>
                        <div class="stat-icon">📦</div>
                    </div>
                    <div class="stat-card stat-success">
                        <div><div class="stat-value"><?php echo $currencySymbol; ?><?php echo number_format($salesSummary['total_revenue'] ?? 0, 2); ?></div><div class="stat-label">Revenue</div></div>
                        <div class="stat-icon">💵</div>
                    </div>
                    <div class="stat-card stat-warning">
                        <div><div class="stat-value"><?php echo $currencySymbol; ?><?php echo number_format($salesSummary['total_profit'] ?? 0, 2); ?></div><div class="stat-label">Profit</div></div>
                        <div class="stat-icon">📈</div>
                    </div>
                </div>
                
                <div class="reports-grid">
                    <!-- Top Selling Products -->
                    <div class="card">
                        <div class="card-header"><h2>🏆 Top Selling Products</h2></div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr><th>Product</th><th>Category</th><th>Vehicle</th><th>Sold</th><th>Revenue</th><th>Profit</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($topProducts && $topProducts->num_rows > 0): 
                                            while ($row = $topProducts->fetch_assoc()): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($row['name']); ?></strong><br><small class="text-muted"><?php echo htmlspecialchars($row['tire_size'] ?? ''); ?></small></td>
                                            <td><span class="badge-cat"><?php echo htmlspecialchars($row['category_name'] ?? '—'); ?></span></td>
                                            <td><?php echo htmlspecialchars($row['vehicle_type'] ?? '—'); ?></td>
                                            <td><strong><?php echo $row['total_sold']; ?></strong></td>
                                            <td><?php echo $currencySymbol; ?><?php echo number_format($row['revenue'], 2); ?></td>
                                            <td><span class="<?php echo $row['profit'] >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo $currencySymbol; ?><?php echo number_format($row['profit'], 2); ?></span></td>
                                        </tr>
                                        <?php endwhile; else: ?>
                                        <tr><td colspan="6" class="text-center text-muted">No sales data</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Monthly Sales -->
                    <div class="card">
                        <div class="card-header"><h2>📅 Monthly Sales</h2></div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr><th>Month</th><th>Sales</th><th>Items</th><th>Revenue</th><th>Profit</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($monthlySales && $monthlySales->num_rows > 0): 
                                            while ($row = $monthlySales->fetch_assoc()): ?>
                                        <tr>
                                            <td><strong><?php echo date('F Y', strtotime($row['month'] . '-01')); ?></strong></td>
                                            <td><?php echo $row['transactions']; ?></td>
                                            <td><?php echo $row['items']; ?></td>
                                            <td><?php echo $currencySymbol; ?><?php echo number_format($row['revenue'], 2); ?></td>
                                            <td><span class="<?php echo $row['profit'] >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo $currencySymbol; ?><?php echo number_format($row['profit'], 2); ?></span></td>
                                        </tr>
                                        <?php endwhile; else: ?>
                                        <tr><td colspan="5" class="text-center text-muted">No sales data</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sales by Category -->
                    <div class="card">
                        <div class="card-header"><h2>📂 Sales by Category</h2></div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr><th>Category</th><th>Sales</th><th>Items</th><th>Revenue</th><th>Profit</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($salesByCategory && $salesByCategory->num_rows > 0): 
                                            while ($row = $salesByCategory->fetch_assoc()): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($row['cname']); ?></strong></td>
                                            <td><?php echo $row['transactions'] ?? 0; ?></td>
                                            <td><?php echo $row['items_sold'] ?? 0; ?></td>
                                            <td><?php echo $currencySymbol; ?><?php echo number_format($row['revenue'] ?? 0, 2); ?></td>
                                            <td><span class="<?php echo ($row['profit'] ?? 0) >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo $currencySymbol; ?><?php echo number_format($row['profit'] ?? 0, 2); ?></span></td>
                                        </tr>
                                        <?php endwhile; else: ?>
                                        <tr><td colspan="5" class="text-center text-muted">No data</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sales by Vehicle Type -->
                    <div class="card">
                        <div class="card-header"><h2>🚗 Sales by Vehicle Type</h2></div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr><th>Vehicle Type</th><th>Sales</th><th>Items</th><th>Revenue</th><th>Profit</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($salesByVehicle && $salesByVehicle->num_rows > 0): 
                                            while ($row = $salesByVehicle->fetch_assoc()): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($row['vehicle_type']); ?></strong></td>
                                            <td><?php echo $row['transactions'] ?? 0; ?></td>
                                            <td><?php echo $row['items_sold'] ?? 0; ?></td>
                                            <td><?php echo $currencySymbol; ?><?php echo number_format($row['revenue'] ?? 0, 2); ?></td>
                                            <td><span class="<?php echo ($row['profit'] ?? 0) >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo $currencySymbol; ?><?php echo number_format($row['profit'] ?? 0, 2); ?></span></td>
                                        </tr>
                                        <?php endwhile; else: ?>
                                        <tr><td colspan="5" class="text-center text-muted">No data</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Inventory Value by Category -->
                <div class="card" style="margin-bottom:20px;">
                    <div class="card-header"><h2>💎 Inventory Value by Category</h2></div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr><th>Category</th><th>Products</th><th>Stock</th><th>Est. Cost Value</th><th>Est. Retail Value</th></tr>
                                </thead>
                                <tbody>
                                    <?php if ($categoryValue && $categoryValue->num_rows > 0): 
                                        while ($row = $categoryValue->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($row['cname']); ?></strong></td>
                                        <td><?php echo $row['products']; ?></td>
                                        <td><?php echo $row['stock'] ?? 0; ?></td>
                                        <td><?php echo $currencySymbol; ?><?php echo number_format($row['cost_value'] ?? 0, 2); ?></td>
                                        <td><strong><?php echo $currencySymbol; ?><?php echo number_format($row['value'] ?? 0, 2); ?></strong></td>
                                    </tr>
                                    <?php endwhile; else: ?>
                                    <tr><td colspan="5" class="text-center text-muted">No data</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Expiring Soon -->
                <?php if ($expiringProducts && $expiringProducts->num_rows > 0): ?>
                <div class="card">
                    <div class="card-header"><h2>⏰ Expiring Soon (within 90 days)</h2></div>
                    <div class="card-body">
                        <?php while ($row = $expiringProducts->fetch_assoc()): 
                            $daysLeft = $row['days_left'];
                            $class = $daysLeft <= 30 ? 'expiry-critical' : 'expiry-warning';
                        ?>
                        <div class="<?php echo $class; ?>">
                            <strong><?php echo htmlspecialchars($row['name']); ?></strong> - 
                            Expires: <?php echo date('M d, Y', strtotime($row['expiration_date'])); ?> 
                            (<?php echo $daysLeft; ?> days left) | 
                            Stock: <?php echo $row['quantity']; ?>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>