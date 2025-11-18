<?php
// debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/permissions.php';

$page_title = 'Exhibition & Event Analytics';

if ($_SESSION['user_type'] !== 'admin') {
    header('Location: index.php?error=access_denied');
    exit;
}

try {
    $db = db();

    // Filters
    $date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-6 months'));
    $date_to = $_GET['date_to'] ?? date('Y-m-d');

    // Exhibition Attendance Comparison 
    $stmt = $db->prepare("
        SELECT 
            E.exhibition_id,
            E.title,
            E.start_date,
            E.end_date,
            S.name AS curator_name,
            COUNT(DISTINCT EV.event_id) AS event_count,
            COALESCE(SUM(T.quantity), 0) AS total_attendance,
            DATEDIFF(E.end_date, E.start_date) AS duration_days
        FROM EXHIBITION E
        LEFT JOIN STAFF S ON E.curator_id = S.staff_id
        LEFT JOIN EVENT EV ON E.exhibition_id = EV.exhibition_id
        LEFT JOIN TICKET T ON EV.event_id = T.event_id
        WHERE E.start_date BETWEEN ? AND ?
        GROUP BY E.exhibition_id, E.title, E.start_date, E.end_date, S.name
        ORDER BY total_attendance DESC
    ");
    $stmt->bind_param('ss', $date_from, $date_to);
    $stmt->execute();
    $exhibitions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Event Capacity Utilization 
    $stmt = $db->prepare("
        SELECT 
            EV.event_id,
            EV.name,
            EV.event_date,
            EV.capacity,
            COALESCE(SUM(T.quantity), 0) AS tickets_sold,
            ROUND((COALESCE(SUM(T.quantity), 0) / EV.capacity) * 100, 1) AS utilization_pct,
            EX.title AS exhibition_name
        FROM EVENT EV
        LEFT JOIN TICKET T ON EV.event_id = T.event_id
        LEFT JOIN EXHIBITION EX ON EV.exhibition_id = EX.exhibition_id
        WHERE EV.event_date BETWEEN ? AND ?
        GROUP BY EV.event_id, EV.name, EV.event_date, EV.capacity, EX.title
        ORDER BY utilization_pct DESC
    ");
    $stmt->bind_param('ss', $date_from, $date_to);
    $stmt->execute();
    $events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Curator Performance 
    $stmt = $db->prepare("
        SELECT 
            S.staff_id,
            S.name AS curator_name,
            COUNT(E.exhibition_id) AS exhibitions_curated,
            AVG(DATEDIFF(E.end_date, E.start_date)) AS avg_duration,
            COUNT(DISTINCT EV.event_id) AS events_hosted
        FROM STAFF S
        JOIN EXHIBITION E ON S.staff_id = E.curator_id
        LEFT JOIN EVENT EV ON E.exhibition_id = EV.exhibition_id
        WHERE E.start_date BETWEEN ? AND ?
        GROUP BY S.staff_id, S.name
        ORDER BY exhibitions_curated DESC
    ");
    $stmt->bind_param('ss', $date_from, $date_to);
    $stmt->execute();
    $curators = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Calculate summary statistics
    $total_exhibitions = count($exhibitions);
    $total_events = count($events);
    $avg_utilization = count($events) > 0 ? array_sum(array_column($events, 'utilization_pct')) / count($events) : 0;

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
    echo "</div></div></body></html>";
    exit;
}

include __DIR__ . '/../templates/layout_header.php';
?>

<style>
.executive-header {
    background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
    border-radius: 15px;
    padding: 30px;
    color: white;
    margin-bottom: 30px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}
.stat-card-executive {
    border-radius: 10px;
    padding: 25px;
    background: white;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border-left: 4px solid;
    transition: transform 0.2s;
}
.stat-card-executive:hover {
    transform: translateY(-2px);
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
        <h1 class="mb-2"><i class="bi bi-calendar-event"></i> Exhibition & Event Analytics</h1>
        <p class="mb-0 opacity-75">Attendance tracking, capacity utilization, curator performance, and revenue analysis</p>
    </div>
    
    <!-- Filters -->
    <div class="bg-light rounded p-4 mb-4">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label class="form-label fw-bold">Date From</label>
                <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Date To</label>
                <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>">
            </div>
            <div class="col-md-4 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-warning">
                    <i class="bi bi-funnel"></i> Apply Filters
                </button>
                <a href="exhibition-analytics.php" class="btn btn-outline-secondary">Clear</a>
            </div>
        </form>
    </div>
    
    <!-- Summary Statistics -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="stat-card-executive" style="border-left-color: #ffc107;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted mb-1 small text-uppercase">Total Exhibitions</p>
                        <h3 class="mb-0 text-warning"><?= number_format($total_exhibitions) ?></h3>
                    </div>
                    <i class="bi bi-easel fs-1 text-warning opacity-25"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card-executive" style="border-left-color: #28a745;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted mb-1 small text-uppercase">Total Events</p>
                        <h3 class="mb-0 text-success"><?= number_format($total_events) ?></h3>
                    </div>
                    <i class="bi bi-calendar-check fs-1 text-success opacity-25"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card-executive" style="border-left-color: #007bff;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted mb-1 small text-uppercase">Avg Capacity Utilization</p>
                        <h3 class="mb-0 text-primary"><?= number_format($avg_utilization, 1) ?>%</h3>
                    </div>
                    <i class="bi bi-speedometer2 fs-1 text-primary opacity-25"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Exhibition Attendance Comparison -->
    <div class="chart-card">
        <h5 class="mb-3"><i class="bi bi-bar-chart"></i> Exhibition Attendance Comparison</h5>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Exhibition</th>
                        <th>Curator</th>
                        <th>Dates</th>
                        <th class="text-end">Events</th>
                        <th class="text-end">Total Attendance</th>
                        <th class="text-end">Duration (Days)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($exhibitions as $ex): ?>
                        <tr>
                            <td class="fw-bold"><?= htmlspecialchars($ex['title']) ?></td>
                            <td><?= htmlspecialchars($ex['curator_name']) ?></td>
                            <td class="small">
                                <?= date('M j', strtotime($ex['start_date'])) ?> - 
                                <?= date('M j, Y', strtotime($ex['end_date'])) ?>
                            </td>
                            <td class="text-end"><?= number_format($ex['event_count']) ?></td>
                            <td class="text-end">
                                <span class="badge bg-success"><?= number_format($ex['total_attendance']) ?></span>
                            </td>
                            <td class="text-end"><?= number_format($ex['duration_days']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Event Capacity and Curator Performance -->
    <div class="row">
        <div class="col-md-6">
            <div class="chart-card">
                <h5 class="mb-3"><i class="bi bi-speedometer"></i> Event Capacity Utilization</h5>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Event</th>
                                <th class="text-end">Capacity</th>
                                <th class="text-end">Sold</th>
                                <th class="text-end">Util %</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($events, 0, 10) as $ev): ?>
                                <tr>
                                    <td class="small"><?= htmlspecialchars($ev['name']) ?></td>
                                    <td class="text-end"><?= number_format($ev['capacity']) ?></td>
                                    <td class="text-end"><?= number_format($ev['tickets_sold']) ?></td>
                                    <td class="text-end">
                                        <span class="badge <?= $ev['utilization_pct'] >= 90 ? 'bg-danger' : ($ev['utilization_pct'] >= 70 ? 'bg-warning' : 'bg-success') ?>">
                                            <?= number_format($ev['utilization_pct'], 1) ?>%
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="chart-card">
                <h5 class="mb-3"><i class="bi bi-person-badge"></i> Curator Performance</h5>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Curator</th>
                                <th class="text-end">Exhibitions</th>
                                <th class="text-end">Events</th>
                                <th class="text-end">Avg Duration</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($curators as $cur): ?>
                                <tr>
                                    <td><?= htmlspecialchars($cur['curator_name']) ?></td>
                                    <td class="text-end">
                                        <span class="badge bg-primary"><?= number_format($cur['exhibitions_curated']) ?></span>
                                    </td>
                                    <td class="text-end"><?= number_format($cur['events_hosted']) ?></td>
                                    <td class="text-end"><?= number_format($cur['avg_duration'], 0) ?> days</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- DETAILED BUSINESS INTELLIGENCE SECTION -->

    <!-- Exhibition Performance & Strategic Analysis -->
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="card border-warning">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="bi bi-graph-up-arrow"></i> Exhibition Performance & Insights</h5>
                </div>
                <div class="card-body">
                    <?php
                    // Calculate comprehensive metrics
                    $total_attendance = array_sum(array_column($exhibitions, 'total_attendance'));
                    $avg_attendance_per_exhibition = $total_exhibitions > 0 ? $total_attendance / $total_exhibitions : 0;
                    $avg_events_per_exhibition = $total_exhibitions > 0 ? array_sum(array_column($exhibitions, 'event_count')) / $total_exhibitions : 0;
                    
                    // Find best/worst performers
                    $best_exhibition = !empty($exhibitions) ? $exhibitions[0] : null;
                    $worst_exhibition = !empty($exhibitions) ? end($exhibitions) : null;
                    
                    // Calculate capacity efficiency
                    $over_90_pct = array_filter($events, function($e) { return $e['utilization_pct'] >= 90; });
                    $under_50_pct = array_filter($events, function($e) { return $e['utilization_pct'] < 50; });
                    $optimal_range = array_filter($events, function($e) { return $e['utilization_pct'] >= 70 && $e['utilization_pct'] < 90; });
                    
                    $high_demand_pct = $total_events > 0 ? (count($over_90_pct) / $total_events) * 100 : 0;
                    $underutilized_pct = $total_events > 0 ? (count($under_50_pct) / $total_events) * 100 : 0;
                    ?>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <h6 class="text-warning"><i class="bi bi-bar-chart-line-fill"></i> Performance Metrics</h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <tbody>
                                        <tr>
                                            <td><strong>Total Attendance (Period):</strong></td>
                                            <td class="text-end">
                                                <span class="badge bg-success fs-6"><?= number_format($total_attendance) ?> visitors</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Avg Attendance/Exhibition:</strong></td>
                                            <td class="text-end">
                                                <span class="badge bg-primary fs-6"><?= number_format($avg_attendance_per_exhibition, 0) ?> visitors</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Avg Events/Exhibition:</strong></td>
                                            <td class="text-end">
                                                <span class="badge bg-info fs-6"><?= number_format($avg_events_per_exhibition, 1) ?> events</span>
                                            </td>
                                        </tr>
                                        <tr class="table-light">
                                            <td><strong>Avg Capacity Utilization:</strong></td>
                                            <td class="text-end">
                                                <span class="badge bg-<?= $avg_utilization >= 75 ? 'success' : ($avg_utilization >= 60 ? 'warning' : 'danger') ?> fs-6">
                                                    <?= number_format($avg_utilization, 1) ?>%
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>High-Demand Events (≥90%):</strong></td>
                                            <td class="text-end">
                                                <span class="badge bg-danger fs-6"><?= count($over_90_pct) ?> events (<?= number_format($high_demand_pct, 1) ?>%)</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Underutilized Events (<50%):</strong></td>
                                            <td class="text-end">
                                                <span class="badge bg-warning text-dark fs-6"><?= count($under_50_pct) ?> events (<?= number_format($underutilized_pct, 1) ?>%)</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <h6 class="text-warning"><i class="bi bi-lightbulb-fill"></i> Strategic Insights</h6>
                            <div class="alert alert-light border">
                                <ul class="mb-0 small">
                                    <?php if ($avg_utilization < 60): ?>
                                    <li class="mb-2 text-danger">
                                        <i class="bi bi-exclamation-triangle-fill"></i>
                                        <strong>Capacity Alert:</strong> Average utilization at <?= number_format($avg_utilization, 1) ?>% 
                                        indicates oversized venues or weak marketing. Review capacity planning.
                                    </li>
                                    <?php elseif ($avg_utilization >= 85): ?>
                                    <li class="mb-2 text-success">
                                        <i class="bi bi-check-circle-fill"></i>
                                        <strong>Excellent Utilization:</strong> <?= number_format($avg_utilization, 1) ?>% average shows 
                                        strong demand and efficient capacity management.
                                    </li>
                                    <?php endif; ?>
                                    
                                    <?php if ($high_demand_pct > 30): ?>
                                    <li class="mb-2 text-warning">
                                        <i class="bi bi-people-fill"></i>
                                        <strong>Capacity Constraint:</strong> <?= number_format($high_demand_pct, 1) ?>% of events are near/at capacity. 
                                        Consider adding sessions or larger venues for popular exhibitions.
                                    </li>
                                    <?php endif; ?>
                                    
                                    <?php if ($underutilized_pct > 25): ?>
                                    <li class="mb-2 text-danger">
                                        <i class="bi bi-exclamation-circle-fill"></i>
                                        <strong>Underperformance:</strong> <?= number_format($underutilized_pct, 1) ?>% of events below 50% capacity. 
                                        Review marketing strategy, timing, and pricing for poorly attended events.
                                    </li>
                                    <?php endif; ?>
                                    
                                    <?php if ($best_exhibition): ?>
                                    <li class="mb-2 text-success">
                                        <i class="bi bi-trophy-fill"></i>
                                        <strong>Top Performer:</strong> "<?= htmlspecialchars($best_exhibition['title']) ?>" 
                                        drew <?= number_format($best_exhibition['total_attendance']) ?> visitors. 
                                        Analyze success factors for future exhibitions.
                                    </li>
                                    <?php endif; ?>
                                    
                                    <?php if ($avg_events_per_exhibition < 2): ?>
                                    <li class="mb-2 text-info">
                                        <i class="bi bi-calendar-plus"></i>
                                        <strong>Programming Opportunity:</strong> Average <?= number_format($avg_events_per_exhibition, 1) ?> 
                                        events per exhibition. Consider more programming to maximize attendance.
                                    </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Exhibition Performance Table -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="bi bi-table"></i> Comprehensive Exhibition Analysis</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Rank</th>
                            <th>Exhibition Title</th>
                            <th>Curator</th>
                            <th>Duration</th>
                            <th class="text-end">Events</th>
                            <th class="text-end">Total Attendance</th>
                            <th class="text-end">Daily Avg</th>
                            <th class="text-end">Per Event Avg</th>
                            <th>Performance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $rank = 1;
                        foreach ($exhibitions as $ex): 
                            $daily_avg = $ex['duration_days'] > 0 ? $ex['total_attendance'] / $ex['duration_days'] : 0;
                            $per_event_avg = $ex['event_count'] > 0 ? $ex['total_attendance'] / $ex['event_count'] : 0;
                            
                            // Performance rating
                            if ($ex['total_attendance'] >= $avg_attendance_per_exhibition * 1.5) {
                                $performance = ['label' => 'Excellent', 'class' => 'success'];
                            } elseif ($ex['total_attendance'] >= $avg_attendance_per_exhibition * 0.8) {
                                $performance = ['label' => 'Good', 'class' => 'primary'];
                            } elseif ($ex['total_attendance'] >= $avg_attendance_per_exhibition * 0.5) {
                                $performance = ['label' => 'Fair', 'class' => 'warning'];
                            } else {
                                $performance = ['label' => 'Needs Improvement', 'class' => 'danger'];
                            }
                        ?>
                            <tr>
                                <td>
                                    <?php if ($rank <= 3): ?>
                                        <span class="badge bg-warning text-dark">#<?= $rank ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">#<?= $rank ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?= htmlspecialchars($ex['title']) ?></strong></td>
                                <td><?= htmlspecialchars($ex['curator_name']) ?></td>
                                <td>
                                    <?= date('M j', strtotime($ex['start_date'])) ?> - <?= date('M j, Y', strtotime($ex['end_date'])) ?>
                                    <br><small class="text-muted">(<?= $ex['duration_days'] ?> days)</small>
                                </td>
                                <td class="text-end"><?= number_format($ex['event_count']) ?></td>
                                <td class="text-end"><strong class="text-success"><?= number_format($ex['total_attendance']) ?></strong></td>
                                <td class="text-end"><?= number_format($daily_avg, 1) ?>/day</td>
                                <td class="text-end"><?= number_format($per_event_avg, 0) ?>/event</td>
                                <td>
                                    <span class="badge bg-<?= $performance['class'] ?>">
                                        <?= $performance['label'] ?>
                                    </span>
                                </td>
                            </tr>
                        <?php 
                        $rank++;
                        endforeach; ?>
                    </tbody>
                    <tfoot class="table-info fw-bold">
                        <tr>
                            <td colspan="4">TOTALS / AVERAGES</td>
                            <td class="text-end"><?= number_format(array_sum(array_column($exhibitions, 'event_count'))) ?></td>
                            <td class="text-end"><?= number_format($total_attendance) ?></td>
                            <td class="text-end" colspan="3">Avg: <?= number_format($avg_attendance_per_exhibition, 0) ?> per exhibition</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- Event Capacity Analysis with Recommendations -->
    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-speedometer2"></i> Capacity Utilization Breakdown</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Event Name</th>
                                    <th>Exhibition</th>
                                    <th>Date</th>
                                    <th class="text-end">Capacity</th>
                                    <th class="text-end">Sold</th>
                                    <th class="text-end">Available</th>
                                    <th class="text-end">Utilization</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($events as $ev): 
                                    $available = $ev['capacity'] - $ev['tickets_sold'];
                                    $status = $ev['utilization_pct'] >= 95 ? ['label' => 'Sold Out', 'class' => 'danger'] :
                                             ($ev['utilization_pct'] >= 80 ? ['label' => 'High Demand', 'class' => 'warning'] :
                                             ($ev['utilization_pct'] >= 50 ? ['label' => 'Good', 'class' => 'success'] :
                                             ['label' => 'Low Attendance', 'class' => 'secondary']));
                                ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($ev['name']) ?></strong></td>
                                        <td><small class="text-muted"><?= htmlspecialchars($ev['exhibition_name'] ?? 'N/A') ?></small></td>
                                        <td><?= date('M j, Y', strtotime($ev['event_date'])) ?></td>
                                        <td class="text-end"><?= number_format($ev['capacity']) ?></td>
                                        <td class="text-end text-primary"><?= number_format($ev['tickets_sold']) ?></td>
                                        <td class="text-end <?= $available <= 10 ? 'text-danger' : 'text-success' ?>">
                                            <?= number_format($available) ?>
                                        </td>
                                        <td class="text-end">
                                            <div class="progress" style="height: 20px; min-width: 80px;">
                                                <div class="progress-bar bg-<?= $status['class'] ?>" 
                                                     style="width: <?= min(100, $ev['utilization_pct']) ?>%">
                                                    <?= number_format($ev['utilization_pct'], 1) ?>%
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $status['class'] ?>"><?= $status['label'] ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card h-100 border-info">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-clipboard-check"></i> Capacity Insights</h5>
                </div>
                <div class="card-body">
                    <h6 class="text-info">Utilization Distribution:</h6>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span><strong>Sold Out (≥95%):</strong></span>
                            <span class="badge bg-danger"><?= count(array_filter($events, fn($e) => $e['utilization_pct'] >= 95)) ?> events</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span><strong>Near Capacity (80-94%):</strong></span>
                            <span class="badge bg-warning text-dark"><?= count(array_filter($events, fn($e) => $e['utilization_pct'] >= 80 && $e['utilization_pct'] < 95)) ?> events</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span><strong>Optimal (50-79%):</strong></span>
                            <span class="badge bg-success"><?= count($optimal_range) ?> events</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span><strong>Underutilized (<50%):</strong></span>
                            <span class="badge bg-secondary"><?= count($under_50_pct) ?> events</span>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <h6 class="text-info mt-3">Key Metrics:</h6>
                    <ul class="small mb-0">
                        <li class="mb-2">
                            <strong>Total Capacity Available:</strong> 
                            <?= number_format(array_sum(array_column($events, 'capacity'))) ?> seats
                        </li>
                        <li class="mb-2">
                            <strong>Total Tickets Sold:</strong> 
                            <?= number_format(array_sum(array_column($events, 'tickets_sold'))) ?> tickets
                        </li>
                        <li class="mb-2">
                            <strong>Unutilized Capacity:</strong> 
                            <?= number_format(array_sum(array_column($events, 'capacity')) - array_sum(array_column($events, 'tickets_sold'))) ?> seats
                        </li>
                        <li class="mb-2">
                            <strong>Revenue Opportunity Loss:</strong> 
                            <span class="text-danger">
                                <?= number_format((array_sum(array_column($events, 'capacity')) - array_sum(array_column($events, 'tickets_sold'))) * 15) ?> potential visitors
                            </span>
                        </li>
                    </ul>
                    
                    <hr>
                    
                    <h6 class="text-info">Recommendations:</h6>
                    <ul class="small mb-0">
                        <?php if ($high_demand_pct > 20): ?>
                        <li class="mb-2">Add additional sessions for high-demand events</li>
                        <?php endif; ?>
                        <?php if ($underutilized_pct > 30): ?>
                        <li class="mb-2">Reduce capacity or improve marketing for underperforming events</li>
                        <?php endif; ?>
                        <li class="mb-2">Implement dynamic pricing based on demand</li>
                        <li class="mb-2">Create waitlists for sold-out events</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Curator Performance Detailed Analysis -->
    <div class="card border-secondary mb-4">
        <div class="card-header bg-secondary text-white">
            <h5 class="mb-0"><i class="bi bi-award"></i> Curator Performance Dashboard</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Curator Name</th>
                            <th class="text-end">Exhibitions</th>
                            <th class="text-end">Total Events</th>
                            <th class="text-end">Avg Duration</th>
                            <th class="text-end">Events per Exhibition</th>
                            <th>Productivity</th>
                            <th>Recommendation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $avg_exhibitions_per_curator = count($curators) > 0 ? array_sum(array_column($curators, 'exhibitions_curated')) / count($curators) : 0;
                        foreach ($curators as $cur): 
                            $events_per_ex = $cur['exhibitions_curated'] > 0 ? $cur['events_hosted'] / $cur['exhibitions_curated'] : 0;
                            
                            if ($cur['exhibitions_curated'] >= $avg_exhibitions_per_curator * 1.2) {
                                $productivity = ['label' => 'High', 'class' => 'success'];
                                $recommendation = 'Excellent productivity. Consider mentoring junior curators.';
                            } elseif ($cur['exhibitions_curated'] >= $avg_exhibitions_per_curator * 0.8) {
                                $productivity = ['label' => 'Good', 'class' => 'primary'];
                                $recommendation = 'Solid performance. Maintain current workload.';
                            } else {
                                $productivity = ['label' => 'Low', 'class' => 'warning'];
                                $recommendation = 'Consider additional exhibition assignments.';
                            }
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($cur['curator_name']) ?></strong></td>
                                <td class="text-end"><span class="badge bg-primary"><?= $cur['exhibitions_curated'] ?></span></td>
                                <td class="text-end"><?= number_format($cur['events_hosted']) ?></td>
                                <td class="text-end"><?= number_format($cur['avg_duration'], 0) ?> days</td>
                                <td class="text-end"><?= number_format($events_per_ex, 1) ?></td>
                                <td>
                                    <span class="badge bg-<?= $productivity['class'] ?>"><?= $productivity['label'] ?></span>
                                </td>
                                <td><small class="text-muted"><?= $recommendation ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>