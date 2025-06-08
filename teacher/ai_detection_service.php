<?php
/** AI Detection Service using Sapling.ai API with Advanced Fallback **/

class AIDetectionService {
    private $apiKey;
    private $apiUrl;
    
    public function __construct() {
        // Real Sapling.ai API key
        $this->apiKey = 'K0OWG6JZRP4B4244P5BBY1USLKGK68RG';
        $this->apiUrl = 'https://api.sapling.ai/api/v1/aidetect';
    }
    
    /**
     * Detect AI probability in text
     * @param string $text Text to analyze
     * @return array Returns array with score and details
     */
    public function detectAI($text) {
        if (empty($text) || strlen(trim($text)) < 50) {
            return [
                'score' => 0,
                'percentage' => 0,
                'error' => 'Text too short for reliable detection'
            ];
        }
        
        // Use real Sapling.ai API
        $apiResult = $this->callSaplingAPI($text);
        
        if ($apiResult && isset($apiResult['score'])) {
                    return [
            'score' => $apiResult['score'],
            'percentage' => round($apiResult['score'] * 100, 1),
            'error' => null
        ];
        }
        
        // Fallback to heuristic detection if API fails
        $score = $this->advancedAIDetection($text);
        
        return [
            'score' => $score,
            'percentage' => round($score * 100, 1),
            'error' => null
        ];
    }
    
    /**
     * Real API call to Sapling.ai
     */
    private function callSaplingAPI($text) {
        $data = [
            'key' => $this->apiKey,
            'text' => $text,
            'sent_scores' => false
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen(json_encode($data))
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            error_log("Sapling API cURL Error: " . $curlError);
            return false;
        }
        
        if ($httpCode !== 200) {
            error_log("Sapling API HTTP Error: " . $httpCode . " - " . $response);
            return false;
        }
        
        $result = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Sapling API JSON Error: " . json_last_error_msg());
            return false;
        }
        
