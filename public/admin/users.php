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

$page_title = 'System Management';
$db = db();

$success = '';
$error = '';

// Handle department manager assignment
if (isset($_POST['set_manager'])) {
    $department_id = intval($_POST['department_id']);
    $manager_id = !empty($_POST['manager_id']) ? intval($_POST['manager_id']) : null;
    
    // if manager_id is set check if theyre in the same department
    if ($manager_id !== null) {
        $check_stmt = $db->prepare("SELECT department_id FROM STAFF WHERE staff_id = ?");
        $check_stmt->bind_param('i', $manager_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $staff = $check_result->fetch_assoc();
        
        if ($staff['department_id'] != $department_id) {
            $error = "Manager must be a staff member in this department!";
        } else {
            // Update department manager
            $stmt = $db->prepare("UPDATE DEPARTMENT SET manager_id = ? WHERE department_id = ?");
            $stmt->bind_param('ii', $manager_id, $department_id);
            
            if ($stmt->execute()) {
                $success = "Department manager updated successfully!";
                logActivity('department_manager_set', 'DEPARTMENT', $department_id, 
                    "Set manager to staff_id: $manager_id");
            } else {
                $error = "Failed to update manager: " . $db->error;
            }
        }
    } else {
        // Remove manager (set to NULL)
        $stmt = $db->prepare("UPDATE DEPARTMENT SET manager_id = NULL WHERE department_id = ?");
        $stmt->bind_param('i', $department_id);
        
        if ($stmt->execute()) {
            $success = "Department manager removed.";
            logActivity('department_manager_removed', 'DEPARTMENT', $department_id, "Removed department manager");
        } else {
            $error = "Failed to remove manager: " . $db->error;
        }
    }
}

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

// Handle add new user auto creates linked records
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
            
            // Hash password using SHA256
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
                
                $success = "User created successfully!";
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

// Handle edit user
if (isset($_POST['edit_user'])) {
    $user_id = intval($_POST['user_id']);
    $email = trim($_POST['email'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if (empty($email)) {
        $error = 'Email is required.';
    } else {
        try {
            // Start transaction
            $db->begin_transaction();
            
            // Get current user info
            $user_info_stmt = $db->prepare("SELECT user_type, linked_id FROM USER_ACCOUNT WHERE user_id = ?");
            $user_info_stmt->bind_param('i', $user_id);
            $user_info_stmt->execute();
            $user_info_result = $user_info_stmt->get_result();
            $user_info = $user_info_result->fetch_assoc();
            
            if (!$user_info) {
                throw new Exception('User not found.');
            }
            
            // Update USER_ACCOUNT
            $stmt = $db->prepare("UPDATE USER_ACCOUNT SET email = ?, is_active = ? WHERE user_id = ?");
            $stmt->bind_param('sii', $email, $is_active, $user_id);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to update user account: ' . $db->error);
            }
            
            // Update linked records based on user type
            if ($user_info['user_type'] === 'member' && $user_info['linked_id']) {
                $first_name = trim($_POST['first_name'] ?? '');
                $last_name = trim($_POST['last_name'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $address = trim($_POST['address'] ?? '');
                
                $stmt = $db->prepare("UPDATE MEMBER SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ? WHERE member_id = ?");
                $stmt->bind_param('sssssi', $first_name, $last_name, $email, $phone, $address, $user_info['linked_id']);
                
                if (!$stmt->execute()) {
                    throw new Exception('Failed to update member record: ' . $db->error);
                }
                
            } elseif (in_array($user_info['user_type'], ['curator', 'shop_staff', 'event_staff', 'admin']) && $user_info['linked_id']) {
                $first_name = trim($_POST['first_name'] ?? '');
                $last_name = trim($_POST['last_name'] ?? '');
                $full_name = $first_name . ' ' . $last_name;
                $title = trim($_POST['job_title'] ?? '');
                $department_id = intval($_POST['department_id'] ?? 0);
                
                $stmt = $db->prepare("UPDATE STAFF SET name = ?, email = ?, title = ?, department_id = ? WHERE staff_id = ?");
                $stmt->bind_param('sssii', $full_name, $email, $title, $department_id, $user_info['linked_id']);
                
                if (!$stmt->execute()) {
                    throw new Exception('Failed to update staff record: ' . $db->error);
                }
            }
            
            // Commit transaction
            $db->commit();
            $success = "User updated successfully!";
            logActivity('user_updated', 'USER_ACCOUNT', $user_id, "Updated user account and linked record");
            
        } catch (Exception $e) {
            $db->rollback();
            $error = 'Error updating user: ' . $e->getMessage();
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

// Get all departments with manager info
$departments_query = "
    SELECT 
        d.*,
        s.name as manager_name,
        s.email as manager_email,
        s.title as manager_title,
        (SELECT COUNT(*) FROM STAFF WHERE department_id = d.department_id) as staff_count
    FROM DEPARTMENT d
    LEFT JOIN STAFF s ON d.manager_id = s.staff_id
    ORDER BY d.department_name
";

$departments = $db->query($departments_query)->fetch_all(MYSQLI_ASSOC);

// Get all staff grouped by department for manager selection
$staff_by_dept = [];
$all_staff_query = "
    SELECT staff_id, name, title, department_id, email
    FROM STAFF
    ORDER BY name
";
$all_staff = $db->query($all_staff_query)->fetch_all(MYSQLI_ASSOC);

foreach ($all_staff as $staff) {
    $dept_id = $staff['department_id'];
    if (!isset($staff_by_dept[$dept_id])) {
        $staff_by_dept[$dept_id] = [];
    }
    $staff_by_dept[$dept_id][] = $staff;
}

// Get staff for supervisor dropdown for user creation
$supervisors_result = $db->query("SELECT staff_id, name, title FROM STAFF ORDER BY name");
$supervisors = $supervisors_result ? $supervisors_result->fetch_all(MYSQLI_ASSOC) : [];

include __DIR__ . '/../templates/layout_header.php';
?>

<style>
.stat-card {
    transition: transform 0.2s;
}
.stat-card:hover {
    transform: translateY(-2px);
}
.dept-card {
    transition: transform 0.2s, box-shadow 0.2s;
}
.dept-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.manager-badge {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 15px;
    border-radius: 8px;
}
.nav-tabs .nav-link {
    color: #666;
}
.nav-tabs .nav-link.active {
    color: #0d6efd;
    font-weight: 600;
}
</style>

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

<!-- Page Header -->
<div class="mb-4">
    <h2><i class="bi bi-gear"></i> System Management</h2>
    <p class="text-muted">Manage users, staff, and departments</p>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs mb-4" id="managementTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="users-tab" data-bs-toggle="tab" data-bs-target="#users" type="button" role="tab">
            <i class="bi bi-people"></i> Users & Accounts
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="departments-tab" data-bs-toggle="tab" data-bs-target="#departments" type="button" role="tab">
            <i class="bi bi-building"></i> Departments
        </button>
    </li>
</ul>

<div class="tab-content" id="managementTabsContent">
    <!-- users tab -->
    <div class="tab-pane fade show active" id="users" role="tabpanel">
        <!-- Add New User Button -->
        <div class="mb-4">
            <button class="btn btn-primary" onclick="resetUserForm()" data-bs-toggle="modal" data-bs-target="#createUserModal">
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
                                <h6 class="text-uppercase mb-1">Staff</h6>
                                <h2 class="mb-0"><?= count(array_filter($users, fn($u) => in_array($u['user_type'], ['admin', 'curator', 'shop_staff', 'event_staff']))) ?></h2>
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
                                    <div class="d-flex gap-1">
                                        <button type="button" class="btn btn-sm btn-primary" onclick="editUser(<?= $user['user_id'] ?>)" title="Edit User">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-warning" onclick="resetPassword(<?= $user['user_id'] ?>, '<?= htmlspecialchars($user['username'], ENT_QUOTES) ?>')" title="Reset Password">
                                            <i class="bi bi-key"></i>
                                        </button>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                            <input type="hidden" name="is_active" value="<?= $user['is_active'] ?>">
                                            <button type="submit" name="toggle_active" class="btn btn-sm btn-<?= $user['is_active'] ? 'danger' : 'success' ?>" 
                                                    onclick="return confirm('<?= $user['is_active'] ? 'Deactivate' : 'Activate' ?> this user?')"
                                                    title="<?= $user['is_active'] ? 'Deactivate' : 'Activate' ?> User">
                                                <i class="bi bi-<?= $user['is_active'] ? 'x-circle' : 'check-circle' ?>"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- departments tab -->
    <div class="tab-pane fade" id="departments" role="tabpanel">
        <!-- Summary Statistics -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-building"></i> Total Departments</h5>
                        <h2><?= count($departments) ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-person-badge"></i> With Managers</h5>
                        <h2><?= count(array_filter($departments, fn($d) => $d['manager_id'] !== null)) ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-people"></i> Total Staff</h5>
                        <h2><?= array_sum(array_column($departments, 'staff_count')) ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Departments Grid -->
        <div class="row">
            <?php foreach ($departments as $dept): ?>
                <div class="col-md-6 mb-4">
                    <div class="card h-100 dept-card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="bi bi-building"></i> <?= htmlspecialchars($dept['department_name']) ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <p class="text-muted mb-2">
                                    <i class="bi bi-geo-alt"></i> <strong>Location:</strong> 
                                    <?= htmlspecialchars($dept['location'] ?? 'N/A') ?>
                                </p>
                                <p class="mb-0">
                                    <i class="bi bi-people"></i> <strong>Staff Count:</strong> 
                                    <span class="badge bg-secondary"><?= $dept['staff_count'] ?></span>
                                </p>
                            </div>
                            
                            <hr>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">
                                    <i class="bi bi-person-badge"></i> Department Manager
                                </label>
                                <?php if ($dept['manager_id']): ?>
                                    <div class="manager-badge">
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-person-circle fs-2 me-3"></i>
                                            <div>
                                                <div class="fw-bold"><?= htmlspecialchars($dept['manager_name']) ?></div>
                                                <?php if ($dept['manager_title']): ?>
                                                    <small><?= htmlspecialchars($dept['manager_title']) ?></small><br>
                                                <?php endif; ?>
                                                <small><i class="bi bi-envelope"></i> <?= htmlspecialchars($dept['manager_email']) ?></small>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-warning mb-2">
                                        <i class="bi bi-exclamation-triangle"></i> No manager assigned
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <button class="btn btn-primary btn-sm w-100" 
                                    onclick="showManagerModal(<?= $dept['department_id'] ?>, '<?= htmlspecialchars($dept['department_name'], ENT_QUOTES) ?>', <?= $dept['manager_id'] ?? 'null' ?>)">
                                <i class="bi bi-pencil"></i> <?= $dept['manager_id'] ? 'Change' : 'Assign' ?> Manager
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Create User Modal -->
<div class="modal fade" id="createUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" onsubmit="return validateUserForm()">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-plus"></i> Create New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Account Information -->
                    <h6 class="border-bottom pb-2 mb-3">Account Information</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Username *</label>
                            <input type="text" class="form-control" name="username" id="username" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password *</label>
                            <input type="password" class="form-control" name="password" id="password" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email" id="email" required>
                        </div>
                        <div class="col-md-6 mb-3">
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
                    
                    <!-- Personal Information (conditional) -->
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
                                <input type="tel" class="form-control" name="phone" id="phone" placeholder="(123) 456-7890">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" id="address" rows="2"></textarea>
                        </div>
                    </div>
                    
                    <!-- Staff-specific fields -->
                    <div id="staff_fields" style="display: none;">
                        <h6 class="border-bottom pb-2 mb-3 mt-4">Staff Information</h6>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Department *</label>
                                <select class="form-select" name="department_id" id="department_id">
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
                                <input type="text" class="form-control" name="ssn" id="ssn" placeholder="123-45-6789" pattern="\d{3}-?\d{2}-?\d{4}">
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

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="editUserForm">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" class="form-control" name="email" id="edit_email" required>
                    </div>
                    
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="is_active" id="edit_is_active">
                        <label class="form-check-label" for="edit_is_active">Account Active</label>
                    </div>
                    
                    <div id="edit_personal_info" style="display: none;">
                        <h6 class="border-bottom pb-2 mb-3">Personal Information</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">First Name</label>
                                <input type="text" class="form-control" name="first_name" id="edit_first_name">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Name</label>
                                <input type="text" class="form-control" name="last_name" id="edit_last_name">
                            </div>
                        </div>
                    </div>
                    
                    <div id="edit_member_info" style="display: none;">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone</label>
                                <input type="tel" class="form-control" name="phone" id="edit_phone">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" id="edit_address" rows="2"></textarea>
                        </div>
                    </div>
                    
                    <div id="edit_staff_info" style="display: none;">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Job Title</label>
                                <input type="text" class="form-control" name="job_title" id="edit_job_title">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Department</label>
                                <select class="form-select" name="department_id" id="edit_department_id">
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?= $dept['department_id'] ?>">
                                            <?= htmlspecialchars($dept['department_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_user" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Save Changes
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

<!-- Set Manager Modal -->
<div class="modal fade" id="managerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="set_manager" value="1">
                <input type="hidden" name="department_id" id="modal_department_id">
                
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-person-badge"></i> Set Department Manager
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> <strong>Department:</strong> 
                        <span id="modal_department_name"></span>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Select Manager</label>
                        <select class="form-select" name="manager_id" id="modal_manager_select" required>
                            <option value="">-- No Manager --</option>
                        </select>
                        <small class="text-muted">
                            <i class="bi bi-info-circle"></i> Only staff members from this department are shown
                        </small>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="bi bi-shield-exclamation"></i> 
                        <strong>Note:</strong> The manager must be a staff member assigned to this department.
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Set Manager
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Pass php data to javascript
const staffByDept = <?= json_encode($staff_by_dept) ?>;

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

function resetUserForm() {
    document.getElementById('user_type').value = '';
    updateFormFields();
}

function validateUserForm() {
    const userType = document.getElementById('user_type').value;
    if (!userType) {
        alert('Please select a user role');
        return false;
    }
    return true;
}

function resetPassword(userId, username) {
    document.getElementById('reset_user_id').value = userId;
    document.getElementById('reset_username').textContent = username;
    document.getElementById('new_password').value = '';
    
    new bootstrap.Modal(document.getElementById('resetPasswordModal')).show();
}

async function editUser(userId) {
    try {
        const response = await fetch(`/admin/get_user_details.php?user_id=${userId}`);
        const data = await response.json();
        
        if (data.error) {
            alert('Error loading user data: ' + data.error);
            return;
        }
        
        // Populate form
        document.getElementById('edit_user_id').value = data.user_id;
        document.getElementById('edit_email').value = data.email;
        document.getElementById('edit_is_active').checked = data.is_active == 1;
        
        // Show/hide sections based on user type
        const editPersonalInfo = document.getElementById('edit_personal_info');
        const editMemberInfo = document.getElementById('edit_member_info');
        const editStaffInfo = document.getElementById('edit_staff_info');
        
        editPersonalInfo.style.display = 'none';
        editMemberInfo.style.display = 'none';
        editStaffInfo.style.display = 'none';
        
        if (data.user_type === 'member') {
            editPersonalInfo.style.display = 'block';
            editMemberInfo.style.display = 'block';
            document.getElementById('edit_first_name').value = data.first_name || '';
            document.getElementById('edit_last_name').value = data.last_name || '';
            document.getElementById('edit_phone').value = data.phone || '';
            document.getElementById('edit_address').value = data.address || '';
        } else if (['curator', 'shop_staff', 'event_staff', 'admin'].includes(data.user_type)) {
            editPersonalInfo.style.display = 'block';
            editStaffInfo.style.display = 'block';
            document.getElementById('edit_first_name').value = data.first_name || '';
            document.getElementById('edit_last_name').value = data.last_name || '';
            document.getElementById('edit_job_title').value = data.title || '';
            document.getElementById('edit_department_id').value = data.department_id || '';
        }
        
        new bootstrap.Modal(document.getElementById('editUserModal')).show();
    } catch (error) {
        alert('Error loading user data: ' + error.message);
    }
}

function showManagerModal(deptId, deptName, currentManagerId) {
    document.getElementById('modal_department_id').value = deptId;
    document.getElementById('modal_department_name').textContent = deptName;
    
    // Populate manager select with staff from this department
    const select = document.getElementById('modal_manager_select');
    select.innerHTML = '<option value="">-- No Manager --</option>';
    
    const deptStaff = staffByDept[deptId] || [];
    
    if (deptStaff.length === 0) {
        const option = document.createElement('option');
        option.disabled = true;
        option.textContent = 'No staff members in this department';
        select.appendChild(option);
    } else {
        deptStaff.forEach(staff => {
            const option = document.createElement('option');
            option.value = staff.staff_id;
            option.textContent = staff.name + (staff.title ? ' - ' + staff.title : '');
            if (staff.staff_id == currentManagerId) {
                option.selected = true;
            }
            select.appendChild(option);
        });
    }
    
    new bootstrap.Modal(document.getElementById('managerModal')).show();
}
</script>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>