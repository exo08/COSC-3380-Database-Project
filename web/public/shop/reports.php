<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/permissions.php';

// Check permission
requirePermission('report_sales');

$page_title = 'Sales Reports';
$db = db();

$error = '';
$active_report = $_GET['report'] ?? 'daily';

// Get parameters
$report_date = $_GET['date'] ?? date('Y-m-d');
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$days_back = $_GET['days'] ?? 30;
$stock_threshold = $_GET['threshold'] ?? 10;

// Get report data based on selection
$report_data = [];

try {
    switch($active_report) {
        case 'daily':
            $stmt = $db->prepare("CALL DailySalesSummary(?)");
            $stmt->bind_param('s', $report_date);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $report_data = $result->fetch_all(MYSQLI_ASSOC);
                $result->close();
                $stmt->close();
                $db->next_result();
            }
            break;
            
        case 'top_items':
            $stmt = $db->prepare("CALL TopSellingItems(?)");
            $stmt->bind_param('i', $days_back);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $report_data = $result->fetch_all(MYSQLI_ASSOC);
                $result->close();
                $stmt->close();
                $db->next_result();
            }
            break;
            
        case 'low_stock':
            $stmt = $db->prepare("CALL GetLowStockAlerts(?)");
            $stmt->bind_param('i', $stock_threshold);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $report_data = $result->fetch_all(MYSQLI_ASSOC);
                $result->close();
                $stmt->close();
                $db->next_result();
            }
            break;
            
        case 'revenue':
            $stmt = $db->prepare("CALL RevenueByDateRange(?, ?)");
            $stmt->bind_param('ss', $start_date, $end_date);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $report_data = $result->fetch_all(MYSQLI_ASSOC);
                $result->close();
                $stmt->close();
                $db->next_result();
            }
            break;
    }
} catch (Exception $e) {
    $error = 'Error loading report: ' . $e->getMessage();
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
    background: linear-gradient(90deg, #28a745 0%, #218838 100%);
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

.revenue-highlight {
    font-size: 2rem;
    font-weight: bold;
    color: #28a745;
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
            <a class="nav-link <?= $active_report === 'daily' ? 'active' : '' ?>" href="?report=daily">
                <i class="bi bi-calendar-day"></i> Daily Summary
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $active_report === 'top_items' ? 'active' : '' ?>" href="?report=top_items">
                <i class="bi bi-trophy"></i> Top Sellers
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $active_report === 'low_stock' ? 'active' : '' ?>" href="?report=low_stock">
                <i class="bi bi-exclamation-triangle"></i> Low Stock
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $active_report === 'revenue' ? 'active' : '' ?>" href="?report=revenue">
                <i class="bi bi-graph-up"></i> Revenue Trend
            </a>
        </li>
    </ul>
</div>

<!-- Filters -->
<?php if ($active_report === 'daily'): ?>
    <div class="filter-card">
        <form method="GET" class="row g-3">
            <input type="hidden" name="report" value="daily">
            <div class="col-md-4">
                <label class="form-label">Report Date:</label>
                <input type="date" class="form-control" name="date" value="<?= htmlspecialchars($report_date) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i> View Report
                </button>
            </div>
        </form>
    </div>
<?php elseif ($active_report === 'top_items'): ?>
    <div class="filter-card">
        <form method="GET" class="row g-3">
            <input type="hidden" name="report" value="top_items">
            <div class="col-md-4">
                <label class="form-label">Look Back Period:</label>
                <select class="form-select" name="days">
                    <option value="7" <?= $days_back == 7 ? 'selected' : '' ?>>Last 7 Days</option>
                    <option value="30" <?= $days_back == 30 ? 'selected' : '' ?>>Last 30 Days</option>
                    <option value="90" <?= $days_back == 90 ? 'selected' : '' ?>>Last 90 Days</option>
                    <option value="365" <?= $days_back == 365 ? 'selected' : '' ?>>Last Year</option>
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
<?php elseif ($active_report === 'low_stock'): ?>
    <div class="filter-card">
        <form method="GET" class="row g-3">
            <input type="hidden" name="report" value="low_stock">
            <div class="col-md-4">
                <label class="form-label">Stock Threshold:</label>
                <input type="number" class="form-control" name="threshold" value="<?= htmlspecialchars($stock_threshold) ?>" min="0">
                <small class="text-muted">Show items with stock at or below this number</small>
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i> View Report
                </button>
            </div>
        </form>
    </div>
<?php elseif ($active_report === 'revenue'): ?>
    <div class="filter-card">
        <form method="GET" class="row g-3">
            <input type="hidden" name="report" value="revenue">
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
    <?php if ($active_report === 'daily'): ?>
        <h4 class="mb-4"><i class="bi bi-calendar-day"></i> Daily Sales Summary - <?= date('F j, Y', strtotime($report_date)) ?></h4>
        
        <?php if (empty($report_data)): ?>
            <div class="alert alert-info">No sales recorded for this date.</div>
        <?php else: 
            $data = $report_data[0];
        ?>
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h6 class="text-uppercase">Total Revenue</h6>
                            <div class="revenue-highlight">$<?= number_format($data['total_revenue'], 2) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <h6 class="text-uppercase">Transactions</h6>
                            <div class="revenue-highlight"><?= $data['total_transactions'] ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <h6 class="text-uppercase">Items Sold</h6>
                            <div class="revenue-highlight"><?= $data['total_items_sold'] ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-bordered">
                    <tbody>
                        <tr>
                            <th width="40%">Average Transaction Value:</th>
                            <td><strong>$<?= number_format($data['avg_transaction_value'], 2) ?></strong></td>
                        </tr>
                        <tr>
                            <th>Total Discounts Applied:</th>
                            <td><strong>$<?= number_format($data['total_discounts'], 2) ?></strong></td>
                        </tr>
                        <tr>
                            <th>Member Transactions:</th>
                            <td>
                                <span class="stat-badge bg-success text-white"><?= $data['member_transactions'] ?></span>
                                (<?= $data['total_transactions'] > 0 ? round(($data['member_transactions'] / $data['total_transactions']) * 100, 1) : 0 ?>%)
                            </td>
                        </tr>
                        <tr>
                            <th>Walk-in Transactions:</th>
                            <td>
                                <span class="stat-badge bg-secondary text-white"><?= $data['visitor_transactions'] ?></span>
                                (<?= $data['total_transactions'] > 0 ? round(($data['visitor_transactions'] / $data['total_transactions']) * 100, 1) : 0 ?>%)
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        
    <?php elseif ($active_report === 'top_items'): ?>
        <h4 class="mb-4"><i class="bi bi-trophy"></i> Top Selling Items - Last <?= $days_back ?> Days</h4>
        
        <?php if (empty($report_data)): ?>
            <div class="alert alert-info">No sales data available for this period.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Rank</th>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th class="text-center">Times Sold</th>
                            <th class="text-center">Total Qty</th>
                            <th class="text-end">Revenue</th>
                            <th class="text-center">Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $rank = 1;
                        foreach ($report_data as $row): 
                            $stock_class = $row['current_stock'] == 0 ? 'danger' : ($row['current_stock'] < 10 ? 'warning' : 'success');
                        ?>
                            <tr>
                                <td>
                                    <?php if ($rank <= 3): ?>
                                        <span class="badge bg-warning">🏆 #<?= $rank ?></span>
                                    <?php else: ?>
                                        <strong>#<?= $rank ?></strong>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?= htmlspecialchars($row['item_name']) ?></strong></td>
                                <td><?= htmlspecialchars($row['category'] ?? 'Uncategorized') ?></td>
                                <td>$<?= number_format($row['current_price'], 2) ?></td>
                                <td class="text-center"><?= $row['times_sold'] ?></td>
                                <td class="text-center">
                                    <span class="stat-badge bg-primary text-white"><?= $row['total_quantity_sold'] ?></span>
                                </td>
                                <td class="text-end"><strong>$<?= number_format($row['total_revenue'], 2) ?></strong></td>
                                <td class="text-center">
                                    <span class="badge bg-<?= $stock_class ?>"><?= $row['current_stock'] ?></span>
                                </td>
                            </tr>
                        <?php 
                            $rank++;
                        endforeach; 
                        ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="6" class="text-end"><strong>Total Revenue (Top Items):</strong></td>
                            <td class="text-end"><strong>$<?= number_format(array_sum(array_column($report_data, 'total_revenue')), 2) ?></strong></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
        
    <?php elseif ($active_report === 'low_stock'): ?>
        <h4 class="mb-4"><i class="bi bi-exclamation-triangle"></i> Low Stock Alerts</h4>
        <p class="text-muted">Items with stock at or below <?= $stock_threshold ?> units</p>
        
        <?php if (empty($report_data)): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle"></i> <strong>Great!</strong> All items are adequately stocked.
            </div>
        <?php else: ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i> <strong><?= count($report_data) ?> item(s)</strong> need restocking.
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th class="text-center">Current Stock</th>
                            <th class="text-center">Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report_data as $row): 
                            $status_class = $row['stock_status'] === 'OUT OF STOCK' ? 'danger' : 'warning';
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($row['item_name']) ?></strong></td>
                                <td><?= htmlspecialchars($row['category'] ?? 'Uncategorized') ?></td>
                                <td>$<?= number_format($row['price'], 2) ?></td>
                                <td class="text-center">
                                    <span class="stat-badge bg-<?= $status_class ?> text-white">
                                        <?= $row['quantity_in_stock'] ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-<?= $status_class ?>"><?= $row['stock_status'] ?></span>
                                </td>
                                <td>
                                    <a href="/shop/inventory.php" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-plus-circle"></i> Restock
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        
    <?php elseif ($active_report === 'revenue'): ?>
        <h4 class="mb-4"><i class="bi bi-graph-up"></i> Revenue Trend</h4>
        <p class="text-muted"><?= date('F j, Y', strtotime($start_date)) ?> to <?= date('F j, Y', strtotime($end_date)) ?></p>
        
        <?php if (empty($report_data)): ?>
            <div class="alert alert-info">No sales data for this date range.</div>
        <?php else: 
            $total_revenue = array_sum(array_column($report_data, 'daily_revenue'));
            $total_transactions = array_sum(array_column($report_data, 'transactions'));
            $avg_daily = count($report_data) > 0 ? $total_revenue / count($report_data) : 0;
        ?>
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h6 class="text-uppercase">Total Revenue</h6>
                            <div class="revenue-highlight">$<?= number_format($total_revenue, 2) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <h6 class="text-uppercase">Total Transactions</h6>
                            <div class="revenue-highlight"><?= $total_transactions ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <h6 class="text-uppercase">Avg Daily Revenue</h6>
                            <div class="revenue-highlight">$<?= number_format($avg_daily, 2) ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th class="text-center">Transactions</th>
                            <th class="text-end">Revenue</th>
                            <th class="text-end">Discounts</th>
                            <th class="text-center">Unique Members</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report_data as $row): ?>
                            <tr>
                                <td><strong><?= date('D, M j, Y', strtotime($row['sale_date'])) ?></strong></td>
                                <td class="text-center"><?= $row['transactions'] ?></td>
                                <td class="text-end"><strong>$<?= number_format($row['daily_revenue'], 2) ?></strong></td>
                                <td class="text-end text-danger">-$<?= number_format($row['daily_discounts'], 2) ?></td>
                                <td class="text-center"><?= $row['unique_members'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td><strong>Totals:</strong></td>
                            <td class="text-center"><strong><?= $total_transactions ?></strong></td>
                            <td class="text-end"><strong>$<?= number_format($total_revenue, 2) ?></strong></td>
                            <td class="text-end"><strong>-$<?= number_format(array_sum(array_column($report_data, 'daily_discounts')), 2) ?></strong></td>
                            <td class="text-center"><strong><?= count(array_unique(array_column($report_data, 'unique_members'))) ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Print Button -->
<button class="btn btn-success btn-lg rounded-circle print-btn" onclick="window.print()" title="Print Report">
    <i class="bi bi-printer fs-4"></i>
</button>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>
