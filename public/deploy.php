<?php
if (($_GET['key'] ?? '') !== 'devithor2026') {
    http_response_code(403); exit('Forbidden');
}

// ── MIGRATE action — runs pending SQL migrations ──────────────────────────────
if (($_GET['action'] ?? '') === 'migrate') {
    require __DIR__ . '/../src/bootstrap.php';
    header('Content-Type: text/plain');
    $stmts = [
        'pin_hash column'     => "ALTER TABLE users ADD COLUMN IF NOT EXISTS pin_hash VARCHAR(255) NULL",
        'mock_exams table'    => "CREATE TABLE IF NOT EXISTS mock_exams (id VARCHAR(64) PRIMARY KEY, title VARCHAR(255) NOT NULL, description TEXT NULL, class_id VARCHAR(64) NULL, subject_tag VARCHAR(100) NULL, duration_minutes INT NOT NULL DEFAULT 60, total_marks INT NOT NULL DEFAULT 100, pass_marks INT NOT NULL DEFAULT 40, rules_text TEXT NULL, plan_required ENUM('FREE','BASIC','PREMIUM') NULL, is_published TINYINT(1) NOT NULL DEFAULT 0, shuffle_questions TINYINT(1) NOT NULL DEFAULT 1, show_answers_after TINYINT(1) NOT NULL DEFAULT 1, max_attempts INT NOT NULL DEFAULT 1, scheduled_at DATETIME NULL, expires_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)",
        'exam_questions table' => "CREATE TABLE IF NOT EXISTS exam_questions (id VARCHAR(64) PRIMARY KEY, exam_id VARCHAR(64) NOT NULL, question_text TEXT NOT NULL, option_a VARCHAR(500) NOT NULL, option_b VARCHAR(500) NOT NULL, option_c VARCHAR(500) NULL, option_d VARCHAR(500) NULL, correct_option CHAR(1) NOT NULL, marks INT NOT NULL DEFAULT 1, explanation TEXT NULL, order_index INT NOT NULL DEFAULT 0, INDEX idx_eq_exam (exam_id))",
        'exam_attempts table' => "CREATE TABLE IF NOT EXISTS exam_attempts (id VARCHAR(64) PRIMARY KEY, exam_id VARCHAR(64) NOT NULL, user_id VARCHAR(64) NOT NULL, started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, submitted_at DATETIME NULL, time_taken_seconds INT NULL, score INT NULL, total_marks INT NOT NULL, pass_marks INT NOT NULL, passed TINYINT(1) NULL, status ENUM('IN_PROGRESS','SUBMITTED','TIMED_OUT') NOT NULL DEFAULT 'IN_PROGRESS', certificate_number VARCHAR(64) NULL, certificate_issued_at DATETIME NULL, INDEX idx_ea_exam (exam_id), INDEX idx_ea_user (user_id))",
        'exam_answers table'  => "CREATE TABLE IF NOT EXISTS exam_answers (id BIGINT AUTO_INCREMENT PRIMARY KEY, attempt_id VARCHAR(64) NOT NULL, question_id VARCHAR(64) NOT NULL, selected_option CHAR(1) NULL, is_correct TINYINT(1) NULL, marks_awarded INT NULL DEFAULT 0, INDEX idx_ans_attempt (attempt_id))",
    ];
    try {
        $pdo = Devithor\Database::pdo();
        foreach ($stmts as $label => $sql) {
            $pdo->exec($sql);
            echo "OK: $label\n";
        }
        echo "\nAll migrations done ✓\n";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
    exit;
}

// ── SETUP-EXAMS: directly writes exam routes into routes/admin.php ───────────
if (($_GET['action'] ?? '') === 'setup-exams') {
    header('Content-Type: text/plain');
    $root = '/home/u169457691/domains/apptesting.in';

    // 1. Create .user.ini to force OPcache timestamp checking
    $userIni = "opcache.revalidate_freq=0\n";
    file_put_contents("$root/public/.user.ini", $userIni);
    echo "OK: .user.ini written (opcache.revalidate_freq=0)\n";

    // 2. Write exam routes directly to routes/exams.php (bypass GitHub CDN)
    $examRoutes = <<<'PHP'
<?php
use Devithor\Auth;
use Devithor\Controllers\Admin\ExamController as AdminExam;
use Devithor\Controllers\Api\ExamApiController as ApiExam;

$m = [Auth::requireAdmin()];
$router->get('/admin/exams',                        [AdminExam::class,'index'],          $m);
$router->get('/admin/exams/new',                    [AdminExam::class,'showCreate'],     $m);
$router->post('/admin/exams',                       [AdminExam::class,'create'],         $m);
$router->get('/admin/exams/{id}/questions',         [AdminExam::class,'questions'],      $m);
$router->post('/admin/exams/{id}/questions',        [AdminExam::class,'questionCreate'], $m);
$router->post('/admin/exams/questions/{id}/delete', [AdminExam::class,'questionDelete'], $m);
$router->get('/admin/exams/{id}/results',           [AdminExam::class,'results'],        $m);
$router->post('/admin/exams/{id}/publish',          [AdminExam::class,'publish'],        $m);
$router->get('/admin/exams/{id}',                   [AdminExam::class,'showEdit'],       $m);
$router->post('/admin/exams/{id}',                  [AdminExam::class,'update'],         $m);
$router->post('/admin/exams/{id}/delete',           [AdminExam::class,'delete'],         $m);
$a = [Auth::requireUser()];
$router->get('/api/v1/exams',                       [ApiExam::class,'list'],       $a);
$router->get('/api/v1/exams/{id}',                  [ApiExam::class,'show'],       $a);
$router->post('/api/v1/exams/{id}/start',           [ApiExam::class,'start'],      $a);
$router->post('/api/v1/exams/attempts/{id}/answer', [ApiExam::class,'saveAnswer'], $a);
$router->post('/api/v1/exams/attempts/{id}/submit', [ApiExam::class,'submit'],     $a);
$router->get('/api/v1/exams/attempts/{id}/result',  [ApiExam::class,'result'],     $a);
PHP;
    file_put_contents("$root/routes/exams.php", $examRoutes);
    touch("$root/routes/exams.php");
    if (function_exists('opcache_invalidate')) opcache_invalidate("$root/routes/exams.php", true);
    echo "OK: routes/exams.php written\n";

    // 3. Write index.php with routes/exams.php require
    $indexContent = file_get_contents("$root/public/index.php");
    if (!str_contains($indexContent, 'routes/exams.php')) {
        $indexContent = str_replace(
            "require __DIR__ . '/../routes/admin.php';",
            "require __DIR__ . '/../routes/admin.php';\n    require __DIR__ . '/../routes/exams.php';",
            $indexContent
        );
        file_put_contents("$root/public/index.php", $indexContent);
        touch("$root/public/index.php");
    }
    if (function_exists('opcache_invalidate')) opcache_invalidate("$root/public/index.php", true);
    if (function_exists('opcache_reset')) opcache_reset();
    echo "OK: index.php updated\n";
    echo "\nDone! Wait 30 seconds then visit /admin/exams\n";
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
    'public/deploy.php',
    'public/.htaccess',
    'public/exam-admin.php',
    'src/Router.php',
    'src/Database.php',
    'src/Request.php',
    'src/Response.php',
    'src/Validator.php',
    'src/View.php',
    'src/Auth.php',
    'routes/admin.php',
    'routes/api.php',
    'routes/exams.php',
    'src/Controllers/Admin/ClassController.php',
    'src/Controllers/Admin/CourseController.php',
    'src/Controllers/Admin/LessonController.php',
    'src/Controllers/Admin/DashboardController.php',
    'src/Controllers/Admin/UserController.php',
    'src/Controllers/Admin/ExamController.php',
    'src/Controllers/Api/ExamApiController.php',
    'src/Views/admin/exams/index.php',
    'src/Views/admin/exams/edit.php',
    'src/Views/admin/exams/questions.php',
    'src/Views/admin/exams/results.php',
    'src/Views/layouts/admin.php',
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
        // Clear OPcache for this file so PHP serves fresh bytecode immediately.
        if (function_exists('opcache_invalidate')) opcache_invalidate($dest, true);
        $results[] = ['status' => 'ok', 'file' => $file, 'msg' => number_format(strlen($content)) . ' bytes'];
        $ok++;
    } else {
        $results[] = ['status' => 'fail', 'file' => $file, 'msg' => 'Write failed'];
        $fail++;
    }
}

