<?php
// Membership & Visitor Insights Report
// Comprehensive analysis of membership demographics, retention, and visitor engagement

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
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-12 months'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$membership_type_filter = $_GET['membership_type'] ?? '';

// Membership pricing and names
$membership_prices = [
    1 => 45,   // Student
    2 => 75,   // Individual
    3 => 125,  // Family
    4 => 250,  // Patron
    5 => 500   // Benefactor
];

$membership_names = [
    1 => 'Student',
    2 => 'Individual',
    3 => 'Family',
    4 => 'Patron',
    5 => 'Benefactor'
];

// ========== MEMBERSHIP STATISTICS ==========
// Active members (not expired)
$active_members_query = "
    SELECT COUNT(*) as count
    FROM MEMBER
    WHERE expiration_date >= CURDATE()
";
$total_active_members = $db->query($active_members_query)->fetch_assoc()['count'];

// Members expiring in 30 days
$expiring_query = "
    SELECT COUNT(*) as count
    FROM MEMBER
    WHERE expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
";
$expiring_soon = $db->query($expiring_query)->fetch_assoc()['count'];

// ========== MEMBERSHIP TIER DISTRIBUTION ==========
$tier_query = "
    SELECT 
        membership_type,
        is_student,
        COUNT(*) as member_count,
        SUM(CASE WHEN expiration_date >= CURDATE() THEN 1 ELSE 0 END) as active_count,
        SUM(CASE WHEN auto_renew = 1 THEN 1 ELSE 0 END) as auto_renew_count
    FROM MEMBER
    GROUP BY membership_type, is_student
    ORDER BY membership_type
";
$tier_distribution = $db->query($tier_query)->fetch_all(MYSQLI_ASSOC);

