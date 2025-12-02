<?php
/**
 * Modern Dashboard Admin Page
 * 
 * Provides integration between the modern dashboard and WordPress admin interface
 * with proper WordPress admin styling, navigation, and functionality.
 *
 * @package ContentPilot
 * @subpackage Admin
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Check user permissions
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

// Get dashboard configuration
$dashboard_config = get_option('ai_news_dashboard_config', [
    'theme' => 'light',
    'auto_refresh' => true,
    'refresh_interval' => 30,
    'notifications' => true
]);

// Prepare data for the dashboard
$dashboard_data = [
    'ajax_url' => admin_url('admin-ajax.php'),
    'rest_url' => rest_url('contentpilot/v1/'),
    'nonce' => wp_create_nonce('ai_news_dashboard_nonce'),
    'user' => [
        'id' => get_current_user_id(),
        'name' => wp_get_current_user()->display_name,
        'capabilities' => array_keys(array_filter(wp_get_current_user()->allcaps))
    ],
    'config' => $dashboard_config,
    'features' => [
        'real_time_monitoring' => true,
        'api_platform' => true,
        'seo_compliance' => true,
        'webhooks' => true,
        'graphql' => true
    ]
];

// Enqueue dashboard assets
wp_enqueue_style('ai-news-dashboard', plugin_dir_url(__FILE__) . 'assets/css/dashboard.css', [], '2.0.0');
wp_enqueue_style('ai-news-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], '3.9.1', true);
wp_enqueue_script('axios', 'https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js', [], '0.27.2', true);
wp_enqueue_script('ai-news-dashboard', plugin_dir_url(__FILE__) . 'assets/js/dashboard.js', ['jquery', 'chart-js', 'axios'], '2.0.0', true);

// Localize script with dashboard data
wp_localize_script('ai-news-dashboard', 'ai_news_dashboard', $dashboard_data);

// Set up WordPress admin hooks
add_action('admin_head', 'ai_news_dashboard_admin_head');
add_action('admin_footer', 'ai_news_dashboard_admin_footer');

/**
 * Add admin head elements for the dashboard
 */
function ai_news_dashboard_admin_head() {
    global $pagenow;
    
    // Only add on our dashboard page
    if ($pagenow !== 'admin.php' || !isset($_GET['page']) || $_GET['page'] !== 'ai-news-dashboard') {
        return;
    }
    
    // Add viewport meta tag for responsive design
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n";
    
    // Add PWA manifest link
    echo '<link rel="manifest" href="' . plugin_dir_url(__FILE__) . 'manifest.json">' . "\n";
    
    // Add theme color for mobile browsers
    echo '<meta name="theme-color" content="#007cba">' . "\n";
    
    // Add Apple PWA meta tags
    echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
    echo '<meta name="apple-mobile-web-app-status-bar-style" content="default">' . "\n";
    
    // Add preconnect for external resources
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
    echo '<link rel="preconnect" href="https://cdn.jsdelivr.net">' . "\n";
}

/**
 * Add admin footer elements for the dashboard
 */
