<!-- includes/topbar.php -->
<?php
// Make sure the company name function is available
if (!function_exists('getCompanyName')) {
    function getCompanyName() {
        global $APP_NAME;
        return isset($APP_NAME) ? $APP_NAME : 'TireTrack Pro';
    }
}
?>

<header class="topbar">
    <div class="topbar-left">
        <button class="menu-toggle" onclick="toggleSidebar()" id="menuToggle">
            <span class="hamburger-icon">☰</span>
        </button>
        <span class="company-name"><?php echo getCompanyName(); ?></span>
    </div>
    
    <div class="topbar-right">
        
        <?php if ($auth->isLoggedIn()): ?>
        <div class="dropdown">
            <button class="btn btn-sm dropdown-toggle" onclick="toggleDropdown(event, 'userDropdown')">
                👤 <?php echo htmlspecialchars($auth->getUser()['username']); ?>
                <span class="dropdown-arrow">▼</span>
            </button>
            <div id="userDropdown" class="dropdown-menu">
                <a href="<?php echo BASE_URL; ?>manage/profile.php" class="topbar-nav-link">👤 My Profile</a>
                <div class="dropdown-divider"></div>
                <a href="<?php echo BASE_URL; ?>logout.php" class="topbar-nav-link" id="topbarLogoutLink">🚪 Logout</a>
            </div>
        </div>
        <?php else: ?>
        <a href="<?php echo BASE_URL; ?>login.php" class="btn btn-sm btn-primary">🔐 Login</a>
        <?php endif; ?>
    </div>
</header>

<script>
// ==========================================
// SIDEBAR TOGGLE FUNCTION
// ==========================================
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.querySelector('.main-content');
    const menuToggle = document.getElementById('menuToggle');
    
    if (!sidebar) {
        console.error('Sidebar element not found!');
        return;
    }
    
    // Toggle collapsed class on sidebar
    sidebar.classList.toggle('collapsed');
    
    // Toggle expanded class on main content
    if (mainContent) {
        mainContent.classList.toggle('expanded');
    }
    
    // Save state to localStorage
    const isCollapsed = sidebar.classList.contains('collapsed');
    localStorage.setItem('sidebarCollapsed', isCollapsed);
    
    // Optional: Add animation class to menu toggle
    if (menuToggle) {
        menuToggle.classList.add('menu-toggle-animate');
        setTimeout(() => {
            menuToggle.classList.remove('menu-toggle-animate');
        }, 300);
    }
}

// ==========================================
// INITIALIZE SIDEBAR STATE
// ==========================================
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.querySelector('.main-content');
    
    if (sidebar) {
        // Check localStorage for saved state
        const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        
        if (isCollapsed) {
            sidebar.classList.add('collapsed');
            if (mainContent) mainContent.classList.add('expanded');
        } else {
            sidebar.classList.remove('collapsed');
            if (mainContent) mainContent.classList.remove('expanded');
        }
    }

    // ==========================================
    // POS CART PROTECTION - For topbar links
    // ==========================================
    if (typeof POS !== 'undefined' && typeof _posConfirmedLeave !== 'undefined') {
        // Intercept topbar dropdown links
        var topbarLinks = document.querySelectorAll('.topbar-nav-link');
        topbarLinks.forEach(function(link) {
            link.addEventListener('click', function(e) {
                // Skip if it's the profile link or javascript:void links
                if (link.href.includes('profile.php') || link.href.startsWith('javascript:')) {
                    return;
                }
                
                // Check if cart has items (logout protection)
                if (link.id === 'topbarLogoutLink' && POS.cart.length > 0 && !_posConfirmedLeave) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (typeof showLogoutWarningModal === 'function') {
                        showLogoutWarningModal();
                    } else {
                        // Fallback if function not defined
                        alert('You have ' + POS.cart.length + ' item(s) in your cart. Please complete or void the sale before logging out.');
                    }
                    return false;
                }
            });
        });
    }
});

