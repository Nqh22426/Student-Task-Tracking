<?php
/**
 * PDF Text Extraction
 * Multi-platform support (Windows, Linux, Mac)
 */

function extractPDFText($pdfPath) {
    // Set a reasonable time limit for PDF extraction
    set_time_limit(60); // 60 seconds per PDF
    
    // Validate input
    if (empty($pdfPath) || !file_exists($pdfPath)) {
        error_log("PDF Extraction Error: File not found - $pdfPath");
        return false;
    }
    
    $fileSize = filesize($pdfPath);
    if ($fileSize === false || $fileSize === 0) {
        error_log("PDF Extraction Error: Invalid file size - $pdfPath");
        return false;
    }
    
    // Skip very large files to avoid timeout
    if ($fileSize > 50 * 1024 * 1024) { // 50MB limit
        error_log("PDF Extraction Error: File too large - $pdfPath (" . round($fileSize/1024/1024, 2) . " MB)");
        return false;
    }
    
    error_log("PDF Extraction: Starting extraction for $pdfPath (" . round($fileSize/1024, 2) . " KB)");
    
    $startTime = microtime(true);
    $extractedText = '';
    
    // Method 1: pdftotext command (Linux/Mac/Windows with poppler-utils)
    $pdfToTextResult = tryPdfToTextCommand($pdfPath);
    if ($pdfToTextResult !== false && strlen(trim($pdfToTextResult)) > 50) {
        $extractedText = $pdfToTextResult;
        $method = 'pdftotext command';
    }
    
    // Method 2: Advanced PHP-based extraction if command-line failed
    if (empty($extractedText)) {
        $phpExtractionResult = tryPhpBasedExtraction($pdfPath);
        if ($phpExtractionResult !== false && strlen(trim($phpExtractionResult)) > 20) {
            $extractedText = $phpExtractionResult;
            $method = 'PHP regex extraction';
        }
    }
    
    // Method 3: Fallback - Try to extract any readable characters
    if (empty($extractedText)) {
        $fallbackResult = tryFallbackExtraction($pdfPath);
        if ($fallbackResult !== false && strlen(trim($fallbackResult)) > 10) {
            $extractedText = $fallbackResult;
            $method = 'Fallback extraction';
        }
    }
    
    $duration = round((microtime(true) - $startTime) * 1000, 2);
    
    if (!empty($extractedText)) {
        $cleanedText = cleanText($extractedText);
        $wordCount = str_word_count($cleanedText);
        
        error_log("PDF Extraction Success: $method - {$wordCount} words extracted in {$duration}ms");
        return $cleanedText;
    } else {
        error_log("PDF Extraction Failed: No text could be extracted from $pdfPath in {$duration}ms");
        return false;
    }
}

/**
 * Try pdftotext command with cross-platform support
 */
function tryPdfToTextCommand($pdfPath) {
    if (!function_exists('exec')) {
        return false;
    }
    
    $commands = [];
    
    // Windows - try common installation paths
    if (stripos(PHP_OS, 'WIN') === 0) {
        $windowsPaths = [
            '"C:\Program Files\poppler\bin\pdftotext.exe"',
            '"C:\Program Files (x86)\poppler\bin\pdftotext.exe"',
            '"C:\poppler\bin\pdftotext.exe"',
            'pdftotext.exe', // If in PATH
            'pdftotext'      // Fallback
        ];
        foreach ($windowsPaths as $path) {
            $commands[] = $path . ' ' . escapeshellarg($pdfPath) . ' -';
        }
    } else {
        // Linux/Mac
        $commands[] = 'pdftotext ' . escapeshellarg($pdfPath) . ' -';
        $commands[] = '/usr/bin/pdftotext ' . escapeshellarg($pdfPath) . ' -';
        $commands[] = '/usr/local/bin/pdftotext ' . escapeshellarg($pdfPath) . ' -';
    }
    
    foreach ($commands as $command) {
        $output = [];
        $returnCode = 0;
        
        // Execute with timeout and error suppression
        exec($command . ' 2>nul', $output, $returnCode);
        
        if ($returnCode === 0 && !empty($output)) {
            $text = implode("\n", $output);
            if (strlen(trim($text)) > 50) {
                return $text;
            }
        }
    }
    
    return false;
}

/**
 * Improved PHP-based PDF text extraction with encoding safety
 */
