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
            // Same clock as PHP (config.php). TIMESTAMP columns are stored in
            // UTC and converted per connection, so this also fixes how older
            // orders display. A numeric offset like -08:00 works on any MySQL,
            // even without its time zone tables; it's worked out from PHP, so
            // it follows daylight saving.
            $pdo->exec("SET time_zone = '" . date('P') . "'");
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection failed. Please try again later.");
        }
    }

    return $pdo;
}
