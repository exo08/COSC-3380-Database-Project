<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../app/session.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/permissions.php';

// Require member permission
if ($_SESSION['user_type'] !== 'member') {
    header('Location: /dashboard.php?error=access_denied');
    exit;
}

$page_title = 'My Settings';
$db = db();
$success = '';
$error = '';

// Get member ID from session
$user_id = $_SESSION['user_id'];
$linked_id = $_SESSION['linked_id'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->begin_transaction();
        
        if (isset($_POST['action'])) {
            // Update Profile Information
            if ($_POST['action'] === 'update_profile') {
                $first_name = trim($_POST['first_name']);
                $last_name = trim($_POST['last_name']);
                $email = trim($_POST['email']);
                $phone = trim($_POST['phone']);
                $address = trim($_POST['address']);
                
                // Validate required fields
                if (empty($first_name) || empty($last_name) || empty($email)) {
                    throw new Exception("First name, last name, and email are required.");
                }
                
                // Validate email format
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("Invalid email format.");
                }
                
                // Check if email is already taken by another member
                $stmt = $db->prepare("SELECT member_id FROM MEMBER WHERE email = ? AND member_id != ?");
                $stmt->bind_param('si', $email, $linked_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    throw new Exception("This email is already in use by another member.");
                }
                $stmt->close();
                
                // Update member information
                $stmt = $db->prepare("
                    UPDATE MEMBER 
                    SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ?
                    WHERE member_id = ?
                ");
                $stmt->bind_param('sssssi', $first_name, $last_name, $email, $phone, $address, $linked_id);
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to update profile: " . $stmt->error);
                }
                $stmt->close();
                
                // Update session email if changed
                $_SESSION['email'] = $email;
                
                // Update USER_ACCOUNT email as well
                $stmt = $db->prepare("UPDATE USER_ACCOUNT SET email = ? WHERE user_id = ?");
                $stmt->bind_param('si', $email, $user_id);
                $stmt->execute();
                $stmt->close();
                
                logActivity('update', 'MEMBER', $linked_id, "Updated profile information");
                
                $db->commit();
                $success = "Profile updated successfully!";
                
            } 
            // Change Password
            elseif ($_POST['action'] === 'change_password') {
                $current_password = $_POST['current_password'];
                $new_password = $_POST['new_password'];
                $confirm_password = $_POST['confirm_password'];
                
                // Validate required fields
                if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                    throw new Exception("All password fields are required.");
                }
                
                // Verify current password
                $stmt = $db->prepare("SELECT password FROM USER_ACCOUNT WHERE user_id = ?");
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user_data = $result->fetch_assoc();
                $stmt->close();
                
                if (!password_verify($current_password, $user_data['password'])) {
                    throw new Exception("Current password is incorrect.");
                }
                
                // Validate new password
                if (strlen($new_password) < 8) {
                    throw new Exception("New password must be at least 8 characters long.");
                }
                
                if ($new_password !== $confirm_password) {
                    throw new Exception("New passwords do not match.");
                }
                
                // Hash and update new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE USER_ACCOUNT SET password = ? WHERE user_id = ?");
                $stmt->bind_param('si', $hashed_password, $user_id);
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to update password: " . $stmt->error);
                }
                $stmt->close();
                
                logActivity('password_change', 'USER_ACCOUNT', $user_id, "Changed password");
                
                $db->commit();
                $success = "Password changed successfully!";
            }
        }
        
    } catch (Exception $e) {
        $db->rollback();
        $error = $e->getMessage();
    }
}

// Get current member details
$member_query = "
    SELECT m.*, 
           CASE 
               WHEN m.expiration_date < CURDATE() THEN 'Expired'
               WHEN DATEDIFF(m.expiration_date, CURDATE()) <= 30 THEN 'Expiring Soon'
               ELSE 'Active'
           END as membership_status,
           DATEDIFF(m.expiration_date, CURDATE()) as days_until_expiration
    FROM MEMBER m
    WHERE m.member_id = ?
";
$stmt = $db->prepare($member_query);
$stmt->bind_param('i', $linked_id);
$stmt->execute();
$member = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Membership type names
$membership_types = [
    '1' => 'Student',
    '2' => 'Individual', 
    '3' => 'Family',
    '4' => 'Benefactor', 
    '5' => 'Patron', 
];

