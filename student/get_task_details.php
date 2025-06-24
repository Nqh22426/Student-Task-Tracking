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
    // Check if the student has access to this task
    $stmt = $pdo->prepare("
        SELECT t.*, t.is_locked, c.name AS class_name, c.color AS class_color, c.class_code, c.id AS class_id
        FROM tasks t
        JOIN classes c ON t.class_id = c.id
        JOIN class_enrollments ce ON c.id = ce.class_id
        WHERE t.id = ? AND ce.student_id = ?
    ");
    $stmt->execute([$task_id, $student_id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$task) {
        echo json_encode(['error' => 'Task not found or you do not have access to it']);
        exit();
    }
    
    // Get teacher information for this task
    $stmt = $pdo->prepare("
        SELECT u.username, u.profile_image
        FROM users u
        JOIN classes c ON u.id = c.teacher_id
        JOIN tasks t ON c.id = t.class_id
        WHERE t.id = ?
    ");
    $stmt->execute([$task_id]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Add teacher information to the task data
    if ($teacher) {
        $task['teacher_name'] = $teacher['username'];
        $task['teacher_image'] = $teacher['profile_image'];
    }
    
    // Check if student has submitted this task
    $stmt = $pdo->prepare("
        SELECT * FROM submissions 
        WHERE task_id = ? AND student_id = ? 
        ORDER BY submission_date DESC LIMIT 1
    ");
    $stmt->execute([$task_id, $student_id]);
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($submission) {
        $task['has_submission'] = true;
        $task['submission_filename'] = $submission['filename'];
        $task['submission_date'] = $submission['submission_date'];
        $task['submission_path'] = '../uploads/student_submissions/' . $student_id . '/' . $submission['filename'];
    } else {
        $task['has_submission'] = false;
    }
    
    // Ensure is_locked is properly converted to boolean
    $task['is_locked'] = (bool)$task['is_locked'];
    
    // Use timestamps for date comparison to determine task status
    $current_time = time();
    $start_time = strtotime($task['start_datetime']);
    $end_time = strtotime($task['end_datetime']);
    
    // Add timestamp information for debugging
    $task['debug'] = [
        'current_time' => $current_time,
        'start_time' => $start_time,
        'end_time' => $end_time,
        'current_formatted' => date('Y-m-d H:i:s', $current_time),
        'start_formatted' => date('Y-m-d H:i:s', $start_time),
        'end_formatted' => date('Y-m-d H:i:s', $end_time)
    ];
    
    // Check if student_progress table exists and get progress data if it does
    try {
        // Get student's progress on this task
        $stmt = $pdo->prepare("
            SELECT * FROM student_progress 
            WHERE task_id = ? AND student_id = ?
        ");
        $stmt->execute([$task_id, $student_id]);
        $progress = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If no progress record exists, set default values
        if (!$progress) {
            $progress = [
                'status' => 'not_started',
                'submission_text' => null,
                'submission_date' => null
            ];
        }
        
        // Add progress data to the task
        $task['progress'] = $progress['status'];
        $task['submission_text'] = $progress['submission_text'] ?? null;
        $task['progress_submission_date'] = $progress['submission_date'] ?? null;
    } catch (PDOException $progressError) {
        // If there's an error, use default values
        $task['progress'] = 'not_started';
        $task['submission_text'] = null;
        $task['progress_submission_date'] = null;
    }
    
    echo json_encode($task);
    
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit();
} 