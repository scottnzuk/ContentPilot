<?php
/**
 * Admin Settings Class
 *
 * @package ContentPilot
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
            add_action('wp_ajax_cp_generate_posts', array($this, 'ajax_generate_posts'));
            add_action('wp_ajax_cp_purge_cache', array($this, 'ajax_purge_cache'));
            add_action('wp_ajax_cp_test_humanizer', array($this, 'ajax_test_humanizer'));

            // Log successful initialization if logger is available
            if ($this->logger) {
                $this->logger->log('info', 'Admin_Settings class initialized successfully');
            }

        } catch (Exception $e) {
            // Log initialization failure
            if (function_exists('error_log')) {
                error_log('ContentPilot Admin Settings initialization failed: ' . $e->getMessage());
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
     * LLM Provider callback
     */
    public function llm_provider_callback() {
        try {
            $options = get_option('cp_settings', array());
            $value = isset($options['llm_provider']) ? sanitize_text_field($options['llm_provider']) : 'openrouter';

            echo '<select name="cp_settings[llm_provider]" id="llm_provider">';
            echo '<option value="openai"' . selected($value, 'openai', false) . '>OpenAI</option>';
            echo '<option value="anthropic"' . selected($value, 'anthropic', false) . '>Anthropic</option>';
            echo '<option value="openrouter"' . selected($value, 'openrouter', false) . '>OpenRouter (Recommended)</option>';
            echo '<option value="custom"' . selected($value, 'custom', false) . '>Custom API</option>';
            echo '</select>';
            echo '<p class="description">' . esc_html__('Select your preferred LLM provider. OpenRouter is recommended for access to multiple AI models.', 'contentpilot') . '</p>';
        } catch (Exception $e) {
            $this->logger->log('error', 'Failed to render LLM provider field: ' . $e->getMessage());
            echo '<p class="error">' . esc_html__('Error loading provider options.', 'contentpilot') . '</p>';
        }
    }

    /**
     * API Key callback
     */
    public function api_key_callback() {
        try {
            $options = get_option('cp_settings', array());
            $value = isset($options['api_key']) ? self::decrypt_api_key($options['api_key']) : '';

            echo '<input type="password" name="cp_settings[api_key]" id="api_key" value="' .
                 esc_attr($value) . '" class="regular-text" />';
            echo '<p class="description">' .
                 esc_html__('Enter your API key for the selected LLM provider.', 'contentpilot') . '</p>';
            echo '<p class="description"><small>' .
                 esc_html__('Your API key is encrypted and stored securely.', 'contentpilot') . '</small></p>';
        } catch (Exception $e) {
            $this->logger->log('error', 'Failed to render API key field: ' . $e->getMessage());
            echo '<p class="error">' . esc_html__('Error loading API key field.', 'contentpilot') . '</p>';
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
                    $sanitized['llm_provider'] = 'openrouter'; // Default fallback to OpenRouter
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
     * Validate API key format based on provider
     */
    private function validate_api_key_format($api_key) {
        try {
            $options = get_option('cp_settings', array());
            $provider = isset($options['llm_provider']) ? $options['llm_provider'] : 'openrouter';

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
}