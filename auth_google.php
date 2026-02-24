<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/google_config.php';

// Check if Google Config is set (Check for default placeholders)
if (
    !defined('GOOGLE_CLIENT_ID') ||
    GOOGLE_CLIENT_ID === 'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com' ||
    GOOGLE_CLIENT_ID === 'YOUR_ID.apps.googleusercontent.com' ||
    empty(GOOGLE_CLIENT_ID)
) {
    die('<div style="font-family: \'Sarabun\', sans-serif; padding: 2rem; background: #fff1f2; color: #991b1b; border-radius: 12px; border: 1px solid #fecaca; max-width: 600px; margin: 5rem auto; text-align: center;">
        <h2 style="margin-top:0;">⚠️ ระบบ Google Login ยังไม่ได้ตั้งค่า</h2>
        <p>กรุณากรอกกุญแจ API ในไฟล์ <code>includes/google_config.php</code> ก่อนใช้งานครับ</p>
        <p style="font-size: 0.9rem; opacity: 0.8; background: #fff; padding: 1rem; border-radius: 8px; border: 1px solid #fecaca; text-align: left;">
            1. คัดลอกไฟล์ <b>google_config.example.php</b> เป็น <b>google_config.php</b><br>
            2. นำ Client ID และ Client Secret จาก Google Cloud Console มาใส่<br>
            3. ระบบจะเริ่มใช้งานได้ทันทีครับ
        </p>
        <hr style="border:0; border-top:1px solid #fecaca; margin: 1.5rem 0;">
        <a href="login.php" style="color: #991b1b; font-weight: bold; text-decoration: none;">&larr; กลับหน้าเข้าสู่ระบบ</a>
    </div>');
}

$pdo = get_pdo();

// 1. If we don't have a code, redirect to Google Login
if (!isset($_GET['code'])) {
    $params = [
        'response_type' => 'code',
        'client_id' => GOOGLE_CLIENT_ID,
        'redirect_uri' => GOOGLE_REDIRECT_URL,
        'scope' => 'email profile',
        'access_type' => 'online',
        'prompt' => 'select_account'
    ];
    header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params));
    exit;
}

// 2. Handle the callback
$code = $_GET['code'];

// Exchange code for Access Token
$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'code' => $code,
    'client_id' => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri' => GOOGLE_REDIRECT_URL,
    'grant_type' => 'authorization_code'
]));
$response = curl_exec($ch);
$data = json_decode($response, true);

if (isset($data['error'])) {
    die('Google Error: ' . $data['error_description']);
}

$access_token = $data['access_token'];

// Fetch User Info
$ch = curl_init('https://www.googleapis.com/oauth2/v3/userinfo');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token]);
$userinfo = json_decode(curl_exec($ch), true);

if (!isset($userinfo['email'])) {
    die('Could not fetch user info.');
}

$google_id = $userinfo['sub'];
$email = $userinfo['email'];
$name = $userinfo['name'];
$username = explode('@', $email)[0];

// 3. Database Sync
if ($pdo) {
    // Check if user exists by google_id OR email
    $stmt = $pdo->prepare("SELECT * FROM users WHERE google_id = ? OR email = ?");
    $stmt->execute([$google_id, $email]);
    $user = $stmt->fetch();

    if ($user) {
        // User exists, update google_id if missing
        if (empty($user['google_id'])) {
            $stmt = $pdo->prepare("UPDATE users SET google_id = ? WHERE id = ?");
            $stmt->execute([$google_id, $user['id']]);
        }
    } else {
        // Auto-Register new user
        $role = (strpos($email, '@udru.ac.th') !== false) ? 'contributor' : 'reader';

        $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, role, google_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$username, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), $name, $email, $role, $google_id]);

        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$pdo->lastInsertId()]);
        $user = $stmt->fetch();
    }

    // 4. Check status before session
    if ($user['status'] === 'suspended') {
        header("Location: login.php?error=suspended");
        exit;
    }

    // 5. Create Session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role'] = $user['role'];

    session_regenerate_id(true);
    header("Location: index.php");
    exit;
}
?>