<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/permissions.php';

// Require admin permission
if ($_SESSION['user_type'] !== 'admin') {
    header('Location: /dashboard.php?error=access_denied');
    exit;
}

$page_title = 'Shop Management';
$db = db();
$success = '';
$error = '';

// Handle shop item creation/editing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
        $item_id = $_POST['item_id'] ?? null;
        $item_name = trim($_POST['item_name']);
        $description = trim($_POST['description']);
        $category = trim($_POST['category']);
        $price = (float)$_POST['price'];
        $quantity = (int)$_POST['quantity_in_stock'];

        if ($_POST['action'] === 'add') {
            $stmt = $db->prepare("
                INSERT INTO SHOP_ITEM (item_name, description, category, price, quantity_in_stock)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("sssdi", $item_name, $description, $category, $price, $quantity);
            
            if ($stmt->execute()) {
                $success = "Shop item created successfully!";
                logActivity('shop_item_created', 'SHOP_ITEM', $db->insert_id, "Created item: $item_name");
            } else {
                $error = "Error creating item: " . $db->error;
            }
            $stmt->close();
        } else {
            $stmt = $db->prepare("
                UPDATE SHOP_ITEM 
                SET item_name = ?, description = ?, category = ?, price = ?, quantity_in_stock = ?
                WHERE item_id = ?
            ");
            $stmt->bind_param("sssdii", $item_name, $description, $category, $price, $quantity, $item_id);
            
            if ($stmt->execute()) {
                $success = "Shop item updated successfully!";
                logActivity('shop_item_updated', 'SHOP_ITEM', $item_id, "Updated item: $item_name");
            } else {
                $error = "Error updating item: " . $db->error;
            }
            $stmt->close();
        }
    } elseif ($_POST['action'] === 'delete') {
        $item_id = $_POST['item_id'];
        $stmt = $db->prepare("DELETE FROM SHOP_ITEM WHERE item_id = ?");
        $stmt->bind_param("i", $item_id);
        
        if ($stmt->execute()) {
            $success = "Shop item deleted successfully!";
            logActivity('shop_item_deleted', 'SHOP_ITEM', $item_id, "Deleted shop item");
        } else {
            $error = "Error deleting item: " . $db->error;
        }
        $stmt->close();
    }
}

// Get all shop items with sales data
$items_query = "
    SELECT si.*, 
           COUNT(DISTINCT sli.sale_id) as times_sold,
           SUM(sli.quantity) as total_quantity_sold,
           SUM(sli.quantity * sli.price_at_sale) as total_revenue,
           CASE 
               WHEN si.quantity_in_stock = 0 THEN 'Out of Stock'
               WHEN si.quantity_in_stock <= 10 THEN 'Low Stock'
               ELSE 'In Stock'
           END as stock_status
    FROM SHOP_ITEM si
    LEFT JOIN SALE_ITEM sli ON si.item_id = sli.item_id
    GROUP BY si.item_id
    ORDER BY si.item_name
";

$items_result = $db->query($items_query);
$items = $items_result ? $items_result->fetch_all(MYSQLI_ASSOC) : [];

// Get categories for filter
$categories = array_unique(array_column($items, 'category'));
sort($categories);

// Get shop stats
$stats = [];
$stats['total_items'] = count($items);
$stats['low_stock_items'] = count(array_filter($items, fn($i) => $i['stock_status'] === 'Low Stock' || $i['stock_status'] === 'Out of Stock'));
$stats['total_inventory_value'] = array_sum(array_map(fn($i) => $i['price'] * $i['quantity_in_stock'], $items));

