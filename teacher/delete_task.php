<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

// Check if task ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid task ID";
    header("Location: dashboard.php");
    exit();
}

$task_id = $_GET['id'];
$teacher_id = $_SESSION['user_id'];

try {
    // Get class ID for the task before deleting
    $stmt = $pdo->prepare(
        "SELECT t.class_id FROM tasks t
        JOIN classes c ON t.class_id = c.id
        WHERE t.id = ? AND c.teacher_id = ?"
    );
    $stmt->execute([$task_id, $teacher_id]);
    $result = $stmt->fetch();
    
    if (!$result) {
        $_SESSION['error'] = "Task not found or you don't have permission to delete it";
        header("Location: dashboard.php");
        exit();
    }
    
    $class_id = $result['class_id'];
    
    // Delete the task
    $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
    $stmt->execute([$task_id]);
    
    $_SESSION['success'] = "Task deleted successfully";
    header("Location: class_details.php?id=" . $class_id);
} catch (PDOException $e) {
    $_SESSION['error'] = "Error deleting task: " . $e->getMessage();
    header("Location: dashboard.php");
}
exit(); 