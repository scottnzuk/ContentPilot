<?php
/**
 * Advanced Cache Manager for Microservices Architecture
 *
 * Provides enterprise-grade caching with Redis/Memcached support,
 * compression, intelligent invalidation, and performance monitoring.
 *
 * @package AI_Auto_News_Poster\Performance
 * @since 1.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Advanced Cache Manager Class
 */
class AANP_AdvancedCacheManager {
    
    /**
     * Primary cache backend (Redis/Memcached)
     * @var object
     */
    private $primary_backend = null;
    
    /**
     * Secondary cache backend (WordPress transients)
     * @var object
     */
    private $secondary_backend = null;
    
    /**
     * Compression enabled
     * @var bool
     */
    private $compression_enabled = true;
    
    /**
     * Cache configuration
     * @var array
     */
    private $config = array();
    
    /**
     * Logger instance
     * @var AANP_Logger
     */
    private $logger;
    
    /**
     * Cache statistics
     * @var array
     */
    private $stats = array(
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0,
        'compressions' => 0,
        'decompressions' => 0
    );
    
    /**
     * Performance metrics
     * @var array
     */
    private $metrics = array();
    
    /**
     * Cache patterns for batch operations
     * @var array
     */
    private $cache_patterns = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = AANP_Logger::getInstance();
        
