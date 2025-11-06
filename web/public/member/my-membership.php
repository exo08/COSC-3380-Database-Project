<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/permissions.php';

// Require member permission
if ($_SESSION['user_type'] !== 'member') {
    header('Location: /dashboard.php?error=access_denied');
    exit;
}

$page_title = 'My Membership';
$db = db();
$success = '';
$error = '';

// Get member_id from session
$user_id = $_SESSION['user_id'];
$member_result = $db->query("SELECT linked_id FROM USER_ACCOUNT WHERE user_id = $user_id");
$member_data = $member_result->fetch_assoc();
$member_id = $member_data['linked_id'];

// Handle membership renewal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'renew') {
    try {
        // Calculate new expiration date (1 year from current expiration or today, whichever is later)
        $current_member = $db->query("SELECT expiration_date FROM MEMBER WHERE member_id = $member_id")->fetch_assoc();
        $current_expiration = strtotime($current_member['expiration_date']);
        $today = strtotime('today');
        
        $start_from = ($current_expiration > $today) ? $current_expiration : $today;
        $new_expiration = date('Y-m-d', strtotime('+1 year', $start_from));
        
        $stmt = $db->prepare("UPDATE MEMBER SET expiration_date = ? WHERE member_id = ?");
        $stmt->bind_param('si', $new_expiration, $member_id);
        
        if ($stmt->execute()) {
            $success = "Membership renewed successfully! Your new expiration date is " . date('F j, Y', strtotime($new_expiration));
            logActivity('membership_renewed', 'MEMBER', $member_id, "Member renewed membership until $new_expiration");
        } else {
            $error = "Error renewing membership: " . $db->error;
        }
        $stmt->close();
    } catch (Exception $e) {
        $error = "Error processing renewal: " . $e->getMessage();
    }
}

// Handle membership upgrade
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upgrade') {
    $new_type = intval($_POST['new_membership_type']);
    
    try {
        $stmt = $db->prepare("UPDATE MEMBER SET membership_type = ? WHERE member_id = ?");
        $stmt->bind_param('ii', $new_type, $member_id);
        
        if ($stmt->execute()) {
            $type_names = [1 => 'Individual', 2 => 'Family', 3 => 'Student', 4 => 'Senior', 5 => 'Patron'];
            $success = "Membership upgraded to {$type_names[$new_type]} successfully!";
            logActivity('membership_upgraded', 'MEMBER', $member_id, "Member upgraded to type $new_type");
        } else {
            $error = "Error upgrading membership: " . $db->error;
        }
        $stmt->close();
    } catch (Exception $e) {
        $error = "Error processing upgrade: " . $e->getMessage();
    }
}

// Handle auto-renew toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_autorenew') {
    $auto_renew = isset($_POST['auto_renew']) ? 1 : 0;
    
    try {
        $stmt = $db->prepare("UPDATE MEMBER SET auto_renew = ? WHERE member_id = ?");
        $stmt->bind_param('ii', $auto_renew, $member_id);
        
        if ($stmt->execute()) {
            $success = "Auto-renew " . ($auto_renew ? "enabled" : "disabled") . " successfully!";
            logActivity('autorenew_changed', 'MEMBER', $member_id, "Auto-renew set to $auto_renew");
        } else {
            $error = "Error updating auto-renew: " . $db->error;
        }
        $stmt->close();
    } catch (Exception $e) {
        $error = "Error updating settings: " . $e->getMessage();
    }
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    
    try {
        $stmt = $db->prepare("UPDATE MEMBER SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ? WHERE member_id = ?");
        $stmt->bind_param('sssssi', $first_name, $last_name, $email, $phone, $address, $member_id);
        
        if ($stmt->execute()) {
            $success = "Profile updated successfully!";
            logActivity('profile_updated', 'MEMBER', $member_id, "Member updated profile information");
        } else {
            $error = "Error updating profile: " . $db->error;
        }
        $stmt->close();
    } catch (Exception $e) {
        $error = "Error updating profile: " . $e->getMessage();
    }
}

