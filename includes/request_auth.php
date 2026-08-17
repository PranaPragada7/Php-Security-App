<?php
declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/auth.php';

function normalized_request_headers(): array
{
    $headers = [];

    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            $headers[strtolower((string) $name)] = trim((string) $value);
        }
    }

    foreach ($_SERVER as $name => $value) {
        if (str_starts_with($name, 'HTTP_')) {
            $headerName = strtolower(str_replace('_', '-', substr($name, 5)));
            $headers[$headerName] ??= trim((string) $value);
        }
    }

    return $headers;
}
function request_credentials(): array
{
    $headers = normalized_request_headers();

    return [
        'session_id' => (string) ($_SESSION['session_id'] ?? $headers['x-session-id'] ?? ''),
        'token' => (string) ($_SESSION['token'] ?? $headers['x-token'] ?? ''),
    ];
}

function authenticated_request_user(Auth $auth): array|false
{
    $credentials = request_credentials();
    if ($credentials['session_id'] === '' || $credentials['token'] === '') {
        return false;
    }

    return $auth->verifySession($credentials['session_id'], $credentials['token']);
}
