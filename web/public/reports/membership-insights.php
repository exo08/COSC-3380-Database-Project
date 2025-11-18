<?php
// debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/permissions.php';

$page_title = 'Membership & Visitor Insights';

// Only admin can access
if ($_SESSION['user_type'] !== 'admin') {
    header('Location: index.php?error=access_denied');
    exit;
}

try {
    $db = db();

    // Get filter parameters with validation
    $date_from = $_GET['date_from'] ?? date('Y-01-01');
    $date_to = $_GET['date_to'] ?? date('Y-m-d');
    $membership_type = $_GET['membership_type'] ?? '';
    
    // Validate dates
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
        throw new Exception("Invalid date format");
    }

    // Demographics Breakdown 
    $demographics_query = "
        SELECT 
            CASE 
                WHEN m.member_id IS NOT NULL THEN 'Member'
                ELSE 'Visitor'
            END as customer_type,
            CASE 
                WHEN COALESCE(m.is_student, v.is_student) = 1 THEN 'Student'
                ELSE 'Regular'
            END as student_status,
            COUNT(DISTINCT t.ticket_id) as total_tickets,
            SUM(t.quantity) as total_attendees,
            AVG(t.quantity) as avg_party_size
        FROM TICKET t
        LEFT JOIN MEMBER m ON t.member_id = m.member_id
        LEFT JOIN VISITOR v ON t.visitor_id = v.visitor_id
        INNER JOIN EVENT e ON t.event_id = e.event_id
        WHERE e.event_date BETWEEN ? AND ?
        GROUP BY customer_type, student_status
        ORDER BY customer_type, student_status
    ";

    $stmt = $db->prepare($demographics_query);
    if (!$stmt) {
        throw new Exception("Failed to prepare demographics query: " . $db->error);
    }
    $stmt->bind_param('ss', $date_from, $date_to);
    $stmt->execute();
    $demographics = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Membership Tier Distribution
    $tier_distribution_query = "
        SELECT 
            membership_type,
            COUNT(*) as member_count,
            COUNT(CASE WHEN expiration_date >= CURDATE() THEN 1 END) as active_count,
            COUNT(CASE WHEN expiration_date < CURDATE() THEN 1 END) as expired_count,
            AVG(DATEDIFF(expiration_date, start_date)) as avg_duration_days
        FROM MEMBER
    ";
    
    if ($membership_type) {
        $tier_distribution_query .= " WHERE membership_type = ?";
    }
    
    $tier_distribution_query .= "
        GROUP BY membership_type
        ORDER BY 
            CASE membership_type
                WHEN 'family' THEN 1
                WHEN 'individual' THEN 2
                WHEN 'student' THEN 3
            END
    ";

    if ($membership_type) {
        $stmt = $db->prepare($tier_distribution_query);
        if (!$stmt) {
            throw new Exception("Failed to prepare tier distribution query: " . $db->error);
        }
        $stmt->bind_param('s', $membership_type);
        $stmt->execute();
        $tier_distribution = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $result = $db->query($tier_distribution_query);
        if (!$result) {
            throw new Exception("Tier distribution query failed: " . $db->error);
        }
        $tier_distribution = $result->fetch_all(MYSQLI_ASSOC);
    }

    // Visitor Frequency Analysis
    $frequency_query = "
        SELECT 
            v.visitor_id,
            v.first_name,
            v.last_name,
            v.email,
            COUNT(DISTINCT t.ticket_id) as visit_count,
            MIN(e.event_date) as first_visit,
            MAX(e.event_date) as last_visit,
            SUM(t.quantity) as total_attendees_brought
        FROM VISITOR v
        INNER JOIN TICKET t ON v.visitor_id = t.visitor_id
        INNER JOIN EVENT e ON t.event_id = e.event_id
        WHERE e.event_date BETWEEN ? AND ?
        GROUP BY v.visitor_id, v.first_name, v.last_name, v.email
        HAVING visit_count >= 2
        ORDER BY visit_count DESC
        LIMIT 20
    ";

    $stmt = $db->prepare($frequency_query);
    if (!$stmt) {
        throw new Exception("Failed to prepare frequency query: " . $db->error);
    }
    $stmt->bind_param('ss', $date_from, $date_to);
    $stmt->execute();
    $frequent_visitors = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Member Activity Analysis 
    $activity_query = "
        SELECT 
            m.member_id,
            CONCAT(m.first_name, ' ', m.last_name) as member_name,
            m.membership_type,
            m.start_date,
            m.expiration_date,
            COUNT(DISTINCT t.ticket_id) as tickets_purchased,
            COUNT(DISTINCT s.sale_id) as shop_purchases,
            SUM(t.quantity) as total_event_attendance,
            COALESCE(SUM(s.discount_amount), 0) as total_discounts_used,
            DATEDIFF(m.expiration_date, CURDATE()) as days_until_expiration
        FROM MEMBER m
        LEFT JOIN TICKET t ON m.member_id = t.member_id
        LEFT JOIN SALE s ON m.member_id = s.member_id
        WHERE m.expiration_date >= CURDATE()
        GROUP BY m.member_id, m.first_name, m.last_name, m.membership_type, m.start_date, m.expiration_date
        HAVING (tickets_purchased > 0 OR shop_purchases > 0)
        ORDER BY total_event_attendance DESC, total_discounts_used DESC
        LIMIT 25
    ";

    $result = $db->query($activity_query);
    if (!$result) {
        throw new Exception("Activity query failed: " . $db->error);
    }
    $active_members = $result->fetch_all(MYSQLI_ASSOC);

    // Expiration Forecasting (Next 90 Days)
    $expiration_forecast_query = "
        SELECT 
            CASE 
                WHEN DATEDIFF(expiration_date, CURDATE()) <= 30 THEN '0-30 Days'
                WHEN DATEDIFF(expiration_date, CURDATE()) <= 60 THEN '31-60 Days'
                WHEN DATEDIFF(expiration_date, CURDATE()) <= 90 THEN '61-90 Days'
            END as expiration_window,
            membership_type,
            COUNT(*) as member_count,
            SUM(CASE WHEN is_student = 1 THEN 1 ELSE 0 END) as student_count
        FROM MEMBER
        WHERE expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)
        GROUP BY expiration_window, membership_type
        ORDER BY 
            CASE expiration_window
                WHEN '0-30 Days' THEN 1
                WHEN '31-60 Days' THEN 2
                WHEN '61-90 Days' THEN 3
            END,
            membership_type
    ";

    $result = $db->query($expiration_forecast_query);
    if (!$result) {
        throw new Exception("Expiration forecast query failed: " . $db->error);
    }
    $expiration_forecast = $result->fetch_all(MYSQLI_ASSOC);

    // Summary Statistics
    $result = $db->query("SELECT COUNT(*) as count FROM MEMBER WHERE expiration_date >= CURDATE()");
    if (!$result) {
        throw new Exception("Failed to get active members count: " . $db->error);
    }
    $total_active_members = $result->fetch_assoc()['count'];
    
    $total_visitors = count($frequent_visitors);
    $avg_visit_frequency = !empty($frequent_visitors) ? array_sum(array_column($frequent_visitors, 'visit_count')) / count($frequent_visitors) : 0;
    
    $result = $db->query("SELECT COUNT(*) as count FROM MEMBER WHERE expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
    if (!$result) {
        throw new Exception("Failed to get expiring members count: " . $db->error);
    }
    $expiring_soon = $result->fetch_assoc()['count'];

    // Handle CSV export
    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="membership-insights-' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        fputcsv($output, ['Demographics Breakdown']);
        fputcsv($output, ['Customer Type', 'Student Status', 'Total Tickets', 'Total Attendees', 'Avg Party Size']);
        foreach ($demographics as $row) {
            fputcsv($output, [$row['customer_type'], $row['student_status'], $row['total_tickets'], $row['total_attendees'], number_format($row['avg_party_size'], 2)]);
        }
        
        fputcsv($output, []);
        fputcsv($output, ['Tier Distribution']);
        fputcsv($output, ['Membership Type', 'Total Members', 'Active', 'Expired', 'Avg Duration (Days)']);
        foreach ($tier_distribution as $row) {
            fputcsv($output, [$row['membership_type'], $row['member_count'], $row['active_count'], $row['expired_count'], number_format($row['avg_duration_days'], 1)]);
        }
        
        fputcsv($output, []);
        fputcsv($output, ['Frequent Visitors']);
        fputcsv($output, ['Name', 'Email', 'Visit Count', 'Total Attendees Brought', 'First Visit', 'Last Visit']);
        foreach ($frequent_visitors as $row) {
            fputcsv($output, [
                $row['first_name'] . ' ' . $row['last_name'],
                $row['email'],
                $row['visit_count'],
                $row['total_attendees_brought'],
                $row['first_visit'],
                $row['last_visit']
            ]);
        }
        
        fclose($output);
        exit;
    }

} catch (Exception $e) {
    error_log("Membership Insights Error: " . $e->getMessage());
    $error_message = $e->getMessage();
}