        $this->init_config();
        $this->init_cache_backends();
        $this->init_hooks();
    }
    
    /**
     * Initialize cache configuration
     */
    private function init_config() {
        $options = get_option('aanp_settings', array());

        // Check for Redis constants first
        $redis_host = defined('WP_REDIS_HOST') ? constant('WP_REDIS_HOST') : (isset($options['redis_host']) ? $options['redis_host'] : '127.0.0.1');
        $redis_port = defined('WP_REDIS_PORT') ? constant('WP_REDIS_PORT') : (isset($options['redis_port']) ? intval($options['redis_port']) : 6379);
        $redis_password = defined('WP_REDIS_PASSWORD') ? constant('WP_REDIS_PASSWORD') : (isset($options['redis_password']) ? $options['redis_password'] : '');
        $redis_database = defined('WP_REDIS_DB') ? constant('WP_REDIS_DB') : (isset($options['redis_database']) ? intval($options['redis_database']) : 0);

        // Check for Memcached constants first
        $memcached_servers = defined('WP_MEMCACHED_HOSTS') ? constant('WP_MEMCACHED_HOSTS') : (isset($options['memcached_servers']) ? $options['memcached_servers'] : array(
            array('127.0.0.1', 11211)
        ));

        // Determine primary backend based on available constants
        $primary_backend = 'wordpress';
        if (defined('WP_REDIS_HOST') && class_exists('Redis')) {
            $primary_backend = 'redis';
        } elseif (defined('WP_MEMCACHED_HOSTS') && class_exists('Memcached')) {
            $primary_backend = 'memcached';
        } elseif (isset($options['cache_backend'])) {
            $primary_backend = $options['cache_backend'];
        }

        $this->config = array(
            'primary_backend' => $primary_backend,
            'redis_host' => $redis_host,
            'redis_port' => $redis_port,
            'redis_password' => $redis_password,
            'redis_database' => $redis_database,
            'memcached_servers' => $memcached_servers,
            'compression_threshold' => isset($options['compression_threshold']) ? intval($options['compression_threshold']) : 1024, // 1KB
            'compression_level' => isset($options['compression_level']) ? intval($options['compression_level']) : 6,
            'default_ttl' => isset($options['cache_ttl']) ? intval($options['cache_ttl']) : 3600, // 1 hour
            'enable_stats' => true,
            'enable_metrics' => isset($options['enable_cache_metrics']) ? (bool) $options['enable_cache_metrics'] : true,
            'cache_prefix' => 'aanp_',
            'max_key_length' => 250,
            'batch_size' => 100
        );

        $this->compression_enabled = $this->config['compression_threshold'] > 0;
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('aanp_cache_cleanup', array($this, 'cleanup_expired_cache'));
        add_action('init', array($this, 'schedule_cleanup'));
        
        // Cache invalidation hooks
        add_action('save_post', array($this, 'invalidate_post_cache'));
        add_action('deleted_post', array($this, 'invalidate_post_cache'));
        add_action('wp_update_nav_menu', array($this, 'invalidate_menu_cache'));
    }
    
    /**
     * Schedule cache cleanup
     */
    public function schedule_cleanup() {
        if (!wp_next_scheduled('aanp_cache_cleanup')) {
            wp_schedule_event(time(), 'daily', 'aanp_cache_cleanup');
        }
    }
    
    /**
     * Initialize cache backends
     */
    private function init_cache_backends() {
        // Initialize primary backend - use constants-based detection
        if ($this->config['primary_backend'] === 'redis' && defined('WP_REDIS_HOST')) {
            $this->primary_backend = $this->init_redis_backend();
        } elseif ($this->config['primary_backend'] === 'memcached' && defined('WP_MEMCACHED_HOSTS')) {
            $this->primary_backend = $this->init_memcached_backend();
        } else {
            // Fallback to WordPress backend if constants are not defined
            $this->primary_backend = $this->init_wordpress_backend();
        }

        // Initialize secondary backend (WordPress transients/object cache)
        $this->secondary_backend = $this->init_wordpress_backend();

        $this->logger->info('Advanced cache manager initialized', array(
            'primary_backend' => $this->config['primary_backend'],
            'actual_backend' => $this->primary_backend ? get_class($this->primary_backend) : 'wordpress',
            'compression_enabled' => $this->compression_enabled,
            'compression_threshold' => $this->config['compression_threshold']
        ));
    }
    
    /**
     * Initialize Redis backend
     *
     * @return object|false Redis instance or false on failure
     */
    private function init_redis_backend() {
        if (!class_exists('Redis')) {
            $this->logger->warning('Redis extension not available');
            return false;
        }

        try {
            $redis = new Redis();
            $connected = false;

            // Try different connection methods
            $connection_methods = array(
                'connect',
                'pconnect'
            );

            foreach ($connection_methods as $method) {
                try {
                    if ($method === 'connect') {
                        $connected = $redis->connect(
                            $this->config['redis_host'],
                            $this->config['redis_port'],
                            5.0
                        );
                    } else {
                        $connected = $redis->pconnect(
                            $this->config['redis_host'],
                            $this->config['redis_port']
                        );
                    }

                    if ($connected) break;

                } catch (Exception $e) {
                    $this->logger->debug("Redis {$method} failed", array(
                        'error' => $e->getMessage()
                    ));
                }
            }

            if (!$connected) {
                throw new Exception('Failed to connect to Redis');
            }

            // Authenticate if password is set
            if (!empty($this->config['redis_password'])) {
                $redis->auth($this->config['redis_password']);
            }

            // Select database
            if ($this->config['redis_database'] > 0) {
                $redis->select($this->config['redis_database']);
            }

            $this->logger->info('Redis backend initialized successfully', array(
                'host' => $this->config['redis_host'],
                'port' => $this->config['redis_port'],
                'database' => $this->config['redis_database']
            ));

            return $redis;

        } catch (Exception $e) {
            $this->logger->error('Failed to initialize Redis backend', array(
                'error' => $e->getMessage(),
                'host' => $this->config['redis_host'],
                'port' => $this->config['redis_port']
            ));

            // Fallback to WordPress backend
            return false;
        }
    }
    
    /**
     * Initialize Memcached backend
     *
     * @return object|false Memcached instance or false on failure
     */
    private function init_memcached_backend() {
        if (!class_exists('Memcached')) {
            $this->logger->warning('Memcached extension not available');
            return false;
        }

        try {
            $memcached = new Memcached();
            $memcached->setOption(Memcached::OPT_COMPRESSION, true);
            $memcached->setOption(Memcached::OPT_BINARY_PROTOCOL, true);

            // Add servers
            foreach ($this->config['memcached_servers'] as $server) {
                if (count($server) >= 2) {
                    $host = $server[0];
                    $port = intval($server[1]);
                    $weight = isset($server[2]) ? intval($server[2]) : 1;

                    $memcached->addServer($host, $port, $weight);
                }
            }

            // Test connection
            $test_key = $this->config['cache_prefix'] . 'connection_test';
            $test_value = 'connection_ok';

            $memcached->set($test_key, $test_value, 60);
            $result = $memcached->get($test_key);

            if ($result === $test_value) {
                $memcached->delete($test_key);
                $this->logger->info('Memcached backend initialized successfully', array(
                    'servers' => $this->config['memcached_servers']
                ));
                return $memcached;
            }

            throw new Exception('Memcached connection test failed');

        } catch (Exception $e) {
            $this->logger->error('Failed to initialize Memcached backend', array(
                'error' => $e->getMessage(),
                'servers' => $this->config['memcached_servers']
            ));

            // Fallback to WordPress backend
            return false;
        }
    }
    
    /**
     * Initialize WordPress backend
     *
     * @return object WordPress cache wrapper
     */
    private function init_wordpress_backend() {
        return new class($this) {
            private $manager;
            
            public function __construct($manager) {
                $this->manager = $manager;
            }
            
            public function get($key) {
                return get_transient($key);
            }
            
            public function set($key, $value, $ttl) {
                return set_transient($key, $value, $ttl);
            }
            
            public function delete($key) {
                return delete_transient($key);
            }
            
            public function exists($key) {
                return get_transient($key) !== false;
            }
        };
    }
    
    /**
     * Get cache value with intelligent fallback
     *
     * @param string $key Cache key
     * @param mixed $default Default value if not found
     * @param int $ttl Time to live (seconds)
     * @return mixed Cached value or default
     */
    public function get($key, $default = false, $ttl = null) {
        $start_time = microtime(true);
        
        try {
            $this->validate_cache_key($key);
            
            $full_key = $this->build_cache_key($key);
            
            // Try primary backend first
            if ($this->primary_backend) {
                $value = $this->get_from_backend($this->primary_backend, $full_key);

                if ($value !== null) {
                    // Decompress if necessary
                    $value = $this->decompress_data($value);

                    $this->record_hit($key, true);

                    if ($this->config['enable_metrics']) {
                        $this->record_metrics('get', $key, microtime(true) - $start_time, true);
                    }

                    return $value;
                }
            }

            // Try secondary backend (WordPress transients)
            $value = $this->get_from_backend($this->secondary_backend, $full_key);

            if ($value !== null) {
                // Decompress if necessary
                $value = $this->decompress_data($value);

                // Update primary backend for faster access next time if available
                if ($this->primary_backend) {
                    $ttl = $ttl ?? $this->config['default_ttl'];
                    $this->set_to_backend($this->primary_backend, $full_key, $value, $ttl);
                }

                $this->record_hit($key, true);

                if ($this->config['enable_metrics']) {
                    $this->record_metrics('get', $key, microtime(true) - $start_time, true);
                }

                return $value;
            }
            
            // Cache miss
            $this->record_hit($key, false);
            
            if ($this->config['enable_metrics']) {
                $this->record_metrics('get', $key, microtime(true) - $start_time, false);
            }
            
            return $default;
            
        } catch (Exception $e) {
            $this->logger->error('Cache get operation failed', array(
                'key' => $key,
                'error' => $e->getMessage()
            ));
            
            return $default;
        }
    }
    
    /**
     * Set cache value with intelligent compression
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $ttl Time to live (seconds)
     * @return bool Success status
     */
    public function set($key, $value, $ttl = null) {
        $start_time = microtime(true);
        
        try {
            $this->validate_cache_key($key);
            
            $full_key = $this->build_cache_key($key);
            $ttl = $ttl ?? $this->config['default_ttl'];
            
            // Compress if necessary
            $cache_value = $this->compress_data($value);
            
            $success = true;
            
            // Set to primary backend if available
            if ($this->primary_backend) {
                $success &= $this->set_to_backend($this->primary_backend, $full_key, $cache_value, $ttl);
            }
            // Always set to secondary backend for fallback
            $success &= $this->set_to_backend($this->secondary_backend, $full_key, $cache_value, $ttl);
            
            $this->stats['sets']++;
            
            if ($this->config['enable_metrics']) {
                $this->record_metrics('set', $key, microtime(true) - $start_time, $success);
            }
            
            return $success;
            
        } catch (Exception $e) {
            $this->logger->error('Cache set operation failed', array(
                'key' => $key,
                'error' => $e->getMessage()
            ));
            
            return false;
        }
    }
    
    /**
     * Delete cache value
     *
     * @param string $key Cache key
     * @return bool Success status
     */
    public function delete($key) {
        try {
            $this->validate_cache_key($key);
            
            $full_key = $this->build_cache_key($key);
            
            $success = true;
            
            // Delete from primary backend if available
            if ($this->primary_backend) {
                $success &= $this->delete_from_backend($this->primary_backend, $full_key);
            }
            // Always delete from secondary backend
            $success &= $this->delete_from_backend($this->secondary_backend, $full_key);
            
            $this->stats['deletes']++;
            
            return $success;
            
        } catch (Exception $e) {
            $this->logger->error('Cache delete operation failed', array(
                'key' => $key,
                'error' => $e->getMessage()
            ));
            
            return false;
        }
    }
    
    /**
     * Check if cache key exists
     *
     * @param string $key Cache key
     * @return bool True if exists
     */
    public function exists($key) {
        try {
            $this->validate_cache_key($key);
            
            $full_key = $this->build_cache_key($key);
            
            // Check primary backend first
            if ($this->backend_exists($this->primary_backend, $full_key)) {
                return true;
            }
            
            // Check secondary backend
            return $this->backend_exists($this->secondary_backend, $full_key);
            
        } catch (Exception $e) {
            $this->logger->error('Cache exists operation failed', array(
                'key' => $key,
                'error' => $e->getMessage()
            ));
            
            return false;
        }
    }
    
    /**
     * Get multiple cache values at once
     *
     * @param array $keys Array of cache keys
     * @param mixed $default Default value for missing keys
     * @return array Associative array of key => value pairs
     */
    public function get_multi($keys, $default = false) {
        $results = array();
        
        try {
            $full_keys = array();
            foreach ($keys as $key) {
                $this->validate_cache_key($key);
                $full_keys[$key] = $this->build_cache_key($key);
            }
            
            // Try primary backend
            if ($this->primary_backend instanceof Redis) {
                $redis_keys = array_values($full_keys);
                $redis_results = $this->primary_backend->mget($redis_keys);
                
                foreach ($redis_results as $index => $value) {
                    $key = array_keys($full_keys)[$index];
                    if ($value !== false) {
                        $results[$key] = $this->decompress_data($value);
                    } else {
                        $results[$key] = $default;
                    }
                }
            } else {
                // Fallback to individual gets for other backends
                foreach ($full_keys as $key => $full_key) {
                    $value = $this->get_from_backend($this->primary_backend, $full_key);
                    $results[$key] = $value !== null ? $this->decompress_data($value) : $default;
                }
            }
            
            return $results;
            
        } catch (Exception $e) {
            $this->logger->error('Cache get_multi operation failed', array(
                'keys_count' => count($keys),
                'error' => $e->getMessage()
            ));
            
            // Return default values on error
            return array_fill_keys($keys, $default);
        }
    }
    
    /**
     * Set multiple cache values at once
     *
     * @param array $data Associative array of key => value pairs
     * @param int $ttl Default time to live
     * @return bool Success status
     */
    public function set_multi($data, $ttl = null) {
        try {
            $ttl = $ttl ?? $this->config['default_ttl'];
            $success = true;
            
            foreach ($data as $key => $value) {
                $success &= $this->set($key, $value, $ttl);
            }
            
            return $success;
            
        } catch (Exception $e) {
            $this->logger->error('Cache set_multi operation failed', array(
                'data_count' => count($data),
                'error' => $e->getMessage()
            ));
            
            return false;
        }
    }
    
    /**
     * Delete multiple cache values by pattern
     *
     * @param string $pattern Key pattern (supports * wildcard)
     * @return int Number of deleted keys
     */
    public function delete_by_pattern($pattern) {
        try {
            $deleted_count = 0;
            
            // Delete from primary backend
            if ($this->primary_backend instanceof Redis) {
                $keys = $this->primary_backend->keys($this->config['cache_prefix'] . $pattern);
                if (!empty($keys)) {
                    $deleted_count += $this->primary_backend->del($keys);
                }
            }
            
            // For WordPress backend, we need to query the database
            if ($this->secondary_backend instanceof stdClass && method_exists($this->secondary_backend, 'get')) {
                $deleted_count += $this->delete_wordpress_by_pattern($pattern);
            }
            
            return $deleted_count;
            
        } catch (Exception $e) {
            $this->logger->error('Cache delete_by_pattern operation failed', array(
                'pattern' => $pattern,
                'error' => $e->getMessage()
            ));
            
            return 0;
        }
    }
    
    /**
     * Delete WordPress transients by pattern
     *
     * @param string $pattern Pattern to match
     * @return int Number of deleted transients
     */
    private function delete_wordpress_by_pattern($pattern) {
        global $wpdb;
        
        $pattern = $this->config['cache_prefix'] . $pattern;
        $like_pattern = '%' . $wpdb->esc_like($pattern) . '%';
        
        // Find matching transients
        $transients = $wpdb->get_col($wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} 
             WHERE option_name LIKE %s 
             AND option_name LIKE %s",
            '_transient_' . $like_pattern,
            '%' . $wpdb->esc_like($pattern) . '%'
        ));
        
        $deleted_count = 0;
        
        // Delete each transient and its timeout
        foreach ($transients as $transient_name) {
            delete_transient(str_replace('_transient_', '', $transient_name));
            $deleted_count++;
        }
        
        return $deleted_count;
    }
    
    /**
     * Clear all cache
     *
     * @return bool Success status
     */
    public function clear() {
        try {
            $success = true;
            
            // Clear primary backend if available
            if ($this->primary_backend instanceof Redis) {
                $success &= $this->primary_backend->flushdb();
            } elseif ($this->primary_backend instanceof Memcached) {
                $success &= $this->primary_backend->flush();
            }

            // Clear secondary backend (WordPress)
            $success &= $this->clear_wordpress_cache();
            
            return $success;
            
        } catch (Exception $e) {
            $this->logger->error('Cache clear operation failed', array(
                'error' => $e->getMessage()
            ));
            
            return false;
        }
    }
    
    /**
     * Clear WordPress cache
     *
     * @return bool Success status
     */
    private function clear_wordpress_cache() {
        global $wpdb;
        
        try {
            // Delete all plugin transients
            $deleted = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    '_transient_' . $this->config['cache_prefix'] . '%'
                )
            );
            
            // Delete timeout transients
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    '_transient_timeout_' . $this->config['cache_prefix'] . '%'
                )
            );
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('WordPress cache clear failed', array(
                'error' => $e->getMessage()
            ));
            
            return false;
        }
    }
    
    /**
     * Get cache statistics
     *
     * @return array Cache statistics
     */
    public function get_stats() {
        $hit_rate = 0;
        $total_requests = $this->stats['hits'] + $this->stats['misses'];
        
        if ($total_requests > 0) {
            $hit_rate = ($this->stats['hits'] / $total_requests) * 100;
        }
        
        $stats = array_merge($this->stats, array(
            'hit_rate_percent' => round($hit_rate, 2),
            'total_requests' => $total_requests,
            'backend_type' => $this->config['primary_backend'],
            'compression_enabled' => $this->compression_enabled
        ));
        
        // Add backend-specific stats
        if ($this->primary_backend instanceof Redis) {
            $info = $this->primary_backend->info();
            $stats['redis_info'] = array(
                'used_memory' => $info['used_memory'] ?? 0,
                'connected_clients' => $info['connected_clients'] ?? 0,
                'total_commands_processed' => $info['total_commands_processed'] ?? 0
            );
        } elseif ($this->primary_backend instanceof Memcached) {
            $stats['memcached_stats'] = $this->primary_backend->getStats();
        }
        
        return $stats;
    }
    
    /**
     * Get performance metrics
     *
     * @return array Performance metrics
     */
    public function get_metrics() {
        if (!$this->config['enable_metrics']) {
            return array();
        }
        
        return array(
            'operations' => $this->metrics,
            'average_response_time' => $this->calculate_average_response_time(),
            'cache_efficiency' => $this->calculate_cache_efficiency()
        );
    }
    
    /**
     * Cleanup expired cache entries
     */
    public function cleanup_expired_cache() {
        try {
            $this->logger->info('Starting cache cleanup');
            
            // For Redis, expired entries are handled automatically
            if ($this->primary_backend instanceof Redis) {
                // Force a memory optimization
                $this->primary_backend->bgSave();
            }
            
            // For WordPress backend, cleanup is automatic via transients
            // but we can optimize by removing old entries
            
            $cleaned_count = $this->cleanup_wordpress_expired();
            
            $this->logger->info('Cache cleanup completed', array(
                'cleaned_entries' => $cleaned_count
            ));
            
        } catch (Exception $e) {
            $this->logger->error('Cache cleanup failed', array(
                'error' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Cleanup expired WordPress transients
     *
     * @return int Number of cleaned entries
     */
    private function cleanup_wordpress_expired() {
        global $wpdb;
        
        // WordPress doesn't have a built-in way to clean expired transients
        // We can remove old timeout entries that are no longer needed
        
        $cutoff_time = current_time('timestamp') - (24 * 60 * 60); // 24 hours ago
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE %s 
             AND option_name < %s",
            '_transient_timeout_' . $this->config['cache_prefix'] . '%',
            '_transient_timeout_' . $this->config['cache_prefix'] . date('Y-m-d H:i:s', $cutoff_time)
        ));
        
        return intval($deleted);
    }
    
    /**
     * Invalidate cache when posts are updated
     *
     * @param int $post_id Post ID
     */
    public function invalidate_post_cache($post_id) {
        $patterns = array(
            'post_' . $post_id,
            'posts_list',
            'recent_posts',
            'featured_posts'
        );
        
        foreach ($patterns as $pattern) {
            $this->delete_by_pattern($pattern);
        }
    }
    
    /**
     * Invalidate menu cache
     */
    public function invalidate_menu_cache() {
        $this->delete_by_pattern('menu_*');
        $this->delete_by_pattern('navigation_*');
    }
    
    // Private helper methods
    
    /**
     * Validate cache key
     *
     * @param string $key Cache key
     * @throws InvalidArgumentException If key is invalid
     */
    private function validate_cache_key($key) {
        if (empty($key) || !is_string($key)) {
            throw new InvalidArgumentException('Cache key must be a non-empty string');
        }
        
        if (strlen($key) > $this->config['max_key_length']) {
            throw new InvalidArgumentException(
                sprintf('Cache key too long (max %d characters)', $this->config['max_key_length'])
            );
        }
        
        // Check for invalid characters
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $key)) {
            throw new InvalidArgumentException('Cache key contains invalid characters');
        }
    }
    
    /**
     * Build full cache key with prefix
     *
     * @param string $key Cache key
     * @return string Full cache key
     */
    private function build_cache_key($key) {
        return $this->config['cache_prefix'] . $key;
    }
    
    /**
     * Get value from cache backend
     *
     * @param object $backend Cache backend
     * @param string $key Cache key
     * @return mixed Cached value or null
     */
    private function get_from_backend($backend, $key) {
        if ($backend instanceof Redis) {
            $value = $backend->get($key);
            return $value !== false ? $value : null;
        } elseif ($backend instanceof Memcached) {
            $value = $backend->get($key);
            return $value !== false ? $value : null;
        } elseif (method_exists($backend, 'get')) {
            return $backend->get($key);
        }
        
        return null;
    }
    
    /**
     * Set value to cache backend
     *
     * @param object $backend Cache backend
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $ttl Time to live
     * @return bool Success status
     */
    private function set_to_backend($backend, $key, $value, $ttl) {
        try {
            if ($backend instanceof Redis) {
                return $backend->setex($key, $ttl, $value);
            } elseif ($backend instanceof Memcached) {
                return $backend->set($key, $value, $ttl);
            } elseif (method_exists($backend, 'set')) {
                return $backend->set($key, $value, $ttl);
            }
            
            return false;
        } catch (Exception $e) {
            $this->logger->debug('Backend set operation failed', array(
                'backend_type' => get_class($backend),
                'error' => $e->getMessage()
            ));
            return false;
        }
    }
    
    /**
     * Delete value from cache backend
     *
     * @param object $backend Cache backend
     * @param string $key Cache key
     * @return bool Success status
     */
    private function delete_from_backend($backend, $key) {
        try {
            if ($backend instanceof Redis) {
                return $backend->del($key) > 0;
            } elseif ($backend instanceof Memcached) {
                return $backend->delete($key);
            } elseif (method_exists($backend, 'delete')) {
                return $backend->delete($key);
            }
            
            return false;
        } catch (Exception $e) {
            $this->logger->debug('Backend delete operation failed', array(
                'backend_type' => get_class($backend),
                'error' => $e->getMessage()
            ));
            return false;
        }
    }
    
    /**
     * Check if key exists in backend
     *
     * @param object $backend Cache backend
     * @param string $key Cache key
     * @return bool True if exists
     */
    private function backend_exists($backend, $key) {
        if ($backend instanceof Redis) {
            return $backend->exists($key) > 0;
        } elseif ($backend instanceof Memcached) {
            return $backend->get($key) !== false;
        } elseif (method_exists($backend, 'get')) {
            return $backend->get($key) !== false;
        }
        
        return false;
    }
    
    /**
     * Compress data if necessary
     *
     * @param mixed $data Data to compress
     * @return mixed Compressed or original data
     */
    private function compress_data($data) {
        if (!$this->compression_enabled) {
            return $data;
        }
        
        $serialized = serialize($data);
        $uncompressed_length = strlen($serialized);
        
        if ($uncompressed_length < $this->config['compression_threshold']) {
            return $serialized;
        }
        
        $compressed = gzcompress($serialized, $this->config['compression_level']);
        
        if ($compressed !== false) {
            $this->stats['compressions']++;
            // Add compression marker to the beginning
            return 'COMP:' . $compressed;
        }
        
        return $serialized;
    }
    
    /**
     * Decompress data if necessary
     *
     * @param mixed $data Data to decompress
     * @return mixed Decompressed or original data
     */
    private function decompress_data($data) {
        if (is_string($data) && strpos($data, 'COMP:') === 0) {
            $compressed = substr($data, 5);
            $decompressed = gzuncompress($compressed);
            
            if ($decompressed !== false) {
                $this->stats['decompressions']++;
                return unserialize($decompressed);
            }
        }
        
        // Try to unserialize if it's not compressed
        if (is_string($data)) {
            $unserialized = @unserialize($data);
            if ($unserialized !== false || $data === serialize(false)) {
                return $unserialized;
            }
        }
        
        return $data;
    }
    
    /**
     * Record cache hit/miss
     *
     * @param string $key Cache key
     * @param bool $hit True if cache hit
     */
    private function record_hit($key, $hit) {
        if ($hit) {
            $this->stats['hits']++;
        } else {
            $this->stats['misses']++;
        }
    }
    
    /**
     * Record performance metrics
     *
     * @param string $operation Operation type
     * @param string $key Cache key
     * @param float $response_time Response time in seconds
     * @param bool $success Success status
     */
    private function record_metrics($operation, $key, $response_time, $success) {
        if (!$this->config['enable_metrics']) {
            return;
        }
        
        $metric_key = $operation . '_' . md5($key);
        
        if (!isset($this->metrics[$operation])) {
            $this->metrics[$operation] = array(
                'count' => 0,
                'total_time' => 0,
                'success_count' => 0,
                'fail_count' => 0,
                'average_time' => 0
            );
        }
        
        $metric = &$this->metrics[$operation];
        $metric['count']++;
        $metric['total_time'] += $response_time;
        $metric['average_time'] = $metric['total_time'] / $metric['count'];
        
        if ($success) {
            $metric['success_count']++;
        } else {
            $metric['fail_count']++;
        }
    }
    
    /**
     * Calculate average response time
     *
     * @return float Average response time in milliseconds
     */
    private function calculate_average_response_time() {
        $total_time = 0;
        $total_operations = 0;
        
        foreach ($this->metrics as $operation => $data) {
            $total_time += $data['total_time'];
            $total_operations += $data['count'];
        }
        
        return $total_operations > 0 ? ($total_time / $total_operations) * 1000 : 0;
    }
    
    /**
     * Calculate cache efficiency
     *
     * @return float Cache efficiency percentage
     */
    private function calculate_cache_efficiency() {
        $total_requests = $this->stats['hits'] + $this->stats['misses'];
        return $total_requests > 0 ? ($this->stats['hits'] / $total_requests) * 100 : 0;
    }
    
    /**
     * Health check method
     *
     * @return bool Health status
     */
    public function health_check() {
        try {
            // Test primary backend
            $test_key = 'health_check_' . time();
            $test_value = array('test' => 'data', 'timestamp' => time());
            
            $set_success = $this->set($test_key, $test_value, 60);
            $get_value = $this->get($test_key);
            $exists_check = $this->exists($test_key);
            $delete_success = $this->delete($test_key);
            
            // Verify all operations worked correctly
            $health_ok = $set_success && $get_value === $test_value && $exists_check && $delete_success;
            
            if ($health_ok) {
                $this->logger->info('Cache manager health check passed');
                return true;
            } else {
                $this->logger->warning('Cache manager health check failed');
                return false;
            }
            
        } catch (Exception $e) {
            $this->logger->error('Cache manager health check failed', array(
                'error' => $e->getMessage()
            ));
            return false;
        }
    }
}