php<?php
session_start();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/permissions.php';

// ADD THIS BLOCK:
$report_name = basename(__FILE__, '.php'); // Gets filename without .php
if (!hasReportAccess($report_name)) {
    header('Location: index.php?error=access_denied');
    exit;
}

$page_title = 'Revenue by Item';
$db = db();

$revenue = [];
$search_performed = false;

if (isset($_POST['item_id'])) {
    $item_id = $_POST['item_id'];
    
    $stmt = $db->prepare("CALL GetRevenueByItem(?)");
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $revenue = $result->fetch_assoc();
    $stmt->close();
    $db->next_result();
    
    $search_performed = true;
}

// Get all shop items for dropdown
$items_result = $db->query("SELECT item_id, item_name FROM SHOP_ITEM ORDER BY item_name");
$shop_items = $items_result->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../templates/layout_header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="index.php" class="btn btn-outline-secondary mb-2">
                <i class="bi bi-arrow-left"></i> Back to Reports
            </a>
            <h1 class="mb-0"><i class="bi bi-bag"></i> Revenue by Item</h1>
            <p class="text-muted">View sales and revenue for specific shop items</p>
        </div>
    </div>
    
    <div class="card mb-4">
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-8">
                        <label class="form-label">Select Item</label>
                        <select name="item_id" class="form-select" required>
                            <option value="">Choose an item...</option>
                            <?php foreach ($shop_items as $item): ?>
                                <option value="<?= $item['item_id'] ?>" 
                                    <?= (isset($_POST['item_id']) && $_POST['item_id'] == $item['item_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($item['item_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i> Get Revenue Report
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <?php if ($search_performed): ?>
        <?php if (empty($revenue) || $revenue['number_sold'] === null): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i> No sales data found for this item.
            </div>
        <?php else: ?>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <h6 class="text-uppercase">Item</h6>
                            <h3><?= htmlspecialchars($revenue['item_name']) ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <h6 class="text-uppercase">Units Sold</h6>
                            <h3><?= number_format($revenue['number_sold']) ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h6 class="text-uppercase">Total Revenue</h6>
                            <h3>$<?= number_format($revenue['total_revenue'], 2) ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>

5. Revenue by Category
File: web/public/reports/revenue-by-category.php
php<?php
session_start();
require_once __DIR__ . '/../app/db.php';

$page_title = 'Revenue by Category';
$db = db();

$revenue = [];
$search_performed = false;

if (isset($_POST['category'])) {
    $category = $_POST['category'];
    
    $stmt = $db->prepare("CALL GetRevenueByCategory(?)");
    $stmt->bind_param("s", $category);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $revenue = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $db->next_result();
    
    $search_performed = true;
}

include __DIR__ . '/../templates/layout_header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="index.php" class="btn btn-outline-secondary mb-2">
                <i class="bi bi-arrow-left"></i> Back to Reports
            </a>
            <h1 class="mb-0"><i class="bi bi-tags"></i> Revenue by Category</h1>
            <p class="text-muted">Analyze sales performance by product category</p>
        </div>
    </div>
    
    <div class="card mb-4">
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-8">
                        <label class="form-label">Category Name (partial match)</label>
                        <input type="text" name="category" class="form-control" 
                               value="<?= htmlspecialchars($_POST['category'] ?? '') ?>" 
                               placeholder="e.g., Books, Apparel, Art..." required>
                        <small class="text-muted">Searches for categories containing this text</small>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i> Search
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <?php if ($search_performed): ?>
        <?php if (empty($revenue)): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i> No categories found matching "<?= htmlspecialchars($_POST['category']) ?>".
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Category</th>
                                    <th>Units Sold</th>
                                    <th>Total Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_sold = 0;
                                $total_revenue = 0;
                                foreach ($revenue as $cat): 
                                    $total_sold += $cat['number_sold'];
                                    $total_revenue += $cat['total_revenue'];
                                ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($cat['category']) ?></strong></td>
                                        <td><?= number_format($cat['number_sold']) ?></td>
                                        <td>$<?= number_format($cat['total_revenue'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-secondary">
                                <tr>
                                    <th>TOTAL</th>
                                    <th><?= number_format($total_sold) ?></th>
                                    <th>$<?= number_format($total_revenue, 2) ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Chart -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Revenue Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="revenueChart" height="80"></canvas>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php if ($search_performed && !empty($revenue)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('revenueChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($revenue, 'category')) ?>,
        datasets: [{
            label: 'Revenue ($)',
            data: <?= json_encode(array_column($revenue, 'total_revenue')) ?>,
            backgroundColor: 'rgba(25, 135, 84, 0.7)',
            borderColor: 'rgba(25, 135, 84, 1)',
            borderWidth: 1
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
        }
    }
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>