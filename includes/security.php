<?php
/**
 * Phase 12 Security Core
 * Functions for XSS, CSRF, and Secure Headers
 */

// 1. Secure Headers
function set_secure_headers()
{
    header("X-Frame-Options: SAMEORIGIN");
    header("X-XSS-Protection: 1; mode=block");
    header("X-Content-Type-Options: nosniff");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Content-Security-Policy: default-src 'self' https:; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://unpkg.com https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; frame-src 'self' https://www.youtube.com;");
}

// 2. XSS Protection (Escape HTML)
function e($string)
{
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// 3. CSRF Protection (Compatibility: random_bytes polyfill for PHP < 7.0)
if (!function_exists('random_bytes')) {
    function random_bytes($length)
    {
        if (function_exists('openssl_random_pseudo_bytes')) {
            $bytes = openssl_random_pseudo_bytes($length, $strong);
            if ($strong === true) return $bytes;
        }
        $data = '';
        for ($i = 0; $i < $length; $i++) {
            $data .= chr(mt_rand(0, 255));
        }
        return $data;
    }
}

function generate_csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        try {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            $_SESSION['csrf_token'] = md5(uniqid(mt_rand(), true));
        }
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token)
{
    if (empty($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        throw new Exception("ความปลอดภัยผิดพลาด (Session/CSRF Expired) กรุณารีเฟรชหน้าจอหรือลองใหม่อีกครั้งเพื่อความปลอดภัยครับ");
    }
    return true;
}

// 5. Input Sanitization (Basic)
function sanitize_input($data)
{
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = sanitize_input($value);
        }
    } else {
        $data = trim($data);
    }
    return $data;
}

// Apply secure headers immediately
set_secure_headers();
?>