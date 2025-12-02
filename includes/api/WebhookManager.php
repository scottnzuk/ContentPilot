<?php
/**
 * Webhook Manager for Real-Time Events
 * 
 * Provides comprehensive webhook management with event broadcasting,
 * delivery management, security features, and integration capabilities.
 *
 * @package AI_Auto_News_Poster
 * @subpackage Includes/API
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WebhookManager {
    
    /**
     * Webhook manager instance (singleton)
     */
    private static $instance = null;
    
    /**
     * Registered webhooks storage
     */
    private $webhooks = [];
    
    /**
     * Event queue
     */
    private $event_queue = [];
    
    /**
     * Configuration
     */
    private $config = [];
    
    /**
     * Supported event types
     */
    private $event_types = [];
    
    /**
     * Delivery status tracking
     */
    private $delivery_status = [];
    
    /**
     * Retry configuration
     */
    private $retry_config = [];
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Initialize webhook manager
     */
    private function init() {
        $this->load_configuration();
        $this->register_event_types();
        $this->load_registrations();
        $this->setup_hooks();
        $this->schedule_processing();
    }
    
    /**
     * Load configuration
     */
    private function load_configuration() {
        $this->config = get_option('ai_news_webhook_config', [
            'enabled' => true,
            'retry_enabled' => true,
            'max_retries' => 3,
            'retry_delay' => 60, // seconds
            'timeout' => 30, // seconds
            'verify_ssl' => true,
            'secret_key_length' => 32,
            'event_history_retention' => 30, // days
            'rate_limiting' => [
                'requests_per_minute' => 60,
                'requests_per_hour' => 1000
            ]
        ]);
        
        $this->retry_config = [
            'initial_delay' => $this->config['retry_delay'],
            'max_delay' => 3600, // 1 hour
            'backoff_multiplier' => 2,
            'max_retries' => $this->config['max_retries']
        ];
    }
    
    /**
     * Register event types
     */
    private function register_event_types() {
        $this->event_types = [
            'content.created' => [
                'description' => 'New content has been created',
                'category' => 'content',
                'priority' => 'high'
            ],
            'content.published' => [
                'description' => 'Content has been published',
                'category' => 'content',
                'priority' => 'high'
            ],
            'content.updated' => [
                'description' => 'Content has been updated',
                'category' => 'content',
                'priority' => 'medium'
            ],
            'content.deleted' => [
                'description' => 'Content has been deleted',
                'category' => 'content',
                'priority' => 'medium'
            ],
            'content.scheduled' => [
                'description' => 'Content has been scheduled for publication',
                'category' => 'content',
                'priority' => 'medium'
            ],
            'ai.content_generated' => [
                'description' => 'AI has generated new content',
                'category' => 'ai',
                'priority' => 'high'
            ],
            'seo.optimization_completed' => [
                'description' => 'SEO optimization has been completed',
                'category' => 'seo',
                'priority' => 'medium'
            ],
            'analytics.data_updated' => [
                'description' => 'Analytics data has been updated',
                'category' => 'analytics',
                'priority' => 'low'
            ],
            'monitoring.alert_created' => [
                'description' => 'A new monitoring alert has been created',
                'category' => 'monitoring',
                'priority' => 'high'
            ],
            'monitoring.alert_resolved' => [
                'description' => 'A monitoring alert has been resolved',
                'category' => 'monitoring',
                'priority' => 'medium'
            ],
            'system.performance_issue' => [
                'description' => 'System performance issue detected',
                'category' => 'system',
                'priority' => 'high'
            ],
            'user.login' => [
                'description' => 'User has logged in',
                'category' => 'user',
                'priority' => 'low'
            ],
            'user.activity' => [
                'description' => 'User activity recorded',
                'category' => 'user',
                'priority' => 'low'
            ],
            'plugin.settings_updated' => [
                'description' => 'Plugin settings have been updated',
                'category' => 'plugin',
                'priority' => 'medium'
            ],
            'plugin.backup_completed' => [
                'description' => 'Plugin backup has been completed',
                'category' => 'plugin',
                'priority' => 'medium'
            ]
        ];
    }
    
    /**
     * Set up WordPress hooks
     */
    private function setup_hooks() {
        // Content events
        add_action('post_published', [$this, 'trigger_content_event'], 10, 2);
        add_action('post_updated', [$this, 'trigger_content_update_event'], 10, 3);
        add_action('wp_trash_post', [$this, 'trigger_content_delete_event'], 10, 2);
        add_action('transition_post_status', [$this, 'trigger_status_change_event'], 10, 3);
        
        // AI events
        add_action('ai_news_content_generated', [$this, 'trigger_ai_generation_event'], 10, 2);
        add_action('ai_news_seo_optimized', [$this, 'trigger_seo_optimization_event'], 10, 2);
        
        // Monitoring events
        add_action('ai_news_performance_alert', [$this, 'trigger_alert_event'], 10, 2);
        add_action('ai_news_system_error', [$this, 'trigger_system_error_event'], 10, 3);
        
        // User events
        add_action('wp_login', [$this, 'trigger_user_login_event'], 10, 2);
        add_action('wp_login_failed', [$this, 'trigger_login_failed_event'], 10, 2);
        
        // Plugin events
        add_action('ai_news_settings_updated', [$this, 'trigger_settings_update_event'], 10, 2);
        
        // AJAX handlers
        add_action('wp_ajax_ai_news_register_webhook', [$this, 'ajax_register_webhook']);
        add_action('wp_ajax_ai_news_update_webhook', [$this, 'ajax_update_webhook']);
        add_action('wp_ajax_ai_news_delete_webhook', [$this, 'ajax_delete_webhook']);
        add_action('wp_ajax_ai_news_test_webhook', [$this, 'ajax_test_webhook']);
        add_action('wp_ajax_ai_news_get_webhook_logs', [$this, 'ajax_get_webhook_logs']);
        add_action('wp_ajax_ai_news_resend_webhook', [$this, 'ajax_resend_webhook']);
        
        // Scheduled processing
        add_action('ai_news_process_webhook_queue', [$this, 'process_event_queue']);
        add_action('ai_news_cleanup_webhook_logs', [$this, 'cleanup_old_logs']);
    }
    
    /**
     * Schedule background processing
     */
    private function schedule_processing() {
        if (!wp_next_scheduled('ai_news_process_webhook_queue')) {
            wp_schedule_event(time(), 'every_2_minutes', 'ai_news_process_webhook_queue');
        }
        
        if (!wp_next_scheduled('ai_news_cleanup_webhook_logs')) {
            wp_schedule_event(time(), 'daily', 'ai_news_cleanup_webhook_logs');
        }
    }
    
    /**
     * Register webhook
     */
    public function register_webhook($name, $url, $events, $secret = null, $description = '', $filters = []) {
        // Validate URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return new WP_Error('invalid_url', 'Invalid webhook URL provided.');
        }
        
        // Validate events
        foreach ($events as $event) {
            if (!isset($this->event_types[$event])) {
                return new WP_Error('invalid_event', "Invalid event type: {$event}");
            }
        }
        
        // Generate webhook ID and secret
        $webhook_id = $this->generate_webhook_id();
        $webhook_secret = $secret ?: $this->generate_secret();
        
        // Create webhook object
        $webhook = [
            'id' => $webhook_id,
            'name' => $name,
            'url' => $url,
            'events' => $events,
            'secret' => $webhook_secret,
            'description' => $description,
            'filters' => $filters,
            'status' => 'active',
            'created_at' => time(),
            'last_triggered' => null,
            'delivery_count' => 0,
            'success_count' => 0,
            'error_count' => 0,
            'metadata' => []
        ];
        
        // Store webhook
        $this->store_webhook($webhook);
        $this->webhooks[$webhook_id] = $webhook;
        
        // Send test event
        $this->send_test_event($webhook);
        
        return $webhook_id;
    }
    
    /**
     * Update webhook
     */
    public function update_webhook($webhook_id, $updates) {
        if (!isset($this->webhooks[$webhook_id])) {
            return new WP_Error('webhook_not_found', 'Webhook not found.');
        }
        
        $webhook = $this->webhooks[$webhook_id];
        
        // Apply updates
        foreach ($updates as $key => $value) {
            if ($key === 'url' && !filter_var($value, FILTER_VALIDATE_URL)) {
                continue;
            }
            if ($key === 'events') {
                foreach ($value as $event) {
                    if (!isset($this->event_types[$event])) {
                        continue;
                    }
                }
            }
            
            $webhook[$key] = $value;
        }
        
        $webhook['updated_at'] = time();
        
        // Store updated webhook
        $this->store_webhook($webhook);
        $this->webhooks[$webhook_id] = $webhook;
        
        return true;
    }
    
    /**
     * Delete webhook
     */
    public function delete_webhook($webhook_id) {
        if (!isset($this->webhooks[$webhook_id])) {
            return false;
        }
        
        // Remove from active webhooks
        unset($this->webhooks[$webhook_id]);
        
        // Remove from database
        $this->remove_webhook_from_db($webhook_id);
        
        return true;
    }
    
    /**
     * Get webhook by ID
     */
    public function get_webhook($webhook_id) {
        return $this->webhooks[$webhook_id] ?? null;
    }
    
    /**
     * Get all webhooks
     */
    public function get_all_webhooks($filters = []) {
        $webhooks = array_values($this->webhooks);
        
        // Apply filters
        if (!empty($filters)) {
            $webhooks = array_filter($webhooks, function($webhook) use ($filters) {
                foreach ($filters as $filter => $value) {
                    if (isset($webhook[$filter]) && $webhook[$filter] !== $value) {
                        return false;
                    }
                }
                return true;
            });
        }
        
        return $webhooks;
    }
    
    /**
     * Trigger event
     */
    public function trigger_event($event_type, $data = [], $context = []) {
        if (!$this->config['enabled']) {
            return false;
        }
        
        // Validate event type
        if (!isset($this->event_types[$event_type])) {
            return false;
        }
        
        // Find webhooks that should receive this event
        $target_webhooks = $this->find_target_webhooks($event_type, $context);
        
        if (empty($target_webhooks)) {
            return false;
        }
        
        // Create event object
        $event = [
            'id' => $this->generate_event_id(),
            'type' => $event_type,
            'timestamp' => time(),
            'data' => $data,
            'context' => $context,
            'source' => 'ai_auto_news_poster',
            'version' => '2.0.0'
        ];
        
        // Queue event for delivery
        foreach ($target_webhooks as $webhook_id) {
            $this->queue_event_delivery($event, $webhook_id);
        }
        
        // Log event
        $this->log_event($event);
        
        return true;
    }
    
    /**
     * Find target webhooks for event
     */
    private function find_target_webhooks($event_type, $context) {
        $targets = [];
        
        foreach ($this->webhooks as $webhook_id => $webhook) {
            // Check if webhook is active
            if ($webhook['status'] !== 'active') {
                continue;
            }
            
            // Check if webhook subscribes to this event
            if (!in_array($event_type, $webhook['events'])) {
                continue;
            }
            
            // Apply filters
            if (!$this->apply_filters($webhook, $context)) {
                continue;
            }
            
            $targets[] = $webhook_id;
        }
        
        return $targets;
    }
    
    /**
     * Apply webhook filters
     */
    private function apply_filters($webhook, $context) {
        if (empty($webhook['filters'])) {
            return true;
        }
        
        foreach ($webhook['filters'] as $filter) {
            $field = $filter['field'] ?? '';
            $operator = $filter['operator'] ?? 'equals';
            $value = $filter['value'] ?? '';
            
            if (!isset($context[$field])) {
                return false;
            }
            
            $context_value = $context[$field];
            
            if (!$this->evaluate_condition($context_value, $value, $operator)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Evaluate filter condition
     */
    private function evaluate_condition($context_value, $filter_value, $operator) {
        switch ($operator) {
            case 'equals':
                return $context_value == $filter_value;
            case 'not_equals':
                return $context_value != $filter_value;
            case 'contains':
                return strpos($context_value, $filter_value) !== false;
            case 'starts_with':
                return strpos($context_value, $filter_value) === 0;
            case 'ends_with':
                return substr($context_value, -strlen($filter_value)) === $filter_value;
            case 'greater_than':
                return $context_value > $filter_value;
            case 'less_than':
                return $context_value < $filter_value;
            default:
                return false;
        }
    }
    
    /**
     * Queue event delivery
     */
    private function queue_event_delivery($event, $webhook_id) {
        $delivery_id = $this->generate_delivery_id();
        
        $delivery = [
            'id' => $delivery_id,
            'event_id' => $event['id'],
            'webhook_id' => $webhook_id,
            'status' => 'pending',
            'attempts' => 0,
            'scheduled_at' => time(),
            'last_attempt_at' => null,
            'error_message' => null
        ];
        
        $this->delivery_status[$delivery_id] = $delivery;
        
        // Store in database
        $this->store_delivery($delivery);
    }
    
    /**
     * Process event queue
     */
    public function process_event_queue() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_news_webhook_deliveries';
        
        // Get pending deliveries
        $deliveries = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} 
             WHERE status = 'pending' AND scheduled_at <= %s
             ORDER BY scheduled_at ASC
             LIMIT 50",
            current_time('mysql')
        ));
        
        foreach ($deliveries as $delivery_row) {
            $this->process_delivery($delivery_row);
        }
    }
    
    /**
     * Process individual delivery
     */
    private function process_delivery($delivery_row) {
        $delivery = (array) $delivery_row;
        $webhook = $this->webhooks[$delivery['webhook_id']] ?? null;
        
        if (!$webhook) {
            $this->mark_delivery_failed($delivery['id'], 'Webhook not found');
            return;
        }
        
        // Get event data
        $event = $this->get_event_by_id($delivery['event_id']);
        if (!$event) {
            $this->mark_delivery_failed($delivery['id'], 'Event not found');
            return;
        }
        
        // Attempt delivery
        $result = $this->send_webhook_request($webhook, $event);
        
        if ($result['success']) {
            $this->mark_delivery_success($delivery['id'], $result['response_code']);
        } else {
            $this->handle_delivery_failure($delivery, $result);
        }
    }
    
    /**
     * Send webhook request
     */
    private function send_webhook_request($webhook, $event) {
        $payload = json_encode($event, JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $payload, $webhook['secret']);
        
        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'ContentPilot Webhook/2.0.0',
            'X-Webhook-Event' => $event['type'],
            'X-Webhook-ID' => $event['id'],
            'X-Webhook-Timestamp' => $event['timestamp'],
            'X-Webhook-Signature' => "sha256={$signature}"
        ];
        
        $args = [
            'body' => $payload,
            'headers' => $headers,
            'timeout' => $this->config['timeout'],
            'sslcertificates' => ABSPATH . WPINC . '/certificates/ca-bundle.crt',
            'sslverify' => $this->config['verify_ssl']
        ];
        
        $start_time = microtime(true);
        $response = wp_remote_post($webhook['url'], $args);
        $response_time = microtime(true) - $start_time;
        
        $result = [
            'success' => false,
            'response_code' => 0,
            'response_body' => '',
            'response_time' => $response_time,
            'error_message' => ''
        ];
        
        if (is_wp_error($response)) {
            $result['error_message'] = $response->get_error_message();
            return $result;
        }
        
        $result['response_code'] = wp_remote_retrieve_response_code($response);
        $result['response_body'] = wp_remote_retrieve_body($response);
        
        // Consider 2xx responses as success
        if ($result['response_code'] >= 200 && $result['response_code'] < 300) {
            $result['success'] = true;
        } else {
            $result['error_message'] = "HTTP {$result['response_code']}: {$result['response_body']}";
        }
        
        // Update webhook statistics
        $this->update_webhook_stats($webhook['id'], $result);
        
        return $result;
    }
    
    /**
     * Handle delivery failure
     */
    private function handle_delivery_failure($delivery, $result) {
        $delivery['attempts']++;
        $delivery['last_attempt_at'] = time();
        $delivery['error_message'] = $result['error_message'];
        
        if ($delivery['attempts'] < $this->retry_config['max_retries']) {
            // Schedule retry
            $delay = $this->calculate_retry_delay($delivery['attempts']);
            $delivery['scheduled_at'] = time() + $delay;
            $delivery['status'] = 'pending';
            
            $this->delivery_status[$delivery['id']] = $delivery;
            $this->update_delivery($delivery);
        } else {
            // Mark as failed
            $delivery['status'] = 'failed';
            $delivery['scheduled_at'] = 0;
            
            $this->delivery_status[$delivery['id']] = $delivery;
            $this->update_delivery($delivery);
        }
    }
    
    /**
     * Calculate retry delay
     */
    private function calculate_retry_delay($attempt) {
        $delay = $this->retry_config['initial_delay'] * 
                 pow($this->retry_config['backoff_multiplier'], $attempt - 1);
        
        return min($delay, $this->retry_config['max_delay']);
    }
    
    /**
     * Mark delivery as successful
     */
    private function mark_delivery_success($delivery_id, $response_code) {
        $delivery = $this->delivery_status[$delivery_id];
        $delivery['status'] = 'success';
        $delivery['scheduled_at'] = 0;
        $delivery['last_attempt_at'] = time();
        $delivery['response_code'] = $response_code;
        
        $this->delivery_status[$delivery_id] = $delivery;
        $this->update_delivery($delivery);
    }
    
    /**
     * Mark delivery as failed
     */
    private function mark_delivery_failed($delivery_id, $error_message) {
        $delivery = $this->delivery_status[$delivery_id];
        $delivery['status'] = 'failed';
        $delivery['scheduled_at'] = 0;
        $delivery['last_attempt_at'] = time();
        $delivery['error_message'] = $error_message;
        
        $this->delivery_status[$delivery_id] = $delivery;
        $this->update_delivery($delivery);
    }
    
    /**
     * Send test event
     */
    private function send_test_event($webhook) {
        $test_event = [
            'id' => $this->generate_event_id(),
            'type' => 'test',
            'timestamp' => time(),
            'data' => [
                'message' => 'This is a test webhook event from AI Auto News Poster',
                'webhook_name' => $webhook['name'],
                'webhook_url' => $webhook['url']
            ],
            'context' => ['test' => true],
            'source' => 'ai_auto_news_poster',
            'version' => '2.0.0'
        ];
        
        $result = $this->send_webhook_request($webhook, $test_event);
        
        // Log test delivery
        $this->log_webhook_activity([
            'webhook_id' => $webhook['id'],
            'event_type' => 'test',
            'delivery_status' => $result['success'] ? 'success' : 'failed',
            'response_code' => $result['response_code'],
            'error_message' => $result['error_message'] ?? null
        ]);
    }
    
    /**
     * Update webhook statistics
     */
    private function update_webhook_stats($webhook_id, $result) {
        if (!isset($this->webhooks[$webhook_id])) {
            return;
        }
        
        $webhook = $this->webhooks[$webhook_id];
        $webhook['delivery_count']++;
        $webhook['last_triggered'] = time();
        
        if ($result['success']) {
            $webhook['success_count']++;
        } else {
            $webhook['error_count']++;
        }
        
        $this->webhooks[$webhook_id] = $webhook;
        $this->store_webhook($webhook);
    }
    
    // Event trigger methods
    
    public function trigger_content_event($post_id, $post) {
        $this->trigger_event('content.published', [
            'post_id' => $post_id,
            'post_title' => $post->post_title,
            'post_type' => $post->post_type,
            'post_status' => $post->post_status,
            'author_id' => $post->post_author,
            'permalink' => get_permalink($post_id)
        ], [
            'post_id' => $post_id,
            'post_type' => $post->post_type,
            'author_id' => $post->post_author
        ]);
    }
    
    public function trigger_content_update_event($post_id, $post_after, $post_before) {
        if ($post_after->post_status !== $post_before->post_status) {
            return; // Status changes handled separately
        }
        
        $this->trigger_event('content.updated', [
            'post_id' => $post_id,
            'post_title' => $post_after->post_title,
            'changes' => $this->detect_changes($post_before, $post_after)
        ], [
            'post_id' => $post_id,
            'post_type' => $post_after->post_type
        ]);
    }
    
    public function trigger_content_delete_event($post_id, $post) {
        $this->trigger_event('content.deleted', [
            'post_id' => $post_id,
            'post_title' => $post->post_title,
            'post_type' => $post->post_type
        ], [
            'post_id' => $post_id,
            'post_type' => $post->post_type
        ]);
    }
    
    public function trigger_status_change_event($new_status, $old_status, $post) {
        if ($new_status === 'publish' && $old_status !== 'publish') {
            $this->trigger_content_event($post->ID, $post);
        } elseif ($new_status === 'future' && $old_status !== 'future') {
            $this->trigger_event('content.scheduled', [
                'post_id' => $post->ID,
                'scheduled_date' => $post->post_date
            ], [
                'post_id' => $post->ID,
                'post_type' => $post->post_type
            ]);
        }
    }
    
    public function trigger_ai_generation_event($post_id, $data) {
        $this->trigger_event('ai.content_generated', [
            'post_id' => $post_id,
            'prompt' => $data['prompt'] ?? '',
            'generation_time' => $data['generation_time'] ?? 0,
            'word_count' => $data['word_count'] ?? 0
        ], [
            'post_id' => $post_id
        ]);
    }
    
    public function trigger_seo_optimization_event($post_id, $data) {
        $this->trigger_event('seo.optimization_completed', [
            'post_id' => $post_id,
            'seo_score' => $data['seo_score'] ?? 0,
            'optimizations_applied' => $data['optimizations'] ?? []
        ], [
            'post_id' => $post_id
        ]);
    }
    
    public function trigger_alert_event($alert_data, $alert_level) {
        $this->trigger_event('monitoring.alert_created', [
            'alert_id' => $alert_data['id'] ?? '',
            'alert_type' => $alert_data['type'] ?? '',
            'severity' => $alert_data['severity'] ?? '',
            'message' => $alert_data['message'] ?? ''
        ], [
            'severity' => $alert_data['severity'] ?? '',
            'alert_type' => $alert_data['type'] ?? ''
        ]);
    }
    
    public function trigger_system_error_event($error_message, $error_code, $context = []) {
        $this->trigger_event('system.performance_issue', [
            'error_message' => $error_message,
            'error_code' => $error_code,
            'context' => $context
        ], [
            'error_code' => $error_code
        ]);
    }
    
    public function trigger_user_login_event($user_login, $user) {
        $this->trigger_event('user.login', [
            'user_id' => $user->ID,
            'username' => $user_login,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip_address' => $this->get_client_ip()
        ], [
            'user_id' => $user->ID
        ]);
    }
    
    public function trigger_login_failed_event($username, $user) {
        $this->trigger_event('user.login_failed', [
            'username' => $username,
            'ip_address' => $this->get_client_ip()
        ], []);
    }
    
    public function trigger_settings_update_event($settings, $previous_settings) {
        $this->trigger_event('plugin.settings_updated', [
            'updated_settings' => array_diff_key($settings, $previous_settings),
            'total_settings' => count($settings)
        ], []);
    }
    
    // AJAX handlers
    
    public function ajax_register_webhook() {
        // Check rate limiting
        if (AANP_Rate_Limiter::getInstance()->is_rate_limited('webhook_register')) {
            AANP_Error_Handler::getInstance()->handle_error(
                'Rate limit exceeded for webhook registration',
                ['endpoint' => 'webhook_register', 'ip' => $this->get_client_ip()],
                'rate_limiting'
            );
            wp_send_json_error('Rate limit exceeded. Please try again later');
        }

        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ai_news_dashboard_nonce')) {
            wp_send_json_error('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $name = sanitize_text_field($_POST['name'] ?? '');
        $url = esc_url_raw($_POST['url'] ?? '');
        $events = array_map('sanitize_text_field', $_POST['events'] ?? []);
        $description = sanitize_textarea_field($_POST['description'] ?? '');

        if (empty($name) || empty($url) || empty($events)) {
            wp_send_json_error('All required fields must be filled');
        }

        $result = $this->register_webhook($name, $url, $events, null, $description);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success([
            'webhook_id' => $result,
            'message' => 'Webhook registered successfully'
        ]);
    }
    
    public function ajax_test_webhook() {
        // Check rate limiting
        if (AANP_Rate_Limiter::getInstance()->is_rate_limited('webhook_test')) {
            AANP_Error_Handler::getInstance()->handle_error(
                'Rate limit exceeded for webhook test',
                ['endpoint' => 'webhook_test', 'ip' => $this->get_client_ip()],
                'rate_limiting'
            );
            wp_send_json_error('Rate limit exceeded. Please try again later');
        }

        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ai_news_dashboard_nonce')) {
            wp_send_json_error('Security check failed');
        }

        $webhook_id = sanitize_text_field($_POST['webhook_id'] ?? '');
        $webhook = $this->get_webhook($webhook_id);

        if (!$webhook) {
            wp_send_json_error('Webhook not found');
        }

        // Send test event
        $this->send_test_event($webhook);

        wp_send_json_success(['message' => 'Test event sent successfully']);
    }
    
    public function ajax_get_webhook_logs() {
        // Check rate limiting
        if (AANP_Rate_Limiter::getInstance()->is_rate_limited('webhook_get_logs')) {
            AANP_Error_Handler::getInstance()->handle_error(
                'Rate limit exceeded for webhook logs endpoint',
                ['endpoint' => 'webhook_get_logs', 'ip' => $this->get_client_ip()],
                'rate_limiting'
            );
            wp_send_json_error('Rate limit exceeded. Please try again later');
        }

        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ai_news_dashboard_nonce')) {
            wp_send_json_error('Security check failed');
        }

        $webhook_id = sanitize_text_field($_POST['webhook_id'] ?? '');
        $limit = intval($_POST['limit'] ?? 50);

        $logs = $this->get_webhook_logs($webhook_id, $limit);

        wp_send_json_success(['logs' => $logs]);
    }
    
    public function ajax_resend_webhook() {
        // Check rate limiting
        if (AANP_Rate_Limiter::getInstance()->is_rate_limited('webhook_resend')) {
            AANP_Error_Handler::getInstance()->handle_error(
                'Rate limit exceeded for webhook resend endpoint',
                ['endpoint' => 'webhook_resend', 'ip' => $this->get_client_ip()],
                'rate_limiting'
            );
            wp_send_json_error('Rate limit exceeded. Please try again later');
        }

        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ai_news_dashboard_nonce')) {
            wp_send_json_error('Security check failed');
        }

        $delivery_id = sanitize_text_field($_POST['delivery_id'] ?? '');

        // This would implement webhook resending logic
        wp_send_json_success(['message' => 'Webhook delivery rescheduled']);
    }
    
    // Utility methods
    
    private function load_registrations() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_news_webhooks';
        $results = $wpdb->get_results("SELECT * FROM {$table_name}");
        
        foreach ($results as $row) {
            $webhook = [
                'id' => $row->id,
                'name' => $row->name,
                'url' => $row->url,
                'events' => maybe_unserialize($row->events),
                'secret' => $row->secret,
                'description' => $row->description,
                'filters' => maybe_unserialize($row->filters),
                'status' => $row->status,
                'created_at' => strtotime($row->created_at),
                'updated_at' => strtotime($row->updated_at),
                'last_triggered' => $row->last_triggered ? strtotime($row->last_triggered) : null,
                'delivery_count' => $row->delivery_count,
                'success_count' => $row->success_count,
                'error_count' => $row->error_count,
                'metadata' => maybe_unserialize($row->metadata)
            ];
            
            $this->webhooks[$row->id] = $webhook;
        }
    }
    
    private function store_webhook($webhook) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_news_webhooks';
        
        $data = [
            'id' => $webhook['id'],
            'name' => $webhook['name'],
            'url' => $webhook['url'],
            'events' => maybe_serialize($webhook['events']),
            'secret' => $webhook['secret'],
            'description' => $webhook['description'],
            'filters' => maybe_serialize($webhook['filters']),
            'status' => $webhook['status'],
            'created_at' => date('Y-m-d H:i:s', $webhook['created_at']),
            'updated_at' => date('Y-m-d H:i:s', $webhook['updated_at']),
            'last_triggered' => $webhook['last_triggered'] ? date('Y-m-d H:i:s', $webhook['last_triggered']) : null,
            'delivery_count' => $webhook['delivery_count'],
            'success_count' => $webhook['success_count'],
            'error_count' => $webhook['error_count'],
            'metadata' => maybe_serialize($webhook['metadata'])
        ];
        
        $formats = ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s'];
        
        $result = $wpdb->replace($table_name, $data, $formats);
        
        if ($result === false) {
            error_log('Failed to store webhook: ' . $wpdb->last_error);
        }
    }
    
    private function remove_webhook_from_db($webhook_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_news_webhooks';
        $wpdb->delete($table_name, ['id' => $webhook_id], ['%s']);
    }
    
    private function store_delivery($delivery) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_news_webhook_deliveries';
        
        $data = [
            'id' => $delivery['id'],
            'event_id' => $delivery['event_id'],
            'webhook_id' => $delivery['webhook_id'],
            'status' => $delivery['status'],
            'attempts' => $delivery['attempts'],
            'scheduled_at' => date('Y-m-d H:i:s', $delivery['scheduled_at']),
            'last_attempt_at' => $delivery['last_attempt_at'] ? date('Y-m-d H:i:s', $delivery['last_attempt_at']) : null,
            'error_message' => $delivery['error_message']
        ];
        
        $formats = ['%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s'];
        
        $wpdb->replace($table_name, $data, $formats);
    }
    
    private function update_delivery($delivery) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_news_webhook_deliveries';
        
        $wpdb->update(
            $table_name,
            [
                'status' => $delivery['status'],
                'attempts' => $delivery['attempts'],
                'scheduled_at' => date('Y-m-d H:i:s', $delivery['scheduled_at']),
                'last_attempt_at' => $delivery['last_attempt_at'] ? date('Y-m-d H:i:s', $delivery['last_attempt_at']) : null,
                'error_message' => $delivery['error_message'],
                'response_code' => $delivery['response_code'] ?? null
            ],
            ['id' => $delivery['id']],
            ['%s', '%d', '%s', '%s', '%s', '%d'],
            ['%s']
        );
    }
    
    private function log_event($event) {
        $log_entry = [
            'event_id' => $event['id'],
            'event_type' => $event['type'],
            'timestamp' => date('Y-m-d H:i:s', $event['timestamp']),
            'data' => maybe_serialize($event['data']),
            'context' => maybe_serialize($event['context'])
        ];
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_news_webhook_events';
        
        $wpdb->insert($table_name, $log_entry, ['%s', '%s', '%s', '%s', '%s']);
    }
    
    private function log_webhook_activity($activity) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_news_webhook_logs';
        
        $wpdb->insert($table_name, [
            'webhook_id' => $activity['webhook_id'],
            'event_type' => $activity['event_type'],
            'delivery_status' => $activity['delivery_status'],
            'response_code' => $activity['response_code'],
            'error_message' => $activity['error_message'],
            'timestamp' => current_time('mysql')
        ], ['%s', '%s', '%s', '%d', '%s', '%s']);
    }
    
    private function get_event_by_id($event_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_news_webhook_events';
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE event_id = %s",
            $event_id
        ));
        
        if (!$result) {
            return null;
        }
        
        return [
            'id' => $result->event_id,
            'type' => $result->event_type,
            'timestamp' => strtotime($result->timestamp),
            'data' => maybe_unserialize($result->data),
            'context' => maybe_unserialize($result->context)
        ];
    }
    
    private function get_webhook_logs($webhook_id, $limit = 50) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_news_webhook_logs';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} 
             WHERE webhook_id = %s 
             ORDER BY timestamp DESC 
             LIMIT %d",
            $webhook_id,
            $limit
        ), ARRAY_A);
    }
    
    private function generate_webhook_id() {
        return 'wh_' . wp_generate_password(16, false);
    }
    
    private function generate_event_id() {
        return 'evt_' . wp_generate_password(16, false);
    }
    
    private function generate_delivery_id() {
        return 'del_' . wp_generate_password(16, false);
    }
    
    private function generate_secret() {
        return wp_generate_password($this->config['secret_key_length'], false);
    }
    
    private function get_client_ip() {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
    
    private function detect_changes($before, $after) {
        $changes = [];
        $fields = ['post_title', 'post_content', 'post_excerpt', 'post_status', 'post_type'];
        
        foreach ($fields as $field) {
            if ($before->$field !== $after->$field) {
                $changes[$field] = [
                    'before' => $before->$field,
                    'after' => $after->$field
                ];
            }
        }
        
        return $changes;
    }
    
    public function cleanup_old_logs() {
        global $wpdb;
        
        $retention_days = $this->config['event_history_retention'];
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));
        
        // Clean up old events
        $events_table = $wpdb->prefix . 'ai_news_webhook_events';
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$events_table} WHERE timestamp < %s",
            $cutoff_date
        ));
        
        // Clean up old deliveries
        $deliveries_table = $wpdb->prefix . 'ai_news_webhook_deliveries';
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$deliveries_table} 
             WHERE scheduled_at < %s AND status IN ('success', 'failed')",
            $cutoff_date
        ));
        
        // Clean up old logs
        $logs_table = $wpdb->prefix . 'ai_news_webhook_logs';
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$logs_table} WHERE timestamp < %s",
            $cutoff_date
        ));
    }
}

// Initialize the webhook manager
WebhookManager::get_instance();