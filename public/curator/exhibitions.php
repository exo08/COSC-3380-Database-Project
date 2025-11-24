<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/permissions.php';

// check permission
requirePermission('view_exhibitions');

$page_title = "Manage Exhibitions";
$db = db();

$success = '';
$error = '';

// handle soft delete
if(isset($_POST['delete_exhibition'])){
    requirePermission('delete_exhibition');
    $exhibition_id = intval($_POST['exhibition_id']);

    if($db->query("UPDATE EXHIBITION SET is_deleted = TRUE WHERE exhibition_id = $exhibition_id")){
        $success = 'Exhibition marked as deleted.';
        logActivity('exhibition_deleted', 'EXHIBITION', $exhibition_id, "Soft deleted exhibition ID: $exhibition_id");
    }else{
        $error = 'Error deleting exhibition: ' . $db->error;
    }
}

// handle add/edit
if(isset($_POST['save_exhibition'])){
    $exhibition_id = !empty($_POST['exhibition_id']) ? intval($_POST['exhibition_id']) : null;
    $title = $_POST['title'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $curator_id = !empty($_POST['curator_id']) ? intval($_POST['curator_id']) : null;
    $description = $_POST['description'] ?? '';
    $theme_sponsor = $_POST['theme_sponsor'] ?? '';

    try {
        if($exhibition_id){ // update existing
                requirePermission('edit_exhibition');
                $stmt = $db->prepare("UPDATE EXHIBITION SET title = ?, start_date = ?, end_date = ?, curator_id = ?, description = ?, theme_sponsor = ? WHERE exhibition_id = ?");
                $stmt->bind_param('sssissi', $title, $start_date, $end_date, $curator_id, $description, $theme_sponsor, $exhibition_id);
                if($stmt->execute()){
                    $success = 'Exhibition updated successfully.';
                    logActivity('exhibition_updated', 'EXHIBITION', $exhibition_id, "Updated exhibition: $title");
                }else{
                    $error = 'Error updating exhibition: ' . $db->error;
                }
            }else{ // insert new using stored procedure CreateExhibition
                requirePermission('add_exhibition');
                $stmt = $db->prepare("CALL CreateExhibition(?,?,?,?,?,?, @new_exhibition_id)");
                $stmt->bind_param('sssiss', $title, $start_date, $end_date, $curator_id, $description, $theme_sponsor);

                if($stmt->execute()){
                    $result = $db->query("SELECT @new_exhibition_id AS exhibition_id");
                    $new_exhibition = $result->fetch_assoc();
                    $new_exhibition_id = $new_exhibition['exhibition_id'];
                    $success = "Exhibition added successfully! (ID: $new_exhibition_id)";
                    logActivity('exhibition_created', 'EXHIBITION', $new_exhibition_id, "Created exhibition: $title");
                }else{
                    $error = 'Error adding exhibition: ' . $db->error;
                }
                if(isset($stmt)) $stmt->close();
            }
    } catch (Exception $e) {
        // check if it's the date validation error from the trigger
        if (strpos($e->getMessage(), 'End date cannot be earlier than start date') !== false) {
            $error = 'End date cannot be earlier than start date.';
        } else {
            $error = 'Error saving exhibition: ' . $e->getMessage();
        }
    }
}

// Get all exhibitions with artwork count and curator name
$exhibitions_query = "
    SELECT e.*, 
           CONCAT(s.name) as curator_name,
           COUNT(DISTINCT ea.artwork_id) AS artwork_count,
           CASE 
               WHEN e.end_date >= CURDATE() AND e.start_date <= CURDATE() THEN 'Active'
               WHEN e.start_date > CURDATE() THEN 'Upcoming'
               ELSE 'Past'
           END as status
    FROM EXHIBITION e
    LEFT JOIN STAFF s ON e.curator_id = s.staff_id
    LEFT JOIN EXHIBITION_ARTWORK ea ON e.exhibition_id = ea.exhibition_id
    WHERE e.is_deleted = FALSE OR e.is_deleted IS NULL
    GROUP BY e.exhibition_id
    ORDER BY e.start_date DESC
";

$exhibitions_result = $db->query($exhibitions_query);

if($exhibitions_result){
    $exhibitions = $exhibitions_result->fetch_all(MYSQLI_ASSOC);
}else{
    $exhibitions = [];
    $error = 'Error loading exhibitions: ' . $db->error;
}

// Get all staff for curator dropdown (only curators or admins)
$staff_result = $db->query("
    SELECT s.staff_id, s.name, s.title 
    FROM STAFF s
    JOIN USER_ACCOUNT ua ON s.staff_id = ua.linked_id
    WHERE ua.user_type = 'curator'
    ORDER BY s.name
");
$staff_list = $staff_result ? $staff_result->fetch_all(MYSQLI_ASSOC) : [];

include __DIR__ . '/../templates/layout_header.php';
?>

<?php if($success) : ?>
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

<!-- add new button -->
<?php if (hasPermission('add_exhibition')): ?>
<div class="mb-4">
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#exhibitionModal" onclick="clearForm()">
        <i class="bi bi-plus-circle"></i> Add New Exhibition
    </button>
</div>
<?php endif; ?>

<!-- exhibitions table -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-building"></i> All Exhibitions (<?= count($exhibitions) ?>)</h5>
    </div>
    <div class="card-body">
        <?php if (empty($exhibitions)): ?>
            <p class="text-muted">No exhibitions found. Add your first exhibition using the button above!</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Dates</th>
                        <th>Curator</th>
                        <th>Artworks</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($exhibitions as $exhibition): ?>
                    <tr>
                        <td><?= htmlspecialchars($exhibition['exhibition_id']) ?></td>
                        <td>
                            <strong><?= htmlspecialchars($exhibition['title']) ?></strong>
                            <?php if (!empty($exhibition['theme_sponsor'])): ?>
                                <br><small class="text-muted">Sponsor: <?= htmlspecialchars($exhibition['theme_sponsor']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <small>
                                <?= date('M d, Y', strtotime($exhibition['start_date'])) ?><br>
                                to<br>
                                <?= date('M d, Y', strtotime($exhibition['end_date'])) ?>
                            </small>
                        </td>
                        <td><?= htmlspecialchars($exhibition['curator_name'] ?? 'Not assigned') ?></td>
                        <td>
                            <span class="badge bg-info"><?= $exhibition['artwork_count'] ?> artworks</span>
                        </td>
                        <td>
                            <?php
                            $status = $exhibition['status'];
                            $badge_class = 'secondary';
                            if ($status === 'Active') $badge_class = 'success';
                            elseif ($status === 'Upcoming') $badge_class = 'primary';
                            elseif ($status === 'Past') $badge_class = 'secondary';
                            ?>
                            <span class="badge bg-<?= $badge_class ?>"><?= $status ?></span>
                        </td>
                        <td>
                            <?php if (hasPermission('edit_exhibition')): ?>
                            <button class="btn btn-sm btn-outline-primary" onclick='editExhibition(<?= json_encode($exhibition, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                <i class="bi bi-pencil"></i>
                            </button>
                            <?php endif; ?>
                            
                            <?php if (hasPermission('delete_exhibition')): ?>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteExhibition(<?= $exhibition['exhibition_id'] ?>, '<?= htmlspecialchars($exhibition['title'], ENT_QUOTES) ?>')">
                                <i class="bi bi-trash"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- add/edit modal -->
<div class="modal fade" id="exhibitionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add New Exhibition</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="exhibition_id" id="exhibition_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Title *</label>
                        <input type="text" class="form-control" name="title" id="title" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Date *</label>
                            <input type="date" class="form-control" name="start_date" id="start_date" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Date *</label>
                            <input type="date" class="form-control" name="end_date" id="end_date" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Curator</label>
                            <select class="form-select" name="curator_id" id="curator_id">
                                <option value="">Not assigned</option>
                                <?php foreach ($staff_list as $staff): ?>
                                    <option value="<?= $staff['staff_id'] ?>">
                                        <?= htmlspecialchars($staff['name']) ?> 
                                        <?= !empty($staff['title']) ? '(' . htmlspecialchars($staff['title']) . ')' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Theme/Sponsor</label>
                            <input type="text" class="form-control" name="theme_sponsor" id="theme_sponsor" placeholder="e.g., Renaissance Art">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="description" rows="4" placeholder="Enter exhibition description..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="save_exhibition" class="btn btn-primary">
                        <i class="bi bi-save"></i> Save Exhibition
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- delete modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this exhibition?</p>
                    <p class="fw-bold" id="deleteExhibitionTitle"></p>
                    <p class="text-muted small">Note: This will mark the exhibition as deleted but will not remove associated artworks.</p>
                    <input type="hidden" name="exhibition_id" id="delete_exhibition_id">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_exhibition" class="btn btn-danger">
                        <i class="bi bi-trash"></i> Delete
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function clearForm() {
    document.getElementById('modalTitle').textContent = 'Add New Exhibition';
    document.getElementById('exhibition_id').value = '';
    document.getElementById('title').value = '';
    document.getElementById('start_date').value = '';
    document.getElementById('end_date').value = '';
    document.getElementById('curator_id').value = '';
    document.getElementById('theme_sponsor').value = '';
    document.getElementById('description').value = '';
}

function editExhibition(exhibition) {
    document.getElementById('modalTitle').textContent = 'Edit Exhibition';
    document.getElementById('exhibition_id').value = exhibition.exhibition_id;
    document.getElementById('title').value = exhibition.title || '';
    document.getElementById('start_date').value = exhibition.start_date || '';
    document.getElementById('end_date').value = exhibition.end_date || '';
    document.getElementById('curator_id').value = exhibition.curator_id || '';
    document.getElementById('theme_sponsor').value = exhibition.theme_sponsor || '';
    document.getElementById('description').value = exhibition.description || '';
    
    new bootstrap.Modal(document.getElementById('exhibitionModal')).show();
}

function deleteExhibition(id, title) {
    document.getElementById('delete_exhibition_id').value = id;
    document.getElementById('deleteExhibitionTitle').textContent = title;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>