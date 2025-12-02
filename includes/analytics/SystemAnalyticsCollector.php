<?php
/**
 * System Analytics Collector
 *
 * Collects system-level metrics, server performance data, WordPress health,
 * plugin compatibility, database performance, and infrastructure analytics.
 *
 * @package AI_Auto_News_Poster\Analytics
 * @since 1.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * System Analytics Collector Class
 */
class AANP_System_Analytics_Collector {
    
    /**
     * Logger instance
     * @var AANP_Logger
     */
    private $logger;
    
    /**
     * Cache manager instance
     * @var AANP_AdvancedCacheManager
     */
    private $cache_manager;
    
    /**
     * Constructor
     *
     * @param AANP_Logger $logger
     * @param AANP_AdvancedCacheManager $cache_manager
     */
    public function __construct(AANP_Logger $logger = null, AANP_AdvancedCacheManager $cache_manager = null) {
        $this->logger = $logger ?: AANP_Logger::getInstance();
        $this->cache_manager = $cache_manager ?: new AANP_AdvancedCacheManager();
    }
    
    /**
     * Collect system analytics metrics
     *
     * @param array $params Collection parameters
     * @return array Collected metrics
     */
    public function collect($params = array()) {
        try {
            $metrics = array();
            
            // WordPress health metrics
            $metrics = array_merge($metrics, $this->collect_wordpress_health());
            
            // Server performance metrics
            $metrics = array_merge($metrics, $this->collect_server_performance());
            
            // Database performance metrics
            $metrics = array_merge($metrics, $this->collect_database_performance());
            
            // Plugin compatibility metrics
            $metrics = array_merge($metrics, $this->collect_plugin_compatibility());
            
            // PHP environment metrics
            $metrics = array_merge($metrics, $this->collect_php_environment());
            
            // Security metrics
            $metrics = array_merge($metrics, $this->collect_security_metrics());
            
            // Infrastructure metrics
            $metrics = array_merge($metrics, $this->collect_infrastructure_metrics());
            
            $summary = $this->calculate_summary($metrics);
            
            $this->logger->debug('System analytics metrics collected', array(
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
            $this->logger->error('System analytics collection failed', array(
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
     * Collect WordPress health metrics
     *
     * @return array WordPress health metrics
     */
    private function collect_wordpress_health() {
        global $wp_version;
        
        $metrics = array();
        
        // WordPress version
        $metrics['wordpress_version'] = $wp_version;
        $metrics['wordpress_version_numeric'] = $this->version_to_number($wp_version);
        
        // WordPress health check
        $health_status = $this->get_wordpress_health_status();
        $metrics['health_status'] = $health_status['status'];
        $metrics['health_score'] = $health_status['score'];
        $metrics['critical_issues'] = count($health_status['critical']);
        $metrics['warnings'] = count($health_status['warnings']);
        
        // WordPress configuration
        $metrics['debug_mode_enabled'] = defined('WP_DEBUG') && WP_DEBUG;
        $metrics['auto_update_enabled'] = wp_is_auto_update_enabled_for_type('core');
        $metrics['multisite_enabled'] = is_multisite();
        
        // WordPress performance
        $metrics['page_cache_enabled'] = $this->is_page_cache_enabled();
        $metrics['object_cache_enabled'] = wp_using_ext_object_cache();
        $metrics['gzip_compression_enabled'] = $this->is_gzip_enabled();
        
        // Cron system status
        $cron_disabled = wp_doing_cron();
        $metrics['cron_system_disabled'] = $cron_disabled;
        
        return $metrics;
    }
    
    /**
     * Collect server performance metrics
     *
     * @return array Server performance metrics
     */
    private function collect_server_performance() {
        $metrics = array();
        
        // Server load
        $load_avg = sys_getloadavg();
        $metrics['server_load_1min'] = $load_avg[0] ?? 0;
        $metrics['server_load_5min'] = $load_avg[1] ?? 0;
        $metrics['server_load_15min'] = $load_avg[2] ?? 0;
        
        // Memory usage
        $metrics['memory_usage_bytes'] = memory_get_usage(true);
        $metrics['memory_peak_bytes'] = memory_get_peak_usage(true);
        $metrics['memory_limit_bytes'] = $this->parse_size(ini_get('memory_limit'));
        $metrics['memory_usage_percentage'] = $this->get_memory_usage_percentage();
        
        // Disk space
        $disk_space = $this->get_disk_space_info();
        $metrics['disk_total_bytes'] = $disk_space['total'];
        $metrics['disk_free_bytes'] = $disk_space['free'];
        $metrics['disk_used_percentage'] = $disk_space['used_percentage'];
        
        // PHP execution time
        $metrics['max_execution_time'] = ini_get('max_execution_time');
        $metrics['max_input_time'] = ini_get('max_input_time');
        
        // Uptime (if available)
        $metrics['server_uptime_hours'] = $this->get_server_uptime_hours();
        
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
        
        try {
            // Database version
            $metrics['database_version'] = $wpdb->db_version();
            
            // Database size
            $metrics['database_size_mb'] = $this->get_database_size_mb();
            
            // Table counts
            $metrics['total_tables'] = $this->get_total_tables();
            
            // Query performance
            $metrics['avg_query_time_ms'] = $this->get_average_query_time();
            $metrics['slow_queries_detected'] = $this->count_slow_queries();
            
            // Connection statistics
            $metrics['database_connected'] = $this->is_database_connected();
            
            // Index usage
            $metrics['index_coverage_score'] = $this->calculate_index_coverage_score();
            
        } catch (Exception $e) {
            $metrics['database_error'] = $e->getMessage();
        }
        
        return $metrics;
    }
    
    /**
     * Collect plugin compatibility metrics
     *
     * @return array Plugin compatibility metrics
     */
    private function collect_plugin_compatibility() {
        $metrics = array();
        
        // Active plugins
        $active_plugins = get_option('active_plugins', array());
        $metrics['active_plugins_count'] = count($active_plugins);
        $metrics['plugin_compatibility_score'] = $this->calculate_plugin_compatibility_score();
        
        // Caching plugins
        $caching_plugins = array(
            'w3-total-cache/w3-total-cache.php' => 'W3 Total Cache',
            'wp-super-cache/wp-cache.php' => 'WP Super Cache',
            'wp-rocket/wp-rocket.php' => 'WP Rocket',
            'litespeed-cache/litespeed-cache.php' => 'LiteSpeed Cache',
            'wp-optimize/wp-optimize.php' => 'WP Optimize'
        );
        
        $detected_caching = array();
        foreach ($caching_plugins as $plugin_file => $plugin_name) {
            if (is_plugin_active($plugin_file)) {
                $detected_caching[] = $plugin_name;
            }
        }
        $metrics['active_caching_plugins'] = $detected_caching;
        
        // SEO plugins
        $seo_plugins = array(
            'wordpress-seo/wp-seo.php' => 'Yoast SEO',
            'seo-by-rank-math/rank-math.php' => 'RankMath',
            'all-in-one-seo-pack/all_in_one_seo_pack.php' => 'All in One SEO Pack'
        );
        
        $detected_seo = array();
        foreach ($seo_plugins as $plugin_file => $plugin_name) {
            if (is_plugin_active($plugin_file)) {
                $detected_seo[] = $plugin_name;
            }
        }
        $metrics['active_seo_plugins'] = $detected_seo;
        
        // Security plugins
        $security_plugins = array(
            'wordfence/wordfence.php' => 'Wordfence',
            'sucuri-scanner/sucuri.php' => 'Sucuri Security',
            'defender-security/defender-security.php' => 'Defender Security'
        );
        
        $detected_security = array();
        foreach ($security_plugins as $plugin_file => $plugin_name) {
            if (is_plugin_active($plugin_file)) {
                $detected_security[] = $plugin_name;
            }
        }
        $metrics['active_security_plugins'] = $detected_security;
        
        return $metrics;
    }
    
    /**
     * Collect PHP environment metrics
     *
     * @return array PHP environment metrics
     */
    private function collect_php_environment() {
        $metrics = array();
        
        // PHP version
        $metrics['php_version'] = PHP_VERSION;
        $metrics['php_version_numeric'] = $this->version_to_number(PHP_VERSION);
        
        // PHP extensions
        $required_extensions = array('curl', 'gd', 'json', 'mbstring', 'openssl', 'zip');
        $available_extensions = array();
        foreach ($required_extensions as $extension) {
            if (extension_loaded($extension)) {
                $available_extensions[] = $extension;
            }
        }
        $metrics['required_extensions_available'] = $available_extensions;
        $metrics['missing_extensions'] = array_diff($required_extensions, $available_extensions);
        
        // PHP configuration
        $metrics['upload_max_filesize_bytes'] = $this->parse_size(ini_get('upload_max_filesize'));
        $metrics['post_max_size_bytes'] = $this->parse_size(ini_get('post_max_size'));
        $metrics['max_input_vars'] = ini_get('max_input_vars');
        $metrics['allow_url_fopen'] = ini_get('allow_url_fopen');
        
        return $metrics;
    }
    
    /**
     * Collect security metrics
     *
     * @return array Security metrics
     */
    private function collect_security_metrics() {
        $metrics = array();
        
        // WordPress security
        $metrics['file_editor_enabled'] = !defined('DISALLOW_FILE_EDIT') || !DISALLOW_FILE_EDIT;
        $metrics['debug_log_enabled'] = defined('WP_DEBUG_LOG') && WP_DEBUG_LOG;
        $metrics['ssl_enabled'] = is_ssl();
        
        // File permissions
        $upload_dir = wp_upload_dir();
        $metrics['upload_dir_writable'] = wp_is_writable($upload_dir['basedir']);
        
        // Security headers
        $metrics['security_headers_score'] = $this->calculate_security_headers_score();
        
        // Password policy
        $metrics['strong_passwords_enforced'] = $this->are_strong_passwords_enforced();
        
        // Two-factor authentication
        $metrics['two_factor_available'] = $this->is_two_factor_available();
        
        return $metrics;
    }
    
    /**
     * Collect infrastructure metrics
     *
     * @return array Infrastructure metrics
     */
    private function collect_infrastructure_metrics() {
        $metrics = array();
        
        // CDN usage
        $metrics['cdn_enabled'] = $this->is_cdn_enabled();
        $metrics['cdn_provider'] = $this->get_cdn_provider();
        
        // Backup status
        $backup_status = $this->get_backup_status();
        $metrics['backup_available'] = $backup_status['available'];
        $metrics['backup_last_run'] = $backup_status['last_run'];
        $metrics['backup_size_mb'] = $backup_status['size_mb'];
        
        // Uptime monitoring
        $metrics['uptime_monitoring_enabled'] = $this->is_uptime_monitoring_enabled();
        
        // Server location and performance
        $metrics['server_response_time_ms'] = $this->get_server_response_time();
        
        return $metrics;
    }
    
    /**
     * Get WordPress health status
     *
     * @return array Health status information
     */
    private function get_wordpress_health_status() {
        // This would integrate with WordPress Site Health
        // For now, return a basic status
        return array(
            'status' => 'good',
            'score' => 85,
            'critical' => array(),
            'warnings' => array()
        );
    }
    
    /**
     * Check if page cache is enabled
     *
     * @return bool True if page cache is enabled
     */
    private function is_page_cache_enabled() {
        $caching_plugins = array(
            'w3-total-cache/w3-total-cache.php',
            'wp-super-cache/wp-cache.php',
            'wp-rocket/wp-rocket.php'
        );
        
        foreach ($caching_plugins as $plugin) {
            if (is_plugin_active($plugin)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if GZIP compression is enabled
     *
     * @return bool True if GZIP is enabled
     */
    private function is_gzip_enabled() {
        return function_exists('gzencode') || ini_get('zlib.output_compression');
    }
    
    /**
     * Get memory usage percentage
     *
     * @return float Memory usage percentage
     */
    private function get_memory_usage_percentage() {
        $usage = memory_get_usage(true);
        $limit = $this->parse_size(ini_get('memory_limit'));
        
        return $limit > 0 ? ($usage / $limit) * 100 : 0;
    }
    
    /**
     * Get disk space information
     *
     * @return array Disk space data
     */
    private function get_disk_space_info() {
        $total = disk_total_space('.');
        $free = disk_free_space('.');
        $used = $total - $free;
        $used_percentage = $total > 0 ? ($used / $total) * 100 : 0;
        
        return array(
            'total' => $total,
            'free' => $free,
            'used' => $used,
            'used_percentage' => $used_percentage
        );
    }
    
    /**
     * Get server uptime in hours
     *
     * @return float Server uptime in hours
     */
    private function get_server_uptime_hours() {
        if (function_exists('sys_getloadavg') && is_readable('/proc/uptime')) {
            $uptime_data = file_get_contents('/proc/uptime');
            if ($uptime_data) {
                $uptime_seconds = floatval(explode(' ', $uptime_data)[0]);
                return $uptime_seconds / 3600; // Convert to hours
            }
        }
        
        return 0; // Unable to determine
    }
    
    /**
     * Get database size in MB
     *
     * @return float Database size in MB
     */
    private function get_database_size_mb() {
        global $wpdb;
        
        $tables = $wpdb->get_results("SHOW TABLE STATUS");
        $size = 0;
        
        foreach ($tables as $table) {
            $size += $table->Data_length + $table->Index_length;
        }
        
        return round($size / (1024 * 1024), 2);
    }
    
    /**
     * Get total number of tables
     *
     * @return int Total tables count
     */
    private function get_total_tables() {
        global $wpdb;
        
        return intval($wpdb->get_var("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()"));
    }
    
    /**
     * Get average query time
     *
     * @return float Average query time in milliseconds
     */
    private function get_average_query_time() {
        // This would require query logging
        return 15.5; // Placeholder value
    }
    
    /**
     * Count slow queries
     *
     * @return int Number of slow queries detected
     */
    private function count_slow_queries() {
        // This would require slow query log analysis
        return 0; // Placeholder value
    }
    
    /**
     * Check if database is connected
     *
     * @return bool True if database is connected
     */
    private function is_database_connected() {
        global $wpdb;
        
        try {
            $wpdb->get_var("SELECT 1");
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Calculate index coverage score
     *
     * @return float Index coverage score (0-100)
     */
    private function calculate_index_coverage_score() {
        // This would analyze table indexes
        return 85.0; // Placeholder value
    }
    
    /**
     * Calculate plugin compatibility score
     *
     * @return int Plugin compatibility score (0-100)
     */
    private function calculate_plugin_compatibility_score() {
        $active_plugins = get_option('active_plugins', array());
        $active_count = count($active_plugins);
        
        // Score decreases with too many plugins
        if ($active_count < 10) return 95;
        if ($active_count < 20) return 85;
        if ($active_count < 30) return 75;
        return 65;
    }
    
    /**
     * Version to number conversion
     *
     * @param string $version Version string
     * @return int Numeric version
     */
    private function version_to_number($version) {
        $parts = explode('.', $version);
        $number = 0;
        $multiplier = 100;
        
        foreach ($parts as $part) {
            $number += intval($part) * $multiplier;
            $multiplier /= 100;
        }
        
        return $number;
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
     * Calculate security headers score
     *
     * @return int Security headers score (0-100)
     */
    private function calculate_security_headers_score() {
        $headers_to_check = array(
            'X-Frame-Options',
            'X-Content-Type-Options',
            'X-XSS-Protection',
            'Strict-Transport-Security'
        );
        
        $score = 0;
        foreach ($headers_to_check as $header) {
            if (isset($_SERVER['HTTP_' . str_replace('-', '_', strtoupper($header))])) {
                $score += 25;
            }
        }
        
        return $score;
    }
    
    /**
     * Check if strong passwords are enforced
     *
     * @return bool True if strong passwords are enforced
     */
    private function are_strong_passwords_enforced() {
        // This would check password policy settings
        return false; // Placeholder
    }
    
    /**
     * Check if two-factor authentication is available
     *
     * @return bool True if 2FA is available
     */
    private function is_two_factor_available() {
        // Check for 2FA plugins
        $two_factor_plugins = array(
            'two-factor/two-factor.php',
            'google-authenticator/google-authenticator.php'
        );
        
        foreach ($two_factor_plugins as $plugin) {
            if (is_plugin_active($plugin)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if CDN is enabled
     *
     * @return bool True if CDN is enabled
     */
    private function is_cdn_enabled() {
        // Check for CDN plugins
        $cdn_plugins = array(
            'w3-total-cache/w3-total-cache.php',
            'wp-rocket/wp-rocket.php'
        );
        
        foreach ($cdn_plugins as $plugin) {
            if (is_plugin_active($plugin)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get CDN provider
     *
     * @return string CDN provider name
     */
    private function get_cdn_provider() {
        // This would detect actual CDN provider
        return 'None detected'; // Placeholder
    }
    
    /**
     * Get backup status
     *
     * @return array Backup status information
     */
    private function get_backup_status() {
        // This would check for backup plugins
        return array(
            'available' => false,
            'last_run' => null,
            'size_mb' => 0
        );
    }
    
    /**
     * Check if uptime monitoring is enabled
     *
     * @return bool True if uptime monitoring is enabled
     */
    private function is_uptime_monitoring_enabled() {
        // This would check for uptime monitoring services
        return false; // Placeholder
    }
    
    /**
     * Get server response time
     *
     * @return float Server response time in milliseconds
     */
    private function get_server_response_time() {
        // Measure local response time
        $start_time = microtime(true);
        
        // Make a simple request to measure response time
        wp_remote_get(home_url('/'), array('timeout' => 10));
        
        $response_time = (microtime(true) - $start_time) * 1000;
        
        return round($response_time, 2);
    }
    
    /**
     * Calculate system analytics summary
     *
     * @param array $metrics Individual metrics
     * @return array System analytics summary
     */
    private function calculate_summary($metrics) {
        $summary = array(
            'total_operations' => 0,
            'successful_operations' => 0,
            'failed_operations' => 0,
            'average_response_time' => $metrics['server_response_time_ms'] ?? 0,
            'total_items_processed' => 0,
            'error_rate' => 0,
            'system_health_score' => $this->calculate_system_health_score($metrics),
            'performance_score' => $this->calculate_system_performance_score($metrics),
            'security_score' => $metrics['security_headers_score'] ?? 0,
            'compatibility_score' => $metrics['plugin_compatibility_score'] ?? 0
        );
        
        return $summary;
    }
    
    /**
     * Calculate overall system health score
     *
     * @param array $metrics System metrics
     * @return int System health score (0-100)
     */
    private function calculate_system_health_score($metrics) {
        $score = 100;
        
        // Deduct for WordPress health issues
        $score -= ($metrics['critical_issues'] ?? 0) * 10;
        $score -= ($metrics['warnings'] ?? 0) * 2;
        
        // Deduct for high memory usage
        if (($metrics['memory_usage_percentage'] ?? 0) > 80) {
            $score -= 15;
        }
        
        // Deduct for low disk space
        if (($metrics['disk_used_percentage'] ?? 0) > 90) {
            $score -= 20;
        }
        
        // Deduct for missing PHP extensions
        $score -= count($metrics['missing_extensions'] ?? array()) * 5;
        
        return max(0, min(100, intval($score)));
    }
    
    /**
     * Calculate system performance score
     *
     * @param array $metrics System metrics
     * @return int Performance score (0-100)
     */
    private function calculate_system_performance_score($metrics) {
        $score = 100;
        
        // Deduct for high server load
        $load = $metrics['server_load_1min'] ?? 0;
        if ($load > 2.0) {
            $score -= 20;
        } elseif ($load > 1.0) {
            $score -= 10;
        }
        
        // Deduct for slow response time
        $response_time = $metrics['server_response_time_ms'] ?? 0;
        if ($response_time > 1000) {
            $score -= 25;
        } elseif ($response_time > 500) {
            $score -= 15;
        }
        
        // Deduct for poor database performance
        if (($metrics['avg_query_time_ms'] ?? 0) > 100) {
            $score -= 10;
        }
        
        return max(0, min(100, intval($score)));
    }
    
    /**
     * Health check method
     *
     * @return bool Health status
     */
    public function health_check() {
        try {
            // Test basic system functionality
            if (!function_exists('memory_get_usage')) {
                return false;
            }
            
            // Test database connection
            global $wpdb;
            $test_result = $wpdb->get_var('SELECT 1');
            
            if ($test_result !== '1') {
                return false;
            }
            
            // Test file system access
            if (!is_readable('.') || !is_writable('.')) {
                return false;
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('SystemAnalyticsCollector health check failed', array(
                'error' => $e->getMessage()
            ));
            return false;
        }
    }
}