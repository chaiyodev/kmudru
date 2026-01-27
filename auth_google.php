<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/google_config.php';

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

    // 4. Create Session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role'] = $user['role'];

    session_regenerate_id(true);
    header("Location: index.php");
    exit;
}
?>