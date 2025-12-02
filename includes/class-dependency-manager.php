<?php
/**
 * Smart Dependency Manager
 * 
 * Automatically detects missing dependencies and provides graceful fallbacks
 * to ensure the plugin works across all hosting environments.
 * 
 * @package ContentPilot
 * @since 1.3.0
 * @author scottnzuk
 */

if (!defined('ABSPATH')) {
    exit;
}

class CP_DependencyManager {
    
    /**
     * Hosting compatibility manager
     *
     * @var CP_HostingCompatibility
     */
    private $hosting_compatibility;
    
    /**
     * Detected missing dependencies
     * 
     * @var array
     */
    private $missing_dependencies = array();
    
    /**
     * Applied fallbacks
     * 
     * @var array
     */
    private $applied_fallbacks = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->hosting_compatibility = new CP_HostingCompatibility();
        $this->check_and_load_dependencies();
    }
    
    /**
     * Check and load dependencies with graceful fallbacks
     */
    public function check_and_load_dependencies() {
        // Check caching dependencies
        $this->check_caching_dependencies();
        
        // Check PHP dependencies
        $this->check_php_dependencies();
        
        // Check WordPress dependencies
        $this->check_wordpress_dependencies();
        
        // Check Python dependencies
        $this->check_python_dependencies();
        
        // Check performance dependencies
        $this->check_performance_dependencies();
        
        // Apply fallbacks for missing dependencies
        $this->apply_fallbacks();
        
        // Configure alternative approaches
        $this->configure_alternatives();
    }
    
    /**
     * Check caching system dependencies
     */
    private function check_caching_dependencies() {
        // Check Redis
        if (!$this->hosting_compatibility->is_feature_available('supports_redis')) {
            $this->missing_dependencies['redis'] = array(
                'name' => 'Redis',
                'feature' => 'advanced_caching',
                'fallback' => 'wp_transients',
                'description' => 'WordPress transients for basic caching'
            );
        }
        
        // Check Memcached
        if (!$this->hosting_compatibility->is_feature_available('supports_memcached')) {
            $this->missing_dependencies['memcached'] = array(
                'name' => 'Memcached',
                'feature' => 'advanced_caching',
                'fallback' => 'wp_transients',
                'description' => 'WordPress transients for basic caching'
            );
        }
        
        // Check object cache
        if (!$this->hosting_compatibility->is_feature_available('has_object_cache')) {
            $this->missing_dependencies['object_cache'] = array(
                'name' => 'Object Cache',
                'feature' => 'intermediate_caching',
                'fallback' => 'database_cache',
                'description' => 'Database-based caching as fallback'
            );
        }
    }
    
    /**
     * Check PHP extension dependencies
     */
    private function check_php_dependencies() {
        $required_extensions = array(
            'curl' => array(
                'feature' => 'http_requests',
                'fallback' => 'wp_remote_get',
                'description' => 'WordPress HTTP API as alternative'
            ),
            'json' => array(
                'feature' => 'data_processing',
                'fallback' => 'native_json',
                'description' => 'PHP native JSON functions'
            ),
            'mbstring' => array(
                'feature' => 'text_processing',
                'fallback' => 'basic_text_functions',
                'description' => 'Basic string functions with limitations'
            ),
            'gd' => array(
                'feature' => 'image_processing',
                'fallback' => 'external_image_service',
                'description' => 'External image processing service'
            )
        );
        
        foreach ($required_extensions as $extension => $details) {
            if (!extension_loaded($extension)) {
                $this->missing_dependencies[$extension] = array(
                    'name' => $extension . ' extension',
                    'feature' => $details['feature'],
                    'fallback' => $details['fallback'],
                    'description' => $details['description']
                );
            }
        }
    }
    
    /**
     * Check WordPress-specific dependencies
     */
    private function check_wordpress_dependencies() {
        // Check WordPress version
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            $this->missing_dependencies['wp_version'] = array(
                'name' => 'WordPress 5.0+',
                'feature' => 'core_functionality',
                'fallback' => 'legacy_mode',
                'description' => 'Limited functionality mode for older WordPress'
            );
        }
        
        // Check required WordPress functions
        $required_functions = array(
            'wp_remote_get' => array(
                'feature' => 'http_requests',
                'fallback' => 'basic_http',
                'description' => 'Basic HTTP functions'
            ),
            'wp_verify_nonce' => array(
                'feature' => 'security',
                'fallback' => 'basic_validation',
                'description' => 'Basic form validation'
            )
        );
        
        foreach ($required_functions as $function => $details) {
            if (!function_exists($function)) {
                $this->missing_dependencies[$function] = array(
                    'name' => $function . '() function',
                    'feature' => $details['feature'],
                    'fallback' => $details['fallback'],
                    'description' => $details['description']
                );
            }
        }
    }
    
    /**
     * Check Python integration dependencies
     */
    private function check_python_dependencies() {
        if (!$this->hosting_compatibility->is_feature_available('supports_python')) {
            $this->missing_dependencies['python'] = array(
                'name' => 'Python Integration',
                'feature' => 'content_humanization',
                'fallback' => 'php_enhancement',
                'description' => 'PHP-based content enhancement as alternative'
            );
        }
        
        // Check Python humano package
        $humanizer_path = CP_PLUGIN_DIR . 'cp-humanizer/humanizer.py';
        if (!file_exists($humanizer_path)) {
            $this->missing_dependencies['humano_package'] = array(
                'name' => 'Humano Python Package',
                'feature' => 'advanced_humanization',
                'fallback' => 'basic_enhancement',
                'description' => 'Basic text enhancement algorithms'
            );
        }
    }
    
    /**
     * Check performance optimization dependencies
     */
    private function check_performance_dependencies() {
        // Check for advanced performance monitoring
        $analytics_level = $this->hosting_compatibility->get_recommended_settings()['enable_analytics'] ?? false;
        if (!$analytics_level) {
            $this->missing_dependencies['advanced_analytics'] = array(
                'name' => 'Advanced Analytics',
                'feature' => 'performance_monitoring',
                'fallback' => 'basic_statistics',
                'description' => 'Basic usage statistics'
            );
        }
        
        // Check for concurrent processing
        if (!$this->hosting_compatibility->is_feature_available('concurrent_processing')) {
            $this->missing_dependencies['concurrent_processing'] = array(
                'name' => 'Concurrent Processing',
                'feature' => 'performance_optimization',
                'fallback' => 'sequential_processing',
                'description' => 'Sequential processing with delays'
            );
        }
    }
    
    /**
     * Apply fallbacks for missing dependencies
     */
    private function apply_fallbacks() {
        foreach ($this->missing_dependencies as $dependency => $details) {
            $this->provide_alternative($dependency, $details);
        }
    }
    
    /**
     * Provide alternative implementation for missing dependency
     * 
     * @param string $dependency Dependency name
     * @param array $details Dependency details
     */
    private function provide_alternative($dependency, $details) {
        $fallback = $details['fallback'];
        
        switch ($dependency) {
            case 'redis':
            case 'memcached':
                $this->enable_wordpress_caching();
                $this->applied_fallbacks[$dependency] = 'WordPress transients';
                break;
                
            case 'object_cache':
                $this->enable_database_caching();
                $this->applied_fallbacks[$dependency] = 'Database caching';
                break;
                
            case 'curl':
                $this->enable_wordpress_http_api();
                $this->applied_fallbacks[$dependency] = 'WordPress HTTP API';
                break;
                
            case 'python':
                $this->enable_php_enhancement();
                $this->applied_fallbacks[$dependency] = 'PHP content enhancement';
                break;
                
            case 'humano_package':
                $this->enable_basic_enhancement();
                $this->applied_fallbacks[$dependency] = 'Basic enhancement algorithms';
                break;
                
            case 'advanced_analytics':
                $this->enable_basic_statistics();
                $this->applied_fallbacks[$dependency] = 'Basic statistics tracking';
                break;
                
            case 'concurrent_processing':
                $this->enable_sequential_processing();
                $this->applied_fallbacks[$dependency] = 'Sequential processing';
                break;
                
            case 'wp_version':
                $this->enable_legacy_mode();
                $this->applied_fallbacks[$dependency] = 'Legacy compatibility mode';
                break;
                
            case 'gd':
                $this->enable_external_image_service();
                $this->applied_fallbacks[$dependency] = 'External image service';
                break;
        }
    }
    
    /**
     * Configure alternative approaches based on hosting environment
     */
    private function configure_alternatives() {
        $compatibility_report = $this->hosting_compatibility->get_compatibility_report();
        $profile = $compatibility_report['hosting_profile'];
        
        switch ($profile) {
            case 'shared':
                $this->configure_shared_hosting_alternatives();
                break;
            case 'vps':
                $this->configure_vps_alternatives();
                break;
            case 'managed_wordpress':
                $this->configure_managed_wp_alternatives();
                break;
        }
    }
    
    /**
     * Configure alternatives for shared hosting
     */
    private function configure_shared_hosting_alternatives() {
        // Reduce memory usage
        add_filter('cp_memory_limit', function($limit) {
            return '128M';
        }, 10, 1);
        
        // Use lightweight operations
        add_filter('cp_operation_mode', function() {
            return 'lightweight';
        }, 10, 1);
        
        // Disable non-essential features
        add_filter('cp_enable_heavy_analytics', function() {
            return false;
        }, 10, 1);
        
        // Use minimal caching
        add_filter('cp_cache_strategy', function() {
            return 'minimal';
        }, 10, 1);
    }
    
    /**
     * Configure alternatives for VPS
     */
    private function configure_vps_alternatives() {
        // Moderate memory usage
        add_filter('cp_memory_limit', function($limit) {
            return '256M';
        }, 10, 1);
        
        // Standard operations
        add_filter('cp_operation_mode', function() {
            return 'standard';
        }, 10, 1);
        
        // Enable most features
        add_filter('cp_enable_most_features', function() {
            return true;
        }, 10, 1);
    }
    
    /**
     * Configure alternatives for managed WordPress
     */
    private function configure_managed_wp_alternatives() {
        // Use WordPress-specific optimizations
        add_filter('cp_use_wordpress_optimizations', function() {
            return true;
        }, 10, 1);
        
        // Enable CDN if available
        add_filter('cp_enable_cdn', function() {
            return true;
        }, 10, 1);
        
        // Use provider-specific caching
        add_filter('cp_cache_provider', function() {
            return 'wordpress_optimized';
        }, 10, 1);
    }
    
    /**
     * Enable WordPress caching fallback
     */
    private function enable_wordpress_caching() {
        add_filter('cp_cache_driver', function() {
            return 'wp_transients';
        }, 10, 1);
        
        add_action('init', function() {
            // Set up WordPress transient caching
            add_filter('cp_cache_duration', function($duration) {
                return min($duration, 1800); // Max 30 minutes for shared hosting
            }, 10, 1);
        });
    }
    
    /**
     * Enable database caching fallback
     */
    private function enable_database_caching() {
        add_filter('cp_cache_driver', function() {
            return 'database';
        }, 10, 1);
        
        // Use lightweight database operations
        add_filter('cp_db_optimization', function() {
            return true;
        }, 10, 1);
    }
    
    /**
     * Enable WordPress HTTP API fallback
     */
    private function enable_wordpress_http_api() {
        add_filter('cp_http_client', function() {
            return 'wp_remote';
        }, 10, 1);
        
        // Ensure WP HTTP API is used for all requests
        add_filter('cp_use_wp_http_api', function() {
            return true;
        }, 10, 1);
    }
    
    /**
     * Enable PHP content enhancement fallback
     */
    private function enable_php_enhancement() {
        add_filter('cp_content_enhancement_method', function() {
            return 'php_algorithms';
        }, 10, 1);
        
        // Register PHP enhancement algorithms
        add_filter('cp_enhancement_algorithms', function($algorithms) {
            $algorithms[] = 'basic_text_processing';
            $algorithms[] = 'simple_sentence_restructure';
            $algorithms[] = 'vocabulary_substitution';
            return $algorithms;
        }, 10, 1);
    }
    
    /**
     * Enable basic enhancement fallback
     */
    private function enable_basic_enhancement() {
        add_filter('cp_humanization_level', function() {
            return 'basic';
        }, 10, 1);
        
        // Use simple text processing
        add_filter('cp_text_processing_approach', function() {
            return 'pattern_based';
        }, 10, 1);
    }
    
    /**
     * Enable basic statistics fallback
     */
    private function enable_basic_statistics() {
        add_filter('cp_analytics_level', function() {
            return 'minimal';
        }, 10, 1);
        
        // Track only essential metrics
        add_filter('cp_tracked_metrics', function($metrics) {
            return array(
                'posts_generated',
                'feeds_processed',
                'errors_encountered',
                'execution_time'
            );
        }, 10, 1);
    }
    
    /**
     * Enable sequential processing fallback
     */
    private function enable_sequential_processing() {
        add_filter('cp_processing_mode', function() {
            return 'sequential';
        }, 10, 1);
        
        // Add delays between operations
        add_filter('cp_operation_delay', function($delay) {
            return max($delay, 2); // Minimum 2 second delay
        }, 10, 1);
    }
    
    /**
     * Enable legacy compatibility mode
     */
    private function enable_legacy_mode() {
        add_filter('cp_compatibility_mode', function() {
            return 'legacy';
        }, 10, 1);
        
        // Disable advanced features
        add_filter('cp_disable_advanced_features', function() {
            return true;
        }, 10, 1);
        
        // Use simplified operations
        add_filter('cp_operation_complexity', function() {
            return 'simple';
        }, 10, 1);
    }
    
    /**
     * Enable external image service fallback
     */
    private function enable_external_image_service() {
        add_filter('cp_image_processing_method', function() {
            return 'external_service';
        }, 10, 1);
        
        // Use placeholder images as ultimate fallback
        add_filter('cp_use_placeholder_images', function() {
            return true;
        }, 10, 1);
    }
    
    /**
     * Get missing dependencies report
     * 
     * @return array Missing dependencies report
     */
    public function get_missing_dependencies() {
        return $this->missing_dependencies;
    }
    
    /**
     * Get applied fallbacks report
     * 
     * @return array Applied fallbacks report
     */
    public function get_applied_fallbacks() {
        return $this->applied_fallbacks;
    }
    
    /**
     * Check if feature has working fallback
     * 
     * @param string $feature Feature name
     * @return bool Has fallback
     */
    public function has_fallback($feature) {
        foreach ($this->missing_dependencies as $dependency => $details) {
            if ($details['feature'] === $feature) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Get fallback status for specific feature
     * 
     * @param string $feature Feature name
     * @return string|false Fallback status or false
     */
    public function get_fallback_status($feature) {
        foreach ($this->missing_dependencies as $dependency => $details) {
            if ($details['feature'] === $feature) {
                return $details['fallback'];
            }
        }
        return false;
    }
    
    /**
     * Get dependency health status
     * 
     * @return array Health status
     */
    public function get_health_status() {
        $total_dependencies = count($this->missing_dependencies);
        $critical_missing = 0;
        $warnings = array();
        
        foreach ($this->missing_dependencies as $dependency => $details) {
            if (in_array($details['feature'], array('core_functionality', 'security', 'http_requests'))) {
                $critical_missing++;
            } else {
                $warnings[] = $details['description'];
            }
        }
        
        return array(
            'health_score' => max(0, 100 - ($critical_missing * 25) - (count($warnings) * 5)),
            'critical_issues' => $critical_missing,
            'warnings' => $warnings,
            'total_missing' => $total_dependencies,
            'status' => $critical_missing > 0 ? 'degraded' : 'good'
        );
    }
}