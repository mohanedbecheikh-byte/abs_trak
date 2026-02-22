<?php

require_once __DIR__ . '/env.php';

function readEnv(string $key): ?string
{
    $value = getenv($key);
    if ($value === false) {
        return null;
    }
    $trimmed = trim((string)$value);
    return $trimmed === '' ? null : $trimmed;
}

$dbUrl = readEnv('DATABASE_URL') ?? readEnv('DB_URL') ?? readEnv('SUPABASE_DB_URL');
$dbHost = readEnv('DB_HOST') ?? 'localhost';
$dbPort = readEnv('DB_PORT') ?? '5432';
$dbName = readEnv('DB_NAME') ?? 'postgres';
$dbUser = readEnv('DB_USER') ?? 'postgres';
$dbPass = readEnv('DB_PASS') ?? '';
$dbSslMode = readEnv('DB_SSLMODE') ?? 'require';
$dbSchema = readEnv('DB_SCHEMA') ?? 'public';

if ($dbUrl !== null) {
    $parts = parse_url($dbUrl);
    if (is_array($parts)) {
        if (isset($parts['host']) && $parts['host'] !== '') {
            $dbHost = $parts['host'];
        }
        if (isset($parts['port']) && (int)$parts['port'] > 0) {
            $dbPort = (string)((int)$parts['port']);
        }
        if (isset($parts['path']) && $parts['path'] !== '') {
            $parsedDb = ltrim($parts['path'], '/');
            if ($parsedDb !== '') {
                $dbName = $parsedDb;
            }
        }
        if (isset($parts['user']) && $parts['user'] !== '') {
            $dbUser = $parts['user'];
        }
        if (isset($parts['pass'])) {
            $dbPass = $parts['pass'];
        }

        if (isset($parts['query']) && $parts['query'] !== '') {
            parse_str($parts['query'], $query);
            if (is_array($query)) {
                if (!empty($query['sslmode'])) {
                    $dbSslMode = (string)$query['sslmode'];
                }
                if (!empty($query['schema'])) {
                    $dbSchema = (string)$query['schema'];
                } elseif (!empty($query['search_path'])) {
                    $dbSchema = (string)$query['search_path'];
                }
            }
        }
    }
}

$dsnParts = [
    'host=' . $dbHost,
    'port=' . $dbPort,
    'dbname=' . $dbName,
    'sslmode=' . $dbSslMode,
];
if ($dbSchema !== '') {
    $dsnParts[] = 'options=--search_path=' . $dbSchema;
}
$dsn = 'pgsql:' . implode(';', $dsnParts);

try {
    $pdo = new PDO(
        $dsn,
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 10,
        ]
    );
} catch (PDOException $e) {
    error_log('DB connection failed: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'DB connection failed']);
    exit;
}
