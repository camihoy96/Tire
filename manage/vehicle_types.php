<?php
// manage/vehicle_types.php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';
require_once '../includes/Security.php';

$db = Database::getInstance()->getConnection();
$auth = new Auth();
$auth->requireLogin();

$user = $auth->getUser();
$errors = [];
$success = '';

// Handle Add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_vehicle_type'])) {
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Invalid security token";
    } else {
        $name = Security::sanitize($_POST['name']);
        $icon = Security::sanitize($_POST['icon'] ?? '🛞');
        $description = Security::sanitize($_POST['description'] ?? '');
        
        if (empty($name)) {
            $errors[] = "Vehicle type name is required";
        }
        
        // Check for duplicate name
        if (empty($errors)) {
            $stmt = $db->prepare("SELECT id FROM vehicle_types WHERE name = ?");
            $stmt->bind_param("s", $name);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $errors[] = "A vehicle type with this name already exists";
            }
            $stmt->close();
        }
        
        if (empty($errors)) {
            $stmt = $db->prepare("INSERT INTO vehicle_types (name, icon, description) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $name, $icon, $description);
            
            if ($stmt->execute()) {
                $success = "Vehicle type added successfully!";
            } else {
                $errors[] = "Failed to add vehicle type.";
            }
            $stmt->close();
        }
    }
}

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_vehicle_type'])) {
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Invalid security token";
    } else {
        $id = intval($_POST['id']);
        $name = Security::sanitize($_POST['name']);
        $icon = Security::sanitize($_POST['icon'] ?? '🛞');
        $description = Security::sanitize($_POST['description'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($name)) {
            $errors[] = "Vehicle type name is required";
        }
        
        // Check for duplicate name (excluding current record)
        if (empty($errors)) {
            $stmt = $db->prepare("SELECT id FROM vehicle_types WHERE name = ? AND id != ?");
            $stmt->bind_param("si", $name, $id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $errors[] = "A vehicle type with this name already exists";
            }
            $stmt->close();
        }
        
        if (empty($errors)) {
            $stmt = $db->prepare("UPDATE vehicle_types SET name = ?, icon = ?, description = ?, is_active = ? WHERE id = ?");
            $stmt->bind_param("sssii", $name, $icon, $description, $is_active, $id);
            
            if ($stmt->execute()) {
                $success = "Vehicle type updated successfully!";
            } else {
                $errors[] = "Failed to update vehicle type.";
            }
            $stmt->close();
        }
    }
}

