<?php
// manage/categories.php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';
require_once '../includes/Security.php';

$db = Database::getInstance()->getConnection();
$auth = new Auth();
$auth->requireLogin();

$user = $auth->getUser();
$flash = Security::getFlash();
$errors = [];
$success = '';

// Handle category deletion with AJAX support
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $categoryId = intval($_GET['delete']);
    
    // Check if category has products
    $checkStmt = $db->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = ? AND is_active = 1");
    $checkStmt->bind_param("i", $categoryId);
    $checkStmt->execute();
    $productCount = $checkStmt->get_result()->fetch_assoc()['count'];
    $checkStmt->close();
    
    if ($productCount > 0) {
        $response = ['success' => false, 'message' => "Cannot delete category. It has {$productCount} active products. Remove or reassign products first."];
    } else {
        $stmt = $db->prepare("UPDATE categories SET is_active = 0 WHERE category_id = ?");
        $stmt->bind_param("i", $categoryId);
        
        if ($stmt->execute()) {
            $response = ['success' => true, 'message' => "Category deleted successfully!"];
        } else {
            $response = ['success' => false, 'message' => "Failed to delete category."];
        }
        $stmt->close();
    }
    
    // If it's an AJAX request, return JSON
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    } else {
        if ($response['success']) {
            $success = $response['message'];
        } else {
            $errors[] = $response['message'];
        }
    }
}

// Handle category addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Invalid security token";
    } else {
        $cname = Security::sanitize($_POST['cname']);
        $description = Security::sanitize($_POST['description']);
        $userId = $auth->getUserId();
        
        if (empty($cname)) {
            $errors[] = "Category name is required";
        }
        
        // Check for duplicate name
        if (empty($errors)) {
            $checkStmt = $db->prepare("SELECT category_id FROM categories WHERE cname = ? AND is_active = 1");
            $checkStmt->bind_param("s", $cname);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows > 0) {
                $errors[] = "A category with this name already exists";
            }
            $checkStmt->close();
        }
        
        if (empty($errors)) {
            $stmt = $db->prepare("INSERT INTO categories (cname, description, created_by, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->bind_param("ssi", $cname, $description, $userId);
            
            if ($stmt->execute()) {
                $success = "Category added successfully!";
            } else {
                $errors[] = "Failed to add category.";
            }
            $stmt->close();
        }
    }
}

// Handle category update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_category'])) {
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Invalid security token";
    } else {
        $categoryId = intval($_POST['category_id']);
        $cname = Security::sanitize($_POST['cname']);
        $description = Security::sanitize($_POST['description']);
        
        if (empty($cname)) {
            $errors[] = "Category name is required";
        }
        
        // Check for duplicate name (excluding current)
        if (empty($errors)) {
            $checkStmt = $db->prepare("SELECT category_id FROM categories WHERE cname = ? AND category_id != ? AND is_active = 1");
            $checkStmt->bind_param("si", $cname, $categoryId);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows > 0) {
                $errors[] = "A category with this name already exists";
            }
            $checkStmt->close();
        }
        
        if (empty($errors)) {
            $stmt = $db->prepare("UPDATE categories SET cname = ?, description = ? WHERE category_id = ?");
            $stmt->bind_param("ssi", $cname, $description, $categoryId);
            
            if ($stmt->execute()) {
                $success = "Category updated successfully!";
            } else {
                $errors[] = "Failed to update category.";
            }
            $stmt->close();
        }
    }
}

