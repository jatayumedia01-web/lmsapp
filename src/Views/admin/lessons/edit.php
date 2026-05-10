<?php
/** @var array $course */
/** @var array $lesson */
/** @var array $faqs */
/** @var string $mode  'create' | 'edit' */
/** @var array $errors */
/** @var array $me */
/** @var ?array $flash */
/** @var string $page */
use Devithor\View;

$action  = $mode === 'create'
    ? '/admin/courses/' . $course['id'] . '/lessons'
    : '/admin/lessons/' . $lesson['id'];
$heading = $mode === 'create' ? 'New lesson' : 'Edit lesson';
$faqs    = $faqs ?? [];

// Map current provider → tab to highlight on first paint.
$activeTab = (string) ($lesson['video_provider'] ?? 'YOUTUBE');
if (!in_array($activeTab, ['YOUTUBE','VIMEO','HLS','MP4','UPLOAD','CLOUDFLARE'], true)) {
    $activeTab = 'YOUTUBE';
}

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
        <a href="/admin/lessons/<?= View::e(urlencode($lesson['id'])) ?>/video" class="btn btn-secondary">📺 Preview &amp; analytics</a>
    <?php endif; ?>
</header>

<?php if (!empty($flash)): ?>
    <div class="alert alert-<?= View::e($flash['kind']) ?> auto-hide"><?= View::e($flash['message']) ?></div>
