<?php
session_start();
require_once __DIR__ . '/app/db.php';

$db = db();

// Get filter parameters
$category_filter = $_GET['category'] ?? '';
$search_query = $_GET['search'] ?? '';

// Get all categories for filter
$categories_query = "
    SELECT DISTINCT c.category_id, c.name 
    FROM CATEGORY c
    INNER JOIN SHOP_ITEM si ON c.category_id = si.category_id
    WHERE si.quantity_in_stock > 0 AND c.is_active = 1
    ORDER BY c.name
";
$categories_result = $db->query($categories_query);

// Build shop items query
$items_query = "
    SELECT 
        si.item_id,
        si.item_name,
        si.description,
        c.category_id,
        c.name as category_name,
        si.price,
        si.quantity_in_stock
    FROM SHOP_ITEM si
    LEFT JOIN CATEGORY c ON si.category_id = c.category_id
    WHERE si.quantity_in_stock > 0
";

$params = [];
$types = '';

if (!empty($category_filter)) {
    $items_query .= " AND c.category_id = ?";
    $params[] = $category_filter;
    $types .= 'i';
}

if (!empty($search_query)) {
    $items_query .= " AND (si.item_name LIKE ? OR si.description LIKE ?)";
    $search_param = '%' . $search_query . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

$items_query .= " ORDER BY c.name, si.item_name";

$stmt = $db->prepare($items_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$items_result = $stmt->get_result();

// Group items by category
$items_by_category = [];
while ($item = $items_result->fetch_assoc()) {
    $category = $item['category_name'] ?? 'Uncategorized';
    if (!isset($items_by_category[$category])) {
        $items_by_category[$category] = [];
    }
    $items_by_category[$category][] = $item;
}
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Homies Fine Arts - Museum Gift Shop</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
        <style>
            :root {
                --primary-color: #2c3e50;
                --accent-color: #3498db;
                --success-color: #27ae60;
            }

            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: #f5f7fa;
            }

            .main-navbar {
                background: var(--primary-color);
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                padding: 1rem 0;
            }

            .main-navbar .navbar-brand {
                font-size: 1.5rem;
                font-weight: 600;
                color: white !important;
            }

            .main-navbar .nav-link {
                color: rgba(255,255,255,0.85) !important;
                font-weight: 500;
                margin: 0 0.5rem;
                padding: 0.5rem 1rem !important;
                transition: all 0.3s;
            }

            .main-navbar .nav-link:hover,
            .main-navbar .nav-link.active {
                color: white !important;
                background: rgba(255,255,255,0.1);
                border-radius: 5px;
            }

            .page-header {
                background: linear-gradient(135deg, var(--success-color), #229954);
                color: white;
                padding: 60px 0;
                margin-bottom: 40px;
            }

            .page-header h1 {
                font-size: 3rem;
                font-weight: 700;
                margin-bottom: 1rem;
            }

            .filter-section {
                background: white;
                padding: 1.5rem;
                border-radius: 15px;
                box-shadow: 0 5px 20px rgba(0,0,0,0.08);
                margin-bottom: 2rem;
            }

            .category-badge {
                display: inline-block;
                padding: 0.5rem 1rem;
                margin: 0.25rem;
                border-radius: 20px;
                border: 2px solid #e0e0e0;
                background: white;
                cursor: pointer;
                transition: all 0.3s;
                text-decoration: none;
                color: #666;
            }

            .category-badge:hover,
            .category-badge.active {
                border-color: var(--success-color);
                background: var(--success-color);
                color: white;
            }

            .shop-item-card {
                background: white;
                border-radius: 15px;
                overflow: hidden;
                box-shadow: 0 5px 20px rgba(0,0,0,0.08);
                transition: all 0.3s;
                margin-bottom: 2rem;
                height: 100%;
                display: flex;
                flex-direction: column;
            }

            .shop-item-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            }

            .item-image {
                height: 250px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-size: 4rem;
                position: relative;
            }

            .stock-badge {
                position: absolute;
                top: 15px;
                right: 15px;
                background: rgba(255,255,255,0.95);
                color: #666;
                padding: 0.5rem 1rem;
                border-radius: 20px;
                font-weight: 600;
                font-size: 0.875rem;
            }

            .stock-badge.low-stock {
                background: #ffc107;
                color: #000;
            }

            .item-body {
                padding: 1.5rem;
                flex-grow: 1;
                display: flex;
                flex-direction: column;
            }

            .item-category {
                color: var(--success-color);
                font-weight: 600;
                font-size: 0.875rem;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                margin-bottom: 0.5rem;
            }

            .item-name {
                color: var(--primary-color);
                font-size: 1.25rem;
                font-weight: 700;
                margin-bottom: 0.75rem;
            }

            .item-description {
                color: #666;
                font-size: 0.9rem;
                margin-bottom: 1rem;
                flex-grow: 1;
            }

            .item-price {
                color: var(--success-color);
                font-size: 1.75rem;
                font-weight: 700;
                margin-bottom: 1rem;
            }

            .btn-add-to-cart {
                background: var(--success-color);
                color: white;
                border: none;
                padding: 0.75rem 1.5rem;
                border-radius: 25px;
                font-weight: 600;
                transition: all 0.3s;
                width: 100%;
            }

            .btn-add-to-cart:hover {
                background: #229954;
                transform: translateY(-2px);
                box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            }

            .btn-add-to-cart:disabled {
                background: #95a5a6;
                cursor: not-allowed;
            }

            .cart-indicator {
                position: fixed;
                bottom: 2rem;
                right: 2rem;
                background: var(--success-color);
                color: white;
                width: 60px;
                height: 60px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.5rem;
                cursor: pointer;
                box-shadow: 0 5px 20px rgba(0,0,0,0.3);
                transition: all 0.3s;
                z-index: 1000;
            }

            .cart-indicator:hover {
                transform: scale(1.1);
            }

            .cart-count {
                position: absolute;
                top: -5px;
                right: -5px;
                background: #e74c3c;
                color: white;
                width: 25px;
                height: 25px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 0.875rem;
                font-weight: 700;
            }

            .category-section {
                margin-bottom: 3rem;
            }

            .category-title {
                color: var(--primary-color);
                font-size: 2rem;
                font-weight: 700;
                margin-bottom: 2rem;
                padding-bottom: 1rem;
                border-bottom: 3px solid var(--success-color);
            }

            .search-box {
                border-radius: 25px;
                border: 2px solid #e0e0e0;
                padding: 0.75rem 1.5rem;
            }

            .search-box:focus {
                border-color: var(--success-color);
                box-shadow: 0 0 0 0.2rem rgba(39, 174, 96, 0.25);
            }

            .member-discount-banner {
                background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
                color: white;
                padding: 1rem;
                border-radius: 10px;
                text-align: center;
                margin-bottom: 2rem;
            }

            .btn-donate {
                background: var(--secondary-color);
                color: white;
                border: none;
                padding: 0.5rem 1rem;
                border-radius: 25px;
                font-weight: 500;
                transition: all 0.3s;
            }
        </style>
    </head>
    <body>
        <!-- navigation -->
        <nav class="navbar navbar-expand-lg main-navbar sticky-top">
            <div class="container">
                <a class="navbar-brand" href="index.php">
                    <i class="bi bi-building"></i> HFA Museum
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav mx-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="index.php"><i class="bi bi-house"></i> Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="events.php"><i class="bi bi-calendar-event"></i> Events</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="exhibitions.php"><i class="bi bi-easel"></i> Exhibitions</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="shop.php"><i class="bi bi-shop"></i> Gift Shop</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="about.php"><i class="bi bi-info-circle"></i> About</a>
                        </li>
                    </ul>
                    <div class="d-flex gap-2">
                        <a href="donate.php" class="btn btn-donate">
                            <i class="bi bi-heart"></i> Donate
                        </a>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="dashboard.php" class="btn btn-primary">
                                <i class="bi bi-person-circle"></i> My Account
                            </a>
                        <?php else: ?>
                            <a href="login.php" class="btn btn-primary">
                                <i class="bi bi-box-arrow-in-right"></i> Login
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </nav>

        <!-- header -->
        <div class="page-header">
            <div class="container text-center">
                <h1><i class="bi bi-shop"></i> Gift Shop</h1>
                <p class="lead">Discover unique art-inspired gifts, books, and merchandise</p>
            </div>
        </div>

        <div class="container pb-5">
            <!-- Member Discount Banner -->
            <?php if (isset($_SESSION['user_id']) && isset($_SESSION['membership_type'])): ?>
                <div class="member-discount-banner">
                    <h5 class="mb-0">
                        <i class="bi bi-star-fill"></i> 
                        Member Benefit: Enjoy 10% off all purchases!
                    </h5>
                </div>
            <?php endif; ?>

            <!-- Filter Section -->
            <div class="filter-section">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <form method="GET" action="" class="d-flex gap-2">
                            <input type="text" 
                                class="form-control search-box" 
                                name="search" 
                                placeholder="Search for items..." 
                                value="<?= htmlspecialchars($search_query) ?>">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-search"></i> Search
                            </button>
                        </form>
                    </div>
                    <div class="col-md-6 text-md-end mt-3 mt-md-0">
                        <strong>Filter by Category:</strong>
                        <a href="shop.php" class="category-badge <?= empty($category_filter) ? 'active' : '' ?>">
                            All Items
                        </a>
                        <?php 
                        $categories_result->data_seek(0);
                        while ($cat = $categories_result->fetch_assoc()): 
                        ?>
                            <a href="?category=<?= urlencode($cat['category_id']) ?>" 
                            class="category-badge <?= $category_filter == $cat['category_id'] ? 'active' : '' ?>">
                                <?= htmlspecialchars($cat['name']) ?>
                            </a>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>

            <!-- Shop Items by Category -->
            <?php if (!empty($items_by_category)): ?>
                <?php foreach ($items_by_category as $category => $items): ?>
                    <div class="category-section">
                        <h2 class="category-title">
                            <i class="bi bi-tag"></i> <?= htmlspecialchars($category) ?>
                        </h2>
                        
                        <div class="row">
                            <?php foreach ($items as $item): 
                                $is_low_stock = $item['quantity_in_stock'] <= 5;
                            ?>
                            <div class="col-md-4 col-lg-3">
                                <div class="shop-item-card">
                                    <div class="item-image">
                                        <i class="bi bi-gift"></i>
                                        <span class="stock-badge <?= $is_low_stock ? 'low-stock' : '' ?>">
                                            <?php if ($is_low_stock): ?>
                                                <i class="bi bi-exclamation-circle"></i> Only <?= $item['quantity_in_stock'] ?> left!
                                            <?php else: ?>
                                                <i class="bi bi-check-circle"></i> In Stock
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    
                                    <div class="item-body">
                                        <div class="item-category"><?= htmlspecialchars($category) ?></div>
                                        <h3 class="item-name"><?= htmlspecialchars($item['item_name']) ?></h3>
                                        <p class="item-description"><?= htmlspecialchars($item['description'] ?? '') ?></p>
                                        
                                        <div class="mt-auto">
                                            <div class="item-price">$<?= number_format($item['price'], 2) ?></div>
                                            <button class="btn btn-add-to-cart" 
                                                    onclick="addToCart(<?= $item['item_id'] ?>, '<?= htmlspecialchars(addslashes($item['item_name'])) ?>', <?= $item['price'] ?>)">
                                                <i class="bi bi-cart-plus"></i> Add to Cart
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox" style="font-size: 5rem; color: #ccc;"></i>
                    <h3 class="mt-3">No Items Found</h3>
                    <p class="text-muted">Try adjusting your search or filter criteria</p>
                    <a href="shop.php" class="btn btn-success">View All Items</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Shopping Cart Indicator -->
        <div class="cart-indicator" onclick="viewCart()" title="View Cart">
            <i class="bi bi-cart"></i>
            <span class="cart-count" id="cart-count">0</span>
        </div>

        <!-- Footer -->
        <footer class="bg-dark text-white py-4 mt-5">
            <div class="container">
                <div class="row">
                    <div class="col-md-6">
                        <h5><i class="bi bi-shop"></i> Gift Shop</h5>
                        <p>Support the museum through your purchases</p>
                        <p class="mb-0"><small><i class="bi bi-truck"></i> Free shipping on orders over $50</small></p>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <p class="mb-0">&copy; 2025 Homies Fine Arts. All rights reserved.</p>
                    </div>
                </div>
            </div>
        </footer>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            // shopping cart functionality using localStorage
            let cart = JSON.parse(localStorage.getItem('museumCart') || '[]');
            
            function updateCartCount() {
                const count = cart.reduce((sum, item) => sum + item.quantity, 0);
                document.getElementById('cart-count').textContent = count;
            }
            
            function addToCart(itemId, itemName, price) {
                const existingItem = cart.find(item => item.id === itemId);
                
                if (existingItem) {
                    existingItem.quantity++;
                } else {
                    cart.push({
                        id: itemId,
                        name: itemName,
                        price: price,
                        quantity: 1
                    });
                }
                
                localStorage.setItem('museumCart', JSON.stringify(cart));
                updateCartCount();
                
                // Show success message
                alert(`"${itemName}" added to cart!`);
            }
            
            function viewCart() {
                if (cart.length === 0) {
                    alert('Your cart is empty!');
                    return;
                }
                
                // redirect to checkout page (not implemented yet)
                window.location.href = 'shop/checkout.php';
            }
            
            // Initialize cart count on page load
            updateCartCount();
        </script>
    </body>
</html>