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
include __DIR__ . '/templates/header.php';
?>

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
<?php
include __DIR__ . '/templates/footer.php';
?>