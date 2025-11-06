<?php
session_start();
require_once __DIR__ . '/../app/db.php';

$page_title = 'Event Attendance Report';
$db = db();

$attendance = [];
$search_performed = false;

// Get all events for dropdown
$events_result = $db->query("SELECT event_id, name, event_date FROM EVENT ORDER BY event_date DESC");
$events = $events_result->fetch_all(MYSQLI_ASSOC);

if (isset($_POST['event_id'])) {
    $event_id = $_POST['event_id'];
    
    $stmt = $db->prepare("CALL GetEventAttendance(?)");
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $attendance = $result->fetch_assoc();
    $stmt->close();
    $db->next_result();
    
    $search_performed = true;
}

include __DIR__ . '/../templates/layout_header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="index.php" class="btn btn-outline-secondary mb-2">
                <i class="bi bi-arrow-left"></i> Back to Reports
            </a>
            <h1 class="mb-0"><i class="bi bi-clipboard-check"></i> Event Attendance Report</h1>
            <p class="text-muted">Check-in status for individual events</p>
        </div>
    </div>
    
    <div class="card mb-4">
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-8">
                        <label class="form-label">Select Event</label>
                        <select name="event_id" class="form-select" required>
                            <option value="">Choose an event...</option>
                            <?php foreach ($events as $event): ?>
                                <option value="<?= $event['event_id'] ?>"
                                    <?= (isset($_POST['event_id']) && $_POST['event_id'] == $event['event_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($event['name']) ?> - <?= date('M d, Y', strtotime($event['event_date'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i> Get Attendance
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <?php if ($search_performed): ?>
        <?php if (empty($attendance) || ($attendance['present'] == 0 && $attendance['absent'] == 0)): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i> No attendance data found for this event. No tickets have been sold yet.
            </div>
        <?php else: ?>
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="bi bi-calendar-event"></i> <?= htmlspecialchars($attendance['name']) ?></h4>
                </div>
            </div>
            
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <h6 class="text-uppercase">Total Tickets</h6>
                            <h2><?= number_format($attendance['present'] + $attendance['absent']) ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h6 class="text-uppercase">Checked In (Present)</h6>
                            <h2><?= number_format($attendance['present']) ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-warning text-white">
                        <div class="card-body text-center">
                            <h6 class="text-uppercase">Not Checked In (Absent)</h6>
                            <h2><?= number_format($attendance['absent']) ?></h2>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php 
            $total = $attendance['present'] + $attendance['absent'];
            $attendance_rate = $total > 0 ? ($attendance['present'] / $total) * 100 : 0;
            $no_show_rate = $total > 0 ? ($attendance['absent'] / $total) * 100 : 0;
            ?>
            
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">Attendance Rate</h5>
                        </div>
                        <div class="card-body">
                            <div class="progress" style="height: 40px;">
                                <div class="progress-bar bg-success" role="progressbar" 
                                     style="width: <?= $attendance_rate ?>%">
                                    <strong><?= number_format($attendance_rate, 1) ?>%</strong>
                                </div>
                            </div>
                            <p class="text-center mt-3 mb-0">
                                <?= number_format($attendance['present']) ?> out of <?= number_format($total) ?> attendees checked in
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-warning text-white">
                            <h5 class="mb-0">No-Show Rate</h5>
                        </div>
                        <div class="card-body">
                            <div class="progress" style="height: 40px;">
                                <div class="progress-bar bg-warning" role="progressbar" 
                                     style="width: <?= $no_show_rate ?>%">
                                    <strong><?= number_format($no_show_rate, 1) ?>%</strong>
                                </div>
                            </div>
                            <p class="text-center mt-3 mb-0">
                                <?= number_format($attendance['absent']) ?> ticket holders did not attend
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Chart -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Attendance Breakdown</h5>
                </div>
                <div class="card-body">
                    <canvas id="attendanceChart" height="100"></canvas>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php if ($search_performed && !empty($attendance) && ($attendance['present'] > 0 || $attendance['absent'] > 0)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('attendanceChart').getContext('2d');
new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: ['Checked In', 'No-Show'],
        datasets: [{
            data: [<?= $attendance['present'] ?>, <?= $attendance['absent'] ?>],
            backgroundColor: [
                'rgba(25, 135, 84, 0.8)',
                'rgba(255, 193, 7, 0.8)'
            ],
            borderColor: [
                'rgba(25, 135, 84, 1)',
                'rgba(255, 193, 7, 1)'
            ],
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>