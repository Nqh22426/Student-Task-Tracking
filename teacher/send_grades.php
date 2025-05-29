<?php
session_start();
require_once '../config.php';

// Clear any output buffer
if (ob_get_level()) {
    ob_clean();
}

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if task_id is provided
if (!isset($_POST['task_id']) || !is_numeric($_POST['task_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid task ID']);
    exit();
}

$task_id = $_POST['task_id'];
$teacher_id = $_SESSION['user_id'];

// Verify that the teacher has permission to update this task
$stmt = $pdo->prepare("
    SELECT t.id, t.class_id, t.grades_sent
    FROM tasks t
    JOIN classes c ON t.class_id = c.id
    WHERE t.id = ? AND c.teacher_id = ?
");
$stmt->execute([$task_id, $teacher_id]);
$task = $stmt->fetch();

if (!$task) {
    echo json_encode(['success' => false, 'message' => 'Task not found or you don\'t have permission']);
    exit();
}

try {
    // Begin transaction
    $pdo->beginTransaction();

    // Update task to mark grades as sent
    $stmt = $pdo->prepare("UPDATE tasks SET grades_sent = 1 WHERE id = ?");
    $stmt->execute([$task_id]);
    
    // Commit changes
    $pdo->commit();

    // Return success response
    echo json_encode(['success' => true, 'message' => 'Grades have been sent to students successfully']);
} catch (Exception $e) {
    // Roll back transaction on error
    $pdo->rollBack();
    
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
} 
?> 