<?php
// includes/ExpiryManager.php
class ExpiryManager {
    private $db;
    private $auth;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->auth = new Auth();
    }
    
    /**
     * Check for expiring products and create notifications
     */
    public function checkExpiringProducts() {
        $today = date('Y-m-d');
        $threshold = date('Y-m-d', strtotime("+{$this->getNotificationDays()} days"));
        
        // Check for products expiring soon
        $query = "
            SELECT p.*, c.cname as category_name 
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.category_id
            WHERE p.is_active = 1 
            AND p.expiration_date IS NOT NULL
            AND p.expiration_date BETWEEN ? AND ?
            AND p.expiration_date > ?
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("sss", $today, $threshold, $today);
        $stmt->execute();
        $expiringSoon = $stmt->get_result();
        
        while ($product = $expiringSoon->fetch_assoc()) {
            $daysUntilExpiry = $this->daysUntil($product['expiration_date']);
            $message = "Product '{$product['name']}' will expire in {$daysUntilExpiry} days on {$product['expiration_date']}";
            
            $this->createNotification($product['product_id'], 'expiry_soon', $message);
        }
        
        // Check for already expired products
        $expiredQuery = "
            SELECT p.*, c.cname as category_name 
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.category_id
            WHERE p.is_active = 1 
            AND p.expiration_date IS NOT NULL
            AND p.expiration_date < ?
        ";
        
        $stmt = $this->db->prepare($expiredQuery);
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $expired = $stmt->get_result();
        
        while ($product = $expired->fetch_assoc()) {
            $daysOverdue = abs($this->daysUntil($product['expiration_date']));
            $message = "⚠️ Product '{$product['name']}' has been expired for {$daysOverdue} days (expired on {$product['expiration_date']})";
            
            $this->createNotification($product['product_id'], 'expired', $message);
        }
        
        return ['expiring' => $expiringSoon->num_rows, 'expired' => $expired->num_rows];
    }
    
    /**
     * Create a notification
     */
    private function createNotification($productId, $type, $message) {
        // Check if notification already exists for this product and type
        $checkStmt = $this->db->prepare("
            SELECT notification_id FROM expiry_notifications 
            WHERE product_id = ? AND notification_type = ? AND is_read = 0
        ");
        $checkStmt->bind_param("is", $productId, $type);
        $checkStmt->execute();
        $existing = $checkStmt->get_result();
        
        if ($existing->num_rows === 0) {
            $stmt = $this->db->prepare("
                INSERT INTO expiry_notifications (product_id, notification_type, message) 
                VALUES (?, ?, ?)
            ");
            $stmt->bind_param("iss", $productId, $type, $message);
            $stmt->execute();
        }
    }
    
    /**
     * Get expiry statistics
     */
    public function getExpiryStats() {
        $today = date('Y-m-d');
        $stats = [];
        
        // Expired products count
        $expiredQuery = "SELECT COUNT(*) as count FROM products WHERE is_active = 1 AND expiration_date IS NOT NULL AND expiration_date < ?";
        $stmt = $this->db->prepare($expiredQuery);
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $stats['expired'] = $stmt->get_result()->fetch_assoc()['count'];
        
        // Expiring soon (next 90 days)
        $threshold = date('Y-m-d', strtotime("+90 days"));
        $expiringQuery = "SELECT COUNT(*) as count FROM products WHERE is_active = 1 AND expiration_date IS NOT NULL AND expiration_date BETWEEN ? AND ?";
        $stmt = $this->db->prepare($expiringQuery);
        $stmt->bind_param("ss", $today, $threshold);
        $stmt->execute();
        $stats['expiring_soon'] = $stmt->get_result()->fetch_assoc()['count'];
        
        // Critical (next 30 days)
        $criticalThreshold = date('Y-m-d', strtotime("+30 days"));
        $criticalQuery = "SELECT COUNT(*) as count FROM products WHERE is_active = 1 AND expiration_date IS NOT NULL AND expiration_date BETWEEN ? AND ?";
        $stmt = $this->db->prepare($criticalQuery);
        $stmt->bind_param("ss", $today, $criticalThreshold);
        $stmt->execute();
        $stats['critical'] = $stmt->get_result()->fetch_assoc()['count'];
        
        // Products with no expiration date set
        $noExpiryQuery = "SELECT COUNT(*) as count FROM products WHERE is_active = 1 AND expiration_date IS NULL";
        $result = $this->db->query($noExpiryQuery);
        $stats['no_expiry'] = $result->fetch_assoc()['count'];
        
        return $stats;
    }
    
    /**
     * Get expiring products list
     */
    public function getExpiringProducts($limit = 10) {
        $today = date('Y-m-d');
        $threshold = date('Y-m-d', strtotime("+{$this->getNotificationDays()} days"));
        
        $query = "
            SELECT p.*, c.cname as category_name,
                   DATEDIFF(p.expiration_date, ?) as days_until_expiry,
                   CASE 
                       WHEN DATEDIFF(p.expiration_date, ?) <= 0 THEN 'Expired'
                       WHEN DATEDIFF(p.expiration_date, ?) <= 30 THEN 'Critical'
                       WHEN DATEDIFF(p.expiration_date, ?) <= 60 THEN 'Warning'
                       ELSE 'Normal'
                   END as expiry_status
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.category_id
            WHERE p.is_active = 1 
            AND p.expiration_date IS NOT NULL
            AND p.expiration_date <= ?
            ORDER BY p.expiration_date ASC
            LIMIT ?
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("sssssi", $today, $today, $today, $today, $threshold, $limit);
        $stmt->execute();
        return $stmt->get_result();
    }
    
    /**
     * Get unread notifications
     */
    public function getUnreadNotifications($limit = 20) {
        $query = "
            SELECT n.*, p.name as product_name 
            FROM expiry_notifications n
            JOIN products p ON n.product_id = p.product_id
            WHERE n.is_read = 0
            ORDER BY n.created_at DESC
            LIMIT ?
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        return $stmt->get_result();
    }
    
    /**
     * Mark notification as read
     */
    public function markNotificationAsRead($notificationId) {
        $stmt = $this->db->prepare("UPDATE expiry_notifications SET is_read = 1 WHERE notification_id = ?");
        $stmt->bind_param("i", $notificationId);
        return $stmt->execute();
    }
    
    /**
     * Mark all notifications as read
     */
    public function markAllNotificationsAsRead() {
        return $this->db->query("UPDATE expiry_notifications SET is_read = 1");
    }
    
    /**
     * Get notification count
     */
    public function getNotificationCount() {
        $result = $this->db->query("SELECT COUNT(*) as count FROM expiry_notifications WHERE is_read = 0");
        return $result->fetch_assoc()['count'];
    }
    
    /**
     * Get notification days setting
     */
    private function getNotificationDays() {
        $result = $this->db->query("SELECT default_notification_days FROM expiry_settings LIMIT 1");
        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc()['default_notification_days'];
        }
        return 90; // Default 90 days
    }
    
    /**
     * Update expiry settings
     */
    public function updateSettings($notificationDays, $emailNotifications, $dashboardNotifications, $userId) {
        $stmt = $this->db->prepare("
            UPDATE expiry_settings 
            SET default_notification_days = ?, 
                email_notifications = ?, 
                dashboard_notifications = ?,
                updated_by = ?,
                updated_at = NOW()
            WHERE setting_id = 1
        ");
        $stmt->bind_param("iiii", $notificationDays, $emailNotifications, $dashboardNotifications, $userId);
        return $stmt->execute();
    }
    
    /**
     * Calculate days until expiry
     */
    private function daysUntil($date) {
        $now = new DateTime();
        $expiry = new DateTime($date);
        $interval = $now->diff($expiry);
        return $expiry >= $now ? $interval->days : -$interval->days;
    }
    
    /**
     * Validate expiration date
     */
    public function validateExpiryDate($manufacturingDate, $expirationDate) {
        $errors = [];
        
        if ($expirationDate && $manufacturingDate) {
            $manufacturing = new DateTime($manufacturingDate);
            $expiry = new DateTime($expirationDate);
            
            if ($expiry <= $manufacturing) {
                $errors[] = "Expiration date must be after manufacturing date";
            }
            
            // Check if tire is already expired (5+ years old typically)
            $today = new DateTime();
            $age = $today->diff($manufacturing)->days;
            if ($age > 2190) { // 6 years in days
                $errors[] = "Warning: This tire is over 6 years old from manufacturing date";
            }
        }
        
        return $errors;
    }
}