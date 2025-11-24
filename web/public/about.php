<?php
// Set page title
$page_title = 'About Us';

// Include header
include __DIR__ . '/templates/header.php';
?>

<!-- Page-specific styles for About page -->
<style>
    .page-header {
        background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
        color: white;
        padding: 60px 0;
        margin-bottom: 40px;
    }

    .page-header h1 {
        font-size: 3rem;
        font-weight: 700;
        margin-bottom: 1rem;
    }

    .info-card {
        background: white;
        border-radius: 15px;
        padding: 2rem;
        box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        margin-bottom: 2rem;
        height: 100%;
    }

    .info-card h3 {
        color: var(--primary-color);
        font-weight: 700;
        margin-bottom: 1.5rem;
    }

    .info-card i.main-icon {
        font-size: 3rem;
        color: var(--accent-color);
        margin-bottom: 1rem;
    }

    .contact-item {
        padding: 1rem;
        border-left: 4px solid var(--accent-color);
        background: #f8f9fa;
        border-radius: 8px;
        margin-bottom: 1rem;
    }

    .contact-item i {
        color: var(--accent-color);
        font-size: 1.5rem;
        margin-right: 1rem;
    }

    .hours-table {
        width: 100%;
    }

    .hours-table td {
        padding: 0.75rem 0;
        border-bottom: 1px solid #e9ecef;
    }

    .hours-table tr:last-child td {
        border-bottom: none;
    }

    .hours-table .day {
        font-weight: 600;
        color: var(--primary-color);
    }

    .hours-table .time {
        text-align: right;
        color: #666;
    }

    .map-container {
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    }

    .mission-section {
        background: white;
        padding: 3rem;
        border-radius: 15px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        margin-bottom: 3rem;
    }

    .mission-section h2 {
        color: var(--primary-color);
        font-weight: 700;
        margin-bottom: 1.5rem;
    }

    .stat-box {
        text-align: center;
        padding: 2rem;
        background: linear-gradient(135deg, var(--accent-color), var(--primary-color));
        color: white;
        border-radius: 15px;
        margin-bottom: 2rem;
    }

    .stat-box h3 {
        font-size: 3rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }

    .stat-box p {
        margin: 0;
        font-size: 1.1rem;
    }
</style>

<!-- Page Header -->
<div class="page-header">
    <div class="container text-center">
        <h1><i class="bi bi-building"></i> About the Museum</h1>
        <p class="lead">Discover our mission, visit us, and get in touch</p>
    </div>
</div>

