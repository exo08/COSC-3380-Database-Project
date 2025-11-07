<?php
session_start();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/permissions.php';

$page_title = 'Activity Log';

// Only admin can access this report
if ($_SESSION['user_type'] !== 'admin') {
    header('Location: index.php?error=access_denied');
    exit;
}

$db = db();

// Get filter parameters
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
$user_filter = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;

// Get all users for filter dropdown
$users_result = $db->query("SELECT staff_id, name, email FROM STAFF ORDER BY name");
$users = $users_result->fetch_all(MYSQLI_ASSOC);

// Call stored procedure
if ($user_filter) {
    $stmt = $db->prepare("CALL GetActivityLog(?, ?)");
    $stmt->bind_param("ii", $limit, $user_filter);
    $stmt->execute();
    $result = $stmt->get_result();
    $activities = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $stmt = $db->prepare("CALL GetActivityLog(?, NULL)");
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $activities = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
$db->next_result();

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="activity-log-' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Timestamp', 'User', 'Action Type', 'Table', 'Record ID', 'Description', 'IP Address']);
    
    foreach ($activities as $activity) {
        fputcsv($output, [
            $activity['timestamp'],
            $activity['user_name'] ?? 'Unknown User',
            $activity['action_type'],
            $activity['table_name'] ?? '',
            $activity['record_id'] ?? '',
            $activity['description'] ?? '',
            $activity['ip_address']
        ]);
    }
    
    fclose($output);
    exit;
}

include __DIR__ . '/../templates/layout_header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="index.php" class="btn btn-outline-secondary mb-2">
                <i class="bi bi-arrow-left"></i> Back to Reports
            </a>
            <h1 class="mb-0"><i class="bi bi-shield-check text-danger"></i> Activity Log</h1>
            <p class="text-muted">System activity tracking and audit trail (Admin Only)</p>
        </div>
        <div>
            <a href="?export=csv&limit=<?= $limit ?><?= $user_filter ? '&user_id='.$user_filter : '' ?>" class="btn btn-success">
                <i class="bi bi-file-earmark-spreadsheet"></i> Export to CSV
            </a>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Filter by User</label>
                    <select name="user_id" class="form-select">
                        <option value="">All Users</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= $user['staff_id'] ?>" 
                                <?= ($user_filter == $user['staff_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['email']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Number of Records</label>
                    <select name="limit" class="form-select">
                        <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>Last 50</option>
                        <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>Last 100</option>
                        <option value="250" <?= $limit == 250 ? 'selected' : '' ?>>Last 250</option>
                        <option value="500" <?= $limit == 500 ? 'selected' : '' ?>>Last 500</option>
                        <option value="1000" <?= $limit == 1000 ? 'selected' : '' ?>>Last 1000</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <a href="activity-log.php" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-x-circle"></i> Clear Filters
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Stats -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Showing <strong><?= count($activities) ?></strong> activity records
                <?php if ($user_filter): ?>
                    for user: <strong><?= htmlspecialchars(array_filter($users, fn($u) => $u['staff_id'] == $user_filter)[0]['name'] ?? 'Unknown') ?></strong>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Activity Log Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm" id="activityTable">
                    <thead class="table-dark">
                        <tr>
                            <th>Timestamp</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Table</th>
                            <th>Record ID</th>
                            <th>Description</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($activities)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted">No activity records found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($activities as $activity): ?>
                                <?php
                                // Color code by action type
                                $badge_color = 'secondary';
                                if (strpos(strtolower($activity['action_type']), 'create') !== false || 
                                    strpos(strtolower($activity['action_type']), 'add') !== false) {
                                    $badge_color = 'success';
                                } elseif (strpos(strtolower($activity['action_type']), 'update') !== false || 
                                          strpos(strtolower($activity['action_type']), 'edit') !== false) {
                                    $badge_color = 'warning';
                                } elseif (strpos(strtolower($activity['action_type']), 'delete') !== false || 
                                          strpos(strtolower($activity['action_type']), 'remove') !== false) {
                                    $badge_color = 'danger';
                                } elseif (strpos(strtolower($activity['action_type']), 'login') !== false) {
                                    $badge_color = 'info';
                                }
                                ?>
                                <tr>
                                    <td>
                                        <small><?= date('M d, Y', strtotime($activity['timestamp'])) ?></small><br>
                                        <small class="text-muted"><?= date('g:i:s A', strtotime($activity['timestamp'])) ?></small>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($activity['user_name'] ?? 'System') ?></strong><br>
                                        <small class="text-muted"><?= htmlspecialchars($activity['user_email'] ?? '') ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $badge_color ?>">
                                            <?= htmlspecialchars($activity['action_type']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($activity['table_name']): ?>
                                            <code><?= htmlspecialchars($activity['table_name']) ?></code>
                                        <?php else: ?>
                                            <em class="text-muted">N/A</em>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $activity['record_id'] ? htmlspecialchars($activity['record_id']) : '<em class="text-muted">N/A</em>' ?></td>
                                    <td>
                                        <?php if ($activity['description']): ?>
                                            <small><?= htmlspecialchars($activity['description']) ?></small>
                                        <?php else: ?>
                                            <em class="text-muted">No description</em>
                                        <?php endif; ?>
                                    </td>
                                    <td><small><?= htmlspecialchars($activity['ip_address']) ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    $('#activityTable').DataTable({
        pageLength: 25,
        order: [[0, 'desc']], // Sort by timestamp descending
        columnDefs: [
            { orderable: false, targets: [5] } // Disable sorting on description column
        ]
    });
});
</script>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>