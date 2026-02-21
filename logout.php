<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/security.php';

applyAppSecurityHeaders();

$adminWasLoggedIn = currentAdminId() !== null;

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'] ?? 'Strict',
    ]);
}

session_destroy();
header('Location: ' . ($adminWasLoggedIn ? '/admin_login.php' : '/login.php'));
exit;