function tryPhpBasedExtraction($pdfPath) {
    try {
        // Read file in binary mode to avoid encoding issues
        $content = file_get_contents($pdfPath);
        if ($content === false) {
            error_log("PDF Extraction Error: Cannot read file - $pdfPath");
            return false;
        }
        
        // Check valid PDF
        if (substr($content, 0, 4) !== '%PDF') {
            error_log("PDF Extraction Error: Invalid PDF format - $pdfPath");
            return false;
        }
        
        $extractedText = '';
        
        // Method A: Extract from stream objects
        $streamResult = extractFromStreams($content);
        if (!empty($streamResult)) {
            $extractedText .= $streamResult;
        }
        
        // Method B: Extract from text objects (BT...ET blocks)
        $textObjectResult = extractFromTextObjects($content);
        if (!empty($textObjectResult)) {
            $extractedText .= $textObjectResult;
        }
        
        // Method C: Extract from compressed streams
        $compressedResult = extractFromCompressedStreams($content);
        if (!empty($compressedResult)) {
            $extractedText .= $compressedResult;
        }
        
        return !empty($extractedText) ? $extractedText : false;
        
    } catch (Exception $e) {
        error_log("PDF Extraction Exception: " . $e->getMessage() . " - File: $pdfPath");
        return false;
    }
}

/**
 * Extract text from PDF stream objects
 */
function extractFromStreams($content) {
    $text = '';
    
    // Find all stream...endstream blocks
    if (preg_match_all('/stream\s*\n(.*?)\nendstream/s', $content, $streamMatches)) {
        foreach ($streamMatches[1] as $stream) {
            // Try to decode if not compressed
            $decodedText = extractReadableTextAdvanced($stream);
            if (!empty($decodedText)) {
                $text .= $decodedText . ' ';
            }
        }
    }
    
    return $text;
}

/**
 * Extract text from BT...ET (text objects)
 */
function extractFromTextObjects($content) {
    $text = '';
    
    // Find all BT...ET blocks (text objects)
    if (preg_match_all('/BT\s*(.*?)\s*ET/s', $content, $btMatches)) {
        foreach ($btMatches[1] as $btContent) {
            // Extract from Tj operations (simple text)
            if (preg_match_all('/\((.*?)\)\s*Tj/s', $btContent, $tjMatches)) {
                foreach ($tjMatches[1] as $tjText) {
                    $text .= decodePdfText($tjText) . ' ';
                }
            }
            
            // Extract from TJ operations (array of text)
            if (preg_match_all('/\[(.*?)\]\s*TJ/s', $btContent, $tjArrayMatches)) {
                foreach ($tjArrayMatches[1] as $tjArray) {
                    if (preg_match_all('/\((.*?)\)/', $tjArray, $arrayTextMatches)) {
                        foreach ($arrayTextMatches[1] as $arrayText) {
                            $text .= decodePdfText($arrayText) . ' ';
                        }
                    }
                }
            }
        }
    }
    
    return $text;
}

/**
 * Extract from compressed streams with safe decompression
 */
