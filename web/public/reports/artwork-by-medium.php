<?php
session_start();
require_once __DIR__ . '/../app/db.php';

$page_title = 'Artwork by Medium';
$db = db();

$artworks = [];
$search_performed = false;

if (isset($_POST['medium'])) {
    $medium = $_POST['medium'];
    
    $stmt = $db->prepare("CALL ArtworkByMedium(?)");
    $stmt->bind_param("i", $medium);
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
            <h1 class="mb-0"><i class="bi bi-brush"></i> Artwork by Medium</h1>
            <p class="text-muted">Search artworks by their medium type</p>
        </div>
    </div>
    
    <div class="card mb-4">
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-8">
                        <label class="form-label">Medium Type</label>
                        <select name="medium" class="form-select" required>
                            <option value="">Select a medium...</option>
                            <option value="1">Oil Painting</option>
                            <option value="2">Watercolor</option>
                            <option value="3">Sculpture</option>
                            <option value="4">Photography</option>
                            <option value="5">Digital Art</option>
                            <option value="6">Mixed Media</option>
                        </select>
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
                <i class="bi bi-exclamation-triangle"></i> No artworks found for this medium.
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Found <?= count($artworks) ?> artwork(s)</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Artwork ID</th>
                                    <th>Title</th>
                                    <th>Artist</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($artworks as $artwork): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($artwork['artwork_id']) ?></td>
                                        <td><?= htmlspecialchars($artwork['title']) ?></td>
                                        <td><?= htmlspecialchars($artwork['first_name'] . ' ' . $artwork['last_name']) ?></td>
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