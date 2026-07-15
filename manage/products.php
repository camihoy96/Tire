<?php
// manage/products.php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';
require_once '../includes/Security.php';

$db = Database::getInstance()->getConnection();
$auth = new Auth();

// Require login to access this page
$auth->requireLogin();

$user = $auth->getUser();
$flash = Security::getFlash();
$errors = [];
$success = '';

// Handle product deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $productId = intval($_GET['delete']);
    
    // FORCE PERMANENT DELETE
    $stmt = $db->prepare("DELETE FROM products WHERE product_id = ?");
    $stmt->bind_param("i", $productId);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $success = "Product permanently deleted!";
        } else {
            $errors[] = "Product not found.";
        }
    } else {
        if ($db->errno == 1451) {
            $db->query("SET FOREIGN_KEY_CHECKS = 0");
            $stmt2 = $db->prepare("DELETE FROM products WHERE product_id = ?");
            $stmt2->bind_param("i", $productId);
            if ($stmt2->execute() && $stmt2->affected_rows > 0) {
                $success = "Product permanently deleted!";
            } else {
                $errors[] = "Cannot delete product.";
            }
            $stmt2->close();
            $db->query("SET FOREIGN_KEY_CHECKS = 1");
        } else {
            $errors[] = "Failed to delete: " . $stmt->error;
        }
    }
    $stmt->close();
    
    if (empty($errors)) {
        Security::setFlash('success', $success);
    } else {
        Security::setFlash('error', implode(', ', $errors));
    }
    header('Location: products.php');
    exit;
}

