<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/permissions.php';

// check permission
requirePermission('view_acquisitions');

$page_title = "Manage Acquisitions";
$db = db();

$success = '';
$error = '';

// helper functions
function methodLabel($code) {
    $map = [1=>'Purchase',2=>'Bequest',3=>'Gift',4=>'Transfer'];
    return $map[(int)$code] ?? 'Unknown';
}
function statusBadge($status) {
    $class = [
        'pending'  => 'warning',
        'accepted' => 'success',
        'rejected' => 'secondary'
    ][$status] ?? 'secondary';
    return "<span class='badge bg-{$class} text-uppercase'>".htmlspecialchars($status)."</span>";
}
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// reject donation
if (isset($_POST['reject_donation'])) {
    requirePermission('edit_acquisitions');
    $acquisition_id = (int)($_POST['acquisition_id'] ?? 0);
    $artwork_id     = (int)($_POST['artwork_id'] ?? 0);

    if ($acquisition_id) {
        $db->begin_transaction();
        try {
            $stmt = $db->prepare("UPDATE ACQUISITION SET acquisition_status='rejected' WHERE acquisition_id=?");
            $stmt->bind_param('i', $acquisition_id);
            $stmt->execute();
            $stmt->close();

            if ($artwork_id) {
                $stmt = $db->prepare("UPDATE ARTWORK SET is_deleted=1, title=CONCAT(title,' (rejected #', ?, ')') WHERE artwork_id=?");
                $stmt->bind_param('ii', $acquisition_id, $artwork_id);
                $stmt->execute();
                $stmt->close();
            }

            $db->commit();
            $success = 'Donation rejected.';
            logActivity('donation_rejected', 'ACQUISITION', $acquisition_id, $artwork_id ? "Rejected donation; artwork $artwork_id hidden" : "Rejected donation; no artwork yet");
        } catch (Throwable $e) {
            $db->rollback();
            $error = 'Error rejecting donation: '.$e->getMessage();
        }
    } else {
        $error = 'Invalid record for rejection.';
    }
}

