<?php
require_once __DIR__ . '/includes/db.php';

$seedDemoStudent = strtolower((string)(getenv('SEED_DEMO_STUDENT') ?: '0')) === '1';
$passwordHash = password_hash((string)(getenv('DEMO_STUDENT_PASSWORD') ?: 'demo1234'), PASSWORD_BCRYPT);
$demoStudentEmail = (string)(getenv('DEMO_STUDENT_EMAIL') ?: 'student@example.com');
$demoStudentName = (string)(getenv('DEMO_STUDENT_NAME') ?: 'Etudiant Demo');
$adminEmail = trim((string)(getenv('ADMIN_SEED_EMAIL') ?: ''));
$adminPassword = (string)(getenv('ADMIN_SEED_PASSWORD') ?: '');
$adminName = trim((string)(getenv('ADMIN_SEED_NAME') ?: 'Main Administrator'));

$pdo->beginTransaction();
try {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS admins (
            id BIGSERIAL PRIMARY KEY,
            full_name VARCHAR(120) NOT NULL,
            email VARCHAR(120) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            last_login_at TIMESTAMPTZ NULL DEFAULT NULL,
            created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
        )"
    );

    if ($adminEmail !== '' && $adminPassword !== '') {
        $adminStmt = $pdo->prepare(
            "INSERT INTO admins (full_name, email, password_hash)
             VALUES (?, ?, ?)
             ON CONFLICT (email) DO UPDATE
             SET full_name = EXCLUDED.full_name,
                 password_hash = EXCLUDED.password_hash"
        );
        $adminStmt->execute([
            $adminName !== '' ? $adminName : 'Main Administrator',
            $adminEmail,
            password_hash($adminPassword, PASSWORD_BCRYPT),
        ]);
    }

    if ($seedDemoStudent) {
        $student = $pdo->prepare(
            "INSERT INTO students (full_name, email, password_hash, group_name)
             VALUES (?, ?, ?, ?)
             ON CONFLICT (email) DO UPDATE
             SET full_name = EXCLUDED.full_name,
                 password_hash = EXCLUDED.password_hash"
        );
        $student->execute([$demoStudentName, $demoStudentEmail, $passwordHash, 'G1']);
    }

    $pdo->exec('DELETE FROM attendance');
    $pdo->exec('DELETE FROM modules');
    $pdo->exec('DELETE FROM weeks');

    $modules = [
        ['Securite Informatique', 'TD', 'Benhami', 'Salle cours', 'Samedi', '11:10:00', '12:40:00'],
        ['Systeme d\'exploitation 2', 'TD', 'Haifaoui', 'Amphi Benbadis', 'Dimanche', '09:35:00', '11:05:00'],
        ['Systeme d\'exploitation 2', 'TP', 'Tilmatine', 'lab 07', 'Samedi', '14:20:00', '15:50:00'],
        ['Recherche d\'information', 'TD', 'BELATTAR', 'Salle 5 info', 'Samedi', '12:45:00', '14:15:00'],
        ['Donnees semi structurees', 'TP', 'Izountar', 'lab 1', 'Dimanche', '14:20:00', '15:50:00'],
        ['Business Intelligence', 'TD', 'Taibouni', 'Salle 1 info', 'Mercredi', '08:00:00', '09:30:00'],
        ['Redaction Scientifique', 'TD', 'Boufedji', '-', 'Mardi', '09:35:00', '11:05:00'],
        ['Projet', 'TD', 'Taibouni', 'Salle 3 info', 'Mercredi', '12:45:00', '14:15:00'],
    ];
    $moduleStmt = $pdo->prepare(
        "INSERT INTO modules (name, type, teacher, room, day_of_week, time_start, time_end)
         VALUES (?, ?, ?, ?, ?, ?, ?)
         ON CONFLICT (name, type, day_of_week, time_start) DO UPDATE
         SET teacher = EXCLUDED.teacher,
             room = EXCLUDED.room,
             time_end = EXCLUDED.time_end"
    );
    foreach ($modules as $module) {
        $moduleStmt->execute($module);
    }

    $start = new DateTimeImmutable('2025-09-06');
    $weekStmt = $pdo->prepare(
        "INSERT INTO weeks (week_number, date_start, date_end, semester)
         VALUES (?, ?, ?, ?)
         ON CONFLICT (semester, week_number) DO UPDATE
         SET date_start = EXCLUDED.date_start,
             date_end = EXCLUDED.date_end"
    );
    for ($i = 1; $i <= 14; $i++) {
        $weekStart = $start->modify('+' . (($i - 1) * 7) . ' days');
        $weekEnd = $weekStart->modify('+6 days');
        $weekStmt->execute([$i, $weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d'), '2025-S2']);
    }

    $pdo->commit();
    echo "Seed complete.\n";
    if ($seedDemoStudent) {
        echo "Demo student seeded for: " . $demoStudentEmail . "\n";
    } else {
        echo "Demo student seed skipped. Set SEED_DEMO_STUDENT=1 to create one.\n";
    }
    if ($adminEmail !== '' && $adminPassword !== '') {
        echo "Admin account seeded for: " . $adminEmail . "\n";
    } else {
        echo "Admin seed skipped. Set ADMIN_SEED_EMAIL and ADMIN_SEED_PASSWORD to create one.\n";
    }
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "Seed failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
