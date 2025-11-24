<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/permissions.php';

// Check permission
requirePermission('report_collection');

$page_title = 'Collection Reports';
$db = db();

$error = '';
$active_report = $_GET['report'] ?? 'by_artist';

// Get report data based on selection
$report_data = [];

try {
    switch($active_report) {
        case 'by_artist':
            $result = $db->query("CALL ArtworksByArtist()");
            if ($result) {
                $report_data = $result->fetch_all(MYSQLI_ASSOC);
                $result->close();
                $db->next_result(); // Clear stored procedure result
            }
            break;
            
        case 'by_period':
            $result = $db->query("CALL ArtworksByPeriod()");
            if ($result) {
                $report_data = $result->fetch_all(MYSQLI_ASSOC);
                $result->close();
                $db->next_result();
            }
            break;
            
        case 'by_medium':
            $result = $db->query("CALL ArtworksByMedium()");
            if ($result) {
                $report_data = $result->fetch_all(MYSQLI_ASSOC);
                $result->close();
                $db->next_result();
            }
            break;
            
        case 'unlocated':
            $result = $db->query("CALL GetUnlocatedArtworks()");
            if ($result) {
                $report_data = $result->fetch_all(MYSQLI_ASSOC);
                $result->close();
                $db->next_result();
            }
            break;
    }
} catch (Exception $e) {
    $error = 'Error loading report: ' . $e->getMessage();
}

include __DIR__ . '/../templates/layout_header.php';
?>

