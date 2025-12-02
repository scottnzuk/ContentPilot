<?php
/**
 * Content Verification Engine
 *
 * Handles source validation, content legitimacy checks, retraction detection,
 * and comprehensive verification of RSS feeds and original articles.
 *
 * @package AI_Auto_News_Poster
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class CP_ContentVerifier {
    
    private $cache_manager;
    private $logger;
    private $verified_sources_table;
    private $content_verification_table;
    
    // Red flag keywords for retraction/misinformation detection
    private $retraction_keywords = array(
        'retracted', 'correction', 'withdrawn', 'error', 'mistake',
        'incorrect', 'update', 'clarification', 'not accurate',
        'false', 'misleading', 'apologize', 'reconsideration',
        'revised', 'amended', 'corrected', 'debunked', 'disputed'
    );
    
    // Known reliable news domains with credibility scores
    private $trusted_sources = array(
        'bbc.co.uk' => 95,
        'reuters.com' => 90,
        'cnn.com' => 85,
        'apnews.com' => 90,
        'guardian.co.uk' => 88,
        'nytimes.com' => 90,
        'washingtonpost.com' => 85,
        'theatlantic.com' => 80,
        'economist.com' => 85,
        'bloomberg.com' => 85,
        'wsj.com' => 85,
        'ft.com' => 85,
        'independent.co.uk' => 75,
        'sky.com' => 70,
        'itv.com' => 70,
        'telegraph.co.uk' => 70,
        'dailymail.co.uk' => 60,
        'mirror.co.uk' => 60
    );
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->cache_manager = new CP_Cache_Manager();
        $this->logger = CP_Logger::getInstance();
        $this->verified_sources_table = $GLOBALS['wpdb']->prefix . 'aanp_verified_sources';
        $this->content_verification_table = $GLOBALS['wpdb']->prefix . 'aanp_content_verification';
        
        $this->logger->debug('Content verifier initialized');
    }
    
    /**
     * Validate source URL accessibility and legitimacy
     *
     * @param string $original_url Original article URL
     * @return array Verification result
     */
    public function validate_source_url($original_url) {
        try {
            // Check cache first
            $cache_key = 'url_verification_' . md5($original_url);
            $cached_result = $this->cache_manager->get($cache_key);
            if ($cached_result !== false) {
                return $cached_result;
            }
            
            $result = array(
                'status' => 'unknown',
                'accessible' => false,
                'status_code' => null,
                'is_direct_article' => false,
                'domain' => parse_url($original_url, PHP_URL_HOST),
                'error_message' => null
            );
            
            // Validate URL format
            if (!filter_var($original_url, FILTER_VALIDATE_URL)) {
                $result['status'] = 'error';
                $result['error_message'] = 'Invalid URL format';
                return $this->cache_and_return($result, $cache_key, 1800); // 30 min cache
            }
            
            // Check if URL returns a valid response
            $response = wp_remote_get($original_url, array(
                'timeout' => 15,
                'user-agent' => 'AI Auto News Poster/' . AANP_VERSION,
                'headers' => array(
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'
                )
            ));
            
            if (is_wp_error($response)) {
                $result['status'] = 'error';
                $result['error_message'] = 'HTTP request failed: ' . $response->get_error_message();
                return $this->cache_and_return($result, $cache_key, 900); // 15 min cache for failures
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $result['status_code'] = $response_code;
            
            if ($response_code === 200) {
                $result['accessible'] = true;
                $result['status'] = 'verified';
                
                // Check if this appears to be a direct article (not RSS feed)
                $body = wp_remote_retrieve_body($response);
                $result['is_direct_article'] = $this->is_direct_article($body);
                
            } elseif (in_array($response_code, array(404, 410))) {
                $result['status'] = 'error';
                $result['error_message'] = 'Article not found (HTTP ' . $response_code . ')';
            } elseif ($response_code === 403) {
                $result['status'] = 'warning';
                $result['error_message'] = 'Access forbidden (may be paywalled)';
            } else {
                $result['status'] = 'error';
                $result['error_message'] = 'HTTP error ' . $response_code;
            }
            
            return $this->cache_and_return($result, $cache_key, $result['status'] === 'verified' ? 3600 : 1800);
            
        } catch (Exception $e) {
            $this->logger->error('URL validation failed', array(
                'url' => $original_url,
                'error' => $e->getMessage()
            ));
            
            return array(
                'status' => 'error',
                'accessible' => false,
                'error_message' => 'Validation error: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Check content availability and extract basic info
     *
     * @param string $url Article URL
     * @return array Content analysis
     */
    public function check_content_availability($url) {
        try {
            $response = wp_remote_get($url, array(
                'timeout' => 20,
                'user-agent' => 'AI Auto News Poster/' . AANP_VERSION
            ));
            
            if (is_wp_error($response)) {
                return array(
                    'available' => false,
                    'error' => $response->get_error_message()
                );
            }
            
            $status_code = wp_remote_retrieve_response_code($response);
            if ($status_code !== 200) {
                return array(
                    'available' => false,
                    'error' => 'HTTP ' . $status_code
                );
            }
            
            $content = wp_remote_retrieve_body($response);
            
            return array(
                'available' => true,
                'content_length' => strlen($content),
                'has_retraction' => $this->detect_retraction_keywords($content),
                'is_well_formed' => $this->validate_content_structure($content)
            );
            
        } catch (Exception $e) {
            return array(
                'available' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Detect retracted content in text
     *
     * @param string $url Article URL
     * @param string $content Article content
     * @return array Detection result
     */
    public function detect_retracted_content($url, $content = '') {
        try {
            // If content not provided, fetch it
            if (empty($content)) {
                $content_check = $this->check_content_availability($url);
                if (!$content_check['available']) {
                    return array(
                        'retracted' => true,
                        'reason' => 'Content not available',
                        'confidence' => 0.9
                    );
                }
                
                // Re-fetch with full content for analysis
                $response = wp_remote_get($url, array('timeout' => 20));
                if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                    $content = wp_remote_retrieve_body($response);
                } else {
                    $content = '';
                }
            }
            
            $lower_content = strtolower($content);
            $found_keywords = array();
            
            foreach ($this->retraction_keywords as $keyword) {
                if (strpos($lower_content, $keyword) !== false) {
                    $found_keywords[] = $keyword;
                }
            }
            
            $confidence = count($found_keywords) * 0.2; // 20% confidence per keyword
            $confidence = min($confidence, 0.9); // Cap at 90%
            
            return array(
                'retracted' => !empty($found_keywords) && $confidence > 0.4,
                'keywords_found' => $found_keywords,
                'confidence' => $confidence,
                'reason' => !empty($found_keywords) ? 
                    'Retraction keywords detected: ' . implode(', ', $found_keywords) : null
            );
            
        } catch (Exception $e) {
            return array(
                'retracted' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Verify RSS item legitimacy
     *
     * @param SimpleXMLElement $rss_item RSS item
     * @return array Verification result
     */
    public function verify_rss_item_legitimacy($rss_item) {
        try {
            $title = (string) $rss_item->title;
            $link = (string) $rss_item->link;
            $description = isset($rss_item->description) ? (string) $rss_item->description : '';
            
            $result = array(
                'legitimate' => true,
                'issues' => array(),
                'domain' => parse_url($link, PHP_URL_HOST),
                'title_quality' => $this->assess_title_quality($title),
                'content_length' => strlen($description),
                'spam_indicators' => $this->detect_spam_indicators($title, $description)
            );
            
            // Check for spam indicators
            if (!empty($result['spam_indicators'])) {
                $result['legitimate'] = false;
                $result['issues'] = array_merge($result['issues'], $result['spam_indicators']);
            }
            
            // Check title quality
            if ($result['title_quality']['score'] < 0.5) {
                $result['issues'][] = 'Poor title quality';
            }
            
            // Check for sufficient content
            if (strlen(trim($description)) < 50) {
                $result['issues'][] = 'Insufficient content description';
            }
            
            // Verify source domain credibility
            $domain_credibility = $this->get_domain_credibility($result['domain']);
            $result['credibility_score'] = $domain_credibility['score'];
            $result['credibility_status'] = $domain_credibility['status'];
            
            if ($domain_credibility['score'] < 30) {
                $result['legitimate'] = false;
                $result['issues'][] = 'Low credibility source domain';
            }
            
            return $result;
            
        } catch (Exception $e) {
            return array(
                'legitimate' => false,
                'error' => $e->getMessage(),
                'issues' => array('Verification failed')
            );
        }
    }
    
    /**
     * Extract original article URL from RSS item
     *
     * @param SimpleXMLElement $rss_item RSS item
     * @return string|null Original URL
     */
    public function extract_original_article_url($rss_item) {
        try {
            $possible_urls = array();
            
            // Primary: link element
            $link = (string) $rss_item->link;
            if (!empty($link)) {
                $possible_urls[] = $link;
            }
            
            // Fallback: guid
            if (isset($rss_item->guid) && !empty($rss_item->guid)) {
                $guid = (string) $rss_item->guid;
                if (filter_var($guid, FILTER_VALIDATE_URL)) {
                    $possible_urls[] = $guid;
                }
            }
            
            // Fallback: source element
            if (isset($rss_item->source) && !empty($rss_item->source)) {
                $source = (string) $rss_item->source;
                if (filter_var($source, FILTER_VALIDATE_URL)) {
                    $possible_urls[] = $source;
                }
            }
            
            // Return first valid, non-RSS URL
            foreach ($possible_urls as $url) {
                if ($this->is_direct_article_url($url)) {
                    return $url;
                }
            }
            
            // If no direct article URL found, return the first valid URL
            return !empty($possible_urls) ? $possible_urls[0] : null;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to extract original URL', array(
                'error' => $e->getMessage()
            ));
            return null;
        }
    }
    
    /**
     * Validate image sources for accessibility
     *
     * @param array $image_urls Array of image URLs
     * @return array Validation results
     */
    public function validate_image_sources($image_urls) {
        $results = array();
        
        foreach ($image_urls as $index => $url) {
            try {
                $response = wp_remote_head($url, array('timeout' => 10));
                
                $results[$index] = array(
                    'url' => $url,
                    'accessible' => !is_wp_error($response) && 
                                   wp_remote_retrieve_response_code($response) === 200,
                    'content_type' => null,
                    'file_size' => null
                );
                
                if (!is_wp_error($response)) {
                    $headers = wp_remote_retrieve_headers($response);
                    $results[$index]['content_type'] = isset($headers['content-type']) ? 
                        $headers['content-type'] : null;
                    
                    if (isset($headers['content-length'])) {
                        $results[$index]['file_size'] = intval($headers['content-length']);
                    }
                }
                
            } catch (Exception $e) {
                $results[$index] = array(
                    'url' => $url,
                    'accessible' => false,
                    'error' => $e->getMessage()
                );
            }
        }
        
        return $results;
    }
    
    /**
     * Check if content appears to be a direct article (not RSS)
     *
     * @param string $body HTML content
     * @return bool True if appears to be article
     */
    private function is_direct_article($body) {
        $article_indicators = array(
            '<article', '<h1', '<h2', 'published', 'author', 
            'news', 'article', 'story', 'report'
        );
        
        $rss_indicators = array(
            'rss', 'xml', 'feed', '<rss', '<?xml'
        );
        
        $lower_body = strtolower($body);
        
        // If contains RSS indicators, it's probably RSS
        foreach ($rss_indicators as $indicator) {
            if (strpos($lower_body, $indicator) !== false) {
                return false;
            }
        }
        
        // If contains article indicators, it's probably an article
        $article_score = 0;
        foreach ($article_indicators as $indicator) {
            if (strpos($lower_body, $indicator) !== false) {
                $article_score++;
            }
        }
        
        return $article_score >= 2;
    }
    
    /**
     * Check if URL appears to be direct article (not RSS feed)
     *
     * @param string $url URL to check
     * @return bool True if appears to be direct article
     */
    private function is_direct_article_url($url) {
        $rss_patterns = array('/rss', '/feed', 'feed=', 'rss=', 'atom');
        
        foreach ($rss_patterns as $pattern) {
            if (stripos($url, $pattern) !== false) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Detect retraction keywords in content
     *
     * @param string $content Content to analyze
     * @return array Found keywords
     */
    private function detect_retraction_keywords($content) {
        $found = array();
        $lower_content = strtolower($content);
        
        foreach ($this->retraction_keywords as $keyword) {
            if (strpos($lower_content, $keyword) !== false) {
                $found[] = $keyword;
            }
        }
        
        return $found;
    }
    
    /**
     * Validate basic content structure
     *
     * @param string $content Content to validate
     * @return bool True if well-formed
     */
    private function validate_content_structure($content) {
        return strlen($content) > 100 && 
               strpos($content, '<html') !== false &&
               strpos($content, '<head') !== false;
    }
    
    /**
     * Assess title quality
     *
     * @param string $title Title to assess
     * @return array Quality analysis
     */
    private function assess_title_quality($title) {
        $score = 0.5; // Base score
        
        // Length check
        if (strlen($title) > 10 && strlen($title) < 150) {
            $score += 0.2;
        }
        
        // Capitalization check
        $words = explode(' ', $title);
        $capitalized_words = 0;
        foreach ($words as $word) {
            if (ctype_upper(substr($word, 0, 1))) {
                $capitalized_words++;
            }
        }
        
        if (count($words) > 0) {
            $capitalization_ratio = $capitalized_words / count($words);
            if ($capitalization_ratio > 0.3 && $capitalization_ratio < 0.8) {
                $score += 0.2;
            }
        }
        
        // Suspicious patterns
        $suspicious_patterns = array('!!!', '???', 'SHOCKING', 'AMAZING', 'INCREDIBLE');
        foreach ($suspicious_patterns as $pattern) {
            if (stripos($title, $pattern) !== false) {
                $score -= 0.3;
                break;
            }
        }
        
        return array(
            'score' => max(0, min(1, $score)),
            'length_ok' => strlen($title) > 10 && strlen($title) < 150,
            'suspicious_patterns' => false
        );
    }
    
    /**
     * Detect spam indicators in title and content
     *
     * @param string $title Title
     * @param string $content Content
     * @return array Spam indicators
     */
    private function detect_spam_indicators($title, $content) {
        $indicators = array();
        
        $spam_patterns = array(
            'click here' => 'Clickbait language',
            'buy now' => 'Commercial content',
            'limited time' => 'Sales pressure',
            'free money' => 'Too good to be true',
            'make money fast' => 'Get-rich-quick scheme',
            'you won\'t believe' => 'Clickbait title'
        );
        
        $lower_title = strtolower($title);
        $lower_content = strtolower($content);
        
        foreach ($spam_patterns as $pattern => $description) {
            if (strpos($lower_title, $pattern) !== false || 
                strpos($lower_content, $pattern) !== false) {
                $indicators[] = $description;
            }
        }
        
        return $indicators;
    }
    
    /**
     * Get domain credibility score
     *
     * @param string $domain Domain to check
     * @return array Credibility data
     */
    private function get_domain_credibility($domain) {
        // Check local cache first
        $cache_key = 'domain_credibility_' . md5($domain);
        $cached = $this->cache_manager->get($cache_key);
        if ($cached !== false) {
            return $cached;
        }
        
        $result = array(
            'score' => 50, // Default score
            'status' => 'unknown',
            'source' => 'calculated'
        );
        
        // Check against known trusted sources
        if (isset($this->trusted_sources[$domain])) {
            $result['score'] = $this->trusted_sources[$domain];
            $result['status'] = $result['score'] >= 80 ? 'trusted' : 
                               ($result['score'] >= 60 ? 'reliable' : 'questionable');
            $result['source'] = 'whitelist';
        } else {
            // Calculate based on domain characteristics
            $calculated_score = $this->calculate_domain_credibility($domain);
            $result['score'] = $calculated_score['score'];
            $result['status'] = $calculated_score['status'];
        }
        
        // Cache for 24 hours
        $this->cache_manager->set($cache_key, $result, 86400);
        
        return $result;
    }
    
    /**
     * Calculate domain credibility based on characteristics
     *
     * @param string $domain Domain
     * @return array Credibility calculation
     */
    private function calculate_domain_credibility($domain) {
        $score = 50; // Base score
        
        // .com domains get slight boost
        if (strpos($domain, '.com') !== false) {
            $score += 5;
        }
        
        // News organizations get boost
        $news_indicators = array('news', 'times', 'post', 'herald', 'tribune', 'gazette');
        foreach ($news_indicators as $indicator) {
            if (strpos($domain, $indicator) !== false) {
                $score += 10;
                break;
            }
        }
        
        // Government domains get boost
        if (strpos($domain, '.gov') !== false) {
            $score += 15;
        }
        
        // Educational domains get boost
        if (strpos($domain, '.edu') !== false) {
            $score += 10;
        }
        
        // Questionable TLDs get penalty
        $questionable_tlds = array('.tk', '.ml', '.ga', '.cf');
        foreach ($questionable_tlds as $tld) {
            if (strpos($domain, $tld) !== false) {
                $score -= 20;
                break;
            }
        }
        
        // Suspicious patterns get penalty
        $suspicious_patterns = array('free', 'download', 'click', 'win');
        foreach ($suspicious_patterns as $pattern) {
            if (strpos($domain, $pattern) !== false) {
                $score -= 15;
                break;
            }
        }
        
        $score = max(0, min(100, $score));
        
        return array(
            'score' => $score,
            'status' => $score >= 70 ? 'reliable' : 
                       ($score >= 50 ? 'neutral' : 'questionable')
        );
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