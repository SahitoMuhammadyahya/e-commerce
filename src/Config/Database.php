<?php

namespace EssenceStore\Config;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        self::loadEnv();

        $connection = getenv('DB_CONNECTION') ?: ($_ENV['DB_CONNECTION'] ?? 'mysql');

        try {
            if ($connection === 'sqlite') {
                $dbPath = getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? ':memory:');
                if ($dbPath !== ':memory:' && !str_starts_with($dbPath, '/') && !preg_match('/^[A-Za-z]:/', $dbPath)) {
                    $dbPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $dbPath;
                }
                $pdo = new PDO("sqlite:" . $dbPath);
                $pdo->exec("PRAGMA foreign_keys = ON;");
            } else {
                $host = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? '127.0.0.1');
                $port = getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? '3306');
                $database = getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? 'essence_store');
                $username = getenv('DB_USERNAME') ?: ($_ENV['DB_USERNAME'] ?? 'root');
                $password = getenv('DB_PASSWORD') ?: ($_ENV['DB_PASSWORD'] ?? '');

                $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
                $pdo = new PDO($dsn, $username, $password);
            }

            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

            self::$instance = $pdo;
            return self::$instance;
        } catch (PDOException $e) {
            throw new PDOException("Database connection failed: " . $e->getMessage(), (int)$e->getCode());
        }
    }

    public static function setConnection(?PDO $pdo): void
    {
        self::$instance = $pdo;
    }

    public static function loadEnv(?string $filePath = null): void
    {
        if ($filePath === null) {
            $filePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';
        }

        if (!file_exists($filePath)) {
            return;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (strpos($line, '=') !== false) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                $value = trim($value, '"\'');
                $existingEnv = getenv($key);
                if ($existingEnv !== false && $existingEnv !== '') {
                    $_ENV[$key] = $existingEnv;
                } else {
                    $_ENV[$key] = $value;
                    putenv("{$key}={$value}");
                }
            }
        }
    }
}
