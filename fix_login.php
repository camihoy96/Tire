<?php
// fix_login.php - Diagnostic and fix for login issues
require_once 'config.php';
require_once 'includes/Database.php';

$db = Database::getInstance()->getConnection();

echo "<h1>🔧 Login Diagnostic & Fix</h1>";

// 1. Check current admin user
$result = $db->query("SELECT * FROM users WHERE username = 'admin'");
$user = $result->fetch_assoc();

if ($user) {
    echo "<h2>Current Admin User:</h2>";
    echo "<pre>";
    print_r($user);
    echo "</pre>";
    
    // Test password verification
    $testPasswords = ['admin123', 'password', 'admin', '123456', 'Password123'];
    
    echo "<h2>Password Test:</h2>";
    foreach ($testPasswords as $testPass) {
        $verify = password_verify($testPass, $user['password_hash']);
        echo "<p>Testing '<strong>{$testPass}</strong>': " . ($verify ? '✅ MATCH!' : '❌ No match') . "</p>";
    }
    
    // 2. Delete old admin and create fresh one
    echo "<h2>Creating Fresh Admin...</h2>";
    
    $db->query("DELETE FROM users WHERE username = 'admin'");
    $db->query("DELETE FROM sessions WHERE 1=1");
    
    $newPassword = 'admin123';
    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    
    echo "<p>New Hash: <code>{$newHash}</code></p>";
    echo "<p>Hash Length: " . strlen($newHash) . "</p>";
    echo "<p>Hash Info: <pre>";
    print_r(password_get_info($newHash));
    echo "</pre></p>";
    
    $stmt = $db->prepare("
        INSERT INTO users (fullname, username, email, password_hash, user_type, is_active, login_attempts, locked_until, created_at) 
        VALUES ('Administrator', 'admin', 'admin@tire.com', ?, 'admin', 1, 0, NULL, NOW())
    ");
    $stmt->bind_param("s", $newHash);
    
    if ($stmt->execute()) {
        echo "<p style='color:green;font-size:18px;'>✅ New admin created!</p>";
        
        // Verify immediately
        $verifyStmt = $db->prepare("SELECT * FROM users WHERE username = 'admin'");
        $verifyStmt->execute();
        $newUser = $verifyStmt->get_result()->fetch_assoc();
        
        $verifyCheck = password_verify($newPassword, $newUser['password_hash']);
        echo "<p>Immediate verification: " . ($verifyCheck ? '✅ PASSWORD MATCHES!' : '❌ PASSWORD MISMATCH!') . "</p>";
        
        $verifyStmt->close();
    } else {
        echo "<p style='color:red;'>❌ Failed to create admin: " . $stmt->error . "</p>";
    }
    $stmt->close();
    
} else {
    echo "<p>No admin user found. Creating one...</p>";
    
    $newPassword = 'admin123';
    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    
    $stmt = $db->prepare("
        INSERT INTO users (fullname, username, email, password_hash, user_type, is_active, login_attempts, locked_until, created_at) 
        VALUES ('Administrator', 'admin', 'admin@tire.com', ?, 'admin', 1, 0, NULL, NOW())
    ");
    $stmt->bind_param("s", $newHash);
    $stmt->execute();
    $stmt->close();
    
    echo "<p style='color:green;'>✅ Admin created!</p>";
}

// 3. Test the login manually
echo "<h2>Manual Login Test:</h2>";

$stmtTest = $db->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
$testUsername = 'admin';
$stmtTest->bind_param("ss", $testUsername, $testUsername);
$stmtTest->execute();
$testUser = $stmtTest->get_result()->fetch_assoc();
$stmtTest->close();

if ($testUser) {
    echo "<p>User found in database: Yes</p>";
    echo "<p>Is Active: " . ($testUser['is_active'] ? 'Yes' : 'No') . "</p>";
    echo "<p>Locked Until: " . ($testUser['locked_until'] ?? 'NULL') . "</p>";
    echo "<p>Login Attempts: " . $testUser['login_attempts'] . "</p>";
    
    $manualVerify = password_verify('admin123', $testUser['password_hash']);
    echo "<p>Password 'admin123' matches: " . ($manualVerify ? '✅ YES' : '❌ NO') . "</p>";
    
    if (!$manualVerify) {
        // If still not working, try re-hashing with different algorithm
        echo "<h2>Trying alternative hash method...</h2>";
        
        $newHash2 = password_hash('admin123', PASSWORD_BCRYPT);
        $updateStmt = $db->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
        $updateStmt->bind_param("si", $newHash2, $testUser['user_id']);
        $updateStmt->execute();
        $updateStmt->close();
        
        $finalVerify = password_verify('admin123', $newHash2);
        echo "<p>Final verification: " . ($finalVerify ? '✅ WORKS!' : '❌ STILL FAILS!') . "</p>";
    }
}

echo "<hr>";
echo "<h2>🎯 Login Credentials:</h2>";
echo "<p style='font-size:20px;'><strong>Username:</strong> admin</p>";
echo "<p style='font-size:20px;'><strong>Password:</strong> admin123</p>";

echo "<br><p style='color:red;font-size:18px;'><strong>⚠️ DELETE THIS FILE (fix_login.php) AFTER USE!</strong></p>";
?>