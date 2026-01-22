<?php
session_start();
require_once __DIR__ . '/db.php';

function login($username, $password)
{
    $pdo = get_pdo();
    if (!$pdo)
        return false;

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        return true;
    }
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