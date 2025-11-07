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

$page_title = 'Exhibition Attendance';
$db = db();

$attendance = [];
$search_performed = false;

// Get all exhibitions for dropdown
$exhibitions_result = $db->query("SELECT exhibition_id, title FROM EXHIBITION ORDER BY start_date DESC");
$exhibitions = $exhibitions_result->fetch_all(MYSQLI_ASSOC);

if (isset($_POST['exhibition_title'])) {
    $title = $_POST['exhibition_title'];
    
    $stmt = $db->prepare("CALL GetExhibitionAttendance(?)");
    $stmt->bind_param("s", $title);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $attendance = $result->fetch_assoc();
    $stmt->close();
    $db->next_result();
    
    $search_performed = true;
}

include __DIR__ . '/../templates/layout_header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="index.php" class="btn btn-outline-secondary mb-2">
                <i class="bi bi-arrow-left"></i> Back to Reports
            </a>
            <h1 class="mb-0"><i class="bi bi-people-fill"></i> Exhibition Attendance</h1>
            <p class="text-muted">View ticket sales and attendance by exhibition</p>
        </div>
    </div>
    
    <div class="card mb-4">
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-8">
                        <label class="form-label">Select Exhibition</label>
                        <select name="exhibition_title" class="form-select" required>
                            <option value="">Choose an exhibition...</option>
                            <?php foreach ($exhibitions as $exhibition): ?>
                                <option value="<?= htmlspecialchars($exhibition['title']) ?>"
                                    <?= (isset($_POST['exhibition_title']) && $_POST['exhibition_title'] == $exhibition['title']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($exhibition['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i> Get Attendance
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <?php if ($search_performed): ?>
        <?php if (empty($attendance) || $attendance['Total_tickets_sold'] === null): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i> No attendance data found for this exhibition.
            </div>
        <?php else: ?>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <h6 class="text-uppercase">Total Tickets Sold</h6>
                            <h2><?= number_format($attendance['Total_tickets_sold']) ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h6 class="text-uppercase">Checked In</h6>
                            <h2><?= number_format($attendance['Total_checked_in']) ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-warning text-white">
                        <div class="card-body text-center">
                            <h6 class="text-uppercase">No-Shows</h6>
                            <h2><?= number_format($attendance['Total_tickets_sold'] - $attendance['Total_checked_in']) ?></h2>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php 
            $attendance_rate = ($attendance['Total_tickets_sold'] > 0) 
                ? ($attendance['Total_checked_in'] / $attendance['Total_tickets_sold']) * 100 
                : 0;
            ?>
            
            <div class="card mt-4">
                <div class="card-body">
                    <h5>Attendance Rate</h5>
                    <div class="progress" style="height: 30px;">
                        <div class="progress-bar bg-success" role="progressbar" 
                             style="width: <?= $attendance_rate ?>%">
                            <?= number_format($attendance_rate, 1) ?>%
                        </div>
                    </div>
                    <p class="text-muted mt-2">
                        <?= number_format($attendance['Total_checked_in']) ?> out of 
                        <?= number_format($attendance['Total_tickets_sold']) ?> ticket holders attended
                    </p>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>