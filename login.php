<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/security.php';

function adminTableExists(PDO $pdo): bool
{
    $stmt = $pdo->query("SHOW TABLES LIKE 'admins'");
    return (bool)$stmt->fetchColumn();
}

function hasStudentLoginTrackingColumns(PDO $pdo): bool
{
    $columns = $pdo->query('SHOW COLUMNS FROM students')->fetchAll(PDO::FETCH_COLUMN, 0);
    if (!is_array($columns)) {
        return false;
    }
    return in_array('first_login_at', $columns, true) && in_array('last_login_at', $columns, true);
}

$nonce = securityPageNonce();
applyLoginSecurityHeaders($nonce);
$hasAdminTable = adminTableExists($pdo);
$hasStudentLoginTracking = hasStudentLoginTrackingColumns($pdo);

if (currentStudentId() !== null) {
    header('Location: /dashboard.php');
    exit;
}
if (currentAdminId() !== null) {
    header('Location: /admin_dashboard.php');
    exit;
}

$error = '';
$mode = 'login';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyFormCsrfOrFail();
    $mode = $_POST['mode'] ?? 'login';
    if ($mode !== 'register') {
        $mode = 'login';
    }
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $inviteCode = trim($_POST['invitation_code'] ?? '');
    $fullName = preg_replace('/\s+/', ' ', trim($_POST['full_name'] ?? ''));
    $requiredInviteCode = getenv('INVITATION_CODE');
    $rateStatus = loginRateLimitStatus($email);

    if ($rateStatus['blocked']) {
        $wait = (int)$rateStatus['retry_after'];
        $error = 'Trop de tentatives. Reessayez dans ' . max(1, $wait) . ' secondes.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Identifiants invalides.';
        recordLoginFailure($email);
    } elseif ($mode === 'register' && (strlen($fullName) < 3 || strlen($fullName) > 100)) {
        $error = 'Nom complet invalide.';
        recordLoginFailure($email);
    } elseif ($mode === 'register' && !validPasswordPolicy($password)) {
        $error = 'Mot de passe faible (min 10, majuscule, minuscule, chiffre, symbole).';
        recordLoginFailure($email);
    } else {
        if ($mode === 'register') {
            if ($fullName === '' || $email === '' || $password === '' || $inviteCode === '') {
                $error = 'Veuillez remplir tous les champs.';
                recordLoginFailure($email);
            } elseif (!is_string($requiredInviteCode) || trim($requiredInviteCode) === '') {
                $error = 'Inscription desactivee. Contactez un administrateur.';
                recordLoginFailure($email);
            } elseif (!hash_equals($requiredInviteCode, $inviteCode)) {
                $error = 'Code d\'invitation invalide.';
                recordLoginFailure($email);
            } else {
                $check = $pdo->prepare('SELECT id FROM students WHERE email = ?');
                $check->execute([$email]);
                if ($check->fetch()) {
                    $error = 'Creation de compte impossible.';
                    recordLoginFailure($email);
                } else {
                    $insert = $pdo->prepare('
                        INSERT INTO students (full_name, email, password_hash, group_name)
                        VALUES (?, ?, ?, ?)
                    ');
                    $insert->execute([
                        $fullName,
                        $email,
                        password_hash($password, PASSWORD_BCRYPT),
                        'G1',
                    ]);

                    $studentId = (int)$pdo->lastInsertId();
                    if ($hasStudentLoginTracking) {
                        $touchLogin = $pdo->prepare(
                            'UPDATE students
                             SET first_login_at = COALESCE(first_login_at, NOW()),
                                 last_login_at = NOW()
                             WHERE id = ?'
                        );
                        $touchLogin->execute([$studentId]);
                    }
                    clearLoginFailures($email);
                    initializeAuthenticatedSession($studentId, $fullName);
                    header('Location: /dashboard.php');
                    exit;
                }
            }
        } else {
            if ($email === '' || $password === '') {
                $error = 'Veuillez renseigner email et mot de passe.';
                recordLoginFailure($email);
            } else {
                $admin = false;
                if ($hasAdminTable) {
                    $adminStmt = $pdo->prepare('SELECT id, email, password_hash FROM admins WHERE email = ?');
                    $adminStmt->execute([$email]);
                    $admin = $adminStmt->fetch();
                }

                if ($admin && password_verify($password, $admin['password_hash'])) {
                    clearLoginFailures($email);
                    initializeAdminSession((int)$admin['id'], $admin['email']);
                    $up = $pdo->prepare('UPDATE admins SET last_login_at = NOW() WHERE id = ?');
                    $up->execute([(int)$admin['id']]);
                    header('Location: /admin_dashboard.php');
                    exit;
                }

                $stmt = $pdo->prepare('SELECT id, full_name, email, password_hash FROM students WHERE email = ?');
                $stmt->execute([$email]);
                $student = $stmt->fetch();

                if ($student && password_verify($password, $student['password_hash'])) {
                    clearLoginFailures($email);
                    if ($hasStudentLoginTracking) {
                        $touchLogin = $pdo->prepare(
                            'UPDATE students
                             SET first_login_at = COALESCE(first_login_at, NOW()),
                                 last_login_at = NOW()
                             WHERE id = ?'
                        );
                        $touchLogin->execute([(int)$student['id']]);
                    }
                    initializeAuthenticatedSession((int)$student['id'], $student['full_name']);
                    header('Location: /dashboard.php');
                    exit;
                }
                usleep(random_int(200000, 600000));
                $error = 'Email ou mot de passe incorrect.';
                recordLoginFailure($email);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>AbsTrack - Connexion</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Mono:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="login-body">
<div class="login-bg-grid"></div>
<div class="login-orb login-orb-1"></div>
<div class="login-orb login-orb-2"></div>
<div class="login-orb login-orb-3"></div>
<div class="login-particles" id="particles"></div>

<div class="login-card-wrap">
  <div class="login-card" id="login-card">
    <div class="login-top-badge">L3 Informatique - 2025-2026</div>

    <div class="login-logo-area">
      <div class="login-logo-icon-bg">
        <svg class="login-brand-mark" viewBox="0 0 64 64" aria-hidden="true">
          <rect x="9" y="10" width="18" height="44" rx="8" fill="#3b82f6"></rect>
          <rect x="37" y="10" width="18" height="44" rx="8" fill="#06b6d4"></rect>
          <path d="M27 22h10M27 32h10M27 42h10" stroke="#dbeafe" stroke-width="3" stroke-linecap="round"></path>
        </svg>
      </div>
      <div>
        <div class="login-logo-name">AbsTrack</div>
        <div class="login-logo-tagline">Smart attendance tracking platform</div>
      </div>
    </div>

    <div class="login-divider">
      <div class="login-divider-line"></div>
      <div class="login-divider-text">Connexion</div>
      <div class="login-divider-line"></div>
    </div>

    <?php if ($error !== ''): ?>
      <div class="login-alert-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <form method="post" class="login-form" id="login-form">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" id="mode-input" name="mode" value="<?php echo htmlspecialchars($mode, ENT_QUOTES, 'UTF-8'); ?>">

      <div id="register-fields" style="<?php echo $mode === 'register' ? '' : 'display:none'; ?>">
        <label for="full_name">Nom complet</label>
        <input id="full_name" name="full_name" type="text" autocomplete="name" value="<?php echo htmlspecialchars($_POST['full_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
      </div>

      <label for="email">Email</label>
      <input id="email" name="email" type="email" autocomplete="username" required value="<?php echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

      <label for="password">Mot de passe</label>
      <input id="password" name="password" type="password" autocomplete="current-password" required>

      <div id="invite-field" style="<?php echo $mode === 'register' ? '' : 'display:none'; ?>">
        <label for="invitation_code">Code d'invitation</label>
        <input id="invitation_code" name="invitation_code" type="text" value="<?php echo htmlspecialchars($_POST['invitation_code'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
      </div>

      <button class="login-submit" id="login-submit" type="submit">
        <span class="login-submit-spinner" aria-hidden="true"></span>
        <span class="login-submit-text"><?php echo $mode === 'register' ? 'Creer et se connecter' : 'Se connecter'; ?></span>
      </button>

      <button class="login-switch" id="mode-switch" type="button"><?php echo $mode === 'register' ? 'J\'ai deja un compte' : 'Nouveau compte'; ?></button>
    </form>

    <?php if ((getenv('APP_SHOW_DEMO_CREDENTIALS') ?: '0') === '1'): ?>
      <div class="login-info-box">
        <div class="login-info-icon">i</div>
        <div class="login-info-text">
          Compte demo disponible:<br>
          <strong>student@example.com</strong> / <strong>demo1234</strong>
        </div>
      </div>
    <?php endif; ?>

    <div class="login-features">
      <div class="login-feature">
        <div class="login-feature-icon fi-weeks" aria-hidden="true">
          <svg viewBox="0 0 24 24">
            <rect x="3" y="5" width="18" height="16" rx="4"></rect>
            <path d="M8 3v4M16 3v4M3 10h18"></path>
            <circle cx="8" cy="14" r="1.2"></circle>
            <circle cx="12" cy="14" r="1.2"></circle>
            <circle cx="16" cy="14" r="1.2"></circle>
          </svg>
        </div>
        <div class="login-feature-label">Semaines de suivi</div>
      </div>
      <div class="login-feature">
        <div class="login-feature-icon fi-live" aria-hidden="true">
          <svg viewBox="0 0 24 24">
            <path d="M4 15.5V18h2.5l7.4-7.4-2.5-2.5L4 15.5z"></path>
            <path d="M13.2 6.3l2.5 2.5"></path>
            <path d="M17 5a3 3 0 0 1 2 5.2"></path>
            <path d="M7 19a3 3 0 0 1-2-5.2"></path>
          </svg>
        </div>
        <div class="login-feature-label">Stats temps reel</div>
      </div>
      <div class="login-feature">
        <div class="login-feature-icon fi-alert" aria-hidden="true">
          <svg viewBox="0 0 24 24">
            <path d="M12 3l9 16H3L12 3z"></path>
            <path d="M12 9v5"></path>
            <circle cx="12" cy="17" r="1.2"></circle>
          </svg>
        </div>
        <div class="login-feature-label">Alerte absences</div>
      </div>
    </div>
  </div>
</div>

<script nonce="<?php echo htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8'); ?>">
const container = document.getElementById('particles');
for (let i = 0; i < 18; i += 1) {
  const p = document.createElement('div');
  p.className = 'login-particle';
  const x = Math.random() * 100;
  const dur = 8 + Math.random() * 12;
  const delay = Math.random() * 10;
  const size = Math.random() > 0.6 ? 3 : 2;
  p.style.left = `${x}%`;
  p.style.width = `${size}px`;
  p.style.height = `${size}px`;
  p.style.animationDuration = `${dur}s`;
  p.style.animationDelay = `-${delay}s`;
  p.style.background = Math.random() > 0.5 ? '#4f8cff' : '#a78bfa';
  container.appendChild(p);
}

const form = document.getElementById('login-form');
const submit = document.getElementById('login-submit');
const modeInput = document.getElementById('mode-input');
const modeSwitch = document.getElementById('mode-switch');
const inviteField = document.getElementById('invite-field');
const registerFields = document.getElementById('register-fields');
const submitText = document.querySelector('.login-submit-text');
const fullNameInput = document.getElementById('full_name');
const inviteInput = document.getElementById('invitation_code');

function applyMode(mode) {
  const registerMode = mode === 'register';
  modeInput.value = registerMode ? 'register' : 'login';
  inviteField.style.display = registerMode ? '' : 'none';
  registerFields.style.display = registerMode ? '' : 'none';
  submitText.textContent = registerMode ? 'Creer et se connecter' : 'Se connecter';
  modeSwitch.textContent = registerMode ? "J'ai deja un compte" : 'Nouveau compte';
  fullNameInput.required = registerMode;
  inviteInput.required = registerMode;
}

modeSwitch.addEventListener('click', () => {
  applyMode(modeInput.value === 'register' ? 'login' : 'register');
});
form.addEventListener('submit', () => {
  submit.classList.add('loading');
});
applyMode(modeInput.value);

const card = document.getElementById('login-card');
document.addEventListener('mousemove', (e) => {
  const rect = card.getBoundingClientRect();
  const cx = rect.left + rect.width / 2;
  const cy = rect.top + rect.height / 2;
  const dx = (e.clientX - cx) / (window.innerWidth / 2);
  const dy = (e.clientY - cy) / (window.innerHeight / 2);
  card.style.transform = `perspective(800px) rotateY(${dx * 3}deg) rotateX(${-dy * 3}deg)`;
});
document.addEventListener('mouseleave', () => {
  card.style.transform = 'perspective(800px) rotateY(0deg) rotateX(0deg)';
});
</script>
</body>
</html>
