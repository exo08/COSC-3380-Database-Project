<?php
session_start();
require_once __DIR__ . '/../app/db.php';

$page_title = 'Unlocated Artworks';
$db = db();

$result = $db->query("CALL GetUnlocatedArtworks()");
$artworks = $result->fetch_all(MYSQLI_ASSOC);
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
            <h1 class="mb-0"><i class="bi bi-exclamation-triangle text-warning"></i> Unlocated Artworks</h1>
            <p class="text-muted">Artworks without assigned locations - requires immediate attention</p>
        </div>
    </div>
    
    <?php if (empty($artworks)): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle"></i> <strong>Great news!</strong> All artworks have assigned locations.
        </div>
    <?php else: ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i> <strong><?= count($artworks) ?> artwork(s)</strong> need location assignment
        </div>
        
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Artwork ID</th>
                                <th>Title</th>
                                <th>Artist</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($artworks as $artwork): ?>
                                <tr>
                                    <td><?= htmlspecialchars($artwork['artwork_id']) ?></td>
                                    <td><strong><?= htmlspecialchars($artwork['title']) ?></strong></td>
                                    <td>
                                        <?php if ($artwork['first_name']): ?>
                                            <?= htmlspecialchars($artwork['first_name'] . ' ' . $artwork['last_name']) ?>
                                        <?php else: ?>
                                            <em class="text-muted">Unknown Artist</em>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge bg-danger">No Location</span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>