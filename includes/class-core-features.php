<?php
/**
 * Core Features Class - All Features Now Free
 *
 * @package AI_Auto_News_Poster
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Core Features Class - All Features Available
 * 
 * This class now provides all previously "pro" features as core functionality
 * with no license validation or restrictions.
 */
class AANP_Core_Features {
    
    /**
     * Logger instance
     * @var AANP_Logger
     */
    private $logger;
    
    /**
     * Constructor
     */
    public function __construct() {
        try {
            $this->logger = new AANP_Logger();
            $this->init_hooks();
        } catch (Exception $e) {
            $this->log_error('Failed to initialize Core Features', $e);
        }
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        try {
            add_action('init', array($this, 'init_core_features'), 10);
            add_action('aanp_after_post_generation', array($this, 'generate_featured_image'));
            add_action('aanp_after_post_creation', array($this, 'add_seo_meta'));
            add_action('wp', array($this, 'schedule_automatic_generation'));
        } catch (Exception $e) {
            $this->log_error('Failed to initialize hooks', $e);
        }
    }
    
    /**
     * Initialize core features (formerly pro features)
     */
    public function init_core_features() {
        try {
            $this->log_info('Initializing Core Features');
            
            // All features are now available by default
            $this->initialize_featured_image_generation();
            $this->initialize_seo_optimization();
            $this->initialize_scheduling();
            
            $this->log_info('Core Features initialized successfully');
            
        } catch (Exception $e) {
            $this->log_error('Core Features initialization error', $e);
        }
    }
    
    /**
     * Initialize featured image generation
     */
    private function initialize_featured_image_generation() {
        try {
            // Featured image generation is now a core feature
            add_action('aanp_pro_generate_featured_image', array($this, 'trigger_featured_image_generation'));
        } catch (Exception $e) {
            $this->log_error('Failed to initialize featured image generation', $e);
        }
    }
    
    /**
     * Initialize SEO optimization
     */
    private function initialize_seo_optimization() {
        try {
            // SEO optimization is now a core feature
            add_action('aanp_pro_add_seo_meta', array($this, 'trigger_seo_meta_generation'));
        } catch (Exception $e) {
            $this->log_error('Failed to initialize SEO optimization', $e);
        }
    }
    
    /**
     * Initialize scheduling
     */
    private function initialize_scheduling() {
        try {
            // Scheduling is now a core feature
            add_action('aanp_pro_auto_generate', array($this, 'trigger_automatic_generation'));
        } catch (Exception $e) {
            $this->log_error('Failed to initialize scheduling', $e);
        }
    }
    
    /**
     * Generate featured image for post
     *
     * @param int $post_id Post ID
     * @param array $article Article data
     */
    public function generate_featured_image($post_id, $article) {
        try {
            // Validate parameters
            $this->validate_post_id($post_id);
            $this->validate_article_data($article);
            
            // All features are now available - no license check needed
            do_action('aanp_generate_featured_image', $post_id, $article);
            
            $this->log_info("Featured image generation triggered for post {$post_id}");
            
        } catch (Exception $e) {
            $this->log_error("Error generating featured image for post {$post_id}", $e);
        }
    }
    
    /**
     * Add SEO meta tags
     *
     * @param int $post_id Post ID
     * @param array $generated_content Generated content
     */
    public function add_seo_meta($post_id, $generated_content) {
        try {
            // Validate parameters
            $this->validate_post_id($post_id);
            
            if (!is_array($generated_content) && !is_string($generated_content)) {
                throw new Exception('Invalid content format');
            }
            
            // All SEO features are now available - no license check needed
            $sanitized_content = $this->sanitize_seo_content($generated_content);
            do_action('aanp_add_seo_meta', $post_id, $sanitized_content);
            
            $this->log_info("SEO meta generation triggered for post {$post_id}");
            
        } catch (Exception $e) {
            $this->log_error("Error adding SEO meta for post {$post_id}", $e);
        }
    }
    
