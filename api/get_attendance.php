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
$moduleId = (int)($_GET['module_id'] ?? 0);
$semester = trim($_GET['semester'] ?? '2025-S2');

if ($moduleId < 1) {
    jsonResponse(['error' => 'Invalid module_id'], 400);
}
if (!preg_match('/^[0-9]{4}-S[1-2]$/', $semester)) {
    jsonResponse(['error' => 'Invalid semester'], 400);
}

$moduleExists = $pdo->prepare('SELECT 1 FROM modules WHERE id = ? LIMIT 1');
$moduleExists->execute([$moduleId]);
if (!$moduleExists->fetchColumn()) {
    jsonResponse(['error' => 'Module not found'], 404);
}

$stmt = $pdo->prepare(
    "SELECT
        w.id AS week_id,
        w.week_number,
        w.date_start,
        w.date_end,
        COALESCE(a.status, 'unknown') AS status
     FROM weeks w
     LEFT JOIN attendance a
        ON a.week_id = w.id
        AND a.student_id = ?
        AND a.module_id = ?
     WHERE w.semester = ?
     ORDER BY w.week_number"
);

$stmt->execute([$studentId, $moduleId, $semester]);
jsonResponse($stmt->fetchAll());
