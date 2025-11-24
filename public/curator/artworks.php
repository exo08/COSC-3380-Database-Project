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

// Handle Delete
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

// Handle create new artist inline
if (isset($_POST['create_inline_artist'])) {
    requirePermission('add_artist');
    $first_name = $_POST['new_artist_first_name'] ?? '';
    $last_name = $_POST['new_artist_last_name'] ?? '';
    $birth_year = !empty($_POST['new_artist_birth_year']) ? intval($_POST['new_artist_birth_year']) : null;
    $nationality = $_POST['new_artist_nationality'] ?? '';
    
    try {
        $stmt = $db->prepare("CALL CreateArtist(?, ?, ?, NULL, ?, '', @new_artist_id)");
        $stmt->bind_param('ssis', $first_name, $last_name, $birth_year, $nationality);
        
        if ($stmt->execute()) {
            $result = $db->query("SELECT @new_artist_id as artist_id");
            $new_artist = $result->fetch_assoc();
            $new_artist_id = $new_artist['artist_id'];
            $success = "Artist created successfully! (ID: $new_artist_id). Now you can add your artwork.";
            logActivity('artist_created', 'ARTIST', $new_artist_id, "Quick-created artist: $first_name $last_name");
        } else {
            $error = 'Error creating artist: ' . $db->error;
        }
        if (isset($stmt)) $stmt->close();
    } catch (Exception $e) {
        $error = 'Error creating artist: ' . $e->getMessage();
    }
}

// Handle assign to exhibition
if (isset($_POST['assign_to_exhibition'])) {
    requirePermission('edit_artwork');
    $artwork_id = intval($_POST['artwork_id']);
    $exhibition_id = intval($_POST['exhibition_id']);
    $location_id = intval($_POST['exhibition_location_id']);
    $start_view_date = $_POST['start_view_date'] ?? '';
    $end_view_date = $_POST['end_view_date'] ?? '';
    
    try {
        // Start transaction
        $db->begin_transaction();
        
        // Create the exhibition artwork record
        $stmt = $db->prepare("CALL CreateExhibitionArtwork(?, ?, ?, ?, ?, @new_exhibition_art_id)");
        $stmt->bind_param('iiiss', $artwork_id, $location_id, $exhibition_id, $start_view_date, $end_view_date);
        
        if ($stmt->execute()) {
            $result = $db->query("SELECT @new_exhibition_art_id as exhibition_art_id");
            $new_record = $result->fetch_assoc();
            $stmt->close();
            
            // Update the artwork's location to reflect the exhibition location
            $update_stmt = $db->prepare("UPDATE ARTWORK SET location_id = ? WHERE artwork_id = ?");
            $update_stmt->bind_param('ii', $location_id, $artwork_id);
            
            if ($update_stmt->execute()) {
                $db->commit();
                $success = "Artwork successfully assigned to exhibition and location updated!";
                logActivity('artwork_exhibited', 'EXHIBITION_ARTWORK', $new_record['exhibition_art_id'], "Assigned artwork $artwork_id to exhibition $exhibition_id at location $location_id");
            } else {
                throw new Exception('Error updating artwork location: ' . $db->error);
            }
            
            $update_stmt->close();
        } else {
            throw new Exception('Error creating exhibition artwork: ' . $db->error);
        }
    } catch (Exception $e) {
        $db->rollback();
        $error = 'Error assigning artwork: ' . $e->getMessage();
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
    $artist_ids = $_POST['artist_ids'] ?? [];
    $artist_roles = $_POST['artist_roles'] ?? []; // get the roles for each artist to display when editing 
    
    try {
        // Start transaction
        $db->begin_transaction();
        
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
                $stmt->close();
                
                // Remove existing artist links and re-add
                $db->query("DELETE FROM ARTWORK_CREATOR WHERE artwork_id = $artwork_id");
                
                // Add artist links with individual roles
                if (!empty($artist_ids)) {
                    foreach ($artist_ids as $index => $artist_id) {
                        if (!empty($artist_id)) {
                            // Get the corresponding role for this artist
                            $role = isset($artist_roles[$index]) && !empty($artist_roles[$index]) ? $artist_roles[$index] : 'Creator';
                            
                            $artist_stmt = $db->prepare("CALL MatchArtToArtist(?, ?, ?)");
                            $artist_stmt->bind_param("iis", $artwork_id, $artist_id, $role);
                            $artist_stmt->execute();
                            $artist_stmt->close();
                        }
                    }
                }
                
                $db->commit();
                $success = 'Artwork updated successfully!';
                logActivity('artwork_updated', 'ARTWORK', $artwork_id, "Updated artwork: $title");
            } else {
                throw new Exception('Error updating artwork: ' . $db->error);
            }
        } else {
            // Insert new using stored procedure
            requirePermission('add_artwork');
            
            $stmt = $db->prepare("CALL CreateArtwork(?, ?, ?, ?, ?, ?, ?, ?, ?, @new_artwork_id)");
            $stmt->bind_param("siiiddiis", $title, $creation_year, $medium, $height, $width, $depth, $is_owned, $location_id, $description);
            
            if ($stmt->execute()) {
                $result = $db->query("SELECT @new_artwork_id as artwork_id");
                $new_artwork = $result->fetch_assoc();
                $new_artwork_id = $new_artwork['artwork_id'];
                $stmt->close();
                
                // Add artist links with individual roles
                if (!empty($artist_ids)) {
                    foreach ($artist_ids as $index => $artist_id) {
                        if (!empty($artist_id)) {
                            // Get the corresponding role for this artist
                            $role = isset($artist_roles[$index]) && !empty($artist_roles[$index]) ? $artist_roles[$index] : 'Creator';
                            
                            $artist_stmt = $db->prepare("CALL MatchArtToArtist(?, ?, ?)");
                            $artist_stmt->bind_param("iis", $new_artwork_id, $artist_id, $role);
                            $artist_stmt->execute();
                            $artist_stmt->close();
                        }
                    }
                }
                
                $db->commit();
                $success = "Artwork added successfully! (ID: $new_artwork_id)";
                logActivity('artwork_created', 'ARTWORK', $new_artwork_id, "Created artwork: $title");
            } else {
                throw new Exception('Error adding artwork: ' . $db->error);
            }
        }
    } catch (Exception $e) {
        $db->rollback();
        $error = 'Error saving artwork: ' . $e->getMessage();
    }
}

