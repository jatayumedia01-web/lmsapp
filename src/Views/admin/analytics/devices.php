<?php
/** @var array $platforms */
/** @var array $os */
/** @var array $appVersions */
/** @var array $models */
/** @var array $me */
/** @var string $page */
use Devithor\View;

ob_start();
?>
<header>
    <div>
        <h2>Devices</h2>
        <p>Platforms, OS versions, app versions, and physical models — what your users are running on.</p>
    </div>
    <div class="spacer"></div>
    <a href="/admin/analytics" class="btn btn-ghost btn-sm">← Overview</a>
</header>

<div class="grid-2">
    <div class="card">
        <h3>Platforms</h3>
        <?php if (empty($platforms)): ?>
            <p class="text-muted">No devices registered yet.</p>
        <?php else: ?>
            <table class="table" style="margin-bottom:0">
                <thead><tr><th>Platform</th><th class="text-right">Devices</th><th class="text-right">Users</th></tr></thead>
                <tbody>
                <?php foreach ($platforms as $p): ?>
                    <tr>
                        <td>
                            <span class="badge badge-<?= $p['platform'] === 'ANDROID' ? 'success' : ($p['platform'] === 'IOS' ? 'info' : 'muted') ?>">
                                <?= View::e($p['platform']) ?>
                            </span>
                        </td>
                        <td class="text-right"><?= number_format((int) $p['devices_count']) ?></td>
                        <td class="text-right"><?= number_format((int) $p['users_count']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>App versions</h3>
        <?php if (empty($appVersions)): ?>
            <p class="text-muted">No app versions captured yet.</p>
        <?php else: ?>
            <table class="table" style="margin-bottom:0">
                <thead><tr><th>Version</th><th class="text-right">Devices</th></tr></thead>
                <tbody>
                <?php foreach ($appVersions as $v): ?>
                    <tr>
                        <td><code><?= View::e($v['app_version']) ?></code></td>
                        <td class="text-right"><?= number_format((int) $v['c']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <h3>OS versions</h3>
        <?php if (empty($os)): ?>
            <p class="text-muted">—</p>
        <?php else: ?>
            <table class="table" style="margin-bottom:0">
                <thead><tr><th>OS</th><th>Version</th><th class="text-right">Devices</th></tr></thead>
                <tbody>
                <?php foreach ($os as $row): ?>
                    <tr>
                        <td><?= View::e($row['os_name']) ?></td>
                        <td class="text-muted"><?= View::e($row['os_version']) ?></td>
                        <td class="text-right"><?= number_format((int) $row['c']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>Top models</h3>
        <?php if (empty($models)): ?>
            <p class="text-muted">—</p>
        <?php else: ?>
            <table class="table" style="margin-bottom:0">
                <thead><tr><th>Make</th><th>Model</th><th class="text-right">Devices</th></tr></thead>
                <tbody>
                <?php foreach ($models as $m): ?>
                    <tr>
                        <td class="text-muted"><?= View::e($m['manufacturer']) ?></td>
                        <td><?= View::e($m['model']) ?></td>
                        <td class="text-right"><?= number_format((int) $m['c']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();
$title   = 'Devices';
include __DIR__ . '/../../layouts/admin.php';
