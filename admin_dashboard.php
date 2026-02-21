<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/security.php';

$nonce = securityPageNonce();
applyLoginSecurityHeaders($nonce);
requireAdminLogin();

function ensureAdminTable(PDO $pdo): void
{
    $stmt = $pdo->query("SHOW TABLES LIKE 'admins'");
    if (!$stmt->fetchColumn()) {
        throw new RuntimeException('Admins table is missing. Run the latest database migration.');
    }
}

function hasStudentLoginTrackingColumns(PDO $pdo): bool
{
    $columns = $pdo->query('SHOW COLUMNS FROM students')->fetchAll(PDO::FETCH_COLUMN, 0);
    if (!is_array($columns)) {
        return false;
    }
    return in_array('first_login_at', $columns, true) && in_array('last_login_at', $columns, true);
}

function setFlashMessage(string $type, string $text): void
{
    $_SESSION['admin_flash'] = ['type' => $type, 'text' => $text];
}

function readFlashMessage(): ?array
{
    if (!isset($_SESSION['admin_flash']) || !is_array($_SESSION['admin_flash'])) {
        return null;
    }
    $flash = $_SESSION['admin_flash'];
    unset($_SESSION['admin_flash']);
    return $flash;
}

function validAdminManagedPassword(string $password): bool
{
    return strlen($password) >= 8
        && preg_match('/[A-Za-z]/', $password)
        && preg_match('/\d/', $password);
}

function validModuleType(string $type): bool
{
    return in_array($type, ['COURS', 'TD', 'TP', 'ENLIGNE'], true);
}

try {
    ensureAdminTable($pdo);
} catch (Throwable $e) {
    http_response_code(500);
    exit('Server setup incomplete.');
}
$hasStudentLoginTracking = hasStudentLoginTrackingColumns($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyFormCsrfOrFail();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create_student') {
            $fullName = preg_replace('/\s+/', ' ', trim($_POST['full_name'] ?? ''));
            $email = strtolower(trim($_POST['email'] ?? ''));
            $groupName = trim($_POST['group_name'] ?? 'G1');
            $password = $_POST['password'] ?? '';

            if (strlen($fullName) < 3 || strlen($fullName) > 100) {
                throw new RuntimeException('Full name must be between 3 and 100 characters.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Invalid email format.');
            }
            if (!preg_match('/^[A-Za-z0-9_-]{1,10}$/', $groupName)) {
                throw new RuntimeException('Group name must be 1-10 chars (letters, numbers, _ or -).');
            }
            if (!validAdminManagedPassword($password)) {
                throw new RuntimeException('Password must be at least 8 characters and include letters and numbers.');
            }

            $stmt = $pdo->prepare('SELECT id FROM students WHERE email = ?');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                throw new RuntimeException('A student with this email already exists.');
            }

            $insert = $pdo->prepare(
                'INSERT INTO students (full_name, email, password_hash, group_name) VALUES (?, ?, ?, ?)'
            );
            $insert->execute([$fullName, $email, password_hash($password, PASSWORD_BCRYPT), $groupName]);
            setFlashMessage('success', 'Student account created.');
        } elseif ($action === 'update_group') {
            $studentId = (int)($_POST['student_id'] ?? 0);
            $groupName = trim($_POST['group_name'] ?? '');
            if ($studentId < 1) {
                throw new RuntimeException('Invalid student.');
            }
            if (!preg_match('/^[A-Za-z0-9_-]{1,10}$/', $groupName)) {
                throw new RuntimeException('Group name must be 1-10 chars (letters, numbers, _ or -).');
            }
            $upd = $pdo->prepare('UPDATE students SET group_name = ? WHERE id = ?');
            $upd->execute([$groupName, $studentId]);
            setFlashMessage('success', 'Student group updated.');
        } elseif ($action === 'reset_password') {
            $studentId = (int)($_POST['student_id'] ?? 0);
            $newPassword = $_POST['new_password'] ?? '';
            if ($studentId < 1) {
                throw new RuntimeException('Invalid student.');
            }
            if (!validAdminManagedPassword($newPassword)) {
                throw new RuntimeException('Password must be at least 8 characters and include letters and numbers.');
            }
            $upd = $pdo->prepare('UPDATE students SET password_hash = ? WHERE id = ?');
            $upd->execute([password_hash($newPassword, PASSWORD_BCRYPT), $studentId]);
            setFlashMessage('success', 'Student password reset.');
        } elseif ($action === 'delete_student') {
            $studentId = (int)($_POST['student_id'] ?? 0);
            $confirm = trim($_POST['confirm_text'] ?? '');
            if ($studentId < 1) {
                throw new RuntimeException('Invalid student.');
            }
            if ($confirm !== 'DELETE') {
                throw new RuntimeException('Type DELETE to confirm.');
            }
            $del = $pdo->prepare('DELETE FROM students WHERE id = ?');
            $del->execute([$studentId]);
            setFlashMessage('success', 'Student deleted.');
        } elseif ($action === 'create_module') {
            $name = preg_replace('/\s+/', ' ', trim($_POST['name'] ?? ''));
            $type = trim($_POST['type'] ?? '');

            if ($name === '' || strlen($name) > 100) {
                throw new RuntimeException('Module name is required (max 100 chars).');
            }
            if (!validModuleType($type)) {
                throw new RuntimeException('Invalid module type.');
            }

            $insertModule = $pdo->prepare(
                'INSERT INTO modules (name, type, day_of_week, time_start, time_end)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $insertModule->execute([$name, $type, null, null, null]);
            setFlashMessage('success', 'Module created.');
        } elseif ($action === 'update_module') {
            $moduleId = (int)($_POST['module_id'] ?? 0);
            $name = preg_replace('/\s+/', ' ', trim($_POST['name'] ?? ''));
            $type = trim($_POST['type'] ?? '');

            if ($moduleId < 1) {
                throw new RuntimeException('Invalid module.');
            }
            if ($name === '' || strlen($name) > 100) {
                throw new RuntimeException('Module name is required (max 100 chars).');
            }
            if (!validModuleType($type)) {
                throw new RuntimeException('Invalid module type.');
            }

            $updateModule = $pdo->prepare(
                'UPDATE modules
                 SET name = ?, type = ?, day_of_week = ?, time_start = ?, time_end = ?
                 WHERE id = ?'
            );
            $updateModule->execute([$name, $type, null, null, null, $moduleId]);
            setFlashMessage('success', 'Module updated.');
        } elseif ($action === 'delete_module') {
            $moduleId = (int)($_POST['module_id'] ?? 0);
            $confirm = trim($_POST['confirm_text'] ?? '');
            if ($moduleId < 1) {
                throw new RuntimeException('Invalid module.');
            }
            if ($confirm !== 'DELETE') {
                throw new RuntimeException('Type DELETE to confirm module deletion.');
            }
            $deleteModule = $pdo->prepare('DELETE FROM modules WHERE id = ?');
            $deleteModule->execute([$moduleId]);
            setFlashMessage('success', 'Module deleted.');
        } else {
            throw new RuntimeException('Unknown action.');
        }
    } catch (RuntimeException $e) {
        setFlashMessage('error', $e->getMessage());
    } catch (Throwable $e) {
        error_log('Admin action failed: ' . $e->getMessage());
        setFlashMessage('error', 'Action failed. Please verify input and try again.');
    }

    header('Location: /admin_dashboard.php');
    exit;
}

