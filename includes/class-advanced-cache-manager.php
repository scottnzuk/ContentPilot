<?php
/**
 * Advanced Cache Manager for AI Auto News Poster
 * 
 * Provides multi-layer caching with Redis, Memcached, file-based caching,
 * and OpenLiteSpeed integration for maximum performance optimization.
 *
 * @package AI_Auto_News_Poster
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AANP_Advanced_Cache_Manager {
    
    /**
     * Cache configurations
     * @var array
     */
    private $config = array();
    
    /**
     * Active cache drivers
     * @var array
     */
    private $drivers = array();
    
    /**
     * Cache statistics
     * @var array
     */
    private $stats = array();
    
    /**
     * Logger instance
     * @var AANP_Logger
     */
    private $logger;
    
    /**
     * Default cache TTL
     * @var int
     */
    private $default_ttl = 3600; // 1 hour
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = AANP_Logger::getInstance();
        $this->initialize_configuration();
        $this->initialize_drivers();
        $this->initialize_hooks();
        
        $this->logger->info('Advanced Cache Manager initialized', array(
            'drivers' => array_keys($this->drivers),
            'config' => $this->config
        ));
    }
    
    /**
     * Initialize cache configuration
     */
    private function initialize_configuration() {
        $settings = get_option('aanp_cache_settings', array());
        
        $this->config = array(
            'redis' => array(
                'enabled' => $settings['redis_enabled'] ?? false,
                'host' => $settings['redis_host'] ?? '127.0.0.1',
                'port' => $settings['redis_port'] ?? 6379,
                'database' => $settings['redis_database'] ?? 0,
                'password' => $settings['redis_password'] ?? '',
                'timeout' => $settings['redis_timeout'] ?? 5,
                'prefix' => $settings['redis_prefix'] ?? 'aanp_redis_'
            ),
            'memcached' => array(
                'enabled' => $settings['memcached_enabled'] ?? false,
                'servers' => $settings['memcached_servers'] ?? array(
                    array('127.0.0.1', 11211)
                ),
                'prefix' => $settings['memcached_prefix'] ?? 'aanp_mem_'
            ),
            'file' => array(
                'enabled' => $settings['file_enabled'] ?? true,
                'directory' => $settings['file_directory'] ?? WP_CONTENT_DIR . '/cache/aanp/',
                'prefix' => $settings['file_prefix'] ?? 'aanp_file_',
                'max_size' => $settings['file_max_size'] ?? 100 * 1024 * 1024, // 100MB
                'gc_interval' => $settings['file_gc_interval'] ?? 3600 // 1 hour
            ),
            'object' => array(
                'enabled' => $settings['object_enabled'] ?? true,
                'group' => $settings['object_group'] ?? 'aanp_cache',
                'ttl' => $settings['object_ttl'] ?? 1800 // 30 minutes
            ),
            'database' => array(
                'enabled' => $settings['database_enabled'] ?? true,
                'table' => $settings['database_table'] ?? 'aanp_cache',
                'ttl' => $settings['database_ttl'] ?? 7200 // 2 hours
            ),
            'openlitespeed' => array(
                'enabled' => $settings['ols_enabled'] ?? false,
                'esi_blocks' => $settings['ols_esi_blocks'] ?? true,
                'ttl' => $settings['ols_ttl'] ?? 300 // 5 minutes
            )
        );
        
        // Ensure cache directory exists
        if ($this->config['file']['enabled']) {
            $this->ensure_cache_directory();
        }
    }
    
    /**
     * Initialize cache drivers
     */
    private function initialize_drivers() {
        // Initialize Redis if available and enabled
        if ($this->config['redis']['enabled'] && class_exists('Redis')) {
            try {
                $this->drivers['redis'] = new AANP_Redis_Cache_Driver($this->config['redis']);
                $this->logger->info('Redis cache driver initialized');
            } catch (Exception $e) {
                $this->logger->error('Failed to initialize Redis cache driver', array(
                    'error' => $e->getMessage()
                ));
            }
        }
        
        // Initialize Memcached if available and enabled
        if ($this->config['memcached']['enabled'] && class_exists('Memcached')) {
            try {
                $this->drivers['memcached'] = new AANP_Memcached_Cache_Driver($this->config['memcached']);
                $this->logger->info('Memcached cache driver initialized');
            } catch (Exception $e) {
                $this->logger->error('Failed to initialize Memcached cache driver', array(
                    'error' => $e->getMessage()
                ));
            }
        }
        
        // Initialize File cache driver
        if ($this->config['file']['enabled']) {
            try {
                $this->drivers['file'] = new AANP_File_Cache_Driver($this->config['file']);
                $this->logger->info('File cache driver initialized');
            } catch (Exception $e) {
                $this->logger->error('Failed to initialize File cache driver', array(
                    'error' => $e->getMessage()
                ));
            }
        }
        
        // Initialize WordPress Object Cache driver
        if ($this->config['object']['enabled'] && wp_using_ext_object_cache()) {
            try {
                $this->drivers['object'] = new AANP_Object_Cache_Driver($this->config['object']);
                $this->logger->info('Object cache driver initialized');
            } catch (Exception $e) {
                $this->logger->error('Failed to initialize Object cache driver', array(
                    'error' => $e->getMessage()
                ));
            }
        }
        
        // Initialize Database cache driver
        if ($this->config['database']['enabled']) {
            try {
                $this->drivers['database'] = new AANP_Database_Cache_Driver($this->config['database']);
                $this->logger->info('Database cache driver initialized');
            } catch (Exception $e) {
                $this->logger->error('Failed to initialize Database cache driver', array(
                    'error' => $e->getMessage()
                ));
            }
        }
        
        // Fallback to WordPress transients if no other drivers available
        if (empty($this->drivers)) {
            $this->drivers['transient'] = new AANP_Transient_Cache_Driver();
            $this->logger->warning('Using WordPress transients as fallback cache driver');
        }
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function initialize_hooks() {
        // Clear cache on content changes
        add_action('save_post', array($this, 'clear_post_cache'), 10, 2);
        add_action('delete_post', array($this, 'clear_post_cache'), 10, 1);
        add_action('wp_update_nav_menu', array($this, 'clear_menu_cache'));
        
        // Clear cache on plugin activation/deactivation
        register_activation_hook(__FILE__, array($this, 'clear_all_cache'));
        register_deactivation_hook(__FILE__, array($this, 'clear_all_cache'));
        
        // Garbage collection
        add_action('aanp_cache_gc', array($this, 'garbage_collection'));
        
        // Schedule garbage collection
        if (!wp_next_scheduled('aanp_cache_gc')) {
            wp_schedule_event(time(), 'hourly', 'aanp_cache_gc');
        }
    }
    
    /**
     * Get cached data with automatic fallback
     */
    public function get($key, $default = false) {
        $key = $this->normalize_key($key);
        
        // Try drivers in priority order
        foreach ($this->get_driver_priority() as $driver_name) {
            if (!isset($this->drivers[$driver_name])) {
                continue;
            }
            
            try {
                $value = $this->drivers[$driver_name]->get($key);
                
                if ($value !== false) {
                    $this->increment_stat('hits');
                    $this->logger->debug('Cache hit', array(
                        'key' => $key,
                        'driver' => $driver_name
                    ));
                    return $value;
                }
            } catch (Exception $e) {
                $this->logger->warning('Cache driver error', array(
                    'driver' => $driver_name,
                    'error' => $e->getMessage()
                ));
                continue;
            }
        }
        
        $this->increment_stat('misses');
        return $default;
    }
    
    /**
     * Set cached data
     */
    public function set($key, $value, $ttl = null) {
        $key = $this->normalize_key($key);
        
        if ($ttl === null) {
            $ttl = $this->default_ttl;
        }
        
        $success_count = 0;
        
        // Set in all available drivers
        foreach ($this->drivers as $driver_name => $driver) {
            try {
                $result = $driver->set($key, $value, $ttl);
                if ($result) {
                    $success_count++;
                    $this->logger->debug('Cache set', array(
                        'key' => $key,
                        'driver' => $driver_name,
                        'ttl' => $ttl
                    ));
                }
            } catch (Exception $e) {
                $this->logger->warning('Cache set error', array(
                    'driver' => $driver_name,
                    'error' => $e->getMessage()
                ));
            }
        }
        
        return $success_count > 0;
    }
    
    /**
     * Delete cached data
     */
    public function delete($key) {
        $key = $this->normalize_key($key);
        
        $success_count = 0;
        
        foreach ($this->drivers as $driver_name => $driver) {
            try {
                $result = $driver->delete($key);
                if ($result) {
                    $success_count++;
                    $this->logger->debug('Cache delete', array(
                        'key' => $key,
                        'driver' => $driver_name
                    ));
                }
            } catch (Exception $e) {
                $this->logger->warning('Cache delete error', array(
                    'driver' => $driver_name,
                    'error' => $e->getMessage()
                ));
            }
        }
        
        return $success_count > 0;
    }
    
    /**
     * Clear all cache
     */
    public function clear_all_cache() {
        $success_count = 0;
        
        foreach ($this->drivers as $driver_name => $driver) {
            try {
                $result = $driver->clear();
                if ($result) {
                    $success_count++;
                    $this->logger->info('Cache cleared', array(
                        'driver' => $driver_name
                    ));
                }
            } catch (Exception $e) {
                $this->logger->error('Cache clear error', array(
                    'driver' => $driver_name,
                    'error' => $e->getMessage()
                ));
            }
        }
        
        // Clear WordPress object cache
        wp_cache_flush();
        
        return $success_count > 0;
    }
    
    /**
     * Clear post-specific cache
     */
    public function clear_post_cache($post_id, $post = null) {
        $cache_keys = array(
            'post_' . $post_id,
            'post_meta_' . $post_id,
            'post_content_' . $post_id,
            'seo_data_' . $post_id
        );
        
        foreach ($cache_keys as $key) {
            $this->delete($key);
        }
        
        // Clear related RSS feed cache
        $this->delete_rss_cache();
        
        $this->logger->debug('Post cache cleared', array(
            'post_id' => $post_id
        ));
    }
    
    /**
     * Clear menu cache
     */
    public function clear_menu_cache() {
        $this->delete('navigation_menus');
        $this->logger->debug('Menu cache cleared');
    }
    
    /**
     * Clear RSS feed cache
     */
    public function delete_rss_cache() {
        // Clear RSS-related cache
        $this->delete('rss_feeds');
        $this->delete('rss_fetch_stats');
        $this->delete('news_sources');
    }
    
    /**
     * Cache API response
     */
    public function cache_api_response($endpoint, $params, $response, $ttl = 300) {
        $key = 'api_' . md5($endpoint . serialize($params));
        return $this->set($key, $response, $ttl);
    }
    
    /**
     * Get cached API response
     */
    public function get_cached_api_response($endpoint, $params) {
        $key = 'api_' . md5($endpoint . serialize($params));
        return $this->get($key);
    }
    
    /**
     * Cache SEO analysis results
     */
    public function cache_seo_analysis($content_id, $analysis, $ttl = 3600) {
        $key = 'seo_analysis_' . $content_id;
        return $this->set($key, $analysis, $ttl);
    }
    
    /**
     * Get cached SEO analysis
     */
    public function get_cached_seo_analysis($content_id) {
        $key = 'seo_analysis_' . $content_id;
        return $this->get($key);
    }
    
    /**
     * Cache performance metrics
     */
    public function cache_performance_metrics($metrics, $ttl = 60) {
        $key = 'performance_metrics_' . date('Y-m-d-H-i');
        return $this->set($key, $metrics, $ttl);
    }
    
    /**
     * Get cached performance metrics
     */
    public function get_cached_performance_metrics($timestamp = null) {
        if ($timestamp === null) {
            $timestamp = date('Y-m-d-H-i');
        }
        $key = 'performance_metrics_' . $timestamp;
        return $this->get($key);
    }
    
    /**
     * Get cache statistics
     */
    public function get_cache_statistics() {
        $total_requests = $this->stats['hits'] + $this->stats['misses'];
        $hit_rate = $total_requests > 0 ? ($this->stats['hits'] / $total_requests) * 100 : 0;
        
        return array(
            'hits' => $this->stats['hits'],
            'misses' => $this->stats['misses'],
            'hit_rate' => round($hit_rate, 2),
            'total_requests' => $total_requests,
            'drivers' => array_keys($this->drivers),
            'driver_stats' => $this->get_driver_statistics(),
            'memory_usage' => $this->get_memory_usage()
        );
    }
    
    /**
     * Get driver statistics
     */
    private function get_driver_statistics() {
        $stats = array();
        
        foreach ($this->drivers as $driver_name => $driver) {
            try {
                if (method_exists($driver, 'get_statistics')) {
                    $stats[$driver_name] = $driver->get_statistics();
                }
            } catch (Exception $e) {
                $stats[$driver_name] = array('error' => $e->getMessage());
            }
        }
        
        return $stats;
    }
    
    /**
     * Get memory usage
     */
    private function get_memory_usage() {
        if (function_exists('memory_get_usage')) {
            return array(
                'current' => memory_get_usage(true),
                'peak' => memory_get_peak_usage(true),
                'limit' => ini_get('memory_limit')
            );
        }
        return array();
    }
    
    /**
     * Perform garbage collection
     */
    public function garbage_collection() {
        foreach ($this->drivers as $driver_name => $driver) {
            try {
                if (method_exists($driver, 'garbage_collect')) {
                    $driver->garbage_collect();
                    $this->logger->debug('Garbage collection performed', array(
                        'driver' => $driver_name
                    ));
                }
            } catch (Exception $e) {
                $this->logger->warning('Garbage collection error', array(
                    'driver' => $driver_name,
                    'error' => $e->getMessage()
                ));
            }
        }
    }
    
    /**
     * Ensure cache directory exists
     */
    private function ensure_cache_directory() {
        $directory = $this->config['file']['directory'];
        
        if (!file_exists($directory)) {
            wp_mkdir_p($directory);
            
            // Add .htaccess for security
            $htaccess_content = "Order Deny,Allow\nDeny from all";
            file_put_contents($directory . '.htaccess', $htaccess_content);
            
            $this->logger->info('Cache directory created', array(
                'directory' => $directory
            ));
        }
    }
    
    /**
     * Get driver priority order
     */
    private function get_driver_priority() {
        // Priority order: Redis > Memcached > Object > Database > File > Transient
        return array('redis', 'memcached', 'object', 'database', 'file', 'transient');
    }
    
    /**
     * Normalize cache key
     */
    private function normalize_key($key) {
        // Remove special characters and normalize
        $key = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
        $key = trim($key, '_');
        
        // Add prefix
        return 'aanp_' . $key;
    }
    
    /**
     * Increment statistics counter
     */
    private function increment_stat($stat) {
        if (!isset($this->stats[$stat])) {
            $this->stats[$stat] = 0;
        }
        $this->stats[$stat]++;
    }
    
    /**
     * Preload cache with common keys
     */
    public function preload_cache() {
        $common_keys = array(
            'dashboard_config',
            'plugin_settings',
            'rss_sources',
            'system_status',
            'performance_metrics'
        );
        
        foreach ($common_keys as $key) {
            if ($this->get($key) === false) {
                // Generate default values for common cache keys
                $this->generate_cache_value($key);
            }
        }
        
        $this->logger->info('Cache preloading completed');
    }
    
    /**
     * Generate cache value for common keys
     */
    private function generate_cache_value($key) {
        switch ($key) {
            case 'dashboard_config':
                $value = array(
                    'theme' => 'light',
                    'auto_refresh' => true,
                    'refresh_interval' => 30,
                    'notifications' => true
                );
                break;
                
            case 'plugin_settings':
                $value = get_option('aanp_settings', array());
                break;
                
            case 'rss_sources':
                $value = get_option('aanp_rss_sources', array());
                break;
                
            case 'system_status':
                $value = array(
                    'php_version' => PHP_VERSION,
                    'wp_version' => get_bloginfo('version'),
                    'memory_limit' => ini_get('memory_limit'),
                    'max_execution_time' => ini_get('max_execution_time')
                );
                break;
                
            case 'performance_metrics':
                $value = array(
                    'load_time' => timer_stop(),
                    'memory_usage' => memory_get_usage(true),
                    'query_count' => get_num_queries()
                );
                break;
                
            default:
                $value = null;
        }
        
        if ($value !== null) {
            $this->set($key, $value, 3600); // Cache for 1 hour
        }
    }
    
    /**
     * Get cache health status
     */
    public function get_cache_health() {
        $health = array(
            'overall_status' => 'healthy',
            'drivers' => array(),
            'issues' => array()
        );
        
        foreach ($this->drivers as $driver_name => $driver) {
            try {
                $status = 'healthy';
                $message = 'OK';
                
                // Test driver functionality
                $test_key = 'health_test_' . $driver_name;
                $test_value = 'test_' . time();
                
                if (!$driver->set($test_key, $test_value, 60)) {
                    $status = 'error';
                    $message = 'Failed to write test data';
                    $health['overall_status'] = 'warning';
                    $health['issues'][] = "Driver $driver_name: $message";
                } elseif ($driver->get($test_key) !== $test_value) {
                    $status = 'warning';
                    $message = 'Data consistency issue';
                    $health['overall_status'] = 'warning';
                    $health['issues'][] = "Driver $driver_name: $message";
                } else {
                    $driver->delete($test_key);
                }
                
                $health['drivers'][$driver_name] = array(
                    'status' => $status,
                    'message' => $message
                );
                
            } catch (Exception $e) {
                $health['drivers'][$driver_name] = array(
                    'status' => 'error',
                    'message' => $e->getMessage()
                );
                $health['overall_status'] = 'critical';
                $health['issues'][] = "Driver $driver_name: " . $e->getMessage();
            }
        }
        
        return $health;
    }
    
    /**
     * Clean up resources
     */
    public function cleanup() {
        try {
            $this->garbage_collection();
            $this->logger->info('Advanced Cache Manager cleanup completed');
            
        } catch (Exception $e) {
            $this->logger->error('Cleanup failed', array(
                'error' => $e->getMessage()
            ));
        }
    }
}