    /**
     * Schedule automatic generation
     */
    public function schedule_automatic_generation() {
        try {
            // Scheduling is now a core feature - no license check needed
            
            // Check if already scheduled
            $next_scheduled = wp_next_scheduled('aanp_auto_generate_posts');
            if ($next_scheduled !== false) {
                return; // Already scheduled
            }
            
            // Schedule with proper error handling
            $scheduled = wp_schedule_event(time(), 'hourly', 'aanp_auto_generate_posts');
            
            if ($scheduled === false) {
                $this->log_error('Failed to schedule automatic generation');
                return;
            }
            
            // Add the action hook
            add_action('aanp_auto_generate_posts', array($this, 'run_automatic_generation'));
            
            $this->log_info('Automatic generation scheduled successfully');
            
        } catch (Exception $e) {
            $this->log_error('Error scheduling automatic generation', $e);
        }
    }
    
    /**
     * Run automatic generation
     */
    public function run_automatic_generation() {
        try {
            // No license validation needed
            do_action('aanp_auto_generate_content');
            
            $this->log_info('Automatic generation executed');
            
        } catch (Exception $e) {
            $this->log_error('Error in automatic generation', $e);
        }
    }
    
    /**
     * Trigger featured image generation action
     */
    public function trigger_featured_image_generation($post_id, $article) {
        // This is a compatibility hook for existing code
        $this->generate_featured_image($post_id, $article);
    }
    
    /**
     * Trigger SEO meta generation action
     */
    public function trigger_seo_meta_generation($post_id, $content) {
        // This is a compatibility hook for existing code
        $this->add_seo_meta($post_id, $content);
    }
    
    /**
     * Trigger automatic generation action
     */
    public function trigger_automatic_generation() {
        // This is a compatibility hook for existing code
        $this->run_automatic_generation();
    }
    
    /**
     * Get core features status
     *
     * @return array Core features status
     */
    public static function get_core_features_status() {
        try {
            $instance = new self();
            return $instance->get_core_features_status_safely();
            
        } catch (Exception $e) {
            error_log('AANP Core Features: Failed to get status - ' . $e->getMessage());
            return array();
        }
    }
    
    /**
     * Get core features status safely
     *
     * @return array Core features status
     */
    private function get_core_features_status_safely() {
        try {
            return array(
                'scheduling' => array(
                    'available' => true,
                    'title' => __('Automated Scheduling', 'contentpilot'),
                    'description' => __('Automatically generate posts on a schedule using WP-Cron.', 'contentpilot')
                ),
                'batch_size' => array(
                    'available' => true,
                    'title' => __('Large Batch Generation', 'contentpilot'),
                    'description' => __('Generate up to 30 posts per batch.', 'contentpilot')
                ),
                'featured_images' => array(
                    'available' => true,
                    'title' => __('Featured Image Generation', 'contentpilot'),
                    'description' => __('Automatically generate relevant featured images for posts.', 'contentpilot')
                ),
                'seo_optimization' => array(
                    'available' => true,
                    'title' => __('SEO Optimization', 'contentpilot'),
                    'description' => __('Auto-fill SEO meta descriptions and keywords.', 'contentpilot')
                ),
                'advanced_analytics' => array(
                    'available' => true,
                    'title' => __('Advanced Analytics', 'contentpilot'),
                    'description' => __('Get detailed insights into your content performance.', 'contentpilot')
                ),
                'priority_support' => array(
                    'available' => true,
                    'title' => __('Community Support', 'contentpilot'),
                    'description' => __('Get support through our community forums and documentation.', 'contentpilot')
                )
            );
            
        } catch (Exception $e) {
            $this->log_error('Failed to get core features status', $e);
            return array();
        }
    }
    
    /**
     * Display welcome notice for free features
     */
    public static function display_welcome_notice() {
        try {
            $instance = new self();
            $instance->display_welcome_notice_safely();
            
        } catch (Exception $e) {
            error_log('AANP Core Features: Failed to display welcome notice - ' . $e->getMessage());
        }
    }
    
