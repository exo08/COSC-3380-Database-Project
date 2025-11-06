<?php
session_start();
require_once __DIR__ . '/../app/db.php';

$page_title = 'Demographics Report';
$db = db();

$result = $db->query("CALL GetDemographics()");
$demographics = $result->fetch_all(MYSQLI_ASSOC);
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
            <h1 class="mb-0"><i class="bi bi-pie-chart"></i> Demographics Report</h1>
            <p class="text-muted">Visitor and member demographics breakdown</p>
        </div>
    </div>
    
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Customer Type</th>
                            <th>Student Status</th>
                            <th>Total Tickets</th>
                            <th>Total Attendees</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($demographics as $demo): ?>
                            <tr>
                                <td>
                                    <?php if ($demo['customer_type'] === 'Member'): ?>
                                        <span class="badge bg-primary"><?= htmlspecialchars($demo['customer_type']) ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($demo['customer_type']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($demo['is_student']): ?>
                                        <i class="bi bi-mortarboard"></i> Student
                                    <?php else: ?>
                                        <i class="bi bi-person"></i> Regular
                                    <?php endif; ?>
                                </td>
                                <td><?= number_format($demo['total_tickets']) ?></td>
                                <td><?= number_format($demo['total_attendees']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Chart -->
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0">Visual Breakdown</h5>
        </div>
        <div class="card-body">
            <canvas id="demoChart" height="80"></canvas>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('demoChart').getContext('2d');
const chart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_map(function($d) { 
            return $d['customer_type'] . ' - ' . ($d['is_student'] ? 'Student' : 'Regular'); 
        }, $demographics)) ?>,
        datasets: [{
            label: 'Total Attendees',
            data: <?= json_encode(array_column($demographics, 'total_attendees')) ?>,
            backgroundColor: [
                'rgba(13, 110, 253, 0.7)',
                'rgba(13, 110, 253, 0.4)',
                'rgba(108, 117, 125, 0.7)',
                'rgba(108, 117, 125, 0.4)'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false
    }
});
</script>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>