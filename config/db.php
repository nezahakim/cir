<?php
/**
 * config/db.php
 * Database connection using PDO for the Community Issue Reporter system.
 * All database interactions in this project use PDO (not mysqli).
 */

// Database configuration constants
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'cir_db');
define('DB_USER', 'root');      
define('DB_PASS', '***');
define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a PDO database connection instance.
 * Uses a static variable to reuse the same connection (singleton pattern).
 */
function getDB(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // Throw exceptions on error
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // Return associative arrays
            PDO::ATTR_EMULATE_PREPARES   => false,                    // Use real prepared statements
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Show a clean error — never expose raw DB errors in production
            die('<div style="font-family:sans-serif;padding:40px;background:#fff3f3;border:2px solid #dc3545;border-radius:8px;margin:40px auto;max-width:600px;">
                    <h3 style="color:#dc3545;">⚠ Database Connection Failed</h3>
                    <p>Could not connect to the database. Please ensure:</p>
                    <ul>
                        <li>XAMPP MySQL service is running</li>
                        <li>Database <strong>cir_db</strong> exists (import cir_database.sql)</li>
                        <li>Credentials in config/db.php are correct</li>
                    </ul>
                    <p style="color:#666;font-size:0.85em;">Error: ' . htmlspecialchars($e->getMessage()) . '</p>
                 </div>');
        }
    }

    return $pdo;
}
