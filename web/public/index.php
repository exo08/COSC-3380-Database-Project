<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// Simple home page for testing
require __DIR__ . '/app/db.php'; // add /.. for local testing

// test DB connection
// try {
//     $db = db();
//     $db_status = "Database connected!";
// }catch (Exception $e){
//     $db_status = "Database error: " . $e->getMessage();
// }

// check for logged in user
session_start();
$is_logged_in = isset($_SESSION['user_id']);
$username = $is_logged_in ? ($_SESSION['first_name'] ?? 'User') : '';
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale-1.0">
        <title>Homies Fine Arts - Home</title>
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
                padding: 40px 0 20px;
            }

            .footer a {
                color: rgba(255, 255, 255, 0.7);
                text-decoration: none;
                transition: color 0.3s;
            }

            .footer a:hover {
                color: white;
            }

        </style>
    </head>
    <body>
        <!-- navigation -->
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
                            <a class="nav-link active" href="index.php">
                                <i class="bi bi-house"></i> Home
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="events.php">
                                <i class="bi bi-calendar-event"></i> Events
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="exhibitions.php">
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
                        <button class="btn btn-donate" onclick="window.location.href='donate.php'">
                            <i class="bi bi-heart"></i> Donate
                        </button>
                        <?php if ($is_logged_in): ?>
                            <button class="btn btn-account" onclick="window.location.href='dashboard.php'">
                                <i class="bi bi-person-circle"></i> My Account
                            </button>
                        <?php else: ?>
                            <button class="btn btn-login" onclick="window.location.href='login.php'">
                                <i class="bi bi-box-arrow-in-right"></i> Login
                            </button>
                            <button class="btn btn-outline-light" onclick="window.location.href='register.php'">
                                <i class="bi bi-person-plus"></i> Sign Up
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </nav>
        <!-- hero section -->
        <section class="hero-section">
            <div class="container">
                <h1>Welcome to the Homies Fine Arts Museum</h1>
                <p>Discover very mediocre art collections, okay exhibitions, and cultural experiences that make you question our legitimacy.</p>
                <div class="d-flex gap-3 justify-content-center">
                    <a href="exhibitions.php" class="btn btn-light btn-lg px-4">
                        <i class="bi bi-easel"></i> View Exhibitions
                    </a>
                    <a href="events.php" class="btn btn-outline-light btn-lg px-4">
                        <i class="bi bi-calendar-event"></i> Upcoming Events
                    </a>
                </div>
            </div>
        </section>

        <!-- Features -->
        <section class="py-5">
        <div class="container">
            <h2 class="section-title">Experience the Museum</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="feature-card">
                        <i class="bi bi-palette"></i>
                        <h3>World-Class Collection</h3>
                        <p>Explore a dozen artworks spanning centuries, from classical masterpieces to contemporary works</p>
                        <a href="exhibitions.php" class="btn btn-outline-primary mt-3">
                            Explore Collection
                        </a>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <i class="bi bi-calendar-week"></i>
                        <h3>Special Events</h3>
                        <p>Join us for random events throughout the year</p>
                        <a href="events.php" class="btn btn-outline-primary mt-3">
                            View Events
                        </a>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <i class="bi bi-star"></i>
                        <h3>Become a Member</h3>
                        <p>Enjoy unlimited free admission, exclusive previews, and special discounts at our gift shop</p>
                        <a href="register.php" class="btn btn-outline-primary mt-3">
                            Join Today
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- statistics -->
    <section class="stats-section">
        <div class="container">
            <div class="row">
                <div class="col-md-3">
                    <div class="stat-box">
                        <h2>
                            <i class="bi bi-palette-fill"></i> 4+
                        </h2>
                        <p>Artworks in collection</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box">
                        <h2>
                            <i class="bi bi-easel"></i> 3+
                        </h2>
                        <p>Annual exhibitions</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box">
                        <h2>
                            <i class="bi bi-people"></i> 10+
                        </h2>
                        <p>Annual visitors</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box">
                        <h2>
                            <i class="bi bi-star"></i> 1
                        </h2>
                        <p>Month of excellence</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- cta -->
    <section class="cta-section">
        <div class="container text-center">
            <h2 class="section-title">Support the Arts</h2>
            <p class="lead mb-4">Your contribution helps us stay rich</p>
            <div class="d-flex gap-3 justify-content-center">
                <a href="donate.php" class="btn btn-donate btn-lg px-5">
                    <i class="bi bi-heart-fill"></i> Make a donation
                </a>
                <a href="register.php" class="btn btn-primary btn-lg px-5">
                    <i class="bi bi-star-fill"></i> Become a member
                </a>
            </div>
        </div>
    </section>

    <!-- footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5 class="mb-3">
                        <i class="bi bi-building"></i> Homies Fine Arts
                    </h5>
                    <p>Premier destination for art lovers, featuring very mediocre art.</p>
                </div>
                <div class="col-md-4">
                    <h5 class="mb-3">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="exhibitions.php"><i class="bi bi-arrow-right"></i> Current exhibitions</a></li>
                        <li class="mb-2"><a href="events.php"><i class="bi bi-arrow-right"></i> Upcoming events</a></li>
                        <li class="mb-2"><a href="register.php"><i class="bi bi-arrow-right"></i> Membership</a></li>
                        <li class="mb-2"><a href="donate.php"><i class="bi bi-arrow-right"></i> Support us</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5 class="mb-3">Visit us</h5>
                    <p><i class="bi bi-geo-alt"></i> 123 Money St<br>Laundering, XO 00000</p>
                    <p><i class="bi bi-telephone"></i> (123) 456-7890</p>
                    <p><i class="bi bi-envelope"></i> info@hfa.com</p>
                    <div class="mt-3">
                        <a href="#" class="text-white me-3"><i class="bi bi-facebook fs-4"></i></a>
                        <a href="#" class="text-white me-3"><i class="bi bi-twitter fs-4"></i></a>
                        <a href="#" class="text-white me-3"><i class="bi bi-instagram fs-4"></i></a>
                    </div>
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