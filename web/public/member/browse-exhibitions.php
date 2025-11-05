<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/app/db.php';

$page_title = 'Exhibitions';
$db = db();

// Get current and upcoming exhibitions
$exhibitions_query = "
    SELECT e.*, 
           CONCAT(s.name) as curator_name,
           COUNT(DISTINCT ea.artwork_id) as artwork_count,
           l.name as main_location,
           CASE 
               WHEN e.end_date >= CURDATE() AND e.start_date <= CURDATE() THEN 'Current'
               WHEN e.start_date > CURDATE() THEN 'Upcoming'
               ELSE 'Past'
           END as status
    FROM EXHIBITION e
    LEFT JOIN STAFF s ON e.curator_id = s.staff_id
    LEFT JOIN EXHIBITION_ARTWORK ea ON e.exhibition_id = ea.exhibition_id
    LEFT JOIN LOCATION l ON ea.location_id = l.location_id
    WHERE (e.is_deleted = FALSE OR e.is_deleted IS NULL)
      AND e.end_date >= CURDATE()
    GROUP BY e.exhibition_id
    ORDER BY 
        CASE 
            WHEN e.start_date <= CURDATE() AND e.end_date >= CURDATE() THEN 1
            WHEN e.start_date > CURDATE() THEN 2
        END,
        e.start_date
";

$exhibitions_result = $db->query($exhibitions_query);
$exhibitions = $exhibitions_result ? $exhibitions_result->fetch_all(MYSQLI_ASSOC) : [];

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);
$username = $is_logged_in ? ($_SESSION['username'] ?? 'User') : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - Museum HFA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #e74c3c;
            --accent-color: #3498db;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .main-navbar {
            background-color: var(--primary-color);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 1rem 0;
        }

        .main-navbar .navbar-brand {
            font-size: 1.5rem;
            font-weight: 600;
            color: white;
        }

        .main-navbar .nav-link {
            color: rgba(255, 255, 255, 0.85);
            font-weight: 500;
            margin: 0 0.5rem;
            transition: all 0.3s;
            padding: 0.5rem 1rem;
        }

        .main-navbar .nav-link:hover,
        .main-navbar .nav-link.active {
            color: white;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 5px;
        }

        .btn-account {
            background: var(--accent-color);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-account:hover {
            background: #2980b9;
            color: white;
            transform: translateY(-2px);
        }

        .hero-section {
            background: linear-gradient(rgba(44,62,80,0.85), rgba(44,62,80,0.85)), 
                        url('https://images.unsplash.com/photo-1513364776144-60967b0f800f?w=1200') center/cover;
            color: white;
            padding: 100px 0 80px;
            margin-bottom: 60px;
        }

        .hero-section h1 {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .exhibition-card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
            height: 100%;
        }

        .exhibition-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
        }

        .exhibition-image {
            height: 250px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 4rem;
        }

        .status-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .curator-info {
            background: #f8f9fa;
            padding: 10px 15px;
            border-radius: 8px;
            margin-top: 10px;
        }

        .artwork-count {
            display: inline-block;
            background: var(--accent-color);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 600;
        }

        .footer {
            background: var(--primary-color);
            color: white;
            padding: 40px 0 20px;
            margin-top: 80px;
        }

        .footer a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
        }

        .footer a:hover {
            color: white;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg main-navbar sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-bank2"></i> HFA Museum
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="bi bi-house"></i> Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="events.php">
                            <i class="bi bi-calendar-event"></i> Events
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="exhibitions.php">
                            <i class="bi bi-easel"></i> Exhibitions
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="shop.php">
                            <i class="bi bi-shop"></i> Gift Shop
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="about.php">
                            <i class="bi bi-info-circle"></i> About
                        </a>
                    </li>
                </ul>
                <div class="d-flex gap-2">
                    <?php if ($is_logged_in): ?>
                        <button class="btn btn-account" onclick="window.location.href='dashboard.php'">
                            <i class="bi bi-person-circle"></i> My Account
                        </button>
                    <?php else: ?>
                        <button class="btn btn-outline-light" onclick="window.location.href='login.php'">
                            <i class="bi bi-box-arrow-in-right"></i> Login
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section text-center">
        <div class="container">
            <h1>Current & Upcoming Exhibitions</h1>
            <p class="lead">Explore our curated collections and featured artworks</p>
        </div>
    </section>

    <!-- Exhibitions Grid -->
    <section class="py-5">
        <div class="container">
            <?php if (empty($exhibitions)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox text-muted" style="font-size: 4rem;"></i>
                    <h3 class="mt-3 text-muted">No exhibitions available at this time</h3>
                    <p class="text-muted">Check back soon for upcoming exhibitions</p>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($exhibitions as $exhibition): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card exhibition-card">
                                <div class="exhibition-image position-relative">
                                    <i class="bi bi-palette-fill"></i>
                                    <span class="status-badge bg-<?= $exhibition['status'] === 'Current' ? 'success' : 'primary' ?>">
                                        <?= htmlspecialchars($exhibition['status']) ?>
                                    </span>
                                </div>
                                <div class="card-body">
                                    <h5 class="card-title"><?= htmlspecialchars($exhibition['title']) ?></h5>
                                    
                                    <div class="mb-3">
                                        <small class="text-muted">
                                            <i class="bi bi-calendar-range"></i>
                                            <?= date('M j, Y', strtotime($exhibition['start_date'])) ?> - 
                                            <?= date('M j, Y', strtotime($exhibition['end_date'])) ?>
                                        </small>
                                    </div>

                                    <?php if (!empty($exhibition['description'])): ?>
                                        <p class="card-text text-muted">
                                            <?= htmlspecialchars(substr($exhibition['description'], 0, 120)) ?>
                                            <?= strlen($exhibition['description']) > 120 ? '...' : '' ?>
                                        </p>
                                    <?php endif; ?>

                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span class="artwork-count">
                                            <i class="bi bi-palette"></i> <?= $exhibition['artwork_count'] ?> artworks
                                        </span>
                                    </div>

                                    <?php if (!empty($exhibition['curator_name'])): ?>
                                        <div class="curator-info">
                                            <small>
                                                <i class="bi bi-person-badge"></i>
                                                <strong>Curator:</strong> <?= htmlspecialchars($exhibition['curator_name']) ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($exhibition['theme_sponsor'])): ?>
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                <i class="bi bi-tag"></i> <?= htmlspecialchars($exhibition['theme_sponsor']) ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5 class="mb-3">
                        <i class="bi bi-building"></i> Homies Fine Arts
                    </h5>
                    <p>Your premier destination for art and culture.</p>
                </div>
                <div class="col-md-4">
                    <h5 class="mb-3">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="exhibitions.php"><i class="bi bi-arrow-right"></i> Exhibitions</a></li>
                        <li class="mb-2"><a href="events.php"><i class="bi bi-arrow-right"></i> Events</a></li>
                        <li class="mb-2"><a href="shop.php"><i class="bi bi-arrow-right"></i> Gift Shop</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5 class="mb-3">Contact</h5>
                    <p><i class="bi bi-geo-alt"></i> 123 Art Street, Houston, TX</p>
                    <p><i class="bi bi-telephone"></i> (123) 456-7890</p>
                    <p><i class="bi bi-envelope"></i> info@hfa.com</p>
                </div>
            </div>
            <hr class="my-4" style="border-color: rgba(255, 255, 255, 0.1);">
            <div class="text-center">
                <p class="mb-0">&copy; 2025 Homies Fine Arts. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
