<?php
// manage/pos.php - Main POS Interface
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';
require_once '../includes/Security.php';

$db = Database::getInstance()->getConnection();
$auth = new Auth();
$auth->requireLogin();

$user = $auth->getUser();
$flash = Security::getFlash();

// Get settings
$currencySymbol = getSystemSetting('currency_symbol', '₱');
$taxRate = getSystemSetting('tax_rate', 12);
$lowStockThreshold = getSystemSetting('low_stock_threshold', 10);

// Get customers for dropdown
$customers = $db->query("SELECT customer_id, first_name, last_name, phone FROM customers ORDER BY first_name ASC");

// Get payment methods
$paymentMethods = $db->query("SELECT * FROM payment_methods WHERE is_active = 1");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo Security::generateCSRFToken(); ?>">
    <title>POS - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="../assets/js/html5-qrcode.min.js"></script>
    <style>
/* POS-specific styles */
.pos-container {
    display: grid;
    grid-template-columns: 1fr 400px;
    gap: 15px;
    margin-top: -25px;
    margin-left: -25px;
    height: calc(100vh - 120px);
    overflow: hidden;
}

/* Products Panel - Fixed header, scrollable grid */
.products-panel {
    display: flex;
    flex-direction: column;
    overflow: hidden;
    height: 100%;
    background: #f8fafc;
    border-radius: 12px;
}

/* Fixed header section - WILL NOT SCROLL */
.products-panel-header {
    flex-shrink: 0;
    position: sticky;
    top: 0;
    background: #f8fafc;
    z-index: 10;
    padding: 15px;
    border-bottom: 1px solid #e2e8f0;
}

.product-search {
    margin-bottom: 10px;
}

.product-search input {
    width: 100%;
    padding: 10px 15px;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    font-size: 0.95rem;
    box-sizing: border-box;
}

.product-search input:focus {
    border-color: #3b82f6;
    outline: none;
}

.categories-filter {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding-bottom: 8px;
}

.category-chip {
    padding: 6px 14px;
    background: #f1f5f9;
    border: none;
    border-radius: 20px;
    cursor: pointer;
    transition: all 0.3s;
    font-size: 0.8rem;
}

.category-chip.active {
    background: #3b82f6;
    color: white;
}

/* Scrollable products grid - ONLY THIS SCROLLS */
.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 10px;
    overflow-y: auto;
    flex: 1;
    padding: 15px;
    align-content: start;
}

/* COMPACT PRODUCT CARD */
.product-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 10px 12px;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    flex-direction: column;
    gap: 3px;
    min-height: 0;
}

.product-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 3px 12px rgba(0,0,0,0.08);
    border-color: #3b82f6;
}

.product-card .product-name {
    font-weight: 600;
    font-size: 0.85rem;
    line-height: 1.2;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    margin: 0;
}

.product-card .product-price {
    color: #3b82f6;
    font-size: 1rem;
    font-weight: bold;
}

.product-card .product-stock {
    font-size: 0.7rem;
    color: #64748b;
}

.product-card.out-of-stock {
    opacity: 0.5;
    cursor: not-allowed;
}

.product-card small {
    color: #94a3b8;
    font-size: 0.7rem;
    line-height: 1.2;
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Cart Panel - Fixed structure */
.cart-panel {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    display: flex;
    flex-direction: column;
    height: 100%;
    overflow: hidden;
}

/* Cart Header - FIXED (will not scroll) */
.cart-header {
    flex-shrink: 0;
    padding: 15px 20px;
    border-bottom: 1px solid #e2e8f0;
    background: white;
    position: sticky;
    top: 0;
    z-index: 5;
}

.cart-header h2 {
    margin: 0 0 8px 0;
    font-size: 1.1rem;
}

/* Cart Items - SCROLLABLE */
.cart-items {
    flex: 1;
    overflow-y: auto;
    padding: 15px 20px;
    min-height: 0;
}

/* Cart Footer - FIXED (will not scroll) */
.cart-footer {
    flex-shrink: 0;
    padding: 15px 20px;
    border-top: 1px solid #e2e8f0;
    background: #f8fafc;
    position: sticky;
    bottom: 0;
}

.cart-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #f1f5f9;
    gap: 8px;
}

.cart-item-info {
    flex: 1;
    min-width: 0;
}

.cart-item-name {
    font-weight: 500;
    font-size: 0.85rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.cart-item-price {
    font-size: 0.75rem;
    color: #64748b;
}

.cart-item-quantity {
    display: flex;
    align-items: center;
    gap: 4px;
}

.qty-btn {
    width: 25px;
    height: 25px;
    border: 1px solid #e2e8f0;
    background: white;
    border-radius: 5px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    padding: 0;
    line-height: 1;
}

.qty-btn:hover {
    background: #f1f5f9;
}

.qty-input {
    width: 40px;
    text-align: center;
    border: 1px solid #e2e8f0;
    border-radius: 5px;
    padding: 3px;
    font-size: 0.85rem;
}

.cart-item-total {
    font-weight: 600;
    min-width: 65px;
    text-align: right;
    font-size: 0.85rem;
}

.cart-item-remove {
    background: none;
    border: none;
    color: #ef4444;
    cursor: pointer;
    font-size: 1rem;
    padding: 0 3px;
}

.cart-summary {
    margin-bottom: 12px;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 6px;
    font-size: 0.85rem;
}

.summary-row.total {
    font-size: 1.1rem;
    font-weight: bold;
    color: #1e293b;
    border-top: 2px solid #e2e8f0;
    padding-top: 8px;
    margin-top: 8px;
}

.payment-section {
    margin-top: 12px;
}

.payment-methods {
    display: flex;
    gap: 6px;
    margin-bottom: 12px;
}

.payment-method-btn {
    flex: 1;
    padding: 8px 4px;
    border: 2px solid #e2e8f0;
    background: white;
    border-radius: 6px;
    cursor: pointer;
    text-align: center;
    font-size: 0.8rem;
    transition: all 0.3s;
}

.payment-method-btn.active {
    border-color: #3b82f6;
    background: #eff6ff;
    color: #3b82f6;
    font-weight: 600;
}

.amount-received {
    margin-bottom: 12px;
}

.amount-received label {
    display: block;
    margin-bottom: 4px;
    font-weight: 500;
    font-size: 0.85rem;
}

.amount-received input {
    width: 100%;
    padding: 10px;
    border: 2px solid #e2e8f0;
    border-radius: 6px;
    font-size: 1rem;
    box-sizing: border-box;
}

.amount-received input:focus {
    border-color: #3b82f6;
    outline: none;
}

.change-due {
    font-size: 1rem;
    font-weight: bold;
    margin-top: 8px;
    padding: 8px;
    background: #f0fdf4;
    border-radius: 6px;
}

.action-buttons {
    display: flex;
    gap: 8px;
    margin-top: 12px;
}

.btn-complete-sale {
    flex: 1;
    padding: 12px;
    background: #059669;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s;
}

.btn-complete-sale:hover {
    background: #047857;
}

.btn-complete-sale:disabled {
    background: #94a3b8;
    cursor: not-allowed;
}

.btn-void {
    padding: 12px 20px;
    background: #ef4444;
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s;
}

.btn-void:hover {
    background: #dc2626;
}

.empty-cart {
    text-align: center;
    color: #94a3b8;
    padding: 40px 0;
}

.empty-cart p {
    margin: 0;
}

.form-group {
    margin-top: 8px;
}

.form-group label {
    display: block;
    margin-bottom: 4px;
    font-weight: 500;
    font-size: 0.85rem;
}

.form-control {
    width: 100%;
    padding: 8px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 0.85rem;
    box-sizing: border-box;
}

@keyframes posBorderPulse {
    0%, 100% { border-color: #10b981; }
    50% { border-color: #34d399; }
}

@keyframes posScanAnimation {
    0% { top: 10%; }
    50% { top: 80%; }
    100% { top: 10%; }
}

.btn-scanner-icon:hover {
    background: #dbeafe !important;
    border-color: #93c5fd !important;
    transform: translateY(-50%) scale(1.1) !important;
}

.btn-scanner-icon.scanning {
    background: #fef3c7 !important;
    border-color: #f59e0b !important;
    animation: posPulse 1.5s infinite;
}

@keyframes posPulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.6; }
}

/* Scrollbar styling */
.products-grid::-webkit-scrollbar,
.cart-items::-webkit-scrollbar {
    width: 5px;
}

.products-grid::-webkit-scrollbar-thumb,
.cart-items::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 10px;
}