// Sales stats
$sales_today = $db->query("
    SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total
    FROM SALE 
    WHERE DATE(sale_date) = CURDATE()
")->fetch_assoc();

$stats['sales_today'] = $sales_today['count'];
$stats['revenue_today'] = $sales_today['total'];

// Recent sales
$recent_sales = $db->query("
    SELECT s.*, 
           CONCAT(COALESCE(m.first_name, v.first_name, 'Walk-in'), ' ', COALESCE(m.last_name, v.last_name, 'Customer')) as customer_name,
           COUNT(sli.item_id) as item_count
    FROM SALE s
    LEFT JOIN MEMBER m ON s.member_id = m.member_id
    LEFT JOIN VISITOR v ON s.visitor_id = v.visitor_id
    LEFT JOIN SALE_ITEM sli ON s.sale_id = sli.sale_id
    GROUP BY s.sale_id
    ORDER BY s.sale_date DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../templates/layout_header.php';
?>

<style>
.item-card {
    transition: transform 0.2s, box-shadow 0.2s;
    height: 100%;
}
.item-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.stock-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    font-size: 0.75rem;
}
.stat-card {
    border-radius: 10px;
    transition: transform 0.2s;
}
.stat-card:hover {
    transform: translateY(-3px);
}
.low-stock {
    border-left: 4px solid #ffc107;
}
.out-of-stock {
    border-left: 4px solid #dc3545;
}
</style>

<!-- Success/Error Messages -->
<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2><i class="bi bi-shop"></i> Shop Management</h2>
        <p class="text-muted">Manage inventory, view sales, and monitor stock levels</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/shop/new-sale.php" class="btn btn-success btn-lg">
            <i class="bi bi-cart-plus"></i> Process Sale
        </a>
        <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#itemModal" onclick="resetItemForm()">
            <i class="bi bi-plus-circle"></i> Add Item
        </button>
    </div>
</div>

<!-- Stats Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card stat-card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-1">Total Items</h6>
                        <h2 class="mb-0"><?= $stats['total_items'] ?></h2>
                    </div>
                    <i class="bi bi-box-seam fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card stat-card bg-warning text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-1">Low Stock Items</h6>
                        <h2 class="mb-0"><?= $stats['low_stock_items'] ?></h2>
                    </div>
                    <i class="bi bi-exclamation-triangle fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card stat-card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-1">Sales Today</h6>
                        <h2 class="mb-0"><?= $stats['sales_today'] ?></h2>
                    </div>
                    <i class="bi bi-cart-check fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card stat-card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-1">Revenue Today</h6>
                        <h2 class="mb-0">$<?= number_format($stats['revenue_today'], 2) ?></h2>
                    </div>
                    <i class="bi bi-cash-stack fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link active" data-bs-toggle="tab" href="#inventory">
            <i class="bi bi-box-seam"></i> Inventory
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#sales">
            <i class="bi bi-receipt"></i> Recent Sales
        </a>
    </li>
</ul>

<div class="tab-content">
    <!-- Inventory Tab -->
    <div class="tab-pane fade show active" id="inventory">
        <!-- Filter -->
        <div class="row mb-4">
            <div class="col-md-6">
                <select class="form-select" id="categoryFilter">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <select class="form-select" id="stockFilter">
                    <option value="">All Stock Levels</option>
                    <option value="In Stock">In Stock</option>
                    <option value="Low Stock">Low Stock</option>
                    <option value="Out of Stock">Out of Stock</option>
                </select>
            </div>
        </div>
        
        <!-- Items Grid -->
        <div class="row g-4" id="itemsContainer">
            <?php foreach ($items as $item): 
                $stock_class = $item['stock_status'] === 'Low Stock' ? 'low-stock' : ($item['stock_status'] === 'Out of Stock' ? 'out-of-stock' : '');
            ?>
                <div class="col-md-4 item-card-container" data-category="<?= htmlspecialchars($item['category']) ?>" data-stock="<?= htmlspecialchars($item['stock_status']) ?>">
                    <div class="card item-card <?= $stock_class ?>">
                        <div class="card-body position-relative">
                            <span class="badge stock-badge bg-<?= $item['stock_status'] === 'In Stock' ? 'success' : ($item['stock_status'] === 'Low Stock' ? 'warning' : 'danger') ?>">
                                <?= htmlspecialchars($item['stock_status']) ?>
                            </span>
                            
                            <h5 class="card-title mb-2"><?= htmlspecialchars($item['item_name']) ?></h5>
                            <p class="text-muted small mb-3">
                                <span class="badge bg-secondary"><?= htmlspecialchars($item['category']) ?></span>
                            </p>
                            
                            <?php if ($item['description']): ?>
                                <p class="card-text small text-muted mb-3">
                                    <?= htmlspecialchars(substr($item['description'], 0, 80)) ?><?= strlen($item['description']) > 80 ? '...' : '' ?>
                                </p>
                            <?php endif; ?>
                            
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h4 class="text-primary mb-0">$<?= number_format($item['price'], 2) ?></h4>
                                </div>
                                <div class="text-end">
                                    <div class="small text-muted">Stock</div>
                                    <div class="fw-bold"><?= $item['quantity_in_stock'] ?></div>
                                </div>
                            </div>
                            
                            <div class="border-top pt-3">
                                <div class="row g-2 small text-muted mb-3">
                                    <div class="col-6">
                                        <i class="bi bi-cart"></i> Sold: <?= $item['total_quantity_sold'] ?? 0 ?>
                                    </div>
                                    <div class="col-6 text-end">
                                        <i class="bi bi-currency-dollar"></i> Revenue: $<?= number_format($item['total_revenue'] ?? 0, 2) ?>
                                    </div>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <button class="btn btn-sm btn-outline-primary flex-grow-1" onclick="editItem(<?= htmlspecialchars(json_encode($item)) ?>)">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this item?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="item_id" value="<?= $item['item_id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Recent Sales Tab -->
    <div class="tab-pane fade" id="sales">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title mb-4">Recent Sales</h5>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Sale ID</th>
                                <th>Date & Time</th>
                                <th>Customer</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Discount</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_sales as $sale): ?>
                                <tr>
                                    <td><strong>#<?= $sale['sale_id'] ?></strong></td>
                                    <td><?= date('M j, Y g:i A', strtotime($sale['sale_date'])) ?></td>
                                    <td><?= htmlspecialchars($sale['customer_name']) ?></td>
                                    <td><?= $sale['item_count'] ?> items</td>
                                    <td class="fw-bold">$<?= number_format($sale['total_amount'], 2) ?></td>
                                    <td>
                                        <?php if ($sale['discount_amount'] > 0): ?>
                                            <span class="badge bg-success">-$<?= number_format($sale['discount_amount'], 2) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="/shop/sales.php?sale_id=<?= $sale['sale_id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-center mt-3">
                    <a href="/shop/sales.php" class="btn btn-outline-primary">
                        View All Sales <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Item Modal -->