// Redis Cache Driver
class AANP_Redis_Cache_Driver {
    private $redis;
    private $config;
    
    public function __construct($config) {
        $this->config = $config;
        $this->redis = new Redis();
        
        $this->redis->connect($config['host'], $config['port'], $config['timeout']);
        
        if (!empty($config['password'])) {
            $this->redis->auth($config['password']);
        }
        
        $this->redis->select($config['database']);
    }
    
    public function get($key) {
        $data = $this->redis->get($this->config['prefix'] . $key);
        return $data ? unserialize($data) : false;
    }
    
    public function set($key, $value, $ttl) {
        $serialized = serialize($value);
        return $this->redis->setex($this->config['prefix'] . $key, $ttl, $serialized);
    }
    
    public function delete($key) {
        return $this->redis->del($this->config['prefix'] . $key);
    }
    
    public function clear() {
        return $this->redis->flushDB();
    }
    
    public function get_statistics() {
        return $this->redis->info();
    }
}

// Memcached Cache Driver
class AANP_Memcached_Cache_Driver {
    private $memcached;
    private $config;
    
    public function __construct($config) {
        $this->config = $config;
        $this->memcached = new Memcached();
        
        foreach ($config['servers'] as $server) {
            $this->memcached->addServer($server[0], $server[1]);
        }
    }
    
