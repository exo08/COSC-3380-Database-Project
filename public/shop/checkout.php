<?php
// Prevent any output before headers
ob_start();

session_start();
require_once __DIR__ . '/../app/db.php';

$db = db();
$current_page = 'shop/checkout.php';
$page_title = 'Checkout';

// Check if user is logged in and get member_id removed check for expiration here
$member_id = null;

if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'member') {
    $member_id = $_SESSION['member_id'] ?? null;
}

// ajax endpoint to validate member discount BEFORE purchase
// test insert to trigger the validation without committing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['validate_member'])) {
    header('Content-Type: application/json');
    
    $validate_member_id = intval($_POST['member_id']);
    $subtotal = floatval($_POST['subtotal']);
    $discount = floatval($_POST['discount']);
    $total = floatval($_POST['total']);
    
    try {
        $db->begin_transaction();
        
        // test INSERT trigger will write to SALE_VALIDATION_MESSAGES
        $test_stmt = $db->prepare("
            INSERT INTO SALE (sale_date, member_id, visitor_id, total_amount, discount_amount, payment_method)
            VALUES (NOW(), ?, NULL, ?, ?, 1)
        ");
        
        $test_stmt->bind_param("idd", $validate_member_id, $total, $discount);
        $test_stmt->execute();
        
        $test_sale_id = $db->insert_id;
        
        // retrieve the message that the trigger wrote
        $msg_stmt = $db->prepare("
            SELECT message, message_type 
            FROM SALE_VALIDATION_MESSAGES 
            WHERE member_id = ? 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $msg_stmt->bind_param("i", $validate_member_id);
        $msg_stmt->execute();
        $msg_result = $msg_stmt->get_result();
        
        $response = ['success' => true];
        
        if ($msg_result->num_rows > 0) {
            $msg_data = $msg_result->fetch_assoc();
            $response['message'] = $msg_data['message'];
            $response['message_type'] = $msg_data['message_type'];
            
            // If warning member is expired
            if ($msg_data['message_type'] === 'warning') {
                $response['needs_confirmation'] = true;
            }
        }
        
        // rollback test transaction dont keep the test insert
        $db->rollback();
        
        echo json_encode($response);
        exit();
        
    } catch (Exception $e) {
        $db->rollback();
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        exit();
    }
}

// handle actual purchase after user confirms
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_purchase'])) {
    error_log("=== PURCHASE REQUEST RECEIVED ===");
    error_log("POST keys: " . implode(', ', array_keys($_POST)));
    
    $cart_data_raw = $_POST['cart_data'] ?? '';
    error_log("Cart data raw: " . substr($cart_data_raw, 0, 200));
    
    $cart_data = json_decode($cart_data_raw, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON ERROR: " . json_last_error_msg());
        $_SESSION['error_message'] = "Invalid cart data: " . json_last_error_msg();
        header("Location: checkout.php");
        exit();
    }
    
    error_log("Cart data items: " . count($cart_data));
    
    if (empty($cart_data)) {
        error_log("ERROR: Cart is empty after decode");
        $_SESSION['error_message'] = "Cart is empty!";
        header("Location: checkout.php");
        exit();
    }
    
    try {
        error_log("Starting transaction...");
        $db->begin_transaction();
        
        // Calculate totals
        $subtotal = 0;
        foreach ($cart_data as $item) {
            $price = floatval($item['price'] ?? 0);
            $quantity = intval($item['quantity'] ?? 0);
            $subtotal += $price * $quantity;
        }
        
        error_log("Subtotal: " . $subtotal);
        
        // trigger will subtract discount if member is active
        // send the discount php calculated but trigger can override it
        $is_member = !empty($member_id);
        $discount_amount = $is_member ? ($subtotal * 0.10) : 0;
        $total_amount = $subtotal;  // send full subtotal
        
        error_log("Is member: " . ($is_member ? 'YES' : 'NO'));
        error_log("Member ID: " . ($member_id ?? 'NULL'));
        error_log("Discount suggested: " . $discount_amount);
        error_log("Total (full subtotal): " . $total_amount);
        
        if (!isset($_POST['payment_method'])) {
            throw new Exception("Payment method not set");
        }
        
        $payment_method = intval($_POST['payment_method']);
        error_log("Payment method: " . $payment_method);
        
        // Insert into SALE table
        error_log("Preparing SALE INSERT...");
        $sale_stmt = $db->prepare("
            INSERT INTO SALE (sale_date, member_id, visitor_id, total_amount, discount_amount, payment_method)
            VALUES (NOW(), ?, NULL, ?, ?, ?)
        ");
        
        if (!$sale_stmt) {
            throw new Exception("Prepare failed: " . $db->error);
        }
        
        $sale_member_id = $member_id;
        
        // Bind parameters
        if (!$sale_stmt->bind_param("iddi", $sale_member_id, $total_amount, $discount_amount, $payment_method)) {
            throw new Exception("Bind failed: " . $sale_stmt->error);
        }
        
        // Execute
        error_log("Executing SALE INSERT...");
        if (!$sale_stmt->execute()) {
            throw new Exception("Execute failed: " . $sale_stmt->error);
        }
        
        $sale_id = $db->insert_id;
        error_log("SALE inserted! ID: " . $sale_id);
        
        // Insert each item into SALE_ITEM table
        error_log("Preparing SALE_ITEM INSERTs...");
        $sale_item_stmt = $db->prepare("
            INSERT INTO SALE_ITEM (sale_id, item_id, quantity, price_at_sale)
            VALUES (?, ?, ?, ?)
        ");
        
        if (!$sale_item_stmt) {
            throw new Exception("SALE_ITEM prepare failed: " . $db->error);
        }
        
        $item_count = 0;
        foreach ($cart_data as $item) {
            $item_id = intval($item['id'] ?? 0);
            $quantity = intval($item['quantity'] ?? 0);
            $price = floatval($item['price'] ?? 0);
            
            error_log("  Item: id=$item_id, qty=$quantity, price=$price");
            
            $sale_item_stmt->bind_param("iiid", $sale_id, $item_id, $quantity, $price);
            
            if (!$sale_item_stmt->execute()) {
                throw new Exception("SALE_ITEM execute failed: " . $sale_item_stmt->error);
            }
            $item_count++;
        }
        
        error_log("Inserted $item_count SALE_ITEMs");
        
        // Get the trigger message
        if ($sale_member_id) {
            error_log("Fetching trigger message...");
            $msg_stmt = $db->prepare("
                SELECT message, message_type 
                FROM SALE_VALIDATION_MESSAGES 
                WHERE member_id = ? 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $msg_stmt->bind_param("i", $sale_member_id);
            $msg_stmt->execute();
            $msg_result = $msg_stmt->get_result();
            
            if ($msg_result->num_rows > 0) {
                $msg_data = $msg_result->fetch_assoc();
                $_SESSION['trigger_message'] = $msg_data['message'];
                $_SESSION['trigger_message_type'] = $msg_data['message_type'];
                error_log("Trigger message: " . $msg_data['message']);
            } else {
                error_log("No trigger message found");
            }
        }
        
        error_log("Committing transaction...");
        $db->commit();
        error_log("=== PURCHASE COMPLETE === Sale ID: $sale_id");
        
        // Clear cart and redirect
        $_SESSION['success_message'] = "Purchase completed successfully! Order #" . $sale_id;
        $_SESSION['last_sale_id'] = $sale_id;
        
        error_log("Redirecting to order-confirmation.php");
        
        // Clean output buffer and redirect was causing haeder issues
        ob_end_clean();
        header("Location: order-confirmation.php");
        exit();
        
    } catch (Exception $e) {
        error_log("=== PURCHASE FAILED ===");
        error_log("Exception: " . $e->getMessage());
        error_log("File: " . $e->getFile() . " Line: " . $e->getLine());
        error_log("Trace: " . $e->getTraceAsString());
        
        $db->rollback();
        error_log("Transaction rolled back");
        
        $_SESSION['error_message'] = "Purchase failed: " . $e->getMessage();
        
        ob_end_clean();
        header("Location: checkout.php");
        exit();
    }
}

include __DIR__ . '/../templates/header.php';
?>

<style>
    :root {
        --primary-color: #2c3e50;
        --success-color: #27ae60;
        --error-color: #e74c3c;
    }

    .checkout-container {
        max-width: 1200px;
        margin: 2rem auto;
        padding: 0 1rem;
    }

    .checkout-card {
        background: white;
        border-radius: 15px;
        padding: 2rem;
        box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        margin-bottom: 2rem;
    }

    .cart-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem;
        border-bottom: 1px solid #e0e0e0;
    }

    .cart-item:last-child {
        border-bottom: none;
    }

    .item-details {
        flex-grow: 1;
    }

    .item-name {
        font-weight: 600;
        font-size: 1.1rem;
        color: #2c3e50;
    }

    .item-quantity {
        color: #666;
        font-size: 0.9rem;
    }

    .item-price {
        font-weight: 700;
        font-size: 1.25rem;
        color: #27ae60;
        text-align: right;
    }

    .quantity-controls {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin: 0.5rem 0;
    }

    .quantity-btn {
        width: 30px;
        height: 30px;
        border-radius: 5px;
        border: 1px solid #ddd;
        background: white;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
        color: #333;
    }

    .quantity-btn:hover {
        background: #27ae60;
        color: white;
        border-color: #27ae60;
    }

    .quantity-display {
        min-width: 40px;
        text-align: center;
        font-weight: 600;
    }

    .remove-btn {
        color: #e74c3c;
        cursor: pointer;
        padding: 0.5rem 1rem;
        border-radius: 5px;
        transition: all 0.2s;
        background: transparent;
        border: none;
    }

    .remove-btn:hover {
        background: #e74c3c;
        color: white;
    }

    .summary-row {
        display: flex;
        justify-content: space-between;
        padding: 0.75rem 0;
        font-size: 1.1rem;
    }

    .summary-row.total {
        border-top: 2px solid #e0e0e0;
        font-weight: 700;
        font-size: 1.5rem;
        color: #2c3e50;
        margin-top: 1rem;
        padding-top: 1rem;
    }

    .discount-row {
        color: #27ae60;
        font-weight: 600;
    }

    .payment-option {
        border: 2px solid #e0e0e0;
        border-radius: 10px;
        padding: 1rem;
        margin-bottom: 1rem;
        cursor: pointer;
        transition: all 0.3s;
    }

    .payment-option:hover {
        border-color: #27ae60;
    }

    .payment-option input[type="radio"] {
        margin-right: 0.75rem;
    }

    .payment-option.selected {
        border-color: #27ae60;
        background: rgba(39, 174, 96, 0.05);
    }

    .checkout-btn {
        background: #27ae60;
        color: white;
        border: none;
        padding: 1rem 2rem;
        border-radius: 10px;
        font-size: 1.25rem;
        font-weight: 600;
        width: 100%;
        cursor: pointer;
        transition: all 0.3s;
    }

    .checkout-btn:hover {
        background: #229954;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
    }

    .checkout-btn:disabled {
        background: #ccc;
        cursor: not-allowed;
        transform: none;
    }

    .empty-cart {
        text-align: center;
        padding: 4rem 2rem;
    }

    .empty-cart i {
        font-size: 5rem;
        color: #ccc;
        margin-bottom: 1rem;
    }

    /* Toast notification styles */
    .toast-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 10000;
        display: none;
    }

    .toast-container {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        z-index: 10001;
        display: none;
    }

    .toast-content {
        background: white;
        border-radius: 15px;
        padding: 2rem;
        max-width: 500px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        animation: slideDown 0.3s ease-out;
        position: relative;
        pointer-events: auto;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .toast-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .toast-icon {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }

    .toast-icon.warning {
        background: #fff3cd;
        color: #856404;
    }

    .toast-icon.error {
        background: #f8d7da;
        color: #721c24;
    }

    .toast-title {
        font-size: 1.3rem;
        font-weight: 700;
        color: #2c3e50;
    }

    .toast-message {
        font-size: 1rem;
        line-height: 1.6;
        color: #555;
        margin-bottom: 1.5rem;
    }

    .toast-buttons {
        display: flex;
        gap: 1rem;
    }

    .toast-btn {
        flex: 1;
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        font-weight: 600;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
        font-size: 1rem;
        pointer-events: auto;
        outline: none;
    }

    .toast-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .toast-btn:active {
        transform: translateY(0);
    }

    .toast-btn-primary {
        background: #27ae60;
        color: white;
    }

    .toast-btn-primary:hover {
        background: #229954;
    }

    .toast-btn-secondary {
        background: #3498db;
        color: white;
    }

    .toast-btn-secondary:hover {
        background: #2980b9;
    }

    .toast-btn-cancel {
        background: #95a5a6;
        color: white;
    }

    .toast-btn-cancel:hover {
        background: #7f8c8d;
    }

    .toast-btn-ok {
        background: #27ae60;
        color: white;
        width: 100%;
    }

    .toast-btn-ok:hover {
        background: #229954;
    }

    /* Simple error toast */
    .simple-toast {
        position: fixed;
        top: 20px;
        right: 20px;
        background: white;
        border-radius: 10px;
        padding: 1rem 1.5rem;
        box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        z-index: 10002;
        display: flex;
        align-items: center;
        gap: 1rem;
        min-width: 300px;
        animation: slideInRight 0.3s ease-out;
    }

    .simple-toast.hiding {
        animation: slideOutRight 0.3s ease-out forwards;
    }

    @keyframes slideInRight {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }

    .simple-toast-icon {
        background: #e74c3c;
        color: white;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
    }

    .simple-toast-content {
        flex-grow: 1;
    }

    .simple-toast-title {
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 0.25rem;
    }

    .simple-toast-message {
        font-size: 0.875rem;
        color: #666;
    }
</style>

<!-- Toast notification -->
<div class="toast-overlay" id="toast-overlay"></div>
<div class="toast-container" id="toast-container">
    <div class="toast-content">
        <div class="toast-header">
            <div class="toast-icon warning" id="toast-icon">
                <i class="bi bi-exclamation-triangle-fill"></i>
            </div>
            <div class="toast-title" id="toast-title">Membership Status</div>
        </div>
        <div class="toast-message" id="toast-message"></div>
        <div class="toast-buttons" id="toast-buttons">
            <!-- buttons inserted dynamically -->
        </div>
    </div>
</div>

<div class="checkout-container">
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['error_message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <h1 class="mb-4"><i class="bi bi-cart-check"></i> Checkout</h1>

    <div class="row">
        <!-- Cart Items -->
        <div class="col-lg-7">
            <div class="checkout-card">
                <h3 class="mb-3">Your Cart</h3>
                <div id="cart-items">
                    <div class="empty-cart">
                        <i class="bi bi-cart-x"></i>
                        <h4>Your cart is empty</h4>
                        <p class="text-muted">Add some items to get started!</p>
                        <a href="../shop.php" class="btn btn-success">Browse Shop</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Order Summary -->
        <div class="col-lg-5">
            <div class="checkout-card">
                <h3 class="mb-3">Order Summary</h3>

                <div class="summary-row">
                    <span>Subtotal:</span>
                    <span id="subtotal-display">$0.00</span>
                </div>

                <div class="summary-row discount-row" id="discount-row" style="display: none;">
                    <span>Member Discount (10%):</span>
                    <span id="discount-display">-$0.00</span>
                </div>

                <div class="summary-row total">
                    <span>Total:</span>
                    <span id="total-display">$0.00</span>
                </div>

                <form method="POST" action="" id="checkout-form" style="display: none;">
                    <input type="hidden" name="cart_data" id="cart-data-input">
                    <input type="hidden" name="complete_purchase" value="1">
                    
                    <h4 class="mt-4 mb-3">Payment Method</h4>
                    
                    <div class="payment-option" onclick="selectPayment(1, this)">
                        <input type="radio" name="payment_method" value="1" id="payment-1" required>
                        <label for="payment-1" style="cursor: pointer;">
                            <strong><i class="bi bi-credit-card"></i> Credit Card</strong>
                            <p class="mb-0 small text-muted">Visa, Mastercard, Amex</p>
                        </label>
                    </div>

                    <div class="payment-option" onclick="selectPayment(2, this)">
                        <input type="radio" name="payment_method" value="2" id="payment-2">
                        <label for="payment-2" style="cursor: pointer;">
                            <strong><i class="bi bi-wallet2"></i> Debit Card</strong>
                            <p class="mb-0 small text-muted">Direct bank debit</p>
                        </label>
                    </div>

                    <div class="payment-option" onclick="selectPayment(3, this)">
                        <input type="radio" name="payment_method" value="3" id="payment-3">
                        <label for="payment-3" style="cursor: pointer;">
                            <strong><i class="bi bi-cash"></i> Cash</strong>
                            <p class="mb-0 small text-muted">Pay at the counter</p>
                        </label>
                    </div>

                    <button type="button" onclick="handleCheckoutClick()" class="checkout-btn mt-4">
                        <i class="bi bi-check-circle"></i> Complete Purchase
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    let cart = JSON.parse(localStorage.getItem('museumCart') || '[]');
    const memberId = <?= $member_id ? $member_id : 'null' ?>;
    const isMember = memberId !== null;
    let memberDiscount = isMember ? 0.10 : 0;

    console.log('Member ID:', memberId);
    console.log('Is member:', isMember);

    function updateCartDisplay() {
        const cartItemsDiv = document.getElementById('cart-items');
        const checkoutForm = document.getElementById('checkout-form');
        const discountRow = document.getElementById('discount-row');
        
        if (cart.length === 0) {
            cartItemsDiv.innerHTML = `
                <div class="empty-cart">
                    <i class="bi bi-cart-x"></i>
                    <h4>Your cart is empty</h4>
                    <p class="text-muted">Add some items to get started!</p>
                    <a href="../shop.php" class="btn btn-success">Browse Shop</a>
                </div>
            `;
            checkoutForm.style.display = 'none';
            return;
        }

        checkoutForm.style.display = 'block';
        
        let html = '';
        let subtotal = 0;

        cart.forEach((item, index) => {
            const itemTotal = item.price * item.quantity;
            subtotal += itemTotal;
            
            html += `
                <div class="cart-item">
                    <div class="item-details">
                        <div class="item-name">${escapeHtml(item.name)}</div>
                        <div class="item-quantity">$${item.price.toFixed(2)} each</div>
                        <div class="quantity-controls">
                            <button type="button" class="quantity-btn" onclick="updateQuantity(${index}, -1)">
                                <i class="bi bi-dash"></i>
                            </button>
                            <span class="quantity-display">${item.quantity}</span>
                            <button type="button" class="quantity-btn" onclick="updateQuantity(${index}, 1)">
                                <i class="bi bi-plus"></i>
                            </button>
                            <button type="button" class="remove-btn" onclick="removeItem(${index})">
                                <i class="bi bi-trash"></i> Remove
                            </button>
                        </div>
                    </div>
                    <div class="item-price">
                        $${itemTotal.toFixed(2)}
                    </div>
                </div>
            `;
        });

        cartItemsDiv.innerHTML = html;

        // Update summary
        const discountAmount = isMember ? (subtotal * memberDiscount) : 0;
        const total = subtotal - discountAmount;

        document.getElementById('subtotal-display').textContent = '$' + subtotal.toFixed(2);
        
        if (isMember) {
            discountRow.style.display = 'flex';
            document.getElementById('discount-display').textContent = '-$' + discountAmount.toFixed(2);
        } else {
            discountRow.style.display = 'none';
        }
        
        document.getElementById('total-display').textContent = '$' + total.toFixed(2);

        // Update hidden input with cart data
        document.getElementById('cart-data-input').value = JSON.stringify(cart);
    }

    function updateQuantity(index, change) {
        if (change < 0 && cart[index].quantity === 1) {
            removeItem(index);
            return;
        }

        const newQuantity = cart[index].quantity + change;
        
        if (newQuantity > cart[index].maxStock) {
            showErrorToast('Cannot add more items. Maximum stock reached.', 'Stock Limit');
            return;
        }

        if (newQuantity > 0) {
            cart[index].quantity = newQuantity;
            localStorage.setItem('museumCart', JSON.stringify(cart));
            updateCartDisplay();
        }
    }

    function removeItem(index) {
        cart.splice(index, 1);
        localStorage.setItem('museumCart', JSON.stringify(cart));
        updateCartDisplay();
    }

    function selectPayment(value, element) {
        document.querySelectorAll('.payment-option').forEach(el => {
            el.classList.remove('selected');
        });
        element.classList.add('selected');
        document.getElementById('payment-' + value).checked = true;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Handle checkout click validated w trigger first
    async function handleCheckoutClick() {
        console.log('handleCheckoutClick called');
        
        // Check if cart is empty
        if (cart.length === 0) {
            showErrorToast('Your cart is empty!', 'Cart Empty');
            return;
        }
        
        // Check if payment method is selected
        const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
        if (!paymentMethod) {
            showErrorToast('Please select a payment method before completing your purchase', 'Payment Method Required');
            return;
        }
        
        console.log('Payment method selected:', paymentMethod.value);
        console.log('Is member:', isMember);
        console.log('Member ID:', memberId);
        console.log('Cart items:', cart.length);

        // If a member alidate with trigger first
        if (isMember && memberId) {
            try {
                console.log('Member checkout - validating with trigger first');
                
                // Calculate totals
                let subtotal = 0;
                cart.forEach(item => {
                    subtotal += item.price * item.quantity;
                });
                
                const discount = subtotal * memberDiscount;
                const total = subtotal - discount;
                
                console.log('Subtotal:', subtotal, 'Discount:', discount, 'Total:', total);

                // ajax validation endpoint
                const formData = new FormData();
                formData.append('validate_member', '1');
                formData.append('member_id', memberId);
                formData.append('subtotal', subtotal);
                formData.append('discount', discount);
                formData.append('total', total);

                console.log('Sending AJAX validation request...');
                const response = await fetch('checkout.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                console.log('Validation response:', data);

                if (data.success && data.needs_confirmation) {
                    // toast warning member is expired
                    console.log('Member expired - showing toast');
                    showMembershipToast(data.message, data.message_type);
                } else {
                    // no warning continue 
                    console.log('Member active - submitting form directly');
                    document.getElementById('checkout-form').submit();
                }

            } catch (error) {
                console.error('Validation error:', error);
                showErrorToast('An error occurred. Please try again.', 'Error');
            }
        } else {
            // not a member continue 
            console.log('Guest checkout - submitting form directly');
            document.getElementById('checkout-form').submit();
        }
    }

    // auto dismiss error toast
    function showErrorToast(message, title = 'Error') {
        // remove existing simple toasts
        const existingToast = document.querySelector('.simple-toast');
        if (existingToast) {
            existingToast.remove();
        }

        // Create toast element
        const toast = document.createElement('div');
        toast.className = 'simple-toast';
        
        toast.innerHTML = `
            <div class="simple-toast-icon">
                <i class="bi bi-exclamation-circle-fill"></i>
            </div>
            <div class="simple-toast-content">
                <div class="simple-toast-title">${escapeHtml(title)}</div>
                <div class="simple-toast-message">${escapeHtml(message)}</div>
            </div>
        `;

        document.body.appendChild(toast);

        // auto remove after 3 seconds
        setTimeout(() => {
            toast.classList.add('hiding');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // membership expiration toast w 3 buttons for continue, renew, cancel
    function showMembershipToast(message, messageType) {
        const overlay = document.getElementById('toast-overlay');
        const container = document.getElementById('toast-container');
        const titleDiv = document.getElementById('toast-title');
        const messageDiv = document.getElementById('toast-message');
        const buttonsDiv = document.getElementById('toast-buttons');
        const iconDiv = document.getElementById('toast-icon');

        // Update icon to warning style
        iconDiv.className = 'toast-icon warning';
        iconDiv.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i>';

        titleDiv.textContent = 'Membership Status';
        messageDiv.textContent = message;

        // Create buttons with proper event handlers for membership toast
        buttonsDiv.innerHTML = `
            <button type="button" class="toast-btn toast-btn-primary" id="btn-continue">
                <i class="bi bi-check-circle"></i> Continue Purchase
            </button>
            <button type="button" class="toast-btn toast-btn-secondary" id="btn-renew">
                <i class="bi bi-arrow-repeat"></i> Renew Membership
            </button>
            <button type="button" class="toast-btn toast-btn-cancel" id="btn-cancel">
                <i class="bi bi-x-circle"></i> Cancel
            </button>
        `;

        overlay.style.display = 'block';
        container.style.display = 'block';

        // Add event listeners after buttons are created
        document.getElementById('btn-continue').addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            proceedWithPurchase();
        });

        document.getElementById('btn-renew').addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            goToRenewMembership();
        });

        document.getElementById('btn-cancel').addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            closeToast();
        });
    }

    function proceedWithPurchase() {
        console.log('proceedWithPurchase called');
        closeToast();
        // confirmed purchase without discount
        memberDiscount = 0; // no discount for expired member
        updateCartDisplay();
        console.log('Submitting form');
        document.getElementById('checkout-form').submit();
    }

    function goToRenewMembership() {
        console.log('goToRenewMembership called');
        closeToast();
        window.location.href = '../member/membership.php';
    }

    function closeToast() {
        console.log('closeToast called');
        document.getElementById('toast-overlay').style.display = 'none';
        document.getElementById('toast-container').style.display = 'none';
    }

    // Close toast when clicking overlay
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('toast-overlay').addEventListener('click', function() {
            closeToast();
        });
    });

    // Initialize display
    updateCartDisplay();
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>