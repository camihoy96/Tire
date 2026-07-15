<?php
// index.php
require_once 'config.php';
require_once 'includes/Database.php';
require_once 'includes/Auth.php';
require_once 'includes/Security.php';

$db = Database::getInstance()->getConnection();
$auth = new Auth();

// Check if setup is needed
$checkAdmin = $db->query("SELECT COUNT(*) as count FROM users WHERE user_type = 'admin' AND is_active = 1");
$setupNeeded = ($checkAdmin && $checkAdmin->fetch_assoc()['count'] == 0);

// Get stats
$totalProducts = 0;
$lowStock = 0;
$outOfStock = 0;
$todaySalesCount = 0;
$todayRevenue = 0;
$todayProfit = 0;
$totalValue = 0;
$totalCategories = 0;
$totalVehicleTypes = 0;

// Get settings from database
$currencySymbol = getSystemSetting('currency_symbol', '₱');
$lowStockThreshold = getSystemSetting('low_stock_threshold', 10);

if (!$setupNeeded) {
    $totalProducts = $db->query("SELECT COUNT(*) as count FROM products WHERE is_active = 1")->fetch_assoc()['count'];
    $lowStock = $db->query("SELECT COUNT(*) as count FROM products WHERE is_active = 1 AND quantity > 0 AND quantity <= min_quantity")->fetch_assoc()['count'];
    $outOfStock = $db->query("SELECT COUNT(*) as count FROM products WHERE is_active = 1 AND quantity = 0")->fetch_assoc()['count'];
    $totalCategories = $db->query("SELECT COUNT(*) as count FROM categories WHERE is_active = 1")->fetch_assoc()['count'];
    $totalVehicleTypes = $db->query("SELECT COUNT(*) as count FROM vehicle_types WHERE is_active = 1")->fetch_assoc()['count'];
    
    // Today's sales stats
    $todayStart = date('Y-m-d 00:00:00');
    $todayEnd = date('Y-m-d 23:59:59');
    $todayStats = $db->query("SELECT COUNT(*) as count, COALESCE(SUM(total_price), 0) as revenue, COALESCE(SUM(profit), 0) as profit, COALESCE(SUM(quantity), 0) as items FROM sales WHERE sale_date BETWEEN '{$todayStart}' AND '{$todayEnd}'")->fetch_assoc();
    $todaySalesCount = $todayStats['count'];
    $todayRevenue = $todayStats['revenue'];
    $todayProfit = $todayStats['profit'];
    
    // Inventory value
    $valueResult = $db->query("SELECT COALESCE(SUM(price * quantity), 0) as total FROM products WHERE is_active = 1");
    $totalValue = $valueResult->fetch_assoc()['total'];
}

