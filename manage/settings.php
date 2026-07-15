<?php
// manage/settings.php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';
require_once '../includes/Security.php';

$db = Database::getInstance()->getConnection();
$auth = new Auth();
$auth->requireAdmin();

$success = '';
$errors = [];

// Helper function to update setting
function updateSetting($key, $value, $userId) {
    global $db;
    $stmt = $db->prepare("
        INSERT INTO system_settings (setting_key, setting_value, updated_by) 
        VALUES (?, ?, ?) 
        ON DUPLICATE KEY UPDATE 
        setting_value = VALUES(setting_value), 
        updated_by = VALUES(updated_by),
        updated_at = NOW()
    ");
    $stmt->bind_param("ssi", $key, $value, $userId);
    return $stmt->execute();
}

// Handle database export (direct download)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_database'])) {
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Invalid security token";
    } else {
        // Set headers for file download
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="database_backup_' . date('Y-m-d_H-i-s') . '.sql"');
        
        // Get all tables
        $tables = [];
        $result = $db->query("SHOW TABLES");
        while ($row = $result->fetch_array()) {
            $tables[] = $row[0];
        }
        
        // Start output buffering
        ob_start();
        
        echo "-- Tire Management System Database Backup\n";
        echo "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        echo "-- Database: " . DB_NAME . "\n\n";
        echo "SET FOREIGN_KEY_CHECKS=0;\n\n";
        
        foreach ($tables as $table) {
            // Get CREATE TABLE statement
            $createResult = $db->query("SHOW CREATE TABLE `$table`");
            $createRow = $createResult->fetch_assoc();
            echo "-- Table structure for table `$table`\n";
            echo "DROP TABLE IF EXISTS `$table`;\n";
            echo $createRow['Create Table'] . ";\n\n";
            
            // Get table data
            $dataResult = $db->query("SELECT * FROM `$table`");
            if ($dataResult && $dataResult->num_rows > 0) {
                echo "-- Dumping data for table `$table`\n";
                
                while ($row = $dataResult->fetch_assoc()) {
                    $columns = array_keys($row);
                    $values = array_map(function($value) use ($db) {
                        if ($value === null) return 'NULL';
                        return "'" . $db->real_escape_string($value) . "'";
                    }, array_values($row));
                    
                    echo "INSERT INTO `$table` (`" . implode("`, `", $columns) . "`) VALUES (" . implode(", ", $values) . ");\n";
                }
                echo "\n";
            }
        }
        
        echo "SET FOREIGN_KEY_CHECKS=1;\n";
        
        // Output the buffer and exit
        ob_end_flush();
        exit;
    }
}