// Handle Delete (SECURED)
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    // Use prepared statement to prevent SQL injection
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM products WHERE vehicle_type = (SELECT name FROM vehicle_types WHERE id = ?) AND is_active = 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['cnt'] ?? 0;
    $stmt->close();
    
    if ($count > 0) {
        // Soft delete - just deactivate
        $stmt = $db->prepare("UPDATE vehicle_types SET is_active = 0 WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        $success = "Vehicle type deactivated (used by $count products)";
    } else {
        // Hard delete - no products using this type
        $stmt = $db->prepare("DELETE FROM vehicle_types WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        $success = "Vehicle type deleted successfully!";
    }
}

// Get all vehicle types
$vehicle_types = $db->query("SELECT * FROM vehicle_types ORDER BY name ASC");

// Fetch categories for icon mapping
$categoriesMap = [];
$catList = $db->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY cname ASC");
if ($catList && $catList->num_rows > 0) {
    while ($cat = $catList->fetch_assoc()) {
        $catName = strtolower($cat['cname']);
        $emoji = '📂';
        if (strpos($catName, 'car') !== false || strpos($catName, 'sedan') !== false) $emoji = '🚗';
        elseif (strpos($catName, 'suv') !== false || strpos($catName, 'crossover') !== false) $emoji = '🚙';
        elseif (strpos($catName, 'truck') !== false || strpos($catName, 'pickup') !== false) $emoji = '🚛';
        elseif (strpos($catName, 'van') !== false || strpos($catName, 'mpv') !== false) $emoji = '🚐';
        elseif (strpos($catName, 'motorcycle') !== false || strpos($catName, 'motor') !== false) $emoji = '🏍️';
        elseif (strpos($catName, 'bicycle') !== false || strpos($catName, 'bike') !== false) $emoji = '🚲';
        elseif (strpos($catName, 'bus') !== false) $emoji = '🚌';
        elseif (strpos($catName, 'atv') !== false || strpos($catName, 'off-road') !== false) $emoji = '🏎️';
        elseif (strpos($catName, 'tractor') !== false) $emoji = '🚜';
        elseif (strpos($catName, 'scooter') !== false) $emoji = '🛵';
        elseif (strpos($catName, 'tire') !== false) $emoji = '🛞';
        $categoriesMap[$emoji] = $cat['cname'];
    }
}

// Get vehicle type for editing
$edit_vehicle = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $stmt = $db->prepare("SELECT * FROM vehicle_types WHERE id = ?");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $edit_vehicle = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Types - <?php echo APP_NAME; ?></title>
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
                    <span>Vehicle Types</span>
                </div>
                
                <div class="page-header">
    <div class="page-header-content">
        <div>
            <h1>🚗 Vehicle Types</h1>
            <p>Manage vehicle type classifications for tires</p>
        </div>
        <?php 
        $currentUser = $auth->getUser();
        $isAdminOrManager = ($currentUser && ($auth->isAdmin() || ($currentUser['user_type'] ?? '') === 'manager'));
        if ($isAdminOrManager): 
        ?>
        <button class="btn btn-primary" onclick="openAddModal()">
            ➕ Add Vehicle Type
        </button>
        <?php endif; ?>
    </div>
</div>
                
                <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible">
                    ✅ <?php echo htmlspecialchars($success); ?>
                    <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($errors)): ?>
                <div class="alert alert-error alert-dismissible">
                    ❌ <?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?>
                    <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
                </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header">
                        <h2>Vehicle Type List</h2>
                        <span class="badge badge-primary"><?php echo $vehicle_types ? $vehicle_types->num_rows : 0; ?> Types</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Name</th>
                                        <th>Icon</th>
                                        <th>Description</th>
                                        <th>Products</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($vehicle_types && $vehicle_types->num_rows > 0): 
                                        while ($row = $vehicle_types->fetch_assoc()):
                                            // Secure product count query
                                            $prodStmt = $db->prepare("SELECT COUNT(*) as cnt FROM products WHERE vehicle_type = ? AND is_active = 1");
                                            $prodStmt->bind_param("s", $row['name']);
                                            $prodStmt->execute();
                                            $prodCount = $prodStmt->get_result()->fetch_assoc()['cnt'] ?? 0;
                                            $prodStmt->close();
                                            
                                            $catName = isset($categoriesMap[$row['icon']]) ? $categoriesMap[$row['icon']] : '';
                                    ?>
                                    <tr>
                                        <td>
                                            <span style="font-size: 20px;"><?php echo htmlspecialchars($row['icon'] ?? '🛞'); ?></span>
                                            <?php if ($catName): ?>
                                                <span style="margin-left: 8px; font-weight: 500;"><?php echo htmlspecialchars($catName); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                                        <td>
                                    <span style="font-size: 20px;"><?php echo htmlspecialchars($row['icon'] ?? '🛞'); ?></span>
                                </td>
                                        <td><?php echo htmlspecialchars($row['description'] ?? '—'); ?></td>
                                        <td>
                                            <span class="badge badge-info"><?php echo $prodCount; ?> products</span>
                                        </td>
                                        <td>
                                            <?php if ($row['is_active']): ?>
                                                <span class="badge badge-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                       <td class="action-buttons">
    <?php 
    $currentUser = $auth->getUser();
    $isAdminOrManager = ($currentUser && ($auth->isAdmin() || ($currentUser['user_type'] ?? '') === 'manager'));
    if ($isAdminOrManager): 
    ?>
        <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>)" 
                class="btn btn-sm btn-primary" title="Edit">✏️</button>
        <a href="javascript:void(0)" 
           onclick="confirmDelete(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['name'], ENT_QUOTES); ?>')" 
           class="btn btn-sm btn-danger" title="Delete">🗑️</a>
    <?php else: ?>
        <span class="text-muted" style="font-size:0.75rem;">View Only</span>
    <?php endif; ?>
