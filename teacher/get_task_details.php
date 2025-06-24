<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Check if task ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid task ID']);
    exit();
}

$task_id = $_GET['id'];
$teacher_id = $_SESSION['user_id'];

try {
    // Fetch task details, ensuring it belongs to a class owned by the current teacher
    $stmt = $pdo->prepare(
        "SELECT t.* FROM tasks t
        JOIN classes c ON t.class_id = c.id
        WHERE t.id = ? AND c.teacher_id = ?"
    );
    $stmt->execute([$task_id, $teacher_id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($task) {
        echo json_encode(['success' => true, 'task' => $task]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Task not found or you do not have permission to view it']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} 