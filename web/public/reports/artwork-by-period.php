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

$page_title = 'Artwork by Period';
$db = db();

$artworks = [];
$search_performed = false;

if (isset($_POST['lower_year']) && isset($_POST['upper_year'])) {
    $lower_year = $_POST['lower_year'];
    $upper_year = $_POST['upper_year'];
    
    $stmt = $db->prepare("CALL ArtworkByPeriod(?, ?)");
    $stmt->bind_param("ii", $lower_year, $upper_year);
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
            <h1 class="mb-0"><i class="bi bi-calendar-range"></i> Artwork by Period</h1>
            <p class="text-muted">Search artworks by creation year range</p>
        </div>
    </div>
    
    <div class="card mb-4">
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label">From Year</label>
                        <input type="number" name="lower_year" class="form-control" 
                               value="<?= htmlspecialchars($_POST['lower_year'] ?? '') ?>" 
                               placeholder="e.g., 1800" required min="1000" max="2100">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">To Year</label>
                        <input type="number" name="upper_year" class="form-control" 
                               value="<?= htmlspecialchars($_POST['upper_year'] ?? '') ?>" 
                               placeholder="e.g., 1900" required min="1000" max="2100">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i> Search
                        </button>
                    </div>
                </div>
                <small class="text-muted mt-2 d-block">
                    <strong>Examples:</strong> Renaissance (1400-1600), Impressionism (1860-1890), Modern (1900-2000)
                </small>
            </form>
        </div>
    </div>
    
    <?php if ($search_performed): ?>
        <?php if (empty($artworks)): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i> No artworks found for the period 
                <?= htmlspecialchars($_POST['lower_year']) ?> - <?= htmlspecialchars($_POST['upper_year']) ?>.
            </div>
        <?php else: ?>
            <div class="alert alert-success mb-4">
                Found <?= count($artworks) ?> artwork(s) from 
                <?= htmlspecialchars($_POST['lower_year']) ?> to <?= htmlspecialchars($_POST['upper_year']) ?>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Artwork ID</th>
                                    <th>Year</th>
                                    <th>Title</th>
                                    <th>Artist</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($artworks as $artwork): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($artwork['artwork_id']) ?></td>
                                        <td><strong><?= htmlspecialchars($artwork['creation_year']) ?></strong></td>
                                        <td><?= htmlspecialchars($artwork['title']) ?></td>
                                        <td>
                                            <?php if ($artwork['first_name']): ?>
                                                <?= htmlspecialchars($artwork['first_name'] . ' ' . $artwork['last_name']) ?>
                                            <?php else: ?>
                                                <em class="text-muted">Unknown Artist</em>
                                            <?php endif; ?>
                                        </td>
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