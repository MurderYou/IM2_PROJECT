<?php
/**
 * logout.php
 * Clears the session completely and returns the user to the login page.
 */

session_start();

$_SESSION = [];

// Also remove the session cookie itself, not just the server-side data.
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

header('Location: login.php');
exit;