<div class="modal fade" id="itemModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="itemModalTitle">Add Shop Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" id="itemAction" value="add">
                    <input type="hidden" name="item_id" id="itemId">
                    
                    <div class="mb-3">
                        <label class="form-label">Item Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="item_name" id="itemName" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="itemDescription" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Category <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="category" id="itemCategory" list="categoryList" required>
                            <datalist id="categoryList">
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat) ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Price <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" name="price" id="itemPrice" step="0.01" min="0" required>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Stock Quantity <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="quantity_in_stock" id="itemQuantity" min="0" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Save Item
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Filter functionality
document.getElementById('categoryFilter').addEventListener('change', filterItems);
document.getElementById('stockFilter').addEventListener('change', filterItems);

function filterItems() {
    const category = document.getElementById('categoryFilter').value;
    const stock = document.getElementById('stockFilter').value;
    
    document.querySelectorAll('.item-card-container').forEach(item => {
        const itemCategory = item.dataset.category;
        const itemStock = item.dataset.stock;
        
        const categoryMatch = !category || itemCategory === category;
        const stockMatch = !stock || itemStock === stock;
        
        item.style.display = (categoryMatch && stockMatch) ? 'block' : 'none';
    });
}

function resetItemForm() {
    document.getElementById('itemModalTitle').textContent = 'Add Shop Item';
    document.getElementById('itemAction').value = 'add';
    document.getElementById('itemId').value = '';
    document.getElementById('itemName').value = '';
    document.getElementById('itemDescription').value = '';
    document.getElementById('itemCategory').value = '';
    document.getElementById('itemPrice').value = '';
    document.getElementById('itemQuantity').value = '';
}

function editItem(item) {
    document.getElementById('itemModalTitle').textContent = 'Edit Shop Item';
    document.getElementById('itemAction').value = 'edit';
    document.getElementById('itemId').value = item.item_id;
    document.getElementById('itemName').value = item.item_name;
    document.getElementById('itemDescription').value = item.description || '';
    document.getElementById('itemCategory').value = item.category;
    document.getElementById('itemPrice').value = item.price;
    document.getElementById('itemQuantity').value = item.quantity_in_stock;
    
    new bootstrap.Modal(document.getElementById('itemModal')).show();
}
</script>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>