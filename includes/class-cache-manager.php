<?php
/**
 * Cache Manager Class
 *
 * @package ContentPilot
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class CP_Cache_Manager {

    private $cache_group = 'aanp_cache';
    private $cache_expiry = 3600; // 1 hour default

    /**
     * @var object|null Redis or Memcached instance
     */
    private $fast_cache_backend = null;

    /**
     * @var string Cache backend type ('redis', 'memcached', or 'wordpress')
     */
    private $cache_backend_type = 'wordpress';

    /**
     * Constructor
     */
    public function __construct() {
        $this->detect_fast_cache_backend();
        add_action('init', array($this, 'init_cache_hooks'));
    }
    
    /**
     * Detect and initialize Redis or Memcached backend
     */
    private function detect_fast_cache_backend() {
        // Check for Redis configuration
        if (defined('WP_REDIS_HOST') && class_exists('Redis')) {
            $this->initialize_redis_backend();
            return;
        }

        // Check for Memcached configuration
        if (defined('WP_MEMCACHED_HOSTS') && class_exists('Memcached')) {
            $this->initialize_memcached_backend();
            return;
        }
    }

    /**
     * Initialize Redis backend
     */
    private function initialize_redis_backend() {
        try {
            $redis = new Redis();

            // Try to connect to Redis
            $connected = $redis->connect(
                constant('WP_REDIS_HOST'),
                defined('WP_REDIS_PORT') ? constant('WP_REDIS_PORT') : 6379,
                5.0 // timeout
            );

            if (!$connected) {
                return;
            }

            // Authenticate if password is set
            if (defined('WP_REDIS_PASSWORD') && constant('WP_REDIS_PASSWORD')) {
                $redis->auth(constant('WP_REDIS_PASSWORD'));
            }

            // Select database if specified
            if (defined('WP_REDIS_DB')) {
                $redis->select(constant('WP_REDIS_DB'));
            }

            $this->fast_cache_backend = $redis;
            $this->cache_backend_type = 'redis';

            // Add error handler for Redis
            CP_Error_Handler::getInstance()->handle_error(
                'Redis cache backend initialized successfully',
                array(
                    'host' => constant('WP_REDIS_HOST'),
                    'port' => defined('WP_REDIS_PORT') ? constant('WP_REDIS_PORT') : 6379,
                    'db' => defined('WP_REDIS_DB') ? constant('WP_REDIS_DB') : 0
                ),
                'cache'
            );

        } catch (Exception $e) {
            CP_Error_Handler::getInstance()->handle_error(
                'Failed to initialize Redis cache backend: ' . $e->getMessage(),
                array('exception' => $e),
                'cache'
            );
            $this->fast_cache_backend = null;
            $this->cache_backend_type = 'wordpress';
        }
    }

    /**
     * Initialize Memcached backend
     */
    private function initialize_memcached_backend() {
        try {
            $memcached = new Memcached();
            $memcached->setOption(Memcached::OPT_COMPRESSION, true);
            $memcached->setOption(Memcached::OPT_BINARY_PROTOCOL, true);

            // Add servers from WP_MEMCACHED_HOSTS constant
            $servers = constant('WP_MEMCACHED_HOSTS');
            if (is_array($servers)) {
                foreach ($servers as $server) {
                    if (is_array($server) && count($server) >= 2) {
                        $host = $server[0];
                        $port = isset($server[1]) ? intval($server[1]) : 11211;
                        $weight = isset($server[2]) ? intval($server[2]) : 1;
                        $memcached->addServer($host, $port, $weight);
                    }
                }
            }

            // Test connection
            $test_key = 'aanp_connection_test_' . time();
            $test_value = 'connection_ok';
            $memcached->set($test_key, $test_value, 10);
            $result = $memcached->get($test_key);

            if ($result === $test_value) {
                $memcached->delete($test_key);
                $this->fast_cache_backend = $memcached;
                $this->cache_backend_type = 'memcached';

                CP_Error_Handler::getInstance()->handle_error(
                    'Memcached cache backend initialized successfully',
                    array('servers' => $servers),
                    'cache'
                );
            }

        } catch (Exception $e) {
            CP_Error_Handler::getInstance()->handle_error(
                'Failed to initialize Memcached cache backend: ' . $e->getMessage(),
                array('exception' => $e),
                'cache'
            );
            $this->fast_cache_backend = null;
            $this->cache_backend_type = 'wordpress';
        }
    }

    /**
     * Initialize cache hooks
     */
    public function init_cache_hooks() {
        // Purge cache when posts are created/updated
        add_action('aanp_post_created', array($this, 'purge_post_cache'));
        add_action('aanp_settings_updated', array($this, 'purge_settings_cache'));
    }
    
    /**
     * Get cached data
     *
     * @param string $key Cache key
     * @param mixed $default Default value
     * @return mixed Cached data or default
     */
    public function get($key, $default = false) {
        $cache_key = $this->get_cache_key($key);

        // Try fast cache backend first (Redis/Memcached)
        if ($this->fast_cache_backend) {
            try {
                $data = $this->get_from_fast_backend($cache_key);
                if ($data !== false) {
                    return $data;
                }
            } catch (Exception $e) {
                CP_Error_Handler::getInstance()->handle_error(
                    'Fast cache backend get operation failed: ' . $e->getMessage(),
                    array('key' => $key, 'backend' => $this->cache_backend_type),
                    'cache'
                );
            }
        }

        // Try WordPress object cache
        $data = wp_cache_get($cache_key, $this->cache_group);

        if ($data !== false) {
            // Update fast cache backend with this value for faster access next time
            if ($this->fast_cache_backend) {
                try {
                    $this->set_to_fast_backend($cache_key, $data, $this->cache_expiry);
                } catch (Exception $e) {
                    CP_Error_Handler::getInstance()->handle_error(
                        'Failed to update fast cache backend: ' . $e->getMessage(),
                        array('key' => $key, 'backend' => $this->cache_backend_type),
                        'cache'
                    );
                }
            }
            return $data;
        }

        // Try transient cache
        $data = get_transient($cache_key);

        if ($data !== false && $this->fast_cache_backend) {
            // Update fast cache backend with this value for faster access next time
            try {
                $this->set_to_fast_backend($cache_key, $data, $this->cache_expiry);
            } catch (Exception $e) {
                CP_Error_Handler::getInstance()->handle_error(
                    'Failed to update fast cache backend: ' . $e->getMessage(),
                    array('key' => $key, 'backend' => $this->cache_backend_type),
                    'cache'
                );
            }
        }

        return $data !== false ? $data : $default;
    }
    
    /**
     * Set cached data
     *
     * @param string $key Cache key
     * @param mixed $data Data to cache
     * @param int $expiry Cache expiry in seconds
     * @return bool Success status
     */
    public function set($key, $data, $expiry = null) {
        if ($expiry === null) {
            $expiry = $this->cache_expiry;
        }

        $cache_key = $this->get_cache_key($key);
        $success = true;

        // Set in fast cache backend first (Redis/Memcached)
        if ($this->fast_cache_backend) {
            try {
                $success = $this->set_to_fast_backend($cache_key, $data, $expiry);
            } catch (Exception $e) {
                CP_Error_Handler::getInstance()->handle_error(
                    'Fast cache backend set operation failed: ' . $e->getMessage(),
                    array('key' => $key, 'backend' => $this->cache_backend_type),
                    'cache'
                );
                $success = false;
            }
        }

        // Set in WordPress object cache
        wp_cache_set($cache_key, $data, $this->cache_group, $expiry);

        // Set in transient cache as fallback
        $transient_success = set_transient($cache_key, $data, $expiry);

        return $success && $transient_success;
    }
    
    /**
     * Delete cached data
     *
     * @param string $key Cache key
     * @return bool Success status
     */
    public function delete($key) {
        $cache_key = $this->get_cache_key($key);
        $success = true;

        // Delete from fast cache backend first (Redis/Memcached)
        if ($this->fast_cache_backend) {
            try {
                $success = $this->delete_from_fast_backend($cache_key);
            } catch (Exception $e) {
                CP_Error_Handler::getInstance()->handle_error(
                    'Fast cache backend delete operation failed: ' . $e->getMessage(),
                    array('key' => $key, 'backend' => $this->cache_backend_type),
                    'cache'
                );
                $success = false;
            }
        }

        // Delete from object cache
        wp_cache_delete($cache_key, $this->cache_group);

        // Delete from transient cache
        $transient_success = delete_transient($cache_key);

        return $success && $transient_success;
    }
    
    /**
     * Purge all plugin cache
     */
    public function purge_all() {
        // Clear WordPress object cache for our group
        wp_cache_flush_group($this->cache_group);
        
        // Clear transients
        $this->clear_transients();
        
        // Purge external caches
        $this->purge_external_cache();
    }
    
    /**
     * Purge post-related cache
     */
    public function purge_post_cache() {
        $this->delete('recent_posts');
        $this->delete('post_stats');
        $this->purge_external_cache();
    }
    
    /**
     * Purge settings cache
     */
    public function purge_settings_cache() {
        $this->delete('plugin_settings');
        $this->delete('rss_feeds');
    }
    
    /**
     * Get value from fast cache backend
     *
     * @param string $key Cache key
     * @return mixed Cached data or false
     */
    private function get_from_fast_backend($key) {
        if ($this->cache_backend_type === 'redis' && $this->fast_cache_backend instanceof Redis) {
            return $this->fast_cache_backend->get($key);
        } elseif ($this->cache_backend_type === 'memcached' && $this->fast_cache_backend instanceof Memcached) {
            return $this->fast_cache_backend->get($key);
        }
        return false;
    }

    /**
     * Set value to fast cache backend
     *
     * @param string $key Cache key
     * @param mixed $data Data to cache
     * @param int $expiry Cache expiry in seconds
     * @return bool Success status
     */
    private function set_to_fast_backend($key, $data, $expiry) {
        if ($this->cache_backend_type === 'redis' && $this->fast_cache_backend instanceof Redis) {
            return $this->fast_cache_backend->setex($key, $expiry, $data);
        } elseif ($this->cache_backend_type === 'memcached' && $this->fast_cache_backend instanceof Memcached) {
            return $this->fast_cache_backend->set($key, $data, $expiry);
        }
        return false;
    }

    /**
     * Delete value from fast cache backend
     *
     * @param string $key Cache key
     * @return bool Success status
     */
    private function delete_from_fast_backend($key) {
        if ($this->cache_backend_type === 'redis' && $this->fast_cache_backend instanceof Redis) {
            return $this->fast_cache_backend->del($key) > 0;
        } elseif ($this->cache_backend_type === 'memcached' && $this->fast_cache_backend instanceof Memcached) {
            return $this->fast_cache_backend->delete($key);
        }
        return false;
    }

    /**
     * Get cache key with prefix
     *
     * @param string $key Original key
     * @return string Prefixed cache key
     */
    private function get_cache_key($key) {
        return 'aanp_' . md5($key);
    }
    
    /**
     * Clear all plugin transients
     */
    private function clear_transients() {
        global $wpdb;
        
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_aanp_%'
            )
        );
        
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_timeout_aanp_%'
            )
        );
    }
    
    /**
     * Purge external cache systems
     */
    private function purge_external_cache() {
        // OpenLiteSpeed Cache
        if (function_exists('litespeed_purge_all')) {
            litespeed_purge_all();
        }
        
        // W3 Total Cache
        if (function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
        }
        
        // WP Super Cache
        if (function_exists('wp_cache_clear_cache')) {
            wp_cache_clear_cache();
        }
        
        // WP Rocket
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
        }
        
        // Cloudflare
        $this->purge_cloudflare_cache();
    }
    
    /**
     * Purge Cloudflare cache if configured
     */
    private function purge_cloudflare_cache() {
        $options = get_option('aanp_settings', array());
        
        if (empty($options['cloudflare_zone_id']) || empty($options['cloudflare_api_key'])) {
            return;
        }
        
        $url = 'https://api.cloudflare.com/client/v4/zones/' . $options['cloudflare_zone_id'] . '/purge_cache';
        
        wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $options['cloudflare_api_key'],
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array('purge_everything' => true)),
            'timeout' => 30
        ));
    }
    
    /**
     * Get cache statistics
     *
     * @return array Cache stats
     */
    public function get_cache_stats() {
        global $wpdb;

        $transient_count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_aanp_%'
            )
        );

        $stats = array(
            'transients' => (int) $transient_count,
            'object_cache_enabled' => wp_using_ext_object_cache(),
            'cache_plugins' => $this->detect_cache_plugins(),
            'fast_cache_backend' => $this->cache_backend_type
        );

        // Add Redis-specific stats if available
        if ($this->cache_backend_type === 'redis' && $this->fast_cache_backend instanceof Redis) {
            try {
                $redis_info = $this->fast_cache_backend->info();
                $stats['redis_stats'] = array(
                    'used_memory' => $redis_info['used_memory'] ?? 0,
                    'connected_clients' => $redis_info['connected_clients'] ?? 0,
                    'uptime' => $redis_info['uptime_in_seconds'] ?? 0
                );
            } catch (Exception $e) {
                CP_Error_Handler::getInstance()->handle_error(
                    'Failed to get Redis stats: ' . $e->getMessage(),
                    array('exception' => $e),
                    'cache'
                );
            }
        }

        // Add Memcached-specific stats if available
        if ($this->cache_backend_type === 'memcached' && $this->fast_cache_backend instanceof Memcached) {
            try {
                $stats['memcached_stats'] = $this->fast_cache_backend->getStats();
            } catch (Exception $e) {
                CP_Error_Handler::getInstance()->handle_error(
                    'Failed to get Memcached stats: ' . $e->getMessage(),
                    array('exception' => $e),
                    'cache'
                );
            }
        }

        return $stats;
    }
    
    /**
     * Detect active cache plugins
     *
     * @return array Active cache plugins
     */
    private function detect_cache_plugins() {
        $plugins = array();
        
        if (defined('LSCWP_V')) {
            $plugins[] = 'LiteSpeed Cache';
        }
        
        if (defined('W3TC')) {
            $plugins[] = 'W3 Total Cache';
        }
        
        if (defined('WP_CACHE') && WP_CACHE) {
            $plugins[] = 'WP Super Cache';
        }
        
        if (defined('WP_ROCKET_VERSION')) {
            $plugins[] = 'WP Rocket';
        }
        
        return $plugins;
    }
}