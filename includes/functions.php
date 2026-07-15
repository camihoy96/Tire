<?php
// includes/functions.php
if (!function_exists('getSystemSetting')) {
    function getSystemSetting($key, $default = null) {
        try {
            if (file_exists(__DIR__ . '/Database.php')) {
                require_once __DIR__ . '/Database.php';
                $db = Database::getInstance()->getConnection();
                
                $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
                $stmt->bind_param("s", $key);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    return $row['setting_value'];
                }
            }
        } catch (Exception $e) {
            // Fall through to default
        }
        return $default;
    }
}

if (!function_exists('getCurrencySymbol')) {
    function getCurrencySymbol() {
        return getSystemSetting('currency_symbol', '₱');
    }
}

if (!function_exists('getLowStockThreshold')) {
    function getLowStockThreshold() {
        return (int)getSystemSetting('low_stock_threshold', 10);
    }
}

if (!function_exists('getExpiryAlertDays')) {
    function getExpiryAlertDays() {
        return (int)getSystemSetting('expiry_alert_days', 90);
    }
}
?>