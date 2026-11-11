<?php
/**
 * src/Db.php — PDO SQLite wrapper (singleton).
 */
declare(strict_types=1);

namespace App;

use PDO;
use PDOException;
use PDOStatement;

class Db
{
    private static ?PDO $pdo = null;

    private function __construct() {}
    private function __clone() {}
    public function __wakeup() {}

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $dsn      = env('DB_DSN', '');
            $username = env('DB_USERNAME', '');
            $password = env('DB_PASSWORD', '');
            $options  = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            // SQLite default jika DSN tidak di-set
            if ($dsn === '') {
                $dbPath = env('DB_DATABASE', APP_ROOT . '/database/bookings.sqlite');
                $dir = dirname($dbPath);
                if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
                    throw new RuntimeException("Gagal membuat folder database: {$dir}");
                }
                $dsn = 'sqlite:' . $dbPath;
            }

            self::$pdo = new PDO($dsn, $username, $password, $options);
        }
        return self::$pdo;
    }

    /**
     * Ambil semua baris.
     */
    public static function query(string $sql, array $params = []): array
    {
        $stmt = self::prepare($sql, $params);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Ambil satu baris.
     */
    public static function find(string $sql, array $params = []): ?array
    {
        $stmt = self::prepare($sql, $params);
        $stmt->execute();
        return $stmt->fetch() ?: null;
    }

    /**
     * Eksekusi (INSERT/UPDATE/DELETE), kembalikan lastInsertId.
     */
    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::prepare($sql, $params);
        $stmt->execute();
        return (int)self::pdo()->lastInsertId();
    }

    /**
     * Eksekusi dan kembalikan jumlah baris yang terpengaruh.
     */
    public static function affect(string $sql, array $params = []): int
    {
        $stmt = self::prepare($sql, $params);
        $stmt->execute();
        return (int)$stmt->rowCount();
    }

    /**
     * Hitung hasil query.
     */
    public static function count(string $sql, array $params = []): int
    {
        $stmt = self::prepare($sql, $params);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    private static function prepare(string $sql, array $params): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        foreach ($params as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $bindKey = is_int($key) ? $key + 1 : (str_starts_with($key, ':') ? $key : ':' . $key);
            $stmt->bindValue($bindKey, $value, $type);
        }
        return $stmt;
    }
}
