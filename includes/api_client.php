<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';

/**
 * Call the same-origin JSON API without hard-coded hosts or browser-visible
 * application tokens.
 */
function api_request(string $method, string $path, ?array $payload = null, array $query = []): array
{
    if (!extension_loaded('curl')) {
        return ['status' => 0, 'data' => ['error' => 'The PHP cURL extension is required.']];
    }

    $url = API_BASE_URL . '/' . ltrim($path, '/');
    if ($query !== []) {
        $url .= '?' . http_build_query($query);
    }

    $csrfToken = csrf_token();
    $sessionCookie = session_name() . '=' . session_id();

    $headers = [
        'Accept: application/json',
        'X-CSRF-Token: ' . $csrfToken,
    ];

    if ($payload !== null) {
        $headers[] = 'Content-Type: application/json';
    }

    // Release PHP's file-session lock before the API opens the same session.
    // Without this, a page calling its own API blocks until the cURL timeout.
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    $handle = curl_init($url);
    curl_setopt_array($handle, [
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => SSL_VERIFY_PEER,
        CURLOPT_SSL_VERIFYHOST => SSL_VERIFY_PEER ? 2 : 0,
        CURLOPT_COOKIE => $sessionCookie,
    ]);

    if ($payload !== null) {
        curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($payload, JSON_THROW_ON_ERROR));
    }

    $body = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
    $curlError = curl_error($handle);
    curl_close($handle);

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if ($body === false || $status === 0) {
        error_log('Internal API request failed: ' . $curlError);
        return ['status' => 0, 'data' => ['error' => 'The application service is temporarily unavailable.']];
    }

    $data = json_decode($body, true);
    if (!is_array($data)) {
        return ['status' => $status, 'data' => ['error' => 'The application service returned an invalid response.']];
    }

    return ['status' => $status, 'data' => $data];
}
