<?php
// Collection Analysis Report
// Comprehensive analysis of museum collection: growth, distribution, acquisitions

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/permissions.php';

// Only admin can access
if ($_SESSION['user_type'] !== 'admin') {
    header('Location: index.php?error=access_denied');
    exit;
}

$db = db();

// Get filters
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-2 years'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$time_period = $_GET['time_period'] ?? 'monthly'; // monthly or yearly
$ownership_filter = $_GET['ownership'] ?? 'all'; // all, owned, loaned

// Medium names mapping
$medium_names = [
    1 => 'Oil Painting',
    2 => 'Watercolor',
    3 => 'Acrylic',
    4 => 'Sculpture',
    5 => 'Photography',
    6 => 'Drawing',
    7 => 'Mixed Media',
    8 => 'Digital Art',
    9 => 'Printmaking',
    10 => 'Textile'
];

// Acquisition method names
$acquisition_methods = [
    1 => 'Purchase',
    2 => 'Gift',
    3 => 'Donation',
    4 => 'Bequest',
    5 => 'Exchange'
];

// ========== COLLECTION OVERVIEW STATISTICS ==========
$total_artworks_query = "
    SELECT 
        COUNT(*) as total_count,
        SUM(CASE WHEN is_owned = 1 THEN 1 ELSE 0 END) as owned_count,
        SUM(CASE WHEN is_owned = 0 THEN 1 ELSE 0 END) as loaned_count
    FROM ARTWORK
";
$collection_stats = $db->query($total_artworks_query)->fetch_assoc();

// ========== COLLECTION GROWTH OVER TIME ==========
$date_format = match($time_period) {
    'yearly' => '%Y',
    default => '%Y-%m'
};

$date_group = match($time_period) {
    'yearly' => 'YEAR(acq.acquisition_date)',
    default => 'DATE_FORMAT(acq.acquisition_date, "%Y-%m")'
};

// Get acquisitions over time
$growth_query = "
    SELECT 
        $date_group as period,
        COUNT(*) as acquisitions_count,
        SUM(CASE WHEN acq.method = 1 THEN 1 ELSE 0 END) as purchases,
        SUM(CASE WHEN acq.method IN (2,3,4) THEN 1 ELSE 0 END) as donations_gifts,
        SUM(CASE WHEN acq.price_value IS NOT NULL THEN acq.price_value ELSE 0 END) as total_spent
    FROM ACQUISITION acq
    WHERE acq.acquisition_date BETWEEN ? AND ?
    GROUP BY period
    ORDER BY period
";

$stmt = $db->prepare($growth_query);
$stmt->bind_param("ss", $date_from, $date_to);
$stmt->execute();
$growth_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calculate cumulative growth
$cumulative_count = 0;
foreach ($growth_data as &$row) {
    $cumulative_count += $row['acquisitions_count'];
    $row['cumulative_total'] = $cumulative_count;
}

// ========== DISTRIBUTION BY OWNERSHIP ==========
$ownership_distribution = [
    ['type' => 'Owned', 'count' => $collection_stats['owned_count']],
    ['type' => 'On Loan', 'count' => $collection_stats['loaned_count']]
];

// ========== DISTRIBUTION BY MEDIUM ==========
$medium_query = "
    SELECT 
        medium,
        COUNT(*) as count
    FROM ARTWORK
    WHERE medium IS NOT NULL
    GROUP BY medium
    ORDER BY count DESC
";
$medium_distribution = $db->query($medium_query)->fetch_all(MYSQLI_ASSOC);

// ========== ACQUISITION METHODS DETAIL ==========
// Get all acquisitions with artwork details
$acquisitions_query = "
    SELECT 
        acq.acquisition_id,
        acq.acquisition_date,
        acq.method,
        acq.price_value,
        acq.source_name,
        art.artwork_id,
        art.title as artwork_title,
        art.creation_year,
        art.medium,
        art.is_owned,
        CONCAT(COALESCE(artist.first_name, ''), ' ', COALESCE(artist.last_name, '')) as artist_name
    FROM ACQUISITION acq
    JOIN ARTWORK art ON acq.artwork_id = art.artwork_id
    LEFT JOIN ARTWORK_CREATOR ac ON art.artwork_id = ac.artwork_id
    LEFT JOIN ARTIST artist ON ac.artist_id = artist.artist_id
    WHERE acq.acquisition_date BETWEEN ? AND ?
    ORDER BY acq.method, acq.acquisition_date DESC
";

