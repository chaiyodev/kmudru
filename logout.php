<?php
require_once 'includes/auth.php';

// Accept both GET (legacy/link) and POST (secure) logout
// For POST requests, verify CSRF token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'includes/security.php';
    try {
        verify_csrf_token($_POST['csrf_token'] ?? '');
    } catch (Exception $e) {
        // If CSRF fails, still allow logout for security (better to log out than stay in)
        error_log("Logout CSRF warning: " . $e->getMessage());
    }
}

logout();
?>