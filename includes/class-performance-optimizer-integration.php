<?php
/**
 * Enhanced Performance Optimizer Integration
 * 
 * Integrates the advanced caching manager and OpenLiteSpeed optimizations
 * with the existing performance optimizer for maximum performance.
 *
 * @package AI_Auto_News_Poster
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AANP_Performance_Optimizer_Integration {
    
    /**
     * Cache manager instance
     * @var AANP_Advanced_Cache_Manager
     */
    private $cache_manager;
    
    /**
     * OpenLiteSpeed optimizer instance
     * @var AANP_OpenLiteSpeed_Optimizer
     */
    private $ols_optimizer;
    
    /**
     * Logger instance
     * @var AANP_Logger
     */
    private $logger;
    
    /**
     * Performance monitoring enabled
     * @var bool
     */
    private $monitoring_enabled = true;
    
    /**
     * Performance thresholds
     * @var array
     */
    private $thresholds = array(
        'response_time' => 2000, // 2 seconds
        'memory_usage' => 128 * 1024 * 1024, // 128MB
        'cpu_usage' => 80, // 80%
        'database_queries' => 100
    );
    
    /**
     * Performance metrics cache
     * @var array
     */
    private $metrics_cache = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = AANP_Logger::getInstance();
        $this->initialize_dependencies();
        $this->init_enhanced_optimizations();
        
        $this->logger->info('Enhanced Performance Optimizer Integration initialized', array(
            'cache_manager' => $this->cache_manager ? 'available' : 'not available',
            'ols_optimizer' => $this->ols_optimizer ? 'available' : 'not available'
        ));
    }
    
    /**
     * Initialize dependencies
     */
    private function initialize_dependencies() {
        try {
            // Initialize advanced cache manager
            if (class_exists('AANP_Advanced_Cache_Manager')) {
                $this->cache_manager = new AANP_Advanced_Cache_Manager();
            }
            
            // Initialize OpenLiteSpeed optimizer
            if (class_exists('AANP_OpenLiteSpeed_Optimizer')) {
                $this->ols_optimizer = new AANP_OpenLiteSpeed_Optimizer();
            }
            
        } catch (Exception $e) {
            $this->logger->error('Failed to initialize performance dependencies', array(
                'error' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Initialize enhanced optimizations
     */
    private function init_enhanced_optimizations() {
        if (!$this->monitoring_enabled) {
            return;
        }
        
        // Start performance monitoring
        add_action('wp_head', array($this, 'start_performance_monitoring'), 1);
        add_action('wp_footer', array($this, 'end_performance_monitoring'), 999);
        
        // Monitor database queries
        add_action('shutdown', array($this, 'log_performance_metrics'));
        
        // Add performance monitoring to admin
        if (is_admin()) {
            add_action('admin_enqueue_scripts', array($this, 'enqueue_performance_scripts'));
        }
        
        // Monitor specific WordPress events
        add_action('save_post', array($this, 'monitor_post_performance'), 10, 2);
        add_action('delete_post', array($this, 'monitor_post_deletion_performance'));
        
        // AJAX handlers for real-time monitoring
        add_action('wp_ajax_aanp_get_performance_metrics', array($this, 'ajax_get_performance_metrics'));
        add_action('wp_ajax_nopriv_aanp_get_performance_metrics', array($this, 'ajax_get_performance_metrics'));
        
        // Cache optimization hooks
        add_action('wp_head', array($this, 'add_performance_optimizations'), 2);
        
        // OpenLiteSpeed specific optimizations
        add_action('wp_head', array($this, 'add_ols_performance_headers'), 1);
        add_filter('wp_headers', array($this, 'filter_ols_headers'));
    }
    
    /**
     * Start performance monitoring
     */
    public function start_performance_monitoring() {
        if (!$this->monitoring_enabled) {
            return;
        }
        
        // Record start time
        $this->metrics_cache['start_time'] = microtime(true);
        $this->metrics_cache['start_memory'] = memory_get_usage(true);
        
        // Add performance monitoring script
        if (!is_admin()) {
            echo '<script>
                window.aanpPerformanceMonitoring = {
                    startTime: ' . microtime(true) . ',
                    startMemory: ' . memory_get_usage(true) . ',
                    domReady: false,
                    pageLoad: false
                };
                
                document.addEventListener("DOMContentLoaded", function() {
                    window.aanpPerformanceMonitoring.domReady = true;
                });
                
                window.addEventListener("load", function() {
                    window.aanpPerformanceMonitoring.pageLoad = true;
                });
            </script>';
        }
    }
    
    /**
     * End performance monitoring
     */
    public function end_performance_monitoring() {
        if (!$this->monitoring_enabled) {
            return;
        }
        
        $end_time = microtime(true);
        $end_memory = memory_get_usage(true);
        
        $metrics = array(
            'total_time' => ($end_time - $this->metrics_cache['start_time']) * 1000,
            'memory_usage' => $end_memory - $this->metrics_cache['start_memory'],
            'peak_memory' => memory_get_peak_usage(true),
            'dom_ready_time' => 0,
            'page_load_time' => 0
        );
        
        // Get timing from browser if available
        echo '<script>
            if (window.aanpPerformanceMonitoring) {
                var timing = performance.timing;
                var navigation = performance.navigation;
                
                window.aanpPerformanceMonitoring.metrics = {
                    domReady: timing.domContentLoadedEventEnd - timing.navigationStart,
                    pageLoad: timing.loadEventEnd - timing.navigationStart,
                    responseTime: timing.responseEnd - timing.navigationStart,
                    domParse: timing.domComplete - timing.responseEnd
                };
            }
        </script>';
        
        // Store metrics for server-side logging
        $this->metrics_cache['end_metrics'] = $metrics;
    }
    
    /**
     * Log performance metrics
     */
    public function log_performance_metrics() {
        if (!$this->monitoring_enabled || empty($this->metrics_cache)) {
            return;
        }
        
        try {
            $metrics = array_merge(
                $this->metrics_cache,
                $this->collect_server_metrics()
            );
            
            // Check performance thresholds
            $this->check_performance_thresholds($metrics);
            
            // Cache metrics if cache manager is available
            if ($this->cache_manager) {
                $this->cache_manager->cache_performance_metrics($metrics, 60);
            }
            
            // Log if performance is poor
            $avg_response_time = $this->get_average_response_time();
            if ($avg_response_time > $this->thresholds['response_time']) {
                $this->logger->warning('Performance threshold exceeded', array(
                    'response_time' => $avg_response_time,
                    'threshold' => $this->thresholds['response_time']
                ));
            }
            
        } catch (Exception $e) {
            $this->logger->error('Failed to log performance metrics', array(
                'error' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Collect server-side performance metrics
     */
    private function collect_server_metrics() {
        $metrics = array();
        
        try {
            // Query count
            $metrics['query_count'] = get_num_queries();
            
            // Load time
            $metrics['load_time'] = timer_stop(0, 3);
            
            // Memory usage
            $metrics['memory_usage'] = memory_get_usage(true);
            $metrics['memory_peak'] = memory_get_peak_usage(true);
            
            // Database connection status
            global $wpdb;
            $metrics['db_connected'] = ($wpdb->last_error === '') ? true : false;
            $metrics['db_queries'] = isset($wpdb->queries) ? count($wpdb->queries) : 0;
            
            // PHP configuration
            $metrics['php_version'] = PHP_VERSION;
            $metrics['max_execution_time'] = ini_get('max_execution_time');
            $metrics['memory_limit'] = ini_get('memory_limit');
            
            // WordPress configuration
            $metrics['wp_version'] = get_bloginfo('version');
            $metrics['is_admin'] = is_admin();
            $metrics['is_https'] = is_ssl();
            
            // Cache statistics if available
            if ($this->cache_manager) {
                $metrics['cache_stats'] = $this->cache_manager->get_cache_statistics();
                $metrics['cache_health'] = $this->cache_manager->get_cache_health();
            }
            
            // OpenLiteSpeed stats if available
            if ($this->ols_optimizer) {
                $metrics['ols_config'] = $this->ols_optimizer->get_ols_configuration();
            }
            
        } catch (Exception $e) {
            $this->logger->error('Failed to collect server metrics', array(
                'error' => $e->getMessage()
            ));
        }
        
        return $metrics;
    }
    
    /**
     * Check performance thresholds
     */
    private function check_performance_thresholds($metrics) {
        $alerts = array();
        
        // Response time check
        if (isset($metrics['total_time']) && $metrics['total_time'] > $this->thresholds['response_time']) {
            $alerts[] = 'Response time exceeded threshold';
        }
        
        // Memory usage check
        if (isset($metrics['memory_usage']) && $metrics['memory_usage'] > $this->thresholds['memory_usage']) {
            $alerts[] = 'Memory usage exceeded threshold';
        }
        
        // Database queries check
        if (isset($metrics['db_queries']) && $metrics['db_queries'] > $this->thresholds['database_queries']) {
            $alerts[] = 'Too many database queries';
        }
        
        // Log alerts
        if (!empty($alerts)) {
            $this->logger->warning('Performance alerts detected', array(
                'alerts' => $alerts,
                'metrics' => $metrics
            ));
        }
    }
    
    /**
     * Get average response time from cache
     */
    private function get_average_response_time() {
        if (!$this->cache_manager) {
            return 0;
        }
        
        $response_times = array();
        
        // Get last 10 response time measurements
        for ($i = 0; $i < 10; $i++) {
            $timestamp = date('Y-m-d-H-i', strtotime("-$i minutes"));
            $metrics = $this->cache_manager->get_cached_performance_metrics($timestamp);
            
            if ($metrics && isset($metrics['total_time'])) {
                $response_times[] = $metrics['total_time'];
            }
        }
        
        return !empty($response_times) ? array_sum($response_times) / count($response_times) : 0;
    }
    
    /**
     * AJAX handler for performance metrics
     */
    public function ajax_get_performance_metrics() {
        try {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'aanp_performance_metrics')) {
                wp_send_json_error('Invalid nonce');
            }
            
            $metrics = $this->collect_server_metrics();
            
            // Add real-time metrics if available
            if (isset($_POST['browser_metrics'])) {
                $browser_metrics = json_decode(stripslashes($_POST['browser_metrics']), true);
                if ($browser_metrics) {
                    $metrics['browser'] = $browser_metrics;
                }
            }
            
            wp_send_json_success($metrics);
            
        } catch (Exception $e) {
            wp_send_json_error('Failed to get performance metrics');
        }
    }
    
    /**
     * Enqueue performance monitoring scripts
     */
    public function enqueue_performance_scripts() {
        wp_enqueue_script(
            'aanp-performance-monitor',
            AANP_PLUGIN_URL . 'assets/js/performance-monitor.js',
            array('jquery'),
            AANP_VERSION,
            true
        );
        
        wp_localize_script('aanp-performance-monitor', 'aanp_performance', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aanp_performance_metrics'),
            'monitoring_enabled' => $this->monitoring_enabled,
            'thresholds' => $this->thresholds
        ));
    }
    
    /**
     * Add performance optimizations to head
     */
    public function add_performance_optimizations() {
        if (!$this->monitoring_enabled) {
            return;
        }
        
        // Preload critical resources
        $this->add_preload_hints();
        
        // Add resource hints
        $this->add_resource_hints();
        
        // Critical CSS inlining for dashboard
        if (is_admin() && strpos($_SERVER['REQUEST_URI'] ?? '', 'ai-news-dashboard') !== false) {
            $this->inline_critical_dashboard_css();
        }
    }
    
    /**
     * Add preload hints for critical resources
     */
    private function add_preload_hints() {
        echo '<link rel="preload" href="' . AANP_PLUGIN_URL . 'admin/dashboard/assets/css/dashboard.css" as="style">' . "\n";
        echo '<link rel="preload" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">' . "\n";
        echo '<link rel="preload" href="' . AANP_PLUGIN_URL . 'admin/dashboard/assets/js/dashboard.js" as="script">' . "\n";
    }
    
    /**
     * Add resource hints for faster loading
     */
    private function add_resource_hints() {
        echo '<link rel="dns-prefetch" href="//fonts.googleapis.com">' . "\n";
        echo '<link rel="dns-prefetch" href="//cdn.jsdelivr.net">' . "\n";
        echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
        echo '<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>' . "\n";
    }
    
    /**
     * Inline critical CSS for dashboard
     */
    private function inline_critical_dashboard_css() {
        $critical_css = '
        <style id="aanp-critical-css">
            /* Critical above-the-fold styles */
            .dashboard-container { display: flex; flex-direction: column; min-height: 100vh; }
            .dashboard-header { 
                background: #fff; 
                border-bottom: 1px solid #e5e7eb; 
                position: sticky; 
                top: 0; 
                z-index: 1000;
            }
            .dashboard-main { display: flex; flex: 1; overflow: hidden; }
            .dashboard-sidebar { width: 250px; background: #fff; border-right: 1px solid #e5e7eb; }
            .dashboard-content { flex: 1; padding: 2rem; overflow-y: auto; background: #f9fafb; }
            .metric-card { 
                background: #fff; 
                border-radius: 8px; 
                padding: 1.5rem; 
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                border: 1px solid #e5e7eb;
            }
            @media (max-width: 768px) {
                .dashboard-sidebar { width: 200px; }
                .dashboard-content { padding: 1rem; }
            }
        </style>';
        
        echo $critical_css;
    }
    
    /**
     * Add OpenLiteSpeed performance headers
     */
    public function add_ols_performance_headers() {
        if (!$this->ols_optimizer) {
            return;
        }
        
        // OpenLiteSpeed specific performance headers are handled by the optimizer
        // This method can add additional dashboard-specific headers
        if (is_admin() && strpos($_SERVER['REQUEST_URI'] ?? '', 'ai-news-dashboard') !== false) {
            echo '<meta http-equiv="x-dns-prefetch-control" content="on">' . "\n";
        }
    }
    
    /**
     * Filter headers for OpenLiteSpeed optimization
     */
    public function filter_ols_headers($headers) {
        if (!$this->ols_optimizer) {
            return $headers;
        }
        
        // Add additional performance headers
        $headers['X-AANP-Optimized'] = 'true';
        $headers['X-AANP-Version'] = AANP_VERSION;
        
        return $headers;
    }
    
    /**
     * Monitor post performance
     */
    public function monitor_post_performance($post_id, $post) {
        $start_time = microtime(true);
        
        // Cache post-related data
        if ($this->cache_manager) {
            $this->cache_manager->clear_post_cache($post_id);
        }
        
        $end_time = microtime(true);
        $duration = ($end_time - $start_time) * 1000;
        
        if ($duration > 100) {
            $this->logger->debug('Post operation performance', array(
                'post_id' => $post_id,
                'operation' => 'save',
                'duration_ms' => round($duration, 2)
            ));
        }
    }
    
    /**
     * Monitor post deletion performance
     */
    public function monitor_post_deletion_performance($post_id) {
        $start_time = microtime(true);
        
        // Clear related cache
        if ($this->cache_manager) {
            $this->cache_manager->clear_post_cache($post_id);
        }
        
        $end_time = microtime(true);
        $duration = ($end_time - $start_time) * 1000;
        
        $this->logger->debug('Post deletion performance', array(
            'post_id' => $post_id,
            'duration_ms' => round($duration, 2)
        ));
    }
    
    /**
     * Get comprehensive performance report
     */
    public function get_comprehensive_performance_report() {
        try {
            $metrics = $this->collect_server_metrics();
            
            // Add cache health
            $metrics['cache_health'] = $this->cache_manager ? $this->cache_manager->get_cache_health() : array();
            
            // Add OpenLiteSpeed configuration
            $metrics['ols_optimization'] = $this->ols_optimizer ? $this->ols_optimizer->generate_performance_report() : array();
            
            // Add performance recommendations
            $metrics['recommendations'] = $this->get_performance_recommendations($metrics);
            
            return $metrics;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to generate comprehensive performance report', array(
                'error' => $e->getMessage()
            ));
            
            return array('error' => 'Failed to generate report');
        }
    }
    
    /**
     * Get performance recommendations
     */
    private function get_performance_recommendations($metrics) {
        $recommendations = array();
        
        // Memory usage recommendations
        if (isset($metrics['memory_usage']) && $metrics['memory_usage'] > $this->thresholds['memory_usage']) {
            $recommendations[] = array(
                'type' => 'memory',
                'priority' => 'high',
                'message' => 'Memory usage is high. Consider optimizing queries and enabling object caching.',
                'action' => 'Enable Redis/Memcached caching'
            );
        }
        
        // Response time recommendations
        if (isset($metrics['total_time']) && $metrics['total_time'] > $this->thresholds['response_time']) {
            $recommendations[] = array(
                'type' => 'response_time',
                'priority' => 'high',
                'message' => 'Page load time is slow. Optimize database queries and enable caching.',
                'action' => 'Enable OpenLiteSpeed caching and optimize queries'
            );
        }
        
        // Cache recommendations
        if (empty($metrics['cache_health']['drivers'])) {
            $recommendations[] = array(
                'type' => 'caching',
                'priority' => 'medium',
                'message' => 'No caching drivers detected. Enable Redis or Memcached for better performance.',
                'action' => 'Install and configure Redis or Memcached'
            );
        }
        
        // OpenLiteSpeed recommendations
        if (!$this->ols_optimizer || !$this->ols_optimizer->get_ols_configuration()['esi_support']) {
            $recommendations[] = array(
                'type' => 'server',
                'priority' => 'medium',
                'message' => 'OpenLiteSpeed ESI support not detected. Configure ESI blocks for optimal performance.',
                'action' => 'Enable ESI support in OpenLiteSpeed configuration'
            );
        }
        
        return $recommendations;
    }
    
    /**
     * Clean up resources
     */
    public function cleanup() {
        try {
            // Clear metrics cache
            $this->metrics_cache = array();
            
            if ($this->cache_manager) {
                $this->cache_manager->cleanup();
            }
            
            $this->logger->info('Enhanced Performance Optimizer cleanup completed');
            
        } catch (Exception $e) {
            $this->logger->error('Cleanup failed', array(
                'error' => $e->getMessage()
            ));
        }
    }
}