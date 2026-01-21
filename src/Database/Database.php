<?php
namespace App\Database;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            // путь к конфигу относительно src/Database.php
            $config = require __DIR__ . '/../../config/database.php';

            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $config['host'],
                $config['port'],
                $config['database'],
                $config['charset']
            );

            try {
                self::$instance = new PDO(
                    $dsn,
                    $config['username'],
                    $config['password'],
                    $config['options']
                );
            } catch (PDOException $e) {
                throw new \RuntimeException('Database connection failed: ' . $e->getMessage());
            }
        }

        return self::$instance;
    }

    public static function migrate(): array
    {
        $results = [];
        // правильный путь к миграциям
        $migrationsPath = dirname(__DIR__) . '/migrations/schema';
        $migrations = glob($migrationsPath . '/*.sql');
        sort($migrations);

        $db = self::getInstance();

        foreach ($migrations as $migration) {
            try {
                $sql = file_get_contents($migration);
                $db->exec($sql);
                $results[] = basename($migration) . ' - SUCCESS';
            } catch (PDOException $e) {
                $results[] = basename($migration) . ' - FAILED: ' . $e->getMessage();
            }
        }

        return $results;
    }

    public static function seed(): array
    {
        $results = [];
        // правильный путь к сидерам
        $seedsPath = dirname(__DIR__) . '/migrations/seeds';
        $seeds = glob($seedsPath . '/*.sql');
        sort($seeds);

        $db = self::getInstance();

        foreach ($seeds as $seed) {
            try {
                $sql = file_get_contents($seed);
                $db->exec($sql);
                $results[] = basename($seed) . ' - SUCCESS';
            } catch (PDOException $e) {
                $results[] = basename($seed) . ' - FAILED: ' . $e->getMessage();
            }
        }

        return $results;
    }
}