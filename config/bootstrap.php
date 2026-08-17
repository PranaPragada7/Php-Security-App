<?php
declare(strict_types=1);

/**
 * Load local settings when present and fall back to environment-based defaults.
 */
if (!function_exists('app_env')) {
    function app_env(string $name, ?string $default = null): ?string
    {
        $value = getenv($name);
        return $value === false ? $default : $value;
    }
}

$localSettings = __DIR__ . '/settings.php';
$exampleSettings = __DIR__ . '/settings.example.php';

require_once is_file($localSettings) ? $localSettings : $exampleSettings;
