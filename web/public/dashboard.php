<?php
session_start();
require_once __DIR__ . '/app/db.php';

$page_title = 'Dashboard';
$db = db();

// get quick stats based on role
$stats = [];

if ($_SESSION['user_type'] === 'admin' || $_SESSION['user_type'] === 'curator') {
    $stats['total_artworks'] = $db->query("SELECT COUNT(*) as count FROM ARTWORK")->fetch_assoc()['count'];
    $stats['owned_artworks'] = $db->query("SELECT COUNT(*) as count FROM ARTWORK WHERE is_owned = 1")->fetch_assoc()['count'];
    $stats['active_exhibitions'] = $db->query("SELECT COUNT(*) as count FROM EXHIBITION WHERE end_date >= CURDATE()")->fetch_assoc()['count'];
    $stats['total_artists'] = $db->query("SELECT COUNT(*) as count FROM ARTIST")->fetch_assoc()['count'];
}

if ($_SESSION['user_type'] === 'admin' || $_SESSION['user_type'] === 'shop_staff') {
    $stats['total_sales_today'] = $db->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM SALE WHERE DATE(sale_date) = CURDATE()")->fetch_assoc()['total'];
    $stats['items_sold_today'] = $db->query("SELECT COUNT(*) as count FROM SALE WHERE DATE(sale_date) = CURDATE()")->fetch_assoc()['count'];
    $stats['total_items'] = $db->query("SELECT COUNT(*) as count FROM SHOP_ITEM WHERE is_deleted = 0")->fetch_assoc()['count'];
    $stats['low_stock_count'] = $db->query("SELECT COUNT(*) as count FROM SHOP_ITEM WHERE quantity_in_stock <= reorder_threshold AND is_deleted = 0")->fetch_assoc()['count'];
}

if ($_SESSION['user_type'] === 'admin' || $_SESSION['user_type'] === 'event_staff') {
    $stats['upcoming_events'] = $db->query("SELECT COUNT(*) as count FROM EVENT WHERE event_date >= CURDATE()")->fetch_assoc()['count'];
    $stats['tickets_sold_today'] = $db->query("SELECT COUNT(*) as count FROM TICKET WHERE DATE(purchase_date) = CURDATE()")->fetch_assoc()['count'];
}

if ($_SESSION['user_type'] === 'member') {
    $member_id = $_SESSION['linked_id'] ?? 0;
    $stats['my_tickets'] = $db->query("SELECT COUNT(*) as count FROM TICKET WHERE member_id = $member_id")->fetch_assoc()['count'];
    $stats['my_purchases'] = $db->query("SELECT COUNT(*) as count FROM SALE WHERE member_id = $member_id")->fetch_assoc()['count'];
}

