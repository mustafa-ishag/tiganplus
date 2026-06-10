<?php

declare(strict_types=1);

namespace EtganERP\Infrastructure\Database;

use PDO;
use PDOException;
use RuntimeException;

/**
 * اتصال قاعدة البيانات
 * Database Connection
 */
final class DatabaseConnection
{
    private static ?PDO $instance = null;
    private static array $config = [];

    private function __construct()
    {
        // منع إنشاء كائن مباشر
    }

    public static function setConfig(array $config): void
    {
        self::$config = $config;
    }

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            self::$instance = self::createConnection();
        }

        return self::$instance;
    }

    private static function createConnection(): PDO
    {
        if (empty(self::$config)) {
            throw new RuntimeException('لم يتم تكوين قاعدة البيانات');
        }

        $config = self::$config['connections'][self::$config['default']];

        $dsn = sprintf(
            '%s:host=%s;port=%s;dbname=%s;charset=%s',
            $config['driver'],
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );

        try {
            $pdo = new PDO(
                $dsn,
                $config['username'],
                $config['password'],
                $config['options']
            );

            // تعيين المنطقة الزمنية
            $pdo->exec("SET time_zone = '+03:00'");

            return $pdo;
        } catch (PDOException $e) {
            throw new RuntimeException('فشل في الاتصال بقاعدة البيانات: ' . $e->getMessage());
        }
    }

    public static function beginTransaction(): void
    {
        self::getInstance()->beginTransaction();
    }

    public static function commit(): void
    {
        self::getInstance()->commit();
    }

    public static function rollback(): void
    {
        self::getInstance()->rollBack();
    }

    public static function inTransaction(): bool
    {
        return self::getInstance()->inTransaction();
    }

    public static function lastInsertId(): string
    {
        return self::getInstance()->lastInsertId();
    }

    public static function prepare(string $sql): \PDOStatement
    {
        return self::getInstance()->prepare($sql);
    }

    public static function execute(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = self::execute($sql, $params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        $stmt = self::execute($sql, $params);
        return $stmt->fetchAll();
    }

    public static function fetchColumn(string $sql, array $params = []): mixed
    {
        $stmt = self::execute($sql, $params);
        return $stmt->fetchColumn();
    }

    public static function count(string $table, string $where = '', array $params = []): int
    {
        $sql = "SELECT COUNT(*) FROM {$table}";
        if (!empty($where)) {
            $sql .= " WHERE {$where}";
        }

        return (int) self::fetchColumn($sql, $params);
    }

    public static function exists(string $table, string $where, array $params = []): bool
    {
        return self::count($table, $where, $params) > 0;
    }

    // منع النسخ والاستنساخ
    private function __clone()
    {
    }

    public function __wakeup(): void
    {
        throw new RuntimeException('لا يمكن إلغاء تسلسل Singleton');
    }
}
