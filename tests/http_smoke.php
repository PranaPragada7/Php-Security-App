<?php
declare(strict_types=1);

$baseUrl = rtrim((string) (getenv('TEST_BASE_URL') ?: 'http://127.0.0.1'), '/');
$cookieJar = tempnam(sys_get_temp_dir(), 'cipherdesk-cookies-');
$adminCookieJar = tempnam(sys_get_temp_dir(), 'cipherdesk-admin-');
$username = 'smoke_' . bin2hex(random_bytes(4));
$password = 'Smoke-Test-Password-42';

function http_request(string $method, string $url, string $cookieJar, array $form = [], ?string $csrf = null): array
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
        curl_setopt($handle, CURLOPT_POSTFIELDS, $csrf === null ? http_build_query($form) : json_encode($form));
        curl_setopt($handle, CURLOPT_HTTPHEADER, $csrf === null
            ? ['Content-Type: application/x-www-form-urlencoded']
            : ['Content-Type: application/json', 'X-CSRF-Token: ' . $csrf]);
    }

    $response = curl_exec($handle);
    if ($response === false) {
        throw new RuntimeException('HTTP request failed: ' . curl_error($handle));
    }

    $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
    $headerSize = (int) curl_getinfo($handle, CURLINFO_HEADER_SIZE);

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
    $malformedRegistration = http_request('POST', $baseUrl . '/register.php', $cookieJar, [
        'csrf_token' => csrf_from($registration['body']), 'register' => '1',
        'name' => ['invalid'], 'username' => ['invalid'], 'password' => $password,
    ]);
    smoke_check($malformedRegistration['status'] === 200, 'malformed registration renders without a server error');
    smoke_check(str_contains($malformedRegistration['body'], 'Username must be a string'), 'malformed registration shows a validation error');

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
    $job = http_request('POST', $baseUrl . '/api/jobs.php', $cookieJar, [
        'job_name' => 'Zero OPN record', 'opn_number' => '0',
    ], csrf_from($dashboard['body']));
    smoke_check($job['status'] === 201, 'zero OPN can be encrypted and saved');
    $jobId = json_decode($job['body'], true)['jobid'];

    $forbiddenConfig = http_request('GET', $baseUrl . '/config/settings.example.php', $cookieJar);
    smoke_check($forbiddenConfig['status'] === 403, 'configuration directory is not web-accessible');
    foreach (['/tests/run.php', '/tests/http_smoke.php', '/.git/config', '/.env'] as $privatePath) {
        $privateResource = http_request('GET', $baseUrl . $privatePath, $cookieJar);
        smoke_check($privateResource['status'] === 403, "$privatePath is not web-accessible");
    }

    // Exercise the actual API boundary, not only Validator methods.
    $adminPage = http_request('GET', $baseUrl . '/index.php', $adminCookieJar);
    $adminLogin = http_request('POST', $baseUrl . '/index.php', $adminCookieJar, [
        'csrf_token' => csrf_from($adminPage['body']),
        'login' => '1',
        'username' => getenv('ADMIN_USERNAME') ?: 'admin',
        'password' => getenv('ADMIN_PASSWORD') ?: 'CI-Administrator-Password-42',
    ]);
    smoke_check($adminLogin['status'] === 302, 'administrator login succeeds');
    $adminDashboard = http_request('GET', $baseUrl . '/dashboard.php', $adminCookieJar);
    $csrf = csrf_from($adminDashboard['body']);
    $adminJobs = http_request('GET', $baseUrl . '/api/jobs.php', $adminCookieJar);
    $savedJob = array_values(array_filter(
        json_decode($adminJobs['body'], true)['jobs'],
        static fn (array $row): bool => (string) $row['jobid'] === (string) $jobId
    ));
    smoke_check(count($savedJob) === 1 && $savedJob[0]['opn_number'] === '0', 'zero OPN decrypts intact');
    smoke_check($savedJob[0]['hmac_verified'] === true, 'zero OPN retains valid integrity');
    foreach ([
        ['/api/register.php', ['username' => [], 'password' => $password, 'name' => 'Test User']],
        ['/api/register.php', ['username' => 'valid_user', 'password' => $password, 'name' => 'Test User', 'email' => []]],
        ['/api/jobs.php', ['job_name' => 'Test job', 'opn_number' => '12345', 'clear_text_data' => []]],
        ['/api/users.php', ['userid' => 1, 'role' => []]],
        ['/api/users.php', ['userid' => [42], 'role' => 'user']],
    ] as [$path, $payload]) {
        $method = $path === '/api/users.php' ? 'PATCH' : 'POST';
        $result = http_request($method, $baseUrl . $path, $adminCookieJar, $payload, $csrf);
        smoke_check($result['status'] === 400, "$path rejects array fields");
        smoke_check(isset(json_decode($result['body'], true)['error']), "$path returns a JSON error");
    }
    $logs = http_request('GET', $baseUrl . '/api/activity_logs.php?activity_type%5B%5D=LOGIN', $adminCookieJar);
    smoke_check($logs['status'] === 400, 'activity logs reject array filters');
    $logsPage = http_request('GET', $baseUrl . '/activity_logs.php?activity_type%5B%5D=LOGIN', $adminCookieJar);
    smoke_check($logsPage['status'] === 400, 'activity log page rejects array filters');
    foreach (['userid', 'limit', 'offset'] as $numericFilter) {
        foreach (['/api/activity_logs.php', '/activity_logs.php'] as $logsPath) {
            $invalidFilter = http_request('GET', $baseUrl . $logsPath . '?' . $numericFilter . '%5B%5D=42', $adminCookieJar);
            smoke_check($invalidFilter['status'] === 400, 'activity logs reject array-valued numeric filters');
        }
    }
    foreach (['/api/users.php', '/api/users.php?id%5B%5D=42'] as $deletePath) {
        $invalidDelete = http_request('DELETE', $baseUrl . $deletePath, $adminCookieJar, ['id' => [42]], $csrf);
        smoke_check($invalidDelete['status'] === 400, 'array ID cannot select a different user for deletion');
    }

    $getLogout = http_request('GET', $baseUrl . '/dashboard.php?logout=1', $cookieJar);
    smoke_check($getLogout['status'] === 200, 'GET logout does not change the session');
    $invalidLogout = http_request('POST', $baseUrl . '/dashboard.php', $cookieJar, ['logout' => '1']);
    smoke_check($invalidLogout['status'] === 403, 'logout without CSRF is rejected');
    $logout = http_request('POST', $baseUrl . '/dashboard.php', $cookieJar, [
        'logout' => '1', 'csrf_token' => csrf_from($getLogout['body']),
    ]);
    smoke_check($logout['status'] === 302, 'valid logout redirects');
    $revoked = http_request('GET', $baseUrl . '/api/jobs.php', $cookieJar);
    smoke_check($revoked['status'] === 401, 'logout revokes access');

    fwrite(STDOUT, "HTTP smoke test passed.\n");
} finally {
    if (is_file($adminCookieJar)) {
        unlink($adminCookieJar);
    }
    if (is_file($cookieJar)) {
        unlink($cookieJar);
    }
}