// Get all categories with product count
$categories = $db->query("
    SELECT c.*, 
           COUNT(p.product_id) as product_count,
           SUM(p.quantity) as total_stock
    FROM categories c 
    LEFT JOIN products p ON c.category_id = p.category_id AND p.is_active = 1
    WHERE c.is_active = 1 
    GROUP BY c.category_id 
    ORDER BY c.cname ASC
");

// Get category for editing
$editCategory = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $stmt = $db->prepare("SELECT * FROM categories WHERE category_id = ? AND is_active = 1");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $editCategory = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories - <?php echo APP_NAME; ?></title>
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
                    <span>Categories</span>
                </div>
                
                <div class="page-header">
                    <div class="page-header-content">
                        <div>
                            <h1>📂 Category Management</h1>
                            <p>Organize your tire products by categories</p>
                        </div>
                         <?php if ($auth->isAdmin() || $auth->getUser()['user_type'] === 'manager'): ?>
                        <button class="btn btn-primary" onclick="openAddModal()">
                            ➕ Add Category
                        </button> <?php endif; ?>
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
                        <h2>Category List</h2>
                        <span class="badge badge-primary"><?php echo $categories->num_rows; ?> Categories</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Category Name</th>
                                        <th>Description</th>
                                        <th>Products</th>
                                        <th>Total Stock</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($categories && $categories->num_rows > 0): 
                                        while ($row = $categories->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($row['cname']); ?></strong>
                                        </td>
                                        <td>
                                            <?php echo $row['description'] ? htmlspecialchars($row['description']) : '<span class="text-muted">No description</span>'; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-info"><?php echo $row['product_count']; ?> products</span>
                                        </td>
                                        <td>
                                            <strong><?php echo number_format($row['total_stock'] ?? 0); ?></strong>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?php echo date('M d, Y', strtotime($row['created_at'])); ?></small>
                                        </td>
                                        <td class="action-buttons">
                                            <?php if ($auth->isAdmin() || $auth->getUser()['user_type'] === 'manager'): ?>
                                            <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($row)); ?>)" 
                                                    class="btn btn-sm btn-primary" title="Edit">✏️</button>
                                            <a href="javascript:void(0)" 
                                               onclick="confirmDelete(<?php echo $row['category_id']; ?>, '<?php echo htmlspecialchars($row['cname'], ENT_QUOTES); ?>', <?php echo $row['product_count']; ?>)" 
                                               class="btn btn-sm btn-danger" 
                                               title="Delete">🗑️</a>
                                            <?php endif; ?>
                                         </div>
                                    </tr>
                                    <?php endwhile; 
                                    else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center p-4">
                                            <div class="empty-state">
                                                <div class="empty-icon">📂</div>
                                                <h3>No categories found</h3>
                                                <p>Start by adding your first product category!</p>
                                                <button class="btn btn-primary" onclick="openAddModal()">➕ Add Category</button>
                                            </div>
                                         </div>
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
    
    <!-- Category Modal -->
    <div id="categoryModal" class="modal-overlay" style="display: none;">
        <div class="modal">
            <div class="modal-header">
                <h3 id="modalTitle">➕ Add Category</h3>
                <button onclick="closeCategoryModal()" class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="categoryForm">
                    <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                    <input type="hidden" name="category_id" id="categoryId">
                    <input type="hidden" name="add_category" id="formAction" value="1">
                    
                    <div class="form-group">
                        <label for="cname">Category Name *</label>
                        <input type="text" id="cname" name="cname" class="form-control" 
                               placeholder="e.g., Truck Tires, Car Tires" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="3" 
                                  placeholder="Brief description of this category"></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">💾 Save Category</button>
                        <button type="button" class="btn btn-secondary" onclick="closeCategoryModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Toast notification function
        function showToast(message, type = 'success', details = null) {
            const toastContainer = document.getElementById('toast-container') || (() => {
                const container = document.createElement('div');
                container.id = 'toast-container';
                container.className = 'toast-notification';
                document.body.appendChild(container);
                return container;
            })();
            
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            
            let title = 'Success';
            let icon = '✅';
            if (type === 'error') {
                title = 'Error';
                icon = '❌';
            } else if (type === 'warning') {
                title = 'Warning';
                icon = '⚠️';
            } else if (type === 'info') {
                title = 'Info';
                icon = 'ℹ️';
            }
            
            let detailsHtml = '';
            if (details) {
                detailsHtml = `<div class="sale-details">${details}</div>`;
            }
            
            toast.innerHTML = `
                <div class="toast-header">
                    <span class="toast-title">${icon} ${title}</span>
                    <button class="toast-close" onclick="this.closest('.toast').remove()">&times;</button>
                </div>
                <div class="toast-body">
                    ${message}
                    ${detailsHtml}
                </div>
            `;
            
            toastContainer.appendChild(toast);
            
            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 5000);
        }
        
        // Custom confirm dialog
        function showConfirmDialog(options) {
            return new Promise((resolve) => {
                const existingModal = document.querySelector('.confirm-modal-overlay');
                if (existingModal) existingModal.remove();
                
                const overlay = document.createElement('div');
                overlay.className = 'confirm-modal-overlay';
                
                let icon = options.type === 'danger' ? '⚠️' : '❓';
                let confirmClass = options.type === 'danger' ? 'danger' : 'warning';
                
                overlay.innerHTML = `
                    <div class="confirm-modal ${confirmClass}">
                        <div class="confirm-modal-header">
                            <div class="confirm-modal-icon">${icon}</div>
                            <h3 class="confirm-modal-title">${options.title}</h3>
                        </div>
                        <div class="confirm-modal-body">
                            <div class="confirm-modal-message">${options.message}</div>
                        </div>
                        <div class="confirm-modal-footer">
                            <button class="confirm-modal-btn cancel" onclick="this.closest('.confirm-modal-overlay').remove(); window._confirmResolve(false)">Cancel</button>
                            <button class="confirm-modal-btn confirm ${confirmClass}" onclick="this.closest('.confirm-modal-overlay').remove(); window._confirmResolve(true)">${options.confirmText || 'Delete'}</button>
                        </div>
                    </div>
                `;
                
                document.body.appendChild(overlay);
                window._confirmResolve = resolve;
                
                const handleEsc = (e) => {
                    if (e.key === 'Escape') {
                        overlay.remove();
                        resolve(false);
                        document.removeEventListener('keydown', handleEsc);
                    }
                };
                document.addEventListener('keydown', handleEsc);
                
                overlay.addEventListener('click', (e) => {
                    if (e.target === overlay) {
                        overlay.remove();
                        resolve(false);
                        document.removeEventListener('keydown', handleEsc);
                    }
                });
            });
        }
        
        // Delete category function
        async function confirmDelete(categoryId, categoryName, productCount) {
            let message = `Are you sure you want to delete category "<strong>${escapeHtml(categoryName)}</strong>"?<br><br>`;
            
            if (productCount > 0) {
                message += `⚠️ This category has <strong>${productCount}</strong> product(s) assigned to it.<br>`;
                message += `❌ Cannot delete categories that have products. Please reassign or delete the products first.`;
                
                showConfirmDialog({
                    title: 'Cannot Delete Category',
                    message: message,
                    type: 'warning',
                    confirmText: 'OK'
                }).then(() => {});
                return;
            }
            
            message += `⚠️ This action cannot be undone!`;
            
            const confirmed = await showConfirmDialog({
                title: 'Delete Category?',
                message: message,
                type: 'danger',
                confirmText: 'Yes, Delete'
            });
            
            if (confirmed) {
                showToast('Deleting category...', 'info');
                
                try {
                    const response = await fetch(`?delete=${categoryId}`, {
                        method: 'GET',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        showToast(`✅ Category "${categoryName}" deleted successfully!`, 'success');
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        showToast(`❌ ${result.message}`, 'error');
                    }
                } catch (error) {
                    console.error('Delete error:', error);
                    showToast('❌ Error deleting category. Please try again.', 'error');
                }
            }
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function openAddModal() {
            document.getElementById('modalTitle').textContent = '➕ Add Category';
            document.getElementById('formAction').name = 'add_category';
            document.getElementById('categoryForm').reset();
            document.getElementById('categoryId').value = '';
            document.getElementById('categoryModal').style.display = 'flex';
        }
        
        function openEditModal(category) {
            document.getElementById('modalTitle').textContent = '✏️ Edit Category';
            document.getElementById('formAction').name = 'update_category';
            document.getElementById('categoryId').value = category.category_id;
            document.getElementById('cname').value = category.cname;
            document.getElementById('description').value = category.description || '';
            document.getElementById('categoryModal').style.display = 'flex';
        }
        
        function closeCategoryModal() {
            document.getElementById('categoryModal').style.display = 'none';
        }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                closeCategoryModal();
            }
        }
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeCategoryModal();
        });
        
        document.querySelectorAll('.alert-dismissible').forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            }, 5000);
        });
    </script>
</body>
</html>