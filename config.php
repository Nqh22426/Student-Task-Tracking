<?php
// Database configuration constants
define('DB_HOST', 'localhost');
define('DB_NAME', 'datn');
define('DB_USER', 'root');
define('DB_PASS', '');

// Legacy variables for backward compatibility
$host = DB_HOST;
$dbname = DB_NAME;
$username = DB_USER;
$password = DB_PASS;

// Base URL for absolute paths
$base_url = 'http://localhost/datn';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    die();
}
?> 