include __DIR__ . '/../templates/layout_header.php';

// Display error if occurred
if (isset($error_message)): ?>
    <div class="container-fluid">
        <div class="alert alert-danger">
            <h4><i class="bi bi-exclamation-triangle"></i> Error</h4>
            <p>An error occurred while generating the report: <?= htmlspecialchars($error_message) ?></p>
            <a href="index.php" class="btn btn-primary">Back to Reports</a>
        </div>
    </div>
    <?php include __DIR__ . '/../templates/layout_footer.php'; ?>
    <?php exit; ?>
<?php endif; ?>

<style>
.stat-card {
    transition: transform 0.2s, box-shadow 0.2s;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 12px rgba(0,0,0,0.1);
}

.alert-warning-custom {
    background-color: #fff3cd;
    border-left: 4px solid #ffc107;
}
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="index.php" class="btn btn-outline-secondary mb-2">
                <i class="bi bi-arrow-left"></i> Back to Reports
            </a>
            <h1 class="mb-0"><i class="bi bi-people-fill text-info"></i> Membership & Visitor Insights</h1>
            <p class="text-muted">Demographics, retention analysis & visitor patterns</p>
        </div>
        <div>
            <a href="?export=csv&<?= http_build_query(['date_from' => $date_from, 'date_to' => $date_to, 'membership_type' => $membership_type]) ?>" class="btn btn-success">
                <i class="bi bi-file-earmark-spreadsheet"></i> Export to CSV
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="bi bi-funnel"></i> Date Range & Filters</h5>
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
                <div class="col-md-3">
                    <label class="form-label">Membership Type</label>
                    <select name="membership_type" class="form-select">
                        <option value="">All Types</option>
                        <option value="student" <?= $membership_type === 'student' ? 'selected' : '' ?>>Student</option>
                        <option value="individual" <?= $membership_type === 'individual' ? 'selected' : '' ?>>Individual</option>
                        <option value="family" <?= $membership_type === 'family' ? 'selected' : '' ?>>Family</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="bi bi-search"></i> Apply Filters
                    </button>
                    <a href="membership-insights.php" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card stat-card border-info h-100">
                <div class="card-body text-center">
                    <i class="bi bi-person-badge text-info" style="font-size: 2.5rem;"></i>
                    <h3 class="mb-0 mt-2"><?= number_format($total_active_members) ?></h3>
                    <p class="text-muted mb-0">Active Members</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card border-success h-100">
                <div class="card-body text-center">
                    <i class="bi bi-people text-success" style="font-size: 2.5rem;"></i>
                    <h3 class="mb-0 mt-2"><?= number_format($total_visitors) ?></h3>
                    <p class="text-muted mb-0">Frequent Visitors</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card border-primary h-100">
                <div class="card-body text-center">
                    <i class="bi bi-arrow-repeat text-primary" style="font-size: 2.5rem;"></i>
                    <h3 class="mb-0 mt-2"><?= number_format($avg_visit_frequency, 1) ?></h3>
                    <p class="text-muted mb-0">Avg Visits per Visitor</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card border-warning h-100">
                <div class="card-body text-center">
                    <i class="bi bi-alarm text-warning" style="font-size: 2.5rem;"></i>
                    <h3 class="mb-0 mt-2"><?= number_format($expiring_soon) ?></h3>
                    <p class="text-muted mb-0">Expiring in 30 Days</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Demographics & Tier Distribution -->
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-pie-chart"></i> Demographics Breakdown</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($demographics)): ?>
                    <div style="height: 250px;">
                        <canvas id="demographicsChart"></canvas>
                    </div>
                    <hr>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th class="text-end">Tickets</th>
                                    <th class="text-end">Attendees</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($demographics as $demo): ?>
                                    <tr>
                                        <td><span class="badge bg-<?= $demo['customer_type'] === 'Member' ? 'primary' : 'secondary' ?>"><?= htmlspecialchars($demo['customer_type']) ?></span></td>
                                        <td><?= htmlspecialchars($demo['student_status']) ?></td>
                                        <td class="text-end"><?= number_format($demo['total_tickets']) ?></td>
                                        <td class="text-end"><strong><?= number_format($demo['total_attendees']) ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                        <p class="text-muted text-center py-5">No demographic data available for the selected date range.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-diagram-3"></i> Membership Tier Distribution</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($tier_distribution)): ?>
                    <div style="height: 250px;">
                        <canvas id="tierChart"></canvas>
                    </div>
                    <hr>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>Tier</th>
                                    <th class="text-end">Total</th>
                                    <th class="text-end">Active</th>
                                    <th class="text-end">Expired</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tier_distribution as $tier): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars(ucfirst($tier['membership_type'])) ?></strong></td>
                                        <td class="text-end"><?= number_format($tier['member_count']) ?></td>
                                        <td class="text-end text-success"><?= number_format($tier['active_count']) ?></td>
                                        <td class="text-end text-danger"><?= number_format($tier['expired_count']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                        <p class="text-muted text-center py-5">No membership tier data available.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Expiration Forecast -->
    <?php if (!empty($expiration_forecast)): ?>
    <div class="alert alert-warning-custom mb-4" role="alert">
        <h5 class="alert-heading"><i class="bi bi-exclamation-triangle"></i> Upcoming Membership Expirations</h5>
        <p class="mb-3">Members expiring in the next 90 days - consider renewal campaigns!</p>
        <div class="row g-2">
            <?php 
            $windows = ['0-30 Days' => 0, '31-60 Days' => 0, '61-90 Days' => 0];
            foreach ($expiration_forecast as $exp) {
                if (isset($windows[$exp['expiration_window']])) {
                    $windows[$exp['expiration_window']] += $exp['member_count'];
                }
            }
            foreach ($windows as $window => $count):
            ?>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body text-center">
                            <h4 class="mb-0"><?= $count ?></h4>
                            <small class="text-muted"><?= $window ?></small>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Frequent Visitors & Active Members -->
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0"><i class="bi bi-star"></i> Top 20 Frequent Visitors</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($frequent_visitors)): ?>
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Name</th>
                                    <th class="text-end">Visits</th>
                                    <th class="text-end">Attendees</th>
                                    <th>Last Visit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($frequent_visitors as $visitor): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($visitor['first_name'] . ' ' . $visitor['last_name']) ?></strong></td>
                                        <td class="text-end"><span class="badge bg-info"><?= $visitor['visit_count'] ?></span></td>
                                        <td class="text-end"><?= number_format($visitor['total_attendees_brought']) ?></td>
                                        <td class="small"><?= date('M j, Y', strtotime($visitor['last_visit'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                        <p class="text-muted text-center py-5">No frequent visitors found for the selected date range.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="bi bi-activity"></i> Top 25 Most Active Members</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($active_members)): ?>
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Name</th>
                                    <th>Tier</th>
                                    <th class="text-end">Events</th>
                                    <th class="text-end">Purchases</th>
                                    <th class="text-end">Savings</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($active_members as $member): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($member['member_name']) ?></strong></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($member['membership_type']) ?></span></td>
                                        <td class="text-end"><?= number_format($member['tickets_purchased']) ?></td>
                                        <td class="text-end"><?= number_format($member['shop_purchases']) ?></td>
                                        <td class="text-end text-success">$<?= number_format($member['total_discounts_used'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                        <p class="text-muted text-center py-5">No active member data available.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
<?php if (!empty($demographics)): ?>
// Demographics Chart
const demoCtx = document.getElementById('demographicsChart').getContext('2d');
new Chart(demoCtx, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_map(function($d) { 
            return $d['customer_type'] . ' - ' . $d['student_status']; 
        }, $demographics)) ?>,
        datasets: [{
            data: <?= json_encode(array_column($demographics, 'total_attendees')) ?>,
            backgroundColor: ['#0dcaf0', '#6ea8fe', '#6c757d', '#adb5bd']
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
<?php endif; ?>

<?php if (!empty($tier_distribution)): ?>
// Tier Distribution Chart
const tierCtx = document.getElementById('tierChart').getContext('2d');
new Chart(tierCtx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_map('ucfirst', array_column($tier_distribution, 'membership_type'))) ?>,
        datasets: [
            {
                label: 'Active',
                data: <?= json_encode(array_column($tier_distribution, 'active_count')) ?>,
                backgroundColor: '#198754'
            },
            {
                label: 'Expired',
                data: <?= json_encode(array_column($tier_distribution, 'expired_count')) ?>,
                backgroundColor: '#dc3545'
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            x: { stacked: true },
            y: { stacked: true, beginAtZero: true }
        }
    }
});
<?php endif; ?>
</script>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>