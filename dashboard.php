<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/security.php';
requireLogin();
$nonce = securityPageNonce();
applyLoginSecurityHeaders($nonce);
$studentName = currentStudentName();
$csrf = csrfToken();
$parts = preg_split('/\s+/', trim($studentName));
$initials = '';
foreach ($parts as $p) {
    if ($p !== '') {
        $initials .= strtoupper(substr($p, 0, 1));
    }
    if (strlen($initials) >= 2) {
        break;
    }
}
if ($initials === '') {
    $initials = 'ET';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AbsTrack — L3 Informatique</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Mono:wght@300;400;500&display=swap" rel="stylesheet">
<meta name="csrf-token" content="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>

<header>
  <div class="h-left">
    <div class="h-logo" aria-hidden="true">
      <svg class="brand-mark-small" viewBox="0 0 64 64">
        <rect x="9" y="10" width="18" height="44" rx="8" fill="#3b82f6"></rect>
        <rect x="37" y="10" width="18" height="44" rx="8" fill="#06b6d4"></rect>
        <path d="M27 22h10M27 32h10M27 42h10" stroke="#dbeafe" stroke-width="3" stroke-linecap="round"></path>
      </svg>
    </div>
    <span class="h-title">AbsTrack</span>
    <div class="h-sep"></div>
    <span class="h-semester">L3 Informatique · Semestre 2 · 2025-2026</span>
  </div>
  <div class="h-right">
    <div class="h-student">
      <div class="h-student-name"><?php echo htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8'); ?></div>
      <div class="h-student-meta">Groupe G1 · Departement Informatique</div>
    </div>
    <div class="h-avatar"><?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?></div>
    <a class="logout-btn" href="/logout.php">Deconnexion</a>
  </div>
</header>

<div class="stats" id="stats-bar"></div>

<div class="top-tabs">
  <button class="tab-btn active" data-tab="detail" type="button"><span class="tab-icon">▦</span>Detail module</button>
  <button class="tab-btn" data-tab="overview" type="button"><span class="tab-icon">◫</span>Vue d'ensemble</button>
</div>

<div class="layout">
  <div class="sidebar" id="sidebar"></div>

  <div class="main">
    <div id="view-detail">
      <div class="mod-header">
        <div class="mod-header-left">
          <div class="mod-header-title" id="dv-title">—</div>
          <div class="mod-header-meta" id="dv-meta"></div>
          <div class="mod-progress-row">
            <div class="pct-big" id="dv-pct">—</div>
            <div>
              <div class="dv-counts" id="dv-counts">— presences</div>
              <div class="pct-sub">Regle absence: 3 = danger, 5 = exclu</div>
            </div>
          </div>
        </div>
      </div>

      <div class="alert" id="dv-alert">
        <span class="alert-icon">⚠</span>
        <span><strong>Attention !</strong> Vous avez atteint le seuil de <strong>danger (3 absences)</strong>.</span>
      </div>

      <div class="prog-wrap">
        <div class="prog-head">
          <span>Progression du risque d'exclusion</span>
          <span id="dv-prog-label">—</span>
        </div>
        <div class="prog-track"><div class="prog-fill" id="dv-prog-fill" style="width:0%"></div></div>
      </div>

      <div class="grid-header">
        <div class="grid-label">Semaines 1 -> 14 · Cliquez pour marquer present / absent</div>
        <div class="grid-legend">
          <div class="leg"><div class="leg-dot p"></div>Present</div>
          <div class="leg"><div class="leg-dot a"></div>Absent</div>
          <div class="leg"><div class="leg-dot u"></div>?</div>
          <div class="leg"><div class="leg-dot f"></div>A venir</div>
        </div>
      </div>

      <div class="grid-board">
        <div class="week-label-row" id="week-labels"></div>
        <div class="weeks-row" id="cells-row"></div>
      </div>

    </div>

    <div id="view-overview" class="overview">
      <div class="overview-title">Vue d'ensemble — tous les modules</div>
      <table class="ov-table" id="ov-table">
        <thead>
          <tr>
            <th>Module</th>
            <th>Type</th>
            <th>Presences</th>
            <th>Absences</th>
            <th>Statut</th>
            <th>Progression</th>
          </tr>
        </thead>
        <tbody id="ov-body"></tbody>
      </table>
    </div>
  </div>
</div>

<div id="tooltip" class="hide"></div>

<script nonce="<?php echo htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8'); ?>">
window.APP_CONFIG = { csrfToken: <?php echo json_encode($csrf); ?> };
</script>
<script src="/assets/js/api.js"></script>
<script src="/assets/js/app.js"></script>
</body>
</html>

