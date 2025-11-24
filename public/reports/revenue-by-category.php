<?php
// Revenue by Category Report
// Shows breakdown of shop revenue by product category

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

// Get all active categories from the database
$category_query = "
    SELECT category_id, name, description 
    FROM CATEGORY 
    WHERE is_active = 1
    ORDER BY name ASC
";
$available_categories = $db->query($category_query)->fetch_all(MYSQLI_ASSOC);

// Get filters
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-1 month'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$selected_category_id = $_GET['category_id'] ?? '';

// Build query based on filters
$revenue_query = "
    SELECT 
        c.category_id,
        c.name as category_name,
        si.item_id,
        si.item_name,
        si.price,
        COUNT(s.sale_item_id) as total_sales,
        SUM(s.quantity) as total_quantity,
        SUM(s.quantity * si.price) as total_revenue
    FROM SALE_ITEM s
    JOIN SHOP_ITEM si ON s.item_id = si.item_id
    LEFT JOIN CATEGORY c ON si.category_id = c.category_id
    JOIN SALE ON s.sale_id = SALE.sale_id
    WHERE SALE.sale_date BETWEEN ? AND ?
";

$params = [$date_from, $date_to];
$param_types = "ss";

if (!empty($selected_category_id)) {
    $revenue_query .= " AND si.category_id = ?";
    $params[] = $selected_category_id;
    $param_types .= "i";
}

$revenue_query .= "
    GROUP BY c.category_id, c.name, si.item_id, si.item_name, si.price
    ORDER BY c.name ASC, total_revenue DESC
";

$stmt = $db->prepare($revenue_query);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$revenue_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get selected category name for display
$selected_category_name = '';
if (!empty($selected_category_id)) {
    foreach ($available_categories as $cat) {
        if ($cat['category_id'] == $selected_category_id) {
            $selected_category_name = $cat['name'];
            break;
        }
    }
}

// Calculate category totals
$category_totals = [];
foreach ($revenue_data as $row) {
    $cat_name = $row['category_name'] ?: 'Uncategorized';
    if (!isset($category_totals[$cat_name])) {
        $category_totals[$cat_name] = [
            'total_sales' => 0,
            'total_quantity' => 0,
            'total_revenue' => 0
        ];
    }
    $category_totals[$cat_name]['total_sales'] += $row['total_sales'];
    $category_totals[$cat_name]['total_quantity'] += $row['total_quantity'];
    $category_totals[$cat_name]['total_revenue'] += $row['total_revenue'];
}

// Calculate grand totals
$grand_total_sales = array_sum(array_column($revenue_data, 'total_sales'));
$grand_total_quantity = array_sum(array_column($revenue_data, 'total_quantity'));
$grand_total_revenue = array_sum(array_column($revenue_data, 'total_revenue'));

// Page title
$page_title = 'Revenue by Category';
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

.category-header {
    background-color: #e9ecef;
    font-weight: bold;
}

.category-total {
    background-color: #f8f9fa;
    font-weight: 600;
    border-top: 2px solid #dee2e6;
}

