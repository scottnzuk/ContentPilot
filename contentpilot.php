<?php
/**
 * Plugin Name: ContentPilot - AI-Powered Content Generation
 * Plugin URI: https://github.com/scottnzuk/contentpilot-enhanced
 * Description: Enterprise-grade AI content generation and SEO optimization WordPress plugin with offline AI content humanization, microservices architecture, and advanced analytics.
 * Version: 2.0.0
 * Author: scottnzuk
 * Author URI: https://github.com/scottnzuk
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: contentpilot
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 *
 * @package ContentPilot
 * @category Content Generation
 * @author Scott Nzuk
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('CP_VERSION', '2.0.0');
define('CP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CP_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('CP_TEXT_DOMAIN', 'contentpilot');

// Include core files
require_once CP_PLUGIN_DIR . 'includes/class-admin-settings.php';
require_once CP_PLUGIN_DIR . 'includes/class-news-fetch.php';
require_once CP_PLUGIN_DIR . 'includes/class-ai-generator.php';
require_once CP_PLUGIN_DIR . 'includes/class-post-creator.php';
require_once CP_PLUGIN_DIR . 'includes/class-content-verifier.php';
require_once CP_PLUGIN_DIR . 'includes/class-security-manager.php';
require_once CP_PLUGIN_DIR . 'includes/class-error-handler.php';
require_once CP_PLUGIN_DIR . 'includes/class-rate-limiter.php';
require_once CP_PLUGIN_DIR . 'includes/class-cache-manager.php';
require_once CP_PLUGIN_DIR . 'includes/class-logger.php';

// Initialize plugin
function cp_initialize_plugin() {
    // Initialize admin settings
    $admin_settings = new CP_Admin_Settings();

    // Initialize services
    $news_fetch = new CP_News_Fetch();
    $ai_generator = new CP_AI_Generator();
    $post_creator = new CP_Post_Creator();
    $content_verifier = new CP_Content_Verifier();

    // Register hooks
    add_action('admin_menu', array($admin_settings, 'add_settings_page'));
    add_action('admin_init', array($admin_settings, 'register_settings'));
    add_action('admin_enqueue_scripts', array($admin_settings, 'enqueue_admin_scripts'));

    // Initialize security and error handling
    $security_manager = new CP_Security_Manager();
    $error_handler = CP_Error_Handler::getInstance();
    $logger = CP_Logger::getInstance();

    $logger->info('ContentPilot plugin initialized successfully', array('version' => CP_VERSION));
}

// Hook plugin initialization
add_action('plugins_loaded', 'cp_initialize_plugin');

// Activation and deactivation hooks
register_activation_hook(__FILE__, 'cp_activate_plugin');
register_deactivation_hook(__FILE__, 'cp_deactivate_plugin');

function cp_activate_plugin() {
    // Set up database tables, default settings, etc.
    $admin_settings = new CP_Admin_Settings();
    $admin_settings->setup_default_settings();

    // Log activation
    $logger = CP_Logger::getInstance();
    $logger->info('ContentPilot plugin activated', array('version' => CP_VERSION));
}

function cp_deactivate_plugin() {
    // Clean up any scheduled events, etc.
    wp_clear_scheduled_hook('cp_scheduled_generation');

    // Log deactivation
    $logger = CP_Logger::getInstance();
    $logger->info('ContentPilot plugin deactivated');
}

// Plugin uninstall hook
register_uninstall_hook(__FILE__, 'cp_uninstall_plugin');

function cp_uninstall_plugin() {
    // Clean up database tables, options, etc.
    $admin_settings = new CP_Admin_Settings();
    $admin_settings->cleanup_plugin_data();

    // Log uninstall
    $logger = CP_Logger::getInstance();
    $logger->info('ContentPilot plugin uninstalled');
}

// Load plugin text domain for translations
function cp_load_textdomain() {
    load_plugin_textdomain(
        'contentpilot',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages/'
    );
}
add_action('plugins_loaded', 'cp_load_textdomain');

// Add plugin action links
function cp_add_plugin_action_links($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=contentpilot-settings') . '">' . __('Settings', 'contentpilot') . '</a>';
    $docs_link = '<a href="https://github.com/scottnzuk/contentpilot-enhanced/wiki" target="_blank">' . __('Docs', 'contentpilot') . '</a>';
    $support_link = '<a href="https://github.com/scottnzuk/contentpilot-enhanced/issues" target="_blank">' . __('Support', 'contentpilot') . '</a>';

    array_unshift($links, $support_link, $docs_link, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . CP_PLUGIN_BASENAME, 'cp_add_plugin_action_links');

// Add plugin row meta links
function cp_add_plugin_row_meta($links, $file) {
    if ($file == CP_PLUGIN_BASENAME) {
        $links[] = '<a href="https://github.com/scottnzuk/contentpilot-enhanced/wiki" target="_blank">' . __('Documentation', 'contentpilot') . '</a>';
        $links[] = '<a href="https://github.com/scottnzuk/contentpilot-enhanced/issues" target="_blank">' . __('Report Issue', 'contentpilot') . '</a>';
        $links[] = '<a href="https://github.com/scottnzuk/contentpilot-enhanced" target="_blank">' . __('GitHub', 'contentpilot') . '</a>';
    }
    return $links;
}
add_filter('plugin_row_meta', 'cp_add_plugin_row_meta', 10, 2);

// Check for required PHP version
function cp_check_php_version() {
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>'
                . sprintf(__('ContentPilot requires PHP 7.4 or higher. You are running version %s. Please upgrade PHP to use this plugin.', 'contentpilot'), PHP_VERSION)
                . '</p></div>';
        });

        // Deactivate plugin
        deactivate_plugins(plugin_basename(__FILE__));
    }
}
add_action('admin_init', 'cp_check_php_version');

// Check for required WordPress version
function cp_check_wp_version() {
    global $wp_version;

    if (version_compare($wp_version, '5.0', '<')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>'
                . sprintf(__('ContentPilot requires WordPress 5.0 or higher. You are running version %s. Please upgrade WordPress to use this plugin.', 'contentpilot'), $wp_version)
                . '</p></div>';
        });

        // Deactivate plugin
        deactivate_plugins(plugin_basename(__FILE__));
    }
}
add_action('admin_init', 'cp_check_wp_version');

// Add welcome notice on first activation
function cp_add_welcome_notice() {
    if (get_option('cp_welcome_notice_shown') !== '1') {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>'
                . __('Welcome to ContentPilot! Thank you for installing the most advanced AI content generation plugin for WordPress. Please go to the settings page to configure your API keys and preferences.', 'contentpilot')
                . '</p></div>';
        });

        update_option('cp_welcome_notice_shown', '1');
    }
}
add_action('admin_init', 'cp_add_welcome_notice');

// Add plugin header info
function cp_add_plugin_header_info() {
    echo '<style>
        .cp-plugin-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        .cp-plugin-header h1 {
            margin: 0;
            font-size: 24px;
            flex-grow: 1;
        }
        .cp-plugin-header .version {
            background: rgba(255,255,255,0.2);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            margin-left: 10px;
        }
        .cp-plugin-logo {
            width: 40px;
            height: 40px;
            background: white;
            border-radius: 8px;
            margin-right: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #667eea;
        }
    </style>';
}
add_action('admin_head', 'cp_add_plugin_header_info');
