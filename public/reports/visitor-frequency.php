<?php
session_start();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/permissions.php';

$report_name = basename(__FILE__, '.php'); 
if (!hasReportAccess($report_name)) {
    header('Location: index.php?error=access_denied');
    exit;
}

$page_title = 'Visitor Frequency Analysis';
$db = db();

// Call the stored procedure
$result = $db->query("CALL GetVisitorFrequencyAnalysis()");
$frequency_data = $result->fetch_all(MYSQLI_ASSOC);
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
            <h1 class="mb-0"><i class="bi bi-graph-up"></i> Visitor Frequency Analysis</h1>
            <p class="text-muted">Analyze how often visitors return to the museum</p>
        </div>
        <div>
            <button onclick="window.print()" class="btn btn-primary">
                <i class="bi bi-printer"></i> Print
            </button>
        </div>
    </div>
    
    <!-- Summary Cards -->
    <div class="row mb-4">
        <?php 
        $total_visitors = array_sum(array_column($frequency_data, 'number_of_visitors'));
        $repeat_visitors = 0;
        foreach ($frequency_data as $row) {
            if ($row['visit_frequency'] !== '1 visit') {
                $repeat_visitors += $row['number_of_visitors'];
            }
        }
        $repeat_percentage = $total_visitors > 0 ? ($repeat_visitors / $total_visitors) * 100 : 0;
        ?>
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6>Total Visitors</h6>
                    <h2><?= number_format($total_visitors) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6>Repeat Visitors (2+ visits)</h6>
                    <h2><?= number_format($repeat_visitors) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6>Repeat Visitor Rate</h6>
                    <h2><?= number_format($repeat_percentage, 1) ?>%</h2>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Data Table -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Visit Frequency</th>
                            <th>Number of Visitors</th>
                            <th>Percentage</th>
                            <th>Visual</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($frequency_data as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['visit_frequency']) ?></td>
                                <td><?= number_format($row['number_of_visitors']) ?></td>
                                <td><?= number_format($row['percentage'], 2) ?>%</td>
                                <td>
                                    <div class="progress" style="height: 25px;">
                                        <div class="progress-bar bg-primary" role="progressbar" 
                                             style="width: <?= $row['percentage'] ?>%">
                                            <?= number_format($row['percentage'], 1) ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Chart -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Visitor Frequency Distribution</h5>
        </div>
        <div class="card-body">
            <canvas id="frequencyChart" height="100"></canvas>
        </div>
    </div>
</div>

<!-- Add Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('frequencyChart').getContext('2d');
const chart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($frequency_data, 'visit_frequency')) ?>,
        datasets: [{
            label: 'Number of Visitors',
            data: <?= json_encode(array_column($frequency_data, 'number_of_visitors')) ?>,
            backgroundColor: 'rgba(13, 110, 253, 0.7)',
            borderColor: 'rgba(13, 110, 253, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
</script>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>