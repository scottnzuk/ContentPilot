<?php
/**
 * Installation Wizard
 * 
 * Automated setup process that handles plugin installation,
 * system checks, database setup, and feature configuration.
 * 
 * @package ContentPilot
 * @since 1.3.0
 * @author scottnzuk
 */

if (!defined('ABSPATH')) {
    exit;
}

class CP_InstallationWizard {
    
    /**
     * Hosting compatibility manager
     *
     * @var CP_HostingCompatibility
     */
    private $hosting_compatibility;

    /**
     * Dependency manager
     *
     * @var CP_DependencyManager
     */
    private $dependency_manager;
    
    /**
     * Installation steps
     * 
     * @var array
     */
    private $steps = array();
    
    /**
     * Current step
     * 
     * @var int
     */
    private $current_step = 0;
    
    /**
     * Installation results
     * 
     * @var array
     */
    private $results = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->hosting_compatibility = new CP_HostingCompatibility();
        $this->dependency_manager = new CP_DependencyManager();
        $this->initialize_steps();
    }
    
    /**
     * Initialize installation steps
     */
    private function initialize_steps() {
        $this->steps = array(
            0 => 'system_check',
            1 => 'database_setup',
            2 => 'feature_configuration',
            3 => 'performance_optimization',
            4 => 'testing',
            5 => 'completion'
        );
    }
    
    /**
     * Run installation wizard
     * 
     * @return array Installation results
     */
    public function run_installation_wizard() {
        $results = array(
            'success' => true,
            'steps_completed' => array(),
            'warnings' => array(),
            'errors' => array(),
            'configuration' => array()
        );
        
        try {
            foreach ($this->steps as $step_id => $step_name) {
                $this->current_step = $step_id;
                $step_result = $this->execute_step($step_name);
                
                if ($step_result['success']) {
                    $results['steps_completed'][] = $step_name;
                } else {
                    $results['errors'][] = $step_result['error'];
                    if ($step_result['critical']) {
                        $results['success'] = false;
                        break;
                    }
                }
                
                // Collect warnings
                if (!empty($step_result['warnings'])) {
                    $results['warnings'] = array_merge($results['warnings'], $step_result['warnings']);
                }
                
                // Collect configuration
                if (!empty($step_result['configuration'])) {
                    $results['configuration'] = array_merge($results['configuration'], $step_result['configuration']);
                }
            }
            
            // Final validation
            if ($results['success']) {
                $this->validate_installation($results);
            }
            
        } catch (Exception $e) {
            $results['success'] = false;
            $results['errors'][] = 'Installation wizard failed: ' . $e->getMessage();
        }
        
        $this->results = $results;
        return $results;
    }
    
    /**
     * Execute installation step
     * 
     * @param string $step_name Step name
     * @return array Step result
     */
    private function execute_step($step_name) {
        switch ($step_name) {
            case 'system_check':
                return $this->perform_system_check();
            case 'database_setup':
                return $this->setup_database_tables();
            case 'feature_configuration':
                return $this->configure_features_based_on_environment();
            case 'performance_optimization':
                return $this->optimize_for_detected_environment();
            case 'testing':
                return $this->run_basic_functionality_test();
            case 'completion':
                return $this->confirm_installation_success();
            default:
                return array('success' => true, 'warnings' => array());
        }
    }
    
    /**
     * Perform system compatibility check
     * 
     * @return array System check results
     */
    private function perform_system_check() {
        $result = array(
            'success' => true,
            'warnings' => array(),
            'critical' => false,
            'configuration' => array()
        );
        
        // Get hosting compatibility validation
        $validation = $this->hosting_compatibility->validate_environment();
        
        if (!$validation['is_compatible']) {
            $result['success'] = false;
            $result['critical'] = true;
            $result['error'] = 'System requirements not met';
            $result['errors'] = $validation['errors'];
        }
        
        // Collect warnings
        $result['warnings'] = array_merge($result['warnings'], $validation['warnings']);
        
        // Get compatibility report
        $compatibility_report = $this->hosting_compatibility->get_compatibility_report();
        $result['configuration']['hosting_profile'] = $compatibility_report['hosting_profile'];
        $result['configuration']['compatibility_score'] = $compatibility_report['compatibility_score'];
        
        // Get dependency health status
        $health_status = $this->dependency_manager->get_health_status();
        $result['configuration']['dependency_health'] = $health_status;
        
        // Add recommendations
        if (!empty($validation['recommendations'])) {
            $result['warnings'] = array_merge($result['warnings'], $validation['recommendations']);
        }
        
        return $result;
    }
    
    /**
     * Setup database tables
     * 
     * @return array Database setup results
     */
    private function setup_database_tables() {
        global $wpdb;
        
        $result = array(
            'success' => true,
            'warnings' => array(),
            'critical' => false,
            'configuration' => array()
        );
        
        try {
            // Create main generated posts table
            $this->create_generated_posts_table();
            $result['configuration']['tables_created'][] = 'cp_generated_posts';
            
            // Create verification database tables
            $verification_db_result = $this->create_verification_database_tables();
            if ($verification_db_result['success']) {
                $result['configuration']['tables_created'] = array_merge(
                    $result['configuration']['tables_created'],
                    $verification_db_result['tables_created']
                );
            } else {
                $result['warnings'][] = 'Verification database tables creation failed: ' . $verification_db_result['error'];
            }
            
            // Set up indexes for performance
            $this->setup_database_indexes();
            
        } catch (Exception $e) {
            $result['success'] = false;
            $result['critical'] = true;
            $result['error'] = 'Database setup failed: ' . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Create main generated posts table
     * 
     * @throws Exception If table creation fails
     */
    private function create_generated_posts_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cp_generated_posts';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            source_url varchar(255) NOT NULL,
            generated_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_post_id (post_id),
            INDEX idx_generated_at (generated_at)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $db_result = dbDelta($sql);
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
            throw new Exception('Failed to create main database table');
        }
    }
    
    /**
     * Create verification database tables
     * 
     * @return array Creation results
     */
    private function create_verification_database_tables() {
        try {
            // Load the verification database class
            if (class_exists('CP_VerificationDatabase')) {
                $verification_db = new CP_VerificationDatabase();
                $verification_db->create_tables();
                return array('success' => true, 'tables_created' => array('cp_verification_cache', 'cp_source_links'));
            } elseif (class_exists('CP_VerificationDatabase')) {
                $verification_db = new CP_VerificationDatabase();
                $verification_db->create_tables();
                return array('success' => true, 'tables_created' => array('cp_verification_cache', 'cp_source_links'));
            } else {
                return array('success' => false, 'error' => 'VerificationDatabase class not found');
            }
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }
    
    /**
     * Setup database indexes for performance
     */
    private function setup_database_indexes() {
        global $wpdb;
        
        // Add additional indexes for performance
        $indexes = array(
            "ALTER TABLE {$wpdb->prefix}cp_generated_posts ADD INDEX idx_post_source (post_id, source_url)"
        );
        
        foreach ($indexes as $index_sql) {
            $wpdb->query($index_sql);
        }
    }
    
    /**
     * Configure features based on hosting environment
     * 
     * @return array Configuration results
     */
    private function configure_features_based_on_environment() {
        $result = array(
            'success' => true,
            'warnings' => array(),
            'critical' => false,
            'configuration' => array()
        );
        
        try {
            // Get recommended settings from hosting compatibility
            $recommended_settings = $this->hosting_compatibility->get_recommended_settings();
            
            // Configure cache settings
            $this->configure_cache_settings($recommended_settings);
            $result['configuration']['cache_configured'] = true;
            
            // Configure performance settings
            $this->configure_performance_settings($recommended_settings);
            $result['configuration']['performance_configured'] = true;
            
            // Configure feature toggles
            $this->configure_feature_toggles($recommended_settings);
            $result['configuration']['features_configured'] = true;
            
            // Set up default options
            $this->setup_default_options();
            $result['configuration']['defaults_set'] = true;
            
            // Apply hosting-specific optimizations
            $this->apply_hosting_optimizations();
            $result['configuration']['hosting_optimizations_applied'] = true;
            
        } catch (Exception $e) {
            $result['success'] = false;
            $result['critical'] = false;
            $result['error'] = 'Feature configuration failed: ' . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Configure cache settings
     * 
     * @param array $settings Recommended settings
     */
    private function configure_cache_settings($settings) {
        $cache_duration = $settings['cache_duration'] ?? 1800;
        
        // Update cache duration setting
        $current_options = get_option('cp_settings', array());
        $current_options['cache_duration'] = $cache_duration;
        update_option('cp_settings', $current_options);
        
        // Apply cache optimizations
        add_action('init', function() use ($cache_duration) {
            add_filter('cp_cache_duration', function() use ($cache_duration) {
                return $cache_duration;
            }, 10, 1);
        });
    }
    
    /**
     * Configure performance settings
     * 
     * @param array $settings Recommended settings
     */
    private function configure_performance_settings($settings) {
        $batch_size = $settings['batch_size'] ?? 5;
        $memory_limit = $settings['memory_limit'] ?? '128M';
        
        // Update batch size setting
        $current_options = get_option('cp_settings', array());
        $current_options['batch_size'] = $batch_size;
        update_option('cp_settings', $current_options);
        
        // Apply memory limit
        if (ini_get('memory_limit') && $this->return_bytes(ini_get('memory_limit')) > $this->return_bytes($memory_limit)) {
            ini_set('memory_limit', $memory_limit);
        }
    }
    
    /**
     * Configure feature toggles
     * 
     * @param array $settings Recommended settings
     */
    private function configure_feature_toggles($settings) {
        $analytics_enabled = $settings['enable_analytics'] ?? false;
        $monitoring_enabled = $settings['enable_monitoring'] ?? false;
        
        $current_options = get_option('cp_settings', array());
        $current_options['enable_analytics'] = $analytics_enabled;
        $current_options['enable_monitoring'] = $monitoring_enabled;
        update_option('cp_settings', $current_options);
        
        // Apply feature filters
        add_filter('cp_enable_analytics', function() use ($analytics_enabled) {
            return $analytics_enabled;
        }, 10, 1);
        
        add_filter('cp_enable_monitoring', function() use ($monitoring_enabled) {
            return $monitoring_enabled;
        }, 10, 1);
    }
    
    /**
     * Setup default options
     */
    private function setup_default_options() {
        $default_options = array(
            // LLM Configuration
            'llm_provider' => 'openai',
            'api_key' => '',

            // Content Generation Settings
            'categories' => array(),
            'word_count' => 'medium',
            'tone' => 'neutral',

            // RSS Feed Sources
            'rss_feeds' => array(
                'https://feeds.bbci.co.uk/news/rss.xml',
                'https://rss.cnn.com/rss/edition.rss',
                'https://feeds.reuters.com/reuters/topNews'
            ),

            // Performance Settings
            'cache_duration' => 1800,
            'batch_size' => 5,
            'debug_mode' => false,
            'rate_limit_enabled' => true,

            // Feature Toggles
            'enable_analytics' => false,
            'enable_monitoring' => false,

            // Content Enhancement
            'content_enhancement_enabled' => true,
            'humanization_level' => 'basic'
        );

        // Only add default options if they don't already exist
        add_option('cp_settings', $default_options);
    }
    
    /**
     * Apply hosting-specific optimizations
     */
    private function apply_hosting_optimizations() {
        // This is handled by the hosting compatibility class
        // but we can add additional optimization here if needed
        
        add_action('init', function() {
            // Record that optimization has been applied
            update_option('cp_optimization_applied', current_time('Y-m-d H:i:s', true));
        });
    }
    
    /**
     * Optimize for detected environment
     * 
     * @return array Optimization results
     */
    private function optimize_for_detected_environment() {
        $result = array(
            'success' => true,
            'warnings' => array(),
            'critical' => false,
            'configuration' => array()
        );
        
        try {
            $compatibility_report = $this->hosting_compatibility->get_compatibility_report();
            $profile = $compatibility_report['hosting_profile'];
            
            // Apply profile-specific optimizations
            switch ($profile) {
                case 'shared':
                    $this->optimize_for_shared_hosting();
                    $result['configuration']['profile'] = 'shared';
                    break;
                    
                case 'vps':
                    $this->optimize_for_vps();
                    $result['configuration']['profile'] = 'vps';
                    break;
                    
                case 'dedicated':
                    $this->optimize_for_dedicated();
                    $result['configuration']['profile'] = 'dedicated';
                    break;
                    
                case 'managed_wordpress':
                    $this->optimize_for_managed_wordpress();
                    $result['configuration']['profile'] = 'managed_wordpress';
                    break;
            }
            
            // Enable caching optimizations
            $this->enable_caching_optimizations();
            $result['configuration']['caching_optimized'] = true;
            
            // Apply performance monitoring
            if ($compatibility_report['available_features']['performance_monitoring'] !== 'basic') {
                $this->enable_performance_monitoring();
                $result['configuration']['monitoring_enabled'] = true;
            }
            
        } catch (Exception $e) {
            $result['success'] = false;
            $result['critical'] = false;
            $result['error'] = 'Performance optimization failed: ' . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Optimize for shared hosting
     */
    private function optimize_for_shared_hosting() {
        // Reduce memory usage
        add_filter('cp_memory_limit', function() {
            return '128M';
        }, 10, 1);
        
        // Use minimal caching
        add_filter('cp_cache_strategy', function() {
            return 'minimal';
        }, 10, 1);
        
        // Disable heavy features
        add_filter('cp_disable_heavy_features', function() {
            return true;
        }, 10, 1);
        
        // Add operation delays to prevent overwhelming the server
        add_filter('cp_operation_delay', function() {
            return 3; // 3 second delay between operations
        }, 10, 1);
    }
    
    /**
     * Optimize for VPS
     */
    private function optimize_for_vps() {
        // Moderate memory usage
        add_filter('cp_memory_limit', function() {
            return '256M';
        }, 10, 1);
        
        // Enable Redis/Memcached if available
        if ($this->hosting_compatibility->is_feature_available('supports_redis') || 
            $this->hosting_compatibility->is_feature_available('supports_memcached')) {
            add_filter('cp_cache_strategy', function() {
                return 'advanced';
            }, 10, 1);
        }
        
        // Enable concurrent processing
        add_filter('cp_concurrent_processing', function() {
            return true;
        }, 10, 1);
    }
    
    /**
     * Optimize for dedicated server
     */
    private function optimize_for_dedicated() {
        // High memory usage
        add_filter('cp_memory_limit', function() {
            return '512M';
        }, 10, 1);
        
        // Full feature set
        add_filter('cp_cache_strategy', function() {
            return 'maximum';
        }, 10, 1);
        
        add_filter('cp_concurrent_processing', function() {
            return true;
        }, 10, 1);
        
        add_filter('cp_full_feature_set', function() {
            return true;
        }, 10, 1);
    }
    
    /**
     * Optimize for managed WordPress
     */
    private function optimize_for_managed_wordpress() {
        // Use WordPress-specific optimizations
        add_filter('cp_use_wordpress_optimizations', function() {
            return true;
        }, 10, 1);
        
        // Enable CDN if available
        add_filter('cp_enable_cdn', function() {
            return true;
        }, 10, 1);
        
        // Use provider-specific caching
        add_filter('cp_cache_strategy', function() {
            return 'wordpress_optimized';
        }, 10, 1);
    }
    
    /**
     * Enable caching optimizations
     */
    private function enable_caching_optimizations() {
        add_action('init', function() {
            // Set up cache warming
            add_action('wp', function() {
                $this->warm_cache();
            });
        });
    }
    
    /**
     * Enable performance monitoring
     */
    private function enable_performance_monitoring() {
        add_action('init', function() {
            // Start performance monitoring
            if (!wp_next_scheduled('cp_performance_log')) {
                wp_schedule_event(time(), 'hourly', 'cp_performance_log');
            }
            
            add_action('cp_performance_log', array($this, 'log_performance_metrics'));
        });
    }
    
    /**
     * Warm cache for better performance
     */
    private function warm_cache() {
        // Pre-load frequently accessed data
        if (function_exists('wp_cache_set')) {
            wp_cache_set('cp_warm_cache', 'warmed', 'cp', 3600);
        }
    }
    
    /**
     * Log performance metrics
     */
    public function log_performance_metrics() {
        // Simple performance logging
        $metrics = array(
            'timestamp' => current_time('Y-m-d H:i:s', true),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'cache_hits' => $this->get_cache_hit_ratio()
        );
        
        update_option('cp_performance_log', $metrics);
    }
    
    /**
     * Get cache hit ratio
     * 
     * @return float Cache hit ratio
     */
    private function get_cache_hit_ratio() {
        // Simple cache hit ratio calculation
        return 0.85; // Placeholder - in real implementation would calculate actual ratio
    }
    
    /**
     * Run basic functionality test
     * 
     * @return array Test results
     */
    private function run_basic_functionality_test() {
        $result = array(
            'success' => true,
            'warnings' => array(),
            'critical' => false,
            'configuration' => array()
        );
        
        $tests = array(
            'database_connection' => 'Test database setup',
            'rss_fetching' => 'Test RSS feed processing',
            'content_creation' => 'Test post generation',
            'admin_access' => 'Test admin interface',
            'basic_caching' => 'Test fallback caching'
        );
        
        $passed_tests = 0;
        $total_tests = count($tests);
        
        foreach ($tests as $test => $description) {
            $test_result = $this->run_test($test);
            if ($test_result['success']) {
                $passed_tests++;
            } else {
                $result['warnings'][] = $description . ': ' . $test_result['error'];
            }
        }
        
        $result['configuration']['tests_passed'] = $passed_tests;
        $result['configuration']['tests_total'] = $total_tests;
        $result['configuration']['success_rate'] = round(($passed_tests / $total_tests) * 100, 2);
        
        // Consider test failed if less than 80% pass rate
        if ($passed_tests < ($total_tests * 0.8)) {
            $result['success'] = false;
            $result['critical'] = false;
            $result['error'] = 'Insufficient test pass rate (' . $result['configuration']['success_rate'] . '%)';
        }
        
        return $result;
    }
    
    /**
     * Run individual test
     * 
     * @param string $test Test name
     * @return array Test result
     */
    private function run_test($test) {
        switch ($test) {
            case 'database_connection':
                return $this->test_database_connection();
            case 'rss_fetching':
                return $this->test_rss_fetching();
            case 'content_creation':
                return $this->test_content_creation();
            case 'admin_access':
                return $this->test_admin_access();
            case 'basic_caching':
                return $this->test_basic_caching();
            default:
                return array('success' => true);
        }
    }
    
    /**
     * Test database connection
     * 
     * @return array Test result
     */
    private function test_database_connection() {
        global $wpdb;
        
        try {
            // Test main table exists
            $table_name = $wpdb->prefix . 'cp_generated_posts';
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
            
            if (!$table_exists) {
                return array('success' => false, 'error' => 'Main table does not exist');
            }
            
            // Test write capability
            $test_result = $wpdb->insert($table_name, array(
                'post_id' => 0,
                'source_url' => 'test_installation'
            ));
            
            if ($test_result === false) {
                return array('success' => false, 'error' => 'Cannot write to database');
            }
            
            // Clean up test data
            $wpdb->delete($table_name, array('source_url' => 'test_installation'));
            
            return array('success' => true);
            
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }
    
    /**
     * Test RSS fetching
     * 
     * @return array Test result
     */
    private function test_rss_fetching() {
        try {
            $test_url = 'https://feeds.bbci.co.uk/news/rss.xml';
            $response = wp_remote_get($test_url, array('timeout' => 10));
            
            if (is_wp_error($response)) {
                return array('success' => false, 'error' => 'RSS fetch failed: ' . $response->get_error_message());
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                return array('success' => false, 'error' => 'RSS fetch returned HTTP ' . $response_code);
            }
            
            return array('success' => true);
            
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }
    
    /**
     * Test content creation capability
     * 
     * @return array Test result
     */
    private function test_content_creation() {
        try {
            // Test basic post creation capability
            $test_post = array(
                'post_title' => 'Installation Test Post',
                'post_content' => 'This is a test post created during installation.',
                'post_status' => 'draft'
            );
            
            $post_id = wp_insert_post($test_post);
            
            if (is_wp_error($post_id)) {
                return array('success' => false, 'error' => 'Post creation failed: ' . $post_id->get_error_message());
            }
            
            // Clean up test post
            wp_delete_post($post_id, true);
            
            return array('success' => true);
            
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }
    
    /**
     * Test admin access
     * 
     * @return array Test result
     */
    private function test_admin_access() {
        try {
            // Test if admin functions are accessible
            if (!function_exists('add_option')) {
                return array('success' => false, 'error' => 'WordPress admin functions not available');
            }
            
            // Test plugin admin page capability
            if (!current_user_can('manage_options')) {
                return array('success' => false, 'error' => 'Insufficient permissions for admin access');
            }
            
            return array('success' => true);
            
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }
    
    /**
     * Test basic caching
     * 
     * @return array Test result
     */
    private function test_basic_caching() {
        try {
            // Test WordPress transients
            $test_key = 'cp_installation_test';
            $test_value = 'installation_test_value';
            
            $set_result = set_transient($test_key, $test_value, 300); // 5 minutes
            if (!$set_result) {
                return array('success' => false, 'error' => 'Cannot set transient cache');
            }
            
            $get_result = get_transient($test_key);
            if ($get_result !== $test_value) {
                return array('success' => false, 'error' => 'Cache retrieval failed');
            }
            
            // Clean up test cache
            delete_transient($test_key);
            
            return array('success' => true);
            
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }
    
    /**
     * Confirm installation success
     * 
     * @return array Completion results
     */
    private function confirm_installation_success() {
        $result = array(
            'success' => true,
            'warnings' => array(),
            'critical' => false,
            'configuration' => array()
        );
        
        try {
            // Record successful installation
            update_option('cp_installation_completed', current_time('Y-m-d H:i:s', true));
            update_option('cp_installation_version', CP_VERSION);
            
            // Set activation redirect flag for first-time users
            add_option('cp_activation_redirect', true);
            
            // Clear any existing caches
            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
            }
            
            $result['configuration']['installation_time'] = current_time('Y-m-d H:i:s', true);
            $result['configuration']['version'] = CP_VERSION;
            $result['configuration']['installation_successful'] = true;
            
        } catch (Exception $e) {
            $result['success'] = false;
            $result['critical'] = false;
            $result['error'] = 'Installation confirmation failed: ' . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Validate final installation
     * 
     * @param array $results Installation results
     */
    private function validate_installation(&$results) {
        // Check if all critical steps completed
        $required_steps = array('system_check', 'database_setup', 'feature_configuration');
        foreach ($required_steps as $step) {
            if (!in_array($step, $results['steps_completed'])) {
                $results['success'] = false;
                $results['errors'][] = 'Required installation step failed: ' . $step;
                break;
            }
        }
        
        // Check compatibility score
        $compatibility_score = $this->hosting_compatibility->get_compatibility_report()['compatibility_score'];
        if ($compatibility_score < 60) {
            $results['warnings'][] = 'Low compatibility score (' . $compatibility_score . '%). Plugin may not function optimally.';
        }
        
        // Check dependency health
        $health_status = $this->dependency_manager->get_health_status();
        if ($health_status['critical_issues'] > 0) {
            $results['warnings'][] = $health_status['critical_issues'] . ' critical dependencies missing. Some features may not work.';
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
     * Get installation results
     * 
     * @return array Installation results
     */
    public function get_results() {
        return $this->results;
    }
    
    /**
     * Get installation status
     * 
     * @return array Installation status
     */
    public function get_status() {
        if (empty($this->results)) {
            return array('status' => 'not_started', 'message' => 'Installation wizard not yet run');
        }
        
        if ($this->results['success']) {
            return array(
                'status' => 'completed',
                'message' => 'Installation completed successfully',
                'steps_completed' => count($this->results['steps_completed']),
                'total_steps' => count($this->steps)
            );
        } else {
            return array(
                'status' => 'failed',
                'message' => 'Installation failed',
                'errors' => $this->results['errors']
            );
        }
    }
}