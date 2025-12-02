<?php
/**
 * SERP Analysis System
 * 
 * Handles search engine ranking position tracking, competitor analysis,
 * keyword research, and SERP features monitoring.
 *
 * @package AI_Auto_News_Poster\SEO
 * @since 1.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * SERP Analyzer Class
 */
class AANP_SERP_Analyzer {
    
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
     * Service configuration
     * @var array
     */
    private $config = array();
    
    /**
     * SERP analysis metrics
     * @var array
     */
    private $metrics = array();
    
    /**
     * Supported search engines
     * @var array
     */
    private $search_engines = array();
    
    /**
     * Geographic locations for SERP tracking
     * @var array
     */
    private $locations = array();
    
    /**
     * Constructor
     *
     * @param AANP_AdvancedCacheManager $cache_manager
     */
    public function __construct(AANP_AdvancedCacheManager $cache_manager = null) {
        $this->logger = AANP_Logger::getInstance();
        $this->cache_manager = $cache_manager ?: new AANP_AdvancedCacheManager();
        
        // Initialize rate limiter
        if (class_exists('AANP_Rate_Limiter')) {
            $this->rate_limiter = new AANP_Rate_Limiter();
        }
        
        $this->init_config();
        $this->init_search_engines();
        $this->init_locations();
        $this->init_hooks();
    }
    
    /**
     * Initialize service configuration
     */
    private function init_config() {
        $options = get_option('aanp_settings', array());
        
        $this->config = array(
            'enable_serp_tracking' => isset($options['enable_serp_tracking']) ? (bool) $options['enable_serp_tracking'] : true,
            'tracking_frequency' => isset($options['serp_tracking_frequency']) ? $options['serp_tracking_frequency'] : 'daily',
            'max_keywords_tracked' => isset($options['max_serp_keywords']) ? intval($options['max_serp_keywords']) : 50,
            'competitor_domains' => isset($options['competitor_domains']) ? $options['competitor_domains'] : array(),
            'default_location' => isset($options['serp_default_location']) ? $options['serp_default_location'] : 'US',
            'default_language' => isset($options['serp_default_language']) ? $options['serp_default_language'] : 'en',
            'enable_competitor_tracking' => isset($options['enable_competitor_tracking']) ? (bool) $options['enable_competitor_tracking'] : true,
            'enable_serp_features' => isset($options['enable_serp_features']) ? (bool) $options['enable_serp_features'] : true,
            'enable_alerts' => isset($options['enable_serp_alerts']) ? (bool) $options['enable_serp_alerts'] : true,
            'alert_threshold_days' => isset($options['serp_alert_threshold_days']) ? intval($options['serp_alert_threshold_days']) : 7,
            'position_change_threshold' => isset($options['serp_position_change_threshold']) ? intval($options['serp_position_change_threshold']) : 3,
            'api_timeout' => 30,
            'retry_attempts' => 2,
            'cache_duration' => 86400, // 24 hours
            'max_results_per_query' => 100
        );
    }
    
    /**
     * Initialize search engines
     */
    private function init_search_engines() {
        $this->search_engines = array(
            'google' => array(
                'name' => 'Google',
                'base_url' => 'https://www.google.com/search',
                'available' => true,
                'api_available' => false, // Google doesn't offer free SERP API
                'scraping_supported' => true
            ),
            'bing' => array(
                'name' => 'Bing',
                'base_url' => 'https://www.bing.com/search',
                'available' => true,
                'api_available' => false,
                'scraping_supported' => true
            ),
            'yahoo' => array(
                'name' => 'Yahoo',
                'base_url' => 'https://search.yahoo.com/search',
                'available' => true,
                'api_available' => false,
                'scraping_supported' => true
            ),
            'duckduckgo' => array(
                'name' => 'DuckDuckGo',
                'base_url' => 'https://html.duckduckgo.com/html/',
                'available' => true,
                'api_available' => true,
                'scraping_supported' => true
            )
        );
    }
    