<!-- Mission Section -->
<div class="container pb-5">
    <div class="mission-section">
        <h2 class="text-center mb-4">Our Mission</h2>
        <p class="lead text-center">
            Homies Fine Arts is dedicated to preserving, studying, and exhibiting the finest examples 
            of art from around the world. We strive to inspire, educate, and connect diverse communities through 
            the transformative power of art.
        </p>
        <p class="text-center mt-4">
            Since our founding about a month ago, we have built one of the most comprehensive art collections 
            in the region, spanning ancient civilizations to contemporary works. Our commitment to accessibility 
            ensures that art remains a vital part of our community's cultural landscape.
        </p>
    </div>

    <!-- Stats -->
    <div class="row mb-5">
        <div class="col-md-3">
            <div class="stat-box">
                <h3><i class="bi bi-palette-fill"></i></h3>
                <h3>10+</h3>
                <p>Artworks</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-box">
                <h3><i class="bi bi-people-fill"></i></h3>
                <h3>10+</h3>
                <p>Annual Visitors</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-box">
                <h3><i class="bi bi-calendar-event-fill"></i></h3>
                <h3>2+</h3>
                <p>Exhibitions/Year</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-box">
                <h3><i class="bi bi-star-fill"></i></h3>
                <h3>1</h3>
                <p>Month of Excellence</p>
            </div>
        </div>
    </div>

    <!-- Contact and Hours -->
    <div class="row">
        <div class="col-md-6">
            <div class="info-card">
                <i class="bi bi-telephone main-icon"></i>
                <h3>Contact Information</h3>
                
                <div class="contact-item">
                    <i class="bi bi-geo-alt-fill"></i>
                    <div class="d-inline-block">
                        <strong>Address</strong><br>
                        123 Money Street<br>
                        Laundering, TX 00000
                    </div>
                </div>
                
                <div class="contact-item">
                    <i class="bi bi-telephone-fill"></i>
                    <div class="d-inline-block">
                        <strong>Phone</strong><br>
                        (123) 456-7890
                    </div>
                </div>
                
                <div class="contact-item">
                    <i class="bi bi-envelope-fill"></i>
                    <div class="d-inline-block">
                        <strong>Email</strong><br>
                        <a href="mailto:info@hfa.com">info@hfa.com</a>
                    </div>
                </div>
                
                <div class="contact-item">
                    <i class="bi bi-globe"></i>
                    <div class="d-inline-block">
                        <strong>Social Media</strong><br>
                        <a href="#" class="me-2"><i class="bi bi-facebook"></i> Facebook</a>
                        <a href="#" class="me-2"><i class="bi bi-twitter"></i> Twitter</a>
                        <a href="#"><i class="bi bi-instagram"></i> Instagram</a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="info-card">
                <i class="bi bi-clock main-icon"></i>
                <h3>Hours of Operation</h3>
                
                <table class="hours-table">
                    <tr>
                        <td class="day">Monday</td>
                        <td class="time">Closed</td>
                    </tr>
                    <tr>
                        <td class="day">Tuesday</td>
                        <td class="time">10:00 AM - 5:00 PM</td>
                    </tr>
                    <tr>
                        <td class="day">Wednesday</td>
                        <td class="time">10:00 AM - 9:00 PM</td>
                    </tr>
                    <tr>
                        <td class="day">Thursday</td>
                        <td class="time">10:00 AM - 9:00 PM</td>
                    </tr>
                    <tr>
                        <td class="day">Friday</td>
                        <td class="time">10:00 AM - 7:00 PM</td>
                    </tr>
                    <tr>
                        <td class="day">Saturday</td>
                        <td class="time">10:00 AM - 7:00 PM</td>
                    </tr>
                    <tr>
                        <td class="day">Sunday</td>
                        <td class="time">12:15 PM - 7:00 PM</td>
                    </tr>
                </table>
                
                <div class="alert alert-info mt-3">
                    <i class="bi bi-info-circle"></i> 
                    <strong>General Admission:</strong> Free for members, $15 for adults, $7.50 for students
                </div>
            </div>
        </div>
    </div>

    <!-- Map -->
    <div class="row mt-4">
        <div class="col-12">
            <h3 class="text-center mb-4">Visit Us</h3>
            <div class="map-container">
                <iframe 
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3465.158842886373!2d-95.39606248489034!3d29.725485481996767!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x8640bf3be7a9dc8b%3A0x820a8b11fbb5e7dc!2sThe%20Museum%20of%20Fine%20Arts%2C%20Houston!5e0!3m2!1sen!2sus!4v1234567890123!5m2!1sen!2sus" 
                    width="100%" 
                    height="400" 
                    style="border:0;" 
                    allowfullscreen="" 
                    loading="lazy">
                </iframe>
            </div>
        </div>
    </div>

    <!-- Parking & Accessibility -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="info-card">
                <i class="bi bi-p-square main-icon"></i>
                <h3>Parking</h3>
                <p>There is no parking, you have to walk.</p>
                <ul>
                    <li><strong>Members:</strong> Free parking</li>
                    <li><strong>Visitors:</strong> $15 per day</li>
                    <li><strong>Valet Service:</strong> Available on weekends ($25)</li>
                </ul>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="info-card">
                <i class="bi bi-universal-access main-icon"></i>
                <h3>Accessibility</h3>
                <p>We are committed to doing our best to provide an accessible experience for all visitors.</p>
                <ul>
                    <li>Not wheelchair accessible entrances and galleries</li>
                    <li>Wheelchairs available at visitor services ($500)</li>
                    <li>Service animals may be allowed</li>
                    <li>Restrooms exist</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include __DIR__ . '/templates/footer.php';
?>