<?php
/**
 * One-time routing fix — upload to public_html/ and visit:
 *   https://apptesting.in/fix.php?key=devithor2026
 *
 * Fixes: routes/exams.php, index.php, .htaccess, and deploy.php $root.
 * Delete this file after running.
 */
if (($_GET['key'] ?? '') !== 'devithor2026') { http_response_code(403); exit('Forbidden'); }
header('Content-Type: text/plain');

// __DIR__ is public_html/ when this file lives there
$app = __DIR__ . '/devithor-backend';   // public_html/devithor-backend
$pub = $app    . '/public';             // public_html/devithor-backend/public
$rts = $app    . '/routes';             // public_html/devithor-backend/routes

echo "=== Devithor Fix ===\n";
echo "app : $app\n";
echo "pub : $pub\n";
echo "rts : $rts\n\n";

if (!is_dir($app)) {
    echo "ERROR: devithor-backend/ not found at expected path.\n";
    echo "Check that this file is in public_html/ (same folder as index.php).\n";
    exit;
}

$ok = 0; $fail = 0;

function wx(string $path, string $content, string $label): void {
    global $ok, $fail;
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        echo "FAIL: mkdir $dir ($label)\n"; $fail++; return;
    }
    if (file_put_contents($path, $content) !== false) {
        if (function_exists('opcache_invalidate')) opcache_invalidate($path, true);
        echo "OK  : $label\n"; $ok++;
    } else {
        echo "FAIL: write $path ($label)\n"; $fail++;
    }
}

// ── 1. routes/exams.php ────────────────────────────────────────────────────────
$examRoutes = <<<'PHP'
<?php
use Devithor\Auth;
use Devithor\Controllers\Admin\ExamController as AdminExam;
use Devithor\Controllers\Api\ExamApiController as ApiExam;

$m = [Auth::requireAdmin()];
$router->get('/admin/exams',                        [AdminExam::class, 'index'],          $m);
$router->get('/admin/exams/new',                    [AdminExam::class, 'showCreate'],     $m);
$router->post('/admin/exams',                       [AdminExam::class, 'create'],         $m);
$router->get('/admin/exams/{id}/questions',         [AdminExam::class, 'questions'],      $m);
$router->post('/admin/exams/{id}/questions',        [AdminExam::class, 'questionCreate'], $m);
$router->post('/admin/exams/questions/{id}/delete', [AdminExam::class, 'questionDelete'], $m);
$router->get('/admin/exams/{id}/results',           [AdminExam::class, 'results'],        $m);
$router->post('/admin/exams/{id}/publish',          [AdminExam::class, 'publish'],        $m);
$router->get('/admin/exams/{id}',                   [AdminExam::class, 'showEdit'],       $m);
$router->post('/admin/exams/{id}',                  [AdminExam::class, 'update'],         $m);
$router->post('/admin/exams/{id}/delete',           [AdminExam::class, 'delete'],         $m);

$a = [Auth::requireUser()];
$router->get('/api/v1/exams',                       [ApiExam::class, 'list'],       $a);
$router->get('/api/v1/exams/{id}',                  [ApiExam::class, 'show'],       $a);
$router->post('/api/v1/exams/{id}/start',           [ApiExam::class, 'start'],      $a);
$router->post('/api/v1/exams/attempts/{id}/answer', [ApiExam::class, 'saveAnswer'], $a);
$router->post('/api/v1/exams/attempts/{id}/submit', [ApiExam::class, 'submit'],     $a);
$router->get('/api/v1/exams/attempts/{id}/result',  [ApiExam::class, 'result'],     $a);
PHP;
wx("$rts/exams.php", $examRoutes, 'routes/exams.php');

// ── 2. index.php — inject require for exams.php ────────────────────────────────
$idxPath = "$pub/index.php";
$idx     = file_get_contents($idxPath);
if ($idx === false) {
    echo "FAIL: cannot read $idxPath\n"; $fail++;
} elseif (str_contains($idx, 'routes/exams.php')) {
    echo "SKIP: index.php already requires routes/exams.php\n";
} else {
    $new = str_replace(
        "require __DIR__ . '/../routes/admin.php';",
        "require __DIR__ . '/../routes/admin.php';\n    require __DIR__ . '/../routes/exams.php';",
        $idx
    );
    if ($new === $idx) {
        // fallback: insert before dispatch if admin.php line not found
        $new = str_replace(
            '$router->dispatch(Request::fromGlobals());',
            "require __DIR__ . '/../routes/exams.php';\n    \$router->dispatch(Request::fromGlobals());",
            $idx
        );
    }
    wx($idxPath, $new, 'index.php (routes/exams.php added)');
}

// ── 3. .htaccess — clean version, exam routes via index.php ───────────────────
$ht = <<<'HT'
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^ index.php [QSA,L]
</IfModule>
Options -Indexes
<FilesMatch "^\.">
    Require all denied
</FilesMatch>
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set Permissions-Policy "geolocation=(), camera=(), microphone=()"
</IfModule>
<FilesMatch "\.(css|js|png|jpg|jpeg|svg|webp|woff2)$">
    <IfModule mod_headers.c>
        Header set Cache-Control "public, max-age=2592000, immutable"
    </IfModule>
</FilesMatch>
HT;
wx("$pub/.htaccess", $ht, '.htaccess (clean — exam routes via index.php)');

// ── 4. Fix deploy.php $root so future deploys go to correct directory ──────────
$deployPath = __DIR__ . '/deploy.php';
$deploy     = @file_get_contents($deployPath);
if ($deploy !== false) {
    // Replace every $root = '...' line pointing to old wrong path
    $fixed = preg_replace(
        "/(\\\$root\s*=\s*)'\/home\/[^']*apptesting\.in';/",
        "\$1'$app';",
        $deploy
    );
    if ($fixed !== $deploy) {
        wx($deployPath, $fixed, "deploy.php (\$root → $app)");
    } else {
        echo "SKIP: deploy.php \$root already correct or pattern not matched\n";
    }
} else {
    echo "SKIP: deploy.php not found at $deployPath\n";
}

// ── 5. OPcache flush ──────────────────────────────────────────────────────────
if (function_exists('opcache_reset')) { opcache_reset(); echo "OK  : opcache_reset()\n"; }

echo "\n=== $ok OK, $fail FAILED ===\n";
if ($fail === 0) {
    echo "All done. Visit /admin/exams — it should work now.\n";
    echo "Delete public_html/fix.php after verifying.\n";
} else {
    echo "Some steps failed — check write permissions on $app\n";
}