// Handle restore backup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_backup'])) {
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Invalid security token";
    } else {
        if (isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
            $fileInfo = pathinfo($_FILES['backup_file']['name']);
            $extension = strtolower($fileInfo['extension']);
            
            if ($extension !== 'sql' && $extension !== 'zip') {
                $errors[] = "Invalid file type. Please upload .sql or .zip file.";
            } else {
                $uploadDir = '../backups/restore/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $uploadPath = $uploadDir . 'restore_' . time() . '.' . $extension;
                
                if (move_uploaded_file($_FILES['backup_file']['tmp_name'], $uploadPath)) {
                    // Extract if zip
                    if ($extension === 'zip') {
                        $zip = new ZipArchive();
                        if ($zip->open($uploadPath) === TRUE) {
                            $extractPath = $uploadDir . 'extracted_';
                            $zip->extractTo($extractPath);
                            $zip->close();
                            
                            // Find the sql file
                            $sqlFiles = glob($extractPath . '/*.sql');
                            if (count($sqlFiles) > 0) {
                                $sqlContent = file_get_contents($sqlFiles[0]);
                                unlink($sqlFiles[0]);
                                rmdir($extractPath);
                            } else {
                                $errors[] = "No SQL file found in the zip archive.";
                            }
                            unlink($uploadPath);
                        } else {
                            $errors[] = "Failed to extract zip file.";
                        }
                    } else {
                        $sqlContent = file_get_contents($uploadPath);
                        unlink($uploadPath);
                    }
                    
                    if (isset($sqlContent) && !empty($sqlContent)) {
                        // Disable foreign key checks
                        $db->query("SET FOREIGN_KEY_CHECKS = 0");
                        
                        // Split queries by semicolon
                        $queries = explode(";\n", $sqlContent);
                        $successCount = 0;
                        
                        foreach ($queries as $query) {
                            $query = trim($query);
                            if (!empty($query) && !preg_match('/^--/', $query)) {
                                if ($db->query($query)) {
                                    $successCount++;
                                } else {
                                    $errors[] = "Error executing query: " . $db->error;
                                }
                            }
                        }
                        
                        // Re-enable foreign key checks
                        $db->query("SET FOREIGN_KEY_CHECKS = 1");
                        
                        if ($successCount > 0) {
                            $success = "Database restored successfully! $successCount queries executed.";
                            if (isset($_SESSION['cached_app_settings'])) {
                                unset($_SESSION['cached_app_settings']);
                            }
                        } else {
                            $errors[] = "Failed to restore database. No valid queries found.";
                        }
                    }
                } else {
                    $errors[] = "Failed to upload backup file.";
                }
            }
        } else {
            $errors[] = "Please select a backup file to restore.";
        }
    }
}

// Handle logo upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_logo'])) {
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Invalid security token";
    } else {
        $userId = $auth->getUserId();
        
        if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/logos/';
            
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fileInfo = pathinfo($_FILES['company_logo']['name']);
            $extension = strtolower($fileInfo['extension']);
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
            
            if (!in_array($extension, $allowedExtensions)) {
                $errors[] = "Invalid file type. Allowed: JPG, PNG, GIF, SVG, WEBP";
            } else {
                $filename = 'logo_' . time() . '.' . $extension;
                $uploadPath = $uploadDir . $filename;
                
                if (move_uploaded_file($_FILES['company_logo']['tmp_name'], $uploadPath)) {
                    $logoUrl = BASE_URL . 'uploads/logos/' . $filename;
                    
                    $oldLogo = getSetting('company_logo', '');
                    if ($oldLogo && !empty($oldLogo)) {
                        $oldLogoPath = str_replace(BASE_URL, '../', $oldLogo);
                        if (file_exists($oldLogoPath)) {
                            unlink($oldLogoPath);
                        }
                    }
                    
                    if (updateSetting('company_logo', $logoUrl, $userId)) {
                        $success = "Company logo uploaded successfully!";
                    } else {
                        $errors[] = "Failed to save logo URL to database.";
                    }
                } else {
                    $errors[] = "Failed to upload logo file.";
                }
            }
        } else {
            $errors[] = "Please select a logo file to upload.";
        }
    }
}

// Handle remove logo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_logo'])) {
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Invalid security token";
    } else {
        $userId = $auth->getUserId();
        $oldLogo = getSetting('company_logo', '');
        
        if ($oldLogo && !empty($oldLogo)) {
            $oldLogoPath = str_replace(BASE_URL, '../', $oldLogo);
            if (file_exists($oldLogoPath)) {
                unlink($oldLogoPath);
            }
        }
        
        if (updateSetting('company_logo', '', $userId)) {
            $success = "Company logo removed successfully!";
        } else {
            $errors[] = "Failed to remove logo.";
        }
    }
}

// Handle General Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_general_settings'])) {
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Invalid security token";
    } else {
        $userId = $auth->getUserId();
        
        $companyName = Security::sanitize($_POST['company_name'] ?? '');
        $companyEmail = Security::sanitize($_POST['company_email'] ?? '');
        $companyPhone = Security::sanitize($_POST['company_phone'] ?? '');
        $companyAddress = Security::sanitize($_POST['company_address'] ?? '');
        
        $successCount = 0;
        
        if (updateSetting('company_name', $companyName, $userId)) $successCount++;
        if (updateSetting('company_email', $companyEmail, $userId)) $successCount++;
        if (updateSetting('company_phone', $companyPhone, $userId)) $successCount++;
        if (updateSetting('company_address', $companyAddress, $userId)) $successCount++;
        
        if ($successCount > 0) {
            $success = "General settings updated successfully!";
            if (isset($_SESSION['cached_app_settings'])) {
                unset($_SESSION['cached_app_settings']);
            }
        } else {
            $errors[] = "Failed to update general settings.";
        }
    }
}

