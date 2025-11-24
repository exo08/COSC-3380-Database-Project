<?php
session_start();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/permissions.php';

$page_title = 'Exhibition Artwork List';

// Only admin and curator can access
if ($_SESSION['user_type'] !== 'admin' && $_SESSION['user_type'] !== 'curator') {
    header('Location: index.php?error=access_denied');
    exit;
}

$db = db();

$artworks = [];
$search_performed = false;

// Get all exhibitions for dropdown
$exhibitions_result = $db->query("SELECT exhibition_id, title FROM EXHIBITION ORDER BY start_date DESC");
$exhibitions = $exhibitions_result->fetch_all(MYSQLI_ASSOC);

if (isset($_POST['exhibition_title'])) {
    $title = $_POST['exhibition_title'];
    
    $stmt = $db->prepare("CALL GetExhibitionArtworkList(?)");
    $stmt->bind_param("s", $title);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $artworks = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $db->next_result();
    
    $search_performed = true;
}

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv' && isset($_GET['title'])) {
    $title = $_GET['title'];
    $stmt = $db->prepare("CALL GetExhibitionArtworkList(?)");
    $stmt->bind_param("s", $title);
    $stmt->execute();
    $result = $stmt->get_result();
    $export_data = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $db->next_result();
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="exhibition-artworks-' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Artwork Title', 'Artist First Name', 'Artist Last Name']);
    
    foreach ($export_data as $row) {
        fputcsv($output, [
            $row['title'],
            $row['first_name'],
            $row['last_name']
        ]);
    }
    
    fclose($output);
    exit;
}

include __DIR__ . '/../templates/layout_header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="index.php" class="btn btn-outline-secondary mb-2">
                <i class="bi bi-arrow-left"></i> Back to Reports
            </a>
            <h1 class="mb-0"><i class="bi bi-easel"></i> Exhibition Artwork List</h1>
            <p class="text-muted">View all artworks displayed in a specific exhibition</p>
        </div>
        <?php if ($search_performed && !empty($artworks)): ?>
            <a href="?export=csv&title=<?= urlencode($_POST['exhibition_title']) ?>" class="btn btn-success">
                <i class="bi bi-file-earmark-spreadsheet"></i> Export to CSV
            </a>
        <?php endif; ?>
    </div>
    
    <div class="card mb-4">
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-8">
                        <label class="form-label">Select Exhibition</label>
                        <select name="exhibition_title" class="form-select" required>
                            <option value="">Choose an exhibition...</option>
                            <?php foreach ($exhibitions as $exhibition): ?>
                                <option value="<?= htmlspecialchars($exhibition['title']) ?>"
                                    <?= (isset($_POST['exhibition_title']) && $_POST['exhibition_title'] == $exhibition['title']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($exhibition['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i> View Artworks
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <?php if ($search_performed): ?>
        <?php if (empty($artworks)): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i> No artworks found for this exhibition. The exhibition may not have any artworks assigned yet.
            </div>
        <?php else: ?>
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="bi bi-palette"></i> <?= htmlspecialchars($_POST['exhibition_title']) ?>
                    </h4>
                </div>
            </div>
            
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <h3>Total Artworks: <?= count($artworks) ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="artworksTable">
                            <thead class="table-dark">
                                <tr>
                                    <th>#</th>
                                    <th>Artwork Title</th>
                                    <th>Artist</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $count = 1; foreach ($artworks as $artwork): ?>
                                    <tr>
                                        <td><?= $count++ ?></td>
                                        <td><strong><?= htmlspecialchars($artwork['title']) ?></strong></td>
                                        <td>
                                            <?php if ($artwork['first_name'] && $artwork['last_name']): ?>
                                                <?= htmlspecialchars($artwork['first_name'] . ' ' . $artwork['last_name']) ?>
                                            <?php else: ?>
                                                <em class="text-muted">Unknown Artist</em>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-info-circle"></i> Exhibition Manifest</h5>
                </div>
                <div class="card-body">
                    <p><strong>Exhibition:</strong> <?= htmlspecialchars($_POST['exhibition_title']) ?></p>
                    <p><strong>Total Artworks:</strong> <?= count($artworks) ?></p>
                    <p><strong>Generated:</strong> <?= date('F j, Y g:i A') ?></p>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    $('#artworksTable').DataTable({
        pageLength: 25,
        order: [[1, 'asc']] // Sort by artwork title
    });
});
</script>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>