</td>
                                    </tr>
                                    <?php endwhile; 
                                    else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center p-4">
                                           <div class="empty-state">
    <div class="empty-icon">🚗</div>
    <h3>No vehicle types found</h3>
    <p>Add your first vehicle type classification</p>
    <?php if ($isAdminOrManager): ?>
        <button class="btn btn-primary" onclick="openAddModal()">➕ Add Vehicle Type</button>
    <?php endif; ?>
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
    
    <!-- Add/Edit Modal -->
    <div id="vehicleModal" class="modal-overlay" style="display: none;">
        <div class="modal">
            <div class="modal-header">
                <h3 id="modalTitle">➕ Add Vehicle Type</h3>
                <button onclick="closeModal()" class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="vehicleForm">
                    <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                    <input type="hidden" name="id" id="vehicleId">
                    <input type="hidden" name="add_vehicle_type" id="formAction" value="1">
                    
                    <div class="form-group">
                        <label for="name">Vehicle Type Name *</label>
                        <input type="text" id="name" name="name" class="form-control" required 
                               placeholder="e.g., Car / Sedan, Motorcycle">
                    </div>
                    
                   <div class="form-group">
    <label for="icon">Icon</label>
    <select id="icon" name="icon" class="form-control">
        <option value="">Select Icon</option>
        <option value="🚗">🚗 Car / Sedan</option>
        <option value="🚙">🚙 SUV / Crossover</option>
        <option value="🚛">🚛 Truck / Pickup</option>
        <option value="🚐">🚐 Van / MPV</option>
        <option value="🏍️">🏍️ Motorcycle</option>
        <option value="🚲">🚲 Bicycle</option>
        <option value="🚌">🚌 Bus</option>
        <option value="🏎️">🏎️ ATV / Off-road</option>
        <option value="🚜">🚜 Tractor</option>
        <option value="🛵">🛵 Scooter</option>
        <option value="🛞">🛞 Tire / General</option>
    </select>
    <small class="form-text">Select an icon for this vehicle type</small>
</div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="2" 
                                  placeholder="Brief description of this vehicle type"></textarea>
                    </div>
                    
                    <div class="form-group" id="statusGroup" style="display:none;">
                        <label>
                            <input type="checkbox" name="is_active" id="is_active" value="1" checked> Active
                        </label>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">💾 Save</button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
<script>
function openAddModal() {
    document.getElementById('modalTitle').textContent = '➕ Add Vehicle Type';
    document.getElementById('formAction').name = 'add_vehicle_type';
    document.getElementById('formAction').value = '1';
    document.getElementById('vehicleForm').reset();
    document.getElementById('vehicleId').value = '';
    document.getElementById('statusGroup').style.display = 'none';
    document.getElementById('vehicleModal').style.display = 'flex';
}

function openEditModal(data) {
    document.getElementById('modalTitle').textContent = '✏️ Edit Vehicle Type';
    document.getElementById('formAction').name = 'update_vehicle_type';
    document.getElementById('formAction').value = '1';
    document.getElementById('vehicleId').value = data.id;
    document.getElementById('name').value = data.name;
    document.getElementById('icon').value = data.icon || '🛞';
    document.getElementById('description').value = data.description || '';
    document.getElementById('is_active').checked = data.is_active == 1;
    document.getElementById('statusGroup').style.display = 'block';
    document.getElementById('vehicleModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('vehicleModal').style.display = 'none';
}

function confirmDelete(id, name) {
    if (confirm(`Are you sure you want to delete "${name}"?\n\nIf products use this type, it will be deactivated instead.`)) {
        window.location.href = `?delete=${id}`;
    }
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal-overlay')) {
        closeModal();
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
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