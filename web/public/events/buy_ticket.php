<?php
session_start();
require_once __DIR__ . '/../app/db.php';

$page_title = 'Buy Event Tickets';
$db = db();

$success = '';
$error = '';
$selected_event = null;

// Check if user is logged in as member
$is_member = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'member';
$member_id = $is_member ? ($_SESSION['linked_id'] ?? null) : null;

// Get event details if event_id is provided
if (isset($_GET['event_id'])) {
    $event_id = intval($_GET['event_id']);
    $stmt = $db->prepare("
        SELECT 
            e.*,
            l.name as location_name,
            ex.title as exhibition_title,
            COALESCE(SUM(t.quantity), 0) as tickets_sold,
            (e.capacity - COALESCE(SUM(t.quantity), 0)) as tickets_available
        FROM EVENT e
        LEFT JOIN LOCATION l ON e.location_id = l.location_id
        LEFT JOIN EXHIBITION ex ON e.exhibition_id = ex.exhibition_id
        LEFT JOIN TICKET t ON e.event_id = t.event_id
        WHERE e.event_id = ? AND e.event_date >= CURDATE()
        GROUP BY e.event_id
    ");
    $stmt->bind_param('i', $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $selected_event = $result->fetch_assoc();
    $stmt->close();
}

// Handle ticket purchase
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'purchase') {
    $event_id = intval($_POST['event_id']);
    $quantity = intval($_POST['quantity']);
    $purchase_date = date('Y-m-d');
    
    $visitor_id = null;
    $purchase_member_id = null;
    
    // Check available capacity
    $stmt = $db->prepare("
        SELECT capacity, COALESCE(SUM(t.quantity), 0) as tickets_sold 
        FROM EVENT e
        LEFT JOIN TICKET t ON e.event_id = t.event_id
        WHERE e.event_id = ?
        GROUP BY e.event_id
    ");
    $stmt->bind_param('i', $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $event_check = $result->fetch_assoc();
    $stmt->close();
    
    $available = $event_check['capacity'] - $event_check['tickets_sold'];
    
    if ($quantity > $available) {
        $error = "Not enough tickets available. Only $available tickets remaining.";
    } elseif ($quantity < 1) {
        $error = "Please select at least 1 ticket.";
    } else {
        try {
            $db->begin_transaction();
            
            if ($is_member && $member_id) {
                // Logged-in member purchase
                $purchase_member_id = $member_id;
                
            } else {
                // Guest/Visitor purchase - collect info
                $first_name = trim($_POST['first_name'] ?? '');
                $last_name = trim($_POST['last_name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $is_student = isset($_POST['is_student']) ? 1 : 0;
                $created_at = date('Y-m-d');
                
                // Validate required fields
                if (empty($first_name) || empty($last_name) || empty($email)) {
                    throw new Exception("Please fill in all required fields.");
                }
                
                // Create visitor record
                $stmt = $db->prepare("CALL CreateVisitor(?, ?, ?, ?, ?, ?, @new_visitor_id)");
                $stmt->bind_param('sissss', $first_name, $last_name, $is_student, $email, $phone, $created_at);
                $stmt->execute();
                $stmt->close();
                $db->next_result();
                
                $result = $db->query("SELECT @new_visitor_id as visitor_id");
                $row = $result->fetch_assoc();
                $visitor_id = $row['visitor_id'];
            }
            
            // Create ticket
            $checked_in = 0;
            $check_in_time = null;
            
            $stmt = $db->prepare("CALL CreateTicket(?, ?, ?, ?, ?, ?, ?, @new_ticket_id)");
            $stmt->bind_param('iiisiss', $event_id, $visitor_id, $purchase_member_id, $purchase_date, $quantity, $checked_in, $check_in_time);
            
            if ($stmt->execute()) {
                $stmt->close();
                $db->next_result();
                
                $result = $db->query("SELECT @new_ticket_id as ticket_id");
                $row = $result->fetch_assoc();
                $ticket_id = $row['ticket_id'];
                
                $db->commit();
                
                $success = "Success! Your ticket(s) have been purchased. Confirmation #$ticket_id";
                
                // Refresh event data
                if ($selected_event) {
                    $stmt = $db->prepare("
                        SELECT 
                            e.*,
                            l.name as location_name,
                            ex.title as exhibition_title,
                            COALESCE(SUM(t.quantity), 0) as tickets_sold,
                            (e.capacity - COALESCE(SUM(t.quantity), 0)) as tickets_available
                        FROM EVENT e
                        LEFT JOIN LOCATION l ON e.location_id = l.location_id
                        LEFT JOIN EXHIBITION ex ON e.exhibition_id = ex.exhibition_id
                        LEFT JOIN TICKET t ON e.event_id = t.event_id
                        WHERE e.event_id = ?
                        GROUP BY e.event_id
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
            $db->rollback();
            $error = "Failed to purchase ticket: " . $e->getMessage();
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
        e.description,
        l.name as location_name,
        COALESCE(SUM(t.quantity), 0) as tickets_sold,
        (e.capacity - COALESCE(SUM(t.quantity), 0)) as tickets_available
    FROM EVENT e
    LEFT JOIN LOCATION l ON e.location_id = l.location_id
    LEFT JOIN TICKET t ON e.event_id = t.event_id
    WHERE e.event_date >= CURDATE()
    GROUP BY e.event_id
    ORDER BY e.event_date ASC
    LIMIT 20
")->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../templates/header.php';
?>

<style>
.hero-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 20px;
    padding: 50px 30px;
    margin-bottom: 40px;
    text-align: center;
}

.event-card {
    border: none;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: transform 0.3s, box-shadow 0.3s;
    margin-bottom: 20px;
    overflow: hidden;
}

.event-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.event-card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border: none;
}

.event-detail-card {
    background: white;
    border-radius: 20px;
    padding: 40px;
    box-shadow: 0 8px 30px rgba(0,0,0,0.12);
    margin-bottom: 30px;
}

.purchase-form-card {
    background: #f8f9fa;
    border-radius: 15px;
    padding: 30px;
    border-left: 5px solid #667eea;
}

.badge-available {
    background: #28a745;
    color: white;
    padding: 8px 15px;
    border-radius: 20px;
    font-size: 0.9rem;
}

.badge-limited {
    background: #ffc107;
    color: #000;
    padding: 8px 15px;
    border-radius: 20px;
    font-size: 0.9rem;
}

.badge-soldout {
    background: #dc3545;
    color: white;
    padding: 8px 15px;
    border-radius: 20px;
    font-size: 0.9rem;
}

.quantity-selector {
    display: flex;
    align-items: center;
    gap: 15px;
}

.quantity-btn {
    width: 40px;
    height: 40px;
    border: 2px solid #667eea;
    background: white;
    color: #667eea;
    border-radius: 50%;
    font-size: 1.2rem;
    cursor: pointer;
    transition: all 0.3s;
}

.quantity-btn:hover {
    background: #667eea;
    color: white;
}

.quantity-display {
    font-size: 1.5rem;
    font-weight: bold;
    min-width: 50px;
    text-align: center;
}
</style>

<div class="container my-4">
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

<?php if (!$selected_event): ?>
    <!-- Hero Section -->
    <div class="hero-section">
        <i class="bi bi-ticket-perforated" style="font-size: 4rem; margin-bottom: 20px;"></i>
        <h1 class="display-4 mb-3">Upcoming Events</h1>
        <p class="lead">Explore art, culture, and creativity at Homies Fine Arts Museum</p>
    </div>

    <!-- Events Grid -->
    <div class="row">
        <?php if (empty($upcoming_events)): ?>
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> No upcoming events at this time. Check back soon!
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($upcoming_events as $event): 
                $percent_sold = $event['capacity'] > 0 ? ($event['tickets_sold'] / $event['capacity']) * 100 : 0;
                $is_soldout = $event['tickets_available'] <= 0;
                $is_limited = $event['tickets_available'] > 0 && $event['tickets_available'] <= 10;
            ?>
                <div class="col-md-6 col-lg-4">
                    <div class="event-card">
                        <div class="event-card-header">
                            <h5 class="mb-0"><?= htmlspecialchars($event['name']) ?></h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <i class="bi bi-calendar-event text-primary"></i>
                                <strong><?= date('l, F j, Y', strtotime($event['event_date'])) ?></strong>
                            </div>
                            <div class="mb-3">
                                <i class="bi bi-geo-alt text-primary"></i>
                                <?= htmlspecialchars($event['location_name'] ?? 'TBD') ?>
                            </div>
                            <?php if ($event['description']): ?>
                                <p class="text-muted small mb-3">
                                    <?= htmlspecialchars(substr($event['description'], 0, 100)) ?>
                                    <?= strlen($event['description']) > 100 ? '...' : '' ?>
                                </p>
                            <?php endif; ?>
                            
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <?php if ($is_soldout): ?>
                                    <span class="badge-soldout">SOLD OUT</span>
                                <?php elseif ($is_limited): ?>
                                    <span class="badge-limited"><?= $event['tickets_available'] ?> LEFT</span>
                                <?php else: ?>
                                    <span class="badge-available"><?= $event['tickets_available'] ?> Available</span>
                                <?php endif; ?>
                            </div>
                            
                            <a href="?event_id=<?= $event['event_id'] ?>" 
                               class="btn btn-primary w-100 <?= $is_soldout ? 'disabled' : '' ?>">
                                <?= $is_soldout ? 'Sold Out' : 'Buy Tickets' ?>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

<?php else: ?>
    <!-- Back Button -->
    <a href="/events.php" class="btn btn-outline-secondary mb-4">
        <i class="bi bi-arrow-left"></i> Back to All Events
    </a>

    <!-- Event Details -->
    <div class="event-detail-card">
        <h2 class="mb-4"><?= htmlspecialchars($selected_event['name']) ?></h2>
        
        <div class="row mb-4">
            <div class="col-md-3">
                <h6 class="text-muted"><i class="bi bi-calendar-event"></i> Date</h6>
                <p><?= date('l, F j, Y', strtotime($selected_event['event_date'])) ?></p>
            </div>
            <div class="col-md-3">
                <h6 class="text-muted"><i class="bi bi-geo-alt"></i> Location</h6>
                <p><?= htmlspecialchars($selected_event['location_name'] ?? 'TBD') ?></p>
            </div>
            <div class="col-md-3">
                <h6 class="text-muted"><i class="bi bi-people"></i> Capacity</h6>
                <p><?= $selected_event['capacity'] ?> attendees</p>
            </div>
            <div class="col-md-3">
                <h6 class="text-muted"><i class="bi bi-ticket-perforated"></i> Available</h6>
                <p class="text-<?= $selected_event['tickets_available'] <= 10 ? 'danger' : 'success' ?>">
                    <strong><?= $selected_event['tickets_available'] ?> tickets</strong>
                </p>
            </div>
        </div>
        
        <?php if ($selected_event['description']): ?>
            <hr>
            <h5>About This Event</h5>
            <p class="text-muted"><?= nl2br(htmlspecialchars($selected_event['description'])) ?></p>
        <?php endif; ?>
    </div>

    <!-- Purchase Form -->
    <?php if ($selected_event['tickets_available'] > 0): ?>
        <div class="purchase-form-card">
            <h4 class="mb-4"><i class="bi bi-cart"></i> Purchase Tickets</h4>
            
            <?php if ($is_member): ?>
                <div class="alert alert-info mb-4">
                    <i class="bi bi-star-fill"></i> You're logged in as a member! Purchasing as: 
                    <strong><?= htmlspecialchars($_SESSION['username']) ?></strong>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="purchaseForm">
                <input type="hidden" name="action" value="purchase">
                <input type="hidden" name="event_id" value="<?= $selected_event['event_id'] ?>">
                
                <!-- Quantity Selection -->
                <div class="mb-4">
                    <label class="form-label"><strong>Number of Tickets</strong></label>
                    <div class="quantity-selector">
                        <button type="button" class="quantity-btn" onclick="changeQuantity(-1)">−</button>
                        <div class="quantity-display" id="quantityDisplay">1</div>
                        <button type="button" class="quantity-btn" onclick="changeQuantity(1)">+</button>
                        <input type="hidden" name="quantity" id="quantityInput" value="1" required>
                    </div>
                    <small class="text-muted">Maximum: <?= min(10, $selected_event['tickets_available']) ?> tickets per order</small>
                </div>
                
                <?php if (!$is_member): ?>
                    <!-- Guest Information -->
                    <hr class="my-4">
                    <h5 class="mb-3">Your Information</h5>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">First Name *</label>
                            <input type="text" class="form-control" name="first_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Last Name *</label>
                            <input type="text" class="form-control" name="last_name" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email Address *</label>
                            <input type="email" class="form-control" name="email" required>
                            <small class="text-muted">You'll receive confirmation at this email</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" name="phone">
                        </div>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_student" id="isStudent">
                        <label class="form-check-label" for="isStudent">
                            I am a student
                        </label>
                    </div>
                    
                    <div class="alert alert-light border">
                        <i class="bi bi-info-circle"></i> 
                        <strong>Not a member yet?</strong> 
                        <a href="/register.php">Join our membership program</a> for exclusive benefits!
                    </div>
                <?php endif; ?>
                
                <hr class="my-4">
                
                <!-- Submit Button -->
                <button type="submit" class="btn btn-primary btn-lg w-100">
                    <i class="bi bi-cart-check"></i> Complete Purchase
                </button>
            </form>
        </div>
    <?php else: ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i> 
            <strong>Sorry, this event is sold out.</strong> Check back for future events!
        </div>
    <?php endif; ?>
<?php endif; ?>

</div>
<!-- End Container -->

<script>
const maxQuantity = <?= $selected_event ? min(10, $selected_event['tickets_available']) : 10 ?>;

function changeQuantity(delta) {
    const display = document.getElementById('quantityDisplay');
    const input = document.getElementById('quantityInput');
    let current = parseInt(input.value);
    let newValue = current + delta;
    
    // Constrain between 1 and max
    newValue = Math.max(1, Math.min(maxQuantity, newValue));
    
    display.textContent = newValue;
    input.value = newValue;
}
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>