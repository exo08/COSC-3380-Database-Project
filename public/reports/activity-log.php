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
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$user_filter = isset($_GET['user_id']) && $_GET['user_id'] !== '' ? (int)$_GET['user_id'] : null;
$action_filter = $_GET['action_type'] ?? '';
$table_filter = $_GET['table_name'] ?? '';
$username_search = $_GET['username_search'] ?? '';

// Get all users for filter dropdown
$users_result = $db->query("SELECT staff_id, name, email FROM STAFF ORDER BY name");
$users = $users_result->fetch_all(MYSQLI_ASSOC);

// Get unique action types
$action_types_result = $db->query("SELECT DISTINCT action_type FROM ACTIVITY_LOG ORDER BY action_type");
$action_types = $action_types_result->fetch_all(MYSQLI_ASSOC);

// Get unique table names
$table_names_result = $db->query("SELECT DISTINCT table_name FROM ACTIVITY_LOG WHERE table_name IS NOT NULL ORDER BY table_name");
$table_names = $table_names_result->fetch_all(MYSQLI_ASSOC);

// Build WHERE clause
$where_conditions = ["DATE(timestamp) BETWEEN ? AND ?"];
$params = [$date_from, $date_to];
$types = "ss";

if ($user_filter !== null) {
    $where_conditions[] = "AL.user_id = ?";
    $params[] = $user_filter;
    $types .= "i";
}

if ($action_filter !== '') {
    $where_conditions[] = "AL.action_type = ?";
    $params[] = $action_filter;
    $types .= "s";
}

if ($table_filter !== '') {
    $where_conditions[] = "AL.table_name = ?";
    $params[] = $table_filter;
    $types .= "s";
}

