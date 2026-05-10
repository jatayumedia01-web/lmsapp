<?php
/** @var array $course */
/** @var array $lesson */
/** @var string $mode  'create' | 'edit' */
/** @var array $errors */
/** @var array $me */
/** @var string $page */
use Devithor\View;

$action  = $mode === 'create'
    ? '/admin/courses/' . $course['id'] . '/lessons'
    : '/admin/lessons/' . $lesson['id'];
$heading = $mode === 'create' ? 'New lesson' : 'Edit lesson';

ob_start();
?>
<header>
    <div>
        <p><a href="/admin/courses/<?= View::e($course['id']) ?>/lessons">← Back to lessons</a></p>
        <h2><?= View::e($heading) ?></h2>
        <p class="text-muted"><?= View::e($course['title']) ?></p>
    </div>
    <?php if ($mode === 'edit'): ?>
        <div class="spacer"></div>
        <a href="/admin/lessons/<?= View::e(urlencode($lesson['id'])) ?>/video" class="btn btn-secondary">📺 Video preview &amp; analytics</a>
    <?php endif; ?>
</header>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        Please fix the highlighted fields:
        <ul style="margin:6px 0 0 16px">
        <?php foreach ($errors as $field => $msg): ?>
            <li><strong><?= View::e($field) ?></strong>: <?= View::e($msg) ?></li>
        <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" action="<?= View::e($action) ?>" class="card">
    <?php if ($mode === 'create'): ?>
        <div class="field">
            <label for="id">Lesson ID (optional)</label>
            <input id="id" name="id" type="text" value="<?= View::e((string) ($lesson['id'] ?? '')) ?>" placeholder="auto: <?= View::e($course['id']) ?>_l<?= ((int) $lesson['order_index']) + 1 ?>">
        </div>
    <?php endif; ?>

    <div class="field">
        <label for="title">Title *</label>
        <input id="title" name="title" type="text" value="<?= View::e((string) $lesson['title']) ?>" required>
    </div>

    <div class="field">
        <label for="description">Description *</label>
        <textarea id="description" name="description" rows="3" required><?= View::e((string) $lesson['description']) ?></textarea>
    </div>

    <!-- ============ VIDEO BLOCK ============ -->
    <div class="video-editor">
        <h3 style="margin-top:0">Video</h3>
        <p class="text-muted" style="margin-top:-4px;font-size:12px">
            Paste a YouTube (unlisted), Vimeo, Cloudflare Stream, HLS .m3u8 or MP4 URL. Provider is auto-detected.
        </p>

        <div class="field">
            <label for="video_url">Video URL *</label>
            <input id="video_url" name="video_url" type="url" value="<?= View::e((string) $lesson['video_url']) ?>" required
                   placeholder="https://youtu.be/dQw4w9WgXcQ">
            <small class="text-muted" id="video-detect-hint">Detected: <span id="provider-badge" class="badge badge-muted"><?= View::e((string) ($lesson['video_provider'] ?? 'OTHER')) ?></span></small>
        </div>

        <div class="video-preview-box" id="video-preview-box" style="<?= empty($lesson['thumbnail_url']) ? 'display:none' : '' ?>">
            <div class="video-thumb">
                <img id="video-thumb-img" src="<?= View::e((string) ($lesson['thumbnail_url'] ?? '')) ?>" alt="">
                <span class="video-play-icon">▶</span>
            </div>
            <div class="video-meta">
                <strong id="video-title-line"><?= View::e((string) ($lesson['title'] ?? 'Auto-detected video')) ?></strong>
                <div class="text-muted" style="font-size:12px;margin-top:4px" id="video-author-line"></div>
                <div style="margin-top:8px">
                    <a id="video-open-link" href="<?= View::e((string) $lesson['video_url']) ?>" target="_blank" rel="noopener noreferrer" class="text-muted" style="font-size:12px">Open in new tab ↗</a>
                </div>
            </div>
        </div>

        <div class="field-row">
            <div class="field">
                <label for="thumbnail_url">Thumbnail URL (optional)</label>
                <input id="thumbnail_url" name="thumbnail_url" type="url" value="<?= View::e((string) ($lesson['thumbnail_url'] ?? '')) ?>" placeholder="auto from YouTube">
            </div>
            <div class="field">
                <label for="subtitles_url">Subtitles URL (.vtt) (optional)</label>
                <input id="subtitles_url" name="subtitles_url" type="url" value="<?= View::e((string) ($lesson['subtitles_url'] ?? '')) ?>" placeholder="https://.../captions.vtt">
            </div>
        </div>

        <div class="field">
            <label for="chapters_json">Chapters (optional, one per line: <code>120 Title</code>)</label>
            <textarea id="chapters_json" name="chapters_json" rows="3" placeholder="0 Intro&#10;120 Setup&#10;360 Demo"><?= View::e((string) ($lesson['chapters_json'] ?? '')) ?></textarea>
            <small class="text-muted">Each line = seconds offset, then chapter title. Saved as JSON.</small>
        </div>

        <div class="field-row">
            <div class="field">
                <label><input type="checkbox" name="is_downloadable" value="1" <?= ((int) ($lesson['is_downloadable'] ?? 0)) ? 'checked' : '' ?>> Allow download</label>
            </div>
            <div class="field">
                <label><input type="checkbox" name="allow_speed" value="1" <?= ((int) ($lesson['allow_speed'] ?? 1)) ? 'checked' : '' ?>> Show speed control</label>
            </div>
            <div class="field">
                <label><input type="checkbox" name="watermark_enabled" value="1" <?= ((int) ($lesson['watermark_enabled'] ?? 0)) ? 'checked' : '' ?>> Watermark (user email overlay)</label>
            </div>
        </div>
    </div>
    <!-- ============ /VIDEO BLOCK ============ -->

    <div class="field-row" style="margin-top:24px">
        <div class="field">
            <label for="order_index">Order index (0-based)</label>
            <input id="order_index" name="order_index" type="number" min="0" value="<?= (int) $lesson['order_index'] ?>">
        </div>
        <div class="field">
            <label for="duration_seconds">Duration (seconds)</label>
            <input id="duration_seconds" name="duration_seconds" type="number" min="0" value="<?= (int) $lesson['duration_seconds'] ?>">
        </div>
    </div>

    <div class="field">
        <label><input type="checkbox" name="is_free_preview" value="1" <?= !empty($lesson['is_free_preview']) ? 'checked' : '' ?>> Free preview (visible to non-paying users)</label>
    </div>

    <div class="flex-row">
        <button type="submit" class="btn btn-primary">
            <?= $mode === 'create' ? 'Create lesson' : 'Save changes' ?>
        </button>
        <a href="/admin/courses/<?= View::e($course['id']) ?>/lessons" class="btn btn-ghost">Cancel</a>
        <div class="spacer"></div>
        <?php if ($mode === 'edit'): ?>
            <button type="button"
                    class="btn btn-danger btn-sm"
                    data-confirm="Delete this lesson?"
                    onclick="document.getElementById('delete-form').submit()">
                Delete
            </button>
        <?php endif; ?>
    </div>
