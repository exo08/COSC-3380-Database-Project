<?php
session_start();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/permissions.php';

requirePermission('view_events');

$page_title = 'Manage Events';
$db = db();

// Handle event creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    requirePermission('add_event');
    
    $event_name = trim($_POST['event_name']);
    $description = trim($_POST['description']);
    $event_date = $_POST['event_date'];
    $location_id = intval($_POST['location_id']);
    $exhibition_id = !empty($_POST['exhibition_id']) ? intval($_POST['exhibition_id']) : null;
    $capacity = intval($_POST['capacity']);
    
    $stmt = $db->prepare("CALL CreateEvent(?, ?, ?, ?, ?, ?, @new_event_id)");
    $stmt->bind_param('sssiii', $event_name, $description, $event_date, $location_id, $exhibition_id, $capacity);
    
    if ($stmt->execute()) {
        $success = "Event created successfully!";
        logActivity('create', 'EVENT', null, "Created event: $event_name");
    } else {
        $error = "Failed to create event: " . $db->error;
    }
    $stmt->close();
    $db->next_result();
}

// Handle event updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    requirePermission('edit_event');
    
    $event_id = intval($_POST['event_id']);
    $event_name = trim($_POST['event_name']);
    $description = trim($_POST['description']);
    $event_date = $_POST['event_date'];
    $location_id = intval($_POST['location_id']);
    $exhibition_id = !empty($_POST['exhibition_id']) ? intval($_POST['exhibition_id']) : null;
    $capacity = intval($_POST['capacity']);
    
    $stmt = $db->prepare("
        UPDATE EVENT 
        SET name = ?, description = ?, event_date = ?, location_id = ?, exhibition_id = ?, capacity = ?
        WHERE event_id = ?
    ");
    $stmt->bind_param('sssiiii', $event_name, $description, $event_date, $location_id, $exhibition_id, $capacity, $event_id);
    
    if ($stmt->execute()) {
        $success = "Event updated successfully!";
        logActivity('update', 'EVENT', $event_id, "Updated event: $event_name");
    } else {
        $error = "Failed to update event: " . $db->error;
    }
    $stmt->close();
}

// Get all events with location and exhibition info
$events_query = "
    SELECT 
        e.*,
        l.name as location_name,
        ex.title as exhibition_title,
        (SELECT COUNT(*) FROM TICKET WHERE event_id = e.event_id) as tickets_sold,
        (SELECT COUNT(*) FROM TICKET WHERE event_id = e.event_id AND checked_in = 1) as checked_in_count
    FROM EVENT e
    LEFT JOIN LOCATION l ON e.location_id = l.location_id
    LEFT JOIN EXHIBITION ex ON e.exhibition_id = ex.exhibition_id
    ORDER BY e.event_date DESC
";
$events = $db->query($events_query)->fetch_all(MYSQLI_ASSOC);

// Get locations for dropdown
$locations = $db->query("SELECT location_id, name FROM LOCATION ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Get exhibitions for dropdown
$exhibitions = $db->query("SELECT exhibition_id, title FROM EXHIBITION WHERE end_date >= CURDATE() ORDER BY title")->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../templates/layout_header.php';
?>

<?php if (isset($success)): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Add Event Button -->
<?php if (hasPermission('add_event')): ?>
<div class="mb-3">
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEventModal">
        <i class="bi bi-plus-circle"></i> Add New Event
    </button>
</div>
<?php endif; ?>

<!-- Events Table -->
<div class="card">
    <div class="card-body">
        <h5 class="card-title mb-4"><i class="bi bi-calendar-event"></i> All Events</h5>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Event Name</th>
                        <th>Date</th>
                        <th>Location</th>
                        <th>Exhibition</th>
                        <th class="text-center">Capacity</th>
                        <th class="text-center">Sold</th>
                        <th class="text-center">Checked In</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($events)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                <i class="bi bi-calendar-x fs-1"></i>
                                <p>No events found</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($events as $event): 
                            $is_past = strtotime($event['event_date']) < time();
                            $percent_sold = $event['capacity'] > 0 ? round(($event['tickets_sold'] / $event['capacity']) * 100) : 0;
                        ?>
                            <tr class="<?= $is_past ? 'table-secondary' : '' ?>">
                                <td>
                                    <strong><?= htmlspecialchars($event['name']) ?></strong>
                                    <?php if ($is_past): ?>
                                        <span class="badge bg-secondary ms-2">Past</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('M j, Y', strtotime($event['event_date'])) ?></td>
                                <td><?= htmlspecialchars($event['location_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($event['exhibition_title'] ?? 'General') ?></td>
                                <td class="text-center"><?= $event['capacity'] ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?= $percent_sold >= 90 ? 'danger' : ($percent_sold >= 70 ? 'warning' : 'primary') ?>">
                                        <?= $event['tickets_sold'] ?> (<?= $percent_sold ?>%)
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?= $event['checked_in_count'] ?>
                                </td>
                                <td class="text-end">
                                    <?php if (hasPermission('edit_event')): ?>
                                        <button class="btn btn-sm btn-outline-primary" 
                                                onclick="editEvent(<?= htmlspecialchars(json_encode($event)) ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Event Modal -->
<div class="modal fade" id="addEventModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Add New Event</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Event Name *</label>
                            <input type="text" class="form-control" name="event_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Event Date *</label>
                            <input type="date" class="form-control" name="event_date" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Location *</label>
                            <select class="form-select" name="location_id" required>
                                <option value="">Select Location</option>
                                <?php foreach ($locations as $loc): ?>
                                    <option value="<?= $loc['location_id'] ?>">
                                        <?= htmlspecialchars($loc['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Related Exhibition (Optional)</label>
                            <select class="form-select" name="exhibition_id">
                                <option value="">No Exhibition</option>
                                <?php foreach ($exhibitions as $ex): ?>
                                    <option value="<?= $ex['exhibition_id'] ?>">
                                        <?= htmlspecialchars($ex['title']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Capacity *</label>
                        <input type="number" class="form-control" name="capacity" min="1" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Event</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Event Modal -->
<div class="modal fade" id="editEventModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="editEventForm">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="event_id" id="edit_event_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit Event</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Event Name *</label>
                            <input type="text" class="form-control" name="event_name" id="edit_event_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Event Date *</label>
                            <input type="date" class="form-control" name="event_date" id="edit_event_date" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="edit_description" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Location *</label>
                            <select class="form-select" name="location_id" id="edit_location_id" required>
                                <option value="">Select Location</option>
                                <?php foreach ($locations as $loc): ?>
                                    <option value="<?= $loc['location_id'] ?>">
                                        <?= htmlspecialchars($loc['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Related Exhibition (Optional)</label>
                            <select class="form-select" name="exhibition_id" id="edit_exhibition_id">
                                <option value="">No Exhibition</option>
                                <?php foreach ($exhibitions as $ex): ?>
                                    <option value="<?= $ex['exhibition_id'] ?>">
                                        <?= htmlspecialchars($ex['title']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Capacity *</label>
                        <input type="number" class="form-control" name="capacity" id="edit_capacity" min="1" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Event</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editEvent(event) {
    document.getElementById('edit_event_id').value = event.event_id;
    document.getElementById('edit_event_name').value = event.name;
    document.getElementById('edit_description').value = event.description || '';
    document.getElementById('edit_event_date').value = event.event_date;
    document.getElementById('edit_location_id').value = event.location_id;
    document.getElementById('edit_exhibition_id').value = event.exhibition_id || '';
    document.getElementById('edit_capacity').value = event.capacity;
    
    new bootstrap.Modal(document.getElementById('editEventModal')).show();
}
</script>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>