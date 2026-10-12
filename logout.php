<?php
/**
 * logout.php
 * Destroys the current session completely and returns to the login page.
 */
require_once __DIR__ . '/config/db.php'; // starts the session so we can end it

$_SESSION = [];

// Also clear the session cookie itself.
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
