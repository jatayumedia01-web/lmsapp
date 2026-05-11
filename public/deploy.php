<?php
/**
 * PHP-based deployer — no shell_exec needed.
 * Fetches files directly from GitHub raw content and writes to disk.
 * URL: https://apptesting.in/deploy.php?key=devithor2026
 */
if (($_GET['key'] ?? '') !== 'devithor2026') {
    http_response_code(403); exit('Forbidden');
}

header('Content-Type: text/html; charset=utf-8');

$repo   = 'jatayumedia01-web/lmsapp';
$branch = 'main';
$root   = '/home/u169457691/domains/apptesting.in';

// All tracked files — add new ones here when needed
$files = [
    'public/index.php',
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
