<?php
session_start();
require_once __DIR__ . '/../app/db.php';

$page_title = 'Current & Upcoming Exhibitions';
$db = db();

$result = $db->query("CALL GetCurrentAndUpcomingExhibitions()");
$exhibitions = $result->fetch_all(MYSQLI_ASSOC);
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
            <h1 class="mb-0"><i class="bi bi-building"></i> Current & Upcoming Exhibitions</h1>
        </div>
        <button onclick="window.print()" class="btn btn-primary">
            <i class="bi bi-printer"></i> Print
        </button>
    </div>
    
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Exhibition ID</th>
                            <th>Title</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Status</th>
                            <th>Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($exhibitions as $exhibition): ?>
                            <?php
                            $start = new DateTime($exhibition['start_date']);
                            $end = new DateTime($exhibition['end_date']);
                            $today = new DateTime();
                            
                            $status = 'Upcoming';
                            $badge_class = 'bg-info';
                            if ($start <= $today && $end >= $today) {
                                $status = 'Current';
                                $badge_class = 'bg-success';
                            }
                            
                            $duration = $start->diff($end)->days;
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($exhibition['exhibition_id']) ?></td>
                                <td><strong><?= htmlspecialchars($exhibition['title']) ?></strong></td>
                                <td><?= $start->format('M d, Y') ?></td>
                                <td><?= $end->format('M d, Y') ?></td>
                                <td><span class="badge <?= $badge_class ?>"><?= $status ?></span></td>
                                <td><?= $duration ?> days</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>