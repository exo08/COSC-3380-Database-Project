<?php
// debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/permissions.php';

$page_title = 'Collection & Acquisition Analysis';

if ($_SESSION['user_type'] !== 'admin') {
    header('Location: index.php?error=access_denied');
    exit;
}

try {
    $db = db();

    // Filters
    $date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-1 year'));
    $date_to = $_GET['date_to'] ?? date('Y-m-d');
    $medium_filter = $_GET['medium'] ?? '';

    // Acquisition Spending Trends 
    $stmt = $db->prepare("
        SELECT 
            DATE_FORMAT(acquisition_date, '%Y-%m') AS month,
            method,
            COUNT(*) AS count,
            SUM(price_value) AS total_value,
            AVG(price_value) AS avg_value
        FROM ACQUISITION
        WHERE acquisition_date BETWEEN ? AND ?
        GROUP BY month, method
        ORDER BY month DESC
        LIMIT 24
    ");
    $stmt->bind_param('ss', $date_from, $date_to);
    $stmt->execute();
    $acquisition_trends = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Owned vs Loaned breakdown
    $ownership = $db->query("
        SELECT 
            CASE WHEN is_owned = 1 THEN 'Owned' ELSE 'Loaned' END AS status,
            COUNT(*) AS count,
            AVG(CASE 
                WHEN height IS NOT NULL AND width IS NOT NULL 
                THEN height * width 
                ELSE NULL 
            END) AS avg_size_cm2
        FROM ARTWORK
        GROUP BY status
        ORDER BY status DESC
    ")->fetch_all(MYSQLI_ASSOC);

    // Collection Growth Over Time
    $growth = $db->query("
        SELECT 
            YEAR(acquisition_date) AS year,
            COUNT(*) AS acquisitions,
            SUM(price_value) AS total_spent
        FROM ACQUISITION
        WHERE acquisition_date IS NOT NULL
        GROUP BY year
        ORDER BY year DESC
        LIMIT 10
    ")->fetch_all(MYSQLI_ASSOC);

    // Artwork by Medium with Space Analysis 
    if ($medium_filter) {
        $stmt = $db->prepare("
            SELECT 
                A.medium,
                COUNT(A.artwork_id) AS artwork_count,
                SUM(CASE WHEN A.is_owned = 1 THEN 1 ELSE 0 END) AS owned_count,
                AVG(A.height * A.width) AS avg_size_cm2,
                L.name AS current_location,
                COUNT(DISTINCT L.location_id) AS locations_used
            FROM ARTWORK A
            LEFT JOIN LOCATION L ON A.location_id = L.location_id
            WHERE A.medium = ?
            GROUP BY A.medium, L.name
            ORDER BY artwork_count DESC
        ");
        $stmt->bind_param('s', $medium_filter);
        $stmt->execute();
        $medium_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $medium_data = $db->query("
            SELECT 
                A.medium,
                COUNT(A.artwork_id) AS artwork_count,
                SUM(CASE WHEN A.is_owned = 1 THEN 1 ELSE 0 END) AS owned_count,
                AVG(A.height * A.width) AS avg_size_cm2,
                L.name AS current_location,
                COUNT(DISTINCT L.location_id) AS locations_used
            FROM ARTWORK A
            LEFT JOIN LOCATION L ON A.location_id = L.location_id
            GROUP BY A.medium, L.name
            ORDER BY artwork_count DESC
        ")->fetch_all(MYSQLI_ASSOC);
    }

    // Acquisition Methods Breakdown 
    $stmt = $db->prepare("
        SELECT 
            method,
            COUNT(*) AS count,
            SUM(price_value) AS total_value,
            ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM ACQUISITION WHERE acquisition_date BETWEEN ? AND ?)), 1) AS percentage
        FROM ACQUISITION
        WHERE acquisition_date BETWEEN ? AND ?
        GROUP BY method
        ORDER BY count DESC
    ");
    $stmt->bind_param('ssss', $date_from, $date_to, $date_from, $date_to);
    $stmt->execute();
    $methods = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Unlocated Artworks Priority List
    $unlocated = $db->query("
        SELECT 
            A.artwork_id,
            A.title,
            A.creation_year,
            A.is_owned,
            CONCAT(AR.first_name, ' ', AR.last_name) AS artist_name,
            CASE 
                WHEN A.is_owned = 1 AND ACQ.acquisition_date IS NOT NULL 
                THEN DATEDIFF(NOW(), ACQ.acquisition_date)
                ELSE NULL
            END AS days_since_acquisition
        FROM ARTWORK A
        LEFT JOIN ARTWORK_CREATOR AC ON A.artwork_id = AC.artwork_id
        LEFT JOIN ARTIST AR ON AC.artist_id = AR.artist_id
        LEFT JOIN ACQUISITION ACQ ON A.artwork_id = ACQ.artwork_id
        WHERE A.location_id IS NULL
        ORDER BY A.is_owned DESC, days_since_acquisition DESC
        LIMIT 20
    ")->fetch_all(MYSQLI_ASSOC);

    // Calculate totals
    $total_artworks = array_sum(array_column($ownership, 'count'));

    $owned_artworks = 0;
    $loaned_artworks = 0;
    foreach ($ownership as $row) {
        if ($row['status'] === 'Owned') {
            $owned_artworks = $row['count'];
        } elseif ($row['status'] === 'Loaned') {
            $loaned_artworks = $row['count'];
        }
    }

    // Failsafe
    if ($owned_artworks == 0 && $loaned_artworks == 0) {
        $counts = $db->query("
            SELECT 
                SUM(CASE WHEN is_owned = 1 THEN 1 ELSE 0 END) AS owned_count,
                SUM(CASE WHEN is_owned = 0 THEN 1 ELSE 0 END) AS loaned_count,
                COUNT(*) AS total_count
            FROM ARTWORK
        ")->fetch_assoc();
        
        $owned_artworks = $counts['owned_count'];
        $loaned_artworks = $counts['loaned_count'];
        $total_artworks = $counts['total_count'];
    }

    $total_acquisition_spending = array_sum(array_column($acquisition_trends, 'total_value'));

    // Get all mediums for filter
    $mediums = $db->query("SELECT DISTINCT medium FROM ARTWORK WHERE medium IS NOT NULL ORDER BY medium")->fetch_all(MYSQLI_ASSOC);

} catch (Exception $e) {
    // Display error for debugging
    echo "<!DOCTYPE html><html><head><title>Error</title>";
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">';
    echo "</head><body>";
    echo "<div class='container mt-5'>";
    echo "<div class='alert alert-danger'>";
    echo "<h4><i class='bi bi-exclamation-triangle'></i> Error Loading Report</h4>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . "</p>";
    echo "<p><strong>Line:</strong> " . htmlspecialchars($e->getLine()) . "</p>";
    echo "<hr><pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div></div></body></html>";
    exit;
}

include __DIR__ . '/../templates/layout_header.php';
?>
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card border-primary">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-clipboard-data"></i> Collection Health & Strategic Insights</h5>
            </div>
            <div class="card-body">
                <?php
                // Calculate key metrics
                $ownership_ratio = $total_artworks > 0 ? ($owned_artworks / $total_artworks) * 100 : 0;
                $unlocated_pct = $total_artworks > 0 ? (count($unlocated) / $total_artworks) * 100 : 0;
                
                // Calculate acquisition trends
                $recent_acquisitions = 0;
                $recent_spending = 0;
                if (!empty($growth) && count($growth) >= 2) {
                    $recent_acquisitions = $growth[0]['acquisitions'] ?? 0;
                    $previous_acquisitions = $growth[1]['acquisitions'] ?? 1;
                    $acquisition_growth = $previous_acquisitions > 0 ? (($recent_acquisitions - $previous_acquisitions) / $previous_acquisitions) * 100 : 0;
                }
                
                // Analyze acquisition methods
                $purchase_method = array_filter($methods, function($m) { return strtolower($m['method']) === 'purchase'; });
                $purchase_pct = !empty($purchase_method) ? reset($purchase_method)['percentage'] : 0;
                ?>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <h6 class="text-primary"><i class="bi bi-bar-chart-fill"></i> Collection Composition Metrics</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <tbody>
                                    <tr>
                                        <td><strong>Ownership Ratio:</strong></td>
                                        <td class="text-end">
                                            <span class="badge bg-<?= $ownership_ratio >= 70 ? 'success' : ($ownership_ratio >= 50 ? 'warning' : 'danger') ?> fs-6">
                                                <?= number_format($ownership_ratio, 1) ?>%
                                            </span>
                                        </td>
                                        <td class="text-end text-success fw-bold"><?= number_format($owned_artworks) ?> / <?= number_format($total_artworks) ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Loaned Dependency:</strong></td>
                                        <td class="text-end">
                                            <span class="badge bg-<?= (100 - $ownership_ratio) > 40 ? 'warning' : 'info' ?> fs-6">
                                                <?= number_format(100 - $ownership_ratio, 1) ?>%
                                            </span>
                                        </td>
                                        <td class="text-end text-warning fw-bold"><?= number_format($loaned_artworks) ?> items</td>
                                    </tr>
                                    <tr class="table-light">
                                        <td><strong>Unlocated Artworks:</strong></td>
                                        <td class="text-end">
                                            <span class="badge bg-<?= $unlocated_pct > 10 ? 'danger' : ($unlocated_pct > 5 ? 'warning' : 'success') ?> fs-6">
                                                <?= number_format($unlocated_pct, 1) ?>%
                                            </span>
                                        </td>
                                        <td class="text-end text-danger fw-bold"><?= count($unlocated) ?> items</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Medium Diversity:</strong></td>
                                        <td class="text-end">
                                            <span class="badge bg-primary fs-6"><?= count($mediums) ?> types</span>
                                        </td>
                                        <td class="text-end text-primary fw-bold">
                                            <?php
                                            $avg_per_medium = count($mediums) > 0 ? $total_artworks / count($mediums) : 0;
                                            echo number_format($avg_per_medium, 1);
                                            ?> avg/medium
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <h6 class="text-primary"><i class="bi bi-lightbulb-fill"></i> Strategic Recommendations</h6>
                        <div class="alert alert-light border">
                            <ul class="mb-0 small">
                                <?php if ($ownership_ratio < 60): ?>
                                <li class="mb-2 text-warning">
                                    <i class="bi bi-exclamation-triangle-fill"></i>
                                    <strong>Risk Alert:</strong> Only <?= number_format($ownership_ratio, 1) ?>% of collection is owned. 
                                    High dependency on loans creates exhibition planning risks. Consider strategic acquisitions.
                                </li>
                                <?php elseif ($ownership_ratio >= 80): ?>
                                <li class="mb-2 text-success">
                                    <i class="bi bi-check-circle-fill"></i>
                                    <strong>Strong Position:</strong> <?= number_format($ownership_ratio, 1) ?>% ownership provides 
                                    excellent exhibition flexibility and long-term collection stability.
                                </li>
                                <?php endif; ?>
                                
                                <?php if ($unlocated_pct > 5): ?>
                                <li class="mb-2 text-danger">
                                    <i class="bi bi-exclamation-circle-fill"></i>
                                    <strong>Urgent Action:</strong> <?= number_format($unlocated_pct, 1) ?>% of collection is unlocated. 
                                    Implement immediate inventory audit and location tracking protocol.
                                </li>
                                <?php endif; ?>
                                
                                <?php if ($purchase_pct < 40 && $total_acquisition_spending > 0): ?>
                                <li class="mb-2 text-info">
                                    <i class="bi bi-info-circle-fill"></i>
                                    <strong>Acquisition Strategy:</strong> Only <?= number_format($purchase_pct, 1) ?>% of acquisitions are purchases. 
                                    Strong donation/gift program, but consider budget for strategic purchases.
                                </li>
                                <?php endif; ?>
                                
                                <?php if (count($mediums) < 5): ?>
                                <li class="mb-2 text-warning">
                                    <i class="bi bi-palette"></i>
                                    <strong>Collection Diversity:</strong> Limited to <?= count($mediums) ?> media types. 
                                    Consider expanding collection diversity to attract broader audiences.
                                </li>
                                <?php endif; ?>
                                
                                <li class="mb-2 text-primary">
                                    <i class="bi bi-graph-up-arrow"></i>
                                    <strong>Space Planning:</strong> Monitor average artwork sizes by medium for 
                                    optimal gallery space allocation and future expansion planning.
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Acquisition Analysis -->
<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-cash-stack"></i> Acquisition Spending Analysis</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>Period</th>
                                <th>Method</th>
                                <th class="text-end">Acquisitions</th>
                                <th class="text-end">Total Value</th>
                                <th class="text-end">Avg Value</th>
                                <th class="text-end">% of Period</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Group by period for percentage calculation
                            $period_totals = [];
                            foreach ($acquisition_trends as $trend) {
                                if (!isset($period_totals[$trend['month']])) {
                                    $period_totals[$trend['month']] = 0;
                                }
                                $period_totals[$trend['month']] += $trend['total_value'];
                            }
                            
                            foreach ($acquisition_trends as $trend): 
                                $period_pct = $period_totals[$trend['month']] > 0 
                                    ? ($trend['total_value'] / $period_totals[$trend['month']]) * 100 
                                    : 0;
                            ?>
                                <tr>
                                    <td><strong><?= date('M Y', strtotime($trend['month'] . '-01')) ?></strong></td>
                                    <td>
                                        <span class="badge bg-<?= 
                                            strtolower($trend['method']) === 'purchase' ? 'danger' : 
                                            (strtolower($trend['method']) === 'donation' ? 'success' : 'primary') 
                                        ?>">
                                            <?= htmlspecialchars(ucfirst($trend['method'])) ?>
                                        </span>
                                    </td>
                                    <td class="text-end"><?= number_format($trend['count']) ?></td>
                                    <td class="text-end text-danger fw-bold">$<?= number_format($trend['total_value'], 2) ?></td>
                                    <td class="text-end">$<?= number_format($trend['avg_value'], 2) ?></td>
                                    <td class="text-end">
                                        <span class="badge bg-secondary"><?= number_format($period_pct, 1) ?>%</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-info fw-bold">
                            <tr>
                                <td colspan="2">TOTAL</td>
                                <td class="text-end"><?= number_format(array_sum(array_column($acquisition_trends, 'count'))) ?></td>
                                <td class="text-end">$<?= number_format($total_acquisition_spending, 2) ?></td>
                                <td class="text-end">
                                    $<?= array_sum(array_column($acquisition_trends, 'count')) > 0 
                                        ? number_format($total_acquisition_spending / array_sum(array_column($acquisition_trends, 'count')), 2) 
                                        : '0.00' ?>
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card h-100 border-info">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-graph-up"></i> Acquisition Insights</h5>
            </div>
            <div class="card-body">
                <h6 class="text-info">Method Breakdown:</h6>
                <div class="mb-3">
                    <?php foreach ($methods as $method): ?>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <strong><?= htmlspecialchars(ucfirst($method['method'])) ?></strong><br>
                            <small class="text-muted"><?= number_format($method['count']) ?> items</small>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-primary fs-6"><?= number_format($method['percentage'], 1) ?>%</span><br>
                            <small class="text-success">$<?= number_format($method['total_value'], 0) ?></small>
                        </div>
                    </div>
                    <div class="progress mb-2" style="height: 8px;">
                        <div class="progress-bar" style="width: <?= $method['percentage'] ?>%"></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <hr>
                
                <h6 class="text-info mt-3">Key Metrics:</h6>
                <ul class="small mb-0">
                    <li class="mb-2">
                        <strong>Avg Acquisition Cost:</strong> 
                        $<?= array_sum(array_column($acquisition_trends, 'count')) > 0 
                            ? number_format($total_acquisition_spending / array_sum(array_column($acquisition_trends, 'count')), 2) 
                            : '0.00' ?>
                    </li>
                    <li class="mb-2">
                        <strong>Total Acquisitions:</strong> 
                        <?= number_format(array_sum(array_column($acquisition_trends, 'count'))) ?> items
                    </li>
                    <li class="mb-2">
                        <strong>Total Investment:</strong> 
                        $<?= number_format($total_acquisition_spending, 2) ?>
                    </li>
                    <?php if (!empty($growth)): ?>
                    <li class="mb-2">
                        <strong>Recent Year Growth:</strong> 
                        <?php 
                        if (count($growth) >= 2) {
                            $recent = $growth[0]['acquisitions'];
                            $previous = $growth[1]['acquisitions'];
                            $growth_rate = $previous > 0 ? (($recent - $previous) / $previous) * 100 : 0;
                            $is_growing = $growth_rate > 0;
                        ?>
                            <span class="badge bg-<?= $is_growing ? 'success' : 'danger' ?>">
                                <?= $is_growing ? '+' : '' ?><?= number_format($growth_rate, 1) ?>%
                            </span>
                        <?php } else { ?>
                            <span class="text-muted">N/A</span>
                        <?php } ?>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Space Utilization & Medium Analysis -->
<div class="card border-warning mb-4">
    <div class="card-header bg-warning text-dark">
        <h5 class="mb-0"><i class="bi bi-rulers"></i> Space Utilization & Collection Distribution</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-8">
                <h6 class="text-warning">Medium Distribution Analysis</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Medium</th>
                                <th class="text-end">Count</th>
                                <th class="text-end">% of Collection</th>
                                <th class="text-end">Owned</th>
                                <th class="text-end">Ownership %</th>
                                <th class="text-end">Avg Size</th>
                                <th>Space Needs</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Aggregate medium data (since we have duplicates due to JOIN)
                            $medium_summary = [];
                            foreach ($medium_data as $m) {
                                $medium = $m['medium'] ?? 'Unknown';
                                if (!isset($medium_summary[$medium])) {
                                    $medium_summary[$medium] = [
                                        'count' => 0,
                                        'owned' => 0,
                                        'sizes' => []
                                    ];
                                }
                                $medium_summary[$medium]['count'] = $m['artwork_count'];
                                $medium_summary[$medium]['owned'] = $m['owned_count'];
                                if ($m['avg_size_cm2']) {
                                    $medium_summary[$medium]['sizes'][] = $m['avg_size_cm2'];
                                }
                            }
                            
                            foreach ($medium_summary as $medium => $data):
                                $pct = $total_artworks > 0 ? ($data['count'] / $total_artworks) * 100 : 0;
                                $ownership_pct = $data['count'] > 0 ? ($data['owned'] / $data['count']) * 100 : 0;
                                $avg_size = !empty($data['sizes']) ? array_sum($data['sizes']) / count($data['sizes']) : 0;
                                $space_need = $avg_size > 50000 ? 'High' : ($avg_size > 10000 ? 'Medium' : 'Low');
                            ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($medium) ?></strong></td>
                                    <td class="text-end"><?= number_format($data['count']) ?></td>
                                    <td class="text-end">
                                        <span class="badge bg-<?= $pct >= 20 ? 'primary' : 'secondary' ?>">
                                            <?= number_format($pct, 1) ?>%
                                        </span>
                                    </td>
                                    <td class="text-end text-success"><?= number_format($data['owned']) ?></td>
                                    <td class="text-end">
                                        <span class="badge bg-<?= $ownership_pct >= 70 ? 'success' : 'warning' ?>">
                                            <?= number_format($ownership_pct, 1) ?>%
                                        </span>
                                    </td>
                                    <td class="text-end"><?= $avg_size > 0 ? number_format($avg_size, 0) . ' cm²' : 'N/A' ?></td>
                                    <td>
                                        <span class="badge bg-<?= 
                                            $space_need === 'High' ? 'danger' : 
                                            ($space_need === 'Medium' ? 'warning' : 'success') 
                                        ?>">
                                            <?= $space_need ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="col-md-4">
                <h6 class="text-warning">Space Planning Insights</h6>
                <div class="alert alert-light border">
                    <ul class="mb-0 small">
                        <?php
                        // Find dominant medium
                        $max_count = 0;
                        $dominant_medium = '';
                        foreach ($medium_summary as $med => $data) {
                            if ($data['count'] > $max_count) {
                                $max_count = $data['count'];
                                $dominant_medium = $med;
                            }
                        }
                        ?>
                        <li class="mb-2">
                            <i class="bi bi-star-fill text-warning"></i>
                            <strong>Dominant Medium:</strong> <?= htmlspecialchars($dominant_medium) ?> 
                            (<?= number_format(($max_count / $total_artworks) * 100, 1) ?>% of collection)
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-collection"></i>
                            <strong>Medium Diversity:</strong> <?= count($medium_summary) ?> different media types
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-rulers"></i>
                            <strong>Space Planning:</strong> Allocate gallery space proportional to collection size and artwork dimensions
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-exclamation-triangle"></i>
                            <strong>High Space Need Items:</strong> Prioritize larger galleries for media with avg size > 50,000 cm²
                        </li>
                    </ul>
                </div>
                
                <h6 class="text-warning mt-3">Recommendations:</h6>
                <ul class="small mb-0">
                    <li class="mb-2">Review unlocated items urgently—assign locations within 30 days</li>
                    <li class="mb-2">Balance acquisition methods: <?= number_format(100 - $purchase_pct, 1) ?>% donations is strong, maintain donor relationships</li>
                    <li class="mb-2">Consider storage expansion for high-volume media types</li>
                    <li class="mb-2">Develop rotation schedule for owned works to maximize collection visibility</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Critical Action Items -->
<?php if (count($unlocated) > 0 || $unlocated_pct > 5 || $ownership_ratio < 50): ?>
<div class="card border-danger">
    <div class="card-header bg-danger text-white">
        <h5 class="mb-0"><i class="bi bi-exclamation-octagon"></i> Critical Action Items</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6 class="text-danger">Immediate Actions Required:</h6>
                <ul class="mb-0">
                    <?php if (count($unlocated) > 0): ?>
                    <li class="mb-2">
                        <strong class="text-danger">HIGH PRIORITY:</strong> <?= count($unlocated) ?> unlocated artworks 
                        require immediate inventory audit and location assignment
                    </li>
                    <?php endif; ?>
                    <?php if ($ownership_ratio < 50): ?>
                    <li class="mb-2">
                        <strong class="text-warning">MEDIUM PRIORITY:</strong> Ownership ratio below 50% creates 
                        exhibition planning risks. Develop acquisition strategy.
                    </li>
                    <?php endif; ?>
                    <?php if ($unlocated_pct > 10): ?>
                    <li class="mb-2">
                        <strong class="text-danger">HIGH PRIORITY:</strong> Over 10% of collection is unlocated. 
                        This indicates systemic inventory management issues.
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="col-md-6">
                <h6 class="text-danger">Long-term Strategic Goals:</h6>
                <ul class="mb-0">
                    <li class="mb-2">Achieve 70%+ ownership ratio for collection stability</li>
                    <li class="mb-2">Maintain unlocated artwork percentage below 2%</li>
                    <li class="mb-2">Diversify collection across 8-10 different media</li>
                    <li class="mb-2">Establish quarterly inventory audits</li>
                    <li class="mb-2">Develop digital asset management system</li>
                </ul>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>