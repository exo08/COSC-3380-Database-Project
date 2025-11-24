<?php
// Session timeout management
// Include this file at the top of pages that require authentication

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define timeout duration (5 minutes = 300 seconds)
define('SESSION_TIMEOUT', 600); // 5 minutes in seconds

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    
    // Check if last activity timestamp exists
    if (isset($_SESSION['last_activity'])) {
        
        // Calculate idle time
        $idle_time = time() - $_SESSION['last_activity'];
        
        // If idle time exceeds timeout, log out user
        if ($idle_time > SESSION_TIMEOUT) {
            // Store username for the logout message
            $username = $_SESSION['username'] ?? 'User';
            
            // Destroy session
            session_unset();
            session_destroy();
            
            // Start new session for the timeout message
            session_start();
            $_SESSION['timeout_message'] = 'Logged out due to more than 10 minutes of inactivity';
            
            // Redirect to login page
            header('Location: /login.php?reason=timeout');
            exit;
        }
    }
    
    // Update last activity timestamp
    $_SESSION['last_activity'] = time();
}

// Session security
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
} else if (time() - $_SESSION['created'] > 1800) {
    // Regenerate session ID every 30 minutes for security
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}