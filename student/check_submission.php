<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Check if task_id parameter exists and is numeric
if (!isset($_GET['task_id']) || !is_numeric($_GET['task_id'])) {
    echo json_encode(['error' => 'Invalid task ID']);
    exit();
}

$task_id = $_GET['task_id'];
$student_id = $_SESSION['user_id'];

try {
    // Check if the student has a submission for this task
    $stmt = $pdo->prepare("
        SELECT * FROM submissions 
        WHERE student_id = ? AND task_id = ? 
        ORDER BY submission_date DESC LIMIT 1
    ");
    $stmt->execute([$student_id, $task_id]);
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($submission) {
        // If submission exists, get file path
        $file_path = '../uploads/student_submissions/' . $student_id . '/' . $submission['filename'];
        
        echo json_encode([
            'hasSubmission' => true,
            'filename' => $submission['filename'],
            'filePath' => $file_path,
            'submissionDate' => $submission['submission_date']
        ]);
    } else {
        echo json_encode(['hasSubmission' => false]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage(),
        'hasSubmission' => false
    ]);
}
?> 