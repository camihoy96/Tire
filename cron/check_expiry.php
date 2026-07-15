<?php
// cron/check_expiry.php - Run this daily via cron job
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/ExpiryManager.php';

$expiryManager = new ExpiryManager();
$result = $expiryManager->checkExpiringProducts();

// Log the results
$logMessage = date('Y-m-d H:i:s') . " - Expiry check completed: {$result['expiring']} expiring soon, {$result['expired']} expired\n";
file_put_contents(__DIR__ . '/../logs/expiry_check.log', $logMessage, FILE_APPEND);

echo "Expiry check completed. " . json_encode($result);