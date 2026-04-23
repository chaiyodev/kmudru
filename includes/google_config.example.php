<?php
/**
 * Google API Configuration (Template)
 * 
 * Instructions:
 * 1. Copy this file to 'google_config.php'
 * 2. Get your Client ID and Client Secret from https://console.cloud.google.com/
 * 3. Update the constants below with your actual values.
 * 
 * IMPORTANT: NEVER commit 'google_config.php' to version control (it is ignored by .gitignore).
 */

// Google API Credentials
define('GOOGLE_CLIENT_ID', 'YOUR_ID_HERE.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'YOUR_SECRET_HERE');

// Dynamic Redirect URL Generation
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// For local XAMPP: http://localhost/udruwisdom/
// For Production: https://yourdomain.com/
$base_url = $protocol . '://' . $host . '/udruwisdom';

define('GOOGLE_REDIRECT_URL', $base_url . '/auth_google.php');

// Permissions (Scopes)
$google_scopes = [
    'https://www.googleapis.com/auth/userinfo.profile',
    'https://www.googleapis.com/auth/userinfo.email'
];
?>
