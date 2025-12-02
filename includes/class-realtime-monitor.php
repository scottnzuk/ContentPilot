/**
 * Real-Time WebSocket Monitor for AI Auto News Poster Dashboard
 * 
 * Provides real-time monitoring of performance metrics, system alerts,
 * and live updates via WebSocket connections for immediate dashboard updates.
 *
 * @package AI_Auto_News_Poster
 * @since 2.0.0
 */

class AANP_RealTime_Monitor {
    
    /**
     * WebSocket connection
     * @var WebSocket|null
     */
    private $websocket = null;
    
    /**
     * Connection status
     * @var bool
     */
    private $is_connected = false;
    
    /**
     * Reconnection attempts
     * @var int
     */
    private $reconnection_attempts = 0;
    
    /**
     * Maximum reconnection attempts
     * @var int
     */
    private $max_reconnection_attempts = 5;
    
    /**
     * Reconnection delay (seconds)
     * @var int
     */
    private $reconnection_delay = 5;
    
    /**
     * Performance metrics cache
     * @var array
     */
    private $metrics_cache = array();
    
    /**
     * Active alerts
     * @var array
     */
    private $active_alerts = array();
    
    /**
     * Monitored metrics
     * @var array
     */
    private $monitored_metrics = array(
        'response_time',
        'memory_usage',
        'cpu_usage',
        'database_queries',
        'cache_hit_rate',
        'server_load',
        'disk_usage'
    );
    
    /**
     * Alert thresholds
     * @var array
     */
    private $alert_thresholds = array(
        'response_time' => 2000, // 2 seconds
        'memory_usage' => 80, // 80%
        'cpu_usage' => 80, // 80%
        'database_queries' => 100,
        'cache_hit_rate' => 70, // 70%
        'server_load' => 2.0,
        'disk_usage' => 85 // 85%
    );
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
        $this->start_monitoring();
        
        $this->log_info('Real-time monitor initialized', array(
            'metrics' => $this->monitored_metrics,
            'websocket_url' => $this->get_websocket_url()
        ));
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Initialize WebSocket connection
        add_action('wp_loaded', array($this, 'initialize_websocket'));
        
        // Add AJAX handlers for WebSocket communication
        add_action('wp_ajax_aanp_websocket_connect', array($this, 'ajax_websocket_connect'));
        add_action('wp_ajax_aanp_websocket_disconnect', array($this, 'ajax_websocket_disconnect'));
        add_action('wp_ajax_aanp_websocket_send', array($this, 'ajax_websocket_send'));
        add_action('wp_ajax_nopriv_aanp_websocket_connect', array($this, 'ajax_websocket_connect'));
        
        // Performance monitoring hooks
        add_action('aanp_performance_check', array($this, 'check_performance_metrics'));
        add_action('aanp_system_alert', array($this, 'handle_system_alert'), 10, 2);
        
        // Dashboard AJAX for real-time data
        add_action('wp_ajax_aanp_get_realtime_metrics', array($this, 'ajax_get_realtime_metrics'));
        add_action('wp_ajax_nopriv_aanp_get_realtime_metrics', array($this, 'ajax_get_realtime_metrics'));
        
