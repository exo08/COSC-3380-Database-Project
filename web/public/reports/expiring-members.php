<?php
session_start();
require_once __DIR__ . '/../app/db.php';

$page_title = 'Expiring Memberships';
$db = db();

$result = $db->query("CALL GetExpiringMembers()");
$members = $result->fetch_all(MYSQLI_ASSOC);
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
            <h1 class="mb-0"><i class="bi bi-alarm"></i> Expiring Memberships</h1>
            <p class="text-muted">Members whose memberships expire in the next 7 days (no auto-renew)</p>
        </div>
        <a href="?export=csv" class="btn btn-success">
            <i class="bi bi-file-earmark-spreadsheet"></i> Export for Email Campaign
        </a>
    </div>
    
    <?php if (empty($members)): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle"></i> No memberships expiring in the next 7 days!
        </div>
    <?php else: ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i> <strong><?= count($members) ?> member(s)</strong> need renewal reminders
        </div>
        
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Member ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Membership Type</th>
                                <th>Days Until Expiration</th>
                                <th>Urgency</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($members as $member): ?>
                                <?php
                                $days_left = (new DateTime($member['expiration_date']))->diff(new DateTime())->days;
                                $urgency_class = $days_left <= 2 ? 'danger' : ($days_left <= 5 ? 'warning' : 'info');
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($member['member_id']) ?></td>
                                    <td><?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) ?></td>
                                    <td><a href="mailto:<?= htmlspecialchars($member['email']) ?>"><?= htmlspecialchars($member['email']) ?></a></td>
                                    <td>Type <?= htmlspecialchars($member['membership_type']) ?></td>
                                    <td><?= $days_left ?> day(s)</td>
                                    <td><span class="badge bg-<?= $urgency_class ?>"><?= $days_left <= 2 ? 'URGENT' : 'Reminder Needed' ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>