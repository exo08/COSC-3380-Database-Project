<?php
// Financial Report
// Reports on all revenue sources: shop sales, memberships, event tickets

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

// Get date range from filters (default to last 12 months)
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-12 months'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$time_period = $_GET['time_period'] ?? 'monthly'; // daily, monthly, yearly
$revenue_source = $_GET['revenue_source'] ?? 'all'; // all, shop, memberships, events

// Membership pricing array
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

// ========== SHOP SALES REVENUE ==========
// Get detailed shop sales data with item breakdown
$shop_sales_query = "
    SELECT 
        si.item_name,
        SUM(sali.quantity) as total_quantity_sold,
        sali.price_at_sale as unit_price,
        SUM(sali.quantity * sali.price_at_sale) as total_revenue,
        COUNT(DISTINCT s.sale_id) as number_of_transactions,
        MIN(s.sale_date) as first_sale_date,
        MAX(s.sale_date) as last_sale_date
    FROM SALE_ITEM sali
    JOIN SHOP_ITEM si ON sali.item_id = si.item_id
    JOIN SALE s ON sali.sale_id = s.sale_id
    WHERE DATE(s.sale_date) BETWEEN ? AND ?
    GROUP BY si.item_id, si.item_name, sali.price_at_sale
    ORDER BY total_revenue DESC
";

$stmt = $db->prepare($shop_sales_query);
$stmt->bind_param("ss", $date_from, $date_to);
$stmt->execute();
$shop_sales_detail = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calculate shop totals
$total_shop_revenue = array_sum(array_column($shop_sales_detail, 'total_revenue'));
$total_shop_transactions = 0;
foreach ($shop_sales_detail as $item) {
    $total_shop_transactions += $item['number_of_transactions'];
}

// Individual transactions for each item part of the expandable rows
$shop_transactions_by_item = [];
$shop_transactions_query = "
    SELECT 
        si.item_name,
        sali.price_at_sale,
        s.sale_id,
        s.sale_date,
        sali.quantity,
        (sali.quantity * sali.price_at_sale) as transaction_total,
        s.payment_method,
        CASE 
            WHEN s.member_id IS NOT NULL THEN CONCAT('Member #', s.member_id)
            WHEN s.visitor_id IS NOT NULL THEN CONCAT('Visitor #', s.visitor_id)
            ELSE 'Walk-in'
        END as customer_type
    FROM SALE_ITEM sali
    JOIN SHOP_ITEM si ON sali.item_id = si.item_id
    JOIN SALE s ON sali.sale_id = s.sale_id
    WHERE DATE(s.sale_date) BETWEEN ? AND ?
    ORDER BY si.item_name, s.sale_date DESC
";

$stmt = $db->prepare($shop_transactions_query);
$stmt->bind_param("ss", $date_from, $date_to);
$stmt->execute();
$all_shop_transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Group transactions by item name and price
foreach ($all_shop_transactions as $transaction) {
    $key = $transaction['item_name'] . '_' . $transaction['price_at_sale'];
    if (!isset($shop_transactions_by_item[$key])) {
        $shop_transactions_by_item[$key] = [];
    }
    $shop_transactions_by_item[$key][] = $transaction;
}

// ========== MEMBERSHIP REVENUE ==========
// Get membership registrations within date range
$membership_revenue_query = "
    SELECT 
        membership_type,
        is_student,
        COUNT(*) as member_count,
        MIN(start_date) as first_registration,
        MAX(start_date) as last_registration
    FROM MEMBER
    WHERE start_date BETWEEN ? AND ?
    GROUP BY membership_type, is_student
    ORDER BY membership_type
";

$stmt = $db->prepare($membership_revenue_query);
$stmt->bind_param("ss", $date_from, $date_to);
$stmt->execute();
$membership_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calculate membership revenue with pricing
$membership_revenue_detail = [];
$total_membership_revenue = 0;
$total_memberships_sold = 0;

