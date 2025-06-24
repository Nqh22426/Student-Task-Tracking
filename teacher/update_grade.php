<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_POST['submission_id']) || !isset($_POST['grade'])) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit();
}

$submission_id = intval($_POST['submission_id']);
$grade = $_POST['grade'];
$teacher_id = $_SESSION['user_id'];

try {
    // Verify that the teacher owns the class for this submission
    $stmt = $pdo->prepare("
        SELECT s.id, s.task_id, t.class_id, c.teacher_id 
        FROM submissions s
        JOIN tasks t ON s.task_id = t.id
        JOIN classes c ON t.class_id = c.id
        WHERE s.id = ? AND c.teacher_id = ?
    ");
    $stmt->execute([$submission_id, $teacher_id]);
    $submission = $stmt->fetch();

    if (!$submission) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    if ($grade === 'null') {
        // Remove grade
        $stmt = $pdo->prepare("UPDATE submissions SET grade = NULL WHERE id = ?");
        if ($stmt->execute([$submission_id])) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        exit();
    }

    $grade = floatval($grade);
    if ($grade < 0 || $grade > 100) {
        echo json_encode(['success' => false, 'message' => 'Invalid grade']);
        exit();
    }

    // Update grade
    $stmt = $pdo->prepare("UPDATE submissions SET grade = ? WHERE id = ?");
    if ($stmt->execute([$grade, $submission_id])) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
exit(); 