<!-- includes/sidebar.php -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
    <div class="logo">
        <?php 
        $companyLogo = getSetting('company_logo', '');
        if ($companyLogo && !empty($companyLogo)): 
        ?>
            <img src="<?php echo $companyLogo; ?>" alt="<?php echo getCompanyName(); ?>" class="logo-img">
        <?php else: ?>
            <span class="logo-icon">🛞</span>
        <?php endif; ?>
        <span class="logo-text"><?php echo getCompanyName(); ?></span>
    </div>
    <small class="version">v<?php echo APP_VERSION; ?></small>
</div>
    
    <div class="sidebar-nav-container">
        <nav class="sidebar-nav">
            <a href="<?php echo BASE_URL; ?>index.php" 
               class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>"
               data-title="Dashboard">
                <span class="nav-icon">📊</span>
                <span>Dashboard</span>
            </a>
            <a href="<?php echo BASE_URL; ?>manage/pos.php" 
   class="nav-item <?php echo strpos($_SERVER['PHP_SELF'], 'pos.php') !== false ? 'active' : ''; ?>"
   data-title="POS">
    <span class="nav-icon">💳</span>
    <span>Point of Sale</span>
</a>
            <a href="<?php echo BASE_URL; ?>manage/products.php" 
               class="nav-item <?php echo strpos($_SERVER['PHP_SELF'], 'products.php') !== false ? 'active' : ''; ?>"
               data-title="Inventory">
                <span class="nav-icon">📦</span>
                <span>Tire Stocks</span>
            </a>
            
            <a href="<?php echo BASE_URL; ?>manage/categories.php" 
               class="nav-item <?php echo strpos($_SERVER['PHP_SELF'], 'categories.php') !== false ? 'active' : ''; ?>"
               data-title="Categories">
                <span class="nav-icon">📂</span>
                <span>Categories</span>
            </a>
            <a href="<?php echo BASE_URL; ?>manage/vehicle_types.php" 
   class="nav-item <?php echo strpos($_SERVER['PHP_SELF'], 'vehicle_types.php') !== false ? 'active' : ''; ?>"
   data-title="Vehicle Types">
    <span class="nav-icon">🚗</span>
    <span>Vehicle Types</span>
</a>
            <a href="<?php echo BASE_URL; ?>manage/sales.php" 
               class="nav-item <?php echo strpos($_SERVER['PHP_SELF'], 'sales.php') !== false ? 'active' : ''; ?>"
               data-title="Sales">
                <span class="nav-icon">💰</span>
                <span>Sales</span>
            </a>
            
            <?php if ($auth->isAdmin()): ?>
            <a href="<?php echo BASE_URL; ?>manage/users.php" 
               class="nav-item <?php echo strpos($_SERVER['PHP_SELF'], 'users.php') !== false ? 'active' : ''; ?>"
               data-title="Users">
                <span class="nav-icon">👥</span>
                <span>Users</span>
            </a>
              <?php endif; ?>
              <?php if ($auth->isAdmin()): ?>
            <a href="<?php echo BASE_URL; ?>manage/reports.php" 
               class="nav-item <?php echo strpos($_SERVER['PHP_SELF'], 'reports.php') !== false ? 'active' : ''; ?>"
               data-title="Reports">
                <span class="nav-icon">📈</span>
                <span>Reports</span>
            </a>
             <?php endif; ?>
            <?php if ($auth->isAdmin()): ?>
            <a href="<?php echo BASE_URL; ?>manage/settings.php" 
               class="nav-item <?php echo strpos($_SERVER['PHP_SELF'], 'settings.php') !== false ? 'active' : ''; ?>"
               data-title="Settings">
                <span class="nav-icon">⚙️</span>
                <span>Settings</span>
            </a>
            <?php endif; ?>
        </nav>
    </div>
 <div class="sidebar-footer">
       <?php if ($auth->isLoggedIn()): ?>
<div class="user-info">
    <div class="user-avatar">
        <?php echo strtoupper(substr($auth->getUser()['fullname'], 0, 2)); ?>
    </div>
    <div class="user-details">
        <strong><?php echo htmlspecialchars($auth->getUser()['fullname']); ?></strong>
        <small><?php echo ucfirst($auth->getUser()['user_type']); ?></small>
    </div>
</div>

<a href="<?php echo BASE_URL; ?>logout.php" class="nav-item logout-item" data-title="Logout" id="logoutLink">
    <span class="nav-icon">🚪</span>
    <span>Logout</span>
</a>
<?php else: ?>
<a href="<?php echo BASE_URL; ?>login.php" class="btn btn-primary btn-block">
    <span>Log in</span>
</a>
<?php endif; ?>
    </div>
</aside>

<style>
/* ==========================================
   SIDEBAR STYLES - FIXED SCROLLING
   ========================================== */
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 260px;
    height: 100vh;
    background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
    color: #e2e8f0;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 1000;
    display: flex;
    flex-direction: column;
    box-shadow: 2px 0 10px rgba(0,0,0,0.1);
}

/* Sidebar Collapsed State */
.sidebar.collapsed {
    width: 70px;
}

