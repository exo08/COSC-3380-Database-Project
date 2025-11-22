<?php
session_start();
require_once __DIR__ . '/../app/db.php';

$db = db();
$current_page = 'shop/checkout.php';
$page_title = 'Checkout';

// Check if user is logged in to determine if they can use member benefits
$is_member = false;
$member_discount = 0;
$member_id = null;

// Debug log session info
error_log("Session user_id: " . ($_SESSION['user_id'] ?? 'not set'));
error_log("Session role: " . ($_SESSION['role'] ?? 'not set'));
error_log("Session member_id: " . ($_SESSION['member_id'] ?? 'not set'));

if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'member') {
    $member_id = $_SESSION['member_id'] ?? null;
    
    if ($member_id) {
        // Check if member has active membership
        $stmt = $db->prepare("SELECT member_id, expiration_date FROM MEMBER WHERE member_id = ? AND expiration_date >= CURDATE()");
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $member_data = $result->fetch_assoc();
            $is_member = true;
            $member_discount = 0.10; // 10% discount
            error_log("Member is active! Expiration: " . $member_data['expiration_date']);
        } else {
            error_log("Member not found or expired");
        }
    } else {
        error_log("member_id not in session");
    }
} else {
    error_log("User not logged in as member");
}

// Handle form submission - Process checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_purchase'])) {
    $cart_data = json_decode($_POST['cart_data'], true);
    
    if (empty($cart_data)) {
        $_SESSION['error_message'] = "Cart is empty!";
        header("Location: checkout.php");
        exit();
    }
    
    try {
        $db->begin_transaction();
        
        // Calculate totals
        $subtotal = 0;
        foreach ($cart_data as $item) {
            $subtotal += $item['price'] * $item['quantity'];
        }
        
        $discount_amount = $is_member ? ($subtotal * $member_discount) : 0;
        $total_amount = $subtotal - $discount_amount;
        
        $payment_method = intval($_POST['payment_method']);
        
        // Insert into SALE table
        // The trigger will validate that member_id has an active membership
        $sale_stmt = $db->prepare("
            INSERT INTO SALE (sale_date, member_id, visitor_id, total_amount, discount_amount, payment_method)
            VALUES (NOW(), ?, NULL, ?, ?, ?)
        ");
        
        $sale_member_id = $is_member ? $member_id : null;
        $sale_stmt->bind_param("iddi", $sale_member_id, $total_amount, $discount_amount, $payment_method);
        $sale_stmt->execute();
        
        $sale_id = $db->insert_id;
        
        error_log("Created sale_id: $sale_id with member_id: " . ($sale_member_id ?? 'NULL') . ", discount: $discount_amount");
        
        // Insert each item into SALE_ITEM table
        // The trigger will automatically reduce stock
        $sale_item_stmt = $db->prepare("
            INSERT INTO SALE_ITEM (sale_id, item_id, quantity, price_at_sale)
            VALUES (?, ?, ?, ?)
        ");
        
        foreach ($cart_data as $item) {
            $item_id = intval($item['id']);
            $quantity = intval($item['quantity']);
            $price = floatval($item['price']);
            
            $sale_item_stmt->bind_param("iiid", $sale_id, $item_id, $quantity, $price);
            $sale_item_stmt->execute();
        }
        
        $db->commit();
        
        // Clear the cart and redirect to success page
        $_SESSION['success_message'] = "Purchase completed successfully! Order #" . $sale_id;
        $_SESSION['last_sale_id'] = $sale_id;
        
        echo "<script>
            localStorage.removeItem('museumCart');
            localStorage.removeItem('isMemberActive');
            window.location.href = 'order-confirmation.php';
        </script>";
        exit();
        
    } catch (Exception $e) {
        $db->rollback();
        error_log("Purchase error: " . $e->getMessage());
        $_SESSION['error_message'] = "Purchase failed: " . $e->getMessage();
        header("Location: checkout.php");
        exit();
    }
}

// Include header
include __DIR__ . '/../templates/header.php';
?>

<style>
    :root {
        --primary-color: #2c3e50;
        --success-color: #27ae60;
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

    .member-badge {
        background: linear-gradient(135deg, #f39c12, #e67e22);
        color: white;
        padding: 0.75rem 1.5rem;
        border-radius: 10px;
        display: inline-block;
        margin-bottom: 1rem;
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
</style>

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
                
                <?php if ($is_member): ?>
                    <div class="member-badge">
                        <i class="bi bi-star-fill"></i> Active Member - 10% Discount Applied!
                    </div>
                <?php endif; ?>

                <div class="summary-row">
                    <span>Subtotal:</span>
                    <span id="subtotal-display">$0.00</span>
                </div>

                <?php if ($is_member): ?>
                    <div class="summary-row discount-row">
                        <span>Member Discount (10%):</span>
                        <span id="discount-display">-$0.00</span>
                    </div>
                <?php endif; ?>

                <div class="summary-row total">
                    <span>Total:</span>
                    <span id="total-display">$0.00</span>
                </div>

                <form method="POST" action="" id="checkout-form" style="display: none;">
                    <input type="hidden" name="cart_data" id="cart-data-input">
                    
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

                    <button type="submit" name="complete_purchase" class="checkout-btn mt-4">
                        <i class="bi bi-check-circle"></i> Complete Purchase
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    let cart = JSON.parse(localStorage.getItem('museumCart') || '[]');
    const isMember = <?= $is_member ? 'true' : 'false' ?>;
    const memberDiscount = <?= $member_discount ?>;

    console.log('Is member:', isMember);
    console.log('Member discount:', memberDiscount);

    function updateCartDisplay() {
        const cartItemsDiv = document.getElementById('cart-items');
        const checkoutForm = document.getElementById('checkout-form');
        
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
            document.getElementById('discount-display').textContent = '-$' + discountAmount.toFixed(2);
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
            alert('Cannot add more items. Maximum stock reached.');
            return;
        }

        if (newQuantity > 0) {
            cart[index].quantity = newQuantity;
            localStorage.setItem('museumCart', JSON.stringify(cart));
            updateCartDisplay();
        }
    }

    function removeItem(index) {
        if (confirm('Remove this item from cart?')) {
            cart.splice(index, 1);
            localStorage.setItem('museumCart', JSON.stringify(cart));
            updateCartDisplay();
        }
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

    // Initialize display
    updateCartDisplay();
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>