    public function get($key) {
        return $this->memcached->get($this->config['prefix'] . $key);
    }
    
    public function set($key, $value, $ttl) {
        return $this->memcached->set($this->config['prefix'] . $key, $value, $ttl);
    }
    
    public function delete($key) {
        return $this->memcached->delete($this->config['prefix'] . $key);
    }
    
    public function clear() {
        return $this->memcached->flush();
    }
    
    public function get_statistics() {
        return $this->memcached->getStats();
    }
}

// File Cache Driver
class AANP_File_Cache_Driver {
    private $config;
    
    public function __construct($config) {
        $this->config = $config;
    }
    
    public function get($key) {
        $file = $this->config['directory'] . $this->config['prefix'] . $key . '.cache';
        
        if (!file_exists($file)) {
            return false;
        }
        
        $data = file_get_contents($file);
        $serialized = unserialize($data);
        
        // Check if expired
        if ($serialized['expires'] < time()) {
            unlink($file);
            return false;
        }
        
        return $serialized['data'];
    }
    
    public function set($key, $value, $ttl) {
        $file = $this->config['directory'] . $this->config['prefix'] . $key . '.cache';
        $data = array(
            'data' => $value,
            'expires' => time() + $ttl
        );
        
        return file_put_contents($file, serialize($data), LOCK_EX) !== false;
    }
    
