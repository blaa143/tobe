 <?php
// config.php - Main configuration file for the PMMS application
// This file MUST be clean with NO output before this line.
session_start();

// Database credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'database'); // <--- CHECK YOUR ACTUAL DB NAME HERE!
define('DB_USER', 'root');
define('DB_PASS', '');

// Establish Database Connection using PDO
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}

// Utility function for redirection
function redirect($url) {
    header("Location: " . $url);
    exit();
}

// Include the new logging utility
require_once 'logger.php';
?>
