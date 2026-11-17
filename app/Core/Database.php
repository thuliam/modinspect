<?php
declare(strict_types=1);
namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(array $config): PDO
    {
        if (self::$pdo) return self::$pdo;
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['host'], $config['port'], $config['database']);
        try {
            self::$pdo = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            // PHP runs in Asia/Bangkok (bootstrap.php). Pin the DB session to the same offset so NOW(), TIMESTAMP columns and
            // PHP-generated times agree even when the database server itself runs in UTC (CI, many hosts).
            self::$pdo->exec("SET time_zone = '" . date('P') . "'");
        } catch (PDOException $e) {
            // Do NOT chain $e: PDO's trace carries the constructor arguments (DSN, user name, password) and PHP prints it on an uncaught exception.
            throw new RuntimeException('ไม่สามารถเชื่อมต่อฐานข้อมูลได้ กรุณาตรวจสอบไฟล์ .env (SQLSTATE ' . preg_replace('/[^A-Za-z0-9]/', '', (string)$e->getCode()) . ')');
        }
        return self::$pdo;
    }
}

