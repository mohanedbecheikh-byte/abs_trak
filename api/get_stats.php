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

$studentId = currentStudentId();

$stmt = $pdo->prepare(
    "SELECT
        m.id,
        m.name,
        m.type,
        SUM(a.status = 'present') AS present_count,
        SUM(a.status = 'absent') AS absent_count,
        SUM(a.status = 'unknown') AS unknown_count,
        COUNT(a.id) AS recorded_count
     FROM modules m
     LEFT JOIN attendance a
       ON a.module_id = m.id
      AND a.student_id = ?
     GROUP BY m.id, m.name, m.type
     ORDER BY m.name"
);
$stmt->execute([$studentId]);

$rows = $stmt->fetchAll();

$totals = [
    'present' => 0,
    'absent' => 0,
    'unknown' => 0,
    'recorded' => 0,
];

foreach ($rows as $row) {
    $totals['present'] += (int)$row['present_count'];
    $totals['absent'] += (int)$row['absent_count'];
    $totals['unknown'] += (int)$row['unknown_count'];
    $totals['recorded'] += (int)$row['recorded_count'];
}

jsonResponse([
    'modules' => $rows,
    'totals' => $totals,
]);
