<?php
// ONE-TIME migration runner via HTTP — DELETE THIS FILE after use!
// Access: https://apptesting.in/run-migrations.php?key=devithor2026

if (($_GET['key'] ?? '') !== 'devithor2026') {
    http_response_code(403);
    die('Forbidden');
}

define('IN_CLI', false);
require __DIR__ . '/../src/bootstrap.php';

use Devithor\Database;

header('Content-Type: text/plain');

// Ensure migrations table
Database::exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        filename VARCHAR(255) PRIMARY KEY,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

$applied = array_column(Database::all('SELECT filename FROM schema_migrations'), 'filename');
$applied = array_flip($applied);

$files = glob(__DIR__ . '/../migrations/*.sql') ?: [];
sort($files);

$pending = array_filter($files, fn($f) => !isset($applied[basename($f)]));

if (empty($pending)) {
    echo "Nothing to migrate. Schema up to date.\n";
    exit;
}

foreach ($pending as $file) {
    $name = basename($file);
    echo "Applying $name ... ";
    $sql = file_get_contents($file);
    if (!$sql || trim($sql) === '') { echo "skipped (empty)\n"; continue; }
    try {
        foreach (preg_split('/;\s*$/m', $sql) as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '') continue;
            Database::pdo()->exec($stmt);
        }
        Database::exec('INSERT INTO schema_migrations (filename) VALUES (?)', [$name]);
        echo "OK\n";
    } catch (Throwable $e) {
        echo "FAILED: " . $e->getMessage() . "\n";
    }
}

echo "\nDone.\n";
echo "\n*** DELETE THIS FILE NOW: public/run-migrations.php ***\n";