// Full OPcache flush after all files are written.
if (function_exists('opcache_reset')) opcache_reset();

// Always write exam routes directly + invalidate using realpath.
$examRoutes = '<?php
use Devithor\Auth;
use Devithor\Controllers\Admin\ExamController as AdminExam;
use Devithor\Controllers\Api\ExamApiController as ApiExam;
$m=[Auth::requireAdmin()];
$router->get("/admin/exams",[AdminExam::class,"index"],$m);
$router->get("/admin/exams/new",[AdminExam::class,"showCreate"],$m);
$router->post("/admin/exams",[AdminExam::class,"create"],$m);
$router->get("/admin/exams/{id}/questions",[AdminExam::class,"questions"],$m);
$router->post("/admin/exams/{id}/questions",[AdminExam::class,"questionCreate"],$m);
$router->post("/admin/exams/questions/{id}/delete",[AdminExam::class,"questionDelete"],$m);
$router->get("/admin/exams/{id}/results",[AdminExam::class,"results"],$m);
$router->post("/admin/exams/{id}/publish",[AdminExam::class,"publish"],$m);
$router->get("/admin/exams/{id}",[AdminExam::class,"showEdit"],$m);
$router->post("/admin/exams/{id}",[AdminExam::class,"update"],$m);
$router->post("/admin/exams/{id}/delete",[AdminExam::class,"delete"],$m);
$a=[Auth::requireUser()];
$router->get("/api/v1/exams",[ApiExam::class,"list"],$a);
$router->get("/api/v1/exams/{id}",[ApiExam::class,"show"],$a);
$router->post("/api/v1/exams/{id}/start",[ApiExam::class,"start"],$a);
$router->post("/api/v1/exams/attempts/{id}/answer",[ApiExam::class,"saveAnswer"],$a);
$router->post("/api/v1/exams/attempts/{id}/submit",[ApiExam::class,"submit"],$a);
$router->get("/api/v1/exams/attempts/{id}/result",[ApiExam::class,"result"],$a);
';
$examDest = "$root/routes/exams.php";
$examWritten = file_put_contents($examDest, $examRoutes);
$examReal = realpath($examDest) ?: $examDest;
if (function_exists('opcache_invalidate')) { opcache_invalidate($examReal, true); opcache_invalidate($examDest, true); }
if ($examWritten !== false) {
    $results[] = ['status' => 'ok',   'file' => 'routes/exams.php (direct)', 'msg' => "$examWritten bytes written to $examReal"];
} else {
    $results[] = ['status' => 'fail', 'file' => 'routes/exams.php (direct)', 'msg' => "WRITE FAILED — check permissions on $examDest"];
}

// Force index.php to require routes/exams.php using realpath-based invalidation.
$idxDest = "$root/public/index.php";
$idx = file_get_contents($idxDest);
if ($idx && !str_contains($idx, 'routes/exams.php')) {
    $idx = str_replace(
        "require __DIR__ . '/../routes/admin.php';",
        "require __DIR__ . '/../routes/admin.php';\n    require __DIR__ . '/../routes/exams.php';",
        $idx
    );
    file_put_contents($idxDest, $idx);
}
$idxReal = realpath($idxDest) ?: $idxDest;
if (function_exists('opcache_invalidate')) { opcache_invalidate($idxReal, true); opcache_invalidate($idxDest, true); }
if (function_exists('opcache_reset')) opcache_reset();

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
