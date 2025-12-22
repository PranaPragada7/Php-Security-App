<?php
/**
 * Database Configuration Settings - EXAMPLE FILE
 * Copy this file to settings.php and update with your configuration
 * Secure Web Application - Settings File
 */

// Database configuration array
$db_config = [
    'host' => 'localhost',
    'port' => '3306',
    'database' => 'encryption_demo_server',
    'username' => 'root',
    'password' => '', // Set your MySQL password here
    'charset' => 'utf8mb4'
];

// Database connection string
$dsn = "mysql:host={$db_config['host']};port={$db_config['port']};dbname={$db_config['database']};charset={$db_config['charset']}";

// PDO options
$pdo_options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

// AES Encryption Key (32 bytes for AES-256)
// Generate a secure random key using: php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
// IMPORTANT: In production, store this in an environment variable or secure key management system
// MUST CHANGE: Replace with your own secure random 64-character hex string
define('AES_KEY', 'CHANGE_THIS_TO_RANDOM_64_HEX_CHARS'); // 64 hex chars = 32 bytes
define('AES_METHOD', 'AES-256-CBC');

// HMAC Secret Key (64 hex characters for SHA-256 HMAC)
// Generate a secure random key using: php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
// IMPORTANT: In production, store this in an environment variable or secure key management system
// MUST CHANGE: Replace with your own secure random 64-character hex string
define('HMAC_SECRET_KEY', 'CHANGE_THIS_TO_RANDOM_64_HEX_CHARS'); // 64 hex chars = 32 bytes

// RBAC Configuration
define('RBAC_ENABLED', true);

// Activity Logging Configuration
define('ACTIVITY_LOGGING_ENABLED', true);

// Session configuration
define('SESSION_LIFETIME', 3600); // 1 hour in seconds
define('SESSION_NAME', 'SECURE_SESSION');

// SSL/HTTPS configuration
define('HTTPS_ENABLED', true);
// SSL verification for outbound HTTPS requests (should be true in production)
// Set to false only for development with self-signed certificates (not recommended)
define('SSL_VERIFY_PEER', true);

// Application base URL
define('BASE_URL', 'https://localhost');
define('API_BASE_URL', 'https://localhost/api');

// Application environment
// Set to 'production' to hide detailed errors from users
// Set to 'development' to show detailed errors for debugging
define('APP_ENV', 'development'); // Change to 'production' in production

// Error reporting (based on environment)
if (defined('APP_ENV') && APP_ENV === 'production') {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Timezone
date_default_timezone_set('America/Chicago');