    /**
     * Initialize geographic locations
     */
    private function init_locations() {
        $this->locations = array(
            'US' => array('name' => 'United States', 'code' => 'US'),
            'GB' => array('name' => 'United Kingdom', 'code' => 'GB'),
            'CA' => array('name' => 'Canada', 'code' => 'CA'),
            'AU' => array('name' => 'Australia', 'code' => 'AU'),
            'DE' => array('name' => 'Germany', 'code' => 'DE'),
            'FR' => array('name' => 'France', 'code' => 'FR'),
            'IN' => array('name' => 'India', 'code' => 'IN'),
            'JP' => array('name' => 'Japan', 'code' => 'JP'),
            'BR' => array('name' => 'Brazil', 'code' => 'BR'),
            'ES' => array('name' => 'Spain', 'code' => 'ES')
        );
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        if ($this->config['enable_serp_tracking']) {
            add_action('aanp_update_serp_rankings', array($this, 'update_serp_rankings'));
            add_action('aanp_analyze_competitors', array($this, 'analyze_competitors'));
            
            // Schedule regular SERP analysis
            $this->schedule_serp_analysis();
        }
    }
    
    /**
     * Schedule SERP analysis
     */
    private function schedule_serp_analysis() {
        $frequency_map = array(
            'hourly' => 'hourly',
            'daily' => 'daily',
            'weekly' => 'weekly'
        );
        
        $frequency = $frequency_map[$this->config['tracking_frequency']] ?? 'daily';
        
        if (!wp_next_scheduled('aanp_update_serp_rankings')) {
            wp_schedule_event(time(), $frequency, 'aanp_update_serp_rankings');
        }
    }
    