$stmt = $db->prepare($acquisitions_query);
$stmt->bind_param("ss", $date_from, $date_to);
$stmt->execute();
$all_acquisitions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Group acquisitions by method
$acquisitions_by_method = [];
$method_totals = [];

foreach ($all_acquisitions as $acq) {
    $method = $acq['method'];
    if (!isset($acquisitions_by_method[$method])) {
        $acquisitions_by_method[$method] = [];
        $method_totals[$method] = [
            'count' => 0,
            'total_cost' => 0,
            'with_price_count' => 0
        ];
    }
    $acquisitions_by_method[$method][] = $acq;
    $method_totals[$method]['count']++;
    if ($acq['price_value']) {
        $method_totals[$method]['total_cost'] += $acq['price_value'];
        $method_totals[$method]['with_price_count']++;
    }
}

// ========== ARTWORKS BY OWNERSHIP WITH DETAILS ==========
$artworks_query = "
    SELECT 
        art.artwork_id,
        art.title,
        art.creation_year,
        art.medium,
        art.is_owned,
        art.description,
        CONCAT(COALESCE(artist.first_name, ''), ' ', COALESCE(artist.last_name, '')) as artist_name,
        loc.name as location_name,
        acq.acquisition_date,
        acq.method as acquisition_method,
        acq.price_value
    FROM ARTWORK art
    LEFT JOIN ARTWORK_CREATOR ac ON art.artwork_id = ac.artwork_id
    LEFT JOIN ARTIST artist ON ac.artist_id = artist.artist_id
    LEFT JOIN LOCATION loc ON art.location_id = loc.location_id
    LEFT JOIN ACQUISITION acq ON art.artwork_id = acq.artwork_id
    ORDER BY art.is_owned DESC, art.title
";
$all_artworks = $db->query($artworks_query)->fetch_all(MYSQLI_ASSOC);

// Separate owned and loaned
$owned_artworks = array_filter($all_artworks, fn($a) => $a['is_owned'] == 1);
$loaned_artworks = array_filter($all_artworks, fn($a) => $a['is_owned'] == 0);

// Page title
$page_title = 'Collection Analysis';
include __DIR__ . '/../templates/layout_header.php';
?>

<style>
.stat-card {
    transition: transform 0.2s, box-shadow 0.2s;
    border-left: 4px solid;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 12px rgba(0,0,0,0.15);
}

.revenue-table {
    font-size: 0.95rem;
}

.revenue-table th {
    background-color: #f8f9fa;
    font-weight: 600;
    position: sticky;
    top: 0;
    z-index: 10;
}

.table-container {
    border: 1px solid #dee2e6;
    border-radius: 0.25rem;
    overflow-x: auto;
}

.chart-container {
    position: relative;
    height: 400px;
    margin-bottom: 2rem;
}

/* Expandable row styles */
.expandable-row {
    cursor: pointer;
    transition: background-color 0.2s;
}

.expandable-row:hover {
    background-color: #f8f9fa;
}

.expandable-row td {
    position: relative;
}

.expandable-row td:first-child::before {
    content: '▶';
    position: absolute;
    left: 8px;
    transition: transform 0.3s;
    font-size: 0.8rem;
    color: #6c757d;
}

.expandable-row.expanded td:first-child::before {
    transform: rotate(90deg);
}

.detail-row {
    display: none;
}

.detail-row.show {
    display: table-row;
}

.detail-row td {
    padding: 0 !important;
    border-top: none !important;
}