// Get individual members for each tier
$members_by_tier = [];
foreach ($tier_distribution as $tier) {
    $key = $tier['membership_type'] . '_' . $tier['is_student'];
    
    $members_query = "
        SELECT 
            member_id,
            CONCAT(first_name, ' ', last_name) as member_name,
            email,
            phone,
            start_date,
            expiration_date,
            auto_renew,
            CASE 
                WHEN expiration_date < CURDATE() THEN 'Expired'
                WHEN DATEDIFF(expiration_date, CURDATE()) <= 30 THEN 'Expiring Soon'
                ELSE 'Active'
            END as status
        FROM MEMBER
        WHERE membership_type = ? AND is_student = ?
        ORDER BY expiration_date DESC
    ";
    
    $stmt = $db->prepare($members_query);
    $stmt->bind_param("ii", $tier['membership_type'], $tier['is_student']);
    $stmt->execute();
    $members_by_tier[$key] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// ========== NEW MEMBERSHIPS OVER TIME ==========
$new_members_query = "
    SELECT 
        DATE_FORMAT(start_date, '%Y-%m') as period,
        membership_type,
        COUNT(*) as new_members
    FROM MEMBER
    WHERE start_date BETWEEN ? AND ?
    GROUP BY period, membership_type
    ORDER BY period
";

$stmt = $db->prepare($new_members_query);
$stmt->bind_param("ss", $date_from, $date_to);
$stmt->execute();
$new_members_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ========== VISITOR ANALYSIS ==========
$visitor_query = "
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
    GROUP BY v.visitor_id
    HAVING visit_count >= 2
    ORDER BY visit_count DESC
    LIMIT 50
";

$stmt = $db->prepare($visitor_query);
$stmt->bind_param("ss", $date_from, $date_to);
$stmt->execute();
$frequent_visitors = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$total_visitors = count($frequent_visitors);
$avg_visit_frequency = $total_visitors > 0 ? array_sum(array_column($frequent_visitors, 'visit_count')) / $total_visitors : 0;

// Get ticket purchases for each visitor
$visitor_tickets = [];
foreach ($frequent_visitors as $visitor) {
    $tickets_query = "
        SELECT 
            t.ticket_id,
            t.purchase_date,
            t.quantity,
            t.checked_in,
            e.name as event_name,
            e.event_date
        FROM TICKET t
        JOIN EVENT e ON t.event_id = e.event_id
        WHERE t.visitor_id = ?
        ORDER BY e.event_date DESC
    ";
    
    $stmt = $db->prepare($tickets_query);
    $stmt->bind_param("i", $visitor['visitor_id']);
    $stmt->execute();
    $visitor_tickets[$visitor['visitor_id']] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Page title
$page_title = 'Membership & Visitor Insights';
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
            <h1 class="mb-0"><i class="bi bi-people-fill text-info"></i> Membership & Visitor Insights</h1>
            <p class="text-muted">Demographics, retention analysis & visitor patterns</p>
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
            <h5 class="mb-0"><i class="bi bi-funnel"></i> Date Range & Filters</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">From Date</label>
                    <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">To Date</label>
                    <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Membership Type</label>
                    <select name="membership_type" class="form-select">
                        <option value="">All Types</option>
                        <?php foreach ($membership_names as $id => $name): ?>
                            <option value="<?= $id ?>" <?= $membership_type_filter == $id ? 'selected' : '' ?>>
                                <?= $name ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Apply
                    </button>
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
                    <small class="text-muted">Not expired</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card border-success h-100">
                <div class="card-body text-center">
                    <i class="bi bi-people text-success" style="font-size: 2.5rem;"></i>
                    <h3 class="mb-0 mt-2"><?= number_format($total_visitors) ?></h3>
                    <p class="text-muted mb-0">Frequent Visitors</p>
                    <small class="text-muted">2+ visits</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card border-primary h-100">
                <div class="card-body text-center">
                    <i class="bi bi-arrow-repeat text-primary" style="font-size: 2.5rem;"></i>
                    <h3 class="mb-0 mt-2"><?= number_format($avg_visit_frequency, 1) ?></h3>
                    <p class="text-muted mb-0">Avg Visits per Visitor</p>
                    <small class="text-muted">Engagement rate</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card border-warning h-100">
                <div class="card-body text-center">
                    <i class="bi bi-alarm text-warning" style="font-size: 2.5rem;"></i>
                    <h3 class="mb-0 mt-2"><?= number_format($expiring_soon) ?></h3>
                    <p class="text-muted mb-0">Expiring in 30 Days</p>
                    <small class="text-muted">Renewal needed</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Membership Growth Chart -->
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="bi bi-graph-up"></i> Membership Growth Trends</h5>
        </div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="membershipGrowthChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Membership Tier Distribution Table -->
    <div class="card mb-4">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-table"></i> Membership Tier Distribution</h5>
            <small class="text-muted"><i class="bi bi-info-circle"></i> Click any row to view members</small>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table class="table table-hover revenue-table mb-0">
                    <thead>
                        <tr>
                            <th style="padding-left: 2rem;">Membership Type</th>
                            <th>Student Status</th>
                            <th class="text-end">Total Members</th>
                            <th class="text-end">Active</th>
                            <th class="text-end">Auto-Renew</th>
                            <th class="text-end">Annual Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tier_distribution)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    No membership data available
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($tier_distribution as $idx => $tier): ?>
                                <?php 
                                $tier_key = $tier['membership_type'] . '_' . $tier['is_student'];
                                $members = $members_by_tier[$tier_key] ?? [];
                                $tier_name = $membership_names[$tier['membership_type']] ?? 'Unknown';
                                $price = $membership_prices[$tier['membership_type']] ?? 0;
                                $annual_value = $tier['member_count'] * $price;
                                ?>
                                <!-- Summary Row -->
                                <tr class="expandable-row" data-target="tier-detail-<?= $idx ?>" onclick="toggleDetailRow(this)">
                                    <td style="padding-left: 2rem;"><strong><?= htmlspecialchars($tier_name) ?></strong></td>
                                    <td>
                                        <?php if ($tier['is_student']): ?>
                                            <span class="badge bg-info">Student</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Non-Student</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end"><strong><?= number_format($tier['member_count']) ?></strong></td>
                                    <td class="text-end">
                                        <span class="text-success"><?= number_format($tier['active_count']) ?></span>
                                    </td>
                                    <td class="text-end">
                                        <?= number_format($tier['auto_renew_count']) ?>
                                        <small class="text-muted">(<?= $tier['member_count'] > 0 ? number_format(($tier['auto_renew_count'] / $tier['member_count']) * 100, 0) : 0 ?>%)</small>
                                    </td>
                                    <td class="text-end">
                                        <strong class="text-success">$<?= number_format($annual_value, 2) ?></strong>
                                    </td>
                                </tr>
                                <!-- Detail Row -->
                                <tr class="detail-row" id="tier-detail-<?= $idx ?>">
                                    <td colspan="6" style="padding: 0; background-color: #f8f9fa;">
                                        <div style="padding: 1rem 0; border-left: 3px solid #0d6efd; margin-left: 2rem;">
                                            <div style="padding: 0 1rem 0.5rem 1rem;">
                                                <strong><i class="bi bi-people"></i> Members</strong>
                                                <span class="badge bg-primary ms-2"><?= count($members) ?> members</span>
                                            </div>
                                            
                                            <?php if (empty($members)): ?>
                                                <p class="text-muted mb-0" style="padding: 0 1rem;">No members in this tier</p>
                                            <?php else: ?>
                                                <table class="table table-sm mb-0" style="background-color: white; margin: 0 1rem; width: calc(100% - 2rem);">
                                                    <tbody>
                                                        <?php foreach ($members as $member): ?>
                                                            <tr style="border-bottom: 1px solid #e9ecef;">
                                                                <td style="padding: 0.75rem; padding-left: 2rem; width: 30%;">
                                                                    <strong><?= htmlspecialchars($member['member_name']) ?></strong>
                                                                    <br>
                                                                    <small class="text-muted">Member #<?= $member['member_id'] ?></small>
                                                                    <br>
                                                                    <small class="text-muted"><?= htmlspecialchars($member['email']) ?></small>
                                                                    <?php if ($member['phone']): ?>
                                                                        <br>
                                                                        <small class="text-muted"><i class="bi bi-telephone"></i> <?= htmlspecialchars($member['phone']) ?></small>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td style="padding: 0.75rem; width: 15%;">
                                                                    <?php 
                                                                    $status_class = match($member['status']) {
                                                                        'Active' => 'bg-success',
                                                                        'Expiring Soon' => 'bg-warning text-dark',
                                                                        'Expired' => 'bg-danger',
                                                                        default => 'bg-secondary'
                                                                    };
                                                                    ?>
                                                                    <span class="badge <?= $status_class ?>" style="font-size: 0.7rem;">
                                                                        <?= $member['status'] ?>
                                                                    </span>
                                                                    <?php if ($member['auto_renew']): ?>
                                                                        <br><span class="badge bg-info mt-1" style="font-size: 0.7rem;">Auto-Renew</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td class="text-end" style="padding: 0.75rem; width: 15%;">
                                                                    <small class="text-muted">Joined</small><br>
                                                                    <?= date('M d, Y', strtotime($member['start_date'])) ?>
                                                                </td>
                                                                <td class="text-end" style="padding: 0.75rem; width: 15%;">
                                                                    <small class="text-muted">Expires</small><br>
                                                                    <?= date('M d, Y', strtotime($member['expiration_date'])) ?>
                                                                </td>
                                                                
                                                                <td class="text-end" style="padding: 0.75rem; width: 10%;">
                                                                    <small class="text-muted">Value</small><br>
                                                                    <strong class="text-success">$<?= number_format($price, 2) ?></strong>
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

    <!-- Frequent Visitors Table -->
    <div class="card mb-4">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-table"></i> Frequent Visitor Analysis</h5>
            <small class="text-muted"><i class="bi bi-info-circle"></i> Click any row to view visit history</small>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table class="table table-hover revenue-table mb-0">
                    <thead>
                        <tr>
                            <th style="padding-left: 2rem;">Visitor Name</th>
                            <th class="text-end">Visit Count</th>
                            <th class="text-end">Total Attendees</th>
                            <th>First Visit</th>
                            <th>Last Visit</th>
                            <th>Engagement</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($frequent_visitors)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    No frequent visitors in the selected period
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($frequent_visitors as $idx => $visitor): ?>
                                <?php $tickets = $visitor_tickets[$visitor['visitor_id']] ?? []; ?>
                                <!-- Summary Row -->
                                <tr class="expandable-row" data-target="visitor-detail-<?= $idx ?>" onclick="toggleDetailRow(this)">
                                    <td style="padding-left: 2rem;">
                                        <strong><?= htmlspecialchars($visitor['first_name'] . ' ' . $visitor['last_name']) ?></strong>
                                        <br>
                                        <small class="text-muted"><?= htmlspecialchars($visitor['email']) ?></small>
                                    </td>
                                    <td class="text-end"><strong><?= number_format($visitor['visit_count']) ?></strong></td>
                                    <td class="text-end"><?= number_format($visitor['total_attendees_brought']) ?></td>
                                    <td><?= date('M d, Y', strtotime($visitor['first_visit'])) ?></td>
                                    <td><?= date('M d, Y', strtotime($visitor['last_visit'])) ?></td>
                                    <td>
                                        <?php if ($visitor['visit_count'] >= 5): ?>
                                            <span class="badge bg-success">High</span>
                                        <?php elseif ($visitor['visit_count'] >= 3): ?>
                                            <span class="badge bg-info">Medium</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Low</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <!-- Detail Row -->
                                <tr class="detail-row" id="visitor-detail-<?= $idx ?>">
                                    <td colspan="6" style="padding: 0; background-color: #f8f9fa;">
                                        <div style="padding: 1rem 0; border-left: 3px solid #0d6efd; margin-left: 2rem;">
                                            <div style="padding: 0 1rem 0.5rem 1rem;">
                                                <strong><i class="bi bi-calendar-event"></i> Visit History</strong>
                                                <span class="badge bg-primary ms-2"><?= count($tickets) ?> events attended</span>
                                            </div>
                                            
                                            <?php if (empty($tickets)): ?>
                                                <p class="text-muted mb-0" style="padding: 0 1rem;">No visit history</p>
                                            <?php else: ?>
                                                <table class="table table-sm mb-0" style="background-color: white; margin: 0 1rem; width: calc(100% - 2rem);">
                                                    <tbody>
                                                        <?php foreach ($tickets as $ticket): ?>
                                                            <tr style="border-bottom: 1px solid #e9ecef;">
                                                                <td style="padding: 0.75rem; padding-left: 2rem; width: 30%;">
                                                                    <strong><?= htmlspecialchars($ticket['event_name']) ?></strong>
                                                                    <br>
                                                                    <small class="text-muted">Ticket #<?= $ticket['ticket_id'] ?></small>
                                                                </td>
                                                                <td style="padding: 0.75rem; width: 20%;">
                                                                    <small class="text-muted">Event Date</small><br>
                                                                    <?= date('M d, Y', strtotime($ticket['event_date'])) ?>
                                                                </td>
                                                                <td class="text-end" style="padding: 0.75rem; width: 15%;">
                                                                    <small class="text-muted">Purchased</small><br>
                                                                    <?= date('M d, Y', strtotime($ticket['purchase_date'])) ?>
                                                                </td>
                                                                <td class="text-end" style="padding: 0.75rem; width: 15%;">
                                                                    <small class="text-muted">Quantity</small><br>
                                                                    <strong><?= $ticket['quantity'] ?></strong>
                                                                </td>
                                                                <td style="padding: 0.75rem; width: 20%;">
                                                                    <span class="badge <?= $ticket['checked_in'] ? 'bg-success' : 'bg-secondary' ?>" style="font-size: 0.7rem;">
                                                                        <?= $ticket['checked_in'] ? 'Attended' : 'No Show' ?>
                                                                    </span>
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

    <!-- Key Insights -->
    <div class="card">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="bi bi-lightbulb"></i> Key Insights</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6 class="text-primary"><i class="bi bi-people"></i> Membership Health</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <i class="bi bi-check-circle text-success"></i>
                            Active members: <strong><?= number_format($total_active_members) ?></strong>
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-alarm text-warning"></i>
                            Expiring soon: <strong><?= number_format($expiring_soon) ?></strong>
                        </li>
                        <?php 
                        $total_auto_renew = array_sum(array_column($tier_distribution, 'auto_renew_count'));
                        $total_members = array_sum(array_column($tier_distribution, 'member_count'));
                        ?>
                        <li class="mb-2">
                            <i class="bi bi-arrow-repeat text-info"></i>
                            Auto-renew rate: <strong><?= $total_members > 0 ? number_format(($total_auto_renew / $total_members) * 100, 1) : 0 ?>%</strong>
                        </li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h6 class="text-info"><i class="bi bi-graph-up-arrow"></i> Visitor Engagement</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <i class="bi bi-people text-success"></i>
                            Frequent visitors (2+ visits): <strong><?= number_format($total_visitors) ?></strong>
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-bar-chart text-primary"></i>
                            Average visits per visitor: <strong><?= number_format($avg_visit_frequency, 1) ?></strong>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Prepare data for chart
const membershipGrowthData = <?= json_encode($new_members_data) ?>;
const membershipNames = <?= json_encode($membership_names) ?>;

// Group by period and aggregate by membership type
const periods = [...new Set(membershipGrowthData.map(d => d.period))].sort();
const membershipTypes = [...new Set(membershipGrowthData.map(d => d.membership_type))];

const datasets = membershipTypes.map(type => {
    const data = periods.map(period => {
        const found = membershipGrowthData.find(d => d.period === period && d.membership_type == type);
        return found ? found.new_members : 0;
    });
    
    const colors = {
        1: 'rgba(23, 162, 184, 0.8)',   // Student cyan
        2: 'rgba(54, 162, 235, 0.8)',   // Individual blue
        3: 'rgba(75, 192, 192, 0.8)',   // Family teal
        4: 'rgba(255, 206, 86, 0.8)',   // Patron yellow
        5: 'rgba(153, 102, 255, 0.8)'   // Benefactor purple
    };
    
    return {
        label: membershipNames[type] || 'Unknown',
        data: data,
        borderColor: colors[type] || 'rgba(201, 203, 207, 0.8)',
        backgroundColor: colors[type] || 'rgba(201, 203, 207, 0.2)',
        tension: 0.4,
        fill: false
    };
});

// Membership Growth Chart
const growthCtx = document.getElementById('membershipGrowthChart').getContext('2d');
new Chart(growthCtx, {
    type: 'line',
    data: {
        labels: periods,
        datasets: datasets
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
                text: 'New Memberships by Type Over Time'
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