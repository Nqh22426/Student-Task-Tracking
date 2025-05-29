<?php
$host = 'localhost';
$dbname = 'datn';
$username = 'root';
$password = '';

// Thêm base URL cho đường dẫn tuyệt đối
$base_url = 'http://localhost/datn';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    die();
}
?> 