// Handle Inventory Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_inventory_settings'])) {
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Invalid security token";
    } else {
        $userId = $auth->getUserId();
        
        $lowStockThreshold = isset($_POST['low_stock_threshold']) ? intval($_POST['low_stock_threshold']) : 10;
        $expiryAlertDays = isset($_POST['expiry_alert_days']) ? intval($_POST['expiry_alert_days']) : 90;
        $currencySymbol = Security::sanitize($_POST['currency_symbol'] ?? '₱');
        
        $successCount = 0;
        
        if (updateSetting('low_stock_threshold', $lowStockThreshold, $userId)) $successCount++;
        if (updateSetting('expiry_alert_days', $expiryAlertDays, $userId)) $successCount++;
        if (updateSetting('currency_symbol', $currencySymbol, $userId)) $successCount++;
        
        if ($successCount > 0) {
            $success = "Inventory settings updated successfully!";
            if (isset($_SESSION['cached_app_settings'])) {
                unset($_SESSION['cached_app_settings']);
            }
        } else {
            $errors[] = "Failed to update inventory settings.";
        }
    }
}

// Handle Notification Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_notification_settings'])) {
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Invalid security token";
    } else {
        $userId = $auth->getUserId();
        
        $enableNotifications = isset($_POST['enable_notifications']) ? 1 : 0;
        
        if (updateSetting('enable_notifications', $enableNotifications, $userId)) {
            $success = "Notification settings updated successfully!";
            if (isset($_SESSION['cached_app_settings'])) {
                unset($_SESSION['cached_app_settings']);
            }
        } else {
            $errors[] = "Failed to update notification settings.";
        }
    }
}

// Get current settings from database
$companyName = getSetting('company_name', APP_NAME);
$companyEmail = getSetting('company_email', '');
$companyPhone = getSetting('company_phone', '');
$companyAddress = getSetting('company_address', '');
$lowStockThreshold = getSetting('low_stock_threshold', 10);
$expiryAlertDays = getSetting('expiry_alert_days', 90);
$currencySymbol = getSetting('currency_symbol', '₱');
$enableNotifications = getSetting('enable_notifications', 1);
$companyLogo = getSetting('company_logo', '');

