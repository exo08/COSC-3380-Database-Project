<?php
// main layout header with sidebar navigation
// goes at the top of the dashboard pages

if(!isset($_SESSION['user_id'])){
    header('Location: /login.php');
    exit;
}

require_once __DIR__ . '/../app/permissions.php';

$user = [
    'username' => $_SESSION['username'],
    'user_type' => $_SESSION['user_type'],
    'email' => $_SESSION['email']
];

$menu_items = getAllowedMenuItems();
$role_name = getRoleDisplayName($user['user_type']);

// get current page to highlight the active menu
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Dashboard' ?> - Museum HFA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            overflow-x: hidden;
        }

        /* sidebar styles */
        #sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%);
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }

        #sidebar .nav-link{
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            margin: 4px 10px;
            border-radius: 8px;
            transition: all 0.3s;
        }

        #sidebar .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            transform: translateX(5px);
        }

        #sidebar .nav-link.active {
            background: linear-gradient(90deg, #3498db 0%, #2980b9 100%);
            color: white;
            font-weight: 500;
        }

        #sidebar .nav-link i {
            width: 20px;
            margin-right: 10px;
        }

        .sidebar-header {
            padding: 20px;
            background: rgba(0, 0, 0, 0.2);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .user-info {
            color: white;
            padding: 15px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* main content */
        #main-content {
            min-height: 100vh;
            background: #f8f9fa;
        }

        .top-bar{
            background: white;
            padding: 15px 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .content-area {
            padding: 0 30px 30px 30px;
        }

        /* cards */
        .stat-card{
            border-radius: 10px;
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- sidebar -->
            <div class="col-md-2 col-lg-2 px-0" id="sidebar">
                <!-- logo -->
                <div class="sidebar-header text-center">
                    <h4 class="text-white mb-0">
                        <i class="bi bi-bank2"></i> Museum HFA
                    </h4>
                </div>

                <!-- user info -->
                <div class="user-info">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-person-circle fs-3 me-2"></i>
                        <div>
                            <div class="fw-bold">
                                <?= htmlspecialchars($user['username']) ?>
                            </div>
                            <small class="badge bg-primary"><?= $role_name ?></small>
                        </div>
                    </div>
                </div>

                <!-- navigation menu -->
                <nav class="nav flex-column py-3">
                    <?php foreach($menu_items as $item): ?>
                        <?php
                            $is_active = '';
                            // check if current page matches menu item
                            if(strpos($_SERVER['REQUEST_URI'], $item['url']) !== false){
                                $is_active = 'active';
                            }

                            // highlight dashboard on dashboard.php
                            if($current_page === 'dashboard.php' && $item['url'] === '/dashboard.php'){
                                $is_active = 'active';
                            }
                        ?>
                            <a href="<?= $item['url'] ?>" class="nav-link <?= $is_active ?>">
                                <i class="bi bi-<?= $item['icon'] ?>"></i><?= $item['name'] ?>
                            </a>
                    <?php endforeach; ?>

                    <hr style="border-color: rgba(255, 255, 255, 0.1); margin: 20px 10px;">
                    <a href="/logout.php" class="nav-link text-danger">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </nav>
            </div>

            <!-- main content -->
            <div class="col-md-10 col-lg-10 px-0" id="main-content">
                <!-- top bar -->
                <div class="top-bar d-flex justify-content-between align-items-center">
                    <h3 class="mb-0"><?= $page_title ?? 'Dashboard' ?></h3>
                    <div class="text-muted">
                        <i class="bi bi-calendar"></i> <?= date('l, F j, Y') ?>
                    </div>
                </div>

                <!-- content area (pages add content here) -->
                <div class="content-area">