<?php
session_start();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/permissions.php';

$page_title = 'Organization Donor Summary';

// Only admin and curator can access
if ($_SESSION['user_type'] !== 'admin' && $_SESSION['user_type'] !== 'curator') {
    header('Location: index.php?error=access_denied');
    exit;
}

$db = db();

$donations = [];
$search_performed = false;
$donor_info = [];

if (isset($_POST['organization_name']) && isset($_POST['email'])) {
    $organization_name = $_POST['organization_name'];
    $email = $_POST['email'];
    
    $stmt = $db->prepare("CALL GetOrgDonorSummary(?, ?)");
    $stmt->bind_param("ss", $organization_name, $email);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $donations = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $db->next_result();
    
    // Get donor details
    $donor_stmt = $db->prepare("SELECT donor_id, organization_name, email, phone, address FROM DONOR WHERE organization_name = ? AND email = ? AND is_organization = 1");
    $donor_stmt->bind_param("ss", $organization_name, $email);
    $donor_stmt->execute();
    $donor_result = $donor_stmt->get_result();
    $donor_info = $donor_result->fetch_assoc();
    $donor_stmt->close();
    
    $search_performed = true;
}

include __DIR__ . '/../templates/layout_header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="index.php" class="btn btn-outline-secondary mb-2">
                <i class="bi bi-arrow-left"></i> Back to Reports
            </a>
            <h1 class="mb-0"><i class="bi bi-building-check"></i> Organization Donor Summary</h1>
            <p class="text-muted">View donation history for organizational donors</p>
        </div>
        <?php if ($search_performed && !empty($donations)): ?>
            <button onclick="window.print()" class="btn btn-primary">
                <i class="bi bi-printer"></i> Print
            </button>
        <?php endif; ?>
    </div>
    
    <!-- Search Form -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="bi bi-search"></i> Search for Organization</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Organization Name</label>
                        <input type="text" name="organization_name" class="form-control" 
                               value="<?= htmlspecialchars($_POST['organization_name'] ?? '') ?>" 
                               placeholder="Enter organization name" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" 
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                               placeholder="contact@organization.org" required>
                    </div>
                    <div class="col-md-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Search Organization
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <?php if ($search_performed): ?>
        <?php if (empty($donor_info)): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i> No organization found with the provided information. Please check the details and try again.
            </div>
        <?php else: ?>
            <!-- Organization Information Card -->
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-building"></i> Organization Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Donor ID:</strong> <?= htmlspecialchars($donor_info['donor_id']) ?></p>
                            <p><strong>Organization:</strong> <?= htmlspecialchars($donor_info['organization_name']) ?></p>
                            <p><strong>Email:</strong> <a href="mailto:<?= htmlspecialchars($donor_info['email']) ?>"><?= htmlspecialchars($donor_info['email']) ?></a></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Phone:</strong> <?= htmlspecialchars($donor_info['phone'] ?? 'Not provided') ?></p>
                            <p><strong>Address:</strong> <?= htmlspecialchars($donor_info['address'] ?? 'Not provided') ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if (empty($donations)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> This organization has not made any donations yet.
                </div>
            <?php else: ?>
                <!-- Donation Summary -->
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h6>Total Donations</h6>
                                <h2><?= count($donations) ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h6>Total Amount</h6>
                                <h2>$<?= number_format(array_sum(array_column($donations, 'amount')), 2) ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h6>Average Donation</h6>
                                <h2>$<?= number_format(array_sum(array_column($donations, 'amount')) / count($donations), 2) ?></h2>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Donations Table -->
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="bi bi-list-ul"></i> Donation History</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Donation ID</th>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Purpose</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($donations as $donation): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($donation['donation_id']) ?></td>
                                            <td><?= date('M d, Y', strtotime($donation['donation_date'])) ?></td>
                                            <td><strong>$<?= number_format($donation['amount'], 2) ?></strong></td>
                                            <td>
                                                <?php
                                                // Decode purpose
                                                $purposes = [
                                                    1 => 'General Fund',
                                                    2 => 'Acquisition',
                                                    3 => 'Exhibition',
                                                    4 => 'Education',
                                                    5 => 'Restoration'
                                                ];
                                                echo $purposes[$donation['purpose']] ?? 'Purpose ' . $donation['purpose'];
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-secondary">
                                    <tr>
                                        <th colspan="2" class="text-end">TOTAL:</th>
                                        <th>$<?= number_format(array_sum(array_column($donations, 'amount')), 2) ?></th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="card mt-4">
                    <div class="card-header bg-warning">
                        <h5 class="mb-0"><i class="bi bi-gear"></i> Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <a href="mailto:<?= htmlspecialchars($donor_info['email']) ?>?subject=Thank you for your generous support" class="btn btn-primary me-2">
                            <i class="bi bi-envelope"></i> Send Thank You Email
                        </a>
                        <button onclick="window.print()" class="btn btn-secondary">
                            <i class="bi bi-printer"></i> Print Tax Receipt
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../templates/layout_footer.php'; ?>