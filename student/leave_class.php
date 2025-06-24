<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

if (!isset($_POST['class_id']) || !is_numeric($_POST['class_id'])) {
    $_SESSION['error'] = "Invalid class ID.";
    header("Location: dashboard.php");
    exit();
}

$class_id = $_POST['class_id'];
$student_id = $_SESSION['user_id'];

try {
    // Check if student is enrolled in this class
    $stmt = $pdo->prepare("SELECT * FROM class_enrollments WHERE class_id = ? AND student_id = ?");
    $stmt->execute([$class_id, $student_id]);
    
    if ($stmt->rowCount() == 0) {
        $_SESSION['error'] = "You are not enrolled in this class.";
        header("Location: dashboard.php");
        exit();
    }

    // Get class name for success message
    $stmt = $pdo->prepare("SELECT name FROM classes WHERE id = ?");
    $stmt->execute([$class_id]);
    $class = $stmt->fetch();
    
    // Check if student_progress table exists before trying to delete records
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'student_progress'");
    $stmt->execute();
    $tableExists = ($stmt->rowCount() > 0);
    
    if ($tableExists) {
        try {
            // Try to delete student progress for this class's tasks
            $stmt = $pdo->prepare("
                DELETE sp FROM student_progress sp
                JOIN tasks t ON sp.task_id = t.id
                WHERE sp.student_id = ? AND t.class_id = ?
            ");
            $stmt->execute([$student_id, $class_id]);
        } catch (PDOException $e) {
            error_log("Error deleting student progress: " . $e->getMessage());
        }
    }
    
    // Remove enrollment
    $stmt = $pdo->prepare("DELETE FROM class_enrollments WHERE class_id = ? AND student_id = ?");
    $stmt->execute([$class_id, $student_id]);
    
    $_SESSION['success'] = "You have successfully left the class: " . htmlspecialchars($class['name']);
} catch (PDOException $e) {
    $_SESSION['error'] = "Error leaving class: " . $e->getMessage();
}

header("Location: dashboard.php");
exit();
?> 