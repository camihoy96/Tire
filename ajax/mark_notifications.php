<?php
// ajax/mark_notifications.php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/ExpiryManager.php';
require_once '../includes/Security.php';

header('Content-Type: application/json');

$expiryManager = new ExpiryManager();
$input = json_decode(file_get_contents('php://input'), true);

if ($input['action'] === 'mark_all') {
    $success = $expiryManager->markAllNotificationsAsRead();
    echo json_encode(['success' => $success]);
} elseif (isset($input['notification_id'])) {
    $success = $expiryManager->markNotificationAsRead($input['notification_id']);
    echo json_encode(['success' => $success]);
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}