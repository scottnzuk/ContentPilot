<?php
/**
 * Content Verification Admin Page
 *
 * Provides comprehensive interface for managing content verification,
 * viewing verification results, and handling problematic content.
 *
 * @package AI_Auto_News_Poster
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AANP_VerificationAdmin {
    
    private $verification_db;
    private $logger;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->verification_db = new AANP_VerificationDatabase();
        $this->logger = AANP_Logger::getInstance();
        
        // Initialize admin hooks
        add_action('admin_menu', array($this, 'add_verification_menu'));
        add_action('wp_ajax_aanp_verify_content', array($this, 'handle_verify_content'));
        add_action('wp_ajax_aanp_get_verification_stats', array($this, 'handle_get_verification_stats'));
        add_action('wp_ajax_aanp_flag_problematic_content', array($this, 'handle_flag_content'));
        
        $this->logger->debug('Verification admin initialized');
    }
    
    /**
     * Add verification admin menu
     */
    public function add_verification_menu() {
        // Add verification page as submenu of main AANP menu
        add_submenu_page(
            'ai-auto-news-poster',
            'Content Verification',
            'Content Verification',
            'manage_options',
            'aanp-verification',
            array($this, 'display_verification_page')
        );
        
        // Add verification settings page
        add_submenu_page(
            'aanp-verification',
            'Verification Settings',
            'Settings',
            'manage_options',
            'aanp-verification-settings',
            array($this, 'display_verification_settings')
        );
    }
    
    /**
     * Display main verification page
     */
    public function display_verification_page() {
        // Handle actions
        $this->handle_verification_actions();
        
        // Get verification statistics
        $stats = $this->verification_db->get_verification_stats(30);
        
        // Get recent verification records
        $recent_records = $this->verification_db->get_verification_records(array(
            'limit' => 20,
            'days' => 7
        ));
        
        // Get problematic domains
        $problematic_domains = $this->get_problematic_domains();
        
        ?>
        <div class="wrap aanp-verification-page">
            <h1 class="wp-heading-inline">
                <span class="aanp-icon" style="margin-right: 10px;">🛡️</span>
                Content Verification Dashboard
            </h1>
            <p class="description">Monitor and manage content verification status, source credibility, and problematic content alerts.</p>
            
            <!-- Verification Statistics Cards -->
            <div class="aanp-stats-cards" style="display: flex; gap: 20px; margin: 20px 0;">
                <div class="aanp-stat-card" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 8px; padding: 20px; flex: 1;">
                    <div class="aanp-stat-number" style="font-size: 28px; font-weight: bold; color: #1e7e34;">
                        <?php echo intval($stats['total_verifications'] ?? 0); ?>
                    </div>
                    <div class="aanp-stat-label" style="color: #6c757d;">Total Verifications (30 days)</div>
                </div>
                
                <div class="aanp-stat-card" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 8px; padding: 20px; flex: 1;">
                    <div class="aanp-stat-number" style="font-size: 28px; font-weight: bold; color: #28a745;">
                        <?php echo intval($stats['status_breakdown']['verified'] ?? 0); ?>
                    </div>
                    <div class="aanp-stat-label" style="color: #6c757d;">Verified Content</div>
                </div>
                
                <div class="aanp-stat-card" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 8px; padding: 20px; flex: 1;">
                    <div class="aanp-stat-number" style="font-size: 28px; font-weight: bold; color: #dc3545;">
                        <?php echo intval($stats['retraction_detected'] ?? 0); ?>
                    </div>
                    <div class="aanp-stat-label" style="color: #6c757d;">Retracted Content</div>
                </div>
                
                <div class="aanp-stat-card" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 8px; padding: 20px; flex: 1;">
                    <div class="aanp-stat-number" style="font-size: 28px; font-weight: bold; color: #ffc107;">
                        <?php echo intval(($stats['status_breakdown']['warning'] ?? 0) + ($stats['status_breakdown']['error'] ?? 0)); ?>
                    </div>
                    <div class="aanp-stat-label" style="color: #6c757d;">Content Issues</div>
                </div>
            </div>
            
            <div class="aanp-dashboard-content" style="display: flex; gap: 20px; margin-top: 20px;">
                
                <!-- Recent Verification Results -->
                <div class="aanp-dashboard-panel" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 8px; flex: 2;">
                    <div class="aanp-panel-header" style="border-bottom: 1px solid #ccd0d4; padding: 15px 20px;">
                        <h3 style="margin: 0;">Recent Verification Results</h3>
                    </div>
                    <div class="aanp-panel-content" style="padding: 20px;">
                        <?php if (!empty($recent_records)): ?>
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th>Source</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_records as $record): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo esc_html($record['source_domain'] ?? 'Unknown'); ?></strong>
                                                <br>
                                                <small style="color: #6c757d;">
                                                    <a href="<?php echo esc_url($record['original_url']); ?>" target="_blank">View Source</a>
                                                </small>
                                            </td>
                                            <td>
                                                <?php 
                                                $status_class = $this->get_status_css_class($record['verification_status']);
                                                $status_icon = $this->get_status_icon($record['verification_status']);
                                                ?>
                                                <span class="status-badge <?php echo $status_class; ?>" style="
                                                    padding: 4px 8px; 
                                                    border-radius: 12px; 
                                                    font-size: 12px; 
                                                    font-weight: 500;
                                                    background-color: <?php echo $this->get_status_bg_color($record['verification_status']); ?>;
                                                    color: <?php echo $this->get_status_text_color($record['verification_status']); ?>;
                                                ">
                                                    <?php echo $status_icon; ?> <?php echo esc_html(ucfirst($record['verification_status'])); ?>
                                                </span>
                                                <?php if ($record['retraction_detected']): ?>
                                                    <br><small style="color: #dc3545;">Retracted</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <time datetime="<?php echo esc_attr($record['verification_date']); ?>">
                                                    <?php echo esc_html(date('M j, Y g:i A', strtotime($record['verification_date']))); ?>
                                                </time>
                                            </td>
                                            <td>
                                                <button type="button" class="button button-small verify-content-btn" 
                                                        data-record-id="<?php echo esc_attr($record['id']); ?>"
                                                        data-url="<?php echo esc_attr($record['original_url']); ?>">
                                                    Re-verify
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p>No recent verification records found.</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Problematic Domains -->
                <div class="aanp-dashboard-panel" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 8px; flex: 1;">
                    <div class="aanp-panel-header" style="border-bottom: 1px solid #ccd0d4; padding: 15px 20px;">
                        <h3 style="margin: 0;">Problematic Sources</h3>
                    </div>
                    <div class="aanp-panel-content" style="padding: 20px;">
                        <?php if (!empty($problematic_domains)): ?>
                            <ul style="list-style: none; padding: 0; margin: 0;">
                                <?php foreach ($problematic_domains as $domain_data): ?>
                                    <li style="margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #f0f0f1;">
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <div>
                                                <strong><?php echo esc_html($domain_data['domain']); ?></strong>
                                                <br>
                                                <small style="color: #6c757d;">
                                                    Issues: <?php echo intval($domain_data['issues_count']); ?>
                                                    | Retraction rate: <?php echo number_format($domain_data['retraction_rate'] * 100, 1); ?>%
                                                </small>
                                            </div>
                                            <button type="button" class="button button-small flag-domain-btn" 
                                                    data-domain="<?php echo esc_attr($domain_data['domain']); ?>">
                                                Flag
                                            </button>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p>No problematic domains detected in the last 30 days.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Verification Controls -->
            <div class="aanp-controls-section" style="margin-top: 30px;">
                <div class="aanp-controls-header" style="margin-bottom: 15px;">
                    <h3>Verification Controls</h3>
                </div>
                <div class="aanp-controls-content" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 8px; padding: 20px;">
                    <form method="post" class="aanp-verify-form">
                        <?php wp_nonce_field('aanp_verify_content_action', 'aanp_verify_nonce'); ?>
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="verify_url">Verify URL</label>
                                </th>
                                <td>
                                    <input type="url" id="verify_url" name="verify_url" class="regular-text" 
                                           placeholder="Enter URL to verify..." style="width: 400px;" required>
                                    <button type="submit" class="button button-primary" name="action" value="verify_content">
                                        Verify Content
                                    </button>
                                </td>
                            </tr>
                        </table>
                    </form>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Handle verification buttons
            $('.verify-content-btn').on('click', function() {
                var recordId = $(this).data('record-id');
                var url = $(this).data('url');
                
                $.post(ajaxurl, {
                    action: 'aanp_verify_content',
                    record_id: recordId,
                    url: url,
                    nonce: '<?php echo wp_create_nonce('aanp_verify_content_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Verification failed: ' + (response.data || 'Unknown error'));
                    }
                });
            });
            
            // Handle domain flagging
            $('.flag-domain-btn').on('click', function() {
                var domain = $(this).data('domain');
                
                if (confirm('Flag domain "' + domain + '" as problematic?')) {
                    $.post(ajaxurl, {
                        action: 'aanp_flag_problematic_content',
                        domain: domain,
                        nonce: '<?php echo wp_create_nonce('aanp_flag_content_nonce'); ?>'
                    }, function(response) {
                        if (response.success) {
                            alert('Domain flagged successfully');
                            location.reload();
                        } else {
                            alert('Flagging failed: ' + (response.data || 'Unknown error'));
                        }
                    });
                }
            });
            
            // Form submission
            $('.aanp-verify-form').on('submit', function(e) {
                e.preventDefault();
                var url = $('#verify_url').val();
                
                $.post(ajaxurl, {
                    action: 'aanp_verify_content',
                    url: url,
                    nonce: '<?php echo wp_create_nonce('aanp_verify_content_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('Verification completed successfully');
                        location.reload();
                    } else {
                        alert('Verification failed: ' + (response.data || 'Unknown error'));
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Display verification settings page
     */
    public function display_verification_settings() {
        // Handle form submission
        if (isset($_POST['submit'])) {
            $this->handle_verification_settings_save();
        }
        
        $settings = $this->get_verification_settings();
        ?>
        <div class="wrap">
            <h1>Content Verification Settings</h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('aanp_verification_settings_action', 'aanp_settings_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="enable_verification">Enable Content Verification</label>
                        </th>
                        <td>
                            <input type="checkbox" id="enable_verification" name="enable_verification" value="1" 
                                   <?php checked($settings['enable_verification']); ?>>
                            <label for="enable_verification">Automatically verify content before processing</label>
                            <p class="description">When enabled, the system will check source URLs, detect retracted content, and verify legitimacy before generating posts.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="strictness_level">Verification Strictness</label>
                        </th>
                        <td>
                            <select id="strictness_level" name="strictness_level">
                                <option value="permissive" <?php selected($settings['strictness_level'], 'permissive'); ?>>
                                    Permissive - Only flag obvious issues
                                </option>
                                <option value="moderate" <?php selected($settings['strictness_level'], 'moderate'); ?>>
                                    Moderate - Balance between security and usability
                                </option>
                                <option value="conservative" <?php selected($settings['strictness_level'], 'conservative'); ?>>
                                    Conservative - Flag potential issues
                                </option>
                            </select>
                            <p class="description">Higher strictness will flag more content as potentially problematic.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="retraction_handling">Retracted Content Handling</label>
                        </th>
                        <td>
                            <select id="retraction_handling" name="retraction_handling">
                                <option value="skip" <?php selected($settings['retraction_handling'], 'skip'); ?>>
                                    Skip Processing - Don't generate posts from retracted content
                                </option>
                                <option value="flag" <?php selected($settings['retraction_handling'], 'flag'); ?>>
                                    Flag and Continue - Generate posts with warnings
                                </option>
                                <option value="warn" <?php selected($settings['retraction_handling'], 'warn'); ?>>
                                    Warn Only - Generate posts with retraction notices
                                </option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="verification_timeout">Verification Timeout</label>
                        </th>
                        <td>
                            <input type="number" id="verification_timeout" name="verification_timeout" 
                                   value="<?php echo esc_attr($settings['verification_timeout']); ?>" min="5" max="60">
                            <span>seconds</span>
                            <p class="description">Maximum time to spend verifying a single URL.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="enable_notifications">Email Notifications</label>
                        </th>
                        <td>
                            <input type="checkbox" id="enable_notifications" name="enable_notifications" value="1" 
                                   <?php checked($settings['enable_notifications']); ?>>
                            <label for="enable_notifications">Send email alerts for problematic content</label>
                            <p class="description">Receive notifications when retracted or problematic content is detected.</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <!-- Source Whitelist Management -->
            <div class="aanp-whitelist-section" style="margin-top: 40px;">
                <h2>Trusted Sources Whitelist</h2>
                <p class="description">Domains on this list will bypass verification checks.</p>
                
                <form method="post" class="aanp-add-source-form">
                    <?php wp_nonce_field('aanp_add_source_action', 'aanp_add_source_nonce'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="new_source_domain">Add Trusted Domain</label>
                            </th>
                            <td>
                                <input type="text" id="new_source_domain" name="new_source_domain" 
                                       class="regular-text" placeholder="example.com" required>
                                <input type="text" id="new_source_name" name="new_source_name" 
                                       class="regular-text" placeholder="Source Name" required style="margin-left: 10px;">
                                <input type="number" id="new_source_score" name="new_source_score" 
                                       value="95" min="0" max="100" style="width: 80px; margin-left: 10px;">
                                <span style="margin-left: 5px;">Credibility Score</span>
                                <button type="submit" class="button" name="action" value="add_source">Add Source</button>
                            </td>
                        </tr>
                    </table>
                </form>
                
                <?php $trusted_sources = $this->get_trusted_sources(); ?>
                <?php if (!empty($trusted_sources)): ?>
                    <h3>Current Trusted Sources</h3>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Domain</th>
                                <th>Source Name</th>
                                <th>Credibility Score</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($trusted_sources as $source): ?>
                                <tr>
                                    <td><strong><?php echo esc_html($source['domain']); ?></strong></td>
                                    <td><?php echo esc_html($source['source_name']); ?></td>
                                    <td><?php echo number_format($source['credibility_score'], 1); ?>%</td>
                                    <td>
                                        <span class="status-badge verified" style="padding: 4px 8px; background: #d4edda; color: #155724; border-radius: 12px; font-size: 12px;">
                                            ✅ Verified
                                        </span>
                                    </td>
                                    <td>
                                        <button type="button" class="button button-small remove-source-btn" 
                                                data-source-id="<?php echo esc_attr($source['id']); ?>">
                                            Remove
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Handle source removal
            $('.remove-source-btn').on('click', function() {
                var sourceId = $(this).data('source-id');
                
                if (confirm('Remove this source from the trusted list?')) {
                    $.post(ajaxurl, {
                        action: 'aanp_remove_trusted_source',
                        source_id: sourceId,
                        nonce: '<?php echo wp_create_nonce('aanp_remove_source_nonce'); ?>'
                    }, function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Failed to remove source: ' + (response.data || 'Unknown error'));
                        }
                    });
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Handle verification actions
     */
    private function handle_verification_actions() {
        if (!isset($_POST['action']) || !isset($_POST['aanp_verify_nonce'])) {
            return;
        }
        
        if (!wp_verify_nonce($_POST['aanp_verify_nonce'], 'aanp_verify_content_action')) {
            wp_die('Security check failed');
        }
        
        switch ($_POST['action']) {
            case 'verify_content':
                $url = esc_url_raw($_POST['verify_url'] ?? '');
                if (!empty($url)) {
                    $this->verify_single_url($url);
                }
                break;
                
            case 'add_source':
                $this->add_trusted_source();
                break;
        }
    }
    
    /**
     * Handle AJAX verification request
     */
    public function handle_verify_content() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'aanp_verify_content_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $url = esc_url_raw($_POST['url'] ?? '');
        if (empty($url)) {
            wp_send_json_error('URL is required');
        }
        
        try {
            $content_verifier = new AANP_ContentVerifier();
            $verification_result = $content_verifier->validate_source_url($url);
            
            if ($verification_result) {
                wp_send_json_success($verification_result);
            } else {
                wp_send_json_error('Verification failed');
            }
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Handle AJAX flag content request
     */
    public function handle_flag_content() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'aanp_flag_content_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $domain = sanitize_text_field($_POST['domain'] ?? '');
        if (empty($domain)) {
            wp_send_json_error('Domain is required');
        }
        
        try {
            $retracted_handler = new AANP_RetractedContentHandler();
            $flag_result = $retracted_handler->flag_problematic_content('', array('domain' => $domain));
            
            if ($flag_result['flagged']) {
                wp_send_json_success($flag_result);
            } else {
                wp_send_json_error('Failed to flag content');
            }
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Get verification settings
     */
    private function get_verification_settings() {
        $defaults = array(
            'enable_verification' => true,
            'strictness_level' => 'moderate',
            'retraction_handling' => 'skip',
            'verification_timeout' => 20,
            'enable_notifications' => true
        );
        
        $settings = get_option('aanp_verification_settings', $defaults);
        return wp_parse_args($settings, $defaults);
    }
    
    /**
     * Save verification settings
     */
    private function handle_verification_settings_save() {
        if (!wp_verify_nonce($_POST['aanp_settings_nonce'], 'aanp_verification_settings_action')) {
            wp_die('Security check failed');
        }
        
        $settings = array(
            'enable_verification' => !empty($_POST['enable_verification']),
            'strictness_level' => sanitize_text_field($_POST['strictness_level']),
            'retraction_handling' => sanitize_text_field($_POST['retraction_handling']),
            'verification_timeout' => intval($_POST['verification_timeout']),
            'enable_notifications' => !empty($_POST['enable_notifications'])
        );
        
        update_option('aanp_verification_settings', $settings);
        
        echo '<div class="notice notice-success"><p>Settings saved successfully.</p></div>';
    }
    
    /**
     * Get problematic domains
     */
    private function get_problematic_domains() {
        try {
            global $wpdb;
            
            $table_name = $wpdb->prefix . 'aanp_content_verification';
            
            return $wpdb->get_results("
                SELECT 
                    SUBSTRING_INDEX(SUBSTRING_INDEX(original_url, '/', 3), '://', -1) as domain,
                    COUNT(*) as issues_count,
                    AVG(CASE WHEN retraction_detected = 1 THEN 1 ELSE 0 END) as retraction_rate
                 FROM {$table_name} 
                 WHERE verification_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                 GROUP BY domain
                 HAVING issues_count > 2
                 ORDER BY retraction_rate DESC, issues_count DESC
                 LIMIT 10
            ", ARRAY_A);
            
        } catch (Exception $e) {
            $this->logger->error('Failed to get problematic domains', array(
                'error' => $e->getMessage()
            ));
            return array();
        }
    }
    
    /**
     * Get trusted sources
     */
    private function get_trusted_sources() {
        try {
            global $wpdb;
            
            $table_name = $wpdb->prefix . 'aanp_verified_sources';
            
            return $wpdb->get_results(
                "SELECT * FROM {$table_name} ORDER BY credibility_score DESC",
                ARRAY_A
            );
            
        } catch (Exception $e) {
            $this->logger->error('Failed to get trusted sources', array(
                'error' => $e->getMessage()
            ));
            return array();
        }
    }
    
    /**
     * Add trusted source
     */
    private function add_trusted_source() {
        if (!wp_verify_nonce($_POST['aanp_add_source_nonce'], 'aanp_add_source_action')) {
            wp_die('Security check failed');
        }
        
        $domain = sanitize_text_field($_POST['new_source_domain'] ?? '');
        $source_name = sanitize_text_field($_POST['new_source_name'] ?? '');
        $credibility_score = floatval($_POST['new_source_score'] ?? 95);
        
        if (empty($domain) || empty($source_name)) {
            echo '<div class="notice notice-error"><p>Domain and source name are required.</p></div>';
            return;
        }
        
        try {
            $verification_db = new AANP_VerificationDatabase();
            $result = $verification_db->update_source_credibility($domain, $credibility_score, 'verified', array(
                'added_via_admin' => true,
                'added_by' => get_current_user_id()
            ));
            
            if ($result) {
                echo '<div class="notice notice-success"><p>Trusted source added successfully.</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Failed to add trusted source.</p></div>';
            }
            
        } catch (Exception $e) {
            echo '<div class="notice notice-error"><p>Error: ' . esc_html($e->getMessage()) . '</p></div>';
        }
    }
    
    /**
     * Verify single URL
     */
    private function verify_single_url($url) {
        try {
            $content_verifier = new AANP_ContentVerifier();
            $result = $content_verifier->validate_source_url($url);
            
            if ($result['status'] === 'verified') {
                echo '<div class="notice notice-success"><p>URL verification successful. Content appears to be accessible and legitimate.</p></div>';
            } else {
                echo '<div class="notice notice-warning"><p>URL verification completed with warnings: ' . esc_html($result['error_message'] ?? 'Unknown issue') . '</p></div>';
            }
            
        } catch (Exception $e) {
            echo '<div class="notice notice-error"><p>Verification failed: ' . esc_html($e->getMessage()) . '</p></div>';
        }
    }
    
    /**
     * Get status CSS class
     */
    private function get_status_css_class($status) {
        switch ($status) {
            case 'verified':
                return 'verified';
            case 'warning':
                return 'warning';
            case 'error':
                return 'error';
            default:
                return 'unknown';
        }
    }
    
    /**
     * Get status icon
     */
    private function get_status_icon($status) {
        switch ($status) {
            case 'verified':
                return '✅';
            case 'warning':
                return '⚠️';
            case 'error':
                return '❌';
            default:
                return '❓';
        }
    }
    
    /**
     * Get status background color
     */
    private function get_status_bg_color($status) {
        switch ($status) {
            case 'verified':
                return '#d4edda';
            case 'warning':
                return '#fff3cd';
            case 'error':
                return '#f8d7da';
            default:
                return '#e2e3e5';
        }
    }
    
    /**
     * Get status text color
     */
    private function get_status_text_color($status) {
        switch ($status) {
            case 'verified':
                return '#155724';
            case 'warning':
                return '#856404';
            case 'error':
                return '#721c24';
            default:
                return '#383d41';
        }
    }
}

// Initialize verification admin
new AANP_VerificationAdmin();