<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$teacher_id = $data['teacher_id'] ?? null;

if (!$teacher_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid teacher ID']);
    exit();
}

try {
    // Delete the teacher
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'teacher'");
    $result = $stmt->execute([$teacher_id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Teacher not found']);
    }
} catch (PDOException $e) {
    error_log("Error deleting teacher: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?> 