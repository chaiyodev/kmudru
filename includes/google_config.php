<?php
// Google API Configuration
// IMPORTANT: Replace these with your actual credentials in your local environment
// DO NOT commit your real Client Secret to version control
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base_url = $protocol . '://' . $host . '/udruwisdom';

define('GOOGLE_CLIENT_ID', 'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET');
define('GOOGLE_REDIRECT_URL', $base_url . '/auth_google.php');

// Permissions (Scopes)
$google_scopes = [
    'https://www.googleapis.com/auth/userinfo.profile',
    'https://www.googleapis.com/auth/userinfo.email'
];
?>