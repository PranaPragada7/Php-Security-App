<?php
/**
 * CSRF Protection Helper Functions
 * Secure Web Application - CSRF Token Management
 */

/**
 * Initialize CSRF token in session if it doesn't exist
 */
function csrf_init() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

/**
 * Get current CSRF token
 * @return string CSRF token
 */
function csrf_token() {
    csrf_init();
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token (constant-time comparison)
 * @param string $token Token to validate
 * @return bool True if valid, false otherwise
 */
function csrf_validate($token) {
    csrf_init();
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Validate CSRF token from request (checks POST field or X-CSRF-Token header)
 * @return bool True if valid, false otherwise
 */
function csrf_validate_request() {
    $token = null;
    
    // Check POST field first
    if (isset($_POST['csrf_token'])) {
        $token = $_POST['csrf_token'];
    }
    // Check header (for API calls)
    elseif (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
    }
    
    if ($token === null) {
        return false;
    }
    
    return csrf_validate($token);
}
