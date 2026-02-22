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
     ORDER BY
       CASE day_of_week
         WHEN 'Samedi' THEN 1
         WHEN 'Dimanche' THEN 2
         WHEN 'Lundi' THEN 3
         WHEN 'Mardi' THEN 4
         WHEN 'Mercredi' THEN 5
         WHEN 'Jeudi' THEN 6
         ELSE 7
       END,
       time_start"
);

jsonResponse($stmt->fetchAll());
