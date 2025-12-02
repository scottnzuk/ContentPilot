<?php
/**
 * Real-Time Performance Monitor
 * 
 * Provides live performance tracking, system monitoring, and real-time metrics collection
 * for the ContentPilot plugin with advanced analytics and alerting capabilities.
 *
 * @package ContentPilot
 * @subpackage Includes/Monitoring
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class RealTimeMonitor {
    
    /**
     * Monitor instance (singleton)
     */
    private static $instance = null;
    
    /**
     * Monitoring data storage
     */
    private $monitoring_data = [];
    
    /**
     * Performance thresholds
     */
    private $thresholds = [
        'response_time' => 2000,      // 2 seconds
        'memory_usage' => 128,        // 128MB
        'cpu_usage' => 80,           // 80%
        'error_rate' => 5,           // 5%
        'concurrent_requests' => 50   // 50 requests
    ];
    
    /**
     * Active monitoring sessions
     */
    private $active_sessions = [];
    
    /**
     * Real-time metrics cache
     */
    private $metrics_cache = [];
    
    /**
     * Monitoring enabled flag
     */
    private $monitoring_enabled = false;
    
    /**
     * Cache expiry time (seconds)
     */
    private $cache_expiry = 300; // 5 minutes
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Initialize monitoring system
     */
    private function init() {
        // Load monitoring configuration
        $this->load_monitoring_config();
        
        // Set up hooks
        $this->setup_hooks();
        
        // Initialize monitoring session
        $this->start_monitoring_session();
        
        // Set up cleanup schedule
        $this->schedule_cleanup();
    }
    
    /**
     * Load monitoring configuration
     */
    private function load_monitoring_config() {
        $config = get_option('ai_news_monitoring_config', []);
        
        // Merge with defaults
        $this->thresholds = array_merge($this->thresholds, $config['thresholds'] ?? []);
        
        // Set cache expiry
        $this->cache_expiry = $config['cache_expiry'] ?? 300;
        
        // Set monitoring enabled
        $this->monitoring_enabled = $config['enabled'] ?? true;
    }
    
    /**
     * Set up WordPress hooks
     */
    private function setup_hooks() {
        // Start performance tracking
        add_action('init', [$this, 'start_performance_tracking'], 1);
        
        // End performance tracking
        add_action('wp_die_handler', [$this, 'end_performance_tracking']);
        
        // AJAX handlers for real-time data
        add_action('wp_ajax_ai_news_get_realtime_data', [$this, 'ajax_get_realtime_data']);
        add_action('wp_ajax_ai_news_start_monitoring', [$this, 'ajax_start_monitoring']);
        add_action('wp_ajax_ai_news_stop_monitoring', [$this, 'ajax_stop_monitoring']);
        
        // WebSocket handlers
        add_action('wp_ajax_ai_news_websocket', [$this, 'handle_websocket_connection']);
        add_action('wp_ajax_nopriv_ai_news_websocket', [$this, 'handle_websocket_connection']);
        
        // Performance alerts
        add_action('ai_news_performance_alert', [$this, 'handle_performance_alert']);
        
        // Database optimization
        add_action('wp_scheduled_delete', [$this, 'cleanup_old_monitoring_data']);
    }
    
    /**
     * Start monitoring session
     */
    public function start_monitoring_session() {
        if (!$this->monitoring_enabled) {
            return false;
        }
        
        $session_id = $this->generate_session_id();
        $user_id = get_current_user_id();
        
        $this->active_sessions[$session_id] = [
            'id' => $session_id,
            'start_time' => time(),
            'user_id' => $user_id,
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'page_url' => $_SERVER['REQUEST_URI'] ?? '',
            'memory_usage_start' => memory_get_usage(true),
            'peak_memory_start' => memory_get_peak_usage(true),
            'request_count' => 0,
            'error_count' => 0,
            'performance_data' => []
        ];
        
        // Store session in database for persistence
        $this->store_monitoring_session($this->active_sessions[$session_id]);
        
        return $session_id;
    }
    
    /**
     * Start performance tracking for current request
     */
    public function start_performance_tracking() {
        if (!is_admin() && !$this->should_track_frontend()) {
            return;
        }
        
        $session_id = $this->start_monitoring_session();
        
        if ($session_id) {
            // Record initial metrics
            $this->record_metric('start_time', microtime(true), $session_id);
            $this->record_metric('memory_usage', memory_get_usage(true), $session_id);
            $this->record_metric('peak_memory', memory_get_peak_usage(true), $session_id);
            
            // Start query monitoring if available
            if (function_exists('query_monitor')) {
                add_action('shutdown', function() use ($session_id) {
                    $this->record_query_performance($session_id);
                });
            }
        }
    }
    
    /**
     * End performance tracking
     */
    public function end_performance_tracking() {
        $this->finalize_monitoring_session();
    }
    
    /**
     * Record performance metric
     */
    public function record_metric($metric_name, $value, $session_id = null, $context = []) {
        if (!$session_id && !$this->get_current_session_id()) {
            return false;
        }
        
        $session_id = $session_id ?: $this->get_current_session_id();
        
        if (!isset($this->active_sessions[$session_id])) {
            return false;
        }
        
        $timestamp = microtime(true);
        $metric_data = [
            'name' => $metric_name,
            'value' => $value,
            'timestamp' => $timestamp,
            'context' => $context
        ];
        
        // Store in active session
        $this->active_sessions[$session_id]['performance_data'][] = $metric_data;
        
        // Store in persistent storage
        $this->store_metric($session_id, $metric_data);
        
        // Check for alerts
        $this->check_metric_threshold($metric_name, $value, $session_id);
        
        // Update cache
        $this->update_metrics_cache($metric_name, $value);
        
        return true;
    }
    
    /**
     * Record memory usage
     */
    public function record_memory_usage($session_id = null) {
        $current_memory = memory_get_usage(true);
        $peak_memory = memory_get_peak_usage(true);
        $memory_limit = $this->parse_memory_limit(ini_get('memory_limit'));
        
        $this->record_metric('current_memory', $current_memory, $session_id);
        $this->record_metric('peak_memory', $peak_memory, $session_id);
        $this->record_metric('memory_percentage', ($current_memory / $memory_limit) * 100, $session_id);
        $this->record_metric('peak_memory_percentage', ($peak_memory / $memory_limit) * 100, $session_id);
        
        return [
            'current' => $current_memory,
            'peak' => $peak_memory,
            'limit' => $memory_limit,
            'current_percentage' => ($current_memory / $memory_limit) * 100,
            'peak_percentage' => ($peak_memory / $memory_limit) * 100
        ];
    }
    
    /**
     * Record response time
     */
    public function record_response_time($response_time, $session_id = null, $context = []) {
        $this->record_metric('response_time', $response_time * 1000, $session_id, $context); // Convert to milliseconds
        
        // Calculate response time percentage of threshold
        $threshold = $this->thresholds['response_time'];
        $percentage = ($response_time * 1000) / $threshold * 100;
        $this->record_metric('response_time_percentage', $percentage, $session_id);
        
        return $response_time;
    }
    
    /**
     * Record database query performance
     */
    public function record_query_performance($session_id = null, $query_time = null, $query_count = null, $slow_queries = []) {
        if ($query_time !== null) {
            $this->record_metric('query_time_total', $query_time, $session_id);
            $this->record_metric('query_time_average', $query_time / max($query_count, 1), $session_id);
        }
        
        if ($query_count !== null) {
            $this->record_metric('query_count', $query_count, $session_id);
        }
        
        if (!empty($slow_queries)) {
            $this->record_metric('slow_query_count', count($slow_queries), $session_id);
            $this->record_metric('slow_queries', $slow_queries, $session_id);
        }
    }
    
    /**
     * Record error
     */
    public function record_error($error_message, $error_type = 'general', $session_id = null, $context = []) {
        if (!$session_id) {
            $session_id = $this->get_current_session_id();
        }
        
        $error_data = [
            'message' => $error_message,
            'type' => $error_type,
            'timestamp' => time(),
            'context' => $context,
            'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5)
        ];
        
        // Increment error count
        if (isset($this->active_sessions[$session_id])) {
            $this->active_sessions[$session_id]['error_count']++;
        }
        
        $this->record_metric('error', $error_data, $session_id);
        
        // Log error
        error_log("ContentPilot Monitor Error: {$error_message}");
        
        return true;
    }
    
    /**
     * Get real-time metrics
     */
    public function get_realtime_metrics($session_id = null, $time_range = '5m') {
        $session_id = $session_id ?: $this->get_current_session_id();
        
        // Calculate time range
        $end_time = time();
        $start_time = $this->parse_time_range($time_range);
        
        $metrics = [];
        
        // Get cached metrics if available
        $cache_key = "realtime_metrics_{$session_id}_{$time_range}";
        $cached_metrics = get_transient($cache_key);
        
        if ($cached_metrics !== false && !$this->is_cache_stale($cached_metrics)) {
            return $cached_metrics;
        }
        
        // Get fresh metrics from database
        $db_metrics = $this->get_metrics_from_database($session_id, $start_time, $end_time);
        
        // Process and aggregate metrics
        $metrics = $this->process_metrics($db_metrics);
        
        // Add current system metrics
        $metrics['system'] = $this->get_current_system_metrics();
        
        // Add session info
        if ($session_id && isset($this->active_sessions[$session_id])) {
            $metrics['session'] = $this->active_sessions[$session_id];
        }
        
        // Cache the results
        set_transient($cache_key, $metrics, $this->cache_expiry);
        
        return $metrics;
    }
    
    /**
     * Get current system metrics
     */
    public function get_current_system_metrics() {
        $metrics = [];
        
        // Memory usage
        $metrics['memory'] = [
            'current' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'limit' => $this->parse_memory_limit(ini_get('memory_limit')),
            'percentage' => (memory_get_usage(true) / $this->parse_memory_limit(ini_get('memory_limit'))) * 100
        ];
        
        // CPU usage (if available)
        if (function_exists('sys_getloadavg')) {
            $load_avg = sys_getloadavg();
            $metrics['cpu'] = [
                'load_average' => $load_avg[0],
                'load_1min' => $load_avg[0],
                'load_5min' => $load_avg[1],
                'load_15min' => $load_avg[2]
            ];
        }
        
        // Database performance
        global $wpdb;
        $metrics['database'] = [
            'total_queries' => $wpdb->num_queries ?? 0,
            'query_time' => $wpdb->timer_stop ?? 0,
            'slow_query_threshold' => 1.0 // 1 second
        ];
        
        // WordPress specific metrics
        $metrics['wordpress'] = [
            'transients_count' => $this->count_transients(),
            'options_size' => $this->get_options_table_size(),
            'posts_count' => wp_count_posts()->publish ?? 0,
            'comments_count' => wp_count_comments()->approved ?? 0
        ];
        
        // Plugin specific metrics
        $metrics['plugin'] = [
            'cache_size' => $this->get_cache_size(),
            'queue_size' => $this->get_queue_size(),
            'api_requests' => $this->get_api_request_count(),
            'error_rate' => $this->calculate_error_rate()
        ];
        
        return $metrics;
    }
    
    /**
     * Check metric thresholds and trigger alerts
     */
    private function check_metric_threshold($metric_name, $value, $session_id) {
        $threshold_exceeded = false;
        $alert_level = 'info';
        
        switch ($metric_name) {
            case 'response_time':
                if ($value > $this->thresholds['response_time']) {
                    $threshold_exceeded = true;
                    $alert_level = $value > $this->thresholds['response_time'] * 2 ? 'critical' : 'warning';
                }
                break;
                
            case 'memory_percentage':
                if ($value > $this->thresholds['memory_usage']) {
                    $threshold_exceeded = true;
                    $alert_level = $value > 90 ? 'critical' : 'warning';
                }
                break;
                
            case 'query_time_average':
                if ($value > 1.0) { // 1 second threshold for average query time
                    $threshold_exceeded = true;
                    $alert_level = $value > 5.0 ? 'critical' : 'warning';
                }
                break;
                
            case 'error_rate':
                if ($value > $this->thresholds['error_rate']) {
                    $threshold_exceeded = true;
                    $alert_level = 'critical';
                }
                break;
        }
        
        if ($threshold_exceeded) {
            $this->trigger_performance_alert($metric_name, $value, $alert_level, $session_id);
        }
    }
    
    /**
     * Trigger performance alert
     */
    private function trigger_performance_alert($metric_name, $value, $alert_level, $session_id) {
        $alert_data = [
            'metric' => $metric_name,
            'value' => $value,
            'threshold' => $this->thresholds[$metric_name] ?? 'unknown',
            'level' => $alert_level,
            'session_id' => $session_id,
            'timestamp' => time(),
            'message' => $this->generate_alert_message($metric_name, $value, $alert_level)
        ];
        
        // Store alert
        $this->store_alert($alert_data);
        
        // Send real-time notification
        do_action('ai_news_performance_alert', $alert_data);
        
        // Log alert
        error_log("ContentPilot Monitor Alert [{$alert_level}]: {$alert_data['message']}");
    }
    
    /**
     * Generate alert message
     */
    private function generate_alert_message($metric_name, $value, $alert_level) {
        $messages = [
            'response_time' => "High response time detected: {$value}ms",
            'memory_percentage' => "High memory usage: {$value}%",
            'query_time_average' => "Slow database queries: {$value}s average",
            'error_rate' => "High error rate: {$value}%"
        ];
        
        return $messages[$metric_name] ?? "Performance threshold exceeded for {$metric_name}: {$value}";
    }
    
    /**
     * Handle performance alert
     */
    public function handle_performance_alert($alert_data) {
        // Send WebSocket notification if connected
        $this->send_websocket_alert($alert_data);
        
        // Update dashboard if available
        do_action('ai_news_dashboard_alert', $alert_data);
        
        // Send email notification for critical alerts
        if ($alert_data['level'] === 'critical') {
            $this->send_email_alert($alert_data);
        }
        
        // Store in monitoring events log
        $this->log_monitoring_event('alert', $alert_data);
    }
    
    /**
     * AJAX handler for real-time data
     */
    public function ajax_get_realtime_data() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ai_news_dashboard_nonce')) {
            wp_die('Security check failed');
        }
        
        // Get session ID
        $session_id = $_POST['session_id'] ?? $this->get_current_session_id();
        
        // Get real-time metrics
        $metrics = $this->get_realtime_metrics($session_id);
        
        // Prepare response
        $response = [
            'success' => true,
            'data' => [
                'metrics' => $metrics,
                'session_id' => $session_id,
                'timestamp' => time()
            ]
        ];
        
        wp_send_json($response);
    }
    
    /**
     * AJAX handler to start monitoring
     */
    public function ajax_start_monitoring() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ai_news_dashboard_nonce')) {
            wp_die('Security check failed');
        }
        
        $this->monitoring_enabled = true;
        $session_id = $this->start_monitoring_session();
        
        wp_send_json([
            'success' => true,
            'session_id' => $session_id,
            'message' => 'Monitoring started successfully'
        ]);
    }
    
    /**
     * AJAX handler to stop monitoring
     */
    public function ajax_stop_monitoring() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ai_news_dashboard_nonce')) {
            wp_die('Security check failed');
        }
        
        $this->monitoring_enabled = false;
        $this->finalize_monitoring_sessions();
        
        wp_send_json([
            'success' => true,
            'message' => 'Monitoring stopped successfully'
        ]);
    }
    
    /**
     * Handle WebSocket connection
     */
    public function handle_websocket_connection() {
        // This would typically be handled by a WebSocket server
        // For now, we'll use AJAX polling as fallback
        
        if (!wp_verify_nonce($_GET['nonce'] ?? '', 'ai_news_dashboard_nonce')) {
            wp_die('Security check failed');
        }
        
        $action = $_GET['action'] ?? 'get_data';
        
        switch ($action) {
            case 'get_data':
                $metrics = $this->get_realtime_metrics();
                wp_send_json($metrics);
                break;
                
            case 'send_alert':
                $alert_data = json_decode(file_get_contents('php://input'), true);
                $this->send_websocket_alert($alert_data);
                wp_send_json(['success' => true]);
                break;
                
            default:
                wp_send_json(['error' => 'Invalid action']);
        }
    }
    
    /**
     * Send WebSocket alert
     */
    private function send_websocket_alert($alert_data) {
        // In a real implementation, this would send to WebSocket clients
        // For now, we'll store in transient for immediate access
        $alerts_key = 'ai_news_realtime_alerts';
        $alerts = get_transient($alerts_key) ?: [];
        
        array_unshift($alerts, $alert_data);
        
        // Keep only last 100 alerts
        $alerts = array_slice($alerts, 0, 100);
        
        set_transient($alerts_key, $alerts, 3600); // 1 hour expiry
    }
    
    /**
     * Finalize monitoring session
     */
    private function finalize_monitoring_session($session_id = null) {
        $session_id = $session_id ?: $this->get_current_session_id();
        
        if (!isset($this->active_sessions[$session_id])) {
            return false;
        }
        
        $session = $this->active_sessions[$session_id];
        
        // Record final metrics
        $this->record_memory_usage($session_id);
        
        // Calculate session duration
        $duration = time() - $session['start_time'];
        $this->record_metric('session_duration', $duration, $session_id);
        
        // Store session summary
        $this->store_session_summary($session_id, $session);
        
        // Remove from active sessions
        unset($this->active_sessions[$session_id]);
        
        return true;
    }
    
    /**
     * Finalize all monitoring sessions
     */
    private function finalize_monitoring_sessions() {
        foreach ($this->active_sessions as $session_id => $session) {
            $this->finalize_monitoring_session($session_id);
        }
    }
    
    /**
     * Store monitoring session in database
     */
    private function store_monitoring_session($session_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_news_monitoring_sessions';
        
        $wpdb->insert(
            $table_name,
            [
                'session_id' => $session_data['id'],
                'user_id' => $session_data['user_id'],
                'start_time' => date('Y-m-d H:i:s', $session_data['start_time']),
                'ip_address' => $session_data['ip_address'],
                'user_agent' => $session_data['user_agent'],
                'page_url' => $session_data['page_url'],
                'memory_usage_start' => $session_data['memory_usage_start'],
                'peak_memory_start' => $session_data['peak_memory_start']
            ],
            [
                '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%d'
            ]
        );
    }
    
    /**
     * Store metric in database
     */
    private function store_metric($session_id, $metric_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_news_monitoring_metrics';
        
        $wpdb->insert(
            $table_name,
            [
                'session_id' => $session_id,
                'metric_name' => $metric_data['name'],
                'metric_value' => maybe_serialize($metric_data['value']),
                'timestamp' => date('Y-m-d H:i:s', $metric_data['timestamp']),
                'context' => maybe_serialize($metric_data['context'])
            ],
            ['%s', '%s', '%s', '%s', '%s']
        );
    }
    
    /**
     * Store alert in database
     */
    private function store_alert($alert_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_news_monitoring_alerts';
        
        $wpdb->insert(
            $table_name,
            [
                'session_id' => $alert_data['session_id'],
                'metric_name' => $alert_data['metric'],
                'alert_level' => $alert_data['level'],
                'metric_value' => $alert_data['value'],
                'threshold' => $alert_data['threshold'],
                'message' => $alert_data['message'],
                'timestamp' => date('Y-m-d H:i:s', $alert_data['timestamp'])
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
    }
    
    /**
     * Get metrics from database
     */
    private function get_metrics_from_database($session_id, $start_time, $end_time) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_news_monitoring_metrics';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} 
             WHERE session_id = %s 
             AND timestamp >= %s 
             AND timestamp <= %s 
             ORDER BY timestamp DESC",
            $session_id,
            date('Y-m-d H:i:s', $start_time),
            date('Y-m-d H:i:s', $end_time)
        ));
        
        return $results ?: [];
    }
    
    /**
     * Process and aggregate metrics
     */
    private function process_metrics($raw_metrics) {
        $processed = [];
        
        // Group metrics by name
        $grouped = [];
        foreach ($raw_metrics as $metric) {
            $name = $metric->metric_name;
            if (!isset($grouped[$name])) {
                $grouped[$name] = [];
            }
            $grouped[$name][] = maybe_unserialize($metric->metric_value);
        }
        
        // Calculate aggregations
        foreach ($grouped as $name => $values) {
            if (is_numeric($values[0])) {
                $processed[$name] = [
                    'current' => $values[0],
                    'average' => array_sum($values) / count($values),
                    'min' => min($values),
                    'max' => max($values),
                    'count' => count($values)
                ];
            } else {
                $processed[$name] = $values;
            }
        }
        
        return $processed;
    }
    
    /**
     * Schedule cleanup of old monitoring data
     */
    public function cleanup_old_monitoring_data() {
        global $wpdb;
        
        $retention_days = get_option('ai_news_monitoring_retention_days', 30);
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));
        
        // Clean up old sessions
        $sessions_table = $wpdb->prefix . 'ai_news_monitoring_sessions';
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$sessions_table} WHERE start_time < %s",
            $cutoff_date
        ));
        
        // Clean up old metrics
        $metrics_table = $wpdb->prefix . 'ai_news_monitoring_metrics';
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$metrics_table} WHERE timestamp < %s",
            $cutoff_date
        ));
        
        // Clean up old alerts
        $alerts_table = $wpdb->prefix . 'ai_news_monitoring_alerts';
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$alerts_table} WHERE timestamp < %s",
            $cutoff_date
        ));
    }
    
    /**
     * Get current session ID
     */
    private function get_current_session_id() {
        // In a real implementation, this would be stored in session/cookie
        return key($this->active_sessions) ?: null;
    }
    
    /**
     * Generate unique session ID
     */
    private function generate_session_id() {
        return uniqid('session_', true);
    }
    
    /**
     * Check if should track frontend
     */
    private function should_track_frontend() {
        return apply_filters('ai_news_track_frontend', false);
    }
    
    /**
     * Parse memory limit
     */
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
    
    /**
     * Parse time range
     */
    private function parse_time_range($time_range) {
        switch ($time_range) {
            case '1m':
                return time() - 60;
            case '5m':
                return time() - 300;
            case '15m':
                return time() - 900;
            case '1h':
                return time() - 3600;
            case '24h':
                return time() - 86400;
            case '7d':
                return time() - 604800;
            default:
                return time() - 300; // Default to 5 minutes
        }
    }
    
    /**
     * Check if cache is stale
     */
    private function is_cache_stale($cached_data) {
        return (time() - $cached_data['timestamp']) > $this->cache_expiry;
    }
    
    /**
     * Update metrics cache
     */
    private function update_metrics_cache($metric_name, $value) {
        $this->metrics_cache[$metric_name] = [
            'value' => $value,
            'timestamp' => time()
        ];
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Create database tables
     */
    public static function create_database_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Monitoring sessions table
        $sessions_table = $wpdb->prefix . 'ai_news_monitoring_sessions';
        $sessions_sql = "CREATE TABLE {$sessions_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            session_id varchar(255) NOT NULL,
            user_id bigint(20) DEFAULT 0,
            start_time datetime NOT NULL,
            end_time datetime DEFAULT NULL,
            ip_address varchar(45) NOT NULL,
            user_agent text,
            page_url varchar(255),
            memory_usage_start bigint(20) DEFAULT 0,
            peak_memory_start bigint(20) DEFAULT 0,
            memory_usage_end bigint(20) DEFAULT 0,
            peak_memory_end bigint(20) DEFAULT 0,
            error_count int(11) DEFAULT 0,
            request_count int(11) DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY session_id (session_id),
            KEY user_id (user_id),
            KEY start_time (start_time)
        ) {$charset_collate};";
        
        // Monitoring metrics table
        $metrics_table = $wpdb->prefix . 'ai_news_monitoring_metrics';
        $metrics_sql = "CREATE TABLE {$metrics_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            session_id varchar(255) NOT NULL,
            metric_name varchar(100) NOT NULL,
            metric_value longtext,
            timestamp datetime NOT NULL,
            context longtext,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY metric_name (metric_name),
            KEY timestamp (timestamp)
        ) {$charset_collate};";
        
        // Monitoring alerts table
        $alerts_table = $wpdb->prefix . 'ai_news_monitoring_alerts';
        $alerts_sql = "CREATE TABLE {$alerts_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            session_id varchar(255) NOT NULL,
            metric_name varchar(100) NOT NULL,
            alert_level varchar(20) NOT NULL,
            metric_value longtext,
            threshold varchar(100),
            message text,
            timestamp datetime NOT NULL,
            resolved tinyint(1) DEFAULT 0,
            resolved_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY metric_name (metric_name),
            KEY alert_level (alert_level),
            KEY timestamp (timestamp),
            KEY resolved (resolved)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sessions_sql);
        dbDelta($metrics_sql);
        dbDelta($alerts_sql);
    }
    
    /**
     * Schedule cleanup task
     */
    private function schedule_cleanup() {
        if (!wp_next_scheduled('ai_news_cleanup_monitoring_data')) {
            wp_schedule_event(time(), 'daily', 'ai_news_cleanup_monitoring_data');
        }
    }
    
    /**
     * Additional utility methods for system metrics
     */
    private function count_transients() {
        global $wpdb;
        return $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'"
        );
    }
    
    private function get_options_table_size() {
        global $wpdb;
        $result = $wpdb->get_row(
            "SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'size' 
             FROM information_schema.TABLES 
             WHERE table_schema = DATABASE() AND table_name = '{$wpdb->options}'"
        );
        return $result ? $result->size : 0;
    }
    
    private function get_cache_size() {
        $cache_dir = WP_CONTENT_DIR . '/cache/ai-news/';
        if (!is_dir($cache_dir)) {
            return 0;
        }
        
        $size = 0;
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($cache_dir)) as $file) {
            $size += $file->getSize();
        }
        
        return $size / 1024 / 1024; // Convert to MB
    }
    
    private function get_queue_size() {
        // Get queue size from WordPress options or custom table
        return get_option('ai_news_queue_size', 0);
    }
    
    private function get_api_request_count() {
        // Get API request count from last hour
        $cache_key = 'ai_news_api_requests_count';
        $count = get_transient($cache_key);
        
        if ($count === false) {
            // Calculate actual count (this would need implementation based on your logging)
            $count = 0;
            set_transient($cache_key, $count, 3600); // 1 hour
        }
        
        return $count;
    }
    
    private function calculate_error_rate() {
        // Calculate error rate as percentage of total requests
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_news_monitoring_metrics';
        $result = $wpdb->get_row(
            "SELECT 
                SUM(CASE WHEN metric_name = 'error' THEN 1 ELSE 0 END) as errors,
                COUNT(*) as total
             FROM {$table_name} 
             WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );
        
        if ($result && $result->total > 0) {
            return ($result->errors / $result->total) * 100;
        }
        
        return 0;
    }
    
    private function store_session_summary($session_id, $session_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_news_monitoring_sessions';
        
        $wpdb->update(
            $table_name,
            [
                'end_time' => date('Y-m-d H:i:s'),
                'memory_usage_end' => memory_get_usage(true),
                'peak_memory_end' => memory_get_peak_usage(true),
                'error_count' => $session_data['error_count'],
                'request_count' => $session_data['request_count']
            ],
            ['session_id' => $session_id],
            ['%s', '%d', '%d', '%d', '%d'],
            ['%s']
        );
    }
    
    private function send_email_alert($alert_data) {
        $admin_email = get_option('admin_email');
        $subject = sprintf(
            'AI Auto News Poster Alert: %s threshold exceeded',
            $alert_data['metric']
        );
        
        $message = sprintf(
            "Alert Details:\n\n" .
            "Metric: %s\n" .
            "Value: %s\n" .
            "Threshold: %s\n" .
            "Level: %s\n" .
            "Time: %s\n\n" .
            "Please check the dashboard for more details.",
            $alert_data['metric'],
            $alert_data['value'],
            $alert_data['threshold'],
            $alert_data['level'],
            date('Y-m-d H:i:s', $alert_data['timestamp'])
        );
        
        wp_mail($admin_email, $subject, $message);
    }
    
    private function log_monitoring_event($event_type, $event_data) {
        $log_entry = [
            'timestamp' => time(),
            'type' => $event_type,
            'data' => $event_data
        ];
        
        $log_key = 'ai_news_monitoring_events';
        $events = get_transient($log_key) ?: [];
        
        array_unshift($events, $log_entry);
        
        // Keep only last 1000 events
        $events = array_slice($events, 0, 1000);
        
        set_transient($log_key, $events, 86400); // 24 hours
    }
}

// Initialize the monitor
RealTimeMonitor::get_instance();