<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Check if submission_id is provided
if (!isset($_POST['submission_id']) || !is_numeric($_POST['submission_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid submission ID']);
    exit();
}

$submission_id = $_POST['submission_id'];
$teacher_id = $_SESSION['user_id'];

try {
    // Load required files
    if (!file_exists('extract_pdf_text.php')) {
        throw new Exception('extract_pdf_text.php file not found');
    }
    if (!file_exists('python_ai_wrapper.php')) {
        throw new Exception('python_ai_wrapper.php file not found');
    }
    
    require_once 'extract_pdf_text.php';
    require_once 'python_ai_wrapper.php';

    // Verify that the teacher has access to this submission
    $stmt = $pdo->prepare("
        SELECT s.*, t.class_id, c.teacher_id, u.username as student_name
        FROM submissions s
        JOIN tasks t ON s.task_id = t.id
        JOIN classes c ON t.class_id = c.id
        JOIN users u ON s.student_id = u.id
        WHERE s.id = ? AND c.teacher_id = ?
    ");
    $stmt->execute([$submission_id, $teacher_id]);
    $submission = $stmt->fetch();

    if (!$submission) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Submission not found or unauthorized']);
        exit();
    }

    // Check if submission has a file
    if (empty($submission['filename'])) {
        echo json_encode(['success' => false, 'message' => 'No file found for this submission']);
        exit();
    }

    $filePath = "../uploads/student_submissions/{$submission['student_id']}/{$submission['filename']}";
    
    if (!file_exists($filePath)) {
        echo json_encode(['success' => false, 'message' => 'File not found: ' . $filePath]);
        exit();
    }

    // Extract text from PDF
    $extractedText = extractPDFText($filePath);
    
    if (!$extractedText || !hasEnoughTextForDetection($extractedText)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Could not extract enough text from PDF (extracted: ' . strlen($extractedText ?? '') . ' chars)'
        ]);
        exit();
    }

    // Initialize Python AI Detection Service
    $pythonAIService = new PythonAIDetectionService(true);
    
    // Perform AI detection
    $detection = $pythonAIService->checkAI($extractedText);
    
    if ($detection && isset($detection['status']) && $detection['status'] === 'success') {
        $aiPercentage = $detection['ai_percentage'];
        
        // Update database with AI detection result
        $pythonAIService->updateDatabase($submission_id, $aiPercentage);
        
        echo json_encode([
            'success' => true,
            'message' => 'AI detection completed successfully',
            'submission_id' => $submission_id,
            'student_name' => $submission['student_name'],
            'filename' => $submission['filename'],
            'ai_percentage' => $aiPercentage,
            'detection_method' => $detection['method'] ?? 'RoBERTa Large OpenAI Detector',
            'details' => $detection['details'] ?? null
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $detection['error'] ?? 'AI detection failed',
            'submission_id' => $submission_id,
            'student_name' => $submission['student_name'],
            'filename' => $submission['filename']
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?> 