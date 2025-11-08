<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/permissions.php';

// Require admin permission
if ($_SESSION['user_type'] !== 'admin') {
    header('Location: /dashboard.php?error=access_denied');
    exit;
}

$page_title = 'Events Management';
$db = db();
$success = '';
$error = '';

// Handle event creation/editing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
        $event_id = $_POST['event_id'] ?? null;
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $event_date = $_POST['event_date'];
        $location_id = $_POST['location_id'];
        $exhibition_id = !empty($_POST['exhibition_id']) ? $_POST['exhibition_id'] : null;
        $capacity = (int)$_POST['capacity'];

        if ($_POST['action'] === 'add') {
            $stmt = $db->prepare("
                INSERT INTO EVENT (name, description, event_date, location_id, exhibition_id, capacity)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("sssiii", $name, $description, $event_date, $location_id, $exhibition_id, $capacity);
            
            if ($stmt->execute()) {
                $success = "Event created successfully!";
                logActivity('event_created', 'EVENT', $db->insert_id, "Created event: $name");
            } else {
                $error = "Error creating event: " . $db->error;
            }
            $stmt->close();
        } else {
            $stmt = $db->prepare("
                UPDATE EVENT 
                SET name = ?, description = ?, event_date = ?, location_id = ?, exhibition_id = ?, capacity = ?
                WHERE event_id = ?
            ");
            $stmt->bind_param("sssiiii", $name, $description, $event_date, $location_id, $exhibition_id, $capacity, $event_id);
            
            if ($stmt->execute()) {
                $success = "Event updated successfully!";
                logActivity('event_updated', 'EVENT', $event_id, "Updated event: $name");
            } else {
                $error = "Error updating event: " . $db->error;
            }
            $stmt->close();
        }
    } elseif ($_POST['action'] === 'delete') {
        $event_id = $_POST['event_id'];
        $stmt = $db->prepare("DELETE FROM EVENT WHERE event_id = ?");
        $stmt->bind_param("i", $event_id);
        
        if ($stmt->execute()) {
            $success = "Event deleted successfully!";
            logActivity('event_deleted', 'EVENT', $event_id, "Deleted event");
        } else {
            $error = "Error deleting event: " . $db->error;
        }
        $stmt->close();
    }
}

// Get all events with stats
$events_query = "
    SELECT e.*, 
           l.name as location_name,
           ex.title as exhibition_title,
           COUNT(DISTINCT t.ticket_id) as tickets_sold,
           SUM(t.quantity) as total_tickets,
           COUNT(DISTINCT CASE WHEN t.checked_in = 1 THEN t.ticket_id END) as checked_in_count,
           CASE 
               WHEN e.event_date < CURDATE() THEN 'Past'
               WHEN e.event_date = CURDATE() THEN 'Today'
               WHEN e.event_date > CURDATE() THEN 'Upcoming'
           END as status
    FROM EVENT e
    LEFT JOIN LOCATION l ON e.location_id = l.location_id
    LEFT JOIN EXHIBITION ex ON e.exhibition_id = ex.exhibition_id
    LEFT JOIN TICKET t ON e.event_id = t.event_id
    GROUP BY e.event_id
    ORDER BY e.event_date DESC
";

$events_result = $db->query($events_query);
$events = $events_result ? $events_result->fetch_all(MYSQLI_ASSOC) : [];

// Get locations for dropdown
$locations_result = $db->query("SELECT location_id, name FROM LOCATION WHERE location_type IN (1, 2) ORDER BY name");
$locations = $locations_result ? $locations_result->fetch_all(MYSQLI_ASSOC) : [];