$csrf = csrfToken();
$adminEmail = currentAdminEmail();
$flash = readFlashMessage();

$metrics = [
    'students' => (int)$pdo->query('SELECT COUNT(*) FROM students')->fetchColumn(),
    'logged_students' => 0,
    'modules' => (int)$pdo->query('SELECT COUNT(*) FROM modules')->fetchColumn(),
    'attendance_records' => (int)$pdo->query('SELECT COUNT(*) FROM attendance')->fetchColumn(),
];
if ($hasStudentLoginTracking) {
    $metrics['logged_students'] = (int)$pdo->query('SELECT COUNT(*) FROM students WHERE first_login_at IS NOT NULL')->fetchColumn();
}

$attBreakdown = $pdo->query(
    "SELECT
        SUM(status = 'present') AS present_count,
        SUM(status = 'absent') AS absent_count
     FROM attendance"
)->fetch();
$presentTotal = (int)($attBreakdown['present_count'] ?? 0);
$absentTotal = (int)($attBreakdown['absent_count'] ?? 0);
$known = $presentTotal + $absentTotal;
$globalRate = $known > 0 ? round(($presentTotal / $known) * 100, 1) : 0.0;

$students = $pdo->query(
    $hasStudentLoginTracking
        ? "SELECT
            s.id,
            s.full_name,
            s.email,
            s.group_name,
            s.first_login_at,
            s.last_login_at,
            s.created_at,
            SUM(a.status = 'present') AS present_count,
            SUM(a.status = 'absent') AS absent_count
         FROM students s
         LEFT JOIN attendance a ON a.student_id = s.id
         GROUP BY s.id, s.full_name, s.email, s.group_name, s.first_login_at, s.last_login_at, s.created_at
         ORDER BY s.created_at DESC"
        : "SELECT
            s.id,
            s.full_name,
            s.email,
            s.group_name,
            NULL AS first_login_at,
            NULL AS last_login_at,
            s.created_at,
            SUM(a.status = 'present') AS present_count,
            SUM(a.status = 'absent') AS absent_count
         FROM students s
         LEFT JOIN attendance a ON a.student_id = s.id
         GROUP BY s.id, s.full_name, s.email, s.group_name, s.created_at
         ORDER BY s.created_at DESC"
)->fetchAll();

