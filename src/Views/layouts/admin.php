<?php
/** @var string $title */
/** @var string $page  current sidebar key — 'dashboard' | 'courses' | ... */
/** @var array $me     admin user row */
/** @var string $content rendered page body */
use Devithor\View;

$navItems = [
    ['main',
        ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => '◈', 'href' => '/admin/dashboard'],
    ],
    ['Catalog',
        ['key' => 'courses',  'label' => 'Courses',  'icon' => '▤', 'href' => '/admin/courses', 'aliases' => ['lessons']],
    ],
    ['People',
        ['key' => 'users',    'label' => 'Users',    'icon' => '◉', 'href' => '/admin/users'],
    ],
    ['Revenue',
        ['key' => 'billing',  'label' => 'Billing',  'icon' => '◊', 'href' => '/admin/billing'],
    ],
    ['Community',
        ['key' => 'qa',       'label' => 'Q&A moderation', 'icon' => '✦', 'href' => '/admin/qa'],
    ],
    ['System',
        ['key' => 'settings', 'label' => 'Settings', 'icon' => '⚙', 'href' => '/admin/settings'],
    ],
];
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= View::e($title ?? 'Devithor Admin') ?> · Devithor</title>
    <?php
    // Cache-bust admin assets by mtime so browsers fetch a fresh copy after
    // each deploy (the public .htaccess marks them `immutable` for perf).
    $cssPath = __DIR__ . '/../../../public/assets/css/admin.css';
    $jsPath  = __DIR__ . '/../../../public/assets/js/admin.js';
    $cssVer  = file_exists($cssPath) ? filemtime($cssPath) : '1';
    $jsVer   = file_exists($jsPath)  ? filemtime($jsPath)  : '1';
    ?>
    <link rel="stylesheet" href="/assets/css/admin.css?v=<?= $cssVer ?>">
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="brand">
            <h1>Devithor</h1>
            <small>LMS admin</small>
        </div>
        <nav>
            <?php foreach ($navItems as $i => $section): ?>
                <?php $groupName = array_shift($section); ?>
                <?php if ($groupName !== 'main'): ?>
                    <div class="nav-group"><?= View::e($groupName) ?></div>
                <?php endif; ?>
                <?php foreach ($section as $item): ?>
                    <?php
                    $isActive = $page === $item['key']
                        || (isset($item['aliases']) && in_array($page, $item['aliases'], true));
                    ?>
                    <a href="<?= View::e($item['href']) ?>" class="<?= $isActive ? 'active' : '' ?>">
                        <span class="nav-icon"><?= View::e($item['icon']) ?></span>
                        <?= View::e($item['label']) ?>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-footer">
            <div class="me-label">Signed in as</div>
            <div class="me-name"><?= View::e($me['full_name']) ?></div>
            <form method="post" action="/admin/logout" class="mb-0">
                <button class="btn btn-ghost btn-sm" type="submit">Sign out →</button>
            </form>
        </div>
    </aside>
    <main class="main">
        <?= $content ?>
    </main>
</div>
<script src="/assets/js/admin.js?v=<?= $jsVer ?>"></script>
</body>
</html>
