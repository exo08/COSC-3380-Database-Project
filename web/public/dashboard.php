<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if(!isset($_SESSION['user_id'])){
    header('Location: /login.php');
    exit;
}

$user = [
    'username' => $_SESSION['username'],
    'user_type' => $_SESSION['user_type'],
    'email' => $_SESSION['email']
];
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale-1.0">
        <title>Dashboard HFA</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    </head>
    <body>
        <nav class="navbar navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="/">
                <i class="bi bi-bank2"></i> Museum MFA
            </a>
            <div class="ms-auto">
                <span class="text-white me-3">
                    <i class="bi bi-person-circle"></i> <?= htmlspecialchars($user['username']) ?>
                </span>
                <a href="/logout.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
    </nav>
    <div class="container my-5">
        <div class="row">
            <div class="col-md-12">
                <div class="alert alert-success">
                    <h4><i class="bi bi-check-circle"></i> Login Successful!</h4>
                    <p class="mb-0">Welcome back, <strong><?= htmlspecialchars($user['username']) ?></strong>!</p>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-person-badge"></i> User Info</h5>
                        <hr>
                        <p><strong>Username:</strong> <?= htmlspecialchars($user['username']) ?></p>
                        <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
                        <p><strong>Role:</strong> 
                            <span class="badge bg-primary"><?= ucfirst($user['user_type']) ?></span>
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-speedometer2"></i> Dashboard</h5>
                        <hr>
                        <p class="lead">This is your dashboard. More features coming soon!</p>
                        
                        <div class="row mt-4">
                            <div class="col-md-6 mb-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <i class="bi bi-palette-fill text-primary" style="font-size: 2rem;"></i>
                                        <h6 class="mt-2">Exhibitions</h6>
                                        <small class="text-muted">Coming soon</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <i class="bi bi-calendar-event text-success" style="font-size: 2rem;"></i>
                                        <h6 class="mt-2">Events</h6>
                                        <small class="text-muted">Coming soon</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <i class="bi bi-image text-info" style="font-size: 2rem;"></i>
                                        <h6 class="mt-2">Artworks</h6>
                                        <small class="text-muted">Coming soon</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <i class="bi bi-shop text-warning" style="font-size: 2rem;"></i>
                                        <h6 class="mt-2">Gift Shop</h6>
                                        <small class="text-muted">Coming soon</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if ($user['user_type'] === 'admin'): ?>
                            <div class="alert alert-info mt-3">
                                <strong><i class="bi bi-star"></i> Admin Access:</strong> 
                                You have full access to manage the museum database.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container text-center">
            <p class="mb-0">&copy; 2025 Museum HFA. All rights reserved.</p>
            <small class="text-muted">Team project</small>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </body>
</html>