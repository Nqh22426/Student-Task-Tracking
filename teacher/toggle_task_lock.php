<?php
session_start();
require_once '../config.php';

// Kiểm tra nếu người dùng đã đăng nhập và là giáo viên
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Kiểm tra nếu có dữ liệu POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task_id']) && isset($_POST['status'])) {
    $task_id = $_POST['task_id'];
    $status = $_POST['status'] === 'locked' ? 1 : 0; // Chuyển đổi 'locked'/'unlocked' thành 1/0
    $teacher_id = $_SESSION['user_id'];
    
    // Kiểm tra xem giáo viên có quyền với task này không
    $stmt = $pdo->prepare("
        SELECT t.id 
        FROM tasks t 
        JOIN classes c ON t.class_id = c.id 
        WHERE t.id = ? AND c.teacher_id = ?
    ");
    $stmt->execute([$task_id, $teacher_id]);
    $task = $stmt->fetch();
    
    if (!$task) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to modify this task']);
        exit();
    }
    
    // Cập nhật trạng thái khóa của task
    $stmt = $pdo->prepare("UPDATE tasks SET is_locked = ? WHERE id = ?");
    $result = $stmt->execute([$status, $task_id]);
    
    if ($result) {
        echo json_encode([
            'success' => true, 
            'message' => 'Task status updated successfully',
            'is_locked' => (bool)$status,  // Include the current lock status in the response
            'task_id' => $task_id // Include the task_id in the response
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update task status']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?> 