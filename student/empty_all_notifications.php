<?php
session_start();
require_once '../config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['notification_ids']) || !is_array($input['notification_ids'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid notification IDs']);
    exit();
}

$notification_ids = $input['notification_ids'];
$student_id = $_SESSION['user_id'];

try {
    $placeholders = str_repeat('?,', count($notification_ids) - 1) . '?';
    
    // Verify that all notifications belong to this student before deleting
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE id IN ($placeholders) AND recipient_id = ?");
    $params = array_merge($notification_ids, [$student_id]);
    $stmt->execute($params);
    $result = $stmt->fetch();
    
    if ($result['count'] != count($notification_ids)) {
        echo json_encode(['success' => false, 'message' => 'Some notifications not found or access denied']);
        exit();
    }
    
    // Delete all notifications
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE id IN ($placeholders) AND recipient_id = ?");
    $stmt->execute($params);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'All notifications deleted successfully', 'deleted_count' => $stmt->rowCount()]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete notifications']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 