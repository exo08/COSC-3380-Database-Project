<?php
session_start();
require_once __DIR__ . '/../app/db.php';

$db = db();
$current_page = 'shop.php';
$page_title = 'Order Confirmation';

// Check if there's a recent sale
if (!isset($_SESSION['last_sale_id'])) {
    header("Location: ../shop.php");
    exit();
}

$sale_id = $_SESSION['last_sale_id'];

// Get sale details
$stmt = $db->prepare("
    SELECT 
        s.sale_id,
        s.sale_date,
        s.total_amount,
        s.discount_amount,
        s.payment_method,
        m.first_name,
        m.last_name,
        m.email
    FROM SALE s
    LEFT JOIN MEMBER m ON s.member_id = m.member_id
    WHERE s.sale_id = ?
");
$stmt->bind_param("i", $sale_id);
$stmt->execute();
$sale_result = $stmt->get_result();

if ($sale_result->num_rows === 0) {
    header("Location: ../shop.php");
    exit();
}

$sale = $sale_result->fetch_assoc();

// Get sale items with category information
$items_stmt = $db->prepare("
    SELECT 
        si.quantity,
        si.price_at_sale,
        sh.item_name,
        sh.category_id,
        c.name as category_name
    FROM SALE_ITEM si
    JOIN SHOP_ITEM sh ON si.item_id = sh.item_id
    LEFT JOIN CATEGORY c ON sh.category_id = c.category_id
    WHERE si.sale_id = ?
");
$items_stmt->bind_param("i", $sale_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();

$payment_methods = [
    1 => 'Credit Card',
    2 => 'Debit Card',
    3 => 'Cash'
];

// Clear the session variable
unset($_SESSION['last_sale_id']);

// Include header
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
    }

    .success-icon {
        width: 80px;
        height: 80px;
        background: var(--success-color);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem;
        color: white;
        font-size: 2.5rem;
    }

    .order-number {
        background: linear-gradient(135deg, var(--primary-color), #34495e);
        color: white;
        padding: 1rem 2rem;
        border-radius: 10px;
        display: inline-block;
        margin: 1rem 0 2rem;
    }

    .order-item {
        display: flex;
        justify-content: space-between;
        padding: 1rem 0;
        border-bottom: 1px solid #e0e0e0;
    }

    .order-item:last-child {
        border-bottom: none;
    }

    .item-info {
        flex-grow: 1;
    }

    .item-name {
        font-weight: 600;
        color: var(--primary-color);
    }

    .item-category {
        color: #666;
        font-size: 0.9rem;
    }

    .item-total {
        font-weight: 700;
        color: var(--success-color);
        text-align: right;
    }

    .summary-section {
        background: #f8f9fa;
        padding: 1.5rem;
        border-radius: 10px;
        margin-top: 2rem;
    }

    .summary-row {
        display: flex;
        justify-content: space-between;
        padding: 0.5rem 0;
        font-size: 1.1rem;
    }

    .summary-row.total {
        border-top: 2px solid #dee2e6;
        margin-top: 1rem;
        padding-top: 1rem;
        font-weight: 700;
        font-size: 1.5rem;
        color: var(--primary-color);
    }

    .discount-applied {
        color: var(--success-color);
        font-weight: 600;
    }

    .info-section {
        background: #f8f9fa;
        padding: 1.5rem;
        border-radius: 10px;
        margin-top: 1rem;
    }

    .info-row {
        display: flex;
        justify-content: space-between;
        padding: 0.5rem 0;
    }

    .info-label {
        font-weight: 600;
        color: #666;
    }

    .action-buttons {
        display: flex;
        gap: 1rem;
        margin-top: 2rem;
    }

    .btn-custom {
        flex: 1;
        padding: 1rem;
        border-radius: 10px;
        font-weight: 600;
        text-decoration: none;
        text-align: center;
        transition: all 0.3s;
    }

    .btn-primary-custom {
        background: var(--success-color);
        color: white;
        border: none;
    }

    .btn-primary-custom:hover {
        background: #229954;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
        color: white;
    }

    .btn-secondary-custom {
        background: white;
        color: var(--primary-color);
        border: 2px solid var(--primary-color);
    }

    .btn-secondary-custom:hover {
        background: var(--primary-color);
        color: white;
    }
</style>

<div class="confirmation-container">
    <div class="confirmation-card">
        <div class="text-center">
            <div class="success-icon">
                <i class="bi bi-check-lg"></i>
            </div>
            <h1>Order Confirmed!</h1>
            <p class="lead">Thank you for your purchase</p>
            
            <div class="order-number">
                <strong>Order #<?= htmlspecialchars($sale['sale_id']) ?></strong>
            </div>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($_SESSION['success_message']) ?>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <!-- Order Items -->
        <div class="mt-4">
            <h3 class="mb-3"><i class="bi bi-bag-check"></i> Items Purchased</h3>
            <?php while ($item = $items_result->fetch_assoc()): ?>
                <div class="order-item">
                    <div class="item-info">
                        <div class="item-name"><?= htmlspecialchars($item['item_name']) ?></div>
                        <div class="item-category">
                            <?= htmlspecialchars($item['category_name'] ?? 'Uncategorized') ?> • 
                            Quantity: <?= $item['quantity'] ?> × 
                            $<?= number_format($item['price_at_sale'], 2) ?>
                        </div>
                    </div>
                    <div class="item-total">
                        $<?= number_format($item['price_at_sale'] * $item['quantity'], 2) ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>

        <!-- Order Summary -->
        <div class="summary-section">
            <h4 class="mb-3">Order Summary</h4>
            
            <?php
            $subtotal = $sale['total_amount'] + $sale['discount_amount'];
            ?>
            
            <div class="summary-row">
                <span>Subtotal:</span>
                <span>$<?= number_format($subtotal, 2) ?></span>
            </div>

            <?php if ($sale['discount_amount'] > 0): ?>
                <div class="summary-row discount-applied">
                    <span><i class="bi bi-star-fill"></i> Member Discount:</span>
                    <span>-$<?= number_format($sale['discount_amount'], 2) ?></span>
                </div>
            <?php endif; ?>

            <div class="summary-row total">
                <span>Total Paid:</span>
                <span>$<?= number_format($sale['total_amount'], 2) ?></span>
            </div>
        </div>

        <!-- Order Information -->
        <div class="info-section">
            <h4 class="mb-3">Order Information</h4>
            
            <div class="info-row">
                <span class="info-label">Order Date:</span>
                <span><?= date('F j, Y g:i A', strtotime($sale['sale_date'])) ?></span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Payment Method:</span>
                <span><?= $payment_methods[$sale['payment_method']] ?? 'Unknown' ?></span>
            </div>

            <?php if ($sale['first_name']): ?>
                <div class="info-row">
                    <span class="info-label">Customer:</span>
                    <span><?= htmlspecialchars($sale['first_name'] . ' ' . $sale['last_name']) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($sale['email']): ?>
                <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span><?= htmlspecialchars($sale['email']) ?></span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="../shop.php" class="btn-custom btn-secondary-custom">
                <i class="bi bi-shop"></i> Continue Shopping
            </a>
            <a href="../dashboard.php" class="btn-custom btn-primary-custom">
                <i class="bi bi-house-door"></i> Go to Dashboard
            </a>
        </div>

        <!-- Additional Info -->
        <div class="alert alert-info mt-4">
            <i class="bi bi-info-circle"></i> 
            <strong>Note:</strong> A confirmation has been recorded in our system. 
            <?php if ($sale['email']): ?>
                A receipt will be sent to <?= htmlspecialchars($sale['email']) ?>.
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>