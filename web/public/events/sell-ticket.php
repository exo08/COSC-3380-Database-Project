<?php
session_start();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/permissions.php';

requirePermission('sell_ticket');

$page_title = 'Sell Tickets';
$db = db();

$success = '';
$error = '';
$selected_event = null;

// Get event details if event_id is provided
if (isset($_GET['event_id'])) {
    $event_id = intval($_GET['event_id']);
    $stmt = $db->prepare("
        SELECT 
            e.*,
            l.name as location_name,
            ex.title as exhibition_title,
            (SELECT COUNT(*) FROM TICKET WHERE event_id = e.event_id) as tickets_sold,
            (e.capacity - (SELECT COUNT(*) FROM TICKET WHERE event_id = e.event_id)) as tickets_available
        FROM EVENT e
        LEFT JOIN LOCATION l ON e.location_id = l.location_id
        LEFT JOIN EXHIBITION ex ON e.exhibition_id = ex.exhibition_id
        WHERE e.event_id = ?
    ");
    $stmt->bind_param('i', $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $selected_event = $result->fetch_assoc();
    $stmt->close();
}

// Handle ticket sale
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'sell') {
    $event_id = intval($_POST['event_id']);
    $customer_type = $_POST['customer_type'];
    $quantity = intval($_POST['quantity']);
    $purchase_date = date('Y-m-d');
    
    $member_id = null;
    $visitor_id = null;
    
    // Check available capacity
    $stmt = $db->prepare("
        SELECT capacity, (SELECT COUNT(*) FROM TICKET WHERE event_id = ?) as tickets_sold 
        FROM EVENT WHERE event_id = ?
    ");
    $stmt->bind_param('ii', $event_id, $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $event_check = $result->fetch_assoc();
    $stmt->close();
    
    $available = $event_check['capacity'] - $event_check['tickets_sold'];
    
    if ($quantity > $available) {
        $error = "Not enough tickets available. Only $available tickets remaining.";
    } else {
        try {
            if ($customer_type === 'member') {
                // Existing member
                $member_id = intval($_POST['member_id']);
                
            } elseif ($customer_type === 'new_visitor') {
                // Create new visitor - DIRECT INSERT (bypassing stored procedure)
                $first_name = trim($_POST['first_name']);
                $last_name = trim($_POST['last_name']);
                $email = trim($_POST['email']);
                $phone = trim($_POST['phone']);
                $is_student = isset($_POST['is_student']) ? 1 : 0;
                $created_at = date('Y-m-d');
    
                // Validate
                if (empty($first_name)) {
                    throw new Exception("First name is required");
                }
                if (empty($email)) {
                    throw new Exception("Email is required");
                }
    
                // Use direct INSERT with proper NULL handling
                if (empty($last_name)) {
                    $last_name = null;
                }
                if (empty($phone)) {
                    $phone = null;
                }
    
                // Direct INSERT instead of stored procedure
                $stmt = $db->prepare("
                    INSERT INTO VISITOR (first_name, last_name, is_student, email, phone, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
    
                $stmt->bind_param('ssisss', $first_name, $last_name, $is_student, $email, $phone, $created_at);
    
                if (!$stmt->execute()) {
                    throw new Exception("Failed to create visitor: " . $stmt->error);
                }
    
                $visitor_id = $db->insert_id;
                $stmt->close();
            }
            
            // Create ticket
            $checked_in = 0;
            $check_in_time = null;
            
            $stmt = $db->prepare("CALL CreateTicket(?, ?, ?, ?, ?, ?, ?, @new_ticket_id)");
            $stmt->bind_param('iiisiss', $event_id, $visitor_id, $member_id, $purchase_date, $quantity, $checked_in, $check_in_time);
            
            if ($stmt->execute()) {
                $stmt->close();
                $db->next_result();
                
                $result = $db->query("SELECT @new_ticket_id as ticket_id");
                $row = $result->fetch_assoc();
                $ticket_id = $row['ticket_id'];
                
                $success = "Ticket #$ticket_id sold successfully!";
                logActivity('create', 'TICKET', $ticket_id, "Sold $quantity ticket(s) for event #$event_id");
                
                // Refresh event data
                if ($selected_event) {
                    $stmt = $db->prepare("
                        SELECT 
                            e.*,
                            l.name as location_name,
                            ex.title as exhibition_title,
                            (SELECT COUNT(*) FROM TICKET WHERE event_id = e.event_id) as tickets_sold,
                            (e.capacity - (SELECT COUNT(*) FROM TICKET WHERE event_id = e.event_id)) as tickets_available
                        FROM EVENT e
                        LEFT JOIN LOCATION l ON e.location_id = l.location_id
                        LEFT JOIN EXHIBITION ex ON e.exhibition_id = ex.exhibition_id
                        WHERE e.event_id = ?
                    ");
                    $stmt->bind_param('i', $event_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $selected_event = $result->fetch_assoc();
                    $stmt->close();
                }
            } else {
                throw new Exception($db->error);
            }
            
        } catch (Exception $e) {
            $error = "Failed to create ticket: " . $e->getMessage();
        }
    }
}

// Get upcoming events
$upcoming_events = $db->query("
    SELECT 
        e.event_id,
        e.name,
        e.event_date,
        e.capacity,
        l.name as location_name,
        (SELECT COUNT(*) FROM TICKET WHERE event_id = e.event_id) as tickets_sold
    FROM EVENT e
    LEFT JOIN LOCATION l ON e.location_id = l.location_id
    WHERE e.event_date >= CURDATE()
    ORDER BY e.event_date ASC
    LIMIT 20
")->fetch_all(MYSQLI_ASSOC);

// Get members for dropdown
$members = $db->query("
    SELECT member_id, first_name, last_name, email 
    FROM MEMBER 
    WHERE expiration_date >= CURDATE()
    ORDER BY last_name, first_name
")->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../templates/layout_header.php';
?>

<style>
.event-selector-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 15px;
    padding: 30px;
    margin-bottom: 30px;
}

.event-info-card {
    background: white;
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    border-left: 5px solid #007bff;
}

.customer-type-btn {
    padding: 20px;
    border: 2px solid #dee2e6;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.3s;
}

.customer-type-btn:hover {
    border-color: #007bff;
    background: #f8f9fa;
}

.customer-type-btn.active {
    border-color: #007bff;
    background: #e7f1ff;
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

<!-- Event Selector -->
<div class="event-selector-card">
    <h4 class="mb-3"><i class="bi bi-calendar-event"></i> Select Event</h4>
    <form method="GET">
        <select class="form-select form-select-lg" name="event_id" onchange="this.form.submit()" required>
            <option value="">Choose an event...</option>
            <?php foreach ($upcoming_events as $event): 
                $available = $event['capacity'] - $event['tickets_sold'];
                $sold_out = $available <= 0;
            ?>
                <option value="<?= $event['event_id'] ?>" 
                        <?= ($selected_event && $selected_event['event_id'] == $event['event_id']) ? 'selected' : '' ?>
                        <?= $sold_out ? 'disabled' : '' ?>>
                    <?= htmlspecialchars($event['name']) ?> - <?= date('M j, Y', strtotime($event['event_date'])) ?> 
                    (<?= $available ?> available)
                    <?= $sold_out ? ' - SOLD OUT' : '' ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<?php if ($selected_event): ?>
    <!-- Event Information -->
    <div class="event-info-card mb-4">
        <h4 class="mb-4"><?= htmlspecialchars($selected_event['name']) ?></h4>
        <div class="row">
            <div class="col-md-3">
                <h6 class="text-muted">Date</h6>
                <p><?= date('l, F j, Y', strtotime($selected_event['event_date'])) ?></p>
            </div>
            <div class="col-md-3">
                <h6 class="text-muted">Location</h6>
                <p><?= htmlspecialchars($selected_event['location_name'] ?? 'TBD') ?></p>
            </div>
            <div class="col-md-3">
                <h6 class="text-muted">Capacity</h6>
                <p><?= $selected_event['capacity'] ?> attendees</p>
            </div>
            <div class="col-md-3">
                <h6 class="text-muted">Available</h6>
                <p class="text-<?= $selected_event['tickets_available'] <= 10 ? 'danger' : 'success' ?>">
                    <strong><?= $selected_event['tickets_available'] ?> tickets</strong>
                </p>
            </div>
        </div>
        
        <?php if ($selected_event['description']): ?>
            <hr>
            <p class="text-muted mb-0"><?= htmlspecialchars($selected_event['description']) ?></p>
        <?php endif; ?>
    </div>

    <!-- Ticket Sale Form -->
    <?php if ($selected_event['tickets_available'] > 0): ?>
        <div class="card">
            <div class="card-body">
                <h5 class="card-title mb-4"><i class="bi bi-ticket-perforated"></i> Sell Tickets</h5>
                
                <form method="POST" id="sellTicketForm">
                    <input type="hidden" name="action" value="sell">
                    <input type="hidden" name="event_id" value="<?= $selected_event['event_id'] ?>">
                    
                    <!-- Customer Type Selection -->
                    <div class="mb-4">
                        <label class="form-label"><strong>Customer Type *</strong></label>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="customer-type-btn" onclick="selectCustomerType('member')">
                                    <input type="radio" name="customer_type" value="member" id="type_member" required>
                                    <label for="type_member" class="d-block" style="cursor: pointer;">
                                        <i class="bi bi-star fs-3 d-block mb-2"></i>
                                        <strong>Existing Member</strong>
                                        <p class="text-muted mb-0 small">Select from member list</p>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="customer-type-btn" onclick="selectCustomerType('new_visitor')">
                                    <input type="radio" name="customer_type" value="new_visitor" id="type_visitor" required>
                                    <label for="type_visitor" class="d-block" style="cursor: pointer;">
                                        <i class="bi bi-person-plus fs-3 d-block mb-2"></i>
                                        <strong>New Visitor</strong>
                                        <p class="text-muted mb-0 small">Enter visitor information</p>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Member Selection -->
                    <div id="memberSection" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label">Select Member *</label>
                            <select class="form-select" name="member_id">
                                <option value="">Choose member...</option>
                                <?php foreach ($members as $member): ?>
                                    <option value="<?= $member['member_id'] ?>">
                                        <?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) ?> 
                                        (<?= htmlspecialchars($member['email']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Visitor Information -->
                    <div id="visitorSection" style="display: none;">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">First Name *</label>
                                <input type="text" class="form-control" name="first_name">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Name *</label>
                                <input type="text" class="form-control" name="last_name">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone</label>
                                <input type="tel" class="form-control" name="phone">
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="is_student" id="is_student">
                                <label class="form-check-label" for="is_student">
                                    Student (discounted rate)
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quantity -->
                    <div class="mb-4">
                        <label class="form-label">Number of Tickets *</label>
                        <input type="number" 
                               class="form-control form-control-lg" 
                               name="quantity" 
                               min="1" 
                               max="<?= $selected_event['tickets_available'] ?>" 
                               value="1" 
                               required>
                        <small class="text-muted">Maximum: <?= $selected_event['tickets_available'] ?> tickets</small>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-cart-check"></i> Complete Sale
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i> This event is sold out. No tickets available.
        </div>
    <?php endif; ?>
<?php endif; ?>

<script>
function selectCustomerType(type) {
    // Update radio buttons
    document.querySelectorAll('input[name="customer_type"]').forEach(radio => {
        radio.checked = (radio.value === type);
    });
    
    // Update visual styling
    document.querySelectorAll('.customer-type-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.currentTarget.classList.add('active');
    
    // Show/hide sections
    document.getElementById('memberSection').style.display = (type === 'member') ? 'block' : 'none';
    document.getElementById('visitorSection').style.display = (type === 'new_visitor') ? 'block' : 'none';
    
    // Set required fields
    if (type === 'member') {
        document.querySelector('select[name="member_id"]').required = true;
        document.querySelector('input[name="first_name"]').required = false;
        document.querySelector('input[name="last_name"]').required = false;
        document.querySelector('input[name="email"]').required = false;
    } else {
        document.querySelector('select[name="member_id"]').required = false;
        document.querySelector('input[name="first_name"]').required = true;
        document.querySelector('input[name="last_name"]').required = true;
        document.querySelector('input[name="email"]').required = true;
    }
}
</script>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>