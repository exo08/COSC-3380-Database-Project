<?php
// enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/permissions.php';

// check permission
requirePermission('process_sale');

$page_title = 'Process Sale';
$db = db();

$success = '';
$error = '';
$receipt = null;

// initialize cart in session if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// handle add to cart
if (isset($_POST['add_to_cart'])) {
    $item_id = intval($_POST['item_id']);
    $quantity = intval($_POST['quantity']);
    
    // get item details
    $stmt = $db->prepare("SELECT item_id, item_name, price, quantity_in_stock FROM SHOP_ITEM WHERE item_id = ?");
    $stmt->bind_param('i', $item_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $item = $result->fetch_assoc();
    
    if ($item) {
        // check stock
        if ($quantity > $item['quantity_in_stock']) {
            $error = "Only {$item['quantity_in_stock']} units available in stock.";
        } else {
            // add to cart or update quantity
            if (isset($_SESSION['cart'][$item_id])) {
                $_SESSION['cart'][$item_id]['quantity'] += $quantity;
            } else {
                $_SESSION['cart'][$item_id] = [
                    'item_id' => $item['item_id'],
                    'item_name' => $item['item_name'],
                    'price' => $item['price'],
                    'quantity' => $quantity
                ];
            }
            $success = "Added {$item['item_name']} to cart.";
        }
    }
}

// handle remove from cart
if (isset($_POST['remove_from_cart'])) {
    $item_id = intval($_POST['item_id']);
    unset($_SESSION['cart'][$item_id]);
    $success = "Item removed from cart.";
}

// handle clear cart
if (isset($_POST['clear_cart'])) {
    $_SESSION['cart'] = [];
    $success = "Cart cleared.";
}

// handle process sale
if (isset($_POST['process_sale'])) {
    if (empty($_SESSION['cart'])) {
        $error = "Cart is empty. Add items before processing sale.";
    } else {
        $member_id = !empty($_POST['member_id']) ? intval($_POST['member_id']) : null;
        $payment_method = intval($_POST['payment_method']);
        
        // calculate totals
        $subtotal = 0;
        foreach ($_SESSION['cart'] as $item) {
            $subtotal += $item['price'] * $item['quantity'];
        }
        
        // apply member discount (10%)
        $discount = 0;
        if ($member_id) {
            $discount = $subtotal * 0.10;
        }
        
        $total = $subtotal - $discount;
        
        try {
            // start transaction
            $db->begin_transaction();

            // create sale using stored procedure
            $stmt = $db->prepare("CALL CreateSale(NOW(), ?, NULL, ?, ?, ?, @new_sale_id)");
            $stmt->bind_param('iddi', $member_id, $total, $discount, $payment_method);
            
            if ($stmt->execute()) {
                // get the new sale ID
                $result = $db->query("SELECT @new_sale_id as sale_id");
                $sale = $result->fetch_assoc();
                $sale_id = $sale['sale_id'];
                $stmt->close();
                
                // add sale items using stored procedure
                foreach ($_SESSION['cart'] as $item) {
                    $stmt = $db->prepare("CALL CreateSaleItem(?, ?, ?, ?, @new_sale_item_id)");
                    $stmt->bind_param('iiid', $sale_id, $item['item_id'], $item['quantity'], $item['price']);
                    $stmt->execute();
                    $stmt->close();
                }
                
                // Commit transaction
                $db->commit();
                
                // Prepare receipt
                $receipt = [
                    'sale_id' => $sale_id,
                    'items' => $_SESSION['cart'],
                    'subtotal' => $subtotal,
                    'discount' => $discount,
                    'total' => $total,
                    'payment_method' => $payment_method,
                    'member_id' => $member_id
                ];
                
                // Clear cart
                $_SESSION['cart'] = [];
                
                $success = "Sale processed successfully! Sale ID: $sale_id";
                logActivity('sale_created', 'SALE', $sale_id, "Processed sale for $" . number_format($total, 2));
            } else {
                throw new Exception($db->error);
            }
        } catch (Exception $e) {
            $db->rollback();
            $error = 'Error processing sale: ' . $e->getMessage();
        }
    }
}

// Get all shop items for search
$search = $_GET['search'] ?? '';
if (!empty($search)) {
    $search_term = "%$search%";
    $stmt = $db->prepare("SELECT * FROM SHOP_ITEM WHERE (item_name LIKE ? OR description LIKE ?) AND quantity_in_stock > 0 ORDER BY item_name");
    $stmt->bind_param('ss', $search_term, $search_term);
    $stmt->execute();
    $items_result = $stmt->get_result();
} else {
    $items_result = $db->query("SELECT * FROM SHOP_ITEM WHERE quantity_in_stock > 0 ORDER BY item_name LIMIT 20");
}

$items = $items_result ? $items_result->fetch_all(MYSQLI_ASSOC) : [];

// Get members for dropdown
$members_result = $db->query("SELECT member_id, first_name, last_name, email FROM MEMBER WHERE expiration_date >= CURDATE() ORDER BY last_name, first_name");
$members = $members_result ? $members_result->fetch_all(MYSQLI_ASSOC) : [];

include __DIR__ . '/../templates/layout_header.php';
?>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($receipt): ?>
    <!-- Receipt Modal -->
    <div class="modal fade show" id="receiptModal" tabindex="-1" style="display: block;" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="bi bi-receipt"></i> Sale Receipt</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" onclick="window.location.href='new-sale.php'"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <h4>Museum HFA Gift Shop</h4>
                        <p class="text-muted">Sale #<?= $receipt['sale_id'] ?></p>
                        <p class="text-muted"><?= date('F j, Y g:i A') ?></p>
                    </div>
                    
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th class="text-end">Price</th>
                                <th class="text-end">Qty</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($receipt['items'] as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['item_name']) ?></td>
                                    <td class="text-end">$<?= number_format($item['price'], 2) ?></td>
                                    <td class="text-end"><?= $item['quantity'] ?></td>
                                    <td class="text-end">$<?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="text-end"><strong>Subtotal:</strong></td>
                                <td class="text-end">$<?= number_format($receipt['subtotal'], 2) ?></td>
                            </tr>
                            <?php if ($receipt['discount'] > 0): ?>
                                <tr class="text-success">
                                    <td colspan="3" class="text-end"><strong>Member Discount (10%):</strong></td>
                                    <td class="text-end">-$<?= number_format($receipt['discount'], 2) ?></td>
                                </tr>
                            <?php endif; ?>
                            <tr class="table-success">
                                <td colspan="3" class="text-end"><h5>Total:</h5></td>
                                <td class="text-end"><h5>$<?= number_format($receipt['total'], 2) ?></h5></td>
                            </tr>
                        </tfoot>
                    </table>
                    
                    <div class="text-center mt-3">
                        <p class="text-muted">
                            Payment Method: 
                            <?php 
                                $methods = [1 => 'Cash', 2 => 'Credit Card', 3 => 'Debit Card', 4 => 'Gift Card'];
                                echo $methods[$receipt['payment_method']] ?? 'Other';
                            ?>
                        </p>
                        <?php if ($receipt['member_id']): ?>
                            <p class="text-success"><i class="bi bi-star-fill"></i> Member Discount Applied</p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="window.location.href='new-sale.php'">
                        <i class="bi bi-plus-circle"></i> New Sale
                    </button>
                    <button type="button" class="btn btn-primary" onclick="window.print()">
                        <i class="bi bi-printer"></i> Print Receipt
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-backdrop fade show"></div>
<?php endif; ?>

<div class="row">
    <!-- Left Column: Item Search and List -->
    <div class="col-md-7">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-search"></i> Search Items</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="mb-3">
                    <div class="input-group">
                        <input type="text" class="form-control" name="search" placeholder="Search items..." value="<?= htmlspecialchars($search) ?>">
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-search"></i> Search
                        </button>
                        <?php if (!empty($search)): ?>
                            <a href="new-sale.php" class="btn btn-secondary">
                                <i class="bi bi-x"></i> Clear
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
                
                <?php if (empty($items)): ?>
                    <p class="text-muted">No items found. Try a different search.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($item['item_name']) ?></strong>
                                            <?php if (!empty($item['description'])): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars(substr($item['description'], 0, 50)) ?>...</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>$<?= number_format($item['price'], 2) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $item['quantity_in_stock'] < 10 ? 'warning' : 'info' ?>">
                                                <?= $item['quantity_in_stock'] ?> in stock
                                            </span>
                                        </td>
                                        <td>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="item_id" value="<?= $item['item_id'] ?>">
                                                <div class="input-group input-group-sm" style="width: 120px;">
                                                    <input type="number" name="quantity" class="form-control" value="1" min="1" max="<?= $item['quantity_in_stock'] ?>" required>
                                                    <button type="submit" name="add_to_cart" class="btn btn-primary btn-sm">
                                                        <i class="bi bi-cart-plus"></i>
                                                    </button>
                                                </div>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Right Column: Shopping Cart -->
    <div class="col-md-5">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-cart"></i> Shopping Cart (<?= count($_SESSION['cart']) ?> items)</h5>
            </div>
            <div class="card-body">
                <?php if (empty($_SESSION['cart'])): ?>
                    <p class="text-muted text-center py-4">Cart is empty. Add items to get started.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th class="text-end">Price</th>
                                    <th class="text-end">Qty</th>
                                    <th class="text-end">Total</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $subtotal = 0;
                                foreach ($_SESSION['cart'] as $item): 
                                    $item_total = $item['price'] * $item['quantity'];
                                    $subtotal += $item_total;
                                ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['item_name']) ?></td>
                                        <td class="text-end">$<?= number_format($item['price'], 2) ?></td>
                                        <td class="text-end"><?= $item['quantity'] ?></td>
                                        <td class="text-end">$<?= number_format($item_total, 2) ?></td>
                                        <td>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="item_id" value="<?= $item['item_id'] ?>">
                                                <button type="submit" name="remove_from_cart" class="btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <hr>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Member (Optional for 10% discount)</label>
                            <select class="form-select" name="member_id" id="member_id" onchange="updateTotal()">
                                <option value="">Walk-in Customer</option>
                                <?php foreach ($members as $member): ?>
                                    <option value="<?= $member['member_id'] ?>">
                                        <?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Payment Method *</label>
                            <select class="form-select" name="payment_method" required>
                                <option value="1">Cash</option>
                                <option value="2">Credit Card</option>
                                <option value="3">Debit Card</option>
                                <option value="4">Gift Card</option>
                            </select>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>Subtotal:</span>
                            <strong>$<span id="subtotal"><?= number_format($subtotal, 2) ?></span></strong>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mb-2 text-success" id="discount-row" style="display: none !important;">
                            <span>Member Discount (10%):</span>
                            <strong>-$<span id="discount">0.00</span></strong>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5>Total:</h5>
                            <h5>$<span id="total"><?= number_format($subtotal, 2) ?></span></h5>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" name="process_sale" class="btn btn-success btn-lg">
                                <i class="bi bi-check-circle"></i> Process Sale
                            </button>
                            <button type="submit" name="clear_cart" class="btn btn-outline-secondary">
                                <i class="bi bi-trash"></i> Clear Cart
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
const subtotalAmount = <?= $subtotal ?>;

function updateTotal() {
    const memberSelect = document.getElementById('member_id');
    const discountRow = document.getElementById('discount-row');
    const discountSpan = document.getElementById('discount');
    const totalSpan = document.getElementById('total');
    
    if (memberSelect.value) {
        // Apply 10% discount
        const discount = subtotalAmount * 0.10;
        const total = subtotalAmount - discount;
        
        discountRow.style.display = 'flex';
        discountSpan.textContent = discount.toFixed(2);
        totalSpan.textContent = total.toFixed(2);
    } else {
        // No discount
        discountRow.style.display = 'none';
        totalSpan.textContent = subtotalAmount.toFixed(2);
    }
}
</script>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>