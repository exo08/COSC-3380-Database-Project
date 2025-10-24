<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if(!isset($_SESSION['user_id'])){
    header('Location: /login.php');
    exit;
}

// load permissions
require_once __DIR__ . '/app/permissions.php'; // add /.. for local testing

$user = [
    'username' => $_SESSION['username'],
    'user_type' => $_SESSION['user_type'],
    'email' => $_SESSION['email']
];

// get role specific menu items
$menu_items = getAllowedMenuItems();
$role_name = getRoleDisplayName($user['user_type']);
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
                <i class="bi bi-bank2"></i> Museum HFA
            </a>
            <div class="ms-auto">
                <span class="text-white me-3">
                    <i class="bi bi-person-circle"></i> <?= htmlspecialchars($user['username']) ?>
                    <span class="badge bg-primary"><?= $role_name ?></span>
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
                    <p class="mb-0">You are logged in as <strong><?= $role_name ?></strong></p>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- user info card -->
            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-person-badge"></i> User Info</h5>
                        <hr>
                        <p><strong>Username:</strong> <?= htmlspecialchars($user['username']) ?></p>
                        <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
                        <p><strong>Role:</strong> 
                            <span class="badge bg-primary"><?= $role_name ?></span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- quick action menu-->
            <div class="col-md-8 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-lightning-charge"></i> Quick Actions</h5>
                    </div>

                    <div class="card-body">
                        <div class="row">
                            <?php foreach($menu_items as $item): ?>
                                <div class="col-md-6 mb-3">
                                    <a href="<?= $item['url'] ?>" class="btn btn-outline-primary w-100 text-start">
                                        <i class="bi bi-<?= $item['icon'] ?>"></i> <?= $item['name'] ?>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- role specific instructions -->
         <div class="row">
            <div class="col-md-12">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-info-circle"></i> Your Permissions</h5>
                        <hr>

                        <?php if($user['user_type'] === 'admin'): ?>
                            <div class="alert alert-info">
                                <strong>Administrator Access:</strong> You have full access to all museum systems.
                            </div>
                        <?php elseif($user['user_type'] === 'curator'): ?>
                            <div class="alert alert-success">
                                <strong>Curator Access:</strong> You can manage artworks, artists, exhibitions, and acquisitions. Generate collection and exhibition reports.
                            </div>
                        <?php elseif($user['user_type'] === 'shop_staff'): ?>
                            <div class="alert alert-warning">
                                <strong>Shop Staff Access:</strong> You can process sales, manage inventory, and view sales reports.
                            </div>
                        <?php elseif($user['user_type'] === 'event_staff'): ?>
                            <div class="alert alert-primary">
                                <strong>Event Staff Access:</strong> You can manage events, sell and check-in tickets, and view event reports.
                            </div>
                        <?php elseif($user['user_type'] === 'member'): ?>
                            <div class="alert alert-secondary">
                                <strong>Member Access:</strong> You can browse exhibitions, purchase tickets, shop at the store, and manage your membership.
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