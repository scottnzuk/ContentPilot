<?php
/**
 * Performance Analytics Collector
 *
 * Collects performance metrics, response times, throughput data,
 * and system performance indicators.
 *
 * @package AI_Auto_News_Poster\Analytics
 * @since 1.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Performance Analytics Collector Class
 */
class AANP_Performance_Collector {
    
    /**
     * Logger instance
     * @var AANP_Logger
     */
    private $logger;
    
    /**
     * Constructor
     *
     * @param AANP_Logger $logger
     */
    public function __construct(AANP_Logger $logger = null) {
        $this->logger = $logger ?: AANP_Logger::getInstance();
    }
    
    /**
     * Collect performance metrics
     *
     * @param array $params Collection parameters
     * @return array Collected metrics
     */
    public function collect($params = array()) {
        try {
            $metrics = array();
            
            // WordPress performance metrics
            $metrics = array_merge($metrics, $this->collect_wordpress_performance());
            
            // Database performance metrics
            $metrics = array_merge($metrics, $this->collect_database_performance());
            
            // API performance metrics
            $metrics = array_merge($metrics, $this->collect_api_performance());
            
            // Cache performance metrics
            $metrics = array_merge($metrics, $this->collect_cache_performance());
            
            // Service performance metrics
            $metrics = array_merge($metrics, $this->collect_service_performance());
            
            $summary = $this->calculate_summary($metrics);
            
            $this->logger->debug('Performance metrics collected', array(
                'metrics_count' => count($metrics),
                'summary' => $summary
            ));
            
            return array(
                'success' => true,
                'metrics' => $metrics,
                'summary' => $summary,
                'collected_at' => current_time('Y-m-d H:i:s')
            );
            
        } catch (Exception $e) {
            $this->logger->error('Performance metrics collection failed', array(
                'error' => $e->getMessage()
            ));
            
            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'metrics' => array(),
                'summary' => array()
            );
        }
    }
    
    /**
     * Collect WordPress performance metrics
     *
     * @return array WordPress performance metrics
     */
    private function collect_wordpress_performance() {
        $metrics = array();
        
        // Query performance
        $metrics['wp_query_count'] = $this->get_query_count();
        $metrics['wp_query_time'] = $this->get_query_time();
        
        // Memory usage
        $metrics['memory_usage_bytes'] = memory_get_usage(true);
        $metrics['memory_peak_bytes'] = memory_get_peak_usage(true);
        $metrics['memory_limit_bytes'] = $this->parse_size(ini_get('memory_limit'));
        
        // Page generation time
        $metrics['page_generation_time'] = $this->get_page_generation_time();
        
        // Object cache hits
        $metrics['object_cache_hits'] = wp_cache_get('stats') ? 1 : 0;
        
        // Transients usage
        global $wpdb;
        $transient_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_%'"
        );
        $metrics['transient_count'] = intval($transient_count);
        
        return $metrics;
    }
    
    /**
     * Collect database performance metrics
     *
     * @return array Database performance metrics
     */
    private function collect_database_performance() {
        global $wpdb;
        
        $metrics = array();
        
        // Database size
        $metrics['db_size_mb'] = $this->get_database_size();
        
        // Table counts
        $metrics['wp_posts_count'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts}");
        $metrics['wp_options_count'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options}");
        
        // Slow query detection
        $metrics['slow_queries_detected'] = $this->detect_slow_queries();
        
        // Index usage
        $metrics['index_usage_score'] = $this->calculate_index_usage_score();
        
        return $metrics;
    }
    
    /**
     * Collect API performance metrics
     *
     * @return array API performance metrics
     */
    private function collect_api_performance() {
        $metrics = array();
        
        // HTTP response times
        $metrics['avg_http_response_time'] = $this->get_average_http_response_time();
        
        // API calls count
        $metrics['api_calls_count'] = $this->get_api_calls_count();
        
        // API error rate
        $metrics['api_error_rate'] = $this->get_api_error_rate();
        
        // External service health
        $metrics['external_services_healthy'] = $this->check_external_services_health();
        
        return $metrics;
    }
    
    /**
     * Collect cache performance metrics
     *
     * @return array Cache performance metrics
     */
    private function collect_cache_performance() {
        $metrics = array();
        
        // Object cache
        $metrics['object_cache_enabled'] = wp_using_ext_object_cache() ? 1 : 0;
        $metrics['object_cache_hit_ratio'] = $this->get_object_cache_hit_ratio();
        
        // Page cache
        $metrics['page_cache_enabled'] = $this->is_page_cache_enabled() ? 1 : 0;
        
        // Transients cache
        $metrics['transient_cache_size'] = $this->get_transient_cache_size();
        
        return $metrics;
    }
    
    /**
     * Collect service performance metrics
     *
     * @return array Service performance metrics
     */
    private function collect_service_performance() {
        $metrics = array();
        
        // AANP service performance
        if (class_exists('AANP_ServiceOrchestrator')) {
            $orchestrator = new AANP_ServiceOrchestrator();
            $service_health = $orchestrator->get_services_health();
            
            $metrics['services_healthy_count'] = count(array_filter($service_health));
            $metrics['services_total_count'] = count($service_health);
            $metrics['services_health_ratio'] = $metrics['services_total_count'] > 0 
                ? $metrics['services_healthy_count'] / $metrics['services_total_count'] 
                : 0;
        }
        
        // Plugin loading performance
        $metrics['plugin_load_time'] = $this->get_plugin_load_time();
        
        // Queue processing performance
        $metrics['queue_processing_rate'] = $this->get_queue_processing_rate();
        
        return $metrics;
    }
    
    /**
     * Get WordPress query count
     *
     * @return int Query count
     */
    private function get_query_count() {
        global $wpdb;
        return defined('SAVEQUERIES') && SAVEQUERIES ? count($wpdb->queries) : 0;
    }
    
    /**
     * Get WordPress query time
     *
     * @return float Query time in seconds
     */
    private function get_query_time() {
        global $wpdb;
        if (!defined('SAVEQUERIES') || !SAVEQUERIES) {
            return 0;
        }
        
        $total_time = 0;
        foreach ($wpdb->queries as $query) {
            $total_time += $query[1];
        }
        
        return $total_time;
    }
    
    /**
     * Get page generation time
     *
     * @return float Page generation time in seconds
     */
    private function get_page_generation_time() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return defined('END_TIMER') ? END_TIMER : 0;
        }
        
        // Fallback to a simple timer
        return isset($GLOBALS['page_generation_start']) 
            ? microtime(true) - $GLOBALS['page_generation_start']
            : 0;
    }
    
    /**
     * Get database size
     *
     * @return float Database size in MB
     */
    private function get_database_size() {
        global $wpdb;
        
        $tables = $wpdb->get_results("SHOW TABLE STATUS");
        $size = 0;
        
        foreach ($tables as $table) {
            $size += $table->Data_length + $table->Index_length;
        }
        
        return round($size / (1024 * 1024), 2);
    }
    
    /**
     * Detect slow queries
     *
     * @return int Number of slow queries detected
     */
    private function detect_slow_queries() {
        global $wpdb;
        
        if (!defined('SAVEQUERIES') || !SAVEQUERIES) {
            return 0;
        }
        
        $slow_queries = 0;
        $slow_threshold = 1.0; // 1 second
        
        foreach ($wpdb->queries as $query) {
            if ($query[1] > $slow_threshold) {
                $slow_queries++;
            }
        }
        
        return $slow_queries;
    }
    
    /**
     * Calculate index usage score
     *
     * @return float Index usage score (0-100)
     */
    private function calculate_index_usage_score() {
        global $wpdb;
        
        // This is a simplified calculation
        // In a real implementation, you'd analyze EXPLAIN output
        $tables_with_indexes = 0;
        $total_tables = 0;
        
        $tables = $wpdb->get_results("SHOW TABLE STATUS");
        
        foreach ($tables as $table) {
            if (strpos($table->Name, $wpdb->prefix) === 0) {
                $total_tables++;
                
                // Check if table has indexes
                $indexes = $wpdb->get_results("SHOW INDEX FROM {$table->Name}");
                if (!empty($indexes)) {
                    $tables_with_indexes++;
                }
            }
        }
        
        return $total_tables > 0 ? ($tables_with_indexes / $total_tables) * 100 : 100;
    }
    
    /**
     * Get average HTTP response time
     *
     * @return float Average HTTP response time in seconds
     */
    private function get_average_http_response_time() {
        // This would typically be calculated from actual HTTP request logs
        // For now, return a placeholder value
        return 0.5; // 500ms average
    }
    
    /**
     * Get API calls count
     *
     * @return int API calls count
     */
    private function get_api_calls_count() {
        // This would track actual API calls made by the plugin
        return 0; // Placeholder
    }
    
    /**
     * Get API error rate
     *
     * @return float API error rate (0-1)
     */
    private function get_api_error_rate() {
        // This would track actual API errors
        return 0.02; // 2% error rate
    }
    
    /**
     * Check external services health
     *
     * @return int Number of healthy external services
     */
    private function check_external_services_health() {
        $services = array('openai', 'anthropic', 'stability');
        $healthy = 0;
        
        foreach ($services as $service) {
            if ($this->is_service_healthy($service)) {
                $healthy++;
            }
        }
        
        return $healthy;
    }
    
    /**
     * Check if a specific service is healthy
     *
     * @param string $service Service name
     * @return bool True if healthy
     */
    private function is_service_healthy($service) {
        // This would perform actual health checks
        // For now, return true for most services
        return true;
    }
    
    /**
     * Get object cache hit ratio
     *
     * @return float Cache hit ratio (0-1)
     */
    private function get_object_cache_hit_ratio() {
        if (!wp_using_ext_object_cache()) {
            return 0;
        }
        
        // This would require cache statistics
        // For now, return a reasonable estimate
        return 0.8; // 80% hit ratio
    }
    
    /**
     * Check if page cache is enabled
     *
     * @return bool True if page cache is enabled
     */
    private function is_page_cache_enabled() {
        // Check for common caching plugins
        $caching_plugins = array('w3-total-cache', 'wp-super-cache', 'wp-rocket');
        
        foreach ($caching_plugins as $plugin) {
            if (is_plugin_active($plugin . '/' . $plugin . '.php')) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get transient cache size
     *
     * @return int Number of transients
     */
    private function get_transient_cache_size() {
        global $wpdb;
        
        return intval($wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_%'"
        ));
    }
    
    /**
     * Get plugin load time
     *
     * @return float Plugin load time in seconds
     */
    private function get_plugin_load_time() {
        // This would measure actual plugin loading time
        return 0.1; // 100ms average
    }
    
    /**
     * Get queue processing rate
     *
     * @return float Queue processing rate (items per minute)
     */
    private function get_queue_processing_rate() {
        // This would track actual queue processing
        return 5.0; // 5 items per minute average
    }
    
    /**
     * Calculate performance summary
     *
     * @param array $metrics Individual metrics
     * @return array Performance summary
     */
    private function calculate_summary($metrics) {
        $summary = array(
            'total_operations' => $metrics['wp_query_count'] ?? 0,
            'successful_operations' => 0,
            'failed_operations' => $metrics['slow_queries_detected'] ?? 0,
            'average_response_time' => ($metrics['wp_query_time'] ?? 0) * 1000, // Convert to ms
            'total_items_processed' => $metrics['api_calls_count'] ?? 0,
            'error_rate' => ($metrics['slow_queries_detected'] ?? 0) / max(1, $metrics['wp_query_count'] ?? 1) * 100,
            'performance_score' => $this->calculate_performance_score($metrics)
        );
        
        return $summary;
    }
    
    /**
     * Calculate overall performance score
     *
     * @param array $metrics Performance metrics
     * @return int Performance score (0-100)
     */
    private function calculate_performance_score($metrics) {
        $score = 100;
        
        // Deduct for high query count
        if (($metrics['wp_query_count'] ?? 0) > 100) {
            $score -= min(20, ($metrics['wp_query_count'] - 100) / 10);
        }
        
        // Deduct for slow queries
        if (($metrics['slow_queries_detected'] ?? 0) > 0) {
            $score -= min(30, ($metrics['slow_queries_detected'] ?? 0) * 5);
        }
        
        // Deduct for high memory usage
        $memory_usage_percent = (($metrics['memory_usage_bytes'] ?? 0) / ($metrics['memory_limit_bytes'] ?? 1)) * 100;
        if ($memory_usage_percent > 80) {
            $score -= min(20, ($memory_usage_percent - 80) / 2);
        }
        
        // Deduct for poor cache performance
        if (($metrics['object_cache_hit_ratio'] ?? 0) < 0.7) {
            $score -= min(15, (0.7 - $metrics['object_cache_hit_ratio']) * 50);
        }
        
        return max(0, min(100, intval($score)));
    }
    
    /**
     * Parse size string to bytes
     *
     * @param string $size Size string
     * @return int Size in bytes
     */
    private function parse_size($size) {
        $size = trim($size);
        $last = strtolower(substr($size, -1));
        $size = (int) $size;
        
        switch ($last) {
            case 'g':
                $size *= 1024;
            case 'm':
                $size *= 1024;
            case 'k':
                $size *= 1024;
        }
        
        return $size;
    }
    
    /**
     * Health check method
     *
     * @return bool Health status
     */
    public function health_check() {
        try {
            // Test database connection
            global $wpdb;
            $test_result = $wpdb->get_var('SELECT 1');
            
            if ($test_result !== '1') {
                return false;
            }
            
            // Test memory availability
            if (memory_get_usage(true) >= $this->parse_size(ini_get('memory_limit')) * 0.9) {
                return false;
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('PerformanceCollector health check failed', array(
                'error' => $e->getMessage()
            ));
            return false;
        }
    }
}