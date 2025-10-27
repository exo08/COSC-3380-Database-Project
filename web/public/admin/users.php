<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/permissions.php';

// Only admins can manage users
if ($_SESSION['user_type'] !== 'admin') {
    header('Location: /dashboard.php?error=access_denied');
    exit;
}

$page_title = 'Manage Users';
$db = db();

$success = '';
$error = '';

// Handle activate/deactivate user
if (isset($_POST['toggle_active'])) {
    $user_id = intval($_POST['user_id']);
    $is_active = intval($_POST['is_active']);
    
    $new_status = $is_active ? 0 : 1; // Toggle
    
    if ($db->query("UPDATE USER_ACCOUNT SET is_active = $new_status WHERE user_id = $user_id")) {
        $status_text = $new_status ? 'activated' : 'deactivated';
        $success = "User $status_text successfully!";
        logActivity('user_status_changed', 'USER_ACCOUNT', $user_id, "User $status_text");
    } else {
        $error = 'Error updating user status: ' . $db->error;
    }
}

// Handle add new user - auto-creates linked records
if (isset($_POST['create_user'])) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $user_type = $_POST['user_type'] ?? '';
    
    // Role-specific fields
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    if (empty($username) || empty($email) || empty($password) || empty($user_type)) {
        $error = 'Username, email, password, and role are required.';
    } elseif (empty($first_name) || empty($last_name)) {
        $error = 'First name and last name are required.';
    } else {
        try {
            // Start transaction
            $db->begin_transaction();
            
            // Hash password using SHA256 (matching your login.php)
            $password_hash = hash('sha256', $password);
            $linked_id = null;
            
            // submit handler (send to server)
            // Create linked record based on user type
            if ($user_type === 'member') {
                // Create MEMBER record
                $membership_type = intval($_POST['membership_type'] ?? 1);
                $is_student = isset($_POST['is_student']) ? 1 : 0;
                $start_date = date('Y-m-d');
                $expiration_date = date('Y-m-d', strtotime('+1 year'));
                $auto_renew = isset($_POST['auto_renew']) ? 1 : 0;
                
                $stmt = $db->prepare("CALL CreateMember(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, @new_member_id)");
                $stmt->bind_param('sssssiissi', 
                    $first_name, $last_name, $email, $phone, $address,
                    $membership_type, $is_student, $start_date, $expiration_date, $auto_renew
                );
                
                if ($stmt->execute()) {
                    $result = $db->query("SELECT @new_member_id as member_id");
                    $new_record = $result->fetch_assoc();
                    $linked_id = $new_record['member_id'];
                    $stmt->close();
                } else {
                    throw new Exception("Failed to create member: " . $db->error);
                }
                
            } elseif (in_array($user_type, ['curator', 'shop_staff', 'event_staff', 'admin'])) {
                // Create STAFF record
                $department_id = intval($_POST['department_id'] ?? 1);
                $title = $_POST['job_title'] ?? '';
                if($user_type === 'admin' && trim($title) === ''){ // Default title for admin
                    $title = 'Administrator';
                }
                $hire_date = $_POST['hire_date'] ?? date('Y-m-d');
                if(empty($_POST['ssn'])){
                    throw new Exception('SSN is required for staff accounts.');
                }
                $ssn = preg_replace('/\D/', '', $_POST['ssn']); // remove non-digit characters
                $supervisor_id = !empty($_POST['supervisor_id']) ? intval($_POST['supervisor_id']) : null;
                $full_name = $first_name . ' ' . $last_name;
                
                $stmt = $db->prepare("CALL CreateStaff(?, ?, ?, ?, ?, ?, ?, @new_staff_id)");
                $stmt->bind_param('iissssi',
                    $ssn, $department_id, $full_name, $email, $title, $hire_date, $supervisor_id
                );
                
                if ($stmt->execute()) {
                    $result = $db->query("SELECT @new_staff_id as staff_id");
                    $new_record = $result->fetch_assoc();
                    $linked_id = $new_record['staff_id'];
                    $stmt->close();
                } else {
                    throw new Exception("Failed to create staff: " . $db->error);
                }
            }
            
            // Create USER_ACCOUNT record using CreateUser procedure
            $stmt = $db->prepare("CALL CreateUser(?, ?, ?, ?, ?, @new_user_id)");
            $stmt->bind_param('ssssi', $username, $email, $password_hash, $user_type, $linked_id);
            
            if ($stmt->execute()) {
                $result = $db->query("SELECT @new_user_id as user_id");
                $new_user = $result->fetch_assoc();
                $new_user_id = $new_user['user_id'];
                $stmt->close();
                
                // Commit transaction
                $db->commit();
                
                $success = "User created successfully! (ID: $new_user_id" . ($linked_id ? ", Linked ID: $linked_id" : "") . ")";
                logActivity('user_created', 'USER_ACCOUNT', $new_user_id, "Created user: $username (role: $user_type)");
            } else {
                throw new Exception($db->error);
            }
            
        } catch (Exception $e) {
            $db->rollback();
            $error = 'Error creating user: ' . $e->getMessage();
        }
    }
}

