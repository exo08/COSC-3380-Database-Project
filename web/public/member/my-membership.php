// NOT DONE YET

<?php
session_start();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/permissions.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'member') {
    header('Location: /login.php');
    exit;
}

$db = db();
$member_id = $_SESSION['member_id']; // assuming it's stored at login
$success = '';
$error = '';

// Fetch current member data
$stmt = $db->prepare("SELECT * FROM MEMBER WHERE member_id = ?");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$member = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_info') {
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        $auto_renew = isset($_POST['auto_renew']) ? 1 : 0;

        $stmt = $db->prepare("UPDATE MEMBER SET phone = ?, address = ?, auto_renew = ? WHERE member_id = ?");
        $stmt->bind_param("ssii", $phone, $address, $auto_renew, $member_id);

        if ($stmt->execute()) {
            $success = "Profile updated successfully.";
        } else {
            $error = "Failed to update profile: " . $db->error;
        }
        $stmt->close();

    } elseif ($action === 'renew') {
        // Extend expiration by 1 year
        $stmt = $db->prepare("UPDATE MEMBER SET expiration_date = DATE_ADD(expiration_date, INTERVAL 1 YEAR) WHERE member_id = ?");
        $stmt->bind_param("i", $member_id);

        if ($stmt->execute()) {
            $success = "Membership renewed for one year!";
        } else {
            $error = "Could not renew membership: " . $db->error;
        }
        $stmt->close();
    }
}
?>


// HTML + Bootstrap
<?php include __DIR__ . '/../templates/layout_header.php'; ?>

<div class="container mt-4">
    <h2><i class="bi bi-person-badge"></i> My Membership</h2>
    <p class="text-muted">Manage your membership details and renewal settings</p>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title"><?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) ?></h5>
            <p class="mb-1"><strong>Email:</strong> <?= htmlspecialchars($member['email']) ?></p>
            <p class="mb-1"><strong>Membership Type:</strong> <?= htmlspecialchars($member['membership_type']) ?></p>
            <p class="mb-1"><strong>Expiration Date:</strong> <?= htmlspecialchars($member['expiration_date']) ?></p>
            <p class="mb-3">
                <strong>Status:</strong>
                <?php if (strtotime($member['expiration_date']) < time()): ?>
                    <span class="badge bg-danger">Expired</span>
                <?php else: ?>
                    <span class="badge bg-success">Active</span>
                <?php endif; ?>
            </p>

            <form method="POST" class="d-inline">
                <input type="hidden" name="action" value="renew">
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-arrow-repeat"></i> Renew Membership
                </button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Update Contact Info</div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="update_info">
                <div class="mb-3">
                    <label class="form-label">Phone</label>
                    <input type="text" class="form-control" name="phone" value="<?= htmlspecialchars($member['phone']) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Address</label>
                    <input type="text" class="form-control" name="address" value="<?= htmlspecialchars($member['address']) ?>">
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="auto_renew" <?= $member['auto_renew'] ? 'checked' : '' ?>>
                    <label class="form-check-label">Enable Auto-Renew</label>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Save Changes
                </button>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>