// ==========================================
// DROPDOWN FUNCTIONS
// ==========================================
function toggleDropdown(event, dropdownId) {
    event.stopPropagation();
    
    const dropdown = document.getElementById(dropdownId);
    const allDropdowns = document.querySelectorAll('.dropdown-menu');
    
    if (!dropdown) return;
    
    // Close all other dropdowns first
    allDropdowns.forEach(menu => {
        if (menu.id !== dropdownId) {
            menu.classList.remove('show');
        }
    });
    
    // Toggle current dropdown
    dropdown.classList.toggle('show');
    
    // Toggle active class on the button
    const button = event.currentTarget;
    if (button) {
        button.classList.toggle('active');
    }
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    if (!event.target.closest('.dropdown')) {
        const dropdowns = document.querySelectorAll('.dropdown-menu');
        dropdowns.forEach(menu => {
            menu.classList.remove('show');
        });
        
        // Remove active class from all dropdown toggles
        document.querySelectorAll('.dropdown-toggle').forEach(btn => {
            btn.classList.remove('active');
        });
    }
});

// Close dropdown on ESC key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const dropdowns = document.querySelectorAll('.dropdown-menu');
        dropdowns.forEach(menu => {
            menu.classList.remove('show');
        });
        
        document.querySelectorAll('.dropdown-toggle').forEach(btn => {
            btn.classList.remove('active');
        });
    }
});

// ==========================================
// GLOBAL SEARCH FUNCTIONALITY
// ==========================================
document.getElementById('globalSearch')?.addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('.table tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        if (searchTerm === '') {
            row.style.display = '';
        } else {
            row.style.display = text.includes(searchTerm) ? '' : 'none';
        }
    });
});
</script>

<style>
/* Topbar Styles */
.topbar {
    position: fixed;
    top: 0;
    left: 260px;
    right: 0;
    height: 60px;
    background: white;
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 20px;
    z-index: 100;
    transition: left 0.3s ease;
}

/* When sidebar is collapsed */
.sidebar.collapsed ~ .main-content .topbar {
    left: 70px;
}

.topbar-left {
    display: flex;
    align-items: center;
    gap: 15px;
}

.topbar-right {
    display: flex;
    align-items: center;
    gap: 20px;
}

/* Menu Toggle Button */
.menu-toggle {
    background: none;
    border: none;
    cursor: pointer;
    padding: 8px;
    border-radius: 8px;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.menu-toggle:hover {
    background: var(--gray-100);
    transform: scale(1.05);
}

.menu-toggle-animate .hamburger-icon {
    animation: rotateIcon 0.3s ease;
}

.hamburger-icon {
    font-size: 1.5rem;
    display: inline-block;
    transition: transform 0.3s ease;
}

/* Company Name */
.company-name {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--primary);
}

/* Search Box */
.search-box {
    position: relative;
}

.search-box input {
    width: 250px;
    padding: 8px 12px;
    border: 1px solid var(--gray-300);
    border-radius: 8px;
    font-size: 0.9rem;
    transition: all 0.3s ease;
}

.search-box input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
    width: 300px;
}

/* Dropdown Styles */
.dropdown {
    position: relative;
    display: inline-block;
}

.dropdown-toggle {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    background: none;
    border: none;
    padding: 8px 12px;
    border-radius: 8px;
    transition: background 0.2s ease;
}

.dropdown-toggle:hover {
    background: var(--gray-100);
}

.dropdown-arrow {
    font-size: 0.7rem;
    transition: transform 0.3s ease;
}

.dropdown-toggle.active .dropdown-arrow {
    transform: rotate(180deg);
}

.dropdown-menu {
    display: none;
    position: absolute;
    right: 0;
    top: 100%;
    margin-top: 0.5rem;
    min-width: 200px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    z-index: 1000;
    padding: 0.5rem 0;
    animation: dropdownFadeIn 0.2s ease;
}

.dropdown-menu.show {
    display: block;
}

.dropdown-menu a {
    display: block;
    padding: 0.75rem 1.25rem;
    color: var(--gray-700);
    text-decoration: none;
    transition: background 0.2s ease;
    font-size: 0.9rem;
}

.dropdown-menu a:hover {
    background: var(--gray-100);
    color: var(--primary);
}

.dropdown-divider {
    height: 1px;
    background: var(--gray-200);
    margin: 0.5rem 0;
}

/* Animations */
@keyframes dropdownFadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes rotateIcon {
    0% { transform: rotate(0deg); }
    50% { transform: rotate(90deg); }
    100% { transform: rotate(0deg); }
}

/* Responsive */
@media (max-width: 768px) {
    .topbar {
        left: 0;
    }
    
    .company-name {
        display: none;
    }
    
    .search-box input {
        width: 150px;
    }
    
    .search-box input:focus {
        width: 180px;
    }
}
</style>