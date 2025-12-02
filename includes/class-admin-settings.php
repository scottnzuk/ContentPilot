<?php
/**
 * Admin Settings Class
 *
 * @package AI_Auto_News_Poster
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class CP_Admin_Settings {
    
    /**
     * Error logging instance
     */
    private $logger;
    
    /**
     * Security manager instance
     */
    private $security_manager;
    
    /**
     * Constructor
     */
    public function __construct() {
        try {
            // Initialize logger and security manager with error handling
            $this->init_dependencies();
            
            // Register hooks with error handling
            add_action('admin_menu', array($this, 'add_admin_menu'), 10);
            add_action('admin_init', array($this, 'init_settings'), 10);
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'), 10);
            add_action('wp_ajax_aanp_generate_posts', array($this, 'ajax_generate_posts'));
            add_action('wp_ajax_aanp_purge_cache', array($this, 'ajax_purge_cache'));
            add_action('wp_ajax_aanp_test_humanizer', array($this, 'ajax_test_humanizer'));
            
            // Log successful initialization if logger is available
            if ($this->logger) {
                $this->logger->log('info', 'Admin_Settings class initialized successfully');
            }
            
        } catch (Exception $e) {
            // Log initialization failure
            if (function_exists('error_log')) {
                error_log('AANP Admin Settings initialization failed: ' . $e->getMessage());
            }
            throw $e;
        }
    }
    
    /**
     * Initialize dependencies with graceful fallback
     */
    private function init_dependencies() {
        // Initialize logger
        if (class_exists('AANP_Logger')) {
            try {
                $this->logger = AANP_Logger::getInstance();
            } catch (Exception $e) {
                $this->logger = null;
                if (function_exists('error_log')) {
                    error_log('AANP Logger initialization failed: ' . $e->getMessage());
                }
            }
        } else {
            $this->logger = null;
        }
        
        // Initialize security manager
        if (class_exists('AANP_Security_Manager')) {
            try {
                $this->security_manager = new AANP_Security_Manager();
            } catch (Exception $e) {
                $this->security_manager = null;
                if (function_exists('error_log')) {
                    error_log('AANP Security Manager initialization failed: ' . $e->getMessage());
                }
            }
        } else {
            $this->security_manager = null;
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        try {
            // Main menu page
            add_menu_page(
                __('ContentPilot', 'contentpilot'),
                __('ContentPilot', 'contentpilot'),
                'manage_options',
                'cp-dashboard',
                array($this, 'dashboard_page'),
                'dashicons-megaphone',
                30
            );

            // Dashboard submenu
            add_submenu_page(
                'cp-dashboard',
                __('Dashboard', 'contentpilot'),
                __('Dashboard', 'contentpilot'),
                'manage_options',
                'cp-dashboard',
                array($this, 'dashboard_page')
            );

            // RSS Feeds submenu
            add_submenu_page(
                'cp-dashboard',
                __('RSS Feeds', 'contentpilot'),
                __('RSS Feeds', 'contentpilot'),
                'manage_options',
                'cp-rss-feeds',
                array($this, 'rss_feeds_page')
            );

            // Settings submenu
            add_submenu_page(
                'cp-dashboard',
                __('Settings', 'contentpilot'),
                __('Settings', 'contentpilot'),
                'manage_options',
                'cp-settings',
                array($this, 'settings_page')
            );

            // Analytics submenu
            add_submenu_page(
                'cp-dashboard',
                __('Analytics', 'contentpilot'),
                __('Analytics', 'contentpilot'),
                'manage_options',
                'cp-analytics',
                array($this, 'analytics_page')
            );
            
            // Add dashboard widgets
            add_action('wp_dashboard_setup', array($this, 'add_dashboard_widgets'));
            
        } catch (Exception $e) {
            $this->logger->log('error', 'Failed to add admin menu: ' . $e->getMessage());
            add_action('admin_notices', array($this, 'admin_menu_error_notice'));
        }
    }
    
    /**
     * Initialize settings
     */
    public function init_settings() {
        try {
            register_setting('cp_settings_group', 'cp_settings', array($this, 'sanitize_settings'));
            
            // Main settings section
            add_settings_section(
                'cp_main_section',
                __('Main Settings', 'contentpilot'),
                array($this, 'main_section_callback'),
                'contentpilot'
            );
            
            // LLM Provider field
            add_settings_field(
                'llm_provider',
                __('LLM Provider', 'contentpilot'),
                array($this, 'llm_provider_callback'),
                'contentpilot',
                'cp_main_section'
            );
            
            // API Key field
            add_settings_field(
                'api_key',
                __('API Key', 'contentpilot'),
                array($this, 'api_key_callback'),
                'contentpilot',
                'cp_main_section'
            );
            
            // Categories field
            add_settings_field(
                'categories',
                __('Post Categories', 'contentpilot'),
                array($this, 'categories_callback'),
                'contentpilot',
                'cp_main_section'
            );
            
            // Word count field
            add_settings_field(
                'word_count',
                __('Word Count', 'contentpilot'),
                array($this, 'word_count_callback'),
                'contentpilot',
                'cp_main_section'
            );
            
            // Tone field
            add_settings_field(
                'tone',
                __('Tone of Voice', 'contentpilot'),
                array($this, 'tone_callback'),
                'contentpilot',
                'cp_main_section'
            );
            
            // RSS Feeds section
            add_settings_section(
                'cp_rss_section',
                __('RSS Feeds', 'contentpilot'),
                array($this, 'rss_section_callback'),
                'contentpilot'
            );
            
            // RSS Feeds field
            add_settings_field(
                'rss_feeds',
                __('RSS Feed URLs', 'contentpilot'),
                array($this, 'rss_feeds_callback'),
                'contentpilot',
                'cp_rss_section'
            );
            
            // Cache section
            add_settings_section(
                'cp_cache_section',
                __('Cache Settings', 'contentpilot'),
                array($this, 'cache_section_callback'),
                'contentpilot'
            );
            
            // Cache management
            add_settings_field(
                'cache_management',
                __('Cache Management', 'contentpilot'),
                array($this, 'cache_management_callback'),
                'contentpilot',
                'cp_cache_section'
            );
            
        } catch (Exception $e) {
            $this->logger->log('error', 'Failed to initialize settings: ' . $e->getMessage());
            add_action('admin_notices', array($this, 'settings_init_error_notice'));
        }
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        try {
            if ($hook !== 'settings_page_contentpilot') {
                return;
            }
            
            wp_enqueue_script(
                'cp-admin-js',
                CP_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery'),
                AANP_VERSION,
                true
            );
            
            wp_enqueue_style(
                'cp-admin-css',
                CP_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                AANP_VERSION
            );
            
            // Generate secure nonce for settings page
            $settings_nonce = wp_create_nonce('aanp_settings_nonce');
            
            wp_localize_script('aanp-admin-js', 'aanp_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('aanp_nonce'),
                'settings_nonce' => $settings_nonce,
                'generating_text' => __('Generating posts...', 'contentpilot'),
                'success_text' => __('Posts generated successfully!', 'contentpilot'),
                'error_text' => __('Error generating posts. Please try again.', 'contentpilot')
            ));
            
        } catch (Exception $e) {
            $this->logger->log('error', 'Failed to enqueue admin scripts: ' . $e->getMessage());
        }
    }
    
    /**
     * Dashboard page content
     */
    public function dashboard_page() {
        try {
            include CP_PLUGIN_DIR . 'admin/dashboard-page.php';
        } catch (Exception $e) {
            $this->logger->log('error', 'Failed to load dashboard page: ' . $e->getMessage());
            wp_die(__('Failed to load dashboard page.', 'contentpilot'));
        }
    }
    
    /**
     * RSS Feeds page content
     */
    public function rss_feeds_page() {
        try {
            include CP_PLUGIN_DIR . 'admin/rss-feeds-page.php';
        } catch (Exception $e) {
            $this->logger->log('error', 'Failed to load RSS feeds page: ' . $e->getMessage());
            wp_die(__('Failed to load RSS feeds page.', 'contentpilot'));
        }
    }
    
    /**
     * Settings page content
     */
    public function settings_page() {
        try {
            include CP_PLUGIN_DIR . 'admin/settings-page.php';
        } catch (Exception $e) {
            $this->logger->log('error', 'Failed to load settings page: ' . $e->getMessage());
            wp_die(__('Failed to load settings page.', 'contentpilot'));
        }
    }
    
    /**
     * Analytics page content
     */
    public function analytics_page() {
        echo '<div class="wrap">';
        echo '<h1>' . __('Analytics', 'ai-auto-news-poster') . '</h1>';
        echo '<p>' . __('Analytics feature coming soon.', 'ai-auto-news-poster') . '</p>';
        echo '</div>';
    }
    
    /**
     * Add dashboard widgets
     */
    public function add_dashboard_widgets() {
        wp_add_dashboard_widget(
            'cp_dashboard_widget',
            __('ContentPilot Status', 'contentpilot'),
            array($this, 'dashboard_widget_content')
        );
        
        // Add RSS Feeds dashboard widget
        wp_add_dashboard_widget(
            'cp_rss_feeds_widget',
            __('RSS Feeds Status', 'contentpilot'),
            array($this, 'rss_feeds_dashboard_widget')
        );
    }
    
    /**
     * Dashboard widget content
     */
    public function dashboard_widget_content() {
        // Get plugin statistics
        $stats = $this->get_plugin_stats();
        
        echo '<div class="aanp-dashboard-widget">';
        echo '<div class="stats-grid">';
        
        // Posts created
        echo '<div class="stat-item">';
        echo '<div class="stat-number">' . esc_html($stats['posts_created']) . '</div>';
        echo '<div class="stat-label">' . __('Posts Created', 'ai-auto-news-poster') . '</div>';
        echo '</div>';
        
        // News fetched
        echo '<div class="stat-item">';
        echo '<div class="stat-number">' . esc_html($stats['news_fetched']) . '</div>';
        echo '<div class="stat-label">' . __('News Items Fetched', 'ai-auto-news-poster') . '</div>';
        echo '</div>';
        
        // Success rate
        echo '<div class="stat-item">';
        echo '<div class="stat-number">' . esc_html($stats['success_rate']) . '%</div>';
        echo '<div class="stat-label">' . __('Success Rate', 'ai-auto-news-poster') . '</div>';
        echo '</div>';
        
        // Last run
        echo '<div class="stat-item">';
        echo '<div class="stat-number">' . esc_html($stats['last_run']) . '</div>';
        echo '<div class="stat-label">' . __('Last Run', 'ai-auto-news-poster') . '</div>';
        echo '</div>';
        
        echo '</div>';
        
        // Quick actions
        echo '<div class="quick-actions">';
        echo '<a href="' . admin_url('admin.php?page=cp-settings') . '" class="button button-primary">';
        echo __('Configure Settings', 'ai-auto-news-poster');
        echo '</a>';
        echo ' ';
        echo '<a href="' . admin_url('admin.php?page=cp-analytics') . '" class="button">';
        echo __('View Analytics', 'ai-auto-news-poster');
        echo '</a>';
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * RSS Feeds dashboard widget content
     */
    public function rss_feeds_dashboard_widget() {
        // Get RSS feeds statistics
        if (class_exists('AANP_RSSFeedManager')) {
            $rss_manager = new AANP_RSSFeedManager();
            $stats = $rss_manager->get_feed_statistics();
        } else {
            $stats = array(
                'total_feeds' => 0,
                'enabled_feeds' => 0,
                'average_reliability' => 0,
                'recent_activity' => null
            );
        }
        
        echo '<div class="aanp-rss-widget">';
        echo '<div class="rss-stats">';
        
        echo '<div class="rss-stat-item">';
        echo '<div class="rss-stat-number">' . esc_html($stats['total_feeds']) . '</div>';
        echo '<div class="rss-stat-label">' . __('Total Feeds', 'ai-auto-news-poster') . '</div>';
        echo '</div>';
        
        echo '<div class="rss-stat-item">';
        echo '<div class="rss-stat-number">' . esc_html($stats['enabled_feeds']) . '</div>';
        echo '<div class="rss-stat-label">' . __('Enabled Feeds', 'ai-auto-news-poster') . '</div>';
        echo '</div>';
        
        echo '<div class="rss-stat-item">';
        echo '<div class="rss-stat-number">' . esc_html($stats['average_reliability']) . '%</div>';
        echo '<div class="rss-stat-label">' . __('Avg Reliability', 'ai-auto-news-poster') . '</div>';
        echo '</div>';
        
        echo '</div>';
        
        // Regional breakdown
        if (!empty($stats['regions'])) {
            echo '<div class="rss-regions">';
            echo '<h4>' . __('Regional Distribution', 'ai-auto-news-poster') . '</h4>';
            foreach ($stats['regions'] as $region => $count) {
                echo '<div class="region-item">';
                echo '<span class="region-name">' . esc_html($region) . '</span>';
                echo '<span class="region-count">' . esc_html($count) . '</span>';
                echo '</div>';
            }
            echo '</div>';
        }
        
        // Recent activity
        if ($stats['recent_activity']) {
            echo '<div class="rss-activity">';
            echo '<h4>' . __('Recent Activity', 'ai-auto-news-poster') . '</h4>';
            echo '<p>' . sprintf(__('Last feed update: %s', 'ai-auto-news-poster'),
                esc_html(human_time_diff(strtotime($stats['recent_activity'])) . ' ' . __('ago', 'ai-auto-news-poster'))
            ) . '</p>';
            echo '</div>';
        }
        
        // Quick actions
        echo '<div class="rss-quick-actions">';
        echo '<a href="' . admin_url('admin.php?page=cp-rss-feeds') . '" class="button button-primary">';
        echo __('Manage RSS Feeds', 'ai-auto-news-poster');
        echo '</a>';
        echo ' ';
        echo '<a href="' . wp_nonce_url(admin_url('admin.php?page=cp-dashboard&cp_action=validate_rss_feeds'), 'cp_rss_admin') . '" class="button">';
        echo __('Validate All Feeds', 'ai-auto-news-poster');
        echo '</a>';
        echo '</div>';
        
        echo '</div>';
    }
    
    /**
     * Get plugin statistics for dashboard widgets
     */
    private function get_plugin_stats() {
        $stats = array(
            'posts_created' => 0,
            'news_fetched' => 0,
            'success_rate' => 95,
            'last_run' => __('Never', 'ai-auto-news-poster')
        );
        
        try {
            // Get posts created by AI Auto News Poster
            $posts = get_posts(array(
                'post_type' => 'post',
                'meta_query' => array(
                    array(
                        'key' => '_aanp_generated',
                        'value' => '1',
                        'compare' => '='
                    )
                ),
                'posts_per_page' => -1,
                'fields' => 'ids'
            ));
            $stats['posts_created'] = count($posts);
            
            // Get last run time
            $last_run = get_option('cp_last_run');
            if ($last_run) {
                $stats['last_run'] = human_time_diff($last_run) . ' ' . __('ago', 'ai-auto-news-poster');
            }
            
            // Get news fetched count
            $news_count = get_option('cp_news_fetched_count', 0);
            $stats['news_fetched'] = $news_count;
            
        } catch (Exception $e) {
            $this->logger->log('error', 'Failed to get plugin stats: ' . $e->getMessage());
        }
        
        return $stats;
    }
    
    /**
     * Main section callback
     */
    public function main_section_callback() {
        echo '<p>' . esc_html__('Configure your ContentPilot settings below.', 'contentpilot') . '</p>';
    }
    
    /**
     * RSS section callback
     */
    public function rss_section_callback() {
        echo '<p>' . esc_html__('Manage RSS feeds for news sources.', 'contentpilot') . '</p>';
    }
    
    /**
     * Cache section callback
     */
    public function cache_section_callback() {
        echo '<p>' . esc_html__('Manage caching for better performance.', 'contentpilot') . '</p>';
    }
    
    /**
     * Cache management callback
     */
    public function cache_management_callback() {
        try {
            $cache_manager = new AANP_Cache_Manager();
            $stats = $cache_manager->get_cache_stats();
            
            echo '<div class="cp-cache-info">';
            echo '<p><strong>' . esc_html__('Cache Status:', 'contentpilot') . '</strong></p>';
            echo '<ul>';
            echo '<li>' . sprintf(
                esc_html__('Object Cache: %s', 'ai-auto-news-poster'),
                $stats['object_cache_enabled'] ? 'Enabled' : 'Disabled'
            ) . '</li>';
            echo '<li>' . sprintf(
                esc_html__('Transients: %d', 'contentpilot'),
                absint($stats['transients'])
            ) . '</li>';
            if (!empty($stats['cache_plugins'])) {
                echo '<li>' . esc_html__('Cache Plugins: ', 'contentpilot') .
                     esc_html(implode(', ', $stats['cache_plugins'])) . '</li>';
            }
            echo '</ul>';
            echo '<button type="button" id="cp-purge-cache" class="button">' .
                 esc_html__('Purge All Cache', 'contentpilot') . '</button>';
            echo '</div>';
        } catch (Exception $e) {
            $this->logger->log('error', 'Failed to display cache management: ' . $e->getMessage());
            echo '<p class="error">' . esc_html__('Error loading cache information.', 'contentpilot') . '</p>';
        }
    }
    
    /**
     * LLM Provider callback
     */
    public function llm_provider_callback() {
        try {
            $options = get_option('cp_settings', array());
            $value = isset($options['llm_provider']) ? sanitize_text_field($options['llm_provider']) : 'openai';
            
            echo '<select name="aanp_settings[llm_provider]" id="llm_provider">';
            echo '<option value="openai"' . selected($value, 'openai', false) . '>OpenAI</option>';
            echo '<option value="anthropic"' . selected($value, 'anthropic', false) . '>Anthropic</option>';
            echo '<option value="openrouter"' . selected($value, 'openrouter', false) . '>OpenRouter</option>';
            echo '<option value="custom"' . selected($value, 'custom', false) . '>Custom API</option>';
            echo '</select>';
            echo '<p class="description">' . esc_html__('Select your preferred LLM provider.', 'contentpilot') . '</p>';
        } catch (Exception $e) {
            $this->logger->log('error', 'Failed to render LLM provider field: ' . $e->getMessage());
            echo '<p class="error">' . esc_html__('Error loading provider options.', 'ai-auto-news-poster') . '</p>';
        }
    }
    
    /**
     * API Key callback
     */
    public function api_key_callback() {
        try {
            $options = get_option('aanp_settings', array());
            $value = isset($options['api_key']) ? self::decrypt_api_key($options['api_key']) : '';
            
            echo '<input type="password" name="aanp_settings[api_key]" id="api_key" value="' . 
                 esc_attr($value) . '" class="regular-text" />';
            echo '<p class="description">' .
                 esc_html__('Enter your API key for the selected LLM provider.', 'contentpilot') . '</p>';
            echo '<p class="description"><small>' .
                 esc_html__('Your API key is encrypted and stored securely.', 'contentpilot') . '</small></p>';
        } catch (Exception $e) {
            $this->logger->log('error', 'Failed to render API key field: ' . $e->getMessage());
            echo '<p class="error">' . esc_html__('Error loading API key field.', 'ai-auto-news-poster') . '</p>';
        }
    }
    
    /**
     * Categories callback
     */
    public function categories_callback() {
        try {
            $options = get_option('aanp_settings', array());
            $selected_categories = isset($options['categories']) ? 
                                  array_map('absint', $options['categories']) : array();
            
            $categories = get_categories(array('hide_empty' => false));
            
            if (empty($categories)) {
                echo '<p>' . esc_html__('No categories found. Please create categories first.', 'ai-auto-news-poster') . '</p>';
                return;
            }
            
            echo '<div class="aanp-categories">';
            foreach ($categories as $category) {
                $checked = in_array($category->term_id, $selected_categories, true) ? 'checked' : '';
                echo '<label>';
                echo '<input type="checkbox" name="aanp_settings[categories][]" value="' . 
                     absint($category->term_id) . '" ' . $checked . ' />';
                echo ' ' . esc_html($category->name);
                echo '</label><br>';
            }
            echo '</div>';
            echo '<p class="description">' . esc_html__('Select categories for generated posts.', 'contentpilot') . '</p>';
        } catch (Exception $e) {
            $this->logger->log('error', 'Failed to render categories field: ' . $e->getMessage());
            echo '<p class="error">' . esc_html__('Error loading categories.', 'ai-auto-news-poster') . '</p>';
        }
    }
    
    /**
     * Word count callback
     */
    public function word_count_callback() {
        try {
            $options = get_option('aanp_settings', array());
            $value = isset($options['word_count']) ? sanitize_text_field($options['word_count']) : 'medium';
            
            echo '<select name="aanp_settings[word_count]" id="word_count">';
            echo '<option value="short"' . selected($value, 'short', false) . '>Short (300-400 words)</option>';
            echo '<option value="medium"' . selected($value, 'medium', false) . '>Medium (500-600 words)</option>';
            echo '<option value="long"' . selected($value, 'long', false) . '>Long (800-1000 words)</option>';
            echo '</select>';
            echo '<p class="description">' .
                 esc_html__('Select the desired word count for generated posts.', 'contentpilot') . '</p>';
        } catch (Exception $e) {
            $this->logger->log('error', 'Failed to render word count field: ' . $e->getMessage());
            echo '<p class="error">' . esc_html__('Error loading word count options.', 'ai-auto-news-poster') . '</p>';
        }
    }
    
    /**
     * Tone callback
     */
    public function tone_callback() {
        try {
            $options = get_option('aanp_settings', array());
            $value = isset($options['tone']) ? sanitize_text_field($options['tone']) : 'neutral';
            
            echo '<select name="aanp_settings[tone]" id="tone">';
            echo '<option value="neutral"' . selected($value, 'neutral', false) . '>Neutral</option>';
            echo '<option value="professional"' . selected($value, 'professional', false) . '>Professional</option>';
            echo '<option value="friendly"' . selected($value, 'friendly', false) . '>Friendly</option>';
            echo '</select>';
            echo '<p class="description">' .
                 esc_html__('Select the tone of voice for generated content.', 'contentpilot') . '</p>';
        } catch (Exception $e) {
            $this->logger->log('error', 'Failed to render tone field: ' . $e->getMessage());
            echo '<p class="error">' . esc_html__('Error loading tone options.', 'ai-auto-news-poster') . '</p>';
        }
    }
    
    /**
     * RSS Feeds callback
     */
    public function rss_feeds_callback() {
        try {
            $options = get_option('aanp_settings', array());
            $feeds = isset($options['rss_feeds']) ? array_map('esc_url_raw', $options['rss_feeds']) : array();
            
            echo '<div id="rss-feeds-container">';
            if (!empty($feeds)) {
                foreach ($feeds as $index => $feed) {
                    echo '<div class="rss-feed-row">';
                    echo '<input type="url" name="aanp_settings[rss_feeds][]" value="' . 
                         esc_attr($feed) . '" class="regular-text" placeholder="https://example.com/feed.xml" />';
                    echo '<button type="button" class="button remove-feed">Remove</button>';
                    echo '</div>';
                }
            }
            echo '</div>';
            echo '<button type="button" id="add-feed" class="button">Add RSS Feed</button>';
            echo '<p class="description">' .
                 esc_html__('Add RSS feed URLs for news sources.', 'contentpilot') . '</p>';
        } catch (Exception $e) {
            $this->logger->log('error', 'Failed to render RSS feeds field: ' . $e->getMessage());
            echo '<p class="error">' . esc_html__('Error loading RSS feeds.', 'ai-auto-news-poster') . '</p>';
        }
    }
    
    /**
     * AJAX handler for generating posts
     */
    public function ajax_generate_posts() {
        try {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'], 'cp_nonce')) {
                $this->logger->log('warning', 'Invalid nonce for post generation request');
                wp_send_json_error(__('Security check failed.', 'contentpilot'));
                return;
            }
            
            // Check user capabilities
            if (!current_user_can('manage_options')) {
                $this->logger->log('warning', 'Insufficient permissions for post generation', array(
                    'user_id' => get_current_user_id()
                ));
                wp_die(__('Insufficient permissions', 'contentpilot'));
            }
            
            // Rate limiting
            $rate_limiter = new AANP_Rate_Limiter();
            if ($rate_limiter->is_rate_limited('generate_posts', 3, 3600)) {
                $this->logger->log('warning', 'Rate limit exceeded for post generation', array(
                    'user_id' => get_current_user_id()
                ));
                wp_send_json_error(__('Rate limit exceeded. Please wait before generating more posts.', 'contentpilot'));
                return;
            }
            
            $rate_limiter->record_attempt('generate_posts', 3600);
            
            // Initialize classes with error handling
            $news_fetch = new AANP_News_Fetch();
            $ai_generator = new AANP_AI_Generator();
            $post_creator = new AANP_Post_Creator();
            
            // Fetch news articles
            $articles = $news_fetch->fetch_latest_news();
            
            if (empty($articles)) {
                wp_send_json_error(__('No articles found', 'contentpilot'));
                return;
            }
            
            // Use the main plugin's max posts setting (now 30)
            $max_posts = AI_Auto_News_Poster::get_max_posts_per_batch();
            $articles = array_slice($articles, 0, $max_posts);
            
            $generated_posts = array();
            
            foreach ($articles as $article) {
                try {
                    // Generate content using AI
                    $generated_content = $ai_generator->generate_content($article);
                    
                    if ($generated_content) {
                        // Create WordPress post
                        $post_id = $post_creator->create_post($generated_content, $article);
                        
                        if ($post_id) {
                            $generated_posts[] = array(
                                'id' => $post_id,
                                'title' => $generated_content['title'],
                                'edit_link' => get_edit_post_link($post_id)
                            );
                        }
                    }
                } catch (Exception $e) {
                    $this->logger->log('error', 'Failed to process individual article', array(
                        'article_url' => $article['url'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ));
                    continue;
                }
            }
            
            if (!empty($generated_posts)) {
                $this->logger->log('info', sprintf('Generated %d posts successfully', count($generated_posts)));
                wp_send_json_success(array(
                    'message' => sprintf(
                        /* translators: %d: Number of posts generated */
                        __('%d posts generated successfully!', 'contentpilot'),
                        count($generated_posts)
                    ),
                    'posts' => $generated_posts
                ));
            } else {
                wp_send_json_error(__('Failed to generate posts', 'contentpilot'));
            }
            
        } catch (Exception $e) {
            $this->logger->log('error', 'Error in AJAX post generation', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
            wp_send_json_error(__('An error occurred while generating posts.', 'contentpilot'));
        }
    }
    
    /**
     * AJAX handler for cache purging
     */
    public function ajax_purge_cache() {
        try {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'], 'cp_nonce')) {
                $this->logger->log('warning', 'Invalid nonce for cache purge request');
                wp_die(__('Security check failed.', 'contentpilot'));
            }
            
            // Check user capabilities
            if (!current_user_can('manage_options')) {
                $this->logger->log('warning', 'Insufficient permissions for cache purge', array(
                    'user_id' => get_current_user_id()
                ));
                wp_die(__('Insufficient permissions', 'contentpilot'));
            }
            
            $cache_manager = new AANP_Cache_Manager();
            $result = $cache_manager->purge_all();
            
            if ($result) {
                $this->logger->log('info', 'Cache purged successfully by user', array(
                    'user_id' => get_current_user_id()
                ));
                wp_send_json_success(__('Cache purged successfully!', 'contentpilot'));
            } else {
                wp_send_json_error(__('Failed to purge cache.', 'contentpilot'));
            }
            
        } catch (Exception $e) {
            $this->logger->log('error', 'Error purging cache', array(
                'error' => $e->getMessage(),
                'user_id' => get_current_user_id()
            ));
            wp_send_json_error(__('An error occurred while purging cache.', 'contentpilot'));
        }
    }
    
    /**
     * Sanitize settings with comprehensive validation
     */
    public function sanitize_settings($input) {
        try {
            // Verify nonce for settings save
            if (!wp_verify_nonce($_POST['_wpnonce'], 'cp_settings_group')) {
                $this->logger->log('warning', 'Invalid nonce for settings save');
                add_settings_error('cp_settings', 'invalid_nonce',
                    __('Security verification failed. Settings not saved.', 'contentpilot'));
                return get_option('cp_settings', array());
            }
            
            $sanitized = array();
            
            // Validate and sanitize LLM provider
            if (isset($input['llm_provider'])) {
                $allowed_providers = array('openai', 'anthropic', 'openrouter', 'custom');
                $provider = sanitize_text_field($input['llm_provider']);
                if (in_array($provider, $allowed_providers, true)) {
                    $sanitized['llm_provider'] = $provider;
                } else {
                    add_settings_error('cp_settings', 'invalid_provider',
                        __('Invalid LLM provider selected.', 'contentpilot'));
                    $sanitized['llm_provider'] = 'openai'; // Default fallback
                    $this->logger->log('warning', 'Invalid LLM provider submitted', array(
                        'submitted_value' => $provider,
                        'user_id' => get_current_user_id()
                    ));
                }
            }
            
            // Sanitize and encrypt API key with proper error handling
            if (isset($input['api_key'])) {
                $api_key = trim(sanitize_text_field($input['api_key']));
                if (!empty($api_key)) {
                    // Enhanced validation for API key format
                    if (strlen($api_key) < 10) {
                        add_settings_error('cp_settings', 'invalid_api_key',
                            __('API key appears to be too short.', 'contentpilot'));
                        $this->logger->log('warning', 'API key validation failed - too short', array(
                            'user_id' => get_current_user_id()
                        ));
                    } elseif (!$this->validate_api_key_format($api_key)) {
                        add_settings_error('cp_settings', 'invalid_api_key',
                            __('API key format is invalid.', 'contentpilot'));
                        $this->logger->log('warning', 'API key validation failed - invalid format', array(
                            'user_id' => get_current_user_id()
                        ));
                    } else {
                        // Store encrypted API key with error handling
                        try {
                            $sanitized['api_key'] = $this->encrypt_api_key($api_key);
                            $this->logger->log('info', 'API key updated successfully', array(
                                'user_id' => get_current_user_id(),
                                'provider' => $sanitized['llm_provider'] ?? 'unknown'
                            ));
                        } catch (Exception $e) {
                            add_settings_error('cp_settings', 'encryption_failed',
                                __('Failed to encrypt API key. Please check your server configuration.', 'contentpilot'));
                            $this->logger->log('error', 'API key encryption failed', array(
                                'error' => $e->getMessage(),
                                'user_id' => get_current_user_id()
                            ));
                        }
                    }
                } else {
                    $sanitized['api_key'] = '';
                }
            }
            
            // Validate and sanitize categories
            if (isset($input['categories']) && is_array($input['categories'])) {
                $sanitized['categories'] = array();
                $valid_categories = get_categories(array('hide_empty' => false));
                $valid_cat_ids = wp_list_pluck($valid_categories, 'term_id');
                
                foreach ($input['categories'] as $cat_id) {
                    $cat_id = intval($cat_id);
                    if (in_array($cat_id, $valid_cat_ids, true)) {
                        $sanitized['categories'][] = $cat_id;
                    }
                }
            }
            
            // Validate and sanitize word count
            if (isset($input['word_count'])) {
                $allowed_counts = array('short', 'medium', 'long');
                $word_count = sanitize_text_field($input['word_count']);
                if (in_array($word_count, $allowed_counts, true)) {
                    $sanitized['word_count'] = $word_count;
                } else {
                    $sanitized['word_count'] = 'medium'; // Default fallback
                }
            }
            
            // Validate and sanitize tone
            if (isset($input['tone'])) {
                $allowed_tones = array('neutral', 'professional', 'friendly');
                $tone = sanitize_text_field($input['tone']);
                if (in_array($tone, $allowed_tones, true)) {
                    $sanitized['tone'] = $tone;
                } else {
                    $sanitized['tone'] = 'neutral'; // Default fallback
                }
            }
            
            // Validate and sanitize RSS feeds
            if (isset($input['rss_feeds']) && is_array($input['rss_feeds'])) {
                $sanitized['rss_feeds'] = array();
                $max_feeds = 20; // Limit number of feeds
                $feed_count = 0;
                $validated_feeds = array();
                
                foreach ($input['rss_feeds'] as $feed) {
                    if ($feed_count >= $max_feeds) {
                        add_settings_error('cp_settings', 'too_many_feeds',
                            __('Maximum 20 RSS feeds allowed.', 'contentpilot'));
                        $this->logger->log('warning', 'RSS feeds limit exceeded', array(
                            'submitted_count' => count($input['rss_feeds']),
                            'max_allowed' => $max_feeds,
                            'user_id' => get_current_user_id()
                        ));
                        break;
                    }
                    
                    $feed = trim($feed);
                    if (!empty($feed)) {
                        $feed = esc_url_raw($feed);
                        if (filter_var($feed, FILTER_VALIDATE_URL)) {
                            // Additional security check for feed URL
                            $parsed_url = parse_url($feed);
                            if (isset($parsed_url['scheme']) && 
                                in_array($parsed_url['scheme'], array('http', 'https'), true)) {
                                $validated_feeds[] = $feed;
                                $feed_count++;
                            }
                        }
                    }
                }
                
                $sanitized['rss_feeds'] = $validated_feeds;
                
                // Ensure at least one feed exists
                if (empty($sanitized['rss_feeds'])) {
                    $sanitized['rss_feeds'] = array(
                        'https://feeds.bbci.co.uk/news/rss.xml'
                    );
                    add_settings_error('cp_settings', 'no_feeds',
                        __('At least one RSS feed is required. Default feed added.', 'contentpilot'));
                }
            }
            
            $this->logger->log('info', 'Settings saved successfully', array(
                'user_id' => get_current_user_id(),
                'settings_updated' => array_keys($sanitized)
            ));
            
            return $sanitized;
            
        } catch (Exception $e) {
            $this->logger->log('error', 'Error sanitizing settings', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
            add_settings_error('cp_settings', 'sanitization_error',
                __('An error occurred while saving settings.', 'contentpilot'));
            return get_option('cp_settings', array());
        }
    }
    
    /**
     * Encrypt API key for secure storage with comprehensive error handling
     */
    private function encrypt_api_key($api_key) {
        try {
            // Input validation
            if (empty($api_key)) {
                throw new Exception('API key is empty');
            }
            
            if (!function_exists('openssl_encrypt')) {
                throw new Exception('OpenSSL encryption not available');
            }
            
            // Generate secure encryption key
            $key = hash('sha256', wp_salt('auth') . wp_salt('secure_auth'), true);
            if (strlen($key) !== 32) {
                throw new Exception('Invalid encryption key length');
            }
            
            // Generate random initialization vector
            $iv = openssl_random_pseudo_bytes(16);
            if ($iv === false || strlen($iv) !== 16) {
                throw new Exception('Failed to generate random IV');
            }
            
            // Perform encryption
            $encrypted = openssl_encrypt($api_key, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
            if ($encrypted === false) {
                $error = openssl_error_string();
                throw new Exception('Encryption failed: ' . ($error[1] ?? 'Unknown error'));
            }
            
            // Combine IV and encrypted data
            $combined = $iv . $encrypted;
            $encoded = base64_encode($combined);
            
            if ($encoded === false) {
                throw new Exception('Failed to encode encrypted data');
            }
            
            $this->logger->log('info', 'API key encrypted successfully');
            return $encoded;
            
        } catch (Exception $e) {
            $this->logger->log('error', 'API key encryption failed', array(
                'error' => $e->getMessage(),
                'error_code' => $e->getCode()
            ));
            throw $e;
        }
    }
    
    /**
     * Decrypt API key for use with comprehensive error handling
     */
    public static function decrypt_api_key($encrypted_key) {
        try {
            // Input validation
            if (empty($encrypted_key)) {
                return '';
            }
            
            if (!function_exists('openssl_decrypt')) {
                throw new Exception('OpenSSL decryption not available');
            }
            
            // Decode base64
            $data = base64_decode($encrypted_key, true); // strict mode
            if ($data === false) {
                throw new Exception('Invalid base64 encoding');
            }
            
            // Validate data length
            if (strlen($data) < 16) {
                throw new Exception('Invalid encrypted data length');
            }
            
            // Extract IV and encrypted data
            $iv = substr($data, 0, 16);
            $encrypted = substr($data, 16);
            
            // Generate same key used for encryption
            $key = hash('sha256', wp_salt('auth') . wp_salt('secure_auth'), true);
            if (strlen($key) !== 32) {
                throw new Exception('Invalid encryption key length');
            }
            
            // Perform decryption
            $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
            if ($decrypted === false) {
                throw new Exception('Decryption failed - data may be corrupted');
            }
            
            return $decrypted;
            
        } catch (Exception $e) {
            // Log error without exposing sensitive data
            if (class_exists('AANP_Logger')) {
                $logger = AANP_Logger::getInstance();
                $logger->log('error', 'API key decryption failed', array(
                    'error' => $e->getMessage(),
                    'error_code' => $e->getCode()
                ));
            }
            return '';
        }
    }
    
    /**
     * Validate API key format based on provider
     */
    private function validate_api_key_format($api_key) {
        try {
            $options = get_option('aanp_settings', array());
            $provider = isset($options['llm_provider']) ? $options['llm_provider'] : 'openai';
            
            switch ($provider) {
                case 'openai':
                    // OpenAI API keys start with 'sk-' and are 51+ characters
                    return (substr($api_key, 0, 3) === 'sk-' && strlen($api_key) >= 51);
                    
                case 'anthropic':
                    // Anthropic API keys start with 'sk-ant-' and are typically 48+ characters
                    return (substr($api_key, 0, 7) === 'sk-ant-' && strlen($api_key) >= 48);
                    
                case 'openrouter':
                    // OpenRouter API keys typically start with 'sk-or-' and are 30+ characters
                    return (substr($api_key, 0, 5) === 'sk-or-' && strlen($api_key) >= 30);
                    
                case 'custom':
                    // For custom APIs, just ensure minimum length
                    return strlen($api_key) >= 10;
                    
                default:
                    return strlen($api_key) >= 10;
            }
        } catch (Exception $e) {
            $this->logger->log('error', 'API key validation error', array(
                'error' => $e->getMessage()
            ));
            return false;
        }
    }
    
    /**
     * Admin menu error notice
     */
    public function admin_menu_error_notice() {
        echo '<div class="notice notice-error"><p>' .
             esc_html__('ContentPilot: Failed to initialize admin menu. Please contact support.', 'contentpilot') .
             '</p></div>';
    }
    
    /**
     * Settings initialization error notice
     */
    public function settings_init_error_notice() {
        echo '<div class="notice notice-error"><p>' .
             esc_html__('ContentPilot: Failed to initialize settings. Please contact support.', 'contentpilot') .
             '</p></div>';
    }
    
    /**
     * AJAX handler for testing humanizer
     */
    public function ajax_test_humanizer() {
        try {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'], 'cp_settings_nonce')) {
                $this->logger->log('warning', 'Invalid nonce for humanizer test request');
                wp_send_json_error(__('Security check failed.', 'contentpilot'));
                return;
            }
            
            // Check user capabilities
            if (!current_user_can('manage_options')) {
                $this->logger->log('warning', 'Insufficient permissions for humanizer test', array(
                    'user_id' => get_current_user_id()
                ));
                wp_die(__('Insufficient permissions', 'contentpilot'));
            }
            
            // Get input parameters
            $text = isset($_POST['text']) ? sanitize_textarea_field($_POST['text']) : '';
            $strength = isset($_POST['strength']) ? sanitize_text_field($_POST['strength']) : 'medium';
            $personality = isset($_POST['personality']) ? sanitize_text_field($_POST['personality']) : '';
            
            if (empty($text)) {
                wp_send_json_error(__('No text provided for testing.', 'contentpilot'));
                return;
            }
            
            // Initialize humanizer manager
            $humanizer_manager = new AANP_HumanizerManager();
            
            // Update settings for test
            $test_settings = array(
                'enabled' => true,
                'strength' => $strength,
                'custom_personality' => $personality
            );
            $humanizer_manager->update_settings($test_settings);
            
            // Test humanization
            $test_result = $humanizer_manager->test_humanizer($text);
            
            if ($test_result['success']) {
                $this->logger->info('Humanizer test completed successfully', array(
                    'text_length' => strlen($text),
                    'execution_time_ms' => $test_result['execution_time_ms']
                ));
                
                wp_send_json_success(array(
                    'success' => true,
                    'content' => $test_result['humanized_content'],
                    'execution_time_ms' => $test_result['execution_time_ms'],
                    'test_metadata' => $test_result['test_metadata'] ?? array()
                ));
            } else {
                $this->logger->warning('Humanizer test failed', array(
                    'error' => $test_result['error']
                ));
                
                wp_send_json_success(array(
                    'success' => false,
                    'error' => $test_result['error']
                ));
            }
            
        } catch (Exception $e) {
            $this->logger->error('Error in AJAX humanizer test', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
            wp_send_json_error(__('An error occurred while testing the humanizer.', 'contentpilot'));
        }
    }
    
    /**
     * Get encryption status for admin dashboard
     */
    public function get_encryption_status() {
        try {
            $status = array(
                'openssl_available' => function_exists('openssl_encrypt'),
                'encryption_ready' => false,
                'message' => ''
            );
            
            if (!$status['openssl_available']) {
                $status['message'] = __('OpenSSL encryption not available.', 'contentpilot');
            } else {
                // Test encryption/decryption
                $test_data = 'test_encryption_' . time();
                try {
                    $encrypted = $this->encrypt_api_key($test_data);
                    $decrypted = self::decrypt_api_key($encrypted);
                    $status['encryption_ready'] = ($decrypted === $test_data);
                    $status['message'] = $status['encryption_ready'] ?
                        __('Encryption system working correctly.', 'contentpilot') :
                        __('Encryption system test failed.', 'contentpilot');
                } catch (Exception $e) {
                    $status['message'] = __('Encryption system error: ', 'contentpilot') . $e->getMessage();
                }
            }
            
            return $status;
        } catch (Exception $e) {
            return array(
                'openssl_available' => false,
                'encryption_ready' => false,
                'message' => __('Unable to check encryption status.', 'contentpilot')
            );
        }
    }
}