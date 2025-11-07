<?php
session_start();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/permissions.php';

// ADD THIS BLOCK:
$report_name = basename(__FILE__, '.php'); // Gets filename without .php
if (!hasReportAccess($report_name)) {
    header('Location: index.php?error=access_denied');
    exit;
}


$page_title = 'Acquisition History Report';
$db = db();

// Call the stored procedure
$result = $db->query("CALL GetAcquisitionHistory()");
$acquisitions = $result->fetch_all(MYSQLI_ASSOC);
$result->close();
$db->next_result(); // Important: clear the result set

// Handle export requests
if (isset($_GET['export'])) {
    if ($_GET['export'] === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="acquisition-history-' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Write headers
        if (!empty($acquisitions)) {
            fputcsv($output, array_keys($acquisitions[0]));
        }
        
        // Write data
        foreach ($acquisitions as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }
}

include __DIR__ . '/../templates/layout_header.php';
?>

<div class="container-fluid">
    <!-- Header with back button and export options -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="index.php" class="btn btn-outline-secondary mb-2">
                <i class="bi bi-arrow-left"></i> Back to Reports
            </a>
            <h1 class="mb-0"><i class="bi bi-clock-history"></i> Acquisition History</h1>
            <p class="text-muted">Complete history of all artwork acquisitions</p>
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
    
    <!-- Stats Summary -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6>Total Acquisitions</h6>
                    <h2><?= count($acquisitions) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6>Total Value</h6>
                    <h2>$<?= number_format(array_sum(array_column($acquisitions, 'price_value')), 2) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6>Average Price</h6>
                    <h2>$<?= number_format(array_sum(array_column($acquisitions, 'price_value')) / count($acquisitions), 2) ?></h2>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Data Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="acquisitionsTable">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Artist</th>
                            <th>Price</th>
                            <th>Acquisition Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($acquisitions as $acq): ?>
                            <tr>
                                <td><?= htmlspecialchars($acq['acquisition_id']) ?></td>
                                <td><?= htmlspecialchars($acq['title']) ?></td>
                                <td><?= htmlspecialchars($acq['first_name'] . ' ' . $acq['last_name']) ?></td>
                                <td>$<?= number_format($acq['price_value'], 2) ?></td>
                                <td><?= date('M d, Y', strtotime($acq['acquisition_date'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add DataTables for sorting/searching -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    $('#acquisitionsTable').DataTable({
        pageLength: 25,
        order: [[4, 'desc']] // Sort by date descending
    });
});
</script>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>