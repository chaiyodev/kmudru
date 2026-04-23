<?php
/**
 * Simple session-based API rate limiter.
 * Include this at the top of API endpoints to prevent abuse.
 *
 * Usage: require_once '../includes/rate_limit.php';
 *        check_rate_limit('chat_fetch', 60, 30); // 30 requests per 60 seconds
 */

function check_rate_limit($action_key = 'api', $window_seconds = 60, $max_requests = 30)
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $key = 'rate_limit_' . $action_key;
    $now = time();

    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'window_start' => $now];
    }

    $data = &$_SESSION[$key];

    // Reset window if expired
    if ($now - $data['window_start'] >= $window_seconds) {
        $data['count'] = 0;
        $data['window_start'] = $now;
    }

    $data['count']++;

    if ($data['count'] > $max_requests) {
        header('Content-Type: application/json');
        http_response_code(429);
        echo json_encode([
            'error' => 'คำขอมากเกินไป กรุณารอสักครู่แล้วลองใหม่',
            'retry_after' => $window_seconds - ($now - $data['window_start'])
        ]);
        exit;
    }
}
