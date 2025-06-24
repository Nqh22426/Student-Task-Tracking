<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $class_id = isset($_POST['class_id']) ? intval($_POST['class_id']) : 0;
    $title = trim($_POST['task_title'] ?? '');
    $description = trim($_POST['task_description'] ?? '');
    $start_datetime = $_POST['task_start_datetime'] ?? '';
    $end_datetime = $_POST['task_end_datetime'] ?? '';
    
    // Validate class ID
    if (empty($class_id)) {
        $_SESSION['error'] = "Invalid class ID";
        header("Location: dashboard.php");
        exit();
    }
    
    // Ensure this class belongs to the current teacher
    $stmt = $pdo->prepare("SELECT id FROM classes WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$class_id, $_SESSION['user_id']]);
    if (!$stmt->fetch()) {
        $_SESSION['error'] = "You don't have permission to add tasks to this class";
        header("Location: dashboard.php");
        exit();
    }
    
    // Validate required fields
    if (empty($title)) {
        $_SESSION['error'] = "Task title is required";
        header("Location: class_details.php?id=" . $class_id);
        exit();
    }
    
    if (empty($start_datetime) || empty($end_datetime)) {
        $_SESSION['error'] = "Start and end dates are required";
        header("Location: class_details.php?id=" . $class_id);
        exit();
    }
    
    // Validate datetime format and logic
    $start_timestamp = strtotime($start_datetime);
    $end_timestamp = strtotime($end_datetime);
    
    if (!$start_timestamp || !$end_timestamp) {
        $_SESSION['error'] = "Invalid date format";
        header("Location: class_details.php?id=" . $class_id);
        exit();
    }
    
    if ($end_timestamp <= $start_timestamp) {
        $_SESSION['error'] = "End date must be after start date";
        header("Location: class_details.php?id=" . $class_id);
        exit();
    }
    
    // Handle PDF file upload
    $pdf_file_path = null;
    if (isset($_FILES['task_pdf_file']) && $_FILES['task_pdf_file']['error'] == 0) {
        // Check if the file is a PDF
        $file_ext = strtolower(pathinfo($_FILES['task_pdf_file']['name'], PATHINFO_EXTENSION));
        if ($file_ext != 'pdf') {
            $_SESSION['error'] = "Only PDF files are allowed";
            header("Location: class_details.php?id=" . $class_id);
            exit();
        }

        // Create directory if it doesn't exist
        $upload_dir = '../uploads/task_pdfs/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        // Generate unique file name
        $new_file_name = 'task_' . time() . '_' . uniqid() . '.pdf';
        $upload_path = $upload_dir . $new_file_name;

        // Move uploaded file to our directory
        if (move_uploaded_file($_FILES['task_pdf_file']['tmp_name'], $upload_path)) {
            $pdf_file_path = 'uploads/task_pdfs/' . $new_file_name;
        } else {
            $_SESSION['error'] = "Error uploading PDF file";
            header("Location: class_details.php?id=" . $class_id);
            exit();
        }
    }
    
    try {
        // Insert the new task
        $stmt = $pdo->prepare("INSERT INTO tasks (class_id, title, description, pdf_file, start_datetime, end_datetime) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $class_id,
            $title,
            $description,
            $pdf_file_path,
            date('Y-m-d H:i:s', $start_timestamp),
            date('Y-m-d H:i:s', $end_timestamp)
        ]);
        
        // Get the newly created task ID
        $task_id = $pdo->lastInsertId();
        
        // Send notification emails to students
        require_once '../includes/notification_service.php';
        $notificationService = new NotificationService($pdo);
        $notificationService->createTaskCreatedNotifications($task_id, $class_id, $_SESSION['user_id']);
        
        $_SESSION['success'] = "Task created successfully";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error creating task: " . $e->getMessage();
    }
    
    // Redirect back to class details
    header("Location: class_details.php?id=" . $class_id);
    exit();
} else {
    header("Location: dashboard.php");
    exit();
}