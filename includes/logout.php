<?php
declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/auth.php';

function logout_current_user(): void
{
    if (isset($_SESSION['session_id'], $_SESSION['userid'])) {
        try {
            (new Auth())->destroySession((string) $_SESSION['session_id'], (int) $_SESSION['userid']);
        } catch (Throwable $error) {
            error_log('Application session revocation failed: ' . $error->getMessage());
        }
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $parameters = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $parameters['path'],
            $parameters['domain'],
            $parameters['secure'],
            $parameters['httponly']
        );
    }

    session_destroy();
}
