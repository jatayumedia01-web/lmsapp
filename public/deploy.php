<?php
// One-time setup: upload this file to public/ via File Manager, then hit the URL.
// After that, every git push → visit URL → server updates instantly.
// URL: https://apptesting.in/deploy.php?key=devithor2026

if (($_GET['key'] ?? '') !== 'devithor2026') {
    http_response_code(403); exit('Forbidden');
}

header('Content-Type: text/plain');
echo "=== Devithor Deploy ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

// Find the actual project root (one level up from public/)
$root = dirname(__DIR__);
echo "Root: $root\n\n";

$output = shell_exec("cd $root && git pull origin main 2>&1");
echo $output ?? "No output from git pull.\n";

echo "\n=== Done ===\n";
