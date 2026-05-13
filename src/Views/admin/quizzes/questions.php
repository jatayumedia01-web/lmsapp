<?php
/** @var array $quiz */
/** @var array $questions */
/** @var array $me */
/** @var ?array $flash */
/** @var string $page */
use Devithor\View;
ob_start();
?>
<header>
    <div>
        <p><a href="/admin/quizzes/<?= View::e(rawurlencode($quiz['id'])) ?>">← Back to quiz settings</a></p>
        <h2><?= View::e($quiz['title']) ?> · Questions</h2>
        <p class="text-muted"><?= count($questions) ?> question<?= count($questions) === 1 ? '' : 's' ?> · pass at <?= (int) $quiz['pass_score_pct'] ?>%</p>
    </div>
</header>

<?php if ($flash): ?>
    <div class="alert alert-<?= View::e($flash['kind']) ?> auto-hide"><?= View::e($flash['message']) ?></div>
<?php endif; ?>

<!-- Existing questions list -->
<?php if (!empty($questions)): ?>
<div class="card">
    <h3 style="margin-bottom:8px">Question bank</h3>
    <?php foreach ($questions as $i => $q):
        $opts = $q['options_json'] ? json_decode((string) $q['options_json'], true) : null;
    ?>
        <div class="question-card">
            <div class="question-head">
                <span class="rank-badge"><?= $i + 1 ?></span>
                <span class="badge badge-muted"><?= View::e($q['question_type']) ?></span>
                <span class="badge badge-primary"><?= (int) $q['points'] ?> pt<?= (int) $q['points'] === 1 ? '' : 's' ?></span>
                <div class="spacer"></div>
                <form method="post" action="/admin/quizzes/questions/<?= (int) $q['id'] ?>/reorder?dir=up" style="display:inline">
                    <button type="submit" class="btn btn-ghost btn-sm">↑</button>
                </form>
                <form method="post" action="/admin/quizzes/questions/<?= (int) $q['id'] ?>/reorder?dir=down" style="display:inline">
                    <button type="submit" class="btn btn-ghost btn-sm">↓</button>
                </form>
                <form method="post" action="/admin/quizzes/questions/<?= (int) $q['id'] ?>/delete" style="display:inline">
                    <button type="submit" class="btn btn-danger btn-sm" data-confirm="Delete this question?">Delete</button>
                </form>
            </div>
            <p class="question-text"><?= View::e($q['question_text']) ?></p>
            <?php if (is_array($opts)): ?>
                <ul class="question-opts">
                    <?php foreach ($opts as $opt): ?>
                        <li class="<?= !empty($opt['is_correct']) ? 'opt-correct' : '' ?>">
                            <?= !empty($opt['is_correct']) ? '✓ ' : '○ ' ?>
                            <?= View::e($opt['text']) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php elseif (!empty($q['correct_answer_text'])): ?>
                <p class="text-muted" style="font-size:13px">Expected answer: <code><?= View::e($q['correct_answer_text']) ?></code></p>
            <?php endif; ?>
            <?php if (!empty($q['explanation'])): ?>
                <p class="text-muted" style="font-size:12px;border-left:2px solid var(--border);padding-left:10px">💡 <?= View::e($q['explanation']) ?></p>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Add new question -->
<div class="card">
    <h3 style="margin-bottom:8px">+ Add question</h3>
    <form method="post" action="/admin/quizzes/<?= View::e(rawurlencode($quiz['id'])) ?>/questions" id="add-question-form">
        <div class="field-row">
            <div class="field">
                <label>Type</label>
                <select name="question_type" id="question_type">
                    <option value="MCQ">Multiple choice (one correct)</option>
                    <option value="MULTI">Multiple correct</option>
                    <option value="TRUE_FALSE">True / False</option>
                    <option value="SHORT">Short answer</option>
                    <option value="FILL">Fill in the blank</option>
                </select>
            </div>
            <div class="field">
                <label>Points</label>
                <input name="points" type="number" min="1" value="1">
            </div>
        </div>

        <div class="field">
            <label>Question text *</label>
            <textarea name="question_text" rows="2" required placeholder="What is 2 + 2?"></textarea>
        </div>

        <!-- MCQ / MULTI options -->
        <div class="qtype-block" data-for="MCQ MULTI" style="display:block">
            <label class="text-muted" style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.4px;margin-bottom:6px;display:block">Options · check the correct one(s)</label>
            <div id="options-list">
                <?php for ($i = 0; $i < 4; $i++): ?>
                    <div class="opt-row">
                        <input type="radio"    name="mcq_correct"   value="<?= $i ?>" <?= $i === 0 ? 'checked' : '' ?> class="mcq-radio">
                        <input type="checkbox" name="multi_correct[]" value="<?= $i ?>" class="multi-check" style="display:none">
                        <input type="text" name="option_text[]" placeholder="Option <?= $i + 1 ?>">
                    </div>
                <?php endfor; ?>
            </div>
            <button type="button" class="btn btn-ghost btn-sm" onclick="addOption()">+ Add option</button>
        </div>

        <!-- True / False -->
        <div class="qtype-block" data-for="TRUE_FALSE" style="display:none">
            <label>Correct answer</label>
            <div>
                <label><input type="radio" name="tf_correct" value="1" checked> True</label>
                &nbsp;&nbsp;
                <label><input type="radio" name="tf_correct" value="0"> False</label>
            </div>
        </div>

        <!-- SHORT / FILL -->
        <div class="qtype-block" data-for="SHORT FILL" style="display:none">
            <label>Expected answer (case-insensitive exact match)</label>
            <input name="correct_answer_text" type="text" placeholder="42">
        </div>

        <div class="field">
            <label>Explanation (shown to learner after answering)</label>
            <textarea name="explanation" rows="2" placeholder="Optional. e.g. 'Sum of 2 and 2 is 4.'"></textarea>
        </div>

        <div class="field">
            <label>Image URL (optional)</label>
            <input name="image_url" type="url" placeholder="https://...">
        </div>

        <button type="submit" class="btn btn-primary">Add question</button>
    </form>
</div>

<script>
(function () {
    var typeSel = document.getElementById('question_type');
    var blocks  = document.querySelectorAll('.qtype-block');

    function update() {
        var t = typeSel.value;
        blocks.forEach(function (b) {
            b.style.display = b.dataset.for.indexOf(t) >= 0 ? 'block' : 'none';
        });
        // toggle radio vs checkbox in options
        document.querySelectorAll('.mcq-radio').forEach(function (r) { r.style.display = (t === 'MCQ') ? 'inline-block' : 'none'; });
        document.querySelectorAll('.multi-check').forEach(function (c) { c.style.display = (t === 'MULTI') ? 'inline-block' : 'none'; });
    }
    typeSel.addEventListener('change', update);
    update();

    window.addOption = function () {
        var list = document.getElementById('options-list');
        var idx = list.querySelectorAll('.opt-row').length;
        var div = document.createElement('div');
        div.className = 'opt-row';
        div.innerHTML = '<input type="radio" name="mcq_correct" value="' + idx + '" class="mcq-radio">'
                      + '<input type="checkbox" name="multi_correct[]" value="' + idx + '" class="multi-check" style="display:none">'
                      + '<input type="text" name="option_text[]" placeholder="Option ' + (idx + 1) + '">';
        list.appendChild(div);
        update();
    };
})();
</script>
<?php
$content = ob_get_clean();
$title   = $quiz['title'] . ' · questions';
include __DIR__ . '/../../layouts/admin.php';
