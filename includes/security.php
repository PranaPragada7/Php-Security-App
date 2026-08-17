<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

function request_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }

    $remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    if (
        in_array($remoteAddress, TRUSTED_PROXIES, true)
        && strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
    ) {
        return true;
    }

    return isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443;
}

function apply_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: camera=(), geolocation=(), microphone=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; font-src 'self' https://cdnjs.cloudflare.com; img-src 'self' data:; connect-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
    header('Cache-Control: no-store, max-age=0');

    if (request_is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function enforce_https_if_required(): void
{
    if (!HTTPS_ENABLED || request_is_https() || headers_sent()) {
        return;
    }

    $path = $_SERVER['REQUEST_URI'] ?? '/';
    header('Location: ' . BASE_URL . $path, true, 302);
    exit;
}
