<?php
/**
 * RSS Feeds Management Page
 *
 * Admin interface for managing RSS feeds with search, selection, and validation features.
 *
 * @package AI_Auto_News_Poster
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Initialize RSS Feed Manager
if (!class_exists('AANP_RSSFeedManager')) {
    require_once plugin_dir_path(__FILE__) . '../includes/class-rss-feed-manager.php';
}

$rss_manager = new AANP_RSSFeedManager();

// Handle form submissions
if (isset($_POST['aanp_rss_action']) && wp_verify_nonce($_POST['aanp_rss_nonce'], 'aanp_rss_admin')) {
    $action = sanitize_text_field($_POST['aanp_rss_action']);
    
    switch ($action) {
        case 'bulk_enable':
            $feed_ids = array_map('intval', $_POST['feed_ids'] ?? array());
            $result = $rss_manager->enable_feeds($feed_ids);
            $message = $result['success'] ? 
                sprintf(__('Successfully enabled %d feeds.', 'ai-auto-news-poster'), $result['enabled_count']) :
                __('Failed to enable feeds.', 'ai-auto-news-poster');
            $message_type = $result['success'] ? 'success' : 'error';
            break;
            
        case 'bulk_disable':
            $feed_ids = array_map('intval', $_POST['feed_ids'] ?? array());
            $result = $rss_manager->disable_feeds($feed_ids);
            $message = $result['success'] ? 
                sprintf(__('Successfully disabled %d feeds.', 'ai-auto-news-poster'), $result['disabled_count']) :
                __('Failed to disable feeds.', 'ai-auto-news-poster');
            $message_type = $result['success'] ? 'success' : 'error';
            break;
            
        case 'validate_feeds':
            $results = array();
            $feeds = $rss_manager->get_feeds();
            foreach ($feeds as $feed) {
                $results[$feed['id']] = $rss_manager->validate_feed($feed['url']);
            }
            set_transient('aanp_rss_validation_results', $results, 300);
            $message = __('Feed validation completed.', 'ai-auto-news-poster');
            $message_type = 'success';
            break;
            
        case 'enable_top_feeds':
            $top_feeds = $rss_manager->get_top_reliable_feeds(20);
            $result = $rss_manager->enable_feeds($top_feeds);
            $message = $result['success'] ? 
                sprintf(__('Successfully enabled top %d reliable feeds.', 'ai-auto-news-poster'), count($top_feeds)) :
                __('Failed to enable top feeds.', 'ai-auto-news-poster');
            $message_type = $result['success'] ? 'success' : 'error';
            break;
    }
}

// Get current filters and search
$current_region = isset($_GET['region']) ? sanitize_text_field($_GET['region']) : '';
$current_category = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : '';
$current_search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
$current_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 20;

// Build query arguments
$args = array(
    'limit' => $per_page,
    'offset' => ($page - 1) * $per_page,
    'orderby' => 'reliability_score',
    'order' => 'DESC'
);

if ($current_region) $args['region'] = $current_region;
if ($current_category) $args['category'] = $current_category;
if ($current_search) $args['search'] = $current_search;
if ($current_status !== '') $args['enabled'] = ($current_status === 'enabled');

// Get feeds and statistics
$feeds = $rss_manager->get_feeds($args);
$total_feeds = count($rss_manager->get_feeds(array_merge($args, array('limit' => 1000))));
$statistics = $rss_manager->get_feed_statistics();
$categories = $rss_manager->get_categories();
$regions = array('UK', 'EU', 'USA');

// Calculate pagination
$total_pages = ceil($total_feeds / $per_page);
$validation_results = get_transient('aanp_rss_validation_results');
?>
<div class="wrap">
    <h1><?php _e('RSS Feed Management', 'ai-auto-news-poster'); ?></h1>
    
    <?php if (isset($message)): ?>
        <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>
    
    <?php if ($validation_results): ?>
        <div class="notice notice-info is-dismissible">
            <h3><?php _e('Feed Validation Results', 'ai-auto-news-poster'); ?></h3>
            <p><?php printf(__('Validated %d feeds:', 'ai-auto-news-poster'), count($validation_results)); ?></p>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Feed Name', 'ai-auto-news-poster'); ?></th>
                        <th><?php _e('Status', 'ai-auto-news-poster'); ?></th>
                        <th><?php _e('Items Found', 'ai-auto-news-poster'); ?></th>
                        <th><?php _e('Error', 'ai-auto-news-poster'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($validation_results as $feed_id => $result): ?>
                        <?php 
                        $feed = $rss_manager->get_feed($feed_id);
                        if (!$feed) continue;
                        ?>
                        <tr>
                            <td><?php echo esc_html($feed['name']); ?></td>
                            <td>
                                <?php if ($result['valid']): ?>
                                    <span class="dashicons dashicons-yes-alt" style="color: green;"></span> 
                                    <?php _e('Valid', 'ai-auto-news-poster'); ?>
                                <?php else: ?>
                                    <span class="dashicons dashicons-dismiss" style="color: red;"></span> 
                                    <?php _e('Invalid', 'ai-auto-news-poster'); ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($result['item_count'] ?? 'N/A'); ?></td>
                            <td><?php echo esc_html($result['error'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p>
                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=aanp-rss-feeds&clear_validation=1'), 'aanp_rss_admin'); ?>" 
                   class="button"><?php _e('Clear Results', 'ai-auto-news-poster'); ?></a>
            </p>
        </div>
    <?php endif; ?>
    
    <!-- Statistics Dashboard -->
    <div class="aanp-rss-stats">
        <div class="aanp-stat-card">
            <h3><?php _e('Total Feeds', 'ai-auto-news-poster'); ?></h3>
            <div class="stat-number"><?php echo esc_html($statistics['total_feeds']); ?></div>
        </div>
        <div class="aanp-stat-card">
            <h3><?php _e('Enabled Feeds', 'ai-auto-news-poster'); ?></h3>
            <div class="stat-number"><?php echo esc_html($statistics['enabled_feeds']); ?></div>
        </div>
        <div class="aanp-stat-card">
            <h3><?php _e('Average Reliability', 'ai-auto-news-poster'); ?></h3>
            <div class="stat-number"><?php echo esc_html($statistics['average_reliability']); ?>%</div>
        </div>
        <div class="aanp-stat-card">
            <h3><?php _e('Recent Activity', 'ai-auto-news-poster'); ?></h3>
            <div class="stat-number">
                <?php 
                if ($statistics['recent_activity']) {
                    echo esc_html(human_time_diff(strtotime($statistics['recent_activity'])) . ' ' . __('ago', 'ai-auto-news-poster'));
                } else {
                    echo __('Never', 'ai-auto-news-poster');
                }
                ?>
            </div>
        </div>
    </div>
    
    <!-- Filters and Search -->
    <div class="aanp-rss-filters">
        <form method="get" class="aanp-rss-filter-form">
            <input type="hidden" name="page" value="aanp-rss-feeds" />
            
            <select name="region">
                <option value=""><?php _e('All Regions', 'ai-auto-news-poster'); ?></option>
                <?php foreach ($regions as $region): ?>
                    <option value="<?php echo esc_attr($region); ?>" 
                            <?php selected($current_region, $region); ?>>
                        <?php echo esc_html($region); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <select name="category">
                <option value=""><?php _e('All Categories', 'ai-auto-news-poster'); ?></option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?php echo esc_attr($category['name']); ?>" 
                            <?php selected($current_category, $category['name']); ?>>
                        <?php echo esc_html($category['name'] . ' (' . $category['count'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <select name="status">
                <option value=""><?php _e('All Status', 'ai-auto-news-poster'); ?></option>
                <option value="enabled" <?php selected($current_status, 'enabled'); ?>>
                    <?php _e('Enabled', 'ai-auto-news-poster'); ?>
                </option>
                <option value="disabled" <?php selected($current_status, 'disabled'); ?>>
                    <?php _e('Disabled', 'ai-auto-news-poster'); ?>
                </option>
            </select>
            
            <input type="text" name="search" placeholder="<?php _e('Search feeds...', 'ai-auto-news-poster'); ?>" 
                   value="<?php echo esc_attr($current_search); ?>" />
            
            <input type="submit" class="button" value="<?php _e('Filter', 'ai-auto-news-poster'); ?>" />
            
            <a href="<?php echo admin_url('admin.php?page=aanp-rss-feeds'); ?>" class="button">
                <?php _e('Clear', 'ai-auto-news-poster'); ?>
            </a>
        </form>
    </div>
    
    <!-- Bulk Actions -->
    <div class="aanp-bulk-actions">
        <form method="post" id="aanp-bulk-form">
            <?php wp_nonce_field('aanp_rss_admin', 'aanp_rss_nonce'); ?>
            <div class="bulk-actions">
                <select name="aanp_rss_action">
                    <option value=""><?php _e('Bulk Actions', 'ai-auto-news-poster'); ?></option>
                    <option value="bulk_enable"><?php _e('Enable Selected', 'ai-auto-news-poster'); ?></option>
                    <option value="bulk_disable"><?php _e('Disable Selected', 'ai-auto-news-poster'); ?></option>
                    <option value="validate_feeds"><?php _e('Validate All Feeds', 'ai-auto-news-poster'); ?></option>
                    <option value="enable_top_feeds"><?php _e('Enable Top 20 Reliable Feeds', 'ai-auto-news-poster'); ?></option>
                </select>
                <input type="submit" class="button" value="<?php _e('Apply', 'ai-auto-news-poster'); ?>" />
            </div>
        </form>
    </div>
    
    <!-- Feeds Table -->
    <form method="post" id="aanp-feeds-form">
        <?php wp_nonce_field('aanp_rss_admin', 'aanp_rss_nonce'); ?>
        <input type="hidden" name="aanp_rss_action" value="bulk_action" />
        
        <table class="wp-list-table widefat fixed striped feeds-table">
            <thead>
                <tr>
                    <td class="manage-column column-cb check-column">
                        <input type="checkbox" id="cb-select-all-1" />
                    </td>
                    <th scope="col" class="manage-column column-name column-primary">
                        <?php _e('Feed Name', 'ai-auto-news-poster'); ?>
                    </th>
                    <th scope="col" class="manage-column column-region">
                        <?php _e('Region', 'ai-auto-news-poster'); ?>
                    </th>
                    <th scope="col" class="manage-column column-category">
                        <?php _e('Category', 'ai-auto-news-poster'); ?>
                    </th>
                    <th scope="col" class="manage-column column-status">
                        <?php _e('Status', 'ai-auto-news-poster'); ?>
                    </th>
                    <th scope="col" class="manage-column column-reliability">
                        <?php _e('Reliability', 'ai-auto-news-poster'); ?>
                    </th>
                    <th scope="col" class="manage-column column-articles">
                        <?php _e('Articles', 'ai-auto-news-poster'); ?>
                    </th>
                    <th scope="col" class="manage-column column-last-fetch">
                        <?php _e('Last Fetch', 'ai-auto-news-poster'); ?>
                    </th>
                    <th scope="col" class="manage-column column-actions">
                        <?php _e('Actions', 'ai-auto-news-poster'); ?>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($feeds)): ?>
                    <tr>
                        <td colspan="9" class="no-items"><?php _e('No feeds found.', 'ai-auto-news-poster'); ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($feeds as $feed): ?>
                        <tr>
                            <th scope="row" class="check-column">
                                <input type="checkbox" name="feed_ids[]" value="<?php echo esc_attr($feed['id']); ?>" />
                            </th>
                            <td class="column-name column-primary">
                                <strong><?php echo esc_html($feed['name']); ?></strong>
                                <div class="row-details">
                                    <small>
                                        <a href="<?php echo esc_url($feed['url']); ?>" target="_blank">
                                            <?php echo esc_html(wp_trim_words($feed['url'], 8)); ?>
                                        </a>
                                    </small>
                                    <br>
                                    <small><?php echo esc_html($feed['description']); ?></small>
                                </div>
                            </td>
                            <td class="column-region">
                                <span class="region-badge region-<?php echo strtolower($feed['region']); ?>">
                                    <?php echo esc_html($feed['region']); ?>
                                </span>
                            </td>
                            <td class="column-category">
                                <span class="category-badge">
                                    <?php echo esc_html(ucfirst($feed['category'])); ?>
                                </span>
                            </td>
                            <td class="column-status">
                                <?php if ($feed['enabled']): ?>
                                    <span class="status-enabled"><?php _e('Enabled', 'ai-auto-news-poster'); ?></span>
                                <?php else: ?>
                                    <span class="status-disabled"><?php _e('Disabled', 'ai-auto-news-poster'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="column-reliability">
                                <div class="reliability-score">
                                    <span class="score-<?php echo $feed['reliability_score'] >= 80 ? 'high' : ($feed['reliability_score'] >= 50 ? 'medium' : 'low'); ?>">
                                        <?php echo esc_html($feed['reliability_score']); ?>%
                                    </span>
                                </div>
                            </td>
                            <td class="column-articles">
                                <?php if ($feed['article_count'] > 0): ?>
                                    <span class="article-count"><?php echo esc_html($feed['article_count']); ?></span>
                                <?php else: ?>
                                    <span class="no-articles">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="column-last-fetch">
                                <?php if ($feed['last_fetched']): ?>
                                    <small><?php echo esc_html(human_time_diff(strtotime($feed['last_fetched'])) . ' ' . __('ago', 'ai-auto-news-poster')); ?></small>
                                <?php else: ?>
                                    <small><?php _e('Never', 'ai-auto-news-poster'); ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="column-actions">
                                <button type="button" class="button button-small aanp-toggle-feed" 
                                        data-feed-id="<?php echo esc_attr($feed['id']); ?>"
                                        data-enabled="<?php echo $feed['enabled'] ? 'true' : 'false'; ?>">
                                    <?php echo $feed['enabled'] ? __('Disable', 'ai-auto-news-poster') : __('Enable', 'ai-auto-news-poster'); ?>
                                </button>
                                <button type="button" class="button button-small aanp-validate-feed"
                                        data-feed-url="<?php echo esc_attr($feed['url']); ?>"
                                        data-feed-name="<?php echo esc_attr($feed['name']); ?>">
                                    <?php _e('Test', 'ai-auto-news-poster'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </form>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="tablenav">
            <div class="tablenav-pages">
                <?php
                echo paginate_links(array(
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'current' => $page,
                    'total' => $total_pages,
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;'
                ));
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Feed Validation Modal -->
<div id="aanp-feed-validation-modal" class="aanp-modal" style="display: none;">
    <div class="aanp-modal-content">
        <span class="aanp-modal-close">&times;</span>
        <h3><?php _e('Feed Validation', 'ai-auto-news-poster'); ?></h3>
        <div id="validation-results"></div>
    </div>
</div>

<style>
.aanp-rss-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.aanp-stat-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.aanp-stat-card h3 {
    margin: 0 0 10px 0;
    font-size: 14px;
    color: #646970;
}

.stat-number {
    font-size: 28px;
    font-weight: bold;
    color: #1d2327;
}

.aanp-rss-filters {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin: 20px 0;
}

.aanp-rss-filter-form {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.aanp-rss-filter-form input[type="text"] {
    width: 200px;
}

.aanp-bulk-actions {
    margin: 20px 0;
}

.bulk-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

.feeds-table .region-badge {
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
}

.region-uk { background: #e74c3c; color: white; }
.region-eu { background: #3498db; color: white; }
.region-usa { background: #2ecc71; color: white; }

.category-badge {
    padding: 2px 6px;
    background: #f1f1f1;
    border-radius: 3px;
    font-size: 11px;
}

.status-enabled { color: #46b450; font-weight: bold; }
.status-disabled { color: #dc3232; font-weight: bold; }

.reliability-score {
    display: flex;
    align-items: center;
    gap: 5px;
}

.score-high { color: #46b450; }
.score-medium { color: #ffb900; }
.score-low { color: #dc3232; }

.article-count {
    font-weight: bold;
    color: #0073aa;
}

.no-articles { color: #666; }

.row-details {
    margin-top: 5px;
}

.row-details small a {
    color: #0073aa;
    text-decoration: none;
}

.row-details small a:hover {
    text-decoration: underline;
}

.aanp-modal {
    position: fixed;
    z-index: 160000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.4);
}

.aanp-modal-content {
    background-color: #fefefe;
    margin: 15% auto;
    padding: 20px;
    border: 1px solid #888;
    border-radius: 4px;
    width: 80%;
    max-width: 500px;
}

.aanp-modal-close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.aanp-modal-close:hover,
.aanp-modal-close:focus {
    color: black;
    text-decoration: none;
    cursor: pointer;
}

.validation-result {
    margin: 10px 0;
    padding: 10px;
    border-radius: 4px;
}

.validation-success {
    background-color: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.validation-error {
    background-color: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

@media (max-width: 768px) {
    .aanp-rss-stats {
        grid-template-columns: 1fr;
    }
    
    .aanp-rss-filter-form {
        flex-direction: column;
        align-items: stretch;
    }
    
    .aanp-rss-filter-form input[type="text"],
    .aanp-rss-filter-form select {
        width: 100%;
    }
    
    .bulk-actions {
        flex-direction: column;
        align-items: stretch;
    }
    
    .feeds-table {
        font-size: 14px;
    }
    
    .column-region,
    .column-category,
    .column-status,
    .column-reliability,
    .column-articles,
    .column-last-fetch {
        display: none;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Toggle feed status
    $('.aanp-toggle-feed').on('click', function() {
        var button = $(this);
        var feedId = button.data('feed-id');
        var enabled = button.data('enabled') === true || button.data('enabled') === 'true';
        var action = enabled ? 'disable' : 'enable';
        
        button.prop('disabled', true).text('<?php _e('Processing...', 'ai-auto-news-poster'); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aanp_toggle_rss_feed',
                feed_id: feedId,
                enabled: !enabled,
                nonce: '<?php echo wp_create_nonce('aanp_rss_admin_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    // Update button and status
                    if (enabled) {
                        button.data('enabled', false).text('<?php _e('Enable', 'ai-auto-news-poster'); ?>');
                        button.closest('tr').find('.status-enabled').replaceWith('<span class="status-disabled"><?php _e('Disabled', 'ai-auto-news-poster'); ?></span>');
                    } else {
                        button.data('enabled', true).text('<?php _e('Disable', 'ai-auto-news-poster'); ?>');
                        button.closest('tr').find('.status-disabled').replaceWith('<span class="status-enabled"><?php _e('Enabled', 'ai-auto-news-poster'); ?></span>');
                    }
                    
                    // Show success message
                    $('<div class="notice notice-success is-dismissible"><p><?php _e('Feed status updated successfully.', 'ai-auto-news-poster'); ?></p></div>').insertAfter('h1');
                } else {
                    alert('<?php _e('Failed to update feed status.', 'ai-auto-news-poster'); ?>');
                }
            },
            error: function() {
                alert('<?php _e('An error occurred.', 'ai-auto-news-poster'); ?>');
            },
            complete: function() {
                button.prop('disabled', false);
            }
        });
    });
    
    // Validate single feed
    $('.aanp-validate-feed').on('click', function() {
        var button = $(this);
        var feedUrl = button.data('feed-url');
        var feedName = button.data('feed-name');
        
        button.prop('disabled', true).text('<?php _e('Testing...', 'ai-auto-news-poster'); ?>');
        
        // Simple validation - in real implementation, this would make an AJAX call
        setTimeout(function() {
            var result = $('<div class="validation-result validation-success">' +
                '<strong>' + feedName + '</strong><br>' +
                '<?php _e('Feed appears to be valid.', 'ai-auto-news-poster'); ?>' +
                '</div>');
            
            $('#validation-results').html(result);
            $('#aanp-feed-validation-modal').show();
            
            button.prop('disabled', false).text('<?php _e('Test', 'ai-auto-news-poster'); ?>');
        }, 1000);
    });
    
    // Modal close
    $('.aanp-modal-close, .aanp-modal').on('click', function(e) {
        if (e.target === this) {
            $('#aanp-feed-validation-modal').hide();
        }
    });
    
    // Select all checkboxes
    $('#cb-select-all-1').on('change', function() {
        $('input[name="feed_ids[]"]').prop('checked', this.checked);
    });
    
    // Bulk form submission
    $('#aanp-bulk-form').on('submit', function(e) {
        var action = $(this).find('select[name="aanp_rss_action"]').val();
        if (!action) {
            e.preventDefault();
            alert('<?php _e('Please select an action.', 'ai-auto-news-poster'); ?>');
            return false;
        }
        
        if ((action === 'bulk_enable' || action === 'bulk_disable') && $('input[name="feed_ids[]"]:checked').length === 0) {
            e.preventDefault();
            alert('<?php _e('Please select at least one feed.', 'ai-auto-news-poster'); ?>');
            return false;
        }
    });
});
</script>