<?php
session_start();
require_once '../config.php';

// Check if the user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if there is POST data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task_id']) && isset($_POST['status'])) {
    $task_id = $_POST['task_id'];
    $status = $_POST['status'] === 'locked' ? 1 : 0; // Convert 'locked'/'unlocked' to 1/0
    $teacher_id = $_SESSION['user_id'];
    
    // Check if the teacher has permission to modify this task
    $stmt = $pdo->prepare("
        SELECT t.id 
        FROM tasks t 
        JOIN classes c ON t.class_id = c.id 
        WHERE t.id = ? AND c.teacher_id = ?
    ");
    $stmt->execute([$task_id, $teacher_id]);
    $task = $stmt->fetch();
    
    if (!$task) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to modify this task']);
        exit();
    }
    
    // Update the status of the task
    $stmt = $pdo->prepare("UPDATE tasks SET is_locked = ? WHERE id = ?");
    $result = $stmt->execute([$status, $task_id]);
    
    if ($result) {
        echo json_encode([
            'success' => true, 
            'message' => 'Task status updated successfully',
            'is_locked' => (bool)$status,
            'task_id' => $task_id
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update task status']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?> 