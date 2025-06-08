<?php
session_start();
require_once '../config.php';

// Add error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Check if task_id is provided
if (!isset($_POST['task_id']) || !is_numeric($_POST['task_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid task ID']);
    exit();
}

$task_id = $_POST['task_id'];
$teacher_id = $_SESSION['user_id'];

try {
    // Include required files
    if (!file_exists('extract_pdf_text.php')) {
        throw new Exception('extract_pdf_text.php file not found');
    }
    if (!file_exists('ai_detection_service.php')) {
        throw new Exception('ai_detection_service.php file not found');
    }
    
    require_once 'extract_pdf_text.php';
    require_once 'ai_detection_service.php';

    // Verify that the teacher owns this task
    $stmt = $pdo->prepare("
        SELECT t.*, c.teacher_id 
        FROM tasks t 
        JOIN classes c ON t.class_id = c.id 
        WHERE t.id = ? AND c.teacher_id = ?
    ");
    $stmt->execute([$task_id, $teacher_id]);
    $task = $stmt->fetch();

    if (!$task) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Task not found or unauthorized']);
        exit();
    }

    // Get all submissions for this task that have files
    $stmt = $pdo->prepare("
        SELECT s.id, s.student_id, s.filename, s.ai_probability, u.username
        FROM submissions s
        JOIN users u ON s.student_id = u.id
        WHERE s.task_id = ? AND s.filename IS NOT NULL AND s.filename != ''
    ");
    $stmt->execute([$task_id]);
    $submissions = $stmt->fetchAll();

    if (empty($submissions)) {
        echo json_encode(['success' => true, 'message' => 'No submissions with files found', 'results' => []]);
        exit();
    }

    // Check if functions exist
    if (!function_exists('extractPDFText')) {
        throw new Exception('extractPDFText function not found');
    }
    if (!function_exists('hasEnoughTextForDetection')) {
        throw new Exception('hasEnoughTextForDetection function not found');
    }

    $aiService = new AIDetectionService();
    $results = [];
    $processed = 0;
    $errors = 0;

    foreach ($submissions as $submission) {
        $filePath = "../uploads/student_submissions/{$submission['student_id']}/{$submission['filename']}";
        
        // Check if file exists
        if (!file_exists($filePath)) {
            $results[] = [
                'submission_id' => $submission['id'],
                'student_name' => $submission['username'],
                'filename' => $submission['filename'],
                'error' => 'File not found: ' . $filePath,
                'ai_percentage' => null
            ];
            $errors++;
            continue;
        }

        // Extract text from PDF
        try {
            $extractedText = extractPDFText($filePath);
            
            if (!$extractedText || !hasEnoughTextForDetection($extractedText)) {
                $results[] = [
                    'submission_id' => $submission['id'],
                    'student_name' => $submission['username'],
                    'filename' => $submission['filename'],
                    'error' => 'Could not extract enough text from PDF (extracted: ' . strlen($extractedText ?? '') . ' chars)',
                    'ai_percentage' => null
                ];
                $errors++;
                continue;
            }

            // Perform AI detection
            $detection = $aiService->detectAI($extractedText);
            
            if ($detection && !$detection['error']) {
                $aiPercentage = $detection['percentage'];
                
                // Update database with AI detection result
                $updateStmt = $pdo->prepare("UPDATE submissions SET ai_probability = ? WHERE id = ?");
                $updateStmt->execute([$aiPercentage, $submission['id']]);
                
                $results[] = [
                    'submission_id' => $submission['id'],
                    'student_name' => $submission['username'],
                    'filename' => $submission['filename'],
                    'error' => null,
                    'ai_percentage' => $aiPercentage
                ];
                $processed++;
            } else {
                $results[] = [
                    'submission_id' => $submission['id'],
                    'student_name' => $submission['username'],
                    'filename' => $submission['filename'],
                    'error' => $detection['error'] ?? 'AI detection failed',
                    'ai_percentage' => null
                ];
                $errors++;
            }
        } catch (Exception $e) {
            $results[] = [
                'submission_id' => $submission['id'],
                'student_name' => $submission['username'],
                'filename' => $submission['filename'],
                'error' => 'Processing error: ' . $e->getMessage(),
                'ai_percentage' => null
            ];
            $errors++;
        }
    }

    echo json_encode([
        'success' => true,
        'message' => "AI detection completed. Processed: {$processed}, Errors: {$errors}",
        'results' => $results,
        'summary' => [
            'total' => count($submissions),
            'processed' => $processed,
            'errors' => $errors
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?> 