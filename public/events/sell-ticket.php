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

// Membership type mapping
function getMembershipTypeName($type) {
    $types = [
        1 => 'Student',
        2 => 'Individual',
        3 => 'Family',
        4 => 'Patron',
        5 => 'Benefactor'
    ];
    return $types[$type] ?? 'Member';
}

// ajax member search
if (isset($_GET['search_members'])) {
    header('Content-Type: application/json');
    $search = trim($_GET['q'] ?? '');
    
    if (strlen($search) < 2) {
        echo json_encode(['members' => []]);
        exit;
    }
    
    $search_param = '%' . $search . '%';
    $stmt = $db->prepare("
        SELECT member_id, first_name, last_name, email, membership_type
        FROM MEMBER 
        WHERE expiration_date >= CURDATE()
        AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)
        ORDER BY last_name, first_name
        LIMIT 10
    ");
    $stmt->bind_param('sss', $search_param, $search_param, $search_param);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $members = [];
    while ($row = $result->fetch_assoc()) {
        // display membership type name
        $row['membership_type_name'] = getMembershipTypeName($row['membership_type']);
        $members[] = $row;
    }
    
    echo json_encode(['members' => $members]);
    exit;
}

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
                // Create new visitor
                $first_name = trim($_POST['first_name']);
                $last_name = trim($_POST['last_name']);
                $email = trim($_POST['email']);
                $phone = trim($_POST['phone']);
                $is_student = isset($_POST['is_student']) ? 1 : 0;
                
                $stmt = $db->prepare("CALL CreateVisitor(?, ?, ?, ?, ?, @new_visitor_id)");
                $stmt->bind_param('ssssi', $first_name, $last_name, $email, $phone, $is_student);
                
                if ($stmt->execute()) {
                    $result = $db->query("SELECT @new_visitor_id as visitor_id");
                    $new_visitor = $result->fetch_assoc();
                    $visitor_id = $new_visitor['visitor_id'];
                    $stmt->close();
                } else {
                    throw new Exception($db->error);
                }
            }
            
            // Create ticket using stored procedure CreateTicket(8 param)
            // p_event_id, p_visitor_id, p_member_id, p_purchase_date, p_quantity, p_checked_in, p_check_in_time, p_ticket_id
            $checked_in = 0;  // not checked in yet
            $check_in_time = null;  // no checkin time yet
            
            $stmt = $db->prepare("CALL CreateTicket(?, ?, ?, ?, ?, ?, ?, @new_ticket_id)");
            $stmt->bind_param('iiiisii', $event_id, $visitor_id, $member_id, $purchase_date, $quantity, $checked_in, $check_in_time);
            
            if ($stmt->execute()) {
                $result = $db->query("SELECT @new_ticket_id as ticket_id");
                $new_ticket = $result->fetch_assoc();
                $success = "Ticket sale successful! Ticket ID: " . $new_ticket['ticket_id'];
                $stmt->close();
                
                logActivity('ticket_sold', 'TICKET', $new_ticket['ticket_id'], "Sold $quantity ticket(s) for event $event_id");
                
                // Refresh event details
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

include __DIR__ . '/../templates/layout_header.php';
?>

<style>
.customer-type-btn {
    border: 2px solid #dee2e6;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    transition: all 0.3s;
    cursor: pointer;
}

.customer-type-btn:hover {
    border-color: #0d6efd;
    background-color: #f8f9fa;
}

.customer-type-btn.active {
    border-color: #0d6efd;
    background-color: #e7f1ff;
}

.member-search-results {
    position: absolute;
    z-index: 1000;
    width: 100%;
    max-height: 300px;
    overflow-y: auto;
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    display: none;
}

.member-search-result {
    padding: 12px;
    border-bottom: 1px solid #f0f0f0;
    cursor: pointer;
    transition: background-color 0.2s;
}

.member-search-result:hover {
    background-color: #f8f9fa;
}

.member-search-result:last-child {
    border-bottom: none;
}

.member-search-result .member-name {
    font-weight: 600;
    color: #212529;
}

.member-search-result .member-email {
    font-size: 0.875rem;
    color: #6c757d;
}

.member-search-result .member-type {
    font-size: 0.75rem;
    color: #0d6efd;
}

.selected-member-display {
    display: none;
    padding: 12px;
    background-color: #e7f1ff;
    border: 1px solid #0d6efd;
    border-radius: 4px;
    margin-bottom: 1rem;
}

.selected-member-display.show {
    display: block;
}
</style>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-ticket-perforated"></i> Sell Event Tickets</h2>
        <a href="manage.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back to Events
        </a>
    </div>

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

    <!-- Event Selection -->
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title mb-3">Select Event</h5>
            <div class="row">
                <?php foreach ($upcoming_events as $event): ?>
                    <?php
                    $available = $event['capacity'] - $event['tickets_sold'];
                    $is_selected = $selected_event && $selected_event['event_id'] == $event['event_id'];
                    ?>
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="card h-100 <?= $is_selected ? 'border-primary' : '' ?>">
                            <div class="card-body">
                                <h6 class="card-title"><?= htmlspecialchars($event['name']) ?></h6>
                                <p class="text-muted mb-2">
                                    <i class="bi bi-calendar"></i> <?= date('M d, Y', strtotime($event['event_date'])) ?><br>
                                    <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($event['location_name']) ?>
                                </p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="badge bg-<?= $available > 0 ? 'success' : 'danger' ?>">
                                        <?= $available ?> available
                                    </span>
                                    <?php if ($available > 0): ?>
                                        <a href="?event_id=<?= $event['event_id'] ?>" class="btn btn-sm btn-primary">
                                            Select
                                        </a>
                                    <?php else: ?>
                                        <span class="text-danger">Sold Out</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($selected_event): ?>
    <!-- Selected Event Details -->
    <div class="card mb-4 border-primary">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="bi bi-calendar-event"></i> <?= htmlspecialchars($selected_event['name']) ?></h5>
        </div>
        <div class="row g-0 p-3">
            <div class="col-md-3">
                <h6 class="text-muted">Date & Time</h6>
                <p><?= date('l, M d, Y', strtotime($selected_event['event_date'])) ?><br></p>
            </div>
            <div class="col-md-3">
                <h6 class="text-muted">Location</h6>
                <p><?= htmlspecialchars($selected_event['location_name']) ?></p>
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
            <p class="text-muted mb-0 px-3 pb-3"><?= htmlspecialchars($selected_event['description']) ?></p>
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
                    <input type="hidden" name="member_id" id="selected_member_id" value="">
                    
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
                                        <p class="text-muted mb-0 small">Search by name or email</p>
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
                    
                    <!-- Member Search Section -->
                    <div id="memberSection" style="display: none;">
                        <div class="mb-3 position-relative">
                            <label class="form-label">Search for Member *</label>
                            <input type="text" 
                                   class="form-control form-control-lg" 
                                   id="memberSearch" 
                                   placeholder="Type member's first name, last name, or email..."
                                   autocomplete="off">
                            <div id="memberSearchResults" class="member-search-results"></div>
                        </div>
                        
                        <!-- Selected Member Display -->
                        <div id="selectedMemberDisplay" class="selected-member-display">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>Selected Member:</strong>
                                    <div id="selectedMemberInfo" class="mt-1"></div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearMemberSelection()">
                                    <i class="bi bi-x"></i> Clear
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Visitor Information -->
                    <div id="visitorSection" style="display: none;">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">First Name *</label>
                                <input type="text" class="form-control" name="first_name" id="visitor_first_name">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Name *</label>
                                <input type="text" class="form-control" name="last_name" id="visitor_last_name">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email" id="visitor_email">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone</label>
                                <input type="tel" class="form-control" name="phone" id="visitor_phone">
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
let searchTimeout;
let selectedMember = null;

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
        document.getElementById('visitor_first_name').required = false;
        document.getElementById('visitor_last_name').required = false;
        document.getElementById('visitor_email').required = false;
    } else {
        document.getElementById('visitor_first_name').required = true;
        document.getElementById('visitor_last_name').required = true;
        document.getElementById('visitor_email').required = true;
        clearMemberSelection();
    }
}

