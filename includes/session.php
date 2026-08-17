<?php
declare(strict_types=1);
/**
 * Secure Session Configuration
 * Secure Web Application - Session Security
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/security.php';

apply_security_headers();
enforce_https_if_required();

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Configure secure session settings
    session_name(SESSION_NAME);
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.gc_maxlifetime', (string) SESSION_LIFETIME);
    
    // Set secure flag only if HTTPS is active
    $is_https = request_is_https();
    
    if ($is_https) {
        ini_set('session.cookie_secure', '1');
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
function session_regenerate_secure(bool $delete_old_session = true): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id($delete_old_session);
    }
}