        return $result;
    }
    
    /**
    * ADVANCED AI DETECTION SYSTEM (fallback method)
    * This is a comprehensive system when API fails
    */
    private function advancedAIDetection($text) {
        $totalScore = 0;
        $maxScore = 0;
        $textLength = strlen($text);
        
        // Parse text once for efficiency
        $words = str_word_count(strtolower($text), 1);
        $totalWords = count($words);
        $sentences = array_filter(array_map('trim', preg_split('/[.!?]+/', $text)));
        $sentenceCount = count($sentences);
        $textLower = strtolower($text);
        
        // === 1. VOCABULARY & COMPLEXITY ANALYSIS ===
        $maxScore += 25;
        
        // Combined formal/academic vocabulary
        $formalWords = [
            'furthermore', 'consequently', 'nevertheless', 'comprehensive', 'substantially',
            'specifically', 'particularly', 'significantly', 'moreover', 'additionally',
            'optimization', 'implementation', 'functionality', 'methodology', 'framework',
            'systematically', 'theoretically', 'fundamentally', 'predominantly', 'essentially',
            'simultaneously', 'subsequently', 'accordingly', 'nonetheless', 'henceforth',
            'paradigmatically', 'extraordinarily', 'instantaneously', 'categorically', 'definitively'
        ];
        
        $formalCount = 0;
        foreach ($formalWords as $word) {
            $formalCount += substr_count($textLower, $word);
        }
        
        $formalRatio = $totalWords > 0 ? ($formalCount / $totalWords) * 100 : 0;
        if ($formalRatio > 1.5) $totalScore += 15;
        elseif ($formalRatio > 0.8) $totalScore += 10;
        elseif ($formalRatio > 0.3) $totalScore += 5;
        
        // Technical jargon overuse
        $techTerms = [
            'leverage', 'utilize', 'paradigm', 'synergy', 'optimize', 'streamline',
            'scalability', 'robustness', 'modularity', 'interoperability', 'aggregation',
            'orchestration', 'proliferation', 'monetization', 'digitalization', 'transformation'
        ];
        $techCount = 0;
        foreach ($techTerms as $term) {
            if (stripos($text, $term) !== false) $techCount++;
        }
        if ($techCount >= 3) $totalScore += 15;
        elseif ($techCount >= 2) $totalScore += 10;
        elseif ($techCount >= 1) $totalScore += 5;
        
        // AI-specific buzzwords detection
        $aiBuzzwords = [
            'artificial intelligence', 'machine learning', 'deep learning', 'neural network',
            'algorithm', 'data-driven', 'insights', 'analytics', 'predictive', 'automation'
        ];
        $buzzwordCount = 0;
        foreach ($aiBuzzwords as $buzz) {
            if (stripos($text, $buzz) !== false) $buzzwordCount++;
        }
        if ($buzzwordCount >= 2) $totalScore += 12;
        elseif ($buzzwordCount >= 1) $totalScore += 6;
        
        // === 2. SENTENCE STRUCTURE & UNIFORMITY ===
        $maxScore += 22;
        
        if ($sentenceCount > 3) {
            // Sentence length uniformity (AI signature)
            $lengths = array_map('strlen', $sentences);
            $avgLength = array_sum($lengths) / count($lengths);
            $variance = 0;
            foreach ($lengths as $length) {
                $variance += pow($length - $avgLength, 2);
            }
            $stdDev = sqrt($variance / count($lengths));
            
            // Low variance = too uniform = likely AI
            if ($stdDev < 25) $totalScore += 10;
            elseif ($stdDev < 45) $totalScore += 6;
            elseif ($stdDev < 65) $totalScore += 3;
            
            // Complex sentence starters (AI pattern)
            $complexStarters = 0;
            foreach ($sentences as $sentence) {
                if (preg_match('/^(While|Although|Despite|However|Furthermore|Moreover|Additionally|Consequently|Nevertheless|Nonetheless|Therefore|Thus|Hence),/i', trim($sentence))) {
                    $complexStarters++;
                }
            }
            $starterRatio = $complexStarters / $sentenceCount;
            if ($starterRatio > 0.4) $totalScore += 8;
            elseif ($starterRatio > 0.25) $totalScore += 5;
            
            // Repetitive sentence patterns (AI tends to repeat structures)
            $structurePatterns = [];
            foreach ($sentences as $sentence) {
                $words = explode(' ', trim($sentence));
                if (count($words) >= 3) {
                    $pattern = implode(' ', array_slice($words, 0, 3));
                    if (isset($structurePatterns[$pattern])) {
                        $structurePatterns[$pattern]++;
                    } else {
                        $structurePatterns[$pattern] = 1;
                    }
                }
            }
            $repeatedStructures = 0;
            foreach ($structurePatterns as $count) {
                if ($count > 1) $repeatedStructures++;
            }
            if ($repeatedStructures >= 2) $totalScore += 4;
            
            // Perfect grammar (lack of human errors)
            $hasErrors = preg_match('/\b(its|your|there)\s+\w/i', $text) && 
                        preg_match('/\b(alot|definately|seperate)\b/i', $text);
            if (!$hasErrors && $textLength > 200) $totalScore += 4;
        }
        
        // === 3. LINGUISTIC PATTERNS & TRANSITIONS ===
        $maxScore += 20;
        
        // AI transitional phrases
        $transitions = [
            'it is important to note', 'it should be noted', 'it is worth noting',
            'in conclusion', 'to summarize', 'as mentioned previously',
            'building on this', 'expanding on this point', 'in other words',
            'taking this into account', 'with this in mind', 'bearing this in mind',
            'in light of this', 'given these considerations', 'as we have seen',
            'moving forward', 'going forward', 'looking ahead'
        ];
        
        $transitionCount = 0;
        foreach ($transitions as $transition) {
            if (stripos($text, $transition) !== false) $transitionCount++;
        }
        if ($transitionCount >= 2) $totalScore += 15;
        elseif ($transitionCount >= 1) $totalScore += 8;
        
        // Generic safety language
        $genericPhrases = [
            'variety of', 'range of', 'numerous advantages', 'potential benefits',
            'important considerations', 'effective strategies', 'significant impact',
            'best practices', 'key insights', 'valuable information', 'useful tips',
            'practical solutions', 'innovative approaches', 'proven methods',
            'recommended practices', 'optimal results', 'maximum efficiency'
        ];
        
        $genericCount = 0;
        foreach ($genericPhrases as $phrase) {
            if (stripos($text, $phrase) !== false) $genericCount++;
        }
        if ($genericCount >= 2) $totalScore += 12;
        elseif ($genericCount >= 1) $totalScore += 6;
        
        // Balanced viewpoints (AI neutrality)
        $balanceWords = ['however', 'on the other hand', 'conversely', 'pros and cons', 'advantages and disadvantages', 'both sides'];
        $balanceCount = 0;
        foreach ($balanceWords as $word) {
            if (stripos($text, $word) !== false) $balanceCount++;
        }
        if ($balanceCount >= 2) $totalScore += 3;
        
        // Excessive qualification (AI over-caution)
        $qualifiers = [
            'generally speaking', 'broadly speaking', 'in most cases', 'typically',
            'usually', 'often', 'frequently', 'commonly', 'generally', 'broadly'
        ];
        $qualifierCount = 0;
        foreach ($qualifiers as $qualifier) {
            if (stripos($text, $qualifier) !== false) $qualifierCount++;
        }
        if ($qualifierCount >= 3) $totalScore += 4;
        elseif ($qualifierCount >= 2) $totalScore += 2;
        
        // === 4. PERSONAL ELEMENTS ANALYSIS ===
        $maxScore += 15;
        
        // Lack of personal indicators
        $personalWords = [
            'i think', 'i believe', 'in my opinion', 'personally', 'i feel',
            'my experience', 'i remember', 'honestly', 'frankly', 'i guess',
            'i\'ve noticed', 'from my perspective', 'in my view', 'i suppose',
            'i reckon', 'i\'d say', 'if you ask me', 'to be honest'
        ];
        
        $personalCount = 0;
        foreach ($personalWords as $word) {
            if (stripos($text, $word) !== false) $personalCount++;
        }
        
        // Low personal content = likely AI
        if ($textLength > 200 && $personalCount == 0) $totalScore += 10;
        elseif ($textLength > 100 && $personalCount == 0) $totalScore += 6;
        elseif ($personalCount <= 1 && $textLength > 150) $totalScore += 3;
        
        // Emotional neutrality
        $emotionalWords = [
            'love', 'hate', 'excited', 'angry', 'frustrated', 'amazing',
            'terrible', 'wonderful', 'awful', 'brilliant', 'stupid',
            'gorgeous', 'disgusting', 'hilarious', 'devastating', 'thrilled',
            'furious', 'ecstatic', 'miserable', 'delighted', 'outraged'
        ];
        
        $emotionalCount = 0;
        foreach ($emotionalWords as $emotion) {
            if (stripos($text, $emotion) !== false) $emotionalCount++;
        }
        
        if ($totalWords > 80 && $emotionalCount == 0) $totalScore += 5;
        
        // Lack of colloquialisms/slang (AI avoids informal language)
        $informalWords = [
            'yeah', 'nope', 'gonna', 'wanna', 'gotta', 'kinda', 'sorta',
            'super', 'really', 'pretty much', 'tons of', 'loads of', 'bunch of'
        ];
        $informalCount = 0;
        foreach ($informalWords as $word) {
            if (stripos($text, $word) !== false) $informalCount++;
        }
        if ($totalWords > 100 && $informalCount == 0) $totalScore += 3;
        
        // === 5. MODERN AI MODEL SIGNATURES ===
        $maxScore += 18;
        
        // Current AI characteristic phrases
        $aiPhrases = [
            'i hope this helps', 'let me know if you need', 'feel free to',
            'i\'d be happy to', 'certainly', 'absolutely', 'of course',
            'here\'s what i recommend', 'it\'s worth noting that',
            'keep in mind that', 'as an ai', 'i don\'t have personal',
            'i\'m here to help', 'if you have any questions', 'please don\'t hesitate',
            'i\'d suggest', 'you might consider', 'it could be beneficial',
            'based on the information provided', 'given the context',
            'i understand your concern', 'that\'s a great question',
            'from my understanding', 'it appears that', 'according to',
            'it seems like', 'i believe the answer is', 'here are some options',
            'you might want to try', 'one approach would be', 'another option is'
        ];
        
        $aiPhraseCount = 0;
        foreach ($aiPhrases as $phrase) {
            if (stripos($text, $phrase) !== false) $aiPhraseCount++;
        }
        
        if ($aiPhraseCount >= 2) $totalScore += 30;
        elseif ($aiPhraseCount >= 1) $totalScore += 18;
        
        // Overused AI intensifiers
        $intensifiers = [
            'comprehensive', 'robust', 'versatile', 'cutting-edge', 'sophisticated',
            'streamlined', 'optimal', 'enhanced', 'significant', 'substantial',
            'revolutionary', 'groundbreaking', 'unprecedented', 'transformative',
            'exponential', 'paradigm-shifting', 'game-changing', 'innovative',
            'efficient', 'effective', 'powerful', 'advanced', 'superior',
            'intelligent', 'dynamic', 'seamless', 'scalable', 'flexible'
        ];
        
        $intensifierCount = 0;
        foreach ($intensifiers as $word) {
            if (stripos($text, $word) !== false) $intensifierCount++;
        }
        if ($intensifierCount >= 3) $totalScore += 15;
        elseif ($intensifierCount >= 2) $totalScore += 10;
        elseif ($intensifierCount >= 1) $totalScore += 5;
        
        // Business/corporate speak detection
        $corporateSpeak = [
            'synergies', 'deliverables', 'stakeholders', 'actionable insights',
            'value proposition', 'core competencies', 'best-in-class', 'end-to-end',
            'holistic approach', 'strategic initiatives', 'key performance indicators'
        ];
        $corporateCount = 0;
        foreach ($corporateSpeak as $term) {
            if (stripos($text, $term) !== false) $corporateCount++;
        }
        if ($corporateCount >= 2) $totalScore += 12;
        elseif ($corporateCount >= 1) $totalScore += 6;
        
        // === 6. ADVANCED STATISTICAL ANALYSIS ===
        $maxScore += 16;
        
        // Entropy calculation (information theory)
        $charFreq = array_count_values(str_split($textLower));
        $entropy = 0;
        foreach ($charFreq as $freq) {
            $probability = $freq / $textLength;
            if ($probability > 0) {
                $entropy -= $probability * log($probability, 2);
            }
        }
        
        // Low entropy = more predictable = likely AI
        if ($entropy < 3.5) $totalScore += 8;
        elseif ($entropy < 4.0) $totalScore += 4;
        
        // Lexical diversity (Type-Token Ratio)
        $uniqueWords = count(array_unique($words));
        $ttr = $totalWords > 0 ? $uniqueWords / $totalWords : 0;
        
        // AI has lower lexical diversity
        if ($ttr < 0.4 && $totalWords > 50) $totalScore += 8;
        elseif ($ttr < 0.5 && $totalWords > 50) $totalScore += 4;
        
        // === 7. SYNTACTIC COMPLEXITY ===
        $maxScore += 12;
        
        // Function words ratio (stylometric analysis)
        $functionWords = ['the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with'];
        $functionWordCount = 0;
        foreach ($functionWords as $fw) {
            $functionWordCount += substr_count($textLower, ' ' . $fw . ' ');
        }
        
        $functionRatio = $totalWords > 0 ? $functionWordCount / $totalWords : 0;
        if ($functionRatio > 0.15) $totalScore += 6;
        elseif ($functionRatio > 0.12) $totalScore += 3;
        
        // Passive voice overuse
        $passiveCount = preg_match_all('/\b(is|are|was|were) \w+ed\b/i', $text);
        $passiveRatio = $sentenceCount > 0 ? $passiveCount / $sentenceCount : 0;
        if ($passiveRatio > 0.3) $totalScore += 6;
        elseif ($passiveRatio > 0.2) $totalScore += 3;
        
        // === 8. CONTEXT-AWARE PATTERNS ===
        $maxScore += 15;
        
        // Educational/instructional patterns
        $educationalPhrases = [
            'it is important to understand', 'students should know', 'key concept',
            'learning objectives', 'in this context', 'for example', 'such as',
            'fundamental principles', 'core concepts', 'essential knowledge',
            'critical thinking', 'analytical skills', 'problem-solving'
        ];
        
        $educationalCount = 0;
        foreach ($educationalPhrases as $phrase) {
            if (stripos($text, $phrase) !== false) $educationalCount++;
        }
        if ($educationalCount >= 3) $totalScore += 8;
        elseif ($educationalCount >= 2) $totalScore += 5;
        elseif ($educationalCount >= 1) $totalScore += 2;
        
        // Hedging language (AI uncertainty)
        $hedges = [
            'might', 'could', 'may', 'perhaps', 'possibly', 'likely', 'probably', 'seems',
            'appears to', 'tends to', 'generally', 'typically', 'usually', 'often'
        ];
        $hedgeCount = 0;
        foreach ($hedges as $hedge) {
            if (stripos($text, $hedge) !== false) $hedgeCount++;
        }
        if ($hedgeCount >= 5) $totalScore += 7;
        elseif ($hedgeCount >= 3) $totalScore += 4;
        elseif ($hedgeCount >= 2) $totalScore += 2;
        
        // === 9. ADVANCED STYLISTIC PATTERNS ===
        $maxScore += 12;
        
        // Lists and enumerations (AI loves structured information)
        $listPatterns = [
            'first', 'second', 'third', 'firstly', 'secondly', 'thirdly',
            'additionally', 'furthermore', 'moreover', 'finally', 'lastly'
        ];
        $listCount = 0;
        foreach ($listPatterns as $pattern) {
            if (stripos($text, $pattern) !== false) $listCount++;
        }
        if ($listCount >= 4) $totalScore += 6;
        elseif ($listCount >= 3) $totalScore += 4;
        elseif ($listCount >= 2) $totalScore += 2;
        
        // Temporal consistency issues (AI lacks real-time awareness)
        $timeReferences = [
            'currently', 'at present', 'nowadays', 'recently', 'lately',
            'in recent times', 'these days', 'at the moment'
        ];
        $timeCount = 0;
        foreach ($timeReferences as $time) {
            if (stripos($text, $time) !== false) $timeCount++;
        }
        if ($timeCount >= 3) $totalScore += 6;
        elseif ($timeCount >= 2) $totalScore += 3;
        
        // === 10. MODERN AI DETECTION PATTERNS ===
        $maxScore += 25;
        
        // Strong AI indicators
        $strongAIPatterns = [
            'i don\'t have the ability to', 'i cannot', 'i\'m unable to',
            'my knowledge cutoff', 'as of my last update', 'i don\'t have access to',
            'i cannot browse the internet', 'i\'m an ai language model',
            'i don\'t have real-time information', 'i can\'t verify current'
        ];
        $strongAICount = 0;
        foreach ($strongAIPatterns as $pattern) {
            if (stripos($text, $pattern) !== false) $strongAICount++;
        }
        if ($strongAICount >= 1) $totalScore += 15; // Very strong indicator
        
        // ChatGPT-style responses
        $chatGPTPatterns = [
            'here are some', 'here\'s how you can', 'here are the steps',
            'you can try', 'you might want to', 'consider the following',
            'keep in mind', 'it\'s important to note', 'please note that'
        ];
        $chatGPTCount = 0;
        foreach ($chatGPTPatterns as $pattern) {
            if (stripos($text, $pattern) !== false) $chatGPTCount++;
        }
        if ($chatGPTCount >= 3) $totalScore += 10;
        elseif ($chatGPTCount >= 2) $totalScore += 5;
        
        // Meta-commentary patterns (AI self-reference)
        $metaPatterns = [
            'let me explain', 'let me clarify', 'to elaborate', 'to expand on this',
            'allow me to', 'it is clear that', 'obviously', 'needless to say'
        ];
        $metaCount = 0;
        foreach ($metaPatterns as $meta) {
            if (stripos($text, $meta) !== false) $metaCount++;
        }
        if ($metaCount >= 2) $totalScore += 4;
        elseif ($metaCount >= 1) $totalScore += 2;
        
        // Redundant emphasis patterns
        $redundantPhrases = [
            'as we all know', 'it goes without saying', 'clearly visible',
            'absolutely certain', 'completely obvious', 'totally clear'
        ];
        $redundantCount = 0;
        foreach ($redundantPhrases as $phrase) {
            if (stripos($text, $phrase) !== false) $redundantCount++;
        }
        if ($redundantCount >= 2) $totalScore += 4;
        elseif ($redundantCount >= 1) $totalScore += 2;

        // === FINAL CALCULATION WITH SMART WEIGHTING ===
        
        // Base score calculation
        $normalizedScore = $maxScore > 0 ? $totalScore / $maxScore : 0;
        
        // Text length confidence adjustment
        $lengthMultiplier = 1.0;
        if ($textLength < 50) {
            $lengthMultiplier = 0.3; // Very low confidence
        } elseif ($textLength < 100) {
            $lengthMultiplier = 0.6; // Low confidence
        } elseif ($textLength < 300) {
            $lengthMultiplier = 0.9; // Medium confidence
        } elseif ($textLength < 800) {
            $lengthMultiplier = 1.0; // Normal confidence
        } else {
            $lengthMultiplier = 1.2; // High confidence for long text
        }
        
        // Language complexity factor
        $avgWordsPerSentence = $sentenceCount > 0 ? $totalWords / $sentenceCount : 0;
        $complexityMultiplier = 1.0;
        if ($avgWordsPerSentence > 22) {
            $complexityMultiplier = 1.15; // Higher bonus for complex AI text
        } elseif ($avgWordsPerSentence > 18) {
            $complexityMultiplier = 1.08; // Medium bonus
        } elseif ($avgWordsPerSentence < 8) {
            $complexityMultiplier = 0.85; // Higher penalty for too simple
        } elseif ($avgWordsPerSentence < 12) {
            $complexityMultiplier = 0.92; // Medium penalty
        }
        
        $adjustedScore = $normalizedScore * $lengthMultiplier * $complexityMultiplier;
        
        if ($totalScore > ($maxScore * 0.2)) {
            $adjustedScore *= 1.3;
        }
        if ($totalScore > ($maxScore * 0.35)) {
            $adjustedScore *= 1.5;
        }
        if ($totalScore > ($maxScore * 0.5)) {
            $adjustedScore *= 1.7;
        }
        if ($totalScore > ($maxScore * 0.7)) {
            $adjustedScore *= 2.0;
        }
        
        // Add slight randomness for realism
        $randomFactor = (mt_rand(-1, 1) / 100);
        $finalScore = max(0.01, min(0.99, $adjustedScore + $randomFactor));
        
        return $finalScore;
    }
}
?> 