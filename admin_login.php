<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/security.php';

function ensureAdminTable(PDO $pdo): void
{
    $stmt = $pdo->prepare(
        "SELECT EXISTS (
            SELECT 1
            FROM information_schema.tables
            WHERE table_schema = current_schema()
              AND table_name = 'admins'
        )"
    );
    $stmt->execute();
    if (!(bool)$stmt->fetchColumn()) {
        throw new RuntimeException('Admins table is missing. Run the latest database migration.');
    }
}

$nonce = securityPageNonce();
applyLoginSecurityHeaders($nonce);
try {
    ensureAdminTable($pdo);
} catch (Throwable $e) {
    http_response_code(500);
    exit('Server setup incomplete.');
}

if (currentAdminId() !== null) {
    header('Location: /admin_dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyFormCsrfOrFail();

    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $rateStatus = loginRateLimitStatus($email);

    if ($rateStatus['blocked']) {
        $wait = (int)$rateStatus['retry_after'];
        $error = 'Too many attempts. Retry in ' . max(1, $wait) . ' seconds.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Invalid credentials.';
        recordLoginFailure($email);
    } else {
        $stmt = $pdo->prepare('SELECT id, email, password_hash FROM admins WHERE email = ?');
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            clearLoginFailures($email);
            initializeAdminSession((int)$admin['id'], $admin['email']);
            $up = $pdo->prepare('UPDATE admins SET last_login_at = NOW() WHERE id = ?');
            $up->execute([(int)$admin['id']]);
            header('Location: /admin_dashboard.php');
            exit;
        }

        usleep(random_int(200000, 600000));
        $error = 'Email or password is incorrect.';
        recordLoginFailure($email);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>AbsTrack Admin Login</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Mono:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="login-body">
<div class="login-bg-grid"></div>
<div class="login-orb login-orb-1"></div>
<div class="login-orb login-orb-2"></div>
<div class="login-orb login-orb-3"></div>

<div class="login-card-wrap">
  <div class="login-card">
    <div class="login-top-badge">Administrative Access</div>
    <div class="login-logo-area">
      <div class="login-logo-icon-bg">
        <svg class="login-brand-mark" viewBox="0 0 64 64" aria-hidden="true">
          <rect x="9" y="10" width="18" height="44" rx="8" fill="#3b82f6"></rect>
          <rect x="37" y="10" width="18" height="44" rx="8" fill="#06b6d4"></rect>
          <path d="M27 22h10M27 32h10M27 42h10" stroke="#dbeafe" stroke-width="3" stroke-linecap="round"></path>
        </svg>
      </div>
      <div>
        <div class="login-logo-name">AbsTrack Admin</div>
        <div class="login-logo-tagline">Secure control panel for users and attendance data</div>
      </div>
    </div>

    <?php if ($error !== ''): ?>
      <div class="login-alert-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <form method="post" class="login-form">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
      <label for="email">Admin Email</label>
      <input id="email" name="email" type="email" autocomplete="username" required value="<?php echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
      <label for="password">Password</label>
      <input id="password" name="password" type="password" autocomplete="current-password" required>
      <button class="login-submit" type="submit"><span class="login-submit-text">Sign in as Admin</span></button>
      <a class="login-switch" href="/login.php">Student login</a>
    </form>
  </div>
</div>
</body>
</html>
