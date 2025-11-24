<?php
session_start();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/permissions.php';

$page_title = 'Exhibition Timeline';

// Only admin and curator can access
if ($_SESSION['user_type'] !== 'admin' && $_SESSION['user_type'] !== 'curator') {
    header('Location: index.php?error=access_denied');
    exit;
}

$db = db();

$result = $db->query("CALL GetExhibitionTimeline()");
$exhibitions = $result->fetch_all(MYSQLI_ASSOC);
$result->close();
$db->next_result();

// Categorize exhibitions
$past = [];
$current = [];
$upcoming = [];
$today = new DateTime();

foreach ($exhibitions as $exhibition) {
    $start = new DateTime($exhibition['start_date']);
    $end = new DateTime($exhibition['end_date']);
    
    if ($end < $today) {
        $past[] = $exhibition;
    } elseif ($start <= $today && $end >= $today) {
        $current[] = $exhibition;
    } else {
        $upcoming[] = $exhibition;
    }
}

include __DIR__ . '/../templates/layout_header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="index.php" class="btn btn-outline-secondary mb-2">
                <i class="bi bi-arrow-left"></i> Back to Reports
            </a>
            <h1 class="mb-0"><i class="bi bi-calendar3"></i> Exhibition Timeline</h1>
            <p class="text-muted">Complete chronological history of all exhibitions</p>
        </div>
        <button onclick="window.print()" class="btn btn-primary">
            <i class="bi bi-printer"></i> Print
        </button>
    </div>
    
    <!-- Summary Stats -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h6>Total Exhibitions</h6>
                    <h2><?= count($exhibitions) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h6>Current</h6>
                    <h2><?= count($current) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h6>Upcoming</h6>
                    <h2><?= count($upcoming) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-secondary text-white">
                <div class="card-body text-center">
                    <h6>Past</h6>
                    <h2><?= count($past) ?></h2>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Current Exhibitions -->
    <?php if (!empty($current)): ?>
    <div class="card mb-4">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0"><i class="bi bi-star"></i> Current Exhibitions</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Exhibition ID</th>
                            <th>Title</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Days Remaining</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($current as $exhibition): ?>
                            <?php
                            $end = new DateTime($exhibition['end_date']);
                            $days_remaining = $today->diff($end)->days;
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($exhibition['exhibition_id']) ?></td>
                                <td><strong><?= htmlspecialchars($exhibition['title']) ?></strong></td>
                                <td><?= date('M d, Y', strtotime($exhibition['start_date'])) ?></td>
                                <td><?= date('M d, Y', strtotime($exhibition['end_date'])) ?></td>
                                <td><span class="badge bg-warning"><?= $days_remaining ?> days</span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Upcoming Exhibitions -->
    <?php if (!empty($upcoming)): ?>
    <div class="card mb-4">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0"><i class="bi bi-calendar-plus"></i> Upcoming Exhibitions</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Exhibition ID</th>
                            <th>Title</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Starts In</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcoming as $exhibition): ?>
                            <?php
                            $start = new DateTime($exhibition['start_date']);
                            $days_until = $today->diff($start)->days;
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($exhibition['exhibition_id']) ?></td>
                                <td><strong><?= htmlspecialchars($exhibition['title']) ?></strong></td>
                                <td><?= date('M d, Y', strtotime($exhibition['start_date'])) ?></td>
                                <td><?= date('M d, Y', strtotime($exhibition['end_date'])) ?></td>
                                <td><span class="badge bg-primary"><?= $days_until ?> days</span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Past Exhibitions -->
    <?php if (!empty($past)): ?>
    <div class="card mb-4">
        <div class="card-header bg-secondary text-white">
            <h5 class="mb-0"><i class="bi bi-archive"></i> Past Exhibitions</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped mb-0" id="pastTable">
                    <thead class="table-dark">
                        <tr>
                            <th>Exhibition ID</th>
                            <th>Title</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($past as $exhibition): ?>
                            <?php
                            $start = new DateTime($exhibition['start_date']);
                            $end = new DateTime($exhibition['end_date']);
                            $duration = $start->diff($end)->days;
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($exhibition['exhibition_id']) ?></td>
                                <td><?= htmlspecialchars($exhibition['title']) ?></td>
                                <td><?= date('M d, Y', strtotime($exhibition['start_date'])) ?></td>
                                <td><?= date('M d, Y', strtotime($exhibition['end_date'])) ?></td>
                                <td><?= $duration ?> days</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (empty($exhibitions)): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> No exhibitions found in the system.
        </div>
    <?php endif; ?>
</div>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    $('#pastTable').DataTable({
        pageLength: 25,
        order: [[2, 'desc']] // Sort by start date descending (most recent first)
    });
});
</script>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>