<?php
declare(strict_types=1);

/**
 * Environment-backed settings.
 *
 * Copy this file to settings.php only when a local PHP configuration file is
 * preferable to environment variables. Never commit production secrets.
 */

if (!function_exists('app_env')) {
    function app_env(string $name, ?string $default = null): ?string
    {
        $value = getenv($name);
        return $value === false ? $default : $value;
    }
}

if (!function_exists('app_env_bool')) {
    function app_env_bool(string $name, bool $default): bool
    {
        $value = app_env($name);
        if ($value === null) {
            return $default;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $parsed ?? $default;
    }
}

if (!function_exists('define_app_constant')) {
    function define_app_constant(string $name, mixed $value): void
    {
        if (!defined($name)) {
            define($name, $value);
        }
    }
}

$environment = strtolower((string) app_env('APP_ENV', 'development'));
if (!in_array($environment, ['development', 'test', 'production'], true)) {
    throw new RuntimeException('APP_ENV must be development, test, or production.');
}

define_app_constant('APP_ENV', $environment);
define_app_constant('APP_NAME', app_env('APP_NAME', 'CipherDesk'));
define_app_constant('BASE_URL', rtrim((string) app_env('BASE_URL', 'http://127.0.0.1:8080'), '/'));
define_app_constant('API_BASE_URL', rtrim((string) app_env('API_BASE_URL', BASE_URL . '/api'), '/'));
define_app_constant('SESSION_NAME', app_env('SESSION_NAME', 'CIPHERDESK_SESSION'));
define_app_constant('SESSION_LIFETIME', max(300, (int) app_env('SESSION_LIFETIME', '3600')));
define_app_constant('HTTPS_ENABLED', app_env_bool('HTTPS_ENABLED', APP_ENV === 'production'));
define_app_constant('SSL_VERIFY_PEER', app_env_bool('SSL_VERIFY_PEER', true));
define_app_constant('RBAC_ENABLED', true);
define_app_constant('ACTIVITY_LOGGING_ENABLED', app_env_bool('ACTIVITY_LOGGING_ENABLED', true));
define_app_constant('ROOT_USERNAME', app_env('ROOT_USERNAME', 'admin'));
$trustedProxies = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) app_env('TRUSTED_PROXIES', ''))
)));
define_app_constant('TRUSTED_PROXIES', $trustedProxies);

$developmentAesKey = hash('sha256', 'cipherdesk-development-aes-key');
$developmentHmacKey = hash('sha256', 'cipherdesk-development-hmac-key');
$aesKey = (string) app_env('AES_KEY', APP_ENV === 'production' ? '' : $developmentAesKey);
$hmacKey = (string) app_env('HMAC_SECRET_KEY', APP_ENV === 'production' ? '' : $developmentHmacKey);

foreach (['AES_KEY' => $aesKey, 'HMAC_SECRET_KEY' => $hmacKey] as $keyName => $keyValue) {
    if (strlen($keyValue) !== 64 || !ctype_xdigit($keyValue)) {
        throw new RuntimeException($keyName . ' must be a 64-character hexadecimal secret.');
    }
}

define_app_constant('AES_KEY', strtolower($aesKey));
define_app_constant('AES_METHOD', 'AES-256-CBC');
define_app_constant('HMAC_SECRET_KEY', strtolower($hmacKey));

$db_config = [
    'host' => app_env('DB_HOST', '127.0.0.1'),
    'port' => app_env('DB_PORT', '3306'),
    'database' => app_env('DB_DATABASE', 'cipherdesk'),
    'username' => app_env('DB_USERNAME', 'cipherdesk'),
    'password' => app_env('DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
];

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
    $db_config['host'],
    $db_config['port'],
    $db_config['database'],
    $db_config['charset']
);

$pdo_options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

date_default_timezone_set((string) app_env('APP_TIMEZONE', 'UTC'));
error_reporting(E_ALL);
ini_set('display_errors', APP_ENV === 'production' ? '0' : '1');
ini_set('log_errors', '1');