foreach ($membership_data as $membership) {
    $type = $membership['membership_type'];
    $base_price = $membership_prices[$type];
    $count = $membership['member_count'];
    $revenue = $base_price * $count;
    
    $membership_revenue_detail[] = [
        'type_name' => $membership_names[$type],
        'type_id' => $type,
        'is_student' => $membership['is_student'],
        'count' => $count,
        'price_per' => $base_price,
        'total_revenue' => $revenue,
        'first_registration' => $membership['first_registration'],
        'last_registration' => $membership['last_registration']
    ];
    
    $total_membership_revenue += $revenue;
    $total_memberships_sold += $count;
}

// Individual membership registrations for expandable rows
$membership_registrations = [];
$membership_registrations_query = "
    SELECT 
        member_id,
        CONCAT(first_name, ' ', last_name) as member_name,
        email,
        membership_type,
        is_student,
        start_date,
        expiration_date,
        auto_renew
    FROM MEMBER
    WHERE start_date BETWEEN ? AND ?
    ORDER BY membership_type, start_date DESC
";

$stmt = $db->prepare($membership_registrations_query);
$stmt->bind_param("ss", $date_from, $date_to);
$stmt->execute();
$all_memberships = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Group memberships by type and student status
foreach ($all_memberships as $member) {
    $key = $member['membership_type'] . '_' . $member['is_student'];
    if (!isset($membership_registrations[$key])) {
        $membership_registrations[$key] = [];
    }
    $membership_registrations[$key][] = $member;
}

// ========== REVENUE OVER TIME ==========
// Get revenue trend based on selected time period
$date_format = match($time_period) {
    'daily' => '%Y-%m-%d',
    'yearly' => '%Y',
    default => '%Y-%m' // monthly
};

$date_group = match($time_period) {
    'daily' => 'DATE(s.sale_date)',
    'yearly' => 'YEAR(s.sale_date)',
    default => 'DATE_FORMAT(s.sale_date, "%Y-%m")'
};

// Shop revenue over time
$shop_trend_query = "
    SELECT 
        $date_group as period,
        SUM(sali.quantity * sali.price_at_sale) as revenue,
        COUNT(DISTINCT s.sale_id) as transaction_count
    FROM SALE s
    JOIN SALE_ITEM sali ON s.sale_id = sali.sale_id
    WHERE DATE(s.sale_date) BETWEEN ? AND ?
    GROUP BY period
    ORDER BY period
";

$stmt = $db->prepare($shop_trend_query);
$stmt->bind_param("ss", $date_from, $date_to);
$stmt->execute();
$shop_trend = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Membership revenue over time
$membership_trend_query = "
    SELECT 
        DATE_FORMAT(start_date, '$date_format') as period,
        membership_type,
        COUNT(*) as member_count
    FROM MEMBER
    WHERE start_date BETWEEN ? AND ?
    GROUP BY period, membership_type
    ORDER BY period
";

$stmt = $db->prepare($membership_trend_query);
$stmt->bind_param("ss", $date_from, $date_to);
$stmt->execute();
$membership_trend_raw = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Process membership trend to calculate revenue
$membership_trend = [];
foreach ($membership_trend_raw as $row) {
    $period = $row['period'];
    $revenue = $membership_prices[$row['membership_type']] * $row['member_count'];
    
    if (!isset($membership_trend[$period])) {
        $membership_trend[$period] = ['period' => $period, 'revenue' => 0];
    }
    $membership_trend[$period]['revenue'] += $revenue;
}
$membership_trend = array_values($membership_trend);

// ========== SUMMARY STATISTICS ==========
$total_revenue = $total_shop_revenue + $total_membership_revenue;
$avg_shop_transaction = $total_shop_transactions > 0 ? $total_shop_revenue / $total_shop_transactions : 0;

// Calculate revenue by source percentages
$shop_percentage = $total_revenue > 0 ? ($total_shop_revenue / $total_revenue) * 100 : 0;
$membership_percentage = $total_revenue > 0 ? ($total_membership_revenue / $total_revenue) * 100 : 0;

// Page title
$page_title = 'Financial Performance';
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

