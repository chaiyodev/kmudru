<?php
// includes/logger.php
require_once __DIR__ . '/db.php';

/**
 * Log user activity with optional details
 */
function log_activity($action, $target_type = null, $details = null)
{
    $pdo = get_pdo();
    if (!$pdo)
        return;

    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

    try {
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, target_type, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $action, $target_type, $details, $ip, $ua]);
    } catch (PDOException $e) {
        // Silently fail to not interrupt user flow
    }
}

/**
 * Track page visits for analytics
 */
function track_visitor($page = null)
{
    $pdo = get_pdo();
    if (!$pdo)
        return;

    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $referrer = $_SERVER['HTTP_REFERER'] ?? '';
    $page = $page ?? basename($_SERVER['PHP_SELF']);
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

    // Check if internal (from same domain) or external
    $is_internal = false;
    if (!empty($referrer)) {
        $ref_host = parse_url($referrer, PHP_URL_HOST);
        $current_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $is_internal = ($ref_host === $current_host);
    }

    // Also mark as internal if user is logged in
    if ($user_id) {
        $is_internal = true;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO visitor_stats (ip_address, user_agent, page_visited, referrer, is_internal, user_id, visit_date) VALUES (?, ?, ?, ?, ?, ?, CURDATE())");
        $stmt->execute([$ip, $ua, $page, $referrer, $is_internal ? 1 : 0, $user_id]);
    } catch (PDOException $e) {
        // Silently fail
    }
}
?>