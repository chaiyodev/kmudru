<?php
session_start();
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/logger.php';

function login($username, $password)
{
    // Basic Brute Force Protection
    if (isset($_SESSION['login_attempts']) && $_SESSION['login_attempts'] > 5) {
        if (time() - $_SESSION['last_attempt_time'] < 300) { // 5 mins lockout
            return "Too many attempts. Please wait.";
        } else {
            $_SESSION['login_attempts'] = 0;
        }
    }

    $pdo = get_pdo();
    if (!$pdo)
        return false;

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
        unset($_SESSION['login_attempts']);
        log_activity('login');
        return true;
    }

    $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
    $_SESSION['last_attempt_time'] = time();
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