    /**
     * Display welcome notice safely
     */
    private function display_welcome_notice_safely() {
        try {
            // Check if user can see notices
            if (!current_user_can('manage_options')) {
                return;
            }
            
            // Only show once
            if (get_option('aanp_welcome_notice_shown', false)) {
                return;
            }
            
            echo '<div class="notice notice-success aanp-welcome-notice" style="border-left-color: #46b450;">';
            echo '<p><strong>' . esc_html__('🚀 All Features Now Free!', 'contentpilot') . '</strong></p>';
            echo '<p>' . esc_html__('You now have access to all premium features including automated scheduling, large batch generation, featured image generation, SEO optimization, and advanced analytics - all included in the core plugin!', 'contentpilot') . '</p>';
            echo '<p>';
            echo '<a href="' . esc_url(admin_url('options-general.php?page=contentpilot')) . '" class="button button-primary">';
            echo esc_html__('Configure Your Settings', 'contentpilot');
            echo '</a>';
            echo ' <a href="#" class="button" onclick="this.parentElement.parentElement.parentElement.style.display=\'none\'; return false;">';
            echo esc_html__('Dismiss', 'contentpilot');
            echo '</a>';
            echo '</p>';
            echo '</div>';
            
            // Mark notice as shown
            update_option('aanp_welcome_notice_shown', true);
            
        } catch (Exception $e) {
            $this->log_error('Failed to display welcome notice', $e);
        }
    }
    
    /**
     * Validate post ID
     */
    private function validate_post_id($post_id) {
        if (!is_numeric($post_id) || intval($post_id) <= 0) {
            throw new Exception('Invalid post ID');
        }
        
        if (!post_exists(intval($post_id))) {
            throw new Exception('Post does not exist');
        }
    }
    
    /**
     * Validate article data
     */
    private function validate_article_data($article) {
        if (!is_array($article)) {
            throw new Exception('Article data must be an array');
        }
        
        if (empty($article)) {
            throw new Exception('Article data cannot be empty');
        }
    }
    
    /**
     * Sanitize SEO content
     */
    private function sanitize_seo_content($content) {
        if (is_array($content)) {
            return array_map(array($this, 'sanitize_seo_content'), $content);
        }
        
        if (is_string($content)) {
            return wp_kses($content, array());
        }
        
        return $content;
    }
    
    /**
     * Log info message
     */
    private function log_info($message) {
        try {
            if ($this->logger) {
                $this->logger->log('info', '[Core Features] ' . $message);
            } else {
                error_log('[AANP Core Features Info] ' . $message);
            }
        } catch (Exception $e) {
            error_log('[AANP Core Features] Failed to log info: ' . $e->getMessage());
        }
    }
    
    /**
     * Log error message
     */
    private function log_error($message, Exception $e = null) {
        try {
            $error_message = '[Core Features Error] ' . $message;
            if ($e) {
                $error_message .= ' - Exception: ' . $e->getMessage();
                $error_message .= ' - File: ' . $e->getFile() . ':' . $e->getLine();
            }
            
            if ($this->logger) {
                $this->logger->log('error', $error_message);
            } else {
                error_log($error_message);
            }
        } catch (Exception $log_e) {
            error_log('[AANP Core Features] Failed to log error: ' . $log_e->getMessage());
        }
    }
    
    /**
     * Cleanup resources
     */
    public function __destruct() {
        try {
            // Clear any temporary data
            
            // Remove scheduled events if they exist
            $timestamp = wp_next_scheduled('aanp_auto_generate_posts');
            if ($timestamp !== false) {
                wp_unschedule_event($timestamp, 'aanp_auto_generate_posts');
            }
            
        } catch (Exception $e) {
            // Suppress exceptions in destructor
            error_log('[AANP Core Features] Cleanup error: ' . $e->getMessage());
        }
    }
}

// Initialize Core features
try {
    new AANP_Core_Features();
} catch (Exception $e) {
    error_log('[AANP Core Features] Initialization failed: ' . $e->getMessage());
}