function ai_news_dashboard_admin_footer() {
    global $pagenow;
    
    // Only add on our dashboard page
    if ($pagenow !== 'admin.php' || !isset($_GET['page']) || $_GET['page'] !== 'ai-news-dashboard') {
        return;
    }
    
    // Add WordPress admin integrations
    ?>
    <script type="text/javascript">
        // WordPress admin integration
        jQuery(document).ready(function($) {
            // Hide WordPress admin elements that conflict with modern dashboard
            $('#wpadminbar, #adminmenu, #wpfooter, #screen-options-wrap').hide();
            
            // Add WordPress-style notifications
            window.aiNewsAdmin = {
                showNotice: function(message, type) {
                    var noticeClass = 'notice notice-' + (type || 'info');
                    var notice = $('<div class="' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
                    $('.wrap h1').after(notice);
                    
                    // Auto-hide after 5 seconds
                    setTimeout(function() {
                        notice.fadeOut();
                    }, 5000);
                },
                
                confirmAction: function(message, callback) {
                    if (confirm(message)) {
                        callback();
                    }
                },
                
                showProgress: function(message) {
                    if ($('#ai-news-progress').length === 0) {
                        $('body').append('<div id="ai-news-progress" style="position: fixed; top: 0; left: 0; width: 100%; background: #0073aa; color: white; padding: 10px; text-align: center; z-index: 9999;">' + message + '</div>');
                    } else {
                        $('#ai-news-progress').text(message).show();
                    }
                },
                
                hideProgress: function() {
                    $('#ai-news-progress').hide();
                }
            };
            
            // Override WordPress autosave for our dashboard
            if (typeof wp.autosave !== 'undefined') {
                wp.autosave.server.pause();
            }
            
            // Add dashboard loading state
            $('body').addClass('ai-news-dashboard-loading');
            
            // Remove loading state when dashboard is ready
            $(window).on('load', function() {
                $('body').removeClass('ai-news-dashboard-loading');
            });
        });
    </script>
    <?php
}

/**
 * Dashboard admin page callback
 */
function ai_news_dashboard_page() {
    // Add screen options
    add_screen_option('layout_columns', ['max' => 1]);
    
    // Get current tab from URL
    $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';
    
    // Page title
    $page_title = 'ContentPilot - Dashboard';
    
    // Get navigation tabs
    $tabs = ai_news_dashboard_get_tabs();
    
    ?>
    <div class="wrap ai-news-dashboard-wrap">
        <!-- Dashboard Header -->
        <div class="ai-news-dashboard-header">
            <div class="ai-news-header-content">
                <div class="ai-news-title-section">
                    <h1 class="ai-news-page-title">
                        <span class="ai-news-title-icon">🚀</span>
                        <?php echo esc_html($page_title); ?>
                        <span class="ai-news-version">v2.0</span>
                    </h1>
                    <div class="ai-news-page-description">
                        <?php _e('Monitor performance, manage content, and optimize SEO with real-time analytics', 'contentpilot'); ?>
                    </div>
                </div>
                
                <div class="ai-news-header-actions">
                    <!-- WordPress-style notification area -->
                    <div id="ai-news-notifications" class="ai-news-notifications">
                        <?php
                        // Show any dashboard-specific notices
                        $notices = get_transient('ai_news_dashboard_notices');
                        if ($notices) {
                            foreach ($notices as $notice) {
                                echo '<div class="notice notice-' . esc_attr($notice['type']) . ' is-dismissible">';
                                echo '<p>' . esc_html($notice['message']) . '</p>';
                                echo '</div>';
                            }
                            delete_transient('ai_news_dashboard_notices');
                        }
                        ?>
                    </div>
                    
                    <!-- Action buttons -->
                    <div class="ai-news-quick-actions">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-news-settings')); ?>" class="button button-secondary">
                            <span class="dashicons dashicons-admin-settings"></span>
                            <?php _e('Settings', 'contentpilot'); ?>
                        </a>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-news-help')); ?>" class="button button-secondary">
                            <span class="dashicons dashicons-info"></span>
                            <?php _e('Help', 'contentpilot'); ?>
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Dashboard Navigation Tabs -->
            <?php if (!empty($tabs)): ?>
            <div class="ai-news-dashboard-nav">
                <nav class="nav-tab-wrapper">
                    <?php foreach ($tabs as $tab_slug => $tab): ?>
                        <a href="<?php echo esc_url(add_query_arg('tab', $tab_slug)); ?>" 
                           class="nav-tab <?php echo $current_tab === $tab_slug ? 'nav-tab-active' : ''; ?>">
                            <span class="dashicons <?php echo esc_attr($tab['icon']); ?>"></span>
                            <?php echo esc_html($tab['title']); ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>
            <?php endif; ?>
        </div>

        <!-- Dashboard Content -->
        <div class="ai-news-dashboard-content">
            <?php
            // Load the appropriate dashboard section
            switch ($current_tab) {
                case 'overview':
                    ai_news_dashboard_overview();
                    break;
                case 'performance':
                    ai_news_dashboard_performance();
                    break;
                case 'content':
                    ai_news_dashboard_content();
                    break;
                case 'seo':
                    ai_news_dashboard_seo();
                    break;
                case 'api':
                    ai_news_dashboard_api();
                    break;
                case 'settings':
                    ai_news_dashboard_settings();
                    break;
                default:
                    ai_news_dashboard_overview();
                    break;
            }
            ?>
        </div>
    </div>
    
    <!-- WordPress admin modal integration -->
    <div id="ai-news-modal" class="ai-news-modal" style="display: none;">
        <div class="ai-news-modal-content">
            <div class="ai-news-modal-header">
                <h3 id="ai-news-modal-title">Modal Title</h3>
                <button type="button" class="ai-news-modal-close">&times;</button>
            </div>
            <div class="ai-news-modal-body" id="ai-news-modal-body">
                <!-- Modal content will be loaded here -->
            </div>
            <div class="ai-news-modal-footer">
                <button type="button" class="button button-secondary ai-news-modal-cancel">Cancel</button>
                <button type="button" class="button button-primary ai-news-modal-confirm">Confirm</button>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Get dashboard navigation tabs
 */
function ai_news_dashboard_get_tabs() {
    $tabs = [
        'overview' => [
            'title' => __('Overview', 'contentpilot'),
            'icon' => 'dashicons-dashboard'
        ],
        'performance' => [
            'title' => __('Performance', 'contentpilot'),
            'icon' => 'dashicons-performance'
        ],
        'content' => [
            'title' => __('Content', 'contentpilot'),
            'icon' => 'dashicons-edit-large'
        ],
        'seo' => [
            'title' => __('SEO & EEAT', 'contentpilot'),
            'icon' => 'dashicons-search'
        ],
        'api' => [
            'title' => __('API Platform', 'contentpilot'),
            'icon' => 'dashicons-networking'
        ],
        'settings' => [
            'title' => __('Settings', 'contentpilot'),
            'icon' => 'dashicons-admin-settings'
        ]
    ];
    
    // Filter tabs based on user capabilities
    $user = wp_get_current_user();
    if (!current_user_can('manage_options')) {
        unset($tabs['settings']);
    }
    
    return apply_filters('ai_news_dashboard_tabs', $tabs);
}

/**
 * Dashboard overview section
 */
function ai_news_dashboard_overview() {
    ?>
    <div id="ai-news-dashboard-app">
        <!-- Modern dashboard iframe -->
        <iframe 
            src="<?php echo esc_url(plugin_dir_url(__FILE__) . 'index.html'); ?>" 
            class="ai-news-dashboard-iframe"
            id="main-dashboard"
            width="100%" 
            height="800"
            frameborder="0">
        </iframe>
    </div>
    
    <!-- WordPress-style help sidebar -->
    <div class="ai-news-dashboard-sidebar">
        <div class="ai-news-sidebar-widget">
            <h3><?php _e('Quick Stats', 'contentpilot'); ?></h3>
            <div class="ai-news-quick-stats">
                <?php
                $post_counts = wp_count_posts();
                $comment_counts = wp_count_comments();
                ?>
                <div class="ai-news-stat">
                    <span class="ai-news-stat-number"><?php echo number_format_i18n($post_counts->publish); ?></span>
                    <span class="ai-news-stat-label"><?php _e('Published Posts', 'contentpilot'); ?></span>
                </div>
                <div class="ai-news-stat">
                    <span class="ai-news-stat-number"><?php echo number_format_i18n($comment_counts->approved); ?></span>
                    <span class="ai-news-stat-label"><?php _e('Comments', 'contentpilot'); ?></span>
                </div>
                <div class="ai-news-stat">
                    <span class="ai-news-stat-number" id="ai-news-api-requests">-</span>
                    <span class="ai-news-stat-label"><?php _e('API Requests Today', 'contentpilot'); ?></span>
                </div>
            </div>
        </div>
        
        <div class="ai-news-sidebar-widget">
            <h3><?php _e('Recent Activity', 'contentpilot'); ?></h3>
            <div class="ai-news-recent-activity" id="ai-news-recent-activity">
                <!-- Activity will be loaded via AJAX -->
                <div class="ai-news-activity-loading">
                    <span class="dashicons dashicons-update spin"></span>
                    <?php _e('Loading activity...', 'contentpilot'); ?>
                </div>
            </div>
        </div>
        
        <div class="ai-news-sidebar-widget">
            <h3><?php _e('Quick Actions', 'contentpilot'); ?></h3>
            <div class="ai-news-quick-actions-list">
                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=post&ai_generated=true')); ?>" class="ai-news-quick-action">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php _e('Generate New Post', 'contentpilot'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=ai-news-seo-compliance')); ?>" class="ai-news-quick-action">
                    <span class="dashicons dashicons-search"></span>
                    <?php _e('SEO Audit', 'contentpilot'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=ai-news-api-docs')); ?>" class="ai-news-quick-action">
                    <span class="dashicons dashicons-networking"></span>
                    <?php _e('API Documentation', 'contentpilot'); ?>
                </a>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Performance dashboard section
 */
function ai_news_dashboard_performance() {
    ?>
    <div class="ai-news-performance-section">
        <h2><?php _e('Performance Monitoring', 'contentpilot'); ?></h2>
        
        <div class="ai-news-performance-widgets">
            <!-- Performance metrics widgets will be loaded via AJAX -->
            <div class="ai-news-performance-loading">
                <span class="dashicons dashicons-update spin"></span>
                <?php _e('Loading performance data...', 'contentpilot'); ?>
            </div>
        </div>
        
        <div class="ai-news-performance-actions">
            <button type="button" class="button button-primary" id="ai-news-start-monitoring">
                <span class="dashicons dashicons-play"></span>
                <?php _e('Start Monitoring', 'contentpilot'); ?>
            </button>
            <button type="button" class="button button-secondary" id="ai-news-export-performance-report">
                <span class="dashicons dashicons-download"></span>
                <?php _e('Export Report', 'contentpilot'); ?>
            </button>
        </div>
    </div>
    <?php
}

/**
 * Content dashboard section
 */
function ai_news_dashboard_content() {
    ?>
    <div class="ai-news-content-section">
        <h2><?php _e('Content Management', 'contentpilot'); ?></h2>
        
        <!-- Content management tools -->
        <div class="ai-news-content-tools">
            <div class="ai-news-tool-group">
                <h3><?php _e('AI Content Generation', 'contentpilot'); ?></h3>
                <p><?php _e('Generate high-quality content using AI technology', 'contentpilot'); ?></p>
                <button type="button" class="button button-primary" id="ai-news-generate-content">
                    <span class="dashicons dashicons-magic"></span>
                    <?php _e('Generate Content', 'contentpilot'); ?>
                </button>
            </div>
            
            <div class="ai-news-tool-group">
                <h3><?php _e('Content Optimization', 'contentpilot'); ?></h3>
                <p><?php _e('Optimize existing content for better performance', 'contentpilot'); ?></p>
                <button type="button" class="button button-secondary" id="ai-news-optimize-content">
                    <span class="dashicons dashicons-admin-tools"></span>
                    <?php _e('Optimize Content', 'contentpilot'); ?>
                </button>
            </div>
        </div>
        
        <!-- Content statistics -->
        <div class="ai-news-content-stats">
            <?php
            $post_counts = wp_count_posts();
            $recent_posts = get_posts([
                'numberposts' => 10,
                'post_status' => 'publish',
                'orderby' => 'date',
                'order' => 'DESC'
            ]);
            ?>
            
            <div class="ai-news-stats-grid">
                <div class="ai-news-stat-card">
                    <h4><?php _e('Total Content', 'contentpilot'); ?></h4>
                    <div class="ai-news-stat-value"><?php echo number_format_i18n($post_counts->publish); ?></div>
                </div>
                <div class="ai-news-stat-card">
                    <h4><?php _e('AI Generated', 'contentpilot'); ?></h4>
                    <div class="ai-news-stat-value" id="ai-news-ai-generated-count">-</div>
                </div>
                <div class="ai-news-stat-card">
                    <h4><?php _e('Scheduled', 'contentpilot'); ?></h4>
                    <div class="ai-news-stat-value"><?php echo number_format_i18n($post_counts->future); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Recent content table -->
        <div class="ai-news-recent-content">
            <h3><?php _e('Recent Content', 'contentpilot'); ?></h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Title', 'contentpilot'); ?></th>
                        <th><?php _e('Author', 'contentpilot'); ?></th>
                        <th><?php _e('Date', 'contentpilot'); ?></th>
                        <th><?php _e('Views', 'contentpilot'); ?></th>
                        <th><?php _e('SEO Score', 'contentpilot'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_posts as $post): ?>
                    <tr>
                        <td>
                            <a href="<?php echo get_edit_post_link($post->ID); ?>">
                                <?php echo esc_html($post->post_title); ?>
                            </a>
                            <?php if (get_post_meta($post->ID, '_ai_news_generated', true)): ?>
                                <span class="ai-news-ai-badge" title="<?php _e('AI Generated', 'ai-auto-news-poster'); ?>">🤖</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo get_the_author_meta('display_name', $post->post_author); ?></td>
                        <td><?php echo get_the_date('', $post->ID); ?></td>
                        <td>
                            <?php
                            $views = get_post_meta($post->ID, 'post_views_count', true) ?: 0;
                            echo number_format_i18n($views);
                            ?>
                        </td>
                        <td>
                            <?php
                            $seo_score = get_post_meta($post->ID, '_ai_news_seo_score', true);
                            if ($seo_score) {
                                echo '<span class="ai-news-seo-score score-' . ($seo_score >= 80 ? 'good' : ($seo_score >= 60 ? 'warning' : 'poor')) . '">' . $seo_score . '</span>';
                            } else {
                                echo '<span class="ai-news-no-score">-</span>';
                            }
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}

/**
 * SEO dashboard section
 */
function ai_news_dashboard_seo() {
    ?>
    <div class="ai-news-seo-section">
        <h2><?php _e('SEO & EEAT Compliance', 'contentpilot'); ?></h2>
        
        <!-- SEO iframe for compliance dashboard -->
        <iframe 
            src="<?php echo esc_url(plugin_dir_url(__FILE__) . 'seo-compliance.html'); ?>" 
            class="ai-news-seo-iframe"
            width="100%" 
            height="800"
            frameborder="0">
        </iframe>
    </div>
    <?php
}

/**
 * API dashboard section
 */
function ai_news_dashboard_api() {
    ?>
    <div class="ai-news-api-section">
        <h2><?php _e('API Platform Management', 'contentpilot'); ?></h2>
        
        <!-- API endpoints -->
        <div class="ai-news-api-endpoints">
            <h3><?php _e('Available Endpoints', 'contentpilot'); ?></h3>
            
            <div class="ai-news-endpoint-grid">
                <div class="ai-news-endpoint-card">
                    <h4><?php _e('REST API', 'contentpilot'); ?></h4>
                    <p><?php _e('Complete REST API for content management', 'contentpilot'); ?></p>
                    <div class="ai-news-endpoint-url"><?php echo esc_html(rest_url('ai-auto-news/v1/')); ?></div>
                    <div class="ai-news-endpoint-status active"><?php _e('Active', 'contentpilot'); ?></div>
                </div>
                
                <div class="ai-news-endpoint-card">
                    <h4><?php _e('GraphQL API', 'contentpilot'); ?></h4>
                    <p><?php _e('GraphQL endpoint for complex queries', 'contentpilot'); ?></p>
                    <div class="ai-news-endpoint-url"><?php echo esc_html(rest_url('ai-auto-news/graphql')); ?></div>
                    <div class="ai-news-endpoint-status active"><?php _e('Active', 'contentpilot'); ?></div>
                </div>
                
                <div class="ai-news-endpoint-card">
                    <h4><?php _e('Webhooks', 'contentpilot'); ?></h4>
                    <p><?php _e('Real-time event notifications', 'contentpilot'); ?></p>
                    <div class="ai-news-endpoint-url"><?php _e('Configured via Dashboard', 'contentpilot'); ?></div>
                    <div class="ai-news-endpoint-status active"><?php _e('Active', 'contentpilot'); ?></div>
                </div>
            </div>
        </div>
        
        <!-- API usage statistics -->
        <div class="ai-news-api-stats">
            <h3><?php _e('API Usage Analytics', 'contentpilot'); ?></h3>
            
            <div class="ai-news-api-stats-grid">
                <div class="ai-news-stat-card">
                    <h4><?php _e('Requests Today', 'contentpilot'); ?></h4>
                    <div class="ai-news-stat-value" id="ai-news-requests-today">-</div>
                </div>
                <div class="ai-news-stat-card">
                    <h4><?php _e('Success Rate', 'contentpilot'); ?></h4>
                    <div class="ai-news-stat-value" id="ai-news-success-rate">-</div>
                </div>
                <div class="ai-news-stat-card">
                    <h4><?php _e('Average Response Time', 'contentpilot'); ?></h4>
                    <div class="ai-news-stat-value" id="ai-news-avg-response">-</div>
                </div>
            </div>
        </div>
        
        <!-- API management actions -->
        <div class="ai-news-api-actions">
            <button type="button" class="button button-primary" id="contentpilot-generate-api-key">
                <span class="dashicons dashicons-admin-network"></span>
                <?php _e('Generate API Key', 'contentpilot'); ?>
            </button>
            <a href="<?php echo esc_url(admin_url('admin.php?page=ai-news-api-docs')); ?>" class="button button-secondary">
                <span class="dashicons dashicons-book-alt"></span>
                <?php _e('API Documentation', 'contentpilot'); ?>
            </a>
            <button type="button" class="button button-secondary" id="ai-news-view-api-logs">
                <span class="dashicons dashicons-list-view"></span>
                <?php _e('View Logs', 'contentpilot'); ?>
            </button>
        </div>
    </div>
    <?php
}

/**
 * Settings dashboard section
 */
function ai_news_dashboard_settings() {
    // Redirect to regular settings page for now
    wp_redirect(admin_url('admin.php?page=ai-news-settings'));
    exit;
}

/**
 * Add dashboard to WordPress admin menu
 */
function ai_news_dashboard_admin_menu() {
    // Add main dashboard page
    add_menu_page(
        __('ContentPilot', 'contentpilot'),
        __('ContentPilot', 'contentpilot'),
        'manage_options',
        'ai-news-dashboard',
        'ai_news_dashboard_page',
        'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>'),
        30
    );
    
    // Add dashboard submenu
    add_submenu_page(
        'contentpilot-dashboard',
        __('Dashboard Overview', 'contentpilot'),
        __('Dashboard', 'contentpilot'),
        'manage_options',
        'ai-news-dashboard',
        'ai_news_dashboard_page'
    );
}
add_action('admin_menu', 'ai_news_dashboard_admin_menu');

/**
 * Enqueue dashboard assets
 */
function ai_news_dashboard_admin_enqueue_scripts($hook) {
    // Only load on our dashboard page
    if ($hook !== 'contentpilot_page_contentpilot-dashboard') {
        return;
    }
    
    // Enqueue styles and scripts
    wp_enqueue_style('contentpilot-dashboard-admin', plugin_dir_url(__FILE__) . 'assets/css/dashboard-admin.css', [], '2.0.0');
    wp_enqueue_script('contentpilot-dashboard-admin', plugin_dir_url(__FILE__) . 'assets/js/dashboard-admin.js', ['jquery'], '2.0.0', true);
}
add_action('admin_enqueue_scripts', 'ai_news_dashboard_admin_enqueue_scripts');

/**
 * Add custom CSS for dashboard integration
 */
function ai_news_dashboard_custom_css() {
    global $pagenow;
    
    // Only add on our dashboard page
    if ($pagenow !== 'admin.php' || !isset($_GET['page']) || $_GET['page'] !== 'ai-news-dashboard') {
        return;
    }
    ?>
    <style type="text/css">
        /* Hide WordPress admin elements that conflict with modern dashboard */
        #wpadminbar,
        #adminmenu,
        #wpfooter,
        #screen-options-wrap,
        .update-nag,
        .notice.notice-warning,
        .notice.notice-info:not(.ai-news-notice) {
            display: none !important;
        }
        
        /* Adjust layout for full-width dashboard */
        #wpcontent {
            margin-left: 0;
        }
        
        #wpbody-content {
            padding: 0;
        }
        
        /* Modern dashboard styling */
        .ai-news-dashboard-wrap {
            background: #f1f1f1;
            margin: 0;
            padding: 0;
        }
        
        .ai-news-dashboard-header {
            background: #fff;
            border-bottom: 1px solid #ccd0d4;
            padding: 20px;
        }
        
        .ai-news-dashboard-iframe,
        .ai-news-seo-iframe {
            width: 100%;
            border: none;
            background: #fff;
        }
        
        /* WordPress-style widgets */
        .ai-news-sidebar-widget {
            background: #fff;
            border: 1px solid #ccd0d4;
            margin-bottom: 20px;
            padding: 20px;
        }
        
        .ai-news-sidebar-widget h3 {
            margin: 0 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .ai-news-quick-stats {
            display: grid;
            gap: 15px;
        }
        
        .ai-news-stat {
            text-align: center;
        }
        
        .ai-news-stat-number {
            display: block;
            font-size: 24px;
            font-weight: bold;
            color: #0073aa;
        }
        
        .ai-news-stat-label {
            display: block;
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        /* Loading states */
        .ai-news-activity-loading,
        .ai-news-performance-loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .spin {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        /* Responsive adjustments */
        @media (max-width: 782px) {
            .ai-news-dashboard-content {
                padding: 10px;
            }
            
            .ai-news-sidebar-widget {
                margin-bottom: 10px;
                padding: 15px;
            }
        }
        
        /* Dark theme support */
        @media (prefers-color-scheme: dark) {
            .ai-news-dashboard-wrap {
                background: #1e1e1e;
                color: #fff;
            }
            
            .ai-news-dashboard-header,
            .ai-news-sidebar-widget {
                background: #2c2c2c;
                border-color: #444;
                color: #fff;
            }
            
            .ai-news-sidebar-widget h3 {
                border-bottom-color: #444;
            }
        }
    </style>
    <?php
}
add_action('admin_head', 'ai_news_dashboard_custom_css');

/**
 * AJAX handler for dashboard data
 */
function ai_news_dashboard_ajax_handler() {
    $action = sanitize_text_field($_POST['dashboard_action'] ?? '');
    
    switch ($action) {
        case 'get_stats':
            ai_news_dashboard_get_stats();
            break;
        case 'get_activity':
            ai_news_dashboard_get_activity();
            break;
        case 'get_api_stats':
            ai_news_dashboard_get_api_stats();
            break;
        default:
            wp_send_json_error('Invalid action');
    }
}
add_action('wp_ajax_ai_news_dashboard_data', 'ai_news_dashboard_ajax_handler');

/**
 * Get dashboard statistics
 */
function ai_news_dashboard_get_stats() {
    // Get AI generated content count
    global $wpdb;
    $ai_generated_count = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->postmeta} 
         WHERE meta_key = '_ai_news_generated' 
         AND meta_value = '1'"
    );
    
    // Get API request count for today
    $api_requests_today = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}ai_news_api_logs 
         WHERE DATE(timestamp) = %s",
        current_time('Y-m-d')
    ));
    
    wp_send_json_success([
        'ai_generated' => (int) $ai_generated_count,
        'api_requests_today' => (int) $api_requests_today
    ]);
}