    public function delete($key) {
        $file = $this->config['directory'] . $this->config['prefix'] . $key . '.cache';
        return file_exists($file) ? unlink($file) : true;
    }
    
    public function clear() {
        $files = glob($this->config['directory'] . $this->config['prefix'] . '*.cache');
        $deleted = 0;
        
        foreach ($files as $file) {
            if (unlink($file)) {
                $deleted++;
            }
        }
        
        return $deleted;
    }
    
    public function garbage_collect() {
        $files = glob($this->config['directory'] . $this->config['prefix'] . '*.cache');
        
        foreach ($files as $file) {
            $data = unserialize(file_get_contents($file));
            if ($data['expires'] < time()) {
                unlink($file);
            }
        }
    }
}

// WordPress Object Cache Driver
class AANP_Object_Cache_Driver {
    private $config;
    
    public function __construct($config) {
        $this->config = $config;
    }
    
    public function get($key) {
        return wp_cache_get($key, $this->config['group']);
    }
    
    public function set($key, $value, $ttl) {
        return wp_cache_set($key, $value, $this->config['group'], $ttl);
    }
    
    public function delete($key) {
        return wp_cache_delete($key, $this->config['group']);
    }
    
    public function clear() {
        return wp_cache_flush();
    }
}

// Database Cache Driver
class AANP_Database_Cache_Driver {
    private $config;
    private $table;
    
