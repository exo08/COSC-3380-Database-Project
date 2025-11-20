<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/permissions.php';

// Check permission
requirePermission('view_inventory');

$page_title = 'Manage Inventory';
$db = db();

$success = '';
$error = '';

// Handle Delete
if (isset($_POST['delete_item'])) {
    requirePermission('edit_shop_item');
    $item_id = intval($_POST['item_id']);
    
    try {
        if ($db->query("DELETE FROM SHOP_ITEM WHERE item_id = $item_id")) {
            $success = 'Item deleted successfully!';
            logActivity('shop_item_deleted', 'SHOP_ITEM', $item_id, "Deleted shop item ID: $item_id");
        } else {
            $error = 'Error deleting item: ' . $db->error;
        }
    } catch (Exception $e) {
        $error = 'Error deleting item: ' . $e->getMessage();
    }
}

// Handle add/edit
if (isset($_POST['save_item'])) {
    $item_id = !empty($_POST['item_id']) ? intval($_POST['item_id']) : null;
    $item_name = $_POST['item_name'] ?? '';
    $description = $_POST['description'] ?? '';
    $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $price = floatval($_POST['price']);
    $quantity_in_stock = intval($_POST['quantity_in_stock']);
    
    try {
        if ($item_id) { // update existing
            requirePermission('edit_shop_item');
            
            $stmt = $db->prepare("UPDATE SHOP_ITEM SET 
                    item_name = ?,
                    description = ?,
                    category_id = ?,
                    price = ?,
                    quantity_in_stock = ?
                    WHERE item_id = ?");
            
            $stmt->bind_param("ssidii", $item_name, $description, $category_id, $price, $quantity_in_stock, $item_id);
            
            if ($stmt->execute()) {
                $success = 'Item updated successfully!';
                logActivity('shop_item_updated', 'SHOP_ITEM', $item_id, "Updated shop item: $item_name");
            } else {
                $error = 'Error updating item: ' . $db->error;
            }
        } else { // insert new
            requirePermission('add_shop_item');
            
            // Insert directly instead of using stored procedure (if procedure doesn't exist or needs updating)
            $stmt = $db->prepare("INSERT INTO SHOP_ITEM (item_name, description, category_id, price, quantity_in_stock) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssidi", $item_name, $description, $category_id, $price, $quantity_in_stock);
            
            if ($stmt->execute()) {
                $new_item_id = $db->insert_id;
                $success = "Item added successfully! (ID: $new_item_id)";
                logActivity('shop_item_created', 'SHOP_ITEM', $new_item_id, "Created shop item: $item_name");
            } else {
                $error = 'Error adding item: ' . $db->error;
            }
            
            if (isset($stmt)) $stmt->close();
        }
    } catch (Exception $e) {
        $error = 'Error saving item: ' . $e->getMessage();
    }
}

// Handle stock adjustment
if (isset($_POST['adjust_stock'])) {
    requirePermission('edit_shop_item');
    $item_id = intval($_POST['item_id']);
    $adjustment = intval($_POST['adjustment']);
    
    try {
        $stmt = $db->prepare("UPDATE SHOP_ITEM SET quantity_in_stock = quantity_in_stock + ? WHERE item_id = ?");
        $stmt->bind_param("ii", $adjustment, $item_id);
        
        if ($stmt->execute()) {
            $success = "Stock adjusted by $adjustment units.";
            logActivity('stock_adjusted', 'SHOP_ITEM', $item_id, "Adjusted stock by $adjustment");
        } else {
            $error = 'Error adjusting stock: ' . $db->error;
        }
    } catch (Exception $e) {
        $error = 'Error adjusting stock: ' . $e->getMessage();
    }
}

// Get all shop items with category information
try {
    $items_result = $db->query("
        SELECT 
            si.item_id,
            si.item_name,
            si.description,
            si.category_id,
            c.name as category_name,
            si.price,
            si.quantity_in_stock
        FROM SHOP_ITEM si
        LEFT JOIN CATEGORY c ON si.category_id = c.category_id
        ORDER BY si.item_name
    ");
    
    if ($items_result) {
        $items = $items_result->fetch_all(MYSQLI_ASSOC);
    } else {
        $items = [];
        $error = 'Error loading items: ' . $db->error;
    }
} catch (Exception $e) {
    $items = [];
    $error = 'Error loading items: ' . $e->getMessage();
}

// Get all active categories for dropdown
try {
    $categories_result = $db->query("SELECT category_id, name FROM CATEGORY WHERE is_active = 1 ORDER BY name");
    $categories = $categories_result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    $categories = [];
}

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

<!-- Add New Button -->
<?php if (hasPermission('add_shop_item')): ?>
<div class="mb-4">
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#itemModal" onclick="clearForm()">
        <i class="bi bi-plus-circle"></i> Add New Item
    </button>
</div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card stat-card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-1">Total Items</h6>
                        <h2 class="mb-0"><?= count($items) ?></h2>
                    </div>
                    <i class="bi bi-box-seam fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-1">In Stock</h6>
                        <h2 class="mb-0"><?= count(array_filter($items, fn($i) => $i['quantity_in_stock'] > 0)) ?></h2>
                    </div>
                    <i class="bi bi-check-circle fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card bg-warning text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-1">Low Stock</h6>
                        <h2 class="mb-0"><?= count(array_filter($items, fn($i) => $i['quantity_in_stock'] > 0 && $i['quantity_in_stock'] < 10)) ?></h2>
                    </div>
                    <i class="bi bi-exclamation-triangle fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card bg-danger text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-1">Out of Stock</h6>
                        <h2 class="mb-0"><?= count(array_filter($items, fn($i) => $i['quantity_in_stock'] == 0)) ?></h2>
                    </div>
                    <i class="bi bi-x-circle fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Items table -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-box-seam"></i> All Items (<?= count($items) ?>)</h5>
    </div>
    <div class="card-body">
        <?php if (empty($items)): ?>
            <p class="text-muted">No items found. Add your first item using the button above!</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['item_id']) ?></td>
                        <td>
                            <strong><?= htmlspecialchars($item['item_name']) ?></strong>
                            <?php if (!empty($item['description'])): ?>
                                <br><small class="text-muted"><?= htmlspecialchars(substr($item['description'], 0, 60)) ?>...</small>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($item['category_name'] ?? 'Uncategorized') ?></td>
                        <td><strong>$<?= number_format($item['price'], 2) ?></strong></td>
                        <td>
                            <span class="badge bg-<?= $item['quantity_in_stock'] == 0 ? 'danger' : ($item['quantity_in_stock'] < 10 ? 'warning' : 'success') ?>">
                                <?= $item['quantity_in_stock'] ?> units
                            </span>
                        </td>
                        <td>
                            <?php if ($item['quantity_in_stock'] == 0): ?>
                                <span class="badge bg-danger">Out of Stock</span>
                            <?php elseif ($item['quantity_in_stock'] < 10): ?>
                                <span class="badge bg-warning">Low Stock</span>
                            <?php else: ?>
                                <span class="badge bg-success">In Stock</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (hasPermission('edit_shop_item')): ?>
                            <button class="btn btn-sm btn-outline-info" onclick='adjustStock(<?= json_encode($item, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' title="Adjust Stock">
                                <i class="bi bi-plus-slash-minus"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-primary" onclick='editItem(<?= json_encode($item, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <?php endif; ?>
                            
                            <?php if (hasPermission('edit_shop_item')): ?>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteItem(<?= $item['item_id'] ?>, '<?= htmlspecialchars($item['item_name'], ENT_QUOTES) ?>')" title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- add/edit modal -->
<div class="modal fade" id="itemModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add New Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="item_id" id="item_id">
                    
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Item Name *</label>
                            <input type="text" class="form-control" name="item_name" id="item_name" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Category</label>
                            <select class="form-select" name="category_id" id="category_id">
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="description" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Price *</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" name="price" id="price" step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Quantity in Stock *</label>
                            <input type="number" class="form-control" name="quantity_in_stock" id="quantity_in_stock" min="0" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="save_item" class="btn btn-primary">
                        <i class="bi bi-save"></i> Save Item
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Stock adjustment modal -->
<div class="modal fade" id="stockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Adjust Stock</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="item_id" id="stock_item_id">
                    
                    <p>Item: <strong id="stock_item_name"></strong></p>
                    <p>Current Stock: <strong id="stock_current"></strong> units</p>
                    
                    <div class="mb-3">
                        <label class="form-label">Adjustment *</label>
                        <input type="number" class="form-control" name="adjustment" id="adjustment" placeholder="Enter positive to add, negative to remove" required>
                        <small class="text-muted">Example: +10 to add 10 units, -5 to remove 5 units</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="adjust_stock" class="btn btn-primary">
                        <i class="bi bi-check"></i> Adjust Stock
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete confirmation -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this item?</p>
                    <p class="fw-bold" id="deleteItemName"></p>
                    <input type="hidden" name="item_id" id="delete_item_id">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_item" class="btn btn-danger">
                        <i class="bi bi-trash"></i> Delete
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function clearForm() {
    document.getElementById('modalTitle').textContent = 'Add New Item';
    document.getElementById('item_id').value = '';
    document.getElementById('item_name').value = '';
    document.getElementById('description').value = '';
    document.getElementById('category_id').value = '';
    document.getElementById('price').value = '';
    document.getElementById('quantity_in_stock').value = '0';
}

function editItem(item) {
    document.getElementById('modalTitle').textContent = 'Edit Item';
    document.getElementById('item_id').value = item.item_id;
    document.getElementById('item_name').value = item.item_name || '';
    document.getElementById('description').value = item.description || '';
    document.getElementById('category_id').value = item.category_id || '';
    document.getElementById('price').value = item.price || '';
    document.getElementById('quantity_in_stock').value = item.quantity_in_stock || '0';
    
    new bootstrap.Modal(document.getElementById('itemModal')).show();
}

function adjustStock(item) {
    document.getElementById('stock_item_id').value = item.item_id;
    document.getElementById('stock_item_name').textContent = item.item_name;
    document.getElementById('stock_current').textContent = item.quantity_in_stock;
    document.getElementById('adjustment').value = '';
    
    new bootstrap.Modal(document.getElementById('stockModal')).show();
}

function deleteItem(id, name) {
    document.getElementById('delete_item_id').value = id;
    document.getElementById('deleteItemName').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>