<?php
session_start();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/permissions.php';

requirePermission('checkin_ticket');

$page_title = 'Check-in System';
$db = db();

$success = '';
$error = '';
$ticket_info = null;

// Handle ticket lookup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'lookup') {
    $ticket_id = intval($_POST['ticket_id']);
    
    $stmt = $db->prepare("
        SELECT 
            t.*,
            e.name as event_name,
            e.event_date,
            e.capacity,
            l.name as location_name,
            m.first_name as member_fname, m.last_name as member_lname, m.email as member_email,
            v.first_name as visitor_fname, v.last_name as visitor_lname, v.email as visitor_email
        FROM TICKET t
        JOIN EVENT e ON t.event_id = e.event_id
        LEFT JOIN LOCATION l ON e.location_id = l.location_id
        LEFT JOIN MEMBER m ON t.member_id = m.member_id
        LEFT JOIN VISITOR v ON t.visitor_id = v.visitor_id
        WHERE t.ticket_id = ?
    ");
    $stmt->bind_param('i', $ticket_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $ticket_info = $result->fetch_assoc();
    $stmt->close();
    
    if (!$ticket_info) {
        $error = "Ticket #$ticket_id not found.";
    }
}

// Handle check-in
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'checkin') {
    $ticket_id = intval($_POST['ticket_id']);
    
    // Get current ticket status
    $stmt = $db->prepare("SELECT checked_in, event_id FROM TICKET WHERE ticket_id = ?");
    $stmt->bind_param('i', $ticket_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $ticket = $result->fetch_assoc();
    $stmt->close();
    
    if (!$ticket) {
        $error = "Ticket not found.";
    } elseif ($ticket['checked_in']) {
        $error = "This ticket has already been checked in.";
    } else {
        // Check in the ticket
        $checkin_time = date('Y-m-d H:i:s');
        $stmt = $db->prepare("UPDATE TICKET SET checked_in = 1, check_in_time = ? WHERE ticket_id = ?");
        $stmt->bind_param('si', $checkin_time, $ticket_id);
        
        if ($stmt->execute()) {
            $success = "Ticket #$ticket_id checked in successfully!";
            logActivity('checkin', 'TICKET', $ticket_id, "Checked in ticket");
            
            // Reload ticket info
            $stmt2 = $db->prepare("
                SELECT 
                    t.*,
                    e.name as event_name,
                    e.event_date,
                    e.capacity,
                    l.name as location_name,
                    m.first_name as member_fname, m.last_name as member_lname, m.email as member_email,
                    v.first_name as visitor_fname, v.last_name as visitor_lname, v.email as visitor_email
                FROM TICKET t
                JOIN EVENT e ON t.event_id = e.event_id
                LEFT JOIN LOCATION l ON e.location_id = l.location_id
                LEFT JOIN MEMBER m ON t.member_id = m.member_id
                LEFT JOIN VISITOR v ON t.visitor_id = v.visitor_id
                WHERE t.ticket_id = ?
            ");
            $stmt2->bind_param('i', $ticket_id);
            $stmt2->execute();
            $result = $stmt2->get_result();
            $ticket_info = $result->fetch_assoc();
            $stmt2->close();
        } else {
            $error = "Failed to check in ticket: " . $db->error;
        }
        $stmt->close();
    }
}

// Get today's events for quick reference
$today_events = $db->query("
    SELECT 
        e.event_id,
        e.name,
        e.event_date,
        e.capacity,
        COUNT(t.ticket_id) as tickets_sold,
        SUM(CASE WHEN t.checked_in = 1 THEN 1 ELSE 0 END) as checked_in_count
    FROM EVENT e
    LEFT JOIN TICKET t ON e.event_id = t.event_id
    WHERE DATE(e.event_date) = CURDATE()
    GROUP BY e.event_id, e.name, e.event_date, e.capacity
")->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../templates/layout_header.php';
?>

<style>
.ticket-card {
    background: white;
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    border-left: 5px solid #28a745;
}

.ticket-card.checked-in {
    border-left-color: #6c757d;
    opacity: 0.8;
}

.status-badge {
    font-size: 1.1rem;
    padding: 10px 20px;
}

.lookup-box {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 15px;
    padding: 40px;
    margin-bottom: 30px;
}

.lookup-box input {
    font-size: 1.2rem;
    padding: 15px;
}

.event-summary-card {
    border-left: 4px solid #007bff;
}
</style>

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

<!-- Ticket Lookup -->
<div class="lookup-box">
    <h3 class="mb-4"><i class="bi bi-search"></i> Ticket Lookup</h3>
    <form method="POST">
        <input type="hidden" name="action" value="lookup">
        <div class="input-group input-group-lg">
            <span class="input-group-text"><i class="bi bi-ticket-perforated"></i></span>
            <input type="number" 
                   class="form-control" 
                   name="ticket_id" 
                   placeholder="Enter Ticket ID" 
                   required
                   autofocus>
            <button type="submit" class="btn btn-light btn-lg px-5">
                <i class="bi bi-search"></i> Look Up
            </button>
        </div>
    </form>
</div>

<!-- Ticket info -->
<?php if ($ticket_info): ?>
    <div class="ticket-card <?= $ticket_info['checked_in'] ? 'checked-in' : '' ?> mb-4">
        <div class="row">
            <div class="col-md-8">
                <h4>
                    <i class="bi bi-ticket-detailed"></i> Ticket #<?= $ticket_info['ticket_id'] ?>
                    <?php if ($ticket_info['checked_in']): ?>
                        <span class="badge bg-secondary status-badge ms-3">
                            <i class="bi bi-check-circle"></i> Already Checked In
                        </span>
                    <?php else: ?>
                        <span class="badge bg-success status-badge ms-3">
                            <i class="bi bi-clock"></i> Ready to Check In
                        </span>
                    <?php endif; ?>
                </h4>
                
                <hr class="my-4">
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <h6 class="text-muted">Event</h6>
                        <p class="fs-5 mb-0"><strong><?= htmlspecialchars($ticket_info['event_name']) ?></strong></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <h6 class="text-muted">Date</h6>
                        <p class="fs-5 mb-0"><?= date('l, F j, Y', strtotime($ticket_info['event_date'])) ?></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <h6 class="text-muted">Location</h6>
                        <p class="fs-5 mb-0"><?= htmlspecialchars($ticket_info['location_name'] ?? 'TBD') ?></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <h6 class="text-muted">Quantity</h6>
                        <p class="fs-5 mb-0"><?= $ticket_info['quantity'] ?> attendee(s)</p>
                    </div>
                </div>
                
                <hr class="my-4">
                
                <h6 class="text-muted">Guest Information</h6>
                <?php if ($ticket_info['member_id']): ?>
                    <p class="mb-1">
                        <span class="badge bg-primary">Member</span>
                        <strong><?= htmlspecialchars($ticket_info['member_fname'] . ' ' . $ticket_info['member_lname']) ?></strong>
                    </p>
                    <p class="text-muted"><?= htmlspecialchars($ticket_info['member_email']) ?></p>
                <?php elseif ($ticket_info['visitor_id']): ?>
                    <p class="mb-1">
                        <span class="badge bg-info">Visitor</span>
                        <strong><?= htmlspecialchars($ticket_info['visitor_fname'] . ' ' . $ticket_info['visitor_lname']) ?></strong>
                    </p>
                    <p class="text-muted"><?= htmlspecialchars($ticket_info['visitor_email']) ?></p>
                <?php else: ?>
                    <p class="text-muted">Walk-in guest</p>
                <?php endif; ?>
                
                <?php if ($ticket_info['checked_in']): ?>
                    <hr class="my-4">
                    <p class="text-muted mb-0">
                        <i class="bi bi-clock-history"></i> Checked in: 
                        <?= date('g:i A on F j, Y', strtotime($ticket_info['check_in_time'])) ?>
                    </p>
                <?php endif; ?>
            </div>
            
            <div class="col-md-4 text-center d-flex align-items-center justify-content-center">
                <?php if (!$ticket_info['checked_in']): ?>
                    <form method="POST" onsubmit="return confirm('Check in this ticket?');">
                        <input type="hidden" name="action" value="checkin">
                        <input type="hidden" name="ticket_id" value="<?= $ticket_info['ticket_id'] ?>">
                        <button type="submit" class="btn btn-success btn-lg px-5 py-3">
                            <i class="bi bi-check-circle fs-1 d-block mb-2"></i>
                            <strong>CHECK IN</strong>
                        </button>
                    </form>
                <?php else: ?>
                    <div class="text-secondary">
                        <i class="bi bi-check-circle-fill" style="font-size: 5rem;"></i>
                        <p class="mt-3 fs-5">Checked In</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- todays events summary -->
<?php if (!empty($today_events)): ?>
    <div class="card">
        <div class="card-body">
            <h5 class="card-title mb-4"><i class="bi bi-calendar-day"></i> Today's Events</h5>
            <div class="row">
                <?php foreach ($today_events as $event): 
                    $checkin_pct = $event['tickets_sold'] > 0 ? round(($event['checked_in_count'] / $event['tickets_sold']) * 100) : 0;
                ?>
                    <div class="col-md-4 mb-3">
                        <div class="card event-summary-card">
                            <div class="card-body">
                                <h6><?= htmlspecialchars($event['name']) ?></h6>
                                <div class="d-flex justify-content-between mt-3">
                                    <div>
                                        <small class="text-muted d-block">Checked In</small>
                                        <strong class="fs-4"><?= $event['checked_in_count'] ?></strong>
                                        <small class="text-muted">/ <?= $event['tickets_sold'] ?></small>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-muted d-block">Progress</small>
                                        <strong class="fs-4 text-primary"><?= $checkin_pct ?>%</strong>
                                    </div>
                                </div>
                                <div class="progress mt-3" style="height: 8px;">
                                    <div class="progress-bar bg-primary" style="width: <?= $checkin_pct ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>