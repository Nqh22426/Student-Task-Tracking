<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['notification_id']) || !is_numeric($input['notification_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
    exit();
}

$notification_id = $input['notification_id'];
$student_id = $_SESSION['user_id'];

try {
    // Verify that the notification belongs to this student before deleting
    $stmt = $pdo->prepare("SELECT id FROM notifications WHERE id = ? AND recipient_id = ?");
    $stmt->execute([$notification_id, $student_id]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Notification not found or access denied']);
        exit();
    }
    
    // Delete the notification
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND recipient_id = ?");
    $stmt->execute([$notification_id, $student_id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Notification deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete notification']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 