if ($username_search !== '') {
    $where_conditions[] = "(S.name LIKE ? OR UA.username LIKE ?)";
    $search_param = "%{$username_search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

$where_clause = implode(" AND ", $where_conditions);

// Get activities with filters
$query = "
    SELECT 
        AL.log_id,
        AL.timestamp,
        AL.action_type,
        AL.table_name,
        AL.record_id,
        AL.description,
        AL.ip_address,
        S.name AS staff_name,
        UA.username,
        UA.user_type
    FROM ACTIVITY_LOG AL
    LEFT JOIN USER_ACCOUNT UA ON AL.user_id = UA.user_id
    LEFT JOIN STAFF S ON UA.linked_id = S.staff_id
    WHERE $where_clause
    ORDER BY AL.timestamp DESC
    LIMIT 500
";

$stmt = $db->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$activities = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get summary statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_actions,
        COUNT(DISTINCT AL.user_id) as active_users,
        COUNT(DISTINCT AL.table_name) as affected_tables,
        AL.table_name as most_modified_table,
        COUNT(*) as modification_count
    FROM ACTIVITY_LOG AL
    WHERE $where_clause
    GROUP BY AL.table_name
    ORDER BY modification_count DESC
    LIMIT 1
";

$stmt = $db->prepare($stats_query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$stats_row = $result->fetch_assoc();
$stmt->close();

// Get total count for all filters
$count_query = "SELECT COUNT(*) as total FROM ACTIVITY_LOG AL 
                LEFT JOIN STAFF S ON AL.user_id IN (SELECT user_id FROM USER_ACCOUNT WHERE linked_id = S.staff_id)
                LEFT JOIN USER_ACCOUNT UA ON AL.user_id = UA.user_id
                WHERE $where_clause";
$stmt = $db->prepare($count_query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$total_count = $result->fetch_assoc()['total'];
$stmt->close();

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="activity-log-' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Timestamp', 'User', 'Username', 'Role', 'Action Type', 'Table', 'Record ID', 'Description', 'IP Address']);
    
    foreach ($activities as $activity) {
        fputcsv($output, [
            $activity['timestamp'],
            $activity['staff_name'] ?? 'Unknown User',
            $activity['username'] ?? 'N/A',
            $activity['user_type'] ?? 'N/A',
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

<style>
.stats-card {
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
    background: white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.stats-icon {
    font-size: 2.5rem;
    opacity: 0.2;
}

.filter-card {
    background: white;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.badge-action {
    padding: 0.35em 0.65em;
    font-size: 0.875rem;
}
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="index.php" class="btn btn-outline-secondary mb-2">
                <i class="bi bi-arrow-left"></i> Back to Reports
            </a>
            <h1 class="mb-0"><i class="bi bi-shield-check text-danger"></i> Activity Log</h1>
            <p class="text-muted">System activity tracking and audit trail</p>
        </div>
        <div>
            <a href="?export=csv&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?><?= $user_filter ? '&user_id='.$user_filter : '' ?><?= $action_filter ? '&action_type='.$action_filter : '' ?><?= $table_filter ? '&table_name='.$table_filter : '' ?><?= $username_search ? '&username_search='.$username_search : '' ?>" class="btn btn-success">
                <i class="bi bi-file-earmark-spreadsheet"></i> Export to CSV
            </a>
        </div>
    </div>
    
    <!-- Summary Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stats-card bg-primary text-white position-relative overflow-hidden">
                <i class="bi bi-activity stats-icon position-absolute" style="right: 10px; top: 10px;"></i>
                <h6 class="text-uppercase mb-1 opacity-75">Total Actions</h6>
                <h2 class="mb-0"><?= number_format($stats_row['total_actions'] ?? 0) ?></h2>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="stats-card bg-success text-white position-relative overflow-hidden">
                <i class="bi bi-people stats-icon position-absolute" style="right: 10px; top: 10px;"></i>
                <h6 class="text-uppercase mb-1 opacity-75">Active Users</h6>
                <h2 class="mb-0"><?= number_format($stats_row['active_users'] ?? 0) ?></h2>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="stats-card bg-warning text-white position-relative overflow-hidden">
                <i class="bi bi-table stats-icon position-absolute" style="right: 10px; top: 10px;"></i>
                <h6 class="text-uppercase mb-1 opacity-75">Affected Tables</h6>
                <h2 class="mb-0"><?= number_format($stats_row['affected_tables'] ?? 0) ?></h2>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="stats-card bg-info text-white position-relative overflow-hidden">
                <i class="bi bi-pencil-square stats-icon position-absolute" style="right: 10px; top: 10px;"></i>
                <h6 class="text-uppercase mb-1 opacity-75">Most Modified</h6>
                <h2 class="mb-0 text-truncate" style="font-size: 1.5rem;"><?= htmlspecialchars($stats_row['most_modified_table'] ?? 'N/A') ?></h2>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="filter-card">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label fw-bold">Date From</label>
                <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>">
            </div>
            
            <div class="col-md-3">
                <label class="form-label fw-bold">Date To</label>
                <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>">
            </div>
            
            <div class="col-md-3">
                <label class="form-label fw-bold">Search Username</label>
                <input type="text" name="username_search" class="form-control" placeholder="Search by name or username..." value="<?= htmlspecialchars($username_search) ?>">
            </div>
            
            <div class="col-md-3">
                <label class="form-label fw-bold">Filter by User</label>
                <select name="user_id" class="form-select">
                    <option value="">All Users</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?= $user['staff_id'] ?>" 
                            <?= ($user_filter == $user['staff_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($user['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label class="form-label fw-bold">Action Type</label>
                <select name="action_type" class="form-select">
                    <option value="">All Actions</option>
                    <?php foreach ($action_types as $type): ?>
                        <option value="<?= htmlspecialchars($type['action_type']) ?>" 
                            <?= ($action_filter == $type['action_type']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($type['action_type']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label class="form-label fw-bold">Table Name</label>
                <select name="table_name" class="form-select">
                    <option value="">All Tables</option>
                    <?php foreach ($table_names as $table): ?>
                        <option value="<?= htmlspecialchars($table['table_name']) ?>" 
                            <?= ($table_filter == $table['table_name']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($table['table_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-6 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-funnel"></i> Apply Filters
                </button>
                <a href="activity-log.php" class="btn btn-outline-secondary">
                    <i class="bi bi-x-circle"></i> Clear
                </a>
            </div>
        </form>
    </div>
    
    <!-- Results Info -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-muted mb-0">
            Showing <strong><?= number_format(count($activities)) ?></strong> of <strong><?= number_format($total_count) ?></strong> actions
        </p>
    </div>
    
    <!-- Activities Table -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($activities)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> No activities found matching your filters.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Timestamp</th>
                                <th>User</th>
                                <th>Username</th>
                                <th>Role</th>
                                <th>Action</th>
                                <th>Table</th>
                                <th>Record ID</th>
                                <th>Description</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activities as $activity): ?>
                                <tr>
                                    <td class="small text-nowrap">
                                        <?= date('M j, Y', strtotime($activity['timestamp'])) ?><br>
                                        <span class="text-muted"><?= date('g:i A', strtotime($activity['timestamp'])) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($activity['staff_name'] ?? 'Unknown User') ?></td>
                                    <td class="small text-muted"><?= htmlspecialchars($activity['username'] ?? 'N/A') ?></td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?= htmlspecialchars($activity['user_type'] ?? 'N/A') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $badge_class = 'secondary';
                                        if ($activity['action_type'] == 'INSERT') $badge_class = 'success';
                                        elseif ($activity['action_type'] == 'UPDATE') $badge_class = 'primary';
                                        elseif ($activity['action_type'] == 'DELETE') $badge_class = 'danger';
                                        elseif ($activity['action_type'] == 'LOGIN') $badge_class = 'info';
                                        ?>
                                        <span class="badge bg-<?= $badge_class ?> badge-action">
                                            <?= htmlspecialchars($activity['action_type']) ?>
                                        </span>
                                    </td>
                                    <td class="small font-monospace"><?= htmlspecialchars($activity['table_name'] ?? '—') ?></td>
                                    <td class="small"><?= htmlspecialchars($activity['record_id'] ?? '—') ?></td>
                                    <td class="small">
                                        <span class="text-muted text-truncate d-inline-block" style="max-width: 200px;" title="<?= htmlspecialchars($activity['description'] ?? '') ?>">
                                            <?= htmlspecialchars($activity['description'] ?? '—') ?>
                                        </span>
                                    </td>
                                    <td class="small text-muted"><?= htmlspecialchars($activity['ip_address']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>