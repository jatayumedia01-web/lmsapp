<?php use Devithor\View; ob_start(); ?>
<header>
    <div>
        <h2><?= View::e($exam['title']) ?></h2>
        <p><?= count($questions) ?> questions · <?= (int)$exam['duration_minutes'] ?> min · <?= (int)$exam['pass_marks'] ?>/<?= (int)$exam['total_marks'] ?> to pass</p>
    </div>
    <div class="spacer"></div>
    <a href="/admin/exams/<?= View::e($exam['id']) ?>" class="btn btn-ghost">Edit Settings</a>
    <a href="/admin/exams/<?= View::e($exam['id']) ?>/results" class="btn btn-ghost">Results</a>
    <form method="post" action="/admin/exams/<?= View::e($exam['id']) ?>/publish" style="display:inline">
        <button class="btn <?= $exam['is_published'] ? 'btn-ghost' : 'btn-primary' ?>">
            <?= $exam['is_published'] ? '⏸ Unpublish' : '🚀 Publish Live' ?>
        </button>
    </form>
</header>

<?php if (!$exam['is_published']): ?>
<div style="background:#1a1a2e;border:1px solid #a78bfa;border-radius:8px;padding:12px 16px;margin-bottom:20px;color:#c4b5fd;font-size:13px">
    ⚠ This exam is in Draft mode — students cannot see it. Add questions then click "Publish Live".
</div>
<?php endif; ?>

<div class="grid-2">
<div>
<?php foreach ($questions as $i => $q): ?>
<div class="card" style="margin-bottom:12px">
    <div style="display:flex;align-items:flex-start;gap:12px">
        <span style="background:#4f46e5;color:#fff;border-radius:50%;width:28px;height:28px;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0"><?= $i+1 ?></span>
        <div style="flex:1">
            <p style="margin:0 0 10px;font-weight:500"><?= View::e($q['question_text']) ?></p>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;font-size:13px">
                <?php foreach (['A','B','C','D'] as $opt): ?>
                    <?php $val = $q['option_' . strtolower($opt)] ?? null; if (!$val) continue; ?>
                    <div style="padding:6px 10px;border-radius:6px;<?= $q['correct_option'] === $opt ? 'background:#14532d;color:#86efac;border:1px solid #22c55e' : 'background:#1e1e2e;color:#94a3b8' ?>">
                        <strong><?= $opt ?>.</strong> <?= View::e($val) ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if ($q['explanation']): ?>
            <p style="margin:8px 0 0;font-size:12px;color:#6b7280">💡 <?= View::e($q['explanation']) ?></p>
            <?php endif; ?>
        </div>
        <div style="display:flex;gap:4px;align-items:center">
            <span style="font-size:12px;color:#6b7280"><?= (int)$q['marks'] ?>m</span>
            <form method="post" action="/admin/exams/questions/<?= View::e($q['id']) ?>/delete" onsubmit="return confirm('Delete this question?')">
                <button class="btn btn-ghost btn-sm" style="color:#ef4444">✕</button>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php if (empty($questions)): ?>
<div class="card" style="text-align:center;padding:32px;color:#6b7280">Add questions using the form →</div>
<?php endif; ?>
</div>

<div class="card" style="position:sticky;top:20px;align-self:start">
    <h3>Add Question</h3>
    <form method="post" action="/admin/exams/<?= View::e($exam['id']) ?>/questions">
        <div class="form-group">
            <label>Question *</label>
            <textarea name="question_text" class="form-control" rows="3" required placeholder="Type the question here..."></textarea>
        </div>
        <?php foreach (['A','B','C','D'] as $opt): ?>
        <div class="form-group">
            <label>Option <?= $opt ?> <?= in_array($opt, ['A','B']) ? '*' : '(optional)' ?></label>
            <input type="text" name="option_<?= strtolower($opt) ?>" class="form-control" <?= in_array($opt, ['A','B']) ? 'required' : '' ?> placeholder="Option <?= $opt ?>">
        </div>
        <?php endforeach; ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div class="form-group">
                <label>Correct Answer *</label>
                <select name="correct_option" class="form-control" required>
                    <option value="A">A</option><option value="B">B</option>
                    <option value="C">C</option><option value="D">D</option>
                </select>
            </div>
            <div class="form-group">
                <label>Marks</label>
                <input type="number" name="marks" class="form-control" value="1" min="1">
            </div>
        </div>
        <div class="form-group">
            <label>Explanation (optional)</label>
            <input type="text" name="explanation" class="form-control" placeholder="Why this answer is correct">
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%">+ Add Question</button>
    </form>
</div>
</div>
<?php $content = ob_get_clean(); $title = 'Questions — ' . ($exam['title'] ?? ''); include __DIR__ . '/../../layouts/admin.php'; ?>
