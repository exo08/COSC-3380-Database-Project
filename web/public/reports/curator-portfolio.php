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

$page_title = 'Curator Portfolio';
$db = db();

$exhibitions = [];
$search_performed = false;

// Get all curators for dropdown
$curators_result = $db->query("SELECT staff_id, name FROM STAFF WHERE title LIKE '%curator%' OR title LIKE '%Curator%' ORDER BY name");
$curators = $curators_result->fetch_all(MYSQLI_ASSOC);

if (isset($_POST['curator_id'])) {
    $curator_id = $_POST['curator_id'];
    
    $stmt = $db->prepare("CALL GetCuratorPortfolio(?)");
    $stmt->bind_param("i", $curator_id);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $exhibitions = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $db->next_result();
    
    $search_performed = true;
    
    // Get curator name
    $curator_stmt = $db->prepare("SELECT name FROM STAFF WHERE staff_id = ?");
    $curator_stmt->bind_param("i", $curator_id);
    $curator_stmt->execute();
    $curator_result = $curator_stmt->get_result();
    $curator_name = $curator_result->fetch_assoc()['name'] ?? 'Unknown';
    $curator_stmt->close();
}

include __DIR__ . '/../templates/layout_header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="index.php" class="btn btn-outline-secondary mb-2">
                <i class="bi bi-arrow-left"></i> Back to Reports
            </a>
            <h1 class="mb-0"><i class="bi bi-briefcase"></i> Curator Portfolio</h1>
            <p class="text-muted">View all exhibitions organized by a curator</p>
        </div>
    </div>
    
    <div class="card mb-4">
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-8">
                        <label class="form-label">Select Curator</label>
                        <select name="curator_id" class="form-select" required>
                            <option value="">Choose a curator...</option>
                            <?php foreach ($curators as $curator): ?>
                                <option value="<?= $curator['staff_id'] ?>"
                                    <?= (isset($_POST['curator_id']) && $_POST['curator_id'] == $curator['staff_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($curator['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i> View Portfolio
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <?php if ($search_performed): ?>
        <?php if (empty($exhibitions)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> <?= htmlspecialchars($curator_name) ?> has not curated any exhibitions yet.
            </div>
        <?php else: ?>
            <div class="alert alert-success mb-4">
                <h5 class="mb-0">
                    <i class="bi bi-person-circle"></i> 
                    <?= htmlspecialchars($curator_name) ?> - 
                    <?= count($exhibitions) ?> Exhibition(s)
                </h5>
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
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($exhibitions as $exhibition): ?>
                                    <?php
                                    $start = new DateTime($exhibition['start_date']);
                                    $end = new DateTime($exhibition['end_date']);
                                    $today = new DateTime();
                                    
                                    if ($end < $today) {
                                        $status = 'Past';
                                        $badge = 'secondary';
                                    } elseif ($start <= $today && $end >= $today) {
                                        $status = 'Current';
                                        $badge = 'success';
                                    } else {
                                        $status = 'Upcoming';
                                        $badge = 'primary';
                                    }
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($exhibition['exhibition_id']) ?></td>
                                        <td><strong><?= htmlspecialchars($exhibition['title']) ?></strong></td>
                                        <td><?= $start->format('M d, Y') ?></td>
                                        <td><?= $end->format('M d, Y') ?></td>
                                        <td><span class="badge bg-<?= $badge ?>"><?= $status ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>