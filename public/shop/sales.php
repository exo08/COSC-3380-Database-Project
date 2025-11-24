<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/permissions.php';

// Require shop_staff or admin permission
if (!in_array($_SESSION['user_type'], ['shop_staff', 'admin'])) {
    header('Location: /dashboard.php?error=access_denied');
    exit;
}

requirePermission('view_sales');

$page_title = 'Sales History';
$db = db();
$success = '';
$error = '';

// Get filter parameters
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$search = $_GET['search'] ?? '';
$payment_method = $_GET['payment_method'] ?? '';
$customer_type = $_GET['customer_type'] ?? ''; // 'member', 'visitor', or 'walkin'

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20; // how many per page
$offset = ($page - 1) * $per_page;

// Build the WHERE clause
$where_conditions = ["DATE(s.sale_date) BETWEEN ? AND ?"];
$params = [$date_from, $date_to];
$param_types = "ss";

if (!empty($search)) {
    $where_conditions[] = "(
        CONCAT(COALESCE(m.first_name, ''), ' ', COALESCE(m.last_name, '')) LIKE ? OR
        CONCAT(COALESCE(v.first_name, ''), ' ', COALESCE(v.last_name, '')) LIKE ? OR
        s.sale_id LIKE ?
    )";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= "sss";
}

if (!empty($payment_method)) {
    $where_conditions[] = "s.payment_method = ?";
    $params[] = $payment_method;
    $param_types .= "i";
}

if (!empty($customer_type)) {
    if ($customer_type === 'member') {
        $where_conditions[] = "s.member_id IS NOT NULL";
    } elseif ($customer_type === 'visitor') {
        $where_conditions[] = "s.visitor_id IS NOT NULL AND s.member_id IS NULL";
    } elseif ($customer_type === 'walkin') {
        $where_conditions[] = "s.member_id IS NULL AND s.visitor_id IS NULL";
    }
}

$where_clause = implode(" AND ", $where_conditions);

// Get total count for pagination
$count_query = "
    SELECT COUNT(DISTINCT s.sale_id) as total
    FROM SALE s
    LEFT JOIN MEMBER m ON s.member_id = m.member_id
    LEFT JOIN VISITOR v ON s.visitor_id = v.visitor_id
    WHERE $where_clause
";

$count_stmt = $db->prepare($count_query);
$count_stmt->bind_param($param_types, ...$params);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_sales = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_sales / $per_page);
$count_stmt->close();

// Get sales data
$sales_query = "
    SELECT s.*,
           CONCAT(COALESCE(m.first_name, v.first_name, 'Walk-in'), ' ', COALESCE(m.last_name, v.last_name, 'Customer')) as customer_name,
           m.member_id as is_member,
           v.visitor_id as is_visitor,
           COUNT(sli.item_id) as item_count,
           GROUP_CONCAT(CONCAT(si.item_name, ' (x', sli.quantity, ')') SEPARATOR ', ') as items_summary
    FROM SALE s
    LEFT JOIN MEMBER m ON s.member_id = m.member_id
    LEFT JOIN VISITOR v ON s.visitor_id = v.visitor_id
    LEFT JOIN SALE_ITEM sli ON s.sale_id = sli.sale_id
    LEFT JOIN SHOP_ITEM si ON sli.item_id = si.item_id
    WHERE $where_clause
    GROUP BY s.sale_id
    ORDER BY s.sale_date DESC
    LIMIT ? OFFSET ?
";

$params[] = $per_page;
$params[] = $offset;
$param_types .= "ii";

$sales_stmt = $db->prepare($sales_query);
$sales_stmt->bind_param($param_types, ...$params);
$sales_stmt->execute();
$sales_result = $sales_stmt->get_result();
$sales = $sales_result->fetch_all(MYSQLI_ASSOC);
$sales_stmt->close();

