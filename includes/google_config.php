<?php
// Google API Configuration
// IMPORTANT: Replace these with your actual credentials in your local environment
// DO NOT commit your real Client Secret to version control
// For Local XAMPP: http://localhost/udruwisdom/
// For Production: https://yourdomain.com/
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base_url = $protocol . '://' . $host . '/udruwisdom';

define('GOOGLE_CLIENT_ID', '714328770160-ihce8sl0b6gp7a7ldsllll1bsb2ntjhm.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET_HERE'); // Removed for GitHub push
define('GOOGLE_REDIRECT_URL', $base_url . '/auth_google.php');

// Permissions (Scopes)
$google_scopes = [
    'https://www.googleapis.com/auth/userinfo.profile',
    'https://www.googleapis.com/auth/userinfo.email'
];
?>