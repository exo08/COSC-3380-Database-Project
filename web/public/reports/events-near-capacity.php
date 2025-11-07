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

$page_title = 'Events Near Capacity';
$db = db();


$result = $db->query("CALL GetEventsNearCapacity()");
$events = $result->fetch_all(MYSQLI_ASSOC);
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
            <h1 class="mb-0"><i class="bi bi-exclamation-circle text-warning"></i> Events Near Capacity</h1>
            <p class="text-muted">Events within 10 tickets of selling out</p>
        </div>
    </div>
    
    <?php if (empty($events)): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> No events are currently near capacity.
        </div>
    <?php else: ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i> <strong><?= count($events) ?> event(s)</strong> are nearly sold out!
        </div>
        
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Event ID</th>
                                <th>Event Name</th>
                                <th>Tickets Sold</th>
                                <th>Capacity</th>
                                <th>Remaining</th>
                                <th>Fill Rate</th>
                                <th>Urgency</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($events as $event): ?>
                                <?php
                                $remaining = $event['capacity'] - $event['tickets_sold'];
                                $fill_rate = ($event['tickets_sold'] / $event['capacity']) * 100;
                                
                                if ($remaining <= 3) {
                                    $urgency = 'CRITICAL';
                                    $badge = 'danger';
                                } elseif ($remaining <= 7) {
                                    $urgency = 'HIGH';
                                    $badge = 'warning';
                                } else {
                                    $urgency = 'MEDIUM';
                                    $badge = 'info';
                                }
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($event['event_id']) ?></td>
                                    <td><strong><?= htmlspecialchars($event['name']) ?></strong></td>
                                    <td><?= number_format($event['tickets_sold']) ?></td>
                                    <td><?= number_format($event['capacity']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $badge ?>">
                                            <?= $remaining ?> left
                                        </span>
                                    </td>
                                    <td>
                                        <div class="progress" style="width: 100px; height: 20px;">
                                            <div class="progress-bar bg-<?= $badge ?>" role="progressbar" 
                                                 style="width: <?= $fill_rate ?>%">
                                                <?= number_format($fill_rate, 0) ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="badge bg-<?= $badge ?>"><?= $urgency ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-lightbulb"></i> Recommended Actions</h5>
            </div>
            <div class="card-body">
                <ul class="mb-0">
                    <li>Contact marketing team to promote events with more availability</li>
                    <li>Prepare waitlists for events marked as CRITICAL</li>
                    <li>Consider opening additional time slots for popular events</li>
                    <li>Send "last chance" emails to members for nearly full events</li>
                </ul>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>