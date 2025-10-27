<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// Simple home page for testing
require __DIR__ . '/app/db.php'; // add /.. for local testing

// test DB connection
try {
    $db = db();
    $db_status = "Database connected!";
}catch (Exception $e){
    $db_status = "Database error: " . $e->getMessage();
}

?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale-1.0">
        <title>Museum HFA - Home</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    </head>
    <body>
        <!-- navigation -->
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container">
                <a class="navbar-brand">
                    <i class="bi bi-bank2"></i> Museum HFA
                </a>
                <div class="ms-auto">
                    <a href="/login.php" class="btn btn-outline-light">
                        <i class="bi bi-box-arrow-in-right"></i> Login
                    </a>
                </div>
            </div>
        </nav>

        <div class="bg-light py-5">
            <div class="container text-center">
                <h1 class="display-3 fw-bold mb-4">Welcome to Homies Fine Arts</h1>
                <p class="lead mb-4">UH's Premier Art Museum</p>
                <!-- Database status -->
                <div class="alert alert-info d-inline-block">
                    <?= $db_status ?>
                </div>
                <div class="mt-4">
                    <a href="/login.php" class="btn btn-primary btn-lg">
                        <i class="bi bi-box-arrow-in-right"></i> Login to get started
                    </a>
                </div>
            </div>
        </div>

        <!-- Features -->
        <div class="container my-5">
            <div class="row text-center">
                <div class="col-md-4 mb-4">
                    <i class="bi bi-palette-fill text-primary" style="font-size: 3rem;"></i>
                    <h4 class="mt-3">Gift Shop</h4>
                    <p class="text-muted">Buy Stuff</p>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="bg-dark text-white py-4 mt-5">
            <div class="container text-center">
                <p class="mb-0">&copy; 2025 Museum HFA. All rights reserved.</p>
                <small class="text-muted">Team project</small>
            </div>
        </footer>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </body>
</html>