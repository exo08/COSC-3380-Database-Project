<?php
session_start();
require_once __DIR__ . '/app/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = db();
    
    $donation_type = $_POST['donation_type'] ?? 'money';
    $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;
    
    // Get donor information
    $first_name = !$is_anonymous ? ($_POST['first_name'] ?? '') : 'Anonymous';
    $last_name = !$is_anonymous ? ($_POST['last_name'] ?? '') : 'Donor';
    $organization_name = $_POST['organization_name'] ?? null;
    $is_organization = !empty($organization_name) ? 1 : 0;
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    
    if ($donation_type === 'money') {
        // Monetary donation
        $amount = floatval($_POST['amount'] ?? 0);
        $purpose = $_POST['purpose'] ?? 1; // 1 = General Fund
        
        if ($amount <= 0) {
            $error = 'Please enter a valid donation amount.';
        } elseif (empty($email) && !$is_anonymous) {
            $error = 'Email is required for non-anonymous donations.';
        } else {
            // First, check if donor exists or create new donor
            $donor_id = null;
            
            if (!$is_anonymous && !empty($email)) {
                $stmt = $db->prepare("SELECT donor_id FROM DONOR WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $donor_id = $result->fetch_assoc()['donor_id'];
                }
            }
            
            // Create new donor if doesn't exist
            if ($donor_id === null) {
                $stmt = $db->prepare("
                    INSERT INTO DONOR (first_name, last_name, organization_name, is_organization, 
                                      address, email, phone)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("sssisss", 
                    $first_name, $last_name, $organization_name, $is_organization,
                    $address, $email, $phone
                );
                $stmt->execute();
                $donor_id = $db->insert_id;
            }
            
            // Create donation record
            $donation_date = date('Y-m-d');
            $stmt = $db->prepare("
                INSERT INTO DONATION (donor_id, amount, donation_date, purpose)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("idsi", $donor_id, $amount, $donation_date, $purpose);
            
            if ($stmt->execute()) {
                $success = 'Thank you for your generous donation of $' . number_format($amount, 2) . '!';
                if (!$is_anonymous) {
                    $success .= ' A confirmation email will be sent to ' . htmlspecialchars($email) . '.';
                }
            } else {
                $error = 'An error occurred processing your donation. Please try again.';
            }
        }
    } else {
        // Artwork donation
        $artwork_title = $_POST['artwork_title'] ?? '';
        $artist_name = $_POST['artist_name'] ?? '';
        $creation_year = $_POST['creation_year'] ?? null;
        $medium = $_POST['medium'] ?? '';
        $dimensions = $_POST['dimensions'] ?? '';
        $description = $_POST['description'] ?? '';
        $estimated_value = floatval($_POST['estimated_value'] ?? 0);
        
        if (empty($artwork_title) || empty($artist_name)) {
            $error = 'Please provide artwork title and artist name.';
        } elseif (empty($email) && !$is_anonymous) {
            $error = 'Email is required for non-anonymous donations.';
        } else {
            // Create or get donor
            $donor_id = null;
            
            if (!$is_anonymous && !empty($email)) {
                $stmt = $db->prepare("SELECT donor_id FROM DONOR WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $donor_id = $result->fetch_assoc()['donor_id'];
                }
            }
            
            if ($donor_id === null) {
                $stmt = $db->prepare("
                    INSERT INTO DONOR (first_name, last_name, organization_name, is_organization, 
                                      address, email, phone)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("sssisss", 
                    $first_name, $last_name, $organization_name, $is_organization,
                    $address, $email, $phone
                );
                $stmt->execute();
                $donor_id = $db->insert_id;
            }
            
            $success = 'Thank you for your artwork donation proposal! Our curators will review "' . 
                      htmlspecialchars($artwork_title) . '" and contact you soon.';
            if (!$is_anonymous && !empty($email)) {
                $success .= ' We will reach out to ' . htmlspecialchars($email) . ' within 5-7 business days.';
            }
            
            // Actual artwork would be added to ARTWORK table after curator review
            // for now we're just capturing the donation intent
        }
    }
}

$donation_purposes = [
    1 => 'General Fund',
    2 => 'Artwork Acquisition',
    3 => 'Educational Programs',
    4 => 'Exhibition Support',
    5 => 'Building & Facilities',
    6 => 'Conservation & Preservation'
];
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Homies Fine Arts - Make a Donation</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
        <style>
            :root {
                --primary-color: #2c3e50;
                --secondary-color: #e74c3c;
                --accent-color: #3498db;
            }

            body {
                background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
                min-height: 100vh;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                padding: 50px 0;
            }

            .donation-container {
                max-width: 900px;
                margin: 0 auto;
                padding: 0 15px;
            }

            .donation-card {
                background: white;
                border-radius: 20px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                overflow: hidden;
            }

            .donation-header {
                background: linear-gradient(135deg, var(--secondary-color), #c0392b);
                color: white;
                padding: 2.5rem;
                text-align: center;
            }

            .donation-header h2 {
                margin: 0;
                font-weight: 700;
                font-size: 2.5rem;
            }

            .donation-body {
                padding: 2.5rem;
            }

            .donation-type-selector {
                display: flex;
                gap: 1rem;
                margin-bottom: 2rem;
            }

            .donation-type-btn {
                flex: 1;
                padding: 1.5rem;
                border: 3px solid #e0e0e0;
                border-radius: 15px;
                background: white;
                cursor: pointer;
                transition: all 0.3s;
                text-align: center;
            }

            .donation-type-btn:hover {
                border-color: var(--secondary-color);
                transform: translateY(-5px);
                box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            }

            .donation-type-btn.active {
                border-color: var(--secondary-color);
                background: linear-gradient(135deg, rgba(231, 76, 60, 0.1), rgba(192, 57, 43, 0.1));
            }

            .donation-type-btn i {
                font-size: 3rem;
                color: var(--secondary-color);
                margin-bottom: 0.5rem;
            }

            .donation-type-btn input[type="radio"] {
                display: none;
            }

            .quick-amount-btn {
                padding: 12px 20px;
                border: 2px solid #e0e0e0;
                border-radius: 10px;
                background: white;
                cursor: pointer;
                transition: all 0.3s;
                font-weight: 600;
            }

            .quick-amount-btn:hover,
            .quick-amount-btn.active {
                border-color: var(--secondary-color);
                background: var(--secondary-color);
                color: white;
            }

            .form-label {
                font-weight: 600;
                color: var(--primary-color);
            }

            .form-control:focus {
                border-color: var(--secondary-color);
                box-shadow: 0 0 0 0.2rem rgba(231, 76, 60, 0.25);
            }

            .btn-donate-submit {
                background: linear-gradient(135deg, var(--secondary-color), #c0392b);
                border: none;
                padding: 15px;
                font-weight: 700;
                font-size: 1.1rem;
                transition: all 0.3s;
            }

            .btn-donate-submit:hover {
                transform: translateY(-2px);
                box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            }

            .impact-info {
                background: #f8f9fa;
                border-left: 4px solid var(--secondary-color);
                padding: 1.5rem;
                margin: 2rem 0;
                border-radius: 10px;
            }

            .anonymous-toggle {
                background: #fff3cd;
                border: 2px solid #ffc107;
                border-radius: 10px;
                padding: 1rem;
                margin-bottom: 1.5rem;
            }
        </style>
    </head>
    <body>
        <div class="donation-container">
            <div class="donation-card">
                <div class="donation-header">
                    <i class="bi bi-heart-fill" style="font-size: 3rem;"></i>
                    <h2>Support the Arts</h2>
                    <p class="mb-0 fs-5">Your generosity helps preserve culture and inspire future generations</p>
                </div>
                
                <div class="donation-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <!-- Donation Type Selection -->
                        <div class="donation-type-selector">
                            <label class="donation-type-btn active" id="money-type">
                                <input type="radio" name="donation_type" value="money" checked>
                                <i class="bi bi-currency-dollar"></i>
                                <h5>Monetary Donation</h5>
                                <small class="text-muted">Make a financial contribution</small>
                            </label>
                            
                            <label class="donation-type-btn" id="artwork-type">
                                <input type="radio" name="donation_type" value="artwork">
                                <i class="bi bi-palette"></i>
                                <h5>Artwork Donation</h5>
                                <small class="text-muted">Donate a piece of art</small>
                            </label>
                        </div>

                        <!-- Anonymous Donation Toggle -->
                        <div class="anonymous-toggle">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="is_anonymous" name="is_anonymous">
                                <label class="form-check-label" for="is_anonymous">
                                    <strong><i class="bi bi-incognito"></i> Make this an anonymous donation</strong>
                                    <div class="small text-muted">Your name will not be publicly displayed</div>
                                </label>
                            </div>
                        </div>

                        <!-- Monetary Donation Form -->
                        <div id="money-form">
                            <div class="mb-4">
                                <label class="form-label">Select Amount</label>
                                <div class="d-flex gap-2 flex-wrap mb-3">
                                    <button type="button" class="quick-amount-btn" data-amount="25">$25</button>
                                    <button type="button" class="quick-amount-btn" data-amount="50">$50</button>
                                    <button type="button" class="quick-amount-btn" data-amount="100">$100</button>
                                    <button type="button" class="quick-amount-btn" data-amount="250">$250</button>
                                    <button type="button" class="quick-amount-btn" data-amount="500">$500</button>
                                    <button type="button" class="quick-amount-btn" data-amount="1000">$1,000</button>
                                </div>
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" name="amount" id="amount" 
                                        placeholder="Enter custom amount" min="1" step="0.01" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Donation Purpose</label>
                                <select class="form-select" name="purpose">
                                    <?php foreach ($donation_purposes as $id => $purpose): ?>
                                        <option value="<?= $id ?>"><?= $purpose ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="impact-info">
                                <h6><i class="bi bi-info-circle"></i> Your Impact</h6>
                                <ul class="mb-0">
                                    <li>$25 provides art supplies for educational workshops</li>
                                    <li>$100 sponsors a school group visit</li>
                                    <li>$500 helps conserve artwork for future generations</li>
                                    <li>$1,000+ supports major acquisitions and exhibitions</li>
                                </ul>
                            </div>
                        </div>

                        <!-- Artwork Donation Form -->
                        <div id="artwork-form" style="display: none;">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> <strong>Artwork Donation Process:</strong>
                                After submitting this form, our curatorial team will review your proposal and contact you 
                                within 5-7 business days to discuss the donation.
                            </div>

                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label class="form-label">Artwork Title <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="artwork_title">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Creation Year</label>
                                    <input type="number" class="form-control" name="creation_year" 
                                        min="1000" max="<?= date('Y') ?>" placeholder="<?= date('Y') ?>">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Artist Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="artist_name" 
                                    placeholder="Full name of the artist">
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Medium</label>
                                    <input type="text" class="form-control" name="medium" 
                                        placeholder="e.g., Oil on canvas, Bronze, etc.">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Dimensions</label>
                                    <input type="text" class="form-control" name="dimensions" 
                                        placeholder="e.g., 24 x 36 inches">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Estimated Value (USD)</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" name="estimated_value" 
                                        placeholder="0.00" step="0.01">
                                </div>
                                <small class="text-muted">Optional - for insurance purposes</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Description & Provenance</label>
                                <textarea class="form-control" name="description" rows="4"
                                        placeholder="Describe the artwork, its history, and how you acquired it"></textarea>
                            </div>
                        </div>

                        <!-- Donor Information (shown when not anonymous) -->
                        <div id="donor-info">
                            <hr class="my-4">
                            <h5 class="mb-3"><i class="bi bi-person"></i> Donor Information</h5>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="is_organization" 
                                        name="is_organization">
                                    <label class="form-check-label" for="is_organization">
                                        This donation is from an organization/company
                                    </label>
                                </div>
                            </div>

                            <div id="organization-field" style="display: none;">
                                <div class="mb-3">
                                    <label class="form-label">Organization Name</label>
                                    <input type="text" class="form-control" name="organization_name">
                                </div>
                            </div>

                            <div class="row" id="personal-fields">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">First Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="first_name" id="first_name">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Last Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="last_name" id="last_name">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" name="email" id="email">
                                <small class="text-muted">For donation confirmation and tax receipt</small>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" name="phone" 
                                        placeholder="(555) 123-4567">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Address</label>
                                    <input type="text" class="form-control" name="address" 
                                        placeholder="Street, City, State, ZIP">
                                </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-danger btn-donate-submit w-100">
                                <i class="bi bi-heart-fill"></i> Complete Donation
                            </button>
                        </div>

                        <div class="text-center mt-3">
                            <small class="text-muted">
                                <i class="bi bi-shield-check"></i> Your donation is secure and tax-deductible
                            </small>
                            <br>
                            <a href="index.php" class="text-muted mt-2 d-inline-block">
                                <i class="bi bi-arrow-left"></i> Back to Home
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            // Donation type switching
            document.querySelectorAll('.donation-type-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.donation-type-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    
                    const type = this.querySelector('input').value;
                    document.getElementById('money-form').style.display = type === 'money' ? 'block' : 'none';
                    document.getElementById('artwork-form').style.display = type === 'artwork' ? 'block' : 'none';
                    
                    // Update required fields
                    if (type === 'money') {
                        document.getElementById('amount').required = true;
                    } else {
                        document.getElementById('amount').required = false;
                    }
                });
            });

            // Quick amount buttons
            document.querySelectorAll('.quick-amount-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.quick-amount-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    document.getElementById('amount').value = this.dataset.amount;
                });
            });

            // Anonymous toggle
            document.getElementById('is_anonymous').addEventListener('change', function() {
                const donorInfo = document.getElementById('donor-info');
                donorInfo.style.display = this.checked ? 'none' : 'block';
                
                // Update required fields
                const requiredInputs = donorInfo.querySelectorAll('input[required]');
                requiredInputs.forEach(input => {
                    input.required = !this.checked;
                });
            });

            // Organization toggle
            document.getElementById('is_organization').addEventListener('change', function() {
                document.getElementById('organization-field').style.display = this.checked ? 'block' : 'none';
            });

            // Custom amount input
            document.getElementById('amount').addEventListener('input', function() {
                document.querySelectorAll('.quick-amount-btn').forEach(b => b.classList.remove('active'));
            });
        </script>
    </body>
</html>