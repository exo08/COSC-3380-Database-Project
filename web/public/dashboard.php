<?php
session_start();
require_once __DIR__ . '/app/db.php';

$page_title = 'Dashboard';
$db = db();

// Get quick stats based on role
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

// Get recent activity (last 5 actions)
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

<!-- Welcome alert -->
<div class="alert alert-primary alert-dismissible fade show" role="alert">
    <h5 class="alert-heading"><i class="bi bi-stars"></i> Welcome back, <?= htmlspecialchars($_SESSION['username']) ?>!</h5>
    <p class="mb-0">Here's your overview for today. Use the sidebar to navigate through your tasks.</p>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>

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
    
    <?php if (isset($stats['total_sales_today'])): ?>
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