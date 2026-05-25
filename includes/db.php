<?php
require_once __DIR__ . '/../config/config.php';

/**
 * Get database connection
 */
function getDB() {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            if (($_GET['debug'] ?? '') === 'caneeli2026') {
                $envPath = realpath(__DIR__ . '/../.env');
                $expectedPath = __DIR__ . '/../.env';
                $exists = file_exists($expectedPath) ? 'YES' : 'NO';
                $readable = is_readable($expectedPath) ? 'YES' : 'NO';
                $perms = file_exists($expectedPath) ? substr(sprintf('%o', fileperms($expectedPath)), -4) : 'n/a';
                $parsed = @parse_ini_file($expectedPath);
                $parsedKeys = is_array($parsed) ? implode(',', array_keys($parsed)) : 'PARSE_FAILED';
                $first200 = file_exists($expectedPath) && is_readable($expectedPath)
                    ? htmlspecialchars(bin2hex(substr(file_get_contents($expectedPath), 0, 60)))
                    : 'n/a';
                die("DB ERROR: " . htmlspecialchars($e->getMessage())
                    . "<br>expected_path=" . htmlspecialchars($expectedPath)
                    . "<br>real_path=" . htmlspecialchars((string) $envPath)
                    . "<br>exists=$exists readable=$readable perms=$perms"
                    . "<br>parsed_keys=" . htmlspecialchars($parsedKeys)
                    . "<br>first_60_bytes_hex=$first200");
            }
            die("Database connection failed. Please try again later.");
        }
    }

    return $pdo;
}