// Handle product addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Invalid security token";
    } else {
        $name = trim($_POST['name'] ?? '');
        $barcode = trim($_POST['barcode'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $tire_size = trim($_POST['tire_size'] ?? '');
        $vehicle_type = trim($_POST['vehicle_type'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $cost_price = floatval($_POST['cost_price'] ?? 0);
        $quantity = intval($_POST['quantity'] ?? 0);
        $min_quantity = intval($_POST['min_quantity'] ?? 10);
        $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
        $supplier = trim($_POST['supplier'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $manufacturing_date = !empty($_POST['manufacturing_date']) ? $_POST['manufacturing_date'] : null;
        $expiration_date = !empty($_POST['expiration_date']) ? $_POST['expiration_date'] : null;
        $expiry_notification_days = intval($_POST['expiry_notification_days'] ?? 90);
        $batch_number = trim($_POST['batch_number'] ?? '');
        $last_inspection_date = !empty($_POST['last_inspection_date']) ? $_POST['last_inspection_date'] : null;
        $userId = $auth->getUserId();
        
        // Validation
        if (empty($name)) {
            $errors[] = "Product name is required";
        }
        
        if ($price < 0) {
            $errors[] = "Price cannot be negative";
        }
        
        if ($quantity < 0) {
            $errors[] = "Quantity cannot be negative";
        }
        
        if ($manufacturing_date && $expiration_date && $expiration_date <= $manufacturing_date) {
            $errors[] = "Expiration date must be after manufacturing date";
        }
        
        if (!empty($barcode)) {
            $stmt = $db->prepare("SELECT product_id FROM products WHERE barcode = ? AND is_active = 1");
            $stmt->bind_param("s", $barcode);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $errors[] = "A product with this barcode already exists";
            }
            $stmt->close();
        }
        
        if (empty($errors)) {
            // 17 placeholders (without warranty_months, without is_active hardcoded)
            // s s s s s d d i i i s s i s s i s s = sssssddiiissississ
            $stmt = $db->prepare("
                INSERT INTO products (
                    name, barcode, description, tire_size, vehicle_type, 
                    price, cost_price, quantity, min_quantity, 
                    category_id, supplier, location, created_by, created_at,
                    manufacturing_date, expiration_date, expiry_notification_days, 
                    batch_number, last_inspection_date
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?)
            ");
            
            $stmt->bind_param(
                "sssssddiiissississ",
                $name, $barcode, $description, $tire_size, $vehicle_type,
                $price, $cost_price, $quantity, $min_quantity,
                $category_id, $supplier, $location, $userId,
                $manufacturing_date, $expiration_date, $expiry_notification_days,
                $batch_number, $last_inspection_date
            );
            
            if ($stmt->execute()) {
                $success = "Product added successfully!";
            } else {
                $errors[] = "Failed to add product. Error: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Handle product update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Invalid security token";
    } else {
        $product_id = intval($_POST['product_id']);
        $name = trim($_POST['name'] ?? '');
        $barcode = trim($_POST['barcode'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $tire_size = trim($_POST['tire_size'] ?? '');
        $vehicle_type = trim($_POST['vehicle_type'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $cost_price = floatval($_POST['cost_price'] ?? 0);
        $quantity = intval($_POST['quantity'] ?? 0);
        $min_quantity = intval($_POST['min_quantity'] ?? 10);
        $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
        $supplier = trim($_POST['supplier'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $manufacturing_date = !empty($_POST['manufacturing_date']) ? $_POST['manufacturing_date'] : null;
        $expiration_date = !empty($_POST['expiration_date']) ? $_POST['expiration_date'] : null;
        $expiry_notification_days = intval($_POST['expiry_notification_days'] ?? 90);
        $batch_number = trim($_POST['batch_number'] ?? '');
        $last_inspection_date = !empty($_POST['last_inspection_date']) ? $_POST['last_inspection_date'] : null;
        
        if (empty($name)) {
            $errors[] = "Product name is required";
        }
        
        if ($manufacturing_date && $expiration_date && $expiration_date <= $manufacturing_date) {
            $errors[] = "Expiration date must be after manufacturing date";
        }
        
        if (empty($errors)) {
            // 17 SET columns + 1 WHERE = 18 placeholders
            // s s s s s d d i i i s s s s i s s i = sssssddiiissssissi
            $stmt = $db->prepare("
                UPDATE products 
                SET name = ?, barcode = ?, description = ?, tire_size = ?, vehicle_type = ?,
                    price = ?, cost_price = ?, quantity = ?, min_quantity = ?, 
                    category_id = ?, supplier = ?, location = ?,
                    manufacturing_date = ?, expiration_date = ?, expiry_notification_days = ?,
                    batch_number = ?, last_inspection_date = ?
                WHERE product_id = ?
            ");

            $stmt->bind_param(
                "sssssddiiissssissi",
                $name, $barcode, $description, $tire_size, $vehicle_type,
                $price, $cost_price, $quantity, $min_quantity,
                $category_id, $supplier, $location,
                $manufacturing_date, $expiration_date, $expiry_notification_days,
                $batch_number, $last_inspection_date,
                $product_id
            );
            
            if ($stmt->execute()) {
                $success = "Product updated successfully!";
            } else {
                $errors[] = "Failed to update product. Error: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Get all products with search and filter
$search = isset($_GET['search']) ? Security::sanitize($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? intval($_GET['category']) : 0;
$stock_filter = isset($_GET['stock']) ? Security::sanitize($_GET['stock']) : '';

$query = "
    SELECT p.*, c.cname as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.category_id 
    WHERE p.is_active = 1
";

$params = [];
$types = "";

if (!empty($search)) {
    $query .= " AND (p.name LIKE ? OR p.barcode LIKE ? OR p.supplier LIKE ?)";
    $searchParam = "%{$search}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "sss";
}

if ($category_filter > 0) {
    $query .= " AND p.category_id = ?";
    $params[] = $category_filter;
    $types .= "i";
}

if ($stock_filter === 'low') {
    $query .= " AND p.quantity <= p.min_quantity AND p.quantity > 0";
} elseif ($stock_filter === 'out') {
    $query .= " AND p.quantity = 0";
} elseif ($stock_filter === 'in') {
    $query .= " AND p.quantity > p.min_quantity";
}

$query .= " ORDER BY p.name ASC";

$stmt = $db->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$products = $stmt->get_result();

// Get categories for filter and form
$categories = $db->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY cname ASC");

// Helper function to get setting
if (!function_exists('getSystemSetting')) {
    function getSystemSetting($key, $default = null) {
        global $db;
        try {
            $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
            $stmt->bind_param("s", $key);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                return $row['setting_value'];
            }
        } catch (Exception $e) {}
        return $default;
    }
}

// Get settings
$lowStockThreshold = getSystemSetting('low_stock_threshold', 10);
$expiryAlertDays = getSystemSetting('expiry_alert_days', 90);
$currencySymbol = getSystemSetting('currency_symbol', '₱');

// Get all vehicle types for JavaScript
$allVehicleTypes = [];
$vtQuery = $db->query("SELECT * FROM vehicle_types WHERE is_active = 1 ORDER BY name ASC");
if ($vtQuery) {
    while ($vt = $vtQuery->fetch_assoc()) {
        $allVehicleTypes[] = $vt;
    }
}

// Category icons mapping
// Vehicle type icons mapping (from vehicle_types table)
$categoryIcons = [];
$vtIconQuery = $db->query("SELECT name, icon FROM vehicle_types WHERE is_active = 1");
if ($vtIconQuery) {
    while ($vt = $vtIconQuery->fetch_assoc()) {
        $categoryIcons[$vt['name']] = $vt['icon'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stocks - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
<script src="../assets/js/html5-qrcode.min.js"></script>
    <style>
        .form-section { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 15px 20px; margin-bottom: 20px; }
        .form-section-title { font-size: 0.9rem; font-weight: 600; color: #1e293b; margin: 0 0 12px 0; padding-bottom: 8px; border-bottom: 1px solid #e2e8f0; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px; }
        .form-row.three-col { grid-template-columns: 1fr 1fr 1fr; }
        .form-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; padding-top: 20px; border-top: 1px solid #e2e8f0; }
        .product-cell { line-height: 1.4; }
        .product-name-display { display: block; font-size: 0.95rem; color: #1e293b; margin-bottom: 2px; }
        .vehicle-badge { display: inline-block; font-size: 0.75rem; background: #eff6ff; color: #3b82f6; padding: 2px 8px; border-radius: 12px; margin-top: 3px; font-weight: 500; }
        .barcode-text { font-family: 'Courier New', monospace; font-size: 0.8rem; color: #64748b; }
        .price-display { font-weight: 600; color: #1e293b; }
        .stock-count { font-weight: 700; font-size: 1rem; }
        .stock-count.text-danger { color: #ef4444; }
        .stock-count.text-warning { color: #f59e0b; }
        .stock-count.text-success { color: #10b981; }
        .action-group { display: flex; gap: 4px; align-items: center; }
        .table td { vertical-align: middle; }
        
        /* View Modal Styles */
        .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .detail-item { padding: 10px; background: #f8fafc; border-radius: 8px; }
        .detail-label { font-size: 0.75rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px; }
        .detail-value { font-size: 0.95rem; color: #1e293b; font-weight: 500; }
        .detail-full { grid-column: 1 / -1; }
        /* Barcode Scanner Styles */
.btn-scanner-icon {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 6px;
    padding: 3px 8px;
    cursor: pointer;
    font-size: 1rem;
    transition: all 0.2s;
    vertical-align: middle;
    margin-left: 5px;
}

.btn-scanner-icon:hover {
    background: #dbeafe;
    border-color: #93c5fd;
    transform: scale(1.1);
}

.btn-scanner-icon:active {
    transform: scale(0.95);
}

.btn-scanner-icon.scanning {
    background: #fef3c7;
    border-color: #f59e0b;
    animation: pulse 1.5s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.6; }
}

.barcode-input-wrapper {
    position: relative;
}

.scanner-status {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: #fef3c7;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    color: #92400e;
    display: flex;
    align-items: center;
    gap: 6px;
}

.scanner-dot {
    width: 8px;
    height: 8px;
    background: #f59e0b;
    border-radius: 50%;
    animation: blink 1s infinite;
}

@keyframes blink {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.3; }
}

/* Camera Preview Modal */
.camera-preview-container {
    position: relative;
    background: #000;
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 15px;
}

.camera-preview-container video {
    width: 100%;
    max-height: 400px;
    display: block;
}

.camera-overlay {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 70%;
    height: 150px;
    border: 2px solid #10b981;
    border-radius: 10px;
    box-shadow: 0 0 0 1000px rgba(0,0,0,0.3);
}

.camera-controls {
    display: flex;
    gap: 10px;
    justify-content: center;
    margin-top: 15px;
}

.scanner-result {
    background: #f0fdf4;
    border: 1px solid #86efac;
    border-radius: 8px;
    padding: 12px;
    margin-top: 15px;
    text-align: center;
    font-weight: 600;
    color: #166534;
}
/* Scanning animation */
.scan-line {
    position: absolute;
    top: 0;
    left: 10%;
    width: 80%;
    height: 2px;
    background: linear-gradient(90deg, transparent, #10b981, transparent);
    animation: scanAnimation 2s linear infinite;
    box-shadow: 0 0 8px rgba(16, 185, 129, 0.5);
}

@keyframes scanAnimation {
    0% { top: 10%; }
    50% { top: 80%; }
    100% { top: 10%; }
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Enhanced camera overlay */
.camera-overlay {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 70%;
    height: 150px;
    border: 3px solid #10b981;
    border-radius: 15px;
    box-shadow: 0 0 0 1000px rgba(0,0,0,0.4), 
                inset 0 0 20px rgba(16, 185, 129, 0.1);
    animation: borderPulse 2s infinite;
}

@keyframes borderPulse {
    0%, 100% { border-color: #10b981; }
    50% { border-color: #34d399; }
}

/* Corner markers */
.camera-overlay::before,
.camera-overlay::after {
    content: '';
    position: absolute;
    width: 20px;
    height: 20px;
    border-color: #10b981;
    border-style: solid;
}

.camera-overlay::before {
    top: -2px;
    left: -2px;
    border-width: 4px 0 0 4px;
    border-radius: 5px 0 0 0;
}

.camera-overlay::after {
    bottom: -2px;
    right: -2px;
    border-width: 0 4px 4px 0;
    border-radius: 0 0 5px 0;
}
        @media (max-width: 640px) { .form-row, .form-row.three-col { grid-template-columns: 1fr; } .detail-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include '../includes/sidebar.php'; ?>
        <main class="main-content">
            <?php include '../includes/topbar.php'; ?>
            <div class="content-wrapper">
                <div class="breadcrumb">
                    <a href="<?php echo BASE_URL; ?>index.php">Dashboard</a>
                    <span class="separator">/</span>
                    <span>Tire Stocks</span>
                </div>
                
                <div class="page-header">
                    <div class="page-header-content">
                        <div>
                            <h1>📦 Tire Stocks Management</h1>
                            <p>Manage your tire products and stock levels</p>
                        </div>
                        <?php 
                        $currentUser = $auth->getUser();
                        if ($currentUser && ($auth->isAdmin() || ($currentUser['user_type'] ?? '') === 'manager')): ?>
                        <button class="btn btn-primary" onclick="openAddModal()">➕ Add New Tire</button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible">✅ <?php echo htmlspecialchars($success); ?><button class="alert-close" onclick="this.parentElement.remove()">&times;</button></div>
                <?php endif; ?>
                <?php if (!empty($errors)): ?>
                <div class="alert alert-error alert-dismissible">❌ <?php echo implode('<br>', $errors); ?><button class="alert-close" onclick="this.parentElement.remove()">&times;</button></div>
                <?php endif; ?>
                <?php if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible"><?php echo $flash['message']; ?><button class="alert-close" onclick="this.parentElement.remove()">&times;</button></div>
                <?php endif; ?>
                
                <!-- Filters -->
                <div class="card">
                    <div class="card-body">
                        <form method="GET" class="filters-form">
                            <div class="filters-grid" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: end;">
                                <div class="form-group">
                                    <label>Search</label>
                                    <input type="text" name="search" class="form-control" placeholder="Search by name, barcode..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Category</label>
                                    <select name="category" class="form-control">
                                        <option value="0">All Categories</option>
                                        <?php if ($categories && $categories->num_rows > 0): $categories->data_seek(0);
                                            while ($cat = $categories->fetch_assoc()): ?>
                                            <option value="<?php echo $cat['category_id']; ?>" <?php echo $category_filter == $cat['category_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['cname']); ?></option>
                                        <?php endwhile; endif; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Stock Status</label>
                                    <select name="stock" class="form-control">
                                        <option value="">All Stock</option>
                                        <option value="in" <?php echo $stock_filter === 'in' ? 'selected' : ''; ?>>In Stock</option>
                                        <option value="low" <?php echo $stock_filter === 'low' ? 'selected' : ''; ?>>Low Stock</option>
                                        <option value="out" <?php echo $stock_filter === 'out' ? 'selected' : ''; ?>>Out of Stock</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <div style="display: flex; gap: 8px;">
                                        <button type="submit" class="btn btn-primary">🔍 Filter</button>
                                        <a href="products.php" class="btn btn-secondary">🔄 Reset</a>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Products Table -->
                <div class="card">
                    <div class="card-header">
                        <h2>Product List</h2>
                        <span class="badge badge-primary"><?php echo $products->num_rows; ?> Products</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Product</th><th>Size</th><th>Barcode</th><th>Category</th><th>Location</th>
                                        <th>Price</th><th>Stock</th><th>Expiry Date</th><th>Status</th><th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($products && $products->num_rows > 0): 
                                        while ($row = $products->fetch_assoc()): 
                                          $stockStatus = $row['quantity'] == 0 ? 'out' : ($row['quantity'] <= $row['min_quantity'] ? 'low' : 'in');
                                          $daysUntilExpiry = $row['expiration_date'] ? date_diff(date_create($row['expiration_date']), date_create())->days : null;
                                          $criticalDays = floor($expiryAlertDays / 3);
                                          $vIcon = (!empty($row['vehicle_type']) && isset($categoryIcons[$row['vehicle_type']])) ? $categoryIcons[$row['vehicle_type']] : '🛞';
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="product-cell">
                                                <strong class="product-name-display"><?php echo htmlspecialchars($row['name']); ?></strong>
                                                <?php if (!empty($row['vehicle_type'])): ?>
                                                    <br><span class="vehicle-badge"><?php echo $vIcon; ?> <?php echo htmlspecialchars($row['vehicle_type']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td><?php if (!empty($row['tire_size'])): ?><span class="badge badge-info"><?php echo htmlspecialchars($row['tire_size']); ?></span><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
                                        <td><?php if (!empty($row['barcode'])): ?><span class="barcode-text"><?php echo htmlspecialchars($row['barcode']); ?></span><?php else: ?><span class="text-muted">N/A</span><?php endif; ?></td>
                                        <td><span class="badge badge-secondary"><?php echo htmlspecialchars($row['category_name'] ?? 'Uncategorized'); ?></span></td>
                                        <td><?php if (!empty($row['location'])): ?><span class="badge badge-info">📦 <?php echo htmlspecialchars($row['location']); ?></span><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
                                        <td><span class="price-display"><?php echo $currencySymbol; ?><?php echo number_format($row['price'], 2); ?></span></td>
                                        <td><span class="stock-count <?php echo $stockStatus === 'out' ? 'text-danger' : ($stockStatus === 'low' ? 'text-warning' : 'text-success'); ?>"><?php echo $row['quantity']; ?></span></td>
                                        <td>
                                            <?php if ($row['expiration_date']): ?>
                                                <span class="expiry-date <?php echo $row['expiration_date'] < date('Y-m-d') ? 'expired' : ($daysUntilExpiry <= $expiryAlertDays ? 'expiring-soon' : ''); ?>"><?php echo date('M d, Y', strtotime($row['expiration_date'])); ?></span>
                                                <br><small class="<?php echo $row['expiration_date'] < date('Y-m-d') ? 'text-danger' : 'days-left'; ?>">(<?php echo $row['expiration_date'] >= date('Y-m-d') ? $daysUntilExpiry . ' days left' : 'Expired ' . abs($daysUntilExpiry) . ' days ago'; ?>)</small>
                                            <?php else: ?><span class="text-muted">Not set</span><?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($row['expiration_date'] && $row['expiration_date'] < date('Y-m-d')): ?><span class="badge badge-danger">Expired</span>
                                            <?php elseif ($daysUntilExpiry !== null && $daysUntilExpiry <= $criticalDays): ?><span class="badge badge-danger">⚠️ Critical</span>
                                            <?php elseif ($daysUntilExpiry !== null && $daysUntilExpiry <= $expiryAlertDays): ?><span class="badge badge-warning">Expiring Soon</span>
                                            <?php elseif ($row['expiration_date']): ?><span class="badge badge-success">Good</span>
                                            <?php else: ?><span class="badge badge-secondary">Not Tracked</span><?php endif; ?>
                                            <br>
                                            <?php if ($stockStatus === 'out'): ?><span class="badge badge-danger">Out of Stock</span>
                                            <?php elseif ($stockStatus === 'low'): ?><span class="badge badge-warning">Low Stock</span>
                                            <?php else: ?><span class="badge badge-success">In Stock</span><?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-group">
                                                <!-- View Button -->
                                                <button onclick="openViewModal(<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>)" class="btn btn-sm btn-info" title="View Details">👁️</button>
                                                <?php if ($currentUser && ($auth->isAdmin() || ($currentUser['user_type'] ?? '') === 'manager')): ?>
                                                    <button onclick="openEditModal(this)" class="btn btn-sm btn-primary" title="Edit" data-product='<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>'>✏️</button>
                                                    <a href="javascript:void(0)" 
   onclick="confirmDelete(<?php echo $row['product_id']; ?>, '<?php echo htmlspecialchars(addslashes($row['name']), ENT_QUOTES); ?>')" 
   class="btn btn-sm btn-danger" 
   title="Permanently Delete">
    ❌
</a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; else: ?>
                                    <tr><td colspan="10" class="text-center p-4"><div class="empty-state"><div class="empty-icon">📦</div><h3>No products found</h3><p><?php echo empty($search) ? 'Start by adding your first tire product!' : 'No products match your search criteria.'; ?></p></div></td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Add/Edit Product Modal -->
    <div id="productModal" class="modal-overlay" style="display: none;">
        <div class="modal modal-lg">
            <div class="modal-header">
                <h3 id="modalTitle">➕ Add New Tire</h3>
                <button onclick="closeProductModal()" class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="productForm">
                    <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                    <input type="hidden" name="product_id" id="productId">
                    <input type="hidden" name="add_product" id="formAction" value="1">
                    
                    <div class="form-row">
                        <div class="form-group"><label for="name">Product Name *</label><input type="text" id="name" name="name" class="form-control" required placeholder="Enter tire product name"></div>
                        <div class="form-group">
    <label for="barcode">
        Barcode
        <button type="button" id="barcodeScannerBtn" class="btn-scanner-icon" title="Scan barcode with camera">
            📷
        </button>
    </label>
    <div class="barcode-input-wrapper">
        <input type="text" id="barcode" name="barcode" class="form-control" placeholder="Scan or enter barcode">
        <div id="scannerStatus" class="scanner-status" style="display:none;">
            <span class="scanner-dot"></span> Scanning...
        </div>
    </div>
</div>
                    </div>
                    <div class="form-group"><label for="description">Description</label><textarea id="description" name="description" class="form-control" rows="2" placeholder="Brief description"></textarea></div>
                    
                    <div class="form-section">
                        <h4 class="form-section-title">📂 Classification</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="category_id">Category *</label>
                                <select id="category_id" name="category_id" class="form-control" onchange="filterVehicleTypes()">
                                    <option value="">Select Category</option>
                                    <?php if ($categories && $categories->num_rows > 0): $categories->data_seek(0);
                                        while ($cat = $categories->fetch_assoc()): ?>
                                        <option value="<?php echo $cat['category_id']; ?>" data-cname="<?php echo htmlspecialchars($cat['cname']); ?>"><?php echo htmlspecialchars($cat['cname']); ?></option>
                                    <?php endwhile; endif; ?>
                                </select>
                                <small class="form-text">Select category first</small>
                            </div>
                            <div class="form-group">
                                <label for="vehicle_type">Vehicle Type</label>
                                <select id="vehicle_type" name="vehicle_type" class="form-control"><option value="">Select Category First</option></select>
                                <small class="form-text" id="vehicleTypeHelp">Vehicle types appear after selecting category</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h4 class="form-section-title">📏 Tire Specifications</h4>
                        <div class="form-row">
                            <div class="form-group"><label for="tire_size">Tire Size</label><input type="text" id="tire_size" name="tire_size" class="form-control" placeholder="e.g., 195/65R15"><small class="form-text">Enter tire size specification</small></div>
                            <div class="form-group">
                                <label for="location">Storage Location</label>
                                <select id="location" name="location" class="form-control">
                                    <option value="">Select Location</option>
                                    <?php for ($i = 1; $i <= 10; $i++): $cabValue = "Cab {$i}"; ?>
                                        <option value="<?php echo htmlspecialchars($cabValue); ?>">📦 <?php echo htmlspecialchars($cabValue); ?></option>
                                    <?php endfor; ?>
                                </select>
                                <small class="form-text">Select storage cabinet location</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h4 class="form-section-title">💰 Pricing & Stock</h4>
                        <div class="form-row three-col">
                            <div class="form-group"><label for="price">Selling Price (₱) *</label><input type="number" id="price" name="price" class="form-control" step="0.01" required placeholder="0.00"></div>
                            <div class="form-group"><label for="cost_price">Cost Price (₱)</label><input type="number" id="cost_price" name="cost_price" class="form-control" step="0.01" placeholder="0.00"></div>
                            <div class="form-group"><label for="quantity">Quantity *</label><input type="number" id="quantity" name="quantity" class="form-control" min="0" required placeholder="0"></div>
                        </div>
                        <div class="form-row">
                            <div class="form-group"><label for="min_quantity">Min Stock Alert</label><input type="number" id="min_quantity" name="min_quantity" class="form-control" min="0" value="10"><small class="form-text">Alert when stock goes below this</small></div>
                            <div class="form-group"><label for="supplier">Supplier</label><input type="text" id="supplier" name="supplier" class="form-control" placeholder="e.g., Michelin"></div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h4 class="form-section-title">📅 Expiration & Warranty</h4>
                        <div class="form-row">
                            <div class="form-group"><label for="manufacturing_date">Manufacturing Date</label><input type="date" id="manufacturing_date" name="manufacturing_date" class="form-control"></div>
                            <div class="form-group"><label for="expiration_date">Expiration Date</label><input type="date" id="expiration_date" name="expiration_date" class="form-control"></div>
                        </div>
                        <div class="form-row">
                            <div class="form-group"><label for="batch_number">Batch/Lot Number</label><input type="text" id="batch_number" name="batch_number" class="form-control" placeholder="e.g., BATCH-2024-001"></div>
                        </div>
                        <div class="form-row">
                            <div class="form-group"><label for="expiry_notification_days">Notify before expiry</label><select id="expiry_notification_days" name="expiry_notification_days" class="form-control"><option value="30">30 days</option><option value="60">60 days</option><option value="90" selected>90 days</option><option value="120">120 days</option><option value="180">180 days</option></select></div>
                            <div class="form-group"><label for="last_inspection_date">Last Inspection</label><input type="date" id="last_inspection_date" name="last_inspection_date" class="form-control"></div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">💾 Save Product</button>
                        <button type="button" class="btn btn-secondary" onclick="closeProductModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
  <!-- Barcode Scanner Modal -->
<div id="scannerModal" class="modal-overlay" style="display: none;">
    <div class="modal">
        <div class="modal-header" style="background: linear-gradient(135deg, #1e293b, #334155); color: white; border-radius: 10px 10px 0 0;">
            <h3>
                <span style="font-size: 1.5rem;">📷</span> Scan Barcode
                <span id="scanTimer" style="font-size: 0.9rem; margin-left: 15px; opacity: 0.8;"></span>
            </h3>
            <button onclick="stopScanner()" class="modal-close" style="color: white;">&times;</button>
        </div>
        <div class="modal-body">
            <div class="camera-preview-container" style="position: relative;">
                <div id="cameraPreview" style="width: 100%; max-height: 400px; border: 2px solid #334155;"></div>
                <div class="camera-overlay"></div>
                <!-- Scanning animation -->
                <div class="scan-line"></div>
            </div>
            <p class="text-center" style="margin: 10px 0; color: #64748b;">
                <span id="scanHint">Position barcode within the green box</span>
            </p>
            <div class="camera-controls">
                <button type="button" class="btn btn-secondary" onclick="stopScanner()">
                    ❌ Cancel
                </button>
                <button type="button" class="btn btn-primary" id="retryScanner" style="display:none;" onclick="startScanner()">
                    🔄 Retry
                </button>
            </div>
            <div id="scannerResult" class="scanner-result" style="display:none;"></div>
        </div>
    </div>
</div>
    <!-- View Product Details Modal -->
    <div id="viewModal" class="modal-overlay" style="display: none;">
        <div class="modal modal-lg">
            <div class="modal-header">
                <h3>📋 Product Details</h3>
                <button onclick="closeViewModal()" class="modal-close">&times;</button>
            </div>
            <div class="modal-body" id="viewModalBody">
                <!-- Filled by JavaScript -->
            </div>
        </div>
    </div>
    
<script>
var allVehicleTypes = <?php echo json_encode($allVehicleTypes); ?>;
var categoryIcons = <?php echo json_encode($categoryIcons); ?>;
var currencySymbol = '<?php echo $currencySymbol; ?>';

function filterVehicleTypes() {
    var categorySelect = document.getElementById('category_id');
    var vehicleSelect = document.getElementById('vehicle_type');
    var helpText = document.getElementById('vehicleTypeHelp');
    var selectedOption = categorySelect.options[categorySelect.selectedIndex];
    var selectedCategoryName = selectedOption ? selectedOption.getAttribute('data-cname') : '';
    vehicleSelect.innerHTML = '';
    if (!categorySelect.value || categorySelect.value === '') {
        vehicleSelect.innerHTML = '<option value="">Select Category First</option>';
        if (helpText) helpText.textContent = 'Please select a category first';
        vehicleSelect.disabled = true;
        return;
    }
    vehicleSelect.disabled = false;
    var categoryIcon = categoryIcons[selectedCategoryName] || '🛞';
    var filteredTypes = allVehicleTypes.filter(function(vt) { return vt.icon === categoryIcon; });
    if (filteredTypes.length === 0) {
        vehicleSelect.innerHTML = '<option value="">No matching types</option>';
        allVehicleTypes.forEach(function(vt) { vehicleSelect.innerHTML += '<option value="' + escapeHtml(vt.name) + '">' + vt.icon + ' ' + escapeHtml(vt.name) + '</option>'; });
        if (helpText) helpText.textContent = 'No specific types for this category. Showing all.';
    } else {
        vehicleSelect.innerHTML = '<option value="">Select Vehicle Type</option>';
        filteredTypes.forEach(function(vt) { vehicleSelect.innerHTML += '<option value="' + escapeHtml(vt.name) + '">' + vt.icon + ' ' + escapeHtml(vt.name) + '</option>'; });
        if (helpText) helpText.textContent = 'Showing types for: ' + selectedCategoryName;
    }
}

function openAddModal() {
    document.getElementById('modalTitle').textContent = '➕ Add New Tire';
    document.getElementById('formAction').name = 'add_product';
    document.getElementById('formAction').value = '1';
    document.getElementById('productForm').reset();
    document.getElementById('productId').value = '';
    document.getElementById('location').value = '';
    var vehicleSelect = document.getElementById('vehicle_type');
    vehicleSelect.innerHTML = '<option value="">Select Category First</option>';
    vehicleSelect.disabled = true;
    document.getElementById('vehicleTypeHelp').textContent = 'Please select a category first';
    document.getElementById('productModal').style.display = 'flex';
}

function openEditModal(button) {
    var productData = button.getAttribute('data-product');
    if (!productData) return;
    var product;
    try { product = JSON.parse(productData); } catch (e) { return; }
    
    document.getElementById('modalTitle').textContent = '✏️ Edit Product';
    document.getElementById('formAction').name = 'update_product';
    document.getElementById('formAction').value = '1';
    document.getElementById('productId').value = product.product_id || '';
    document.getElementById('name').value = product.name || '';
    document.getElementById('barcode').value = product.barcode || '';
    document.getElementById('description').value = product.description || '';
    document.getElementById('tire_size').value = product.tire_size || '';
    document.getElementById('price').value = product.price || 0;
    document.getElementById('cost_price').value = product.cost_price || 0;
    document.getElementById('quantity').value = product.quantity || 0;
    document.getElementById('min_quantity').value = product.min_quantity || 10;
    document.getElementById('category_id').value = product.category_id || '';
    document.getElementById('supplier').value = product.supplier || '';
    document.getElementById('location').value = product.location || '';
    document.getElementById('manufacturing_date').value = product.manufacturing_date || '';
    document.getElementById('expiration_date').value = product.expiration_date || '';
    document.getElementById('batch_number').value = product.batch_number || '';
    document.getElementById('expiry_notification_days').value = product.expiry_notification_days || 90;
    document.getElementById('last_inspection_date').value = product.last_inspection_date || '';
    
    filterVehicleTypes();
    setTimeout(function() { document.getElementById('vehicle_type').value = product.vehicle_type || ''; }, 100);
    document.getElementById('productModal').style.display = 'flex';
}

function closeProductModal() { document.getElementById('productModal').style.display = 'none'; }

// View Modal Functions
function openViewModal(product) {
    var vIcon = '🛞';
    if (product.vehicle_type && categoryIcons[product.vehicle_type]) {
        vIcon = categoryIcons[product.vehicle_type];
    }
    
    var html = '<div class="detail-grid">';
    html += '<div class="detail-item detail-full"><div class="detail-label">Product Name</div><div class="detail-value">' + escapeHtml(product.name) + '</div></div>';
    
    if (product.description) {
        html += '<div class="detail-item detail-full"><div class="detail-label">Description</div><div class="detail-value">' + escapeHtml(product.description) + '</div></div>';
    }
    
    html += '<div class="detail-item"><div class="detail-label">Barcode</div><div class="detail-value">' + (product.barcode || 'N/A') + '</div></div>';
    html += '<div class="detail-item"><div class="detail-label">Category</div><div class="detail-value">' + escapeHtml(product.category_name || 'Uncategorized') + '</div></div>';
    
    if (product.vehicle_type) {
        html += '<div class="detail-item"><div class="detail-label">Vehicle Type</div><div class="detail-value">' + vIcon + ' ' + escapeHtml(product.vehicle_type) + '</div></div>';
    }
    
    html += '<div class="detail-item"><div class="detail-label">Tire Size</div><div class="detail-value">' + (product.tire_size || '—') + '</div></div>';
    html += '<div class="detail-item"><div class="detail-label">Location</div><div class="detail-value">' + (product.location ? '📦 ' + escapeHtml(product.location) : '—') + '</div></div>';
    html += '<div class="detail-item"><div class="detail-label">Selling Price</div><div class="detail-value">' + currencySymbol + parseFloat(product.price).toFixed(2) + '</div></div>';
    html += '<div class="detail-item"><div class="detail-label">Cost Price</div><div class="detail-value">' + currencySymbol + parseFloat(product.cost_price || 0).toFixed(2) + '</div></div>';
    html += '<div class="detail-item"><div class="detail-label">Quantity</div><div class="detail-value">' + product.quantity + '</div></div>';
    html += '<div class="detail-item"><div class="detail-label">Min Stock Alert</div><div class="detail-value">' + (product.min_quantity || 10) + '</div></div>';
    html += '<div class="detail-item"><div class="detail-label">Supplier</div><div class="detail-value">' + (product.supplier || '—') + '</div></div>';
    html += '<div class="detail-item"><div class="detail-label">Batch Number</div><div class="detail-value">' + (product.batch_number || '—') + '</div></div>';
    
    if (product.manufacturing_date) {
        html += '<div class="detail-item"><div class="detail-label">Manufacturing Date</div><div class="detail-value">' + product.manufacturing_date + '</div></div>';
    }
    if (product.expiration_date) {
        html += '<div class="detail-item"><div class="detail-label">Expiration Date</div><div class="detail-value">' + product.expiration_date + '</div></div>';
    }
    
    html += '<div class="detail-item"><div class="detail-label">Notify Before Expiry</div><div class="detail-value">' + (product.expiry_notification_days || 90) + ' days</div></div>';
    
    if (product.last_inspection_date) {
        html += '<div class="detail-item"><div class="detail-label">Last Inspection</div><div class="detail-value">' + product.last_inspection_date + '</div></div>';
    }
    
    html += '</div>';
    
    document.getElementById('viewModalBody').innerHTML = html;
    document.getElementById('viewModal').style.display = 'flex';
}

function closeViewModal() { document.getElementById('viewModal').style.display = 'none'; }

function showToast(message, type) {
    type = type || 'success';
    var container = document.getElementById('toast-container');
    if (!container) { container = document.createElement('div'); container.id = 'toast-container'; container.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;'; document.body.appendChild(container); }
    var icons = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };
    var toast = document.createElement('div');
    toast.style.cssText = 'background:white;padding:15px 20px;border-radius:10px;margin-bottom:10px;box-shadow:0 5px 20px rgba(0,0,0,0.15);min-width:300px;border-left:4px solid ' + (type==='error'?'#ef4444':'#059669') + ';';
    toast.innerHTML = '<strong>' + (icons[type]||'') + ' ' + (type.charAt(0).toUpperCase()+type.slice(1)) + '</strong><br>' + message;
    container.appendChild(toast);
    setTimeout(function() { toast.style.opacity = '0'; toast.style.transition = 'opacity 0.3s'; setTimeout(function() { toast.remove(); }, 300); }, 4000);
}

// ============================================
// BARCODE SCANNER FUNCTIONS - COMPLETE VERSION
// ============================================
let html5QrCode = null;
let isScanning = false;
let lastScannedCode = '';
let lastScanTime = 0;
let scanTimeout = null;
let scanStartTime = null;

document.addEventListener('DOMContentLoaded', function() {
    // Initialize scanner button click handler
    const scannerBtn = document.getElementById('barcodeScannerBtn');
    if (scannerBtn) {
        scannerBtn.addEventListener('click', function(e) {
            e.preventDefault();
            startScanner();
        });
    }
    
    // Handle scanner modal close on overlay click
    const scannerModal = document.getElementById('scannerModal');
    if (scannerModal) {
        scannerModal.addEventListener('click', function(e) {
            if (e.target === this) {
                stopScanner();
            }
        });
    }
    
    // Close scanner button in modal header
    const closeBtn = document.querySelector('#scannerModal .modal-close');
    if (closeBtn) {
        closeBtn.addEventListener('click', function() {
            stopScanner();
        });
    }
});

// Audio feedback function
function playBeepSound() {
    try {
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();
        
        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);
        
        oscillator.frequency.value = 800;
        oscillator.type = 'sine';
        gainNode.gain.value = 0.3;
        
        oscillator.start();
        gainNode.gain.exponentialRampToValueAtTime(0.001, audioContext.currentTime + 0.2);
        oscillator.stop(audioContext.currentTime + 0.2);
    } catch (e) {
        console.log('Audio not supported');
    }
}

// Scan timer functions
function updateScanTimer() {
    if (!isScanning) return;
    
    const elapsed = Math.floor((Date.now() - scanStartTime) / 1000);
    const timerElement = document.getElementById('scanTimer');
    if (timerElement) {
        const minutes = Math.floor(elapsed / 60);
        const seconds = elapsed % 60;
        timerElement.textContent = `⏱️ ${minutes}:${seconds.toString().padStart(2, '0')}`;
    }
    
    if (elapsed > 30) {
        showScanHint('⚠️ No barcode detected. Auto-closing...');
        setTimeout(() => stopScanner(), 2000);
    } else {
        scanTimeout = setTimeout(updateScanTimer, 1000);
    }
}

function showScanHint(message) {
    const hintElement = document.getElementById('scanHint');
    if (hintElement) {
        hintElement.textContent = message;
        hintElement.style.color = '#f59e0b';
        hintElement.style.fontWeight = '600';
        setTimeout(() => {
            if (hintElement) {
                hintElement.textContent = 'Position barcode within the green box';
                hintElement.style.color = '#64748b';
                hintElement.style.fontWeight = 'normal';
            }
        }, 3000);
    }
}

function startScanner() {
    console.log('Starting scanner...');
    
    const scannerModal = document.getElementById('scannerModal');
    const scannerBtn = document.getElementById('barcodeScannerBtn');
    const scannerStatus = document.getElementById('scannerStatus');
    const scannerResult = document.getElementById('scannerResult');
    const retryBtn = document.getElementById('retryScanner');
    
    // Reset timer
    scanStartTime = Date.now();
    if (scanTimeout) {
        clearTimeout(scanTimeout);
        scanTimeout = null;
    }
    
    // Reset scanner button
    if (scannerBtn) {
        scannerBtn.innerHTML = '📷';
        scannerBtn.classList.add('scanning');
    }
    
    // Reset result
    if (scannerResult) {
        scannerResult.style.display = 'none';
        scannerResult.innerHTML = '';
    }
    
    // Hide retry button
    if (retryBtn) {
        retryBtn.style.display = 'none';
    }
    
    // Reset scan hint
    const scanHint = document.getElementById('scanHint');
    if (scanHint) {
        scanHint.textContent = 'Position barcode within the green box';
        scanHint.style.color = '#64748b';
        scanHint.style.fontWeight = 'normal';
    }
    
    // Reset timer display
    const timerElement = document.getElementById('scanTimer');
    if (timerElement) {
        timerElement.textContent = '';
    }
    
    // Reset camera preview border
    const cameraPreview = document.getElementById('cameraPreview');
    if (cameraPreview) {
        cameraPreview.style.border = '2px solid #334155';
        cameraPreview.style.boxShadow = '';
    }
    
    // Show modal FIRST
    scannerModal.style.display = 'flex';
    isScanning = true;
    
    // Check if html5-qrcode is loaded
    if (typeof Html5Qrcode === 'undefined') {
        console.error('Html5Qrcode not loaded');
        scannerResult.innerHTML = '⚠️ Barcode scanner library not loaded. Please refresh the page.';
        scannerResult.style.display = 'block';
        scannerResult.className = 'scanner-result';
        scannerResult.style.background = '#fef2f2';
        scannerResult.style.border = '1px solid #fecaca';
        scannerResult.style.color = '#991b1b';
        retryBtn.style.display = 'inline-block';
        scannerBtn.classList.remove('scanning');
        isScanning = false;
        return;
    }
    
    // Show scanning status
    if (scannerStatus) {
        scannerStatus.style.display = 'flex';
    }
    
    // Start timer
    updateScanTimer();
    
    // Stop any existing scanner before starting new one
    if (html5QrCode) {
        html5QrCode.stop().then(() => {
            console.log('Previous scanner stopped');
            initNewScanner();
        }).catch(err => {
            console.log('Stop error (ignored):', err);
            // Force clear and create new
            html5QrCode.clear();
            html5QrCode = null;
            initNewScanner();
        });
    } else {
        initNewScanner();
    }
}

function initNewScanner() {
    console.log('Initializing new scanner...');
    
    const scannerResult = document.getElementById('scannerResult');
    const retryBtn = document.getElementById('retryScanner');
    const scannerBtn = document.getElementById('barcodeScannerBtn');
    const scannerStatus = document.getElementById('scannerStatus');

    // Create new instance
    try {
        html5QrCode = new Html5Qrcode("cameraPreview");
    } catch (e) {
        console.error('Failed to create Html5Qrcode:', e);
        scannerResult.innerHTML = '⚠️ Failed to initialize scanner. Please refresh.';
        scannerResult.style.display = 'block';
        scannerResult.style.background = '#fef2f2';
        scannerResult.style.color = '#991b1b';
        scannerBtn.classList.remove('scanning');
        retryBtn.style.display = 'inline-block';
        isScanning = false;
        return;
    }

    const config = {
        fps: 20,
        qrbox: { width: 300, height: 200 },
        aspectRatio: 1.777,
        disableFlip: true,
        formatsToSupport: [
            Html5QrcodeSupportedFormats.CODE_128,
            Html5QrcodeSupportedFormats.CODE_39,
            Html5QrcodeSupportedFormats.EAN_13,
            Html5QrcodeSupportedFormats.EAN_8,
            Html5QrcodeSupportedFormats.UPC_A,
            Html5QrcodeSupportedFormats.UPC_E
        ]
    };

    Html5Qrcode.getCameras()
        .then(devices => {
            console.log('Cameras found:', devices.length);
            
            if (!devices || devices.length === 0) {
                throw new Error("No camera found");
            }

            const rearCamera = devices.find(device =>
                device.label.toLowerCase().includes("back") ||
                device.label.toLowerCase().includes("rear") ||
                device.label.toLowerCase().includes("environment")
            );

            const cameraId = rearCamera ? rearCamera.id : devices[0].id;
            console.log('Using camera:', rearCamera ? rearCamera.label : devices[0].label);

            return html5QrCode.start(
                cameraId,
                config,
                onScanSuccess,
                onScanFailure
            );
        })
        .then(() => {
            console.log("✅ Scanner started successfully");
        })
        .catch(err => {
            console.error("❌ Camera error:", err);

            scannerResult.innerHTML = `
                <div style="padding: 10px;">
                    <div style="font-size: 2rem;">⚠️</div>
                    <strong>Cannot access camera</strong><br><br>
                    <small style="color: #64748b;">
                        Please check:<br>
                        1. Camera is connected<br>
                        2. Browser has camera permission<br>
                        3. Site is running on HTTPS or localhost<br>
                    </small>
                </div>
            `;
            scannerResult.style.display = 'block';
            scannerResult.style.background = '#fef2f2';
            scannerResult.style.border = '1px solid #fecaca';
            scannerResult.style.color = '#991b1b';

            if (scannerStatus) {
                scannerStatus.style.display = 'none';
            }

            scannerBtn.classList.remove('scanning');
            retryBtn.style.display = 'inline-block';
            isScanning = false;
        });
}

function onScanSuccess(decodedText, decodedResult) {
    if (!isScanning) return;
    
    const now = Date.now();
    if (decodedText === lastScannedCode && now - lastScanTime < 3000) {
        return;
    }

    lastScannedCode = decodedText;
    lastScanTime = now;
    isScanning = false;
    
    // Clear timer
    if (scanTimeout) {
        clearTimeout(scanTimeout);
        scanTimeout = null;
    }
    
    console.log('✅ Barcode detected:', decodedText);
    
    const scannerStatus = document.getElementById('scannerStatus');
    const scannerResult = document.getElementById('scannerResult');
    const scannerBtn = document.getElementById('barcodeScannerBtn');
    const barcodeField = document.getElementById('barcode');
    const cameraPreview = document.getElementById('cameraPreview');
    
    // Play success sound
    playBeepSound();
    
    // Set the barcode value
    if (barcodeField) {
        barcodeField.value = decodedText;
        barcodeField.dispatchEvent(new Event('input', { bubbles: true }));
        barcodeField.dispatchEvent(new Event('change', { bubbles: true }));
        console.log('Barcode set:', barcodeField.value);
    }
    
    // Vibrate on mobile
    if (navigator.vibrate) {
        navigator.vibrate([100, 50, 100]);
    }
    
    // Get barcode format
    let format = 'Unknown';
    let formatIcon = '📊';
    if (decodedResult && decodedResult.result && decodedResult.result.format) {
        format = decodedResult.result.format.formatName || 'Unknown';
        switch(format.toLowerCase()) {
            case 'ean_13': formatIcon = '🔢'; break;
            case 'ean_8': formatIcon = '🔢'; break;
            case 'code_128': formatIcon = '📦'; break;
            case 'code_39': formatIcon = '🏷️'; break;
            case 'upc_a': formatIcon = '🛒'; break;
            case 'upc_e': formatIcon = '🛒'; break;
        }
    }
    
    // Camera preview success effect
    if (cameraPreview) {
        cameraPreview.style.transition = 'all 0.3s';
        cameraPreview.style.border = '3px solid #10b981';
        cameraPreview.style.boxShadow = '0 0 20px rgba(16, 185, 129, 0.5)';
        setTimeout(() => {
            cameraPreview.style.border = '2px solid #334155';
            cameraPreview.style.boxShadow = '';
        }, 800);
    }
    
    // Show success result
    scannerResult.innerHTML = `
        <div style="animation: slideIn 0.3s ease-out;">
            <div style="font-size: 2rem; margin-bottom: 10px;">✅</div>
            <div style="font-size: 1.2rem; font-weight: 700; margin-bottom: 5px;">
                Barcode Detected!
            </div>
            <div style="font-size: 1.5rem; font-family: 'Courier New', monospace; 
                 background: #065f46; color: white; padding: 8px 15px; border-radius: 8px; 
                 display: inline-block; margin: 10px 0; letter-spacing: 2px;">
                ${decodedText}
            </div>
            <br>
            <small style="color: #64748b;">${formatIcon} Format: ${format}</small>
        </div>
    `;
    scannerResult.style.display = 'block';
    scannerResult.style.background = 'linear-gradient(135deg, #f0fdf4, #dcfce7)';
    scannerResult.style.border = '2px solid #86efac';
    scannerResult.style.color = '#166534';
    
    // Hide scanning status
    if (scannerStatus) {
        scannerStatus.style.display = 'none';
    }
    
    // Remove scanning class
    if (scannerBtn) {
        scannerBtn.classList.remove('scanning');
        scannerBtn.innerHTML = '✅';
        setTimeout(() => {
            scannerBtn.innerHTML = '📷';
        }, 2000);
    }
    
    // Flash effect on barcode field
    if (barcodeField) {
        barcodeField.style.transition = 'all 0.3s';
        barcodeField.style.background = '#f0fdf4';
        barcodeField.style.borderColor = '#10b981';
        barcodeField.style.boxShadow = '0 0 0 3px rgba(16, 185, 129, 0.3)';
        barcodeField.style.transform = 'scale(1.02)';
        setTimeout(() => {
            barcodeField.style.background = '';
            barcodeField.style.borderColor = '';
            barcodeField.style.boxShadow = '';
            barcodeField.style.transform = '';
        }, 1500);
    }
    
    // Stop scanner and close modal
    if (html5QrCode) {
        html5QrCode.stop().then(() => {
            console.log('Scanner stopped after successful scan');
            setTimeout(() => {
                closeScannerModal();
                if (barcodeField) {
                    barcodeField.focus();
                    barcodeField.select();
                }
            }, 1200);
        }).catch(err => {
            console.error('Stop error:', err);
            setTimeout(() => {
                closeScannerModal();
                if (barcodeField) {
                    barcodeField.focus();
                }
            }, 1200);
        });
    }
}

function onScanFailure(error) {
    // Only log if it's an actual error, not just "no barcode found"
    if (error && typeof error === 'string' && 
        error.indexOf('NotFound') === -1 && 
        error.indexOf('No barcode') === -1) {
        console.warn('Scan error:', error);
    }
}

function stopScanner() {
    console.log('Stopping scanner...');
    
    isScanning = false;
    
    // Clear timer
    if (scanTimeout) {
        clearTimeout(scanTimeout);
        scanTimeout = null;
    }
    
    if (html5QrCode && html5QrCode.isScanning) {
        html5QrCode.stop().then(() => {
            console.log('Scanner stopped');
            cleanupScanner();
        }).catch(err => {
            console.log('Stop error:', err);
            cleanupScanner();
        });
    } else {
        cleanupScanner();
    }
}

function cleanupScanner() {
    const scannerBtn = document.getElementById('barcodeScannerBtn');
    const scannerStatus = document.getElementById('scannerStatus');
    
    if (scannerBtn) {
        scannerBtn.classList.remove('scanning');
        scannerBtn.innerHTML = '📷';
    }
    
    if (scannerStatus) {
        scannerStatus.style.display = 'none';
    }
    
    // Clear html5QrCode instance
    if (html5QrCode) {
        try {
            html5QrCode.clear();
        } catch (e) {
            console.log('Clear error:', e);
        }
        html5QrCode = null;
    }
    
    closeScannerModal();
}

function closeScannerModal() {
    const scannerModal = document.getElementById('scannerModal');
    if (scannerModal) {
        scannerModal.style.display = 'none';
    }
    
    // Reset timer display
    const timerElement = document.getElementById('scanTimer');
    if (timerElement) {
        timerElement.textContent = '';
    }
    
    // Reset scan hint
    const scanHint = document.getElementById('scanHint');
    if (scanHint) {
        scanHint.textContent = 'Position barcode within the green box';
        scanHint.style.color = '#64748b';
        scanHint.style.fontWeight = 'normal';
    }
    
    isScanning = false;
}

// Keyboard shortcut: Ctrl+B to open scanner
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'b') {
        e.preventDefault();
        const productModal = document.getElementById('productModal');
        if (productModal && productModal.style.display === 'flex') {
            startScanner();
        }
    }
    
    if (e.key === 'Escape') {
        const scannerModal = document.getElementById('scannerModal');
        const productModal = document.getElementById('productModal');
        const viewModal = document.getElementById('viewModal');
        
        if (scannerModal && scannerModal.style.display === 'flex') {
            stopScanner();
        }
        if (productModal && productModal.style.display === 'flex') {
            closeProductModal();
        }
        if (viewModal && viewModal.style.display === 'flex') {
            closeViewModal();
        }
    }
});

// Close modals when clicking overlay
window.onclick = function(event) { 
    if (event.target.classList.contains('modal-overlay')) { 
        var scannerModal = document.getElementById('scannerModal');
        if (scannerModal && scannerModal.style.display === 'flex' && event.target === scannerModal) {
            stopScanner();
        } else {
            closeProductModal(); 
            closeViewModal(); 
        }
    } 
};
// Handle escape key for all modals
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const scannerModal = document.getElementById('scannerModal');
        const productModal = document.getElementById('productModal');
        const viewModal = document.getElementById('viewModal');
        
        if (scannerModal && scannerModal.style.display === 'flex') {
            stopScanner();
        }
        if (productModal && productModal.style.display === 'flex') {
            closeProductModal();
        }
        if (viewModal && viewModal.style.display === 'flex') {
            closeViewModal();
        }
    }
});

function confirmDelete(productId, productName) {
    if (confirm('PERMANENTLY DELETE "' + productName + '"?\n\n⚠️ WARNING: This will permanently remove the product from the database!\n\nThis action CANNOT be undone!')) {
        window.location.href = 'products.php?delete=' + productId;
    }
}

function escapeHtml(text) { 
    var div = document.createElement('div'); 
    div.textContent = text; 
    return div.innerHTML; 
}

// Close modals when clicking overlay
window.onclick = function(event) { 
    if (event.target.classList.contains('modal-overlay')) { 
        var scannerModal = document.getElementById('scannerModal');
        if (scannerModal && scannerModal.style.display === 'flex' && event.target === scannerModal) {
            stopScanner();
        } else {
            closeProductModal(); 
            closeViewModal(); 
        }
    } 
};
</script>
</body>
</html>