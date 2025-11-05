<?php
session_start();
require_once __DIR__ . '/../app/db.php';

$page_title = 'Reports';
$db = db();

// Check permissions (adjust based on your permissions system)
// if ($_SESSION['user_type'] !== 'admin' && $_SESSION['user_type'] !== 'curator') {
//     header('Location: /dashboard.php');
//     exit;
// }

include __DIR__ . '/../templates/layout_header.php';
?>

<div class="container-fluid">
    <h1 class="mb-4"><i class="bi bi-file-bar-graph"></i> Reports Dashboard</h1>
    
    <!-- Report Categories -->
    <div class="row g-4">
        <!-- Collection Management Reports -->
        <div class="col-md-6 col-lg-4">
            <div class="card h-100">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-palette"></i> Collection Management</h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <a href="acquisition-history.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-clock-history"></i> Acquisition History
                        </a>
                        <a href="artwork-catalog.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-book"></i> Full Artwork Catalog
                        </a>
                        <a href="artwork-by-artist.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-person"></i> Artwork by Artist
                        </a>
                        <a href="artwork-by-medium.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-brush"></i> Artwork by Medium
                        </a>
                        <a href="unlocated-artworks.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-exclamation-triangle"></i> Unlocated Artworks
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Financial Reports -->
        <div class="col-md-6 col-lg-4">
            <div class="card h-100">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-currency-dollar"></i> Financial Reports</h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <a href="top-donors.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-trophy"></i> Top Donors
                        </a>
                        <a href="revenue-by-item.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-bag"></i> Revenue by Item
                        </a>
                        <a href="revenue-by-category.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-tags"></i> Revenue by Category
                        </a>
                        <a href="member-sales.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-person-badge"></i> Member Sales
                        </a>
                        <a href="visitor-sales.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-people"></i> Visitor Sales
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Exhibition & Events -->
        <div class="col-md-6 col-lg-4">
            <div class="card h-100">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0"><i class="bi bi-calendar-event"></i> Exhibitions & Events</h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <a href="current-exhibitions.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-building"></i> Current Exhibitions
                        </a>
                        <a href="exhibition-attendance.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-people-fill"></i> Exhibition Attendance
                        </a>
                        <a href="curator-portfolio.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-briefcase"></i> Curator Portfolio
                        </a>
                        <a href="upcoming-events.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-calendar-check"></i> Upcoming Events
                        </a>
                        <a href="events-near-capacity.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-exclamation-circle"></i> Events Near Capacity
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Membership & Visitors -->
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
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>