// accept donation  
if (isset($_POST['accept_donation'])) {
    requirePermission('edit_acquisitions');

    $acquisition_id = (int)($_POST['acquisition_id'] ?? 0);
    $artwork_id     = (int)($_POST['artwork_id'] ?? 0);

    $title         = trim($_POST['title'] ?? '');
    $creation_year = ($_POST['creation_year'] ?? '') !== '' ? (int)$_POST['creation_year'] : null;
    $height        = ($_POST['height'] ?? '') !== '' ? (float)$_POST['height'] : null;
    $width         = ($_POST['width']  ?? '') !== '' ? (float)$_POST['width']  : null;
    $depth         = ($_POST['depth']  ?? '') !== '' ? (float)$_POST['depth']  : null;
    $description   = trim($_POST['description'] ?? '');

    $afirst = trim($_POST['artist_first_name'] ?? '');
    $alast  = trim($_POST['artist_last_name'] ?? '');
    $abirth = ($_POST['artist_birth_year'] ?? '') !== '' ? (int)$_POST['artist_birth_year'] : null;
    $adeath = ($_POST['artist_death_year'] ?? '') !== '' ? (int)$_POST['artist_death_year'] : null;
    $anat   = trim($_POST['artist_nationality'] ?? '');
    $abio   = trim($_POST['artist_bio'] ?? '');

    $gift_code = 3;

    if ($acquisition_id && $title !== '') {
        $db->begin_transaction();
        try {
            // Create artwork if needed
            if (!$artwork_id) {
                $stmt = $db->prepare("INSERT INTO ARTWORK (title, is_owned, is_deleted, description) VALUES ('Unknown', 0, 0, 'Pending donation')");
                $stmt->execute();
                $artwork_id = $stmt->insert_id;
                $stmt->close();

                $stmt = $db->prepare("UPDATE ACQUISITION SET artwork_id=? WHERE acquisition_id=?");
                $stmt->bind_param('ii', $artwork_id, $acquisition_id);
                $stmt->execute();
                $stmt->close();
            }

            // Find or create artist
            $artist_id = null;
            if ($afirst !== '' || $alast !== '') {
                error_log("=== ARTIST PROCESSING DEBUG ===");
                error_log("Searching for artist: '$afirst' '$alast' (birth: $abirth)");
                
                $stmt = $db->prepare("SELECT artist_id FROM ARTIST WHERE first_name=? AND last_name=? AND (birth_year <=> ?)");
                $stmt->bind_param('ssi', $afirst, $alast, $abirth);
                $stmt->execute();
                $stmt->bind_result($artist_id);
                $found = $stmt->fetch();
                $stmt->close();

                if ($found) {
                    error_log("Found existing artist with ID: $artist_id");
                } else {
                    error_log("Artist not found, creating new artist");
                    $stmt = $db->prepare("INSERT INTO ARTIST (first_name,last_name,birth_year,death_year,nationality,bio,is_deleted) VALUES (?,?,?,?,?, ?, 0)");
                    $stmt->bind_param('ssiiss', $afirst, $alast, $abirth, $adeath, $anat, $abio);
                    
                    if ($stmt->execute()) {
                        $artist_id = $stmt->insert_id;
                        error_log("Successfully created artist with ID: $artist_id");
                    } else {
                        error_log("ERROR creating artist: " . $stmt->error);
                    }
                    $stmt->close();
                }
                error_log("Final artist_id: " . ($artist_id ?: 'NULL'));
            } else {
                error_log("No artist name provided, skipping artist creation");
            }

            // Update artwork with real data
            $stmt = $db->prepare("UPDATE ARTWORK SET title=?, creation_year=?, height=?, width=?, depth=?, description=?, is_owned=1, is_deleted=0 WHERE artwork_id=?");
            $stmt->bind_param('sidddsi', $title, $creation_year, $height, $width, $depth, $description, $artwork_id);
            $stmt->execute();
            $stmt->close();

            // Link artist to artwork
            if ($artist_id) {
                error_log("=== ARTWORK_CREATOR LINKING DEBUG ===");
                error_log("Checking if link exists: artwork_id=$artwork_id, artist_id=$artist_id");
                
                // Check if relationship already exists
                $stmt = $db->prepare("SELECT 1 FROM ARTWORK_CREATOR WHERE artwork_id=? AND artist_id=?");
                $stmt->bind_param('ii', $artwork_id, $artist_id);
                $stmt->execute();
                $res = $stmt->get_result();
                $exists = ($res && $res->num_rows > 0);
                $stmt->close();

                if ($exists) {
                    error_log("ARTWORK_CREATOR link already exists, skipping");
                } else {
                    error_log("Creating new ARTWORK_CREATOR link");
                    
                    // Use 'Creator' with capital C to match database convention
                    $stmt = $db->prepare("INSERT INTO ARTWORK_CREATOR (artwork_id, artist_id, role) VALUES (?, ?, 'Creator')");
                    $stmt->bind_param('ii', $artwork_id, $artist_id);
                    
                    if ($stmt->execute()) {
                        error_log("SUCCESS: Linked artwork $artwork_id to artist $artist_id");
                        $success_creator = true;
                    } else {
                        error_log("ERROR inserting ARTWORK_CREATOR: " . $stmt->error);
                        // Don't throw exception let the donation complete even if linking fails
                    }
                    $stmt->close();
                }
                error_log("=====================================");
            } else {
                error_log("WARNING: No artist_id available to link");
            }

            // Mark acquisition accepted
            $stmt = $db->prepare("UPDATE ACQUISITION SET acquisition_status='accepted', method=?, acquisition_date=CURDATE() WHERE acquisition_id=?");
            $stmt->bind_param('ii', $gift_code, $acquisition_id);
            $stmt->execute();
            $stmt->close();

            $db->commit();
            
            $success = "Donation accepted and artwork updated (#{$artwork_id}).";
            if (isset($success_creator)) {
                $success .= " Artist linked successfully.";
            }
            
            logActivity('donation_accepted', 'ACQUISITION', $acquisition_id, "Accepted donation; finalized artwork $artwork_id" . ($artist_id ? " with artist $artist_id" : ""));
        } catch (Throwable $e) {
            $db->rollback();
            error_log("ERROR in accept_donation: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            $error = 'Error accepting donation: '.$e->getMessage();
        }
    } else {
        $error = 'Missing required fields for acceptance.';
    }
}

// Handle delete
if(isset($_POST['delete_acquisition'])){
    requirePermission('delete_acquisition');
    $acquisition_id = intval($_POST['acquisition_id']);

    if($db->query("DELETE FROM ACQUISITION WHERE acquisition_id = $acquisition_id")){
        $success = 'Acquisition deleted successfully.';
        logActivity('acquisition_deleted', 'ACQUISITION', $acquisition_id, "Deleted acquisition ID: $acquisition_id");
    }else{
        $error = 'Error deleting acquisition: ' . $db->error;
    }
}

// Handle add/edit
if(isset($_POST['save_acquisition'])){
    $acquisition_id = !empty($_POST['acquisition_id']) ? intval($_POST['acquisition_id']) : null;
    $artwork_id = intval($_POST['artwork_id']);
    $acquisition_date = $_POST['acquisition_date'] ?? '';
    $method = intval($_POST['method'] ?? 0);
    $price_value = floatval($_POST['price_value'] ?? 0);
    $source_name = $_POST['source_name'] ?? '';

    try {
        if($acquisition_id){
            requirePermission('edit_acquisitions');
            $stmt = $db->prepare("UPDATE ACQUISITION SET artwork_id = ?, acquisition_date = ?, method = ?, price_value = ?, source_name = ? WHERE acquisition_id = ?");
            $stmt->bind_param('isidsi', $artwork_id, $acquisition_date, $method, $price_value, $source_name, $acquisition_id);
            if($stmt->execute()){
                $success = 'Acquisition updated successfully.';
                logActivity('acquisition_updated', 'ACQUISITION', $acquisition_id, "Updated acquisition for artwork $artwork_id");
            }else{
                $error = 'Error updating acquisition: ' . $db->error;
            }
        } else {
            requirePermission('add_acquisition');
            $stmt = $db->prepare("INSERT INTO ACQUISITION (artwork_id, acquisition_date, method, price_value, source_name) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param('isids', $artwork_id, $acquisition_date, $method, $price_value, $source_name);
            if($stmt->execute()){
                $new_id = $stmt->insert_id;
                $success = "Acquisition added successfully (ID: $new_id)";
                logActivity('acquisition_created', 'ACQUISITION', $new_id, "Added acquisition for artwork $artwork_id");
            }else{
                $error = 'Error adding acquisition: ' . $db->error;
            }
        }
        if(isset($stmt)) $stmt->close();
    } catch (Exception $e) {
        $error = 'Error saving acquisition: ' . $e->getMessage();
    }
}

// Load data
$acq_query = "
    SELECT a.*, aw.title AS artwork_title
    FROM ACQUISITION a
    LEFT JOIN ARTWORK aw ON aw.artwork_id = a.artwork_id
    WHERE (aw.artwork_id IS NULL OR COALESCE(aw.is_deleted,0) = 0)
    ORDER BY a.acquisition_date DESC, a.acquisition_id DESC
";
$result = $db->query($acq_query);
$acquisitions = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

$artwork_result = $db->query("SELECT artwork_id, title FROM ARTWORK WHERE COALESCE(is_deleted,0)=0 ORDER BY title");
$artwork_list = $artwork_result ? $artwork_result->fetch_all(MYSQLI_ASSOC) : [];

include __DIR__ . '/../templates/layout_header.php';
?>

<?php if($success): ?>
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if($error): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (hasPermission('add_acquisition')): ?>
<div class="mb-4">
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#acquisitionModal" onclick="clearForm()">
        <i class="bi bi-plus-circle"></i> Add New Acquisition
    </button>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h5 class="mb-0"><i class="bi bi-box-seam"></i> All Acquisitions (<?= count($acquisitions) ?>)</h5>
        <div class="small text-muted">Pending donations can be reviewed here.</div>
    </div>
    <div class="card-body">
        <?php if(empty($acquisitions)): ?>
            <p class="text-muted">No acquisitions recorded yet.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Artwork</th>
                        <th>Date</th>
                        <th>Method</th>
                        <th>Price</th>
                        <th>Source</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($acquisitions as $a): ?>
                    <?php
                        $status = $a['acquisition_status'] ?: 'accepted';
                        $payload = $a['submission_data'] ?? '';
                        $date = $a['acquisition_date'] ? date('M d, Y', strtotime($a['acquisition_date'])) : '—';
                        $price = is_null($a['price_value']) ? '—' : '$'.number_format((float)$a['price_value'], 2);

                        $displayTitle = $a['artwork_title'] ?? '';
                        if ($displayTitle === '' || $displayTitle === null) {
                            $t = '';
                            if ($payload) {
                                $j = json_decode($payload, true);
                                if (json_last_error() === JSON_ERROR_NONE) {
                                    $t = $j['artwork_title'] ?? '';
                                }
                            }
                            $displayTitle = $t !== '' ? $t . ' (donation)' : 'Unknown (donation)';
                        }
                    ?>
                    <tr>
                        <td><?= h($a['acquisition_id']) ?></td>
                        <td><?= h($displayTitle) ?></td>
                        <td><?= h($date) ?></td>
                        <td><?= h(methodLabel($a['method'])) ?></td>
                        <td><?= h($price) ?></td>
                        <td><?= h($a['source_name'] ?? '—') ?></td>
                        <td><?= statusBadge($status) ?></td>
                        <td class="text-nowrap">
                            <?php if ($status === 'pending' && hasPermission('edit_acquisitions')): ?>
                                <button class="btn btn-sm btn-primary"
                                        data-bs-toggle="modal"
                                        data-bs-target="#reviewModal"
                                        data-acq="<?= (int)$a['acquisition_id'] ?>"
                                        data-art="<?= (int)($a['artwork_id'] ?? 0) ?>"
                                        data-title="<?= h($displayTitle) ?>"
                                        data-payload-b64="<?= base64_encode($payload) ?>">
                                    Review
                                </button>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="acquisition_id" value="<?= (int)$a['acquisition_id'] ?>">
                                    <input type="hidden" name="artwork_id" value="<?= (int)($a['artwork_id'] ?? 0) ?>">
                                    <button class="btn btn-sm btn-outline-secondary" name="reject_donation">
                                        Reject
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if (hasPermission('edit_acquisitions') && $status !== 'pending'): ?>
                            <button class="btn btn-sm btn-outline-primary" 
                                    data-acq-json='<?= json_encode($a, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>'
                                    onclick='editAcquisition(this)'>
                                <i class="bi bi-pencil"></i>
                            </button>
                            <?php endif; ?>

                            <?php if (hasPermission('delete_acquisition')): ?>
                            <button class="btn btn-sm btn-outline-danger" 
                                    onclick="deleteAcquisition(<?= (int)$a['acquisition_id'] ?>, '<?= h($displayTitle) ?>')">
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

<!-- add/edit Modal -->
<div class="modal fade" id="acquisitionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add New Acquisition</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="acquisition_id" id="acquisition_id">

                    <div class="mb-3">
                        <label class="form-label">Artwork *</label>
                        <select class="form-select" name="artwork_id" id="artwork_id" required>
                            <option value="">Select Artwork</option>
                            <?php foreach ($artwork_list as $aw): ?>
                                <option value="<?= $aw['artwork_id'] ?>"><?= h($aw['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Acquisition Date *</label>
                            <input type="date" class="form-control" name="acquisition_date" id="acquisition_date" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Method *</label>
                            <select class="form-select" name="method" id="method" required>
                                <option value="">Select Method</option>
                                <option value="1">Purchase</option>
                                <option value="2">Bequest</option>
                                <option value="3">Gift</option>
                                <option value="4">Transfer</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Price Value</label>
                        <input type="number" step="0.01" class="form-control" name="price_value" id="price_value" placeholder="Enter price or 0 if none">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Source Name</label>
                        <input type="text" class="form-control" name="source_name" id="source_name" placeholder="e.g., Donor, Gallery, Estate">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="save_acquisition" class="btn btn-primary">
                        <i class="bi bi-save"></i> Save Acquisition
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Donation Review Modal -->
<div class="modal fade" id="reviewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content" style="max-height: 90vh;">
      <form method="post">
        <input type="hidden" name="acquisition_id" id="m_acq_id">
        <input type="hidden" name="artwork_id" id="m_art_id">

        <div class="modal-header">
          <h5 class="modal-title">Review Donation</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body" style="max-height: calc(90vh - 150px); overflow-y: auto;">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Artwork Title</label>
              <input class="form-control" name="title" id="m_title" required>
            </div>

            <div class="col-md-4">
              <label class="form-label">Creation Year</label>
              <input class="form-control" name="creation_year" id="m_year" type="number" min="0">
            </div>

            <div class="col-md-4">
              <label class="form-label">Height (cm)</label>
              <input class="form-control" name="height" id="m_h" type="number" step="0.01" min="0">
            </div>
            <div class="col-md-4">
              <label class="form-label">Width (cm)</label>
              <input class="form-control" name="width" id="m_w" type="number" step="0.01" min="0">
            </div>
            <div class="col-md-4">
              <label class="form-label">Depth (cm)</label>
              <input class="form-control" name="depth" id="m_d" type="number" step="0.01" min="0">
            </div>

            <div class="col-12">
              <label class="form-label">Description</label>
              <textarea class="form-control" name="description" id="m_desc" rows="2"></textarea>
            </div>

            <div class="col-12"><hr></div>
            <div class="col-12">
              <h6 class="mb-2">Artist</h6>
            </div>

            <div class="col-md-6">
              <label class="form-label">First Name</label>
              <input class="form-control" name="artist_first_name" id="m_afirst">
            </div>
            <div class="col-md-6">
              <label class="form-label">Last Name</label>
              <input class="form-control" name="artist_last_name" id="m_alast">
            </div>
            <div class="col-md-6">
              <label class="form-label">Birth Year</label>
              <input class="form-control" name="artist_birth_year" id="m_abirth" type="number">
            </div>
            <div class="col-md-6">
              <label class="form-label">Death Year</label>
              <input class="form-control" name="artist_death_year" id="m_adeath" type="number">
            </div>
            <div class="col-md-6">
              <label class="form-label">Nationality</label>
              <input class="form-control" name="artist_nationality" id="m_anat">
            </div>
            <div class="col-12">
              <label class="form-label">Bio</label>
              <textarea class="form-control" name="artist_bio" id="m_abio" rows="2"></textarea>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn btn-primary" type="submit" name="accept_donation">Accept & Save</button>
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
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
                    <p>Are you sure you want to delete this acquisition?</p>
                    <p class="fw-bold" id="deleteAcquisitionTitle"></p>
                    <input type="hidden" name="acquisition_id" id="delete_acquisition_id">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_acquisition" class="btn btn-danger">
                        <i class="bi bi-trash"></i> Delete
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
console.log('=== Acquisitions JavaScript Loaded ===');

function clearForm() {
    document.getElementById('modalTitle').textContent = 'Add New Acquisition';
    document.getElementById('acquisition_id').value = '';
    document.getElementById('artwork_id').value = '';
    document.getElementById('acquisition_date').value = '';
    document.getElementById('method').value = '';
    document.getElementById('price_value').value = '';
    document.getElementById('source_name').value = '';
}

function editAcquisition(button) {
    console.log('=== Edit Button Clicked ===');
    
    try {
        const jsonStr = button.getAttribute('data-acq-json');
        console.log('Raw JSON string:', jsonStr);
        
        const acq = JSON.parse(jsonStr);
        console.log('Parsed acquisition data:', acq);
        
        document.getElementById('modalTitle').textContent = 'Edit Acquisition';
        document.getElementById('acquisition_id').value = acq.acquisition_id || '';
        document.getElementById('artwork_id').value = acq.artwork_id || '';
        document.getElementById('acquisition_date').value = acq.acquisition_date || '';
        document.getElementById('method').value = acq.method || '';
        document.getElementById('price_value').value = acq.price_value || '';
        document.getElementById('source_name').value = acq.source_name || '';
        
        const modalEl = document.getElementById('acquisitionModal');
        if (modalEl) {
            const modal = new bootstrap.Modal(modalEl);
            modal.show();
            console.log('Modal opened successfully');
        } else {
            console.error('ERROR: acquisitionModal element not found!');
        }
    } catch (error) {
        console.error('ERROR in editAcquisition:', error);
        alert('Error opening edit modal. Check console for details.');
    }
}

function deleteAcquisition(id, title) {
    document.getElementById('delete_acquisition_id').value = id;
    document.getElementById('deleteAcquisitionTitle').textContent = title;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

// Review modal population
const reviewModal = document.getElementById('reviewModal');
console.log('reviewModal element found:', !!reviewModal);

if (reviewModal) {
  reviewModal.addEventListener('show.bs.modal', function (event) {
    console.log('=== Review Modal Opening ===');
    
    const button = event.relatedTarget;
    const acqId = button.getAttribute('data-acq');
    const artId = button.getAttribute('data-art');
    const currTitle = button.getAttribute('data-title') || '';
    const rawB64 = button.getAttribute('data-payload-b64') || '';

    console.log('Modal data attributes:');
    console.log('  acqId:', acqId);
    console.log('  artId:', artId);
    console.log('  title:', currTitle);
    console.log('  base64 length:', rawB64.length);

    let data = {};
    try { 
      if (rawB64) {
        const decoded = atob(rawB64);
        console.log('Decoded JSON:', decoded);
        data = JSON.parse(decoded);
        console.log('Parsed data:', data);
      } else {
        console.warn('No payload data found!');
      }
    } catch(e) { 
      console.error('ERROR parsing JSON:', e);
      console.error('Raw base64:', rawB64);
    }

    function pick(obj, keys) {
      for (const k of keys) {
        if (obj && Object.prototype.hasOwnProperty.call(obj, k) && obj[k] !== null && obj[k] !== undefined && obj[k] !== '') {
          return obj[k];
        }
      }
      return undefined;
    }

    // Hidden ids
    document.getElementById('m_acq_id').value = acqId;
    document.getElementById('m_art_id').value = artId;

    // Artwork
    document.getElementById('m_title').value = pick(data, ['artwork_title','title']) || (currTitle || '');
    document.getElementById('m_year').value  = (pick(data, ['creation_year','year']) ?? '');
    document.getElementById('m_h').value     = (pick(data, ['height','h']) ?? '');
    document.getElementById('m_w').value     = (pick(data, ['width','w']) ?? '');
    document.getElementById('m_d').value     = (pick(data, ['depth','d']) ?? '');
    document.getElementById('m_desc').value  = pick(data, ['description','desc']) || '';

    // Artist
    const a = data.artist || {};
    document.getElementById('m_afirst').value = pick(a, ['first_name','firstName']) || (pick(data, ['artist_first_name','artistFirstName']) || '');
    document.getElementById('m_alast').value  = pick(a, ['last_name','lastName']) || (pick(data, ['artist_last_name','artistLastName']) || '');
    document.getElementById('m_abirth').value = (pick(a, ['birth_year','birthYear']) ?? (pick(data, ['artist_birth_year','artistBirthYear']) ?? ''));
    document.getElementById('m_adeath').value = (pick(a, ['death_year','deathYear']) ?? (pick(data, ['artist_death_year','artistDeathYear']) ?? ''));
    document.getElementById('m_anat').value   = pick(a, ['nationality']) || (pick(data, ['artist_nationality','artistNationality']) || '');
    document.getElementById('m_abio').value   = pick(a, ['bio']) || (pick(data, ['artist_bio','artistBio']) || '');
    
    console.log('Form populated. Check values:');
    console.log('  Title:', document.getElementById('m_title').value);
    console.log('  Artist first:', document.getElementById('m_afirst').value);
    console.log('  Artist last:', document.getElementById('m_alast').value);
  });
} else {
  console.error('ERROR: reviewModal element not found in DOM!');
}

console.log('=== JavaScript Setup Complete ===');
</script>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>