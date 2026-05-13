<?php
/** @var array $quiz */
/** @var string $mode */
/** @var array $errors */
/** @var array $lessons */
/** @var array $me */
/** @var ?array $flash */
/** @var string $page */
use Devithor\View;

$action = $mode === 'create' ? '/admin/quizzes' : '/admin/quizzes/' . $quiz['id'];
$heading = $mode === 'create' ? 'New quiz' : 'Edit quiz';
$flash = $flash ?? null;

ob_start();
?>
<header>
    <div>
        <p><a href="/admin/quizzes">← Back to quizzes</a></p>
        <h2><?= View::e($heading) ?></h2>
    </div>
    <?php if ($mode === 'edit'): ?>
        <div class="spacer"></div>
        <a href="/admin/quizzes/<?= View::e(rawurlencode($quiz['id'])) ?>/questions" class="btn btn-secondary">Manage questions →</a>
    <?php endif; ?>
</header>

<?php if ($flash): ?>
    <div class="alert alert-<?= View::e($flash['kind']) ?> auto-hide"><?= View::e($flash['message']) ?></div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">Please fix: <?= implode(', ', array_keys($errors)) ?></div>
<?php endif; ?>

<form method="post" action="<?= View::e($action) ?>" class="card">
    <?php if ($mode === 'create'): ?>
        <div class="field">
            <label for="id">Quiz ID (optional)</label>
            <input id="id" name="id" type="text" value="<?= View::e((string) ($quiz['id'] ?? '')) ?>" placeholder="auto: qz_xxxx">
        </div>
    <?php endif; ?>

    <div class="field-row">
        <div class="field">
            <label for="scope">Scope</label>
            <select id="scope" name="scope">
                <option value="LESSON"  <?= $quiz['scope'] === 'LESSON'  ? 'selected' : '' ?>>Lesson</option>
                <option value="SUBJECT" <?= $quiz['scope'] === 'SUBJECT' ? 'selected' : '' ?>>Subject</option>
                <option value="CLASS"   <?= $quiz['scope'] === 'CLASS'   ? 'selected' : '' ?>>Class</option>
            </select>
        </div>
        <div class="field" style="flex:2">
            <label for="parent_id">Attach to (lesson ID / subject ID / class ID) *</label>
            <input id="parent_id" name="parent_id" type="text" value="<?= View::e((string) $quiz['parent_id']) ?>" required placeholder="Pick from list below or paste an ID">
            <details style="margin-top:6px">
                <summary class="text-muted" style="cursor:pointer;font-size:12px">Browse lessons</summary>
                <select onchange="document.getElementById('parent_id').value = this.value" style="margin-top:6px">
                    <option value="">Pick a lesson…</option>
                    <?php foreach ($lessons as $l): ?>
                        <option value="<?= View::e($l['id']) ?>"><?= View::e($l['course_title'] ?? '—') ?> — <?= View::e($l['lesson_title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </details>
        </div>
    </div>

    <div class="field">
        <label for="title">Title *</label>
        <input id="title" name="title" type="text" value="<?= View::e((string) $quiz['title']) ?>" required>
    </div>

    <div class="field">
        <label for="description">Short description</label>
        <textarea id="description" name="description" rows="2"><?= View::e((string) $quiz['description']) ?></textarea>
    </div>

    <div class="field">
        <label for="instructions">Instructions for learners (shown before they start)</label>
        <textarea id="instructions" name="instructions" rows="3"><?= View::e((string) $quiz['instructions']) ?></textarea>
    </div>

    <div class="field-row">
        <div class="field"><label>Pass score %</label><input name="pass_score_pct" type="number" min="0" max="100" value="<?= (int) $quiz['pass_score_pct'] ?>"></div>
        <div class="field"><label>Time limit (minutes)</label><input name="time_limit_minutes" type="number" min="0" value="<?= (int) $quiz['time_limit_minutes'] ?>"><small class="text-muted">0 = no limit</small></div>
        <div class="field"><label>Max attempts</label><input name="max_attempts" type="number" min="0" value="<?= (int) $quiz['max_attempts'] ?>"><small class="text-muted">0 = unlimited</small></div>
    </div>

    <div class="field-row">
        <div class="field"><label><input type="checkbox" name="shuffle_questions"   value="1" <?= ((int) $quiz['shuffle_questions'])   ? 'checked' : '' ?>> Shuffle questions</label></div>
        <div class="field"><label><input type="checkbox" name="shuffle_options"     value="1" <?= ((int) $quiz['shuffle_options'])     ? 'checked' : '' ?>> Shuffle options</label></div>
        <div class="field"><label><input type="checkbox" name="show_correct_answers" value="1" <?= ((int) $quiz['show_correct_answers']) ? 'checked' : '' ?>> Show answers after submit</label></div>
        <div class="field"><label><input type="checkbox" name="is_published"        value="1" <?= ((int) $quiz['is_published'])        ? 'checked' : '' ?>> Published</label></div>
    </div>

    <div class="flex-row">
        <button type="submit" class="btn btn-primary"><?= $mode === 'create' ? 'Create quiz' : 'Save changes' ?></button>
        <a href="/admin/quizzes" class="btn btn-ghost">Cancel</a>
        <div class="spacer"></div>
        <?php if ($mode === 'edit'): ?>
            <button type="button" class="btn btn-danger btn-sm"
                    data-confirm="Delete this quiz, its questions and all attempts?"
                    onclick="document.getElementById('quiz-delete').submit()">Delete</button>
        <?php endif; ?>
    </div>
</form>

<?php if ($mode === 'edit'): ?>
    <form id="quiz-delete" method="post" action="/admin/quizzes/<?= View::e($quiz['id']) ?>/delete" style="display:none"></form>
<?php endif; ?>
<?php
$content = ob_get_clean();
$title   = $heading;
include __DIR__ . '/../../layouts/admin.php';
