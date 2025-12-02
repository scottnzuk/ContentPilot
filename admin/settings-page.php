<?php
/**
 * Admin Settings Page Template
 *
 * @package ContentPilot
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$options = get_option('cp_settings', array());
$post_creator = new CP_Post_Creator();
$stats = $post_creator->get_stats();
$recent_posts = $post_creator->get_recent_posts(5);
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php settings_errors(); ?>

    <!-- Welcome Banner -->
    <div class="notice notice-success">
        <p><strong><?php _e('🎉 All Features Now Available!', 'contentpilot'); ?></strong></p>
        <p><?php _e('You now have access to all premium features including automated scheduling, large batch generation, featured image generation, SEO optimization, and advanced analytics - all included in the core plugin!', 'contentpilot'); ?></p>
    </div>

    <!-- OpenRouter API Key Section -->
    <div class="cp-openrouter-section" style="background: #e7f3ff; padding: 20px; border: 1px solid #0073aa; border-radius: 4px; margin-bottom: 20px;">
        <h2><?php _e('🔑 OpenRouter API Key (Recommended)', 'contentpilot'); ?></h2>
        <p><?php _e('OpenRouter is now the default API provider, giving you access to multiple AI models with a single API key.', 'contentpilot'); ?></p>

        <div class="cp-api-key-actions" style="display: flex; gap: 10px; margin-bottom: 15px;">
            <a href="https://openrouter.ai/" target="_blank" class="button button-primary">
                <span class="dashicons dashicons-admin-links"></span>
                <?php _e('Get OpenRouter API Key', 'contentpilot'); ?>
            </a>
            <a href="https://openrouter.ai/docs" target="_blank" class="button button-secondary">
                <span class="dashicons dashicons-book-alt"></span>
                <?php _e('OpenRouter Documentation', 'contentpilot'); ?>
            </a>
        </div>

        <p class="description">
            <?php _e('✅ Single API key for multiple AI providers | 🚀 Better performance | 💰 Cost-effective pricing | 🔒 Secure and reliable', 'contentpilot'); ?>
        </p>
    </div>

    <!-- Statistics Dashboard -->
    <div class="cp-dashboard" style="margin: 20px 0;">
        <h2><?php _e('Statistics', 'contentpilot'); ?></h2>
        <div style="display: flex; gap: 20px; margin-bottom: 20px;">
            <div class="cp-stat-box" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; text-align: center; min-width: 120px;">
                <h3 style="margin: 0; font-size: 24px; color: #0073aa;"><?php echo esc_html($stats['total']); ?></h3>
                <p style="margin: 5px 0 0 0; color: #666;"><?php _e('Total Posts', 'contentpilot'); ?></p>
            </div>
            <div class="cp-stat-box" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; text-align: center; min-width: 120px;">
                <h3 style="margin: 0; font-size: 24px; color: #00a32a;"><?php echo esc_html($stats['today']); ?></h3>
                <p style="margin: 5px 0 0 0; color: #666;"><?php _e('Today', 'contentpilot'); ?></p>
            </div>
            <div class="cp-stat-box" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; text-align: center; min-width: 120px;">
                <h3 style="margin: 0; font-size: 24px; color: #ff6900;"><?php echo esc_html($stats['week']); ?></h3>
                <p style="margin: 5px 0 0 0; color: #666;"><?php _e('This Week', 'contentpilot'); ?></p>
            </div>
            <div class="cp-stat-box" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; text-align: center; min-width: 120px;">
                <h3 style="margin: 0; font-size: 24px; color: #8c8f94;"><?php echo esc_html($stats['month']); ?></h3>
                <p style="margin: 5px 0 0 0; color: #666;"><?php _e('This Month', 'contentpilot'); ?></p>
            </div>
        </div>
    </div>

    <!-- Generate Posts Section -->
    <div class="cp-generate-section" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; margin-bottom: 20px;">
        <h2><?php _e('Generate Posts', 'contentpilot'); ?></h2>
        <p><?php printf(__('Click the button below to fetch the latest news and generate up to %d unique blog posts automatically.', 'contentpilot'), CP_Post_Creator::get_max_posts_per_batch()); ?></p>

        <div class="cp-generate-controls">
            <button type="button" id="cp-generate-posts" class="button button-primary button-large">
                <span class="dashicons dashicons-update" style="margin-top: 3px;"></span>
                <?php printf(__('Generate %d Posts', 'contentpilot'), CP_Post_Creator::get_max_posts_per_batch()); ?>
            </button>

            <div id="cp-generation-status" style="margin-top: 15px; display: none;">
                <div class="cp-progress" style="background: #f0f0f1; border-radius: 3px; height: 20px; overflow: hidden;">
                    <div class="cp-progress-bar" style="background: #0073aa; height: 100%; width: 0%; transition: width 0.3s ease;"></div>
                </div>
                <p id="cp-status-text" style="margin: 10px 0 0 0; font-style: italic;"></p>
            </div>
        </div>

        <div id="cp-generation-results" style="margin-top: 20px; display: none;">
            <h3><?php _e('Generated Posts', 'contentpilot'); ?></h3>
            <div id="cp-results-list"></div>
        </div>
    </div>

    <!-- Recent Posts -->
    <?php if (!empty($recent_posts)): ?>
    <div class="cp-recent-posts" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; margin-bottom: 20px;">
        <h2><?php _e('Recent Generated Posts', 'contentpilot'); ?></h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Title', 'contentpilot'); ?></th>
                    <th><?php _e('Status', 'contentpilot'); ?></th>
                    <th><?php _e('Generated', 'contentpilot'); ?></th>
                    <th><?php _e('Actions', 'contentpilot'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_posts as $post): ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html($post['title']); ?></strong>
                        <br>
                        <small><a href="<?php echo esc_url($post['source_url']); ?>" target="_blank" rel="noopener"><?php _e('Source', 'contentpilot'); ?></a></small>
                    </td>
                    <td>
                        <span class="post-status <?php echo esc_attr($post['status']); ?>">
                            <?php echo esc_html(ucfirst($post['status'])); ?>
                        </span>
                    </td>
                    <td><?php echo esc_html(human_time_diff(strtotime($post['generated_at']), current_time('timestamp')) . ' ago'); ?></td>
                    <td>
                        <a href="<?php echo esc_url($post['edit_link']); ?>" class="button button-small"><?php _e('Edit', 'contentpilot'); ?></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Humanizer Output Section -->
    <div class="cp-humanizer-section" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; margin-bottom: 20px;">
        <h2><?php _e('Humanize Output', 'contentpilot'); ?></h2>
        <p><?php _e('Make AI-generated content appear more human-written using offline processing.', 'contentpilot'); ?></p>

        <!-- System Status Check -->
        <?php
        if (class_exists('CP_HumanizerManager')) {
            $humanizer_manager = new CP_HumanizerManager();
            $system_status = $humanizer_manager->get_system_status();

            if (!$system_status['overall_status']) {
                echo '<div class="notice notice-warning">';
                echo '<p><strong>' . __('Humanizer Dependencies Missing:', 'contentpilot') . '</strong></p>';
                echo '<ul style="margin-left: 20px;">';
                foreach ($system_status['missing_requirements'] as $requirement) {
                    echo '<li>' . esc_html($requirement) . '</li>';
                }
                echo '</ul>';

                $instructions = $humanizer_manager->get_installation_instructions();
                if (!empty($instructions)) {
                    echo '<p><strong>' . __('Installation Instructions:', 'contentpilot') . '</strong></p>';
                    foreach ($instructions as $instruction) {
                        echo '<p><em>' . esc_html($instruction['requirement']) . ':</em></p>';
                        echo '<ul style="margin-left: 20px;">';
                        foreach ($instruction['instructions'] as $step) {
                            echo '<li><code>' . esc_html($step) . '</code></li>';
                        }
                        echo '</ul>';
                    }
                }
                echo '</div>';
            } else {
                echo '<div class="notice notice-success"><p>' . __('✓ Humanizer dependencies available', 'contentpilot') . '</p></div>';
            }
        }
        ?>

        <form method="post" action="options.php">
            <?php
            settings_fields('cp_settings_group');
            do_settings_sections('contentpilot');
            ?>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Enable Humanizer', 'contentpilot'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="cp_settings[humanizer_enabled]" value="1"
                                   <?php checked(isset($options['humanizer_enabled']) ? $options['humanizer_enabled'] : false); ?> />
                            <?php _e('Enable AI content humanization', 'contentpilot'); ?>
                        </label>
                        <p class="description"><?php _e('Process AI-generated content through the humano package to make it appear more human-written.', 'contentpilot'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php _e('Strength', 'contentpilot'); ?></th>
                    <td>
                        <select name="cp_settings[humanizer_strength]">
                            <option value="low" <?php selected(isset($options['humanizer_strength']) ? $options['humanizer_strength'] : 'medium', 'low'); ?>><?php _e('Low - Minimal changes', 'contentpilot'); ?></option>
                            <option value="medium" <?php selected(isset($options['humanizer_strength']) ? $options['humanizer_strength'] : 'medium', 'medium'); ?>><?php _e('Medium - Balanced changes', 'contentpilot'); ?></option>
                            <option value="high" <?php selected(isset($options['humanizer_strength']) ? $options['humanizer_strength'] : 'medium', 'high'); ?>><?php _e('High - Significant changes', 'contentpilot'); ?></option>
                            <option value="maximum" <?php selected(isset($options['humanizer_strength']) ? $options['humanizer_strength'] : 'medium', 'maximum'); ?>><?php _e('Maximum - Extensive changes', 'contentpilot'); ?></option>
                        </select>
                        <p class="description"><?php _e('Higher strength means more aggressive humanization.', 'contentpilot'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php _e('Custom Personality', 'contentpilot'); ?></th>
                    <td>
                        <input type="text" name="cp_settings[humanizer_personality]"
                               value="<?php echo esc_attr(isset($options['humanizer_personality']) ? $options['humanizer_personality'] : ''); ?>"
                               class="regular-text" placeholder="<?php _e('e.g., casual blogger, professional journalist', 'contentpilot'); ?>" />
                        <p class="description"><?php _e('Optional personality style for humanization (e.g., "casual blogger", "professional journalist").', 'contentpilot'); ?></p>
                    </td>
                </tr>
            </table>

            <div class="cp-test-humanizer" style="margin-top: 20px;">
                <h3><?php _e('Test Humanizer', 'contentpilot'); ?></h3>
                <p><?php _e('Test the humanizer with sample text to see the results.', 'contentpilot'); ?></p>
                <textarea id="humanizer-test-input" rows="4" cols="50" class="large-text"
                          placeholder="<?php _e('Enter sample text to humanize...', 'contentpilot'); ?>">Artificial intelligence has revolutionized the way we approach content creation. The integration of AI-powered tools into various industries has demonstrated significant improvements in efficiency and productivity.</textarea>
                <br><br>
                <button type="button" id="test-humanizer-btn" class="button button-secondary">
                    <?php _e('Test Humanizer', 'contentpilot'); ?>
                </button>

                <div id="humanizer-test-results" style="margin-top: 15px; display: none;">
                    <h4><?php _e('Results:', 'contentpilot'); ?></h4>
                    <div id="original-text" style="background: #f9f9f9; padding: 10px; border: 1px solid #ddd; margin: 5px 0;">
                        <strong><?php _e('Original:', 'contentpilot'); ?></strong>
                        <p id="original-content"></p>
                    </div>
                    <div id="humanized-text" style="background: #e7f3ff; padding: 10px; border: 1px solid #0073aa; margin: 5px 0;">
                        <strong><?php _e('Humanized:', 'contentpilot'); ?></strong>
                        <p id="humanized-content"></p>
                    </div>
                    <div id="humanization-metadata" style="font-size: 12px; color: #666; margin-top: 10px;">
                        <p id="humanization-stats"></p>
                    </div>
                </div>
            </div>

            <?php submit_button(__('Save Settings', 'contentpilot')); ?>
        </form>
    </div>

    <!-- Settings Form -->
    <form method="post" action="options.php">
        <?php
        settings_fields('cp_settings_group');
        do_settings_sections('contentpilot');
        ?>

        <!-- Advanced Features (Now Free) -->
        <div class="cp-core-features" style="background: #e7f3ff; padding: 20px; border: 1px solid #0073aa; border-radius: 4px; margin: 20px 0;">
            <h2><?php _e('✨ All Features Now Available', 'contentpilot'); ?></h2>
            <p class="description"><?php _e('All previously premium features are now included in the core plugin!', 'contentpilot'); ?></p>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Batch Size', 'contentpilot'); ?></th>
                    <td>
                        <select name="cp_settings[batch_size]">
                            <option value="5" <?php selected(isset($options['batch_size']) ? $options['batch_size'] : 5, 5); ?>>5 <?php _e('Posts', 'contentpilot'); ?></option>
                            <option value="10" <?php selected(isset($options['batch_size']) ? $options['batch_size'] : 5, 10); ?>>10 <?php _e('Posts', 'contentpilot'); ?></option>
                            <option value="15" <?php selected(isset($options['batch_size']) ? $options['batch_size'] : 5, 15); ?>>15 <?php _e('Posts', 'contentpilot'); ?></option>
                            <option value="20" <?php selected(isset($options['batch_size']) ? $options['batch_size'] : 5, 20); ?>>20 <?php _e('Posts', 'contentpilot'); ?></option>
                            <option value="25" <?php selected(isset($options['batch_size']) ? $options['batch_size'] : 5, 25); ?>>25 <?php _e('Posts', 'contentpilot'); ?></option>
                            <option value="30" <?php selected(isset($options['batch_size']) ? $options['batch_size'] : 5, 30); ?>>30 <?php _e('Posts', 'contentpilot'); ?></option>
                        </select>
                        <p class="description"><?php _e('Number of posts to generate per batch.', 'contentpilot'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php _e('Featured Images', 'contentpilot'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="cp_settings[featured_images_enabled]" value="1"
                                   <?php checked(isset($options['featured_images_enabled']) ? $options['featured_images_enabled'] : false); ?> />
                            <?php _e('Auto-generate featured images', 'contentpilot'); ?>
                        </label>
                        <p class="description"><?php _e('Automatically create relevant featured images for posts.', 'contentpilot'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php _e('SEO Optimization', 'contentpilot'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="cp_settings[seo_optimization_enabled]" value="1"
                                   <?php checked(isset($options['seo_optimization_enabled']) ? $options['seo_optimization_enabled'] : true); ?> />
                            <?php _e('Auto-fill SEO meta tags', 'contentpilot'); ?>
                        </label>
                        <p class="description"><?php _e('Automatically generate SEO-optimized meta descriptions and keywords.', 'contentpilot'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php _e('Advanced Analytics', 'contentpilot'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="cp_settings[analytics_enabled]" value="1"
                                   <?php checked(isset($options['analytics_enabled']) ? $options['analytics_enabled'] : true); ?> />
                            <?php _e('Enable advanced analytics', 'contentpilot'); ?>
                        </label>
                        <p class="description"><?php _e('Get detailed insights into your content performance.', 'contentpilot'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <?php submit_button(); ?>
    </form>
</div>

<style>
.cp-categories label {
    display: inline-block;
    margin-right: 15px;
    margin-bottom: 5px;
}

.rss-feed-row {
    margin-bottom: 10px;
}

.rss-feed-row input[type="url"] {
    margin-right: 10px;
}

.post-status.draft {
    color: #b32d2e;
}

.post-status.publish {
    color: #00a32a;
}

.post-status.private {
    color: #ff6900;
}

.cp-core-features {
    position: relative;
}

.cp-core-features::before {
    content: "✨ CORE";
    position: absolute;
    top: 10px;
    right: 15px;
    background: #00a32a;
    color: white;
    padding: 5px 10px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: bold;
}

.cp-openrouter-section {
    position: relative;
}

.cp-openrouter-section::before {
    content: "🔑 API";
    position: absolute;
    top: 10px;
    right: 15px;
    background: #0073aa;
    color: white;
    padding: 5px 10px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: bold;
}
</style>