// Get recent products
$products = $db->query("
    SELECT p.*, c.cname as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.category_id 
    WHERE p.is_active = 1 
    ORDER BY p.updated_at DESC 
    LIMIT 10
");

// Get expiring soon products
$expiringProducts = $db->query("
    SELECT name, expiration_date, quantity, 
           DATEDIFF(expiration_date, CURDATE()) as days_left
    FROM products 
    WHERE is_active = 1 
    AND expiration_date IS NOT NULL 
    AND expiration_date >= CURDATE()
    AND DATEDIFF(expiration_date, CURDATE()) <= 90
    ORDER BY days_left ASC 
    LIMIT 5
");

// Get top selling products this month
$topProducts = $db->query("
    SELECT p.name, SUM(s.quantity) as total_sold, SUM(s.total_price) as revenue
    FROM sales s 
    JOIN products p ON s.product_id = p.product_id 
    WHERE MONTH(s.sale_date) = MONTH(CURDATE()) AND YEAR(s.sale_date) = YEAR(CURDATE())
    GROUP BY s.product_id 
    ORDER BY total_sold DESC 
    LIMIT 5
");

// Flash message
$flash = Security::getFlash();

// Handle setup POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_admin']) && $setupNeeded) {
    $fullname = Security::sanitize($_POST['fullname']);
    $username = Security::sanitize($_POST['username']);
    $email = Security::sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = [];
    
    if (empty($fullname) || empty($username) || empty($email) || empty($password)) {
        $errors[] = "All fields are required";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    $check = $db->query("SELECT user_id FROM users WHERE username = '" . $db->real_escape_string($username) . "' OR email = '" . $db->real_escape_string($email) . "'");
    if ($check && $check->num_rows > 0) {
        $errors[] = "Username or email already exists";
    }
    
    if (empty($errors)) {
        $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $db->prepare(
            "INSERT INTO users (fullname, username, email, password_hash, user_type, is_active, created_at) 
             VALUES (?, ?, ?, ?, 'admin', 1, NOW())"
        );
        $stmt->bind_param("ssss", $fullname, $username, $email, $hashed);
        
        if ($stmt->execute()) {
            Security::setFlash('success', 'Admin account created successfully! Please login.');
            Security::redirect(BASE_URL . 'login.php');
        } else {
            $errors[] = "Database error. Please try again.";
        }
    }
    
    if (!empty($errors)) {
        $flash = ['type' => 'error', 'message' => implode('<br>', $errors)];
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo getCompanyName(); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .dashboard-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .quick-actions { margin-bottom: 20px; }
        .actions-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; }
        .action-card { display: flex; align-items: center; gap: 10px; padding: 15px; background: white; border-radius: 10px; text-decoration: none; color: #1e293b; box-shadow: 0 1px 3px rgba(0,0,0,0.1); transition: all 0.2s; }
        .action-card:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .action-icon { font-size: 1.5rem; }
        .action-text { font-weight: 500; font-size: 0.9rem; }
        
        .expiry-alert { padding: 10px 15px; border-radius: 8px; margin-bottom: 8px; font-size: 0.85rem; }
        .expiry-critical { background: #fef2f2; border-left: 3px solid #ef4444; }
        .expiry-warning { background: #fffbeb; border-left: 3px solid #f59e0b; }
        
        .stat-card { background: white; border-radius: 10px; padding: 15px 18px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .stat-value { font-size: 1.4rem; font-weight: 700; }
        .stat-label { font-size: 0.7rem; color: #64748b; text-transform: uppercase; }
        .stat-icon { font-size: 1.8rem; }
        .stat-primary { border-left: 4px solid #3b82f6; }
        .stat-success { border-left: 4px solid #10b981; }
        .stat-warning { border-left: 4px solid #f59e0b; }
        .stat-danger { border-left: 4px solid #ef4444; }
        .stat-info { border-left: 4px solid #06b6d4; }
        
        @media (max-width: 768px) {
            .dashboard-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <?php include 'includes/topbar.php'; ?>
            
            <div class="content-wrapper">
                <?php if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible">
                    <?php echo $flash['message']; ?>
                    <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
                </div>
                <?php endif; ?>
                
                <?php if ($setupNeeded): ?>
                <!-- Setup Wizard -->
                <div class="setup-container">
                    <div class="setup-card">
                        <div class="setup-header">
                            <h1>🚀 Welcome to <?php echo APP_NAME; ?></h1>
                            <p>Your Complete Tire Inventory Management System</p>
                        </div>
                        
                        <div class="setup-body">
                            <div class="features-grid">
                                <div class="feature-item">
                                    <span class="feature-icon">📦</span>
                                    <h3>Inventory Management</h3>
                                    <p>Track tire stock levels, set reorder points, and manage multiple locations</p>
                                </div>
                                <div class="feature-item">
                                    <span class="feature-icon">💰</span>
                                    <h3>Sales Tracking</h3>
                                    <p>Record sales, calculate profits, and generate detailed reports</p>
                                </div>
                                <div class="feature-item">
                                    <span class="feature-icon">📊</span>
                                    <h3>Analytics Dashboard</h3>
                                    <p>Real-time insights into your tire business performance</p>
                                </div>
                            </div>
                            
                            <form method="POST" class="setup-form" id="setupForm">
                                <input type="hidden" name="setup_admin" value="1">
                                <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                                
                                <h3>Create Your Admin Account</h3>
                                
                                <div class="form-group">
                                    <label for="fullname">Full Name</label>
                                    <input type="text" id="fullname" name="fullname" class="form-control" placeholder="Enter your full name" required>
                                </div>
                                <div class="form-group">
                                    <label for="username">Username</label>
                                    <input type="text" id="username" name="username" class="form-control" placeholder="Choose a username" required>
                                </div>
                                <div class="form-group">
                                    <label for="email">Email Address</label>
                                    <input type="email" id="email" name="email" class="form-control" placeholder="Enter your email" required>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="password">Password</label>
                                        <input type="password" id="password" name="password" class="form-control" placeholder="Minimum 8 characters" required minlength="8">
                                    </div>
                                    <div class="form-group">
                                        <label for="confirm_password">Confirm Password</label>
                                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Confirm your password" required>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary btn-lg btn-block">🚀 Setup Admin Account</button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <?php else: ?>
                
                <!-- Dashboard Content -->
                <div class="dashboard">
                    <div class="page-header">
                        <h1>📊 Dashboard</h1>
                        <p>Welcome back, <?php 
                            $currentUser = $auth->getUser();
                            echo ($auth->isLoggedIn() && $currentUser) ? htmlspecialchars($currentUser['fullname']) : 'Guest'; 
                        ?></p>
                    </div>
                    
                    <!-- Stats Cards -->
                    <div class="stats-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:20px;">
                        <div class="stat-card stat-primary">
                            <div><div class="stat-value"><?php echo $totalProducts; ?></div><div class="stat-label">Total Products</div></div>
                            <div class="stat-icon">📦</div>
                        </div>
                        <div class="stat-card stat-warning">
                            <div><div class="stat-value"><?php echo $lowStock; ?></div><div class="stat-label">Low Stock</div></div>
                            <div class="stat-icon">⚠️</div>
                        </div>
                        <div class="stat-card stat-danger">
                            <div><div class="stat-value"><?php echo $outOfStock; ?></div><div class="stat-label">Out of Stock</div></div>
                            <div class="stat-icon">❌</div>
                        </div>
                        <div class="stat-card stat-info">
                            <div><div class="stat-value"><?php echo $totalCategories; ?></div><div class="stat-label">Categories</div></div>
                            <div class="stat-icon">📂</div>
                        </div>
                        <div class="stat-card stat-info">
                            <div><div class="stat-value"><?php echo $totalVehicleTypes; ?></div><div class="stat-label">Vehicle Types</div></div>
                            <div class="stat-icon">🚗</div>
                        </div>
                        <div class="stat-card stat-success">
                            <div><div class="stat-value"><?php echo $currencySymbol; ?><?php echo number_format($totalValue, 2); ?></div><div class="stat-label">Inventory Value</div></div>
                            <div class="stat-icon">📈</div>
                        </div>
                    </div>
                    
                    <!-- Today's Summary -->
                    <div class="card" style="margin-bottom:20px;background:linear-gradient(135deg,#eff6ff,#f0fdf4);">
                        <div class="card-body" style="display:flex;justify-content:space-around;text-align:center;flex-wrap:wrap;gap:10px;padding:15px;">
                            <div><div style="font-size:0.7rem;color:#64748b;">TODAY'S SALES</div><div style="font-size:1.3rem;font-weight:700;"><?php echo number_format($todaySalesCount); ?></div></div>
                            <div><div style="font-size:0.7rem;color:#64748b;">TODAY'S REVENUE</div><div style="font-size:1.3rem;font-weight:700;color:#059669;"><?php echo $currencySymbol; ?><?php echo number_format($todayRevenue, 2); ?></div></div>
                            <div><div style="font-size:0.7rem;color:#64748b;">TODAY'S PROFIT</div><div style="font-size:1.3rem;font-weight:700;color:#f59e0b;"><?php echo $currencySymbol; ?><?php echo number_format($todayProfit, 2); ?></div></div>
                        </div>
                    </div>
                    
                    <div class="dashboard-row">
                        <!-- Expiring Soon -->
                        <div class="card">
                            <div class="card-header"><h2>⏰ Expiring Soon</h2></div>
                            <div class="card-body">
                                <?php if ($expiringProducts && $expiringProducts->num_rows > 0): 
                                    while ($row = $expiringProducts->fetch_assoc()): 
                                        $daysLeft = $row['days_left'];
                                        $class = $daysLeft <= 30 ? 'expiry-critical' : 'expiry-warning';
                                ?>
                                    <div class="expiry-alert <?php echo $class; ?>">
                                        <strong><?php echo htmlspecialchars($row['name']); ?></strong><br>
                                        Expires: <?php echo date('M d, Y', strtotime($row['expiration_date'])); ?> 
                                        (<?php echo $daysLeft; ?> days) | Stock: <?php echo $row['quantity']; ?>
                                    </div>
                                <?php endwhile; else: ?>
                                    <p class="text-muted">No products expiring soon. ✅</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Top Selling -->
                        <div class="card">
                            <div class="card-header"><h2>🏆 Top Sellers This Month</h2></div>
                            <div class="card-body">
                                <?php if ($topProducts && $topProducts->num_rows > 0): ?>
                                    <table class="table" style="font-size:0.85rem;">
                                        <thead><tr><th>Product</th><th>Sold</th><th>Revenue</th></tr></thead>
                                        <tbody>
                                        <?php while ($row = $topProducts->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                                <td><strong><?php echo $row['total_sold']; ?></strong></td>
                                                <td><?php echo $currencySymbol; ?><?php echo number_format($row['revenue'], 2); ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                <?php else: ?>
                                    <p class="text-muted">No sales this month yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <?php 
                    $currentUser = $auth->getUser();
                    $isManagerOrAdmin = ($currentUser && ($auth->isAdmin() || ($currentUser['user_type'] ?? '') === 'manager'));
                    if ($isManagerOrAdmin): 
                    ?>
                    <div class="quick-actions">
                        <h2>Quick Actions</h2>
                        <div class="actions-grid">
                            <a href="manage/pos.php" class="action-card">
                                <span class="action-icon">💳</span>
                                <span class="action-text">Point of Sale</span>
                            </a>
                            <a href="manage/products.php" class="action-card">
                                <span class="action-icon">➕</span>
                                <span class="action-text">Add New Tire</span>
                            </a>
                            <a href="manage/categories.php" class="action-card">
                                <span class="action-icon">📂</span>
                                <span class="action-text">Categories</span>
                            </a>
                            <a href="manage/vehicle_types.php" class="action-card">
                                <span class="action-icon">🚗</span>
                                <span class="action-text">Vehicle Types</span>
                            </a>
                            <a href="manage/sales.php" class="action-card">
                                <span class="action-icon">💰</span>
                                <span class="action-text">Sales Records</span>
                            </a>
                            <a href="manage/reports.php" class="action-card">
                                <span class="action-icon">📊</span>
                                <span class="action-text">Reports</span>
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Recent Products Table -->
                    <div class="card">
                        <div class="card-header">
                            <h2>📦 Recent Products</h2>
                            <a href="manage/products.php" class="btn btn-link">View All</a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Barcode</th>
                                            <th>Category</th>
                                            <th>Vehicle</th>
                                            <th>Price</th>
                                            <th>Stock</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($products && $products->num_rows > 0): 
                                            while ($row = $products->fetch_assoc()): 
                                                $stockStatus = $row['quantity'] == 0 ? 'out' : ($row['quantity'] <= $row['min_quantity'] ? 'low' : 'in');
                                        ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($row['name']); ?></strong>
                                                    <?php if (!empty($row['tire_size'])): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($row['tire_size']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><span style="font-family:monospace;"><?php echo $row['barcode'] ?: 'N/A'; ?></span></td>
                                                <td><span class="badge badge-secondary"><?php echo htmlspecialchars($row['category_name'] ?? 'Uncategorized'); ?></span></td>
                                                <td><?php echo htmlspecialchars($row['vehicle_type'] ?? '—'); ?></td>
                                                <td><?php echo $currencySymbol; ?><?php echo number_format($row['price'], 2); ?></td>
                                                <td>
                                                    <span class="stock-count <?php echo $stockStatus === 'out' ? 'text-danger' : ($stockStatus === 'low' ? 'text-warning' : 'text-success'); ?>">
                                                        <?php echo $row['quantity']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($stockStatus === 'out'): ?>
                                                        <span class="badge badge-danger">Out of Stock</span>
                                                    <?php elseif ($stockStatus === 'low'): ?>
                                                        <span class="badge badge-warning">Low Stock</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-success">In Stock</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; 
                                        else: ?>
                                            <tr>
                                                <td colspan="7" class="text-center p-4">
                                                    No products found. 
                                                    <a href="manage/products.php">Add your first tire product</a>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <script>
        // Search functionality
        document.getElementById('globalSearch')?.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('.table tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
        
        // Form validation for setup
        document.getElementById('setupForm')?.addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;
            
            if (password !== confirm) {
                e.preventDefault();
                alert('Passwords do not match!');
            }
        });
    </script>
</body>
</html>