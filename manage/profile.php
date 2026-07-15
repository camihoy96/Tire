<?php
// manage/profile.php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';
require_once '../includes/Security.php';

$db = Database::getInstance()->getConnection();
$auth = new Auth();

// Require login to access this page
$auth->requireLogin();

$user = $auth->getUser();
$userId = $auth->getUserId();
$flash = Security::getFlash();
$errors = [];
$success = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Invalid security token";
    } else {
        $fullname = Security::sanitize($_POST['fullname']);
        $email = Security::sanitize($_POST['email']);
        
        // Validation
        if (empty($fullname)) {
            $errors[] = "Full name is required";
        }
        
        if (empty($email)) {
            $errors[] = "Email is required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }
        
        // Check if email is already taken by another user
        if (empty($errors)) {
            $stmt = $db->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
            $stmt->bind_param("si", $email, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $errors[] = "Email is already in use by another account";
            }
            $stmt->close();
        }
        
        // Update profile
        if (empty($errors)) {
            $stmt = $db->prepare("UPDATE users SET fullname = ?, email = ? WHERE user_id = ?");
            $stmt->bind_param("ssi", $fullname, $email, $userId);
            
            if ($stmt->execute()) {
                $success = "Profile updated successfully!";
                // Refresh user data
                $user = $auth->getUser();
            } else {
                $errors[] = "Failed to update profile. Please try again.";
            }
            $stmt->close();
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Invalid security token";
    } else {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validation
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $errors[] = "All password fields are required";
        }
        
        // Verify current password
        if (empty($errors)) {
            $stmt = $db->prepare("SELECT password_hash FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $userData = $result->fetch_assoc();
            $stmt->close();
            
            if (!password_verify($current_password, $userData['password_hash'])) {
                $errors[] = "Current password is incorrect";
            }
        }
        
        // Check if new passwords match
        if ($new_password !== $confirm_password) {
            $errors[] = "New passwords do not match";
        }
        
        // Check if new password is same as current
        if ($current_password === $new_password) {
            $errors[] = "New password must be different from current password";
        }
        
        // Update password
        if (empty($errors)) {
            $hashed = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
            $stmt->bind_param("si", $hashed, $userId);
            
            if ($stmt->execute()) {
                $success = "Password changed successfully!";
            } else {
                $errors[] = "Failed to change password. Please try again.";
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Bar -->
            <?php include '../includes/topbar.php'; ?>
            
            <!-- Content -->
            <div class="content-wrapper">
                <!-- Breadcrumb Navigation -->
                <div class="breadcrumb">
                    <a href="../index.php">Dashboard</a>
                    <span class="separator">/</span>
                    <span>My Profile</span>
                </div>
                
                <!-- Page Header -->
                <div class="page-header">
                    <h1>👤 My Profile</h1>
                    <p>Manage your account settings and password</p>
                </div>
                
                <!-- Success Message -->
                <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible">
                    ✅ <?php echo $success; ?>
                    <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
                </div>
                <?php endif; ?>
                
                <!-- Error Messages -->
                <?php if (!empty($errors)): ?>
                <div class="alert alert-error alert-dismissible">
                    ❌ <?php echo implode('<br>', $errors); ?>
                    <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
                </div>
                <?php endif; ?>
                
                <!-- Flash Messages -->
                <?php if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible">
                    <?php echo $flash['message']; ?>
                    <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
                </div>
                <?php endif; ?>
                
                <div class="profile-grid">
                    <!-- Profile Information Card -->
                    <div class="card">
                        <div class="card-header">
                            <h2>Profile Information</h2>
                        </div>
                        <div class="card-body">
                            <!-- User Avatar & Quick Info -->
                            <div class="profile-header">
                                <div class="profile-avatar">
                                    <?php echo strtoupper(substr($user['fullname'], 0, 2)); ?>
                                </div>
                                <div class="profile-info">
                                    <h3><?php echo htmlspecialchars($user['fullname']); ?></h3>
                                    <span class="badge badge-primary"><?php echo ucfirst($user['user_type']); ?></span>
                                    <p class="text-muted">Member since <?php echo date('F j, Y', strtotime($user['created_at'])); ?></p>
                                </div>
                            </div>
                            
                            <form method="POST" class="profile-form">
                                <input type="hidden" name="update_profile" value="1">
                                <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                                
                                <div class="form-group">
                                    <label for="username">Username</label>
                                    <input type="text" id="username" class="form-control" 
                                           value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                                    <small class="form-text">Username cannot be changed</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="fullname">Full Name</label>
                                    <input type="text" id="fullname" name="fullname" class="form-control" 
                                           value="<?php echo htmlspecialchars($user['fullname']); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="email">Email Address</label>
                                    <input type="email" id="email" name="email" class="form-control" 
                                           value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label>Account Type</label>
                                    <input type="text" class="form-control" 
                                           value="<?php echo ucfirst($user['user_type']); ?>" disabled>
                                </div>
                                
                                <div class="form-group">
                                    <label>Last Login</label>
                                    <input type="text" class="form-control" 
                                           value="<?php echo $user['last_login'] ? date('F j, Y g:i A', strtotime($user['last_login'])) : 'Never'; ?>" disabled>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary">
                                        💾 Update Profile
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Password Change Card -->
                    <div class="card">
                        <div class="card-header">
                            <h2>Change Password</h2>
                        </div>
                        <div class="card-body">
                            <form method="POST" class="password-form" id="passwordForm">
                                <input type="hidden" name="change_password" value="1">
                                <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                                
                                <div class="form-group">
                                    <label for="current_password">Current Password</label>
                                    <input type="password" id="current_password" name="current_password" 
                                           class="form-control" placeholder="Enter current password" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="new_password">New Password</label>
                                    <input type="password" id="new_password" name="new_password" 
                                           class="form-control" placeholder="Enter new password" required>
                                    <small class="form-text">Minimum 6 characters recommended</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="confirm_password">Confirm New Password</label>
                                    <input type="password" id="confirm_password" name="confirm_password" 
                                           class="form-control" placeholder="Confirm new password" required>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    🔐 Change Password
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Account Activity Card -->
                    <div class="card">
                        <div class="card-header">
                            <h2>Account Activity</h2>
                        </div>
                        <div class="card-body">
                            <div class="activity-list">
                                <div class="activity-item">
                                    <div class="activity-icon">📅</div>
                                    <div class="activity-details">
                                        <strong>Account Created</strong>
                                        <p><?php echo date('F j, Y g:i A', strtotime($user['created_at'])); ?></p>
                                    </div>
                                </div>
                                
                                <?php if ($user['last_login']): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">🔑</div>
                                    <div class="activity-details">
                                        <strong>Last Login</strong>
                                        <p><?php echo date('F j, Y g:i A', strtotime($user['last_login'])); ?></p>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="activity-item">
                                    <div class="activity-icon">✏️</div>
                                    <div class="activity-details">
                                        <strong>Last Updated</strong>
                                        <p><?php echo date('F j, Y g:i A', strtotime($user['updated_at'])); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Password form validation
        document.getElementById('passwordForm')?.addEventListener('submit', function(e) {
            const currentPassword = document.getElementById('current_password').value;
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            // Clear previous errors
            const existingError = document.querySelector('.alert-error');
            if (existingError) existingError.remove();
            
            // Validate passwords
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                showError('New passwords do not match');
                return false;
            }
            
            if (currentPassword === newPassword) {
                e.preventDefault();
                showError('New password must be different from current password');
                return false;
            }
            
            if (newPassword.length < 6) {
                e.preventDefault();
                showError('New password must be at least 6 characters');
                return false;
            }
        });
        
        function showError(message) {
            const form = document.getElementById('passwordForm');
            const errorDiv = document.createElement('div');
            errorDiv.className = 'alert alert-error';
            errorDiv.innerHTML = '❌ ' + message;
            form.insertBefore(errorDiv, form.firstChild);
            
            // Auto remove after 5 seconds
            setTimeout(() => errorDiv.remove(), 5000);
        }
        
        // Auto-dismiss alerts
        document.querySelectorAll('.alert-dismissible').forEach(alert => {
            setTimeout(() => {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            }, 5000);
        });
    </script>
</body>
</html>