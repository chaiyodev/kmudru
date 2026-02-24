<?php
// includes/google_config.example.php
// คัดลอกไฟล์นี้เป็น google_config.php และใส่กุญแจที่ได้รับจาก Google Cloud Console

define('GOOGLE_CLIENT_ID', 'YOUR_ID.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'YOUR_SECRET');

// เปลี่ยนเป็น URL ของคุณ (เช่น http://domain.com/auth_google.php)
define('GOOGLE_REDIRECT_URL', 'http://localhost/udruwisdom/auth_google.php');
?>