<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/permissions.php';

// Require member permission
if ($_SESSION['user_type'] !== 'member') {
    header('Location: /dashboard.php?error=access_denied');
    exit;
}

$page_title = 'Events & Tickets';
$db = db();
$success = '';
$error = '';

// Get member_id from session
$user_id = $_SESSION['user_id'];
$member_result = $db->query("SELECT linked_id FROM USER_ACCOUNT WHERE user_id = $user_id");
$member_data = $member_result->fetch_assoc();
$member_id = $member_data['linked_id'];

// Get member details for discount eligibility
$member_info = $db->query("
    SELECT m.*, 
           CASE m.membership_type
               WHEN 1 THEN 'Individual'
               WHEN 2 THEN 'Family'
               WHEN 3 THEN 'Student'
               WHEN 4 THEN 'Senior'
               WHEN 5 THEN 'Patron'
               ELSE 'Unknown'
           END as membership_name
    FROM MEMBER m
    WHERE m.member_id = $member_id
")->fetch_assoc();

// Handle ticket purchase
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'purchase') {
    $event_id = intval($_POST['event_id']);
    $quantity = intval($_POST['quantity']);
    $purchase_date = date('Y-m-d');
    
    // Check available capacity
    $stmt = $db->prepare("
        SELECT capacity, 
               (SELECT SUM(quantity) FROM TICKET WHERE event_id = ?) as tickets_sold 
        FROM EVENT WHERE event_id = ?
    ");
    $stmt->bind_param('ii', $event_id, $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $event_check = $result->fetch_assoc();
    $stmt->close();
    
    $available = $event_check['capacity'] - ($event_check['tickets_sold'] ?? 0);
    
    if ($quantity > $available) {
        $error = "Not enough tickets available. Only $available tickets remaining.";
    } else {
        try {
            // Create ticket
            $checked_in = 0;
            $check_in_time = null;
            
            $stmt = $db->prepare("CALL CreateTicket(?, NULL, ?, ?, ?, ?, ?, @new_ticket_id)");
            $stmt->bind_param('iisiss', $event_id, $member_id, $purchase_date, $quantity, $checked_in, $check_in_time);
            
            if ($stmt->execute()) {
                $stmt->close();
                $db->next_result();
                
                $result = $db->query("SELECT @new_ticket_id as ticket_id");
                $row = $result->fetch_assoc();
                $ticket_id = $row['ticket_id'];
                
                $success = "Ticket #$ticket_id purchased successfully! You will receive a confirmation email.";
                logActivity('ticket_purchased', 'TICKET', $ticket_id, "Member purchased $quantity ticket(s) for event #$event_id");
            } else {
                throw new Exception($db->error);
            }
        } catch (Exception $e) {
            $error = "Failed to purchase ticket: " . $e->getMessage();
        }
    }
}

// Get upcoming events with ticket availability
$upcoming_events = $db->query("
    SELECT e.*, 
           l.name as location_name,
           ex.title as exhibition_title,
           COALESCE(SUM(t.quantity), 0) as tickets_sold,
           (e.capacity - COALESCE(SUM(t.quantity), 0)) as tickets_available,
           CASE 
               WHEN e.event_date < CURDATE() THEN 'Past'
               WHEN e.event_date = CURDATE() THEN 'Today'
               WHEN e.event_date > CURDATE() THEN 'Upcoming'
           END as status
    FROM EVENT e
    LEFT JOIN LOCATION l ON e.location_id = l.location_id
    LEFT JOIN EXHIBITION ex ON e.exhibition_id = ex.exhibition_id
    LEFT JOIN TICKET t ON e.event_id = t.event_id
    WHERE e.event_date >= CURDATE()
    GROUP BY e.event_id
    ORDER BY e.event_date ASC
")->fetch_all(MYSQLI_ASSOC);

// Get member's ticket history
$my_tickets = $db->query("
    SELECT t.*, 
           e.name as event_name,
           e.event_date,
           e.description as event_description,
           l.name as location_name,
           CASE 
               WHEN e.event_date < CURDATE() THEN 'Past'
               WHEN e.event_date = CURDATE() THEN 'Today'
               ELSE 'Upcoming'
           END as event_status
    FROM TICKET t
    JOIN EVENT e ON t.event_id = e.event_id
    LEFT JOIN LOCATION l ON e.location_id = l.location_id
    WHERE t.member_id = $member_id
    ORDER BY e.event_date DESC
")->fetch_all(MYSQLI_ASSOC);

// Get event stats for member
$stats = [];
$stats['upcoming_tickets'] = count(array_filter($my_tickets, fn($t) => $t['event_status'] === 'Upcoming' || $t['event_status'] === 'Today'));
$stats['total_tickets'] = array_sum(array_column($my_tickets, 'quantity'));
$stats['checked_in'] = count(array_filter($my_tickets, fn($t) => $t['checked_in'] == 1));

include __DIR__ . '/../templates/layout_header.php';
?>

<style>
.event-card {
    border-left: 4px solid #0d6efd;
    transition: transform 0.2s, box-shadow 0.2s;
    height: 100%;
}
.event-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.event-card.today {
    border-left-color: #ffc107;
}
.event-card.upcoming {
    border-left-color: #198754;
}
.stat-card {
    border-radius: 10px;
    transition: transform 0.2s;
}
.stat-card:hover {
    transform: translateY(-3px);
}
.ticket-card {
    background: #f8f9fa;
    border-left: 4px solid #6c757d;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
    transition: all 0.2s;
}
.ticket-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.ticket-card.upcoming {
    border-left-color: #198754;
    background: #d1e7dd;
}
.ticket-card.today {
    border-left-color: #ffc107;
    background: #fff3cd;
}
.member-badge {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 500;
}
</style>

<!-- Success/Error Messages -->
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

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2><i class="bi bi-calendar-event"></i> Events & Tickets</h2>
        <p class="text-muted">Browse upcoming events and manage your tickets</p>
    </div>
    <div>
        <span class="member-badge">
            <i class="bi bi-star-fill"></i> <?= htmlspecialchars($member_info['membership_name']) ?> Member
        </span>
    </div>
</div>

<!-- Membership Info Alert -->
<?php if (strtotime($member_info['expiration_date']) < strtotime('+30 days')): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i> 
        <strong>Membership Expiring Soon!</strong> 
        Your membership expires on <?= date('M j, Y', strtotime($member_info['expiration_date'])) ?>. 
        <a href="/member/membership.php" class="alert-link">Renew now</a> to continue enjoying member benefits.
    </div>
<?php endif; ?>

<!-- Stats Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card stat-card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-1">Upcoming Events</h6>
                        <h2 class="mb-0"><?= $stats['upcoming_tickets'] ?></h2>
                    </div>
                    <i class="bi bi-calendar-check fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card stat-card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-1">Total Tickets</h6>
                        <h2 class="mb-0"><?= $stats['total_tickets'] ?></h2>
                    </div>
                    <i class="bi bi-ticket-perforated fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card stat-card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-1">Events Attended</h6>
                        <h2 class="mb-0"><?= $stats['checked_in'] ?></h2>
                    </div>
                    <i class="bi bi-check-circle fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link active" data-bs-toggle="tab" href="#upcoming">
            <i class="bi bi-calendar-plus"></i> Available Events
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#mytickets">
            <i class="bi bi-ticket-detailed"></i> My Tickets (<?= count($my_tickets) ?>)
        </a>
    </li>
</ul>

<div class="tab-content">
    <!-- Upcoming Events Tab -->
    <div class="tab-pane fade show active" id="upcoming">
        <?php if (empty($upcoming_events)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> No upcoming events scheduled at this time. Check back soon!
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($upcoming_events as $event): 
                    $sold_out = $event['tickets_available'] <= 0;
                    $nearly_full = $event['tickets_available'] > 0 && $event['tickets_available'] <= ($event['capacity'] * 0.1);
                    $percent_sold = $event['capacity'] > 0 ? ($event['tickets_sold'] / $event['capacity']) * 100 : 0;
                    $status_class = strtolower($event['status']);
                ?>
                    <div class="col-md-6">
                        <div class="card event-card <?= $status_class ?>">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h5 class="card-title mb-1"><?= htmlspecialchars($event['name']) ?></h5>
                                        <p class="text-muted small mb-0">
                                            <i class="bi bi-calendar"></i> <?= date('l, F j, Y', strtotime($event['event_date'])) ?>
                                        </p>
                                        <p class="text-muted small mb-0">
                                            <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($event['location_name']) ?>
                                        </p>
                                    </div>
                                    <?php if ($sold_out): ?>
                                        <span class="badge bg-danger">Sold Out</span>
                                    <?php elseif ($nearly_full): ?>
                                        <span class="badge bg-warning">Nearly Full</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Available</span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($event['description']): ?>
                                    <p class="card-text text-muted small mb-3">
                                        <?= htmlspecialchars(substr($event['description'], 0, 120)) ?>
                                        <?= strlen($event['description']) > 120 ? '...' : '' ?>
                                    </p>
                                <?php endif; ?>
                                
                                <?php if ($event['exhibition_title']): ?>
                                    <p class="small mb-2">
                                        <i class="bi bi-building"></i> 
                                        <strong>Exhibition:</strong> <?= htmlspecialchars($event['exhibition_title']) ?>
                                    </p>
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="small">
                                            <i class="bi bi-people"></i> 
                                            Capacity: <?= $event['tickets_sold'] ?> / <?= $event['capacity'] ?>
                                        </span>
                                        <span class="small">
                                            <?= $event['tickets_available'] ?> tickets available
                                        </span>
                                    </div>
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar <?= $sold_out ? 'bg-danger' : ($nearly_full ? 'bg-warning' : 'bg-success') ?>" 
                                             style="width: <?= min($percent_sold, 100) ?>%"></div>
                                    </div>
                                </div>
                                
                                <?php if (!$sold_out): ?>
                                    <button class="btn btn-primary w-100" 
                                            onclick="showPurchaseModal(<?= htmlspecialchars(json_encode($event)) ?>)">
                                        <i class="bi bi-cart-plus"></i> Purchase Tickets
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-secondary w-100" disabled>
                                        <i class="bi bi-x-circle"></i> Sold Out
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- My Tickets Tab -->
    <div class="tab-pane fade" id="mytickets">
        <?php if (empty($my_tickets)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> You haven't purchased any tickets yet. 
                Browse the <a href="#upcoming" data-bs-toggle="tab" class="alert-link">Available Events</a> tab to get started!
            </div>
        <?php else: ?>
            <?php foreach ($my_tickets as $ticket): ?>
                <div class="ticket-card <?= strtolower($ticket['event_status']) ?>">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h6 class="mb-1">
                                <i class="bi bi-ticket-detailed"></i> 
                                Ticket #<?= $ticket['ticket_id'] ?>
                                <?php if ($ticket['checked_in']): ?>
                                    <span class="badge bg-success ms-2">
                                        <i class="bi bi-check-circle"></i> Checked In
                                    </span>
                                <?php endif; ?>
                            </h6>
                            <h5 class="mb-2"><?= htmlspecialchars($ticket['event_name']) ?></h5>
                            <div class="d-flex gap-3 text-muted small">
                                <span>
                                    <i class="bi bi-calendar"></i> 
                                    <?= date('M j, Y', strtotime($ticket['event_date'])) ?>
                                </span>
                                <span>
                                    <i class="bi bi-geo-alt"></i> 
                                    <?= htmlspecialchars($ticket['location_name']) ?>
                                </span>
                                <span>
                                    <i class="bi bi-people"></i> 
                                    <?= $ticket['quantity'] ?> attendee<?= $ticket['quantity'] > 1 ? 's' : '' ?>
                                </span>
                            </div>
                            <?php if ($ticket['checked_in'] && $ticket['check_in_time']): ?>
                                <p class="text-muted small mb-0 mt-2">
                                    <i class="bi bi-clock-history"></i> 
                                    Checked in: <?= date('M j, Y g:i A', strtotime($ticket['check_in_time'])) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4 text-end">
                            <?php if ($ticket['event_status'] === 'Upcoming' || $ticket['event_status'] === 'Today'): ?>
                                <span class="badge bg-primary mb-2 d-block">
                                    <?= $ticket['event_status'] === 'Today' ? 'Event Today!' : 'Upcoming' ?>
                                </span>
                                <small class="text-muted d-block">
                                    Purchased: <?= date('M j, Y', strtotime($ticket['purchase_date'])) ?>
                                </small>
                            <?php else: ?>
                                <span class="badge bg-secondary mb-2 d-block">Past Event</span>
                                <small class="text-muted d-block">
                                    Purchased: <?= date('M j, Y', strtotime($ticket['purchase_date'])) ?>
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Purchase Ticket Modal -->
<div class="modal fade" id="purchaseModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-cart-plus"></i> Purchase Tickets
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="purchase">
                <input type="hidden" name="event_id" id="purchase_event_id">
                
                <div class="modal-body">
                    <div class="alert alert-success">
                        <i class="bi bi-star-fill"></i> 
                        <strong>Member Benefit:</strong> As a <?= htmlspecialchars($member_info['membership_name']) ?> member, 
                        you receive priority booking and member pricing on all events!
                    </div>
                    
                    <h5 id="purchase_event_name" class="mb-3"></h5>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Date:</strong></p>
                            <p class="text-muted" id="purchase_event_date"></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Location:</strong></p>
                            <p class="text-muted" id="purchase_event_location"></p>
                        </div>
                    </div>
                    
                    <div id="purchase_event_description" class="mb-3"></div>
                    
                    <div class="mb-3">
                        <p class="mb-1"><strong>Availability:</strong></p>
                        <p class="text-muted">
                            <span id="purchase_tickets_available"></span> tickets remaining
                        </p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">
                            <strong>Number of Tickets *</strong>
                        </label>
                        <input type="number" 
                               class="form-control form-control-lg" 
                               name="quantity" 
                               id="purchase_quantity"
                               min="1" 
                               value="1" 
                               required>
                        <small class="text-muted">
                            You can purchase up to <span id="purchase_max_quantity"></span> tickets for this event.
                        </small>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> 
                        You will receive a confirmation email with your ticket details and QR code for check-in.
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Confirm Purchase
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showPurchaseModal(event) {
    document.getElementById('purchase_event_id').value = event.event_id;
    document.getElementById('purchase_event_name').textContent = event.name;
    document.getElementById('purchase_event_date').textContent = new Date(event.event_date).toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
    document.getElementById('purchase_event_location').textContent = event.location_name || 'TBD';
    document.getElementById('purchase_event_description').innerHTML = event.description ? 
        '<p class="text-muted">' + event.description + '</p>' : '';
    document.getElementById('purchase_tickets_available').textContent = event.tickets_available;
    document.getElementById('purchase_max_quantity').textContent = event.tickets_available;
    document.getElementById('purchase_quantity').max = event.tickets_available;
    
    new bootstrap.Modal(document.getElementById('purchaseModal')).show();
}
</script>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>
