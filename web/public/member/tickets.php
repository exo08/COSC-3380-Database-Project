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

$page_title = 'My Tickets';
$db = db();
$success = '';
$error = '';

// Get member ID from session
$user_id = $_SESSION['user_id'];
$linked_id = $_SESSION['linked_id'];

// Get filter from URL
$filter = $_GET['filter'] ?? 'upcoming';

// Get all tickets for this member
$tickets_query = "
    SELECT t.*, 
           e.name as event_name,
           e.description as event_description,
           e.event_date,
           e.capacity,
           l.name as location_name,
           ex.title as exhibition_title,
           CASE 
               WHEN e.event_date < CURDATE() THEN 'Past'
               WHEN e.event_date = CURDATE() THEN 'Today'
               WHEN e.event_date > CURDATE() THEN 'Upcoming'
           END as event_status,
           DATEDIFF(e.event_date, CURDATE()) as days_until_event
    FROM TICKET t
    JOIN EVENT e ON t.event_id = e.event_id
    LEFT JOIN LOCATION l ON e.location_id = l.location_id
    LEFT JOIN EXHIBITION ex ON e.exhibition_id = ex.exhibition_id
    WHERE t.member_id = $linked_id
    ORDER BY e.event_date ASC
";

$tickets_result = $db->query($tickets_query);
$all_tickets = $tickets_result ? $tickets_result->fetch_all(MYSQLI_ASSOC) : [];

// Filter tickets
$filtered_tickets = array_filter($all_tickets, function($ticket) use ($filter) {
    switch($filter) {
        case 'upcoming':
            return $ticket['event_status'] === 'Upcoming' || $ticket['event_status'] === 'Today';
        case 'past':
            return $ticket['event_status'] === 'Past';
        case 'checked_in':
            return $ticket['checked_in'] == 1;
        case 'not_checked_in':
            return $ticket['checked_in'] == 0 && ($ticket['event_status'] === 'Upcoming' || $ticket['event_status'] === 'Today');
        default:
            return true;
    }
});

// Get statistics
$stats = [];
$stats['total_tickets'] = count($all_tickets);
$stats['upcoming'] = count(array_filter($all_tickets, fn($t) => $t['event_status'] === 'Upcoming' || $t['event_status'] === 'Today'));
$stats['past'] = count(array_filter($all_tickets, fn($t) => $t['event_status'] === 'Past'));
$stats['checked_in'] = count(array_filter($all_tickets, fn($t) => $t['checked_in'] == 1));
$stats['total_quantity'] = array_sum(array_column($all_tickets, 'quantity'));

// Get member details for ticket display
$member = $db->query("SELECT * FROM MEMBER WHERE member_id = $linked_id")->fetch_assoc();

include __DIR__ . '/../templates/layout_header.php';
?>

<style>
.ticket-card {
    border-left: 4px solid #667eea;
    transition: all 0.3s;
    background: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.ticket-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
}

.ticket-card.today {
    border-left-color: #ffc107;
    background: linear-gradient(to right, rgba(255, 193, 7, 0.05), white);
}

.ticket-card.past {
    border-left-color: #6c757d;
    opacity: 0.85;
}

.ticket-card.checked-in {
    border-left-color: #28a745;
}

.ticket-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 1.5rem;
}

.ticket-qr {
    background: white;
    padding: 1rem;
    border-radius: 10px;
    display: inline-block;
}

.ticket-qr-code {
    width: 150px;
    height: 150px;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px dashed #ccc;
    font-size: 0.75rem;
    color: #999;
    text-align: center;
}

.ticket-details {
    padding: 1.5rem;
}

.ticket-info-item {
    display: flex;
    padding: 0.75rem 0;
    border-bottom: 1px solid #f0f0f0;
}

.ticket-info-item:last-child {
    border-bottom: none;
}

.ticket-info-item i {
    width: 30px;
    color: #667eea;
}

