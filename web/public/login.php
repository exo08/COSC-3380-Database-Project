<?php
session_start();
$error = '';
$success = '';

// check for login
if(isset($_SESSION['username'])){
    header('Location: /dashboard.php');
    exit;
}

if($_SERVER['REQUEST_METHOD'] == 'POST'){
    require_once __DIR__ . '/app/db.php'; // add /..
    
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if(empty($username) || empty($password)){
        $error = 'Put in both buddy';
    }else{
        $db = db();
        $password_hash = hash('sha256', $password);

        $stmt = $db->prepare("SELECT user_id, username, email, user_type FROM USER_ACCOUNT WHERE username = ? AND password_hash = ? AND is_active = 1");
        $stmt->bind_param("ss", $username, $password_hash);
        $stmt->execute();
        $result = $stmt->get_result();

        if($user = $result->fetch_assoc()){
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_type'] = $user['user_type'];
            $_SESSION['email'] = $user['email'];
            
            // Update last_login timestamp
            $user_id = $user['user_id'];
            $update_stmt = $db->prepare("UPDATE USER_ACCOUNT SET last_login = NOW() WHERE user_id = ?");
            $update_stmt->bind_param("i", $user_id);
            $update_stmt->execute();
            $update_stmt->close();
            
            header('Location: /dashboard.php');
            exit;
        }else{
            $error = 'Invalid user and pass';
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale-1.0">
        <title>Login HFA</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    </head>
    <body class="bg-light">
        <nav class="navbar navbar-dark bg-dark">
            <div class="container">
                <a class="navbar-brand" href="/">
                    <i class="bi bi-bank2"></i> Museum HFA
                </a>
            </div>
        </nav>

        <div class="container mt-5">
            <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <h2 class="text-center mb-4">Login</h2>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control form-control-lg" id="username" name="username" required autofocus>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control form-control-lg" id="password" name="password" required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-lg w-100">
                                <i class="bi bi-box-arrow-in-right"></i> Login
                            </button>
                        </form>
                        
                        <hr class="my-4">
                        
                        <div class="text-center">
                            <p class="mb-2"><strong>Test Accounts:</strong></p>
                            <small class="text-muted d-block">Admin: <code>admin</code> / <code>admin123</code></small>
                            <small class="text-muted d-block">Curator: <code>curator</code> / <code>curator123</code></small>
                            <small class="text-muted d-block">Shop Staff: <code>shopstaff</code> / <code>shop123</code></small>
                            <small class="text-muted d-block">Event Staff: <code>eventstaff</code> / <code>event123</code></small>
                            <small class="text-muted d-block">Member: <code>member</code> / <code>member123</code></small>
                        </div>
                        
                        <div class="text-center mt-4">
                            <a href="/" class="text-decoration-none">
                                <i class="bi bi-arrow-left"></i> Back to Home
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </body>

</html>