<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/permissions.php';

// Check permission
requirePermission('view_artworks');

$page_title = 'Manage Artworks';
$db = db();

$success = '';
$error = '';

// Handle Deleteadmin
if (isset($_POST['delete_artwork'])) {
    requirePermission('delete_artwork');
    $artwork_id = intval($_POST['artwork_id']);
    
    try {
        if ($db->query("DELETE FROM ARTWORK WHERE artwork_id = $artwork_id")) {
            $success = 'Artwork deleted successfully!';
            logActivity('artwork_deleted', 'ARTWORK', $artwork_id, "Deleted artwork ID: $artwork_id");
        } else {
            $error = 'Error deleting artwork: ' . $db->error;
        }
    } catch (Exception $e) {
        $error = 'Error deleting artwork: ' . $e->getMessage();
    }
}

// Handle add/edit
if (isset($_POST['save_artwork'])) {
    $artwork_id = !empty($_POST['artwork_id']) ? intval($_POST['artwork_id']) : null;
    $title = $_POST['title'] ?? '';
    $creation_year = !empty($_POST['creation_year']) ? intval($_POST['creation_year']) : null;
    $medium = !empty($_POST['medium']) ? intval($_POST['medium']) : null;
    $height = !empty($_POST['height']) ? floatval($_POST['height']) : null;
    $width = !empty($_POST['width']) ? floatval($_POST['width']) : null;
    $depth = !empty($_POST['depth']) ? floatval($_POST['depth']) : null;
    $is_owned = isset($_POST['is_owned']) ? 1 : 0;
    $location_id = !empty($_POST['location_id']) ? intval($_POST['location_id']) : null;
    $description = $_POST['description'] ?? '';
    
    try {
        if ($artwork_id) {
            // Update existing
            requirePermission('edit_artwork');
            
            $stmt = $db->prepare("UPDATE ARTWORK SET 
                    title = ?,
                    creation_year = ?,
                    medium = ?,
                    height = ?,
                    width = ?,
                    depth = ?,
                    is_owned = ?,
                    location_id = ?,
                    description = ?
                    WHERE artwork_id = ?");
            
            $stmt->bind_param("siiiddiisi", $title, $creation_year, $medium, $height, $width, $depth, $is_owned, $location_id, $description, $artwork_id);
            
            if ($stmt->execute()) {
                $success = 'Artwork updated successfully!';
                logActivity('artwork_updated', 'ARTWORK', $artwork_id, "Updated artwork: $title");
            } else {
                $error = 'Error updating artwork: ' . $db->error;
            }
        } else {
            // Insert new
            requirePermission('add_artwork');
            
            $stmt = $db->prepare("CALL CreateArtwork(?, ?, ?, ?, ?, ?, ?, ?, ?, @new_artwork_id)");
            $stmt->bind_param("siidddiss", $title, $creation_year, $medium, $height, $width, $depth, $is_owned, $location_id, $description);
            
            if ($stmt->execute()) {
                $result = $db->query("SELECT @new_artwork_id as artwork_id");
                $new_artwork = $result->fetch_assoc();
                $new_artwork_id = $new_artwork['artwork_id'];
                
                $success = "Artwork added successfully! (ID: $new_artwork_id)";
                logActivity('artwork_created', 'ARTWORK', $new_artwork_id, "Created artwork: $title");
            } else {
                $error = 'Error adding artwork: ' . $db->error;
            }
            
            if (isset($stmt)) $stmt->close();
        }
    } catch (Exception $e) {
        $error = 'Error saving artwork: ' . $e->getMessage();
    }
}

// Get all artworks
try {
    $artworks_result = $db->query("
        SELECT a.*, l.name as location_name,
               GROUP_CONCAT(CONCAT(ar.first_name, ' ', ar.last_name) SEPARATOR ', ') as artist_names
        FROM ARTWORK a
        LEFT JOIN LOCATION l ON a.location_id = l.location_id
        LEFT JOIN ARTWORK_CREATOR ac ON a.artwork_id = ac.artwork_id
        LEFT JOIN ARTIST ar ON ac.artist_id = ar.artist_id
        GROUP BY a.artwork_id
        ORDER BY a.title
    ");
    
    if ($artworks_result) {
        $artworks = $artworks_result->fetch_all(MYSQLI_ASSOC);
    } else {
        $artworks = [];
        $error = 'Error loading artworks: ' . $db->error;
    }
} catch (Exception $e) {
    $artworks = [];
    $error = 'Error loading artworks: ' . $e->getMessage();
}

// Get locations for dropdown
try {
    $locations_result = $db->query("SELECT location_id, name FROM LOCATION ORDER BY name");
    $locations = $locations_result ? $locations_result->fetch_all(MYSQLI_ASSOC) : [];
} catch (Exception $e) {
    $locations = [];
}

include __DIR__ . '/../templates/layout_header.php';
?>

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

<!-- Add New Button -->
<?php if (hasPermission('add_artwork')): ?>
<div class="mb-4">
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#artworkModal" onclick="clearForm()">
        <i class="bi bi-plus-circle"></i> Add New Artwork
    </button>
</div>
<?php endif; ?>

<!-- artworks table -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-palette"></i> All Artworks (<?= count($artworks) ?>)</h5>
    </div>
    <div class="card-body">
        <?php if (empty($artworks)): ?>
            <p class="text-muted">No artworks found. Add your first artwork using the button above!</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Artist(s)</th>
                        <th>Year</th>
                        <th>Location</th>
                        <th>Owned</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($artworks as $artwork): ?>
                    <tr>
                        <td><?= htmlspecialchars($artwork['artwork_id']) ?></td>
                        <td><strong><?= htmlspecialchars($artwork['title']) ?></strong></td>
                        <td><?= htmlspecialchars($artwork['artist_names'] ?? 'Unknown') ?></td>
                        <td><?= htmlspecialchars($artwork['creation_year'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($artwork['location_name'] ?? 'Not set') ?></td>
                        <td>
                            <?php if ($artwork['is_owned']): ?>
                                <span class="badge bg-success">Owned</span>
                            <?php else: ?>
                                <span class="badge bg-warning">On Loan</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (hasPermission('edit_artwork')): ?>
                            <button class="btn btn-sm btn-outline-primary" onclick='editArtwork(<?= json_encode($artwork, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                <i class="bi bi-pencil"></i>
                            </button>
                            <?php endif; ?>
                            
                            <?php if (hasPermission('delete_artwork')): ?>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteArtwork(<?= $artwork['artwork_id'] ?>, '<?= htmlspecialchars($artwork['title'], ENT_QUOTES) ?>')">
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
<div class="modal fade" id="artworkModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add New Artwork</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="artwork_id" id="artwork_id">
                    
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Title *</label>
                            <input type="text" class="form-control" name="title" id="title" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Creation Year</label>
                            <input type="number" class="form-control" name="creation_year" id="creation_year" min="1" max="2099">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Height (cm)</label>
                            <input type="number" class="form-control" name="height" id="height" step="0.01">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Width (cm)</label>
                            <input type="number" class="form-control" name="width" id="width" step="0.01">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Depth (cm)</label>
                            <input type="number" class="form-control" name="depth" id="depth" step="0.01">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Medium</label>
                            <input type="number" class="form-control" name="medium" id="medium">
                            <small class="text-muted">Type code</small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Location</label>
                            <select class="form-select" name="location_id" id="location_id">
                                <option value="">Not set</option>
                                <?php foreach ($locations as $loc): ?>
                                    <option value="<?= $loc['location_id'] ?>"><?= htmlspecialchars($loc['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label d-block">Ownership</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="is_owned" id="is_owned" checked>
                                <label class="form-check-label" for="is_owned">Museum Owned</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="save_artwork" class="btn btn-primary">
                        <i class="bi bi-save"></i> Save Artwork
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete confirmation -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this artwork?</p>
                    <p class="fw-bold" id="deleteArtworkTitle"></p>
                    <input type="hidden" name="artwork_id" id="delete_artwork_id">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_artwork" class="btn btn-danger">
                        <i class="bi bi-trash"></i> Delete
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function clearForm() {
    document.getElementById('modalTitle').textContent = 'Add New Artwork';
    document.getElementById('artwork_id').value = '';
    document.getElementById('title').value = '';
    document.getElementById('creation_year').value = '';
    document.getElementById('height').value = '';
    document.getElementById('width').value = '';
    document.getElementById('depth').value = '';
    document.getElementById('medium').value = '';
    document.getElementById('location_id').value = '';
    document.getElementById('is_owned').checked = true;
    document.getElementById('description').value = '';
}

function editArtwork(artwork) {
    document.getElementById('modalTitle').textContent = 'Edit Artwork';
    document.getElementById('artwork_id').value = artwork.artwork_id;
    document.getElementById('title').value = artwork.title || '';
    document.getElementById('creation_year').value = artwork.creation_year || '';
    document.getElementById('height').value = artwork.height || '';
    document.getElementById('width').value = artwork.width || '';
    document.getElementById('depth').value = artwork.depth || '';
    document.getElementById('medium').value = artwork.medium || '';
    document.getElementById('location_id').value = artwork.location_id || '';
    document.getElementById('is_owned').checked = artwork.is_owned == 1;
    document.getElementById('description').value = artwork.description || '';
    
    new bootstrap.Modal(document.getElementById('artworkModal')).show();
}

function deleteArtwork(id, title) {
    document.getElementById('delete_artwork_id').value = id;
    document.getElementById('deleteArtworkTitle').textContent = title;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>