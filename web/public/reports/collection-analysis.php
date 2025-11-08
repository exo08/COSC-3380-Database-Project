<?php
// Enable error reporting for debugging
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

$db = db();

// Filters
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-1 year'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$medium_filter = $_GET['medium'] ?? '';

// Acquisition Spending Trends
$acquisition_trends = $db->query("
    SELECT 
        DATE_FORMAT(acquisition_date, '%Y-%m') AS month,
        method,
        COUNT(*) AS count,
        SUM(price_value) AS total_value,
        AVG(price_value) AS avg_value
    FROM ACQUISITION
    WHERE acquisition_date BETWEEN '$date_from' AND '$date_to'
    GROUP BY month, method
    ORDER BY month DESC
    LIMIT 24
")->fetch_all(MYSQLI_ASSOC);

// Owned vs Loaned breakdown using ORDER BY for correct breakdown since it was causing issues
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
$medium_query = "
    SELECT 
        A.medium,
        COUNT(A.artwork_id) AS artwork_count,
        SUM(CASE WHEN A.is_owned = 1 THEN 1 ELSE 0 END) AS owned_count,
        AVG(A.height * A.width) AS avg_size_cm2,
        L.name AS current_location,
        COUNT(DISTINCT L.location_id) AS locations_used
    FROM ARTWORK A
    LEFT JOIN LOCATION L ON A.location_id = L.location_id
    WHERE 1=1" . ($medium_filter ? " AND A.medium = '$medium_filter'" : "") . "
    GROUP BY A.medium, L.name
    ORDER BY artwork_count DESC
";
$medium_data = $db->query($medium_query)->fetch_all(MYSQLI_ASSOC);

// Acquisition Methods Breakdown
$methods = $db->query("
    SELECT 
        method,
        COUNT(*) AS count,
        SUM(price_value) AS total_value,
        ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM ACQUISITION)), 1) AS percentage
    FROM ACQUISITION
    WHERE acquisition_date BETWEEN '$date_from' AND '$date_to'
    GROUP BY method
    ORDER BY count DESC
")->fetch_all(MYSQLI_ASSOC);

// Unlocated Artworks Priority List
// For owned artworks show days since acquisition
// For loaned artworks show N/A since we don't track loan dates in the current schema
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

// loop through and get counts 
$owned_artworks = 0;
$loaned_artworks = 0;
foreach ($ownership as $row) {
    if ($row['status'] === 'Owned') {
        $owned_artworks = $row['count'];
    } elseif ($row['status'] === 'Loaned') {
        $loaned_artworks = $row['count'];
    }
}

