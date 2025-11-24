<?php
session_start();
require_once __DIR__ . '/../app/db.php';

$db = db();
$current_page = 'shop/order-confirmation.php';
$page_title = 'Order Confirmation';

// redirect if no order was just placed
if (!isset($_SESSION['last_sale_id'])) {
    header("Location: ../shop.php");
    exit();
}

$sale_id = $_SESSION['last_sale_id'];

// fetch order details
$stmt = $db->prepare("
    SELECT 
        s.sale_id,
        s.sale_date,
        s.total_amount,
        s.discount_amount,
        s.member_id,
        CASE s.payment_method
            WHEN 1 THEN 'Credit Card'
            WHEN 2 THEN 'Debit Card'
            WHEN 3 THEN 'Cash'
            ELSE 'Unknown'
        END as payment_method_name
    FROM SALE s
    WHERE s.sale_id = ?
");

$stmt->bind_param("i", $sale_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "Order not found.";
    header("Location: ../shop.php");
    exit();
}

$order = $result->fetch_assoc();
$stmt->close();

// Fetch order items
$items_stmt = $db->prepare("
    SELECT 
        si.quantity,
        si.price_at_sale,
        sh.item_name,
        (si.quantity * si.price_at_sale) as item_total
    FROM SALE_ITEM si
    JOIN SHOP_ITEM sh ON si.item_id = sh.item_id
    WHERE si.sale_id = ?
");

$items_stmt->bind_param("i", $sale_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();
$items = $items_result->fetch_all(MYSQLI_ASSOC);
$items_stmt->close();

// retrieve trigger message if available
$trigger_message = $_SESSION['trigger_message'] ?? null;
$trigger_message_type = $_SESSION['trigger_message_type'] ?? 'info';

// clear the trigger message from session after retrieving
unset($_SESSION['trigger_message']);
unset($_SESSION['trigger_message_type']);

include __DIR__ . '/../templates/header.php';
?>

<style>
    .confirmation-container {
        max-width: 900px;
        margin: 2rem auto;
        padding: 0 1rem;
    }

    .confirmation-card {
        background: white;
        border-radius: 15px;
        padding: 2.5rem;
        box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        margin-bottom: 2rem;
    }

    .success-icon {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, #27ae60, #229954);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem;
        animation: scaleIn 0.5s ease-out;
    }

    .success-icon i {
        font-size: 2.5rem;
        color: white;
    }

    @keyframes scaleIn {
        from {
            transform: scale(0);
            opacity: 0;
        }
        to {
            transform: scale(1);
            opacity: 1;
        }
    }

    .order-number {
        background: linear-gradient(135deg, #3498db, #2980b9);
        color: white;
        padding: 1rem 2rem;
        border-radius: 10px;
        display: inline-block;
        font-size: 1.25rem;
        font-weight: 600;
        margin: 1rem 0;
    }

    .order-detail-row {
        display: flex;
        justify-content: space-between;
        padding: 0.75rem 0;
        border-bottom: 1px solid #e0e0e0;
    }

    .order-detail-row:last-child {
        border-bottom: none;
    }

    .order-detail-label {
        font-weight: 600;
        color: #555;
    }

    .order-item {
        display: flex;
        justify-content: space-between;
        padding: 1rem;
        background: #f8f9fa;
        border-radius: 8px;
        margin-bottom: 0.75rem;
    }

    .item-info {
        flex-grow: 1;
    }

    .item-name {
        font-weight: 600;
        font-size: 1.1rem;
        color: #2c3e50;
        margin-bottom: 0.25rem;
    }

    .item-quantity {
        color: #666;
        font-size: 0.9rem;
    }

    .item-price {
        font-weight: 700;
        font-size: 1.25rem;
        color: #27ae60;
    }

    .total-section {
        background: linear-gradient(135deg, #ecf0f1, #bdc3c7);
        padding: 1.5rem;
        border-radius: 10px;
        margin-top: 1.5rem;
    }

    .total-row {
        display: flex;
        justify-content: space-between;
        padding: 0.5rem 0;
        font-size: 1.1rem;
    }

    .total-row.discount {
        color: #27ae60;
        font-weight: 600;
    }

    .total-row.final {
        font-weight: 700;
        font-size: 1.75rem;
        color: #2c3e50;
        border-top: 2px solid #7f8c8d;
        padding-top: 1rem;
        margin-top: 0.5rem;
    }

    .action-buttons {
        display: flex;
        gap: 1rem;
        margin-top: 2rem;
    }

    .btn-custom {
        flex: 1;
        padding: 1rem 2rem;
        border-radius: 10px;
        font-weight: 600;
        font-size: 1.1rem;
        transition: all 0.3s;
        text-decoration: none;
        text-align: center;
    }

    .btn-primary-custom {
        background: #27ae60;
        color: white;
    }

    .btn-primary-custom:hover {
        background: #229954;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
    }

    .btn-secondary-custom {
        background: #3498db;
        color: white;
    }

    .btn-secondary-custom:hover {
        background: #2980b9;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
    }

    .trigger-alert {
        border-left: 4px solid;
        padding: 1rem 1.5rem;
        margin: 1.5rem 0;
        border-radius: 8px;
        animation: slideIn 0.5s ease-out;
    }

    .trigger-alert.warning {
        background: #fff3cd;
        border-color: #ffc107;
        color: #856404;
    }

    .trigger-alert.info {
        background: #d1ecf1;
        border-color: #17a2b8;
        color: #0c5460;
    }

    .trigger-alert.error {
        background: #f8d7da;
        border-color: #dc3545;
        color: #721c24;
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateX(-20px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    .trigger-alert-title {
        font-weight: 700;
        font-size: 1.1rem;
        margin-bottom: 0.5rem;
    }
</style>

<div class="confirmation-container">
    <div class="confirmation-card">
        <div class="success-icon">
            <i class="bi bi-check-lg"></i>
        </div>

        <h1 class="text-center mb-3">Order Confirmed!</h1>
        <p class="text-center text-muted">Thank you for your purchase</p>

        <div class="text-center">
            <div class="order-number">
                <i class="bi bi-receipt"></i> Order #<?= htmlspecialchars($order['sale_id']) ?>
            </div>
        </div>

        <!-- display trigger message from database -->
        <?php if ($trigger_message): ?>
        <div class="trigger-alert <?= htmlspecialchars($trigger_message_type) ?>">
            <div class="trigger-alert-title">
                <?php if ($trigger_message_type === 'warning'): ?>
                    <i class="bi bi-exclamation-triangle-fill"></i> Important Notice
                <?php elseif ($trigger_message_type === 'error'): ?>
                    <i class="bi bi-x-circle-fill"></i> Error
                <?php else: ?>
                    <i class="bi bi-info-circle-fill"></i> Information
                <?php endif; ?>
            </div>
            <div><?= htmlspecialchars($trigger_message) ?></div>
        </div>
        <?php endif; ?>

        <hr class="my-4">

        <h3 class="mb-3">Order Details</h3>

        <div class="order-detail-row">
            <span class="order-detail-label">Order Date:</span>
            <span><?= date('F d, Y g:i A', strtotime($order['sale_date'])) ?></span>
        </div>

        <div class="order-detail-row">
            <span class="order-detail-label">Payment Method:</span>
            <span><?= htmlspecialchars($order['payment_method_name']) ?></span>
        </div>

        <?php if ($order['member_id']): ?>
        <div class="order-detail-row">
            <span class="order-detail-label">Member Purchase:</span>
            <span>
                <i class="bi bi-star-fill text-warning"></i> Yes
                <?php if ($order['discount_amount'] == 0): ?>
                    <span style="color: #e74c3c; font-weight: 600;"> (No Discount - Membership Expired)</span>
                <?php else: ?>
                    <span style="color: #27ae60; font-weight: 600;"> (10% Discount Applied)</span>
                <?php endif; ?>
            </span>
        </div>
        <?php endif; ?>

        <hr class="my-4">

        <h3 class="mb-3">Items Ordered</h3>

        <?php foreach ($items as $item): ?>
        <div class="order-item">
            <div class="item-info">
                <div class="item-name"><?= htmlspecialchars($item['item_name']) ?></div>
                <div class="item-quantity">
                    Quantity: <?= $item['quantity'] ?> × $<?= number_format($item['price_at_sale'], 2) ?>
                </div>
            </div>
            <div class="item-price">
                $<?= number_format($item['item_total'], 2) ?>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="total-section">
            <div class="total-row">
                <span>Subtotal:</span>
                <span>$<?= number_format($order['total_amount'] + $order['discount_amount'], 2) ?></span>
            </div>

            <?php if ($order['member_id'] && $order['discount_amount'] > 0): ?>
            <div class="total-row discount">
                <span>Member Discount (10%):</span>
                <span>-$<?= number_format($order['discount_amount'], 2) ?></span>
            </div>
            <?php elseif ($order['member_id'] && $order['discount_amount'] == 0): ?>
            <div class="total-row" style="color: #e74c3c;">
                <span>Member Discount:</span>
                <span>$0.00 (Membership Expired)</span>
            </div>
            <?php endif; ?>

            <div class="total-row final">
                <span>Total Paid:</span>
                <span>$<?= number_format($order['total_amount'], 2) ?></span>
            </div>
        </div>

        <div class="action-buttons">
            <a href="../shop.php" class="btn-custom btn-primary-custom">
                <i class="bi bi-shop"></i> Continue Shopping
            </a>
            <a href="../dashboard.php" class="btn-custom btn-secondary-custom">
                <i class="bi bi-house-door"></i> Go to Dashboard
            </a>
        </div>
    </div>
</div>

<script>
    
// clear cart from localStorage when order is confirmed
if (localStorage.getItem('museumCart')) {
    console.log('Clearing cart from localStorage');
    localStorage.removeItem('museumCart');
}
</script>

<?php 
// clear the last_sale_id from session
unset($_SESSION['last_sale_id']);

include __DIR__ . '/../templates/footer.php'; 
?>