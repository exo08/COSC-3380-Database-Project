<?php
session_start();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/permissions.php';

$page_title = 'Reports';
$db = db();

$user_role = $_SESSION['user_type'];

include __DIR__ . '/../templates/layout_header.php';
?>

<div class="container-fluid">
    <h1 class="mb-4"><i class="bi bi-file-bar-graph"></i> Reports Dashboard</h1>
    
    <!-- Report Categories -->
    <div class="row g-4">
        <!-- Collection Management Reports -->
        <?php if ($user_role === 'admin' || $user_role === 'curator'): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card h-100">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-palette"></i> Collection Management</h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <?php if (hasReportAccess('acquisition-history')): ?>
                        <a href="acquisition-history.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-clock-history"></i> Acquisition History
                        </a>
                        <?php endif; ?>
                        
                        <?php if (hasReportAccess('artwork-catalog')): ?>
                        <a href="artwork-catalog.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-book"></i> Full Artwork Catalog
                        </a>
                        <?php endif; ?>
                        
                        <?php if (hasReportAccess('owned-or-loaned')): ?>
                        <a href="owned-or-loaned.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-building"></i> Owned vs Loaned
                        </a>
                        <?php endif; ?>
                        
                        <?php if (hasReportAccess('artwork-by-artist')): ?>
                        <a href="artwork-by-artist.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-person"></i> Artwork by Artist
                        </a>
                        <?php endif; ?>
                        
                        <?php if (hasReportAccess('artwork-by-medium')): ?>
                        <a href="artwork-by-medium.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-brush"></i> Artwork by Medium
                        </a>
                        <?php endif; ?>
                        
                        <?php if (hasReportAccess('artwork-by-period')): ?>
                        <a href="artwork-by-period.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-calendar-range"></i> Artwork by Period
                        </a>
                        <?php endif; ?>
                        
                        <?php if (hasReportAccess('artwork-dimensions')): ?>
                        <a href="artwork-dimensions.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-rulers"></i> Artwork Dimensions
                        </a>
                        <?php endif; ?>
                        
                        <?php if (hasReportAccess('unlocated-artworks')): ?>
                        <a href="unlocated-artworks.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-exclamation-triangle"></i> Unlocated Artworks
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Financial Reports -->
        <?php if ($user_role === 'admin' || $user_role === 'curator' || $user_role === 'shop_staff'): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card h-100">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-currency-dollar"></i> Financial Reports</h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <?php if (hasReportAccess('top-donors')): ?>
                        <a href="top-donors.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-trophy"></i> Top Donors
                        </a>
                        <?php endif; ?>
                        
                        <?php if (hasReportAccess('revenue-by-item')): ?>
                        <a href="revenue-by-item.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-bag"></i> Revenue by Item
                        </a>
                        <?php endif; ?>
                        
                        <?php if (hasReportAccess('revenue-by-category')): ?>
                        <a href="revenue-by-category.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-tags"></i> Revenue by Category
                        </a>
                        <?php endif; ?>
                        
                        <?php if (hasReportAccess('member-sales')): ?>
                        <a href="member-sales.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-person-badge"></i> Member Sales
                        </a>
                        <?php endif; ?>
                        
                        <?php if (hasReportAccess('visitor-sales')): ?>
                        <a href="visitor-sales.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-people"></i> Visitor Sales
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Exhibition & Events -->
        <?php if ($user_role === 'admin' || $user_role === 'curator' || $user_role === 'event_staff'): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card h-100">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0"><i class="bi bi-calendar-event"></i> Exhibitions & Events</h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <?php if (hasReportAccess('current-exhibitions')): ?>
                        <a href="current-exhibitions.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-building"></i> Current Exhibitions
                        </a>
                        <?php endif; ?>
                        
                        <?php if (hasReportAccess('exhibition-attendance')): ?>
                        <a href="exhibition-attendance.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-people-fill"></i> Exhibition Attendance
                        </a>
                        <?php endif; ?>
                        
                        <?php if (hasReportAccess('curator-portfolio')): ?>
                        <a href="curator-portfolio.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-briefcase"></i> Curator Portfolio
                        </a>
                        <?php endif; ?>
                        
                        <?php if (hasReportAccess('event-attendance')): ?>
                        <a href="event-attendance.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-clipboard-check"></i> Event Attendance
                        </a>
                        <?php endif; ?>
                        
                        <?php if (hasReportAccess('upcoming-events')): ?>
                        <a href="upcoming-events.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-calendar-check"></i> Upcoming Events
                        </a>
                        <?php endif; ?>
                        
                        <?php if (hasReportAccess('events-near-capacity')): ?>
                        <a href="events-near-capacity.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-exclamation-circle"></i> Events Near Capacity
                        </a>
                        <?php endif; ?>
                        <?php if (hasReportAccess('exhibition-artwork-list')): ?>
                        <a href="exhibition-artwork-list.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-easel"></i> Exhibition Artwork List
                        </a>
                        <?php endif; ?>

                        <?php if (hasReportAccess('exhibition-timeline')): ?>
                        <a href="exhibition-timeline.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-calendar3"></i> Exhibition Timeline
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Membership & Visitors (Admin only) -->
        <?php if ($user_role === 'admin'): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card h-100">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-person-check"></i> Membership & Visitors</h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <a href="active-memberships.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-card-checklist"></i> Active Memberships
                        </a>
                        <a href="expiring-members.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-alarm"></i> Expiring Memberships
                        </a>
                        <a href="visitor-frequency.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-graph-up"></i> Visitor Frequency Analysis
                        </a>
                        <a href="demographics.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-pie-chart"></i> Demographics Report
                        </a>
                        <a href="activity-log.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-journal-text"></i> Activity Log
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>