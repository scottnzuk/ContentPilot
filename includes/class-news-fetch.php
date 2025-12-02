<?php
/**
 * News Fetch Class
 *
 * @package AI_Auto_News_Poster
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class CP_News_Fetch {
    
    private $cache_manager;
    private $rate_limiter;
    private $logger;
    private $content_verifier;
    private $rss_processor;
    private $retracted_handler;
    private $verification_db;
    private $max_retries = 3;
    private $retry_delay = 1; // seconds
    private $feed_timeout = 30;
    private $max_feed_size = 1048576; // 1MB
    
    /**
     * Constructor
     */
    public function __construct() {
        try {
            $this->cache_manager = new CP_Cache_Manager();
            $this->rate_limiter = new CP_Rate_Limiter();
            $this->logger = CP_Logger::getInstance();

            // Initialize content verification system
            $this->content_verifier = new CP_ContentVerifier();
            $this->rss_processor = new CP_RSSItemProcessor();
            $this->retracted_handler = new CP_RetractedContentHandler();
            $this->verification_db = new CP_VerificationDatabase();
            
            $this->logger->debug('News fetch class with verification initialized successfully');
        } catch (Exception $e) {
            // Log critical error but don't throw exception to prevent fatal errors
            error_log('AANP: Critical error in News_Fetch constructor: ' . $e->getMessage());
            
            // Initialize minimal fallback objects
            $this->cache_manager = null;
            $this->rate_limiter = null;
            $this->logger = null;
            $this->content_verifier = null;
            $this->rss_processor = null;
            $this->retracted_handler = null;
            $this->verification_db = null;
        }
    }
    
    /**
     * Fetch latest news from RSS feeds
     *
     * @return array Array of news articles
     */
    public function fetch_latest_news() {
        $articles = array();
        
        try {
            // Check rate limiting
            if ($this->rate_limiter && ($this->rate_limiter instanceof CP_Rate_Limiter || $this->rate_limiter instanceof AANP_Rate_Limiter) && $this->rate_limiter->is_rate_limited('feed_fetch', 10, 300)) {
                $this->log_warning('Rate limit exceeded for feed fetching', array(
                    'action' => 'feed_fetch',
                    'limit' => 10,
                    'window' => 300
                ));
                return $articles;
            }
            
            // Check cache first
            if ($this->cache_manager && ($this->cache_manager instanceof CP_Cache_Manager || $this->cache_manager instanceof AANP_Cache_Manager)) {
                $cached_articles = $this->cache_manager->get('latest_news');
                if ($cached_articles !== false) {
                    $this->log_info('Retrieved articles from cache', array('count' => count($cached_articles)));
                    return $cached_articles;
                }
            }
            
            // Get configured feeds
            $options = get_option('aanp_settings', array());
            $rss_feeds = $this->validate_and_sanitize_feeds(
                isset($options['rss_feeds']) ? $options['rss_feeds'] : array()
            );
            
            // Use default feeds if none configured
            if (empty($rss_feeds)) {
                $rss_feeds = $this->get_default_feeds();
                $this->log_info('Using default RSS feeds', array('count' => count($rss_feeds)));
            }
            
            // Record rate limit attempt
            if ($this->rate_limiter && ($this->rate_limiter instanceof CP_Rate_Limiter || $this->rate_limiter instanceof AANP_Rate_Limiter)) {
                $this->rate_limiter->record_attempt('feed_fetch', 300);
            }
            
            // Fetch from each feed with error handling
            foreach ($rss_feeds as $feed_url) {
                try {
                    $feed_articles = $this->fetch_from_feed_with_retry($feed_url);
                    if (!empty($feed_articles)) {
                        $articles = array_merge($articles, $feed_articles);
                    }
                } catch (Exception $e) {
                    $this->log_error('Failed to fetch from feed', array(
                        'feed_url' => $feed_url,
                        'error' => $e->getMessage()
                    ));
                    continue;
                }
            }
            
            // Sort by publication date (newest first)
            if (!empty($articles)) {
                usort($articles, function($a, $b) {
                    return strtotime($b['date']) - strtotime($a['date']);
                });
                
                // Return top 10 articles
                $articles = array_slice($articles, 0, 10);
            }
            
            // Cache the results for 30 minutes
            if ($this->cache_manager && ($this->cache_manager instanceof CP_Cache_Manager || $this->cache_manager instanceof AANP_Cache_Manager)) {
                $this->cache_manager->set('latest_news', $articles, 1800);
            }
            
            $this->log_info('Successfully fetched news articles', array(
                'total_articles' => count($articles),
                'feeds_processed' => count($rss_feeds)
            ));
            
        } catch (Exception $e) {
            $this->log_critical('Critical error in fetch_latest_news', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
        }
        
        return $articles;
    }
    
    /**
     * Fetch articles from a single RSS feed with retry logic
     *
     * @param string $feed_url RSS feed URL
     * @return array Array of articles
     */
    private function fetch_from_feed_with_retry($feed_url) {
        $last_error = null;
        
        for ($attempt = 1; $attempt <= $this->max_retries; $attempt++) {
            try {
                return $this->fetch_from_feed($feed_url);
            } catch (Exception $e) {
                $last_error = $e;
                
                if ($attempt < $this->max_retries) {
                    $this->log_warning('Feed fetch attempt failed, retrying', array(
                        'feed_url' => $feed_url,
                        'attempt' => $attempt,
                        'max_retries' => $this->max_retries,
                        'error' => $e->getMessage()
                    ));
                    
                    // Exponential backoff
                    sleep($this->retry_delay * $attempt);
                } else {
                    $this->log_error('All retry attempts failed for feed', array(
                        'feed_url' => $feed_url,
                        'attempts' => $this->max_retries,
                        'final_error' => $e->getMessage()
                    ));
                }
            }
        }
        
        throw $last_error;
    }
    
    /**
     * Fetch articles from a single RSS feed
     *
     * @param string $feed_url RSS feed URL
     * @return array Array of articles
     * @throws Exception On network or parsing errors
     */
    private function fetch_from_feed($feed_url) {
        $articles = array();
        
        // Validate and sanitize URL
        $feed_url = $this->sanitize_feed_url($feed_url);
        if (!$feed_url) {
            throw new Exception('Invalid feed URL provided');
        }
        
        $this->log_info('Starting feed fetch', array('feed_url' => $feed_url));
        
        try {
            // Make HTTP request with timeout and error handling
            $response = wp_remote_get($feed_url, array(
                'timeout' => $this->feed_timeout,
                'user-agent' => 'AI Auto News Poster/' . CP_VERSION,
                'headers' => array(
                    'Accept' => 'application/rss+xml, application/xml, text/xml'
                )
            ));
            
            if (is_wp_error($response)) {
                throw new Exception('HTTP request failed: ' . $response->get_error_message());
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                throw new Exception('HTTP error: ' . $response_code . ' ' . wp_remote_retrieve_response_message($response));
            }
            
            $body = wp_remote_retrieve_body($response);
            
            if (empty($body)) {
                throw new Exception('Empty response body from feed');
            }
            
            // Check response size
            if (strlen($body) > $this->max_feed_size) {
                throw new Exception('Feed response too large: ' . strlen($body) . ' bytes');
            }
            
            // Parse XML with error handling
            $xml = $this->parse_xml_safely($body, $feed_url);
            
            // Validate feed structure
            $this->validate_feed_structure($xml, $feed_url);
            
            // Handle different RSS formats
            if (isset($xml->channel->item)) {
                // RSS 2.0 format
                $items = $xml->channel->item;
                $format = 'RSS 2.0';
            } elseif (isset($xml->entry)) {
                // Atom format
                $items = $xml->entry;
                $format = 'Atom';
            } else {
                throw new Exception('Unsupported feed format or missing items');
            }
            
            // Parse items with verification
            $parsed_count = 0;
            foreach ($items as $item) {
                try {
                    // Process RSS item with verification
                    if ($this->content_verifier && $this->rss_processor) {
                        $processed_item = $this->rss_processor->process_rss_item($item, $feed_url);
                        
                        // Validate content legitimacy
                        if (isset($processed_item['legitimacy']) && $processed_item['legitimacy']['legitimate']) {
                            // Check for retracted content
                            if (isset($processed_item['original_url']) && $processed_item['original_url']) {
                                $retraction_check = $this->retracted_handler->detect_retraction_keywords(
                                    $processed_item['description']
                                );
                                
                                if ($retraction_check['retracted']) {
                                    $this->log_warning('Retracted content detected, skipping', array(
                                        'feed_url' => $feed_url,
                                        'title' => $processed_item['title'],
                                        'confidence' => $retraction_check['confidence']
                                    ));
                                    
                                    // Record the problematic content
                                    if ($this->verification_db) {
                                        $this->verification_db->record_verification(array(
                                            'original_url' => $processed_item['original_url'],
                                            'status' => 'error',
                                            'retraction_detected' => true,
                                            'retraction_confidence' => $retraction_check['confidence'],
                                            'details' => $retraction_check
                                        ));
                                    }
                                    
                                    continue; // Skip this article
                                }
                            }
                            
                            // Convert processed item to standard format
                            $article = $this->convert_processed_item($processed_item);
                            if ($article) {
                                $articles[] = $article;
                                $parsed_count++;
                                
                                // Record verification if available
                                if ($this->verification_db && isset($article['original_url'])) {
                                    $this->record_verification_result($processed_item, $feed_url);
                                }
                            }
                        } else {
                            $this->log_warning('Article failed legitimacy check', array(
                                'feed_url' => $feed_url,
                                'issues' => $processed_item['legitimacy']['issues'] ?? array()
                            ));
                        }
                    } else {
                        // Fallback to original parsing if verification not available
                        if (isset($xml->channel->item)) {
                            // RSS format
                            $article = $this->parse_rss_item($item, $feed_url);
                        } else {
                            // Atom format
                            $article = $this->parse_atom_entry($item, $feed_url);
                        }
                        
                        if ($article && $this->validate_article($article)) {
                            $articles[] = $article;
                            $parsed_count++;
                        }
                    }
                } catch (Exception $e) {
                    $this->log_warning('Failed to parse feed item', array(
                        'feed_url' => $feed_url,
                        'error' => $e->getMessage()
                    ));
                    continue;
                }
            }
            
            $this->log_info('Successfully parsed feed', array(
                'feed_url' => $feed_url,
                'format' => $format,
                'items_parsed' => $parsed_count
            ));
            
        } catch (Exception $e) {
            $this->log_error('Failed to fetch and parse feed', array(
                'feed_url' => $feed_url,
                'error' => $e->getMessage()
            ));
            throw $e;
        }
        
        return $articles;
    }
    
    /**
     * Parse XML safely with error handling
     *
     * @param string $xml_string XML content
     * @param string $feed_url Feed URL for error reporting
     * @return SimpleXMLElement Parsed XML object
     * @throws Exception On XML parsing errors
     */
    private function parse_xml_safely($xml_string, $feed_url) {
        // Enable internal error handling
        libxml_use_internal_errors(true);
        libxml_clear_errors();
        
        // Attempt to parse XML
        $xml = simplexml_load_string($xml_string, 'SimpleXMLElement', LIBXML_NOCDATA);
        
        // Check for parsing errors
        $errors = libxml_get_errors();
        libxml_clear_errors();
        
        if ($xml === false || !empty($errors)) {
            $error_messages = array();
            foreach ($errors as $error) {
                $error_messages[] = trim($error->message);
            }
            
            throw new Exception('XML parsing failed: ' . implode('; ', $error_messages));
        }
        
        // Additional XML validation
        if (!$this->is_valid_xml_structure($xml)) {
            throw new Exception('Invalid XML structure');
        }
        
        return $xml;
    }
    
    /**
     * Validate basic XML structure
     *
     * @param SimpleXMLElement $xml Parsed XML
     * @return bool True if valid
     */
    private function is_valid_xml_structure($xml) {
        // Check if it's a valid XML document
        if (!$xml->getName()) {
            return false;
        }
        
        // Check for RSS or Atom structure
        $has_rss_structure = isset($xml->channel) || isset($xml->entry);
        if (!$has_rss_structure) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate feed structure
     *
     * @param SimpleXMLElement $xml Parsed XML
     * @param string $feed_url Feed URL for error reporting
     * @throws Exception On validation failure
     */
    private function validate_feed_structure($xml, $feed_url) {
        // Check if feed has any items/entries
        $has_items = false;
        if (isset($xml->channel->item) || isset($xml->entry)) {
            $items = isset($xml->channel->item) ? $xml->channel->item : $xml->entry;
            $has_items = count($items) > 0;
        }
        
        if (!$has_items) {
            throw new Exception('Feed contains no items or entries');
        }
        
        // Additional structural validation can be added here
    }
    
    /**
     * Validate parsed article
     *
     * @param array $article Parsed article data
     * @return bool True if valid
     */
    private function validate_article($article) {
        // Check required fields
        if (empty($article['title']) || empty($article['link'])) {
            return false;
        }
        
        // Validate URL
        if (!filter_var($article['link'], FILTER_VALIDATE_URL)) {
            return false;
        }
        
        // Validate title length
        if (strlen($article['title']) < 5 || strlen($article['title']) > 200) {
            return false;
        }
        
        // Validate description length if present
        if (!empty($article['description']) && strlen($article['description']) > 1000) {
            $article['description'] = substr($article['description'], 0, 1000) . '...';
        }
        
        return true;
    }
    
    /**
     * Sanitize feed URL
     *
     * @param string $url Raw URL
     * @return string|false Sanitized URL or false if invalid
     */
    private function sanitize_feed_url($url) {
        if (empty($url) || !is_string($url)) {
            return false;
        }
        
        // Remove whitespace
        $url = trim($url);
        
        // Validate URL format
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        // Only allow HTTP/HTTPS protocols
        $parsed_url = parse_url($url);
        if (!in_array($parsed_url['scheme'], array('http', 'https'))) {
            return false;
        }
        
        // Remove potentially dangerous characters
        $url = filter_var($url, FILTER_SANITIZE_URL);
        
        return $url;
    }
    
    /**
     * Validate and sanitize feed URLs
     *
     * @param array $feeds Array of feed URLs
     * @return array Validated and sanitized feeds
     */
    private function validate_and_sanitize_feeds($feeds) {
        if (!is_array($feeds)) {
            return array();
        }
        
        $validated_feeds = array();
        foreach ($feeds as $feed_url) {
            $sanitized_url = $this->sanitize_feed_url($feed_url);
            if ($sanitized_url) {
                $validated_feeds[] = $sanitized_url;
            }
        }
        
        return array_unique($validated_feeds);
    }
    
    /**
     * Get default RSS feeds
     *
     * @return array Default feed URLs
     */
    private function get_default_feeds() {
        return array(
            'https://feeds.bbci.co.uk/news/rss.xml',
            'https://rss.cnn.com/rss/edition.rss',
            'https://feeds.reuters.com/reuters/topNews'
        );
    }
    
    /**
     * Parse RSS 2.0 item
     *
     * @param SimpleXMLElement $item RSS item
     * @param string $feed_url Source feed URL
     * @return array|null Parsed article data
     */
    private function parse_rss_item($item, $feed_url) {
        $title = $this->sanitize_text((string) $item->title);
        $link = $this->sanitize_url((string) $item->link);
        $description = $this->sanitize_text((string) $item->description);
        $pub_date = (string) $item->pubDate;
        
        if (empty($title) || empty($link)) {
            return null;
        }
        
        // Clean description
        $description = wp_strip_all_tags($description);
        $description = $this->clean_description($description);
        
        // Parse date
        $date = $this->parse_date($pub_date);
        
        return array(
            'title' => $title,
            'link' => $link,
            'description' => $description,
            'date' => $date,
            'source_feed' => $feed_url,
            'source_domain' => parse_url($link, PHP_URL_HOST)
        );
    }
    
    /**
     * Parse Atom entry
     *
     * @param SimpleXMLElement $entry Atom entry
     * @param string $feed_url Source feed URL
     * @return array|null Parsed article data
     */
    private function parse_atom_entry($entry, $feed_url) {
        $title = $this->sanitize_text((string) $entry->title);
        $link = '';
        $description = '';
        $pub_date = (string) $entry->published;
        
        // Get link
        if (isset($entry->link)) {
            if (is_array($entry->link)) {
                foreach ($entry->link as $link_elem) {
                    if ((string) $link_elem['type'] === 'text/html') {
                        $link = $this->sanitize_url((string) $link_elem['href']);
                        break;
                    }
                }
            } else {
                $link = $this->sanitize_url((string) $entry->link['href']);
            }
        }
        
        // Get description
        if (isset($entry->summary)) {
            $description = $this->sanitize_text((string) $entry->summary);
        } elseif (isset($entry->content)) {
            $description = $this->sanitize_text((string) $entry->content);
        }
        
        if (empty($title) || empty($link)) {
            return null;
        }
        
        // Clean description
        $description = wp_strip_all_tags($description);
        $description = $this->clean_description($description);
        
        // Parse date
        $date = $this->parse_date($pub_date);
        
        return array(
            'title' => $title,
            'link' => $link,
            'description' => $description,
            'date' => $date,
            'source_feed' => $feed_url,
            'source_domain' => parse_url($link, PHP_URL_HOST)
        );
    }
    
    /**
     * Sanitize text content
     *
     * @param string $text Raw text
     * @return string Sanitized text
     */
    private function sanitize_text($text) {
        if (empty($text)) {
            return '';
        }
        
        // Remove HTML tags and dangerous characters
        $text = wp_strip_all_tags($text);
        $text = trim($text);
        
        // Limit length to prevent issues
        if (strlen($text) > 1000) {
            $text = substr($text, 0, 997) . '...';
        }
        
        return $text;
    }
    
    /**
     * Sanitize URL
     *
     * @param string $url Raw URL
     * @return string Sanitized URL
     */
    private function sanitize_url($url) {
        if (empty($url)) {
            return '';
        }
        
        $url = trim($url);
        
        // Make relative URLs absolute if they have a base
        if (strpos($url, 'http') !== 0) {
            // Could add base URL handling here if needed
            return '';
        }
        
        return filter_var($url, FILTER_SANITIZE_URL);
    }
    
    /**
     * Clean and truncate description
     *
     * @param string $description Raw description
     * @return string Cleaned description
     */
    private function clean_description($description) {
        // Remove extra whitespace
        $description = preg_replace('/\s+/', ' ', $description);
        $description = trim($description);
        
        // Truncate to reasonable length for AI processing
        if (strlen($description) > 500) {
            $description = substr($description, 0, 500) . '...';
        }
        
        return $description;
    }
    
    /**
     * Parse date string
     *
     * @param string $date_string Date string
     * @return string Formatted date
     */
    private function parse_date($date_string) {
        if (empty($date_string)) {
            return current_time('mysql');
        }
        
        $timestamp = strtotime($date_string);
        
        if ($timestamp === false) {
            return current_time('mysql');
        }
        
        return date('Y-m-d H:i:s', $timestamp);
    }
    
    /**
     * Get feed info for testing
     *
     * @param string $feed_url RSS feed URL
     * @return array Feed information
     */
    public function get_feed_info($feed_url) {
        try {
            // Sanitize URL
            $feed_url = $this->sanitize_feed_url($feed_url);
            if (!$feed_url) {
                return array(
                    'status' => 'error',
                    'message' => 'Invalid feed URL'
                );
            }
            
            $response = wp_remote_get($feed_url, array(
                'timeout' => 15,
                'user-agent' => 'AI Auto News Poster/' . CP_VERSION
            ));
            
            if (is_wp_error($response)) {
                return array(
                    'status' => 'error',
                    'message' => 'HTTP request failed: ' . $response->get_error_message()
                );
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                return array(
                    'status' => 'error',
                    'message' => 'HTTP error: ' . $response_code
                );
            }
            
            $body = wp_remote_retrieve_body($response);
            $xml = $this->parse_xml_safely($body, $feed_url);
            
            $info = array(
                'status' => 'success',
                'title' => '',
                'description' => '',
                'item_count' => 0,
                'format' => 'unknown'
            );
            
            if (isset($xml->channel)) {
                // RSS format
                $info['format'] = 'RSS';
                $info['title'] = $this->sanitize_text((string) $xml->channel->title);
                $info['description'] = $this->sanitize_text((string) $xml->channel->description);
                $info['item_count'] = count($xml->channel->item);
            } elseif (isset($xml->title)) {
                // Atom format
                $info['format'] = 'Atom';
                $info['title'] = $this->sanitize_text((string) $xml->title);
                $info['description'] = $this->sanitize_text((string) $xml->subtitle);
                $info['item_count'] = count($xml->entry);
            }
            
            return $info;
            
        } catch (Exception $e) {
            return array(
                'status' => 'error',
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Validate RSS feed URL
     *
     * @param string $url Feed URL
     * @return bool True if valid
     */
    public function validate_feed_url($url) {
        $feed_url = $this->sanitize_feed_url($url);
        if (!$feed_url) {
            return false;
        }
        
        $info = $this->get_feed_info($feed_url);
        return $info['status'] === 'success';
    }
    
    /**
     * Logging methods
     */
    private function log_debug($message, $context = array()) {
        if ($this->logger) {
            $this->logger->debug($message, $context);
        }
    }
    
    private function log_info($message, $context = array()) {
        if ($this->logger) {
            $this->logger->info($message, $context);
        }
    }
    
    private function log_warning($message, $context = array()) {
        if ($this->logger) {
            $this->logger->warning($message, $context);
        }
    }
    
    private function log_error($message, $context = array()) {
        if ($this->logger) {
            $this->logger->error($message, $context);
        }
    }
    
    private function log_critical($message, $context = array()) {
        if ($this->logger) {
            $this->logger->critical($message, $context);
        }
    }
    
    /**
     * Get configuration settings
     *
     * @return array Configuration options
     */
    public function get_config() {
        return array(
            'max_retries' => $this->max_retries,
            'retry_delay' => $this->retry_delay,
            'feed_timeout' => $this->feed_timeout,
            'max_feed_size' => $this->max_feed_size,
            'verification_enabled' => !empty($this->content_verifier)
        );
    }
    
    /**
     * Update configuration settings
     *
     * @param array $config Configuration options
     */
    public function update_config($config) {
        if (isset($config['max_retries'])) {
            $this->max_retries = max(1, min(10, (int) $config['max_retries']));
        }
        
        if (isset($config['retry_delay'])) {
            $this->retry_delay = max(1, min(10, (int) $config['retry_delay']));
        }
        
        if (isset($config['feed_timeout'])) {
            $this->feed_timeout = max(5, min(120, (int) $config['feed_timeout']));
        }
        
        if (isset($config['max_feed_size'])) {
            $this->max_feed_size = max(1024, min(10485760, (int) $config['max_feed_size']));
        }
    }
    
    /**
     * Convert processed RSS item to standard article format
     *
     * @param array $processed_item Processed RSS item
     * @return array Standard article format
     */
    private function convert_processed_item($processed_item) {
        try {
            if (!isset($processed_item['title']) || empty($processed_item['title'])) {
                return null;
            }
            
            $article = array(
                'title' => $processed_item['title'],
                'link' => $processed_item['original_url'] ?: '',
                'description' => isset($processed_item['description']) ? $processed_item['description'] : '',
                'date' => isset($processed_item['publication_date']['publication_date'])
                    ? $processed_item['publication_date']['publication_date']
                    : current_time('mysql'),
                'source_feed' => '', // Will be set by caller
                'source_domain' => isset($processed_item['publisher_info']['domain'])
                    ? $processed_item['publisher_info']['domain']
                    : parse_url($processed_item['original_url'], PHP_URL_HOST),
                'verification_status' => 'verified',
                'quality_score' => isset($processed_item['quality_score']) ? $processed_item['quality_score'] : 0.5,
                'publisher_info' => isset($processed_item['publisher_info']) ? $processed_item['publisher_info'] : array(),
                'author_info' => isset($processed_item['author_info']) ? $processed_item['author_info'] : array(),
                'retraction_detected' => false,
                'url_verified' => isset($processed_item['url_verified']) ? $processed_item['url_verified'] : false
            );
            
            // Add verification details if available
            if (isset($processed_item['legitimacy'])) {
                $article['verification_status'] = $processed_item['legitimacy']['legitimate'] ? 'verified' : 'warning';
                $article['retraction_detected'] = $processed_item['legitimacy']['retracted'] ?? false;
            }
            
            if (isset($processed_item['verification_result'])) {
                $article['verification_details'] = $processed_item['verification_result'];
            }
            
            return $article;
            
        } catch (Exception $e) {
            $this->log_error('Failed to convert processed item', array(
                'error' => $e->getMessage()
            ));
            return null;
        }
    }
    
    /**
     * Record verification result in database
     *
     * @param array $processed_item Processed RSS item
     * @param string $feed_url Source feed URL
     */
    private function record_verification_result($processed_item, $feed_url) {
        try {
            if (!$this->verification_db) {
                return;
            }
            
            $verification_data = array(
                'original_url' => $processed_item['original_url'] ?? '',
                'status' => 'verified',
                'publisher_info' => $processed_item['publisher_info'] ?? array(),
                'metadata' => array(
                    'feed_url' => $feed_url,
                    'quality_score' => $processed_item['quality_score'] ?? 0.5,
                    'verification_timestamp' => current_time('mysql')
                )
            );
            
            // Set status based on legitimacy
            if (isset($processed_item['legitimacy'])) {
                $verification_data['status'] = $processed_item['legitimacy']['legitimate'] ? 'verified' : 'warning';
                $verification_data['retraction_detected'] = $processed_item['legitimacy']['retracted'] ?? false;
                $verification_data['source_legitimate'] = $processed_item['legitimacy']['legitimate'] ?? true;
            }
            
            // Set accessibility status
            if (isset($processed_item['verification_result'])) {
                $verification_data['content_accessible'] = $processed_item['verification_result']['accessible'] ?? true;
            }
            
            $this->verification_db->record_verification($verification_data);
            
        } catch (Exception $e) {
            $this->log_error('Failed to record verification result', array(
                'error' => $e->getMessage(),
                'url' => $processed_item['original_url'] ?? 'unknown'
            ));
        }
    }
}