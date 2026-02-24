<?php
// Google API Configuration
// IMPORTANT: Replace these with your actual credentials in your local environment
// DO NOT commit your real Client Secret to version control
define('GOOGLE_CLIENT_ID', '714328770160-ihce8sl0b6gp7a7ldsllll1bsb2ntjhm.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', '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');
define('GOOGLE_REDIRECT_URL', 'https://localhost/udruwisdom/auth_google.php');

// Permissions (Scopes)
$google_scopes = [
    'https://www.googleapis.com/auth/userinfo.profile',
    'https://www.googleapis.com/auth/userinfo.email'
];
?>