    /**
     * Analyze keyword ranking for a domain
     *
     * @param string $keyword Keyword to analyze
     * @param string $domain Domain to track
     * @param array $parameters Analysis parameters
     * @return array Analysis results
     */
    public function analyze_keyword_ranking($keyword, $domain, $parameters = array()) {
        $start_time = microtime(true);
        
        try {
            $params = array_merge(array(
                'search_engine' => 'google',
                'location' => $this->config['default_location'],
                'language' => $this->config['default_language'],
                'include_serp_features' => $this->config['enable_serp_features'],
                'include_competitors' => $this->config['enable_competitor_tracking'],
                'max_results' => 50
            ), $parameters);
            
            $this->logger->info('Starting SERP analysis', array(
                'keyword' => $keyword,
                'domain' => $domain,
                'search_engine' => $params['search_engine']
            ));
            
            // Check rate limiting
            if ($this->rate_limiter && $this->rate_limiter->is_rate_limited('serp_analysis', 100, 3600)) {
                throw new Exception('Rate limit exceeded for SERP analysis');
            }
            
            // Check cache
            $cache_key = 'serp_analysis_' . md5($keyword . $domain . $params['search_engine'] . $params['location']);
            $cached_result = $this->cache_manager->get($cache_key);
            if ($cached_result !== false) {
                $this->logger->debug('Returning cached SERP analysis', array('cache_key' => $cache_key));
                return $cached_result;
            }
            
            // Get SERP results
            $serp_results = $this->get_serp_results($keyword, $params);
            
            // Find domain position
            $domain_position = $this->find_domain_position($serp_results, $domain);
            
            // Analyze SERP features
            $serp_features = array();
            if ($params['include_serp_features']) {
                $serp_features = $this->analyze_serp_features($serp_results);
            }
            
            // Analyze competitors
            $competitor_analysis = array();
            if ($params['include_competitors'] && !empty($this->config['competitor_domains'])) {
                $competitor_analysis = $this->analyze_competitor_positions($serp_results, $this->config['competitor_domains']);
            }
            
            // Calculate insights
            $insights = $this->generate_ranking_insights($keyword, $domain_position, $competitor_analysis);
            
            $execution_time = (microtime(true) - $start_time) * 1000;
            
            // Record rate limit attempt
            if ($this->rate_limiter) {
                $this->rate_limiter->record_attempt('serp_analysis', 3600);
            }
            
            // Update metrics
            $this->update_metrics('analyze_keyword_ranking', true, $execution_time, 1);
            
            $result = array(
                'success' => true,
                'keyword' => $keyword,
                'domain' => $domain,
                'search_engine' => $params['search_engine'],
                'location' => $params['location'],
                'position' => $domain_position,
                'serp_results' => array_slice($serp_results, 0, $params['max_results']),
                'serp_features' => $serp_features,
                'competitor_analysis' => $competitor_analysis,
                'insights' => $insights,
                'analysis_timestamp' => current_time('Y-m-d H:i:s'),
                'execution_time_ms' => $execution_time
            );
            
            // Cache result
            $this->cache_manager->set($cache_key, $result, $this->config['cache_duration']);
            
            $this->logger->info('SERP analysis completed', array(
                'keyword' => $keyword,
                'domain' => $domain,
                'position' => $domain_position,
                'execution_time_ms' => $execution_time
            ));
            
            return $result;
            
        } catch (Exception $e) {
            $execution_time = (microtime(true) - $start_time) * 1000;
            
            // Update failure metrics
            $this->update_metrics('analyze_keyword_ranking', false, $execution_time, 0, $e->getMessage());
            
            $this->logger->error('SERP analysis failed', array(
                'keyword' => $keyword,
                'domain' => $domain,
                'error' => $e->getMessage(),
                'execution_time_ms' => $execution_time
            ));
            
            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'keyword' => $keyword,
                'domain' => $domain,
                'execution_time_ms' => $execution_time,
                'timestamp' => current_time('Y-m-d H:i:s')
            );
        }
    }
    
    /**
     * Get SERP results for a keyword
     *
     * @param string $keyword Search keyword
     * @param array $params Search parameters
     * @return array SERP results
     */
    private function get_serp_results($keyword, $params) {
        $search_engine = $params['search_engine'];
        
        if (!isset($this->search_engines[$search_engine])) {
            throw new Exception("Search engine '{$search_engine}' not supported");
        }
        
        $engine_config = $this->search_engines[$search_engine];
        
        if ($engine_config['api_available']) {
            return $this->get_serp_via_api($keyword, $params, $engine_config);
        } else {
            return $this->get_serp_via_scraping($keyword, $params, $engine_config);
        }
    }
    
    /**
     * Get SERP results via API
     *
     * @param string $keyword Search keyword
     * @param array $params Search parameters
     * @param array $engine_config Search engine configuration
     * @return array SERP results
     */
    private function get_serp_via_api($keyword, $params, $engine_config) {
        // This would integrate with available APIs like SerpApi, DataForSEO, etc.
        // For now, return mock data for demonstration
        
        $mock_results = $this->generate_mock_serp_results($keyword, $params['max_results']);
        
        return $mock_results;
    }
    
    /**
     * Get SERP results via web scraping
     *
     * @param string $keyword Search keyword
     * @param array $params Search parameters
     * @param array $engine_config Search engine configuration
     * @return array SERP results
     */
    private function get_serp_via_scraping($keyword, $params, $engine_config) {
        // This would implement respectful web scraping
        // For now, return mock data for demonstration
        
        $mock_results = $this->generate_mock_serp_results($keyword, $params['max_results']);
        
        return $mock_results;
    }
    
    /**
     * Generate mock SERP results for demonstration
     *
     * @param string $keyword Search keyword
     * @param int $max_results Maximum results to return
     * @return array Mock SERP results
     */
    private function generate_mock_serp_results($keyword, $max_results) {
        $results = array();
        $domains = array(
            'wikipedia.org', 'youtube.com', 'amazon.com', 'facebook.com', 
            'twitter.com', 'linkedin.com', 'reddit.com', 'github.com',
            'stackoverflow.com', 'medium.com', 'news.ycombinator.com',
            'techcrunch.com', 'theverge.com', 'engadget.com', 'ars-technica.com'
        );
        
        for ($i = 0; $i < min($max_results, 20); $i++) {
            $domain = $domains[array_rand($domains)];
            $position = $i + 1;
            
            $results[] = array(
                'position' => $position,
                'title' => "Result {$position} for '{$keyword}' - {$domain}",
                'url' => "https://{$domain}/page{$position}",
                'snippet' => "This is a sample snippet for the search result about {$keyword}. It contains relevant information about the topic.",
                'domain' => $domain,
                'is_featured' => ($position <= 3 && rand(1, 10) > 8) // Featured snippet chance
            );
        }
        
        return $results;
    }
    
    /**
     * Find domain position in SERP results
     *
     * @param array $serp_results SERP results
     * @param string $domain Domain to find
     * @return array Position information
     */
    private function find_domain_position($serp_results, $domain) {
        $position_info = array(
            'position' => null,
            'found' => false,
            'url' => null,
            'title' => null
        );
        
        foreach ($serp_results as $result) {
            if (strpos($result['url'], $domain) !== false) {
                $position_info = array(
                    'position' => $result['position'],
                    'found' => true,
                    'url' => $result['url'],
                    'title' => $result['title']
                );
                break;
            }
        }
        
        return $position_info;
    }
    
    /**
     * Analyze SERP features
     *
     * @param array $serp_results SERP results
     * @return array SERP features analysis
     */
    private function analyze_serp_features($serp_results) {
        $features = array(
            'featured_snippet' => null,
            'people_also_ask' => false,
            'image_pack' => false,
            'video_pack' => false,
            'news_pack' => false,
            'local_pack' => false,
            'shopping_results' => false
        );
        
        // Analyze featured snippets
        foreach ($serp_results as $result) {
            if (isset($result['is_featured']) && $result['is_featured']) {
                $features['featured_snippet'] = $result;
                break;
            }
        }
        
        // Mock detection of other SERP features
        $features['people_also_ask'] = rand(1, 10) > 5;
        $features['image_pack'] = rand(1, 10) > 7;
        $features['video_pack'] = rand(1, 10) > 8;
        $features['news_pack'] = rand(1, 10) > 6;
        $features['local_pack'] = rand(1, 10) > 8;
        $features['shopping_results'] = rand(1, 10) > 7;
        
        return $features;
    }
    
    /**
     * Analyze competitor positions
     *
     * @param array $serp_results SERP results
     * @param array $competitor_domains Competitor domains
     * @return array Competitor analysis
     */
    private function analyze_competitor_positions($serp_results, $competitor_domains) {
        $analysis = array();
        
        foreach ($competitor_domains as $competitor) {
            $position = $this->find_domain_position($serp_results, $competitor);
            if ($position['found']) {
                $analysis[$competitor] = $position;
            }
        }
        
        return $analysis;
    }
    
    /**
     * Generate ranking insights
     *
     * @param string $keyword Keyword being analyzed
     * @param array $domain_position Domain position information
     * @param array $competitor_analysis Competitor analysis
     * @return array Ranking insights
     */
    private function generate_ranking_insights($keyword, $domain_position, $competitor_analysis) {
        $insights = array();
        
        // Position-based insights
        if ($domain_position['found']) {
            $position = $domain_position['position'];
            
            if ($position <= 3) {
                $insights[] = array(
                    'type' => 'positive',
                    'message' => "Great ranking! Your domain ranks in the top 3 positions for '{$keyword}'",
                    'priority' => 'high'
                );
            } elseif ($position <= 10) {
                $insights[] = array(
                    'type' => 'neutral',
                    'message' => "Good ranking. Your domain is on the first page for '{$keyword}'",
                    'priority' => 'medium'
                );
            } elseif ($position <= 20) {
                $insights[] = array(
                    'type' => 'warning',
                    'message' => "Moderate ranking. Your domain appears in top 20 for '{$keyword}'",
                    'priority' => 'medium'
                );
            } else {
                $insights[] = array(
                    'type' => 'negative',
                    'message' => "Low ranking. Your domain ranks beyond position 20 for '{$keyword}'",
                    'priority' => 'high'
                );
            }
        } else {
            $insights[] = array(
                'type' => 'negative',
                'message' => "No ranking found. Your domain doesn't appear in top results for '{$keyword}'",
                'priority' => 'high'
            );
        }
        
        // Competitor insights
        if (!empty($competitor_analysis)) {
            $competitor_positions = array_column($competitor_analysis, 'position');
            $avg_competitor_position = array_sum($competitor_positions) / count($competitor_positions);
            
            if ($domain_position['found'] && $domain_position['position'] < $avg_competitor_position) {
                $insights[] = array(
                    'type' => 'positive',
                    'message' => "You're outranking competitors for '{$keyword}'",
                    'priority' => 'medium'
                );
            }
        }
        
        return $insights;
    }
    
    /**
     * Update SERP rankings for tracked keywords
     */
    public function update_serp_rankings() {
        try {
            $this->logger->info('Starting scheduled SERP ranking update');
            
            // Get tracked keywords
            $tracked_keywords = $this->get_tracked_keywords();
            $domain = $this->get_tracked_domain();
            
            if (empty($tracked_keywords) || empty($domain)) {
                $this->logger->info('No keywords or domain to track');
                return;
            }
            
            $results = array();
            
            foreach ($tracked_keywords as $keyword) {
                try {
                    $analysis = $this->analyze_keyword_ranking($keyword, $domain);
                    $results[$keyword] = $analysis;
                    
                    // Store historical data
                    $this->store_ranking_data($keyword, $domain, $analysis);
                    
                } catch (Exception $e) {
                    $this->logger->error('Failed to update ranking for keyword', array(
                        'keyword' => $keyword,
                        'error' => $e->getMessage()
                    ));
                }
            }
            
            // Check for alerts
            if ($this->config['enable_alerts']) {
                $this->check_ranking_alerts($results);
            }
            
            $this->logger->info('SERP ranking update completed', array(
                'keywords_updated' => count($results)
            ));
            
        } catch (Exception $e) {
            $this->logger->error('SERP ranking update failed', array(
                'error' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Analyze competitors across multiple keywords
     *
     * @param array $keywords Keywords to analyze
     * @param array $competitor_domains Competitor domains
     * @param array $parameters Analysis parameters
     * @return array Competitor analysis results
     */
    public function analyze_competitors($keywords = array(), $competitor_domains = array(), $parameters = array()) {
        $keywords = !empty($keywords) ? $keywords : $this->get_tracked_keywords();
        $competitor_domains = !empty($competitor_domains) ? $competitor_domains : $this->config['competitor_domains'];
        
        if (empty($keywords) || empty($competitor_domains)) {
            throw new Exception('Keywords and competitor domains are required');
        }
        
        $analysis_results = array();
        
        foreach ($keywords as $keyword) {
            $keyword_analysis = array(
                'keyword' => $keyword,
                'competitors' => array()
            );
            
            foreach ($competitor_domains as $competitor) {
                try {
                    $result = $this->analyze_keyword_ranking($keyword, $competitor, $parameters);
                    $keyword_analysis['competitors'][$competitor] = $result;
                } catch (Exception $e) {
                    $keyword_analysis['competitors'][$competitor] = array(
                        'success' => false,
                        'error' => $e->getMessage()
                    );
                }
            }
            
            $analysis_results[] = $keyword_analysis;
        }
        
        return $analysis_results;
    }
    
    /**
     * Get keyword suggestions for content optimization
     *
     * @param string $content Content to analyze for keyword suggestions
     * @param array $parameters Suggestion parameters
     * @return array Keyword suggestions
     */
    public function get_keyword_suggestions($content, $parameters = array()) {
        $params = array_merge(array(
            'max_suggestions' => 10,
            'include_long_tail' => true,
            'include_related' => true,
            'min_search_volume' => 100
        ), $parameters);
        
        // This would integrate with keyword research APIs
        // For now, return basic suggestions based on content analysis
        
        $suggestions = $this->extract_keywords_from_content($content);
        
        // Filter and rank suggestions
        $filtered_suggestions = array();
        foreach ($suggestions as $suggestion) {
            if ($suggestion['volume'] >= $params['min_search_volume']) {
                $filtered_suggestions[] = $suggestion;
            }
        }
        
        // Sort by relevance and volume
        usort($filtered_suggestions, function($a, $b) {
            return $b['score'] - $a['score'];
        });
        
        return array_slice($filtered_suggestions, 0, $params['max_suggestions']);
    }
    
    /**
     * Extract keywords from content
     *
     * @param string $content Content to analyze
     * @return array Extracted keywords
     */
    private function extract_keywords_from_content($content) {
        // Simple keyword extraction
        $words = preg_split('/\s+/', strtolower($content));
        $stop_words = array('the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by');
        
        $keyword_freq = array();
        foreach ($words as $word) {
            $word = preg_replace('/[^a-zA-Z]/', '', $word);
            if (strlen($word) > 3 && !in_array($word, $stop_words)) {
                $keyword_freq[$word] = isset($keyword_freq[$word]) ? $keyword_freq[$word] + 1 : 1;
            }
        }
        
        $suggestions = array();
        foreach ($keyword_freq as $keyword => $frequency) {
            $suggestions[] = array(
                'keyword' => $keyword,
                'frequency' => $frequency,
                'volume' => rand(100, 10000), // Mock search volume
                'difficulty' => rand(1, 100), // Mock difficulty score
                'score' => $frequency * (rand(50, 100) / 100) // Combined score
            );
        }
        
        return $suggestions;
    }
    
    /**
     * Get tracked keywords from settings
     *
     * @return array Tracked keywords
     */
    private function get_tracked_keywords() {
        $options = get_option('aanp_settings', array());
        return isset($options['serp_tracked_keywords']) ? $options['serp_tracked_keywords'] : array();
    }
    
    /**
     * Get tracked domain from settings
     *
     * @return string Tracked domain
     */
    private function get_tracked_domain() {
        $options = get_option('aanp_settings', array());
        return isset($options['serp_tracked_domain']) ? $options['serp_tracked_domain'] : parse_url(home_url(), PHP_URL_HOST);
    }
    
    /**
     * Store ranking data for historical tracking
     *
     * @param string $keyword Keyword
     * @param string $domain Domain
     * @param array $analysis Analysis results
     */
    private function store_ranking_data($keyword, $domain, $analysis) {
        // Store in WordPress options for now (in production, use custom tables)
        $history_key = "serp_history_{$domain}_{$keyword}";
        $history = get_option($history_key, array());
        
        $entry = array(
            'timestamp' => current_time('Y-m-d H:i:s'),
            'position' => $analysis['position']['position'],
            'url' => $analysis['position']['url'],
            'search_engine' => $analysis['search_engine'],
            'location' => $analysis['location']
        );
        
        $history[] = $entry;
        
        // Keep only last 100 entries
        if (count($history) > 100) {
            $history = array_slice($history, -100);
        }
        
        update_option($history_key, $history, false);
    }
    
    /**
     * Check for ranking alerts
     *
     * @param array $analysis_results Analysis results
     */
    private function check_ranking_alerts($analysis_results) {
        foreach ($analysis_results as $keyword => $result) {
            if (!$result['success'] || !$result['position']['found']) {
                continue;
            }
            
            $current_position = $result['position']['position'];
            $historical_data = $this->get_historical_ranking_data($keyword, $result['domain'] ?? $this->get_tracked_domain());
            
            if (!empty($historical_data)) {
                $previous_position = $historical_data[count($historical_data) - 2]['position'] ?? $current_position;
                $position_change = $current_position - $previous_position;
                
                if (abs($position_change) >= $this->config['position_change_threshold']) {
                    $this->trigger_ranking_alert($keyword, $current_position, $position_change);
                }
            }
        }
    }
    
    /**
     * Get historical ranking data
     *
     * @param string $keyword Keyword
     * @param string $domain Domain
     * @return array Historical data
     */
    private function get_historical_ranking_data($keyword, $domain) {
        $history_key = "serp_history_{$domain}_{$keyword}";
        return get_option($history_key, array());
    }
    
    /**
     * Trigger ranking alert
     *
     * @param string $keyword Keyword
     * @param int $current_position Current position
     * @param int $position_change Position change
     */
    private function trigger_ranking_alert($keyword, $current_position, $position_change) {
        $change_type = $position_change > 0 ? 'increase' : 'decrease';
        $change_direction = $position_change > 0 ? 'up' : 'down';
        
        $message = "Ranking alert for '{$keyword}': Position {$change_direction} from {$current_position + $position_change} to {$current_position} ({$change_type})";
        
        // Log the alert
        $this->logger->warning('Ranking position changed', array(
            'keyword' => $keyword,
            'current_position' => $current_position,
            'position_change' => $position_change,
            'message' => $message
        ));
        
        // Trigger WordPress action for external integrations
        do_action('aanp_serp_alert', $keyword, $current_position, $position_change, $message);
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
     * Get available search engines
     *
     * @return array Available search engines
     */
    public function get_available_search_engines() {
        return $this->search_engines;
    }
    
    /**
     * Get available locations
     *
     * @return array Available locations
     */
    public function get_available_locations() {
        return $this->locations;
    }
    
    /**
     * Get service metrics
     *
     * @return array Service metrics
     */
    public function get_metrics() {
        return array(
            'service' => 'SERPAnalyzer',
            'metrics' => $this->metrics,
            'config' => $this->config,
            'available_search_engines' => array_keys($this->search_engines),
            'available_locations' => array_keys($this->locations),
            'tracked_keywords_count' => count($this->get_tracked_keywords()),
            'tracked_competitors_count' => count($this->config['competitor_domains']),
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
            // Test search engine availability
            foreach ($this->search_engines as $engine => $config) {
                if (!$config['available']) {
                    return false;
                }
            }
            
            // Test cache functionality
            $test_key = 'serp_health_check_' . time();
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
            $this->logger->error('SERPAnalyzer health check failed', array(
                'error' => $e->getMessage()
            ));
            return false;
        }
    }
    
    /**
     * Cleanup service resources
     */
    public function cleanup() {
        try {
            // Clear SERP analysis cache
            $this->cache_manager->delete_by_pattern('serp_analysis_');
            $this->cache_manager->delete_by_pattern('serp_history_');
            
            $this->logger->info('SERPAnalyzer cleanup completed');
            
        } catch (Exception $e) {
            $this->logger->error('SERPAnalyzer cleanup failed', array(
                'error' => $e->getMessage()
            ));
        }
    }
}