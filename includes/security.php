<?php

function securityPageNonce(): string
{
    static $nonce = null;
    if ($nonce === null) {
        $nonce = base64_encode(random_bytes(18));
    }
    return $nonce;
}

function isSecureRequest(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    $forwardedProto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    return $forwardedProto === 'https';
}

function applyTransportSecurityHeaders(): void
{
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    if (isSecureRequest()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function applyAppSecurityHeaders(): void
{
    applyTransportSecurityHeaders();
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    $csp = [
        "default-src 'self'",
        "base-uri 'self'",
        "form-action 'self'",
        "object-src 'none'",
        "frame-ancestors 'none'",
        "img-src 'self' data:",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
        "font-src 'self' https://fonts.gstatic.com",
        "script-src 'self'",
        "connect-src 'self'",
    ];
    header('Content-Security-Policy: ' . implode('; ', $csp));
}

function applyApiSecurityHeaders(): void
{
    applyTransportSecurityHeaders();
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Content-Type: application/json; charset=utf-8');
}

function applyLoginSecurityHeaders(string $nonce): void
{
    applyTransportSecurityHeaders();
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    $csp = [
        "default-src 'self'",
        "base-uri 'self'",
        "form-action 'self'",
        "object-src 'none'",
        "frame-ancestors 'none'",
        "img-src 'self' data:",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
        "font-src 'self' https://fonts.gstatic.com",
        "script-src 'self' 'nonce-" . $nonce . "'",
        "connect-src 'self'",
    ];
    header('Content-Security-Policy: ' . implode('; ', $csp));
}

function verifyFormCsrfOrFail(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrfToken(), $token)) {
        http_response_code(403);
        exit('Invalid request.');
    }
}

function clientIpAddress(): string
{
    $trustForwarded = strtolower((string)(getenv('TRUST_PROXY_HEADERS') ?: '0')) === '1';
    if ($trustForwarded) {
        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if (is_string($forwarded) && $forwarded !== '') {
            $parts = explode(',', $forwarded);
            $ip = trim($parts[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '0.0.0.0';
}

function rateLimitFilePath(string $bucket): string
{
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'abstrack_rate_limit';
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }
    return $dir . DIRECTORY_SEPARATOR . hash('sha256', $bucket) . '.json';
}

function rateLimitReadState(string $bucket): array
{
    $path = rateLimitFilePath($bucket);
    $fp = fopen($path, 'c+');
    if ($fp === false) {
        return [];
    }
    flock($fp, LOCK_EX);
    $raw = stream_get_contents($fp);
    $data = json_decode($raw ?: '[]', true);
    if (!is_array($data)) {
        $data = [];
    }
    return [$fp, $data];
}

function rateLimitWriteState($fp, array $data): void
{
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($data));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
}

function loginRateLimitStatus(string $email): array
{
    $window = (int)(getenv('LOGIN_RATE_WINDOW_SEC') ?: 900);
    $maxAttempts = (int)(getenv('LOGIN_RATE_MAX_ATTEMPTS') ?: 6);
    $now = time();
    $ip = clientIpAddress();
    $emailNorm = strtolower(trim($email));

    $buckets = [
        'ip:' . $ip,
        'ip_email:' . $ip . ':' . $emailNorm,
    ];

    $retryAfter = 0;
    foreach ($buckets as $bucket) {
        $state = rateLimitReadState($bucket);
        if (!is_array($state) || count($state) !== 2) {
            continue;
        }
        [$fp, $data] = $state;
        $attempts = array_values(array_filter($data['attempts'] ?? [], static function ($ts) use ($now, $window) {
            return is_int($ts) && $ts > ($now - $window);
        }));
        $data['attempts'] = $attempts;
        rateLimitWriteState($fp, $data);
        if (count($attempts) >= $maxAttempts) {
            $candidate = ($attempts[0] + $window) - $now;
            $retryAfter = max($retryAfter, $candidate);
        }
    }

    return [
        'blocked' => $retryAfter > 0,
        'retry_after' => max(0, $retryAfter),
    ];
}

function recordLoginFailure(string $email): void
{
    $now = time();
    $ip = clientIpAddress();
    $emailNorm = strtolower(trim($email));
    $buckets = [
        'ip:' . $ip,
        'ip_email:' . $ip . ':' . $emailNorm,
    ];

    foreach ($buckets as $bucket) {
        $state = rateLimitReadState($bucket);
        if (!is_array($state) || count($state) !== 2) {
            continue;
        }
        [$fp, $data] = $state;
        $attempts = $data['attempts'] ?? [];
        if (!is_array($attempts)) {
            $attempts = [];
        }
        $attempts[] = $now;
        $data['attempts'] = $attempts;
        rateLimitWriteState($fp, $data);
    }
}

function clearLoginFailures(string $email): void
{
    $ip = clientIpAddress();
    $emailNorm = strtolower(trim($email));
    $buckets = [
        'ip:' . $ip,
        'ip_email:' . $ip . ':' . $emailNorm,
    ];

    foreach ($buckets as $bucket) {
        $state = rateLimitReadState($bucket);
        if (!is_array($state) || count($state) !== 2) {
            continue;
        }
        [$fp, $data] = $state;
        $data['attempts'] = [];
        rateLimitWriteState($fp, $data);
    }
}

function validPasswordPolicy(string $password): bool
{
    if (strlen($password) < 10) {
        return false;
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return false;
    }
    if (!preg_match('/[a-z]/', $password)) {
        return false;
    }
    if (!preg_match('/\d/', $password)) {
        return false;
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        return false;
    }
    return true;
}
