<?php
/**
 * Secure Session Configuration
 * Secure Web Application - Session Security
 */

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Configure secure session settings
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    
    // Set secure flag only if HTTPS is active
    $is_https = false;
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        $is_https = true;
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        $is_https = true;
    } elseif (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) {
        $is_https = true;
    }
    
    if ($is_https) {
        ini_set('session.cookie_secure', 1);
    }
    
    // Set SameSite attribute (PHP 7.3+)
    if (PHP_VERSION_ID >= 70300) {
        ini_set('session.cookie_samesite', 'Lax');
    }
    
    session_start();
}

/**
 * Regenerate session ID (call after successful authentication)
 * @param bool $delete_old_session Whether to delete old session
 */
function session_regenerate_secure($delete_old_session = true) {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id($delete_old_session);
    }
}