// Get member details with additional stats
$member_info = $db->query("
    SELECT m.*,
           DATEDIFF(m.expiration_date, CURDATE()) as days_until_expiration,
           CASE 
               WHEN m.expiration_date < CURDATE() THEN 'Expired'
               WHEN DATEDIFF(m.expiration_date, CURDATE()) <= 30 THEN 'Expiring Soon'
               ELSE 'Active'
           END as membership_status,
           CASE m.membership_type
               WHEN 1 THEN 'Individual'
               WHEN 2 THEN 'Family'
               WHEN 3 THEN 'Student'
               WHEN 4 THEN 'Senior'
               WHEN 5 THEN 'Patron'
               ELSE 'Unknown'
           END as membership_name
    FROM MEMBER m
    WHERE m.member_id = $member_id
")->fetch_assoc();

// Get membership activity stats
$stats = [];
$stats['tickets_purchased'] = $db->query("SELECT COUNT(*) as count FROM TICKET WHERE member_id = $member_id")->fetch_assoc()['count'];
$stats['events_attended'] = $db->query("SELECT COUNT(*) as count FROM TICKET WHERE member_id = $member_id AND checked_in = 1")->fetch_assoc()['count'];
$stats['shop_purchases'] = $db->query("SELECT COUNT(*) as count FROM SALE WHERE member_id = $member_id")->fetch_assoc()['count'];
$stats['total_spent'] = $db->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM SALE WHERE member_id = $member_id")->fetch_assoc()['total'];
$stats['member_since'] = date('F Y', strtotime($member_info['start_date']));

// Get recent activity
$recent_activity = $db->query("
    (SELECT 'ticket' as type, t.purchase_date as date, e.name as description, t.quantity as quantity
     FROM TICKET t
     JOIN EVENT e ON t.event_id = e.event_id
     WHERE t.member_id = $member_id
     ORDER BY t.purchase_date DESC
     LIMIT 5)
    UNION ALL
    (SELECT 'purchase' as type, s.sale_date as date, 'Shop Purchase' as description, NULL as quantity
     FROM SALE s
     WHERE s.member_id = $member_id
     ORDER BY s.sale_date DESC
     LIMIT 5)
    ORDER BY date DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// Define membership tiers and benefits
$membership_tiers = [
    1 => [
        'name' => 'Individual',
        'price' => 50,
        'benefits' => [
            'Free general admission',
            'Member newsletter',
            '10% discount in museum shop',
            'Priority event booking'
        ]
    ],
    2 => [
        'name' => 'Family',
        'price' => 100,
        'benefits' => [
            'Free admission for up to 4 people',
            'Member newsletter',
            '15% discount in museum shop',
            'Priority event booking',
            'Free family events'
        ]
    ],
    3 => [
        'name' => 'Student',
        'price' => 25,
        'benefits' => [
            'Free general admission',
            'Member newsletter',
            '10% discount in museum shop',
            'Priority event booking',
            'Student ID required'
        ]
    ],
    4 => [
        'name' => 'Senior',
        'price' => 40,
        'benefits' => [
            'Free general admission',
            'Member newsletter',
            '10% discount in museum shop',
            'Priority event booking',
            'Senior discount'
        ]
    ],
    5 => [
        'name' => 'Patron',
        'price' => 250,
        'benefits' => [
            'All Family benefits',
            'Exclusive patron events',
            '20% discount in museum shop',
            'Behind-the-scenes tours',
            'Recognition in annual report',
            'Guest passes (4 per year)'
        ]
    ]
];

include __DIR__ . '/../templates/layout_header.php';
?>

<style>
.membership-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 15px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}

.membership-badge {
    background: rgba(255,255,255,0.2);
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 500;
    display: inline-block;
}

.status-badge {
    padding: 5px 15px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.875rem;
}

.status-active {
    background: #d4edda;
    color: #155724;
}

.status-expiring {
    background: #fff3cd;
    color: #856404;
}

.status-expired {
    background: #f8d7da;
    color: #721c24;
}

.stat-card {
    border-radius: 10px;
    padding: 20px;
    transition: transform 0.2s;
}

.stat-card:hover {
    transform: translateY(-3px);
}

.tier-card {
    border: 2px solid #dee2e6;
    border-radius: 10px;
    padding: 20px;
    height: 100%;
    transition: all 0.3s;
}

.tier-card:hover {
    border-color: #667eea;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
}

.tier-card.current {
    border-color: #667eea;
    background: #f8f9ff;
}

.benefit-list {
    list-style: none;
    padding: 0;
}

.benefit-list li {
    padding: 8px 0;
    padding-left: 25px;
    position: relative;
}

.benefit-list li:before {
    content: "✓";
    position: absolute;
    left: 0;
    color: #28a745;
    font-weight: bold;
}

.activity-item {
    border-left: 3px solid #667eea;
    padding-left: 15px;
    margin-bottom: 15px;
}

@media print {
    .no-print {
        display: none !important;
    }
}
</style>

<!-- Success/Error Messages -->
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

<!-- Membership Card -->
<div class="membership-card">
    <div class="row align-items-center">
        <div class="col-md-8">
            <div class="mb-3">
                <span class="membership-badge">
                    <i class="bi bi-star-fill"></i> <?= htmlspecialchars($member_info['membership_name']) ?> Member
                </span>
                <?php
                $status_class = 'status-active';
                if ($member_info['membership_status'] === 'Expired') $status_class = 'status-expired';
                elseif ($member_info['membership_status'] === 'Expiring Soon') $status_class = 'status-expiring';
                ?>
                <span class="status-badge <?= $status_class ?> ms-2">
                    <?= htmlspecialchars($member_info['membership_status']) ?>
                </span>
            </div>
            <h3 class="mb-1"><?= htmlspecialchars($member_info['first_name'] . ' ' . $member_info['last_name']) ?></h3>
            <p class="mb-2 opacity-75">Member ID: <?= $member_id ?></p>
            <div class="row mt-3">
                <div class="col-md-6">
                    <small class="opacity-75">Member Since</small>
                    <p class="mb-0"><strong><?= date('F j, Y', strtotime($member_info['start_date'])) ?></strong></p>
                </div>
                <div class="col-md-6">
                    <small class="opacity-75">Expires</small>
                    <p class="mb-0">
                        <strong><?= date('F j, Y', strtotime($member_info['expiration_date'])) ?></strong>
                        <?php if ($member_info['days_until_expiration'] > 0 && $member_info['days_until_expiration'] <= 30): ?>
                            <br><small>(<?= $member_info['days_until_expiration'] ?> days remaining)</small>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-4 text-end">
            <?php if ($member_info['membership_status'] === 'Expired' || $member_info['membership_status'] === 'Expiring Soon'): ?>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="action" value="renew">
                    <button type="submit" class="btn btn-light btn-lg">
                        <i class="bi bi-arrow-clockwise"></i> Renew Now
                    </button>
                </form>
            <?php endif; ?>
            <button class="btn btn-outline-light mt-2" data-bs-toggle="modal" data-bs-target="#upgradeModal">
                <i class="bi bi-arrow-up-circle"></i> Upgrade
            </button>
        </div>
    </div>
</div>

<!-- Stats Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card stat-card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-1">Events Attended</h6>
                        <h2 class="mb-0"><?= $stats['events_attended'] ?></h2>
                    </div>
                    <i class="bi bi-calendar-check fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card stat-card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-1">Tickets Purchased</h6>
                        <h2 class="mb-0"><?= $stats['tickets_purchased'] ?></h2>
                    </div>
                    <i class="bi bi-ticket-perforated fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card stat-card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-1">Shop Purchases</h6>
                        <h2 class="mb-0"><?= $stats['shop_purchases'] ?></h2>
                    </div>
                    <i class="bi bi-bag fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card stat-card bg-warning text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-1">Total Savings</h6>
                        <h2 class="mb-0">$<?= number_format($stats['total_spent'] * 0.1, 2) ?></h2>
                    </div>
                    <i class="bi bi-piggy-bank fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link active" data-bs-toggle="tab" href="#benefits">
            <i class="bi bi-gift"></i> My Benefits
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#profile">
            <i class="bi bi-person"></i> Profile
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#settings">
            <i class="bi bi-gear"></i> Settings
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#activity">
            <i class="bi bi-clock-history"></i> Activity
        </a>
    </li>
</ul>

<div class="tab-content">
    <!-- Benefits Tab -->
    <div class="tab-pane fade show active" id="benefits">
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-4">
                            <i class="bi bi-gift"></i> Your <?= htmlspecialchars($member_info['membership_name']) ?> Benefits
                        </h5>
                        
                        <ul class="benefit-list">
                            <?php foreach ($membership_tiers[$member_info['membership_type']]['benefits'] as $benefit): ?>
                                <li><?= htmlspecialchars($benefit) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        
                        <hr class="my-4">
                        
                        <h6 class="mb-3">Membership Value</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <p class="text-muted mb-1">Annual Cost</p>
                                <h4 class="text-primary">$<?= number_format($membership_tiers[$member_info['membership_type']]['price'], 2) ?></h4>
                            </div>
                            <div class="col-md-6">
                                <p class="text-muted mb-1">Estimated Savings</p>
                                <h4 class="text-success">$<?= number_format($stats['total_spent'] * 0.1, 2) ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card bg-light">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="bi bi-info-circle"></i> Did You Know?
                        </h6>
                        <p class="small">As a member, you've saved an estimated <strong>$<?= number_format($stats['total_spent'] * 0.1, 2) ?></strong> through your member discount on shop purchases!</p>
                        
                        <?php if ($member_info['auto_renew']): ?>
                            <div class="alert alert-success small mb-0 mt-3">
                                <i class="bi bi-check-circle"></i> Auto-renew is enabled. Your membership will automatically renew before expiration.
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning small mb-0 mt-3">
                                <i class="bi bi-exclamation-triangle"></i> Enable auto-renew to ensure uninterrupted benefits.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Profile Tab -->
    <div class="tab-pane fade" id="profile">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title mb-4">
                    <i class="bi bi-person"></i> Profile Information
                </h5>
                
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">First Name *</label>
                            <input type="text" class="form-control" name="first_name" value="<?= htmlspecialchars($member_info['first_name']) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Last Name *</label>
                            <input type="text" class="form-control" name="last_name" value="<?= htmlspecialchars($member_info['last_name']) ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($member_info['email']) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="tel" class="form-control" name="phone" value="<?= htmlspecialchars($member_info['phone'] ?? '') ?>" placeholder="123-456-7890">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <input type="text" class="form-control" name="address" value="<?= htmlspecialchars($member_info['address'] ?? '') ?>" placeholder="123 Main St, City, State ZIP">
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Your email is used for membership communications and ticket confirmations.
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Update Profile
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Settings Tab -->
    <div
