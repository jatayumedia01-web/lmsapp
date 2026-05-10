<?php
/** @var array $campaigns */
/** @var array $stats */
/** @var array $classes */
/** @var array $courses */
/** @var array $me */
/** @var ?array $flash */
/** @var string $page */
use Devithor\View;
ob_start();
?>
<header>
    <div>
        <h2>Notifications</h2>
        <p>Compose broadcasts, see history, monitor reach.</p>
    </div>
</header>

<?php if ($flash): ?>
    <div class="alert alert-<?= View::e($flash['kind']) ?> auto-hide"><?= View::e($flash['message']) ?></div>
<?php endif; ?>

<div class="grid-stats">
    <div class="stat"><div class="stat-label">Total sent</div><div class="stat-value"><?= number_format((int) $stats['total_sent']) ?></div></div>
    <div class="stat"><div class="stat-label">Campaigns</div><div class="stat-value"><?= number_format((int) $stats['campaigns']) ?></div></div>
    <div class="stat"><div class="stat-label">Unread (all users)</div><div class="stat-value"><?= number_format((int) $stats['unread']) ?></div></div>
    <div class="stat"><div class="stat-label">Last 24h</div><div class="stat-value"><?= number_format((int) $stats['last_24h']) ?></div></div>
</div>

<div class="grid-2">
    <div class="card">
        <h3>Compose</h3>
        <form method="post" action="/admin/notifications">
            <div class="field"><label>Title *</label><input name="title" type="text" required placeholder="New lesson released!"></div>
            <div class="field"><label>Body *</label><textarea name="body" rows="3" required placeholder="Algebra basics is now live in Mathematics."></textarea></div>
            <div class="field-row">
                <div class="field"><label>Link (optional)</label><input name="link" type="text" placeholder="/lessons/abc123"></div>
                <div class="field"><label>Icon (emoji)</label><input name="icon" type="text" maxlength="6" placeholder="🎉"></div>
            </div>
            <div class="field-row">
                <div class="field">
                    <label>Audience</label>
                    <select name="target" id="target-sel" onchange="onTarget()">
                        <option value="ALL">All learners</option>
                        <option value="CLASS">Specific class</option>
                        <option value="SUBJECT">Specific subject</option>
                        <option value="PAYING">Paying subscribers</option>
                        <option value="BANNED">Banned users</option>
                        <option value="ROLE">Specific role</option>
                    </select>
                </div>
                <div class="field" id="target-id-wrap" style="display:none">
                    <label>Target id</label>
                    <select name="target_id" id="target-id">
                        <option value="">— pick —</option>
                    </select>
                </div>
            </div>
            <div class="field-row">
                <div class="field"><label><input type="checkbox" name="send_push"  value="1" checked> Send push (FCM)</label></div>
                <div class="field"><label><input type="checkbox" name="send_email" value="1"> Also send email</label></div>
            </div>
            <button type="submit" class="btn btn-primary" data-confirm="Send this notification to the selected audience?">Send now</button>
        </form>
    </div>

    <div class="card">
        <h3>Recent campaigns</h3>
        <?php if (empty($campaigns)): ?>
            <p class="text-muted">No campaigns yet.</p>
        <?php else: ?>
            <table class="table" style="margin-bottom:0">
                <thead><tr><th>Title</th><th>Audience</th><th class="text-right">Sent</th><th>When</th></tr></thead>
                <tbody>
                <?php foreach ($campaigns as $c): ?>
                    <tr>
                        <td>
                            <strong><?= View::e($c['title']) ?></strong>
                            <div class="text-muted" style="font-size:11px"><?= View::e(mb_substr((string) $c['body'], 0, 60)) ?>…</div>
                        </td>
                        <td>
                            <span class="badge badge-muted"><?= View::e($c['target']) ?></span>
                            <?php if (!empty($c['target_id'])): ?>
                                <code style="font-size:10px"><?= View::e($c['target_id']) ?></code>
                            <?php endif; ?>
                        </td>
                        <td class="text-right"><?= number_format((int) $c['sent_count']) ?></td>
                        <td class="text-muted" style="font-size:11px"><?= View::e(substr((string) ($c['sent_at'] ?? '—'), 0, 16)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
var classes = <?= json_encode(array_map(fn ($c) => ['id' => $c['id'], 'label' => $c['name']],   $classes)) ?>;
var courses = <?= json_encode(array_map(fn ($c) => ['id' => $c['id'], 'label' => $c['title']], $courses)) ?>;
var roles   = [{id:'STUDENT', label:'Students'}, {id:'INSTRUCTOR', label:'Instructors'}, {id:'PARENT', label:'Parents'}, {id:'ADMIN', label:'Admins'}];
function onTarget() {
    var t = document.getElementById('target-sel').value;
    var wrap = document.getElementById('target-id-wrap');
    var sel  = document.getElementById('target-id');
    var list = [];
    if (t === 'CLASS')   list = classes;
    if (t === 'SUBJECT') list = courses;
    if (t === 'ROLE')    list = roles;
    if (list.length === 0) { wrap.style.display = 'none'; sel.innerHTML = ''; return; }
    wrap.style.display = 'block';
    sel.innerHTML = '<option value="">— pick —</option>' + list.map(function (i) {
        return '<option value="' + i.id + '">' + i.label + '</option>';
    }).join('');
}
onTarget();
</script>
<?php
$content = ob_get_clean();
$title   = 'Notifications';
include __DIR__ . '/../../layouts/admin.php';
