<?php
// manage/users.php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';
require_once '../includes/Security.php';

$db = Database::getInstance()->getConnection();
$auth = new Auth();
$auth->requireAdmin(); // Only admins can access

$user = $auth->getUser();
$flash = Security::getFlash();
$errors = [];
$success = '';

// Handle user status toggle (activate/deactivate)
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $userId = intval($_GET['toggle']);
    
    if ($userId == $auth->getUserId()) {
        $errors[] = "You cannot deactivate your own account!";
    } else {
        $stmt = $db->prepare("UPDATE users SET is_active = NOT is_active WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        
        if ($stmt->execute()) {
            $success = "User status updated successfully!";
        } else {
            $errors[] = "Failed to update user status.";
        }
        $stmt->close();
    }
}

// Handle user type change
if (isset($_GET['promote']) && is_numeric($_GET['promote']) && isset($_GET['type'])) {
    $userId = intval($_GET['promote']);
    $newType = in_array($_GET['type'], ['admin', 'manager', 'staff']) ? $_GET['type'] : 'staff';
    
    $stmt = $db->prepare("UPDATE users SET user_type = ? WHERE user_id = ? AND user_id != ?");
    $stmt->bind_param("sii", $newType, $userId, $auth->getUserId());
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $success = "User role updated to {$newType}!";
    } else {
        $errors[] = "Failed to update user role.";
    }
    $stmt->close();
}

// Handle password reset (admin resets user password)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Invalid security token";
    } else {
        $userId = intval($_POST['user_id']);
        $newPassword = $_POST['new_password'];
        
        if (strlen($newPassword) < 6) {
            $errors[] = "Password must be at least 6 characters";
        } else {
            $hashed = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
            $stmt->bind_param("si", $hashed, $userId);
            
            if ($stmt->execute()) {
                $success = "Password reset successfully!";
            } else {
                $errors[] = "Failed to reset password.";
            }
            $stmt->close();
        }
    }
}

