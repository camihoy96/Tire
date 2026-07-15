<?php
// config.php - Main Configuration

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'tire');

// Application Settings (Constants for fixed values)
define('APP_VERSION', '2.0.0');
define('BASE_URL', 'http://localhost/tire/');
define('SITE_NAME', 'Tire Inventory Management');

// Default value for dynamic settings
$APP_NAME_DEFAULT = 'TireTrack Pro';

// Security
define('CSRF_TOKEN_NAME', 'csrf_token');
define('PASSWORD_MIN_LENGTH', 8);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 900);

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0);
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.gc_maxlifetime', 28800);
ini_set('session.use_strict_mode', 1);
ini_set('session.use_only_cookies', 1);

session_set_cookie_params([
    'lifetime' => 28800,
    'path' => '/',
    'domain' => '',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Timezone
date_default_timezone_set('Asia/Manila');

// Create logs directory if not exists
if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0777, true);
}

// Function to get dynamic settings from database
function getAppSettings() {
    global $APP_NAME_DEFAULT;
    
    // Try to load from database
    if (file_exists(__DIR__ . '/includes/Database.php')) {
        try {
            require_once __DIR__ . '/includes/Database.php';
            $db = Database::getInstance()->getConnection();
            
            // Get company name
            $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'company_name'");
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $companyName = $row['setting_value'];
            } else {
                $companyName = $APP_NAME_DEFAULT;
            }
            $stmt->close();
            
            // Get other settings as needed
            $settings = [];
            $allSettings = $db->query("SELECT setting_key, setting_value FROM system_settings");
            if ($allSettings) {
                while ($row = $allSettings->fetch_assoc()) {
                    $settings[$row['setting_key']] = $row['setting_value'];
                }
            }
            
            return [
                'APP_NAME' => $companyName,
                'settings' => $settings
            ];
            
        } catch (Exception $e) {
            return ['APP_NAME' => $APP_NAME_DEFAULT, 'settings' => []];
        }
    }
    
    return ['APP_NAME' => $APP_NAME_DEFAULT, 'settings' => []];
}

// Helper function to get system setting from database
function getSystemSetting($key, $default = null) {
    if (file_exists(__DIR__ . '/includes/Database.php')) {
        try {
            require_once __DIR__ . '/includes/Database.php';
            $db = Database::getInstance()->getConnection();
            
            $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
            $stmt->bind_param("s", $key);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                return $row['setting_value'];
            }
        } catch (Exception $e) {
            // Fall through to default
        }
    }
    return $default;
}

// Alias function for getSetting() to maintain compatibility
function getSetting($key, $default = null) {
    return getSystemSetting($key, $default);
}

// Load dynamic settings
$appSettings = getAppSettings();
$APP_NAME = $appSettings['APP_NAME'];  // Use this variable throughout your app
$SYSTEM_SETTINGS = $appSettings['settings'];

// This prevents "constant already defined" warnings
if (!defined('APP_NAME')) {
    define('APP_NAME', $APP_NAME);
}

// Helper function to get company name (use this instead of APP_NAME constant for dynamic updates)
function getCompanyName() {
    global $APP_NAME;
    return $APP_NAME;
}
?>