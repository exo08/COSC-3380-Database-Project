<?php
session_start();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/permissions.php';

$report_name = basename(__FILE__, '.php'); 
if (!hasReportAccess($report_name)) {
    header('Location: index.php?error=access_denied');
    exit;
}

$page_title = 'Upcoming Events';
$db = db();

// Call the stored procedure
$result = $db->query("CALL GetUpcomingEvents()");
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
            <h1 class="mb-0"><i class="bi bi-calendar-check"></i> Upcoming Events</h1>
            <p class="text-muted">All scheduled future events</p>
        </div>
        <div>
            <a href="?export=csv" class="btn btn-success">
                <i class="bi bi-file-earmark-spreadsheet"></i> Export to CSV
            </a>
        </div>
    </div>
    
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Event ID</th>
                            <th>Event Name</th>
                            <th>Date</th>
                            <th>Location</th>
                            <th>Capacity</th>
                            <th>Tickets Sold</th>
                            <th>Available</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $event): ?>
                            <?php 
                            $available = $event['capacity'] - $event['number_tickets_sold'];
                            $percentage = ($event['number_tickets_sold'] / $event['capacity']) * 100;
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($event['event_id']) ?></td>
                                <td><strong><?= htmlspecialchars($event['name']) ?></strong></td>
                                <td><?= date('M d, Y', strtotime($event['event_date'])) ?></td>
                                <td><?= htmlspecialchars($event['name']) ?></td>
                                <td><?= number_format($event['capacity']) ?></td>
                                <td><?= number_format($event['number_tickets_sold']) ?></td>
                                <td><?= number_format($available) ?></td>
                                <td>
                                    <?php if ($percentage >= 90): ?>
                                        <span class="badge bg-danger">Almost Full</span>
                                    <?php elseif ($percentage >= 75): ?>
                                        <span class="badge bg-warning">Filling Up</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Available</span>
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

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>