.filter-badge {
    font-size: 0.875rem;
    padding: 0.5rem 1rem;
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

.expand-icon {
    margin-right: 0.5rem;
    transition: transform 0.3s;
}

.expanded .expand-icon {
    transform: rotate(90deg);
}
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="index.php" class="btn btn-outline-secondary mb-2">
                <i class="bi bi-arrow-left"></i> Back to Reports
            </a>
            <h1 class="mb-0"><i class="bi bi-cash-stack text-success"></i> Financial Performance</h1>
            <p class="text-muted">Comprehensive revenue analysis across all income sources</p>
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
                <div class="col-md-3">
                    <label class="form-label">From Date</label>
                    <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">To Date</label>
                    <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Time Period</label>
                    <select name="time_period" class="form-select">
                        <option value="daily" <?= $time_period === 'daily' ? 'selected' : '' ?>>Daily</option>
                        <option value="monthly" <?= $time_period === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                        <option value="yearly" <?= $time_period === 'yearly' ? 'selected' : '' ?>>Yearly</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Revenue Source</label>
                    <select name="revenue_source" class="form-select" id="revenueSourceFilter">
                        <option value="all" <?= $revenue_source === 'all' ? 'selected' : '' ?>>All Sources</option>
                        <option value="shop" <?= $revenue_source === 'shop' ? 'selected' : '' ?>>Shop Only</option>
                        <option value="memberships" <?= $revenue_source === 'memberships' ? 'selected' : '' ?>>Memberships Only</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Apply Filters
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card stat-card border-success h-100">
                <div class="card-body text-center">
                    <i class="bi bi-currency-dollar text-success" style="font-size: 2.5rem;"></i>
                    <h3 class="mb-0 mt-2">$<?= number_format($total_revenue, 2) ?></h3>
                    <p class="text-muted mb-0">Total Revenue</p>
                    <small class="text-muted">All sources combined</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card border-primary h-100">
                <div class="card-body text-center">
                    <i class="bi bi-shop text-primary" style="font-size: 2.5rem;"></i>
                    <h3 class="mb-0 mt-2">$<?= number_format($total_shop_revenue, 2) ?></h3>
                    <p class="text-muted mb-0">Shop Revenue</p>
                    <small class="text-muted"><?= number_format($shop_percentage, 1) ?>% of total</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card border-info h-100">
                <div class="card-body text-center">
                    <i class="bi bi-people text-info" style="font-size: 2.5rem;"></i>
                    <h3 class="mb-0 mt-2">$<?= number_format($total_membership_revenue, 2) ?></h3>
                    <p class="text-muted mb-0">Membership Revenue</p>
                    <small class="text-muted"><?= number_format($membership_percentage, 1) ?>% of total</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card border-warning h-100">
                <div class="card-body text-center">
                    <i class="bi bi-calculator text-warning" style="font-size: 2.5rem;"></i>
                    <h3 class="mb-0 mt-2">$<?= number_format($avg_shop_transaction, 2) ?></h3>
                    <p class="text-muted mb-0">Avg Transaction</p>
                    <small class="text-muted">Shop purchases</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Revenue Over Time Chart -->
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="bi bi-graph-up"></i> Revenue Trend Over Time</h5>
        </div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="revenueTrendChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Revenue Breakdown Pie Chart -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-pie-chart"></i> Revenue by Source</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="height: 300px;">
                        <canvas id="revenueSourceChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Top Selling Shop Items</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="height: 300px;">
                        <canvas id="topItemsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- DETAILED DATA TABLES -->
    <!-- Shop Sales Detail Table -->
    <?php if ($revenue_source === 'all' || $revenue_source === 'shop'): ?>
    <div class="card mb-4">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-table"></i> Shop Sales Detail</h5>
            <small class="text-muted"><i class="bi bi-info-circle"></i> Click any row to view individual transactions</small>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table class="table table-hover revenue-table mb-0" id="shopSalesTable">
                    <thead>
                        <tr>
                            <th style="padding-left: 2rem;">Item Name</th>
                            <th class="text-end">Unit Price</th>
                            <th class="text-end">Quantity Sold</th>
                            <th class="text-end">Total Revenue</th>
                            <th class="text-end">Transactions</th>
                            <th>Date Range</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($shop_sales_detail)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    No shop sales data for the selected period
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($shop_sales_detail as $idx => $item): ?>
                                <?php 
                                $item_key = $item['item_name'] . '_' . $item['unit_price'];
                                $transactions = $shop_transactions_by_item[$item_key] ?? [];
                                ?>
                                <!-- clickable rows -->
                                <tr class="expandable-row" data-target="shop-detail-<?= $idx ?>" onclick="toggleDetailRow(this)">
                                    <td style="padding-left: 2rem;"><strong><?= htmlspecialchars($item['item_name']) ?></strong></td>
                                    <td class="text-end">$<?= number_format($item['unit_price'], 2) ?></td>
                                    <td class="text-end"><?= number_format($item['total_quantity_sold']) ?></td>
                                    <td class="text-end"><strong class="text-success">$<?= number_format($item['total_revenue'], 2) ?></strong></td>
                                    <td class="text-end"><?= number_format($item['number_of_transactions']) ?></td>
                                    <td>
                                        <small class="text-muted">
                                            <?= date('M d, Y', strtotime($item['first_sale_date'])) ?>
                                            <?php if ($item['first_sale_date'] !== $item['last_sale_date']): ?>
                                                - <?= date('M d, Y', strtotime($item['last_sale_date'])) ?>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                </tr>
                                <!-- expandable rows hidden by default -->
                                <tr class="detail-row" id="shop-detail-<?= $idx ?>">
                                    <td colspan="6" style="padding: 0; background-color: #f8f9fa;">
                                        <div style="padding: 1rem 0; border-left: 3px solid #0d6efd; margin-left: 2rem;">
                                            <div style="padding: 0 1rem 0.5rem 1rem;">
                                                <strong><i class="bi bi-receipt"></i> Individual Transactions</strong>
                                                <span class="badge bg-primary ms-2"><?= count($transactions) ?> transactions</span>
                                            </div>
                                            
                                            <?php if (empty($transactions)): ?>
                                                <p class="text-muted mb-0" style="padding: 0 1rem;">No transaction details available</p>
                                            <?php else: ?>
                                                <table class="table table-sm mb-0" style="background-color: white; margin: 0 1rem; width: calc(100% - 2rem);">
                                                    <tbody>
                                                        <?php foreach ($transactions as $trans): ?>
                                                            <tr style="border-bottom: 1px solid #e9ecef;">
                                                                <td style="padding: 0.75rem; padding-left: 2rem; width: 25%;">
                                                                    <strong>Sale #<?= $trans['sale_id'] ?></strong>
                                                                    <br>
                                                                    <small class="text-muted">
                                                                        <?= date('M d, Y g:i A', strtotime($trans['sale_date'])) ?>
                                                                    </small>
                                                                    <br>
                                                                    <span class="badge bg-info mt-1" style="font-size: 0.7rem;">
                                                                        <?= htmlspecialchars($trans['customer_type']) ?>
                                                                    </span>
                                                                    <span class="badge bg-secondary mt-1 ms-1" style="font-size: 0.7rem;">
                                                                        <?= $trans['payment_method'] == 1 ? 'Cash' : ($trans['payment_method'] == 2 ? 'Credit' : 'Other') ?>
                                                                    </span>
                                                                </td>
                                                                <td class="text-end" style="padding: 0.75rem; width:15%;">
                                                                    <small class="text-muted">Price at Sale</small><br>
                                                                    $<?= number_format($trans['price_at_sale'], 2) ?>
                                                                </td>
                                                                <td class="text-end" style="padding: 0.75rem; width: 15%;">
                                                                    <small class="text-muted">Qty</small><br>
                                                                    <strong><?= $trans['quantity'] ?></strong>
                                                                </td>
                                                                <td class="text-end" style="padding: 0.75rem; width: 15%;">
                                                                    <small class="text-muted">Total</small><br>
                                                                    <strong class="text-success">$<?= number_format($trans['transaction_total'], 2) ?></strong>
                                                                </td>
                                                                <td style="padding: 0.75rem; width: 15%;"></td>
                                                                <td style="padding: 0.75rem; width: 15%;"></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="table-secondary fw-bold">
                                <td style="padding-left: 2rem;">TOTAL SHOP REVENUE</td>
                                <td></td>
                                <td class="text-end"><?= number_format(array_sum(array_column($shop_sales_detail, 'total_quantity_sold'))) ?></td>
                                <td class="text-end text-success">$<?= number_format($total_shop_revenue, 2) ?></td>
                                <td class="text-end"><?= number_format($total_shop_transactions) ?></td>
                                <td></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Membership Revenue Detail Table -->
    <?php if ($revenue_source === 'all' || $revenue_source === 'memberships'): ?>
    <div class="card mb-4">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-table"></i> Membership Revenue Detail</h5>
            <small class="text-muted"><i class="bi bi-info-circle"></i> Click any row to view individual registrations</small>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table class="table table-hover revenue-table mb-0" id="membershipRevenueTable">
                    <thead>
                        <tr>
                            <th style="padding-left: 2rem;">Membership Type</th>
                            <th>Student Status</th>
                            <th class="text-end">Price Per Membership</th>
                            <th class="text-end">Memberships Sold</th>
                            <th class="text-end">Total Revenue</th>
                            <th>Date Range</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($membership_revenue_detail)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    No membership registrations for the selected period
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($membership_revenue_detail as $idx => $membership): ?>
                                <?php 
                                $membership_key = $membership['type_id'] . '_' . $membership['is_student'];
                                $registrations = $membership_registrations[$membership_key] ?? [];
                                ?>
                                <!-- clickable rows -->
                                <tr class="expandable-row" data-target="membership-detail-<?= $idx ?>" onclick="toggleDetailRow(this)">
                                    <td style="padding-left: 2rem;"><strong><?= htmlspecialchars($membership['type_name']) ?></strong></td>
                                    <td>
                                        <?php if ($membership['is_student']): ?>
                                            <span class="badge bg-info">Student</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Non-Student</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">$<?= number_format($membership['price_per'], 2) ?></td>
                                    <td class="text-end"><?= number_format($membership['count']) ?></td>
                                    <td class="text-end"><strong class="text-success">$<?= number_format($membership['total_revenue'], 2) ?></strong></td>
                                    <td>
                                        <small class="text-muted">
                                            <?= date('M d, Y', strtotime($membership['first_registration'])) ?>
                                            <?php if ($membership['first_registration'] !== $membership['last_registration']): ?>
                                                - <?= date('M d, Y', strtotime($membership['last_registration'])) ?>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                </tr>
                                <!-- expandable rows hidden by default -->
                                <tr class="detail-row" id="membership-detail-<?= $idx ?>">
                                    <td colspan="6" style="padding: 0; background-color: #f8f9fa;">
                                        <div style="padding: 1rem 0; border-left: 3px solid #0d6efd; margin-left: 2rem;">
                                            <div style="padding: 0 1rem 0.5rem 1rem;">
                                                <strong><i class="bi bi-people"></i> Individual Registrations</strong>
                                                <span class="badge bg-primary ms-2"><?= count($registrations) ?> members</span>
                                            </div>
                                            
                                            <?php if (empty($registrations)): ?>
                                                <p class="text-muted mb-0" style="padding: 0 1rem;">No registration details available</p>
                                            <?php else: ?>
                                                <table class="table table-sm mb-0" style="background-color: white; margin: 0 1rem; width: calc(100% - 2rem);">
                                                    <tbody>
                                                        <?php foreach ($registrations as $member): ?>
                                                            <tr style="border-bottom: 1px solid #e9ecef;">
                                                                <td style="padding: 0.75rem; padding-left: 2rem; width: 25%;">
                                                                    <strong><?= htmlspecialchars($member['member_name']) ?></strong>
                                                                    <br>
                                                                    <small class="text-muted">Member #<?= $member['member_id'] ?></small>
                                                                    <br>
                                                                    <small class="text-muted"><?= htmlspecialchars($member['email']) ?></small>
                                                                </td>
                                                                <td style="padding: 0.75rem; width: 12.5%;">
                                                                    <?php if ($member['auto_renew']): ?>
                                                                        <span class="badge bg-success" style="font-size: 0.7rem;">Auto-Renew</span>
                                                                    <?php else: ?>
                                                                        <span class="badge bg-warning text-dark" style="font-size: 0.7rem;">Manual</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td class="text-end" style="padding: 0.75rem; width: 12.5%;">
                                                                    <small class="text-muted">Price</small><br>
                                                                    $<?= number_format($membership['price_per'], 2) ?>
                                                                </td>
                                                                
                                                                <td class="text-end" style="padding: 0.75rem; width: 12.5%;">
                                                                    <small class="text-muted">Revenue</small><br>
                                                                    <strong class="text-success">$<?= number_format($membership['price_per'], 2) ?></strong>
                                                                </td>
                                                                <td style="padding: 0.75rem; width: 25%;">
                                                                    <small class="text-muted">
                                                                        Joined: <?= date('M d, Y', strtotime($member['start_date'])) ?>
                                                                        <br>
                                                                        Expires: <?= date('M d, Y', strtotime($member['expiration_date'])) ?>
                                                                    </small>
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
                            <tr class="table-secondary fw-bold">
                                <td style="padding-left: 2rem;">TOTAL MEMBERSHIP REVENUE</td>
                                <td></td>
                                <td></td>
                                <td class="text-end"><?= number_format($total_memberships_sold) ?></td>
                                <td class="text-end text-success">$<?= number_format($total_membership_revenue, 2) ?></td>
                                <td></td>
                            </tr>
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
            <h5 class="mb-0"><i class="bi bi-lightbulb"></i> Key Insights & Business Intelligence</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6 class="text-primary"><i class="bi bi-graph-up-arrow"></i> Revenue Performance</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <i class="bi bi-check-circle text-success"></i>
                            Total revenue for period: <strong>$<?= number_format($total_revenue, 2) ?></strong>
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-check-circle text-success"></i>
                            Shop contributes <strong><?= number_format($shop_percentage, 1) ?>%</strong> of total revenue
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-check-circle text-success"></i>
                            Memberships contribute <strong><?= number_format($membership_percentage, 1) ?>%</strong> of total revenue
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-check-circle text-success"></i>
                            Average shop transaction: <strong>$<?= number_format($avg_shop_transaction, 2) ?></strong>
                        </li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h6 class="text-info"><i class="bi bi-bar-chart"></i> Top Performers</h6>
                    <?php if (!empty($shop_sales_detail)): ?>
                        <?php 
                        $top_item = $shop_sales_detail[0];
                        ?>
                        <ul class="list-unstyled">
                            <li class="mb-2">
                                <i class="bi bi-trophy text-warning"></i>
                                Best-selling item: <strong><?= htmlspecialchars($top_item['item_name']) ?></strong>
                                (<?= number_format($top_item['total_quantity_sold']) ?> units)
                            </li>
                            <li class="mb-2">
                                <i class="bi bi-trophy text-warning"></i>
                                Highest revenue item: <strong><?= htmlspecialchars($top_item['item_name']) ?></strong>
                                ($<?= number_format($top_item['total_revenue'], 2) ?>)
                            </li>
                        </ul>
                    <?php endif; ?>
                    <?php if (!empty($membership_revenue_detail)): ?>
                        <?php 
                        usort($membership_revenue_detail, function($a, $b) {
                            return $b['count'] - $a['count'];
                        });
                        $top_membership = $membership_revenue_detail[0];
                        ?>
                        <ul class="list-unstyled">
                            <li class="mb-2">
                                <i class="bi bi-people text-info"></i>
                                Most popular membership: <strong><?= htmlspecialchars($top_membership['type_name']) ?></strong>
                                (<?= number_format($top_membership['count']) ?> members)
                            </li>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Prepare data for charts
const shopTrendData = <?= json_encode($shop_trend) ?>;
const membershipTrendData = <?= json_encode($membership_trend) ?>;
const shopSalesDetail = <?= json_encode($shop_sales_detail) ?>;
const revenueSource = '<?= $revenue_source ?>';

// Revenue Trend Over Time Chart
const revenueTrendCtx = document.getElementById('revenueTrendChart').getContext('2d');

// Combine shop and membership data by period
const allPeriods = new Set();
shopTrendData.forEach(item => allPeriods.add(item.period));
membershipTrendData.forEach(item => allPeriods.add(item.period));
const sortedPeriods = Array.from(allPeriods).sort();

const shopRevenueByPeriod = {};
const membershipRevenueByPeriod = {};

shopTrendData.forEach(item => {
    shopRevenueByPeriod[item.period] = parseFloat(item.revenue);
});

membershipTrendData.forEach(item => {
    membershipRevenueByPeriod[item.period] = parseFloat(item.revenue);
});

const shopDataPoints = sortedPeriods.map(period => shopRevenueByPeriod[period] || 0);
const membershipDataPoints = sortedPeriods.map(period => membershipRevenueByPeriod[period] || 0);
const totalDataPoints = sortedPeriods.map((period, i) => shopDataPoints[i] + membershipDataPoints[i]);

const datasets = [];

if (revenueSource === 'all' || revenueSource === 'shop') {
    datasets.push({
        label: 'Shop Revenue',
        data: shopDataPoints,
        borderColor: 'rgb(54, 162, 235)',
        backgroundColor: 'rgba(54, 162, 235, 0.1)',
        tension: 0.4,
        fill: true
    });
}

if (revenueSource === 'all' || revenueSource === 'memberships') {
    datasets.push({
        label: 'Membership Revenue',
        data: membershipDataPoints,
        borderColor: 'rgb(75, 192, 192)',
        backgroundColor: 'rgba(75, 192, 192, 0.1)',
        tension: 0.4,
        fill: true
    });
}

if (revenueSource === 'all') {
    datasets.push({
        label: 'Total Revenue',
        data: totalDataPoints,
        borderColor: 'rgb(255, 99, 132)',
        backgroundColor: 'rgba(255, 99, 132, 0.1)',
        borderWidth: 2,
        tension: 0.4,
        fill: false
    });
}

new Chart(revenueTrendCtx, {
    type: 'line',
    data: {
        labels: sortedPeriods,
        datasets: datasets
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
            },
            title: {
                display: true,
                text: 'Revenue Trends'
            },
            tooltip: {
                mode: 'index',
                intersect: false,
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': $' + context.parsed.y.toFixed(2);
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '$' + value.toLocaleString();
                    }
                }
            }
        }
    }
});

