<?php

require_once __DIR__ . '/env.php';

$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbPort = getenv('DB_PORT') ?: '5432';
$dbName = getenv('DB_NAME') ?: 'postgres';
$dbUser = getenv('DB_USER') ?: 'postgres';
$dbPass = getenv('DB_PASS');
if ($dbPass === false) {
    $dbPass = '';
}
$dbSslMode = getenv('DB_SSLMODE') ?: 'require';
$dbSchema = getenv('DB_SCHEMA') ?: 'public';

$dsn = 'pgsql:host=' . $dbHost
    . ';port=' . $dbPort
    . ';dbname=' . $dbName
    . ';sslmode=' . $dbSslMode
    . ';options=--search_path=' . $dbSchema;

try {
    $pdo = new PDO(
        $dsn,
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'DB connection failed']);
    exit;
}
