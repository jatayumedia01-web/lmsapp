<?php
/** @var ?string $error */
use Devithor\View;
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · Devithor admin</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="login">
    <div class="card">
        <h1>Devithor</h1>
        <p>Sign in to the admin dashboard.</p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= View::e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="/admin/login" autocomplete="off">
            <div class="field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" required autofocus>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" required>
            </div>
            <button class="btn btn-primary" type="submit" style="width:100%; justify-content:center">
                Sign in
            </button>
        </form>
    </div>
</body>
</html>