/**
 * Get recent activity
 */
function ai_news_dashboard_get_activity() {
    // Get recent posts
    $recent_posts = get_posts([
        'numberposts' => 5,
        'post_status' => 'publish',
        'orderby' => 'date',
        'order' => 'DESC'
    ]);
    
    $activities = [];
    
    foreach ($recent_posts as $post) {
        $activities[] = [
            'type' => 'post_published',
            'title' => sprintf(__('Published: %s', 'ai-auto-news-poster'), $post->post_title),
            'time' => get_the_time('U', $post->ID),
            'link' => get_permalink($post->ID)
        ];
    }
    
    wp_send_json_success($activities);
}

/**
 * Get API statistics
 */
function ai_news_dashboard_get_api_stats() {
    global $wpdb;
    
    $today = current_time('Y-m-d');
    
    // Get request count for today
    $requests_today = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}ai_news_api_logs 
         WHERE DATE(timestamp) = %s",
        $today
    ));
    
    // Get success rate
    $total_requests = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}ai_news_api_logs 
         WHERE DATE(timestamp) = %s",
        $today
    ));
    
    $successful_requests = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}ai_news_api_logs 
         WHERE DATE(timestamp) = %s AND status_code < 400",
        $today
    ));
    
    $success_rate = $total_requests > 0 ? ($successful_requests / $total_requests) * 100 : 0;
    
    // Get average response time
    $avg_response_time = $wpdb->get_var($wpdb->prepare(
        "SELECT AVG(response_time) FROM {$wpdb->prefix}ai_news_api_logs 
         WHERE DATE(timestamp) = %s",
        $today
    ));
    
    wp_send_json_success([
        'requests_today' => (int) $requests_today,
        'success_rate' => round($success_rate, 1),
        'avg_response_time' => round($avg_response_time, 2)
    ]);
}
?>