function extractFromCompressedStreams($content) {
    $text = '';
    
    try {
        // Look for FlateDecode streams
        if (preg_match_all('/\/Filter\s*\/FlateDecode.*?stream\s*\n(.*?)\nendstream/s', $content, $flateMatches)) {
            foreach ($flateMatches[1] as $compressedData) {
                // Skip if data is too large
                if (strlen($compressedData) > 5000000) { // 5MB limit
                    continue;
                }
                
                // Try multiple decompression methods safely
                $decompressed = false;
                
                // Method 1: gzuncompress
                if ($decompressed === false) {
                    $decompressed = @gzuncompress($compressedData);
                }
                
                // Method 2: gzinflate
                if ($decompressed === false) {
                    $decompressed = @gzinflate($compressedData);
                }
                
                if ($decompressed !== false && strlen($decompressed) > 0) {
                    // Check if decompressed data looks valid
                    if (strlen($decompressed) < 10000000) { // 10MB limit for decompressed
                        $decodedText = extractReadableTextAdvanced($decompressed);
                        if (!empty($decodedText)) {
                            $text .= $decodedText . ' ';
                        }
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("Compressed stream extraction error: " . $e->getMessage());
    }
    
    return $text;
}

/**
 * Advanced readable text extraction with encoding safety
 */
function extractReadableTextAdvanced($stream) {
    if (empty($stream)) {
        return '';
    }
    
    try {
        $text = '';
        $length = strlen($stream);
        
        // Extract printable characters more safely
        for ($i = 0; $i < $length; $i++) {
            $char = $stream[$i];
            $ascii = ord($char);
            
            // Only include safe ASCII characters
            if ($ascii >= 32 && $ascii <= 126) { // Standard printable ASCII
                $text .= $char;
            } else if ($ascii == 9 || $ascii == 10 || $ascii == 13) { // Tab, LF, CR
                $text .= ' ';
            } else if ($ascii == 0) {
                $text .= ' '; // Replace null with space
            }
        }
        
        if (empty($text)) {
            return '';
        }
        
        // Extract meaningful lines safely
        $lines = preg_split('/[\r\n\t]+/', $text);
    $meaningfulText = '';
    
    foreach ($lines as $line) {
        $line = trim($line);
            // More conservative criteria for meaningful text
            if (strlen($line) > 3 && preg_match('/[a-zA-Z]{2,}/', $line)) {
                // Clean the line safely
                $line = preg_replace('/\s+/', ' ', $line);
                // Only keep standard ASCII printable characters
                $line = preg_replace('/[^\x20-\x7E]/', ' ', $line);
                $line = trim($line);
                
                if (!empty($line) && strlen($line) > 3) {
            $meaningfulText .= $line . ' ';
                }
            }
        }
        
        return $meaningfulText;
        
    } catch (Exception $e) {
        error_log("Text extraction error: " . $e->getMessage());
        return '';
    }
}

/**
 * Decode PDF text strings
 */
function decodePdfText($text) {
    // Handle common PDF escape sequences
    $text = str_replace('\\n', "\n", $text);
    $text = str_replace('\\r', "\r", $text);
    $text = str_replace('\\t', "\t", $text);
    $text = str_replace('\\(', '(', $text);
    $text = str_replace('\\)', ')', $text);
    $text = str_replace('\\\\', '\\', $text);
    
    return $text;
}

/**
 * Fallback extraction
 */
function tryFallbackExtraction($pdfPath) {
    try {
        $content = file_get_contents($pdfPath);
        if ($content === false) {
            return false;
        }
        
        // Limit processing to avoid memory issues
        if (strlen($content) > 20000000) { // 20MB limit
            $content = substr($content, 0, 20000000);
        }
        
        // Very basic extraction - look for any readable text patterns
        $text = '';
        $lines = preg_split('/[\r\n]+/', $content, 10000); // Limit lines
        
        $processedLines = 0;
        foreach ($lines as $line) {
            if ($processedLines++ > 5000) break;
            
            if (strlen($line) > 1000) continue;
            
            // Look for lines that contain mostly printable characters
            $printableChars = 0;
            $totalChars = strlen($line);
            
            if ($totalChars > 5 && $totalChars < 1000) {
                for ($i = 0; $i < $totalChars; $i++) {
                    $ascii = ord($line[$i]);
                    if ($ascii >= 32 && $ascii <= 126) {
                        $printableChars++;
                    }
                }
                
                // If line is mostly printable and contains letters
                $ratio = $totalChars > 0 ? $printableChars / $totalChars : 0;
                if ($ratio > 0.8 && preg_match('/[a-zA-Z]{3,}/', $line)) {
                    // Clean line safely - only keep standard ASCII
                    $cleanLine = preg_replace('/[^\x20-\x7E]/', ' ', $line);
                    $cleanLine = preg_replace('/\s+/', ' ', trim($cleanLine));
                    
                    if (strlen($cleanLine) > 10 && strlen($cleanLine) < 500) {
                        $text .= $cleanLine . ' ';
                        
                        if (strlen($text) > 5000) break;
                    }
                }
            }
        }
        
        return !empty($text) ? $text : false;
        
    } catch (Exception $e) {
        error_log("Fallback extraction error: " . $e->getMessage() . " - File: $pdfPath");
        return false;
    }
}

/**
 * Improved text cleaning
 */
function cleanText($text) {
    if (empty($text)) {
        return '';
    }
    
    // Remove excessive whitespace
    $text = preg_replace('/\s+/', ' ', $text);
    
    // Remove non-printable characters but keep basic punctuation
    $text = preg_replace('/[^\x20-\x7E\xA0-\xFF]/', ' ', $text);
    
    // Clean up punctuation spacing
    $text = preg_replace('/\s*([,.!?;:])\s*/', '$1 ', $text);
    
    // Remove excessive spaces again
    $text = preg_replace('/\s+/', ' ', $text);
    
    return trim($text);
}

/**
 * Check if text is sufficient for AI detection
 */
function hasEnoughTextForDetection($text) {
    if (empty($text)) {
        return false;
    }
    
    $wordCount = str_word_count($text);
    $charCount = strlen(trim($text));
    
    return $wordCount >= 10 && $charCount >= 50;
}

/**
 * Get extraction statistics for debugging
 */
function getExtractionStats($text) {
    if (empty($text)) {
        return [
            'word_count' => 0,
            'char_count' => 0,
            'sufficient' => false
        ];
    }
    
    $wordCount = str_word_count($text);
    $charCount = strlen(trim($text));
    
    return [
        'word_count' => $wordCount,
        'char_count' => $charCount,
        'sufficient' => hasEnoughTextForDetection($text),
        'preview' => substr($text, 0, 200) . (strlen($text) > 200 ? '...' : '')
    ];
}
?> 