$membership_type_display = $membership_types[$member['membership_type']] ?? ucfirst($member['membership_type']);

include __DIR__ . '/../templates/layout_header.php';
?>

<style>
.settings-card {
    background: white;
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}

.settings-card h4 {
    color: #667eea;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #e0e0e0;
}

.info-badge {
    display: inline-block;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 500;
}

.info-row {
    padding: 15px 0;
    border-bottom: 1px solid #f0f0f0;
}

.info-row:last-child {
    border-bottom: none;
}

.info-label {
    font-weight: 600;
    color: #6c757d;
    margin-bottom: 5px;
}

.info-value {
    font-size: 1.1rem;
    color: #333;
}

.password-requirements {
    background: #f8f9fa;
    border-left: 4px solid #667eea;
    padding: 15px;
    margin-top: 15px;
    border-radius: 5px;
}

.password-requirements ul {
    margin-bottom: 0;
    padding-left: 20px;
}

.password-requirements li {
    color: #6c757d;
    margin: 5px 0;
}

.btn-profile {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    padding: 12px 30px;
    font-weight: 500;
    transition: transform 0.2s;
}

.btn-profile:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
}

.alert-settings {
    border-radius: 10px;
    border-left: 4px solid;
}
</style>

<!-- Success/Error Messages -->
<?php if ($success): ?>
    <div class="alert alert-success alert-settings alert-dismissible fade show">
        <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-settings alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Page Header -->
<div class="mb-4">
    <h2><i class="bi bi-gear"></i> Account Settings</h2>
    <p class="text-muted">Manage your profile information and account security</p>
</div>

