<?php
class Database {
    private static $connection = null;

    public static function getConnection() {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        self::ensureDatabaseExistsIfLocal();

        $hosts = self::resolveHostCandidates((string) DB_HOST);
        $lastException = null;
        foreach ($hosts as $host) {
            try {
                $dsn = 'mysql:host=' . $host . ';dbname=' . DB_NAME . ';charset=utf8mb4';
                self::$connection = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]);
                return self::$connection;
            } catch (Throwable $exception) {
                $lastException = $exception;
                error_log('Database connection failed on host ' . $host . ': ' . $exception->getMessage());
            }
        }

        if ($lastException instanceof Throwable) {
            throw $lastException;
        }

        throw new RuntimeException('Database connection failed: no host candidate available.');
    }

    private static function ensureDatabaseExistsIfLocal() {
        if (!defined('APP_ENV')) {
            return;
        }

        $env = strtolower(trim((string) APP_ENV));
        if (!in_array($env, ['local', 'development'], true)) {
            return;
        }

        $dsn = 'mysql:host=' . DB_HOST . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        } catch (Throwable $exception) {
            error_log('Database bootstrap skipped: ' . $exception->getMessage());
        }
    }

    private static function resolveHostCandidates($rawHost) {
        $rawHost = trim((string) $rawHost);
        if ($rawHost === '') {
            return ['localhost'];
        }

        $hosts = preg_split('/\s*,\s*/', $rawHost) ?: [];
        $hosts = array_values(array_filter(array_map('trim', $hosts), static function ($value) {
            return (string) $value !== '';
        }));

        if (empty($hosts)) {
            $hosts[] = 'localhost';
        }

        // On shared hosting, either localhost or 127.0.0.1 may be required.
        if (in_array('localhost', $hosts, true) && !in_array('127.0.0.1', $hosts, true)) {
            $hosts[] = '127.0.0.1';
        }

        return $hosts;
    }
}