// Get backup files list
$backupFiles = [];
$backupDir = '../backups/';
if (is_dir($backupDir)) {
    $files = glob($backupDir . 'backup_*.zip');
    rsort($files);
    foreach ($files as $file) {
        $backupFiles[] = [
            'name' => basename($file),
            'size' => round(filesize($file) / 1024, 2),
            'date' => date('Y-m-d H:i:s', filemtime($file)),
            'path' => $file
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - <?php echo htmlspecialchars($companyName); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #374151;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.2s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #3b82f6;
            ring: 2px solid #3b82f6;
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }
        
        .form-text {
            font-size: 12px;
            color: #6b7280;
            margin-top: 5px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .checkbox-group input {
            width: auto;
            margin: 0;
        }
        
        .checkbox-group label {
            margin: 0;
        }
        
        .form-actions {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        .info-card {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .info-card h4 {
            margin: 0 0 10px 0;
            color: #0369a1;
        }
        
        .info-card p {
            margin: 5px 0;
            font-size: 13px;
            color: #0c4a6e;
        }
        
        .info-card i {
            margin-right: 8px;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .alert-dismissible {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .alert-close {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: inherit;
            opacity: 0.7;
        }
        
        .alert-close:hover {
            opacity: 1;
        }
        
        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: #3b82f6;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2563eb;
        }
        
        .btn-secondary {
            background: #e5e7eb;
            color: #374151;
        }
        
        .btn-secondary:hover {
            background: #d1d5db;
        }
        
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
        }
        
        .btn-success {
            background: #059669;
            color: white;
        }
        
        .btn-success:hover {
            background: #047857;
        }
        
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .card-header {
            padding: 15px 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .card-header h2 {
            margin: 0;
            font-size: 18px;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table tr {
            border-bottom: 1px solid #e5e7eb;
        }
        
        .table td, .table th {
            padding: 10px;
            text-align: left;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        .logo-preview {
            margin-top: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            text-align: center;
        }
        
        .logo-preview img {
            max-width: 150px;
            max-height: 100px;
            object-fit: contain;
        }
        
        .logo-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
            justify-content: center;
        }
        
        .file-input-wrapper {
            position: relative;
            display: inline-block;
        }
        
        .file-input-wrapper input[type="file"] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .backup-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .backup-list {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .backup-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .backup-item:hover {
            background: #f8f9fa;
        }
        
        .backup-info {
            flex: 1;
        }
        
        .backup-name {
            font-weight: 500;
            font-size: 13px;
        }
        
        .backup-meta {
            font-size: 11px;
            color: #6b7280;
            margin-top: 3px;
        }
        
        .backup-actions-buttons {
            display: flex;
            gap: 5px;
        }
        
        .btn-sm {
            padding: 4px 8px;
            font-size: 12px;
        }
    </style>
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
                    <span>Settings</span>
                </div>
                
                <div class="page-header">
                    <h1>⚙️ System Settings</h1>
                    <p>Configure your tire management system</p>
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
                
                <div class="settings-grid">
                    <!-- Logo Settings Card -->
                    <div class="card">
                        <div class="card-header">
                            <h2>🖼️ Company Logo</h2>
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                                <input type="hidden" name="upload_logo" value="1">
                                
                                <div class="form-group">
                                    <label>Current Logo</label>
                                    <div class="logo-preview">
                                        <?php if ($companyLogo && !empty($companyLogo)): ?>
                                            <img src="<?php echo $companyLogo; ?>" alt="Company Logo">
                                        <?php else: ?>
                                            <div style="font-size: 48px;">🛞</div>
                                            <p class="form-text">Default icon is being used</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="company_logo">Upload New Logo</label>
                                    <div class="file-input-wrapper">
                                        <input type="file" id="company_logo" name="company_logo" 
                                               accept="image/jpeg,image/png,image/gif,image/svg+xml,image/webp">
                                        <button type="button" class="btn btn-secondary" onclick="document.getElementById('company_logo').click()">
                                            📁 Choose File
                                        </button>
                                        <span id="file-name" class="form-text" style="margin-left: 10px;">No file chosen</span>
                                    </div>
                                    <small class="form-text">Allowed formats: JPG, PNG, GIF, SVG, WEBP. Max size: 2MB</small>
                                </div>
                                
                                <div class="logo-actions">
                                    <button type="submit" class="btn btn-primary">📤 Upload Logo</button>
                                    <?php if ($companyLogo && !empty($companyLogo)): ?>
                                    <button type="submit" name="remove_logo" value="1" class="btn btn-danger" 
                                            onclick="return confirm('Are you sure you want to remove the logo?')">
                                        🗑️ Remove Logo
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- General Settings Card -->
                    <div class="card">
                        <div class="card-header">
                            <h2>🏢 General Settings</h2>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                                <input type="hidden" name="update_general_settings" value="1">
                                
                                <div class="form-group">
                                    <label for="company_name">Company Name</label>
                                    <input type="text" id="company_name" name="company_name" 
                                           class="form-control" 
                                           value="<?php echo htmlspecialchars($companyName); ?>" 
                                           required>
                                    <small class="form-text">Your business/company name displayed throughout the system</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="company_email">Company Email</label>
                                    <input type="email" id="company_email" name="company_email" 
                                           class="form-control" 
                                           value="<?php echo htmlspecialchars($companyEmail); ?>">
                                    <small class="form-text">Email address for system notifications</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="company_phone">Company Phone</label>
                                    <input type="text" id="company_phone" name="company_phone" 
                                           class="form-control" 
                                           value="<?php echo htmlspecialchars($companyPhone); ?>">
                                    <small class="form-text">Contact number for customers</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="company_address">Company Address</label>
                                    <textarea id="company_address" name="company_address" 
                                              class="form-control" rows="3"><?php echo htmlspecialchars($companyAddress); ?></textarea>
                                    <small class="form-text">Physical address of your business</small>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary">💾 Save General Settings</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Inventory Settings Card -->
                    <div class="card">
                        <div class="card-header">
                            <h2>📦 Inventory Settings</h2>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                                <input type="hidden" name="update_inventory_settings" value="1">
                                
                                <div class="form-group">
                                    <label for="low_stock_threshold">Low Stock Threshold</label>
                                    <input type="number" id="low_stock_threshold" name="low_stock_threshold" 
                                           class="form-control" 
                                           value="<?php echo $lowStockThreshold; ?>" 
                                           min="1" required>
                                    <small class="form-text">Alert when stock quantity falls below this number</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="expiry_alert_days">Expiry Alert Days</label>
                                    <input type="number" id="expiry_alert_days" name="expiry_alert_days" 
                                           class="form-control" 
                                           value="<?php echo $expiryAlertDays; ?>" 
                                           min="1" required>
                                    <small class="form-text">Days before expiry to show alerts</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="currency_symbol">Currency Symbol</label>
                                    <input type="text" id="currency_symbol" name="currency_symbol" 
                                           class="form-control" 
                                           value="<?php echo htmlspecialchars($currencySymbol); ?>" 
                                           maxlength="5" required>
                                    <small class="form-text">Currency symbol for prices (₱, $, €, etc.)</small>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary">💾 Save Inventory Settings</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                   <!-- Database Export Card -->
<div class="card">
    <div class="card-header">
        <h2>💾 Database Export</h2>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
            <input type="hidden" name="export_database" value="1">
            
            <div class="form-group">
                <p>Export your entire database to an SQL file. This file can be used for backup or migration purposes.</p>
            </div>
            
            <div class="info-card">
                <h4>📌 Export Information</h4>
                <p><i>✅</i> Exports all database tables and data</p>
                <p><i>💾</i> File will be downloaded as .sql format</p>
                <p><i>⏰</i> No files are stored on the server</p>
                <p><i>💡</i> Can be imported using phpMyAdmin or MySQL command line</p>
            </div>
            
            <div class="form-actions">
               <button type="button" class="btn btn-warning" onclick="showExportModal()" style="background: #ea580c; color: white; font-weight: bold; padding: 10px 20px; display: flex; align-items: center; gap: 8px;">
    ⚠️ Export Database
</button>
            </div>
        </form>
    </div>
</div>
                    <!-- Notification Settings Card -->
                    <div class="card">
                        <div class="card-header">
                            <h2>🔔 Notification Settings</h2>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                                <input type="hidden" name="update_notification_settings" value="1">
                                
                                <div class="form-group">
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="enable_notifications" 
                                               name="enable_notifications" value="1"
                                               <?php echo $enableNotifications ? 'checked' : ''; ?>>
                                        <label for="enable_notifications">Enable Email Notifications</label>
                                    </div>
                                    <small class="form-text">Receive email alerts for low stock and expiring products</small>
                                </div>
                                
                                <div class="info-card">
                                    <h4>📧 Notification Events</h4>
                                    <p><i>⚠️</i> Low stock alerts - When product quantity falls below threshold</p>
                                    <p><i>⏰</i> Expiry alerts - When products are nearing expiration date</p>
                                    <p><i>💰</i> Daily sales summary - End of day report (coming soon)</p>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary">💾 Save Notification Settings</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- System Information Card -->
                    <div class="card">
                        <div class="card-header">
                            <h2>💻 System Information</h2>
                        </div>
                        <div class="card-body">
                            <table class="table" style="width: 100%;">
                                <tr>
                                    <td><strong>PHP Version</strong></td>
                                    <td><?php echo phpversion(); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Server Software</strong></td>
                                    <td><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'N/A'; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Max Upload Size</strong></td>
                                    <td><?php echo ini_get('upload_max_filesize'); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Database</strong></td>
                                    <td><?php echo DB_NAME; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>System Version</strong></td>
                                    <td><?php echo APP_VERSION; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Time Zone</strong></td>
                                    <td><?php echo date_default_timezone_get(); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <!-- Export Confirmation Modal -->
<div id="exportModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 12px; max-width: 500px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.3); animation: slideIn 0.3s ease;">
        <div style="background: #ea580c; color: white; border-radius: 12px 12px 0 0; padding: 20px; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 28px;">⚠️</span>
            <h3 style="margin: 0; font-size: 20px;">Database Export Warning</h3>
        </div>
        
        <div style="padding: 20px;">
            <p style="margin: 0 0 15px 0; color: #374151; font-size: 14px;">
                You are about to export the <strong>ENTIRE database</strong> including:
            </p>
            
            <ul style="margin: 0 0 20px 0; padding-left: 20px; color: #4b5563;">
                <li>📦 All products and inventory</li>
                <li>👥 All customer records</li>
                <li>💰 All sales transactions</li>
                <li>👤 All user accounts</li>
                <li>⚙️ All system settings</li>
            </ul>
            
            <div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 12px; margin-bottom: 20px; border-radius: 6px;">
                <strong style="color: #92400e;">⚠️ Security Notice:</strong>
                <p style="margin: 5px 0 0 0; font-size: 13px; color: #78350f;">
                    This file contains sensitive business data. Keep it secure and do not share it publicly.
                </p>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button onclick="closeExportModal()" class="btn btn-secondary" style="padding: 10px 20px;">
                    Cancel
                </button>
                <button id="confirmExportBtn" class="btn btn-warning" style="background: #ea580c; color: white; padding: 10px 20px;">
                    ✅ Yes, Export Database
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    @keyframes slideIn {
        from {
            transform: translateY(-50px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }
</style>

<script>
function showExportModal() {
    document.getElementById('exportModal').style.display = 'flex';
}

function closeExportModal() {
    document.getElementById('exportModal').style.display = 'none';
}

// Handle the export confirmation
document.getElementById('confirmExportBtn')?.addEventListener('click', function() {
    // Create a form and submit it
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '';
    
    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = 'csrf_token';
    csrfInput.value = '<?php echo Security::generateCSRFToken(); ?>';
    
    const exportInput = document.createElement('input');
    exportInput.type = 'hidden';
    exportInput.name = 'export_database';
    exportInput.value = '1';
    
    form.appendChild(csrfInput);
    form.appendChild(exportInput);
    document.body.appendChild(form);
    form.submit();
    
    closeExportModal();
});

// Close modal when clicking outside
document.getElementById('exportModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeExportModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeExportModal();
    }
});
</script>
    <script>
        document.getElementById('company_logo')?.addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name || 'No file chosen';
            document.getElementById('file-name').textContent = fileName;
        });
        
        document.getElementById('restore_file')?.addEventListener('change', function(e) {
            if (e.target.files[0]) {
                if (confirm('WARNING: Restoring a backup will overwrite ALL current database data!\n\nAre you absolutely sure you want to continue?')) {
                    document.getElementById('restoreForm').submit();
                }
            }
        });
        
        function deleteBackup(filename) {
            if (confirm('Are you sure you want to delete ' + filename + '?')) {
                window.location.href = '?delete_backup=' + encodeURIComponent(filename) + '&csrf_token=<?php echo Security::generateCSRFToken(); ?>';
            }
        }
        
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