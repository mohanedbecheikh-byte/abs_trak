<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/security.php';

applyAppSecurityHeaders();

if (currentStudentId() !== null) {
    header('Location: /dashboard.php');
    exit;
}
if (currentAdminId() !== null) {
    header('Location: /admin_dashboard.php');
    exit;
}

header('Location: /login.php');
exit;
