<?php
if (($_GET['key'] ?? '') !== 'devithor2026') {
    http_response_code(403); exit('Forbidden');
}

// ── MIGRATE action — runs pending SQL migrations ──────────────────────────────
if (($_GET['action'] ?? '') === 'migrate') {
    require __DIR__ . '/../src/bootstrap.php';
    header('Content-Type: text/plain');
    try {
        $pdo = Devithor\Database::pdo();
        $sql = file_get_contents(__DIR__ . '/../migrations/013_pin_auth.sql');
        $pdo->exec($sql);
        echo "Migration 013_pin_auth.sql: OK\n";
        // Verify column exists
        $cols = $pdo->query("SHOW COLUMNS FROM users LIKE 'pin_hash'")->fetchAll();
        echo "pin_hash column: " . (count($cols) > 0 ? "EXISTS ✓" : "MISSING ✗") . "\n";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
    exit;
}

// ── WIPE action — deletes all dummy courses from DB ───────────────────────────
if (($_GET['action'] ?? '') === 'wipe') {
    // Use the app's own bootstrap — it loads .env correctly
    require __DIR__ . '/../src/bootstrap.php';
    header('Content-Type: text/plain');
    try {
        $pdo = Devithor\Database::pdo();
        $before = $pdo->query('SELECT COUNT(*) FROM courses')->fetchColumn();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $pdo->exec('DELETE FROM courses');
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        $after = $pdo->query('SELECT COUNT(*) FROM courses')->fetchColumn();
        echo "=== Wipe Complete ===\n";
        echo "Deleted: $before courses\n";
        echo "Remaining: $after\n";
        echo "App catalog is now empty.\n";
    } catch (Exception $e) {
        echo "DB Error: " . $e->getMessage() . "\n";
    }
    exit;
}

header('Content-Type: text/html; charset=utf-8');

$repo   = 'jatayumedia01-web/lmsapp';
$branch = 'main';
$root   = '/home/u169457691/domains/apptesting.in';

// All tracked files — add new ones here when needed
$files = [
    'public/index.php',
    'public/wipe.php',
    'src/Router.php',
    'src/Database.php',
    'src/Request.php',
    'src/Response.php',
    'src/Validator.php',
    'src/View.php',
    'src/Auth.php',
    'routes/admin.php',
    'routes/api.php',
    'src/Controllers/Admin/ClassController.php',
    'src/Controllers/Admin/CourseController.php',
    'src/Controllers/Admin/LessonController.php',
    'src/Controllers/Admin/DashboardController.php',
    'src/Controllers/Admin/UserController.php',
    'src/Controllers/Api/AuthController.php',
    'src/Controllers/Api/CourseController.php',
    'src/Controllers/Api/LessonController.php',
    'src/Controllers/Api/ProfileController.php',
    'src/Controllers/Api/QuizController.php',
    'src/Controllers/Api/TrackingController.php',
    'src/Controllers/Api/NoteController.php',
    'src/Controllers/Api/NotificationApiController.php',
    'src/Controllers/Api/CertificateController.php',
    'src/Views/admin/classes/index.php',
    'src/Views/admin/classes/edit.php',
    'src/Views/admin/classes/subjects.php',
    'src/Views/admin/courses/index.php',
    'src/Views/admin/courses/edit.php',
    'src/Views/admin/lessons/index.php',
    'src/Views/admin/lessons/edit.php',
    'src/Views/admin/lessons/video.php',
    'src/Views/admin/dashboard.php',
    'src/Views/admin/users/index.php',
    'src/Views/admin/users/show.php',
    'src/Views/admin/quizzes/index.php',
    'src/Views/admin/quizzes/edit.php',
    'src/Views/admin/quizzes/questions.php',
    'src/Views/admin/quizzes/attempts.php',
    'src/Views/admin/notifications/index.php',
    'src/Views/admin/settings/index.php',
    'src/Views/admin/qa/index.php',
    'src/Views/admin/qa/show.php',
    'src/Views/admin/certificates/index.php',
    'src/Views/admin/subscriptions/index.php',
    'src/Views/admin/subscriptions/overview.php',
    'src/Views/admin/subscriptions/plans_index.php',
    'src/Views/admin/subscriptions/plan_edit.php',
    'src/Views/admin/subscriptions/coupons_index.php',
    'src/Views/admin/subscriptions/coupon_edit.php',
    'src/Views/admin/analytics/overview.php',
];

$ok = 0; $fail = 0; $results = [];

foreach ($files as $file) {
    $rawUrl  = "https://raw.githubusercontent.com/$repo/$branch/$file";
    $dest    = "$root/$file";

    $content = @file_get_contents($rawUrl);
    if ($content === false) {
        $results[] = ['status' => 'skip', 'file' => $file, 'msg' => 'Not in repo'];
        continue;
    }

    $dir = dirname($dest);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    if (file_put_contents($dest, $content) !== false) {
        $results[] = ['status' => 'ok', 'file' => $file, 'msg' => number_format(strlen($content)) . ' bytes'];
        $ok++;
    } else {
        $results[] = ['status' => 'fail', 'file' => $file, 'msg' => 'Write failed'];
        $fail++;
    }
}

?><!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Deploy</title>
<style>
body{font-family:monospace;background:#0f0f11;color:#e2e8f0;padding:32px;font-size:13px}
h2{color:#a78bfa;margin-bottom:4px}
.ok{color:#86efac}.fail{color:#f87171}.skip{color:#6b7280}
table{border-collapse:collapse;width:100%;margin-top:16px}
td{padding:4px 12px 4px 0}
.summary{margin-top:20px;padding:12px 20px;border-radius:8px;background:#1a1a2e;font-size:15px}
</style>
</head>
<body>
<h2>🚀 Devithor Deploy</h2>
<p style="color:#64748b">Time: <?= date('Y-m-d H:i:s') ?> &nbsp;·&nbsp; Repo: <?= $repo ?>/<?= $branch ?></p>

<table>
<?php foreach ($results as $r): ?>
<tr>
  <td class="<?= $r['status'] ?>"><?= $r['status'] === 'ok' ? '✓' : ($r['status'] === 'fail' ? '✗' : '—') ?></td>
  <td class="<?= $r['status'] ?>"><?= htmlspecialchars($r['file']) ?></td>
  <td style="color:#94a3b8"><?= htmlspecialchars($r['msg']) ?></td>
</tr>
<?php endforeach; ?>
</table>

<div class="summary">
  <?php if ($fail === 0): ?>
    ✅ <strong style="color:#86efac">Deploy successful</strong> — <?= $ok ?> files updated
  <?php else: ?>
    ⚠️ <?= $ok ?> updated, <span style="color:#f87171"><?= $fail ?> failed</span>
  <?php endif; ?>
</div>
</body>
</html>
