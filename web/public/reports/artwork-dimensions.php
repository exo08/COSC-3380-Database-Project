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

$page_title = 'Artwork Dimensions';
$db = db();

$artworks = [];
$search_performed = false;

if (isset($_POST['title'])) {
    $title = $_POST['title'];
    
    $stmt = $db->prepare("CALL GetDimensions(?)");
    $stmt->bind_param("s", $title);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $artworks = $result->fetch_all(MYSQLI_ASSOC);
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
            <h1 class="mb-0"><i class="bi bi-rulers"></i> Artwork Dimensions</h1>
            <p class="text-muted">View physical dimensions of artworks</p>
        </div>
    </div>
    
    <div class="card mb-4">
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-8">
                        <label class="form-label">Search by Title</label>
                        <input type="text" name="title" class="form-control" 
                               value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" 
                               placeholder="Enter artwork title" required>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i> Search
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <?php if ($search_performed): ?>
        <?php if (empty($artworks)): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i> No artworks found matching "<?= htmlspecialchars($_POST['title']) ?>".
            </div>
        <?php else: ?>
            <div class="alert alert-success mb-4">
                Found <?= count($artworks) ?> artwork(s) matching your search
            </div>
            
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Artwork ID</th>
                                    <th>Title</th>
                                    <th>Height (cm)</th>
                                    <th>Width (cm)</th>
                                    <th>Depth (cm)</th>
                                    <th>Volume (cm³)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($artworks as $artwork): ?>
                                    <?php
                                    $volume = null;
                                    if ($artwork['height'] && $artwork['width'] && $artwork['depth']) {
                                        $volume = $artwork['height'] * $artwork['width'] * $artwork['depth'];
                                    }
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($artwork['artwork_id']) ?></td>
                                        <td><strong><?= htmlspecialchars($artwork['title']) ?></strong></td>
                                        <td><?= $artwork['height'] ? number_format($artwork['height'], 2) : '<em class="text-muted">N/A</em>' ?></td>
                                        <td><?= $artwork['width'] ? number_format($artwork['width'], 2) : '<em class="text-muted">N/A</em>' ?></td>
                                        <td><?= $artwork['depth'] ? number_format($artwork['depth'], 2) : '<em class="text-muted">N/A</em>' ?></td>
                                        <td><?= $volume ? number_format($volume, 2) : '<em class="text-muted">N/A</em>' ?></td>
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