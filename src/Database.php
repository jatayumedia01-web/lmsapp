<?php
declare(strict_types=1);

namespace Devithor;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Singleton PDO wrapper. Connection is lazy — only opened on first use, then
 * reused across the request. All queries use prepared statements (helpers
 * below) so SQL injection is impossible by construction.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $host    = getenv('DB_HOST')     ?: 'localhost';
        $port    = getenv('DB_PORT')     ?: '3306';
        $name    = getenv('DB_DATABASE') ?: '';
        $user    = getenv('DB_USERNAME') ?: '';
        $pass    = getenv('DB_PASSWORD') ?: '';
        $charset = getenv('DB_CHARSET')  ?: 'utf8mb4';

        if ($name === '' || $user === '') {
            throw new RuntimeException('Database credentials missing — populate .env first.');
        }

        $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=$charset";
        try {
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES $charset",
            ]);
        } catch (PDOException $e) {
            // Don't leak credentials in the error message.
            throw new RuntimeException('Database connection failed.', 0, $e);
        }
        return self::$pdo;
    }

    /** SELECT and return all rows as associative arrays. */
    public static function all(string $sql, array $params = []): array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** SELECT and return the first row, or null. */
    public static function one(string $sql, array $params = []): ?array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** SELECT a single scalar value, or null. */
    public static function scalar(string $sql, array $params = [])
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        $val = $stmt->fetchColumn();
        return $val === false ? null : $val;
    }

    /** INSERT / UPDATE / DELETE. Returns affected row count. */
    public static function exec(string $sql, array $params = []): int
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /** INSERT and return the last inserted id (string for varchar PKs, int otherwise). */
    public static function insertGetId(string $sql, array $params = []): string
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return self::pdo()->lastInsertId();
    }
}