<style>
.report-nav {
    background: white;
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 30px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.report-nav .nav-link {
    color: #6c757d;
    border-radius: 5px;
    padding: 10px 20px;
    margin: 0 5px;
    transition: all 0.3s;
}

.report-nav .nav-link:hover {
    background: #f8f9fa;
    color: #495057;
}

.report-nav .nav-link.active {
    background: linear-gradient(90deg, #3498db 0%, #2980b9 100%);
    color: white !important;
    font-weight: 500;
}

.report-card {
    background: white;
    border-radius: 10px;
    padding: 25px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.stat-badge {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 500;
}

.print-btn {
    position: fixed;
    bottom: 30px;
    right: 30px;
    z-index: 1000;
}

@media print {
    .report-nav, .print-btn, .top-bar, #sidebar, .stat-card {
        display: none !important;
    }
    
    .report-card {
        box-shadow: none;
        border: 1px solid #dee2e6;
    }
}
</style>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Report Navigation -->
<div class="report-nav">
    <ul class="nav nav-pills">
        <li class="nav-item">
            <a class="nav-link <?= $active_report === 'by_artist' ? 'active' : '' ?>" href="?report=by_artist">
                <i class="bi bi-palette"></i> By Artist
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $active_report === 'by_period' ? 'active' : '' ?>" href="?report=by_period">
                <i class="bi bi-clock-history"></i> By Period
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $active_report === 'by_medium' ? 'active' : '' ?>" href="?report=by_medium">
                <i class="bi bi-brush"></i> By Medium
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $active_report === 'unlocated' ? 'active' : '' ?>" href="?report=unlocated">
                <i class="bi bi-geo-alt"></i> Unlocated Artworks
            </a>
        </li>
    </ul>
</div>

<!-- Report Content -->
<div class="report-card">
    <?php if ($active_report === 'by_artist'): ?>
        <h4 class="mb-4"><i class="bi bi-palette"></i> Artworks by Artist</h4>
        <p class="text-muted">Collection organized by artist with total artwork counts</p>
        
        <?php if (empty($report_data)): ?>
            <div class="alert alert-info">No data available for this report.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Artist Name</th>
                            <th>Nationality</th>
                            <th>Life Years</th>
                            <th class="text-center">Artwork Count</th>
                            <th>Sample Titles</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report_data as $row): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($row['artist_name']) ?></strong></td>
                                <td><?= htmlspecialchars($row['nationality'] ?? 'Unknown') ?></td>
                                <td>
                                    <?php if ($row['birth_year']): ?>
                                        <?= $row['birth_year'] ?> - <?= $row['death_year'] ?? 'Present' ?>
                                    <?php else: ?>
                                        <span class="text-muted">Unknown</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="stat-badge bg-primary text-white">
                                        <?= $row['artwork_count'] ?>
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?= htmlspecialchars(substr($row['artwork_titles'] ?? 'No titles', 0, 100)) ?>
                                        <?= strlen($row['artwork_titles'] ?? '') > 100 ? '...' : '' ?>
                                    </small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="3"><strong>Total Artists:</strong></td>
                            <td class="text-center"><strong><?= count($report_data) ?></strong></td>
                            <td><strong>Total Artworks: <?= array_sum(array_column($report_data, 'artwork_count')) ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
        
    <?php elseif ($active_report === 'by_period'): ?>
        <h4 class="mb-4"><i class="bi bi-clock-history"></i> Artworks by Historical Period</h4>
        <p class="text-muted">Collection organized by creation period and ownership status</p>
        
        <?php if (empty($report_data)): ?>
            <div class="alert alert-info">No data available for this report.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Time Period</th>
                            <th>Year Range</th>
                            <th class="text-center">Total Artworks</th>
                            <th class="text-center">Museum Owned</th>
                            <th class="text-center">On Loan</th>
                            <th class="text-center">Ownership %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_artworks = 0;
                        $total_owned = 0;
                        foreach ($report_data as $row): 
                            $total_artworks += $row['artwork_count'];
                            $total_owned += $row['owned_count'];
                            $ownership_pct = $row['artwork_count'] > 0 ? round(($row['owned_count'] / $row['artwork_count']) * 100, 1) : 0;
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($row['time_period']) ?></strong></td>
                                <td>
                                    <?php if ($row['earliest_year']): ?>
                                        <?= $row['earliest_year'] ?> - <?= $row['latest_year'] ?>
                                    <?php else: ?>
                                        <span class="text-muted">Unknown</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="stat-badge bg-info text-white"><?= $row['artwork_count'] ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="stat-badge bg-success text-white"><?= $row['owned_count'] ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="stat-badge bg-warning text-white"><?= $row['loaned_count'] ?></span>
                                </td>
                                <td class="text-center"><?= $ownership_pct ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="2"><strong>Totals:</strong></td>
                            <td class="text-center"><strong><?= $total_artworks ?></strong></td>
                            <td class="text-center"><strong><?= $total_owned ?></strong></td>
                            <td class="text-center"><strong><?= $total_artworks - $total_owned ?></strong></td>
                            <td class="text-center"><strong><?= $total_artworks > 0 ? round(($total_owned / $total_artworks) * 100, 1) : 0 ?>%</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
        
    <?php elseif ($active_report === 'by_medium'): ?>
        <h4 class="mb-4"><i class="bi bi-brush"></i> Artworks by Medium</h4>
        <p class="text-muted">Collection organized by artistic medium and average size</p>
        
        <?php if (empty($report_data)): ?>
            <div class="alert alert-info">No data available for this report.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Medium</th>
                            <th class="text-center">Total Count</th>
                            <th class="text-center">Museum Owned</th>
                            <th class="text-center">Avg Size (cm²)</th>
                            <th class="text-center">% of Collection</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_count = array_sum(array_column($report_data, 'artwork_count'));
                        foreach ($report_data as $row): 
                            $pct = $total_count > 0 ? round(($row['artwork_count'] / $total_count) * 100, 1) : 0;
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($row['medium_name']) ?></strong></td>
                                <td class="text-center">
                                    <span class="stat-badge bg-primary text-white"><?= $row['artwork_count'] ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="stat-badge bg-success text-white"><?= $row['owned_count'] ?></span>
                                </td>
                                <td class="text-center">
                                    <?= $row['avg_size_cm2'] ? number_format($row['avg_size_cm2'], 0) : 'N/A' ?>
                                </td>
                                <td class="text-center">
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar" role="progressbar" style="width: <?= $pct ?>%">
                                            <?= $pct ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td><strong>Total:</strong></td>
                            <td class="text-center"><strong><?= $total_count ?></strong></td>
                            <td class="text-center"><strong><?= array_sum(array_column($report_data, 'owned_count')) ?></strong></td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
        
    <?php elseif ($active_report === 'unlocated'): ?>
        <h4 class="mb-4"><i class="bi bi-geo-alt"></i> Unlocated Artworks</h4>
        <p class="text-muted">Artworks without an assigned gallery or storage location</p>
        
        <?php if (empty($report_data)): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle"></i> <strong>Great!</strong> All artworks have assigned locations.
            </div>
        <?php else: ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i> <strong><?= count($report_data) ?> artwork(s)</strong> need location assignment.
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Artwork ID</th>
                            <th>Title</th>
                            <th>Artist</th>
                            <th>Year</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report_data as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['artwork_id']) ?></td>
                                <td><strong><?= htmlspecialchars($row['title']) ?></strong></td>
                                <td><?= htmlspecialchars($row['artist_name'] ?? 'Unknown Artist') ?></td>
                                <td><?= htmlspecialchars($row['creation_year'] ?? 'Unknown') ?></td>
                                <td>
                                    <?php if ($row['is_owned']): ?>
                                        <span class="badge bg-success">Owned</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">On Loan</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="/curator/artworks.php" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i> Assign Location
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Print Button -->
<button class="btn btn-primary btn-lg rounded-circle print-btn" onclick="window.print()" title="Print Report">
    <i class="bi bi-printer fs-4"></i>
</button>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>
