<?php
// includes/expiry_widget.php
require_once __DIR__ . '/ExpiryManager.php';
$expiryManager = new ExpiryManager();
$stats = $expiryManager->getExpiryStats();
$notifications = $expiryManager->getUnreadNotifications(5);
$notificationCount = $expiryManager->getNotificationCount();
?>

<div class="card expiry-widget">
    <div class="card-header">
        <h2>⏰ Expiry Alerts</h2>
        <?php if ($notificationCount > 0): ?>
            <span class="badge badge-danger"><?php echo $notificationCount; ?> New</span>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div class="expiry-stats-grid">
            <div class="stat-card-mini critical">
                <div class="stat-value"><?php echo $stats['critical']; ?></div>
                <div class="stat-label">Critical (30 days)</div>
            </div>
            <div class="stat-card-mini warning">
                <div class="stat-value"><?php echo $stats['expiring_soon']; ?></div>
                <div class="stat-label">Expiring Soon</div>
            </div>
            <div class="stat-card-mini expired">
                <div class="stat-value"><?php echo $stats['expired']; ?></div>
                <div class="stat-label">Expired</div>
            </div>
        </div>
        
        <?php if ($notifications && $notifications->num_rows > 0): ?>
            <div class="notification-list">
                <h4>Recent Alerts</h4>
                <?php while ($notif = $notifications->fetch_assoc()): ?>
                    <div class="notification-item <?php echo $notif['notification_type']; ?>">
                        <div class="notification-message">
                            <?php echo htmlspecialchars($notif['message']); ?>
                        </div>
                        <div class="notification-time">
                            <?php echo date('M d, H:i', strtotime($notif['created_at'])); ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
            <button onclick="markAllNotifications()" class="btn btn-sm btn-secondary">
                Mark all as read
            </button>
        <?php else: ?>
            <p class="text-muted">✓ No expiry alerts at this time</p>
        <?php endif; ?>
    </div>
</div>

<script>
function markAllNotifications() {
    fetch('ajax/mark_notifications.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({action: 'mark_all'})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}
</script>