<div class="row">
    <!-- Left Column - Profile & Password -->
    <div class="col-lg-8">
        <!-- Edit Profile Card -->
        <div class="settings-card">
            <h4><i class="bi bi-person"></i> Profile Information</h4>
            
            <form method="POST" id="profileForm">
                <input type="hidden" name="action" value="update_profile">
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">First Name *</label>
                        <input type="text" class="form-control form-control-lg" name="first_name" 
                               value="<?= htmlspecialchars($member['first_name']) ?>" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Last Name *</label>
                        <input type="text" class="form-control form-control-lg" name="last_name" 
                               value="<?= htmlspecialchars($member['last_name']) ?>" required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Email Address *</label>
                    <input type="email" class="form-control form-control-lg" name="email" 
                           value="<?= htmlspecialchars($member['email']) ?>" required>
                    <small class="text-muted">
                        <i class="bi bi-info-circle"></i> Email must be unique. Used for login and communications.
                    </small>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Phone Number</label>
                    <input type="tel" class="form-control form-control-lg" name="phone" 
                           value="<?= htmlspecialchars($member['phone'] ?? '') ?>" 
                           placeholder="(555) 123-4567">
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Address</label>
                    <textarea class="form-control" name="address" rows="3" 
                              placeholder="Street address, City, State, ZIP"><?= htmlspecialchars($member['address'] ?? '') ?></textarea>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-profile">
                        <i class="bi bi-save"></i> Save Changes
                    </button>
                    <button type="reset" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Change Password Card -->
        <div class="settings-card">
            <h4><i class="bi bi-shield-lock"></i> Change Password</h4>
            
            <form method="POST" id="passwordForm">
                <input type="hidden" name="action" value="change_password">
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Current Password *</label>
                    <input type="password" class="form-control form-control-lg" name="current_password" 
                           id="current_password" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">New Password *</label>
                    <input type="password" class="form-control form-control-lg" name="new_password" 
                           id="new_password" required minlength="8">
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Confirm New Password *</label>
                    <input type="password" class="form-control form-control-lg" name="confirm_password" 
                           id="confirm_password" required minlength="8">
                </div>
                
                <div class="password-requirements">
                    <strong><i class="bi bi-info-circle"></i> Password Requirements:</strong>
                    <ul>
                        <li>Must be at least 8 characters long</li>
                        <li>Use a combination of letters, numbers, and symbols for better security</li>
                        <li>Avoid using common words or personal information</li>
                    </ul>
                </div>
                
                <button type="submit" class="btn btn-warning mt-3">
                    <i class="bi bi-key"></i> Change Password
                </button>
            </form>
        </div>
    </div>
    
    <!-- Right Column - Membership Info (Read-Only) -->
    <div class="col-lg-4">
        <!-- Membership Information Card -->
        <div class="settings-card">
            <h4><i class="bi bi-star"></i> Membership Information</h4>
            
            <div class="info-row">
                <div class="info-label">Member ID</div>
                <div class="info-value">
                    <code>#<?= htmlspecialchars($member['member_id']) ?></code>
                </div>
            </div>
            
            <div class="info-row">
                <div class="info-label">Membership Type</div>
                <div class="info-value">
                    <span class="info-badge bg-primary text-white">
                        <?= htmlspecialchars($membership_type_display) ?>
                    </span>
                    <?php if ($member['is_student']): ?>
                        <span class="info-badge bg-info text-white">Student</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="info-row">
                <div class="info-label">Status</div>
                <div class="info-value">
                    <?php
                    $status_colors = [
                        'Active' => 'success',
                        'Expiring Soon' => 'warning',
                        'Expired' => 'danger'
                    ];
                    $status_color = $status_colors[$member['membership_status']] ?? 'secondary';
                    ?>
                    <span class="info-badge bg-<?= $status_color ?> text-white">
                        <i class="bi bi-circle-fill"></i> <?= htmlspecialchars($member['membership_status']) ?>
                    </span>
                </div>
            </div>
            
            <div class="info-row">
                <div class="info-label">Start Date</div>
                <div class="info-value">
                    <?= !empty($member['start_date']) && $member['start_date'] !== '0000-00-00'
                        ? date('F j, Y', strtotime($member['start_date']))
                        : '<span class="text-muted">Not available</span>' ?>
                </div>
            </div>
            
            <div class="info-row">
                <div class="info-label">Expiration Date</div>
                <div class="info-value">
                    <?= date('F j, Y', strtotime($member['expiration_date'])) ?>
                    <?php if ($member['days_until_expiration'] > 0 && $member['days_until_expiration'] <= 30): ?>
                        <br><small class="text-warning">
                            <i class="bi bi-clock"></i> Expires in <?= $member['days_until_expiration'] ?> days
                        </small>
                    <?php elseif ($member['days_until_expiration'] < 0): ?>
                        <br><small class="text-danger">
                            <i class="bi bi-exclamation-triangle"></i> Expired <?= abs($member['days_until_expiration']) ?> days ago
                        </small>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="info-row">
                <div class="info-label">Auto-Renew</div>
                <div class="info-value">
                    <?php if ($member['auto_renew']): ?>
                        <span class="info-badge bg-success text-white">
                            <i class="bi bi-check-circle"></i> Enabled
                        </span>
                    <?php else: ?>
                        <span class="info-badge bg-secondary text-white">
                            <i class="bi bi-x-circle"></i> Disabled
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="mt-4 d-grid gap-2">
                <a href="membership.php" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-repeat"></i> Manage Membership
                </a>
            </div>
        </div>
        
        <!-- Quick Tips Card -->
        <div class="settings-card" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
            <h5><i class="bi bi-lightbulb"></i> Security Tips</h5>
            <ul class="small mb-0">
                <li class="mb-2">Change your password regularly</li>
                <li class="mb-2">Never share your login credentials</li>
                <li class="mb-2">Keep your email address up to date</li>
                <li class="mb-2">Review your account activity periodically</li>
                <li class="mb-2">You'll be automatically logged out after 5 minutes of inactivity</li>
            </ul>
        </div>
    </div>
</div>

<script>
// Password confirmation validation
document.getElementById('passwordForm').addEventListener('submit', function(e) {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (newPassword !== confirmPassword) {
        e.preventDefault();
        alert('New passwords do not match!');
        return false;
    }
    
    if (newPassword.length < 8) {
        e.preventDefault();
        alert('Password must be at least 8 characters long!');
        return false;
    }
});

// Profile form confirmation
document.getElementById('profileForm').addEventListener('submit', function(e) {
    const email = document.querySelector('input[name="email"]').value;
    if (!email.includes('@')) {
        e.preventDefault();
        alert('Please enter a valid email address!');
        return false;
    }
});
</script>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>