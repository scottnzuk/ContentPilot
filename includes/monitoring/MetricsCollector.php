<?php
/**
 * Comprehensive Metrics Collector
 * 
 * Gathers and processes metrics from multiple sources including system performance,
 * WordPress application metrics, content analytics, SEO data, and API usage.
 *
 * @package AI_Auto_News_Poster
 * @subpackage Includes/Monitoring
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class MetricsCollector {
    
    /**
     * Collection interval in seconds
     */
    const DEFAULT_COLLECTION_INTERVAL = 60; // 1 minute
    
    /**
     * Cache expiry time
     */
    const CACHE_EXPIRY = 300; // 5 minutes
    
    /**
     * Metric categories
     */
    const CATEGORIES = [
        'system',
        'wordpress', 
        'content',
        'seo',
        'api',
        'user',
        'plugin'
    ];
    
    /**
     * Collected metrics storage
     */
    private $metrics = [];
    
    /**
     * Collection configuration
     */
    private $config = [];
    
    /**
     * Last collection timestamp
     */
    private $last_collection = 0;
    
    /**
     * Collection enabled flag
     */
    private $collection_enabled = false;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        static $instance = null;
        if ($instance === null) {
            $instance = new self();
        }
        return $instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Initialize metrics collector
     */
    private function init() {
        $this->load_configuration();
        $this->setup_hooks();
        $this->start_collection_scheduler();
    }
    
    /**
     * Load configuration
     */
    private function load_configuration() {
        $default_config = [
            'enabled' => true,
            'interval' => self::DEFAULT_COLLECTION_INTERVAL,
            'categories' => self::CATEGORIES,
            'retention_days' => 30,
            'cache_enabled' => true,
            'real_time_enabled' => true
        ];
        
        $saved_config = get_option('ai_news_metrics_config', []);
        $this->config = array_merge($default_config, $saved_config);
        
        $this->collection_enabled = $this->config['enabled'];
    }
    
    /**
     * Set up WordPress hooks
     */
    private function setup_hooks() {
        // AJAX handlers
        add_action('wp_ajax_ai_news_get_metrics', [$this, 'ajax_get_metrics']);
        add_action('wp_ajax_ai_news_get_dashboard_data', [$this, 'ajax_get_dashboard_data']);
        add_action('wp_ajax_ai_news_run_metrics_collection', [$this, 'ajax_run_collection']);
        
        // Scheduled collection
        add_action('ai_news_collect_metrics', [$this, 'collect_all_metrics']);
        add_action('wp_scheduled_delete', [$this, 'cleanup_old_metrics']);
        
        // Integration hooks
        add_action('post_published', [$this, 'track_content_publish'], 10, 2);
        add_action('comment_post', [$this, 'track_comment_post']);
        add_action('wp_login', [$this, 'track_user_login']);
        
        // Plugin hooks
        add_action('ai_news_api_request', [$this, 'track_api_request'], 10, 2);
        add_action('ai_news_content_generated', [$this, 'track_content_generation'], 10, 2);
        add_action('ai_news_seo_optimized', [$this, 'track_seo_optimization'], 10, 2);
    }
    
    /**
     * Start collection scheduler
     */
    private function start_collection_scheduler() {
        if (!$this->collection_enabled) {
            return;
        }
        
        // Schedule periodic collection
        if (!wp_next_scheduled('ai_news_collect_metrics')) {
            wp_schedule_event(time(), 'every_' . $this->config['interval'] . '_seconds', 'ai_news_collect_metrics');
        }
    }
    
    /**
     * Collect all metrics
     */
    public function collect_all_metrics() {
        if (!$this->collection_enabled) {
            return;
        }
        
        try {
            $start_time = microtime(true);
            
            // Collect metrics by category
            foreach ($this->config['categories'] as $category) {
                $this->collect_category_metrics($category);
            }
            
            // Process and aggregate metrics
            $this->process_metrics();
            
            // Store metrics
            $this->store_metrics();
            
            // Cache metrics for dashboard
            $this->cache_metrics();
            
            $collection_time = microtime(true) - $start_time;
            $this->record_collection_performance($collection_time);
            
            $this->last_collection = time();
            
        } catch (Exception $e) {
            error_log('AI News Metrics Collection Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Collect metrics for specific category
     */
    private function collect_category_metrics($category) {
        switch ($category) {
            case 'system':
                $this->collect_system_metrics();
                break;
            case 'wordpress':
                $this->collect_wordpress_metrics();
                break;
            case 'content':
                $this->collect_content_metrics();
                break;
            case 'seo':
                $this->collect_seo_metrics();
                break;
            case 'api':
                $this->collect_api_metrics();
                break;
            case 'user':
                $this->collect_user_metrics();
                break;
            case 'plugin':
                $this->collect_plugin_metrics();
                break;
        }
    }
    
    /**
     * Collect system performance metrics
     */
    private function collect_system_metrics() {
        $metrics = [];
        
        // Memory usage
        $memory_usage = memory_get_usage(true);
        $memory_peak = memory_get_peak_usage(true);
        $memory_limit = $this->parse_memory_limit(ini_get('memory_limit'));
        
        $metrics['memory'] = [
            'current' => $memory_usage,
            'peak' => $memory_peak,
            'limit' => $memory_limit,
            'percentage' => ($memory_usage / $memory_limit) * 100,
            'peak_percentage' => ($memory_peak / $memory_limit) * 100
        ];
        
        // CPU and load average
        if (function_exists('sys_getloadavg')) {
            $load_avg = sys_getloadavg();
            $metrics['cpu'] = [
                'load_1min' => $load_avg[0],
                'load_5min' => $load_avg[1],
                'load_15min' => $load_avg[2],
                'processes' => $this->get_process_count()
            ];
        }
        
        // Disk usage
        $metrics['disk'] = $this->get_disk_usage();
        
        // Database performance
        $metrics['database'] = $this->get_database_metrics();
        
        // Network metrics (if available)
        $metrics['network'] = $this->get_network_metrics();
        
        $this->add_metrics('system', $metrics);
    }
    
    /**
     * Collect WordPress-specific metrics
     */
    private function collect_wordpress_metrics() {
        $metrics = [];
        
        // Database stats
        global $wpdb;
        $metrics['database'] = [
            'tables' => $this->get_database_table_stats(),
            'query_count' => $wpdb->num_queries ?? 0,
            'query_time' => $wpdb->timer_stop ?? 0,
            'slow_queries' => $this->get_slow_queries()
        ];
        
        // Object cache
        if (wp_using_ext_object_cache()) {
            $metrics['cache'] = [
                'type' => 'external',
                'stats' => $this->get_object_cache_stats()
            ];
        } else {
            $metrics['cache'] = [
                'type' => 'internal',
                'transients_count' => $this->count_transients()
            ];
        }
        
        // Content statistics
        $post_counts = wp_count_posts();
        $comment_counts = wp_count_comments();
        
        $metrics['content'] = [
            'posts' => [
                'total' => $post_counts->publish ?? 0,
                'drafts' => $post_counts->draft ?? 0,
                'scheduled' => $post_counts->future ?? 0
            ],
            'comments' => [
                'total' => $comment_counts->approved ?? 0,
                'pending' => $comment_counts->moderated ?? 0,
                'spam' => $comment_counts->spam ?? 0
            ],
            'users' => count_users(),
            'media_files' => $this->get_media_count()
        ];
        
        // Transients and options
        $metrics['storage'] = [
            'transients_count' => $this->count_transients(),
            'options_size' => $this->get_options_table_size(),
            'autoload_options' => $this->count_autoload_options(),
            'cron_jobs' => $this->get_cron_jobs_count()
        ];
        
        $this->add_metrics('wordpress', $metrics);
    }
    
    /**
     * Collect content performance metrics
     */
    private function collect_content_metrics() {
        $metrics = [];
        
        // Recent content performance
        $recent_posts = get_posts([
            'numberposts' => 100,
            'post_status' => 'publish',
            'date_query' => [
                [
                    'after' => '30 days ago'
                ]
            ]
        ]);
        
        $total_views = 0;
        $total_engagement = 0;
        $content_quality_scores = [];
        
        foreach ($recent_posts as $post) {
            // Views (if using analytics plugin)
            $views = get_post_meta($post->ID, 'post_views_count', true) ?: 0;
            $total_views += $views;
            
            // Comments count
            $comments = wp_count_comments($post->ID);
            $engagement = ($comments->approved ?? 0) + ($comments->pending ?? 0);
            $total_engagement += $engagement;
            
            // Content quality score
            $quality_score = $this->calculate_content_quality_score($post);
            $content_quality_scores[] = $quality_score;
        }
        
        $metrics['performance'] = [
            'total_content' => count($recent_posts),
            'total_views' => $total_views,
            'average_views' => count($recent_posts) > 0 ? $total_views / count($recent_posts) : 0,
            'total_engagement' => $total_engagement,
            'average_engagement' => count($recent_posts) > 0 ? $total_engagement / count($recent_posts) : 0,
            'quality_score_average' => !empty($content_quality_scores) ? array_sum($content_quality_scores) / count($content_quality_scores) : 0
        ];
        
        // Content generation metrics (plugin specific)
        $metrics['generation'] = [
            'ai_generated_count' => $this->get_ai_generated_count(),
            'average_generation_time' => $this->get_average_generation_time(),
            'generation_success_rate' => $this->get_generation_success_rate()
        ];
        
        // Content scheduling and publishing
        $metrics['scheduling'] = [
            'scheduled_posts' => $this->get_scheduled_posts_count(),
            'auto_published_count' => $this->get_auto_published_count(),
            'queue_size' => $this->get_content_queue_size()
        ];
        
        $this->add_metrics('content', $metrics);
    }
    
    /**
     * Collect SEO metrics
     */
    private function collect_seo_metrics() {
        $metrics = [];
        
        // SEO plugin integration
        $seo_plugin = $this->detect_seo_plugin();
        
        if ($seo_plugin) {
            $metrics['plugin'] = [
                'name' => $seo_plugin,
                'score' => $this->get_seo_plugin_score()
            ];
        }
        
        // Content SEO analysis
        $recent_posts = get_posts([
            'numberposts' => 50,
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => '_yoast_wpseo_metadesc',
                    'compare' => 'EXISTS'
                ]
            ]
        ]);
        
        $seo_scores = [];
        $missing_meta = 0;
        
        foreach ($recent_posts as $post) {
            $seo_score = $this->calculate_seo_score($post);
            $seo_scores[] = $seo_score;
            
            // Check for missing meta descriptions
            $meta_desc = get_post_meta($post->ID, '_yoast_wpseo_metadesc', true);
            if (empty($meta_desc)) {
                $missing_meta++;
            }
        }
        
        $metrics['content_analysis'] = [
            'analyzed_content' => count($seo_scores),
            'average_seo_score' => !empty($seo_scores) ? array_sum($seo_scores) / count($seo_scores) : 0,
            'missing_meta_descriptions' => $missing_meta,
            'seo_optimized_content' => count(array_filter($seo_scores, function($score) {
                return $score >= 80;
            }))
        ];
        
        // SERP rankings (if available)
        $metrics['rankings'] = $this->get_serp_rankings();
        
        // Core Web Vitals
        $metrics['core_web_vitals'] = $this->get_core_web_vitals();
        
        // EEAT metrics
        $metrics['eeat'] = $this->calculate_eeat_metrics();
        
        $this->add_metrics('seo', $metrics);
    }
    
    /**
     * Collect API usage metrics
     */
    private function collect_api_metrics() {
        $metrics = [];
        
        // REST API usage
        global $wpdb;
        
        $api_logs_table = $wpdb->prefix . 'ai_news_api_logs';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$api_logs_table}'") == $api_logs_table) {
            $last_hour = date('Y-m-d H:i:s', strtotime('-1 hour'));
            
            $api_stats = $wpdb->get_results($wpdb->prepare(
                "SELECT 
                    COUNT(*) as total_requests,
                    SUM(CASE WHEN status_code < 400 THEN 1 ELSE 0 END) as successful_requests,
                    SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) as failed_requests,
                    AVG(response_time) as average_response_time,
                    COUNT(DISTINCT user_id) as unique_users
                 FROM {$api_logs_table} 
                 WHERE timestamp >= %s",
                $last_hour
            ));
            
            if ($api_stats) {
                $stats = $api_stats[0];
                
                $metrics['rest_api'] = [
                    'requests_per_hour' => $stats->total_requests,
                    'success_rate' => $stats->total_requests > 0 ? 
                        ($stats->successful_requests / $stats->total_requests) * 100 : 0,
                    'average_response_time' => $stats->average_response_time,
                    'unique_users' => $stats->unique_users,
                    'failed_requests' => $stats->failed_requests
                ];
            }
        }
        
        // GraphQL metrics
        $metrics['graphql'] = $this->get_graphql_metrics();
        
        // Rate limiting
        $metrics['rate_limiting'] = [
            'current_usage' => $this->get_current_api_usage(),
            'limit' => $this->get_api_rate_limit(),
            'reset_time' => $this->get_rate_limit_reset_time()
        ];
        
        // API errors
        $metrics['errors'] = [
            'recent_errors' => $this->get_recent_api_errors(),
            'error_types' => $this->get_error_type_distribution()
        ];
        
        $this->add_metrics('api', $metrics);
    }
    
    /**
     * Collect user engagement metrics
     */
    private function collect_user_metrics() {
        $metrics = [];
        
        // Active users in last 24 hours
        $active_users = $this->get_active_users_count('24 hours');
        
        // User registration patterns
        $new_users = $this->get_new_users_count('24 hours');
        
        // Login statistics
        $recent_logins = $this->get_recent_logins_count('24 hours');
        
        $metrics['engagement'] = [
            'active_users_24h' => $active_users,
            'new_users_24h' => $new_users,
            'recent_logins_24h' => $recent_logins,
            'user_retention_rate' => $this->calculate_user_retention_rate()
        ];
        
        // Content interaction
        $metrics['interactions'] = [
            'comments_last_24h' => $this->get_comments_count('24 hours'),
            'shares_last_24h' => $this->get_content_shares_count('24 hours'),
            'average_session_duration' => $this->get_average_session_duration(),
            'bounce_rate' => $this->get_bounce_rate()
        ];
        
        $this->add_metrics('user', $metrics);
    }
    
    /**
     * Collect plugin-specific metrics
     */
    private function collect_plugin_metrics() {
        $metrics = [];
        
        // AI generation service metrics
        $ai_service_metrics = $this->get_ai_service_metrics();
        
        // Content fetch service metrics
        $fetch_service_metrics = $this->get_content_fetch_metrics();
        
        // Analytics service metrics
        $analytics_metrics = $this->get_analytics_service_metrics();
        
        // Cache performance
        $cache_metrics = $this->get_cache_performance_metrics();
        
        // Queue performance
        $queue_metrics = $this->get_queue_performance_metrics();
        
        $metrics = array_merge($metrics, $ai_service_metrics, $fetch_service_metrics, 
                              $analytics_metrics, $cache_metrics, $queue_metrics);
        
        $this->add_metrics('plugin', $metrics);
    }
    
    /**
     * Process and aggregate collected metrics
     */
    private function process_metrics() {
        foreach ($this->metrics as $category => $category_metrics) {
            // Calculate trends
            $this->calculate_trends($category, $category_metrics);
            
            // Calculate aggregations
            $this->calculate_aggregations($category, $category_metrics);
            
            // Detect anomalies
            $this->detect_anomalies($category, $category_metrics);
        }
        
        // Calculate overall health score
        $this->calculate_overall_health_score();
    }
    
    /**
     * Store metrics in database
     */
    private function store_metrics() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_news_collected_metrics';
        
        // Ensure table exists
        $this->ensure_metrics_table_exists();
        
        foreach ($this->metrics as $category => $category_metrics) {
            foreach ($category_metrics as $metric_name => $metric_data) {
                $wpdb->insert(
                    $table_name,
                    [
                        'category' => $category,
                        'metric_name' => $metric_name,
                        'metric_value' => maybe_serialize($metric_data),
                        'collection_time' => current_time('mysql')
                    ],
                    ['%s', '%s', '%s', '%s']
                );
            }
        }
        
        // Clean up old metrics
        $retention_days = $this->config['retention_days'] ?? 30;
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table_name} WHERE collection_time < %s",
            $cutoff_date
        ));
    }
    
    /**
     * Cache metrics for dashboard access
     */
    private function cache_metrics() {
        if (!$this->config['cache_enabled']) {
            return;
        }
        
        $cached_data = [
            'timestamp' => time(),
            'metrics' => $this->metrics
        ];
        
        set_transient('ai_news_cached_metrics', $cached_data, self::CACHE_EXPIRY);
    }
    
    /**
     * Get cached or fresh metrics
     */
    public function get_cached_metrics($force_refresh = false) {
        if (!$force_refresh && $this->config['cache_enabled']) {
            $cached = get_transient('ai_news_cached_metrics');
            if ($cached && (time() - $cached['timestamp']) < self::CACHE_EXPIRY) {
                return $cached['metrics'];
            }
        }
        
        // Collect fresh metrics
        $this->collect_all_metrics();
        
        return $this->metrics;
    }
    
    /**
     * AJAX handler for metrics
     */
    public function ajax_get_metrics() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ai_news_dashboard_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $category = $_POST['category'] ?? 'all';
        $time_range = $_POST['time_range'] ?? '1h';
        
        $metrics = $this->get_metrics_by_category($category, $time_range);
        
        wp_send_json_success([
            'metrics' => $metrics,
            'timestamp' => time()
        ]);
    }
    
    /**
     * AJAX handler for dashboard data
     */
    public function ajax_get_dashboard_data() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ai_news_dashboard_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $dashboard_data = [
            'metrics' => $this->get_cached_metrics(),
            'activities' => $this->get_recent_activities(),
            'performance' => $this->get_performance_summary(),
            'seo' => $this->get_seo_summary()
        ];
        
        wp_send_json_success($dashboard_data);
    }
    
    /**
     * Track content publication
     */
    public function track_content_publish($post_id, $post) {
        $this->increment_counter('content_published');
        
        // Track generation time
        $start_time = get_post_meta($post_id, '_ai_generation_start_time', true);
        if ($start_time) {
            $generation_time = time() - $start_time;
            $this->record_metric('content_generation_time', $generation_time);
            delete_post_meta($post_id, '_ai_generation_start_time');
        }
    }
    
    /**
     * Track API requests
     */
    public function track_api_request($endpoint, $response_time) {
        $this->record_api_metric('total_requests', 1);
        $this->record_api_metric('response_time', $response_time);
    }
    
    /**
     * Add metrics to collection
     */
    private function add_metrics($category, $metrics) {
        if (!isset($this->metrics[$category])) {
            $this->metrics[$category] = [];
        }
        
        $this->metrics[$category] = array_merge($this->metrics[$category], $metrics);
    }
    
    /**
     * Record metric value
     */
    private function record_metric($name, $value) {
        // This would integrate with RealTimeMonitor
        if (class_exists('RealTimeMonitor')) {
            RealTimeMonitor::get_instance()->record_metric($name, $value);
        }
    }
    
    /**
     * Record API metric
     */
    private function record_api_metric($name, $value) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_news_api_logs';
        
        $wpdb->insert(
            $table_name,
            [
                'endpoint' => $name,
                'metric_value' => $value,
                'timestamp' => current_time('mysql')
            ],
            ['%s', '%s', '%s']
        );
    }
    
    /**
     * Increment counter
     */
    private function increment_counter($counter_name) {
        $current_count = get_option("ai_news_counter_{$counter_name}", 0);
        update_option("ai_news_counter_{$counter_name}", $current_count + 1);
    }
    
    // Additional utility methods for metric collection
    
    private function parse_memory_limit($limit) {
        $limit = trim($limit);
        $last = strtolower(substr($limit, -1));
        $value = (int) $limit;
        
        switch ($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }
    
    private function get_process_count() {
        if (function_exists('exec')) {
            $output = [];
            exec('ps aux | wc -l', $output);
            return (int) trim($output[0]) - 1; // Subtract header line
        }
        return 0;
    }
    
    private function get_disk_usage() {
        $upload_dir = wp_upload_dir();
        $total_space = disk_total_space($upload_dir['basedir']);
        $free_space = disk_free_space($upload_dir['basedir']);
        $used_space = $total_space - $free_space;
        
        return [
            'total' => $total_space,
            'used' => $used_space,
            'free' => $free_space,
            'percentage' => ($used_space / $total_space) * 100
        ];
    }
    
    private function get_database_metrics() {
        global $wpdb;
        
        return [
            'query_count' => $wpdb->num_queries ?? 0,
            'query_time' => $wpdb->timer_stop ?? 0,
            'connection_errors' => $this->get_connection_error_count()
        ];
    }
    
    private function get_network_metrics() {
        // This would require additional tools or APIs
        return [
            'requests_per_second' => $this->get_current_rps(),
            'average_response_time' => $this->get_average_response_time(),
            'error_rate' => $this->get_network_error_rate()
        ];
    }
    
    private function detect_seo_plugin() {
        if (function_exists('yoast_breadcrumb')) {
            return 'Yoast SEO';
        }
        if (class_exists('RankMath')) {
            return 'Rank Math';
        }
        if (defined('AIOSEO_VERSION')) {
            return 'All in One SEO';
        }
        return null;
    }
    
    private function get_slow_queries() {
        // This would integrate with MySQL slow query log
        return []; // Placeholder
    }
    
    private function get_object_cache_stats() {
        // This would get stats from Redis/Memcached
        return []; // Placeholder
    }
    
    private function get_core_web_vitals() {
        // This would get data from Google PageSpeed Insights API
        return []; // Placeholder
    }
    
    private function get_graphql_metrics() {
        // This would collect GraphQL-specific metrics
        return []; // Placeholder
    }
    
    private function calculate_content_quality_score($post) {
        // Calculate based on content length, readability, keyword density, etc.
        $content = $post->post_content;
        $title = $post->post_title;
        
        $score = 0;
        
        // Content length
        if (strlen($content) > 300) $score += 20;
        if (strlen($content) > 1000) $score += 20;
        
        // Title length
        if (strlen($title) > 30 && strlen($title) < 60) $score += 20;
        
        // Meta description
        $meta_desc = get_post_meta($post->ID, '_yoast_wpseo_metadesc', true);
        if ($meta_desc && strlen($meta_desc) > 50 && strlen($meta_desc) < 160) {
            $score += 20;
        }
        
        // Image count
        $image_count = substr_count($content, '<img');
        if ($image_count > 0) $score += 10;
        if ($image_count >= 2) $score += 10;
        
        return min($score, 100);
    }
    
    private function calculate_seo_score($post) {
        $score = 0;
        
        // Title optimization
        $title = $post->post_title;
        if (strlen($title) > 30 && strlen($title) < 60) $score += 20;
        
        // Meta description
        $meta_desc = get_post_meta($post->ID, '_yoast_wpseo_metadesc', true);
        if ($meta_desc && strlen($meta_desc) > 50 && strlen($meta_desc) < 160) {
            $score += 20;
        }
        
        // Content structure
        $content = $post->post_content;
        if (strpos($content, '<h2>') !== false) $score += 15;
        if (strpos($content, '<h3>') !== false) $score += 15;
        
        // Image optimization
        if (substr_count($content, '<img') > 0) {
            $score += 15;
            
            // Check for alt tags (basic check)
            if (strpos($content, 'alt=') !== false) {
                $score += 15;
            }
        }
        
        return min($score, 100);
    }
    
    private function get_active_users_count($time_range) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) 
             FROM {$wpdb->prefix}ai_news_user_activity 
             WHERE timestamp >= %s",
            date('Y-m-d H:i:s', strtotime("-{$time_range}"))
        ));
    }
    
    private function ensure_metrics_table_exists() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_news_collected_metrics';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            category varchar(50) NOT NULL,
            metric_name varchar(100) NOT NULL,
            metric_value longtext,
            collection_time datetime NOT NULL,
            PRIMARY KEY (id),
            KEY category (category),
            KEY metric_name (metric_name),
            KEY collection_time (collection_time)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    private function cleanup_old_metrics() {
        global $wpdb;
        
        $retention_days = $this->config['retention_days'] ?? 30;
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));
        
        $table_name = $wpdb->prefix . 'ai_news_collected_metrics';
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table_name} WHERE collection_time < %s",
            $cutoff_date
        ));
    }
    
    private function record_collection_performance($time) {
        $this->record_metric('metrics_collection_time', $time * 1000);
    }
    
    // Additional methods would be implemented here...
    // For brevity, I'm including just the key methods. Full implementation would include:
    // - All the get_* methods referenced in the code
    // - Trend calculation methods
    // - Anomaly detection
    // - Data aggregation methods
    // - And more utility methods
    
    private function get_recent_activities() {
        // Get recent activities from various sources
        return [];
    }
    
    private function get_performance_summary() {
        // Get performance summary for dashboard
        return [];
    }
    
    private function get_seo_summary() {
        // Get SEO summary for dashboard
        return [];
    }
    
    private function get_metrics_by_category($category, $time_range) {
        // Get specific metrics by category and time range
        return [];
    }
    
    // Placeholder methods for comprehensive metric collection
    private function get_database_table_stats() { return []; }
    private function get_media_count() { return 0; }
    private function count_transients() { return 0; }
    private function get_options_table_size() { return 0; }
    private function count_autoload_options() { return 0; }
    private function get_cron_jobs_count() { return 0; }
    private function get_connection_error_count() { return 0; }
    private function get_current_rps() { return 0; }
    private function get_average_response_time() { return 0; }
    private function get_network_error_rate() { return 0; }
    private function get_seo_plugin_score() { return 0; }
    private function get_serp_rankings() { return []; }
    private function calculate_eeat_metrics() { return []; }
    private function get_current_api_usage() { return 0; }
    private function get_api_rate_limit() { return 1000; }
    private function get_rate_limit_reset_time() { return 0; }
    private function get_recent_api_errors() { return []; }
    private function get_error_type_distribution() { return []; }
    private function get_new_users_count($time) { return 0; }
    private function get_recent_logins_count($time) { return 0; }
    private function calculate_user_retention_rate() { return 0; }
    private function get_comments_count($time) { return 0; }
    private function get_content_shares_count($time) { return 0; }
    private function get_average_session_duration() { return 0; }
    private function get_bounce_rate() { return 0; }
    private function get_ai_service_metrics() { return []; }
    private function get_content_fetch_metrics() { return []; }
    private function get_analytics_service_metrics() { return []; }
    private function get_cache_performance_metrics() { return []; }
    private function get_queue_performance_metrics() { return []; }
    private function get_ai_generated_count() { return 0; }
    private function get_average_generation_time() { return 0; }
    private function get_generation_success_rate() { return 0; }
    private function get_scheduled_posts_count() { return 0; }
    private function get_auto_published_count() { return 0; }
    private function get_content_queue_size() { return 0; }
    private function calculate_trends($category, $metrics) { /* Implementation needed */ }
    private function calculate_aggregations($category, $metrics) { /* Implementation needed */ }
    private function detect_anomalies($category, $metrics) { /* Implementation needed */ }
    private function calculate_overall_health_score() { /* Implementation needed */ }
    
    public function ajax_run_collection() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ai_news_dashboard_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $this->collect_all_metrics();
        
        wp_send_json_success([
            'message' => 'Metrics collection completed successfully',
            'timestamp' => time()
        ]);
    }
}

// Initialize the collector
MetricsCollector::get_instance();