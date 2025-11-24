<?php
session_start();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/permissions.php';

$report_name = basename(__FILE__, '.php'); 
if (!hasReportAccess($report_name)) {
    header('Location: index.php?error=access_denied');
    exit;
}

$page_title = 'Visitor Sales Report';
$db = db();

$result = $db->query("CALL GetVisitorSales()");
$sales = $result->fetch_all(MYSQLI_ASSOC);
$result->close();
$db->next_result();

include __DIR__ . '/../templates/layout_header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="index.php" class="btn btn-outline-secondary mb-2">
                <i class="bi bi-arrow-left"></i> Back to Reports
            </a>
            <h1 class="mb-0"><i class="bi bi-people"></i> Visitor Sales Report</h1>
            <p class="text-muted">Total spending by non-member visitors</p>
        </div>
        <button onclick="window.print()" class="btn btn-primary">
            <i class="bi bi-printer"></i> Print
        </button>
    </div>
    
    <?php if (empty($sales)): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> No visitor purchases recorded yet.
        </div>
    <?php else: ?>
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-secondary text-white">
                    <div class="card-body">
                        <h6>Total Visitors with Purchases</h6>
                        <h2><?= count($sales) ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h6>Total Visitor Spending</h6>
                        <h2>$<?= number_format(array_sum(array_column($sales, 'visitor_spending')), 2) ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h6>Average per Visitor</h6>
                        <h2>$<?= number_format(array_sum(array_column($sales, 'visitor_spending')) / count($sales), 2) ?></h2>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="salesTable">
                        <thead class="table-dark">
                            <tr>
                                <th>Visitor ID</th>
                                <th>Total Spending</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sales as $sale): ?>
                                <tr>
                                    <td><?= htmlspecialchars($sale['visitor_id']) ?></td>
                                    <td>$<?= number_format($sale['visitor_spending'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    $('#salesTable').DataTable({
        pageLength: 25,
        order: [[1, 'desc']]
    });
});
</script>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>