// failsafe for testing
$counts = $db->query("
    SELECT 
        SUM(CASE WHEN is_owned = 1 THEN 1 ELSE 0 END) AS owned_count,
        SUM(CASE WHEN is_owned = 0 THEN 1 ELSE 0 END) AS loaned_count,
        COUNT(*) AS total_count
    FROM ARTWORK
")->fetch_assoc();

// failsafe for testing
if ($owned_artworks == 0 && $loaned_artworks == 0) {
    $owned_artworks = $counts['owned_count'];
    $loaned_artworks = $counts['loaned_count'];
    $total_artworks = $counts['total_count'];
}

$total_acquisition_spending = array_sum(array_column($acquisition_trends, 'total_value'));

// Get all mediums for filter
$mediums = $db->query("SELECT DISTINCT medium FROM ARTWORK WHERE medium IS NOT NULL ORDER BY medium")->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../templates/layout_header.php';
?>

<style>
.executive-header {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    border-radius: 15px;
    padding: 30px;
    color: white;
    margin-bottom: 30px;
}
.stat-card-executive {
    border-radius: 10px;
    padding: 25px;
    background: white;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border-left: 4px solid;
}
.chart-card {
    background: white;
    border-radius: 10px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="executive-header">
        <a href="index.php" class="btn btn-light btn-sm mb-3">
            <i class="bi bi-arrow-left"></i> Back to Reports
        </a>
        <h1 class="mb-2"><i class="bi bi-palette"></i> Collection & Acquisition Analysis</h1>
        <p class="mb-0 opacity-75">Acquisition trends, ownership analysis, space utilization, and collection growth metrics</p>
    </div>
    
    <!-- Filters -->
    <div class="filter-section bg-light rounded p-4 mb-4">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label fw-bold">Date From</label>
                <input type="date" name="date_from" class="form-control" value="<?= $date_from ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">Date To</label>
                <input type="date" name="date_to" class="form-control" value="<?= $date_to ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">Medium Filter</label>
                <select name="medium" class="form-select">
                    <option value="">All Mediums</option>
                    <?php foreach ($mediums as $m): ?>
                        <option value="<?= $m['medium'] ?>" <?= $medium_filter == $m['medium'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($m['medium']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> Apply</button>
                <a href="collection-analysis.php" class="btn btn-outline-secondary">Clear</a>
            </div>
        </form>
    </div>
    
    <!-- Summary Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card-executive" style="border-left-color: #007bff;">
                <div class="d-flex justify-content-between">
                    <div>
                        <p class="text-muted mb-1 small">TOTAL ARTWORKS</p>
                        <h3 class="mb-0 text-primary"><?= number_format($total_artworks) ?></h3>
                    </div>
                    <i class="bi bi-palette fs-1 text-primary opacity-25"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card-executive" style="border-left-color: #28a745;">
                <div class="d-flex justify-content-between">
                    <div>
                        <p class="text-muted mb-1 small">OWNED ARTWORKS</p>
                        <h3 class="mb-0 text-success"><?= number_format($owned_artworks) ?></h3>
                    </div>
                    <i class="bi bi-check-circle fs-1 text-success opacity-25"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card-executive" style="border-left-color: #ffc107;">
                <div class="d-flex justify-content-between">
                    <div>
                        <p class="text-muted mb-1 small">LOANED ARTWORKS</p>
                        <h3 class="mb-0 text-warning"><?= number_format($loaned_artworks) ?></h3>
                    </div>
                    <i class="bi bi-arrow-repeat fs-1 text-warning opacity-25"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card-executive" style="border-left-color: #dc3545;">
                <div class="d-flex justify-content-between">
                    <div>
                        <p class="text-muted mb-1 small">UNLOCATED</p>
                        <h3 class="mb-0 text-danger"><?= number_format(count($unlocated)) ?></h3>
                    </div>
                    <i class="bi bi-exclamation-triangle fs-1 text-danger opacity-25"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Charts Row -->
    <div class="row">
        <div class="col-md-6">
            <div class="chart-card">
                <h5 class="mb-3"><i class="bi bi-pie-chart"></i> Owned vs Loaned Distribution</h5>
                <div style="height: 300px; position: relative;">
                    <canvas id="ownershipChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="chart-card">
                <h5 class="mb-3"><i class="bi bi-bar-chart"></i> Acquisition Methods</h5>
                <div style="height: 300px; position: relative;">
                    <canvas id="methodsChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Collection Growth -->
    <div class="chart-card">
        <h5 class="mb-3"><i class="bi bi-graph-up"></i> Collection Growth Over Time</h5>
        <div style="height: 400px; position: relative;">
            <canvas id="growthChart"></canvas>
        </div>
    </div>
    
    <!-- Medium Analysis Table -->
    <div class="chart-card">
        <h5 class="mb-3"><i class="bi bi-brush"></i> Artwork by Medium & Space Utilization</h5>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Medium</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Owned</th>
                        <th class="text-end">Avg Size (cm²)</th>
                        <th class="text-end">Locations Used</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($medium_data as $m): ?>
                        <tr>
                            <td><span class="badge bg-primary"><?= htmlspecialchars($m['medium'] ?? 'No medium') ?></span></td>
                            <td class="text-end"><?= number_format($m['artwork_count']) ?></td>
                            <td class="text-end"><?= number_format($m['owned_count']) ?></td>
                            <td class="text-end"><?= $m['avg_size_cm2'] ? number_format($m['avg_size_cm2'], 0) : 'N/A' ?></td>
                            <td class="text-end"><?= number_format($m['locations_used']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Unlocated Artworks -->
    <?php if (count($unlocated) > 0): ?>
        <div class="chart-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0"><i class="bi bi-exclamation-triangle text-danger"></i> Unlocated Artworks - Action Required</h5>
                <span class="badge bg-danger"><?= count($unlocated) ?> Items</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Artist</th>
                            <th>Year</th>
                            <th>Status</th>
                            <th class="text-end">Time Unlocated</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($unlocated as $art): ?>
                            <tr>
                                <td><?= $art['artwork_id'] ?></td>
                                <td class="fw-bold"><?= htmlspecialchars($art['title']) ?></td>
                                <td><?= htmlspecialchars($art['artist_name'] ?? 'Unknown') ?></td>
                                <td><?= $art['creation_year'] ?? 'N/A' ?></td>
                                <td>
                                    <span class="badge <?= $art['is_owned'] ? 'bg-success' : 'bg-warning' ?>">
                                        <?= $art['is_owned'] ? 'Owned' : 'Loaned' ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <?php if ($art['is_owned'] && $art['days_since_acquisition'] !== null): ?>
                                        <span class="badge <?= $art['days_since_acquisition'] > 30 ? 'bg-danger' : 'bg-warning' ?>">
                                            <?= $art['days_since_acquisition'] ?> days
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">
                                            <?= $art['is_owned'] ? 'No acquisition date' : 'N/A (Loaned)' ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Ownership pie chart
new Chart(document.getElementById('ownershipChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($ownership, 'status')) ?>,
        datasets: [{
            data: <?= json_encode(array_column($ownership, 'count')) ?>,
            backgroundColor: ['#28a745', '#ffc107']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        let label = context.label || '';
                        if (label) {
                            label += ': ';
                        }
                        label += context.parsed + ' artwork' + (context.parsed !== 1 ? 's' : '');
                        return label;
                    }
                }
            }
        }
    }
});

// Methods Chart
new Chart(document.getElementById('methodsChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($methods, 'method')) ?>,
        datasets: [{
            label: 'Count',
            data: <?= json_encode(array_column($methods, 'count')) ?>,
            backgroundColor: '#007bff'
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
                beginAtZero: true
            }
        }
    }
});

// Growth Chart
new Chart(document.getElementById('growthChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($growth, 'year')) ?>,
        datasets: [{
            label: 'Acquisitions',
            data: <?= json_encode(array_column($growth, 'acquisitions')) ?>,
            borderColor: '#007bff',
            backgroundColor: 'rgba(0, 123, 255, 0.1)',
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
</script>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>