// Handle reset password
if (isset($_POST['reset_password'])) {
    $user_id = intval($_POST['user_id']);
    $new_password = $_POST['new_password'] ?? '';
    
    if (empty($new_password)) {
        $error = 'Password cannot be empty.';
    } else {
        $password_hash = hash('sha256', $new_password);
        
        $stmt = $db->prepare("UPDATE USER_ACCOUNT SET password_hash = ? WHERE user_id = ?");
        $stmt->bind_param('si', $password_hash, $user_id);
        
        if ($stmt->execute()) {
            $success = 'Password reset successfully!';
            logActivity('password_reset', 'USER_ACCOUNT', $user_id, "Admin reset password for user ID: $user_id");
        } else {
            $error = 'Error resetting password: ' . $db->error;
        }
    }
}

// Get all users with linked information
$users_query = "
    SELECT 
        u.*,
        CASE 
            WHEN u.user_type = 'member' THEN CONCAT(m.first_name, ' ', m.last_name)
            WHEN u.user_type IN ('curator', 'shop_staff', 'event_staff', 'admin') THEN s.name
            ELSE NULL
        END as linked_name,
        CASE
            WHEN u.user_type = 'member' THEN m.email
            WHEN u.user_type IN ('curator', 'shop_staff', 'event_staff', 'admin') THEN s.email
            ELSE NULL
        END as linked_email
    FROM USER_ACCOUNT u
    LEFT JOIN MEMBER m ON u.user_type = 'member' AND u.linked_id = m.member_id
    LEFT JOIN STAFF s ON u.user_type IN ('curator', 'shop_staff', 'event_staff', 'admin') AND u.linked_id = s.staff_id
    ORDER BY u.created_at DESC
";

$users_result = $db->query($users_query);
$users = $users_result ? $users_result->fetch_all(MYSQLI_ASSOC) : [];

// Get departments for staff creation
$departments_result = $db->query("SELECT department_id, department_name FROM DEPARTMENT ORDER BY department_name");
$departments = $departments_result ? $departments_result->fetch_all(MYSQLI_ASSOC) : [];

// Get staff for supervisor dropdown
$supervisors_result = $db->query("SELECT staff_id, name, title FROM STAFF ORDER BY name");
$supervisors = $supervisors_result ? $supervisors_result->fetch_all(MYSQLI_ASSOC) : [];

