<?php
if (($_GET['key'] ?? '') !== 'devithor2026') {
    http_response_code(403); exit('Forbidden');
}

header('Content-Type: text/plain');
$root = '/home/u169457691/domains/apptesting.in';
echo "=== Devithor Deploy ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n";
echo "Root: $root\n\n";

// Try every exec method Hostinger may allow
$cmd    = "cd $root && git pull origin main 2>&1";
$output = '';

if (function_exists('exec')) {
    exec($cmd, $lines, $code);
    $output = implode("\n", $lines);
    echo "Method: exec (exit $code)\n\n";
} elseif (function_exists('shell_exec')) {
    $output = (string) shell_exec($cmd);
    echo "Method: shell_exec\n\n";
} elseif (function_exists('passthru')) {
    echo "Method: passthru\n\n";
    passthru($cmd);
    echo "\n=== Done ===\n";
    exit;
} elseif (function_exists('system')) {
    echo "Method: system\n\n";
    system($cmd);
    echo "\n=== Done ===\n";
    exit;
} else {
    echo "ERROR: All exec functions are disabled on this server.\n";
    echo "Use Hostinger hPanel → Git to pull manually.\n\n";
    echo "Disabled: " . ini_get('disable_functions') . "\n";
    exit;
}

echo $output ?: "(no output)";
echo "\n\n=== Done ===\n";
