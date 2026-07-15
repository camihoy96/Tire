<?php
// manage/process_sale.php
header('Content-Type: application/json');
error_reporting(0);

require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';

$db = Database::getInstance()->getConnection();
$auth = new Auth();

if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login to continue']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['items'])) {
    echo json_encode(['success' => false, 'message' => 'Cart is empty or invalid data']);
    exit;
}

$userId = $auth->getUserId();
$items = $input['items'];
$paymentMethod = $input['payment_method'] ?? 'cash';
$amountReceived = floatval($input['amount_received'] ?? 0);
$discountPercentage = floatval($input['discount_percentage'] ?? 0);
$taxRate = floatval($input['tax_rate'] ?? 12);
$reference = $input['reference'] ?? '';

// Calculate totals
$subtotal = 0;
foreach ($items as $item) {
    $subtotal += floatval($item['price']) * intval($item['quantity']);
}

$discountAmount = round($subtotal * ($discountPercentage / 100), 2);
$taxableAmount = $subtotal - $discountAmount;
$taxAmount = round($taxableAmount * ($taxRate / 100), 2);
$totalAmount = round($taxableAmount + $taxAmount, 2);

// Validate payment for cash
if ($paymentMethod === 'cash' && $amountReceived < $totalAmount) {
    echo json_encode([
        'success' => false,
        'message' => 'Insufficient payment! Total is ₱' . number_format($totalAmount, 2) . ' but received only ₱' . number_format($amountReceived, 2)
    ]);
    exit;
}

$changeAmount = max(0, round($amountReceived - $totalAmount, 2));

// Generate receipt number
$receiptNumber = 'RCPT-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));

$db->begin_transaction();

try {
    // Process each item
    foreach ($items as $item) {
        $productId = intval($item['product_id']);
        $quantity = intval($item['quantity']);
        $price = floatval($item['price']); // Selling price per piece
        $itemTotal = round($price * $quantity, 2);
        
        // Get current stock and cost_price (cost per piece)
        $stockResult = $db->query("SELECT quantity, cost_price, name FROM products WHERE product_id = {$productId} AND is_active = 1 FOR UPDATE");
        if (!$stockResult || $stockResult->num_rows === 0) {
            throw new Exception("Product '{$item['name']}' not found or is inactive.");
        }
        $productData = $stockResult->fetch_assoc();
        
        if ($productData['quantity'] < $quantity) {
            throw new Exception("Insufficient stock for '{$productData['name']}'. Available: {$productData['quantity']}, Requested: {$quantity}");
        }
        
        $newQuantity = $productData['quantity'] - $quantity;
        
        // cost_price is per piece, so total cost = cost_price * quantity
        $costPricePerPiece = floatval($productData['cost_price'] ?? 0);
        $totalCost = $costPricePerPiece * $quantity;
        $profit = round($itemTotal - $totalCost, 2);
        
        // Insert into sales table (profit is calculated correctly now)
        $notes = $db->real_escape_string("POS Receipt #{$receiptNumber} | Payment: {$paymentMethod}");
        $sql = "INSERT INTO sales (product_id, quantity, unit_price, total_price, profit, sold_by, sale_date, notes) 
                VALUES ({$productId}, {$quantity}, {$price}, {$itemTotal}, {$profit}, {$userId}, NOW(), '{$notes}')";
        
        if (!$db->query($sql)) {
            throw new Exception("Failed to record sale: " . $db->error);
        }
        
        // Update product stock
        if (!$db->query("UPDATE products SET quantity = {$newQuantity}, updated_at = NOW() WHERE product_id = {$productId}")) {
            throw new Exception("Failed to update stock: " . $db->error);
        }
    }
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Sale completed! Total: ₱' . number_format($totalAmount, 2),
        'receipt_number' => $receiptNumber,
        'total_amount' => $totalAmount,
        'change_amount' => $changeAmount,
        'payment_method' => $paymentMethod,
        'subtotal' => $subtotal,
        'discount_amount' => $discountAmount,
        'tax_amount' => $taxAmount,
        'items_count' => count($items)
    ]);
    
} catch (Exception $e) {
    $db->rollback();
    echo json_encode([
        'success' => false,
        'message' => 'Transaction failed: ' . $e->getMessage()
    ]);
}