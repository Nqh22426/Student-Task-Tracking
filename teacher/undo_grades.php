<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    $_SESSION['error'] = "Unauthorized access";
    header("Location: dashboard.php");
    exit();
}

// Check if task_id is provided
if (!isset($_GET['task_id']) || !is_numeric($_GET['task_id']) || 
    !isset($_GET['class_id']) || !is_numeric($_GET['class_id'])) {
    $_SESSION['error'] = "Invalid parameters";
    header("Location: dashboard.php");
    exit();
}

$task_id = $_GET['task_id'];
$class_id = $_GET['class_id'];
$teacher_id = $_SESSION['user_id'];

// Verify that the teacher owns this class
$stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ? AND teacher_id = ?");
$stmt->execute([$class_id, $teacher_id]);
$class = $stmt->fetch();

if (!$class) {
    $_SESSION['error'] = "Class not found or you don't have permission to access it";
    header("Location: dashboard.php");
    exit();
}

// Verify that the task belongs to this class
$stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ? AND class_id = ?");
$stmt->execute([$task_id, $class_id]);
$task = $stmt->fetch();

if (!$task) {
    $_SESSION['error'] = "Task not found or doesn't belong to this class";
    header("Location: student_submissions.php?id=" . $class_id);
    exit();
}

try {
    // Update task to mark grades as unsent
    $stmt = $pdo->prepare("UPDATE tasks SET grades_sent = 0 WHERE id = ?");
    $stmt->execute([$task_id]);

} catch (Exception $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
}

// Redirect back to the review submissions page
header("Location: review_submissions.php?task_id=$task_id&class_id=$class_id");
exit(); 