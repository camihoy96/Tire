<?php
// includes/Auth.php
require_once __DIR__ . '/Database.php';

class Auth {
    private $db;
    private $user = null;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->checkSession();
    }
    
    private function checkSession() {
        if (isset($_SESSION['user_id']) && isset($_SESSION['session_token'])) {
            $userId = $_SESSION['user_id'];
            $token = $_SESSION['session_token'];
            
            $stmt = $this->db->prepare(
                "SELECT s.*, u.* 
                 FROM sessions s 
                 JOIN users u ON s.user_id = u.user_id 
                 WHERE s.user_id = ? AND s.session_id = ? 
                 AND s.expires_at > NOW() 
                 AND u.is_active = 1"
            );
            $stmt->bind_param("is", $userId, $token);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $this->user = $result->fetch_assoc();
                $this->updateSession($token);
            } else {
                $this->logout();
            }
        }
    }
    
    private function updateSession($token) {
        $expires = date('Y-m-d H:i:s', time() + 28800);
        $stmt = $this->db->prepare(
            "UPDATE sessions SET last_activity = NOW(), expires_at = ? WHERE session_id = ?"
        );
        $stmt->bind_param("ss", $expires, $token);
        $stmt->execute();
    }
    
 public function login($username, $password) {
    // Get user with password_hash
    $stmt = $this->db->prepare(
        "SELECT * FROM users WHERE (username = ? OR email = ?)"
    );
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    // Check if user exists
    if (!$user) {
        return ['success' => false, 'message' => 'Invalid username or password'];
    }
    
    // Check if account is active
    if (!$user['is_active']) {
        return ['success' => false, 'message' => 'Account is deactivated'];
    }
    
    // Verify password (NO LOCKOUT CHECKS)
    if (password_verify($password, $user['password_hash'])) {
        // Reset any lingering lockout data
        $stmt = $this->db->prepare(
            "UPDATE users SET login_attempts = 0, locked_until = NULL, last_login = NOW() WHERE user_id = ?"
        );
        $stmt->bind_param("i", $user['user_id']);
        $stmt->execute();
        
        // Create session
        $this->createSession($user);
        
        return ['success' => true, 'user' => $user];
    } else {
        return ['success' => false, 'message' => 'Invalid username or password'];
    }
}
    
    private function createSession($user) {
        $token = bin2hex(random_bytes(32));
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $expires = date('Y-m-d H:i:s', time() + 28800);
        
        // Remove old sessions for this user
        $stmt = $this->db->prepare("DELETE FROM sessions WHERE user_id = ?");
        $stmt->bind_param("i", $user['user_id']);
        $stmt->execute();
        
        // Create new session
        $stmt = $this->db->prepare(
            "INSERT INTO sessions (session_id, user_id, ip_address, user_agent, expires_at) 
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("sisss", $token, $user['user_id'], $ip, $agent, $expires);
        $stmt->execute();
        
        // Set session variables
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['session_token'] = $token;
        $_SESSION['user_type'] = $user['user_type'];
        $_SESSION['username'] = $user['username'];
        
        // Store full user data
        $this->user = $user;
    }
    
    public function logout() {
        if (isset($_SESSION['session_token'])) {
            $stmt = $this->db->prepare("DELETE FROM sessions WHERE session_id = ?");
            $stmt->bind_param("s", $_SESSION['session_token']);
            $stmt->execute();
        }
        
        session_unset();
        session_destroy();
        $this->user = null;
    }
    
    public function isLoggedIn() {
        return $this->user !== null;
    }
    
    public function getUser() {
        return $this->user;
    }
    
    public function getUserId() {
        return $this->user ? $this->user['user_id'] : null;
    }
    
    public function isAdmin() {
        return $this->user && $this->user['user_type'] === 'admin';
    }
    
    public function hasPermission($requiredType) {
        if (!$this->isLoggedIn()) return false;
        
        $hierarchy = ['staff' => 1, 'manager' => 2, 'admin' => 3];
        $userLevel = $hierarchy[$this->user['user_type']] ?? 0;
        $requiredLevel = $hierarchy[$requiredType] ?? 0;
        
        return $userLevel >= $requiredLevel;
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
            header('Location: ' . BASE_URL . 'login.php');
            exit;
        }
    }
    
    public function requireAdmin() {
        $this->requireLogin();
        if (!$this->isAdmin()) {
            header('HTTP/1.0 403 Forbidden');
            die('Access denied. Admin privileges required.');
        }
    }
}