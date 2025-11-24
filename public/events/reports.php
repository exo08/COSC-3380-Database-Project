<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/permissions.php';

// Check permission
requirePermission('report_events');

$page_title = 'Event Reports';
$db = db();

$error = '';
$active_report = $_GET['report'] ?? 'attendance';

// Get parameters
$event_id = $_GET['event_id'] ?? null;
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Get report data based on selection
$report_data = [];

try {
    switch($active_report) {
        case 'attendance':
            $result = $db->query("CALL EventAttendanceReport()");
            if ($result) {
                $report_data = $result->fetch_all(MYSQLI_ASSOC);
                $result->close();
                $db->next_result();
            }
            break;
            
        case 'capacity':
            if ($event_id) {
                $stmt = $db->prepare("CALL TicketsSoldVsCapacity(?)");
                $stmt->bind_param('i', $event_id);
                if ($stmt->execute()) {
                    $result = $stmt->get_result();
                    $report_data = $result->fetch_all(MYSQLI_ASSOC);
                    $result->close();
                    $stmt->close();
                    $db->next_result();
                }
            } else {
                // Get all events for selection
                $report_data = [];
            }
            break;
            
        case 'members_visitors':
            $stmt = $db->prepare("CALL MemberVsVisitorAdmissions(?, ?)");
            $stmt->bind_param('ss', $start_date, $end_date);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $report_data = $result->fetch_all(MYSQLI_ASSOC);
                $result->close();
                $stmt->close();
                $db->next_result();
            }
            break;
            
        case 'upcoming':
            $result = $db->query("CALL GetUpcomingEvents()");
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

// Get all events for dropdown
try {
    $events_result = $db->query("SELECT event_id, name, event_date FROM EVENT ORDER BY event_date DESC LIMIT 50");
    $all_events = $events_result ? $events_result->fetch_all(MYSQLI_ASSOC) : [];
} catch (Exception $e) {
    $all_events = [];
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
    background: linear-gradient(90deg, #17a2b8 0%, #138496 100%);
    color: white !important;
    font-weight: 500;
}

.report-card {
    background: white;
    border-radius: 10px;
    padding: 25px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.filter-card {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
}

.stat-badge {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 500;
}

.capacity-bar {
    height: 30px;
    border-radius: 15px;
    overflow: hidden;
    background: #e9ecef;
}

.capacity-fill {
    height: 100%;
    transition: width 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
}

.print-btn {
    position: fixed;
    bottom: 30px;
    right: 30px;
    z-index: 1000;
}

@media print {
    .report-nav, .filter-card, .print-btn, .top-bar, #sidebar {
        display: none !important;
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
            <a class="nav-link <?= $active_report === 'attendance' ? 'active' : '' ?>" href="?report=attendance">
                <i class="bi bi-people"></i> Attendance
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $active_report === 'capacity' ? 'active' : '' ?>" href="?report=capacity">
                <i class="bi bi-bar-chart"></i> Capacity
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $active_report === 'members_visitors' ? 'active' : '' ?>" href="?report=members_visitors">
                <i class="bi bi-person-badge"></i> Members vs Visitors
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $active_report === 'upcoming' ? 'active' : '' ?>" href="?report=upcoming">
                <i class="bi bi-calendar-event"></i> Upcoming Events
            </a>
        </li>
    </ul>
</div>

<!-- Filters based on report type -->
<?php if ($active_report === 'capacity'): ?>
    <div class="filter-card">
        <form method="GET" class="row g-3">
            <input type="hidden" name="report" value="capacity">
            <div class="col-md-8">
                <label class="form-label">Select Event:</label>
                <select class="form-select" name="event_id">
                    <option value="">-- Choose an event --</option>
                    <?php foreach ($all_events as $event): ?>
                        <option value="<?= $event['event_id'] ?>" <?= ($event_id == $event['event_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($event['name']) ?> - <?= date('M j, Y', strtotime($event['event_date'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i> View Report
                </button>
            </div>
        </form>
    </div>
<?php elseif ($active_report === 'members_visitors'): ?>
    <div class="filter-card">
        <form method="GET" class="row g-3">
            <input type="hidden" name="report" value="members_visitors">
            <div class="col-md-3">
                <label class="form-label">Start Date:</label>
                <input type="date" class="form-control" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">End Date:</label>
                <input type="date" class="form-control" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i> View Report
                </button>
            </div>
        </form>
    </div>
<?php endif; ?>

<!-- Report Content -->
<div class="report-card">
    <?php if ($active_report === 'attendance'): ?>
        <h4 class="mb-4"><i class="bi bi-people"></i> Event Attendance Report</h4>
        <p class="text-muted">All events with attendance metrics</p>
        
        <?php if (empty($report_data)): ?>
            <div class="alert alert-info">No event data available.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Event Name</th>
                            <th>Date</th>
                            <th>Location</th>
                            <th class="text-center">Capacity</th>
                            <th class="text-center">Sold</th>
                            <th class="text-center">Checked In</th>
                            <th class="text-center">Check-in Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report_data as $row): 
                            $fill_pct = floatval($row['capacity_percentage'] ?? 0);
                            $fill_class = $fill_pct >= 90 ? 'success' : ($fill_pct >= 50 ? 'warning' : 'secondary');
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($row['event_name']) ?></strong></td>
                                <td><?= date('M j, Y', strtotime($row['event_date'])) ?></td>
                                <td><?= htmlspecialchars($row['location_name'] ?? 'N/A') ?></td>
                                <td class="text-center"><?= $row['capacity'] ?></td>
                                <td class="text-center">
                                    <span class="stat-badge bg-<?= $fill_class ?> text-white">
                                        <?= $row['tickets_sold'] ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div style="position: relative;">
                                        <?= $row['checked_in_count'] ?>
                                        <div class="progress" style="height: 4px; margin-top: 4px;">
                                            <div class="progress-bar bg-primary" 
                                                 style="width: <?= $row['tickets_sold'] > 0 ? round(($row['checked_in_count'] / $row['tickets_sold']) * 100) : 0 ?>%">
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center"><?= number_format($row['check_in_rate'], 1) ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        
    <?php elseif ($active_report === 'capacity'): ?>
        <h4 class="mb-4"><i class="bi bi-bar-chart"></i> Tickets Sold vs Capacity</h4>
        
        <?php if (!$event_id): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Please select an event from the dropdown above to view its capacity report.
            </div>
        <?php elseif (empty($report_data)): ?>
            <div class="alert alert-warning">No data found for this event.</div>
        <?php else: 
            $data = $report_data[0];
            $percent = floatval($data['percent_sold']);
            $status_class = $data['status'] === 'SOLD OUT' ? 'danger' : ($data['status'] === 'NEARLY FULL' ? 'warning' : 'success');
        ?>
            <div class="card mb-4">
                <div class="card-body">
                    <h5><?= htmlspecialchars($data['event_name']) ?></h5>
                    <p class="text-muted mb-3">
                        <i class="bi bi-calendar"></i> <?= date('l, F j, Y', strtotime($data['event_date'])) ?>
                    </p>
                    
                    <div class="row">
                        <div class="col-md-3">
                            <div class="text-center p-3 bg-light rounded">
                                <h6 class="text-uppercase text-muted mb-1">Capacity</h6>
                                <h2 class="mb-0"><?= $data['capacity'] ?></h2>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center p-3 bg-primary text-white rounded">
                                <h6 class="text-uppercase mb-1">Tickets Sold</h6>
                                <h2 class="mb-0"><?= $data['tickets_sold'] ?></h2>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center p-3 bg-success text-white rounded">
                                <h6 class="text-uppercase mb-1">Available</h6>
                                <h2 class="mb-0"><?= $data['tickets_remaining'] ?></h2>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center p-3 bg-<?= $status_class ?> text-white rounded">
                                <h6 class="text-uppercase mb-1">Status</h6>
                                <h4 class="mb-0"><?= $data['status'] ?></h4>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <h6 class="mb-2">Capacity Fill: <?= number_format($percent, 1) ?>%</h6>
                        <div class="capacity-bar" style="height: 40px;">
                            <div class="capacity-fill bg-<?= $status_class ?>" style="width: <?= min($percent, 100) ?>%">
                                <?= number_format($percent, 1) ?>%
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
    <?php elseif ($active_report === 'members_visitors'): ?>
        <h4 class="mb-4"><i class="bi bi-person-badge"></i> Member vs Visitor Admissions</h4>
        <p class="text-muted"><?= date('F j, Y', strtotime($start_date)) ?> to <?= date('F j, Y', strtotime($end_date)) ?></p>
        
        <?php if (empty($report_data)): ?>
            <div class="alert alert-info">No ticket data for this date range.</div>
        <?php else: 
            $total_member = array_sum(array_column($report_data, 'member_tickets'));
            $total_visitor = array_sum(array_column($report_data, 'visitor_tickets'));
            $total_tickets = $total_member + $total_visitor;
            $member_pct = $total_tickets > 0 ? round(($total_member / $total_tickets) * 100, 1) : 0;
        ?>
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <h6 class="text-uppercase">Total Tickets</h6>
                            <h2><?= $total_tickets ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h6 class="text-uppercase">Member Tickets</h6>
                            <h2><?= $total_member ?> <small>(<?= $member_pct ?>%)</small></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <h6 class="text-uppercase">Visitor Tickets</h6>
                            <h2><?= $total_visitor ?> <small>(<?= 100 - $member_pct ?>%)</small></h2>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th class="text-center">Member Tickets</th>
                            <th class="text-center">Visitor Tickets</th>
                            <th class="text-center">Total</th>
                            <th class="text-center">Member %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report_data as $row): ?>
                            <tr>
                                <td><strong><?= date('D, M j, Y', strtotime($row['purchase_date'])) ?></strong></td>
                                <td class="text-center">
                                    <span class="stat-badge bg-success text-white"><?= $row['member_tickets'] ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="stat-badge bg-info text-white"><?= $row['visitor_tickets'] ?></span>
                                </td>
                                <td class="text-center"><strong><?= $row['total_tickets'] ?></strong></td>
                                <td class="text-center"><?= number_format($row['member_percentage'], 1) ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        
    <?php elseif ($active_report === 'upcoming'): ?>
        <h4 class="mb-4"><i class="bi bi-calendar-event"></i> Upcoming Events</h4>
        
        <?php if (empty($report_data)): ?>
            <div class="alert alert-info">No upcoming events scheduled.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Event Name</th>
                            <th>Date</th>
                            <th>Location</th>
                            <th>Exhibition</th>
                            <th class="text-center">Capacity</th>
                            <th class="text-center">Sold</th>
                            <th class="text-center">Available</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report_data as $row): 
                            $status_class = $row['availability_status'] === 'SOLD OUT' ? 'danger' : ($row['availability_status'] === 'NEARLY FULL' ? 'warning' : 'success');
                        ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($row['event_name']) ?></strong>
                                    <?php if (!empty($row['description'])): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars(substr($row['description'], 0, 50)) ?>...</small>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('M j, Y', strtotime($row['event_date'])) ?></td>
                                <td><?= htmlspecialchars($row['location_name'] ?? 'TBD') ?></td>
                                <td><?= htmlspecialchars($row['exhibition_name'] ?? 'General') ?></td>
                                <td class="text-center"><?= $row['capacity'] ?></td>
                                <td class="text-center">
                                    <span class="stat-badge bg-primary text-white"><?= $row['tickets_sold'] ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="stat-badge bg-success text-white"><?= $row['tickets_available'] ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-<?= $status_class ?>"><?= $row['availability_status'] ?></span>
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
<button class="btn btn-info btn-lg rounded-circle print-btn" onclick="window.print()" title="Print Report">
    <i class="bi bi-printer fs-4"></i>
</button>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>