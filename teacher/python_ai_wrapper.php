<?php
/**
 * Integrates with python_ai_detector.py using the openai-community/roberta-large-openai-detector model
 */

class PythonAIDetectionService {
    private $pythonPath;
    private $scriptPath;
    private $maxTextLength;
    private $debug;
    
    public function __construct($debug = false) {
        // Initialize paths
        $this->pythonPath = 'python';
        $this->scriptPath = dirname(__DIR__) . '/python_ai_detector.py';
        $this->maxTextLength = 7000; // Limit text length to avoid issues
        $this->debug = $debug;
        
        // Validate script exists
        if (!file_exists($this->scriptPath)) {
            throw new Exception("AI detection script not found at: {$this->scriptPath}");
        }
    }
    
    /**
     * Check if text is AI-generated
     * 
     * @param string $text Text to analyze
     * @return array Result with AI probability and other details
     */
    public function checkAI($text) {
        try {
            // Validate input
            if (empty(trim($text))) {
                return [
                    'ai_probability' => 0.0,
                    'ai_percentage' => 0.0,
                    'error' => 'Empty text provided',
                    'method' => 'RoBERTa Large OpenAI Detector',
                    'status' => 'error'
                ];
            }
            
            // Limit text length to avoid issues with command line
            $cleanText = $this->cleanTextForCommand(substr($text, 0, $this->maxTextLength));
            
            if (strlen($cleanText) < 50) {
                return [
                    'ai_probability' => 0.0,
                    'ai_percentage' => 0.0,
                    'error' => 'Text too short for reliable AI detection',
                    'method' => 'RoBERTa Large OpenAI Detector',
                    'status' => 'error'
                ];
            }
            
            // Create temporary file for text input
            $tempFile = tempnam(sys_get_temp_dir(), 'ai_detect_');
            file_put_contents($tempFile, $cleanText);
            
            // Build command with proper escaping
            $command = escapeshellcmd($this->pythonPath) . ' ' . 
                       escapeshellarg($this->scriptPath) . ' ' . 
                       '--file ' . escapeshellarg($tempFile) . ' ' . 
                       '--json';
            
            // Execute command with stderr redirect to avoid interference
            $output = null;
            $returnCode = null;
            
            if ($this->debug) {
                error_log("Executing AI detection command: $command");
            }
            
            // Execute command
            exec($command, $output, $returnCode);
            
            if ($this->debug) {
                error_log("Python command executed: $command");
                error_log("Python command output (lines: " . count($output) . "): " . implode(" | ", $output));
                error_log("Return code: " . $returnCode);
                error_log("Current working directory: " . getcwd());
                error_log("Script path exists: " . (file_exists($this->scriptPath) ? 'Yes' : 'No'));
            }
            
            // Clean up temp file
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
            
            // Check for execution errors
            if ($returnCode !== 0) {
                if ($this->debug) {
                    error_log("AI detection command failed with code $returnCode: " . implode("\n", $output));
                }
                return [
                    'ai_probability' => 0.0,
                    'ai_percentage' => 0.0,
                    'error' => 'AI detection failed with code ' . $returnCode,
                    'method' => 'RoBERTa Large OpenAI Detector',
                    'status' => 'error',
                    'debug' => $this->debug ? implode("\n", $output) : null
                ];
            }
            
            // Parse JSON output
            $jsonOutput = '';
            
            // Look for JSON object in output (starts with { and ends with })
            foreach ($output as $line) {
                $trimmedLine = trim($line);
                if (strpos($trimmedLine, '{') === 0) {
                    // Found start of JSON, collect all lines until we have complete JSON
                    $jsonLines = [$trimmedLine];
                    $braceCount = substr_count($trimmedLine, '{') - substr_count($trimmedLine, '}');
                    
                    // If this line doesn't close the JSON, continue collecting
                    if ($braceCount > 0) {
                        $currentIndex = array_search($line, $output);
                        for ($i = $currentIndex + 1; $i < count($output); $i++) {
                            $nextLine = trim($output[$i]);
                            $jsonLines[] = $nextLine;
                            $braceCount += substr_count($nextLine, '{') - substr_count($nextLine, '}');
                            if ($braceCount <= 0) break;
                        }
                    }
                    
                    $jsonOutput = implode('', $jsonLines);
                    break;
                }
            }
            
            // If no JSON found, try the last line
            if (empty($jsonOutput) && !empty($output)) {
                $jsonOutput = trim($output[count($output) - 1]);
            }
            
            if ($this->debug) {
                error_log("JSON output extracted: " . $jsonOutput);
            }
            
            $result = json_decode($jsonOutput, true);
            
            if (!$result || !isset($result['ai_percentage'])) {
                if ($this->debug) {
                    error_log("Failed to parse AI detection output. JSON: $jsonOutput");
                    error_log("JSON decode error: " . json_last_error_msg());
                }
                return [
                    'ai_probability' => 0.0,
                    'ai_percentage' => 0.0,
                    'error' => 'Failed to parse AI detection output',
                    'method' => 'RoBERTa Large OpenAI Detector',
                    'status' => 'error',
                    'debug' => $this->debug ? $jsonOutput : null
                ];
            }
            
            // Ensure the result has proper status and format
            $result['status'] = 'success';
            $result['method'] = $result['method'] ?? 'RoBERTa Large OpenAI Detector';
            
            // Ensure ai_percentage is present and numeric
            if (!isset($result['ai_percentage']) || !is_numeric($result['ai_percentage'])) {
                $result['ai_percentage'] = 0.0;
            } else {
                $result['ai_percentage'] = floatval($result['ai_percentage']);
            }
            
            $result['ai_probability'] = $result['ai_percentage'] / 100.0;
            
            return $result;
            
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("AI detection exception: " . $e->getMessage());
            }
            return [
                'ai_probability' => 0.0,
                'ai_percentage' => 0.0,
                'error' => 'AI detection error: ' . $e->getMessage(),
                'method' => 'RoBERTa Large OpenAI Detector',
                'status' => 'error'
            ];
        }
    }
    
    /**
     * Test the Python environment and AI detection
     * 
     * @return array Test results
     */
    public function testEnvironment() {
        try {
            // Check if Python is available
            $command = escapeshellcmd($this->pythonPath) . ' -V';
            $output = null;
            $returnCode = null;
            exec($command, $output, $returnCode);
            
            if ($returnCode !== 0) {
                return [
                    'success' => false,
                    'error' => 'Python not found or not executable',
                    'python_version' => null
                ];
            }
            
            $pythonVersion = isset($output[0]) ? $output[0] : 'Unknown';
            
            // Test AI detection with a simple text - make it longer
            $testText = "This is a comprehensive test of the AI detection system. It should return a valid result with proper percentage values. The system needs to analyze this text and determine whether it was generated by artificial intelligence or written by a human author.";
            $testResult = $this->checkAI($testText);
            
            return [
                'success' => isset($testResult['ai_percentage']),
                'error' => isset($testResult['error']) ? $testResult['error'] : null,
                'python_version' => $pythonVersion,
                'python_path' => $this->pythonPath,
                'script_path' => $this->scriptPath,
                'test_result' => $testResult
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Environment test failed: ' . $e->getMessage(),
                'python_version' => null
            ];
        }
    }
    
    /**
     * Clean text for safe command line usage
     * 
     * @param string $text Text to clean
     * @return string Cleaned text
     */
    private function cleanTextForCommand($text) {
        // Remove non-printable characters
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        
        // Normalize line endings
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        
        return $text;
    }
    
    /**
     * Update database with AI detection result
     * 
     * @param int $submission_id Submission ID
     * @param float $ai_percentage AI percentage
     * @return bool Success status
     */
    public function updateDatabase($submission_id, $ai_percentage) {
        try {
            global $pdo;
            
            if (!$pdo) {
                require_once __DIR__ . '/../config.php';
            }
            
            if (!$pdo) {
                throw new Exception("Database connection not available");
            }
            
            $stmt = $pdo->prepare("UPDATE submissions SET ai_probability = ? WHERE id = ?");
            $result = $stmt->execute([$ai_percentage / 100.0, $submission_id]);
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Database update error: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Backward compatibility function
 */
function createSimplePythonAIDetectionService() {
    return new PythonAIDetectionService();
}
?> 