.detail-row tr:hover {
    background-color: #f8f9fa;
}
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="index.php" class="btn btn-outline-secondary mb-2">
                <i class="bi bi-arrow-left"></i> Back to Reports
            </a>
            <h1 class="mb-0"><i class="bi bi-palette text-primary"></i> Collection Analysis</h1>
            <p class="text-muted">Comprehensive analysis of museum collection growth and distribution</p>
        </div>
        <div>
            <button onclick="window.print()" class="btn btn-outline-primary">
                <i class="bi bi-printer"></i> Print Report
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="bi bi-funnel"></i> Report Filters</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">From Date</label>
                    <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">To Date</label>
                    <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Time Period</label>
                    <select name="time_period" class="form-select">
                        <option value="monthly" <?= $time_period === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                        <option value="yearly" <?= $time_period === 'yearly' ? 'selected' : '' ?>>Yearly</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Ownership</label>
                    <select name="ownership" class="form-select">
                        <option value="all" <?= $ownership_filter === 'all' ? 'selected' : '' ?>>All</option>
                        <option value="owned" <?= $ownership_filter === 'owned' ? 'selected' : '' ?>>Owned Only</option>
                        <option value="loaned" <?= $ownership_filter === 'loaned' ? 'selected' : '' ?>>On Loan Only</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Apply Filters
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card stat-card border-primary h-100">
                <div class="card-body text-center">
                    <i class="bi bi-collection text-primary" style="font-size: 2.5rem;"></i>
                    <h3 class="mb-0 mt-2"><?= number_format($collection_stats['total_count']) ?></h3>
                    <p class="text-muted mb-0">Total Artworks</p>
                    <small class="text-muted">In collection</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card border-success h-100">
                <div class="card-body text-center">
                    <i class="bi bi-check-circle text-success" style="font-size: 2.5rem;"></i>
                    <h3 class="mb-0 mt-2"><?= number_format($collection_stats['owned_count']) ?></h3>
                    <p class="text-muted mb-0">Owned Artworks</p>
                    <small class="text-muted"><?= $collection_stats['total_count'] > 0 ? number_format(($collection_stats['owned_count'] / $collection_stats['total_count']) * 100, 1) : 0 ?>% of collection</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card border-info h-100">
                <div class="card-body text-center">
                    <i class="bi bi-arrow-left-right text-info" style="font-size: 2.5rem;"></i>
                    <h3 class="mb-0 mt-2"><?= number_format($collection_stats['loaned_count']) ?></h3>
                    <p class="text-muted mb-0">On Loan</p>
                    <small class="text-muted"><?= $collection_stats['total_count'] > 0 ? number_format(($collection_stats['loaned_count'] / $collection_stats['total_count']) * 100, 1) : 0 ?>% of collection</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card border-warning h-100">
                <div class="card-body text-center">
                    <i class="bi bi-calendar-plus text-warning" style="font-size: 2.5rem;"></i>
                    <h3 class="mb-0 mt-2"><?= count($all_acquisitions) ?></h3>
                    <p class="text-muted mb-0">New Acquisitions</p>
                    <small class="text-muted">In selected period</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Collection Growth Over Time -->
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="bi bi-graph-up"></i> Collection Growth Over Time</h5>
        </div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="growthChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Distribution Charts -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-pie-chart"></i> Ownership Distribution</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="height: 300px;">
                        <canvas id="ownershipChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-palette"></i> Distribution by Medium</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="height: 300px;">
                        <canvas id="mediumChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Acquisition Methods Table -->
    <div class="card mb-4">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-table"></i> Acquisition Methods Detail</h5>
            <small class="text-muted"><i class="bi bi-info-circle"></i> Click any row to view individual acquisitions</small>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table class="table table-hover revenue-table mb-0">
                    <thead>
                        <tr>
                            <th style="padding-left: 2rem;">Acquisition Method</th>
                            <th class="text-end">Number of Artworks</th>
                            <th class="text-end">Total Cost/Value</th>
                            <th class="text-end">Avg Cost</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($method_totals)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    No acquisitions in the selected period
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($method_totals as $method_id => $totals): ?>
                                <?php 
                                $method_name = $acquisition_methods[$method_id] ?? 'Unknown';
                                $acquisitions = $acquisitions_by_method[$method_id] ?? [];
                                $avg_cost = $totals['with_price_count'] > 0 ? $totals['total_cost'] / $totals['with_price_count'] : 0;
                                ?>
                                <!-- Summary Row -->
                                <tr class="expandable-row" data-target="method-detail-<?= $method_id ?>" onclick="toggleDetailRow(this)">
                                    <td style="padding-left: 2rem;"><strong><?= htmlspecialchars($method_name) ?></strong></td>
                                    <td class="text-end"><?= number_format($totals['count']) ?></td>
                                    <td class="text-end">
                                        <?php if ($totals['total_cost'] > 0): ?>
                                            <strong class="text-success">$<?= number_format($totals['total_cost'], 2) ?></strong>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($avg_cost > 0): ?>
                                            $<?= number_format($avg_cost, 2) ?>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?= $totals['with_price_count'] ?> with pricing info
                                        </small>
                                    </td>
                                </tr>
                                <!-- Detail Row -->
                                <tr class="detail-row" id="method-detail-<?= $method_id ?>">
                                    <td colspan="5" style="padding: 0; background-color: #f8f9fa;">
                                        <div style="padding: 1rem 0; border-left: 3px solid #0d6efd; margin-left: 2rem;">
                                            <div style="padding: 0 1rem 0.5rem 1rem;">
                                                <strong><i class="bi bi-list-ul"></i> Individual Acquisitions</strong>
                                                <span class="badge bg-primary ms-2"><?= count($acquisitions) ?> artworks</span>
                                            </div>
                                            
                                            <?php if (empty($acquisitions)): ?>
                                                <p class="text-muted mb-0" style="padding: 0 1rem;">No acquisitions</p>
                                            <?php else: ?>
                                                <table class="table table-sm mb-0" style="background-color: white; margin: 0 1rem; width: calc(100% - 2rem);">
                                                    <tbody>
                                                        <?php foreach ($acquisitions as $acq): ?>
                                                            <tr style="border-bottom: 1px solid #e9ecef;">
                                                                <td style="padding: 0.75rem; padding-left: 2rem; width: 30%;">
                                                                    <strong><?= htmlspecialchars($acq['artwork_title']) ?></strong>
                                                                    <br>
                                                                    <small class="text-muted">
                                                                        by <?= htmlspecialchars($acq['artist_name'] ?: 'Unknown Artist') ?>
                                                                    </small>
                                                                    <br>
                                                                    <small class="text-muted">
                                                                        Acquired: <?= date('M d, Y', strtotime($acq['acquisition_date'])) ?>
                                                                    </small>
                                                                </td>
                                                                <td class="text-end" style="padding: 0.75rem; width: 15%;">
                                                                    <small class="text-muted">Artwork ID</small><br>
                                                                    #<?= $acq['artwork_id'] ?>
                                                                </td>
                                                                <td class="text-end" style="padding: 0.75rem; width: 20%;">
                                                                    <small class="text-muted">Cost/Value</small><br>
                                                                    <?php if ($acq['price_value']): ?>
                                                                        <strong class="text-success">$<?= number_format($acq['price_value'], 2) ?></strong>
                                                                    <?php else: ?>
                                                                        <span class="text-muted">Not specified</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td class="text-end" style="padding: 0.75rem; width: 15%;">
                                                                    <?php if ($acq['source_name']): ?>
                                                                        <small class="text-muted">Source</small><br>
                                                                        <?= htmlspecialchars($acq['source_name']) ?>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td style="padding: 0.75rem; width: 20%;">
                                                                    <?php if ($acq['medium']): ?>
                                                                        <span class="badge bg-info" style="font-size: 0.7rem;">
                                                                            <?= $medium_names[$acq['medium']] ?? 'Unknown Medium' ?>
                                                                        </span>
                                                                    <?php endif; ?>
                                                                    <?php if ($acq['creation_year']): ?>
                                                                        <br><small class="text-muted">Created: <?= $acq['creation_year'] ?></small>
                                                                    <?php endif; ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Ownership Distribution Detail -->
    <div class="card mb-4">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-table"></i> Artwork Distribution by Ownership</h5>
            <small class="text-muted"><i class="bi bi-info-circle"></i> Click any row to view artworks</small>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table class="table table-hover revenue-table mb-0">
                    <thead>
                        <tr>
                            <th style="padding-left: 2rem;">Ownership Status</th>
                            <th class="text-end">Count</th>
                            <th class="text-end">Percentage</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Owned Artworks Row -->
                        <tr class="expandable-row" data-target="owned-detail" onclick="toggleDetailRow(this)">
                            <td style="padding-left: 2rem;"><strong>Owned by Museum</strong></td>
                            <td class="text-end"><?= number_format(count($owned_artworks)) ?></td>
                            <td class="text-end"><?= $collection_stats['total_count'] > 0 ? number_format((count($owned_artworks) / $collection_stats['total_count']) * 100, 1) : 0 ?>%</td>
                            <td><small class="text-muted">Permanent collection</small></td>
                        </tr>
                        <!-- Owned Detail Row -->
                        <tr class="detail-row" id="owned-detail">
                            <td colspan="4" style="padding: 0; background-color: #f8f9fa;">
                                <div style="padding: 1rem 0; border-left: 3px solid #28a745; margin-left: 2rem;">
                                    <div style="padding: 0 1rem 0.5rem 1rem;">
                                        <strong><i class="bi bi-check-circle"></i> Owned Artworks</strong>
                                        <span class="badge bg-success ms-2"><?= count($owned_artworks) ?> artworks</span>
                                    </div>
                                    
                                    <?php if (empty($owned_artworks)): ?>
                                        <p class="text-muted mb-0" style="padding: 0 1rem;">No owned artworks</p>
                                    <?php else: ?>
                                        <table class="table table-sm mb-0" style="background-color: white; margin: 0 1rem; width: calc(100% - 2rem);">
                                            <tbody>
                                                <?php foreach ($owned_artworks as $artwork): ?>
                                                    <tr style="border-bottom: 1px solid #e9ecef;">
                                                        <td style="padding: 0.75rem; padding-left: 2rem; width: 35%;">
                                                            <strong><?= htmlspecialchars($artwork['title']) ?></strong>
                                                            <br>
                                                            <small class="text-muted">by <?= htmlspecialchars($artwork['artist_name'] ?: 'Unknown Artist') ?></small>
                                                        </td>
                                                        <td class="text-end" style="padding: 0.75rem; width: 15%;">
                                                            <small class="text-muted">Created</small><br>
                                                            <?= $artwork['creation_year'] ?: 'Unknown' ?>
                                                        </td>
                                                        <td class="text-end" style="padding: 0.75rem; width: 20%;">
                                                            <?php if ($artwork['acquisition_method']): ?>
                                                                <small class="text-muted">Acquired via</small><br>
                                                                <?= $acquisition_methods[$artwork['acquisition_method']] ?? 'Unknown' ?>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td style="padding: 0.75rem; width: 30%;">
                                                            <?php if ($artwork['location_name']): ?>
                                                                <small class="text-muted"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($artwork['location_name']) ?></small>
                                                            <?php endif; ?>
                                                            <?php if ($artwork['medium']): ?>
                                                                <br>
                                                                <span class="badge bg-info" style="font-size: 0.7rem;">
                                                                    <?= $medium_names[$artwork['medium']] ?? 'Unknown' ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>

                        <!-- Loaned Artworks Row -->
                        <tr class="expandable-row" data-target="loaned-detail" onclick="toggleDetailRow(this)">
                            <td style="padding-left: 2rem;"><strong>On Loan</strong></td>
                            <td class="text-end"><?= number_format(count($loaned_artworks)) ?></td>
                            <td class="text-end"><?= $collection_stats['total_count'] > 0 ? number_format((count($loaned_artworks) / $collection_stats['total_count']) * 100, 1) : 0 ?>%</td>
                            <td><small class="text-muted">Temporary loans</small></td>
                        </tr>
                        <!-- Loaned Detail Row -->
                        <tr class="detail-row" id="loaned-detail">
                            <td colspan="4" style="padding: 0; background-color: #f8f9fa;">
                                <div style="padding: 1rem 0; border-left: 3px solid #17a2b8; margin-left: 2rem;">
                                    <div style="padding: 0 1rem 0.5rem 1rem;">
                                        <strong><i class="bi bi-arrow-left-right"></i> Loaned Artworks</strong>
                                        <span class="badge bg-info ms-2"><?= count($loaned_artworks) ?> artworks</span>
                                    </div>
                                    
                                    <?php if (empty($loaned_artworks)): ?>
                                        <p class="text-muted mb-0" style="padding: 0 1rem;">No loaned artworks</p>
                                    <?php else: ?>
                                        <table class="table table-sm mb-0" style="background-color: white; margin: 0 1rem; width: calc(100% - 2rem);">
                                            <tbody>
                                                <?php foreach ($loaned_artworks as $artwork): ?>
                                                    <tr style="border-bottom: 1px solid #e9ecef;">
                                                        <td style="padding: 0.75rem; padding-left: 2rem; width: 35%;">
                                                            <strong><?= htmlspecialchars($artwork['title']) ?></strong>
                                                            <br>
                                                            <small class="text-muted">by <?= htmlspecialchars($artwork['artist_name'] ?: 'Unknown Artist') ?></small>
                                                        </td>
                                                        <td class="text-end" style="padding: 0.75rem; width: 15%;">
                                                            <small class="text-muted">Created</small><br>
                                                            <?= $artwork['creation_year'] ?: 'Unknown' ?>
                                                        </td>
                                                        <td class="text-end" style="padding: 0.75rem; width: 20%;">
                                                            <span class="badge bg-warning text-dark">On Loan</span>
                                                        </td>
                                                        <td style="padding: 0.75rem; width: 30%;">
                                                            <?php if ($artwork['location_name']): ?>
                                                                <small class="text-muted"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($artwork['location_name']) ?></small>
                                                            <?php endif; ?>
                                                            <?php if ($artwork['medium']): ?>
                                                                <br>
                                                                <span class="badge bg-info" style="font-size: 0.7rem;">
                                                                    <?= $medium_names[$artwork['medium']] ?? 'Unknown' ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Key Insights -->
    <div class="card">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="bi bi-lightbulb"></i> Collection Insights</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6 class="text-primary"><i class="bi bi-bar-chart"></i> Collection Statistics</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <i class="bi bi-check-circle text-success"></i>
                            Total collection size: <strong><?= number_format($collection_stats['total_count']) ?> artworks</strong>
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-check-circle text-success"></i>
                            Ownership rate: <strong><?= $collection_stats['total_count'] > 0 ? number_format(($collection_stats['owned_count'] / $collection_stats['total_count']) * 100, 1) : 0 ?>%</strong>
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-check-circle text-success"></i>
                            New acquisitions: <strong><?= count($all_acquisitions) ?> in selected period</strong>
                        </li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h6 class="text-info"><i class="bi bi-graph-up-arrow"></i> Growth Trends</h6>
                    <ul class="list-unstyled">
                        <?php if (!empty($growth_data)): ?>
                            <li class="mb-2">
                                <i class="bi bi-calendar-plus text-warning"></i>
                                Most active period: <strong><?= $growth_data[0]['period'] ?? 'N/A' ?></strong>
                            </li>
                        <?php endif; ?>
                        <?php 
                        $total_acquisition_cost = array_sum(array_column($method_totals, 'total_cost'));
                        if ($total_acquisition_cost > 0):
                        ?>
                            <li class="mb-2">
                                <i class="bi bi-cash text-success"></i>
                                Total acquisition investment: <strong>$<?= number_format($total_acquisition_cost, 2) ?></strong>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Prepare data for charts
