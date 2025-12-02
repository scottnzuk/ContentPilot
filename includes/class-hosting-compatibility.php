<?php
/**
 * Hosting Compatibility Manager
 * 
 * Automatically detects hosting environment and enables appropriate features
 * with graceful fallback for missing capabilities.
 * 
 * @package AI_Auto_News_Poster
 * @since 1.3.0
 * @author scottnzuk
 */

if (!defined('ABSPATH')) {
    exit;
}

class CP_HostingCompatibility {
    
    /**
     * Detected hosting environment details
     * 
     * @var array
     */
    private $environment = array();
    
    /**
     * Available features based on hosting environment
     * 
     * @var array
     */
    private $available_features = array();
    
    /**
     * Hosting compatibility profile
     * 
     * @var string
     */
    private $hosting_profile = 'shared';
    
    /**
     * Cache system being used
     * 
     * @var string
     */
    private $cache_system = 'database';
    
    /**
     * Constructor - Initialize hosting compatibility detection
     */
    public function __construct() {
        $this->detect_hosting_environment();
        $this->analyze_available_features();
        $this->configure_optimizations();
    }
    
    /**
     * Detect the hosting environment
     * 
     * @return array Environment details
     */
    public function detect_hosting_environment() {
        $environment = array();
        
        // Detect hosting provider indicators
        $environment['hosting_provider'] = $this->detect_hosting_provider();
        $environment['server_type'] = $this->detect_server_type();
        $environment['php_version'] = PHP_VERSION;
        $environment['wp_version'] = get_bloginfo('version');
        $environment['memory_limit'] = $this->get_memory_limit();
        $environment['max_execution_time'] = ini_get('max_execution_time');
        $environment['supports_redis'] = $this->detect_redis_support();
        $environment['supports_memcached'] = $this->detect_memcached_support();
        $environment['supports_python'] = $this->detect_python_support();
        $environment['has_object_cache'] = wp_using_ext_object_cache();
        $environment['server_software'] = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
        $environment['is_vps'] = $this->detect_vps_environment();
        $environment['is_dedicated'] = $this->detect_dedicated_environment();
        $environment['is_managed_wordpress'] = $this->detect_managed_wordpress();
        $environment['openlitespeed'] = $this->detect_openlitespeed();
        $environment['cloudflare'] = $this->detect_cloudflare();
        $environment['bandwidth_limit'] = $this->estimate_bandwidth_limit();
        
        $this->environment = $environment;
        return $environment;
    }
    
    /**
     * Detect hosting provider
     * 
     * @return string Hosting provider identifier
     */
    private function detect_hosting_provider() {
        $server_name = $_SERVER['SERVER_NAME'] ?? '';
        $http_host = $_SERVER['HTTP_HOST'] ?? '';
        
        // Common hosting provider indicators
        $providers = array(
            'shared' => array(
                'bluehost', 'hostgator', 'godaddy', 'namecheap', 
                'ipage', 'fatcow', 'web.com', 'network solutions',
                'startlogic', 'aplusk', 'ipower', 'powweb',
                'webcontrol', 'secure.net', 'intermedia', 'webhero'
            ),
            'vps' => array(
                'digitalocean', 'linode', 'vultr', 'cloudcone',
                'contabo', 'hetzner', 'scaleway', 'contabo',
                'time4vps', 'cloudcone', 'cloudcone'
            ),
            'managed_wordpress' => array(
                'wpengine', 'kinsta', 'pagely', 'pressable',
                'flywheel', 'pantheon', 'siteground managed',
                'cloudways managed', 'cloudflare pages',
                'netlify functions'
            ),
            'cloud' => array(
                'amazonaws', 'google cloud', 'azure', 'cloudflare',
                'vercel', 'netlify', 'heroku'
            )
        );
        
        $host_string = strtolower($server_name . ' ' . $http_host);
        
        foreach ($providers as $type => $indicators) {
            foreach ($indicators as $indicator) {
                if (strpos($host_string, $indicator) !== false) {
                    return $type;
                }
            }
        }
        
        return 'unknown';
    }
    
    /**
     * Detect server type
     * 
     * @return string Server type
     */
    private function detect_server_type() {
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            return 'load_balancer';
        }
        
        if (isset($_SERVER['HTTP_X_REAL_IP'])) {
            return 'proxy';
        }
        
