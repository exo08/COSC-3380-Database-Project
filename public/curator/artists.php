<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/permissions.php';

// check permission
requirePermission('view_artists');

$page_title = "Manage Artists";
$db = db();

$success = '';
$error = '';

// soft delete
if(isset($_POST['delete_artist'])){
    requirePermission('edit_artist');
    $artist_id = intval($_POST['artist_id']);

    if($db->query("UPDATE ARTIST SET is_deleted = TRUE WHERE artist_id = $artist_id")){
        $success = 'Artist marked as deleted.';
        logActivity('artist_deleted', 'ARTIST', $artist_id, "Soft deleted artist ID: $artist_id");
    }else{
        $error = 'Error deleting artist: ' . $db->error;
    }
}

// add/edit
if(isset($_POST['save_artist'])){
    $artist_id = !empty($_POST['artist_id']) ? intval($_POST['artist_id']) : null;
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $birth_year = !empty($_POST['birth_year']) ? intval($_POST['birth_year']) : null;
    $death_year = !empty($_POST['death_year']) ? intval($_POST['death_year']) : null;
    $nationality = $_POST['nationality'] ?? '';
    $bio = $_POST['bio'] ?? '';

    if($artist_id){ // update existing
        requirePermission('edit_artist');
        $stmt = $db->prepare("UPDATE ARTIST SET first_name = ?, last_name = ?, birth_year = ?, death_year = ?, nationality = ?, bio = ? WHERE artist_id = ?");
        $stmt->bind_param('ssiissi', $first_name, $last_name, $birth_year, $death_year, $nationality, $bio, $artist_id);
        if($stmt->execute()){
            $success = 'Artist updated successfully.';
            logActivity('artist_updated', 'ARTIST', $artist_id, "Updated artist: $first_name $last_name");
        }else{
            $error = 'Error updating artist: ' . $db->error;
        }
    }else{ // insert new using stored procedure CreateArtist
        requirePermission('add_artist');
        $stmt = $db->prepare("CALL CreateArtist(?,?,?,?,?,?, @new_artist_id)");
        $stmt->bind_param('ssiiss', $first_name, $last_name, $birth_year, $death_year, $nationality, $bio);

        if($stmt->execute()){
            $result = $db->query("SELECT @new_artist_id AS artist_id");
            $new_artist = $result->fetch_assoc();
            $new_artist_id = $new_artist['artist_id'];
            $success = "Artist added successfully! (ID: $new_artist_id)";
            logActivity('artist_created', 'ARTIST', $new_artist_id, "Created artist: $first_name $last_name");
        }else{
            $error = 'Error adding artist: ' . $db->error;
        }
        if(isset($stmt)) $stmt->close();
    }
}

// get all artists w/ artwork count
$artists_result = $db->query("
    SELECT a.*, COUNT(DISTINCT ac.artwork_id) AS artwork_count
    FROM ARTIST a
    LEFT JOIN ARTWORK_CREATOR ac ON a.artist_id = ac.artist_id
    WHERE a.is_deleted = FALSE OR a.is_deleted IS NULL
    GROUP BY a.artist_id
    ORDER BY a.last_name, a.first_name
");

if($artists_result){
    $artists = $artists_result->fetch_all(MYSQLI_ASSOC);
}else{
    $artists = [];
    $error = 'Error loading artists: ' . $db->error;
}

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
<?php if (hasPermission('add_artist')): ?>
<div class="mb-4">
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#artistModal" onclick="clearForm()">
        <i class="bi bi-plus-circle"></i> Add New Artist
    </button>
</div>
<?php endif; ?>

<!-- artists table -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-brush"></i> All Artists (<?= count($artists) ?>)</h5>
    </div>
    <div class="card-body">
        <?php if (empty($artists)): ?>
            <p class="text-muted">No artists found. Add your first artist using the button above!</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Birth Year</th>
                        <th>Death Year</th>
                        <th>Nationality</th>
                        <th>Artworks</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($artists as $artist): ?>
                    <tr>
                        <td><?= htmlspecialchars($artist['artist_id']) ?></td>
                        <td>
                            <strong><?= htmlspecialchars($artist['first_name'] . ' ' . $artist['last_name']) ?></strong>
                            <?php if (!empty($artist['bio'])): ?>
                                <br><small class="text-muted"><?= htmlspecialchars(substr($artist['bio'], 0, 60)) ?>...</small>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($artist['birth_year'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($artist['death_year'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($artist['nationality'] ?? '-') ?></td>
                        <td>
                            <span class="badge bg-info"><?= $artist['artwork_count'] ?> artworks</span>
                        </td>
                        <td>
                            <?php if (hasPermission('edit_artist')): ?>
                            <button class="btn btn-sm btn-outline-primary" onclick='editArtist(<?= json_encode($artist, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                <i class="bi bi-pencil"></i>
                            </button>
                            <?php endif; ?>
                            
                            <?php if (hasPermission('edit_artist')): ?>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteArtist(<?= $artist['artist_id'] ?>, '<?= htmlspecialchars($artist['first_name'] . ' ' . $artist['last_name'], ENT_QUOTES) ?>')">
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
<div class="modal fade" id="artistModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add New Artist</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="artist_id" id="artist_id">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">First Name *</label>
                            <input type="text" class="form-control" name="first_name" id="first_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Last Name *</label>
                            <input type="text" class="form-control" name="last_name" id="last_name" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Birth Year</label>
                            <input type="number" class="form-control" name="birth_year" id="birth_year" min="1" max="2099">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Death Year</label>
                            <input type="number" class="form-control" name="death_year" id="death_year" min="1" max="2099">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Nationality</label>
                            <input type="text" class="form-control" name="nationality" id="nationality" placeholder="e.g., American">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Biography</label>
                        <textarea class="form-control" name="bio" id="bio" rows="4" placeholder="Enter artist biography..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="save_artist" class="btn btn-primary">
                        <i class="bi bi-save"></i> Save Artist
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
                    <p>Are you sure you want to delete this artist?</p>
                    <p class="fw-bold" id="deleteArtistName"></p>
                    <p class="text-muted small">Note: This will not delete associated artworks, only mark the artist as deleted.</p>
                    <input type="hidden" name="artist_id" id="delete_artist_id">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_artist" class="btn btn-danger">
                        <i class="bi bi-trash"></i> Delete
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function clearForm() {
    document.getElementById('modalTitle').textContent = 'Add New Artist';
    document.getElementById('artist_id').value = '';
    document.getElementById('first_name').value = '';
    document.getElementById('last_name').value = '';
    document.getElementById('birth_year').value = '';
    document.getElementById('death_year').value = '';
    document.getElementById('nationality').value = '';
    document.getElementById('bio').value = '';
}

function editArtist(artist) {
    document.getElementById('modalTitle').textContent = 'Edit Artist';
    document.getElementById('artist_id').value = artist.artist_id;
    document.getElementById('first_name').value = artist.first_name || '';
    document.getElementById('last_name').value = artist.last_name || '';
    document.getElementById('birth_year').value = artist.birth_year || '';
    document.getElementById('death_year').value = artist.death_year || '';
    document.getElementById('nationality').value = artist.nationality || '';
    document.getElementById('bio').value = artist.bio || '';
    
    new bootstrap.Modal(document.getElementById('artistModal')).show();
}

function deleteArtist(id, name) {
    document.getElementById('delete_artist_id').value = id;
    document.getElementById('deleteArtistName').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>