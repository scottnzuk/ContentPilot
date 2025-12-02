<?php
/**
 * Retracted Content Handler
 *
 * Detects and handles retracted, removed, or problematic content
 * with comprehensive filtering and reporting capabilities.
 *
 * @package ContentPilot
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class CP_RetractedContentHandler {
    
    private $cache_manager;
    private $logger;
    
    // Retraction keywords (expanded list)
    private $retraction_keywords = array(
        // Direct retraction terms
        'retracted', 'retraction', 'withdrawn', 'withdrawal', 'removed', 'removal',
        'cancelled', 'canceled', 'aborted', 'discontinued', 'discontinued',
        
        // Correction terms
        'correction', 'corrected', 'clarification', 'clarified', 'amended', 'amendment',
        'revised', 'revision', 'updated', 'update', 'erratum', 'errata',
        
        // Error acknowledgment
        'error', 'errors', 'mistake', 'mistakes', 'inaccurate', 'incorrect',
        'false', 'misleading', 'mistaken', 'wrong', 'in error',
        
        // Apology/regret language
        'apologize', 'apology', 'sorry', 'regret', 'regrettable',
        'reconsideration', 'review', 'under review', 'being reviewed',
        
        // Legal/policy terms
        'cease', 'cease and desist', 'libel', 'defamation', 'slander',
        'copyright violation', 'plagiarism', 'fabricated', 'fictional',
        
        // Content removal indicators
        'no longer available', 'content removed', 'article removed',
        'page not found', 'deleted', 'archived', 'unavailable',
        
        // Update markers
        'breaking update', 'developing story', 'developing news',
        'correction issued', 'editor note', 'editorial note'
    );
    
    // Source credibility indicators (negative)
    private $questionable_sources = array(
        'fake news', 'satire', 'parody', 'clickbait',
        'tabloid', 'gossip', 'rumor', 'speculation',
        'unconfirmed', 'alleged', 'claimed', 'reports suggest'
    );
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->cache_manager = new CP_Cache_Manager();
        $this->logger = CP_Logger::getInstance();
        
        $this->logger->debug('Retracted content handler initialized');
    }
    
    /**
     * Detect retraction keywords in content
     *
     * @param string $content Content to analyze
     * @return array Detection results
     */
    public function detect_retraction_keywords($content) {
        try {
            $lower_content = strtolower($content);
            $found_keywords = array();
            $keyword_matches = array();
            
            foreach ($this->retraction_keywords as $keyword) {
                if (strpos($lower_content, strtolower($keyword)) !== false) {
                    $found_keywords[] = $keyword;
                    $keyword_matches[] = $this->find_keyword_context($lower_content, $keyword);
                }
            }
            
            // Calculate confidence score
            $confidence = $this->calculate_retraction_confidence($found_keywords, $content);
            
            return array(
                'retracted' => !empty($found_keywords) && $confidence > 0.3,
                'keywords_found' => $found_keywords,
                'keyword_matches' => $keyword_matches,
                'confidence' => $confidence,
                'severity' => $this->assess_severity($found_keywords, $confidence),
                'analysis_date' => current_time('mysql')
            );
            
        } catch (Exception $e) {
            $this->logger->error('Retraction keyword detection failed', array(
                'error' => $e->getMessage()
            ));
            
            return array(
                'retracted' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Check content availability and status
     *
     * @param string $original_url Original article URL
     * @return array Availability analysis
     */
    public function check_content_availability($original_url) {
        try {
            $cache_key = 'content_availability_' . md5($original_url);
            $cached_result = $this->cache_manager->get($cache_key);
            if ($cached_result !== false) {
                return $cached_result;
            }
            
            $result = array(
                'available' => false,
                'status_code' => null,
                'status_message' => null,
                'response_time' => null,
                'content_type' => null,
                'suspicious_redirects' => false,
                'analysis_date' => current_time('mysql')
            );
            
            $start_time = microtime(true);
            
            // Make request with detailed analysis
            $response = wp_remote_get($original_url, array(
                'timeout' => 20,
                'user-agent' => 'CP/' . CP_VERSION,
                'headers' => array(
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.5'
                )
            ));
            
            $end_time = microtime(true);
            $result['response_time'] = round(($end_time - $start_time) * 1000, 2); // ms
            
            if (is_wp_error($response)) {
                $result['status_message'] = 'Request failed: ' . $response->get_error_message();
                return $this->cache_and_return($result, $cache_key, 1800);
            }
            
            $result['status_code'] = wp_remote_retrieve_response_code($response);
            
            // Analyze status code
            switch ($result['status_code']) {
                case 200:
                    $result['available'] = true;
                    $result['status_message'] = 'Content available';
                    break;
                    
                case 404:
                    $result['available'] = false;
                    $result['status_message'] = 'Content not found (404)';
                    break;
                    
                case 410:
                    $result['available'] = false;
                    $result['status_message'] = 'Content permanently removed (410)';
                    break;
                    
                case 403:
                    $result['available'] = false;
                    $result['status_message'] = 'Access forbidden (likely paywalled or restricted)';
                    break;
                    
                case 500:
                case 502:
                case 503:
                case 504:
                    $result['available'] = false;
                    $result['status_message'] = 'Server error (HTTP ' . $result['status_code'] . ')';
                    break;
                    
                default:
                    $result['available'] = false;
                    $result['status_message'] = 'Unknown status: HTTP ' . $result['status_code'];
            }
            
            // Check for redirects or suspicious behavior
            $headers = wp_remote_retrieve_headers($response);
            if (isset($headers['location']) || isset($headers['Location'])) {
                $redirect_location = isset($headers['location']) ? $headers['location'] : $headers['Location'];
                if (!empty($redirect_location) && $redirect_location !== $original_url) {
                    $result['suspicious_redirects'] = true;
                    $result['redirect_location'] = $redirect_location;
                }
            }
            
            // Check content type
            if (isset($headers['content-type'])) {
                $result['content_type'] = $headers['content-type'];
            }
            
            return $this->cache_and_return($result, $cache_key, $result['available'] ? 3600 : 1800);
            
        } catch (Exception $e) {
            return array(
                'available' => false,
                'error' => $e->getMessage(),
                'analysis_date' => current_time('mysql')
            );
        }
    }
    
    /**
     * Handle missing content gracefully
     *
     * @param array $rss_item RSS item data
     * @param array $verification_result Verification results
     * @return array Handling decision
     */
    public function handle_missing_content($rss_item, $verification_result = array()) {
        try {
            $handling_options = array();
            
            // Assess the severity of the issue
            $severity = $this->assess_missing_content_severity($verification_result);
            
            // Determine handling strategy based on severity
            switch ($severity) {
                case 'critical':
                    $handling_options = array(
                        'action' => 'skip',
                        'reason' => 'Critical content issues detected',
                        'notification' => true,
                        'log_level' => 'error'
                    );
                    break;
                    
                case 'high':
                    $handling_options = array(
                        'action' => 'flag',
                        'reason' => 'Content has significant issues',
                        'notification' => true,
                        'log_level' => 'warning',
                        'add_warning_to_post' => true
                    );
                    break;
                    
                case 'medium':
                    $handling_options = array(
                        'action' => 'flag',
                        'reason' => 'Minor content issues detected',
                        'notification' => false,
                        'log_level' => 'info',
                        'add_warning_to_post' => true
                    );
                    break;
                    
                case 'low':
                    $handling_options = array(
                        'action' => 'proceed',
                        'reason' => 'Minor issues, content usable',
                        'notification' => false,
                        'log_level' => 'info'
                    );
                    break;
                    
                default:
                    $handling_options = array(
                        'action' => 'flag',
                        'reason' => 'Unknown issue type',
                        'notification' => true,
                        'log_level' => 'warning'
                    );
            }
            
            // Add content analysis to handling options
            $handling_options['analysis'] = array(
                'rss_item_title' => isset($rss_item['title']) ? $rss_item['title'] : 'Unknown',
                'source_domain' => isset($rss_item['source_domain']) ? $rss_item['source_domain'] : 'Unknown',
                'verification_result' => $verification_result,
                'analysis_date' => current_time('mysql')
            );
            
            return $handling_options;
            
        } catch (Exception $e) {
            return array(
                'action' => 'skip',
                'reason' => 'Analysis failed: ' . $e->getMessage(),
                'error' => $e->getMessage(),
                'log_level' => 'error'
            );
        }
    }
    
    /**
     * Flag problematic content for review
     *
     * @param string $content Article content
     * @param array $source Source information
     * @param array $analysis Analysis results
     * @return array Flagging result
     */
    public function flag_problematic_content($content, $source, $analysis = array()) {
        try {
            $flag_id = 'content_flag_' . uniqid();
            
            $flag_record = array(
                'flag_id' => $flag_id,
                'flagged_at' => current_time('mysql'),
                'source_url' => isset($source['url']) ? $source['url'] : '',
                'source_domain' => isset($source['domain']) ? $source['domain'] : '',
                'content_title' => isset($source['title']) ? $source['title'] : '',
                'issue_type' => $this->classify_content_issue($analysis),
                'severity' => $this->determine_severity_level($analysis),
                'analysis_data' => $analysis,
                'status' => 'pending_review',
                'auto_resolved' => false
            );
            
            // Store flag in cache/database for tracking
            $this->store_content_flag($flag_record);
            
            // Log the flagging
            $this->logger->warning('Problematic content flagged for review', array(
                'flag_id' => $flag_id,
                'source_domain' => $flag_record['source_domain'],
                'issue_type' => $flag_record['issue_type'],
                'severity' => $flag_record['severity']
            ));
            
            // Send notification if severe
            if ($flag_record['severity'] === 'high' || $flag_record['severity'] === 'critical') {
                $this->send_problematic_content_notification($flag_record);
            }
            
            return array(
                'flagged' => true,
                'flag_id' => $flag_id,
                'message' => 'Content flagged for review',
                'review_required' => $flag_record['severity'] !== 'low'
            );
            
        } catch (Exception $e) {
            $this->logger->error('Failed to flag problematic content', array(
                'error' => $e->getMessage()
            ));
            
            return array(
                'flagged' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Find keyword context in content
     *
     * @param string $content Lowercased content
     * @param string $keyword Keyword to find
     * @return array Context information
     */
    private function find_keyword_context($content, $keyword) {
        $position = strpos($content, strtolower($keyword));
        if ($position === false) {
            return array();
        }
        
        $context_start = max(0, $position - 50);
        $context_end = min(strlen($content), $position + strlen($keyword) + 50);
        $context = substr($content, $context_start, $context_end - $context_start);
        
        return array(
            'keyword' => $keyword,
            'position' => $position,
            'context' => $context,
            'sentence_start' => $this->find_sentence_start($content, $position),
            'sentence_end' => $this->find_sentence_end($content, $position + strlen($keyword))
        );
    }
    
    /**
     * Calculate retraction confidence score
     *
     * @param array $found_keywords Found keywords
     * @param string $content Original content
     * @return float Confidence score (0-1)
     */
    private function calculate_retraction_confidence($found_keywords, $content) {
        if (empty($found_keywords)) {
            return 0.0;
        }
        
        $confidence = 0.0;
        $content_length = strlen($content);
        
        // Base confidence per keyword
        foreach ($found_keywords as $keyword) {
            $keyword_confidence = 0.1;
            
            // High-confidence keywords
            if (in_array($keyword, array('retracted', 'retraction', 'withdrawn', 'correction', 'erratum'))) {
                $keyword_confidence = 0.4;
            }
            // Medium-confidence keywords
            elseif (in_array($keyword, array('amended', 'revised', 'clarification', 'apology'))) {
                $keyword_confidence = 0.25;
            }
            // Lower-confidence keywords
            else {
                $keyword_confidence = 0.15;
            }
            
            $confidence += $keyword_confidence;
        }
        
        // Adjust based on content length (longer content with keywords = higher confidence)
        if ($content_length > 1000) {
            $confidence *= 1.2;
        } elseif ($content_length < 100) {
            $confidence *= 0.8;
        }
        
        // Cap at 1.0
        return min(1.0, $confidence);
    }
    
    /**
     * Assess severity of retraction
     *
     * @param array $found_keywords Found keywords
     * @param float $confidence Confidence score
     * @return string Severity level
     */
    private function assess_severity($found_keywords, $confidence) {
        if (empty($found_keywords)) {
            return 'none';
        }
        
        // High severity keywords
        $high_severity = array('retracted', 'retraction', 'withdrawn', 'fabricated', 'libel');
        $found_high_severity = array_intersect($found_keywords, $high_severity);
        
        if (!empty($found_high_severity) && $confidence > 0.5) {
            return 'critical';
        }
        
        if (count($found_keywords) >= 3 || $confidence > 0.7) {
            return 'high';
        }
        
        if (count($found_keywords) >= 2 || $confidence > 0.4) {
            return 'medium';
        }
        
        if (!empty($found_keywords) || $confidence > 0.2) {
            return 'low';
        }
        
        return 'none';
    }
    
    /**
     * Find sentence start
     *
     * @param string $content Content
     * @param int $position Position in content
     * @return int Sentence start position
     */
    private function find_sentence_start($content, $position) {
        $sentence_endings = array('.', '!', '?', ';', ':');
        
        for ($i = $position; $i >= 0; $i--) {
            if (in_array($content[$i], $sentence_endings)) {
                return $i + 1;
            }
        }
        
        return 0;
    }
    
    /**
     * Find sentence end
     *
     * @param string $content Content
     * @param int $position Position in content
     * @return int Sentence end position
     */
    private function find_sentence_end($content, $position) {
        $sentence_endings = array('.', '!', '?', ';', ':');
        $length = strlen($content);
        
        for ($i = $position; $i < $length; $i++) {
            if (in_array($content[$i], $sentence_endings)) {
                return $i + 1;
            }
        }
        
        return $length;
    }
    
    /**
     * Assess missing content severity
     *
     * @param array $verification_result Verification results
     * @return string Severity level
     */
    private function assess_missing_content_severity($verification_result) {
        if (empty($verification_result)) {
            return 'medium';
        }
        
        // Critical issues
        if (isset($verification_result['status_code'])) {
            if (in_array($verification_result['status_code'], array(404, 410))) {
                return 'high';
            }
            
            if (isset($verification_result['retracted']) && $verification_result['retracted']) {
                if (isset($verification_result['severity']) && 
                    in_array($verification_result['severity'], array('critical', 'high'))) {
                    return 'critical';
                }
                return 'high';
            }
        }
        
        // Medium severity for accessibility issues
        if (isset($verification_result['accessible']) && !$verification_result['accessible']) {
            return 'medium';
        }
        
        // Low severity for minor issues
        return 'low';
    }
    
    /**
     * Classify content issue type
     *
     * @param array $analysis Analysis results
     * @return string Issue classification
     */
    private function classify_content_issue($analysis) {
        if (isset($analysis['retracted']) && $analysis['retracted']) {
            return 'retracted_content';
        }
        
        if (isset($analysis['status_code']) && in_array($analysis['status_code'], array(404, 410))) {
            return 'content_removed';
        }
        
        if (isset($analysis['status_code']) && $analysis['status_code'] === 403) {
            return 'access_restricted';
        }
        
        if (isset($analysis['accessible']) && !$analysis['accessible']) {
            return 'content_unavailable';
        }
        
        return 'general_issue';
    }
    
    /**
     * Determine severity level
     *
     * @param array $analysis Analysis results
     * @return string Severity level
     */
    private function determine_severity_level($analysis) {
        if (isset($analysis['severity'])) {
            return $analysis['severity'];
        }
        
        if (isset($analysis['confidence']) && $analysis['confidence'] > 0.7) {
            return 'high';
        }
        
        if (isset($analysis['confidence']) && $analysis['confidence'] > 0.4) {
            return 'medium';
        }
        
        return 'low';
    }
    
    /**
     * Store content flag for tracking
     *
     * @param array $flag_record Flag record
     */
    private function store_content_flag($flag_record) {
        $cache_key = 'content_flags';
        $existing_flags = $this->cache_manager->get($cache_key);
        
        if (!$existing_flags) {
            $existing_flags = array();
        }
        
        $existing_flags[] = $flag_record;
        
        // Keep only last 100 flags
        if (count($existing_flags) > 100) {
            $existing_flags = array_slice($existing_flags, -100);
        }
        
        $this->cache_manager->set($cache_key, $existing_flags, 86400); // 24 hours
    }
    
    /**
     * Send notification about problematic content
     *
     * @param array $flag_record Flag record
     */
    private function send_problematic_content_notification($flag_record) {
        try {
            // Get admin email
            $admin_email = get_option('admin_email');
            if (!$admin_email) {
                return;
            }
            
            $subject = '[CP] Problematic Content Detected - ' . $flag_record['issue_type'];
            $message = "A problematic content has been detected and flagged for review:\n\n";
            $message .= "Flag ID: " . $flag_record['flag_id'] . "\n";
            $message .= "Issue Type: " . $flag_record['issue_type'] . "\n";
            $message .= "Severity: " . $flag_record['severity'] . "\n";
            $message .= "Source Domain: " . $flag_record['source_domain'] . "\n";
            $message .= "Content Title: " . $flag_record['content_title'] . "\n";
            $message .= "Flagged At: " . $flag_record['flagged_at'] . "\n";
            $message .= "\nPlease review this content in the admin dashboard.";
            
            wp_mail($admin_email, $subject, $message);
            
        } catch (Exception $e) {
            $this->logger->error('Failed to send problematic content notification', array(
                'error' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Cache and return result
     *
     * @param array $result Result to cache
     * @param string $cache_key Cache key
     * @param int $ttl Time to live in seconds
     * @return array Cached result
     */
    private function cache_and_return($result, $cache_key, $ttl = 1800) {
        if ($this->cache_manager instanceof CP_Cache_Manager) {
            $this->cache_manager->set($cache_key, $result, $ttl);
        } elseif ($this->cache_manager instanceof AANP_Cache_Manager) {
            $this->cache_manager->set($cache_key, $result, $ttl);
        }
        return $result;
    }
}