<?php endif; ?>

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

    <!-- ============ VIDEO BLOCK with tabbed provider picker ============ -->
    <div class="video-editor">
        <h3 style="margin-top:0">Video source</h3>
        <p class="text-muted" style="margin-top:-4px;font-size:12px">
            Pick where the video lives. We recommend <strong>YouTube unlisted</strong> for free unlimited bandwidth.
        </p>

        <div class="provider-tabs" id="provider-tabs">
            <button type="button" class="provider-tab" data-provider="YOUTUBE">
                <span class="provider-icon" style="background:#FF000022;color:#FF0033">▶</span>
                <span class="provider-label">YouTube</span>
            </button>
            <button type="button" class="provider-tab" data-provider="VIMEO">
                <span class="provider-icon" style="background:#1AB7EA22;color:#1AB7EA">V</span>
                <span class="provider-label">Vimeo</span>
            </button>
            <button type="button" class="provider-tab" data-provider="HLS">
                <span class="provider-icon" style="background:#7C5CFF22;color:#7C5CFF">≡</span>
                <span class="provider-label">HLS Stream</span>
            </button>
            <button type="button" class="provider-tab" data-provider="MP4">
                <span class="provider-icon" style="background:#10B98122;color:#10B981">↓</span>
                <span class="provider-label">MP4 URL</span>
            </button>
            <button type="button" class="provider-tab" data-provider="UPLOAD">
                <span class="provider-icon" style="background:#F59E0B22;color:#F59E0B">⇪</span>
                <span class="provider-label">Direct upload</span>
            </button>
            <button type="button" class="provider-tab" data-provider="CLOUDFLARE">
                <span class="provider-icon" style="background:#22D3EE22;color:#22D3EE">☁</span>
                <span class="provider-label">Cloudflare</span>
            </button>
        </div>

        <input type="hidden" name="video_url" id="video_url" value="<?= View::e((string) $lesson['video_url']) ?>">

        <!-- YouTube panel -->
        <div class="provider-panel" data-panel="YOUTUBE">
            <div class="field">
                <label>Paste YouTube URL or video ID</label>
                <input type="text" class="provider-input" data-target="YOUTUBE"
                       placeholder="https://www.youtube.com/watch?v=dQw4w9WgXcQ or just dQw4w9WgXcQ">
                <small class="text-muted">Use unlisted videos for free unlimited bandwidth. Auto-extracts title &amp; thumbnail.</small>
            </div>
        </div>

        <!-- Vimeo panel -->
        <div class="provider-panel" data-panel="VIMEO">
            <div class="field">
                <label>Paste Vimeo URL or numeric ID</label>
                <input type="text" class="provider-input" data-target="VIMEO"
                       placeholder="https://vimeo.com/76979871 or 76979871">
            </div>
        </div>

        <!-- HLS panel -->
        <div class="provider-panel" data-panel="HLS">
            <div class="field">
                <label>Paste HLS .m3u8 URL</label>
                <input type="text" class="provider-input" data-target="HLS"
                       placeholder="https://cdn.example.com/path/master.m3u8">
                <small class="text-muted">For self-hosted streams via R2 / S3 / CloudFront.</small>
            </div>
        </div>

        <!-- MP4 panel -->
        <div class="provider-panel" data-panel="MP4">
            <div class="field">
                <label>Paste MP4 / WebM URL</label>
                <input type="text" class="provider-input" data-target="MP4"
                       placeholder="https://cdn.example.com/lesson-1.mp4">
            </div>
        </div>

        <!-- Direct upload panel -->
        <div class="provider-panel" data-panel="UPLOAD">
            <?php if ($mode === 'create'): ?>
                <div class="alert alert-warning" style="margin-bottom:8px">
                    Save this lesson first (any provider), then come back to upload a file directly.
                </div>
            <?php else: ?>
                <div class="upload-box" id="upload-box">
                    <input type="file" id="video-file" accept="video/mp4,video/webm,video/quicktime,video/x-m4v" style="display:none">
                    <div class="upload-label">
                        <div style="font-size:38px;color:var(--primary);margin-bottom:8px">⇪</div>
                        <strong>Click to choose a video</strong>
                        <p class="text-muted" style="margin:4px 0 0;font-size:12px">MP4, WebM or MOV · max 256 MB · uploads to <code>/uploads/videos/</code></p>
                    </div>
                    <div class="upload-progress" id="upload-progress" style="display:none">
                        <div class="upload-progress-bar"><div id="upload-bar-fill"></div></div>
                        <div id="upload-status" style="margin-top:8px;font-size:13px"></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Cloudflare panel -->
        <div class="provider-panel" data-panel="CLOUDFLARE">
            <div class="field">
                <label>Paste Cloudflare Stream URL or video ID</label>
                <input type="text" class="provider-input" data-target="CLOUDFLARE"
                       placeholder="https://customer-xxx.cloudflarestream.com/abc123…/manifest/video.m3u8">
                <small class="text-muted">For paid Cloudflare Stream accounts. Configure keys in <a href="/admin/settings?group=video">Settings → Video</a>.</small>
            </div>
        </div>

        <!-- Live preview (shared across providers) -->
        <div class="video-preview-box" id="video-preview-box" style="<?= empty($lesson['thumbnail_url']) && empty($lesson['video_url']) ? 'display:none' : '' ?>">
            <div class="video-thumb">
                <img id="video-thumb-img" src="<?= View::e((string) ($lesson['thumbnail_url'] ?? '')) ?>" alt="" onerror="this.style.display='none'">
                <span class="video-play-icon">▶</span>
            </div>
            <div class="video-meta">
                <strong id="video-title-line"><?= View::e((string) ($lesson['title'] ?? 'Video set')) ?></strong>
                <div class="text-muted" style="font-size:12px;margin-top:4px" id="video-author-line"></div>
                <div style="margin-top:8px;display:flex;gap:8px;align-items:center">
                    <span class="badge badge-muted" id="provider-badge"><?= View::e((string) ($lesson['video_provider'] ?? 'OTHER')) ?></span>
                    <code id="video-url-display" style="font-size:11px;max-width:340px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:inline-block;vertical-align:middle"><?= View::e((string) $lesson['video_url']) ?></code>
                </div>
            </div>
        </div>

        <div class="field-row" style="margin-top:14px">
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
            <small class="text-muted">Each line = seconds offset, then chapter title. Stored as JSON.</small>
        </div>

        <div class="field-row">
            <div class="field"><label><input type="checkbox" name="is_downloadable"   value="1" <?= ((int) ($lesson['is_downloadable']   ?? 0)) ? 'checked' : '' ?>> Allow download</label></div>
            <div class="field"><label><input type="checkbox" name="allow_speed"       value="1" <?= ((int) ($lesson['allow_speed']       ?? 1)) ? 'checked' : '' ?>> Show speed control</label></div>
            <div class="field"><label><input type="checkbox" name="watermark_enabled" value="1" <?= ((int) ($lesson['watermark_enabled'] ?? 0)) ? 'checked' : '' ?>> Watermark (user email overlay)</label></div>
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
            <button type="button" class="btn btn-danger btn-sm"
                    data-confirm="Delete this lesson?"
                    onclick="document.getElementById('delete-form').submit()">Delete</button>
        <?php endif; ?>
    </div>
</form>