/* Responsive */
@media (max-width: 1024px) {
    .pos-container {
        grid-template-columns: 1fr;
        height: auto;
    }
    
    .cart-panel {
        height: auto;
        max-height: 500px;
    }
}
    </style>
</head>
<body>
    <div class="app-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <?php include '../includes/topbar.php'; ?>
            
            <div class="content-wrapper">
                <?php if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible">
                    <?php echo htmlspecialchars($flash['message']); ?>
                    <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
                </div>
                <?php endif; ?>
                
                <div class="pos-container">
                    <!-- Products Panel -->
                    <div class="products-panel">
                        <!-- Fixed Header -->
                        <div class="products-panel-header">
                            <div class="product-search">
                                <div style="position: relative; display: flex; align-items: center;">
                                    <input type="text" id="productSearch" placeholder="🔍 Search products or scan barcode..." style="padding-right: 45px;">
                                    <button type="button" id="posBarcodeScannerBtn" class="btn-scanner-icon" title="Scan barcode with camera" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px; padding: 4px 8px; cursor: pointer; font-size: 1.1rem; transition: all 0.2s; z-index: 5;">
                                        📷
                                    </button>
                                </div>
                            </div>
                            
                            <div class="categories-filter" id="categoryFilter">
                                <button class="category-chip active" data-category="all">All</button>
                                <?php
                                $categories = $db->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY cname ASC");
                                if ($categories) {
                                    while ($cat = $categories->fetch_assoc()):
                                ?>
                                <button class="category-chip" data-category="<?php echo $cat['category_id']; ?>">
                                    <?php echo htmlspecialchars($cat['cname']); ?>
                                </button>
                                <?php endwhile; } ?>
                            </div>
                        </div>
                        
                        <!-- Scrollable Products Grid -->
                        <div class="products-grid" id="productsGrid">
                            <div style="text-align: center; padding: 40px; color: #94a3b8;">
                                Loading products...
                            </div>
                        </div>
                    </div>
                    
                    <!-- Cart Panel -->
                    <div class="cart-panel">
                        <div class="cart-header">
                            <h2>🛒 Current Sale</h2>
                        </div>
                        
                        <div class="cart-items" id="cartItems">
                            <div class="empty-cart">
                                <p>🛒 Cart is empty</p>
                                <small>Click products to add them</small>
                            </div>
                        </div>
                        
                        <div class="cart-footer">
                            <div class="cart-summary">
                                <div class="summary-row">
                                    <span>Subtotal</span>
                                    <span id="subtotal"><?php echo $currencySymbol; ?>0.00</span>
                                </div>
                                <div class="summary-row">
                                    <span>Discount (%)</span>
                                    <span>
                                        <input type="number" id="discountPercent" placeholder="0" 
                                            style="width: 50px; text-align: right; padding: 4px;" min="0" max="100" value="0"
                                            oninput="POS.updateCart()" onchange="POS.updateCart()">
                                        % = <span id="discountAmount"><?php echo $currencySymbol; ?>0.00</span>
                                    </span>
                                </div>
                                <div class="summary-row">
                                    <span>Tax (<?php echo $taxRate; ?>%)</span>
                                    <span id="taxAmount"><?php echo $currencySymbol; ?>0.00</span>
                                </div>
                                <div class="summary-row total">
                                    <span>Total</span>
                                    <span id="totalAmount"><?php echo $currencySymbol; ?>0.00</span>
                                </div>
                            </div>
                            
                            <div class="payment-section">
                                <div class="payment-methods" id="paymentMethods">
                                    <?php 
                                    $pmResult = $db->query("SELECT * FROM payment_methods WHERE is_active = 1");
                                    $first = true;
                                    if ($pmResult) {
                                        while ($pm = $pmResult->fetch_assoc()): 
                                    ?>
                                    <button class="payment-method-btn <?php echo $first ? 'active' : ''; ?>" 
                                            data-method="<?php echo $pm['method_code']; ?>">
                                        <?php echo htmlspecialchars($pm['method_name']); ?>
                                    </button>
                                    <?php $first = false; endwhile; } ?>
                                </div>
                                
                                <div class="amount-received" id="cashPaymentSection">
                                    <label>Amount Received</label>
                                    <input type="number" id="amountReceived" placeholder="0.00" step="0.01" min="0">
                                    <div class="change-due" id="changeDue" style="color: #059669;">
                                        Change: <?php echo $currencySymbol; ?>0.00
                                    </div>
                                </div>
                                
                                <div class="amount-received" id="cardPaymentSection" style="display: none;">
                                    <label>Card Reference Number</label>
                                    <input type="text" id="cardReference" placeholder="Enter reference number">
                                </div>
                                
                                <div class="action-buttons">
                                    <button class="btn-complete-sale" id="completeSaleBtn" disabled>
                                        💳 Complete Sale
                                    </button>
                                    <button class="btn-void" id="voidSaleBtn">
                                        🗑️ Void
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Barcode Scanner Modal -->
    <div id="posScannerModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; align-items: center; justify-content: center;">
        <div class="modal" style="max-width: 500px; width: 90%; background: white; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.2);">
            <div class="modal-header" style="background: linear-gradient(135deg, #1e293b, #334155); color: white; border-radius: 12px 12px 0 0; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; font-size: 1.1rem;">
                    <span style="font-size: 1.5rem;">📷</span> Scan Barcode
                    <span id="posScanTimer" style="font-size: 0.9rem; margin-left: 15px; opacity: 0.8;"></span>
                </h3>
                <button onclick="POS.stopScanner()" style="background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer; padding: 0; line-height: 1;">&times;</button>
            </div>
            <div class="modal-body" style="padding: 20px;">
                <div style="position: relative; background: #000; border-radius: 10px; overflow: hidden; margin-bottom: 15px;">
                    <div id="posCameraPreview" style="width: 100%; max-height: 400px; border: 2px solid #334155;"></div>
                    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 70%; height: 150px; border: 3px solid #10b981; border-radius: 15px; box-shadow: 0 0 0 1000px rgba(0,0,0,0.4); animation: posBorderPulse 2s infinite;"></div>
                    <div style="position: absolute; top: 0; left: 10%; width: 80%; height: 2px; background: linear-gradient(90deg, transparent, #10b981, transparent); animation: posScanAnimation 2s linear infinite; box-shadow: 0 0 8px rgba(16, 185, 129, 0.5);"></div>
                </div>
                <p class="text-center" style="margin: 10px 0; color: #64748b;">
                    <span id="posScanHint">Position barcode within the green box</span>
                </p>
                <div style="display: flex; gap: 10px; justify-content: center;">
                    <button type="button" class="btn btn-secondary" onclick="POS.stopScanner()">❌ Cancel</button>
                    <button type="button" class="btn btn-primary" id="posRetryScanner" style="display:none;" onclick="POS.startScanner()">🔄 Retry</button>
                </div>
                <div id="posScannerResult" style="display:none; background: #f0fdf4; border: 1px solid #86efac; border-radius: 8px; padding: 12px; margin-top: 15px; text-align: center; font-weight: 600; color: #166534;"></div>
            </div>
        </div>
    </div>

    <script>
    // POS Application State
    var POS = {
        cart: [],
        products: [],
        taxRate: <?php echo $taxRate; ?>,
        currencySymbol: '<?php echo $currencySymbol; ?>',
        
        addToCart: function(productId, productName, price, stock) {
            if (stock <= 0) {
                this.showWarningModal('Out of Stock', 
                    '<strong>' + escapeHtml(productName) + '</strong> is currently out of stock and cannot be added to cart.',
                    '⚠️');
                return;
            }
            
            var existingItem = this.cart.find(function(item) {
                return item.product_id === productId;
            });
            
            if (existingItem) {
                if (existingItem.quantity >= stock) {
                    this.showWarningModal('Stock Limit Reached', 
                        'Only <strong>' + stock + '</strong> units of <strong>' + escapeHtml(productName) + '</strong> available. You already have ' + existingItem.quantity + ' in cart.',
                        '⚠️');
                    return;
                }
                existingItem.quantity++;
            } else {
                this.cart.push({
                    product_id: productId,
                    name: productName,
                    price: price,
                    quantity: 1,
                    stock: stock
                });
            }
            
            this.updateCart();
            this.showToast(productName + ' added to cart', 'success');
        },
        
        removeFromCart: function(productId) {
            this.cart = this.cart.filter(function(item) {
                return item.product_id !== productId;
            });
            this.updateCart();
        },
        
        updateQuantity: function(productId, quantity) {
            var item = this.cart.find(function(item) {
                return item.product_id === productId;
            });
            if (item) {
                if (quantity <= 0) {
                    this.removeFromCart(productId);
                } else if (quantity > item.stock) {
                    this.showWarningModal('Stock Limit', 
                        'Only <strong>' + item.stock + '</strong> units available for <strong>' + escapeHtml(item.name) + '</strong>.',
                        '⚠️');
                    this.updateCart();
                } else {
                    item.quantity = quantity;
                    this.updateCart();
                }
            }
        },
        
        calculateTotals: function() {
            var subtotal = this.cart.reduce(function(sum, item) {
                return sum + (parseFloat(item.price) * parseInt(item.quantity));
            }, 0);
            
            var discountInput = document.getElementById('discountPercent');
            var discountPercent = 0;
            if (discountInput && discountInput.value !== '') {
                discountPercent = parseFloat(discountInput.value) || 0;
            }
            
            var discountAmount = subtotal * (discountPercent / 100);
            var taxableAmount = subtotal - discountAmount;
            var taxAmount = taxableAmount * (this.taxRate / 100);
            var total = taxableAmount + taxAmount;
            
            return {
                subtotal: subtotal,
                discountAmount: discountAmount,
                taxAmount: taxAmount,
                total: total
            };
        },
        
        updateCart: function() {
            var totals = this.calculateTotals();
            var cartContainer = document.getElementById('cartItems');
            var self = this;
            
            if (this.cart.length === 0) {
                cartContainer.innerHTML = '<div class="empty-cart"><p>🛒 Cart is empty</p><small>Click products to add them</small></div>';
            } else {
                cartContainer.innerHTML = this.cart.map(function(item) {
                    return '<div class="cart-item">' +
                        '<div class="cart-item-info">' +
                            '<div class="cart-item-name">' + escapeHtml(item.name) + '</div>' +
                            '<div class="cart-item-price">' + self.currencySymbol + parseFloat(item.price).toFixed(2) + ' each | Stock: ' + item.stock + '</div>' +
                        '</div>' +
                        '<div class="cart-item-quantity">' +
                            '<button class="qty-btn" onclick="POS.updateQuantity(' + item.product_id + ', ' + (item.quantity - 1) + ')">-</button>' +
                            '<input type="number" class="qty-input" value="' + item.quantity + '" ' +
                                'onchange="POS.updateQuantity(' + item.product_id + ', parseInt(this.value) || 0)" min="1" max="' + item.stock + '">' +
                            '<button class="qty-btn" onclick="POS.updateQuantity(' + item.product_id + ', ' + (item.quantity + 1) + ')">+</button>' +
                        '</div>' +
                        '<div class="cart-item-total">' + self.currencySymbol + (parseFloat(item.price) * parseInt(item.quantity)).toFixed(2) + '</div>' +
                        '<button class="cart-item-remove" onclick="POS.removeFromCart(' + item.product_id + ')">✕</button>' +
                    '</div>';
                }).join('');
            }
            
            document.getElementById('subtotal').textContent = this.currencySymbol + totals.subtotal.toFixed(2);
            document.getElementById('discountAmount').textContent = this.currencySymbol + totals.discountAmount.toFixed(2);
            document.getElementById('taxAmount').textContent = this.currencySymbol + totals.taxAmount.toFixed(2);
            document.getElementById('totalAmount').textContent = this.currencySymbol + totals.total.toFixed(2);
            
            document.getElementById('completeSaleBtn').disabled = this.cart.length === 0;
            
            this.calculateChange();
        },
        
        calculateChange: function() {
            var totals = this.calculateTotals();
            var amountReceived = parseFloat(document.getElementById('amountReceived').value) || 0;
            var change = amountReceived - totals.total;
            var changeEl = document.getElementById('changeDue');
            
            if (totals.total === 0) {
                changeEl.textContent = 'Change: ' + this.currencySymbol + '0.00';
                changeEl.style.color = '#64748b';
            } else if (amountReceived <= 0) {
                changeEl.textContent = '⚠️ Please enter amount received';
                changeEl.style.color = '#f59e0b';
            } else if (change >= 0) {
                changeEl.textContent = 'Change: ' + this.currencySymbol + change.toFixed(2);
                changeEl.style.color = '#059669';
            } else {
                changeEl.textContent = '❌ Short: ' + this.currencySymbol + Math.abs(change).toFixed(2);
                changeEl.style.color = '#ef4444';
            }
        },
        
        loadProducts: function(category, search) {
            category = category || 'all';
            search = search || '';
            var self = this;
            
            document.getElementById('productsGrid').innerHTML = '<div style="text-align:center;padding:40px;color:#94a3b8;">Loading products...</div>';
            
            fetch('api/products.php?category=' + encodeURIComponent(category) + '&search=' + encodeURIComponent(search))
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (data.success) {
                        self.products = data.products;
                        self.renderProducts();
                    } else {
                        document.getElementById('productsGrid').innerHTML = 
                            '<div style="text-align:center;padding:40px;color:#ef4444;">Error: ' + (data.message || 'Failed to load') + '</div>';
                    }
                })
                .catch(function(error) {
                    document.getElementById('productsGrid').innerHTML = 
                        '<div style="text-align:center;padding:40px;color:#ef4444;">⚠️ Error loading products.</div>';
                });
        },
        
        renderProducts: function() {
            var grid = document.getElementById('productsGrid');
            var self = this;
            
            if (this.products.length === 0) {
                grid.innerHTML = '<div style="text-align:center;padding:40px;color:#94a3b8;">📦 No products found</div>';
                return;
            }
            
            grid.innerHTML = this.products.map(function(product) {
                var outOfStock = product.quantity <= 0;
                var outOfStockClass = outOfStock ? ' out-of-stock' : '';
                var onClick = outOfStock ? '' : 
                    'onclick="POS.addToCart(' + product.product_id + ', \'' + escapeJs(product.name) + '\', ' + product.price + ', ' + product.quantity + ')"';
                
                var stockText = outOfStock ? '❌ Out of Stock' : '📦 Stock: ' + product.quantity;
                var stockClass = outOfStock ? 'color:#ef4444;' : '';
                
                var tireSizeHtml = product.tire_size ? '<small>Size: ' + escapeHtml(product.tire_size) + '</small>' : '';
                var vehicleTypeHtml = product.vehicle_type ? '<small>🚗 ' + escapeHtml(product.vehicle_type) + '</small>' : '';
                
                return '<div class="product-card' + outOfStockClass + '" ' + onClick + ' style="' + (outOfStock ? 'opacity:0.6;cursor:not-allowed;' : '') + '">' +
                    '<div class="product-name">' + escapeHtml(product.name) + '</div>' +
                    '<div class="product-price">' + self.currencySymbol + parseFloat(product.price).toFixed(2) + '</div>' +
                    '<div class="product-stock" style="' + stockClass + '">' + stockText + '</div>' +
                    tireSizeHtml +
                    vehicleTypeHtml +
                '</div>';
            }).join('');
        },
        
        completeSale: function() {
            var totals = this.calculateTotals();
            var activeMethod = document.querySelector('.payment-method-btn.active');
            var paymentMethod = activeMethod ? activeMethod.dataset.method : 'cash';
            var amountReceived = parseFloat(document.getElementById('amountReceived').value) || 0;
            
            if (this.cart.length === 0) {
                this.showWarningModal('Empty Cart', 'Please add products to the cart before completing the sale.', '🛒');
                return;
            }
            
            if (paymentMethod === 'cash') {
                if (amountReceived <= 0) {
                    this.showWarningModal('Payment Required', 
                        'Please enter the amount received.<br><br>Total to pay: <strong>' + this.currencySymbol + totals.total.toFixed(2) + '</strong>',
                        '💵');
                    document.getElementById('amountReceived').focus();
                    return;
                }
                
                if (amountReceived < totals.total) {
                    var shortfall = totals.total - amountReceived;
                    this.showWarningModal('Insufficient Payment', 
                        'The amount received is less than the total.<br><br>' +
                        'Total: <strong>' + this.currencySymbol + totals.total.toFixed(2) + '</strong><br>' +
                        'Received: <strong>' + this.currencySymbol + amountReceived.toFixed(2) + '</strong><br>' +
                        'Shortfall: <strong style="color:#ef4444;">' + this.currencySymbol + shortfall.toFixed(2) + '</strong>',
                        '⚠️');
                    document.getElementById('amountReceived').focus();
                    return;
                }
            }
            
            var savedCart = JSON.parse(JSON.stringify(this.cart));
            
            var saleData = {
                customer_id: null,
                items: this.cart,
                payment_method: paymentMethod,
                amount_received: amountReceived,
                discount_percentage: parseFloat(document.getElementById('discountPercent').value) || 0,
                tax_rate: this.taxRate,
                reference: document.getElementById('cardReference').value || null
            };
            
            var self = this;
            var btn = document.getElementById('completeSaleBtn');
            var originalText = btn.textContent;
            btn.textContent = '⏳ Processing...';
            btn.disabled = true;
            
            fetch('process_sale.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(saleData)
            })
            .then(function(response) {
                if (!response.ok) throw new Error('Server error: ' + response.status);
                return response.json();
            })
            .then(function(result) {
                if (result.success) {
                    var printData = {
                        receipt_number: result.receipt_number,
                        total_amount: result.total_amount,
                        change_amount: result.change_amount,
                        payment_method: result.payment_method,
                        subtotal: result.subtotal,
                        discount_amount: result.discount_amount
                    };
                    
                    var changeInfo = result.change_amount > 0 ? 
                        '<br>Change: <strong>' + self.currencySymbol + parseFloat(result.change_amount).toFixed(2) + '</strong>' : '';
                    
                    self.showSuccessModal('Sale Completed! ✅',
                        '<p>Receipt #: <strong>' + result.receipt_number + '</strong></p>' +
                        '<p>Subtotal: <strong>' + self.currencySymbol + parseFloat(result.subtotal).toFixed(2) + '</strong></p>' +
                        (result.discount_amount > 0 ? '<p>Discount: <strong>-' + self.currencySymbol + parseFloat(result.discount_amount).toFixed(2) + '</strong></p>' : '') +
                        '<p>Total: <strong>' + self.currencySymbol + parseFloat(result.total_amount).toFixed(2) + '</strong></p>' +
                        '<p>Payment: <strong>' + result.payment_method + '</strong></p>' +
                        changeInfo +
                        '<p>Items sold: <strong>' + result.items_count + '</strong></p>',
                        printData,
                        savedCart
                    );
                    
                    self.cart = [];
                    self.updateCart();
                    self.loadProducts();
                    document.getElementById('amountReceived').value = '';
                    document.getElementById('discountPercent').value = '0';
                    document.getElementById('cardReference').value = '';
                } else {
                    self.showWarningModal('Sale Failed', result.message || 'An error occurred while processing the sale.', '❌');
                }
                btn.textContent = originalText;
                btn.disabled = self.cart.length === 0;
            })
            .catch(function(error) {
                self.showWarningModal('Connection Error', 'Could not connect to the server.<br><br><small>' + error.message + '</small>', '🔌');
                btn.textContent = originalText;
                btn.disabled = false;
            });
        },
        
        showWarningModal: function(title, message, icon) {
            var modal = document.getElementById('alertModal');
            if (!modal) {
                modal = document.createElement('div');
                modal.id = 'alertModal';
                modal.className = 'modal-overlay';
                modal.style.cssText = 'display:flex;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:10000;align-items:center;justify-content:center;';
                document.body.appendChild(modal);
            }
            
            icon = icon || '⚠️';
            
            modal.innerHTML = '<div class="modal" style="max-width:420px;width:90%;background:white;border-radius:12px;box-shadow:0 10px 40px rgba(0,0,0,0.2);">' +
                '<div class="modal-header" style="padding:20px 20px 10px;border-bottom:1px solid #fee2e2;background:#fef2f2;border-radius:12px 12px 0 0;">' +
                    '<h3 style="margin:0;font-size:1.1rem;color:#991b1b;">' + icon + ' ' + title + '</h3>' +
                '</div>' +
                '<div class="modal-body" style="padding:20px;font-size:0.95rem;line-height:1.6;">' + message + '</div>' +
                '<div class="modal-footer" style="padding:15px 20px;border-top:1px solid #e5e7eb;text-align:right;">' +
                    '<button onclick="document.getElementById(\'alertModal\').style.display=\'none\'" style="padding:8px 24px;background:#dc2626;color:white;border:none;border-radius:6px;cursor:pointer;font-weight:600;">OK</button>' +
                '</div>' +
            '</div>';
            modal.style.display = 'flex';
            
            modal.onclick = function(e) {
                if (e.target === modal) modal.style.display = 'none';
            };
        },
        
        showSuccessModal: function(title, message, saleData, savedCart) {
            var modal = document.getElementById('alertModal');
            if (!modal) {
                modal = document.createElement('div');
                modal.id = 'alertModal';
                modal.className = 'modal-overlay';
                modal.style.cssText = 'display:flex;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:10000;align-items:center;justify-content:center;';
                document.body.appendChild(modal);
            }
            
            var self = this;
            modal._printData = saleData;
            modal._savedCart = savedCart || [];
            
            modal.innerHTML = '<div class="modal" style="max-width:420px;width:90%;background:white;border-radius:12px;box-shadow:0 10px 40px rgba(0,0,0,0.2);">' +
                '<div class="modal-header" style="padding:20px 20px 10px;border-bottom:1px solid #d1fae5;background:#ecfdf5;border-radius:12px 12px 0 0;">' +
                    '<h3 style="margin:0;font-size:1.1rem;color:#065f46;">' + title + '</h3>' +
                '</div>' +
                '<div class="modal-body" style="padding:20px;font-size:0.95rem;line-height:1.6;">' + message + '</div>' +
                '<div class="modal-footer" style="padding:15px 20px;border-top:1px solid #e5e7eb;display:flex;gap:10px;justify-content:flex-end;">' +
                    (saleData ? '<button id="printReceiptBtn" style="padding:8px 16px;background:#3b82f6;color:white;border:none;border-radius:6px;cursor:pointer;">🖨️ Print Receipt</button>' : '') +
                    '<button id="closeSuccessBtn" style="padding:8px 24px;background:#059669;color:white;border:none;border-radius:6px;cursor:pointer;font-weight:600;">OK</button>' +
                '</div>' +
            '</div>';
            modal.style.display = 'flex';
            
            setTimeout(function() {
                var printBtn = document.getElementById('printReceiptBtn');
                var closeBtn = document.getElementById('closeSuccessBtn');
                
                if (printBtn) {
                    printBtn.onclick = function() {
                        modal.style.display = 'none';
                        self.printReceiptWithCart(modal._printData, modal._savedCart);
                    };
                }
                
                if (closeBtn) {
                    closeBtn.onclick = function() {
                        modal.style.display = 'none';
                    };
                }
            }, 100);
            
            modal.onclick = function(e) {
                if (e.target === modal) modal.style.display = 'none';
            };
        },
        
        printReceiptWithCart: function(saleData, cartItems) {
            var receiptWindow = window.open('', '_blank', 'width=350,height=600');
            var self = this;
            
            var itemsHtml = '';
            for (var i = 0; i < cartItems.length; i++) {
                var item = cartItems[i];
                itemsHtml += '<div class="item">' + 
                    escapeHtml(item.name) + ' x' + item.quantity + ' - ' + 
                    self.currencySymbol + (parseFloat(item.price) * parseInt(item.quantity)).toFixed(2) + 
                '</div>';
            }
            
            var changeHtml = (saleData.change_amount && saleData.change_amount > 0) ? 
                '<p>Change: ' + self.currencySymbol + parseFloat(saleData.change_amount).toFixed(2) + '</p>' : '';
            
            var discountHtml = (saleData.discount_amount && saleData.discount_amount > 0) ?
                '<p>Discount: -' + self.currencySymbol + parseFloat(saleData.discount_amount).toFixed(2) + '</p>' : '';
            
            receiptWindow.document.write('<!DOCTYPE html><html><head>' +
                '<title>Receipt ' + (saleData.receipt_number || '') + '</title>' +
                '<style>body{font-family:"Courier New",monospace;margin:0;padding:20px;}.receipt{max-width:300px;margin:0 auto;}.header{text-align:center;margin-bottom:20px;border-bottom:1px dashed #ccc;padding-bottom:10px;}.items{margin-bottom:20px;}.item{margin-bottom:5px;font-size:0.9em;}.total{border-top:1px dashed #000;padding-top:10px;margin-top:10px;}.footer{text-align:center;margin-top:20px;font-size:0.8em;color:#666;}</style>' +
                '</head><body><div class="receipt">' +
                '<div class="header"><h2><?php echo addslashes(APP_NAME); ?></h2><p>Receipt #' + (saleData.receipt_number || 'N/A') + '</p><p>Date: ' + new Date().toLocaleString() + '</p></div>' +
                '<div class="items">' + itemsHtml + '</div>' +
                '<div class="total">' +
                    '<p>Subtotal: ' + self.currencySymbol + parseFloat(saleData.subtotal || 0).toFixed(2) + '</p>' +
                    discountHtml +
                    '<p><strong>Total: ' + self.currencySymbol + parseFloat(saleData.total_amount || 0).toFixed(2) + '</strong></p>' +
                    '<p>Payment: ' + (saleData.payment_method || 'cash') + '</p>' +
                    changeHtml +
                '</div>' +
                '<div class="footer"><p>Thank you for your purchase!</p></div>' +
                '</div><script>window.onload=function(){window.print();setTimeout(function(){window.close();},500);}</' + 'script>' +
                '</body></html>');
            receiptWindow.document.close();
        },
        
        showToast: function(message, type) {
            type = type || 'success';
            var container = document.getElementById('toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'toast-container';
                container.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;';
                document.body.appendChild(container);
            }
            var icons = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };
            var toast = document.createElement('div');
            toast.style.cssText = 'background:white;padding:12px 18px;border-radius:8px;margin-bottom:8px;box-shadow:0 4px 15px rgba(0,0,0,0.15);min-width:250px;border-left:4px solid ' + (type==='error'?'#ef4444':'#059669') + ';font-size:0.9rem;';
            toast.innerHTML = (icons[type]||'') + ' ' + message;
            container.appendChild(toast);
            setTimeout(function() { toast.style.opacity = '0'; toast.style.transition = 'opacity 0.3s'; setTimeout(function() { toast.remove(); }, 300); }, 3000);
        },

        // ============================================
        // BARCODE SCANNER PROPERTIES & METHODS
        // ============================================
        html5QrCode: null,
        isScanning: false,
        lastScannedCode: '',
        lastScanTime: 0,
        scanTimeout: null,
        scanStartTime: null,

        playBeepSound: function() {
            try {
                const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = audioContext.createOscillator();
                const gainNode = audioContext.createGain();
                oscillator.connect(gainNode);
                gainNode.connect(audioContext.destination);
                oscillator.frequency.value = 800;
                oscillator.type = 'sine';
                gainNode.gain.value = 0.3;
                oscillator.start();
                gainNode.gain.exponentialRampToValueAtTime(0.001, audioContext.currentTime + 0.2);
                oscillator.stop(audioContext.currentTime + 0.2);
            } catch (e) {}
        },

        startScanner: function() {
            const scannerModal = document.getElementById('posScannerModal');
            const scannerBtn = document.getElementById('posBarcodeScannerBtn');
            const scannerResult = document.getElementById('posScannerResult');
            const retryBtn = document.getElementById('posRetryScanner');
            
            this.scanStartTime = Date.now();
            if (this.scanTimeout) { clearTimeout(this.scanTimeout); this.scanTimeout = null; }
            if (scannerBtn) { scannerBtn.innerHTML = '📷'; scannerBtn.classList.add('scanning'); }
            if (scannerResult) { scannerResult.style.display = 'none'; scannerResult.innerHTML = ''; }
            if (retryBtn) retryBtn.style.display = 'none';
            
            const scanHint = document.getElementById('posScanHint');
            if (scanHint) { scanHint.textContent = 'Position barcode within the green box'; scanHint.style.color = '#64748b'; }
            const timerElement = document.getElementById('posScanTimer');
            if (timerElement) timerElement.textContent = '';
            
            scannerModal.style.display = 'flex';
            this.isScanning = true;
            
            if (typeof Html5Qrcode === 'undefined') {
                scannerResult.innerHTML = '⚠️ Scanner library not loaded.';
                scannerResult.style.display = 'block';
                scannerResult.style.background = '#fef2f2'; scannerResult.style.color = '#991b1b';
                retryBtn.style.display = 'inline-block';
                scannerBtn.classList.remove('scanning');
                this.isScanning = false;
                return;
            }
            
            var self = this;
            this.updateScanTimer();
            
            if (this.html5QrCode) {
                this.html5QrCode.stop().then(function() { self.initScanner(); }).catch(function() { self.html5QrCode = null; self.initScanner(); });
            } else {
                this.initScanner();
            }
        },

        updateScanTimer: function() {
            if (!this.isScanning) return;
            const elapsed = Math.floor((Date.now() - this.scanStartTime) / 1000);
            const timerElement = document.getElementById('posScanTimer');
            if (timerElement) {
                timerElement.textContent = '⏱️ ' + Math.floor(elapsed/60) + ':' + (elapsed%60).toString().padStart(2,'0');
            }
            if (elapsed > 30) {
                const scanHint = document.getElementById('posScanHint');
                if (scanHint) { scanHint.textContent = '⚠️ No barcode detected. Auto-closing...'; scanHint.style.color = '#f59e0b'; }
                var self = this;
                setTimeout(function() { self.stopScanner(); }, 2000);
            } else {
                var self = this;
                this.scanTimeout = setTimeout(function() { self.updateScanTimer(); }, 1000);
            }
        },

        initScanner: function() {
            const scannerResult = document.getElementById('posScannerResult');
            const retryBtn = document.getElementById('posRetryScanner');
            const scannerBtn = document.getElementById('posBarcodeScannerBtn');
            var self = this;
            
            try { this.html5QrCode = new Html5Qrcode("posCameraPreview"); }
            catch(e) {
                scannerResult.innerHTML = '⚠️ Failed to initialize.';
                scannerResult.style.display = 'block';
                scannerResult.style.background = '#fef2f2'; scannerResult.style.color = '#991b1b';
                scannerBtn.classList.remove('scanning');
                retryBtn.style.display = 'inline-block';
                this.isScanning = false;
                return;
            }

            const config = {
                fps: 20, qrbox: { width: 300, height: 200 }, aspectRatio: 1.777, disableFlip: true,
                formatsToSupport: [Html5QrcodeSupportedFormats.CODE_128, Html5QrcodeSupportedFormats.CODE_39, Html5QrcodeSupportedFormats.EAN_13, Html5QrcodeSupportedFormats.EAN_8, Html5QrcodeSupportedFormats.UPC_A, Html5QrcodeSupportedFormats.UPC_E]
            };

            Html5Qrcode.getCameras().then(function(devices) {
                if (!devices || devices.length === 0) throw new Error("No camera found");
                const rearCamera = devices.find(function(d) { return d.label.toLowerCase().includes("back") || d.label.toLowerCase().includes("rear") || d.label.toLowerCase().includes("environment"); });
                return self.html5QrCode.start(rearCamera ? rearCamera.id : devices[0].id, config,
                    function(decodedText, decodedResult) { self.onScanSuccess(decodedText, decodedResult); },
                    function(error) { self.onScanFailure(error); });
            }).then(function() {
                console.log("✅ POS Scanner started");
            }).catch(function(err) {
                console.error("Camera error:", err);
                scannerResult.innerHTML = '⚠️ Cannot access camera. Please allow permission.';
                scannerResult.style.display = 'block';
                scannerResult.style.background = '#fef2f2'; scannerResult.style.color = '#991b1b';
                scannerBtn.classList.remove('scanning');
                retryBtn.style.display = 'inline-block';
                self.isScanning = false;
            });
        },

        onScanSuccess: function(decodedText, decodedResult) {
            if (!this.isScanning) return;
            const now = Date.now();
            if (decodedText === this.lastScannedCode && now - this.lastScanTime < 3000) return;
            this.lastScannedCode = decodedText;
            this.lastScanTime = now;
            this.isScanning = false;
            if (this.scanTimeout) { clearTimeout(this.scanTimeout); this.scanTimeout = null; }
            
            const scannerResult = document.getElementById('posScannerResult');
            const scannerBtn = document.getElementById('posBarcodeScannerBtn');
            this.playBeepSound();
            if (navigator.vibrate) navigator.vibrate([100, 50, 100]);
            
            var self = this;
            fetch('api/products.php?barcode=' + encodeURIComponent(decodedText))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success && data.product) {
                        self.addToCart(data.product.product_id, data.product.name, data.product.price, data.product.quantity);
                        scannerResult.innerHTML = '<div style="font-size:2rem;">✅</div><div style="font-weight:700;">Product Found!</div><div>' + escapeHtml(data.product.name) + '</div><div style="font-size:1.2rem;color:#059669;">' + self.currencySymbol + parseFloat(data.product.price).toFixed(2) + '</div><div style="font-family:Courier New;background:#065f46;color:white;padding:5px 12px;border-radius:6px;display:inline-block;">' + decodedText + '</div><br><small>Added to cart ✓</small>';
                        scannerResult.style.background = '#f0fdf4'; scannerResult.style.color = '#166534';
                    } else {
                        scannerResult.innerHTML = '<div style="font-size:2rem;">🔍</div><div style="font-weight:700;">Barcode Scanned</div><div style="font-family:Courier New;background:#1e293b;color:white;padding:5px 12px;border-radius:6px;display:inline-block;">' + decodedText + '</div><br><small style="color:#ef4444;">No product found</small>';
                        scannerResult.style.background = '#fef2f2'; scannerResult.style.color = '#991b1b';
                    }
                    scannerResult.style.display = 'block';
                    if (scannerBtn) { scannerBtn.classList.remove('scanning'); scannerBtn.innerHTML = '📷'; }
                    if (self.html5QrCode) {
                        self.html5QrCode.stop().then(function() { setTimeout(function() { self.closeScannerModal(); }, 1500); }).catch(function() { setTimeout(function() { self.closeScannerModal(); }, 1500); });
                    }
                }).catch(function() {
                    scannerResult.innerHTML = '⚠️ Error searching product.';
                    scannerResult.style.display = 'block';
                    scannerResult.style.background = '#fef2f2'; scannerResult.style.color = '#991b1b';
                    if (scannerBtn) scannerBtn.classList.remove('scanning');
                    setTimeout(function() { self.closeScannerModal(); }, 2000);
                });
        },

        onScanFailure: function(error) {
            if (error && typeof error === 'string' && error.indexOf('NotFound') === -1 && error.indexOf('No barcode') === -1) {
                console.warn('Scan error:', error);
            }
        },

        stopScanner: function() {
            this.isScanning = false;
            if (this.scanTimeout) { clearTimeout(this.scanTimeout); this.scanTimeout = null; }
            var self = this;
            if (this.html5QrCode && this.html5QrCode.isScanning) {
                this.html5QrCode.stop().then(function() { self.cleanupScanner(); }).catch(function() { self.cleanupScanner(); });
            } else {
                this.cleanupScanner();
            }
        },

        cleanupScanner: function() {
            const scannerBtn = document.getElementById('posBarcodeScannerBtn');
            if (scannerBtn) { scannerBtn.classList.remove('scanning'); scannerBtn.innerHTML = '📷'; }
            if (this.html5QrCode) { try { this.html5QrCode.clear(); } catch(e) {} this.html5QrCode = null; }
            this.closeScannerModal();
        },

        closeScannerModal: function() {
            const scannerModal = document.getElementById('posScannerModal');
            if (scannerModal) scannerModal.style.display = 'none';
            const timerElement = document.getElementById('posScanTimer');
            if (timerElement) timerElement.textContent = '';
            const scanHint = document.getElementById('posScanHint');
            if (scanHint) { scanHint.textContent = 'Position barcode within the green box'; scanHint.style.color = '#64748b'; }
            this.isScanning = false;
        }
    };

    // Helper functions
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function escapeJs(text) {
        if (!text) return '';
        return text.replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '\\"');
    }

    // Event Listeners
    document.addEventListener('DOMContentLoaded', function() {
        POS.loadProducts();
        
        var searchInput = document.getElementById('productSearch');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                var activeCategory = document.querySelector('.category-chip.active');
                var category = activeCategory ? activeCategory.dataset.category : 'all';
                POS.loadProducts(category, this.value);
            });
        }
        
        // Barcode scanner button
        var posScannerBtn = document.getElementById('posBarcodeScannerBtn');
        if (posScannerBtn) {
            posScannerBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                POS.startScanner();
            });
        }

        // Scanner modal close on overlay click
        var posScannerModal = document.getElementById('posScannerModal');
        if (posScannerModal) {
            posScannerModal.addEventListener('click', function(e) {
                if (e.target === this) POS.stopScanner();
            });
        }

        // Keyboard shortcut
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.shiftKey && e.key === 'B') {
                e.preventDefault();
                POS.startScanner();
            }
        });
        
        var categoryFilter = document.getElementById('categoryFilter');
        if (categoryFilter) {
            categoryFilter.addEventListener('click', function(e) {
                if (e.target.classList.contains('category-chip')) {
                    document.querySelectorAll('.category-chip').forEach(function(chip) { chip.classList.remove('active'); });
                    e.target.classList.add('active');
                    POS.loadProducts(e.target.dataset.category, document.getElementById('productSearch').value);
                }
            });
        }
        
        var paymentMethods = document.getElementById('paymentMethods');
        if (paymentMethods) {
            paymentMethods.addEventListener('click', function(e) {
                if (e.target.classList.contains('payment-method-btn')) {
                    document.querySelectorAll('.payment-method-btn').forEach(function(btn) { btn.classList.remove('active'); });
                    e.target.classList.add('active');
                    var method = e.target.dataset.method;
                    document.getElementById('cashPaymentSection').style.display = method === 'cash' ? 'block' : 'none';
                    document.getElementById('cardPaymentSection').style.display = method !== 'cash' ? 'block' : 'none';
                }
            });
        }
        
        document.getElementById('amountReceived')?.addEventListener('input', function() { POS.calculateChange(); });
        document.getElementById('completeSaleBtn')?.addEventListener('click', function() { POS.completeSale(); });
        
        document.getElementById('voidSaleBtn')?.addEventListener('click', function() {
            if (POS.cart.length > 0) {
                POS.showWarningModal('Void Current Sale?', 
                    '<p>You have <strong>' + POS.cart.length + ' item(s)</strong> in the cart.</p>' +
                    '<p>Total: <strong>' + POS.currencySymbol + POS.calculateTotals().total.toFixed(2) + '</strong></p>' +
                    '<p style="color:#ef4444;">This will clear all items from the cart.</p>', '🗑️');
                var modal = document.getElementById('alertModal');
                if (modal) {
                    var footer = modal.querySelector('.modal-footer');
                    if (footer) {
                        footer.innerHTML = '<button onclick="document.getElementById(\'alertModal\').style.display=\'none\'" style="padding:8px 16px;background:#6b7280;color:white;border:none;border-radius:6px;cursor:pointer;margin-right:8px;">Cancel</button>' +
                            '<button onclick="POS.cart=[];POS.updateCart();document.getElementById(\'alertModal\').style.display=\'none\';POS.showToast(\'Cart cleared\',\'success\');" style="padding:8px 24px;background:#dc2626;color:white;border:none;border-radius:6px;cursor:pointer;font-weight:600;">Yes, Void Sale</button>';
                    }
                }
            }
        });
        
        var productSearch = document.getElementById('productSearch');
        if (productSearch) {
            productSearch.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    var barcode = this.value.trim();
                    if (barcode) {
                        fetch('api/products.php?barcode=' + encodeURIComponent(barcode))
                            .then(function(r) { return r.json(); })
                            .then(function(data) {
                                if (data.success && data.product) {
                                    POS.addToCart(data.product.product_id, data.product.name, data.product.price, data.product.quantity);
                                    document.getElementById('productSearch').value = '';
                                } else { 
                                    POS.showWarningModal('Product Not Found', 'No product found with barcode: <strong>' + escapeHtml(barcode) + '</strong>', '🔍');
                                }
                            })
                            .catch(function() { POS.showToast('Error searching product', 'error'); });
                    }
                }
            });
        }
    });

    // ==========================================
    // CART PROTECTION
    // ==========================================
    var _posConfirmedLeave = false;

    document.addEventListener('DOMContentLoaded', function() {
        var sidebarLinks = document.querySelectorAll('.sidebar-nav a, .sidebar-footer a, .topbar a');
        sidebarLinks.forEach(function(link) {
            link.addEventListener('click', function(e) {
                if (link.href.includes('pos.php') || link.href.includes('logout.php') || 
                    link.href.includes('login.php') || link.href.startsWith('javascript:') || link.getAttribute('onclick')) return;
                if (POS.cart.length > 0 && !_posConfirmedLeave) {
                    e.preventDefault(); e.stopPropagation();
                    showCartWarningModal(link.href);
                    return false;
                }
            });
        });
        
        window.addEventListener('beforeunload', function(e) {
            if (POS.cart.length > 0 && !_posConfirmedLeave) {
                e.preventDefault(); e.returnValue = 'You have items in your cart.';
                return e.returnValue;
            }
        });
    });

    function showCartWarningModal(targetUrl) {
        var modal = document.getElementById('alertModal') || (function() {
            var m = document.createElement('div'); m.id = 'alertModal'; m.className = 'modal-overlay';
            m.style.cssText = 'display:flex;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:10001;align-items:center;justify-content:center;';
            document.body.appendChild(m); return m;
        })();
        modal.innerHTML = '<div class="modal" style="max-width:450px;width:90%;background:white;border-radius:12px;">' +
            '<div class="modal-header" style="padding:20px;border-bottom:1px solid #fef3c7;background:#fffbeb;border-radius:12px 12px 0 0;"><h3 style="margin:0;color:#92400e;">⚠️ Active Cart Detected</h3></div>' +
            '<div class="modal-body" style="padding:20px;"><p>You have <strong>' + POS.cart.length + ' item(s)</strong> with total <strong>' + POS.currencySymbol + POS.calculateTotals().total.toFixed(2) + '</strong>.</p></div>' +
            '<div class="modal-footer" style="padding:15px 20px;display:flex;flex-direction:column;gap:8px;">' +
                '<button onclick="document.getElementById(\'alertModal\').style.display=\'none\'" style="padding:10px;background:#6b7280;color:white;border:none;border-radius:6px;cursor:pointer;">↩️ Stay on POS</button>' +
                '<button onclick="_posConfirmedLeave=true;POS.cart=[];POS.updateCart();document.getElementById(\'alertModal\').style.display=\'none\';window.location.href=\'' + targetUrl + '\'" style="padding:10px;background:#f59e0b;color:white;border:none;border-radius:6px;cursor:pointer;">🗑️ Void Cart & Leave</button>' +
            '</div></div>';
        modal.style.display = 'flex';
        modal.onclick = function(e) { if (e.target === modal) modal.style.display = 'none'; };
    }

    // Logout protection
    document.addEventListener('DOMContentLoaded', function() {
        var logoutLink = document.getElementById('logoutLink');
        if (logoutLink) {
            logoutLink.addEventListener('click', function(e) {
                if (POS.cart.length > 0 && !_posConfirmedLeave) {
                    e.preventDefault(); e.stopPropagation();
                    var modal = document.getElementById('alertModal') || (function() {
                        var m = document.createElement('div'); m.id = 'alertModal'; m.className = 'modal-overlay';
                        m.style.cssText = 'display:flex;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:10001;align-items:center;justify-content:center;';
                        document.body.appendChild(m); return m;
                    })();
                    modal.innerHTML = '<div class="modal" style="max-width:450px;width:90%;background:white;border-radius:12px;">' +
                        '<div class="modal-header" style="padding:20px;border-bottom:1px solid #fee2e2;background:#fef2f2;border-radius:12px 12px 0 0;"><h3 style="margin:0;color:#991b1b;">🚪 Cannot Logout</h3></div>' +
                        '<div class="modal-body" style="padding:20px;"><p>You have <strong>' + POS.cart.length + ' item(s)</strong>. Complete or void the sale first.</p></div>' +
                        '<div class="modal-footer" style="padding:15px 20px;display:flex;gap:8px;justify-content:flex-end;">' +
                            '<button onclick="_posConfirmedLeave=true;POS.cart=[];POS.updateCart();document.getElementById(\'alertModal\').style.display=\'none\';window.location.href=\'<?php echo BASE_URL; ?>logout.php\'" style="padding:10px;background:#f59e0b;color:white;border:none;border-radius:6px;cursor:pointer;">🗑️ Void & Logout</button>' +
                            '<button onclick="document.getElementById(\'alertModal\').style.display=\'none\'" style="padding:10px 24px;background:#059669;color:white;border:none;border-radius:6px;cursor:pointer;">↩️ Return</button>' +
                        '</div></div>';
                    modal.style.display = 'flex';
                    modal.onclick = function(e) { if (e.target === modal) modal.style.display = 'none'; };
                    return false;
                }
            });
        }
    });
    </script>
</body>
</html>