// Get auto reordered items for shop staff
$auto_reordered_items = [];
$auto_reorder_count = 0;
if ($_SESSION['user_type'] === 'shop_staff') {
    $auto_reorder_result = $db->query("
        SELECT 
            item_id,
            item_name,
            quantity_in_stock,
            pending_reorder_quantity,
            last_auto_reorder_date,
            reorder_threshold,
            price
        FROM SHOP_ITEM
        WHERE did_auto_reorder = 1
        AND is_deleted = 0
        ORDER BY last_auto_reorder_date DESC
    ");
    
    $auto_reordered_items = $auto_reorder_result ? $auto_reorder_result->fetch_all(MYSQLI_ASSOC) : [];
    $auto_reorder_count = count($auto_reordered_items);
}

// Get recent activity last 5 entries for admin
$recent_activity = [];
if ($_SESSION['user_type'] === 'admin') {
    $recent_activity = $db->query("
        SELECT action_type, description, timestamp, user_id
        FROM ACTIVITY_LOG
        ORDER BY timestamp DESC
        LIMIT 5
    ")->fetch_all(MYSQLI_ASSOC);
}

include __DIR__ . '/templates/layout_header.php';
?>

<style>
.reorder-alert {
    background: linear-gradient(135deg, #FFF3CD 0%, #FFE69C 100%);
    border-left: 5px solid #FFC107;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { box-shadow: 0 0 0 rgba(255, 193, 7, 0.4); }
    50% { box-shadow: 0 0 20px rgba(255, 193, 7, 0.8); }
}

.stat-card {
    transition: transform 0.2s, box-shadow 0.2s;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 16px rgba(0,0,0,0.2);
}
</style>

<!-- Welcome alert -->
<div class="alert alert-primary alert-dismissible fade show" role="alert">
    <h5 class="alert-heading"><i class="bi bi-stars"></i> Welcome back, <?= htmlspecialchars($_SESSION['username']) ?>!</h5>
    <p class="mb-0">Here's your overview for today. Use the sidebar to navigate through your tasks.</p>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>

<?php if ($_SESSION['user_type'] === 'shop_staff' && $auto_reorder_count > 0): ?>
<!-- auto reordered items alert for Shop Staff -->
<div class="row mb-4">
    <div class="col-12">
        <div class="alert alert-warning border-start border-warning border-4 reorder-alert" role="alert">
            <div class="d-flex align-items-center mb-2">
                <i class="bi bi-exclamation-triangle-fill fs-3 me-3"></i>
                <div>
                    <h5 class="alert-heading mb-1">
                        <strong><?= $auto_reorder_count ?></strong> Item<?= $auto_reorder_count > 1 ? 's' : '' ?> Automatically Reordered
                    </h5>
                    <p class="mb-0">The following items had low stock and were automatically restocked by the system. Please confirm these orders with suppliers.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">
                    <i class="bi bi-box-arrow-in-down-right"></i> Pending Reorder Confirmations
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Item</th>
                                <th>Current Stock</th>
                                <th>Auto-Added Qty</th>
                                <th>Reorder Date</th>
                                <th>Estimated Cost</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($auto_reordered_items as $item): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($item['item_name']) ?></strong>
                                    <br>
                                    <small class="text-muted">ID: <?= $item['item_id'] ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-info">
                                        <?= $item['quantity_in_stock'] ?> units
                                    </span>
                                    <br>
                                    <small class="text-muted">Threshold: <?= $item['reorder_threshold'] ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-warning text-dark fs-6">
                                        +<?= $item['pending_reorder_quantity'] ?> units
                                    </span>
                                </td>
                                <td>
                                    <small>
                                        <?= date('M j, Y', strtotime($item['last_auto_reorder_date'])) ?>
                                        <br>
                                        <?= date('g:i A', strtotime($item['last_auto_reorder_date'])) ?>
                                    </small>
                                </td>
                                <td>
                                    <strong>$<?= number_format($item['price'] * $item['pending_reorder_quantity'], 2) ?></strong>
                                    <br>
                                    <small class="text-muted">@$<?= number_format($item['price'], 2) ?>/unit</small>
                                </td>
                                <td>
                                    <form method="post" action="/shop/confirm-reorder.php" class="d-inline">
                                        <input type="hidden" name="item_id" value="<?= $item['item_id'] ?>">
                                        <button type="submit" name="confirm_reorder" class="btn btn-sm btn-success" title="Confirm order with supplier">
                                            <i class="bi bi-check-circle"></i> Confirm
                                        </button>
                                    </form>
                                    <a href="/shop/inventory.php?item_id=<?= $item['item_id'] ?>" class="btn btn-sm btn-outline-primary" title="View details">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="4" class="text-end"><strong>Total Estimated Reorder Cost:</strong></td>
                                <td colspan="2">
                                    <strong class="text-primary fs-5">
                                        $<?php 
                                        $total_cost = 0;
                                        foreach ($auto_reordered_items as $item) {
                                            $total_cost += $item['price'] * $item['pending_reorder_quantity'];
                                        }
                                        echo number_format($total_cost, 2);
                                        ?>
                                    </strong>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-light">
                <small class="text-muted">
                    <i class="bi bi-info-circle"></i> 
                    <strong>Note:</strong> These items were automatically restocked by the system when inventory fell below the reorder threshold. 
                    Click "Confirm" to clear the alert.
                </small>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Stats card row -->
<div class="row g-4 mb-4">
    <?php if (isset($stats['total_artworks'])): ?>
        <div class="col-md-3">
            <div class="card stat-card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase mb-1">Total Artworks</h6>
                            <h2 class="mb-0"><?= number_format($stats['total_artworks']) ?></h2>
                        </div>
                        <i class="bi bi-palette fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card stat-card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase mb-1">Owned Artworks</h6>
                            <h2 class="mb-0"><?= number_format($stats['owned_artworks']) ?></h2>
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
                            <h6 class="text-uppercase mb-1">Active Exhibitions</h6>
                            <h2 class="mb-0"><?= number_format($stats['active_exhibitions']) ?></h2>
                        </div>
                        <i class="bi bi-building fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card stat-card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase mb-1">Total Artists</h6>
                            <h2 class="mb-0"><?= number_format($stats['total_artists']) ?></h2>
                        </div>
                        <i class="bi bi-brush fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if (isset($stats['total_sales_today']) && $_SESSION['user_type'] === 'shop_staff'): ?>
        <!-- Shop Staff Stats -->
        <div class="col-md-3">
            <div class="card stat-card bg-primary text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-white-50 mb-1 small">Total Items</p>
                            <h3 class="mb-0"><?= $stats['total_items'] ?? 0 ?></h3>
                        </div>
                        <i class="bi bi-box-seam fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card stat-card bg-success text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-white-50 mb-1 small">Sales Today</p>
                            <h3 class="mb-0">$<?= number_format($stats['total_sales_today'], 2) ?></h3>
                            <small><?= $stats['items_sold_today'] ?> transactions</small>
                        </div>
                        <i class="bi bi-cart-check fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card stat-card bg-warning text-dark h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="mb-1 small">Low Stock Items</p>
                            <h3 class="mb-0"><?= $stats['low_stock_count'] ?? 0 ?></h3>
                        </div>
                        <i class="bi bi-exclamation-triangle fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card stat-card bg-danger text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-white-50 mb-1 small">Auto-Reordered</p>
                            <h3 class="mb-0"><?= $auto_reorder_count ?></h3>
                        </div>
                        <i class="bi bi-arrow-repeat fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    <?php elseif (isset($stats['total_sales_today'])): ?>
        <!-- Admin shop stats -->
        <div class="col-md-6">
            <div class="card stat-card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase mb-1">Sales Today</h6>
                            <h2 class="mb-0">$<?= number_format($stats['total_sales_today'], 2) ?></h2>
                            <small><?= $stats['items_sold_today'] ?> transactions</small>
                        </div>
                        <i class="bi bi-cart-check fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if (isset($stats['upcoming_events'])): ?>
        <div class="col-md-6">
            <div class="card stat-card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase mb-1">Upcoming Events</h6>
                            <h2 class="mb-0"><?= number_format($stats['upcoming_events']) ?></h2>
                            <small><?= $stats['tickets_sold_today'] ?> tickets sold today</small>
                        </div>
                        <i class="bi bi-calendar-event fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if (isset($stats['my_tickets'])): ?>
        <div class="col-md-6">
            <div class="card stat-card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase mb-1">My Tickets</h6>
                            <h2 class="mb-0"><?= number_format($stats['my_tickets']) ?></h2>
                        </div>
                        <i class="bi bi-ticket fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card stat-card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase mb-1">My Purchases</h6>
                            <h2 class="mb-0"><?= number_format($stats['my_purchases']) ?></h2>
                        </div>
                        <i class="bi bi-bag-check fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- two column layout -->
<div class="row">
    <!-- recent activity (admin only) -->
    <?php if (!empty($recent_activity)): ?>
    <div class="col-md-6 mb-4">
        <div class="card stat-card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-activity"></i> Recent Activity</h5>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <?php foreach ($recent_activity as $activity): ?>
                        <div class="list-group-item px-0">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <strong><?= htmlspecialchars($activity['action_type']) ?></strong>
                                    <p class="mb-0 small text-muted"><?= htmlspecialchars($activity['description'] ?? 'No description') ?></p>
                                </div>
                                <small class="text-muted"><?= date('g:i A', strtotime($activity['timestamp'])) ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Quick actions -->
    <div class="col-md-<?= empty($recent_activity) ? '12' : '6' ?> mb-4">
        <div class="card stat-card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-lightning-charge"></i> Quick Actions</h5>
            </div>
            <div class="card-body">
                <?php if ($_SESSION['user_type'] === 'curator'): ?>
                    <div class="d-grid gap-2">
                        <a href="/curator/artworks.php" class="btn btn-outline-primary text-start">
                            <i class="bi bi-plus-circle"></i> Add New Artwork
                        </a>
                        <a href="/curator/exhibitions.php" class="btn btn-outline-success text-start">
                            <i class="bi bi-calendar-plus"></i> Create Exhibition
                        </a>
                        <a href="/curator/artists.php" class="btn btn-outline-info text-start">
                            <i class="bi bi-person-plus"></i> Add Artist
                        </a>
                    </div>
                <?php elseif ($_SESSION['user_type'] === 'shop_staff'): ?>
                    <div class="d-grid gap-2">
                        <a href="/shop/new-sale.php" class="btn btn-outline-success text-start">
                            <i class="bi bi-cart-plus"></i> Process New Sale
                        </a>
                        <a href="/shop/inventory.php" class="btn btn-outline-primary text-start">
                            <i class="bi bi-box-seam"></i> Manage Inventory
                        </a>
                    </div>
                <?php elseif ($_SESSION['user_type'] === 'event_staff'): ?>
                    <div class="d-grid gap-2">
                        <a href="/events/sell-ticket.php" class="btn btn-outline-primary text-start">
                            <i class="bi bi-ticket"></i> Sell Ticket
                        </a>
                        <a href="/events/checkin.php" class="btn btn-outline-success text-start">
                            <i class="bi bi-check-square"></i> Check-in Visitor
                        </a>
                    </div>
                <?php elseif ($_SESSION['user_type'] === 'member'): ?>
                    <div class="d-grid gap-2">
                        <a href="/exhibitions.php" class="btn btn-outline-primary text-start">
                            <i class="bi bi-building"></i> Browse Exhibitions
                        </a>
                        <a href="/events.php" class="btn btn-outline-success text-start">
                            <i class="bi bi-calendar-event"></i> View Events
                        </a>
                        <a href="/shop.php" class="btn btn-outline-info text-start">
                            <i class="bi bi-shop"></i> Visit Gift Shop
                        </a>
                    </div>
                <?php else: ?>
                    <p class="text-muted">Use the sidebar to navigate through the system.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/templates/layout_footer.php'; ?>