$loggedStudents = [];
if ($hasStudentLoginTracking) {
    $loggedStudents = $pdo->query(
        "SELECT id, full_name, email, first_login_at, last_login_at
         FROM students
         WHERE first_login_at IS NOT NULL
         ORDER BY first_login_at DESC"
    )->fetchAll();
}

$moduleStats = $pdo->query(
    "SELECT
        m.id,
        m.name,
        m.type,
        SUM(a.status = 'present') AS present_count,
        SUM(a.status = 'absent') AS absent_count
     FROM modules m
     LEFT JOIN attendance a ON a.module_id = m.id
     GROUP BY m.id, m.name, m.type
     ORDER BY m.name, m.type"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>AbsTrack Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Mono:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  :root {
    --bg:#030712;
    --bg-soft:#0b1121;
    --surface:#101a33;
    --surface-2:#0c152c;
    --line:rgba(148,163,184,.24);
    --line-strong:rgba(148,163,184,.36);
    --txt:#e2e8f0;
    --muted:#94a3b8;
    --brand:#2563eb;
    --brand-2:#0891b2;
    --ok:#10b981;
    --warn:#f59e0b;
    --danger:#ef4444;
    --shadow:0 18px 45px rgba(2,6,23,.45);
  }
  * { box-sizing:border-box; }
  html, body { width:100%; max-width:100%; overflow-x:hidden; }
  body {
    margin:0;
    min-height:100vh;
    padding:18px;
    color:var(--txt);
    font-family:'Syne',sans-serif;
    background:
      radial-gradient(1200px 520px at 0% -10%, rgba(37,99,235,.26), transparent 62%),
      radial-gradient(900px 460px at 100% 0%, rgba(8,145,178,.23), transparent 60%),
      linear-gradient(160deg, #030712 0%, #050d1e 100%);
  }
  .admin-shell {
    width:min(1580px, 100%);
    margin:0 auto;
    min-height:calc(100vh - 36px);
    display:grid;
    grid-template-columns:280px 1fr;
    border-radius:20px;
    border:1px solid var(--line);
    overflow:hidden;
    background:rgba(2,6,23,.76);
    box-shadow:var(--shadow);
    backdrop-filter:blur(10px);
  }
  .admin-shell, .side, .content, .panel, .card, .table-wrap { min-width:0; max-width:100%; }
  .side {
    padding:22px;
    border-right:1px solid var(--line);
    background:linear-gradient(180deg, rgba(15,23,42,.88) 0%, rgba(4,10,24,.92) 100%);
  }
  .logo { display:flex; align-items:center; gap:11px; margin-bottom:24px; }
  .logo-badge {
    width:40px;
    height:40px;
    border-radius:12px;
    border:1px solid var(--line-strong);
    display:grid;
    place-items:center;
    background:linear-gradient(145deg, rgba(37,99,235,.22), rgba(8,145,178,.24));
  }
  .logo-badge svg { width:24px; height:24px; filter: drop-shadow(0 6px 10px rgba(37,99,235,.35)); }
  .logo-text { font-size:18px; font-weight:800; letter-spacing:.2px; }
  .menu { display:grid; gap:9px; }
  .menu-btn {
    width:100%;
    border:1px solid var(--line);
    border-radius:12px;
    background:rgba(15,23,42,.5);
    color:var(--txt);
    padding:11px 12px;
    display:flex;
    align-items:center;
    gap:10px;
    font-size:13px;
    font-weight:700;
    letter-spacing:.1px;
    cursor:pointer;
    transition:.2s ease;
  }
  .menu-btn:hover { border-color:var(--line-strong); background:rgba(15,23,42,.72); }
  .menu-btn.active {
    border-color:rgba(56,189,248,.52);
    background:linear-gradient(135deg, rgba(37,99,235,.33), rgba(8,145,178,.24));
    box-shadow:0 0 0 1px rgba(37,99,235,.25) inset;
  }
  .menu-ico {
    width:26px;
    height:26px;
    border-radius:8px;
    display:grid;
    place-items:center;
    background:rgba(15,23,42,.85);
    border:1px solid rgba(148,163,184,.28);
    font-family:'DM Mono', monospace;
    font-size:10px;
    color:#bfdbfe;
  }
  .side-foot {
    margin-top:22px;
    padding-top:14px;
    border-top:1px solid var(--line);
    color:var(--muted);
    font-size:12px;
    line-height:1.55;
  }
  .content { padding:24px; overflow:auto; }
  .topbar {
    margin-bottom:18px;
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:14px;
    flex-wrap:wrap;
  }
  .title { font-size:30px; font-weight:800; line-height:1.08; }
  .subtitle { margin-top:6px; color:var(--muted); font-size:12px; font-family:'DM Mono', monospace; }
  .logout {
    text-decoration:none;
    color:#fff;
    border:1px solid var(--line-strong);
    background:rgba(15,23,42,.8);
    padding:10px 14px;
    border-radius:10px;
    font-size:13px;
    font-weight:700;
  }
  .flash { margin:0 0 14px; padding:11px 13px; border-radius:10px; font-size:13px; border:1px solid; }
  .flash.success { background:rgba(16,185,129,.16); border-color:rgba(16,185,129,.45); color:#bbf7d0; }
  .flash.error { background:rgba(239,68,68,.16); border-color:rgba(239,68,68,.42); color:#fecaca; }
  .cards {
    margin-bottom:16px;
    display:grid;
    grid-template-columns:repeat(auto-fit, minmax(170px,1fr));
    gap:12px;
  }
  .card {
    border:1px solid var(--line);
    border-radius:12px;
    background:linear-gradient(180deg, rgba(15,23,42,.8), rgba(10,17,34,.75));
    padding:13px;
  }
  .k { color:var(--muted); font-size:11px; text-transform:uppercase; letter-spacing:.65px; }
  .k.tight { margin-bottom:10px; }
  .v { margin-top:8px; font-family:'DM Mono', monospace; font-size:28px; }
  .hint { margin-top:10px; color:var(--muted); font-size:12px; line-height:1.5; }
  .panel {
    display:none;
    border:1px solid var(--line);
    border-radius:14px;
    background:linear-gradient(180deg, rgba(15,23,42,.74), rgba(7,13,28,.76));
    padding:16px;
    animation:fadeIn .15s ease;
  }
  .panel.active { display:block; }
  @keyframes fadeIn {
    from { opacity:.4; transform:translateY(3px); }
    to { opacity:1; transform:translateY(0); }
  }
  .section-head { margin-bottom:13px; }
  .section-head h2 { margin:0; font-size:20px; }
  .section-head p { margin:6px 0 0; color:var(--muted); font-size:12px; }
  .grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
  .table-wrap {
    width:100%;
    overflow:auto;
    border:1px solid var(--line);
    border-radius:12px;
    background:rgba(2,6,23,.4);
    -webkit-overflow-scrolling:touch;
  }
  table { width:100%; border-collapse:collapse; min-width:900px; }
  th, td {
    padding:11px 10px;
    border-bottom:1px solid rgba(148,163,184,.16);
    text-align:left;
    font-size:12px;
    font-family:'DM Mono', monospace;
    vertical-align:top;
  }
  th {
    position:sticky;
    top:0;
    background:rgba(15,23,42,.96);
    z-index:1;
    color:var(--muted);
    font-size:11px;
    text-transform:uppercase;
    letter-spacing:.45px;
  }
  .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
  .full { grid-column:1/-1; }
  label { display:block; margin-bottom:4px; color:var(--muted); font-size:12px; }
  input, select {
    width:100%;
    border:1px solid var(--line-strong);
    border-radius:9px;
    padding:9px 10px;
    color:var(--txt);
    background:rgba(2,6,23,.76);
    font-size:13px;
    font-family:'DM Mono', monospace;
  }
  .btn {
    border:1px solid rgba(37,99,235,.7);
    background:linear-gradient(135deg, var(--brand), var(--brand-2));
    color:#fff;
    border-radius:9px;
    padding:8px 11px;
    font-size:12px;
    font-weight:700;
    cursor:pointer;
    white-space:nowrap;
  }
  .btn.danger { border-color:rgba(239,68,68,.6); background:linear-gradient(135deg, #ef4444, #b91c1c); }
  .inline { display:flex; gap:7px; align-items:center; flex-wrap:wrap; }
  .inline input, .inline select { flex:1 1 140px; min-width:0; }
  .stack-actions { display:grid; gap:9px; min-width:290px; }
  .stack-actions > div {
    padding:8px;
    border:1px solid rgba(148,163,184,.14);
    border-radius:9px;
    background:rgba(2,6,23,.32);
  }
  .action-label {
    margin-bottom:6px;
    color:var(--muted);
    font-size:10px;
    text-transform:uppercase;
    letter-spacing:.5px;
  }
  .tag {
    display:inline-block;
    padding:3px 8px;
    border-radius:999px;
    border:1px solid transparent;
    font-size:11px;
    font-weight:700;
  }
  .tag.ok { color:#bbf7d0; background:rgba(16,185,129,.2); border-color:rgba(16,185,129,.42); }
  .tag.warn { color:#fde68a; background:rgba(245,158,11,.22); border-color:rgba(245,158,11,.4); }
  .tag.bad { color:#fecaca; background:rgba(239,68,68,.22); border-color:rgba(239,68,68,.4); }
  .mobile-only { display:none; }
  .student-cards,
  .login-cards,
  .module-cards { display:grid; gap:10px; }
  .student-card,
  .login-card,
  .module-card {
    border:1px solid var(--line);
    border-radius:12px;
    padding:11px;
    background:rgba(2,6,23,.46);
  }
  .card-head { display:flex; justify-content:space-between; gap:8px; align-items:center; margin-bottom:7px; }
  .card-title { font-size:14px; font-weight:700; }
  .card-sub { color:var(--muted); font-size:12px; margin-bottom:8px; word-break:break-word; }
  .card-metrics { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:8px; }
  .metric-chip {
    border:1px solid rgba(148,163,184,.26);
    border-radius:999px;
    padding:3px 8px;
    font-size:11px;
    color:#cbd5e1;
    background:rgba(15,23,42,.65);
  }
  .module-head { display:flex; justify-content:space-between; gap:8px; margin-bottom:8px; align-items:center; }
  .module-name { font-size:14px; font-weight:700; }
  .module-meta { color:var(--muted); font-size:12px; margin-bottom:9px; }
  .student-actions,
  .module-actions { display:grid; gap:8px; }
  .student-actions .inline,
  .module-actions .inline { flex-direction:column; align-items:stretch; }
  .student-actions .inline input,
  .student-actions .inline select,
  .student-actions .inline .btn,
  .module-actions .inline input,
  .module-actions .inline select,
  .module-actions .inline .btn {
    width:100%;
    flex:0 0 auto;
  }
  @media (max-width:1100px) {
    body { padding:10px; }
    .admin-shell { min-height:calc(100vh - 20px); grid-template-columns:1fr; }
    .side { border-right:none; border-bottom:1px solid var(--line); padding:14px; }
    .side-foot { display:none; }
    .menu { display:flex; gap:8px; overflow-x:auto; padding-bottom:3px; }
    .menu-btn { flex:0 0 auto; }
    .menu-ico { width:24px; height:24px; }
    .content { padding:14px; }
    .grid-2 { grid-template-columns:1fr; }
    .title { font-size:24px; }
    .cards { grid-template-columns:repeat(2, minmax(0,1fr)); }
    table { min-width:760px; }
  }
  @media (max-width:900px), (hover:none) and (pointer:coarse) {
    .admin-shell { overflow:visible; border-radius:14px; }
    .content { overflow:visible; padding:12px; }
    .topbar { margin-bottom:14px; }
    .menu { padding-bottom:5px; }
    .menu-btn { padding:10px 11px; }
    table { min-width:640px; }
    .stack-actions { min-width:220px; }
    .mobile-only { display:block; }
    .desktop-only { display:none; }
  }
  @media (max-width:760px) {
    .cards { grid-template-columns:1fr; }
    .panel { padding:12px; }
    .card { padding:11px; }
    table { min-width:580px; }
    .stack-actions { min-width:255px; }
    .stack-actions .inline { flex-direction:column; align-items:stretch; }
    .stack-actions .inline .btn { width:100%; }
    .module-card { padding:9px; }
    .module-meta { margin-bottom:6px; font-size:11px; }
    .module-actions { gap:6px; }
    .module-actions .inline { gap:5px; }
    .module-actions .inline input,
    .module-actions .inline select {
      padding:7px 8px;
      font-size:12px;
    }
    .module-actions .inline .btn {
      padding:7px 8px;
      font-size:11px;
    }
  }
  body.force-mobile { padding:8px; }
  body.force-mobile .admin-shell {
    min-height:calc(100vh - 16px);
    grid-template-columns:1fr;
    border-radius:12px;
    overflow:visible;
  }
  body.force-mobile .side {
    border-right:none;
    border-bottom:1px solid var(--line);
    padding:12px;
  }
  body.force-mobile .side-foot { display:none; }
  body.force-mobile .menu {
    display:flex;
    gap:8px;
    overflow-x:auto;
    padding-bottom:4px;
  }
  body.force-mobile .menu-btn { flex:0 0 auto; }
  body.force-mobile .content {
    padding:12px;
    overflow:visible;
  }
  body.force-mobile .topbar { margin-bottom:14px; }
  body.force-mobile .title { font-size:22px; }
  body.force-mobile .grid-2 { grid-template-columns:1fr; }
  body.force-mobile .cards { grid-template-columns:1fr; }
  body.force-mobile .panel { padding:11px; }
  body.force-mobile .card { padding:10px; }
  body.force-mobile .mobile-only { display:block; }
  body.force-mobile .desktop-only { display:none; }
  body.force-mobile .module-card { padding:9px; }
  body.force-mobile .module-meta { margin-bottom:6px; font-size:11px; }
  body.force-mobile .module-actions { gap:6px; }
  body.force-mobile .module-actions .inline { gap:5px; }
  body.force-mobile .module-actions .inline input,
  body.force-mobile .module-actions .inline select {
    padding:7px 8px;
    font-size:12px;
  }
  body.force-mobile .module-actions .inline .btn {
    padding:7px 8px;
    font-size:11px;
  }
</style>
</head>
<body>
<div class="admin-shell">
  <aside class="side">
    <div class="logo">
      <div class="logo-badge" aria-hidden="true">
        <svg viewBox="0 0 64 64">
          <rect x="9" y="10" width="18" height="44" rx="8" fill="#3b82f6"></rect>
          <rect x="37" y="10" width="18" height="44" rx="8" fill="#06b6d4"></rect>
          <path d="M27 22h10M27 32h10M27 42h10" stroke="#dbeafe" stroke-width="3" stroke-linecap="round"></path>
        </svg>
      </div>
      <div class="logo-text">AbsTrack Admin</div>
    </div>
    <nav class="menu" id="admin-menu">
      <button class="menu-btn active" data-panel="overview" type="button"><span class="menu-ico">OV</span><span>Overview</span></button>
      <button class="menu-btn" data-panel="students" type="button"><span class="menu-ico">ST</span><span>Students</span></button>
      <button class="menu-btn" data-panel="logins" type="button"><span class="menu-ico">LG</span><span>Login Activity</span></button>
      <button class="menu-btn" data-panel="modules" type="button"><span class="menu-ico">MD</span><span>Modules</span></button>
    </nav>
    <div class="side-foot">
      Signed in: <?php echo htmlspecialchars($adminEmail, ENT_QUOTES, 'UTF-8'); ?><br>
      Professional admin control system
    </div>
  </aside>

  <main class="content">
    <div class="topbar">
      <div>
        <div class="title">Admin Control Center</div>
        <div class="subtitle">Global presence rate <?php echo number_format($globalRate, 1); ?>%</div>
      </div>
      <a class="logout" href="/logout.php">Logout</a>
    </div>

    <?php if ($flash !== null): ?>
      <div class="flash <?php echo htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8'); ?>">
        <?php echo htmlspecialchars($flash['text'], ENT_QUOTES, 'UTF-8'); ?>
      </div>
    <?php endif; ?>

    <div class="cards">
      <div class="card"><div class="k">Students</div><div class="v"><?php echo (int)$metrics['students']; ?></div></div>
      <div class="card"><div class="k">Logged Students</div><div class="v"><?php echo (int)$metrics['logged_students']; ?></div></div>
      <div class="card"><div class="k">Modules</div><div class="v"><?php echo (int)$metrics['modules']; ?></div></div>
      <div class="card"><div class="k">Attendance</div><div class="v"><?php echo (int)$metrics['attendance_records']; ?></div></div>
    </div>

    <section class="panel active" id="panel-overview">
      <div class="section-head">
        <h2>Overview</h2>
        <p>Quick summary of attendance and system usage.</p>
      </div>
      <div class="grid-2">
        <div class="card">
          <div class="k">Present Records</div>
          <div class="v"><?php echo $presentTotal; ?></div>
        </div>
        <div class="card">
          <div class="k">Absent Records</div>
          <div class="v"><?php echo $absentTotal; ?></div>
        </div>
      </div>
    </section>

    <section class="panel" id="panel-students">
      <div class="section-head">
        <h2>Students Management</h2>
        <p>Create accounts and manage groups, passwords, and removals.</p>
      </div>
      <div class="grid-2 desktop-only" style="margin-bottom:14px;">
        <div class="card">
          <div class="k tight">Create Student</div>
          <form method="post" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="create_student">
            <div class="full"><label>Full name</label><input type="text" name="full_name" required></div>
            <div><label>Email</label><input type="email" name="email" required></div>
            <div><label>Group</label><input type="text" name="group_name" value="G1" required></div>
            <div class="full"><label>Initial password</label><input type="password" name="password" required></div>
            <div class="full"><button class="btn" type="submit">Create</button></div>
          </form>
        </div>
        <div class="card">
          <div class="k">Students List</div>
          <div class="hint">Use table actions for group updates, password resets, and account deletion.</div>
        </div>
      </div>

      <div class="table-wrap desktop-only">
        <table>
          <thead>
            <tr>
              <th>ID</th><th>Name</th><th>Email</th><th>Group</th><th>Presence</th><th>Absence</th><th>Status</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($students as $student): ?>
            <?php
              $absent = (int)($student['absent_count'] ?? 0);
              $present = (int)($student['present_count'] ?? 0);
              $statusClass = 'ok';
              $statusLabel = 'Stable';
              if ($absent >= 5) {
                  $statusClass = 'bad';
                  $statusLabel = 'Excluded risk';
              } elseif ($absent >= 3) {
                  $statusClass = 'warn';
                  $statusLabel = 'Danger';
              }
            ?>
            <tr>
              <td><?php echo (int)$student['id']; ?></td>
              <td><?php echo htmlspecialchars($student['full_name'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($student['email'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($student['group_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo $present; ?></td>
              <td><?php echo $absent; ?></td>
              <td><span class="tag <?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?></span></td>
              <td>
                <div class="stack-actions">
                  <div>
                    <div class="action-label">Update Group</div>
                    <form method="post" class="inline">
                      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                      <input type="hidden" name="action" value="update_group">
                      <input type="hidden" name="student_id" value="<?php echo (int)$student['id']; ?>">
                      <input type="text" name="group_name" value="<?php echo htmlspecialchars($student['group_name'] ?? 'G1', ENT_QUOTES, 'UTF-8'); ?>" required>
                      <button class="btn" type="submit">Save</button>
                    </form>
                  </div>
                  <div>
                    <div class="action-label">Reset Password</div>
                    <form method="post" class="inline">
                      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                      <input type="hidden" name="action" value="reset_password">
                      <input type="hidden" name="student_id" value="<?php echo (int)$student['id']; ?>">
                      <input type="password" name="new_password" placeholder="New password" required>
                      <button class="btn" type="submit">Reset</button>
                    </form>
                  </div>
                  <div>
                    <div class="action-label">Delete Student</div>
                    <form method="post" class="inline">
                      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                      <input type="hidden" name="action" value="delete_student">
                      <input type="hidden" name="student_id" value="<?php echo (int)$student['id']; ?>">
                      <input type="text" name="confirm_text" placeholder="Type DELETE" required>
                      <button class="btn danger" type="submit">Delete</button>
                    </form>
                  </div>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="mobile-only student-cards">
        <?php foreach ($students as $student): ?>
          <div class="student-card">
            <div class="card-title"><?php echo htmlspecialchars($student['full_name'], ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="card-sub"><?php echo htmlspecialchars($student['email'], ENT_QUOTES, 'UTF-8'); ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="panel" id="panel-logins">
      <div class="section-head">
        <h2>Login Activity</h2>
        <p>Students who logged in with first and latest login timestamps.</p>
      </div>
      <?php if (!$hasStudentLoginTracking): ?>
        <div class="card">Login tracking columns are missing in the database. Apply the latest schema/migration to enable this section.</div>
      <?php else: ?>
        <div class="table-wrap desktop-only">
          <table>
            <thead>
              <tr><th>ID</th><th>Full Name</th><th>Email</th><th>First Login</th><th>Last Login</th></tr>
            </thead>
            <tbody>
            <?php foreach ($loggedStudents as $row): ?>
              <tr>
                <td><?php echo (int)$row['id']; ?></td>
                <td><?php echo htmlspecialchars($row['full_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string)$row['first_login_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string)$row['last_login_at'], ENT_QUOTES, 'UTF-8'); ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="mobile-only login-cards">
          <?php foreach ($loggedStudents as $row): ?>
            <div class="login-card">
              <div class="card-head">
                <div class="card-title"><?php echo htmlspecialchars($row['full_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                <span class="metric-chip">ID <?php echo (int)$row['id']; ?></span>
              </div>
              <div class="card-sub"><?php echo htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8'); ?></div>
              <div class="card-metrics">
                <span class="metric-chip">First <?php echo htmlspecialchars((string)$row['first_login_at'], ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="metric-chip">Last <?php echo htmlspecialchars((string)$row['last_login_at'], ENT_QUOTES, 'UTF-8'); ?></span>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="panel" id="panel-modules">
      <div class="section-head">
        <h2>Modules Management</h2>
        <p>Create, update, and remove modules with type control.</p>
      </div>

      <div class="card" style="margin-bottom:14px;">
        <div class="k tight">Add Module</div>
        <form method="post" class="form-grid">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="action" value="create_module">
          <div class="full">
            <label>Module Name</label>
            <input type="text" name="name" required>
          </div>
          <div>
            <label>Type</label>
            <select name="type" required>
              <option value="TD">TD</option>
              <option value="TP">TP</option>
              <option value="COURS">COURS</option>
              <option value="ENLIGNE">ENLIGNE</option>
            </select>
          </div>
          <div class="full"><button class="btn" type="submit">Create Module</button></div>
        </form>
      </div>

      <div class="table-wrap desktop-only">
        <table>
          <thead>
            <tr><th>ID</th><th>Module</th><th>Type</th><th>Present</th><th>Absent</th><th>Actions</th></tr>
          </thead>
          <tbody>
          <?php foreach ($moduleStats as $m): ?>
            <tr>
              <td><?php echo (int)$m['id']; ?></td>
              <td><?php echo htmlspecialchars($m['name'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($m['type'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo (int)($m['present_count'] ?? 0); ?></td>
              <td><?php echo (int)($m['absent_count'] ?? 0); ?></td>
              <td>
                <div class="stack-actions">
                  <div>
                    <div class="action-label">Update Module</div>
                    <form method="post" class="inline" style="flex-wrap:wrap;">
                      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                      <input type="hidden" name="action" value="update_module">
                      <input type="hidden" name="module_id" value="<?php echo (int)$m['id']; ?>">
                      <input type="text" name="name" value="<?php echo htmlspecialchars($m['name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                      <select name="type" required>
                        <option value="TD" <?php echo $m['type'] === 'TD' ? 'selected' : ''; ?>>TD</option>
                        <option value="TP" <?php echo $m['type'] === 'TP' ? 'selected' : ''; ?>>TP</option>
                        <option value="COURS" <?php echo $m['type'] === 'COURS' ? 'selected' : ''; ?>>COURS</option>
                        <option value="ENLIGNE" <?php echo $m['type'] === 'ENLIGNE' ? 'selected' : ''; ?>>ENLIGNE</option>
                      </select>
                      <button class="btn" type="submit">Update</button>
                    </form>
                  </div>
                  <div>
                    <div class="action-label">Delete Module</div>
                    <form method="post" class="inline">
                      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                      <input type="hidden" name="action" value="delete_module">
                      <input type="hidden" name="module_id" value="<?php echo (int)$m['id']; ?>">
                      <input type="text" name="confirm_text" placeholder="Type DELETE" required>
                      <button class="btn danger" type="submit">Delete</button>
                    </form>
                  </div>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="mobile-only module-cards">
        <?php foreach ($moduleStats as $m): ?>
          <div class="module-card">
            <div class="module-head">
              <div class="module-name"><?php echo htmlspecialchars($m['name'], ENT_QUOTES, 'UTF-8'); ?></div>
              <div>#<?php echo (int)$m['id']; ?></div>
            </div>
            <div class="module-meta">
              Type: <?php echo htmlspecialchars($m['type'], ENT_QUOTES, 'UTF-8'); ?> |
              Present: <?php echo (int)($m['present_count'] ?? 0); ?> |
              Absent: <?php echo (int)($m['absent_count'] ?? 0); ?>
            </div>
            <div class="module-actions">
              <form method="post" class="inline">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="update_module">
                <input type="hidden" name="module_id" value="<?php echo (int)$m['id']; ?>">
                <input type="text" name="name" value="<?php echo htmlspecialchars($m['name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                <select name="type" required>
                  <option value="TD" <?php echo $m['type'] === 'TD' ? 'selected' : ''; ?>>TD</option>
                  <option value="TP" <?php echo $m['type'] === 'TP' ? 'selected' : ''; ?>>TP</option>
                  <option value="COURS" <?php echo $m['type'] === 'COURS' ? 'selected' : ''; ?>>COURS</option>
                  <option value="ENLIGNE" <?php echo $m['type'] === 'ENLIGNE' ? 'selected' : ''; ?>>ENLIGNE</option>
                </select>
                <button class="btn" type="submit">Update Module</button>
              </form>
              <form method="post" class="inline">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="delete_module">
                <input type="hidden" name="module_id" value="<?php echo (int)$m['id']; ?>">
                <input type="text" name="confirm_text" placeholder="Type DELETE" required>
                <button class="btn danger" type="submit">Delete Module</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  </main>
</div>

<script nonce="<?php echo htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8'); ?>">
const forceMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini|Mobile/i.test(navigator.userAgent)
  || window.matchMedia('(max-width: 980px)').matches
  || window.matchMedia('(hover: none) and (pointer: coarse)').matches;
if (forceMobile) {
  document.body.classList.add('force-mobile');
}

const menuButtons = document.querySelectorAll('.menu-btn');
const panels = document.querySelectorAll('.panel');

for (const button of menuButtons) {
  button.addEventListener('click', () => {
    for (const b of menuButtons) b.classList.remove('active');
    for (const panel of panels) panel.classList.remove('active');
    button.classList.add('active');
    const panel = document.getElementById('panel-' + button.dataset.panel);
    if (panel) panel.classList.add('active');
  });
}
</script>
</body>
</html>