.grand-total {
    background-color: #d1ecf1;
    font-weight: bold;
    border-top: 3px solid #0c5460;
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
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="index.php" class="btn btn-outline-secondary mb-2">
                <i class="bi bi-arrow-left"></i> Back to Reports
            </a>
            <h1 class="mb-0"><i class="bi bi-bag-check text-success"></i> Revenue by Category</h1>
            <p class="text-muted">Breakdown of shop revenue by product category</p>
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
                <div class="col-md-4">
                    <label class="form-label">Category</label>
                    <select name="category_id" class="form-select">
                        <option value="">All Categories</option>
                        <?php foreach ($available_categories as $cat): ?>
                            <option value="<?= $cat['category_id'] ?>" 
                                    <?= $selected_category_id == $cat['category_id'] ? 'selected' : '' ?>
                                    title="<?= htmlspecialchars($cat['description']) ?>">
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Select a specific category or view all</small>
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
            <div class="card stat-card border-success h-100">
                <div class="card-body text-center">
                    <i class="bi bi-currency-dollar text-success" style="font-size: 2.5rem;"></i>
                    <h3 class="mb-0 mt-2">$<?= number_format($grand_total_revenue, 2) ?></h3>
                    <p class="text-muted mb-0">Total Revenue</p>
                    <small class="text-muted">
                        <?= $selected_category_name ?: 'All Categories' ?>
                    </small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card border-primary h-100">
                <div class="card-body text-center">
                    <i class="bi bi-cart text-primary" style="font-size: 2.5rem;"></i>
                    <h3 class="mb-0 mt-2"><?= number_format($grand_total_sales) ?></h3>
                    <p class="text-muted mb-0">Total Transactions</p>
                    <small class="text-muted">Number of sales</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card border-info h-100">
                <div class="card-body text-center">
                    <i class="bi bi-box-seam text-info" style="font-size: 2.5rem;"></i>
                    <h3 class="mb-0 mt-2"><?= number_format($grand_total_quantity) ?></h3>
                    <p class="text-muted mb-0">Items Sold</p>
                    <small class="text-muted">Total quantity</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card border-warning h-100">
                <div class="card-body text-center">
                    <i class="bi bi-tags text-warning" style="font-size: 2.5rem;"></i>
                    <h3 class="mb-0 mt-2"><?= count($category_totals) ?></h3>
                    <p class="text-muted mb-0">Categories</p>
                    <small class="text-muted">
                        <?= $selected_category_name ? '1 selected' : 'All shown' ?>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Revenue Table -->
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="bi bi-table"></i> Revenue Breakdown</h5>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table class="table table-hover revenue-table mb-0">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Item Name</th>
                            <th class="text-end">Price</th>
                            <th class="text-end">Transactions</th>
                            <th class="text-end">Quantity Sold</th>
                            <th class="text-end">Total Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($revenue_data)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    No sales data for the selected period
                                    <?= $selected_category_name ? ' in category "' . htmlspecialchars($selected_category_name) . '"' : '' ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php 
                            $current_category = null;
                            foreach ($revenue_data as $row): 
                                $category_display = $row['category_name'] ?: 'Uncategorized';
                                
                                // Display category header when category changes
                                if ($current_category !== $category_display):
                                    if ($current_category !== null):
                                        // Display previous category total
                                        $cat_total = $category_totals[$current_category];
                            ?>
                                        <tr class="category-total">
                                            <td colspan="3"><strong><?= htmlspecialchars($current_category) ?> Total</strong></td>
                                            <td class="text-end"><strong><?= number_format($cat_total['total_sales']) ?></strong></td>
                                            <td class="text-end"><strong><?= number_format($cat_total['total_quantity']) ?></strong></td>
                                            <td class="text-end"><strong>$<?= number_format($cat_total['total_revenue'], 2) ?></strong></td>
                                        </tr>
                            <?php 
                                    endif;
                                    $current_category = $category_display;
                            ?>
                                    <tr class="category-header">
                                        <td colspan="6">
                                            <i class="bi bi-tag-fill"></i> 
                                            <strong><?= htmlspecialchars($category_display) ?></strong>
                                        </td>
                                    </tr>
                            <?php endif; ?>
                                    
                                <!-- Item Row -->
                                <tr>
                                    <td></td>
                                    <td><?= htmlspecialchars($row['item_name']) ?></td>
                                    <td class="text-end">$<?= number_format($row['price'], 2) ?></td>
                                    <td class="text-end"><?= number_format($row['total_sales']) ?></td>
                                    <td class="text-end"><?= number_format($row['total_quantity']) ?></td>
                                    <td class="text-end">$<?= number_format($row['total_revenue'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if ($current_category !== null): ?>
                                <!-- Last category total -->
                                <?php $cat_total = $category_totals[$current_category]; ?>
                                <tr class="category-total">
                                    <td colspan="3"><strong><?= htmlspecialchars($current_category) ?> Total</strong></td>
                                    <td class="text-end"><strong><?= number_format($cat_total['total_sales']) ?></strong></td>
                                    <td class="text-end"><strong><?= number_format($cat_total['total_quantity']) ?></strong></td>
                                    <td class="text-end"><strong>$<?= number_format($cat_total['total_revenue'], 2) ?></strong></td>
                                </tr>
                            <?php endif; ?>
                            
                            <!-- Grand Total -->
                            <tr class="grand-total">
                                <td colspan="3"><strong><i class="bi bi-calculator"></i> GRAND TOTAL</strong></td>
                                <td class="text-end"><strong><?= number_format($grand_total_sales) ?></strong></td>
                                <td class="text-end"><strong><?= number_format($grand_total_quantity) ?></strong></td>
                                <td class="text-end"><strong>$<?= number_format($grand_total_revenue, 2) ?></strong></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Category Performance Chart -->
    <?php if (!empty($category_totals)): ?>
    <div class="card">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Category Performance</h5>
        </div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="categoryChart"></canvas>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if (!empty($category_totals)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Prepare data for chart
const categoryLabels = <?= json_encode(array_keys($category_totals)) ?>;
const categoryRevenue = <?= json_encode(array_column($category_totals, 'total_revenue')) ?>;

// Create chart
const ctx = document.getElementById('categoryChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: categoryLabels,
        datasets: [{
            label: 'Revenue ($)',
            data: categoryRevenue,
            backgroundColor: 'rgba(40, 167, 69, 0.6)',
            borderColor: 'rgba(40, 167, 69, 1)',
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '$' + value.toLocaleString();
                    }
                }
            }
        },
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return 'Revenue: $' + context.parsed.y.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    }
                }
            }
        }
    }
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>