include __DIR__ . '/../templates/layout_header.php';
?>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Add New User Button -->
<div class="mb-4">
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
        <i class="bi bi-person-plus"></i> Create New User
    </button>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card stat-card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-1">Total Users</h6>
                        <h2 class="mb-0"><?= count($users) ?></h2>
                    </div>
                    <i class="bi bi-people fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-1">Active Users</h6>
                        <h2 class="mb-0"><?= count(array_filter($users, fn($u) => $u['is_active'] == 1)) ?></h2>
                    </div>
                    <i class="bi bi-check-circle fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card bg-warning text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-1">Staff Accounts</h6>
                        <h2 class="mb-0"><?= count(array_filter($users, fn($u) => in_array($u['user_type'], ['curator', 'shop_staff', 'event_staff']))) ?></h2>
                    </div>
                    <i class="bi bi-person-badge fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-1">Members</h6>
                        <h2 class="mb-0"><?= count(array_filter($users, fn($u) => $u['user_type'] == 'member')) ?></h2>
                    </div>
                    <i class="bi bi-star fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Users Table -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-people"></i> All Users (<?= count($users) ?>)</h5>
    </div>
    <div class="card-body">
        <?php if (empty($users)): ?>
            <p class="text-muted">No users found.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Linked To</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= htmlspecialchars($user['user_id']) ?></td>
                        <td><strong><?= htmlspecialchars($user['username']) ?></strong></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td>
                            <?php
                            $role_badges = [
                                'admin' => 'danger',
                                'curator' => 'primary',
                                'shop_staff' => 'success',
                                'event_staff' => 'info',
                                'member' => 'warning'
                            ];
                            $badge_color = $role_badges[$user['user_type']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?= $badge_color ?>"><?= getRoleDisplayName($user['user_type']) ?></span>
                        </td>
                        <td>
                            <?php if ($user['linked_name']): ?>
                                <small>
                                    <?= htmlspecialchars($user['linked_name']) ?><br>
                                    <span class="text-muted"><?= htmlspecialchars($user['linked_email']) ?></span>
                                </small>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($user['is_active']): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($user['last_login']): ?>
                                <small><?= date('M d, Y g:i A', strtotime($user['last_login'])) ?></small>
                            <?php else: ?>
                                <span class="text-muted">Never</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <!-- Toggle Active/Inactive -->
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                <input type="hidden" name="is_active" value="<?= $user['is_active'] ?>">
                                <button type="submit" name="toggle_active" class="btn btn-sm btn-outline-<?= $user['is_active'] ? 'warning' : 'success' ?>" title="<?= $user['is_active'] ? 'Deactivate' : 'Activate' ?>">
                                    <i class="bi bi-<?= $user['is_active'] ? 'x-circle' : 'check-circle' ?>"></i>
                                </button>
                            </form>
                            
                            <!-- Reset Password -->
                            <button class="btn btn-sm btn-outline-primary" onclick='resetPassword(<?= $user['user_id'] ?>, "<?= htmlspecialchars($user['username'], ENT_QUOTES) ?>")' title="Reset Password">
                                <i class="bi bi-key"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create User Modal -->
<div class="modal fade" id="createUserModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Create New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> <strong>Note:</strong> Fill in all required information. The system will automatically create the necessary records (Staff or Member) and link them to the user account.
                    </div>
                    
                    <h6 class="border-bottom pb-2 mb-3">Account Information</h6>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Username *</label>
                            <input type="text" class="form-control" name="username" id="username" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email" id="email" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Password *</label>
                            <input type="password" class="form-control" name="password" id="password" required>
                            <small class="text-muted">Will be hashed with SHA256</small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">User Role *</label>
                            <select class="form-select" name="user_type" id="user_type" required onchange="updateFormFields()">
                                <option value="">Select Role...</option>
                                <option value="admin">Administrator</option>
                                <option value="curator">Curator</option>
                                <option value="shop_staff">Shop Staff</option>
                                <option value="event_staff">Event Staff</option>
                                <option value="member">Member</option>
                            </select>
                        </div>
                    </div>
                    
                    <div id="personal_info_section" style="display: none;">
                        <h6 class="border-bottom pb-2 mb-3 mt-4">Personal Information</h6>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">First Name *</label>
                                <input type="text" class="form-control" name="first_name" id="first_name">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Last Name *</label>
                                <input type="text" class="form-control" name="last_name" id="last_name">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Phone</label>
                                <input type="tel" class="form-control" name="phone" id="phone" placeholder="123-456-7890">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Address</label>
                                <input type="text" class="form-control" name="address" id="address" placeholder="123 Main St, City, State ZIP">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Staff-specific fields -->
                    <div id="staff_fields" style="display: none;">
                        <h6 class="border-bottom pb-2 mb-3 mt-4">Staff Information</h6>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Department *</label>
                                <select class="form-select" name="department_id" id="department_id">
                                    <option value="">Select Department...</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?= $dept['department_id'] ?>">
                                            <?= htmlspecialchars($dept['department_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Job Title</label>
                                <input type="text" class="form-control" name="job_title" id="job_title" placeholder="e.g., Senior Curator">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Hire Date</label>
                                <input type="date" class="form-control" name="hire_date" id="hire_date" value="<?= date('Y-m-d') ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">SSN *</label>
                                <input type="text" class="form-control" name="ssn" id="ssn" placeholder="123-45-6789" required pattern="\d{3}-?\d{2}-?\d{4}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Supervisor (Optional)</label>
                                <select class="form-select" name="supervisor_id" id="supervisor_id">
                                    <option value="">No Supervisor</option>
                                    <?php foreach ($supervisors as $sup): ?>
                                        <option value="<?= $sup['staff_id'] ?>">
                                            <?= htmlspecialchars($sup['name']) ?>
                                            <?= !empty($sup['title']) ? '(' . htmlspecialchars($sup['title']) . ')' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Member-specific fields -->
                    <div id="member_fields" style="display: none;">
                        <h6 class="border-bottom pb-2 mb-3 mt-4">Membership Information</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Membership Type</label>
                                <select class="form-select" name="membership_type" id="membership_type">
                                    <option value="1">Individual</option>
                                    <option value="2">Family</option>
                                    <option value="3">Student</option>
                                    <option value="4">Senior</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check form-switch mt-4">
                                    <input class="form-check-input" type="checkbox" name="is_student" id="is_student">
                                    <label class="form-check-label" for="is_student">Student Status</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="auto_renew" id="auto_renew" checked>
                                    <label class="form-check-label" for="auto_renew">Auto-Renew Membership</label>
                                </div>
                            </div>
                        </div>
                        <div class="alert alert-success">
                            <i class="bi bi-calendar-check"></i> Membership will start today and expire in 1 year.
                        </div>
                    </div>
                    
                    <!-- Admin note -->
                    <div class="alert alert-warning" id="admin_note" style="display: none;">
                        <i class="bi bi-shield-exclamation"></i> <strong>Administrator Account:</strong> Admin accounts have full system access.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="create_user" class="btn btn-primary">
                        <i class="bi bi-person-plus"></i> Create User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Reset Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="reset_user_id">
                    
                    <p>Reset password for user: <strong id="reset_username"></strong></p>
                    
                    <div class="mb-3">
                        <label class="form-label">New Password *</label>
                        <input type="password" class="form-control" name="new_password" id="new_password" required>
                        <small class="text-muted">Password will be hashed using SHA256.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="reset_password" class="btn btn-danger">
                        <i class="bi bi-key"></i> Reset Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function updateFormFields() {
    const userType = document.getElementById('user_type').value;
    const personalInfoSection = document.getElementById('personal_info_section');
    const staffFields = document.getElementById('staff_fields');
    const memberFields = document.getElementById('member_fields');
    const adminNote = document.getElementById('admin_note');
    
    // Hide all sections first
    personalInfoSection.style.display = 'none';
    staffFields.style.display = 'none';
    memberFields.style.display = 'none';
    adminNote.style.display = 'none';
    
    // Clear and remove required attributes
    const personalInputs = personalInfoSection.querySelectorAll('input');
    const staffInputs = staffFields.querySelectorAll('input, select');
    
    personalInputs.forEach(input => {
        input.removeAttribute('required');
    });
    staffInputs.forEach(input => {
        input.removeAttribute('required');
    });
    
    // Show appropriate sections based on role
    if (userType === 'member') {
        personalInfoSection.style.display = 'block';
        memberFields.style.display = 'block';
        // Set required fields for member
        document.getElementById('first_name').setAttribute('required', 'required');
        document.getElementById('last_name').setAttribute('required', 'required');
    } else if (['curator', 'shop_staff', 'event_staff', 'admin'].includes(userType)) {
        adminNote.style.display = userType === 'admin' ? 'block' : 'none';
        personalInfoSection.style.display = 'block';
        staffFields.style.display = 'block';
        // Set required fields for staff/admin
        document.getElementById('first_name').setAttribute('required', 'required');
        document.getElementById('last_name').setAttribute('required', 'required');
        document.getElementById('department_id').setAttribute('required', 'required');
        document.getElementById('ssn').setAttribute('required', 'required');
    }
}

function resetPassword(userId, username) {
    document.getElementById('reset_user_id').value = userId;
    document.getElementById('reset_username').textContent = username;
    document.getElementById('new_password').value = '';
    
    new bootstrap.Modal(document.getElementById('resetPasswordModal')).show();
}
</script>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>