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
    require_once __DIR__ . '/app/db.php';
    
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if(empty($username) || empty($password)){
        $error = 'Please enter both username and password';
    }else{
        $db = db();
        $password_hash = hash('sha256', $password);

        $stmt = $db->prepare("SELECT user_id, username, email, user_type, linked_id FROM USER_ACCOUNT WHERE username = ? AND password_hash = ? AND is_active = 1");
        $stmt->bind_param("ss", $username, $password_hash);
        $stmt->execute();
        $result = $stmt->get_result();

        if($user = $result->fetch_assoc()){
            // Set basic session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_type'] = $user['user_type'];
            $_SESSION['role'] = $user['user_type'];  // Added for compatibility with shop system
            $_SESSION['email'] = $user['email'];
            $_SESSION['linked_id'] = $user['linked_id'];
            
            // If user is a member, get their member details and set member_id
            if ($user['user_type'] === 'member' && $user['linked_id']) {
                $member_stmt = $db->prepare("
                    SELECT member_id, first_name, last_name, membership_type, expiration_date 
                    FROM MEMBER 
                    WHERE member_id = ?
                ");
                $member_stmt->bind_param("i", $user['linked_id']);
                $member_stmt->execute();
                $member_result = $member_stmt->get_result();
                
                if ($member_result->num_rows > 0) {
                    $member = $member_result->fetch_assoc();
                    
                    // SET THESE SESSION VARIABLES FOR SHOP DISCOUNT
                    $_SESSION['member_id'] = $member['member_id'];
                    $_SESSION['first_name'] = $member['first_name'];
                    $_SESSION['last_name'] = $member['last_name'];
                    $_SESSION['membership_type'] = $member['membership_type'];
                    $_SESSION['expiration_date'] = $member['expiration_date'];
                }
                $member_stmt->close();
            }
            // If user is staff, get their staff details
            elseif (in_array($user['user_type'], ['curator', 'shop_staff', 'event_staff', 'admin']) && $user['linked_id']) {
                $staff_stmt = $db->prepare("
                    SELECT staff_id, name, email, title 
                    FROM STAFF 
                    WHERE staff_id = ?
                ");
                $staff_stmt->bind_param("i", $user['linked_id']);
                $staff_stmt->execute();
                $staff_result = $staff_stmt->get_result();
                
                if ($staff_result->num_rows > 0) {
                    $staff = $staff_result->fetch_assoc();
                    $_SESSION['staff_id'] = $staff['staff_id'];
                    $_SESSION['staff_name'] = $staff['name'];
                    $_SESSION['staff_title'] = $staff['title'];
                }
                $staff_stmt->close();
            }
            
            // Update last_login timestamp
            $user_id = $user['user_id'];
            $update_stmt = $db->prepare("UPDATE USER_ACCOUNT SET last_login = NOW() WHERE user_id = ?");
            $update_stmt->bind_param("i", $user_id);
            $update_stmt->execute();
            $update_stmt->close();
            
            header('Location: /dashboard.php');
            exit;
        }else{
            $error = 'Invalid username or password';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login - Homies Fine Arts Museum</title>
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
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                flex-direction: column;
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

            .main-navbar .navbar-brand:hover {
                color: white;
            }

            .login-container {
                flex: 1;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 3rem 1rem;
            }

            .login-card {
                background: white;
                border-radius: 20px;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
                overflow: hidden;
                max-width: 450px;
                width: 100%;
            }

            .login-header {
                background: linear-gradient(135deg, var(--primary-color) 0%, #34495e 100%);
                color: white;
                padding: 2.5rem 2rem 2rem;
                text-align: center;
            }

            .login-header h2 {
                font-size: 2rem;
                font-weight: 700;
                margin-bottom: 0.5rem;
            }

            .login-header p {
                opacity: 0.9;
                margin-bottom: 0;
            }

            .login-body {
                padding: 2.5rem 2rem;
            }

            .form-label {
                font-weight: 600;
                color: var(--primary-color);
                margin-bottom: 0.5rem;
            }

            .form-control {
                border-radius: 10px;
                border: 2px solid #e0e0e0;
                padding: 0.75rem 1rem;
                transition: all 0.3s;
            }

            .form-control:focus {
                border-color: var(--accent-color);
                box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
            }

            .btn-login-submit {
                background: linear-gradient(135deg, var(--accent-color) 0%, #2980b9 100%);
                color: white;
                border: none;
                padding: 0.875rem 2rem;
                border-radius: 10px;
                font-weight: 600;
                font-size: 1.1rem;
                width: 100%;
                transition: all 0.3s;
                margin-top: 1rem;
            }

            .btn-login-submit:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 25px rgba(52, 152, 219, 0.4);
            }

            .divider {
                text-align: center;
                margin: 1.5rem 0;
                position: relative;
            }

            .divider::before {
                content: '';
                position: absolute;
                left: 0;
                top: 50%;
                width: 100%;
                height: 1px;
                background: #e0e0e0;
            }

            .divider span {
                background: white;
                padding: 0 1rem;
                position: relative;
                color: #666;
                font-size: 0.9rem;
            }

            .register-link {
                text-align: center;
                padding-top: 1rem;
                border-top: 1px solid #e0e0e0;
            }

            .register-link a {
                color: var(--accent-color);
                font-weight: 600;
                text-decoration: none;
                transition: color 0.3s;
            }

            .register-link a:hover {
                color: #2980b9;
            }

            .alert {
                border-radius: 10px;
                border: none;
            }

            .alert-danger {
                background: #fee;
                color: #c33;
            }

            .alert-success {
                background: #efe;
                color: #3c3;
            }
        </style>
    </head>
    <body>
        <!-- Navigation -->
        <nav class="navbar navbar-expand-lg main-navbar">
            <div class="container">
                <a class="navbar-brand" href="/index.php">
                    <i class="bi bi-bank2"></i> HFA Museum
                </a>
            </div>
        </nav>

        <div class="login-container">
            <div class="login-card">
                <div class="login-header">
                    <h2><i class="bi bi-box-arrow-in-right"></i> Welcome Back</h2>
                    <p>Sign in to your account</p>
                </div>
                
                <div class="login-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="bi bi-person"></i> Username
                            </label>
                            <input type="text" class="form-control" name="username" required autofocus 
                                   placeholder="Enter your username">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="bi bi-lock"></i> Password
                            </label>
                            <input type="password" class="form-control" name="password" required
                                   placeholder="Enter your password">
                        </div>
                        
                        <button type="submit" class="btn btn-login-submit">
                            <i class="bi bi-box-arrow-in-right"></i> Sign In
                        </button>
                    </form>
                    
                    <div class="divider">
                        <span>New to HFA Museum?</span>
                    </div>
                    
                    <div class="register-link">
                        <p class="mb-0">
                            Don't have an account? 
                            <a href="/register.php">
                                <i class="bi bi-person-plus"></i> Become a Member
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </body>
</html>