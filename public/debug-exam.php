<?php
if (($_GET['key'] ?? '') !== 'devithor2026') { http_response_code(403); exit('Forbidden'); }
header('Content-Type: text/plain');

$root = '/home/u169457691/domains/apptesting.in';

echo "=== FILE CHECK ===\n";
$files = [
    'public/index.php'       => "$root/public/index.php",
    'public/exam-admin.php'  => "$root/public/exam-admin.php",
    'public/.htaccess'       => "$root/public/.htaccess",
    'routes/exams.php'       => "$root/routes/exams.php",
    'routes/admin.php'       => "$root/routes/admin.php",
];
foreach ($files as $label => $path) {
    if (file_exists($path)) {
        echo "EXISTS  ($label) — " . filesize($path) . " bytes, mtime=" . date('Y-m-d H:i:s', filemtime($path)) . "\n";
    } else {
        echo "MISSING ($label)\n";
    }
}

echo "\n=== INDEX.PHP CONTAINS routes/exams? ===\n";
$idx = file_get_contents("$root/public/index.php");
echo str_contains($idx, 'routes/exams.php') ? "YES — require routes/exams.php is present\n" : "NO — require routes/exams.php is MISSING\n";

echo "\n=== .HTACCESS CONTAINS exam-admin? ===\n";
$ht = file_get_contents("$root/public/.htaccess");
echo str_contains($ht, 'exam-admin') ? "YES — exam route rule is present\n" : "NO — exam route rule MISSING\n";

echo "\n=== HTACCESS CONTENT ===\n";
echo $ht . "\n";

echo "\n=== OPCACHE STATUS ===\n";
if (function_exists('opcache_get_status')) {
    $s = opcache_get_status(false);
    echo "enabled=" . ($s['opcache_enabled'] ? 'yes' : 'no') . "\n";
    echo "validate_timestamps=" . (ini_get('opcache.validate_timestamps') ?: 'not set') . "\n";
    echo "revalidate_freq=" . ini_get('opcache.revalidate_freq') . "\n";
    echo "restrict_api=" . (ini_get('opcache.restrict_api') ?: 'none') . "\n";
    $idxReal = realpath("$root/public/index.php");
    echo "index.php realpath=$idxReal\n";
    echo "index.php in cache=" . (isset($s['scripts'][$idxReal]) ? 'YES' : 'NO') . "\n";
} else {
    echo "opcache_get_status not available\n";
}

echo "\n=== ROUTES/EXAMS.PHP CONTENT ===\n";
if (file_exists("$root/routes/exams.php")) {
    echo file_get_contents("$root/routes/exams.php") . "\n";
} else {
    echo "FILE DOES NOT EXIST\n";
}
