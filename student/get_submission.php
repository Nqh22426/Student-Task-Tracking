<?php
session_start();
require_once '../config.php';

// Set header to return JSON
header('Content-Type: application/json');

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if task ID is provided
if (!isset($_GET['task_id']) || !is_numeric($_GET['task_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid task ID']);
    exit();
}

$task_id = (int)$_GET['task_id'];
$student_id = $_SESSION['user_id'];

try {
    // Get the latest submission for this task and student
    $stmt = $pdo->prepare("
        SELECT * FROM submissions 
        WHERE student_id = ? AND task_id = ? 
        ORDER BY submission_date DESC 
        LIMIT 1
    ");
    $stmt->execute([$student_id, $task_id]);
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($submission) {
        // Format submission date for display
        if (isset($submission['submission_date'])) {
            $date = new DateTime($submission['submission_date']);
            $submission['formatted_date'] = $date->format('M j, Y - g:i A');
        }
        
        echo json_encode([
            'success' => true, 
            'submission' => $submission
        ]);
    } else {
        echo json_encode([
            'success' => true, 
            'submission' => null, 
            'message' => 'No submission found'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?> 