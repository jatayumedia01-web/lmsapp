<?php
/** @var string $title */
/** @var string $page  current sidebar key — 'dashboard' | 'courses' | ... */
/** @var array $me     admin user row */
/** @var string $content rendered page body */
use Devithor\View;
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= View::e($title ?? 'Devithor Admin') ?> · Devithor</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="brand">
            <h1>Devithor</h1>
            <small class="text-muted">LMS admin</small>
        </div>
        <nav>
            <a href="/admin/dashboard" class="<?= $page === 'dashboard' ? 'active' : '' ?>">Dashboard</a>

            <div class="nav-group">Catalog</div>
            <a href="/admin/courses"   class="<?= in_array($page, ['courses', 'lessons'], true) ? 'active' : '' ?>">Courses</a>

            <div class="nav-group">People</div>
            <a href="/admin/users"     class="<?= $page === 'users' ? 'active' : '' ?>">Users</a>

            <div class="nav-group">Revenue</div>
            <a href="/admin/billing"   class="<?= $page === 'billing' ? 'active' : '' ?>">Billing</a>

            <div class="nav-group">Community</div>
            <a href="/admin/qa"        class="<?= $page === 'qa' ? 'active' : '' ?>">Q&amp;A moderation</a>

            <div class="nav-group">System</div>
            <a href="/admin/settings"  class="<?= $page === 'settings' ? 'active' : '' ?>">Settings</a>
        </nav>
        <div style="padding:16px 20px; margin-top:auto; border-top:1px solid var(--border)">
            <div class="text-muted" style="font-size:12px">Signed in as</div>
            <div style="font-weight:600"><?= View::e($me['full_name']) ?></div>
            <form method="post" action="/admin/logout" class="mt-2">
                <button class="btn btn-ghost btn-sm" type="submit">Sign out</button>
            </form>
        </div>
    </aside>
    <main class="main">
        <?= $content ?>
    </main>
</div>
<script src="/assets/js/admin.js"></script>
</body>
</html>