</form>

<?php if ($mode === 'edit'): ?>
    <form id="delete-form" method="post" action="/admin/lessons/<?= View::e((string) $lesson['id']) ?>/delete" style="display:none"></form>
<?php endif; ?>

<script>
// Live URL parser. As the admin types/pastes, hit /admin/api/video-detect to
// fill provider badge + thumbnail + auto-title (debounced 500ms).
(function () {
    var input  = document.getElementById('video_url');
    var badge  = document.getElementById('provider-badge');
    var thumb  = document.getElementById('video-thumb-img');
    var titleLn= document.getElementById('video-title-line');
    var authLn = document.getElementById('video-author-line');
    var openLn = document.getElementById('video-open-link');
    var box    = document.getElementById('video-preview-box');
    var thumbInput = document.getElementById('thumbnail_url');
    if (!input) return;

    var t = null;
    function detect() {
        var url = input.value.trim();
        if (!url) { box.style.display = 'none'; badge.textContent = 'OTHER'; return; }
        fetch('/admin/api/video-detect?url=' + encodeURIComponent(url), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                badge.textContent = d.provider || 'OTHER';
                badge.className = 'badge ' + (d.provider === 'YOUTUBE' ? 'badge-danger' :
                    d.provider === 'CLOUDFLARE' ? 'badge-info' :
                    d.provider === 'VIMEO' ? 'badge-success' : 'badge-muted');
                if (d.thumbnail_url) {
                    thumb.src = d.thumbnail_url;
                    if (thumbInput && !thumbInput.value) thumbInput.placeholder = d.thumbnail_url;
                    box.style.display = 'flex';
                } else if (d.provider !== 'OTHER') {
                    box.style.display = 'flex';
                    thumb.src = '';
                } else {
                    box.style.display = 'none';
                }
                if (d.title)       titleLn.textContent = d.title;
                if (d.author_name) authLn.textContent = 'by ' + d.author_name;
                openLn.href = url;
            })
            .catch(function () { /* ignore */ });
    }
    input.addEventListener('input', function () {
        clearTimeout(t);
        t = setTimeout(detect, 500);
    });
    if (input.value) detect();
})();
</script>
<?php
$content = ob_get_clean();
$title   = $heading;
include __DIR__ . '/../../layouts/admin.php';