.sidebar.collapsed .logo-text,
.sidebar.collapsed .version,
.sidebar.collapsed .nav-item span:not(.nav-icon),
.sidebar.collapsed .user-details,
.sidebar.collapsed .logout-item span:not(.nav-icon) {
    display: none;
}

.sidebar.collapsed .nav-item {
    justify-content: center;
    padding: 12px;
}

.sidebar.collapsed .nav-icon {
    margin-right: 0;
    font-size: 1.5rem;
}

.sidebar.collapsed .user-info {
    justify-content: center;
    padding: 10px;
}

.sidebar.collapsed .user-avatar {
    margin-right: 0;
}

/* Sidebar Header - Fixed at top */
.sidebar-header {
    flex-shrink: 0;
    padding: 25px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    text-align: center;
}

.logo {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin-bottom: 8px;
}

.logo-icon {
    font-size: 2rem;
}

.logo-text {
    font-size: 1.2rem;
    font-weight: bold;
    background: linear-gradient(135deg, #fff 0%, #3b82f6 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.version {
    font-size: 0.7rem;
    color: rgba(255,255,255,0.5);
    display: block;
}

/* Scrollable Navigation Container */
.sidebar-nav-container {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    margin: 10px 0;
    padding: 0 15px;
}

/* Custom Scrollbar for Navigation */
.sidebar-nav-container::-webkit-scrollbar {
    width: 4px;
}

.sidebar-nav-container::-webkit-scrollbar-track {
    background: rgba(255,255,255,0.05);
    border-radius: 10px;
}

.sidebar-nav-container::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.2);
    border-radius: 10px;
}

.sidebar-nav-container::-webkit-scrollbar-thumb:hover {
    background: rgba(255,255,255,0.3);
}

/* Navigation Items */
.sidebar-nav {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.nav-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 15px;
    color: #cbd5e1;
    text-decoration: none;
    border-radius: 10px;
    transition: all 0.3s ease;
    position: relative;
    white-space: nowrap;
}

.nav-item::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 3px;
    background: #3b82f6;
    transform: scaleY(0);
    transition: transform 0.3s ease;
}

.nav-item:hover::before,
.nav-item.active::before {
    transform: scaleY(1);
}

.nav-item:hover {
    background: rgba(255,255,255,0.08);
    color: white;
    transform: translateX(5px);
}

.nav-item.active {
    background: rgba(59,130,246,0.15);
    color: #3b82f6;
}

.nav-icon {
    font-size: 1.3rem;
    min-width: 24px;
    transition: transform 0.3s ease;
}

.nav-item:hover .nav-icon {
    transform: scale(1.1);
}

/* Tooltip for collapsed sidebar */
.sidebar.collapsed .nav-item {
    position: relative;
}

.sidebar.collapsed .nav-item:hover::after {
    content: attr(data-title);
    position: fixed;
    left: 70px;
    background: #1e293b;
    color: white;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 0.8rem;
    white-space: nowrap;
    z-index: 1001;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    border-left: 3px solid #3b82f6;
    pointer-events: none;
}

/* Sidebar Footer - Fixed at bottom */
.sidebar-footer {
    flex-shrink: 0;
    padding: 20px;
    border-top: 1px solid rgba(255,255,255,0.1);
    background: rgba(15,23,42,0.95);
}

.user-info {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 15px;
    padding: 10px;
    border-radius: 10px;
    background: rgba(255,255,255,0.05);
}

.user-avatar {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #3b82f6, #1e3a8a);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 1rem;
    color: white;
    flex-shrink: 0;
}

.user-details {
    flex: 1;
    overflow: hidden;
}

.user-details strong {
    display: block;
    font-size: 0.9rem;
    margin-bottom: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.user-details small {
    font-size: 0.7rem;
    opacity: 0.7;
}

.logout-item {
    margin-top: 5px;
}

.logout-item:hover {
    background: rgba(239,68,68,0.15);
    color: #ef4444;
}

/* Button Styles */
.btn {
    display: inline-block;
    padding: 10px 16px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.3s ease;
}

.btn-primary {
    background: #3b82f6;
    color: white;
    width: 100%;
    text-align: center;
}

.btn-primary:hover {
    background: #2563eb;
    transform: translateY(-2px);
}

.btn-block {
    display: flex;
    justify-content: center;
    width: 100%;
}

/* Main Content Adjustment */
.main-content {
    margin-left: 260px;
    transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    min-height: 100vh;
}

.main-content.expanded {
    margin-left: 70px;
}

.content-wrapper {
    padding: 20px;
    margin-top: 60px;
}
.logo-img {
    width: 40px;
    height: 40px;
    object-fit: contain;
    border-radius: 8px;
}

.sidebar.collapsed .logo-img {
    width: 35px;
    height: 35px;
}
/* Responsive Design */
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
        width: 260px;
    }
    
    .sidebar.show {
        transform: translateX(0);
    }
    
    .main-content {
        margin-left: 0;
    }
    
    .main-content.expanded {
        margin-left: 0;
    }
}
</style>

<script>
// Ensure sidebar collapse state is applied on load
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    if (sidebar) {
        const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (isCollapsed) {
            sidebar.classList.add('collapsed');
        }
    }
});
</script>