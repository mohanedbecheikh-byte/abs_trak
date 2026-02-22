<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';

requireLogin();
applyApiSecurityHeaders();
requireCsrfHeader();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
if (strpos($contentType, 'application/json') !== 0) {
    jsonResponse(['error' => 'Unsupported content type'], 415);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    jsonResponse(['error' => 'Invalid JSON payload'], 400);
}

$studentId = currentStudentId();
$moduleId = (int)($data['module_id'] ?? 0);
$weekId = (int)($data['week_id'] ?? 0);
$newStatus = $data['status'] ?? '';

$allowed = ['present', 'absent', 'unknown'];
if ($moduleId < 1 || $weekId < 1 || !in_array($newStatus, $allowed, true)) {
    jsonResponse(['error' => 'Invalid payload'], 400);
}

$moduleExists = $pdo->prepare('SELECT 1 FROM modules WHERE id = ? LIMIT 1');
$moduleExists->execute([$moduleId]);
if (!$moduleExists->fetchColumn()) {
    jsonResponse(['error' => 'Module not found'], 404);
}

$weekExists = $pdo->prepare('SELECT 1 FROM weeks WHERE id = ? LIMIT 1');
$weekExists->execute([$weekId]);
if (!$weekExists->fetchColumn()) {
    jsonResponse(['error' => 'Week not found'], 404);
}

$stmt = $pdo->prepare(
    "INSERT INTO attendance (student_id, module_id, week_id, status)
     VALUES (?, ?, ?, ?)
     ON CONFLICT (student_id, module_id, week_id)
     DO UPDATE SET
       status = EXCLUDED.status,
       recorded_at = NOW()"
);
try {
    $stmt->execute([$studentId, $moduleId, $weekId, $newStatus]);
} catch (Throwable $e) {
    error_log('toggle_attendance failed: ' . $e->getMessage());
    jsonResponse(['error' => 'Unable to update attendance'], 500);
}

jsonResponse(['ok' => true, 'status' => $newStatus]);