// Get active exhibitions for dropdown
$exhibitions_result = $db->query("
    SELECT exhibition_id, title 
    FROM EXHIBITION 
    WHERE end_date >= CURDATE() AND (is_deleted = FALSE OR is_deleted IS NULL)
    ORDER BY start_date
");
$exhibitions = $exhibitions_result ? $exhibitions_result->fetch_all(MYSQLI_ASSOC) : [];

// Get event stats
$stats = [];
$stats['total_events'] = count($events);
$stats['upcoming_events'] = count(array_filter($events, fn($e) => $e['status'] === 'Upcoming' || $e['status'] === 'Today'));
$stats['total_tickets_sold'] = array_sum(array_column($events, 'total_tickets'));
$stats['total_checked_in'] = array_sum(array_column($events, 'checked_in_count'));

include __DIR__ . '/../templates/layout_header.php';
?>

<style>
.event-card {
    border-left: 4px solid #0d6efd;
    transition: transform 0.2s, box-shadow 0.2s;
}
.event-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.event-card.past {
    border-left-color: #6c757d;
    opacity: 0.8;
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
</style>

<!-- Success/Error Messages -->
<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2><i class="bi bi-calendar-event"></i> Events Management</h2>
        <p class="text-muted">Manage museum events, programs, and ticket sales</p>
    </div>
    <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#eventModal" onclick="resetEventForm()">
        <i class="bi bi-plus-circle"></i> Create New Event
    </button>
</div>

<!-- Stats Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card stat-card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-1">Total Events</h6>
                        <h2 class="mb-0"><?= $stats['total_events'] ?></h2>
                    </div>
                    <i class="bi bi-calendar-event fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card stat-card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-1">Upcoming Events</h6>
                        <h2 class="mb-0"><?= $stats['upcoming_events'] ?></h2>
                    </div>
                    <i class="bi bi-clock fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card stat-card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-1">Tickets Sold</h6>
                        <h2 class="mb-0"><?= number_format($stats['total_tickets_sold']) ?></h2>
                    </div>
                    <i class="bi bi-ticket fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card stat-card bg-warning text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-1">Checked In</h6>
                        <h2 class="mb-0"><?= number_format($stats['total_checked_in']) ?></h2>
                    </div>
                    <i class="bi bi-check-circle fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Tabs -->
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link active" data-filter="all">All Events</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-filter="upcoming">Upcoming</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-filter="today">Today</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-filter="past">Past</a>
    </li>
</ul>

<!-- Events List -->
<div class="row g-4" id="eventsContainer">
    <?php foreach ($events as $event): 
        $sold = $event['total_tickets'] ?? 0;
        $capacity = $event['capacity'];
        $percent_sold = $capacity > 0 ? ($sold / $capacity) * 100 : 0;
        $status_class = strtolower($event['status']);
    ?>
        <div class="col-md-6 event-item" data-status="<?= $status_class ?>">
            <div class="card event-card <?= $status_class ?>">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h5 class="card-title mb-1"><?= htmlspecialchars($event['name']) ?></h5>
                            <p class="text-muted small mb-0">
                                <i class="bi bi-calendar"></i> <?= date('M j, Y', strtotime($event['event_date'])) ?>
                                <span class="ms-2"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($event['location_name']) ?></span>
                            </p>
                        </div>
                        <span class="badge bg-<?= $status_class === 'upcoming' ? 'success' : ($status_class === 'today' ? 'warning' : 'secondary') ?>">
                            <?= htmlspecialchars($event['status']) ?>
                        </span>
                    </div>
                    
                    <?php if ($event['description']): ?>
                        <p class="card-text text-muted small"><?= htmlspecialchars(substr($event['description'], 0, 100)) ?><?= strlen($event['description']) > 100 ? '...' : '' ?></p>
                    <?php endif; ?>
                    
                    <?php if ($event['exhibition_title']): ?>
                        <p class="small mb-2">
                            <i class="bi bi-building"></i> <strong>Exhibition:</strong> <?= htmlspecialchars($event['exhibition_title']) ?>
                        </p>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="small"><i class="bi bi-ticket"></i> Tickets: <?= $sold ?> / <?= $capacity ?></span>
                            <span class="small"><?= number_format($percent_sold, 1) ?>%</span>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar <?= $percent_sold >= 90 ? 'bg-danger' : ($percent_sold >= 70 ? 'bg-warning' : 'bg-success') ?>" 
                                 style="width: <?= min($percent_sold, 100) ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-primary" onclick="editEvent(<?= htmlspecialchars(json_encode($event)) ?>)">
                            <i class="bi bi-pencil"></i> Edit
                        </button>
                        
                        <a href="/events/sell-ticket.php?event_id=<?= $event['event_id'] ?>" class="btn btn-sm btn-outline-success">
                            <i class="bi bi-cart-plus"></i> Sell
                        </a>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this event? This cannot be undone.')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="event_id" value="<?= $event['event_id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Event Modal -->
<div class="modal fade" id="eventModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="eventModalTitle">Create New Event</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" id="eventAction" value="add">
                    <input type="hidden" name="event_id" id="eventId">
                    
                    <div class="mb-3">
                        <label class="form-label">Event Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" id="eventName" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="eventDescription" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Event Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="event_date" id="eventDate" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Capacity <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="capacity" id="eventCapacity" min="1" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Location <span class="text-danger">*</span></label>
                            <select class="form-select" name="location_id" id="eventLocation" required>
                                <option value="">Select location...</option>
                                <?php foreach ($locations as $loc): ?>
                                    <option value="<?= $loc['location_id'] ?>"><?= htmlspecialchars($loc['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Related Exhibition (Optional)</label>
                            <select class="form-select" name="exhibition_id" id="eventExhibition">
                                <option value="">None</option>
                                <?php foreach ($exhibitions as $ex): ?>
                                    <option value="<?= $ex['exhibition_id'] ?>"><?= htmlspecialchars($ex['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Save Event
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>

// Filter events
document.querySelectorAll('.nav-link[data-filter]').forEach(link => {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
        this.classList.add('active');
        
        const filter = this.dataset.filter;
        document.querySelectorAll('.event-item').forEach(item => {
            if (filter === 'all' || item.dataset.status === filter) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    });
});

function resetEventForm() {
    document.getElementById('eventModalTitle').textContent = 'Create New Event';
    document.getElementById('eventAction').value = 'add';
    document.getElementById('eventId').value = '';
    document.getElementById('eventName').value = '';
    document.getElementById('eventDescription').value = '';
    document.getElementById('eventDate').value = '';
    document.getElementById('eventCapacity').value = '';
    document.getElementById('eventLocation').value = '';
    document.getElementById('eventExhibition').value = '';
}

function editEvent(event) {
    document.getElementById('eventModalTitle').textContent = 'Edit Event';
    document.getElementById('eventAction').value = 'edit';
    document.getElementById('eventId').value = event.event_id;
    document.getElementById('eventName').value = event.name;
    document.getElementById('eventDescription').value = event.description || '';
    document.getElementById('eventDate').value = event.event_date;
    document.getElementById('eventCapacity').value = event.capacity;
    document.getElementById('eventLocation').value = event.location_id;
    document.getElementById('eventExhibition').value = event.exhibition_id || '';
    
    new bootstrap.Modal(document.getElementById('eventModal')).show();
}
</script>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>