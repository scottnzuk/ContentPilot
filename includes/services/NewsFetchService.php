<?php
/**
 * News Fetch Service for Microservices Architecture
 *
 * Handles news source management, content retrieval, RSS parsing,
 * and feed optimization with advanced caching and performance monitoring.
 *
 * @package AI_Auto_News_Poster\Services
 * @since 1.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * News Fetch Service Class
 */
class AANP_NewsFetchService {
    
    /**
     * Cache manager instance
     * @var AANP_AdvancedCacheManager
     */
    private $cache_manager;
    
    /**
     * Logger instance
     * @var AANP_Logger
     */
    private $logger;
    
    /**
     * Rate limiter instance
     * @var AANP_Rate_Limiter
     */
    private $rate_limiter;
    
    /**
     * Connection pool manager
     * @var AANP_ConnectionPoolManager
     */
    private $connection_pool;
    
    /**
     * RSS Feed Manager instance
     * @var AANP_RSSFeedManager
     */
    private $rss_feed_manager;
    
    /**
     * Performance metrics
     * @var array
     */
    private $metrics = array();
    
    /**
     * Service configuration
     * @var array
     */
    private $config = array();
    
    /**
     * Constructor
     *
     * @param AANP_AdvancedCacheManager $cache_manager
     * @param AANP_ConnectionPoolManager $connection_pool
     */
    public function __construct(
        AANP_AdvancedCacheManager $cache_manager = null,
        AANP_ConnectionPoolManager $connection_pool = null
    ) {
        $this->logger = AANP_Logger::getInstance();
        $this->cache_manager = $cache_manager ?: new AANP_AdvancedCacheManager();
        $this->connection_pool = $connection_pool;
        
        // Initialize RSS Feed Manager
        if (class_exists('AANP_RSSFeedManager')) {
            $this->rss_feed_manager = new AANP_RSSFeedManager();
        }
        
        // Initialize rate limiter
        if (class_exists('AANP_Rate_Limiter')) {
            $this->rate_limiter = new AANP_Rate_Limiter();
        }
        
        $this->init_config();
        $this->init_hooks();
    }
    
    /**
     * Initialize service configuration
     */
    private function init_config() {
        $options = get_option('aanp_settings', array());
        
        $this->config = array(
            'cache_duration' => isset($options['cache_duration']) ? intval($options['cache_duration']) : 3600,
            'request_timeout' => isset($options['request_timeout']) ? intval($options['request_timeout']) : 30,
            'max_retries' => isset($options['max_retries']) ? intval($options['max_retries']) : 3,
            'batch_size' => isset($options['batch_size']) ? intval($options['batch_size']) : 10,
            'enable_compression' => true,
            'enable_ssl_verify' => true,
            'user_agent' => 'ContentPilot Enhanced/1.3.0 (+https://github.com/scottnzuk/contentpilot-enhanced)'
        );
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('aanp_fetch_news', array($this, 'scheduled_news_fetch'));
        add_action('init', array($this, 'register_scheduled_events'));
    }
    
    /**
     * Register scheduled events
     */
    public function register_scheduled_events() {
        // Schedule automatic news fetching
        if (!wp_next_scheduled('aanp_scheduled_fetch')) {
            wp_schedule_event(time(), 'hourly', 'aanp_scheduled_fetch');
        }
    }
    
