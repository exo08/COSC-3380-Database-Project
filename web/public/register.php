<?php
session_start();
require_once __DIR__ . '/app/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = db();
    
    // Get form data
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $membership_type = intval($_POST['membership_type'] ?? 1);
    $is_student = isset($_POST['is_student']) ? 1 : 0;
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($first_name) || empty($last_name) || empty($email) || empty($username) || empty($password)) {
        $error = 'Please fill in all required fields.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } else {
        try {
            // Start transaction
            $db->begin_transaction();
            
            // Check if username already exists in USER_ACCOUNT
            $stmt = $db->prepare("SELECT user_id FROM USER_ACCOUNT WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                throw new Exception('Username already taken. Please choose another.');
            }
            
            // Check if email already exists in USER_ACCOUNT
            $stmt = $db->prepare("SELECT user_id FROM USER_ACCOUNT WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                throw new Exception('An account with this email already exists.');
            }
            
            // Calculate membership dates (1 year membership)
            $start_date = date('Y-m-d');
            $expiration_date = date('Y-m-d', strtotime('+1 year'));
            
            // Step 1: Create MEMBER record
            $stmt = $db->prepare("
                INSERT INTO MEMBER (first_name, last_name, email, phone, address, 
                                   membership_type, is_student, start_date, expiration_date, 
                                   auto_renew)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            
            $stmt->bind_param("sssssiiis", 
                $first_name, $last_name, $email, $phone, $address,
                $membership_type, $is_student, $start_date, $expiration_date
            );
            
            if (!$stmt->execute()) {
                throw new Exception('Error creating member record: ' . $stmt->error);
            }
            
            $member_id = $db->insert_id;
            
            // Step 2: Hash password using SHA256 (matching your login.php)
            $password_hash = hash('sha256', $password);
            
            // Step 3: Create USER_ACCOUNT record linked to the MEMBER
            $user_type = 'member';
            $stmt = $db->prepare("
                INSERT INTO USER_ACCOUNT (username, email, password_hash, user_type, linked_id, is_active)
                VALUES (?, ?, ?, ?, ?, 1)
            ");
            
            $stmt->bind_param("ssssi", 
                $username, $email, $password_hash, $user_type, $member_id
            );
            
            if (!$stmt->execute()) {
                throw new Exception('Error creating user account: ' . $stmt->error);
            }
            
            // Commit transaction
            $db->commit();
            
            $success = 'Account created successfully! You can now log in with username: ' . htmlspecialchars($username);
            // Redirect to login page after 2 seconds
            header("refresh:2;url=/login.php");
            
        } catch (Exception $e) {
            // Rollback on error
            $db->rollback();
            $error = $e->getMessage();
        }
    }
}

// Get membership types and prices
$membership_types = [
    1 => ['name' => 'Student', 'price' => 45],
    2 => ['name' => 'Individual', 'price' => 75],
    3 => ['name' => 'Family', 'price' => 125],
    4 => ['name' => 'Patron', 'price' => 250],
    5 => ['name' => 'Benefactor', 'price' => 500]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Become a Member - Museum of Fine Arts</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #e74c3c;
            --accent-color: #3498db;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .registration-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 0 15px;
        }

        .registration-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .registration-header {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .registration-header h2 {
            margin: 0;
            font-weight: 700;
        }

        .registration-body {
            padding: 2rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--primary-color);
        }

        .form-control:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }

        .membership-option {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s;
        }

        .membership-option:hover {
            border-color: var(--accent-color);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .membership-option input[type="radio"]:checked + label {
            background: rgba(52, 152, 219, 0.1);
        }

        .membership-option input[type="radio"] {
            display: none;
        }

        .membership-label {
            cursor: pointer;
            margin: 0;
            display: block;
        }

        .btn-register {
            background: linear-gradient(135deg, var(--accent-color), var(--primary-color));
            border: none;
            padding: 12px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .benefits-list {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .benefits-list h5 {
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .benefits-list li {
            margin-bottom: 0.5rem;
        }

        .alert {
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="registration-container">
        <div class="registration-card">
            <div class="registration-header">
                <h2><i class="bi bi-star-fill"></i> Become a Member</h2>
                <p class="mb-0">Join our community and enjoy exclusive benefits</p>
            </div>
            
            <div class="registration-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
                        <br><small>Redirecting to login page...</small>
                    </div>
                <?php endif; ?>

                <!-- Benefits Section -->
                <div class="benefits-list">
                    <h5><i class="bi bi-gift"></i> Membership Benefits</h5>
                    <ul>
                        <li><i class="bi bi-check-circle-fill text-success"></i> Unlimited free admission</li>
                        <li><i class="bi bi-check-circle-fill text-success"></i> Exclusive exhibition previews</li>
                        <li><i class="bi bi-check-circle-fill text-success"></i> 10% discount at museum shop</li>
                        <li><i class="bi bi-check-circle-fill text-success"></i> Priority event registration</li>
                        <li><i class="bi bi-check-circle-fill text-success"></i> Member-only events and programs</li>
                        <li><i class="bi bi-check-circle-fill text-success"></i> Quarterly newsletter</li>
                    </ul>
                </div>

                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="first_name" required 
                                   value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="last_name" required
                                   value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" name="email" required
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" name="phone"
                               value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                               placeholder="(555) 123-4567">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" name="address" rows="2"
                                  placeholder="Street address, City, State, ZIP"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Membership Type <span class="text-danger">*</span></label>
                        <?php foreach ($membership_types as $type_id => $type_info): ?>
                            <div class="membership-option">
                                <input type="radio" name="membership_type" id="type_<?= $type_id ?>" 
                                       value="<?= $type_id ?>" <?= ($type_id == 2) ? 'checked' : '' ?>>
                                <label class="membership-label" for="type_<?= $type_id ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?= $type_info['name'] ?></strong>
                                            <small class="d-block text-muted">
                                                <?php
                                                switch($type_id) {
                                                    case 1: echo 'For full-time students with valid ID'; break;
                                                    case 2: echo 'Individual membership for one person'; break;
                                                    case 3: echo 'For families with up to 4 children under 18'; break;
                                                    case 4: echo 'Enhanced benefits and VIP events'; break;
                                                    case 5: echo 'Premium tier with all exclusive benefits'; break;
                                                }
                                                ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <strong class="text-primary">$<?= $type_info['price'] ?></strong>
                                            <small class="d-block text-muted">per year</small>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="is_student" name="is_student"
                                   <?= isset($_POST['is_student']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_student">
                                I am a student (Valid student ID required for verification)
                            </label>
                        </div>
                    </div>

                    <hr class="my-4">

                    <h5 class="mb-3"><i class="bi bi-lock"></i> Create Login Credentials</h5>

                    <div class="mb-3">
                        <label class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="username" required
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                               placeholder="Choose a username">
                        <small class="text-muted">You'll use this to log in to your account</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="password" required
                               minlength="8" placeholder="At least 8 characters">
                        <small class="text-muted">Use a strong password with letters, numbers, and symbols</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="confirm_password" required
                               minlength="8">
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="terms" required>
                            <label class="form-check-label" for="terms">
                                I agree to the <a href="#" class="text-primary">Terms of Service</a> and 
                                <a href="#" class="text-primary">Privacy Policy</a>
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-register w-100">
                        <i class="bi bi-check-circle"></i> Create Account & Join
                    </button>

                    <div class="text-center mt-3">
                        <p class="mb-0">Already have an account? 
                            <a href="web/public/login.php" class="text-primary">Login here</a>
                        </p>
                        <a href="index.php" class="text-muted">
                            <i class="bi bi-arrow-left"></i> Back to Home
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add click event to membership options
        document.querySelectorAll('.membership-option').forEach(option => {
            option.addEventListener('click', function() {
                const radio = this.querySelector('input[type="radio"]');
                radio.checked = true;
            });
        });
    </script>
</body>
</html>