// Member search functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('memberSearch');
    const resultsDiv = document.getElementById('memberSearchResults');
    
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            if (query.length < 2) {
                resultsDiv.style.display = 'none';
                resultsDiv.innerHTML = '';
                return;
            }
            
            searchTimeout = setTimeout(() => {
                searchMembers(query);
            }, 300);
        });
        
        // Close results when clicking outside
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !resultsDiv.contains(e.target)) {
                resultsDiv.style.display = 'none';
            }
        });
        
        // Reopen results when clicking on input
        searchInput.addEventListener('focus', function() {
            if (resultsDiv.innerHTML && this.value.length >= 2) {
                resultsDiv.style.display = 'block';
            }
        });
    }
});

function searchMembers(query) {
    const resultsDiv = document.getElementById('memberSearchResults');
    resultsDiv.innerHTML = '<div class="p-3 text-center"><div class="spinner-border spinner-border-sm"></div> Searching...</div>';
    resultsDiv.style.display = 'block';
    
    fetch(`?search_members=1&q=${encodeURIComponent(query)}`)
        .then(response => response.json())
        .then(data => {
            if (data.members.length === 0) {
                resultsDiv.innerHTML = '<div class="p-3 text-muted text-center">No members found</div>';
            } else {
                resultsDiv.innerHTML = data.members.map(member => `
                    <div class="member-search-result" onclick='selectMember(${JSON.stringify(member)})'>
                        <div class="member-name">${escapeHtml(member.first_name)} ${escapeHtml(member.last_name)}</div>
                        <div class="member-email">${escapeHtml(member.email)}</div>
                        <div class="member-type">${escapeHtml(member.membership_type_name)}</div>
                    </div>
                `).join('');
            }
        })
        .catch(error => {
            console.error('Search error:', error);
            resultsDiv.innerHTML = '<div class="p-3 text-danger">Error searching members</div>';
        });
}

function selectMember(member) {
    selectedMember = member;
    
    // Set hidden field
    document.getElementById('selected_member_id').value = member.member_id;
    
    // Update display
    document.getElementById('selectedMemberInfo').innerHTML = `
        <strong>${escapeHtml(member.first_name)} ${escapeHtml(member.last_name)}</strong><br>
        <small class="text-muted">${escapeHtml(member.email)}</small>
    `;
    
    // Show selected member display
    document.getElementById('selectedMemberDisplay').classList.add('show');
    
    // Clear and hide search
    document.getElementById('memberSearch').value = '';
    document.getElementById('memberSearchResults').style.display = 'none';
}

function clearMemberSelection() {
    selectedMember = null;
    document.getElementById('selected_member_id').value = '';
    document.getElementById('selectedMemberDisplay').classList.remove('show');
    document.getElementById('memberSearch').value = '';
    document.getElementById('memberSearchResults').innerHTML = '';
    document.getElementById('memberSearchResults').style.display = 'none';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Form validation
document.getElementById('sellTicketForm')?.addEventListener('submit', function(e) {
    const customerType = document.querySelector('input[name="customer_type"]:checked');
    
    if (!customerType) {
        e.preventDefault();
        alert('Please select a customer type');
        return false;
    }
    
    if (customerType.value === 'member') {
        if (!document.getElementById('selected_member_id').value) {
            e.preventDefault();
            alert('Please search for and select a member');
            return false;
        }
    }
});
</script>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>