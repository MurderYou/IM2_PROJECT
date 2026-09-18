<?php
/**
 * config/db.php
 * Central PDO database connection for SEMS.
 *
 * Assumptions (adjust if your actual setup differs):
 * - Database name: sems_db
 * - Host: localhost, default XAMPP/Laragon credentials (root / no password)
 * - If your existing schema uses a different DB name or credentials,
 *   just change the four constants below — nothing else needs to change.
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'sems_db');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    // Never show raw database errors to the user (rubric requirement 7).
    // Log the real error server-side for debugging instead.
    error_log('Database connection failed: ' . $e->getMessage());

    http_response_code(500);
    die('The system is temporarily unavailable. Please try again in a moment.');
}