// Get summary statistics for the filtered period
$stats_query = "
    SELECT 
        COUNT(DISTINCT s.sale_id) as total_transactions,
        COALESCE(SUM(s.total_amount), 0) as total_revenue,
        COALESCE(SUM(s.discount_amount), 0) as total_discounts,
        COALESCE(AVG(s.total_amount), 0) as avg_transaction,
        COUNT(DISTINCT s.member_id) as unique_members,
        SUM(CASE WHEN s.member_id IS NOT NULL THEN 1 ELSE 0 END) as member_sales,
        SUM(CASE WHEN s.member_id IS NULL AND s.visitor_id IS NULL THEN 1 ELSE 0 END) as walkin_sales
    FROM SALE s
    WHERE DATE(s.sale_date) BETWEEN ? AND ?
";

$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bind_param("ss", $date_from, $date_to);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();
$stats_stmt->close();

// Payment method lookup
$payment_methods = [
    1 => 'Cash',
    2 => 'Credit Card',
    3 => 'Debit Card',
    4 => 'Gift Card',
    5 => 'Member Account'
];

include __DIR__ . '/../templates/layout_header.php';
?>

<style>
.sale-row {
    transition: background-color 0.2s;
    cursor: pointer;
}
.sale-row:hover {
    background-color: rgba(0, 123, 255, 0.05);
}
.stat-card {
    border-radius: 10px;
    transition: transform 0.2s;
    border-left: 4px solid;
}
.stat-card:hover {
    transform: translateY(-3px);
}
.filter-card {
    background-color: #f8f9fa;
    border-radius: 10px;
}
.customer-badge {
    font-size: 0.75rem;
    font-weight: 600;
}
</style>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2><i class="bi bi-receipt"></i> Sales History</h2>
        <p class="text-muted">View and analyze all shop transactions</p>
    </div>
    <div>
        <a href="/shop/new-sale.php" class="btn btn-success btn-lg">
            <i class="bi bi-cart-plus"></i> New Sale
        </a>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card stat-card border-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 small">Total Revenue</p>
                        <h3 class="mb-0">$<?= number_format($stats['total_revenue'], 2) ?></h3>
                    </div>
                    <div class="text-primary">
                        <i class="bi bi-currency-dollar" style="font-size: 2.5rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card stat-card border-success">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 small">Transactions</p>
                        <h3 class="mb-0"><?= number_format($stats['total_transactions']) ?></h3>
                    </div>
                    <div class="text-success">
                        <i class="bi bi-cart-check" style="font-size: 2.5rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card stat-card border-info">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 small">Avg Transaction</p>
                        <h3 class="mb-0">$<?= number_format($stats['avg_transaction'], 2) ?></h3>
                    </div>
                    <div class="text-info">
                        <i class="bi bi-graph-up-arrow" style="font-size: 2.5rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card stat-card border-warning">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 small">Discounts</p>
                        <h3 class="mb-0">$<?= number_format($stats['total_discounts'], 2) ?></h3>
                    </div>
                    <div class="text-warning">
                        <i class="bi bi-tag" style="font-size: 2.5rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card filter-card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label small text-muted">Date From</label>
                <input type="date" class="form-control" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
            </div>
            
            <div class="col-md-3">
                <label class="form-label small text-muted">Date To</label>
                <input type="date" class="form-control" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
            </div>
            
            <div class="col-md-3">
                <label class="form-label small text-muted">Search Customer/ID</label>
                <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Name or Sale ID">
            </div>
            
            <div class="col-md-3">
                <label class="form-label small text-muted">Payment Method</label>
                <select class="form-select" name="payment_method">
                    <option value="">All Methods</option>
                    <?php foreach ($payment_methods as $id => $name): ?>
                        <option value="<?= $id ?>" <?= $payment_method == $id ? 'selected' : '' ?>><?= $name ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label class="form-label small text-muted">Customer Type</label>
                <select class="form-select" name="customer_type">
                    <option value="">All Types</option>
                    <option value="member" <?= $customer_type === 'member' ? 'selected' : '' ?>>Members</option>
                    <option value="visitor" <?= $customer_type === 'visitor' ? 'selected' : '' ?>>Visitors</option>
                    <option value="walkin" <?= $customer_type === 'walkin' ? 'selected' : '' ?>>Walk-ins</option>
                </select>
            </div>
            
            <div class="col-md-9 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-filter"></i> Apply Filters
                </button>
                <a href="/shop/sales.php" class="btn btn-outline-secondary">
                    <i class="bi bi-x-circle"></i> Clear
                </a>
                <div class="ms-auto text-muted small">
                    Showing <?= number_format(count($sales)) ?> of <?= number_format($total_sales) ?> sales
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Sales Table -->
<div class="card">
    <div class="card-body">
        <h5 class="card-title mb-4">
            <i class="bi bi-list-ul"></i> Sales Transactions
            <?php if ($total_pages > 1): ?>
                <span class="text-muted small">(Page <?= $page ?> of <?= $total_pages ?>)</span>
            <?php endif; ?>
        </h5>
        
        <?php if (empty($sales)): ?>
            <div class="text-center py-5">
                <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                <p class="text-muted mt-3">No sales found for the selected filters.</p>
                <a href="/shop/new-sale.php" class="btn btn-primary">
                    <i class="bi bi-cart-plus"></i> Create New Sale
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Sale ID</th>
                            <th>Date & Time</th>
                            <th>Customer</th>
                            <th>Items</th>
                            <th class="text-end">Discount</th>
                            <th class="text-end">Total</th>
                            <th>Payment</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sales as $sale): ?>
                            <tr class="sale-row" data-sale-id="<?= $sale['sale_id'] ?>">
                                <td>
                                    <strong class="text-primary">#<?= $sale['sale_id'] ?></strong>
                                </td>
                                <td>
                                    <div class="small">
                                        <div><?= date('M j, Y', strtotime($sale['sale_date'])) ?></div>
                                        <div class="text-muted"><?= date('g:i A', strtotime($sale['sale_date'])) ?></div>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <?= htmlspecialchars($sale['customer_name']) ?>
                                    </div>
                                    <div class="mt-1">
                                        <?php if ($sale['is_member']): ?>
                                            <span class="badge bg-success customer-badge">
                                                <i class="bi bi-star-fill"></i> Member
                                            </span>
                                        <?php elseif ($sale['is_visitor']): ?>
                                            <span class="badge bg-info customer-badge">
                                                <i class="bi bi-person"></i> Visitor
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary customer-badge">
                                                <i class="bi bi-incognito"></i> Walk-in
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="small">
                                        <strong><?= $sale['item_count'] ?></strong> items
                                    </div>
                                    <div class="text-muted small text-truncate" style="max-width: 200px;" 
                                         title="<?= htmlspecialchars($sale['items_summary']) ?>">
                                        <?= htmlspecialchars($sale['items_summary']) ?>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <?php if ($sale['discount_amount'] > 0): ?>
                                        <span class="badge bg-success">
                                            -$<?= number_format($sale['discount_amount'], 2) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <strong class="text-success">$<?= number_format($sale['total_amount'], 2) ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?= $payment_methods[$sale['payment_method']] ?? 'Unknown' ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-primary" 
                                            onclick="viewSaleDetails(<?= $sale['sale_id'] ?>)">
                                        <i class="bi bi-eye"></i> View
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="4" class="text-end">Totals on this page:</th>
                            <th class="text-end">
                                -$<?= number_format(array_sum(array_column($sales, 'discount_amount')), 2) ?>
                            </th>
                            <th class="text-end text-success">
                                <strong>$<?= number_format(array_sum(array_column($sales, 'total_amount')), 2) ?></strong>
                            </th>
                            <th colspan="2"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Sales pagination" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page - 1 ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>&search=<?= urlencode($search) ?>&payment_method=<?= $payment_method ?>&customer_type=<?= $customer_type ?>">
                                    <i class="bi bi-chevron-left"></i> Previous
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        if ($start_page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=1&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>&search=<?= urlencode($search) ?>&payment_method=<?= $payment_method ?>&customer_type=<?= $customer_type ?>">1</a>
                            </li>
                            <?php if ($start_page > 2): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>&search=<?= urlencode($search) ?>&payment_method=<?= $payment_method ?>&customer_type=<?= $customer_type ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $total_pages ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>&search=<?= urlencode($search) ?>&payment_method=<?= $payment_method ?>&customer_type=<?= $customer_type ?>"><?= $total_pages ?></a>
                            </li>
                        <?php endif; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page + 1 ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>&search=<?= urlencode($search) ?>&payment_method=<?= $payment_method ?>&customer_type=<?= $customer_type ?>">
                                    Next <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Sale Details Modal -->
<div class="modal fade" id="saleDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-receipt"></i> Sale Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="saleDetailsContent">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function viewSaleDetails(saleId) {
    const modal = new bootstrap.Modal(document.getElementById('saleDetailsModal'));
    modal.show();
    
    // Fetch sale details via AJAX
    fetch(`/shop/get-sale-details.php?sale_id=${saleId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displaySaleDetails(data.sale);
            } else {
                document.getElementById('saleDetailsContent').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i> ${data.error || 'Failed to load sale details'}
                    </div>
                `;
            }
        })
        .catch(error => {
            document.getElementById('saleDetailsContent').innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle"></i> Error loading sale details
                </div>
            `;
        });
}

function displaySaleDetails(sale) {
    const paymentMethods = <?= json_encode($payment_methods) ?>;
    
    let itemsHtml = '';
    if (sale.items && sale.items.length > 0) {
        itemsHtml = sale.items.map(item => `
            <tr>
                <td>${item.item_name}</td>
                <td class="text-center">${item.quantity}</td>
                <td class="text-end">$${parseFloat(item.price_at_sale).toFixed(2)}</td>
                <td class="text-end"><strong>$${(item.quantity * item.price_at_sale).toFixed(2)}</strong></td>
            </tr>
        `).join('');
    }
    
    const customerBadge = sale.is_member 
        ? '<span class="badge bg-success"><i class="bi bi-star-fill"></i> Member</span>'
        : sale.is_visitor 
            ? '<span class="badge bg-info"><i class="bi bi-person"></i> Visitor</span>'
            : '<span class="badge bg-secondary"><i class="bi bi-incognito"></i> Walk-in</span>';
    
    const html = `
        <div class="row mb-4">
            <div class="col-md-6">
                <h6 class="text-muted">Sale Information</h6>
                <table class="table table-sm table-borderless">
                    <tr>
                        <td class="text-muted">Sale ID:</td>
                        <td><strong>#${sale.sale_id}</strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Date:</td>
                        <td>${new Date(sale.sale_date).toLocaleString()}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Payment Method:</td>
                        <td><span class="badge bg-secondary">${paymentMethods[sale.payment_method] || 'Unknown'}</span></td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6 class="text-muted">Customer Information</h6>
                <table class="table table-sm table-borderless">
                    <tr>
                        <td class="text-muted">Name:</td>
                        <td><strong>${sale.customer_name}</strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Type:</td>
                        <td>${customerBadge}</td>
                    </tr>
                </table>
            </div>
        </div>
        
        <h6 class="text-muted mb-3">Items Purchased</h6>
        <div class="table-responsive mb-4">
            <table class="table table-sm">
                <thead class="table-light">
                    <tr>
                        <th>Item</th>
                        <th class="text-center">Quantity</th>
                        <th class="text-end">Unit Price</th>
                        <th class="text-end">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    ${itemsHtml}
                </tbody>
            </table>
        </div>
        
        <div class="border-top pt-3">
            <div class="row">
                <div class="col-md-6 offset-md-6">
                    <table class="table table-sm table-borderless">
                        
                        ${sale.discount_amount > 0 ? `
                        <tr class="text-success">
                            <td class="text-end">Discount:</td>
                            <td class="text-end"><strong>-$${parseFloat(sale.discount_amount).toFixed(2)}</strong></td>
                        </tr>
                        ` : ''}
                        <tr class="border-top">
                            <td class="text-end"><h5 class="mb-0">Total:</h5></td>
                            <td class="text-end"><h5 class="mb-0 text-success">$${parseFloat(sale.total_amount).toFixed(2)}</h5></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('saleDetailsContent').innerHTML = html;
}

// Make rows clickable
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.sale-row').forEach(row => {
        row.addEventListener('click', function(e) {
            // Don't trigger if clicking the View button
            if (e.target.closest('button')) return;
            
            const saleId = this.dataset.saleId;
            viewSaleDetails(saleId);
        });
    });
});
</script>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>