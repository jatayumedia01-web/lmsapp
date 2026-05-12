<?php use Devithor\View; ob_start(); ?>
<header>
    <div>
        <h2><?= $exam ? 'Edit Exam' : 'New Mock Exam' ?></h2>
        <p><?= $exam ? View::e($exam['title']) : 'Configure exam settings' ?></p>
    </div>
    <div class="spacer"></div>
    <a href="/admin/exams" class="btn btn-ghost">← Back</a>
</header>

<form method="post" action="<?= $exam ? '/admin/exams/' . View::e($exam['id']) : '/admin/exams' ?>">
<div class="grid-2">
<div class="card">
    <h3>Basic Info</h3>
    <div class="form-group">
        <label>Exam Title *</label>
        <input type="text" name="title" class="form-control" required value="<?= View::e($exam['title'] ?? '') ?>" placeholder="e.g. Class 10 Mathematics Final Exam">
    </div>
    <div class="form-group">
        <label>Description</label>
        <textarea name="description" class="form-control" rows="3"><?= View::e($exam['description'] ?? '') ?></textarea>
    </div>
    <div class="form-group">
        <label>Target Class</label>
        <select name="class_id" class="form-control">
            <option value="">All classes</option>
            <?php foreach ($classes as $cl): ?>
            <option value="<?= View::e($cl['id']) ?>" <?= ($exam['class_id'] ?? '') === $cl['id'] ? 'selected' : '' ?>><?= View::e($cl['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label>Subject Tag</label>
        <input type="text" name="subject_tag" class="form-control" value="<?= View::e($exam['subject_tag'] ?? '') ?>" placeholder="e.g. Mathematics, Physics">
    </div>
    <div class="form-group">
        <label>Plan Required</label>
        <select name="plan_required" class="form-control">
            <option value="">All plans (Free)</option>
            <option value="BASIC" <?= ($exam['plan_required'] ?? '') === 'BASIC' ? 'selected' : '' ?>>Basic & above</option>
            <option value="PREMIUM" <?= ($exam['plan_required'] ?? '') === 'PREMIUM' ? 'selected' : '' ?>>Premium only</option>
        </select>
    </div>
</div>

<div class="card">
    <h3>Exam Rules</h3>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <div class="form-group">
            <label>Duration (minutes) *</label>
            <input type="number" name="duration_minutes" class="form-control" min="1" max="360" required value="<?= (int)($exam['duration_minutes'] ?? 60) ?>">
        </div>
        <div class="form-group">
            <label>Total Marks *</label>
            <input type="number" name="total_marks" class="form-control" min="1" required value="<?= (int)($exam['total_marks'] ?? 100) ?>">
        </div>
        <div class="form-group">
            <label>Pass Marks *</label>
            <input type="number" name="pass_marks" class="form-control" min="0" required value="<?= (int)($exam['pass_marks'] ?? 40) ?>">
        </div>
        <div class="form-group">
            <label>Max Attempts</label>
            <input type="number" name="max_attempts" class="form-control" min="1" value="<?= (int)($exam['max_attempts'] ?? 1) ?>">
        </div>
        <div class="form-group">
            <label>Schedule Start</label>
            <input type="datetime-local" name="scheduled_at" class="form-control" value="<?= $exam['scheduled_at'] ? date('Y-m-d\TH:i', strtotime($exam['scheduled_at'])) : '' ?>">
        </div>
        <div class="form-group">
            <label>Expires At</label>
            <input type="datetime-local" name="expires_at" class="form-control" value="<?= $exam['expires_at'] ? date('Y-m-d\TH:i', strtotime($exam['expires_at'])) : '' ?>">
        </div>
    </div>
    <div style="display:flex;gap:24px;margin-top:8px">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
            <input type="checkbox" name="shuffle_questions" <?= ($exam['shuffle_questions'] ?? 1) ? 'checked' : '' ?>>
            Shuffle questions
        </label>
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
            <input type="checkbox" name="show_answers_after" <?= ($exam['show_answers_after'] ?? 1) ? 'checked' : '' ?>>
            Show answers after submission
        </label>
        <?php if ($exam): ?>
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
            <input type="checkbox" name="is_published" <?= ($exam['is_published'] ?? 0) ? 'checked' : '' ?>>
            Published (live)
        </label>
        <?php endif; ?>
    </div>
    <div class="form-group" style="margin-top:16px">
        <label>Rules & Instructions (shown to students before exam)</label>
        <textarea name="rules_text" class="form-control" rows="5" placeholder="1. No browser switching&#10;2. Submit before time runs out&#10;3. Each question carries equal marks"><?= View::e($exam['rules_text'] ?? '') ?></textarea>
    </div>
    <button type="submit" class="btn btn-primary" style="width:100%">
        <?= $exam ? 'Save Changes → Go to Questions' : 'Create Exam → Add Questions' ?>
    </button>
</div>
</div>
</form>
<?php $content = ob_get_clean(); $title = $exam ? 'Edit Exam' : 'New Exam'; include __DIR__ . '/../../layouts/admin.php'; ?>