.stat-card {
    border-radius: 10px;
    transition: transform 0.2s;
    border: none;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.stat-card:hover {
    transform: translateY(-3px);
}

.filter-tabs .nav-link {
    border-radius: 25px;
    margin-right: 0.5rem;
    padding: 0.5rem 1.5rem;
}

.filter-tabs .nav-link.active {
    background: #667eea;
    color: white;
}

.countdown-badge {
    display: inline-block;
    padding: 0.5rem 1rem;
    border-radius: 25px;
    font-weight: 600;
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(10px);
}

.digital-ticket-modal .modal-content {
    border-radius: 15px;
    overflow: hidden;
}

.digital-ticket {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem;
    border-radius: 15px;
}

.digital-ticket-body {
    background: white;
    color: #333;
    padding: 2rem;
    border-radius: 15px;
    margin-top: 1rem;
}

.barcode {
    background: white;
    padding: 1rem;
    border-radius: 10px;
    text-align: center;
}

.barcode-lines {
    display: flex;
    justify-content: center;
    gap: 2px;
    margin-bottom: 0.5rem;
}

.barcode-lines .line {
    width: 3px;
    height: 60px;
    background: #000;
}

.barcode-lines .line:nth-child(even) {
    opacity: 0.7;
}

.barcode-lines .line:nth-child(3n) {
    width: 5px;
}

@media print {
    .no-print { display: none !important; }
    .ticket-card { page-break-inside: avoid; }
}
</style>

<!-- Success/Error Messages -->
<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show no-print">
        <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show no-print">
        <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4 no-print">
    <div>
        <h2><i class="bi bi-ticket-perforated"></i> My Event Tickets</h2>
        <p class="text-muted">View and manage your event registrations</p>
    </div>
    <a href="/events.php" class="btn btn-primary">
        <i class="bi bi-calendar-plus"></i> Browse Events
    </a>
</div>

<!-- Statistics Cards -->
<div class="row g-4 mb-4 no-print">
    <div class="col-md-3">
        <div class="card stat-card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-1 opacity-75">Total Tickets</h6>
                        <h2 class="mb-0"><?= $stats['total_tickets'] ?></h2>
                        <small class="opacity-75"><?= $stats['total_quantity'] ?> total seats</small>
                    </div>
                    <i class="bi bi-ticket-perforated fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card stat-card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-1 opacity-75">Upcoming</h6>
                        <h2 class="mb-0"><?= $stats['upcoming'] ?></h2>
                    </div>
                    <i class="bi bi-calendar-event fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card stat-card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-1 opacity-75">Attended</h6>
                        <h2 class="mb-0"><?= $stats['checked_in'] ?></h2>
                    </div>
                    <i class="bi bi-check-circle fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card stat-card bg-secondary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-1 opacity-75">Past Events</h6>
                        <h2 class="mb-0"><?= $stats['past'] ?></h2>
                    </div>
                    <i class="bi bi-clock-history fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Tabs -->
<ul class="nav filter-tabs mb-4 no-print">
    <li class="nav-item">
        <a class="nav-link <?= $filter === 'upcoming' ? 'active' : '' ?>" href="?filter=upcoming">
            <i class="bi bi-calendar-event"></i> Upcoming (<?= $stats['upcoming'] ?>)
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $filter === 'past' ? 'active' : '' ?>" href="?filter=past">
            <i class="bi bi-clock-history"></i> Past (<?= $stats['past'] ?>)
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $filter === 'checked_in' ? 'active' : '' ?>" href="?filter=checked_in">
            <i class="bi bi-check-circle"></i> Checked In (<?= $stats['checked_in'] ?>)
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $filter === 'all' ? 'active' : '' ?>" href="?filter=all">
            <i class="bi bi-ticket"></i> All Tickets (<?= $stats['total_tickets'] ?>)
        </a>
    </li>
</ul>

<!-- Tickets List -->
<?php if (empty($filtered_tickets)): ?>
    <div class="text-center py-5">
        <i class="bi bi-ticket-perforated" style="font-size: 5rem; color: #ccc;"></i>
        <h3 class="mt-3 text-muted">No Tickets Found</h3>
        <p class="text-muted">
            <?php if ($filter === 'upcoming'): ?>
                You don't have any upcoming event tickets. Browse our events to register!
            <?php elseif ($filter === 'past'): ?>
                You haven't attended any events yet.
            <?php elseif ($filter === 'checked_in'): ?>
                You haven't checked in to any events yet.
            <?php else: ?>
                You don't have any tickets. Check out our exciting events!
            <?php endif; ?>
        </p>
        <a href="/events.php" class="btn btn-primary">
            <i class="bi bi-calendar-plus"></i> Browse Events
        </a>
    </div>
<?php else: ?>
    <div class="row g-4">
        <?php foreach ($filtered_tickets as $ticket): 
            $status_class = strtolower($ticket['event_status']);
            if ($ticket['checked_in']) {
                $status_class .= ' checked-in';
            }
        ?>
            <div class="col-md-6">
                <div class="ticket-card <?= $status_class ?>">
                    <div class="ticket-header">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h4 class="mb-1"><?= htmlspecialchars($ticket['event_name']) ?></h4>
                                <p class="mb-0 opacity-75">
                                    <i class="bi bi-calendar3"></i> 
                                    <?= date('l, F j, Y', strtotime($ticket['event_date'])) ?>
                                </p>
                            </div>
                            <div class="text-end">
                                <?php if ($ticket['event_status'] === 'Today'): ?>
                                    <span class="badge bg-warning text-dark">
                                        <i class="bi bi-star-fill"></i> TODAY
                                    </span>
                                <?php elseif ($ticket['event_status'] === 'Upcoming'): ?>
                                    <div class="countdown-badge">
                                        <?php if ($ticket['days_until_event'] == 1): ?>
                                            Tomorrow
                                        <?php else: ?>
                                            In <?= $ticket['days_until_event'] ?> days
                                        <?php endif; ?>
                                    </div>
                                <?php elseif ($ticket['checked_in']): ?>
                                    <span class="badge bg-success">
                                        <i class="bi bi-check-circle"></i> Attended
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">
                                        <i class="bi bi-clock-history"></i> Past Event
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="ticket-details">
                        <?php if ($ticket['event_description']): ?>
                            <p class="text-muted small mb-3">
                                <?= htmlspecialchars(substr($ticket['event_description'], 0, 120)) ?><?= strlen($ticket['event_description']) > 120 ? '...' : '' ?>
                            </p>
                        <?php endif; ?>
                        
                        <div class="ticket-info-item">
                            <i class="bi bi-geo-alt-fill"></i>
                            <div>
                                <strong>Location</strong><br>
                                <span class="text-muted small"><?= htmlspecialchars($ticket['location_name']) ?></span>
                            </div>
                        </div>
                        
                        <?php if ($ticket['exhibition_title']): ?>
                            <div class="ticket-info-item">
                                <i class="bi bi-palette-fill"></i>
                                <div>
                                    <strong>Related Exhibition</strong><br>
                                    <span class="text-muted small"><?= htmlspecialchars($ticket['exhibition_title']) ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="ticket-info-item">
                            <i class="bi bi-people-fill"></i>
                            <div>
                                <strong>Tickets</strong><br>
                                <span class="text-muted small">
                                    <?= $ticket['quantity'] ?> <?= $ticket['quantity'] == 1 ? 'ticket' : 'tickets' ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="ticket-info-item">
                            <i class="bi bi-ticket-detailed-fill"></i>
                            <div>
                                <strong>Ticket ID</strong><br>
                                <span class="text-muted small">#<?= str_pad($ticket['ticket_id'], 8, '0', STR_PAD_LEFT) ?></span>
                            </div>
                        </div>
                        
                        <div class="ticket-info-item">
                            <i class="bi bi-calendar-check-fill"></i>
                            <div>
                                <strong>Purchased</strong><br>
                                <span class="text-muted small"><?= date('F j, Y', strtotime($ticket['purchase_date'])) ?></span>
                            </div>
                        </div>
                        
                        <div class="d-flex gap-2 mt-3">
                            <?php if ($ticket['event_status'] !== 'Past' || $ticket['checked_in']): ?>
                                <button class="btn btn-primary flex-grow-1" 
                                        onclick="showDigitalTicket(<?= htmlspecialchars(json_encode($ticket)) ?>, <?= htmlspecialchars(json_encode($member)) ?>)">
                                    <i class="bi bi-phone"></i> Digital Ticket
                                </button>
                            <?php endif; ?>
                            
                            <a href="/events.php" class="btn btn-outline-secondary">
                                <i class="bi bi-info-circle"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Digital Ticket Modal -->
<div class="modal fade digital-ticket-modal" id="digitalTicketModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body p-0">
                <div class="digital-ticket" id="digitalTicketContent">
                    <!-- Content will be populated by JavaScript -->
                </div>
            </div>
            <div class="modal-footer no-print">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="window.print()">
                    <i class="bi bi-printer"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function showDigitalTicket(ticket, member) {
    const eventDate = new Date(ticket.event_date);
    const ticketNumber = String(ticket.ticket_id).padStart(8, '0');
    
    const content = `
        <div class="text-center mb-3">
            <h3 class="mb-1">Museum of Fine Arts</h3>
            <p class="mb-0 opacity-75">Event Ticket</p>
        </div>
        
        <div class="digital-ticket-body">
            <h4 class="mb-3 text-center">${ticket.event_name}</h4>
            
            <div class="text-center mb-4">
                <div class="barcode">
                    <div class="barcode-lines">
                        <div class="line"></div>
                        <div class="line"></div>
                        <div class="line"></div>
                        <div class="line"></div>
                        <div class="line"></div>
                        <div class="line"></div>
                        <div class="line"></div>
                        <div class="line"></div>
                        <div class="line"></div>
                        <div class="line"></div>
                        <div class="line"></div>
                        <div class="line"></div>
                    </div>
                    <strong>${ticketNumber}</strong>
                </div>
            </div>
            
            <div class="row mb-2">
                <div class="col-6">
                    <small class="text-muted d-block">Event Date</small>
                    <strong>${eventDate.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</strong>
                </div>
                <div class="col-6">
                    <small class="text-muted d-block">Location</small>
                    <strong>${ticket.location_name}</strong>
                </div>
            </div>
            
            <div class="row mb-2">
                <div class="col-6">
                    <small class="text-muted d-block">Guest Name</small>
                    <strong>${member.first_name} ${member.last_name}</strong>
                </div>
                <div class="col-6">
                    <small class="text-muted d-block">Tickets</small>
                    <strong>${ticket.quantity} ${ticket.quantity == 1 ? 'ticket' : 'tickets'}</strong>
                </div>
            </div>
            
            ${ticket.exhibition_title ? `
                <div class="row mb-3">
                    <div class="col-12">
                        <small class="text-muted d-block">Related Exhibition</small>
                        <strong>${ticket.exhibition_title}</strong>
                    </div>
                </div>
            ` : ''}
            
            <div class="alert alert-info mb-0">
                <small>
                    <i class="bi bi-info-circle"></i> 
                    Please present this ticket at the entrance. Check-in opens 30 minutes before the event.
                </small>
            </div>
        </div>
    `;
    
    document.getElementById('digitalTicketContent').innerHTML = content;
    new bootstrap.Modal(document.getElementById('digitalTicketModal')).show();
}
</script>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>