    /**
     * Fetch news from configured sources
     *
     * @param array $parameters Fetch parameters
     * @return array Fetch results
     */
    public function fetch_news($parameters = array()) {
        $start_time = microtime(true);
        
        try {
            // Default parameters
            $params = array_merge(array(
                'sources' => $this->get_configured_sources(),
                'limit' => 20,
                'filter_duplicates' => true,
                'validate_content' => true,
                'cache_results' => true
            ), $parameters);
            
            $this->logger->info('Starting news fetch operation', array(
                'sources_count' => count($params['sources']),
                'limit' => $params['limit']
            ));
            
            // Check rate limiting
            if ($this->rate_limiter && $this->rate_limiter->is_rate_limited('news_fetch', 5, 3600)) {
                throw new Exception('Rate limit exceeded for news fetching');
            }
            
            $results = array();
            $errors = array();
            
            // Process sources in batches for better performance
            foreach (array_chunk($params['sources'], $params['batch_size']) as $batch) {
                $batch_results = $this->process_news_batch($batch, $params);
                
                foreach ($batch_results as $source_result) {
                    if ($source_result['success']) {
                        $results = array_merge($results, $source_result['items']);
                    } else {
                        $errors[] = $source_result['error'];
                    }
                }
                
                // Brief pause between batches to be respectful to sources
                if (count($batch) >= $params['batch_size']) {
                    usleep(500000); // 0.5 second pause
                }
            }
            
            // Filter and validate results
            if ($params['filter_duplicates']) {
                $results = $this->filter_duplicate_content($results);
            }
            
            if ($params['validate_content']) {
                $results = $this->validate_fetched_content($results);
            }
            
            // Limit results
            $results = array_slice($results, 0, $params['limit']);
            
            // Cache results if requested
            if ($params['cache_results']) {
                $this->cache_fetched_news($results, $params);
            }
            
            $execution_time = (microtime(true) - $start_time) * 1000;
            
            // Record rate limit attempt
            if ($this->rate_limiter) {
                $this->rate_limiter->record_attempt('news_fetch', 3600);
            }
            
            // Update metrics
            $this->update_metrics('fetch_news', true, $execution_time, count($results));
            
            $response = array(
                'success' => true,
                'items' => $results,
                'total_found' => count($results),
                'errors' => $errors,
                'execution_time_ms' => $execution_time,
                'sources_processed' => count($params['sources']),
                'timestamp' => current_time('Y-m-d H:i:s')
            );
            
            $this->logger->info('News fetch completed successfully', array(
                'total_items' => count($results),
                'execution_time_ms' => $execution_time,
                'sources_count' => count($params['sources'])
            ));
            
            return $response;
            
        } catch (Exception $e) {
            $execution_time = (microtime(true) - $start_time) * 1000;
            
            // Update failure metrics
            $this->update_metrics('fetch_news', false, $execution_time, 0, $e->getMessage());
            
            $this->logger->error('News fetch failed', array(
                'error' => $e->getMessage(),
                'execution_time_ms' => $execution_time
            ));
            
            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'execution_time_ms' => $execution_time,
                'timestamp' => current_time('Y-m-d H:i:s')
            );
        }
    }
    
    /**
     * Process a batch of news sources
     *
     * @param array $sources News sources
     * @param array $params Parameters
     * @return array Processing results
     */
    private function process_news_batch($sources, $params) {
        $results = array();
        
        foreach ($sources as $source) {
            try {
                $source_result = $this->fetch_from_source($source, $params);
                $results[] = $source_result;
                
                // Update RSS feed metrics if we have a feed manager
                if ($this->rss_feed_manager && isset($source_result['source_id'])) {
                    $this->rss_feed_manager->update_feed_metrics(
                        $source_result['source_id'],
                        $source_result['success'],
                        count($source_result['items']),
                        $source_result['error'] ?? ''
                    );
                }
                
            } catch (Exception $e) {
                $results[] = array(
                    'success' => false,
                    'source' => $source,
                    'error' => $e->getMessage(),
                    'timestamp' => current_time('Y-m-d H:i:s')
                );
                
                $this->logger->warning('Failed to fetch from source', array(
                    'source' => $source,
                    'error' => $e->getMessage()
                ));
            }
        }
        
        return $results;
    }
    
    /**
     * Fetch news from a single source
     *
     * @param string $source Source URL or identifier
     * @param array $params Parameters
     * @return array Fetch result
     */
    private function fetch_from_source($source, $params) {
        $cache_key = 'news_fetch_' . md5($source);
        $source_id = null;
        
        // Check cache first
        if ($params['cache_results']) {
            $cached_result = $this->cache_manager->get($cache_key);
            if ($cached_result !== false) {
                $this->logger->debug('Returning cached news from source', array('source' => $source));
                return $cached_result;
            }
        }
        
        // Get source ID if we have RSS feed manager
        if ($this->rss_feed_manager && method_exists($this->rss_feed_manager, 'get_feed')) {
            // Try to find feed by URL
            $feeds = $this->rss_feed_manager->get_feeds(array('limit' => 1000));
            foreach ($feeds as $feed) {
                if ($feed['url'] === $source) {
                    $source_id = $feed['id'];
                    break;
                }
            }
        }
        
        $items = array();
        
        // Determine source type and fetch accordingly
        if ($this->is_rss_feed($source)) {
            $items = $this->fetch_rss_feed($source, $params);
        } elseif ($this->is_api_source($source)) {
            $items = $this->fetch_api_source($source, $params);
        } else {
            // Default to RSS parsing
            $items = $this->fetch_rss_feed($source, $params);
        }
        
        $result = array(
            'success' => true,
            'source' => $source,
            'source_id' => $source_id,
            'items' => $items,
            'timestamp' => current_time('Y-m-d H:i:s')
        );
        
        // Cache the result
        if ($params['cache_results']) {
            $this->cache_manager->set($cache_key, $result, $this->config['cache_duration']);
        }
        
        return $result;
    }
    
    /**
     * Fetch RSS feed content
     *
     * @param string $feed_url RSS feed URL
     * @param array $params Parameters
     * @return array Feed items
     */
    private function fetch_rss_feed($feed_url, $params) {
        $this->logger->debug('Fetching RSS feed', array('url' => $feed_url));
        
        // Use WordPress built-in RSS fetching with error handling
        $response = wp_remote_get($feed_url, array(
            'timeout' => $this->config['request_timeout'],
            'user-agent' => $this->config['user_agent'],
            'sslverify' => $this->config['enable_ssl_verify'],
            'compress' => $this->config['enable_compression']
        ));
        
        if (is_wp_error($response)) {
            throw new Exception('RSS fetch failed: ' . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            throw new Exception("HTTP error: {$response_code}");
        }
        
        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            throw new Exception('Empty response body');
        }
        
        // Parse RSS using WordPress built-in functions
        $rss = simplexml_load_string($body);
        if ($rss === false) {
            throw new Exception('Failed to parse RSS XML');
        }
        
        $items = array();
        $item_count = 0;
        
        // Handle different RSS formats (RSS 2.0, Atom, etc.)
        foreach ($rss->channel->item as $item) {
            if ($item_count >= $params['limit']) {
                break;
            }
            
            $feed_item = $this->parse_rss_item($item, $feed_url);
            if ($feed_item) {
                $items[] = $feed_item;
                $item_count++;
            }
        }
        
        // If no items found in channel, try other formats
        if (empty($items)) {
            foreach ($rss->entry as $entry) {
                if ($item_count >= $params['limit']) {
                    break;
                }
                
                $feed_item = $this->parse_atom_entry($entry, $feed_url);
                if ($feed_item) {
                    $items[] = $feed_item;
                    $item_count++;
                }
            }
        }
        
        $this->logger->debug('RSS feed parsed successfully', array(
            'url' => $feed_url,
            'items_found' => count($items)
        ));
        
        return $items;
    }
    
    /**
     * Parse RSS item
     *
     * @param SimpleXMLElement $item RSS item
     * @param string $source_url Source URL
     * @return array Parsed item
     */
    private function parse_rss_item($item, $source_url) {
        try {
            // Extract basic information
            $title = (string) $item->title;
            $link = (string) $item->link;
            $description = (string) $item->description;
            $pub_date = (string) $item->pubDate;
            
            // Clean HTML from description
            $description = wp_strip_all_tags($description);
            $description = html_entity_decode($description, ENT_QUOTES, 'UTF-8');
            
            // Extract additional metadata if available
            $author = '';
            $category = '';
            $guid = '';
            
            if (isset($item->author)) {
                $author = (string) $item->author;
            } elseif (isset($item->{'dc:creator'})) {
                $author = (string) $item->{'dc:creator'};
            }
            
            if (isset($item->category)) {
                $category = (string) $item->category;
            }
            
            if (isset($item->guid)) {
                $guid = (string) $item->guid;
            }
            
            // Parse publication date
            $timestamp = strtotime($pub_date);
            if ($timestamp === false) {
                $timestamp = time();
            }
            
            // Validate essential fields
            if (empty($title) || empty($link)) {
                return null;
            }
            
            return array(
                'title' => trim($title),
                'link' => esc_url_raw($link),
                'description' => trim($description),
                'pub_date' => date('Y-m-d H:i:s', $timestamp),
                'timestamp' => $timestamp,
                'author' => trim($author),
                'category' => trim($category),
                'guid' => trim($guid),
                'source_url' => $source_url,
                'content_length' => strlen($description),
                'fetched_at' => current_time('Y-m-d H:i:s')
            );
            
        } catch (Exception $e) {
            $this->logger->warning('Failed to parse RSS item', array(
                'source' => $source_url,
                'error' => $e->getMessage()
            ));
            return null;
        }
    }
    
    /**
     * Parse Atom entry
     *
     * @param SimpleXMLElement $entry Atom entry
     * @param string $source_url Source URL
     * @return array Parsed entry
     */
    private function parse_atom_entry($entry, $source_url) {
        try {
            $title = (string) $entry->title;
            $link = '';
            
            // Find the link
            foreach ($entry->link as $link_element) {
                $rel = (string) $link_element['rel'];
                if (empty($rel) || $rel === 'alternate') {
                    $link = (string) $link_element['href'];
                    break;
                }
            }
            
            $summary = (string) $entry->summary;
            $published = (string) $entry->published;
            $updated = (string) $entry->updated;
            
            // Use published date or updated date
            $date = !empty($published) ? $published : $updated;
            $timestamp = strtotime($date);
            if ($timestamp === false) {
                $timestamp = time();
            }
            
            // Validate essential fields
            if (empty($title) || empty($link)) {
                return null;
            }
            
            return array(
                'title' => trim($title),
                'link' => esc_url_raw($link),
                'description' => trim(wp_strip_all_tags($summary)),
                'pub_date' => date('Y-m-d H:i:s', $timestamp),
                'timestamp' => $timestamp,
                'author' => '',
                'category' => '',
                'guid' => '',
                'source_url' => $source_url,
                'content_length' => strlen($summary),
                'fetched_at' => current_time('Y-m-d H:i:s')
            );
            
        } catch (Exception $e) {
            $this->logger->warning('Failed to parse Atom entry', array(
                'source' => $source_url,
                'error' => $e->getMessage()
            ));
            return null;
        }
    }
    
    /**
     * Fetch from API source (placeholder for future implementation)
     *
     * @param string $api_source API source identifier
     * @param array $params Parameters
     * @return array API response
     */
    private function fetch_api_source($api_source, $params) {
        // Placeholder for API-based news sources
        // This could integrate with services like NewsAPI, Google News, etc.
        
        $this->logger->info('API source fetch requested', array(
            'source' => $api_source,
            'note' => 'API integration not yet implemented'
        ));
        
        return array();
    }
    
    /**
     * Check if source is RSS feed
     *
     * @param string $source Source URL or identifier
     * @return bool True if RSS feed
     */
    private function is_rss_feed($source) {
        // Check file extension
        if (preg_match('/\.(rss|xml|atom)$/i', $source)) {
            return true;
        }
        
        // Check if contains RSS-like keywords
        $rss_keywords = array('rss', 'feed', 'atom', 'news');
        $source_lower = strtolower($source);
        
        foreach ($rss_keywords as $keyword) {
            if (strpos($source_lower, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if source is API-based
     *
     * @param string $source Source identifier
     * @return bool True if API source
     */
    private function is_api_source($source) {
        $api_patterns = array(
            'newsapi',
            'google_news',
            'reddit',
            'twitter_api'
        );
        
        foreach ($api_patterns as $pattern) {
            if (stripos($source, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get configured news sources
     *
     * @return array Configured sources
     */
    private function get_configured_sources() {
        // Try to get enabled feeds from RSS Feed Manager first
        if ($this->rss_feed_manager && method_exists($this->rss_feed_manager, 'get_enabled_feed_urls')) {
            $enabled_feeds = $this->rss_feed_manager->get_enabled_feed_urls();
            if (!empty($enabled_feeds)) {
                $this->logger->debug('Using RSS Feed Manager for configured sources', array(
                    'count' => count($enabled_feeds)
                ));
                return $enabled_feeds;
            }
        }
        
        // Fallback to settings
        $options = get_option('aanp_settings', array());
        
        if (isset($options['rss_feeds']) && is_array($options['rss_feeds'])) {
            return $options['rss_feeds'];
        }
        
        // Default news sources
        return array(
            'https://feeds.bbci.co.uk/news/rss.xml',
            'https://rss.cnn.com/rss/edition.rss',
            'https://feeds.reuters.com/reuters/topNews'
        );
    }
    
    /**
     * Filter duplicate content
     *
     * @param array $items News items
     * @return array Filtered items
     */
    private function filter_duplicate_content($items) {
        $seen_titles = array();
        $seen_links = array();
        $filtered = array();
        
        foreach ($items as $item) {
            $title = strtolower(trim($item['title']));
            $link = $item['link'];
            
            // Skip if we've seen this title or link before
            if (isset($seen_titles[$title]) || isset($seen_links[$link])) {
                continue;
            }
            
            $seen_titles[$title] = true;
            $seen_links[$link] = true;
            $filtered[] = $item;
        }
        
        $this->logger->debug('Duplicate filtering completed', array(
            'original_count' => count($items),
            'filtered_count' => count($filtered),
            'duplicates_removed' => count($items) - count($filtered)
        ));
        
        return $filtered;
    }
    
    /**
     * Validate fetched content
     *
     * @param array $items News items
     * @return array Validated items
     */
    private function validate_fetched_content($items) {
        $validated = array();
        
        foreach ($items as $item) {
            // Basic validation
            if (empty($item['title']) || empty($item['link'])) {
                continue;
            }
            
            // Validate URL
            if (!filter_var($item['link'], FILTER_VALIDATE_URL)) {
                continue;
            }
            
            // Check minimum content length
            if (strlen($item['description']) < 50) {
                continue;
            }
            
            // Validate timestamp
            if (!is_numeric($item['timestamp']) || $item['timestamp'] > time() + 86400) {
                continue;
            }
            
            $validated[] = $item;
        }
        
        $this->logger->debug('Content validation completed', array(
            'original_count' => count($items),
            'validated_count' => count($validated),
            'invalid_removed' => count($items) - count($validated)
        ));
        
        return $validated;
    }
    
    /**
     * Cache fetched news
     *
     * @param array $items News items
     * @param array $params Parameters
     */
    private function cache_fetched_news($items, $params) {
        $cache_key = 'fetched_news_' . md5(serialize($params));
        $cache_data = array(
            'items' => $items,
            'params' => $params,
            'cached_at' => current_time('Y-m-d H:i:s'),
            'expires_at' => date('Y-m-d H:i:s', time() + $this->config['cache_duration'])
        );
        
        $this->cache_manager->set($cache_key, $cache_data, $this->config['cache_duration']);
    }
    
    /**
     * Update service metrics
     *
     * @param string $operation Operation name
     * @param bool $success Success status
     * @param float $execution_time Execution time in milliseconds
     * @param int $items_processed Number of items processed
     * @param string $error Error message if failed
     */
    private function update_metrics($operation, $success, $execution_time, $items_processed, $error = '') {
        if (!isset($this->metrics[$operation])) {
            $this->metrics[$operation] = array(
                'total_calls' => 0,
                'successful_calls' => 0,
                'failed_calls' => 0,
                'total_execution_time' => 0,
                'average_execution_time' => 0,
                'total_items_processed' => 0,
                'last_call' => null
            );
        }
        
        $metric = &$this->metrics[$operation];
        
        $metric['total_calls']++;
        $metric['total_execution_time'] += $execution_time;
        $metric['average_execution_time'] = $metric['total_execution_time'] / $metric['total_calls'];
        $metric['total_items_processed'] += $items_processed;
        $metric['last_call'] = array(
            'success' => $success,
            'execution_time' => $execution_time,
            'items_processed' => $items_processed,
            'error' => $error,
            'timestamp' => current_time('Y-m-d H:i:s')
        );
        
        if ($success) {
            $metric['successful_calls']++;
        } else {
            $metric['failed_calls']++;
        }
    }
    
    /**
     * Get service metrics
     *
     * @return array Service metrics
     */
    public function get_metrics() {
        return array(
            'service' => 'NewsFetchService',
            'metrics' => $this->metrics,
            'config' => $this->config,
            'memory_usage' => memory_get_usage(true),
            'timestamp' => current_time('Y-m-d H:i:s')
        );
    }
    
    /**
     * Health check method
     *
     * @return bool Health status
     */
    public function health_check() {
        try {
            // Test basic functionality
            $sources = $this->get_configured_sources();
            if (empty($sources)) {
                return false;
            }
            
            // Test cache functionality
            $test_key = 'health_check_' . time();
            $test_data = array('test' => 'value');
            $this->cache_manager->set($test_key, $test_data, 60);
            $retrieved = $this->cache_manager->get($test_key);
            
            if ($retrieved !== $test_data) {
                return false;
            }
            
            // Clean up test data
            $this->cache_manager->delete($test_key);
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('Health check failed', array(
                'error' => $e->getMessage()
            ));
            return false;
        }
    }
    
    /**
     * Scheduled news fetch
     */
    public function scheduled_news_fetch() {
        try {
            $this->logger->info('Starting scheduled news fetch');
            
            $result = $this->fetch_news(array(
                'limit' => 50,
                'cache_results' => true
            ));
            
            if ($result['success']) {
                $this->logger->info('Scheduled news fetch completed', array(
                    'items_fetched' => $result['total_found']
                ));
                
                // Trigger event for other services
                do_action('aanp_news_fetched', $result);
            }
            
        } catch (Exception $e) {
            $this->logger->error('Scheduled news fetch failed', array(
                'error' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Clean up old cached data
     */
    public function cleanup() {
        try {
            // Clear old cached news data
            $this->cache_manager->delete_by_pattern('news_fetch_');
            $this->cache_manager->delete_by_pattern('fetched_news_');
            
            $this->logger->info('NewsFetchService cleanup completed');
            
        } catch (Exception $e) {
            $this->logger->error('NewsFetchService cleanup failed', array(
                'error' => $e->getMessage()
            ));
        }
    }
}