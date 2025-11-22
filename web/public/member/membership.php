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

$page_title = 'Manage Membership';
$db = db();
$success = '';
$error = '';

// Get member ID from session
$user_id = $_SESSION['user_id'];
$linked_id = $_SESSION['linked_id'];

// Handle membership actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'renew') {
        $membership_type = intval($_POST['membership_type']);
        $payment_method = $_POST['payment_method'];
        // Calculate new expiration date 1 year from current day
        $new_expiration = date('Y-m-d', strtotime('+1 year'));
    
        // Update membership
        $stmt = $db->prepare("
            UPDATE MEMBER 
            SET membership_type = ?, 
                expiration_date = ?,
                start_date = CURDATE()
            WHERE member_id = ?
        ");

        $stmt->bind_param("isi", $membership_type, $new_expiration, $linked_id);
        
        if ($stmt->execute()) {
            // Get membership type name
            $type_names = [1 => 'Student', 2 => 'Individual', 3 => 'Family', 4 => 'Patron', 5 => 'Benefactor'];
            $type_name = $type_names[$membership_type];
            
            $success = "Membership renewed successfully! Your $type_name membership is now active until " . date('F j, Y', strtotime($new_expiration)) . ".";
            logActivity('membership_renewed', 'MEMBER', $linked_id, "Renewed membership: $type_name until $new_expiration");
        } else {
            $error = "Error renewing membership: " . $db->error;
        }
        $stmt->close();
        
    } elseif ($_POST['action'] === 'upgrade') {
        // Calculate new expiration date 1 year from current day
        $new_membership_type = intval($_POST['new_membership_type']);
        $new_expiration = date('Y-m-d', strtotime('+1 year'));
    
        // Update membership
        $stmt = $db->prepare("
            UPDATE MEMBER 
            SET membership_type = ?, 
                expiration_date = ?,
                start_date = CURDATE()
            WHERE member_id = ?
        ");
        $stmt->bind_param("isi", $new_membership_type, $new_expiration, $linked_id);
        
        if ($stmt->execute()) {
            $type_names = [1 => 'Student', 2 => 'Individual', 3 => 'Family', 4 => 'Patron', 5 => 'Benefactor'];
            $type_name = $type_names[$new_membership_type];
            $success = "Membership upgraded to $type_name successfully!";
            logActivity('membership_upgraded', 'MEMBER', $linked_id, "Upgraded membership to: $type_name");
        } else {
            $error = "Error upgrading membership: " . $db->error;
        }
        $stmt->close();
        
    } elseif ($_POST['action'] === 'downgrade') {
        $new_membership_type = intval($_POST['new_membership_type']);
        
        // 1 year from current day
        $new_expiration = date('Y-m-d', strtotime('+1 year'));
        
        // Update membership
        $stmt = $db->prepare("
            UPDATE MEMBER 
            SET membership_type = ?, 
                expiration_date = ?,
                start_date = CURDATE()
            WHERE member_id = ?
        ");
        $stmt->bind_param("isi", $new_membership_type, $new_expiration, $linked_id);
        
        if ($stmt->execute()) {
            $type_names = [1 => 'Student', 2 => 'Individual', 3 => 'Family', 4 => 'Patron', 5 => 'Benefactor'];
            $type_name = $type_names[$new_membership_type];
            $success = "Membership changed to $type_name successfully!";
            logActivity('membership_downgraded', 'MEMBER', $linked_id, "Changed membership to: $type_name");
        } else {
            $error = "Error changing membership: " . $db->error;
        }
        $stmt->close();
        
    } elseif ($_POST['action'] === 'cancel') {
        $reason = trim($_POST['cancellation_reason'] ?? '');
        $confirm = isset($_POST['confirm_cancel']);
        
        if (!$confirm) {
            $error = "Please confirm that you want to cancel your membership.";
        } else {
            // Set expiration to today (effectively canceling) membership cancels at end of day
            // Turn off auto-renew
            $stmt = $db->prepare("
                UPDATE MEMBER 
                SET expiration_date = CURDATE(), 
                    auto_renew = 0 
                WHERE member_id = ?
            ");
            $stmt->bind_param("i", $linked_id);
            
            if ($stmt->execute()) {
                $success = "Membership cancelled. It will expire at the end of today.";
                logActivity('membership_cancelled', 'MEMBER', $linked_id, "Cancelled membership. Reason: " . ($reason ?: 'Not specified'));
            } else {
                $error = "Error cancelling membership: " . $db->error;
            }
            $stmt->close();
        }
    }
}

// Get current member details
$member_query = "
    SELECT m.*, 
           DATEDIFF(m.expiration_date, CURDATE()) as days_until_expiration,
           CASE 
               WHEN m.expiration_date < CURDATE() THEN 'Expired'
               WHEN DATEDIFF(m.expiration_date, CURDATE()) <= 30 THEN 'Expiring Soon'
               ELSE 'Active'
           END as membership_status
    FROM MEMBER m
    WHERE m.member_id = $linked_id
";
$member = $db->query($member_query)->fetch_assoc();

// Membership type details with pricing
$membership_types = [
    1 => ['name' => 'Student', 'price' => 45, 'color' => 'info', 'description' => 'For full-time students with valid ID'],
    2 => ['name' => 'Individual', 'price' => 75, 'color' => 'primary', 'description' => 'Individual membership for one person'],
    3 => ['name' => 'Family', 'price' => 125, 'color' => 'success', 'description' => 'For families with up to 4 children under 18'],
    4 => ['name' => 'Patron', 'price' => 250, 'color' => 'warning', 'description' => 'Enhanced benefits and VIP events'],
    5 => ['name' => 'Benefactor', 'price' => 500, 'color' => 'danger', 'description' => 'Premium tier with all exclusive benefits']
];

$current_type = $membership_types[$member['membership_type']];
$is_expired = $member['membership_status'] === 'Expired';
$is_expiring = $member['membership_status'] === 'Expiring Soon';

include __DIR__ . '/../templates/layout_header.php';
?>

<style>
.pricing-card {
    border: 2px solid #e0e0e0;
    border-radius: 15px;
    padding: 2rem;
    transition: all 0.3s;
    height: 100%;
    position: relative;
    overflow: hidden;
}

.pricing-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    border-color: #667eea;
}

.pricing-card.current {
    border-color: #667eea;
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.05), rgba(118, 75, 162, 0.05));
}

