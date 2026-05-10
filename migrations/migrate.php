<?php
/**
 * Migration runner. Idempotent: every applied migration is recorded in the
 * `schema_migrations` table so re-running this only executes new files.
 *
 * Usage:
 *   php migrations/migrate.php          # apply pending migrations
 *   php migrations/migrate.php --status  # show what's applied vs pending
 *
 * SAFE TO RUN ON PRODUCTION: never drops or alters existing data; new files
 * are applied in lexicographic order (001 → 002 → ...).
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use Devithor\Database;

// Ensure the bookkeeping table exists.
Database::exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        filename VARCHAR(255) PRIMARY KEY,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

$applied = array_column(Database::all('SELECT filename FROM schema_migrations'), 'filename');
$applied = array_flip($applied);

$files = glob(__DIR__ . '/*.sql') ?: [];
sort($files);

$showStatus = in_array('--status', $argv ?? [], true);

if ($showStatus) {
    echo "Migration status:\n";
    foreach ($files as $file) {
        $name = basename($file);
        $marker = isset($applied[$name]) ? '[applied]' : '[pending]';
        echo "  $marker $name\n";
    }
    exit(0);
}

$pending = array_filter($files, fn ($f) => !isset($applied[basename($f)]));

if (empty($pending)) {
    echo "Nothing to migrate. Schema up to date.\n";
    exit(0);
}

foreach ($pending as $file) {
    $name = basename($file);
    echo "Applying $name ... ";
    $sql = file_get_contents($file);
    if ($sql === false || trim($sql) === '') {
        echo "skipped (empty)\n";
        continue;
    }
    try {
        // Split on semicolons that end statements (keep simple; our migrations
        // don't use stored procedures or triggers that would have inner ;).
        foreach (preg_split('/;\s*$/m', $sql) as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '') continue;
            Database::pdo()->exec($stmt);
        }
        Database::exec(
            'INSERT INTO schema_migrations (filename) VALUES (?)',
            [$name],
        );
        echo "ok\n";
    } catch (Throwable $e) {
        echo "FAILED\n";
        fwrite(STDERR, "  Error: " . $e->getMessage() . "\n");
        exit(1);
    }
}

echo "\nDone.\n";