        return 'standard';
    }
    
    /**
     * Get memory limit
     * 
     * @return string Memory limit
     */
    private function get_memory_limit() {
        $limit = ini_get('memory_limit');
        if ($limit == -1) {
            return 'unlimited';
        }
        return $limit;
    }
    
    /**
     * Detect Redis support
     * 
     * @return bool Redis available
     */
    private function detect_redis_support() {
        return class_exists('Redis') && function_exists('redis_connect');
    }
    
    /**
     * Detect Memcached support
     * 
     * @return bool Memcached available
     */
    private function detect_memcached_support() {
        return class_exists('Memcached') || class_exists('Memcache');
    }
    
    /**
     * Detect Python support
     * 
     * @return bool Python available
     */
    private function detect_python_support() {
        // Check if Python is available via system call
        $python_path = $this->find_python_binary();
        if (!$python_path) {
            return false;
        }
        
        // Check if humano package is available
        $humanizer_path = CP_PLUGIN_DIR . 'cp-humanizer/humanizer.py';
        return file_exists($humanizer_path);
    }
    
    /**
     * Find Python binary path
     * 
     * @return string|false Python path or false
     */
    private function find_python_binary() {
        // Common Python paths
        $python_paths = array('python3', 'python', '/usr/bin/python3', '/usr/bin/python');
        
        foreach ($python_paths as $path) {
            if ($this->is_executable($path)) {
                return $path;
            }
        }
        
        return false;
    }
    
    /**
     * Check if file is executable
     * 
     * @param string $path File path
     * @return bool Is executable
     */
    private function is_executable($path) {
        if (!file_exists($path)) {
            return false;
        }
        
        if (is_executable($path)) {
            return true;
        }
        
        // Try to execute and check return code
        $output = array();
        $return_var = 0;
        exec($path . ' --version 2>&1', $output, $return_var);
        
        return $return_var === 0;
    }
    
    /**
     * Detect VPS environment
     * 
     * @return bool Is VPS
     */
    private function detect_vps_environment() {
        $hosting_provider = $this->detect_hosting_provider();
        return in_array($hosting_provider, array('vps', 'cloud'));
    }
    
    /**
     * Detect dedicated server environment
     * 
     * @return bool Is dedicated server
     */
    private function detect_dedicated_environment() {
        $memory_limit = ini_get('memory_limit');
        if ($memory_limit && $memory_limit != -1) {
            $memory_bytes = $this->return_bytes($memory_limit);
            return $memory_bytes >= 2147483648; // 2GB or higher
        }
        
        return $this->environment['hosting_provider'] === 'dedicated';
    }
    
    /**
     * Detect managed WordPress hosting
     * 
     * @return bool Is managed WordPress
     */
    private function detect_managed_wordpress() {
        return $this->environment['hosting_provider'] === 'managed_wordpress';
    }
    
    /**
     * Detect OpenLiteSpeed server
     * 
     * @return bool OpenLiteSpeed detected
     */
    private function detect_openlitespeed() {
        $server_software = $_SERVER['SERVER_SOFTWARE'] ?? '';
        return stripos($server_software, 'openlitespeed') !== false;
    }
    
    /**
     * Detect Cloudflare
     * 
     * @return bool Cloudflare detected
     */
    private function detect_cloudflare() {
        $server_software = $_SERVER['SERVER_SOFTWARE'] ?? '';
        $http_cf_connecting_ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';
        return stripos($server_software, 'cloudflare') !== false || !empty($http_cf_connecting_ip);
    }
    
    /**
     * Estimate bandwidth limit
     * 
     * @return string Bandwidth limit estimate
     */
    private function estimate_bandwidth_limit() {
        $hosting_provider = $this->environment['hosting_provider'];
        
        switch ($hosting_provider) {
            case 'shared':
                return 'unlimited';
            case 'vps':
                return 'high';
            case 'managed_wordpress':
                return 'high';
            case 'cloud':
                return 'high';
            default:
                return 'unknown';
        }
    }
    
    /**
     * Convert memory string to bytes
     * 
     * @param string $val Memory value string
     * @return int Bytes
     */
    private function return_bytes($val) {
        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        $val = (int)$val;
        switch($last) {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }
        return $val;
    }
    
    /**
     * Analyze available features based on environment
     * 
     * @return array Available features
     */
    private function analyze_available_features() {
        $features = array();
        
        // Caching systems
        if ($this->environment['supports_redis']) {
            $features['cache_system'] = 'redis';
            $features['cache_level'] = 'advanced';
        } elseif ($this->environment['supports_memcached']) {
            $features['cache_system'] = 'memcached';
            $features['cache_level'] = 'advanced';
        } elseif ($this->environment['has_object_cache']) {
            $features['cache_system'] = 'object_cache';
            $features['cache_level'] = 'intermediate';
        } else {
            $features['cache_system'] = 'database';
            $features['cache_level'] = 'basic';
        }
        
        // Content enhancement
        if ($this->environment['supports_python']) {
            $features['content_enhancement'] = 'python_offline';
            $features['enhancement_level'] = 'advanced';
        } else {
            $features['content_enhancement'] = 'php_fallback';
            $features['enhancement_level'] = 'basic';
        }
        
        // Performance monitoring
        if ($this->environment['is_vps'] || $this->environment['is_dedicated']) {
            $features['performance_monitoring'] = 'full';
        } else {
            $features['performance_monitoring'] = 'basic';
        }
        
        // PWA features
        $features['pwa_support'] = $this->check_pwa_support();
        
        // Analytics level
        if ($this->environment['memory_limit'] === 'unlimited' || $this->return_bytes($this->environment['memory_limit']) >= 134217728) {
            $features['analytics_level'] = 'full';
        } else {
            $features['analytics_level'] = 'basic';
        }
        
        // Concurrent processing
        if ($this->environment['max_execution_time'] >= 120) {
            $features['concurrent_processing'] = true;
        } else {
            $features['concurrent_processing'] = false;
        }
        
        $this->available_features = $features;
        return $features;
    }
    
    /**
     * Check PWA support
     * 
     * @return bool PWA supported
     */
    private function check_pwa_support() {
        // Check if modern browser features are available
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Block older browsers that don't support PWA features
        $blocked_browsers = array('MSIE', 'Trident/');
        foreach ($blocked_browsers as $blocked) {
            if (strpos($user_agent, $blocked) !== false) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Configure optimizations based on detected environment
     */
    private function configure_optimizations() {
        // Determine hosting profile
        if ($this->environment['is_dedicated']) {
            $this->hosting_profile = 'dedicated';
        } elseif ($this->environment['is_vps']) {
            $this->hosting_profile = 'vps';
        } elseif ($this->environment['is_managed_wordpress']) {
            $this->hosting_profile = 'managed_wordpress';
        } else {
            $this->hosting_profile = 'shared';
        }
        
        // Determine cache system
        $this->cache_system = $this->available_features['cache_system'];
        
        // Apply hosting-specific optimizations
        $this->apply_hosting_optimizations();
    }
    
    /**
     * Apply hosting-specific optimizations
     */
    private function apply_hosting_optimizations() {
        $profile = $this->hosting_profile;
        
        switch ($profile) {
            case 'shared':
                $this->optimize_for_shared_hosting();
                break;
            case 'vps':
                $this->optimize_for_vps();
                break;
            case 'dedicated':
                $this->optimize_for_dedicated();
                break;
            case 'managed_wordpress':
                $this->optimize_for_managed_wordpress();
                break;
        }
    }
    
    /**
     * Optimize for shared hosting environment
     */
    private function optimize_for_shared_hosting() {
        // Reduce memory usage
        if (!ini_get('memory_limit') || $this->return_bytes(ini_get('memory_limit')) > 268435456) {
            ini_set('memory_limit', '256M');
        }
        
        // Reduce max execution time
        if (!ini_get('max_execution_time') || ini_get('max_execution_time') > 60) {
            set_time_limit(60);
        }
        
        // Use basic caching only
        add_filter('cp_cache_level', function() {
            return 'basic';
        });
        
        // Disable heavy analytics
        add_filter('cp_analytics_level', function() {
            return 'minimal';
        });
        
        // Use database transients for caching
        add_action('init', array($this, 'enable_database_caching'));
    }
    
    /**
     * Optimize for VPS environment
     */
    private function optimize_for_vps() {
        // Increase memory limit if needed
        if (ini_get('memory_limit') && $this->return_bytes(ini_get('memory_limit')) < 536870912) {
            ini_set('memory_limit', '512M');
        }
        
        // Enable Redis/Memcached caching if available
        if ($this->environment['supports_redis'] || $this->environment['supports_memcached']) {
            add_action('init', array($this, 'enable_advanced_caching'));
        }
        
        // Enable full performance monitoring
        add_filter('cp_performance_monitoring', function() {
            return 'full';
        });
        
        // Enable concurrent processing
        add_filter('cp_concurrent_processing', function() {
            return true;
        });
    }
    
    /**
     * Optimize for dedicated server environment
     */
    private function optimize_for_dedicated() {
        // High-performance settings
        ini_set('memory_limit', '1G');
        set_time_limit(300);
        
        // Enable all advanced features
        add_filter('cp_cache_level', function() {
            return 'advanced';
        });
        
        add_filter('cp_performance_monitoring', function() {
            return 'full';
        });
        
        add_filter('cp_analytics_level', function() {
            return 'full';
        });
        
        add_filter('cp_concurrent_processing', function() {
            return true;
        });
        
        // Enable advanced caching
        add_action('init', array($this, 'enable_advanced_caching'));
    }
    
    /**
     * Optimize for managed WordPress hosting
     */
    private function optimize_for_managed_wordpress() {
        // Use provider-specific optimizations
        if ($this->environment['openlitespeed']) {
            add_action('init', array($this, 'optimize_for_openlitespeed'));
        }
        
        if ($this->environment['cloudflare']) {
            add_action('init', array($this, 'optimize_for_cloudflare'));
        }
        
        // Enable WordPress-specific optimizations
        add_filter('cp_use_wordpress_caching', function() {
            return true;
        });
        
        // Enable CDN integration if available
        add_filter('cp_cdn_support', function() {
            return true;
        });
    }
    
    /**
     * Enable database caching (fallback)
     */
    public function enable_database_caching() {
        // Use WordPress transients for caching
        add_filter('cp_cache_driver', function() {
            return 'transients';
        });
    }
    
    /**
     * Enable advanced caching (Redis/Memcached)
     */
    public function enable_advanced_caching() {
        if ($this->environment['supports_redis']) {
            add_filter('cp_cache_driver', function() {
                return 'redis';
            });
        } elseif ($this->environment['supports_memcached']) {
            add_filter('cp_cache_driver', function() {
                return 'memcached';
            });
        }
    }
    
    /**
     * Optimize for OpenLiteSpeed
     */
    public function optimize_for_openlitespeed() {
        add_filter('cp_server_optimization', function() {
            return 'openlitespeed';
        });
        
        // Enable OpenLiteSpeed-specific optimizations
        add_filter('cp_use_litespeed_cache', function() {
            return true;
        });
    }
    
    /**
     * Optimize for Cloudflare
     */
    public function optimize_for_cloudflare() {
        add_filter('cp_cdn_provider', function() {
            return 'cloudflare';
        });
        
        // Enable Cloudflare-specific optimizations
        add_filter('cp_use_cloudflare_cache', function() {
            return true;
        });
    }
    
    /**
     * Get hosting compatibility report
     * 
     * @return array Compatibility report
     */
    public function get_compatibility_report() {
        return array(
            'hosting_profile' => $this->hosting_profile,
            'environment' => $this->environment,
            'available_features' => $this->available_features,
            'cache_system' => $this->cache_system,
            'optimization_level' => $this->get_optimization_level(),
            'compatibility_score' => $this->calculate_compatibility_score()
        );
    }
    
    /**
     * Get optimization level
     * 
     * @return string Optimization level
     */
    private function get_optimization_level() {
        switch ($this->hosting_profile) {
            case 'dedicated':
                return 'maximum';
            case 'vps':
                return 'high';
            case 'managed_wordpress':
                return 'high';
            default:
                return 'standard';
        }
    }
    
    /**
     * Calculate compatibility score
     * 
     * @return int Compatibility score (0-100)
     */
    private function calculate_compatibility_score() {
        $score = 100;
        
        // Deduct points for missing features
        if ($this->available_features['cache_level'] === 'basic') {
            $score -= 10;
        }
        
        if ($this->available_features['enhancement_level'] === 'basic') {
            $score -= 5;
        }
        
        if ($this->available_features['performance_monitoring'] === 'basic') {
            $score -= 5;
        }
        
        if (!$this->available_features['concurrent_processing']) {
            $score -= 10;
        }
        
        if ($this->environment['memory_limit'] !== 'unlimited' && 
            $this->return_bytes($this->environment['memory_limit']) < 134217728) {
            $score -= 15;
        }
        
        return max(0, $score);
    }
    
    /**
     * Check if feature is available
     * 
     * @param string $feature Feature name
     * @return bool Feature available
     */
    public function is_feature_available($feature) {
        return isset($this->available_features[$feature]) && $this->available_features[$feature];
    }
    
    /**
     * Get recommended settings for current environment
     * 
     * @return array Recommended settings
     */
    public function get_recommended_settings() {
        $settings = array();
        
        // Cache settings
        $settings['cache_duration'] = $this->get_recommended_cache_duration();
        $settings['batch_size'] = $this->get_recommended_batch_size();
        $settings['memory_limit'] = $this->get_recommended_memory_limit();
        $settings['enable_analytics'] = $this->should_enable_analytics();
        $settings['enable_monitoring'] = $this->should_enable_monitoring();
        
        return $settings;
    }
    
    /**
     * Get recommended cache duration
     * 
     * @return int Cache duration in seconds
     */
    private function get_recommended_cache_duration() {
        switch ($this->hosting_profile) {
            case 'shared':
                return 1800; // 30 minutes
            case 'vps':
                return 3600; // 1 hour
            case 'dedicated':
                return 7200; // 2 hours
            case 'managed_wordpress':
                return 3600; // 1 hour
            default:
                return 1800; // 30 minutes
        }
    }
    
    /**
     * Get recommended batch size
     * 
     * @return int Batch size
     */
    private function get_recommended_batch_size() {
        switch ($this->hosting_profile) {
            case 'shared':
                return 5;
            case 'vps':
                return 15;
            case 'dedicated':
                return 30;
            case 'managed_wordpress':
                return 20;
            default:
                return 5;
        }
    }
    
    /**
     * Get recommended memory limit
     * 
     * @return string Memory limit
     */
    private function get_recommended_memory_limit() {
        switch ($this->hosting_profile) {
            case 'shared':
                return '128M';
            case 'vps':
                return '256M';
            case 'dedicated':
                return '512M';
            case 'managed_wordpress':
                return '256M';
            default:
                return '128M';
        }
    }
    
    /**
     * Should enable analytics
     * 
     * @return bool Enable analytics
     */
    private function should_enable_analytics() {
        return $this->available_features['analytics_level'] !== 'minimal';
    }
    
    /**
     * Should enable monitoring
     * 
     * @return bool Enable monitoring
     */
    private function should_enable_monitoring() {
        return $this->available_features['performance_monitoring'] !== 'basic';
    }
    
    /**
     * Validate hosting environment
     * 
     * @return array Validation results
     */
    public function validate_environment() {
        $validation = array(
            'is_compatible' => true,
            'warnings' => array(),
            'errors' => array(),
            'recommendations' => array()
        );
        
        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            $validation['errors'][] = 'PHP 7.4 or higher required';
            $validation['is_compatible'] = false;
        }
        
        // Check WordPress version
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            $validation['errors'][] = 'WordPress 5.0 or higher required';
            $validation['is_compatible'] = false;
        }
        
        // Check memory limit
        $memory_limit = $this->return_bytes(ini_get('memory_limit'));
        if ($memory_limit > 0 && $memory_limit < 67108864) { // 64MB
            $validation['errors'][] = 'Memory limit too low (minimum 64MB)';
            $validation['is_compatible'] = false;
        } elseif ($memory_limit > 0 && $memory_limit < 134217728) { // 128MB
            $validation['warnings'][] = 'Memory limit below recommended (128MB)';
        }
        
        // Check execution time
        $max_execution = ini_get('max_execution_time');
        if ($max_execution > 0 && $max_execution < 30) {
            $validation['warnings'][] = 'Max execution time below recommended (30 seconds)';
        }
        
        // Provide recommendations
        if ($this->hosting_profile === 'shared') {
            $validation['recommendations'][] = 'Consider upgrading to VPS for better performance';
        }
        
        if (!$this->environment['supports_redis'] && !$this->environment['supports_memcached']) {
            $validation['recommendations'][] = 'Consider enabling Redis or Memcached for better caching performance';
        }
        
        return $validation;
    }
}