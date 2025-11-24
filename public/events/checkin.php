<?php
session_start();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/permissions.php';

requirePermission('checkin_ticket');

$page_title = 'Check-in System';

$success = '';
$error = '';
$ticket_info = null;

try {
    $db = db();

    // Handle ticket lookup
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'lookup') {
        $ticket_id = intval($_POST['ticket_id']);
        
        if ($ticket_id <= 0) {
            throw new Exception("Invalid ticket ID");
        }
        
        $stmt = $db->prepare("
            SELECT 
                t.ticket_id,
                t.event_id,
                t.member_id,
                t.visitor_id,
                t.quantity,
                t.checked_in,
                t.check_in_time,
                t.purchase_date,
                e.name as event_name,
                e.event_date,
                e.capacity,
                l.name as location_name,
                m.first_name as member_first_name, 
                m.last_name as member_last_name, 
                m.email as member_email,
                v.first_name as visitor_first_name, 
                v.last_name as visitor_last_name, 
                v.email as visitor_email
            FROM TICKET t
            JOIN EVENT e ON t.event_id = e.event_id
            LEFT JOIN LOCATION l ON e.location_id = l.location_id
            LEFT JOIN MEMBER m ON t.member_id = m.member_id
            LEFT JOIN VISITOR v ON t.visitor_id = v.visitor_id
            WHERE t.ticket_id = ?
        ");
        
        if (!$stmt) {
            throw new Exception("Failed to prepare lookup query: " . $db->error);
        }
        
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
        
        if ($ticket_id <= 0) {
            throw new Exception("Invalid ticket ID");
        }
        
        // Start transaction
        $db->begin_transaction();
        
        try {
            // Get current ticket status with row lock
            $stmt = $db->prepare("SELECT checked_in, event_id FROM TICKET WHERE ticket_id = ? FOR UPDATE");
            if (!$stmt) {
                throw new Exception("Failed to prepare status check: " . $db->error);
            }
            
            $stmt->bind_param('i', $ticket_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $ticket = $result->fetch_assoc();
            $stmt->close();
            
            if (!$ticket) {
                throw new Exception("Ticket not found.");
            }
            
            if ($ticket['checked_in']) {
                throw new Exception("This ticket has already been checked in.");
            }
            
            // Check in the ticket
            $checkin_time = date('Y-m-d H:i:s');
            $stmt = $db->prepare("UPDATE TICKET SET checked_in = 1, check_in_time = ? WHERE ticket_id = ?");
            if (!$stmt) {
                throw new Exception("Failed to prepare check-in update: " . $db->error);
            }
            
            $stmt->bind_param('si', $checkin_time, $ticket_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to check in ticket: " . $stmt->error);
            }
            $stmt->close();
            
            // Log activity
            $user_id = $_SESSION['user_id'] ?? null;
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $description = "Checked in ticket #$ticket_id";
            
            $log_stmt = $db->prepare("
                INSERT INTO ACTIVITY_LOG (user_id, action_type, table_name, record_id, description, ip_address, timestamp) 
                VALUES (?, 'check_in', 'TICKET', ?, ?, ?, NOW())
            ");
            
            if ($log_stmt) {
                $log_stmt->bind_param('iiss', $user_id, $ticket_id, $description, $ip_address);
                $log_stmt->execute();
                $log_stmt->close();
            }
            
            // Commit transaction
            $db->commit();
            
            // Set success message
            $success = "Ticket #$ticket_id checked in successfully!";
            
            // fetch the ticket info to show updated status
            $stmt = $db->prepare("
                SELECT 
                    t.ticket_id,
                    t.event_id,
                    t.member_id,
                    t.visitor_id,
                    t.quantity,
                    t.checked_in,
                    t.check_in_time,
                    t.purchase_date,
                    e.name as event_name,
                    e.event_date,
                    e.capacity,
                    l.name as location_name,
                    m.first_name as member_first_name, 
                    m.last_name as member_last_name, 
                    m.email as member_email,
                    v.first_name as visitor_first_name, 
                    v.last_name as visitor_last_name, 
                    v.email as visitor_email
                FROM TICKET t
                JOIN EVENT e ON t.event_id = e.event_id
                LEFT JOIN LOCATION l ON e.location_id = l.location_id
                LEFT JOIN MEMBER m ON t.member_id = m.member_id
                LEFT JOIN VISITOR v ON t.visitor_id = v.visitor_id
                WHERE t.ticket_id = ?
            ");
            
            if ($stmt) {
                $stmt->bind_param('i', $ticket_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $ticket_info = $result->fetch_assoc();
                $stmt->close();
            }
            
        } catch (Exception $e) {
            // Rollback on error
            $db->rollback();
            throw $e;
        }
    }

    // Get today's events for quick reference
    $today_events_query = "
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
    ";
    
    $result = $db->query($today_events_query);
    if (!$result) {
        throw new Exception("Failed to fetch today's events: " . $db->error);
    }
    $today_events = $result->fetch_all(MYSQLI_ASSOC);

} catch (Exception $e) {
    error_log("Check-in System Error: " . $e->getMessage());
    $error = $e->getMessage();
}

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

#checkinBtn {
    transition: all 0.3s ease;
}

#checkinBtn:hover {
    transform: scale(1.05);
    box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
}

#checkinBtn:active {
    transform: scale(0.98);
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
                   min="1"
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
                    <i class="bi bi-ticket-detailed"></i> Ticket #<?= htmlspecialchars($ticket_info['ticket_id']) ?>
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
                        <p class="fs-5 mb-0"><?= htmlspecialchars($ticket_info['quantity']) ?> attendee(s)</p>
                    </div>
                </div>
                
                <hr class="my-4">
                
                <h6 class="text-muted">Guest Information</h6>
                <?php if ($ticket_info['member_id']): ?>
                    <?php 
                    $first = $ticket_info['member_first_name'] ?? '';
                    $last = $ticket_info['member_last_name'] ?? '';
                    $member_name = trim($first . ' ' . $last);
    
                    // If still empty or just whitespace, use Unknown Member
                    if (empty($member_name)) {
                        $member_name = 'Unknown Member';
                    }
                    ?>
                    <p class="mb-1">
                        <span class="badge bg-primary">Member</span>
                        <strong><?= htmlspecialchars($member_name) ?></strong>
                    </p>
                <?php elseif ($ticket_info['visitor_id']): ?>
                    <?php 
                    $first = $ticket_info['visitor_first_name'] ?? '';
                    $last = $ticket_info['visitor_last_name'] ?? '';
                    $visitor_name = trim($first . ' ' . $last);
    
                    // If still empty or just whitespace, use Unknown Visitor
                    if (empty($visitor_name)) {
                        $visitor_name = 'Unknown Visitor';
                    }
                    ?>
                    <p class="mb-1">
                        <span class="badge bg-info">Visitor</span>
                        <strong><?= htmlspecialchars($visitor_name) ?></strong>
                    </p>
                    <?php if (!empty($ticket_info['visitor_email'])): ?>
                        <p class="text-muted"><?= htmlspecialchars($ticket_info['visitor_email']) ?></p>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-muted">Walk-in guest</p>
                <?php endif; ?>
                
                <?php if ($ticket_info['checked_in'] && $ticket_info['check_in_time']): ?>
                    <hr class="my-4">
                    <p class="text-muted mb-0">
                        <i class="bi bi-clock-history"></i> Checked in: 
                        <?= date('g:i A \o\n F j, Y', strtotime($ticket_info['check_in_time'])) ?>
                    </p>
                <?php endif; ?>
            </div>
            
            <div class="col-md-4 text-center d-flex align-items-center justify-content-center">
                <?php if (!$ticket_info['checked_in']): ?>
                    <form method="POST" id="checkinForm">
                        <input type="hidden" name="action" value="checkin">
                        <input type="hidden" name="ticket_id" value="<?= htmlspecialchars($ticket_info['ticket_id']) ?>">
                        <button type="submit" class="btn btn-success btn-lg px-5 py-3" id="checkinBtn">
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

<!-- Todays events summary -->
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
                                        <strong class="fs-4"><?= number_format($event['checked_in_count']) ?></strong>
                                        <small class="text-muted">/ <?= number_format($event['tickets_sold']) ?></small>
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
<?php else: ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> No events scheduled for today.
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>