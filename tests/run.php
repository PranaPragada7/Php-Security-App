<?php
declare(strict_types=1);

putenv('APP_ENV=test');
putenv('AES_KEY=4f0e7e281aa92b6ad9770b2601bf0ec7caee4158373ed57585a5ed742cab8914');
putenv('HMAC_SECRET_KEY=724d6d3ba4b2a1d099985ed33c2b64fa2216389602967285b78b18ae0e2bab60');

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/crypt.php';
require_once __DIR__ . '/../includes/hmac.php';
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/request_auth.php';

$tests = 0;

function check(bool $condition, string $message): void
{
    global $tests;
    $tests++;
    if (!$condition) {
        throw new RuntimeException('Test failed: ' . $message);
    }
}

check(APP_ENV === 'test', 'test environment is loaded');
check(strlen(AES_KEY) === 64 && ctype_xdigit(AES_KEY), 'AES key is validated');
check(strlen(HMAC_SECRET_KEY) === 64 && ctype_xdigit(HMAC_SECRET_KEY), 'HMAC key is validated');

$plaintext = 'OPN-8472-ALPHA';
$encryptedOne = AES::_encrypt($plaintext);
$encryptedTwo = AES::_encrypt($plaintext);
check($encryptedOne !== $plaintext, 'AES does not return plaintext');
check($encryptedOne !== $encryptedTwo, 'AES uses a fresh IV');
check(AES::_decrypt($encryptedOne) === $plaintext, 'AES round trip succeeds');

$invalidCiphertextRejected = false;
try {
    AES::_decrypt('not-base64!');
} catch (Throwable) {
    $invalidCiphertextRejected = true;
}
check($invalidCiphertextRejected, 'invalid ciphertext is rejected');

$signature = HMAC::generateForJob('Quarterly export', $plaintext);
check(HMAC::verifyJob('Quarterly export', $plaintext, $signature), 'HMAC verifies original data');
check(!HMAC::verifyJob('Changed export', $plaintext, $signature), 'HMAC rejects modified data');
$delimiterSignature = HMAC::generateForJob('alpha|beta', 'gamma');
check(!HMAC::verifyJob('alpha', 'beta|gamma', $delimiterSignature), 'HMAC field encoding is unambiguous');

check(Validator::validateUsername('prana_7')['valid'], 'valid username accepted');
check(!Validator::validateUsername('bad name')['valid'], 'username whitespace rejected');
check(!Validator::validatePassword('short')['valid'], 'short password rejected');
check(Validator::validatePassword('Correct-Horse-42')['valid'], 'strong-length password accepted');
check(Validator::validateEmail('user@example.test')['valid'], 'reserved test email accepted');
check(!Validator::validateJobName('<script>')['valid'], 'HTML-like job name rejected');

check(RBAC::canManageUsers(RBAC::ROLE_ADMIN), 'admin can manage users');
check(!RBAC::canManageUsers(RBAC::ROLE_USER), 'standard user cannot manage users');
check(RBAC::canSubmitJobs(RBAC::ROLE_USER), 'standard user can submit jobs');
check(!RBAC::canSubmitJobs(RBAC::ROLE_GUEST), 'guest cannot submit jobs');

csrf_init();
$csrfToken = csrf_token();
check(strlen($csrfToken) === 64, 'CSRF token has expected entropy');
check(csrf_validate($csrfToken), 'CSRF token verifies');
check(!csrf_validate(str_repeat('0', 64)), 'incorrect CSRF token is rejected');

$_SESSION['session_id'] = 'server-side-session';
$_SESSION['token'] = 'server-side-token';
$_SERVER['HTTP_X_SESSION_ID'] = 'browser-supplied-session';
$_SERVER['HTTP_X_TOKEN'] = 'browser-supplied-token';
$credentials = request_credentials();
check($credentials['session_id'] === 'server-side-session', 'server session credentials take precedence');
check($credentials['token'] === 'server-side-token', 'server token is not replaced by a header');

$registerPage = file_get_contents(__DIR__ . '/../register.php');
$registerApi = file_get_contents(__DIR__ . '/../api/register.php');
$manageUsersPage = file_get_contents(__DIR__ . '/../manage_users.php');
check(!str_contains($registerPage, 'name="role"'), 'public registration has no role selector');
check(str_contains($registerApi, '$role = RBAC::ROLE_USER;'), 'public registration forces the standard role');
check(!str_contains($manageUsersPage, 'SESSION_TOKEN'), 'admin page does not expose session tokens');
check(!str_contains($manageUsersPage, 'https://localhost'), 'admin API path is portable');

fwrite(STDOUT, sprintf("%d checks passed.%s", $tests, PHP_EOL));
