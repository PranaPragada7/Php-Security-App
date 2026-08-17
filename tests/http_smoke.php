<?php
declare(strict_types=1);

$baseUrl = rtrim((string) (getenv('TEST_BASE_URL') ?: 'http://127.0.0.1'), '/');
$cookieJar = tempnam(sys_get_temp_dir(), 'cipherdesk-cookies-');
$username = 'smoke_' . bin2hex(random_bytes(4));
$password = 'Smoke-Test-Password-42';

function http_request(string $method, string $url, string $cookieJar, array $form = []): array
{
    $handle = curl_init($url);
    curl_setopt_array($handle, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => $cookieJar,
        CURLOPT_COOKIEFILE => $cookieJar,
        CURLOPT_TIMEOUT => 15,
    ]);

    if ($form !== []) {
        curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($form));
        curl_setopt($handle, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    }

    $response = curl_exec($handle);
    if ($response === false) {
        throw new RuntimeException('HTTP request failed: ' . curl_error($handle));
    }

    $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
    $headerSize = (int) curl_getinfo($handle, CURLINFO_HEADER_SIZE);
    curl_close($handle);

    return [
        'status' => $status,
        'headers' => substr($response, 0, $headerSize),
        'body' => substr($response, $headerSize),
    ];
}
function smoke_check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('HTTP smoke test failed: ' . $message);
    }
}

function csrf_from(string $html): string
{
    if (!preg_match('/name="csrf_token" value="([a-f0-9]{64})"/', $html, $matches)) {
        throw new RuntimeException('CSRF token was not found in the response.');
    }
    return $matches[1];
}

try {
    $health = http_request('GET', $baseUrl . '/api/health.php', $cookieJar);
    smoke_check($health['status'] === 200, 'health endpoint is available');
    smoke_check(str_contains($health['body'], '"status":"ok"'), 'database health is reported');

    $loginPage = http_request('GET', $baseUrl . '/index.php', $cookieJar);
    smoke_check($loginPage['status'] === 200, 'login page loads');
    smoke_check(str_contains($loginPage['body'], 'Welcome to CipherDesk'), 'login page branding is rendered');
    smoke_check(str_contains(strtolower($loginPage['headers']), 'x-frame-options: deny'), 'security headers are present');

    $registrationPage = http_request('GET', $baseUrl . '/register.php', $cookieJar);
    $registration = http_request('POST', $baseUrl . '/register.php', $cookieJar, [
        'csrf_token' => csrf_from($registrationPage['body']),
        'register' => '1',
        'name' => 'Smoke Test User',
        'username' => $username,
        'password' => $password,
    ]);
    smoke_check($registration['status'] === 200, 'registration request completes');
    smoke_check(str_contains($registration['body'], 'Account created successfully'), 'registration succeeds');

    $freshLoginPage = http_request('GET', $baseUrl . '/index.php', $cookieJar);
    $login = http_request('POST', $baseUrl . '/index.php', $cookieJar, [
        'csrf_token' => csrf_from($freshLoginPage['body']),
        'login' => '1',
        'username' => $username,
        'password' => $password,
    ]);
    smoke_check($login['status'] === 302, 'successful login redirects');
    smoke_check(str_contains(strtolower($login['headers']), 'location: dashboard.php'), 'login redirects to dashboard');

    $dashboard = http_request('GET', $baseUrl . '/dashboard.php', $cookieJar);
    smoke_check($dashboard['status'] === 200, 'authenticated dashboard loads');
    smoke_check(str_contains($dashboard['body'], 'CipherDesk'), 'dashboard branding is rendered');

    $forbiddenConfig = http_request('GET', $baseUrl . '/config/settings.example.php', $cookieJar);
    smoke_check($forbiddenConfig['status'] === 403, 'configuration directory is not web-accessible');

    fwrite(STDOUT, "HTTP smoke test passed.\n");
} finally {
    if (is_file($cookieJar)) {
        unlink($cookieJar);
    }
}
