<?php
if (($_GET['key'] ?? '') !== 'devithor2026') {
    http_response_code(403); exit('Forbidden');
}

$host = getenv('DB_HOST')     ?: 'localhost';
$db   = getenv('DB_DATABASE') ?: '';
$user = getenv('DB_USERNAME') ?: '';
$pass = getenv('DB_PASSWORD') ?: '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Exception $e) {
    die('DB Error: ' . $e->getMessage());
}

$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
$pdo->exec('DELETE FROM courses');
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

$remaining = $pdo->query('SELECT COUNT(*) FROM courses')->fetchColumn();

header('Content-Type: text/plain');
echo "=== Wipe Complete ===\n";
echo "All dummy courses deleted.\n";
echo "Courses remaining: $remaining\n";
echo "App will show empty catalog now.\n";