<?php if ($mode === 'edit'): ?>
    <form id="delete-form" method="post" action="/admin/lessons/<?= View::e((string) $lesson['id']) ?>/delete" style="display:none"></form>

    <!-- ============ FAQs ============ -->
    <div class="card" id="faqs">
        <div class="card-header flex-row">
            <div>
                <h3 style="margin-bottom:2px">FAQs</h3>
                <p class="text-muted" style="margin:0;font-size:12px"><?= count($faqs) ?> question<?= count($faqs) === 1 ? '' : 's' ?> · shown to learners under the video</p>
            </div>
        </div>

        <?php if (!empty($faqs)): ?>
            <div class="faq-list">
                <?php foreach ($faqs as $i => $f): ?>
                    <details class="faq-item">
                        <summary>
                            <span class="faq-q"><?= View::e($f['question']) ?></span>
                            <?php if (!(int) $f['is_published']): ?>
                                <span class="badge badge-warning" style="margin-left:8px;font-size:10px">Hidden</span>
                            <?php endif; ?>
                        </summary>
                        <form method="post" action="/admin/lessons/<?= View::e((string) $lesson['id']) ?>/faqs/<?= (int) $f['id'] ?>" class="faq-edit-form">
                            <div class="field"><label>Question</label><input name="question" type="text" value="<?= View::e($f['question']) ?>" required></div>
                            <div class="field"><label>Answer</label><textarea name="answer" rows="3" required><?= View::e($f['answer']) ?></textarea></div>
                            <div class="field"><label><input type="checkbox" name="is_published" value="1" <?= (int) $f['is_published'] ? 'checked' : '' ?>> Published</label></div>
                            <div class="flex-row">
                                <button type="submit" class="btn btn-primary btn-sm">Save</button>
                                <button type="submit" formaction="/admin/lessons/<?= View::e((string) $lesson['id']) ?>/faqs/<?= (int) $f['id'] ?>/reorder?dir=up"   formmethod="post" class="btn btn-ghost btn-sm">↑</button>
                                <button type="submit" formaction="/admin/lessons/<?= View::e((string) $lesson['id']) ?>/faqs/<?= (int) $f['id'] ?>/reorder?dir=down" formmethod="post" class="btn btn-ghost btn-sm">↓</button>
                                <div class="spacer"></div>
                                <button type="submit" formaction="/admin/lessons/<?= View::e((string) $lesson['id']) ?>/faqs/<?= (int) $f['id'] ?>/delete" formmethod="post" class="btn btn-danger btn-sm" data-confirm="Delete this FAQ?">Delete</button>
                            </div>
                        </form>
                    </details>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-muted">No FAQs yet. Add one below.</p>
        <?php endif; ?>

        <form method="post" action="/admin/lessons/<?= View::e((string) $lesson['id']) ?>/faqs" class="faq-add-form" style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border)">
            <h4 style="margin:0 0 10px;font-size:13px">+ Add FAQ</h4>
            <div class="field"><label>Question</label><input name="question" type="text" placeholder="e.g. Will I get a certificate?" required></div>
            <div class="field"><label>Answer</label><textarea name="answer" rows="2" required></textarea></div>
            <button type="submit" class="btn btn-primary btn-sm">Add FAQ</button>
        </form>
    </div>
<?php endif; ?>

