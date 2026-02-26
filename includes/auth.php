<?php
// Security: Harden session cookie settings
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
// ini_set('session.cookie_secure', 1); // เปิดใช้เมื่อใช้งานผ่าน HTTPS
session_start();
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/logger.php';

function login($username, $password)
{
    $pdo = get_pdo();
    if (!$pdo)
        return false;

    // --- DB-based Brute Force Protection ---
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // Auto-create login_attempts table if not exists
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            username VARCHAR(100) NOT NULL,
            attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (ip_address),
            INDEX (username),
            INDEX (attempted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (PDOException $e) {
        // Table likely already exists, continue
    }

    // Clean up old attempts (older than 30 minutes)
    $pdo->exec("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)");

    // Check recent failed attempts (last 15 minutes) for this IP or username
    $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE (ip_address = ? OR username = ?) AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $check_stmt->execute([$ip, $username]);
    $attempts = $check_stmt->fetchColumn();

    if ($attempts >= 5) {
        return "มีการพยายามเข้าสู่ระบบมากเกินไป กรุณารอ 15 นาทีแล้วลองอีกครั้ง";
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        if ($user['status'] === 'suspended') {
            return "บัญชีของคุณถูกระงับการใช้งานกรุณาติดต่อผู้ดูแลระบบ";
        }
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];

        // Clear failed attempts on successful login
        $clear_stmt = $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ? OR username = ?");
        $clear_stmt->execute([$ip, $username]);

        log_activity('login');
        return true;
    }

    // Record failed attempt in DB
    $record_stmt = $pdo->prepare("INSERT INTO login_attempts (ip_address, username) VALUES (?, ?)");
    $record_stmt->execute([$ip, $username]);

    return false;
}

function is_logged_in()
{
    return isset($_SESSION['user_id']);
}

function require_login()
{
    if (!is_logged_in()) {
        header("Location: login.php");
        exit;
    }

    $pdo = get_pdo();
    if ($pdo) {
        // Update last_activity with safety
        try {
            $stmt = $pdo->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
        } catch (PDOException $e) {
            // Silently ignore if column doesn't exist yet
        }
    }

    // Check if user is suspended (Live Check)
    $pdo = get_pdo();
    $stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $status = $stmt->fetchColumn();

    if ($status === 'suspended') {
        session_unset();
        session_destroy();
        header("Location: login.php?error=suspended");
        exit;
    }
}

function logout()
{
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit;
}

function get_current_user_data()
{
    if (!is_logged_in())
        return null;
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'full_name' => $_SESSION['full_name'],
        'role' => $_SESSION['role']
    ];
}
?>