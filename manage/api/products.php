<?php
// manage/api/products.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../../config.php';
require_once '../../includes/Database.php';
require_once '../../includes/Auth.php';

$db = Database::getInstance()->getConnection();
$auth = new Auth();

if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$category = isset($_GET['category']) ? $_GET['category'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$barcode = isset($_GET['barcode']) ? trim($_GET['barcode']) : '';

try {
    if (!empty($barcode)) {
        $stmt = $db->prepare("SELECT * FROM products WHERE barcode = ? AND is_active = 1 LIMIT 1");
        $stmt->bind_param("s", $barcode);
        $stmt->execute();
        $result = $stmt->get_result();
        $product = $result->fetch_assoc();
        
        echo json_encode([
            'success' => true,
            'product' => $product
        ]);
        exit;
    }
    
    $query = "SELECT p.*, c.cname as category_name 
             FROM products p 
             LEFT JOIN categories c ON p.category_id = c.category_id 
             WHERE p.is_active = 1";
    
    $params = [];
    $types = "";
    
    if ($category !== 'all' && is_numeric($category)) {
        $query .= " AND p.category_id = ?";
        $params[] = intval($category);
        $types .= "i";
    }
    
    if (!empty($search)) {
        $query .= " AND (p.name LIKE ? OR p.barcode LIKE ? OR p.supplier LIKE ?)";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= "sss";
    }
    
    $query .= " ORDER BY p.name ASC LIMIT 100";
    
    $stmt = $db->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $products = [];
    
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'products' => $products,
        'count' => count($products)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}