// Get all artists for dropdowns
$artists = [];
$artists_result = $db->query("SELECT artist_id, first_name, last_name, nationality FROM ARTIST WHERE is_deleted = FALSE ORDER BY last_name, first_name");
if ($artists_result) {
    while ($artist = $artists_result->fetch_assoc()) {
        $artists[] = $artist;
    }
}

// Get all locations for dropdown
$locations = [];
$locations_result = $db->query("SELECT location_id, name FROM LOCATION ORDER BY name");
if ($locations_result) {
    while ($location = $locations_result->fetch_assoc()) {
        $locations[] = $location;
    }
}

// Get active exhibitions for assignment
$exhibitions = [];
$exhibitions_result = $db->query("
    SELECT exhibition_id, title, start_date, end_date 
    FROM EXHIBITION 
    WHERE is_deleted = FALSE 
    AND end_date >= CURDATE() 
    ORDER BY start_date DESC
");
if ($exhibitions_result) {
    while ($exhibition = $exhibitions_result->fetch_assoc()) {
        $exhibitions[] = $exhibition;
    }
}

// Get all artworks with their artists
$artworks = [];
$artworks_result = $db->query("
    SELECT 
        a.artwork_id,
        a.title,
        a.creation_year,
        a.medium,
        a.height,
        a.width,
        a.depth,
        a.is_owned,
        a.location_id,
        a.description,
        l.name as location_name,
        GROUP_CONCAT(DISTINCT CONCAT(ar.first_name, ' ', ar.last_name) ORDER BY ar.last_name SEPARATOR ', ') as artist_names,
        GROUP_CONCAT(DISTINCT ar.artist_id ORDER BY ar.last_name) as artist_ids_list,
        GROUP_CONCAT(DISTINCT COALESCE(ac.role, 'Creator') ORDER BY ar.last_name SEPARATOR '||') as artist_roles_list
    FROM ARTWORK a
    LEFT JOIN LOCATION l ON a.location_id = l.location_id
    LEFT JOIN ARTWORK_CREATOR ac ON a.artwork_id = ac.artwork_id
    LEFT JOIN ARTIST ar ON ac.artist_id = ar.artist_id
    WHERE a.is_deleted = FALSE
    GROUP BY a.artwork_id
    ORDER BY a.artwork_id DESC
");

if ($artworks_result) {
    while ($artwork = $artworks_result->fetch_assoc()) {
        $artworks[] = $artwork;
    }
}

include __DIR__ . '/../templates/layout_header.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-palette"></i> Manage Artworks</h2>
        <?php if (hasPermission('add_artwork')): ?>
        <button class="btn btn-primary" onclick="clearForm(); new bootstrap.Modal(document.getElementById('artworkModal')).show();">
            <i class="bi bi-plus-circle"></i> Add New Artwork
        </button>
        <?php endif; ?>
    </div>

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

    <div class="card">
        <div class="card-body">
        <?php if (empty($artworks)): ?>
            <p class="text-muted text-center py-4">No artworks found. Add your first artwork using the button above!</p>
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
                            <button class="btn btn-sm btn-outline-success" onclick='assignToExhibition(<?= json_encode($artwork, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' title="Assign to Exhibition">
                                <i class="bi bi-building"></i>
                            </button>
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
                    
                    <!-- Artist Selection Section -->
                    <div class="card bg-light mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label mb-0">Artist(s)</label>
                                <button type="button" class="btn btn-sm btn-success" onclick="addArtistField()">
                                    <i class="bi bi-plus"></i> Add Another Artist
                                </button>
                            </div>
                            
                            <div id="artist-fields-container">
                                <div class="artist-field-group mb-2">
                                    <div class="row">
                                        <div class="col-md-7">
                                            <select class="form-select" name="artist_ids[]" id="artist_id_1">
                                                <option value="">Select an artist...</option>
                                                <?php foreach ($artists as $artist): ?>
                                                    <option value="<?= $artist['artist_id'] ?>">
                                                        <?= htmlspecialchars($artist['first_name'] . ' ' . $artist['last_name']) ?>
                                                        <?= !empty($artist['nationality']) ? ' (' . htmlspecialchars($artist['nationality']) . ')' : '' ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <input type="text" class="form-control" name="artist_roles[]" placeholder="Role (e.g., Creator)" value="Creator">
                                        </div>
                                        <div class="col-md-2">
                                            <button type="button" class="btn btn-outline-danger w-100" onclick="removeArtistField(this)" disabled>
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-2">
                                <small class="text-muted">
                                    Don't see the artist? <a href="#" data-bs-toggle="modal" data-bs-target="#createArtistModal">Create a new artist</a>
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Medium</label>
                            <!-- should change this to use lookup table -->
                            <select class="form-select" name="medium" id="medium">
                                <option value="">Select Medium</option>
                                <option value="1">Oil Paint</option>
                                <option value="2">Watercolor</option>
                                <option value="3">Acrylic</option>
                                <option value="4">Ink</option>
                                <option value="5">Charcoal</option>
                                <option value="6">Graphite</option>
                                <option value="7">Pastel</option>
                                <option value="8">Mixed Media</option>
                                <option value="9">Bronze</option>
                                <option value="10">Marble</option>
                                <option value="11">Wood</option>
                                <option value="12">Clay</option>
                                <option value="13">Glass</option>
                                <option value="14">Digital</option>
                                <option value="15">Photography</option>
                                <option value="16">Installation</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Height (cm)</label>
                            <input type="number" step="0.01" class="form-control" name="height" id="height">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Width (cm)</label>
                            <input type="number" step="0.01" class="form-control" name="width" id="width">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Depth (cm)</label>
                            <input type="number" step="0.01" class="form-control" name="depth" id="depth">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Location</label>
                            <select class="form-select" name="location_id" id="location_id">
                                <option value="">Select location...</option>
                                <?php foreach ($locations as $location): ?>
                                    <option value="<?= $location['location_id'] ?>">
                                        <?= htmlspecialchars($location['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="form-check mt-4">
                                <input type="checkbox" class="form-check-input" name="is_owned" id="is_owned" checked>
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

<!-- Delete Modal -->
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

<!-- Assign to Exhibition Modal -->
<div class="modal fade" id="exhibitionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Assign Artwork to Exhibition</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="artwork_id" id="exhibition_artwork_id">
                    
                    <div class="alert alert-info">
                        <strong>Artwork:</strong> <span id="exhibition_artwork_title"></span>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Select Exhibition *</label>
                        <select class="form-select" name="exhibition_id" id="exhibition_id" required onchange="updateExhibitionDates()">
                            <option value="">Choose an exhibition...</option>
                            <?php foreach ($exhibitions as $exhibition): ?>
                                <option value="<?= $exhibition['exhibition_id'] ?>" 
                                        data-start="<?= $exhibition['start_date'] ?>"
                                        data-end="<?= $exhibition['end_date'] ?>">
                                    <?= htmlspecialchars($exhibition['title']) ?> 
                                    (<?= date('M d, Y', strtotime($exhibition['start_date'])) ?> - 
                                     <?= date('M d, Y', strtotime($exhibition['end_date'])) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Gallery Location *</label>
                        <select class="form-select" name="exhibition_location_id" id="exhibition_location_id" required>
                            <option value="">Select gallery...</option>
                            <?php foreach ($locations as $location): ?>
                                <option value="<?= $location['location_id'] ?>">
                                    <?= htmlspecialchars($location['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Display Start Date *</label>
                            <input type="date" class="form-control" name="start_view_date" id="start_view_date" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Display End Date *</label>
                            <input type="date" class="form-control" name="end_view_date" id="end_view_date" required>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning" id="date-warning" style="display: none;">
                        <i class="bi bi-exclamation-triangle"></i> 
                        <span id="date-warning-text"></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="assign_to_exhibition" class="btn btn-success">
                        <i class="bi bi-check-circle"></i> Assign to Exhibition
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Create Artist Modal -->
<div class="modal fade" id="createArtistModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Quick Create Artist</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">Create a new artist quickly. You can add more details later from the Artists page.</p>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">First Name *</label>
                            <input type="text" class="form-control" name="new_artist_first_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Last Name *</label>
                            <input type="text" class="form-control" name="new_artist_last_name" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Birth Year</label>
                            <input type="number" class="form-control" name="new_artist_birth_year" min="1" max="2099">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nationality</label>
                            <input type="text" class="form-control" name="new_artist_nationality" placeholder="e.g., American">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#artworkModal">
                        Back to Artwork
                    </button>
                    <button type="submit" name="create_inline_artist" class="btn btn-success">
                        <i class="bi bi-person-plus"></i> Create Artist
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let artistFieldCount = 1;

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
    
    // Reset artist fields to just one empty field
    const container = document.getElementById('artist-fields-container');
    container.innerHTML = `
        <div class="artist-field-group mb-2">
            <div class="row">
                <div class="col-md-7">
                    <select class="form-select" name="artist_ids[]" id="artist_id_1">
                        <option value="">Select an artist...</option>
                        <?php foreach ($artists as $artist): ?>
                            <option value="<?= $artist['artist_id'] ?>">
                                <?= htmlspecialchars($artist['first_name'] . ' ' . $artist['last_name']) ?>
                                <?= !empty($artist['nationality']) ? ' (' . htmlspecialchars($artist['nationality']) . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="text" class="form-control" name="artist_roles[]" placeholder="Role (e.g., Creator)" value="Creator">
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-outline-danger w-100" onclick="removeArtistField(this)" disabled>
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    artistFieldCount = 1;
}

// each artist has its own role field
function addArtistField() {
    artistFieldCount++;
    const container = document.getElementById('artist-fields-container');
    const newField = document.createElement('div');
    newField.className = 'artist-field-group mb-2';
    newField.innerHTML = `
        <div class="row">
            <div class="col-md-7">
                <select class="form-select" name="artist_ids[]" id="artist_id_${artistFieldCount}">
                    <option value="">Select an artist...</option>
                    <?php foreach ($artists as $artist): ?>
                        <option value="<?= $artist['artist_id'] ?>">
                            <?= htmlspecialchars($artist['first_name'] . ' ' . $artist['last_name']) ?>
                            <?= !empty($artist['nationality']) ? ' (' . htmlspecialchars($artist['nationality']) . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <input type="text" class="form-control" name="artist_roles[]" placeholder="Role (e.g., Creator)" value="Creator">
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-outline-danger w-100" onclick="removeArtistField(this)">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </div>
    `;
    container.appendChild(newField);
}

function removeArtistField(button) {
    const fieldGroup = button.closest('.artist-field-group');
    fieldGroup.remove();
    artistFieldCount--;
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
    
    // populate artist fields based on existing data
    const container = document.getElementById('artist-fields-container');
    container.innerHTML = ''; // Clear existing fields
    
    // parse artist IDs and roles from the artwork data
    const artistIds = artwork.artist_ids_list ? artwork.artist_ids_list.split(',') : [];
    const artistRoles = artwork.artist_roles_list ? artwork.artist_roles_list.split('||') : [];
    
    if (artistIds.length > 0 && artistIds[0] !== '') {
        // create a field for each existing artist
        artistIds.forEach((artistId, index) => {
            artistFieldCount = index + 1;
            const role = artistRoles[index] || 'Creator';
            
            const newField = document.createElement('div');
            newField.className = 'artist-field-group mb-2';
            newField.innerHTML = `
                <div class="row">
                    <div class="col-md-7">
                        <select class="form-select" name="artist_ids[]" id="artist_id_${artistFieldCount}">
                            <option value="">Select an artist...</option>
                            <?php foreach ($artists as $artist): ?>
                                <option value="<?= $artist['artist_id'] ?>">
                                    <?= htmlspecialchars($artist['first_name'] . ' ' . $artist['last_name']) ?>
                                    <?= !empty($artist['nationality']) ? ' (' . htmlspecialchars($artist['nationality']) . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="text" class="form-control" name="artist_roles[]" placeholder="Role (e.g., Creator)" value="${role}">
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-outline-danger w-100" onclick="removeArtistField(this)" ${index === 0 ? 'disabled' : ''}>
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            `;
            container.appendChild(newField);
            
            // Set the selected artist
            const select = newField.querySelector('select');
            select.value = artistId.trim();
        });
    } else {
        // no artists create one empty field
        artistFieldCount = 1;
        const newField = document.createElement('div');
        newField.className = 'artist-field-group mb-2';
        newField.innerHTML = `
            <div class="row">
                <div class="col-md-7">
                    <select class="form-select" name="artist_ids[]" id="artist_id_1">
                        <option value="">Select an artist...</option>
                        <?php foreach ($artists as $artist): ?>
                            <option value="<?= $artist['artist_id'] ?>">
                                <?= htmlspecialchars($artist['first_name'] . ' ' . $artist['last_name']) ?>
                                <?= !empty($artist['nationality']) ? ' (' . htmlspecialchars($artist['nationality']) . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="text" class="form-control" name="artist_roles[]" placeholder="Role (e.g., Creator)" value="Creator">
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-outline-danger w-100" onclick="removeArtistField(this)" disabled>
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        `;
        container.appendChild(newField);
    }
    
    new bootstrap.Modal(document.getElementById('artworkModal')).show();
}

function deleteArtwork(id, title) {
    document.getElementById('delete_artwork_id').value = id;
    document.getElementById('deleteArtworkTitle').textContent = title;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

function assignToExhibition(artwork) {
    document.getElementById('exhibition_artwork_id').value = artwork.artwork_id;
    document.getElementById('exhibition_artwork_title').textContent = artwork.title;
    document.getElementById('exhibition_id').value = '';
    document.getElementById('exhibition_location_id').value = '';
    document.getElementById('start_view_date').value = '';
    document.getElementById('end_view_date').value = '';
    document.getElementById('date-warning').style.display = 'none';
    
    new bootstrap.Modal(document.getElementById('exhibitionModal')).show();
}

function updateExhibitionDates() {
    const select = document.getElementById('exhibition_id');
    const selectedOption = select.options[select.selectedIndex];
    
    if (selectedOption.value) {
        const startDate = selectedOption.getAttribute('data-start');
        const endDate = selectedOption.getAttribute('data-end');
        
        document.getElementById('start_view_date').value = startDate;
        document.getElementById('end_view_date').value = endDate;
        document.getElementById('start_view_date').min = startDate;
        document.getElementById('start_view_date').max = endDate;
        document.getElementById('end_view_date').min = startDate;
        document.getElementById('end_view_date').max = endDate;
    }
}
</script>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>