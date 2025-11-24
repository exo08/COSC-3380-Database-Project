<?php
session_start();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/permissions.php';

$report_name = basename(__FILE__, '.php'); 
if (!hasReportAccess($report_name)) {
    header('Location: index.php?error=access_denied');
    exit;
}

$page_title = 'Full Artwork Catalog';
$db = db();

$result = $db->query("CALL GetFullArtworkCatalog()");
$artworks = $result->fetch_all(MYSQLI_ASSOC);
$result->close();
$db->next_result();

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="artwork-catalog-' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    if (!empty($artworks)) {
        fputcsv($output, array_keys($artworks[0]));
    }
    foreach ($artworks as $row) {
        fputcsv($output, $row);
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
            <h1 class="mb-0"><i class="bi bi-book"></i> Full Artwork Catalog</h1>
            <p class="text-muted">Complete catalog of all artworks in the collection</p>
        </div>
        <div>
            <a href="?export=csv" class="btn btn-success">
                <i class="bi bi-file-earmark-spreadsheet"></i> Export to CSV
            </a>
            <button onclick="window.print()" class="btn btn-primary">
                <i class="bi bi-printer"></i> Print
            </button>
        </div>
    </div>
    
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h3>Total Artworks: <?= number_format(count($artworks)) ?></h3>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="catalogTable">
                    <thead class="table-dark">
                        <tr>
                            <th>Title</th>
                            <th>Artist</th>
                            <th>Location</th>
                            <th>Owned</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($artworks as $artwork): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($artwork['title']) ?></strong></td>
                                <td>
                                    <?php if ($artwork['first_name']): ?>
                                        <?= htmlspecialchars($artwork['first_name'] . ' ' . $artwork['last_name']) ?>
                                    <?php else: ?>
                                        <em class="text-muted">Unknown Artist</em>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($artwork['name'] ?? 'Not Assigned') ?></td>
                                <td>
                                    <?php if ($artwork['is_owned']): ?>
                                        <span class="badge bg-success">Owned</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">On Loan</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    $('#catalogTable').DataTable({
        pageLength: 25,
        order: [[0, 'asc']]
    });
});
</script>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>