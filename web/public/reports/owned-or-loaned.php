<?php
session_start();
require_once __DIR__ . '/../app/db.php';

$page_title = 'Owned vs Loaned Artworks';
$db = db();

$artworks = [];
$search_performed = false;

if (isset($_POST['search']) || isset($_POST['title'])) {
    $title = $_POST['title'] ?? '';
    
    // If empty, pass NULL to get all artworks
    if (empty($title)) {
        $result = $db->query("CALL OwnedOrLoaned(NULL)");
    } else {
        $stmt = $db->prepare("CALL OwnedOrLoaned(?)");
        $stmt->bind_param("s", $title);
        $stmt->execute();
        $result = $stmt->get_result();
    }
    
    $artworks = $result->fetch_all(MYSQLI_ASSOC);
    if (isset($stmt)) $stmt->close();
    $result->close();
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
            <h1 class="mb-0"><i class="bi bi-building"></i> Owned vs Loaned Artworks</h1>
            <p class="text-muted">Check ownership status of artworks</p>
        </div>
        <?php if ($search_performed && !empty($artworks)): ?>
            <button onclick="window.print()" class="btn btn-primary">
                <i class="bi bi-printer"></i> Print
            </button>
        <?php endif; ?>
    </div>
    
    <div class="card mb-4">
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-8">
                        <label class="form-label">Search by Title (leave blank to show all)</label>
                        <input type="text" name="title" class="form-control" 
                               value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" 
                               placeholder="Enter artwork title or leave blank for all">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" name="search" class="btn btn-primary w-100">
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
                <i class="bi bi-exclamation-triangle"></i> No artworks found matching your search.
            </div>
        <?php else: ?>
            <?php 
            $owned = array_filter($artworks, fn($a) => $a['is_owned'] == 1);
            $loaned = array_filter($artworks, fn($a) => $a['is_owned'] == 0);
            ?>
            
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <h6>Total Artworks</h6>
                            <h2><?= count($artworks) ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h6>Owned</h6>
                            <h2><?= count($owned) ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-warning text-white">
                        <div class="card-body text-center">
                            <h6>On Loan</h6>
                            <h2><?= count($loaned) ?></h2>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="artworkTable">
                            <thead class="table-dark">
                                <tr>
                                    <th>Artwork ID</th>
                                    <th>Title</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($artworks as $artwork): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($artwork['artwork_id']) ?></td>
                                        <td><?= htmlspecialchars($artwork['title']) ?></td>
                                        <td>
                                            <?php if ($artwork['is_owned']): ?>
                                                <span class="badge bg-success">Owned</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">On Loan</span>
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

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    $('#artworkTable').DataTable({
        pageLength: 25
    });
});
</script>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>