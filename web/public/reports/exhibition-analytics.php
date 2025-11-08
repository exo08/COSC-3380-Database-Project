<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/permissions.php';

$page_title = 'Exhibition & Event Analytics';
if ($_SESSION['user_type'] !== 'admin') { header('Location: index.php'); exit; }
$db = db();

// Filters
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-6 months'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');

// Exhibition Attendance Comparison
$exhibitions = $db->query("
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
    WHERE E.start_date BETWEEN '$date_from' AND '$date_to'
    GROUP BY E.exhibition_id
    ORDER BY total_attendance DESC
")->fetch_all(MYSQLI_ASSOC);

// Event Capacity Utilization
$events = $db->query("
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
    WHERE EV.event_date BETWEEN '$date_from' AND '$date_to'
    GROUP BY EV.event_id
    ORDER BY utilization_pct DESC
")->fetch_all(MYSQLI_ASSOC);

// Curator Performance
$curators = $db->query("
    SELECT 
        S.staff_id,
        S.name AS curator_name,
        COUNT(E.exhibition_id) AS exhibitions_curated,
        AVG(DATEDIFF(E.end_date, E.start_date)) AS avg_duration,
        COUNT(DISTINCT EV.event_id) AS events_hosted
    FROM STAFF S
    JOIN EXHIBITION E ON S.staff_id = E.curator_id
    LEFT JOIN EVENT EV ON E.exhibition_id = EV.exhibition_id
    WHERE E.start_date BETWEEN '$date_from' AND '$date_to'
    GROUP BY S.staff_id
    ORDER BY exhibitions_curated DESC
")->fetch_all(MYSQLI_ASSOC);

// Revenue per Exhibition cannot be calculated as TICKET table has no price field , need a separate TICKET_PRICE table or price field in EVENT table

$total_exhibitions = count($exhibitions);
$total_events = count($events);
$avg_utilization = count($events) > 0 ? array_sum(array_column($events, 'utilization_pct')) / count($events) : 0;

include __DIR__ . '/../templates/layout_header.php';
?>

<style>
.executive-header { background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%); border-radius: 15px; padding: 30px; color: white; margin-bottom: 30px; }
.stat-card-executive { border-radius: 10px; padding: 25px; background: white; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border-left: 4px solid; }
.chart-card { background: white; border-radius: 10px; padding: 25px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
</style>

<div class="container-fluid">
    <div class="executive-header">
        <a href="index.php" class="btn btn-light btn-sm mb-3"><i class="bi bi-arrow-left"></i> Back</a>
        <h1 class="mb-2"><i class="bi bi-calendar-event"></i> Exhibition & Event Analytics</h1>
        <p class="mb-0 opacity-75">Attendance tracking, capacity utilization, curator performance, and revenue analysis</p>
    </div>
    
    <div class="bg-light rounded p-4 mb-4">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label class="form-label fw-bold">Date From</label>
                <input type="date" name="date_from" class="form-control" value="<?= $date_from ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Date To</label>
                <input type="date" name="date_to" class="form-control" value="<?= $date_to ?>">
            </div>
            <div class="col-md-4 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-warning"><i class="bi bi-funnel"></i> Apply</button>
                <a href="exhibition-analytics.php" class="btn btn-outline-secondary">Clear</a>
            </div>
        </form>
    </div>
    
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="stat-card-executive" style="border-left-color: #ffc107;">
                <p class="text-muted mb-1 small">TOTAL EXHIBITIONS</p>
                <h3 class="mb-0 text-warning"><?= $total_exhibitions ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card-executive" style="border-left-color: #28a745;">
                <p class="text-muted mb-1 small">TOTAL EVENTS</p>
                <h3 class="mb-0 text-success"><?= $total_events ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card-executive" style="border-left-color: #007bff;">
                <p class="text-muted mb-1 small">AVG CAPACITY UTIL</p>
                <h3 class="mb-0 text-primary"><?= number_format($avg_utilization, 1) ?>%</h3>
            </div>
        </div>
    </div>
    
    <div class="chart-card">
        <h5 class="mb-3"><i class="bi bi-bar-chart"></i> Exhibition Attendance Comparison</h5>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Exhibition</th>
                        <th>Curator</th>
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
                            <td class="text-end"><?= $ex['event_count'] ?></td>
                            <td class="text-end"><span class="badge bg-success"><?= number_format($ex['total_attendance']) ?></span></td>
                            <td class="text-end"><?= $ex['duration_days'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
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
                                    <td class="text-end"><?= $ev['capacity'] ?></td>
                                    <td class="text-end"><?= $ev['tickets_sold'] ?></td>
                                    <td class="text-end">
                                        <span class="badge <?= $ev['utilization_pct'] >= 90 ? 'bg-danger' : ($ev['utilization_pct'] >= 70 ? 'bg-warning' : 'bg-success') ?>">
                                            <?= $ev['utilization_pct'] ?>%
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
                                    <td class="text-end"><span class="badge bg-primary"><?= $cur['exhibitions_curated'] ?></span></td>
                                    <td class="text-end"><?= $cur['events_hosted'] ?></td>
                                    <td class="text-end"><?= number_format($cur['avg_duration'], 0) ?> days</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>