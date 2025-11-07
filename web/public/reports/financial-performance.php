<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/permissions.php';

$page_title = 'Financial Performance Dashboard';

// Only admin can access
if ($_SESSION['user_type'] !== 'admin') {
    header('Location: index.php?error=access_denied');
    exit;
}

$db = db();

// Get filter parameters
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-90 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$group_by = $_GET['group_by'] ?? 'month'; // day, week, month, quarter

// --- SQL Queries Execution ---

// Revenue Trends Over Time
$revenue_trends_query = "
    SELECT 
        DATE_FORMAT(sale_date, " . 
        ($group_by == 'day' ? "'%Y-%m-%d'" : 
        ($group_by == 'week' ? "'%Y-W%u'" : 
        ($group_by == 'month' ? "'%Y-%m'" : "'%Y-Q%q'"))) . ") AS period,
        COUNT(DISTINCT sale_id) AS transaction_count,
        SUM(total_amount) AS total_revenue,
        SUM(discount_amount) AS total_discounts,
        SUM(total_amount - discount_amount) AS net_revenue,
        AVG(total_amount) AS avg_transaction
    FROM SALE
    WHERE sale_date BETWEEN ? AND ?
    GROUP BY period
    ORDER BY period
";

$stmt = $db->prepare($revenue_trends_query);
$stmt->bind_param('ss', $date_from, $date_to);
$stmt->execute();
$revenue_trends = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Revenue by Category (JOIN SALE -> SALE_ITEM -> SHOP_ITEM)
$category_query = "
    SELECT 
        SHI.category,
        COUNT(DISTINCT S.sale_id) AS transactions,
        SUM(SI.quantity) AS items_sold,
        SUM(SI.quantity * SI.price_at_sale) AS revenue
    FROM SALE S
    JOIN SALE_ITEM SI ON S.sale_id = SI.sale_id
    JOIN SHOP_ITEM SHI ON SI.item_id = SHI.item_id
    WHERE S.sale_date BETWEEN ? AND ?
    GROUP BY SHI.category
    ORDER BY revenue DESC
";

$stmt = $db->prepare($category_query);
$stmt->bind_param('ss', $date_from, $date_to);
$stmt->execute();
$categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Member vs Visitor Sales Comparison
$customer_comparison_query = "
    SELECT 
        CASE 
            WHEN member_id IS NOT NULL THEN 'Member'
            ELSE 'Visitor'
        END AS customer_type,
        COUNT(DISTINCT sale_id) AS transactions,
        SUM(total_amount) AS total_revenue,
        AVG(total_amount) AS avg_transaction,
        SUM(discount_amount) AS total_discounts
    FROM SALE
    WHERE sale_date BETWEEN ? AND ?
    GROUP BY customer_type
";

$stmt = $db->prepare($customer_comparison_query);
$stmt->bind_param('ss', $date_from, $date_to);
$stmt->execute();
$customer_comparison = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Top Selling Items (JOIN through SHOP_ITEM)
$top_items_query = "
    SELECT 
        SHI.item_name,
        SHI.category,
        SUM(SI.quantity) AS units_sold,
        SUM(SI.quantity * SI.price_at_sale) AS revenue,
        AVG(SI.price_at_sale) AS avg_price
    FROM SALE_ITEM SI
    JOIN SHOP_ITEM SHI ON SI.item_id = SHI.item_id
    JOIN SALE S ON SI.sale_id = S.sale_id
    WHERE S.sale_date BETWEEN ? AND ?
    GROUP BY SHI.item_id, SHI.item_name, SHI.category
    ORDER BY revenue DESC
    LIMIT 10
";

$stmt = $db->prepare($top_items_query);
$stmt->bind_param('ss', $date_from, $date_to);
$stmt->execute();
$top_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Donor Contributions Analysis
$donor_analysis_query = "
    SELECT 
        D.donor_id,
        CASE 
            WHEN D.is_organization = 1 THEN D.organization_name
            ELSE CONCAT(D.first_name, ' ', D.last_name)
        END AS donor_name,
        D.is_organization,
        COUNT(DON.donation_id) AS donation_count,
        SUM(DON.amount) AS total_contributed,
        MAX(DON.donation_date) AS last_donation_date
    FROM DONOR D
    JOIN DONATION DON ON D.donor_id = DON.donor_id
    WHERE DON.donation_date BETWEEN ? AND ?
    GROUP BY D.donor_id, donor_name, D.is_organization
    ORDER BY total_contributed DESC
    LIMIT 15
";

$stmt = $db->prepare($donor_analysis_query);
$stmt->bind_param('ss', $date_from, $date_to);
$stmt->execute();
$donors = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Acquisition Spending Trends
$acquisition_query = "
    SELECT 
        DATE_FORMAT(acquisition_date, '%Y-%m') AS month,
        COUNT(*) AS acquisitions_count,
        SUM(price_value) AS total_spent,
        AVG(price_value) AS avg_price,
        method
    FROM ACQUISITION
    WHERE acquisition_date BETWEEN ? AND ?
        AND method = 'purchase'
    GROUP BY month, method
    ORDER BY month DESC
";

$stmt = $db->prepare($acquisition_query);
$stmt->bind_param('ss', $date_from, $date_to);
$stmt->execute();
$acquisitions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calculate overall statistics
$total_revenue = array_sum(array_column($revenue_trends, 'total_revenue'));
$total_transactions = array_sum(array_column($revenue_trends, 'transaction_count'));
$total_discounts = array_sum(array_column($revenue_trends, 'total_discounts'));
$avg_transaction = $total_transactions > 0 ? $total_revenue / $total_transactions : 0;

// Donor totals
$total_donations = array_sum(array_column($donors, 'total_contributed'));
$total_donor_count = count($donors);

// Acquisition totals
$total_acquisitions = array_sum(array_column($acquisitions, 'acquisitions_count'));
$total_acquisition_spending = array_sum(array_column($acquisitions, 'total_spent'));

include __DIR__ . '/../templates/layout_header.php';
?>

<style>
.executive-header {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    border-radius: 15px;
    padding: 30px;
    color: white;
    margin-bottom: 30px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.stat-card-executive {
    border-radius: 10px;
    padding: 25px;
    background: white;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border-left: 4px solid;
}

.chart-card {
    background: white;
    border-radius: 10px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.filter-section {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
}

.table-executive {
    font-size: 0.9rem;
}

.badge-category {
    padding: 0.4em 0.8em;
    font-size: 0.85rem;
}
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="executive-header">
        <a href="index.php" class="btn btn-light btn-sm mb-3">
            <i class="bi bi-arrow-left"></i> Back to Reports
        </a>
        <h1 class="mb-2"><i class="bi bi-graph-up-arrow"></i> Financial Performance Dashboard</h1>
        <p class="mb-0 opacity-75">Comprehensive revenue analysis, sales trends, donor contributions, and acquisition spending</p>
    </div>
    
    <!-- Filters -->
    <div class="filter-section">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label fw-bold">Date From</label>
                <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>">
            </div>
            
            <div class="col-md-3">
                <label class="form-label fw-bold">Date To</label>
                <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>">
            </div>
            
            <div class="col-md-3">
                <label class="form-label fw-bold">Group By</label>
                <select name="group_by" class="form-select">
                    <option value="day" <?= $group_by == 'day' ? 'selected' : '' ?>>Daily</option>
                    <option value="week" <?= $group_by == 'week' ? 'selected' : '' ?>>Weekly</option>
                    <option value="month" <?= $group_by == 'month' ? 'selected' : '' ?>>Monthly</option>
                    <option value="quarter" <?= $group_by == 'quarter' ? 'selected' : '' ?>>Quarterly</option>
                </select>
            </div>
            
            <div class="col-md-3 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-funnel"></i> Apply Filters
                </button>
                <a href="financial-performance.php" class="btn btn-outline-secondary">Clear</a>
            </div>
        </form>
    </div>
    
    <!-- Summary Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card-executive" style="border-left-color: #28a745;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted mb-1 small text-uppercase">Total Revenue</p>
                        <h3 class="mb-0 text-success">$<?= number_format($total_revenue, 2) ?></h3>
                    </div>
                    <i class="bi bi-currency-dollar fs-1 text-success opacity-25"></i>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="stat-card-executive" style="border-left-color: #17a2b8;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted mb-1 small text-uppercase">Transactions</p>
                        <h3 class="mb-0 text-info"><?= number_format($total_transactions) ?></h3>
                    </div>
                    <i class="bi bi-cart-check fs-1 text-info opacity-25"></i>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="stat-card-executive" style="border-left-color: #ffc107;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted mb-1 small text-uppercase">Avg Transaction</p>
                        <h3 class="mb-0 text-warning">$<?= number_format($avg_transaction, 2) ?></h3>
                    </div>
                    <i class="bi bi-graph-up fs-1 text-warning opacity-25"></i>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="stat-card-executive" style="border-left-color: #dc3545;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted mb-1 small text-uppercase">Total Discounts</p>
                        <h3 class="mb-0 text-danger">$<?= number_format($total_discounts, 2) ?></h3>
                    </div>
                    <i class="bi bi-tag fs-1 text-danger opacity-25"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Revenue Trends Chart -->
    <div class="chart-card">
        <h4 class="mb-4"><i class="bi bi-graph-up"></i> Revenue Trends</h4>
        <div style="height: 400px; position: relative;">
            <canvas id="revenueTrendsChart"></canvas>
        </div>
    </div>
    
    <div class="row">
        <!-- Revenue by Category -->
        <div class="col-md-6">
            <div class="chart-card">
                <h5 class="mb-3"><i class="bi bi-tags"></i> Revenue by Category</h5>
                <div class="table-responsive">
                    <table class="table table-hover table-executive">
                        <thead class="table-light">
                            <tr>
                                <th>Category</th>
                                <th class="text-end">Transactions</th>
                                <th class="text-end">Items Sold</th>
                                <th class="text-end">Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <td>
                                        <span class="badge badge-category bg-primary">
                                            <?= htmlspecialchars($cat['category']) ?>
                                        </span>
                                    </td>
                                    <td class="text-end"><?= number_format($cat['transactions']) ?></td>
                                    <td class="text-end"><?= number_format($cat['items_sold']) ?></td>
                                    <td class="text-end fw-bold text-success">$<?= number_format($cat['revenue'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Member vs Visitor Comparison -->
        <div class="col-md-6">
            <div class="chart-card">
                <h5 class="mb-3"><i class="bi bi-people"></i> Member vs Visitor Sales</h5>
                <div style="height: 300px; position: relative;">
                    <canvas id="customerComparisonChart"></canvas>
                </div>
                <div class="table-responsive mt-3">
                    <table class="table table-sm table-executive">
                        <thead class="table-light">
                            <tr>
                                <th>Type</th>
                                <th class="text-end">Transactions</th>
                                <th class="text-end">Revenue</th>
                                <th class="text-end">Avg</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customer_comparison as $comp): ?>
                                <tr>
                                    <td>
                                        <span class="badge <?= $comp['customer_type'] == 'Member' ? 'bg-primary' : 'bg-secondary' ?>">
                                            <?= $comp['customer_type'] ?>
                                        </span>
                                    </td>
                                    <td class="text-end"><?= number_format($comp['transactions']) ?></td>
                                    <td class="text-end">$<?= number_format($comp['total_revenue'], 2) ?></td>
                                    <td class="text-end">$<?= number_format($comp['avg_transaction'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Top Selling Items -->
    <div class="chart-card">
        <h5 class="mb-3"><i class="bi bi-trophy"></i> Top 10 Selling Items</h5>
        <div class="table-responsive">
            <table class="table table-hover table-executive">
                <thead class="table-light">
                    <tr>
                        <th>Rank</th>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th class="text-end">Units Sold</th>
                        <th class="text-end">Revenue</th>
                        <th class="text-end">Avg Price</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($top_items as $index => $item): ?>
                        <tr>
                            <td>
                                <?php if ($index < 3): ?>
                                    <span class="badge bg-warning text-dark">#<?= $index + 1 ?></span>
                                <?php else: ?>
                                    <span class="text-muted">#<?= $index + 1 ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="fw-bold"><?= htmlspecialchars($item['item_name']) ?></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($item['category']) ?></span></td>
                            <td class="text-end"><?= number_format($item['units_sold']) ?></td>
                            <td class="text-end text-success fw-bold">$<?= number_format($item['revenue'], 2) ?></td>
                            <td class="text-end">$<?= number_format($item['avg_price'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="row">
        <!-- Donor Contributions -->
        <div class="col-md-6">
            <div class="chart-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0"><i class="bi bi-heart"></i> Top Donors</h5>
                    <div>
                        <span class="badge bg-success"><?= $total_donor_count ?> Donors</span>
                        <span class="badge bg-primary">$<?= number_format($total_donations, 2) ?> Total</span>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover table-executive">
                        <thead class="table-light">
                            <tr>
                                <th>Donor</th>
                                <th class="text-center">Type</th>
                                <th class="text-end">Donations</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($donors as $donor): ?>
                                <tr>
                                    <td><?= htmlspecialchars($donor['donor_name']) ?></td>
                                    <td class="text-center">
                                        <?php if ($donor['is_organization']): ?>
                                            <i class="bi bi-building text-primary" title="Organization"></i>
                                        <?php else: ?>
                                            <i class="bi bi-person text-secondary" title="Individual"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end"><?= number_format($donor['donation_count']) ?></td>
                                    <td class="text-end fw-bold text-success">$<?= number_format($donor['total_contributed'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Acquisition Spending -->
        <div class="col-md-6">
            <div class="chart-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0"><i class="bi bi-cart-plus"></i> Acquisition Spending</h5>
                    <div>
                        <span class="badge bg-warning text-dark"><?= $total_acquisitions ?> Purchases</span>
                        <span class="badge bg-danger">$<?= number_format($total_acquisition_spending, 2) ?> Spent</span>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover table-executive">
                        <thead class="table-light">
                            <tr>
                                <th>Month</th>
                                <th class="text-end">Acquisitions</th>
                                <th class="text-end">Total Spent</th>
                                <th class="text-end">Avg Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($acquisitions as $acq): ?>
                                <tr>
                                    <td><?= date('M Y', strtotime($acq['month'] . '-01')) ?></td>
                                    <td class="text-end"><?= number_format($acq['acquisitions_count']) ?></td>
                                    <td class="text-end text-danger fw-bold">$<?= number_format($acq['total_spent'], 2) ?></td>
                                    <td class="text-end">$<?= number_format($acq['avg_price'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Revenue Trends Chart
const revenueCtx = document.getElementById('revenueTrendsChart').getContext('2d');
new Chart(revenueCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($revenue_trends, 'period')) ?>,
        datasets: [{
            label: 'Total Revenue',
            data: <?= json_encode(array_column($revenue_trends, 'total_revenue')) ?>,
            borderColor: '#28a745',
            backgroundColor: 'rgba(40, 167, 69, 0.1)',
            tension: 0.4,
            fill: true
        }, {
            label: 'Net Revenue',
            data: <?= json_encode(array_column($revenue_trends, 'net_revenue')) ?>,
            borderColor: '#17a2b8',
            backgroundColor: 'rgba(23, 162, 184, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true,
                position: 'top'
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

// Customer Comparison Chart
const customerCtx = document.getElementById('customerComparisonChart').getContext('2d');
new Chart(customerCtx, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($customer_comparison, 'customer_type')) ?>,
        datasets: [{
            data: <?= json_encode(array_column($customer_comparison, 'total_revenue')) ?>,
            backgroundColor: ['#007bff', '#6c757d']
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
</script>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>