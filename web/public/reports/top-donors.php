<?php
session_start();
require_once __DIR__ . '/../app/db.php';

$page_title = 'Top Donors Report';
$db = db();

// Call the stored procedure
$result = $db->query("CALL GetTopDonors()");
$donors = $result->fetch_all(MYSQLI_ASSOC);
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
            <h1 class="mb-0"><i class="bi bi-trophy"></i> Top 10 Donors</h1>
        </div>
        <div>
            <a href="?export=csv" class="btn btn-success">
                <i class="bi bi-file-earmark-spreadsheet"></i> Export to CSV
            </a>
        </div>
    </div>
    
    <div class="card">
        <div class="card-body">
            <table class="table table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>Rank</th>
                        <th>Donor Name</th>
                        <th>Type</th>
                        <th>Total Donated</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $rank = 1; foreach ($donors as $donor): ?>
                        <tr>
                            <td>
                                <?php if ($rank <= 3): ?>
                                    <span class="badge bg-warning text-dark">#<?= $rank ?></span>
                                <?php else: ?>
                                    <?= $rank ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($donor['is_organization']): ?>
                                    <i class="bi bi-building"></i> <?= htmlspecialchars($donor['organization_name']) ?>
                                <?php else: ?>
                                    <i class="bi bi-person"></i> <?= htmlspecialchars($donor['first_name'] . ' ' . $donor['last_name']) ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($donor['is_organization']): ?>
                                    <span class="badge bg-primary">Organization</span>
                                <?php else: ?>
                                    <span class="badge bg-info">Individual</span>
                                <?php endif; ?>
                            </td>
                            <td class="fw-bold">$<?= number_format($donor['total'], 2) ?></td>
                        </tr>
                    <?php $rank++; endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>