.pricing-card.current::before {
    content: "Current Plan";
    position: absolute;
    top: 0;
    right: 0;
    background: #667eea;
    color: white;
    padding: 0.25rem 1rem;
    font-size: 0.75rem;
    font-weight: 600;
    border-bottom-left-radius: 10px;
}

.pricing-card.recommended {
    border-color: #28a745;
    position: relative;
}

.pricing-card.recommended::after {
    content: "Recommended";
    position: absolute;
    top: 0;
    right: 0;
    background: #28a745;
    color: white;
    padding: 0.25rem 1rem;
    font-size: 0.75rem;
    font-weight: 600;
    border-bottom-left-radius: 10px;
}

.price-tag {
    font-size: 3rem;
    font-weight: 700;
    color: #667eea;
    margin: 1rem 0;
}

.price-tag small {
    font-size: 1rem;
    color: #6c757d;
}

.benefit-item {
    padding: 0.5rem 0;
    border-bottom: 1px solid #f0f0f0;
}

.benefit-item:last-child {
    border-bottom: none;
}

.benefit-item i {
    color: #28a745;
    margin-right: 0.5rem;
}

.status-alert {
    border-radius: 15px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.cancel-section {
    background: #fff3cd;
    border: 2px solid #ffc107;
    border-radius: 15px;
    padding: 2rem;
    margin-top: 3rem;
}

.payment-method-option {
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    padding: 1rem;
    margin-bottom: 1rem;
    cursor: pointer;
    transition: all 0.3s;
}

.payment-method-option:hover {
    border-color: #667eea;
    background: rgba(102, 126, 234, 0.05);
}

.payment-method-option input[type="radio"]:checked + label {
    color: #667eea;
    font-weight: 600;
}

.payment-method-option input[type="radio"] {
    margin-right: 0.5rem;
}

.alert-soft-red {
    background-color: #ffe0e0;
    border: 1px solid #ffb3b3;
    color: #8b0000;
}

.alert-soft-red .bi {
    color: #d32f2f;
}

.alert-soft-red h4 {
    color: #8b0000;
}
</style>

<!-- Success/Error Messages -->
<?php if ($success): ?>
    <?php 
    // check if is a cancellation message
    $is_cancellation = strpos($success, 'cancelled') !== false;
    $alert_class = $is_cancellation ? 'alert-soft-red' : 'alert-success';
    ?>
    <div class="alert <?= $alert_class ?> alert-dismissible fade show">
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
    <h2><i class="bi bi-arrow-repeat"></i> Manage Membership</h2>
    <p class="text-muted">Renew, upgrade, or manage your museum membership</p>
</div>

<!-- Status Alert -->
<?php if (empty($success) && empty($error)): ?>
    <?php if ($is_expired): ?>
        <div class="alert alert-danger status-alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-exclamation-circle fs-1 me-3"></i>
                <div>
                    <h4 class="mb-1">Your Membership Has Expired</h4>
                    <p class="mb-0">Your membership expired <?= abs($member['days_until_expiration']) ?> days ago. Renew now to restore your benefits!</p>
                </div>
            </div>
        </div>
    <?php elseif ($is_expiring): ?>
        <div class="alert alert-warning status-alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-clock fs-1 me-3"></i>
                <div>
                    <h4 class="mb-1">Your Membership is Expiring Soon</h4>
                    <p class="mb-0">Your membership expires on <?= date('F j, Y', strtotime($member['expiration_date'])) ?>. Renew now to avoid interruption!</p>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-success status-alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-check-circle fs-1 me-3"></i>
                <div>
                    <h4 class="mb-1">Your Membership is Active</h4>
                    <p class="mb-0">Current plan: <strong><?= $current_type['name'] ?></strong> | Valid until <?= date('F j, Y', strtotime($member['expiration_date'])) ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<!-- Membership Options -->
<h3 class="mb-4">
    <?php if ($is_expired): ?>
        Renew Your Membership
    <?php else: ?>
        Renew or Upgrade Your Membership
    <?php endif; ?>
</h3>

<div class="row g-4 mb-5">
    <?php foreach ($membership_types as $type_id => $type_info): 
        $is_current = ($type_id == $member['membership_type']);
        $is_upgrade = ($type_id > $member['membership_type']);
        $is_downgrade = ($type_id < $member['membership_type']);
        $is_recommended = ($is_expired && $type_id == 2) || ($is_expiring && $type_id == $member['membership_type']);
    ?>
        <div class="col-md-6 col-lg-4">
            <div class="pricing-card <?= $is_current ? 'current' : '' ?> <?= $is_recommended ? 'recommended' : '' ?>">
                <h4 class="mb-2"><?= htmlspecialchars($type_info['name']) ?></h4>
                <p class="text-muted small"><?= htmlspecialchars($type_info['description']) ?></p>
                
                <div class="price-tag">
                    $<?= number_format($type_info['price']) ?>
                    <small>/year</small>
                </div>
                
                <div class="mb-4">
                    <div class="benefit-item">
                        <i class="bi bi-check-circle-fill"></i> Unlimited free admission
                    </div>
                    <div class="benefit-item">
                        <i class="bi bi-check-circle-fill"></i> Exhibition previews
                    </div>
                    <div class="benefit-item">
                        <i class="bi bi-check-circle-fill"></i> 10% shop discount
                    </div>
                    <div class="benefit-item">
                        <i class="bi bi-check-circle-fill"></i> Priority event registration
                    </div>
                    <?php if ($type_id >= 3): ?>
                        <div class="benefit-item">
                            <i class="bi bi-check-circle-fill"></i> Guest passes
                        </div>
                    <?php endif; ?>
                    <?php if ($type_id >= 4): ?>
                        <div class="benefit-item">
                            <i class="bi bi-check-circle-fill"></i> VIP event access
                        </div>
                        <div class="benefit-item">
                            <i class="bi bi-check-circle-fill"></i> Curator tours
                        </div>
                    <?php endif; ?>
                    <?php if ($type_id == 5): ?>
                        <div class="benefit-item">
                            <i class="bi bi-check-circle-fill"></i> Recognition in publications
                        </div>
                        <div class="benefit-item">
                            <i class="bi bi-check-circle-fill"></i> Private collection viewings
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($is_current && !$is_expired && !$is_expiring): ?>
                    <button class="btn btn-outline-secondary w-100" disabled>
                        <i class="bi bi-check"></i> Current Plan
                    </button>
                <?php elseif ($is_current && ($is_expired || $is_expiring)): ?>
                    <button class="btn btn-primary w-100" data-bs-toggle="modal" 
                            data-bs-target="#renewModal" 
                            onclick="setRenewalType(<?= $type_id ?>, '<?= $type_info['name'] ?>', <?= $type_info['price'] ?>)">
                        <i class="bi bi-arrow-repeat"></i> Renew - $<?= number_format($type_info['price']) ?>
                    </button>
                <?php elseif ($is_upgrade): ?>
                    <button class="btn btn-success w-100" data-bs-toggle="modal" 
                            data-bs-target="#upgradeModal" 
                            onclick="setUpgradeType(<?= $type_id ?>, '<?= $type_info['name'] ?>', <?= $type_info['price'] ?>)">
                        <i class="bi bi-arrow-up-circle"></i> Upgrade - $<?= number_format($type_info['price']) ?>
                    </button>
                <?php elseif ($is_downgrade): ?>
                    <button class="btn btn-warning w-100" data-bs-toggle="modal" 
                            data-bs-target="#upgradeModal" 
                            onclick="setDowngradeType(<?= $type_id ?>, '<?= $type_info['name'] ?>', <?= $type_info['price'] ?>)">
                        <i class="bi bi-arrow-down-circle"></i> Downgrade - $<?= number_format($type_info['price']) ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Cancel Membership Section -->
<div class="cancel-section">
    <h4 class="mb-3"><i class="bi bi-exclamation-triangle"></i> Cancel Membership</h4>
    <p class="mb-3">We're sorry to see you considering leaving. If you cancel your membership:</p>
    <ul class="mb-4">
        <li>You'll lose access to all member benefits immediately</li>
        <li>Your membership discount will no longer apply</li>
        <li>You won't receive member-only communications</li>
        <li>You can rejoin at any time</li>
    </ul>
    <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#cancelModal">
        <i class="bi bi-x-circle"></i> Cancel My Membership
    </button>
</div>

<!-- Renewal Modal -->
<div class="modal fade" id="renewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Renew Membership</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="renew">
                    <input type="hidden" name="membership_type" id="renewal_type">
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> You are renewing your <strong id="renewal_type_name"></strong> membership for <strong id="renewal_price"></strong>/year.
                    </div>
                    
                    <h5 class="mb-3">Payment Method</h5>
                    
                    <div class="payment-method-option">
                        <input type="radio" name="payment_method" id="card" value="credit_card" checked>
                        <label for="card" class="d-flex align-items-center">
                            <i class="bi bi-credit-card fs-4 me-3"></i>
                            <div>
                                <strong>Credit/Debit Card</strong>
                                <small class="d-block text-muted">Visa, Mastercard, American Express</small>
                            </div>
                        </label>
                    </div>
                    
                    <div class="payment-method-option">
                        <input type="radio" name="payment_method" id="paypal" value="paypal">
                        <label for="paypal" class="d-flex align-items-center">
                            <i class="bi bi-paypal fs-4 me-3"></i>
                            <div>
                                <strong>PayPal</strong>
                                <small class="d-block text-muted">Pay securely with PayPal</small>
                            </div>
                        </label>
                    </div>
                    
                    <div class="payment-method-option">
                        <input type="radio" name="payment_method" id="bank" value="bank_transfer">
                        <label for="bank" class="d-flex align-items-center">
                            <i class="bi bi-bank fs-4 me-3"></i>
                            <div>
                                <strong>Bank Transfer</strong>
                                <small class="d-block text-muted">Direct bank transfer</small>
                            </div>
                        </label>
                    </div>
                    
                    <div class="alert alert-success mt-3">
                        <i class="bi bi-shield-check"></i> Your payment information is secure and encrypted.
                    </div>
                    
                    <p class="text-muted small">
                        <strong>Note:</strong> This is a demonstration system. In production, you would enter payment details and process the transaction through a payment gateway like Stripe or PayPal.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Complete Renewal
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Upgrade/Downgrade Modal -->
<div class="modal fade" id="upgradeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="upgradeModalTitle">Change Membership</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" id="upgrade_action" value="upgrade">
                    <input type="hidden" name="new_membership_type" id="upgrade_type">
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> You are <span id="upgrade_action_text">changing</span> to <strong id="upgrade_type_name"></strong> membership for <strong id="upgrade_price"></strong>/year.
                    </div>
                    
                    <h5 class="mb-3">Membership Details</h5>
                    <div class="table-responsive mb-3">
                        <table class="table">
                            <tr>
                                <td><strong>Current Plan:</strong></td>
                                <td><?= $current_type['name'] ?> - $<?= number_format($current_type['price']) ?>/year</td>
                            </tr>
                            <tr>
                                <td><strong>New Plan:</strong></td>
                                <td><span id="upgrade_plan_details"></span></td>
                            </tr>
                            <tr>
                                <td><strong>Cost:</strong></td>
                                <td><strong id="upgrade_cost"></strong></td>
                            </tr>
                        </table>
                    </div>
                    
                    <h5 class="mb-3">Payment Method</h5>
                    
                    <div class="payment-method-option">
                        <input type="radio" name="payment_method" id="card_upgrade" value="credit_card" checked>
                        <label for="card_upgrade" class="d-flex align-items-center">
                            <i class="bi bi-credit-card fs-4 me-3"></i>
                            <div>
                                <strong>Credit/Debit Card</strong>
                                <small class="d-block text-muted">Visa, Mastercard, American Express</small>
                            </div>
                        </label>
                    </div>
                    
                    <div class="payment-method-option">
                        <input type="radio" name="payment_method" id="paypal_upgrade" value="paypal">
                        <label for="paypal_upgrade" class="d-flex align-items-center">
                            <i class="bi bi-paypal fs-4 me-3"></i>
                            <div>
                                <strong>PayPal</strong>
                                <small class="d-block text-muted">Pay securely with PayPal</small>
                            </div>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" id="upgrade_submit_btn">
                        <i class="bi bi-arrow-up-circle"></i> <span id="upgrade_submit_text">Complete Change</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Cancel Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Cancel Membership</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="cancel">
                    
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i> <strong>Warning:</strong> This action cannot be undone.
                    </div>
                    
                    <p><strong>Are you sure you want to cancel your membership?</strong></p>
                    
                    <p>You will lose access to:</p>
                    <ul>
                        <li>Unlimited free admission</li>
                        <li>Exhibition previews</li>
                        <li>10% shop discount</li>
                        <li>Member-only events</li>
                        <li>All other member benefits</li>
                    </ul>
                    
                    <div class="mb-3">
                        <label class="form-label">Please tell us why you're leaving (optional):</label>
                        <textarea class="form-control" name="cancellation_reason" rows="3" 
                                  placeholder="Your feedback helps us improve..."></textarea>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="confirm_cancel" id="confirm_cancel" required>
                        <label class="form-check-label" for="confirm_cancel">
                            I understand that my membership will be cancelled and I will lose all benefits
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Keep My Membership</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-x-circle"></i> Yes, Cancel Membership
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function setRenewalType(typeId, typeName, price) {
    document.getElementById('renewal_type').value = typeId;
    document.getElementById('renewal_type_name').textContent = typeName;
    document.getElementById('renewal_price').textContent = '$' + price;
}

function setUpgradeType(typeId, typeName, price) {
    document.getElementById('upgrade_type').value = typeId;
    document.getElementById('upgrade_type_name').textContent = typeName;
    document.getElementById('upgrade_price').textContent = '$' + price;
    document.getElementById('upgrade_plan_details').textContent = typeName + ' - $' + price + '/year';
    document.getElementById('upgrade_cost').textContent = '$' + price + '/year';
    document.getElementById('upgrade_action').value = 'upgrade';
    document.getElementById('upgrade_action_text').textContent = 'upgrading';
    document.getElementById('upgradeModalTitle').textContent = 'Upgrade Membership';
    document.getElementById('upgrade_submit_text').textContent = 'Complete Upgrade';
}

function setDowngradeType(typeId, typeName, price) {
    document.getElementById('upgrade_type').value = typeId;
    document.getElementById('upgrade_type_name').textContent = typeName;
    document.getElementById('upgrade_price').textContent = '$' + price;
    document.getElementById('upgrade_plan_details').textContent = typeName + ' - $' + price + '/year';
    document.getElementById('upgrade_cost').textContent = '$' + price + '/year';
    document.getElementById('upgrade_action').value = 'downgrade';
    document.getElementById('upgrade_action_text').textContent = 'changing';
    document.getElementById('upgradeModalTitle').textContent = 'Change Membership';
    document.getElementById('upgrade_submit_text').textContent = 'Complete Change';
}
</script>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>