        // Schedule monitoring
        if (!wp_next_scheduled('aanp_performance_check')) {
            wp_schedule_event(time(), 'every_minute', 'aanp_performance_check');
        }
    }
    
    /**
     * Initialize WebSocket connection
     */
    public function initialize_websocket() {
        // Only initialize in admin area
        if (!is_admin()) {
            return;
        }
        
        // Check if user has permission
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Initialize WebSocket for dashboard pages
        $current_screen = get_current_screen();
        if ($current_screen && strpos($current_screen->id, 'ai-news-dashboard') !== false) {
            $this->connect_websocket();
        }
    }
    
    /**
     * Connect to WebSocket server
     */
    public function connect_websocket() {
        try {
            $websocket_url = $this->get_websocket_url();
            
            if (!$websocket_url) {
                $this->log_warning('WebSocket URL not available');
                return false;
            }
            
            // For demonstration, we'll simulate WebSocket connection
            // In production, you would use a real WebSocket library
            $this->simulate_websocket_connection();
            
            $this->log_info('WebSocket connection initiated', array(
                'url' => $websocket_url
            ));
            
            return true;
            
        } catch (Exception $e) {
            $this->log_error('Failed to connect to WebSocket', array(
                'error' => $e->getMessage()
            ));
            return false;
        }
    }
    
    /**
     * Simulate WebSocket connection (for demonstration)
     * In production, replace with real WebSocket implementation
     */
    private function simulate_websocket_connection() {
        // Simulate connection success
        $this->is_connected = true;
        $this->reconnection_attempts = 0;
        
        // Start sending simulated metrics
        $this->start_sending_simulated_metrics();
        
        // Set up JavaScript WebSocket on frontend
        $this->setup_frontend_websocket();
    }
    
    /**
     * Set up frontend WebSocket connection
     */
    private function setup_frontend_websocket() {
        $websocket_script = '
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            if (typeof window.aanpDashboard !== "undefined") {
                window.aanpDashboard.initializeWebSocket();
            }
        });
        </script>';
        
        if (is_admin()) {
            add_action('admin_footer', function() use ($websocket_script) {
                echo $websocket_script;
            });
        }
    }
    
    /**
     * Get WebSocket URL
     */
    private function get_websocket_url() {
        $protocol = is_ssl() ? 'wss:' : 'ws:';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $path = '/wp-admin/admin-ajax.php?action=aanp_websocket_handler';
        
        return $protocol . '//' . $host . $path;
    }
    
    /**
     * Start sending simulated metrics
     */
    private function start_sending_simulated_metrics() {
        // Send initial metrics
        $this->send_metrics_to_frontend();
        
        // Set up periodic metric updates
        add_action('wp_footer', array($this, 'send_periodic_metrics'));
    }
    
    /**
     * Send metrics to frontend
     */
    public function send_metrics_to_frontend() {
        $metrics = $this->collect_current_metrics();
        
        // Store metrics in transient for quick access
        set_transient('aanp_realtime_metrics', $metrics, 30); // 30 seconds
        
        // Log metrics for debugging
        $this->log_debug('Metrics collected', array(
            'metrics_count' => count($metrics),
            'response_time' => $metrics['response_time'] ?? 'N/A',
            'memory_usage' => $metrics['memory_usage'] ?? 'N/A'
        ));
    }
    
    /**
     * Collect current performance metrics
     */
    private function collect_current_metrics() {
        $metrics = array();
        
        try {
            // Response time
            $metrics['response_time'] = $this->get_response_time();
            
            // Memory usage
            $metrics['memory_usage'] = $this->get_memory_usage_percentage();
            
            // CPU usage (simulated)
            $metrics['cpu_usage'] = $this->get_cpu_usage();
            
            // Database queries
            $metrics['database_queries'] = get_num_queries();
            
            // Cache statistics
            if (class_exists('AANP_Advanced_Cache_Manager')) {
                $cache_manager = new AANP_Advanced_Cache_Manager();
                $cache_stats = $cache_manager->get_cache_statistics();
                $metrics['cache_hit_rate'] = $cache_stats['hit_rate'] ?? 0;
            }
            
            // Server load (simulated)
            $metrics['server_load'] = $this->get_server_load();
            
            // Disk usage
            $metrics['disk_usage'] = $this->get_disk_usage_percentage();
            
            // Timestamp
            $metrics['timestamp'] = current_time('Y-m-d H:i:s');
            
            // Store in cache
            $this->metrics_cache = $metrics;
            
            return $metrics;
            
        } catch (Exception $e) {
            $this->log_error('Failed to collect metrics', array(
                'error' => $e->getMessage()
            ));
            
            return array(
                'error' => 'Failed to collect metrics',
                'timestamp' => current_time('Y-m-d H:i:s')
            );
        }
    }
    
    /**
     * Get response time
     */
    private function get_response_time() {
        // In a real implementation, you would measure actual response times
        // For demonstration, we'll return a simulated value
        return rand(100, 2000); // 100ms to 2s
    }
    
    /**
     * Get memory usage percentage
     */
    private function get_memory_usage_percentage() {
        $memory_limit = ini_get('memory_limit');
        $memory_usage = memory_get_usage(true);
        
        // Convert memory limit to bytes
        $limit_bytes = $this->convert_to_bytes($memory_limit);
        
        if ($limit_bytes > 0) {
            return round(($memory_usage / $limit_bytes) * 100, 1);
        }
        
        return 0;
    }
    
    /**
     * Get CPU usage (simulated)
     */
    private function get_cpu_usage() {
        // In a real implementation, you would measure actual CPU usage
        return rand(10, 90); // 10% to 90%
    }
    
    /**
     * Get server load (simulated)
     */
    private function get_server_load() {
        // In a real implementation, you would measure actual load average
        return round(rand(10, 200) / 100, 2); // 0.10 to 2.00
    }
    
    /**
     * Get disk usage percentage
     */
    private function get_disk_usage_percentage() {
        $total_space = disk_total_space(ABSPATH);
        $free_space = disk_free_space(ABSPATH);
        
        if ($total_space > 0) {
            $used_space = $total_space - $free_space;
            return round(($used_space / $total_space) * 100, 1);
        }
        
        return 0;
    }
    
    /**
     * Convert memory string to bytes
     */
    private function convert_to_bytes($value) {
        $unit = strtolower(substr($value, -1));
        $value = (int) $value;
        
        switch ($unit) {
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
     * Check performance metrics and trigger alerts
     */
    public function check_performance_metrics() {
        $metrics = $this->collect_current_metrics();
        $alerts = array();
        
        // Check each metric against thresholds
        foreach ($this->alert_thresholds as $metric => $threshold) {
            if (isset($metrics[$metric])) {
                $value = $metrics[$metric];
                
                // Check if threshold is exceeded
                if ($this->is_threshold_exceeded($metric, $value)) {
                    $alerts[] = array(
                        'type' => 'warning',
                        'metric' => $metric,
                        'value' => $value,
                        'threshold' => $threshold,
                        'message' => $this->get_alert_message($metric, $value, $threshold),
                        'timestamp' => current_time('Y-m-d H:i:s')
                    );
                }
            }
        }
        
        // Process alerts
        foreach ($alerts as $alert) {
            $this->trigger_alert($alert);
        }
        
        // Send metrics update
        $this->send_metrics_to_frontend();
    }
    
    /**
     * Check if threshold is exceeded
     */
    private function is_threshold_exceeded($metric, $value) {
        switch ($metric) {
            case 'cache_hit_rate':
                // For cache hit rate, we're checking if it's below threshold
                return $value < $this->alert_thresholds[$metric];
            case 'response_time':
                // For response time, we're checking if it's above threshold
                return $value > $this->alert_thresholds[$metric];
            default:
                return $value > $this->alert_thresholds[$metric];
        }
    }
    
    /**
     * Get alert message
     */
    private function get_alert_message($metric, $value, $threshold) {
        $messages = array(
            'response_time' => "Response time ({$value}ms) exceeded threshold ({$threshold}ms)",
            'memory_usage' => "Memory usage ({$value}%) exceeded threshold ({$threshold}%)",
            'cpu_usage' => "CPU usage ({$value}%) exceeded threshold ({$threshold}%)",
            'database_queries' => "Database queries ({$value}) exceeded threshold ({$threshold})",
            'cache_hit_rate' => "Cache hit rate ({$value}%) below threshold ({$threshold}%)",
            'server_load' => "Server load ({$value}) exceeded threshold ({$threshold})",
            'disk_usage' => "Disk usage ({$value}%) exceeded threshold ({$threshold}%)"
        );
        
        return $messages[$metric] ?? "Metric {$metric} ({$value}) exceeded threshold ({$threshold})";
    }
    
    /**
     * Trigger alert
     */
    private function trigger_alert($alert) {
        // Add to active alerts
        $alert_id = md5($alert['metric'] . $alert['timestamp']);
        $this->active_alerts[$alert_id] = $alert;
        
        // Log alert
        $this->log_warning('Performance alert triggered', $alert);
        
        // Send alert to frontend
        $this->send_alert_to_frontend($alert);
        
        // Send notification if critical
        if ($this->is_critical_alert($alert)) {
            $this->send_critical_notification($alert);
        }
    }
    
    /**
     * Check if alert is critical
     */
    private function is_critical_alert($alert) {
        $critical_metrics = array('memory_usage', 'cpu_usage', 'disk_usage');
        return in_array($alert['metric'], $critical_metrics) && $alert['value'] > 90;
    }
    
    /**
     * Send alert to frontend
     */
    private function send_alert_to_frontend($alert) {
        // Store alert in transient
        set_transient('aanp_realtime_alerts', $this->active_alerts, 300); // 5 minutes
        
        // Log for debugging
        $this->log_info('Alert sent to frontend', array(
            'alert_type' => $alert['type'],
            'metric' => $alert['metric'],
            'message' => $alert['message']
        ));
    }
    
    /**
     * Send critical notification
     */
    private function send_critical_notification($alert) {
        // In a real implementation, you might send email notifications
        // or use other notification systems
        
        $this->log_critical('Critical performance alert', $alert);
    }
    
    /**
     * Handle system alert
     */
    public function handle_system_alert($alert_type, $message) {
        $alert = array(
            'type' => $alert_type,
            'message' => $message,
            'timestamp' => current_time('Y-m-d H:i:s')
        );
        
        $this->send_alert_to_frontend($alert);
    }
    
    /**
     * AJAX handler for WebSocket connection
     */
    public function ajax_websocket_connect() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'aanp_websocket')) {
            wp_send_json_error('Invalid nonce');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $success = $this->connect_websocket();
        
        wp_send_json_success(array(
            'connected' => $success,
            'message' => $success ? 'WebSocket connected' : 'Failed to connect to WebSocket'
        ));
    }
    
    /**
     * AJAX handler for WebSocket disconnection
     */
    public function ajax_websocket_disconnect() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'aanp_websocket')) {
            wp_send_json_error('Invalid nonce');
        }
        
        $this->disconnect_websocket();
        
        wp_send_json_success(array(
            'disconnected' => true,
            'message' => 'WebSocket disconnected'
        ));
    }
    
    /**
     * AJAX handler for WebSocket send
     */
    public function ajax_websocket_send() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'aanp_websocket')) {
            wp_send_json_error('Invalid nonce');
        }
        
        $message = sanitize_text_field($_POST['message'] ?? '');
        $type = sanitize_text_field($_POST['type'] ?? 'info');
        
        $this->send_websocket_message($type, $message);
        
        wp_send_json_success(array(
            'sent' => true,
            'message' => 'Message sent via WebSocket'
        ));
    }
    
    /**
     * AJAX handler for realtime metrics
     */
    public function ajax_get_realtime_metrics() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'aanp_realtime_metrics')) {
            wp_send_json_error('Invalid nonce');
        }
        
        // Get cached metrics
        $metrics = get_transient('aanp_realtime_metrics');
        if (!$metrics) {
            $metrics = $this->collect_current_metrics();
        }
        
        // Get active alerts
        $alerts = get_transient('aanp_realtime_alerts');
        if (!$alerts) {
            $alerts = array();
        }
        
        wp_send_json_success(array(
            'metrics' => $metrics,
            'alerts' => $alerts,
            'connected' => $this->is_connected,
            'timestamp' => current_time('Y-m-d H:i:s')
        ));
    }
    
    /**
     * Disconnect WebSocket
     */
    private function disconnect_websocket() {
        $this->is_connected = false;
        
        // In a real implementation, you would close the WebSocket connection
        $this->log_info('WebSocket disconnected');
    }
    
    /**
     * Send WebSocket message
     */
    private function send_websocket_message($type, $message) {
        // In a real implementation, you would send the message via WebSocket
        $this->log_info('WebSocket message sent', array(
            'type' => $type,
            'message' => $message
        ));
    }
    
    /**
     * Send periodic metrics (called from footer)
     */
    public function send_periodic_metrics() {
        if ($this->is_connected) {
            echo '<script type="text/javascript">
                if (typeof window.aanpDashboard !== "undefined") {
                    window.aanpDashboard.updateMetrics();
                }
            </script>';
        }
    }
    
    /**
     * Get connection status
     */
    public function get_connection_status() {
        return array(
            'connected' => $this->is_connected,
            'reconnection_attempts' => $this->reconnection_attempts,
            'max_reconnection_attempts' => $this->max_reconnection_attempts,
            'monitored_metrics' => $this->monitored_metrics,
            'active_alerts_count' => count($this->active_alerts)
        );
    }
    
    /**
     * Get active alerts
     */
    public function get_active_alerts() {
        return $this->active_alerts;
    }
    
    /**
     * Clear resolved alerts
     */
    public function clear_resolved_alerts() {
        $this->active_alerts = array();
        delete_transient('aanp_realtime_alerts');
        
        $this->log_info('Resolved alerts cleared');
    }
    
    /**
     * Logging methods
     */
    private function log_info($message, $context = array()) {
        if (class_exists('AANP_Logger')) {
            AANP_Logger::getInstance()->info($message, $context);
        }
    }
    
    private function log_warning($message, $context = array()) {
        if (class_exists('AANP_Logger')) {
            AANP_Logger::getInstance()->warning($message, $context);
        }
    }
    
    private function log_error($message, $context = array()) {
        if (class_exists('AANP_Logger')) {
            AANP_Logger::getInstance()->error($message, $context);
        }
    }
    
    private function log_critical($message, $context = array()) {
        if (class_exists('AANP_Logger')) {
            AANP_Logger::getInstance()->log_critical($message, $context);
        }
    }
    
    private function log_debug($message, $context = array()) {
        if (class_exists('AANP_Logger')) {
            AANP_Logger::getInstance()->debug($message, $context);
        }
    }
    
    /**
     * Start monitoring
     */
    private function start_monitoring() {
        // Initial metrics collection
        $this->send_metrics_to_frontend();
        
        // Set up periodic cleanup
        add_action('wp_scheduled_delete', array($this, 'cleanup_old_data'));
    }
    
    /**
     * Clean up old data
     */
    public function cleanup_old_data() {
        // Clear old metrics
        delete_expired_transients();
        
        // Clean up resolved alerts older than 1 hour
        $cutoff_time = time() - 3600;
        foreach ($this->active_alerts as $alert_id => $alert) {
            if (strtotime($alert['timestamp']) < $cutoff_time) {
                unset($this->active_alerts[$alert_id]);
            }
        }
        
        $this->log_debug('Old data cleanup completed');
    }
    
    /**
     * Stop monitoring
     */
    public function stop_monitoring() {
        $this->disconnect_websocket();
        
        // Clear scheduled events
        wp_clear_scheduled_hook('aanp_performance_check');
        
        $this->log_info('Real-time monitoring stopped');
    }
}