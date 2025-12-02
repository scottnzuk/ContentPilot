<?php
/**
 * OpenLiteSpeed Compatibility & Performance Optimizer
 * 
 * Provides OpenLiteSpeed-specific optimizations including ESI blocks,
 * advanced caching, performance headers, and server-specific configurations
 * for maximum performance on OpenLiteSpeed servers.
 *
 * @package AI_Auto_News_Poster
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AANP_OpenLiteSpeed_Optimizer {
    
    /**
     * Cache manager instance
     * @var AANP_Advanced_Cache_Manager
     */
    private $cache_manager;
    
    /**
     * Performance optimizer instance
     * @var AANP_Performance_Optimizer
     */
    private $performance_optimizer;
    
    /**
     * Logger instance
     * @var AANP_Logger
     */
    private $logger;
    
    /**
     * OpenLiteSpeed configuration settings
     * @var array
     */
    private $ols_config = array();
    
    /**
     * ESI enabled status
     * @var bool
     */
    private $esi_enabled = false;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = AANP_Logger::getInstance();
        $this->performance_optimizer = new AANP_Performance_Optimizer();
        $this->cache_manager = new AANP_Advanced_Cache_Manager();
        
        // Load OpenLiteSpeed configuration
        $this->load_ols_configuration();
        
        // Initialize OpenLiteSpeed optimizations
        $this->init_optimizations();
        
        $this->logger->info('OpenLiteSpeed Optimizer initialized', array(
            'esi_enabled' => $this->esi_enabled,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
        ));
    }
    
    /**
     * Load OpenLiteSpeed configuration
     */
    private function load_ols_configuration() {
        // Check if running on OpenLiteSpeed
        $server_software = $_SERVER['SERVER_SOFTWARE'] ?? '';
        $is_ols = (strpos($server_software, 'OpenLiteSpeed') !== false) || 
                  (strpos($server_software, 'LiteSpeed') !== false);
        
        if ($is_ols) {
            $this->ols_config = array(
                'esi_support' => $this->check_esi_support(),
                'lscache_support' => $this->check_lscache_support(),
                'quic_support' => $this->check_quic_support(),
                'brotli_support' => $this->check_brotli_support(),
                'http2_support' => $this->check_http2_support()
            );
            
            $this->esi_enabled = $this->ols_config['esi_support'];
            
            $this->logger->info('OpenLiteSpeed detected and configured', array(
                'configuration' => $this->ols_config
            ));
        } else {
            $this->logger->info('Non-OpenLiteSpeed server detected, using compatibility mode');
        }
    }
    
    /**
     * Check ESI support availability
     */
    private function check_esi_support() {
        // Check for ESI headers and cache headers
        $esi_headers = array(
            'X-LS-Cache' => 'on',
            'esi' => 'on',
            'Cache-Control' => 'public, s-maxage=300'
        );
        
        // Test ESI support by setting headers
        foreach ($esi_headers as $header => $value) {
            header($header . ': ' . $value);
        }
        
        return function_exists('header_remove') && 
               isset($_SERVER['HTTP_X_LS_CACHE']) || 
               $this->detect_esi_through_headers();
    }
    
    /**
     * Detect ESI through headers analysis
     */
    private function detect_esi_through_headers() {
        // Check for LiteSpeed specific headers
        $ols_headers = array(
            'HTTP_X_LSCACHE',
            'HTTP_X_LS_CACHE',
            'HTTP_X_LITESPEED_CACHE',
            'HTTP_X_LSESI'
        );
        
        foreach ($ols_headers as $header) {
            if (isset($_SERVER[$header])) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check LiteSpeed Cache support
     */
    private function check_lscache_support() {
        return function_exists('wp_cache_flush') || 
               function_exists('litespeed_purge_all') ||
               isset($_SERVER['HTTP_X_LSCACHE']);
    }
    
    /**
     * Check QUIC/HTTP3 support
     */
    private function check_quic_support() {
        return isset($_SERVER['HTTP_QUIC']) || 
               isset($_SERVER['HTTP_HTTP2_SETTINGS']) ||
               (isset($_SERVER['HTTP_SEC_WEBSOCKET']) && 
                strpos($_SERVER['HTTP_SEC_WEBSOCKET'], 'quic') !== false);
    }
    
    /**
     * Check Brotli compression support
     */
    private function check_brotli_support() {
        return function_exists('brotli_compress') ||
               isset($_SERVER['HTTP_ACCEPT_ENCODING']) && 
               strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'br') !== false;
    }
    
    /**
     * Check HTTP/2 support
     */
    private function check_http2_support() {
        return isset($_SERVER['HTTP_SEC_WEBSOCKET']) && 
               (isset($_SERVER['HTTP_HTTP2']) || isset($_SERVER['HTTP_HTTP2_SETTINGS']));
    }
    
    /**
     * Initialize OpenLiteSpeed optimizations
     */
    private function init_optimizations() {
        try {
            // Add ESI support
            add_action('init', array($this, 'enable_esi_blocks'));
            
            // Configure cache headers
            add_action('wp_head', array($this, 'set_cache_headers'), 1);
            add_action('admin_head', array($this, 'set_cache_headers'), 1);
            
            // Optimize for LiteSpeed Cache plugin
            add_action('litespeed_purge_all', array($this, 'handle_litespeed_purge'));
            add_action('litespeed_purge_post', array($this, 'handle_litespeed_post_purge'), 10, 2);
            
            // Add performance headers
            add_action('wp_headers', array($this, 'add_performance_headers'));
            
            // Optimize assets for OpenLiteSpeed
            add_action('wp_enqueue_scripts', array($this, 'optimize_assets_for_ols'));
            add_action('admin_enqueue_scripts', array($this, 'optimize_assets_for_ols'));
            
            // Configure ESI cache TTL
            add_filter('litespeed_cache_ttl', array($this, 'set_cache_ttl'));
            
            // Add QUIC optimizations
            add_action('wp_head', array($this, 'add_quic_optimizations'));
            
            // Optimize for HTTP/2 server push
            add_action('wp_head', array($this, 'add_http2_push_headers'));
            
            $this->logger->info('OpenLiteSpeed optimizations initialized successfully');
            
        } catch (Exception $e) {
            $this->logger->error('Failed to initialize OpenLiteSpeed optimizations', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
        }
    }
    
    /**
     * Enable ESI blocks for OpenLiteSpeed
     */
    public function enable_esi_blocks() {
        if (!$this->esi_enabled) {
            return;
        }
        
        try {
            // Set ESI headers
            header('X-LS-Cache: on');
            header('esi: on');
            
            // Enable ESI blocks for dashboard components
            if (is_admin()) {
                add_filter('admin_body_class', array($this, 'add_esi_body_class'));
            }
            
            // Add ESI cache configuration
            add_action('wp_ajax_aanp_esi_cache', array($this, 'handle_esi_cache_request'));
            add_action('wp_ajax_nopriv_aanp_esi_cache', array($this, 'handle_esi_cache_request'));
            
            $this->logger->debug('ESI blocks enabled for OpenLiteSpeed');
            
        } catch (Exception $e) {
            $this->logger->error('Failed to enable ESI blocks', array(
                'error' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Set cache headers for OpenLiteSpeed
     */
    public function set_cache_headers() {
        if (!$this->esi_enabled) {
            return;
        }
        
        try {
            // Set cache control headers
            if (is_admin()) {
                // Admin pages: shorter cache time
                header('Cache-Control: public, s-maxage=60, stale-while-revalidate=300');
            } else {
                // Frontend pages: longer cache time
                header('Cache-Control: public, s-maxage=300, stale-while-revalidate=86400');
            }
            
            // Set Vary header for mobile/desktop detection
            header('Vary: Accept-Encoding, User-Agent');
            
            // Add ETags for better caching
            if (!headers_sent()) {
                $etag = md5($_SERVER['REQUEST_URI'] . $_SERVER['HTTP_USER_AGENT'] ?? '');
                header('ETag: "' . $etag . '"');
            }
            
        } catch (Exception $e) {
            $this->logger->error('Failed to set cache headers', array(
                'error' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Add performance optimization headers
     */
    public function add_performance_headers($headers) {
        if (!$this->esi_enabled) {
            return $headers;
        }
        
        try {
            // Add performance headers
            $headers['X-LS-Cache'] = 'on';
            $headers['X-LS-Purge'] = 'on';
            
            // Add security headers
            $headers['X-Content-Type-Options'] = 'nosniff';
            $headers['X-Frame-Options'] = 'SAMEORIGIN';
            $headers['X-XSS-Protection'] = '1; mode=block';
            
            // Add performance hints
            $headers['X-OPLS-Optimized'] = 'true';
            $headers['X-ESI-Supported'] = $this->esi_enabled ? 'true' : 'false';
            
            $this->logger->debug('Performance headers added', array(
                'headers_count' => count($headers)
            ));
            
        } catch (Exception $e) {
            $this->logger->error('Failed to add performance headers', array(
                'error' => $e->getMessage()
            ));
        }
        
        return $headers;
    }
    
    /**
     * Optimize assets for OpenLiteSpeed
     */
    public function optimize_assets_for_ols() {
        if (!$this->esi_enabled) {
            return;
        }
        
        try {
            // Combine and minify CSS/JS for better performance
            add_filter('style_loader_src', array($this, 'optimize_css_for_ols'), 10, 2);
            add_filter('script_loader_src', array($this, 'optimize_js_for_ols'), 10, 2);
            
            // Add preload hints for critical resources
            add_action('wp_head', array($this, 'add_preload_hints'), 2);
            
            // Configure async/defer for non-critical scripts
            add_filter('script_loader_tag', array($this, 'optimize_script_loading'), 10, 3);
            
            $this->logger->debug('Assets optimized for OpenLiteSpeed');
            
        } catch (Exception $e) {
            $this->logger->error('Failed to optimize assets', array(
                'error' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Optimize CSS for OpenLiteSpeed
     */
    public function optimize_css_for_ols($src, $handle) {
        if (strpos($src, 'dashboard.css') !== false || strpos($src, 'admin.css') !== false) {
            // Add version parameter for cache busting
            $version = AANP_VERSION . '.' . filemtime(ABSPATH . '/wp-admin/css/dashboard.css');
            $src = add_query_arg('ver', $version, $src);
        }
        
        return $src;
    }
    
    /**
     * Optimize JavaScript for OpenLiteSpeed
     */
    public function optimize_js_for_ols($src, $handle) {
        // Add async/defer attributes for non-critical scripts
        if (strpos($src, 'chart.js') !== false || strpos($src, 'dashboard.js') !== false) {
            $src = add_query_arg('async', 'true', $src);
        }
        
        return $src;
    }
    
    /**
     * Add preload hints for critical resources
     */
    public function add_preload_hints() {
        if (!$this->esi_enabled) {
            return;
        }
        
        // Preload critical CSS
        echo '<link rel="preload" href="' . AANP_PLUGIN_URL . 'admin/dashboard/assets/css/dashboard.css" as="style">' . "\n";
        
        // Preload critical fonts
        echo '<link rel="preload" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">' . "\n";
        
        // Preload critical JavaScript
        echo '<link rel="preload" href="' . AANP_PLUGIN_URL . 'admin/dashboard/assets/js/dashboard.js" as="script">' . "\n";
    }
    
    /**
     * Optimize script loading with async/defer
     */
    public function optimize_script_loading($tag, $handle, $src) {
        if (!$this->esi_enabled) {
            return $tag;
        }
        
        // Add async for non-critical scripts
        if (strpos($src, 'chart.js') !== false || strpos($src, 'axios') !== false) {
            $tag = str_replace('<script ', '<script async ', $tag);
        }
        
        // Add defer for dashboard scripts
        if (strpos($src, 'dashboard.js') !== false) {
            $tag = str_replace('<script ', '<script defer ', $tag);
        }
        
        return $tag;
    }
    
    /**
     * Add QUIC optimizations
     */
    public function add_quic_optimizations() {
        if (!$this->ols_config['quic_support']) {
            return;
        }
        
        // Add HTTP/3 and QUIC hints
        echo '<meta http-equiv="x-dns-prefetch-control" content="on">' . "\n";
        echo '<link rel="dns-prefetch" href="//fonts.googleapis.com">' . "\n";
        echo '<link rel="dns-prefetch" href="//cdn.jsdelivr.net">' . "\n";
        
        // Add resource hints for faster loading
        echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
        echo '<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>' . "\n";
    }
    
    /**
     * Add HTTP/2 push headers
     */
    public function add_http2_push_headers() {
        if (!$this->ols_config['http2_support']) {
            return;
        }
        
        // Add resource hints for HTTP/2 server push
        if (is_admin() && strpos($_SERVER['REQUEST_URI'], 'ai-news-dashboard') !== false) {
            // Push critical dashboard resources
            $resources = array(
                'admin/dashboard/assets/css/dashboard.css',
                'admin/dashboard/assets/js/dashboard.js',
                'https://cdn.jsdelivr.net/npm/chart.js',
                'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap'
            );
            
            foreach ($resources as $resource) {
                if (strpos($resource, 'http') === 0) {
                    // External resource
                    header('Link: <' . $resource . '>; rel=preload; as=script');
                } else {
                    // Internal resource
                    header('Link: <' . AANP_PLUGIN_URL . $resource . '>; rel=preload; as=style');
                }
            }
        }
    }
    
    /**
     * Set cache TTL for different content types
     */
    public function set_cache_ttl($ttl) {
        if (!$this->esi_enabled) {
            return $ttl;
        }
        
        // Set different TTL for different content types
        if (is_admin() && strpos($_SERVER['REQUEST_URI'], 'dashboard') !== false) {
            return 60; // 1 minute for dashboard
        } elseif (is_singular() && get_post_meta(get_the_ID(), '_ai_news_generated', true)) {
            return 1800; // 30 minutes for AI-generated content
        } else {
            return 300; // 5 minutes default
        }
    }
    
    /**
     * Handle LiteSpeed cache purge
     */
    public function handle_litespeed_purge() {
        try {
            // Clear plugin-specific cache
            $this->cache_manager->clear_all_cache();
            
            $this->logger->info('LiteSpeed cache purged for AANP');
            
        } catch (Exception $e) {
            $this->logger->error('Failed to handle LiteSpeed cache purge', array(
                'error' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Handle post-specific cache purge
     */
    public function handle_litespeed_post_purge($post_id, $post) {
        try {
            // Clear cache for specific post
            $this->cache_manager->clear_post_cache($post_id);
            
            $this->logger->debug('Post cache purged', array(
                'post_id' => $post_id
            ));
            
        } catch (Exception $e) {
            $this->logger->error('Failed to handle post cache purge', array(
                'error' => $e->getMessage(),
                'post_id' => $post_id
            ));
        }
    }
    
    /**
     * Handle ESI cache requests
     */
    public function handle_esi_cache_request() {
        try {
            // Verify nonce for security
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'aanp_esi_cache')) {
                wp_die('Security check failed');
            }
            
            $cache_key = sanitize_text_field($_POST['cache_key'] ?? '');
            $cache_data = $this->cache_manager->get($cache_key);
            
            if ($cache_data !== false) {
                wp_send_json_success($cache_data);
            } else {
                wp_send_json_error('Cache miss');
            }
            
        } catch (Exception $e) {
            $this->logger->error('ESI cache request failed', array(
                'error' => $e->getMessage()
            ));
            
            wp_send_json_error('Internal server error');
        }
    }
    
    /**
     * Add ESI body class
     */
    public function add_esi_body_class($classes) {
        if ($this->esi_enabled) {
            $classes .= ' aanp-esi-enabled';
        }
        return $classes;
    }
    
    /**
     * Get OpenLiteSpeed configuration
     */
    public function get_ols_configuration() {
        return array_merge($this->ols_config, array(
            'esi_enabled' => $this->esi_enabled,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'cache_support' => $this->ols_config['lscache_support'] ?? false,
            'performance_score' => $this->calculate_performance_score()
        ));
    }
    
    /**
     * Calculate performance optimization score
     */
    private function calculate_performance_score() {
        $score = 0;
        
        if ($this->esi_enabled) $score += 25;
        if ($this->ols_config['lscache_support']) $score += 20;
        if ($this->ols_config['brotli_support']) $score += 15;
        if ($this->ols_config['http2_support']) $score += 15;
        if ($this->ols_config['quic_support']) $score += 25;
        
        return $score;
    }
    
    /**
     * Generate performance report
     */
    public function generate_performance_report() {
        try {
            $config = $this->get_ols_configuration();
            $metrics = $this->performance_optimizer->get_performance_metrics();
            $cache_stats = $this->cache_manager->get_cache_statistics();
            
            $report = array(
                'server_environment' => array(
                    'server_software' => $config['server_software'],
                    'php_version' => PHP_VERSION,
                    'wordpress_version' => get_bloginfo('version')
                ),
                'openlitespeed_features' => array(
                    'esi_support' => $config['esi_support'],
                    'lscache_support' => $config['lscache_support'],
                    'quic_support' => $config['quic_support'],
                    'brotli_support' => $config['brotli_support'],
                    'http2_support' => $config['http2_support']
                ),
                'performance_metrics' => $metrics,
                'cache_statistics' => $cache_stats,
                'optimization_score' => $config['performance_score'],
                'recommendations' => $this->get_optimization_recommendations($config)
            );
            
            $this->logger->info('Performance report generated', array(
                'score' => $config['performance_score']
            ));
            
            return $report;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to generate performance report', array(
                'error' => $e->getMessage()
            ));
            
            return array('error' => 'Failed to generate report');
        }
    }
    
    /**
     * Get optimization recommendations
     */
    private function get_optimization_recommendations($config) {
        $recommendations = array();
        
        if (!$config['esi_support']) {
            $recommendations[] = 'Enable ESI support in OpenLiteSpeed configuration for better performance';
        }
        
        if (!$config['lscache_support']) {
            $recommendations[] = 'Install LiteSpeed Cache plugin for enhanced caching capabilities';
        }
        
        if (!$config['brotli_support']) {
            $recommendations[] = 'Enable Brotli compression for better content delivery';
        }
        
        if (!$config['http2_support']) {
            $recommendations[] = 'Enable HTTP/2 for improved loading performance';
        }
        
        if (!$config['quic_support']) {
            $recommendations[] = 'Enable QUIC/HTTP3 for faster content delivery';
        }
        
        return $recommendations;
    }
    
    /**
     * Clean up resources
     */
    public function cleanup() {
        try {
            // Clear any pending operations
            $this->logger->info('OpenLiteSpeed Optimizer cleanup completed');
            
        } catch (Exception $e) {
            $this->logger->error('Cleanup failed', array(
                'error' => $e->getMessage()
            ));
        }
    }
}