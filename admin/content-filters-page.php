<?php
/**
 * Content Filtering Admin Page
 *
 * Provides comprehensive interface for managing RSS content filtering,
 * niche bundles, and live preview functionality.
 *
 * @package AI_Auto_News_Poster\Admin
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Content Filtering Admin Page Class
 */
class AANP_ContentFilterAdmin {
    
    /**
     * Content Filter Manager instance
     * @var AANP_ContentFilterManager
     */
    private $filter_manager;
    
    /**
     * RSS Feed Manager instance
     * @var AANP_RSSFeedManager
     */
    private $rss_manager;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_aanp_get_live_preview', array($this, 'ajax_get_live_preview'));
        add_action('wp_ajax_aanp_save_filter_preset', array($this, 'ajax_save_filter_preset'));
        add_action('wp_ajax_aanp_load_filter_preset', array($this, 'ajax_load_filter_preset'));
        add_action('wp_ajax_aanp_test_filtering', array($this, 'ajax_test_filtering'));
    }
    
    /**
     * Add admin menu page
     */
    public function add_admin_menu() {
        add_submenu_page(
            'aanp-dashboard',
            'Content Focus & Filters',
            'Content Focus & Filters',
            'manage_options',
            'aanp-content-filters',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'ai-auto-news-poster_page_aanp-content-filters') {
            return;
        }
        
        wp_enqueue_script(
            'aanp-content-filters-admin',
            AANP_PLUGIN_URL . 'assets/js/content-filters-admin.js',
            array('jquery', 'wp-api'),
            AANP_PLUGIN_VERSION,
            true
        );
        
        wp_localize_script('aanp-content-filters-admin', 'aanpFilterAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aanp_filter_nonce'),
            'strings' => array(
                'loading' => __('Loading...', 'ai-auto-news-poster'),
                'error' => __('Error occurred', 'ai-auto-news-poster'),
                'success' => __('Success!', 'ai-auto-news-poster'),
                'confirm_reset' => __('Are you sure you want to reset filters?', 'ai-auto-news-poster'),
                'no_results' => __('No content matches your filters', 'ai-auto-news-poster'),
                'preview_loading' => __('Generating preview...', 'ai-auto-news-poster')
            )
        ));
        
        wp_enqueue_style(
            'aanp-content-filters-admin',
            AANP_PLUGIN_URL . 'assets/css/content-filters-admin.css',
            array(),
            AANP_PLUGIN_VERSION
        );
    }
    
    /**
     * Admin page content
     */
    public function admin_page() {
        // Initialize managers
        $this->filter_manager = new AANP_ContentFilterManager();
        $this->rss_manager = new AANP_RSSFeedManager();
        
        // Get current user filters and available bundles
        $current_filters = $this->filter_manager->get_current_user_filters();
        $available_bundles = $this->filter_manager->get_available_bundles();
        $filter_stats = $this->filter_manager->get_filter_statistics();
        
        // Get currently enabled feeds
        $enabled_feeds = $this->rss_manager->get_feeds(array('enabled' => true));
        
        ?>
        <div class="wrap aanp-content-filters-page">
            <h1 class="wp-heading-inline">
                <span class="dashicons dashicons-filter"></span>
                Content Focus & Filters
            </h1>
            <p class="description">Manage RSS content filtering with smart bundles and live preview</p>
            
            <div class="aanp-filter-dashboard">
                <!-- Filter Statistics -->
                <div class="aanp-filter-stats">
                    <div class="aanp-stat-card">
                        <div class="aanp-stat-number"><?php echo esc_html($filter_stats['total_bundles']); ?></div>
                        <div class="aanp-stat-label">Available Bundles</div>
                    </div>
                    <div class="aanp-stat-card">
                        <div class="aanp-stat-number"><?php echo esc_html(count($enabled_feeds)); ?></div>
                        <div class="aanp-stat-label">Active Feeds</div>
                    </div>
                    <div class="aanp-stat-card">
                        <div class="aanp-stat-number"><?php echo esc_html($filter_stats['user_presets']); ?></div>
                        <div class="aanp-stat-label">Saved Presets</div>
                    </div>
                </div>
                
                <!-- Main Content -->
                <div class="aanp-filter-main">
                    <!-- Quick Bundle Selector -->
                    <div class="aanp-filter-section">
                        <h2><span class="dashicons dashicons-admin-plugins"></span> Content Bundle</h2>
                        <div class="aanp-bundle-selector">
                            <div class="aanp-bundle-dropdown">
                                <label for="bundle-selector">Select Content Bundle:</label>
                                <select id="bundle-selector" name="bundle_selector">
                                    <option value="">-- Choose a bundle --</option>
                                    <?php foreach ($available_bundles as $slug => $bundle): ?>
                                        <option value="<?php echo esc_attr($slug); ?>" 
                                                data-feeds="<?php echo esc_attr(count($bundle['enabled_feeds'])); ?>"
                                                data-default="<?php echo $bundle['is_default'] ? 'true' : 'false'; ?>">
                                            <?php echo esc_html($bundle['name']); ?>
                                            <?php if ($bundle['is_default']): ?>
                                                <span class="aanp-default-badge">Default</span>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="aanp-bundle-actions">
                                <button type="button" id="apply-bundle" class="button button-primary" disabled>
                                    Apply Bundle
                                </button>
                                <button type="button" id="preview-bundle" class="button" disabled>
                                    Preview Results
                                </button>
                            </div>
                        </div>
                        
                        <div id="bundle-info" class="aanp-bundle-info" style="display: none;">
                            <h3 id="bundle-name"></h3>
                            <p id="bundle-description"></p>
                            <div class="aanp-bundle-details">
                                <span id="bundle-feeds-count"></span>
                                <span id="bundle-keywords"></span>
                                <span id="bundle-categories"></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Keyword Engine -->
                    <div class="aanp-filter-section">
                        <h2><span class="dashicons dashicons-search"></span> Keyword Engine</h2>
                        <div class="aanp-keyword-engine">
                            <div class="aanp-keyword-inputs">
                                <div class="aanp-keyword-group">
                                    <label for="positive-keywords">Positive Keywords (required):</label>
                                    <textarea id="positive-keywords" 
                                              placeholder="news, breaking, latest, technology, health"
                                              rows="3"><?php echo esc_textarea($current_filters['positive_keywords'] ?? ''); ?></textarea>
                                    <small>Content MUST contain at least one of these keywords</small>
                                </div>
                                
                                <div class="aanp-keyword-group">
                                    <label for="negative-keywords">Negative Keywords (excluded):</label>
                                    <textarea id="negative-keywords" 
                                              placeholder="-politics, -celebrity, -sports commentary"
                                              rows="3"><?php echo esc_textarea($current_filters['negative_keywords'] ?? ''); ?></textarea>
                                    <small>Content will be EXCLUDED if it contains these keywords (prefix with -)</small>
                                </div>
                            </div>
                            
                            <div class="aanp-keyword-presets">
                                <h3>Saved Presets</h3>
                                <div class="aanp-preset-actions">
                                    <input type="text" id="preset-name" placeholder="Preset name">
                                    <button type="button" id="save-preset" class="button">Save Preset</button>
                                </div>
                                <div id="preset-list" class="aanp-preset-list">
                                    <!-- Presets will be loaded via AJAX -->
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Advanced Filters -->
                    <div class="aanp-filter-section">
                        <h2><span class="dashicons dashicons-admin-settings"></span> Advanced Filters</h2>
                        <div class="aanp-advanced-filters">
                            <div class="aanp-filter-row">
                                <div class="aanp-filter-field">
                                    <label for="content-age-limit">Content Age Limit (days):</label>
                                    <select id="content-age-limit">
                                        <option value="1">1 day (Real-time)</option>
                                        <option value="7" selected>7 days (Recent)</option>
                                        <option value="30">30 days (Monthly)</option>
                                        <option value="90">90 days (Quarterly)</option>
                                        <option value="365">1 year (Archive)</option>
                                    </select>
                                </div>
                                
                                <div class="aanp-filter-field">
                                    <label for="priority-regions">Priority Regions:</label>
                                    <select id="priority-regions" multiple>
                                        <option value="UK" selected>United Kingdom</option>
                                        <option value="USA" selected>United States</option>
                                        <option value="EU" selected>European Union</option>
                                        <option value="AU">Australia</option>
                                        <option value="CA">Canada</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="aanp-filter-row">
                                <div class="aanp-filter-field">
                                    <label for="language-priority">Language Priority:</label>
                                    <select id="language-priority">
                                        <option value="en" selected>English (default)</option>
                                        <option value="balanced">Balanced</option>
                                        <option value="local">Prefer Local Languages</option>
                                    </select>
                                </div>
                                
                                <div class="aanp-filter-field">
                                    <label for="quality-threshold">Minimum Quality Score:</label>
                                    <input type="range" id="quality-threshold" min="0" max="100" value="70">
                                    <span id="quality-value">70</span>
                                </div>
                            </div>
                            
                            <div class="aanp-filter-options">
                                <label class="aanp-checkbox-label">
                                    <input type="checkbox" id="duplicate-detection" checked>
                                    <span class="aanp-checkmark"></span>
                                    Enable duplicate detection
                                </label>
                                
                                <label class="aanp-checkbox-label">
                                    <input type="checkbox" id="region-bias" checked>
                                    <span class="aanp-checkmark"></span>
                                    Apply region bias to filtering
                                </label>
                                
                                <label class="aanp-checkbox-label">
                                    <input type="checkbox" id="auto-categorization" checked>
                                    <span class="aanp-checkmark"></span>
                                    Auto-categorize filtered content
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Live Preview -->
                    <div class="aanp-filter-section">
                        <h2><span class="dashicons dashicons-visibility"></span> Live Preview</h2>
                        <div class="aanp-preview-controls">
                            <button type="button" id="refresh-preview" class="button button-secondary">
                                <span class="dashicons dashicons-update"></span>
                                Refresh Preview
                            </button>
                            <div class="aanp-preview-stats">
                                <span id="preview-stats">Ready to preview filtering results</span>
                            </div>
                        </div>
                        
                        <div id="preview-results" class="aanp-preview-results">
                            <div class="aanp-preview-placeholder">
                                <span class="dashicons dashicons-media-default"></span>
                                <p>Click "Refresh Preview" to see how your filters will affect content</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Sidebar -->
                <div class="aanp-filter-sidebar">
                    <!-- Current Status -->
                    <div class="aanp-sidebar-section">
                        <h3>Current Status</h3>
                        <div class="aanp-status-info">
                            <div class="aanp-status-item">
                                <strong>Active Bundle:</strong>
                                <span id="current-bundle"><?php echo esc_html($current_filters['bundle_slug'] ?? 'None'); ?></span>
                            </div>
                            <div class="aanp-status-item">
                                <strong>Filters Applied:</strong>
                                <span id="filters-status"><?php echo $current_filters ? 'Yes' : 'No'; ?></span>
                            </div>
                            <div class="aanp-status-item">
                                <strong>Last Updated:</strong>
                                <span id="last-updated"><?php echo esc_html($current_filters['updated_at'] ?? 'Never'); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="aanp-sidebar-section">
                        <h3>Quick Actions</h3>
                        <div class="aanp-quick-actions">
                            <button type="button" id="reset-filters" class="button button-secondary">
                                Reset to Default
                            </button>
                            <button type="button" id="export-filters" class="button">
                                Export Settings
                            </button>
                            <button type="button" id="import-filters" class="button">
                                Import Settings
                            </button>
                        </div>
                    </div>
                    
                    <!-- Bundle Recommendations -->
                    <div class="aanp-sidebar-section">
                        <h3>Recommended Bundles</h3>
                        <div id="bundle-recommendations" class="aanp-recommendations">
                            <!-- Recommendations will be loaded via AJAX -->
                        </div>
                    </div>
                    
                    <!-- Help -->
                    <div class="aanp-sidebar-section">
                        <h3>Help & Tips</h3>
                        <div class="aanp-help-content">
                            <details>
                                <summary>How to use bundles?</summary>
                                <p>Choose a pre-configured bundle for instant filtering, or customize your own keywords.</p>
                            </details>
                            <details>
                                <summary>Keyword tips?</summary>
                                <p>Use positive keywords for required content, negative keywords (prefix with -) to exclude unwanted content.</p>
                            </details>
                            <details>
                                <summary>Live preview?</summary>
                                <p>See exactly which articles will pass your filters before applying them.</p>
                            </details>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Modal for import/export -->
        <div id="aanp-import-export-modal" class="aanp-modal" style="display: none;">
            <div class="aanp-modal-content">
                <div class="aanp-modal-header">
                    <h3 id="modal-title">Import/Export Settings</h3>
                    <button type="button" class="aanp-modal-close">&times;</button>
                </div>
                <div class="aanp-modal-body">
                    <div id="export-section" style="display: none;">
                        <h4>Export Settings</h4>
                        <textarea id="export-data" readonly rows="10"></textarea>
                        <button type="button" id="copy-export" class="button">Copy to Clipboard</button>
                    </div>
                    <div id="import-section" style="display: none;">
                        <h4>Import Settings</h4>
                        <textarea id="import-data" placeholder="Paste exported settings here..." rows="10"></textarea>
                        <button type="button" id="confirm-import" class="button button-primary">Import Settings</button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX handler for getting live preview
     */
    public function ajax_get_live_preview() {
        check_ajax_referer('aanp_filter_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        try {
            // Get filter settings from AJAX request
            $filters = array(
                'positive_keywords' => sanitize_textarea_field($_POST['positive_keywords'] ?? ''),
                'negative_keywords' => sanitize_textarea_field($_POST['negative_keywords'] ?? ''),
                'content_age_limit' => intval($_POST['content_age_limit'] ?? 7),
                'priority_regions' => sanitize_text_field($_POST['priority_regions'] ?? 'UK,USA,EU')
            );
            
            // Get sample feed data for preview
            $this->rss_manager = new AANP_RSSFeedManager();
            $enabled_feeds = $this->rss_manager->get_feeds(array('enabled' => true, 'limit' => 3));
            
            $sample_data = array();
            foreach ($enabled_feeds as $feed) {
                // Create sample articles for preview
                $sample_data[] = array(
                    'title' => 'Sample Article from ' . $feed['name'],
                    'description' => 'This is a sample article description to demonstrate how filtering works with different keywords and criteria.',
                    'pub_date' => current_time('Y-m-d H:i:s'),
                    'link' => $feed['url'],
                    'source_url' => $feed['url'],
                    'timestamp' => time() - rand(3600, 86400 * 7) // Random timestamp within last week
                );
            }
            
            // Filter the sample data
            $this->filter_manager = new AANP_ContentFilterManager();
            $preview_results = $this->filter_manager->preview_filtering($sample_data, $filters);
            
            wp_send_json_success($preview_results);
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }
    
    /**
     * AJAX handler for saving filter preset
     */
    public function ajax_save_filter_preset() {
        check_ajax_referer('aanp_filter_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        try {
            $preset_name = sanitize_text_field($_POST['preset_name'] ?? '');
            $filters = array(
                'positive_keywords' => sanitize_textarea_field($_POST['positive_keywords'] ?? ''),
                'negative_keywords' => sanitize_textarea_field($_POST['negative_keywords'] ?? ''),
                'content_age_limit' => intval($_POST['content_age_limit'] ?? 7)
            );
            
            $this->filter_manager = new AANP_ContentFilterManager();
            $result = $this->filter_manager->save_user_preset($preset_name, $filters);
            
            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result);
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }
    
    /**
     * AJAX handler for loading filter preset
     */
    public function ajax_load_filter_preset() {
        check_ajax_referer('aanp_filter_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        try {
            $preset_name = sanitize_text_field($_POST['preset_name'] ?? '');
            
            $this->filter_manager = new AANP_ContentFilterManager();
            $result = $this->filter_manager->load_user_preset($preset_name);
            
            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result);
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }
    
    /**
     * AJAX handler for testing filtering
     */
    public function ajax_test_filtering() {
        check_ajax_referer('aanp_filter_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        try {
            $test_article = array(
                'title' => sanitize_text_field($_POST['test_title'] ?? ''),
                'description' => sanitize_textarea_field($_POST['test_description'] ?? ''),
                'timestamp' => time()
            );
            
            $filters = array(
                'positive_keywords' => sanitize_textarea_field($_POST['positive_keywords'] ?? ''),
                'negative_keywords' => sanitize_textarea_field($_POST['negative_keywords'] ?? ''),
                'content_age_limit' => intval($_POST['content_age_limit'] ?? 7)
            );
            
            $this->filter_manager = new AANP_ContentFilterManager();
            $result = $this->filter_manager->filter_article($test_article, $filters);
            
            wp_send_json_success($result);
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }
}

// Initialize the content filter admin page
new AANP_ContentFilterAdmin();