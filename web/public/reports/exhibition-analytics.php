<?php
// Exhibition and Event Analytics Report
// Comprehensive analysis of exhibitions and events: attendance, tickets, performance

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
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-6 months'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$report_type = $_GET['report_type'] ?? 'both'; // both, exhibitions, events

// ========== EXHIBITION STATISTICS ==========
$exhibition_query = "
    SELECT 
        e.exhibition_id,
        e.title,
        e.start_date,
        e.end_date,
        e.theme_sponsor,
        e.curator_id,
        CONCAT(COALESCE(s.name, ''), '') as curator_name,
        COUNT(DISTINCT ea.artwork_id) as artwork_count,
        DATEDIFF(e.end_date, e.start_date) as duration_days
    FROM EXHIBITION e
    LEFT JOIN STAFF s ON e.curator_id = s.staff_id
    LEFT JOIN EXHIBITION_ARTWORK ea ON e.exhibition_id = ea.exhibition_id
    WHERE e.is_deleted = 0
        AND (e.start_date BETWEEN ? AND ? OR e.end_date BETWEEN ? AND ?)
    GROUP BY e.exhibition_id, e.title, e.start_date, e.end_date, e.theme_sponsor, e.curator_id, s.name
    ORDER BY e.start_date DESC
";

$stmt = $db->prepare($exhibition_query);
$stmt->bind_param("ssss", $date_from, $date_to, $date_from, $date_to);
$stmt->execute();
$exhibitions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get artworks for each exhibition
$exhibition_artworks = [];
foreach ($exhibitions as $exhibition) {
    $artwork_query = "
        SELECT 
            art.artwork_id,
            art.title as artwork_title,
            art.creation_year,
            art.medium,
            CONCAT(COALESCE(artist.first_name, ''), ' ', COALESCE(artist.last_name, '')) as artist_name,
            loc.name as location_name,
            ea.start_view_date,
            ea.end_view_date
        FROM EXHIBITION_ARTWORK ea
        JOIN ARTWORK art ON ea.artwork_id = art.artwork_id
        LEFT JOIN ARTWORK_CREATOR ac ON art.artwork_id = ac.artwork_id
        LEFT JOIN ARTIST artist ON ac.artist_id = artist.artist_id
        LEFT JOIN LOCATION loc ON ea.location_id = loc.location_id
        WHERE ea.exhibition_id = ?
        ORDER BY art.title
    ";
    
    $stmt = $db->prepare($artwork_query);
    $stmt->bind_param("i", $exhibition['exhibition_id']);
    $stmt->execute();
    $exhibition_artworks[$exhibition['exhibition_id']] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// ========== EVENT STATISTICS - FIXED QUERY ==========
// Using a subquery to ensure we get distinct events first, then join for aggregates
$event_query = "
    SELECT DISTINCT
        ev.event_id,
        ev.name as event_name,
        ev.description,
        ev.event_date,
        ev.capacity,
        ev.exhibition_id,
        ex.title as exhibition_title,
        loc.name as location_name,
        COALESCE(ticket_stats.tickets_sold, 0) as tickets_sold,
        COALESCE(ticket_stats.ticket_transactions, 0) as ticket_transactions,
        COALESCE(ticket_stats.checked_in_count, 0) as checked_in_count
    FROM EVENT ev
    LEFT JOIN EXHIBITION ex ON ev.exhibition_id = ex.exhibition_id
    LEFT JOIN LOCATION loc ON ev.location_id = loc.location_id
    LEFT JOIN (
        SELECT 
            event_id,
            SUM(quantity) as tickets_sold,
            COUNT(DISTINCT ticket_id) as ticket_transactions,
            SUM(CASE WHEN checked_in = 1 THEN quantity ELSE 0 END) as checked_in_count
        FROM TICKET
        GROUP BY event_id
    ) ticket_stats ON ev.event_id = ticket_stats.event_id
    WHERE ev.event_date BETWEEN ? AND ?
    ORDER BY ev.event_date DESC
";

$stmt = $db->prepare($event_query);
$stmt->bind_param("ss", $date_from, $date_to);
$stmt->execute();
$events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calculate event metrics
foreach ($events as &$event) {
    $event['capacity_used_pct'] = $event['capacity'] > 0 ? ($event['tickets_sold'] / $event['capacity']) * 100 : 0;
    $event['available_tickets'] = max(0, $event['capacity'] - $event['tickets_sold']);
    $event['checkin_rate'] = $event['tickets_sold'] > 0 ? ($event['checked_in_count'] / $event['tickets_sold']) * 100 : 0;
}
unset($event);

// Get ticket details for each event
$event_tickets = [];
foreach ($events as $event) {
    $ticket_query = "
        SELECT 
            t.ticket_id,
            t.purchase_date,
            t.quantity,
            t.checked_in,
            t.check_in_time,
            CASE 
                WHEN t.member_id IS NOT NULL THEN CONCAT('Member #', t.member_id)
                WHEN t.visitor_id IS NOT NULL THEN CONCAT('Visitor #', t.visitor_id)
                ELSE 'Walk-in'
            END as customer_type,
            CASE 
                WHEN t.member_id IS NOT NULL THEN m.email
                WHEN t.visitor_id IS NOT NULL THEN v.email
                ELSE NULL
            END as customer_email
        FROM TICKET t
        LEFT JOIN MEMBER m ON t.member_id = m.member_id
        LEFT JOIN VISITOR v ON t.visitor_id = v.visitor_id
        WHERE t.event_id = ?
        ORDER BY t.purchase_date DESC
    ";
    
    $stmt = $db->prepare($ticket_query);
    $stmt->bind_param("i", $event['event_id']);
    $stmt->execute();
    $event_tickets[$event['event_id']] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Calculate summary statistics
$total_exhibitions = count($exhibitions);
$total_artworks_exhibited = array_sum(array_column($exhibitions, 'artwork_count'));
$total_events = count($events);
$total_tickets_sold = array_sum(array_column($events, 'tickets_sold'));
$total_capacity = array_sum(array_column($events, 'capacity'));
$avg_capacity_utilization = $total_capacity > 0 ? ($total_tickets_sold / $total_capacity) * 100 : 0;

// Page title
$page_title = 'Exhibition & Event Analytics';
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

.progress {
    height: 20px;
}
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="index.php" class="btn btn-outline-secondary mb-2">
                <i class="bi bi-arrow-left"></i> Back to Reports
            </a>
            <h1 class="mb-0"><i class="bi bi-calendar-event text-info"></i> Exhibition & Event Analytics</h1>
            <p class="text-muted">Comprehensive analysis of exhibitions and event performance</p>
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
                <div class="col-md-4">
                    <label class="form-label">From Date</label>
                    <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">To Date</label>
                    <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Report Type</label>
                    <select name="report_type" class="form-select">
                        <option value="both" <?= $report_type === 'both' ? 'selected' : '' ?>>Both</option>
                        <option value="exhibitions" <?= $report_type === 'exhibitions' ? 'selected' : '' ?>>Exhibitions Only</option>
                        <option value="events" <?= $report_type === 'events' ? 'selected' : '' ?>>Events Only</option>
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
            <div class="card stat-card border-primary h-100">
                <div class="card-body text-center">
                    <i class="bi bi-image text-primary" style="font-size: 2.5rem;"></i>
                    <h3 class="mb-0 mt-2"><?= number_format($total_exhibitions) ?></h3>
                    <p class="text-muted mb-0">Exhibitions</p>
                    <small class="text-muted"><?= number_format($total_artworks_exhibited) ?> artworks total</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card border-success h-100">
                <div class="card-body text-center">
                    <i class="bi bi-calendar-check text-success" style="font-size: 2.5rem;"></i>
                    <h3 class="mb-0 mt-2"><?= number_format($total_events) ?></h3>
                    <p class="text-muted mb-0">Events</p>
                    <small class="text-muted">In selected period</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card border-info h-100">
                <div class="card-body text-center">
                    <i class="bi bi-ticket-perforated text-info" style="font-size: 2.5rem;"></i>
                    <h3 class="mb-0 mt-2"><?= number_format($total_tickets_sold) ?></h3>
                    <p class="text-muted mb-0">Tickets Sold</p>
                    <small class="text-muted">Across all events</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card border-warning h-100">
                <div class="card-body text-center">
                    <i class="bi bi-percent text-warning" style="font-size: 2.5rem;"></i>
                    <h3 class="mb-0 mt-2"><?= number_format($avg_capacity_utilization, 1) ?>%</h3>
                    <p class="text-muted mb-0">Avg Capacity</p>
                    <small class="text-muted">Utilization rate</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Event Performance Table -->
    <?php if ($report_type === 'both' || $report_type === 'events'): ?>
    <div class="card mb-4">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-table"></i> Event Performance Detail</h5>
            <small class="text-muted"><i class="bi bi-info-circle"></i> Click any row to view ticket sales</small>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table class="table table-hover revenue-table mb-0">
                    <thead>
                        <tr>
                            <th style="padding-left: 2rem;">Event Name</th>
                            <th>Date</th>
                            <th class="text-end">Capacity</th>
                            <th class="text-end">Tickets Sold</th>
                            <th class="text-end">Utilization</th>
                            <th>Location</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($events)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    No events in the selected period
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($events as $event): ?>
                                <?php $tickets = $event_tickets[$event['event_id']] ?? []; ?>
                                
                                <!-- Summary Row -->
                                <tr class="expandable-row" data-target="event-detail-<?= $event['event_id'] ?>" onclick="toggleDetailRow(this)">
                                    <td style="padding-left: 2rem;">
                                        <strong><?= htmlspecialchars($event['event_name']) ?></strong>
                                        <?php if ($event['exhibition_title']): ?>
                                            <br><small class="text-muted">Related: <?= htmlspecialchars($event['exhibition_title']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($event['event_date'])) ?></td>
                                    <td class="text-end"><?= number_format($event['capacity']) ?></td>
                                    <td class="text-end"><strong><?= number_format($event['tickets_sold']) ?></strong></td>
                                    <td class="text-end">
                                        <div class="progress">
                                            <div class="progress-bar <?= $event['capacity_used_pct'] >= 90 ? 'bg-success' : ($event['capacity_used_pct'] >= 70 ? 'bg-warning' : 'bg-info') ?>" 
                                                 style="width: <?= min(100, $event['capacity_used_pct']) ?>%">
                                                <?= number_format($event['capacity_used_pct'], 0) ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td><small class="text-muted"><?= htmlspecialchars($event['location_name']) ?></small></td>
                                </tr>
                                
                                <!-- Detail Row -->
                                <tr class="detail-row" id="event-detail-<?= $event['event_id'] ?>">
                                    <td colspan="6" style="padding: 0; background-color: #f8f9fa;">
                                        <div style="padding: 1rem 0; border-left: 3px solid #0d6efd; margin-left: 2rem;">
                                            <div style="padding: 0 1rem 0.5rem 1rem;">
                                                <strong><i class="bi bi-ticket"></i> Ticket Sales</strong>
                                                <span class="badge bg-primary ms-2"><?= count($tickets) ?> transactions</span>
                                                <span class="badge bg-success ms-1"><?= $event['checked_in_count'] ?> checked in</span>
                                            </div>
                                            
                                            <?php if (empty($tickets)): ?>
                                                <p class="text-muted mb-0" style="padding: 0 1rem;">No tickets sold</p>
                                            <?php else: ?>
                                                <table class="table table-sm mb-0" style="background-color: white; margin: 0 1rem; width: calc(100% - 2rem);">
                                                    <tbody>
                                                        <?php foreach ($tickets as $ticket): ?>
                                                            <tr style="border-bottom: 1px solid #e9ecef;">
                                                                <td style="padding: 0.75rem; padding-left: 2rem; width: 25%;">
                                                                    <strong>Ticket #<?= $ticket['ticket_id'] ?></strong>
                                                                    <br>
                                                                    <small class="text-muted">
                                                                        <?= date('M d, Y', strtotime($ticket['purchase_date'])) ?>
                                                                    </small>
                                                                    <br>
                                                                    <span class="badge <?= $ticket['checked_in'] ? 'bg-success' : 'bg-secondary' ?>" style="font-size: 0.7rem;">
                                                                        <?= $ticket['checked_in'] ? 'Checked In' : 'Not Checked In' ?>
                                                                    </span>
                                                                </td>
                                                                <td style="padding: 0.75rem; width: 20%;">
                                                                    <?php if ($ticket['check_in_time']): ?>
                                                                        <small class="text-muted">Check-in</small><br>
                                                                        <?= date('M d, g:i A', strtotime($ticket['check_in_time'])) ?>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td class="text-end" style="padding: 0.75rem; width: 15%;">
                                                                    <small class="text-muted">Quantity</small><br>
                                                                    <strong><?= $ticket['quantity'] ?></strong>
                                                                </td>
                                                                <td class="text-end" style="padding: 0.75rem; width: 10%;">
                                                                    <span class="badge bg-info" style="font-size: 0.7rem;">
                                                                        <?= htmlspecialchars($ticket['customer_type']) ?>
                                                                    </span>
                                                                </td>
                                                                <td style="padding: 0.75rem; width: 30%;">
                                                                    <?php if ($ticket['customer_email']): ?>
                                                                        <small class="text-muted"><?= htmlspecialchars($ticket['customer_email']) ?></small>
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
    <?php endif; ?>

    <!-- Exhibition Detail Table -->
    <?php if ($report_type === 'both' || $report_type === 'exhibitions'): ?>
    <div class="card mb-4">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-table"></i> Exhibition Detail</h5>
            <small class="text-muted"><i class="bi bi-info-circle"></i> Click any row to view exhibited artworks</small>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table class="table table-hover revenue-table mb-0">
                    <thead>
                        <tr>
                            <th style="padding-left: 2rem;">Exhibition Title</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th class="text-end">Duration (Days)</th>
                            <th class="text-end">Artworks</th>
                            <th>Sponsor/Theme</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($exhibitions)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    No exhibitions in the selected period
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($exhibitions as $exhibition): ?>
                                <?php $artworks = $exhibition_artworks[$exhibition['exhibition_id']] ?? []; ?>
                                <!-- Summary Row -->
                                <tr class="expandable-row" data-target="exhibition-detail-<?= $exhibition['exhibition_id'] ?>" onclick="toggleDetailRow(this)">
                                    <td style="padding-left: 2rem;">
                                        <strong><?= htmlspecialchars($exhibition['title']) ?></strong>
                                        <?php if ($exhibition['curator_name']): ?>
                                            <br><small class="text-muted">Curator: <?= htmlspecialchars($exhibition['curator_name']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($exhibition['start_date'])) ?></td>
                                    <td><?= date('M d, Y', strtotime($exhibition['end_date'])) ?></td>
                                    <td class="text-end"><?= number_format($exhibition['duration_days']) ?></td>
                                    <td class="text-end"><strong><?= number_format($exhibition['artwork_count']) ?></strong></td>
                                    <td><small class="text-muted"><?= htmlspecialchars($exhibition['theme_sponsor']) ?></small></td>
                                </tr>
                                <!-- Detail Row -->
                                <tr class="detail-row" id="exhibition-detail-<?= $exhibition['exhibition_id'] ?>">
                                    <td colspan="6" style="padding: 0; background-color: #f8f9fa;">
                                        <div style="padding: 1rem 0; border-left: 3px solid #0d6efd; margin-left: 2rem;">
                                            <div style="padding: 0 1rem 0.5rem 1rem;">
                                                <strong><i class="bi bi-image"></i> Exhibited Artworks</strong>
                                                <span class="badge bg-primary ms-2"><?= count($artworks) ?> artworks</span>
                                            </div>
                                            
                                            <?php if (empty($artworks)): ?>
                                                <p class="text-muted mb-0" style="padding: 0 1rem;">No artworks assigned</p>
                                            <?php else: ?>
                                                <table class="table table-sm mb-0" style="background-color: white; margin: 0 1rem; width: calc(100% - 2rem);">
                                                    <tbody>
                                                        <?php foreach ($artworks as $artwork): ?>
                                                            <tr style="border-bottom: 1px solid #e9ecef;">
                                                                <td style="padding: 0.75rem; padding-left: 2rem; width: 30%;">
                                                                    <strong><?= htmlspecialchars($artwork['artwork_title']) ?></strong>
                                                                    <br>
                                                                    <small class="text-muted">by <?= htmlspecialchars($artwork['artist_name'] ?: 'Unknown Artist') ?></small>
                                                                </td>
                                                                <td style="padding: 0.75rem; width: 15%;">
                                                                    <small class="text-muted">Created</small><br>
                                                                    <?= $artwork['creation_year'] ?: 'Unknown' ?>
                                                                </td>
                                                                <td class="text-end" style="padding: 0.75rem; width: 20%;">
                                                                    <small class="text-muted">Display Period</small><br>
                                                                    <?= date('M d', strtotime($artwork['start_view_date'])) ?> - <?= date('M d', strtotime($artwork['end_view_date'])) ?>
                                                                </td>
                                                                <td class="text-end" style="padding: 0.75rem; width: 15%;">
                                                                    <?php if ($artwork['location_name']): ?>
                                                                        <small class="text-muted">Location</small><br>
                                                                        <?= htmlspecialchars($artwork['location_name']) ?>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td style="padding: 0.75rem; width: 20%;"></td>
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
    <?php endif; ?>

    <!-- Key Insights -->
    <div class="card">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="bi bi-lightbulb"></i> Key Insights</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6 class="text-primary"><i class="bi bi-bar-chart"></i> Exhibition Performance</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <i class="bi bi-check-circle text-success"></i>
                            Total exhibitions: <strong><?= number_format($total_exhibitions) ?></strong>
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-check-circle text-success"></i>
                            Artworks exhibited: <strong><?= number_format($total_artworks_exhibited) ?></strong>
                        </li>
                        <?php if (!empty($exhibitions)): ?>
                            <?php 
                            $avg_artwork_per_exhibition = $total_exhibitions > 0 ? $total_artworks_exhibited / $total_exhibitions : 0;
                            ?>
                            <li class="mb-2">
                                <i class="bi bi-check-circle text-success"></i>
                                Avg artworks per exhibition: <strong><?= number_format($avg_artwork_per_exhibition, 1) ?></strong>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h6 class="text-info"><i class="bi bi-graph-up-arrow"></i> Event Performance</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <i class="bi bi-calendar text-warning"></i>
                            Total events: <strong><?= number_format($total_events) ?></strong>
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-ticket text-info"></i>
                            Total tickets sold: <strong><?= number_format($total_tickets_sold) ?></strong>
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-percent text-success"></i>
                            Capacity utilization: <strong><?= number_format($avg_capacity_utilization, 1) ?>%</strong>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
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