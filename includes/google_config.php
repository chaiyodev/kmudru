<?php
// Google API Configuration
define('GOOGLE_CLIENT_ID', 'YOUR_CLIENT_ID_HERE.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'YOUR_CLIENT_SECRET_HERE');
define('GOOGLE_REDIRECT_URL', 'http://localhost:8080/kmudru/auth_google.php');

// Permissions (Scopes)
$google_scopes = [
    'https://www.googleapis.com/auth/userinfo.profile',
    'https://www.googleapis.com/auth/userinfo.email'
];
?>