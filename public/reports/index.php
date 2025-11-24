<?php
session_start();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/permissions.php';

$page_title = 'Reports';
$db = db();

$user_role = $_SESSION['user_type'];

include __DIR__ . '/../templates/layout_header.php';
?>

<style>
.executive-report-card {
    transition: all 0.3s ease;
    border-left: 4px solid #6f42c1;
}

.executive-report-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 16px rgba(111, 66, 193, 0.2);
}

.quick-search-section {
    margin-top: 2rem;
}

.section-badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
}
</style>

<div class="container-fluid">
    <h1 class="mb-4"><i class="bi bi-file-bar-graph"></i> Reports Dashboard</h1>
    
    <!-- Executive Reports Section (Admin Only) -->
    <?php if ($user_role === 'admin'): ?>
    <div class="row mb-5">
        <div class="col-12">
            <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="card-body text-white">
                    <h3 class="mb-2"><i class="bi bi-graph-up-arrow"></i> Executive Reports</h3>
                    <p class="mb-0 opacity-75">Comprehensive multi-table analysis reports with advanced filtering capabilities</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row g-4 mb-5">
        <div class="col-md-6 col-xl-3">
            <a href="financial-performance.php" class="text-decoration-none">
                <div class="card h-100 executive-report-card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3">
                                <i class="bi bi-currency-dollar fs-3 text-success"></i>
                            </div>
                            <div>
                                <span class="badge bg-success section-badge">Financial</span>
                            </div>
                        </div>
                        <h5 class="card-title mb-2">Financial Performance</h5>
                        <p class="card-text text-muted small">Revenue trends, sales analysis, donor contributions, and acquisition spending</p>
                    </div>
                </div>
            </a>
        </div>
        
        <div class="col-md-6 col-xl-3">
            <a href="collection-analysis.php" class="text-decoration-none">
                <div class="card h-100 executive-report-card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3">
                                <i class="bi bi-palette fs-3 text-primary"></i>
                            </div>
                            <div>
                                <span class="badge bg-primary section-badge">Collection</span>
                            </div>
                        </div>
                        <h5 class="card-title mb-2">Collection Analysis</h5>
                        <p class="card-text text-muted small">Acquisition trends, owned vs loaned, space utilization, and collection growth</p>
                    </div>
                </div>
            </a>
        </div>
        
        <div class="col-md-6 col-xl-3">
            <a href="exhibition-analytics.php" class="text-decoration-none">
                <div class="card h-100 executive-report-card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="rounded-circle bg-warning bg-opacity-10 p-3 me-3">
                                <i class="bi bi-calendar-event fs-3 text-warning"></i>
                            </div>
                            <div>
                                <span class="badge bg-warning section-badge">Exhibitions</span>
                            </div>
                        </div>
                        <h5 class="card-title mb-2">Exhibition Analytics</h5>
                        <p class="card-text text-muted small">Attendance comparison, capacity utilization, curator performance, and revenue</p>
                    </div>
                </div>
            </a>
        </div>
        
        <div class="col-md-6 col-xl-3">
            <a href="membership-insights.php" class="text-decoration-none">
                <div class="card h-100 executive-report-card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="rounded-circle bg-info bg-opacity-10 p-3 me-3">
                                <i class="bi bi-people fs-3 text-info"></i>
                            </div>
                            <div>
                                <span class="badge bg-info section-badge">Membership</span>
                            </div>
                        </div>
                        <h5 class="card-title mb-2">Membership Insights</h5>
                        <p class="card-text text-muted small">Demographics, visitor frequency, retention rates, and member activity analysis</p>
                    </div>
                </div>
            </a>
        </div>
    </div>
    
    <hr class="my-5">
    <?php endif; ?>
    
    <!-- Quick Searches Section -->
    <div class="quick-search-section">
        <h3 class="mb-4"><i class="bi bi-search"></i> Quick Searches</h3>
        
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
                            <?php if (hasReportAccess('advanced-artwork-search')): ?>
                                <a href="advanced-artwork-search.php" class="list-group-item list-group-item-action">
                                    <i class="bi bi-funnel"></i> Advanced Artwork Search
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
                                    <i class="bi bi-clock-history"></i> Artwork by Period
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
            <?php if ($user_role === 'admin' || $user_role === 'shop_staff'): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="bi bi-currency-dollar"></i> Finances</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <?php if(hasReportAccess('top-donors')):?>
                                <a href="top-donors.php" class="list-group-item list-group-item-action">
                                    <i class="bi bi-trophy"></i> Top Donors
                                </a>
                            <?php endif;?>
                            <?php if(hasReportAccess('revenue-by-item')):?>
                                <a href="revenue-by-item.php" class="list-group-item list-group-item-action">
                                    <i class="bi bi-cart"></i> Revenue by Item
                                </a>
                            <?php endif;?>
                            <?php if(hasReportAccess('revenue-by-category')):?>
                                <a href="revenue-by-category.php" class="list-group-item list-group-item-action">
                                    <i class="bi bi-tags"></i> Revenue by Category
                                </a>
                            <?php endif;?>
                            <?php if(hasReportAccess('member-sales')):?>
                                <a href="member-sales.php" class="list-group-item list-group-item-action">
                                    <i class="bi bi-person-check"></i> Member Sales
                                </a>
                            <?php endif;?>
                            <?php if(hasReportAccess('visitor-sales')):?>
                                <a href="visitor-sales.php" class="list-group-item list-group-item-action">
                                    <i class="bi bi-person"></i> Visitor Sales
                                </a>
                            <?php endif;?>
                            <?php if(hasReportAccess('human-donor-summary')):?>
                                <a href="human-donor-summary.php" class="list-group-item list-group-item-action">
                                    <i class="bi bi-search"></i> Individual Donor Lookup
                                </a>
                            <?php endif;?>
                            <?php if(hasReportAccess('org-donor-summary')):?>
                                <a href="org-donor-summary.php" class="list-group-item list-group-item-action">
                                    <i class="bi bi-building"></i> Organization Donor Lookup
                                </a>
                            <?php endif;?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Exhibitions & Events -->
            <?php if ($user_role === 'admin' || $user_role === 'curator' || $user_role === 'event_staff'): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-header bg-warning text-white">
                        <h5 class="mb-0"><i class="bi bi-calendar-event"></i> Exhibitions & Events</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <?php if(hasReportAccess('current-exhibitions')):?>
                                <a href="current-exhibitions.php" class="list-group-item list-group-item-action">
                                    <i class="bi bi-building"></i> Current Exhibitions
                                </a>
                            <?php endif;?>
                            <?php if(hasReportAccess('exhibition-attendance')):?>
                                <a href="exhibition-attendance.php" class="list-group-item list-group-item-action">
                                    <i class="bi bi-people-fill"></i> Exhibition Attendance
                                </a>
                            <?php endif;?>
                            <?php if(hasReportAccess('curator-portfolio')):?>
                                <a href="curator-portfolio.php" class="list-group-item list-group-item-action">
                                    <i class="bi bi-briefcase"></i> Curator Portfolio
                                </a>
                            <?php endif;?>
                            <?php if(hasReportAccess('event-attendance')):?>
                                <a href="event-attendance.php" class="list-group-item list-group-item-action">
                                    <i class="bi bi-clipboard-check"></i> Event Attendance
                                </a>
                            <?php endif;?>
                            <?php if(hasReportAccess('upcoming-events')):?>
                                <a href="upcoming-events.php" class="list-group-item list-group-item-action">
                                    <i class="bi bi-calendar-check"></i> Upcoming Events
                                </a>
                            <?php endif;?>
                            <?php if(hasReportAccess('events-near-capacity')):?>
                                <a href="events-near-capacity.php" class="list-group-item list-group-item-action">
                                    <i class="bi bi-exclamation-circle"></i> Events Near Capacity
                                </a>
                            <?php endif;?>
                            <?php if(hasReportAccess('exhibition-artwork-list')):?>
                                <a href="exhibition-artwork-list.php" class="list-group-item list-group-item-action">
                                    <i class="bi bi-easel"></i> Exhibition Artwork List
                                </a>
                            <?php endif;?>
                            <?php if(hasReportAccess('exhibition-timeline')):?>
                                <a href="exhibition-timeline.php" class="list-group-item list-group-item-action">
                                    <i class="bi bi-calendar3"></i> Exhibition Timeline
                                </a>
                            <?php endif;?>
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
</div>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>