    public function __construct($config) {
        global $wpdb;
        $this->config = $config;
        $this->table = $wpdb->prefix . $config['table'];
        
        $this->create_table();
    }
    
    private function create_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
            cache_key varchar(191) NOT NULL,
            cache_value longtext NOT NULL,
            expires INT NOT NULL,
            PRIMARY KEY (cache_key),
            INDEX expires_idx (expires)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    public function get($key) {
        global $wpdb;
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT cache_value FROM {$this->table} WHERE cache_key = %s AND expires > %d",
            $this->config['prefix'] . $key,
            time()
        ));
        
        return $result ? unserialize($result) : false;
    }
    
    public function set($key, $value, $ttl) {
        global $wpdb;
        
        $expires = time() + $ttl;
        $serialized = serialize($value);
        
        $result = $wpdb->replace(
            $this->table,
            array(
                'cache_key' => $this->config['prefix'] . $key,
                'cache_value' => $serialized,
                'expires' => $expires
            ),
            array('%s', '%s', '%d')
        );
        
        return $result !== false;
    }
    
    public function delete($key) {
        global $wpdb;
        
        return $wpdb->delete(
            $this->table,
            array('cache_key' => $this->config['prefix'] . $key),
            array('%s')
        ) !== false;
    }
    
    public function clear() {
        global $wpdb;
        
        return $wpdb->query("TRUNCATE TABLE {$this->table}") !== false;
    }
    
    public function garbage_collect() {
        global $wpdb;
        
        return $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table} WHERE expires < %d",
            time()
        )) !== false;
    }
}

// WordPress Transient Cache Driver
class AANP_Transient_Cache_Driver {
    public function get($key) {
        return get_transient($key);
    }
    
    public function set($key, $value, $ttl) {
        return set_transient($key, $value, $ttl);
    }
    
    public function delete($key) {
        return delete_transient($key);
    }
    
    public function clear() {
        global $wpdb;
        
        return $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_aanp_%' OR option_name LIKE '_transient_timeout_aanp_%'"
        ) !== false;
    }
}