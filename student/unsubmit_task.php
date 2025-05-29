<?php
session_start();
require_once '../config.php';

// Check if this is an AJAX request
$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] == 1;

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    if ($is_ajax) {
        echo json_encode(['success' => false, 'error' => 'You must be logged in as a student to perform this action.']);
        exit();
    } else {
        $_SESSION['error'] = 'You must be logged in as a student to perform this action.';
        header("Location: dashboard.php");
        exit();
    }
}

// Get task_id and student_id
$task_id = isset($_GET['task_id']) ? intval($_GET['task_id']) : 0;
$student_id = $_SESSION['user_id'];

// Check for explicit redirect parameter
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : '';

// Determine the referring page to redirect back to
$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'dashboard.php';

// Check if it's from todo_list
$from_todo = strpos($referer, 'todo_list.php') !== false;

// Validate task_id
if ($task_id <= 0) {
    if ($is_ajax) {
        echo json_encode(['success' => false, 'error' => 'Invalid task ID.']);
        exit();
    } else {
        $_SESSION['error'] = 'Invalid task ID.';
        header("Location: $referer");
        exit();
    }
}

try {
    // Get task details to check if it's locked
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
    $stmt->execute([$task_id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$task) {
        if ($is_ajax) {
            echo json_encode(['success' => false, 'error' => 'Task not found.']);
            exit();
        } else {
            $_SESSION['error'] = 'Task not found.';
            header("Location: $referer");
            exit();
        }
    }
    
    // Check if task is locked (but not if it's completed)
    if ($task['is_locked']) {
        if ($is_ajax) {
            echo json_encode(['success' => false, 'error' => 'You cannot unsubmit a locked task.']);
            exit();
        } else {
            $_SESSION['error'] = 'You cannot unsubmit a locked task.';
            header("Location: $referer");
            exit();
        }
    }
    
    // Get current submission
    $stmt = $pdo->prepare("SELECT s.*, t.class_id FROM submissions s 
                         JOIN tasks t ON s.task_id = t.id 
                         WHERE s.task_id = ? AND s.student_id = ? 
                         ORDER BY s.submission_date DESC LIMIT 1");
    $stmt->execute([$task_id, $student_id]);
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$submission) {
        if ($is_ajax) {
            echo json_encode(['success' => false, 'error' => 'No submission found for this task.']);
            exit();
        } else {
            $_SESSION['error'] = 'No submission found for this task.';
            header("Location: $referer");
            exit();
        }
    }
    
    // Get class ID for redirection
    $class_id = $submission['class_id'];
    
    // Calculate file path from user info and filename
    $file_to_delete = '../uploads/student_submissions/' . $student_id . '/' . $submission['filename'];
    
    // Delete submission from database
    $stmt = $pdo->prepare("DELETE FROM submissions WHERE id = ?");
    $stmt->execute([$submission['id']]);
    
    // Delete the file if it exists
    if (file_exists($file_to_delete)) {
        unlink($file_to_delete);
    }
    
    if ($is_ajax) {
        echo json_encode(['success' => true, 'message' => 'Submission removed successfully.']);
        exit();
    } else {
        $_SESSION['success'] = 'Submission removed successfully.';
        
        // Redirect based on parameters
        if (!empty($redirect)) {
            // Use explicit redirect parameter if provided
            header("Location: $redirect");
        } else if ($from_todo) {
            header("Location: todo_list.php");
        } else {
            header("Location: tasks_list.php?id=$class_id");
        }
    }
    
} catch (Exception $e) {
    if ($is_ajax) {
        echo json_encode(['success' => false, 'error' => 'An error occurred: ' . $e->getMessage()]);
        exit();
    } else {
        $_SESSION['error'] = 'An error occurred: ' . $e->getMessage();
        header("Location: $referer");
    }
}
exit();
?> 