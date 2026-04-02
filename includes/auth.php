<?php
// Secure session cookie settings for mobile compatibility (Safari/ITP)
if (session_status() === PHP_SESSION_NONE) {
    $current_protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $current_protocol,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    } else {
        // Fallback for PHP older than 7.3.0 (No SameSite support via this function)
        // We use the standard signature to ensure maximum stability and avoid 500 errors.
        session_set_cookie_params(0, '/', '', $current_protocol, true);
    }
    session_start();
}
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
}

/**
 * Require the logged-in user to have a specific role.
 * Redirects to index.php with an HTTP 403 status if the role does not match.
 * Automatically calls require_login() first.
 *
 * @param string|array $role  A single role string or an array of allowed roles.
 */
function require_role($role)
{
    require_login();
    $allowed = is_array($role) ? $role : [$role];
    if (!in_array($_SESSION['role'] ?? '', $allowed, true)) {
        http_response_code(403);
        header("Location: index.php");
        exit;
    }
}

/**
 * Shortcut: require the current user to be an admin.
 */
function require_admin()
{
    require_role('admin');
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

function update_user_activity()
{
    if (is_logged_in()) {
        $pdo = get_pdo();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
            } catch (PDOException $e) {
                // Silently fail if column is missing; avoids 500 error
            }
        }
    }
}

function get_online_members($community_id)
{
    $pdo = get_pdo();
    if (!$pdo) return [];
    try {
        $stmt = $pdo->prepare("SELECT u.id, u.username, u.full_name, u.avatar, m.role FROM community_members m JOIN users u ON m.user_id = u.id WHERE m.community_id = ? AND u.last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE) ORDER BY u.last_activity DESC LIMIT 10");
        $stmt->execute([$community_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

if (is_logged_in()) { update_user_activity(); }
?>