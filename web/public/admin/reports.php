<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/permissions.php';

// Require admin permission
if ($_SESSION['user_type'] !== 'admin') {
    header('Location: /dashboard.php?error=access_denied');
    exit;
}

$page_title = 'Comprehensive Reports';
$db = db();

// Get active report type
$active_report = $_GET['report'] ?? 'overview';
$report_data = [];
$error = '';

// Generate report based on type
switch ($active_report) {
    case 'overview':
        // Dashboard overview statistics
        $report_data['artworks'] = $db->query("SELECT COUNT(*) as count FROM ARTWORK")->fetch_assoc()['count'];
        $report_data['owned_artworks'] = $db->query("SELECT COUNT(*) as count FROM ARTWORK WHERE is_owned = 1")->fetch_assoc()['count'];
        $report_data['exhibitions'] = $db->query("SELECT COUNT(*) as count FROM EXHIBITION WHERE end_date >= CURDATE() AND (is_deleted = FALSE OR is_deleted IS NULL)")->fetch_assoc()['count'];
        $report_data['artists'] = $db->query("SELECT COUNT(*) as count FROM ARTIST")->fetch_assoc()['count'];
        $report_data['members'] = $db->query("SELECT COUNT(*) as count FROM MEMBER WHERE expiration_date >= CURDATE()")->fetch_assoc()['count'];
        $report_data['upcoming_events'] = $db->query("SELECT COUNT(*) as count FROM EVENT WHERE event_date >= CURDATE()")->fetch_assoc()['count'];
        $report_data['total_sales'] = $db->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM SALE WHERE YEAR(sale_date) = YEAR(CURDATE())")->fetch_assoc()['total'];
        $report_data['total_donations'] = $db->query("SELECT COALESCE(SUM(amount), 0) as total FROM DONATION WHERE YEAR(donation_date) = YEAR(CURDATE())")->fetch_assoc()['total'];
        break;
        
    case 'by_artist':
        $report_data = $db->query("
            SELECT a.artist_id, 
                   CONCAT(a.first_name, ' ', a.last_name) as artist_name,
                   a.nationality,
                   a.birth_year,
                   a.death_year,
                   COUNT(DISTINCT ac.artwork_id) as artwork_count,
                   GROUP_CONCAT(DISTINCT aw.title ORDER BY aw.title SEPARATOR ', ') as artwork_titles
            FROM ARTIST a
            LEFT JOIN ARTWORK_CREATOR ac ON a.artist_id = ac.artist_id
            LEFT JOIN ARTWORK aw ON ac.artwork_id = aw.artwork_id
            GROUP BY a.artist_id
            ORDER BY artwork_count DESC, artist_name
        ")->fetch_all(MYSQLI_ASSOC);
        break;
        
    case 'by_period':
        $report_data = $db->query("
            SELECT 
                CASE 
                    WHEN creation_year < 1400 THEN 'Medieval (Before 1400)'
                    WHEN creation_year BETWEEN 1400 AND 1599 THEN 'Renaissance (1400-1599)'
                    WHEN creation_year BETWEEN 1600 AND 1799 THEN 'Baroque & Rococo (1600-1799)'
                    WHEN creation_year BETWEEN 1800 AND 1899 THEN '19th Century (1800-1899)'
                    WHEN creation_year BETWEEN 1900 AND 1945 THEN 'Early Modern (1900-1945)'
                    WHEN creation_year BETWEEN 1946 AND 1979 THEN 'Post-War (1946-1979)'
                    WHEN creation_year >= 1980 THEN 'Contemporary (1980-Present)'
                    ELSE 'Unknown Period'
                END as period,
                MIN(creation_year) as min_year,
                MAX(creation_year) as max_year,
                COUNT(*) as artwork_count,
                SUM(CASE WHEN is_owned = 1 THEN 1 ELSE 0 END) as owned_count,
                SUM(CASE WHEN is_owned = 0 THEN 1 ELSE 0 END) as loan_count
            FROM ARTWORK
            GROUP BY period
            ORDER BY min_year
        ")->fetch_all(MYSQLI_ASSOC);
        break;
        
    case 'exhibitions':
        $report_data = $db->query("
            SELECT e.*, 
                   CONCAT(s.name) as curator_name,
                   COUNT(DISTINCT ea.artwork_id) as artwork_count,
                   CASE 
                       WHEN e.end_date >= CURDATE() AND e.start_date <= CURDATE() THEN 'Active'
                       WHEN e.start_date > CURDATE() THEN 'Upcoming'
                       ELSE 'Past'
                   END as status
            FROM EXHIBITION e
            LEFT JOIN STAFF s ON e.curator_id = s.staff_id
            LEFT JOIN EXHIBITION_ARTWORK ea ON e.exhibition_id = ea.exhibition_id
            WHERE e.is_deleted = FALSE OR e.is_deleted IS NULL
            GROUP BY e.exhibition_id
            ORDER BY e.start_date DESC
        ")->fetch_all(MYSQLI_ASSOC);
        break;
        
    case 'events':
        $report_data = $db->query("
            SELECT e.*,
                   l.name as location_name,
                   ex.title as exhibition_title,
                   COUNT(DISTINCT t.ticket_id) as tickets_sold,
                   SUM(t.quantity) as total_attendees,
                   COUNT(DISTINCT CASE WHEN t.checked_in = 1 THEN t.ticket_id END) as checked_in,
                   CASE 
                       WHEN e.event_date < CURDATE() THEN 'Completed'
                       WHEN e.event_date = CURDATE() THEN 'Today'
                       ELSE 'Upcoming'
                   END as status
            FROM EVENT e
            LEFT JOIN LOCATION l ON e.location_id = l.location_id
            LEFT JOIN EXHIBITION ex ON e.exhibition_id = ex.exhibition_id
            LEFT JOIN TICKET t ON e.event_id = t.event_id
            GROUP BY e.event_id
            ORDER BY e.event_date DESC
        ")->fetch_all(MYSQLI_ASSOC);
        break;
        
    case 'sales':
        $report_data = $db->query("
            SELECT 
                DATE_FORMAT(s.sale_date, '%Y-%m') as month,
                COUNT(DISTINCT s.sale_id) as transaction_count,
                SUM(s.total_amount) as total_revenue,
                SUM(s.discount_amount) as total_discounts,
                COUNT(DISTINCT CASE WHEN s.member_id IS NOT NULL THEN s.sale_id END) as member_sales,
                AVG(s.total_amount) as avg_transaction
            FROM SALE s
            WHERE s.sale_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            GROUP BY month
            ORDER BY month DESC
        ")->fetch_all(MYSQLI_ASSOC);
        break;
        
    case 'inventory':
        $report_data = $db->query("
            SELECT si.*,
                   COUNT(DISTINCT sli.sale_id) as times_sold,
                   SUM(sli.quantity) as total_quantity_sold,
                   SUM(sli.quantity * sli.price_at_sale) as total_revenue,
                   CASE 
                       WHEN si.quantity_in_stock = 0 THEN 'Out of Stock'
                       WHEN si.quantity_in_stock <= 10 THEN 'Low Stock'
                       ELSE 'In Stock'
                   END as stock_status
            FROM SHOP_ITEM si
            LEFT JOIN SALE_ITEM sli ON si.item_id = sli.item_id
            GROUP BY si.item_id
            ORDER BY total_revenue DESC
        ")->fetch_all(MYSQLI_ASSOC);
        break;
        
    case 'membership':
        $report_data = $db->query("
            SELECT 
                membership_type,
                COUNT(*) as member_count,
                COUNT(CASE WHEN expiration_date >= CURDATE() THEN 1 END) as active_members,
                COUNT(CASE WHEN expiration_date < CURDATE() THEN 1 END) as expired_members,
                COUNT(CASE WHEN is_student = 1 THEN 1 END) as student_count,
                COUNT(CASE WHEN auto_renew = 1 THEN 1 END) as auto_renew_count
            FROM MEMBER
            GROUP BY membership_type
            ORDER BY membership_type
        ")->fetch_all(MYSQLI_ASSOC);
        break;
        
    case 'donations':
        $report_data = $db->query("
            SELECT d.*,
                   COALESCE(don.organization_name, CONCAT(don.first_name, ' ', don.last_name)) as donor_name,
                   don.is_organization,
                   a.title as acquisition_artwork,
                   CASE d.purpose
                       WHEN 1 THEN 'General Support'
                       WHEN 2 THEN 'Acquisition Fund'
                       WHEN 3 THEN 'Exhibition Sponsorship'
                       WHEN 4 THEN 'Conservation'
                       WHEN 5 THEN 'Educational Programs'
                       ELSE 'Other'
                   END as purpose_label
            FROM DONATION d
            LEFT JOIN DONOR don ON d.donor_id = don.donor_id
            LEFT JOIN ACQUISITION acq ON d.acquisition_id = acq.acquisition_id
            LEFT JOIN ARTWORK a ON acq.artwork_id = a.artwork_id
            ORDER BY d.donation_date DESC
        ")->fetch_all(MYSQLI_ASSOC);
        break;
        
    case 'acquisitions':
        $report_data = $db->query("
            SELECT acq.*,
                   aw.title as artwork_title,
                   CONCAT(ar.first_name, ' ', ar.last_name) as artist_name,
                   CASE acq.method
                       WHEN 1 THEN 'Purchase'
                       WHEN 2 THEN 'Gift'
                       WHEN 3 THEN 'Bequest'
                       WHEN 4 THEN 'Transfer'
                       ELSE 'Other'
                   END as method_label
            FROM ACQUISITION acq
            LEFT JOIN ARTWORK aw ON acq.artwork_id = aw.artwork_id
            LEFT JOIN ARTWORK_CREATOR ac ON aw.artwork_id = ac.artwork_id
            LEFT JOIN ARTIST ar ON ac.artist_id = ar.artist_id
            ORDER BY acq.acquisition_date DESC
        ")->fetch_all(MYSQLI_ASSOC);
        break;
        
    case 'activity':
        $report_data = $db->query("
            SELECT al.*,
                   u.username,
                   u.user_type
            FROM ACTIVITY_LOG al
            LEFT JOIN USER_ACCOUNT u ON al.user_id = u.user_id
            ORDER BY al.timestamp DESC
            LIMIT 100
        ")->fetch_all(MYSQLI_ASSOC);
        break;
        
    case 'financial':
        // financial overview
        $report_data = [
            'shop_revenue' => $db->query("
                SELECT 
                    DATE_FORMAT(sale_date, '%Y-%m') as month,
                    SUM(total_amount) as revenue
                FROM SALE 
                WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                GROUP BY month
                ORDER BY month DESC
            ")->fetch_all(MYSQLI_ASSOC),
            
            'donations_summary' => $db->query("
                SELECT 
                    DATE_FORMAT(donation_date, '%Y-%m') as month,
                    COUNT(*) as donation_count,
                    SUM(amount) as total_amount
                FROM DONATION
                WHERE donation_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                GROUP BY month
                ORDER BY month DESC
            ")->fetch_all(MYSQLI_ASSOC),
            
            'acquisition_spending' => $db->query("
                SELECT 
                    DATE_FORMAT(acquisition_date, '%Y-%m') as month,
                    COUNT(*) as acquisition_count,
                    SUM(price_value) as total_spent
                FROM ACQUISITION
                WHERE acquisition_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                GROUP BY month
                ORDER BY month DESC
            ")->fetch_all(MYSQLI_ASSOC)
        ];
        break;
}

include __DIR__ . '/../templates/layout_header.php';
?>

<style>
@media print {
    .no-print { display: none !important; }
    .sidebar { display: none !important; }
    .navbar { display: none !important; }
    .report-card { page-break-inside: avoid; }
}

.report-nav {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 2rem;
}

.report-nav .nav-link {
    color: #495057;
    padding: 0.5rem 1rem;
    border-radius: 5px;
    transition: all 0.2s;
}

.report-nav .nav-link:hover {
    background: #e9ecef;
}

.report-nav .nav-link.active {
    background: #0d6efd;
    color: white;
}

.report-card {
    background: white;
    padding: 2rem;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.stat-box {
    padding: 1.5rem;
    border-radius: 8px;
    text-align: center;
    transition: transform 0.2s;
}

.stat-box:hover {
    transform: translateY(-3px);
}

.print-btn {
    position: fixed;
    bottom: 2rem;
    right: 2rem;
    width: 60px;
    height: 60px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
}

table.report-table {
    font-size: 0.9rem;
}

table.report-table th {
    background: #f8f9fa;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.5px;
}
</style>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4 no-print">
    <div>
        <h2><i class="bi bi-graph-up"></i> Comprehensive Reports</h2>
        <p class="text-muted">Complete analytics and insights for museum operations</p>
    </div>
    <button class="btn btn-outline-primary" onclick="window.print()">
        <i class="bi bi-printer"></i> Print Report
    </button>
</div>

<!-- Report navigation -->
<div class="report-nav no-print">
    <div class="row">
        <div class="col-md-12">
            <nav class="nav nav-pills flex-column flex-md-row">
                <a class="nav-link <?= $active_report === 'overview' ? 'active' : '' ?>" href="?report=overview">
                    <i class="bi bi-speedometer2"></i> Overview
                </a>
                <a class="nav-link <?= $active_report === 'by_artist' ? 'active' : '' ?>" href="?report=by_artist">
                    <i class="bi bi-palette"></i> Artists
                </a>
                <a class="nav-link <?= $active_report === 'by_period' ? 'active' : '' ?>" href="?report=by_period">
                    <i class="bi bi-clock-history"></i> By Period
                </a>
                <a class="nav-link <?= $active_report === 'exhibitions' ? 'active' : '' ?>" href="?report=exhibitions">
                    <i class="bi bi-building"></i> Exhibitions
                </a>
                <a class="nav-link <?= $active_report === 'events' ? 'active' : '' ?>" href="?report=events">
                    <i class="bi bi-calendar-event"></i> Events
                </a>
                <a class="nav-link <?= $active_report === 'sales' ? 'active' : '' ?>" href="?report=sales">
                    <i class="bi bi-cart"></i> Sales
                </a>
                <a class="nav-link <?= $active_report === 'inventory' ? 'active' : '' ?>" href="?report=inventory">
                    <i class="bi bi-box-seam"></i> Inventory
                </a>
                <a class="nav-link <?= $active_report === 'membership' ? 'active' : '' ?>" href="?report=membership">
                    <i class="bi bi-people"></i> Membership
                </a>
                <a class="nav-link <?= $active_report === 'donations' ? 'active' : '' ?>" href="?report=donations">
                    <i class="bi bi-heart"></i> Donations
                </a>
                <a class="nav-link <?= $active_report === 'acquisitions' ? 'active' : '' ?>" href="?report=acquisitions">
                    <i class="bi bi-cart-plus"></i> Acquisitions
                </a>
                <a class="nav-link <?= $active_report === 'financial' ? 'active' : '' ?>" href="?report=financial">
                    <i class="bi bi-currency-dollar"></i> Financial
                </a>
                <a class="nav-link <?= $active_report === 'activity' ? 'active' : '' ?>" href="?report=activity">
                    <i class="bi bi-activity"></i> Activity Log
                </a>
            </nav>
        </div>
    </div>
</div>

<!-- report content -->
<div class="report-card">
    <?php if ($active_report === 'overview'): ?>
        <h4 class="mb-4"><i class="bi bi-speedometer2"></i> Museum Overview</h4>
        <p class="text-muted">Comprehensive snapshot of museum operations and statistics</p>
        
        <div class="row g-4 mt-3">
            <div class="col-md-3">
                <div class="stat-box bg-primary text-white">
                    <h2 class="mb-2"><?= number_format($report_data['artworks']) ?></h2>
                    <div class="small">Total Artworks</div>
                    <div class="mt-2 small opacity-75"><?= number_format($report_data['owned_artworks']) ?> Owned</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box bg-success text-white">
                    <h2 class="mb-2"><?= number_format($report_data['exhibitions']) ?></h2>
                    <div class="small">Active Exhibitions</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box bg-info text-white">
                    <h2 class="mb-2"><?= number_format($report_data['artists']) ?></h2>
                    <div class="small">Artists</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box bg-warning text-white">
                    <h2 class="mb-2"><?= number_format($report_data['members']) ?></h2>
                    <div class="small">Active Members</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box bg-secondary text-white">
                    <h2 class="mb-2"><?= number_format($report_data['upcoming_events']) ?></h2>
                    <div class="small">Upcoming Events</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box bg-success text-white">
                    <h2 class="mb-2">$<?= number_format($report_data['total_sales']) ?></h2>
                    <div class="small">Shop Revenue (YTD)</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box bg-danger text-white">
                    <h2 class="mb-2">$<?= number_format($report_data['total_donations']) ?></h2>
                    <div class="small">Donations (YTD)</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box bg-dark text-white">
                    <h2 class="mb-2">$<?= number_format($report_data['total_sales'] + $report_data['total_donations']) ?></h2>
                    <div class="small">Total Revenue (YTD)</div>
                </div>
            </div>
        </div>
        
    <?php elseif ($active_report === 'by_artist'): ?>
        <h4 class="mb-4"><i class="bi bi-palette"></i> Artworks by Artist</h4>
        <p class="text-muted">Collection organized by artist with total artwork counts</p>
        
        <div class="table-responsive mt-4">
            <table class="table table-hover report-table">
                <thead>
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
                                <span class="badge bg-primary"><?= $row['artwork_count'] ?></span>
                            </td>
                            <td class="small text-muted">
                                <?= htmlspecialchars(substr($row['artwork_titles'] ?? 'No titles', 0, 80)) ?>
                                <?= strlen($row['artwork_titles'] ?? '') > 80 ? '...' : '' ?>
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
        
    <?php elseif ($active_report === 'by_period'): ?>
        <h4 class="mb-4"><i class="bi bi-clock-history"></i> Artworks by Historical Period</h4>
        <p class="text-muted">Collection organized by creation period and ownership status</p>
        
        <div class="table-responsive mt-4">
            <table class="table table-hover report-table">
                <thead>
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
                        $ownership_pct = $row['artwork_count'] > 0 ? ($row['owned_count'] / $row['artwork_count']) * 100 : 0;
                    ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($row['period']) ?></strong></td>
                            <td><?= $row['min_year'] ?? '?' ?> - <?= $row['max_year'] ?? '?' ?></td>
                            <td class="text-center"><?= $row['artwork_count'] ?></td>
                            <td class="text-center"><span class="badge bg-success"><?= $row['owned_count'] ?></span></td>
                            <td class="text-center"><span class="badge bg-warning"><?= $row['loan_count'] ?></span></td>
                            <td class="text-center"><?= number_format($ownership_pct, 1) ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <td colspan="2"><strong>Totals:</strong></td>
                        <td class="text-center"><strong><?= $total_artworks ?></strong></td>
                        <td class="text-center"><strong><?= $total_owned ?></strong></td>
                        <td class="text-center"><strong><?= $total_artworks - $total_owned ?></strong></td>
                        <td class="text-center"><strong><?= $total_artworks > 0 ? number_format(($total_owned / $total_artworks) * 100, 1) : 0 ?>%</strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
    <?php elseif ($active_report === 'exhibitions'): ?>
        <h4 class="mb-4"><i class="bi bi-building"></i> Exhibitions Report</h4>
        <p class="text-muted">All exhibitions with status, curator, and artwork counts</p>
        
        <div class="table-responsive mt-4">
            <table class="table table-hover report-table">
                <thead>
                    <tr>
                        <th>Exhibition Title</th>
                        <th>Curator</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th class="text-center">Artworks</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report_data as $row): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($row['title']) ?></strong></td>
                            <td><?= htmlspecialchars($row['curator_name'] ?? 'TBD') ?></td>
                            <td><?= date('M j, Y', strtotime($row['start_date'])) ?></td>
                            <td><?= date('M j, Y', strtotime($row['end_date'])) ?></td>
                            <td class="text-center"><span class="badge bg-primary"><?= $row['artwork_count'] ?></span></td>
                            <td class="text-center">
                                <span class="badge bg-<?= $row['status'] === 'Active' ? 'success' : ($row['status'] === 'Upcoming' ? 'warning' : 'secondary') ?>">
                                    <?= htmlspecialchars($row['status']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
    <?php elseif ($active_report === 'events'): ?>
        <h4 class="mb-4"><i class="bi bi-calendar-event"></i> Events & Attendance Report</h4>
        <p class="text-muted">Event statistics including ticket sales and check-ins</p>
        
        <div class="table-responsive mt-4">
            <table class="table table-hover report-table">
                <thead>
                    <tr>
                        <th>Event Name</th>
                        <th>Date</th>
                        <th>Location</th>
                        <th class="text-center">Capacity</th>
                        <th class="text-center">Tickets Sold</th>
                        <th class="text-center">Checked In</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report_data as $row): 
                        $percent_sold = $row['capacity'] > 0 ? ($row['total_attendees'] / $row['capacity']) * 100 : 0;
                    ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
                            <td><?= date('M j, Y', strtotime($row['event_date'])) ?></td>
                            <td><?= htmlspecialchars($row['location_name']) ?></td>
                            <td class="text-center"><?= $row['capacity'] ?></td>
                            <td class="text-center">
                                <?= $row['total_attendees'] ?? 0 ?>
                                <small class="text-muted">(<?= number_format($percent_sold, 0) ?>%)</small>
                            </td>
                            <td class="text-center"><span class="badge bg-success"><?= $row['checked_in'] ?? 0 ?></span></td>
                            <td class="text-center">
                                <span class="badge bg-<?= $row['status'] === 'Today' ? 'warning' : ($row['status'] === 'Upcoming' ? 'info' : 'secondary') ?>">
                                    <?= htmlspecialchars($row['status']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
    <?php elseif ($active_report === 'sales'): ?>
        <h4 class="mb-4"><i class="bi bi-cart"></i> Shop Sales Report</h4>
        <p class="text-muted">Monthly sales performance and revenue analysis</p>
        
        <div class="table-responsive mt-4">
            <table class="table table-hover report-table">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th class="text-center">Transactions</th>
                        <th class="text-end">Total Revenue</th>
                        <th class="text-end">Total Discounts</th>
                        <th class="text-center">Member Sales</th>
                        <th class="text-end">Avg Transaction</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_revenue = 0;
                    $total_transactions = 0;
                    foreach ($report_data as $row): 
                        $total_revenue += $row['total_revenue'];
                        $total_transactions += $row['transaction_count'];
                    ?>
                        <tr>
                            <td><strong><?= date('F Y', strtotime($row['month'] . '-01')) ?></strong></td>
                            <td class="text-center"><?= number_format($row['transaction_count']) ?></td>
                            <td class="text-end">$<?= number_format($row['total_revenue'], 2) ?></td>
                            <td class="text-end text-danger">-$<?= number_format($row['total_discounts'], 2) ?></td>
                            <td class="text-center"><?= $row['member_sales'] ?></td>
                            <td class="text-end">$<?= number_format($row['avg_transaction'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <td><strong>Totals:</strong></td>
                        <td class="text-center"><strong><?= number_format($total_transactions) ?></strong></td>
                        <td class="text-end"><strong>$<?= number_format($total_revenue, 2) ?></strong></td>
                        <td colspan="3"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
    <?php elseif ($active_report === 'inventory'): ?>
        <h4 class="mb-4"><i class="bi bi-box-seam"></i> Inventory & Product Performance</h4>
        <p class="text-muted">Shop inventory with sales data and stock status</p>
        
        <div class="table-responsive mt-4">
            <table class="table table-hover report-table">
                <thead>
                    <tr>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th class="text-end">Price</th>
                        <th class="text-center">Stock</th>
                        <th class="text-center">Sold</th>
                        <th class="text-end">Revenue</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report_data as $row): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($row['item_name']) ?></strong></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($row['category']) ?></span></td>
                            <td class="text-end">$<?= number_format($row['price'], 2) ?></td>
                            <td class="text-center"><?= $row['quantity_in_stock'] ?></td>
                            <td class="text-center"><?= $row['total_quantity_sold'] ?? 0 ?></td>
                            <td class="text-end">$<?= number_format($row['total_revenue'] ?? 0, 2) ?></td>
                            <td class="text-center">
                                <span class="badge bg-<?= $row['stock_status'] === 'In Stock' ? 'success' : ($row['stock_status'] === 'Low Stock' ? 'warning' : 'danger') ?>">
                                    <?= htmlspecialchars($row['stock_status']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
    <?php elseif ($active_report === 'membership'): ?>
        <h4 class="mb-4"><i class="bi bi-people"></i> Membership Report</h4>
        <p class="text-muted">Membership statistics by type and status</p>
        
        <div class="table-responsive mt-4">
            <table class="table table-hover report-table">
                <thead>
                    <tr>
                        <th>Membership Type</th>
                        <th class="text-center">Total Members</th>
                        <th class="text-center">Active</th>
                        <th class="text-center">Expired</th>
                        <th class="text-center">Students</th>
                        <th class="text-center">Auto-Renew</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $type_labels = [1 => 'Individual', 2 => 'Family', 3 => 'Student', 4 => 'Senior', 5 => 'Patron'];
                    foreach ($report_data as $row): 
                    ?>
                        <tr>
                            <td><strong><?= $type_labels[$row['membership_type']] ?? 'Unknown' ?></strong></td>
                            <td class="text-center"><?= $row['member_count'] ?></td>
                            <td class="text-center"><span class="badge bg-success"><?= $row['active_members'] ?></span></td>
                            <td class="text-center"><span class="badge bg-secondary"><?= $row['expired_members'] ?></span></td>
                            <td class="text-center"><?= $row['student_count'] ?></td>
                            <td class="text-center"><?= $row['auto_renew_count'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
    <?php elseif ($active_report === 'donations'): ?>
        <h4 class="mb-4"><i class="bi bi-heart"></i> Donations Report</h4>
        <p class="text-muted">Complete donation history with donor information</p>
        
        <div class="table-responsive mt-4">
            <table class="table table-hover report-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Donor</th>
                        <th>Purpose</th>
                        <th class="text-end">Amount</th>
                        <th>Linked Acquisition</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_donations = 0;
                    foreach ($report_data as $row): 
                        $total_donations += $row['amount'];
                    ?>
                        <tr>
                            <td><?= date('M j, Y', strtotime($row['donation_date'])) ?></td>
                            <td>
                                <?= htmlspecialchars($row['donor_name']) ?>
                                <?php if ($row['is_organization']): ?>
                                    <span class="badge bg-info">Org</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($row['purpose_label']) ?></span></td>
                            <td class="text-end"><strong>$<?= number_format($row['amount'], 2) ?></strong></td>
                            <td class="small text-muted"><?= htmlspecialchars($row['acquisition_artwork'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <td colspan="3"><strong>Total Donations:</strong></td>
                        <td class="text-end"><strong>$<?= number_format($total_donations, 2) ?></strong></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
    <?php elseif ($active_report === 'acquisitions'): ?>
        <h4 class="mb-4"><i class="bi bi-cart-plus"></i> Acquisitions Report</h4>
        <p class="text-muted">Complete acquisition history with artwork details</p>
        
        <div class="table-responsive mt-4">
            <table class="table table-hover report-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Artwork</th>
                        <th>Artist</th>
                        <th>Method</th>
                        <th class="text-end">Value</th>
                        <th>Source</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_value = 0;
                    foreach ($report_data as $row): 
                        $total_value += $row['price_value'] ?? 0;
                    ?>
                        <tr>
                            <td><?= date('M j, Y', strtotime($row['acquisition_date'])) ?></td>
                            <td><strong><?= htmlspecialchars($row['artwork_title']) ?></strong></td>
                            <td><?= htmlspecialchars($row['artist_name'] ?? 'Unknown') ?></td>
                            <td><span class="badge bg-info"><?= htmlspecialchars($row['method_label']) ?></span></td>
                            <td class="text-end">
                                <?php if ($row['price_value']): ?>
                                    $<?= number_format($row['price_value'], 2) ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted"><?= htmlspecialchars($row['source_name'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <td colspan="4"><strong>Total Value:</strong></td>
                        <td class="text-end"><strong>$<?= number_format($total_value, 2) ?></strong></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
    <?php elseif ($active_report === 'financial'): ?>
        <h4 class="mb-4"><i class="bi bi-currency-dollar"></i> Financial Summary</h4>
        <p class="text-muted">Comprehensive financial overview - last 12 months</p>
        
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0"><i class="bi bi-shop"></i> Shop Revenue</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <?php foreach ($report_data['shop_revenue'] as $row): ?>
                                <tr>
                                    <td><?= date('M Y', strtotime($row['month'] . '-01')) ?></td>
                                    <td class="text-end">$<?= number_format($row['revenue'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h6 class="mb-0"><i class="bi bi-heart"></i> Donations</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <?php foreach ($report_data['donations_summary'] as $row): ?>
                                <tr>
                                    <td><?= date('M Y', strtotime($row['month'] . '-01')) ?></td>
                                    <td class="text-end">${= number_format($row['total_amount'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0"><i class="bi bi-cart-plus"></i> Acquisition Spending</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <?php foreach ($report_data['acquisition_spending'] as $row): ?>
                                <tr>
                                    <td><?= date('M Y', strtotime($row['month'] . '-01')) ?></td>
                                    <td class="text-end">${= number_format($row['total_spent'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
    <?php elseif ($active_report === 'activity'): ?>
        <h4 class="mb-4"><i class="bi bi-activity"></i> User Activity Log</h4>
        <p class="text-muted">Recent system activity (last 100 actions)</p>
        
        <div class="table-responsive mt-4">
            <table class="table table-hover report-table">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>User</th>
                        <th>Role</th>
                        <th>Action</th>
                        <th>Table</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report_data as $row): ?>
                        <tr>
                            <td class="small"><?= date('M j, Y g:i A', strtotime($row['timestamp'])) ?></td>
                            <td><?= htmlspecialchars($row['username'] ?? 'Deleted User') ?></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($row['user_type']) ?></span></td>
                            <td><span class="badge bg-primary"><?= htmlspecialchars($row['action_type']) ?></span></td>
                            <td class="small"><?= htmlspecialchars($row['table_name'] ?? '—') ?></td>
                            <td class="small text-muted"><?= htmlspecialchars($row['description'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Print Button -->
<button class="btn btn-primary btn-lg rounded-circle print-btn no-print" onclick="window.print()" title="Print Report">
    <i class="bi bi-printer fs-4"></i>
</button>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>