// Revenue Source Pie Chart
if (revenueSource === 'all') {
    const revenueSourceCtx = document.getElementById('revenueSourceChart').getContext('2d');
    new Chart(revenueSourceCtx, {
        type: 'doughnut',
        data: {
            labels: ['Shop Sales', 'Memberships'],
            datasets: [{
                data: [<?= $total_shop_revenue ?>, <?= $total_membership_revenue ?>],
                backgroundColor: [
                    'rgba(54, 162, 235, 0.8)',
                    'rgba(75, 192, 192, 0.8)'
                ],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const value = context.parsed;
                            const total = <?= $total_revenue ?>;
                            const percentage = ((value / total) * 100).toFixed(1);
                            return context.label + ': $' + value.toFixed(2) + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });
}

// Top Items Bar Chart
if ((revenueSource === 'all' || revenueSource === 'shop') && shopSalesDetail.length > 0) {
    const topItemsCtx = document.getElementById('topItemsChart').getContext('2d');
    const topItems = shopSalesDetail.slice(0, 5); // Top 5 items
    
    new Chart(topItemsCtx, {
        type: 'bar',
        data: {
            labels: topItems.map(item => item.item_name),
            datasets: [{
                label: 'Revenue',
                data: topItems.map(item => parseFloat(item.total_revenue)),
                backgroundColor: 'rgba(54, 162, 235, 0.8)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Revenue: $' + context.parsed.x.toFixed(2);
                        }
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '$' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
}

// Function to toggle detail rows
function toggleDetailRow(element) {
    const targetId = element.getAttribute('data-target');
    const detailRow = document.getElementById(targetId);
    
    // Toggle the expanded class on the clicked row
    element.classList.toggle('expanded');
    
    // Toggle the show class on the detail row
    detailRow.classList.toggle('show');
    
    // Prevent event bubbling
    event.stopPropagation();
}

// keyboard navigation
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