<script>
// ===== Provider tab switcher + URL writer =====
(function () {
    var tabs   = document.querySelectorAll('.provider-tab');
    var panels = document.querySelectorAll('.provider-panel');
    var hidden = document.getElementById('video_url');
    var urlDisp= document.getElementById('video-url-display');
    var badge  = document.getElementById('provider-badge');
    var thumb  = document.getElementById('video-thumb-img');
    var titleLn= document.getElementById('video-title-line');
    var authLn = document.getElementById('video-author-line');
    var box    = document.getElementById('video-preview-box');
    var thumbInp = document.getElementById('thumbnail_url');

    function setActive(prov) {
        tabs.forEach(function (t) { t.classList.toggle('active', t.dataset.provider === prov); });
        panels.forEach(function (p) { p.classList.toggle('active', p.dataset.panel === prov); });
    }

    function detect(url) {
        if (!url) return;
        fetch('/admin/api/video-detect?url=' + encodeURIComponent(url), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                badge.textContent = d.provider || 'OTHER';
                badge.className = 'badge ' + (
                    d.provider === 'YOUTUBE'    ? 'badge-danger' :
                    d.provider === 'CLOUDFLARE' ? 'badge-info' :
                    d.provider === 'VIMEO'      ? 'badge-success' : 'badge-muted'
                );
                if (d.thumbnail_url) {
                    thumb.src = d.thumbnail_url;
                    thumb.style.display = 'block';
                    if (thumbInp && !thumbInp.value) thumbInp.placeholder = d.thumbnail_url;
                }
                if (d.title)       titleLn.textContent = d.title;
                if (d.author_name) authLn.textContent = 'by ' + d.author_name;
                if (urlDisp) urlDisp.textContent = url;
                box.style.display = 'flex';
            }).catch(function () {});
    }

    tabs.forEach(function (t) {
        t.addEventListener('click', function () { setActive(t.dataset.provider); });
    });

    document.querySelectorAll('.provider-input').forEach(function (inp) {
        inp.addEventListener('input', debounce(function () {
            var v = inp.value.trim();
            if (!v) return;
            // For YouTube/Vimeo, accept bare IDs by reconstructing the URL.
            var prov = inp.dataset.target;
            var resolved = v;
            if (prov === 'YOUTUBE' && /^[A-Za-z0-9_-]{6,15}$/.test(v) && v.indexOf('http') !== 0) {
                resolved = 'https://www.youtube.com/watch?v=' + v;
            } else if (prov === 'VIMEO' && /^\d+$/.test(v)) {
                resolved = 'https://vimeo.com/' + v;
            }
            hidden.value = resolved;
            detect(resolved);
        }, 500));
    });

    function debounce(fn, ms) {
        var t = null;
        return function () {
            clearTimeout(t);
            t = setTimeout(fn, ms);
        };
    }

    setActive(<?= json_encode($activeTab) ?>);
    // Pre-fill the active panel's input from the saved video_url so editing works.
    var initial = hidden.value;
    if (initial) {
        var inp = document.querySelector('.provider-input[data-target="' + <?= json_encode($activeTab) ?> + '"]');
        if (inp) inp.value = initial;
        detect(initial);
    }
})();

// ===== Direct upload (only present in edit mode) =====
(function () {
    var fileInput = document.getElementById('video-file');
    var box       = document.getElementById('upload-box');
    var prog      = document.getElementById('upload-progress');
    var bar       = document.getElementById('upload-bar-fill');
    var status    = document.getElementById('upload-status');
    var hidden    = document.getElementById('video_url');
    if (!fileInput || !box) return;

    box.addEventListener('click', function (e) { if (e.target.tagName !== 'INPUT') fileInput.click(); });

    fileInput.addEventListener('change', function () {
        if (!fileInput.files.length) return;
        var f = fileInput.files[0];
        if (f.size > 256 * 1024 * 1024) {
            status.textContent = 'Too large — max 256MB. Use YouTube unlisted for big files.';
            status.style.color = 'var(--danger)';
            prog.style.display = 'block';
            return;
        }

        var fd = new FormData();
        fd.append('file', f);
        fd.append('lesson_id', <?= json_encode($lesson['id'] ?? '') ?>);

        prog.style.display = 'block';
        status.style.color = 'var(--text-muted)';
        status.textContent = 'Uploading ' + f.name + ' (' + Math.round(f.size / 1024 / 1024) + ' MB)…';

        var xhr = new XMLHttpRequest();
        xhr.open('POST', '/admin/api/upload-video');
        xhr.upload.addEventListener('progress', function (e) {
            if (!e.lengthComputable) return;
            var pct = Math.round((e.loaded / e.total) * 100);
            bar.style.width = pct + '%';
            status.textContent = 'Uploading… ' + pct + '%';
        });
        xhr.addEventListener('load', function () {
            try {
                var resp = JSON.parse(xhr.responseText);
                if (resp.url) {
                    hidden.value = resp.url;
                    var mp4Inp = document.querySelector('.provider-input[data-target="MP4"]');
                    if (mp4Inp) { mp4Inp.value = resp.url; }
                    status.style.color = 'var(--success)';
                    status.textContent = '✓ Uploaded · ' + resp.url + ' — switching to MP4 tab.';
                    document.querySelector('.provider-tab[data-provider="MP4"]').click();
                    var inp = document.querySelector('.provider-input[data-target="MP4"]');
                    if (inp) { inp.dispatchEvent(new Event('input')); }
                } else {
                    status.style.color = 'var(--danger)';
                    status.textContent = 'Upload failed: ' + (resp.error || 'unknown');
                }
            } catch (e) {
                status.style.color = 'var(--danger)';
                status.textContent = 'Server returned invalid response.';
            }
        });
        xhr.send(fd);
    });
})();
</script>
<?php
$content = ob_get_clean();
$title   = $heading;
include __DIR__ . '/../../layouts/admin.php';
