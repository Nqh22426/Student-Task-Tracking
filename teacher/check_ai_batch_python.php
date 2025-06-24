<?php
session_start();
require_once '../config.php';

// Disable error display to prevent non-JSON output
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set content type to JSON
header('Content-Type: application/json; charset=utf-8');

// Increase time limit for AI detection processing
set_time_limit(300); // 5 minutes
ini_set('max_execution_time', 300);

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
    if (!file_exists('extract_pdf_text.php')) {
        throw new Exception('extract_pdf_text.php file not found');
    }
    if (!file_exists('python_ai_wrapper.php')) {
        throw new Exception('python_ai_wrapper.php file not found');
    }
    
    require_once 'extract_pdf_text.php';
    require_once 'python_ai_wrapper.php';

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

    if (!function_exists('extractPDFText')) {
        throw new Exception('extractPDFText function not found');
    }
    if (!function_exists('hasEnoughTextForDetection')) {
        throw new Exception('hasEnoughTextForDetection function not found');
    }

    // Initialize Python AI Detection Service
    $pythonAIService = new PythonAIDetectionService(true);
    
    // Test Python environment
    $envTest = $pythonAIService->testEnvironment();
    if (!$envTest['success']) {
        error_log('Python AI environment test failed: ' . $envTest['error']);
        throw new Exception('Python AI Detection environment test failed: ' . $envTest['error']);
    }
    
    error_log('Python AI environment test passed: ' . json_encode($envTest));
    
    $results = [];
    $processed = 0;
    $errors = 0;

    foreach ($submissions as $submission) {
        $filePath = "../uploads/student_submissions/{$submission['student_id']}/{$submission['filename']}";
        
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

        try {
            // Check processing time to avoid timeout
            $currentTime = time();
            $elapsedTime = $currentTime - (isset($_SERVER['REQUEST_TIME']) ? $_SERVER['REQUEST_TIME'] : $currentTime);
            
            if ($elapsedTime > 240) { // 4 minutes elapsed, stop processing
                $results[] = [
                    'submission_id' => $submission['id'],
                    'student_name' => $submission['username'],
                    'filename' => $submission['filename'],
                    'error' => 'Processing timeout - please try with fewer submissions',
                    'ai_percentage' => null
                ];
                $errors++;
                break;
            }
            
            $extractedText = extractPDFText($filePath);
            error_log("PDF extraction result for {$submission['filename']}: " . strlen($extractedText ?? 0) . " chars");
            
            if (!$extractedText || !hasEnoughTextForDetection($extractedText)) {
                $errorMsg = 'Could not extract enough text from PDF (extracted: ' . strlen($extractedText ?? '') . ' chars)';
                error_log("PDF extraction failed: $errorMsg");
                
                $results[] = [
                    'submission_id' => $submission['id'],
                    'student_name' => $submission['username'],
                    'filename' => $submission['filename'],
                    'error' => $errorMsg,
                    'ai_percentage' => null
                ];
                $errors++;
                continue;
            }
            
            error_log("Text extracted successfully, preview: " . substr($extractedText, 0, 100) . "...");

            // Perform AI detection using the RoBERTa model
            error_log("Starting AI detection for submission {$submission['id']}");
            $detection = $pythonAIService->checkAI($extractedText);
            error_log("AI detection result: " . json_encode($detection));
            
            if ($detection && isset($detection['ai_percentage']) && is_numeric($detection['ai_percentage'])) {
                $aiPercentage = floatval($detection['ai_percentage']);
                
                // Update database with AI detection result
                $dbUpdateResult = $pythonAIService->updateDatabase($submission['id'], $aiPercentage);
                
                $results[] = [
                    'submission_id' => $submission['id'],
                    'student_name' => $submission['username'],
                    'filename' => $submission['filename'],
                    'error' => null,
                    'ai_percentage' => $aiPercentage,
                    'detection_method' => $detection['method'] ?? 'RoBERTa Large OpenAI Detector',
                    'db_updated' => $dbUpdateResult
                ];
                $processed++;
            } else {
                $errorMessage = 'AI detection failed';
                if (isset($detection['error'])) {
                    $errorMessage = $detection['error'];
                } elseif (isset($detection['status']) && $detection['status'] === 'error') {
                    $errorMessage = 'AI detection status error';
                }
                
                $results[] = [
                    'submission_id' => $submission['id'],
                    'student_name' => $submission['username'],
                    'filename' => $submission['filename'],
                    'error' => $errorMessage,
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
            'errors' => $errors,
            'detection_method' => 'RoBERTa Large OpenAI Detector (openai-community/roberta-large-openai-detector)',
            'python_version' => $envTest['python_version'] ?? 'Unknown'
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?> 