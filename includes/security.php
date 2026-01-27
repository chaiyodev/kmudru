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

// 3. CSRF Protection
function generate_csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token)
{
    if (empty($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        die("Security Validation Failed: CSRF token mismatch.");
    }
    return true;
}

// 4. Input Sanitization (Basic)
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