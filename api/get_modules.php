<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';

requireLogin();
applyApiSecurityHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$stmt = $pdo->query(
    "SELECT id, name, type, day_of_week, time_start, time_end
     FROM modules
     ORDER BY FIELD(day_of_week,'Samedi','Dimanche','Lundi','Mardi','Mercredi','Jeudi'), time_start"
);

jsonResponse($stmt->fetchAll());
