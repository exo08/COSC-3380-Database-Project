<?php
session_start();
require_once __DIR__ . '/../app/db.php';

$page_title = 'Active Memberships';
$db = db();

$result = $db->query("CALL GetNumberActiveMemberships()");
$memberships = $result->fetch_all(MYSQLI_ASSOC);
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
            <h1 class="mb-0"><i class="bi bi-card-checklist"></i> Active Memberships</h1>
            <p class="text-muted">Breakdown by membership type and student status</p>
        </div>
    </div>
    
    <?php
    $total_members = array_sum(array_column($memberships, 'total_members'));
    ?>
    
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h3>Total Active Members: <?= number_format($total_members) ?></h3>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Membership Type</th>
                            <th>Student Status</th>
                            <th>Number of Members</th>
                            <th>Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($memberships as $membership): ?>
                            <?php 
                            $percentage = ($membership['total_members'] / $total_members) * 100;
                            ?>
                            <tr>
                                <td>Type <?= htmlspecialchars($membership['membership_type']) ?></td>
                                <td>
                                    <?php if ($membership['is_student']): ?>
                                        <span class="badge bg-info">Student</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Regular</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= number_format($membership['total_members']) ?></td>
                                <td>
                                    <div class="progress" style="height: 25px;">
                                        <div class="progress-bar" role="progressbar" 
                                             style="width: <?= $percentage ?>%">
                                            <?= number_format($percentage, 1) ?>%
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
</div>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>