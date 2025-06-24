<?php
session_start();
require_once '../config.php';

// Check login and role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION['user_id'];

// Check if form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['join_code'])) {
    $join_code = trim($_POST['join_code']);
    
    // Validate join code
    if (empty($join_code)) {
        $_SESSION['error'] = "Join code cannot be empty.";
        header("Location: dashboard.php");
        exit();
    }
    
    // Check if class exists with this class code
    $stmt = $pdo->prepare("SELECT * FROM classes WHERE class_code = ?");
    $stmt->execute([$join_code]);
    $class = $stmt->fetch();
    
    if (!$class) {
        $_SESSION['error'] = "Invalid join code. Please check and try again.";
        header("Location: dashboard.php");
        exit();
    }
    
    $class_id = $class['id'];
    
    // Check if student is already enrolled in this class
    $stmt = $pdo->prepare("SELECT * FROM class_enrollments WHERE class_id = ? AND student_id = ?");
    $stmt->execute([$class_id, $student_id]);
    $enrollment = $stmt->fetch();
    
    if ($enrollment) {
        $_SESSION['error'] = "You are already enrolled in this class.";
        header("Location: dashboard.php");
        exit();
    }
    
    // Enroll student in the class
    try {
        $stmt = $pdo->prepare("INSERT INTO class_enrollments (class_id, student_id) VALUES (?, ?)");
        $result = $stmt->execute([$class_id, $student_id]);
        
        if ($result) {
            $_SESSION['success'] = "Successfully joined class: " . htmlspecialchars($class['name']);
        } else {
            $_SESSION['error'] = "Failed to join class. Please try again.";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
    }
    
    header("Location: dashboard.php");
    exit();
} else {
    // If accessed directly without form submission
    $_SESSION['error'] = "Invalid request.";
    header("Location: dashboard.php");
    exit();
}
?> 