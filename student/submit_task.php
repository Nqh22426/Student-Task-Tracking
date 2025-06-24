<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    if (isset($_POST['ajax'])) {
        echo json_encode(['success' => false, 'error' => 'You must be logged in as a student to submit a task.']);
        exit();
    } else {
        $_SESSION['error'] = "You must be logged in as a student to submit a task.";
        header("Location: dashboard.php");
        exit();
    }
}

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (isset($_POST['ajax'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid request.']);
        exit();
    } else {
        $_SESSION['error'] = "Invalid request.";
        header("Location: dashboard.php");
        exit();
    }
}

// Check if task ID is provided
if (!isset($_POST['task_id']) || !is_numeric($_POST['task_id'])) {
    if (isset($_POST['ajax'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid task ID.']);
        exit();
    } else {
        $_SESSION['error'] = "Invalid task ID.";
        header("Location: dashboard.php");
        exit();
    }
}

$task_id = $_POST['task_id'];
$student_id = $_SESSION['user_id'];

// Check if student has access to this task
$stmt = $pdo->prepare("
    SELECT t.*, c.name AS class_name
    FROM tasks t
    JOIN classes c ON t.class_id = c.id
    JOIN class_enrollments ce ON c.id = ce.class_id
    WHERE t.id = ? AND ce.student_id = ?
");
$stmt->execute([$task_id, $student_id]);
$task = $stmt->fetch();

if (!$task) {
    if (isset($_POST['ajax'])) {
        echo json_encode(['success' => false, 'error' => 'Task not found or you do not have access to it.']);
        exit();
    } else {
        $_SESSION['error'] = "Task not found or you do not have access to it.";
        header("Location: dashboard.php");
        exit();
    }
}

// Check if the task is locked
if ($task['is_locked']) {
    if (isset($_POST['ajax'])) {
        echo json_encode(['success' => false, 'error' => 'This task has been locked by your teacher. You cannot submit at this time.']);
        exit();
    } else {
        $_SESSION['error'] = "This task has been locked by your teacher. You cannot submit at this time.";
        // Get the referer to redirect back to the correct page
        $referer = isset($_POST['referer']) ? $_POST['referer'] : 'todo_list.php';
        header("Location: $referer");
        exit();
    }
}

// Check if a file was uploaded
if (!isset($_FILES['submission_file']) || $_FILES['submission_file']['error'] === UPLOAD_ERR_NO_FILE) {
    if (isset($_POST['ajax'])) {
        echo json_encode(['success' => false, 'error' => 'Please upload a file.']);
        exit();
    } else {
        $_SESSION['error'] = "Please upload a file.";
        // Get the referer to redirect back to the correct page
        $referer = isset($_POST['referer']) ? $_POST['referer'] : 'todo_list.php';
        header("Location: $referer");
        exit();
    }
}

// Validate the uploaded file
$file = $_FILES['submission_file'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => "The uploaded file exceeds the upload_max_filesize directive in php.ini.",
        UPLOAD_ERR_FORM_SIZE => "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.",
        UPLOAD_ERR_PARTIAL => "The uploaded file was only partially uploaded.",
        UPLOAD_ERR_NO_TMP_DIR => "Missing a temporary folder.",
        UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk.",
        UPLOAD_ERR_EXTENSION => "A PHP extension stopped the file upload."
    ];
    
    $errorMessage = $errorMessages[$file['error']] ?? "Unknown upload error.";
    
    if (isset($_POST['ajax'])) {
        echo json_encode(['success' => false, 'error' => 'File upload failed: ' . $errorMessage]);
        exit();
    } else {
        $_SESSION['error'] = "File upload failed: " . $errorMessage;
        // Get the referer to redirect back to the correct page
        $referer = isset($_POST['referer']) ? $_POST['referer'] : 'todo_list.php';
        header("Location: $referer");
        exit();
    }
}

// Check file type
$finfo = new finfo(FILEINFO_MIME_TYPE);
$fileType = $finfo->file($file['tmp_name']);

if ($fileType !== 'application/pdf') {
    if (isset($_POST['ajax'])) {
        echo json_encode(['success' => false, 'error' => 'Only PDF files are allowed.']);
        exit();
    } else {
        $_SESSION['error'] = "Only PDF files are allowed.";
        $referer = isset($_POST['referer']) ? $_POST['referer'] : 'todo_list.php';
        header("Location: $referer");
        exit();
    }
}

// Check file size (limit to 30MB)
$maxSize = 30 * 1024 * 1024;
if ($file['size'] > $maxSize) {
    if (isset($_POST['ajax'])) {
        echo json_encode(['success' => false, 'error' => 'File is too large. Maximum size is 30MB.']);
        exit();
    } else {
        $_SESSION['error'] = "File is too large. Maximum size is 30MB.";
        $referer = isset($_POST['referer']) ? $_POST['referer'] : 'todo_list.php';
        header("Location: $referer");
        exit();
    }
}

// Create directory for student submissions if it doesn't exist
$uploadDir = '../uploads/student_submissions/' . $student_id;
if (!file_exists($uploadDir)) {
    if (!mkdir($uploadDir, 0777, true)) {
        if (isset($_POST['ajax'])) {
            echo json_encode(['success' => false, 'error' => 'Failed to create upload directory.']);
            exit();
        } else {
            $_SESSION['error'] = "Failed to create upload directory.";
            error_log("Failed to create directory: " . $uploadDir);
            $referer = isset($_POST['referer']) ? $_POST['referer'] : 'todo_list.php';
            header("Location: $referer");
            exit();
        }
    }
    error_log("Created directory: " . $uploadDir);
}

// Generate a unique filename to prevent overwriting
$originalName = pathinfo($file['name'], PATHINFO_FILENAME);
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$timestamp = time();
$filename = sanitizeFilename($originalName) . '_' . $timestamp . '.' . $extension;
$uploadPath = $uploadDir . '/' . $filename;

error_log("Attempting to save file: " . $file['name'] . " to " . $uploadPath);

// Move the uploaded file to the destination
if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
    if (isset($_POST['ajax'])) {
        echo json_encode(['success' => false, 'error' => 'Failed to move uploaded file.']);
        exit();
    } else {
        $_SESSION['error'] = "Failed to move uploaded file.";
        error_log("Failed to move file from " . $file['tmp_name'] . " to " . $uploadPath);
        $referer = isset($_POST['referer']) ? $_POST['referer'] : 'todo_list.php';
        header("Location: $referer");
        exit();
    }
}

error_log("Successfully saved file to: " . $uploadPath);

// Create submissions table if it doesn't exist
try {
    $stmt = $pdo->prepare("
        CREATE TABLE IF NOT EXISTS submissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            task_id INT NOT NULL,
            filename VARCHAR(255) NOT NULL,
            file_path VARCHAR(255) DEFAULT NULL,
            submission_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status ENUM('submitted', 'graded') DEFAULT 'submitted',
            grade INT NULL,
            feedback TEXT,
            FOREIGN KEY (student_id) REFERENCES users(id),
            FOREIGN KEY (task_id) REFERENCES tasks(id)
        )
    ");
    $stmt->execute();
} catch (PDOException $e) {
    if (isset($_POST['ajax'])) {
        echo json_encode(['success' => false, 'error' => 'Error creating submissions table: ' . $e->getMessage()]);
        exit();
    } else {
        $_SESSION['error'] = "Error creating submissions table: " . $e->getMessage();
        $referer = isset($_POST['referer']) ? $_POST['referer'] : 'todo_list.php';
        header("Location: $referer");
        exit();
    }
}

// Check if submission already exists
try {
    $stmt = $pdo->prepare("SELECT id FROM submissions WHERE task_id = ? AND student_id = ?");
    $stmt->execute([$task_id, $student_id]);
    $existing = $stmt->fetch();
    
    // Update or create submission
    if ($existing) {
        error_log("Updating existing submission for task ID: " . $task_id . ", student ID: " . $student_id);
        $stmt = $pdo->prepare("
            UPDATE submissions
            SET filename = ?, submission_date = NOW(), status = 'submitted'
            WHERE task_id = ? AND student_id = ?
        ");
        $stmt->execute([$filename, $task_id, $student_id]);
        error_log("Updated submission with filename: " . $filename);
    } else {
        error_log("Creating new submission for task ID: " . $task_id . ", student ID: " . $student_id);
        $stmt = $pdo->prepare("
            INSERT INTO submissions 
            (student_id, task_id, filename)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$student_id, $task_id, $filename]);
        error_log("Created new submission with filename: " . $filename);
    }
} catch (PDOException $e) {
    // Delete file if saving to database fails
    if (file_exists($uploadPath)) {
        unlink($uploadPath);
    }
    
    if (isset($_POST['ajax'])) {
        echo json_encode(['success' => false, 'error' => 'Error saving submission: ' . $e->getMessage()]);
        exit();
    } else {
        $_SESSION['error'] = "Error saving submission: " . $e->getMessage();
        $referer = isset($_POST['referer']) ? $_POST['referer'] : 'todo_list.php';
        header("Location: $referer");
        exit();
    }
}

// Update student_progress table if it exists
try {
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'student_progress'");
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->prepare("
            INSERT INTO student_progress 
            (student_id, task_id, status, submission_date)
            VALUES (?, ?, 'completed', NOW())
            ON DUPLICATE KEY UPDATE status = 'completed', submission_date = NOW()
        ");
        $stmt->execute([$student_id, $task_id]);
    }
} catch (PDOException $e) {
    error_log("Error updating student_progress: " . $e->getMessage());
}

// Success
if (isset($_POST['ajax'])) {
    echo json_encode(['success' => true, 'message' => 'Task submitted successfully!']);
    exit();
} else {
    $_SESSION['success'] = "Task submitted successfully!";
    
    $referer = isset($_POST['referer']) ? $_POST['referer'] : 'todo_list.php';
    
    if (!in_array($referer, ['todo_list.php']) && 
        strpos($referer, 'tasks_list.php') === false && 
        strpos($referer, 'class_details.php') === false) {
        $referer = 'todo_list.php';
    }
    
    header("Location: $referer");
    exit();
}

/**
 * Sanitize filename to prevent directory traversal and other security issues
 */
function sanitizeFilename($filename) {
    // Remove any character that is not alphanumeric, underscore, dash or dot
    $filename = preg_replace('/[^\w\-\.]/', '_', $filename);
    // Remove any leading or trailing dots or spaces
    $filename = trim($filename, ' .');
    // Replace multiple consecutive underscores with a single one
    $filename = preg_replace('/_+/', '_', $filename);
    // Limit length
    $filename = substr($filename, 0, 100);
    
    // Add a default name if the filename is empty after sanitization
    if (empty($filename)) {
        $filename = 'submission';
    }
    
    return $filename;
} 