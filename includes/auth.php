<?php

if (session_status() === PHP_SESSION_NONE) {
    $forwardedProto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $forwardedProto === 'https';
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Strict',
        'secure' => $isHttps,
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
}

function requireLogin(): void
{
    enforceSessionSecurity();
    if (empty($_SESSION['student_id'])) {
        header('Location: /login.php');
        exit;
    }
}

function currentStudentId(): ?int
{
    if (!isset($_SESSION['student_id'])) {
        return null;
    }
    return (int)$_SESSION['student_id'];
}

function currentStudentName(): string
{
    return $_SESSION['student_name'] ?? '';
}

function requireAdminLogin(): void
{
    enforceSessionSecurity();
    if (empty($_SESSION['admin_id'])) {
        header('Location: /admin_login.php');
        exit;
    }
}

function currentAdminId(): ?int
{
    if (!isset($_SESSION['admin_id'])) {
        return null;
    }
    return (int)$_SESSION['admin_id'];
}

function currentAdminEmail(): string
{
    return $_SESSION['admin_email'] ?? '';
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function requireCsrfHeader(): void
{
    $incoming = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $token = csrfToken();
    if (!hash_equals($token, $incoming)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }
}

function initializeAuthenticatedSession(int $studentId, string $studentName): void
{
    session_regenerate_id(true);
    $_SESSION['student_id'] = $studentId;
    $_SESSION['student_name'] = $studentName;
    $_SESSION['session_ua_hash'] = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
    $_SESSION['last_activity_at'] = time();
    csrfToken();
}

function initializeAdminSession(int $adminId, string $adminEmail): void
{
    session_regenerate_id(true);
    $_SESSION['admin_id'] = $adminId;
    $_SESSION['admin_email'] = $adminEmail;
    $_SESSION['session_ua_hash'] = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
    $_SESSION['last_activity_at'] = time();
    csrfToken();
}

function enforceSessionSecurity(): void
{
    if (empty($_SESSION['student_id']) && empty($_SESSION['admin_id'])) {
        return;
    }

    $idleTimeout = (int)(getenv('SESSION_IDLE_TIMEOUT_SEC') ?: 1800);
    $lastActivity = (int)($_SESSION['last_activity_at'] ?? 0);
    if ($lastActivity > 0 && (time() - $lastActivity) > $idleTimeout) {
        $_SESSION = [];
        session_destroy();
        return;
    }

    $expectedUa = $_SESSION['session_ua_hash'] ?? '';
    $currentUa = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
    if (!is_string($expectedUa) || $expectedUa === '' || !hash_equals($expectedUa, $currentUa)) {
        $_SESSION = [];
        session_destroy();
        return;
    }

    $_SESSION['last_activity_at'] = time();
}
