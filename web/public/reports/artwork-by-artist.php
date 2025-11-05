<?php
session_start();
require_once __DIR__ . '/../app/db.php';

$page_title = 'Artwork by Artist';
$db = db();

$artworks = [];
$search_performed = false;

// Handle form submission
if (isset($_POST['first_name']) || isset($_POST['last_name'])) {
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    
    // Call stored procedure with parameters
    $stmt = $db->prepare("CALL ArtworkByArtist(?, ?)");
    $stmt->bind_param("ss", $first_name, $last_name);
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
            <h1 class="mb-0"><i class="bi bi-person"></i> Artwork by Artist</h1>
            <p class="text-muted">Search for artworks by artist name</p>
        </div>
    </div>
    
    <!-- Search Form -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-5">
                        <label class="form-label">First Name</label>
                        <input type="text" name="first_name" class="form-control" 
                               value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" 
                               placeholder="Enter first name" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Last Name</label>
                        <input type="text" name="last_name" class="form-control" 
                               value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" 
                               placeholder="Enter last name" required>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i> Search
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Results -->
    <?php if ($search_performed): ?>
        <?php if (empty($artworks)): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i> No artworks found for this artist.
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between">
                    <h5 class="mb-0">Found <?= count($artworks) ?> artwork(s)</h5>
                    <button onclick="window.print()" class="btn btn-light btn-sm">
                        <i class="bi bi-printer"></i> Print
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Artist ID</th>
                                    <th>Artwork ID</th>
                                    <th>Title</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($artworks as $artwork): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($artwork['artist_id']) ?></td>
                                        <td><?= htmlspecialchars($artwork['artwork_id']) ?></td>
                                        <td><?= htmlspecialchars($artwork['title']) ?></td>
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