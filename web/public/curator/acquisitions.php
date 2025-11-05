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
        if($acquisition_id){ // update
            requirePermission('edit_acquisition');
            $stmt = $db->prepare("UPDATE ACQUISITION 
                SET artwork_id = ?, acquisition_date = ?, method = ?, price_value = ?, source_name = ? 
                WHERE acquisition_id = ?");
            $stmt->bind_param('isidsi', $artwork_id, $acquisition_date, $method, $price_value, $source_name, $acquisition_id);
            if($stmt->execute()){
                $success = 'Acquisition updated successfully.';
                logActivity('acquisition_updated', 'ACQUISITION', $acquisition_id, "Updated acquisition for artwork $artwork_id");
            }else{
                $error = 'Error updating acquisition: ' . $db->error;
            }
        } else { // insert new
            requirePermission('add_acquisition');
            $stmt = $db->prepare("INSERT INTO ACQUISITION (artwork_id, acquisition_date, method, price_value, source_name)
                                  VALUES (?, ?, ?, ?, ?)");
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

// Load all acquisitions
$acq_query = "
    SELECT a.*, aw.title AS artwork_title
    FROM ACQUISITION a
    LEFT JOIN ARTWORK aw ON a.artwork_id = aw.artwork_id
    ORDER BY a.acquisition_date DESC
";
$result = $db->query($acq_query);
$acquisitions = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Load all artworks for dropdown
$artwork_result = $db->query("SELECT artwork_id, title FROM ARTWORK ORDER BY title");
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

<!-- add new acquisition button -->
<?php if (hasPermission('add_acquisition')): ?>
<div class="mb-4">
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#acquisitionModal" onclick="clearForm()">
        <i class="bi bi-plus-circle"></i> Add New Acquisition
    </button>
</div>
<?php endif; ?>

<!-- acquisitions table -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-box-seam"></i> All Acquisitions (<?= count($acquisitions) ?>)</h5>
    </div>
    <div class="card-body">
        <?php if(empty($acquisitions)): ?>
            <p class="text-muted">No acquisitions recorded yet.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Artwork</th>
                        <th>Date</th>
                        <th>Method</th>
                        <th>Price</th>
                        <th>Source</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($acquisitions as $a): ?>
                    <tr>
                        <td><?= htmlspecialchars($a['acquisition_id']) ?></td>
                        <td><?= htmlspecialchars($a['artwork_title'] ?? 'Unknown') ?></td>
                        <td><?= htmlspecialchars(date('M d, Y', strtotime($a['acquisition_date']))) ?></td>
                        <td>
                            <?php
                            $methods = [1=>'Purchase',2=>'Bequest',3=>'Gift',4=>'Transfer'];
                            echo htmlspecialchars($methods[$a['method']] ?? 'Unknown');
                            ?>
                        </td>
                        <td>$<?= number_format($a['price_value'], 2) ?></td>
                        <td><?= htmlspecialchars($a['source_name']) ?></td>
                        <td>
                            <?php if (hasPermission('edit_acquisition')): ?>
                            <button class="btn btn-sm btn-outline-primary" 
                                    onclick='editAcquisition(<?= json_encode($a, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                <i class="bi bi-pencil"></i>
                            </button>
                            <?php endif; ?>

                            <?php if (hasPermission('delete_acquisition')): ?>
                            <button class="btn btn-sm btn-outline-danger" 
                                    onclick="deleteAcquisition(<?= $a['acquisition_id'] ?>, '<?= htmlspecialchars($a['artwork_title'], ENT_QUOTES) ?>')">
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
                                <option value="<?= $aw['artwork_id'] ?>"><?= htmlspecialchars($aw['title']) ?></option>
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
function clearForm() {
    document.getElementById('modalTitle').textContent = 'Add New Acquisition';
    document.getElementById('acquisition_id').value = '';
    document.getElementById('artwork_id').value = '';
    document.getElementById('acquisition_date').value = '';
    document.getElementById('method').value = '';
    document.getElementById('price_value').value = '';
    document.getElementById('source_name').value = '';
}

function editAcquisition(acq) {
    document.getElementById('modalTitle').textContent = 'Edit Acquisition';
    document.getElementById('acquisition_id').value = acq.acquisition_id;
    document.getElementById('artwork_id').value = acq.artwork_id;
    document.getElementById('acquisition_date').value = acq.acquisition_date;
    document.getElementById('method').value = acq.method;
    document.getElementById('price_value').value = acq.price_value;
    document.getElementById('source_name').value = acq.source_name;
    new bootstrap.Modal(document.getElementById('acquisitionModal')).show();
}

function deleteAcquisition(id, title) {
    document.getElementById('delete_acquisition_id').value = id;
    document.getElementById('deleteAcquisitionTitle').textContent = title;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>
