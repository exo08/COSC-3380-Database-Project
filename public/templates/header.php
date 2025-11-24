<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check for logged in user
$is_logged_in = isset($_SESSION['user_id']);
$username = $is_logged_in ? ($_SESSION['first_name'] ?? 'User') : '';

// Get current page for active nav highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale-1.0">
        <title><?php echo isset($page_title) ? $page_title . ' - Homies Fine Arts' : 'Homies Fine Arts Museum'; ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
        <style>
            :root {
                --primary-color: #2c3e50;
                --secondary-color: #e74c3c;
                --accent-color: #3498db;
                --light-bg-color: #ecf0f1;
            }

            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            }

            /* navigation styles */
            .main-navbar {
                background-color: var(--primary-color);
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
                padding: 1rem 0;
            }

            .main-navbar .navbar-brand {
                font-size: 1.5rem;
                font-weight: 600;
                color: white;
                letter-spacing: 0.5px;
            }

            .main-navbar .nav-link {
                color: rgba(255, 255, 255, 0.85);
                font-weight: 500;
                margin: 0 0.5rem;
                transition: all 0.3s;
                padding: 0.5rem 1rem;
            }


            .main-navbar .nav-link:hover, .main-navbar .nav-link.active {
                color: white;
                background: rgba(255, 255, 255, 0.1);
                border-radius: 5px;
            }

            .btn-login {
                background: var(--accent-color);
                color: white;
                border: none;
                padding: 0.5rem 1rem;
                border-radius: 25px;
                font-weight: 500;
                transition: all 0.3s;
            }

            .btn-login:hover {
                background: #2980b9;
                color: white;
                transform: translateY(-2px);
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
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
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
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

            .btn-donate:hover {
                background: #c0392b;
                color: white;
                transform: translateY(-2px);
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            }

            /* hero section */
            .hero-section {
                background: linear-gradient(rgba(44,62,80,0.7), rgba(44,62,80,0.7)), url('https://images.unsplash.com/photo-1518998053901-5348d3961a04?w=1200') center/cover;
                color: white;
                padding: 120px 0;
                text-align: center;
            }

            .hero-section h1 {
                font-size: 3.5rem;
                font-weight: 700;
                margin-bottom: 1.5rem;
                text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
            }

            .hero-section p {
                font-size: 1.3rem;
                margin-bottom: 2rem;
                max-width: 700px;
                margin-left: auto;
                margin-right: auto;
            }

            /* feature section */
            .feature-card {
                background: white;
                border-radius: 15px;
                padding: 2rem;
                text-align: center;
                transition: all 0.3s;
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
                height: 100%;
            }

            .feature-card:hover {
                transform: translateY(-10px);
                box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
            }

            .feature-card i {
                font-size: 3rem;
                color: var(--accent-color);
                margin-bottom: 1rem;
            }

            .feature-card h3 {
                color: var(--primary-color);
                margin-bottom: 1rem;
                font-weight: 600;
            }

            /* section styling */
            .section-title {
                font-size: 2.5rem;
                font-weight: 700;
                color: var(--primary-color);
                margin-bottom: 3rem;
                text-align: center;
            }

            .cta-section {
                background: var(--light-bg-color);
                padding: 80px 0;
            }

            .stats-section {
                background: var(--primary-color);
                color: white;
                padding: 60px 0;
            }

            .stat-box {
                text-align: center;
                padding: 2rem;
            }

            .stat-box h2 {
                font-size: 3rem;
                font-weight: 700;
                margin-bottom: 0.5rem;
            }

            .stat-box p {
                font-size: 1.1rem;
                opacity: 0.9;
            }

            /* footer */
            .footer {
                background: var(--primary-color);
                color: white;
                padding: 4rem 0 2rem;
            }

            .footer h5 {
                font-weight: 600;
                margin-bottom: 1.5rem;
            }

            .footer a {
                color: rgba(255, 255, 255, 0.8);
                text-decoration: none;
                transition: color 0.3s;
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
                <a class="navbar-brand" href="/index.php">
                    <i class="bi bi-bank2"></i> HFA Museum
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav mx-auto">
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>" href="/index.php">
                                <i class="bi bi-house"></i> Home
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'events.php') ? 'active' : ''; ?>" href="/events.php">
                                <i class="bi bi-calendar-event"></i> Events
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'exhibitions.php') ? 'active' : ''; ?>" href="/exhibitions.php">
                                <i class="bi bi-easel"></i> Exhibitions
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'shop.php') ? 'active' : ''; ?>" href="/shop.php">
                                <i class="bi bi-shop"></i> Gift Shop
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'about.php') ? 'active' : ''; ?>" href="/about.php">
                                <i class="bi bi-info-circle"></i> About
                            </a>
                        </li>
                    </ul>
                    <div class="d-flex gap-2">
                        <button class="btn btn-donate" onclick="window.location.href='/donate.php'">
                            <i class="bi bi-heart"></i> Donate
                        </button>
                        <?php if ($is_logged_in): ?>
                            <button class="btn btn-account" onclick="window.location.href='/dashboard.php'">
                                <i class="bi bi-person-circle"></i> My Account
                            </button>
                        <?php else: ?>
                            <button class="btn btn-login" onclick="window.location.href='/login.php'">
                                <i class="bi bi-box-arrow-in-right"></i> Login
                            </button>
                            <button class="btn btn-outline-light" onclick="window.location.href='/register.php'">
                                <i class="bi bi-person-plus"></i> Sign Up
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </nav>