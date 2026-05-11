<?php
/**
 * ONE-TIME cleanup script — removes dummy/test content.
 * Upload to public/ folder, run once, then DELETE this file immediately.
 *
 * Access: https://apptesting.in/cleanup.php?key=devithor2026
 */

define('SECRET_KEY', 'devithor2026');

if (($_GET['key'] ?? '') !== SECRET_KEY) {
    http_response_code(403);
    exit('Forbidden — add ?key=devithor2026 to the URL');
}

// ── DB connection (same env as the app) ──────────────────────────────────────
$host   = getenv('DB_HOST')     ?: 'localhost';
$dbname = getenv('DB_DATABASE') ?: '';
$user   = getenv('DB_USERNAME') ?: '';
$pass   = getenv('DB_PASSWORD') ?: '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $user, $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (\Exception $e) {
    exit('DB connection failed: ' . $e->getMessage());
}

// ── Fetch current courses ─────────────────────────────────────────────────────
$courses = $pdo->query("SELECT id, title, created_at FROM courses ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$lessons = $pdo->query("SELECT id, title, course_id, created_at FROM lessons ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// ── Handle delete action ──────────────────────────────────────────────────────
$action  = $_POST['action'] ?? '';
$message = '';

if ($action === 'delete_course') {
    $courseId = (int) ($_POST['course_id'] ?? 0);
    if ($courseId > 0) {
        deleteCourse($pdo, $courseId);
        $message = "✅ Course #$courseId deleted with all its lessons, progress, feedback.";
        // Refresh list
        $courses = $pdo->query("SELECT id, title, created_at FROM courses ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
        $lessons = $pdo->query("SELECT id, title, course_id, created_at FROM lessons ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    }
}

if ($action === 'delete_old') {
    // Delete everything NOT created today
    $today = date('Y-m-d');
    $oldCourseIds = $pdo->query("SELECT id FROM courses WHERE DATE(created_at) < '$today'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($oldCourseIds as $id) {
        deleteCourse($pdo, (int)$id);
    }
    $count = count($oldCourseIds);
    $message = "✅ Deleted $count old course(s). Today's content is kept.";
    $courses = $pdo->query("SELECT id, title, created_at FROM courses ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    $lessons = $pdo->query("SELECT id, title, course_id, created_at FROM lessons ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
}

if ($action === 'delete_all') {
    // Nuclear option — wipe ALL course content, keep user accounts
    $allIds = $pdo->query("SELECT id FROM courses")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($allIds as $id) {
        deleteCourse($pdo, (int)$id);
    }
    $count = count($allIds);
    $message = "✅ All $count course(s) deleted. User accounts kept intact.";
    $courses = [];
    $lessons = [];
}

// ── Helper: cascade-delete one course ────────────────────────────────────────
function deleteCourse(PDO $pdo, int $courseId): void
{
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");

    // lesson-level data
    $lessonIds = $pdo->query("SELECT id FROM lessons WHERE course_id = $courseId")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($lessonIds as $lid) {
        $pdo->exec("DELETE FROM lesson_feedback   WHERE lesson_id = $lid");
        $pdo->exec("DELETE FROM lesson_answers    WHERE question_id IN (SELECT id FROM lesson_questions WHERE lesson_id = $lid)");
        $pdo->exec("DELETE FROM votes             WHERE target_id IN (SELECT id FROM lesson_questions WHERE lesson_id = $lid) OR target_id IN (SELECT id FROM lesson_answers a JOIN lesson_questions q ON a.question_id=q.id WHERE q.lesson_id = $lid)");
        $pdo->exec("DELETE FROM lesson_questions  WHERE lesson_id = $lid");
        $pdo->exec("DELETE FROM lesson_progress   WHERE lesson_id = $lid");
        $pdo->exec("DELETE FROM user_notes        WHERE lesson_id = $lid");
        $pdo->exec("DELETE FROM bookmarks         WHERE lesson_id = $lid");
        $pdo->exec("DELETE FROM video_views       WHERE lesson_id = $lid");

        // quizzes for this lesson
        $quizIds = $pdo->query("SELECT id FROM quizzes WHERE lesson_id = $lid")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($quizIds as $qid) {
            $pdo->exec("DELETE FROM quiz_attempts WHERE quiz_id = $qid");
            $pdo->exec("DELETE FROM quiz_answers  WHERE question_id IN (SELECT id FROM quiz_questions WHERE quiz_id = $qid)");
            $pdo->exec("DELETE FROM quiz_questions WHERE quiz_id = $qid");
        }
        $pdo->exec("DELETE FROM quizzes WHERE lesson_id = $lid");
        $pdo->exec("DELETE FROM lessons WHERE id = $lid");
    }

    // course-level data
    $pdo->exec("DELETE FROM enrollments  WHERE course_id = $courseId");
    $pdo->exec("DELETE FROM certificates WHERE course_id = $courseId");
    $pdo->exec("DELETE FROM bookmarks    WHERE course_id = $courseId");
    $pdo->exec("DELETE FROM courses      WHERE id = $courseId");

    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
}

// ── UI ────────────────────────────────────────────────────────────────────────
$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Devithor LMS — Content Cleanup</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: system-ui, sans-serif; background: #0f0f11; color: #e2e8f0; min-height: 100vh; padding: 32px 16px; }
  .card { background: #1a1a2e; border: 1px solid #2d2d4e; border-radius: 16px; padding: 28px; max-width: 860px; margin: 0 auto 24px; }
  h1 { font-size: 22px; font-weight: 700; color: #a78bfa; margin-bottom: 4px; }
  h2 { font-size: 16px; font-weight: 600; color: #c4b5fd; margin-bottom: 16px; }
  .badge { display: inline-block; font-size: 11px; padding: 2px 8px; border-radius: 99px; font-weight: 600; }
  .badge-today { background: #14532d; color: #86efac; }
  .badge-old   { background: #7f1d1d; color: #fca5a5; }
  .msg { background: #14532d; border: 1px solid #166534; color: #86efac; padding: 14px 20px; border-radius: 10px; margin-bottom: 20px; font-weight: 600; }
  table { width: 100%; border-collapse: collapse; font-size: 14px; }
  th { text-align: left; padding: 10px 12px; border-bottom: 1px solid #2d2d4e; color: #94a3b8; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; }
  td { padding: 12px 12px; border-bottom: 1px solid #1e1e3a; vertical-align: middle; }
  tr:last-child td { border-bottom: none; }
  .btn { display: inline-block; padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; border: none; cursor: pointer; transition: opacity .15s; }
  .btn:hover { opacity: 0.85; }
  .btn-danger { background: #ef4444; color: #fff; }
  .btn-warning { background: #f59e0b; color: #000; }
  .btn-purple { background: #7c3aed; color: #fff; }
  .btn-gray { background: #374151; color: #d1d5db; }
  .actions { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 20px; }
  .empty { color: #6b7280; font-style: italic; padding: 20px 0; text-align: center; }
  .warn-box { background: #431407; border: 1px solid #c2410c; border-radius: 10px; padding: 16px 20px; margin-bottom: 20px; }
  .warn-box p { color: #fdba74; font-size: 14px; line-height: 1.6; }
  .course-row { font-weight: 600; color: #e2e8f0; }
  .lesson-row { color: #94a3b8; font-size: 13px; }
  .indent { padding-left: 24px; }
</style>
</head>
<body>

<div class="card">
  <h1>🧹 Devithor LMS — Content Cleanup</h1>
  <p style="color:#64748b;font-size:13px;margin-top:4px;margin-bottom:20px">
    Today: <strong style="color:#a78bfa"><?= $today ?></strong> &nbsp;·&nbsp;
    Courses: <strong><?= count($courses) ?></strong> &nbsp;·&nbsp;
    Lessons: <strong><?= count($lessons) ?></strong>
  </p>

  <?php if ($message): ?>
    <div class="msg"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <div class="warn-box">
    <p>⚠️ <strong>This deletes data permanently.</strong> User accounts are NOT affected — only course/lesson content and related progress, feedback, quiz data.</p>
    <p style="margin-top:8px">After cleanup, <strong>delete this file from the server</strong>: <code style="background:#1a1a2e;padding:2px 6px;border-radius:4px">public/cleanup.php</code></p>
  </div>

  <?php if (empty($courses)): ?>
    <p class="empty">No courses in database. Fresh start ready!</p>
  <?php else: ?>
    <h2>Current Courses &amp; Lessons</h2>
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Title</th>
          <th>Created</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($courses as $course):
            $isToday = str_starts_with($course['created_at'], $today);
            $courseLessons = array_filter($lessons, fn($l) => $l['course_id'] == $course['id']);
        ?>
        <tr>
          <td style="color:#6b7280;width:40px">#<?= $course['id'] ?></td>
          <td class="course-row"><?= htmlspecialchars($course['title']) ?>
            <span style="color:#475569;font-weight:400;font-size:12px;margin-left:8px">(<?= count($courseLessons) ?> lesson<?= count($courseLessons) != 1 ? 's' : '' ?>)</span>
          </td>
          <td style="color:#64748b;font-size:13px"><?= $course['created_at'] ?></td>
          <td>
            <span class="badge <?= $isToday ? 'badge-today' : 'badge-old' ?>">
              <?= $isToday ? '✓ Today' : '🗑 Old' ?>
            </span>
          </td>
          <td>
            <form method="post" style="display:inline" onsubmit="return confirm('Delete course &quot;<?= htmlspecialchars(addslashes($course['title'])) ?>&quot; and all its content?')">
              <input type="hidden" name="action" value="delete_course">
              <input type="hidden" name="course_id" value="<?= $course['id'] ?>">
              <button class="btn btn-danger">Delete</button>
            </form>
          </td>
        </tr>
        <?php foreach ($courseLessons as $lesson): ?>
        <tr class="lesson-row">
          <td style="color:#374151">&nbsp;</td>
          <td class="indent">↳ <?= htmlspecialchars($lesson['title']) ?></td>
          <td style="color:#4b5563;font-size:12px"><?= $lesson['created_at'] ?></td>
          <td></td>
          <td></td>
        </tr>
        <?php endforeach; ?>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <div class="actions">
    <form method="post" onsubmit="return confirm('Delete ALL courses and lessons created BEFORE today? Today\'s content will be kept.')">
      <input type="hidden" name="action" value="delete_old">
      <button class="btn btn-warning">🗑 Delete Old Content (keep today)</button>
    </form>
    <form method="post" onsubmit="return confirm('DELETE EVERYTHING? All courses, lessons, progress, quizzes will be gone. User accounts stay. This cannot be undone.')">
      <input type="hidden" name="action" value="delete_all">
      <button class="btn btn-danger">🔥 Delete Everything (fresh start)</button>
    </form>
  </div>
</div>

<div class="card" style="max-width:860px;margin:0 auto;">
  <h2>⚡ After Cleanup</h2>
  <p style="color:#94a3b8;font-size:14px;line-height:1.8">
    1. Delete this file from Hostinger File Manager: <code style="background:#0f0f11;padding:2px 8px;border-radius:4px;color:#a78bfa">public/cleanup.php</code><br>
    2. Add your courses from the admin dashboard<br>
    3. Add lessons and upload Cloudflare HLS links<br>
    4. App will show fresh content on next open
  </p>
</div>

</body>
</html>