// Handle Add User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Invalid security token";
    } else {
        $fullname = Security::sanitize($_POST['fullname']);
        $username = Security::sanitize($_POST['username']);
        $email = Security::sanitize($_POST['email']);
        $password = $_POST['password'];
        $user_type = in_array($_POST['user_type'], ['admin', 'manager', 'staff']) ? $_POST['user_type'] : 'staff';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Validation
        if (empty($fullname) || empty($username) || empty($email) || empty($password)) {
            $errors[] = "All fields are required";
        }
        
        if (strlen($password) < 6) {
            $errors[] = "Password must be at least 6 characters";
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }
        
        // Check existing username
        if (empty($errors)) {
            $checkStmt = $db->prepare("SELECT user_id FROM users WHERE username = ?");
            $checkStmt->bind_param("s", $username);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows > 0) {
                $errors[] = "Username already exists";
            }
            $checkStmt->close();
        }
        
        // Check existing email
        if (empty($errors)) {
            $checkStmt = $db->prepare("SELECT user_id FROM users WHERE email = ?");
            $checkStmt->bind_param("s", $email);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows > 0) {
                $errors[] = "Email already exists";
            }
            $checkStmt->close();
        }
        
        if (empty($errors)) {
            $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $db->prepare("
                INSERT INTO users (fullname, username, email, password_hash, user_type, is_active, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param("sssssi", $fullname, $username, $email, $hashed, $user_type, $is_active);
            
            if ($stmt->execute()) {
                $success = "User '{$username}' created successfully!";
            } else {
                $errors[] = "Failed to create user. " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Handle Edit User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Invalid security token";
    } else {
        $editUserId = intval($_POST['user_id']);
        $fullname = Security::sanitize($_POST['fullname']);
        $username = Security::sanitize($_POST['username']);
        $email = Security::sanitize($_POST['email']);
        $user_type = in_array($_POST['user_type'], ['admin', 'manager', 'staff']) ? $_POST['user_type'] : 'staff';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Validation
        if (empty($fullname) || empty($username) || empty($email)) {
            $errors[] = "All fields are required";
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }
        
        // Check existing username (excluding current user)
        if (empty($errors)) {
            $checkStmt = $db->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
            $checkStmt->bind_param("si", $username, $editUserId);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows > 0) {
                $errors[] = "Username already exists";
            }
            $checkStmt->close();
        }
        
        // Check existing email (excluding current user)
        if (empty($errors)) {
            $checkStmt = $db->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
            $checkStmt->bind_param("si", $email, $editUserId);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows > 0) {
                $errors[] = "Email already exists";
            }
            $checkStmt->close();
        }
        
        // If changing own account, prevent deactivating or demoting
        if ($editUserId == $auth->getUserId()) {
            if (!$is_active) {
                $errors[] = "You cannot deactivate your own account";
            }
            if ($user_type != $auth->getUser()['user_type']) {
                $errors[] = "You cannot change your own role";
            }
        }
        
        if (empty($errors)) {
            $stmt = $db->prepare("
                UPDATE users 
                SET fullname = ?, username = ?, email = ?, user_type = ?, is_active = ? 
                WHERE user_id = ?
            ");
            $stmt->bind_param("ssssii", $fullname, $username, $email, $user_type, $is_active, $editUserId);
            
            if ($stmt->execute()) {
                $success = "User '{$username}' updated successfully!";
            } else {
                $errors[] = "Failed to update user.";
            }
            $stmt->close();
        }
    }
}

// Get all users - EXCLUDING admin from main list
$users = $db->query("
    SELECT u.*, 
           (SELECT COUNT(*) FROM sales WHERE sold_by = u.user_id) as sales_count,
           (SELECT COALESCE(SUM(total_price), 0) FROM sales WHERE sold_by = u.user_id) as total_sales
    FROM users u 
    WHERE u.user_type != 'admin'
    ORDER BY u.user_type ASC, u.fullname ASC
");

// Get admin users separately
$admins = $db->query("
    SELECT u.*, 
           (SELECT COUNT(*) FROM sales WHERE sold_by = u.user_id) as sales_count,
           (SELECT COALESCE(SUM(total_price), 0) FROM sales WHERE sold_by = u.user_id) as total_sales
    FROM users u 
    WHERE u.user_type = 'admin'
    ORDER BY u.fullname ASC
");

// Get user for editing
$editUser = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $editUser = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="app-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <?php include '../includes/topbar.php'; ?>
            
            <div class="content-wrapper">
                <div class="breadcrumb">
                    <a href="<?php echo BASE_URL; ?>index.php">Dashboard</a>
                    <span class="separator">/</span>
                    <span>Users</span>
                </div>
                
                <div class="page-header">
                    <div class="page-header-content">
                        <div>
                            <h1>👥 User Management</h1>
                            <p>Manage system users and their roles</p>
                        </div>
                        <button class="btn btn-primary" onclick="openAddUserModal()">
                            ➕ Add New User
                        </button>
                    </div>
                </div>
                
                <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible">
                    ✅ <?php echo $success; ?>
                    <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($errors)): ?>
                <div class="alert alert-error alert-dismissible">
                    ❌ <?php echo implode('<br>', $errors); ?>
                    <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
                </div>
                <?php endif; ?>
                
              <div class="card">
    <div class="card-header">
        <h2>System Users</h2>
        <span class="badge badge-primary"><?php echo ($users->num_rows + $admins->num_rows); ?> Users</span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Sales</th>
                        <th>Total Sales</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Admin Users First -->
                    <?php if ($admins && $admins->num_rows > 0): ?>
                    <tr class="section-header">
                        <td colspan="8">
                            <strong>👑 Administrators</strong>
                        </td>
                    </tr>
                    <?php while ($row = $admins->fetch_assoc()): 
                        $isCurrentUser = $row['user_id'] == $auth->getUserId();
                    ?>
                    <tr class="admin-row">
                        <td>
                            <strong><?php echo htmlspecialchars($row['fullname']); ?></strong>
                            <br><small class="text-muted">@<?php echo htmlspecialchars($row['username']); ?></small>
                            <?php if ($isCurrentUser): ?>
                                <span class="badge badge-info">You</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-danger"><?php echo ucfirst($row['user_type']); ?></span>
                        </td>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td>
                            <?php if ($row['is_active']): ?>
                                <span class="badge badge-success">Active</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $row['sales_count']; ?></td>
                        <td>₱<?php echo number_format($row['total_sales'], 2); ?></td>
                        <td>
                            <small class="text-muted">
                                <?php echo $row['last_login'] ? date('M d, Y h:i A', strtotime($row['last_login'])) : 'Never'; ?>
                            </small>
                        </td>
                        <td class="action-buttons">
                            <?php if (!$isCurrentUser): ?>
                                <a href="?toggle=<?php echo $row['user_id']; ?>" 
                                   class="btn btn-sm btn-<?php echo $row['is_active'] ? 'warning' : 'success'; ?>"
                                   title="<?php echo $row['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                    <?php echo $row['is_active'] ? '🔒' : '🔓'; ?>
                                </a>
                                
                                <button onclick="openEditUserModal(<?php echo htmlspecialchars(json_encode($row)); ?>)" 
                                        class="btn btn-sm btn-primary" title="Edit User">✏️</button>
                                
                                <button onclick="openResetModal(<?php echo $row['user_id']; ?>, '<?php echo htmlspecialchars($row['fullname'], ENT_QUOTES); ?>')" 
                                        class="btn btn-sm btn-info" title="Reset Password">🔑</button>
                            <?php else: ?>
                                <button onclick="openEditUserModal(<?php echo htmlspecialchars(json_encode($row)); ?>)" 
                                        class="btn btn-sm btn-primary" title="Edit User">✏️</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php endif; ?>
                    
                    <!-- Other Users -->
                    <?php if ($users && $users->num_rows > 0): ?>
                    <tr class="section-header">
                        <td colspan="8">
                            <strong>👥 Team Members</strong>
                        </td>
                    </tr>
                    <?php while ($row = $users->fetch_assoc()): 
                        $isCurrentUser = $row['user_id'] == $auth->getUserId();
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($row['fullname']); ?></strong>
                            <br><small class="text-muted">@<?php echo htmlspecialchars($row['username']); ?></small>
                            <?php if ($isCurrentUser): ?>
                                <span class="badge badge-info">You</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-<?php 
                                echo $row['user_type'] == 'admin' ? 'danger' : 
                                    ($row['user_type'] == 'manager' ? 'warning' : 'primary'); ?>">
                                <?php echo ucfirst($row['user_type']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td>
                            <?php if ($row['is_active']): ?>
                                <span class="badge badge-success">Active</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $row['sales_count']; ?></td>
                        <td>₱<?php echo number_format($row['total_sales'], 2); ?></td>
                        <td>
                            <small class="text-muted">
                                <?php echo $row['last_login'] ? date('M d, Y h:i A', strtotime($row['last_login'])) : 'Never'; ?>
                            </small>
                        </td>
                        <td class="action-buttons">
                            <?php if (!$isCurrentUser): ?>
                                <a href="?toggle=<?php echo $row['user_id']; ?>" 
                                   class="btn btn-sm btn-<?php echo $row['is_active'] ? 'warning' : 'success'; ?>"
                                   title="<?php echo $row['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                    <?php echo $row['is_active'] ? '🔒' : '🔓'; ?>
                                </a>
                            <?php endif; ?>
                            
                            <button onclick="openEditUserModal(<?php echo htmlspecialchars(json_encode($row)); ?>)" 
                                    class="btn btn-sm btn-primary" title="Edit User">✏️</button>
                            
                            <?php if (!$isCurrentUser): ?>
                                <div class="dropdown" style="display: inline-block;">
                                    <button class="btn btn-sm btn-secondary" 
                                            onclick="event.stopPropagation(); this.nextElementSibling.classList.toggle('show')">
                                        👑
                                    </button>
                                    <div class="dropdown-menu" style="min-width: 120px;">
                                        <a href="?promote=<?php echo $row['user_id']; ?>&type=admin">Admin</a>
                                        <a href="?promote=<?php echo $row['user_id']; ?>&type=manager">Manager</a>
                                        <a href="?promote=<?php echo $row['user_id']; ?>&type=staff">Staff</a>
                                    </div>
                                </div>
                                
                                <button onclick="openResetModal(<?php echo $row['user_id']; ?>, '<?php echo htmlspecialchars($row['fullname'], ENT_QUOTES); ?>')" 
                                        class="btn btn-sm btn-info" title="Reset Password">🔑</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php endif; ?>
                    
                    <?php if ($users->num_rows == 0 && $admins->num_rows == 0): ?>
                    <tr>
                        <td colspan="8" class="text-center p-4">
                            <div class="empty-state">
                                <div class="empty-icon">👥</div>
                                <h3>No users found</h3>
                                <p>Add users to get started</p>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
            </div>
        </main>
    </div>
    
    <!-- Add User Modal -->
    <div id="addUserModal" class="modal-overlay" style="display: none;">
        <div class="modal modal-lg">
            <div class="modal-header">
                <h3>➕ Add New User</h3>
                <button onclick="closeAddUserModal()" class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="addUserForm">
                    <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                    <input type="hidden" name="add_user" value="1">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="add_fullname">Full Name *</label>
                            <input type="text" id="add_fullname" name="fullname" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="add_username">Username *</label>
                            <input type="text" id="add_username" name="username" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="add_email">Email *</label>
                            <input type="email" id="add_email" name="email" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="add_password">Password *</label>
                            <input type="password" id="add_password" name="password" class="form-control" required minlength="6">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="add_user_type">Role</label>
                            <select id="add_user_type" name="user_type" class="form-control">
                                <option value="staff">Staff</option>
                                <option value="manager">Manager</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="is_active" value="1" checked>
                                Active Account
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">💾 Create User</button>
                        <button type="button" class="btn btn-secondary" onclick="closeAddUserModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit User Modal -->
    <div id="editUserModal" class="modal-overlay" style="display: none;">
        <div class="modal modal-lg">
            <div class="modal-header">
                <h3>✏️ Edit User</h3>
                <button onclick="closeEditUserModal()" class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="editUserForm">
                    <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                    <input type="hidden" name="edit_user" value="1">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_fullname">Full Name *</label>
                            <input type="text" id="edit_fullname" name="fullname" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_username">Username *</label>
                            <input type="text" id="edit_username" name="username" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_email">Email *</label>
                        <input type="email" id="edit_email" name="email" class="form-control" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_user_type">Role</label>
                            <select id="edit_user_type" name="user_type" class="form-control">
                                <option value="staff">Staff</option>
                                <option value="manager">Manager</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" id="edit_is_active" name="is_active" value="1">
                                Active Account
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">💾 Update User</button>
                        <button type="button" class="btn btn-secondary" onclick="closeEditUserModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Reset Password Modal -->
    <div id="resetModal" class="modal-overlay" style="display: none;">
        <div class="modal">
            <div class="modal-header">
                <h3>🔑 Reset Password</h3>
                <button onclick="closeResetModal()" class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                    <input type="hidden" name="reset_password" value="1">
                    <input type="hidden" name="user_id" id="resetUserId">
                    
                    <div class="form-group">
                        <label>User: <strong id="resetUserName"></strong></label>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" 
                               class="form-control" placeholder="Min 6 characters" required minlength="6">
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">🔑 Reset Password</button>
                        <button type="button" class="btn btn-secondary" onclick="closeResetModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Add User Modal
        function openAddUserModal() {
            document.getElementById('addUserForm').reset();
            document.getElementById('addUserModal').style.display = 'flex';
        }
        
        function closeAddUserModal() {
            document.getElementById('addUserModal').style.display = 'none';
        }
        
        // Edit User Modal
        function openEditUserModal(user) {
            document.getElementById('edit_user_id').value = user.user_id;
            document.getElementById('edit_fullname').value = user.fullname;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_user_type').value = user.user_type;
            document.getElementById('edit_is_active').checked = user.is_active == 1;
            document.getElementById('editUserModal').style.display = 'flex';
        }
        
        function closeEditUserModal() {
            document.getElementById('editUserModal').style.display = 'none';
        }
        
        // Reset Password Modal
        function openResetModal(userId, userName) {
            document.getElementById('resetUserId').value = userId;
            document.getElementById('resetUserName').textContent = userName;
            document.getElementById('resetModal').style.display = 'flex';
        }
        
        function closeResetModal() {
            document.getElementById('resetModal').style.display = 'none';
        }
        
        // Close modals on outside click
        window.onclick = function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                closeAddUserModal();
                closeEditUserModal();
                closeResetModal();
            }
        }
        
        // Close modals on ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAddUserModal();
                closeEditUserModal();
                closeResetModal();
            }
        });
        
        // Close dropdowns on outside click
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.dropdown')) {
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    menu.classList.remove('show');
                });
            }
        });
        
        // Auto-dismiss alerts
        document.querySelectorAll('.alert-dismissible').forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                alert.style.transition = 'opacity 0.5s';
                setTimeout(() => alert.remove(), 500);
            }, 5000);
        });
    </script>
</body>
</html>