const growthData = <?= json_encode($growth_data) ?>;
const ownershipData = <?= json_encode($ownership_distribution) ?>;
const mediumData = <?= json_encode($medium_distribution) ?>;
const mediumNames = <?= json_encode($medium_names) ?>;

// Collection Growth Chart
const growthCtx = document.getElementById('growthChart').getContext('2d');
new Chart(growthCtx, {
    type: 'line',
    data: {
        labels: growthData.map(d => d.period),
        datasets: [
            {
                label: 'New Acquisitions',
                data: growthData.map(d => d.acquisitions_count),
                borderColor: 'rgb(54, 162, 235)',
                backgroundColor: 'rgba(54, 162, 235, 0.1)',
                tension: 0.4,
                yAxisID: 'y'
            },
            {
                label: 'Cumulative Total',
                data: growthData.map(d => d.cumulative_total),
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.1)',
                tension: 0.4,
                yAxisID: 'y'
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
            mode: 'index',
            intersect: false,
        },
        plugins: {
            legend: {
                position: 'top',
            },
            title: {
                display: true,
                text: 'Collection Growth Trends'
            }
        },
        scales: {
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                beginAtZero: true,
                title: {
                    display: true,
                    text: 'Number of Artworks'
                }
            }
        }
    }
});

// Ownership Distribution Chart
const ownershipCtx = document.getElementById('ownershipChart').getContext('2d');
new Chart(ownershipCtx, {
    type: 'doughnut',
    data: {
        labels: ownershipData.map(d => d.type),
        datasets: [{
            data: ownershipData.map(d => d.count),
            backgroundColor: [
                'rgba(40, 167, 69, 0.8)',
                'rgba(23, 162, 184, 0.8)'
            ],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Medium Distribution Chart
const mediumCtx = document.getElementById('mediumChart').getContext('2d');
new Chart(mediumCtx, {
    type: 'bar',
    data: {
        labels: mediumData.map(d => mediumNames[d.medium] || 'Unknown'),
        datasets: [{
            label: 'Artworks',
            data: mediumData.map(d => d.count),
            backgroundColor: 'rgba(54, 162, 235, 0.8)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});

// Toggle detail rows function
function toggleDetailRow(element) {
    const targetId = element.getAttribute('data-target');
    const detailRow = document.getElementById(targetId);
    
    element.classList.toggle('expanded');
    detailRow.classList.toggle('show');
    
    event.stopPropagation();
}

// keyboard nav
document.addEventListener('DOMContentLoaded', function() {
    const expandableRows = document.querySelectorAll('.expandable-row');
    
    expandableRows.forEach(row => {
        row.setAttribute('tabindex', '0');
        row.setAttribute('role', 'button');
        row.setAttribute('aria-expanded', 'false');
        
        row.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggleDetailRow(this);
                const isExpanded = this.classList.contains('expanded');
                this.setAttribute('aria-expanded', isExpanded);
            }
        });
    });
});
</script>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>