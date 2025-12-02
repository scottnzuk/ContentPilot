<?php
/**
 * Intelligent Alerts Manager
 * 
 * Provides intelligent alerting system with escalation, multiple notification channels,
 * alert grouping, and machine learning-based alert prioritization.
 *
 * @package AI_Auto_News_Poster
 * @subpackage Includes/Monitoring
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class AlertsManager {
    
    /**
     * Alert severity levels
     */
    const SEVERITY_CRITICAL = 'critical';
    const SEVERITY_HIGH = 'high';
    const SEVERITY_MEDIUM = 'medium';
    const SEVERITY_LOW = 'low';
    const SEVERITY_INFO = 'info';
    
    /**
     * Alert statuses
     */
    const STATUS_ACTIVE = 'active';
    const STATUS_RESOLVED = 'resolved';
    const STATUS_SUPPRESSED = 'suppressed';
    const STATUS_ACKNOWLEDGED = 'acknowledged';
    
    /**
     * Notification channels
     */
    const CHANNEL_DASHBOARD = 'dashboard';
    const CHANNEL_EMAIL = 'email';
    const CHANNEL_WEBHOOK = 'webhook';
    const CHANNEL_SLACK = 'slack';
    const CHANNEL_SMS = 'sms';
    const CHANNEL_PUSH = 'push';
    
    /**
     * Alert manager instance (singleton)
     */
    private static $instance = null;
    
    /**
     * Active alerts storage
     */
    private $active_alerts = [];
    
    /**
     * Alert rules and thresholds
     */
    private $alert_rules = [];
    
    /**
     * Notification channels configuration
     */
    private $channels = [];
    
    /**
     * Alert escalation schedule
     */
    private $escalation_schedule = [];
    
    /**
     * Suppression rules
     */
    private $suppression_rules = [];
    
    /**
     * Alert statistics
     */
    private $alert_stats = [];
    
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
     * Initialize alerts manager
     */
    private function init() {
        $this->load_configuration();
        $this->setup_hooks();
        $this->load_active_alerts();
        $this->schedule_maintenance();
    }
    
    /**
     * Load configuration
     */
    private function load_configuration() {
        // Load alert rules
        $this->alert_rules = get_option('ai_news_alert_rules', $this->get_default_alert_rules());
        
        // Load notification channels
        $this->channels = get_option('ai_news_notification_channels', $this->get_default_channels());
        
        // Load escalation schedule
        $this->escalation_schedule = get_option('ai_news_escalation_schedule', $this->get_default_escalation_schedule());
        
        // Load suppression rules
        $this->suppression_rules = get_option('ai_news_suppression_rules', []);
        
        // Load alert statistics
        $this->alert_stats = get_option('ai_news_alert_stats', []);
    }
    
    /**
     * Set up WordPress hooks
     */
    private function setup_hooks() {
        // Performance monitoring alerts
        add_action('ai_news_performance_alert', [$this, 'handle_performance_alert'], 10, 2);
        add_action('ai_news_metric_threshold_exceeded', [$this, 'handle_metric_alert'], 10, 3);
        
        // System alerts
        add_action('ai_news_system_error', [$this, 'handle_system_error'], 10, 3);
        add_action('ai_news_security_alert', [$this, 'handle_security_alert'], 10, 3);
        
        // Content alerts
        add_action('ai_news_content_alert', [$this, 'handle_content_alert'], 10, 2);
        add_action('ai_news_seo_alert', [$this, 'handle_seo_alert'], 10, 2);
        
        // API alerts
        add_action('ai_news_api_alert', [$this, 'handle_api_alert'], 10, 2);
        
        // User action alerts
        add_action('ai_news_user_alert', [$this, 'handle_user_alert'], 10, 2);
        
        // AJAX handlers
        add_action('wp_ajax_ai_news_get_alerts', [$this, 'ajax_get_alerts']);
        add_action('wp_ajax_ai_news_acknowledge_alert', [$this, 'ajax_acknowledge_alert']);
        add_action('wp_ajax_ai_news_resolve_alert', [$this, 'ajax_resolve_alert']);
        add_action('wp_ajax_ai_news_suppress_alert', [$this, 'ajax_suppress_alert']);
        add_action('wp_ajax_ai_news_update_alert_settings', [$this, 'ajax_update_alert_settings']);
        
        // Scheduled maintenance
        add_action('ai_news_process_alert_escalations', [$this, 'process_escalations']);
        add_action('ai_news_cleanup_old_alerts', [$this, 'cleanup_old_alerts']);
        add_action('ai_news_check_alert_thresholds', [$this, 'check_threshold_violations']);
    }
    
    /**
     * Create and send alert
     */
    public function create_alert($type, $severity, $title, $message, $context = [], $auto_resolve = false) {
        // Check if alert should be suppressed
        if ($this->should_suppress_alert($type, $severity, $context)) {
            return null;
        }
        
        // Generate alert ID
        $alert_id = $this->generate_alert_id($type, $severity);
        
        // Create alert object
        $alert = [
            'id' => $alert_id,
            'type' => $type,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'context' => $context,
            'status' => self::STATUS_ACTIVE,
            'created_at' => time(),
            'updated_at' => time(),
            'acknowledged_by' => null,
            'acknowledged_at' => null,
            'resolved_by' => null,
            'resolved_at' => null,
            'notification_sent' => false,
            'escalation_count' => 0,
            'auto_resolve' => $auto_resolve,
            'tags' => $this->generate_alert_tags($type, $severity, $context)
        ];
        
        // Check for duplicate alerts
        if ($this->is_duplicate_alert($alert)) {
            return null;
        }
        
        // Store alert
        $this->store_alert($alert);
        
        // Add to active alerts
        $this->active_alerts[$alert_id] = $alert;
        
        // Send notifications
        $this->send_notifications($alert);
        
        // Process escalation if needed
        $this->schedule_escalation($alert);
        
        // Log alert creation
        $this->log_alert_event('created', $alert);
        
        return $alert_id;
    }
    
    /**
     * Handle performance alert
     */
    public function handle_performance_alert($metric_data, $alert_level) {
        $this->create_alert(
            'performance',
            $alert_level,
            "Performance Alert: {$alert_level['metric']}",
            $alert_level['message'],
            $metric_data,
            false
        );
    }
    
    /**
     * Handle metric threshold alert
     */
    public function handle_metric_alert($metric_name, $value, $threshold) {
        $severity = $this->calculate_metric_severity($metric_name, $value, $threshold);
        
        $this->create_alert(
            'metric',
            $severity,
            "Metric Threshold Exceeded: {$metric_name}",
            "Metric {$metric_name} exceeded threshold: {$value} > {$threshold}",
            [
                'metric_name' => $metric_name,
                'value' => $value,
                'threshold' => $threshold,
                'percentage' => ($value / $threshold) * 100
            ],
            false
        );
    }
    
    /**
     * Handle system error alert
     */
    public function handle_system_error($error_message, $error_code, $context = []) {
        $severity = $this->determine_error_severity($error_code, $context);
        
        $this->create_alert(
            'system',
            $severity,
            "System Error (Code: {$error_code})",
            $error_message,
            array_merge(['error_code' => $error_code], $context),
            $severity === self::SEVERITY_LOW
        );
    }
    
    /**
     * Handle security alert
     */
    public function handle_security_alert($threat_type, $severity, $details = []) {
        $this->create_alert(
            'security',
            $severity,
            "Security Alert: {$threat_type}",
            "Security threat detected: {$threat_type}",
            $details,
            false
        );
    }
    
    /**
     * Handle content alert
     */
    public function handle_content_alert($content_type, $alert_data) {
        $severity = $alert_data['severity'] ?? self::SEVERITY_MEDIUM;
        
        $this->create_alert(
            'content',
            $severity,
            "Content Alert: {$content_type}",
            $alert_data['message'],
            $alert_data,
            $severity === self::SEVERITY_LOW
        );
    }
    
    /**
     * Handle SEO alert
     */
    public function handle_seo_alert($seo_type, $alert_data) {
        $severity = $alert_data['severity'] ?? self::SEVERITY_MEDIUM;
        
        $this->create_alert(
            'seo',
            $severity,
            "SEO Alert: {$seo_type}",
            $alert_data['message'],
            $alert_data,
            $severity === self::SEVERITY_LOW
        );
    }
    
    /**
     * Handle API alert
     */
    public function handle_api_alert($api_type, $alert_data) {
        $severity = $alert_data['severity'] ?? self::SEVERITY_MEDIUM;
        
        $this->create_alert(
            'api',
            $severity,
            "API Alert: {$api_type}",
            $alert_data['message'],
            $alert_data,
            $severity === self::SEVERITY_LOW
        );
    }
    
    /**
     * Handle user alert
     */
    public function handle_user_alert($user_type, $alert_data) {
        $severity = $alert_data['severity'] ?? self::SEVERITY_INFO;
        
        $this->create_alert(
            'user',
            $severity,
            "User Alert: {$user_type}",
            $alert_data['message'],
            $alert_data,
            true // User alerts are typically auto-resolved
        );
    }
    
    /**
     * Send notifications for alert
     */
    private function send_notifications($alert) {
        // Get applicable notification channels
        $channels = $this->get_notification_channels_for_alert($alert);
        
        foreach ($channels as $channel) {
            $this->send_notification($channel, $alert);
        }
        
        // Mark as notification sent
        $alert['notification_sent'] = true;
        $alert['updated_at'] = time();
        $this->update_alert($alert);
    }
    
    /**
     * Send notification via specific channel
     */
    private function send_notification($channel, $alert) {
        switch ($channel['type']) {
            case self::CHANNEL_EMAIL:
                $this->send_email_notification($channel, $alert);
                break;
            case self::CHANNEL_WEBHOOK:
                $this->send_webhook_notification($channel, $alert);
                break;
            case self::CHANNEL_SLACK:
                $this->send_slack_notification($channel, $alert);
                break;
            case self::CHANNEL_DASHBOARD:
                $this->send_dashboard_notification($channel, $alert);
                break;
        }
    }
    
    /**
     * Send email notification
     */
    private function send_email_notification($channel, $alert) {
        if (!isset($channel['recipients']) || empty($channel['recipients'])) {
            return;
        }
        
        $subject = $this->get_notification_subject($alert);
        $message = $this->get_notification_message($alert, 'email');
        
        foreach ($channel['recipients'] as $recipient) {
            wp_mail($recipient, $subject, $message, $this->get_email_headers());
        }
        
        // Log notification
        $this->log_notification_sent($channel['type'], $alert['id'], $channel['recipients']);
    }
    
    /**
     * Send webhook notification
     */
    private function send_webhook_notification($channel, $alert) {
        if (!isset($channel['url'])) {
            return;
        }
        
        $payload = [
            'alert' => $alert,
            'timestamp' => time(),
            'source' => 'ai_auto_news_poster'
        ];
        
        $args = [
            'body' => json_encode($payload),
            'headers' => [
                'Content-Type' => 'application/json',
                'User-Agent' => 'AI News Alerts/1.0'
            ],
            'timeout' => 30
        ];
        
        $response = wp_remote_post($channel['url'], $args);
        
        if (is_wp_error($response)) {
            error_log('AI News Alert Webhook Error: ' . $response->get_error_message());
        }
        
        // Log notification
        $this->log_notification_sent($channel['type'], $alert['id'], $channel['url']);
    }
    
    /**
     * Send Slack notification
     */
    private function send_slack_notification($channel, $alert) {
        if (!isset($channel['webhook_url'])) {
            return;
        }
        
        $color = $this->get_slack_color_for_severity($alert['severity']);
        $payload = [
            'text' => $this->get_notification_subject($alert),
            'attachments' => [
                [
                    'color' => $color,
                    'title' => $alert['title'],
                    'text' => $alert['message'],
                    'fields' => $this->format_alert_fields($alert),
                    'footer' => 'AI Auto News Poster',
                    'ts' => $alert['created_at']
                ]
            ]
        ];
        
        wp_remote_post($channel['webhook_url'], [
            'body' => json_encode($payload),
            'headers' => ['Content-Type' => 'application/json']
        ]);
        
        // Log notification
        $this->log_notification_sent($channel['type'], $alert['id'], $channel['webhook_url']);
    }
    
    /**
     * Send dashboard notification
     */
    private function send_dashboard_notification($channel, $alert) {
        // Store in transient for dashboard access
        $transient_key = 'ai_news_alert_' . $alert['id'];
        set_transient($transient_key, $alert, 86400); // 24 hours
        
        // Add to dashboard alerts list
        $dashboard_alerts = get_transient('ai_news_dashboard_alerts') ?: [];
        array_unshift($dashboard_alerts, $alert);
        
        // Keep only last 100 alerts
        $dashboard_alerts = array_slice($dashboard_alerts, 0, 100);
        set_transient('ai_news_dashboard_alerts', $dashboard_alerts, 86400);
        
        // Log notification
        $this->log_notification_sent($channel['type'], $alert['id'], 'dashboard');
    }
    
    /**
     * Schedule alert escalation
     */
    private function schedule_escalation($alert) {
        if (!isset($this->escalation_schedule[$alert['severity']])) {
            return;
        }
        
        $escalation_intervals = $this->escalation_schedule[$alert['severity']];
        
        foreach ($escalation_intervals as $interval) {
            wp_schedule_single_event(
                time() + $interval,
                'ai_news_escalate_alert',
                [$alert['id'], count($escalation_intervals)]
            );
        }
    }
    
    /**
     * Process alert escalations
     */
    public function process_escalations() {
        foreach ($this->active_alerts as $alert_id => $alert) {
            if ($alert['status'] !== self::STATUS_ACTIVE) {
                continue;
            }
            
            $escalation_time = $this->get_escalation_time($alert);
            if ($escalation_time && time() > $escalation_time) {
                $this->escalate_alert($alert);
            }
        }
    }
    
    /**
     * Escalate alert
     */
    private function escalate_alert($alert) {
        // Increment escalation count
        $alert['escalation_count']++;
        $alert['updated_at'] = time();
        
        // Send escalation notification
        $this->send_escalation_notification($alert);
        
        // Update alert
        $this->update_alert($alert);
        
        // Log escalation
        $this->log_alert_event('escalated', $alert);
    }
    
    /**
     * Check threshold violations
     */
    public function check_threshold_violations() {
        foreach ($this->alert_rules as $rule_name => $rule) {
            $current_value = $this->get_metric_value($rule['metric']);
            $threshold_value = $rule['threshold'];
            
            if ($this->is_threshold_exceeded($current_value, $threshold_value, $rule['operator'])) {
                $this->handle_metric_alert($rule['metric'], $current_value, $threshold_value);
            }
        }
    }
    
    /**
     * Acknowledge alert
     */
    public function acknowledge_alert($alert_id, $user_id = null) {
        if (!isset($this->active_alerts[$alert_id])) {
            return false;
        }
        
        $alert = $this->active_alerts[$alert_id];
        $alert['status'] = self::STATUS_ACKNOWLEDGED;
        $alert['acknowledged_by'] = $user_id ?: get_current_user_id();
        $alert['acknowledged_at'] = time();
        $alert['updated_at'] = time();
        
        $this->update_alert($alert);
        $this->log_alert_event('acknowledged', $alert);
        
        return true;
    }
    
    /**
     * Resolve alert
     */
    public function resolve_alert($alert_id, $user_id = null, $resolution_note = '') {
        if (!isset($this->active_alerts[$alert_id])) {
            return false;
        }
        
        $alert = $this->active_alerts[$alert_id];
        $alert['status'] = self::STATUS_RESOLVED;
        $alert['resolved_by'] = $user_id ?: get_current_user_id();
        $alert['resolved_at'] = time();
        $alert['resolution_note'] = $resolution_note;
        $alert['updated_at'] = time();
        
        $this->update_alert($alert);
        
        // Remove from active alerts
        unset($this->active_alerts[$alert_id]);
        
        $this->log_alert_event('resolved', $alert);
        
        return true;
    }
    
    /**
     * Suppress alert
     */
    public function suppress_alert($alert_id, $duration = 3600, $reason = '') {
        if (!isset($this->active_alerts[$alert_id])) {
            return false;
        }
        
        $alert = $this->active_alerts[$alert_id];
        $alert['status'] = self::STATUS_SUPPRESSED;
        $alert['suppressed_until'] = time() + $duration;
        $alert['suppression_reason'] = $reason;
        $alert['updated_at'] = time();
        
        $this->update_alert($alert);
        $this->log_alert_event('suppressed', $alert);
        
        return true;
    }
    
    /**
     * Get alerts with filtering
     */
    public function get_alerts($filters = []) {
        $alerts = array_values($this->active_alerts);
        
        // Apply filters
        if (!empty($filters)) {
            $alerts = array_filter($alerts, function($alert) use ($filters) {
                foreach ($filters as $filter => $value) {
                    if (isset($alert[$filter]) && $alert[$filter] !== $value) {
                        return false;
                    }
                }
                return true;
            });
        }
        
        // Sort by creation time (newest first)
        usort($alerts, function($a, $b) {
            return $b['created_at'] - $a['created_at'];
        });
        
        return $alerts;
    }
    
    /**
     * Get alert statistics
     */
    public function get_alert_statistics($time_range = '24h') {
        $cutoff_time = $this->parse_time_range($time_range);
        
        $stats = [
            'total' => 0,
            'by_severity' => [],
            'by_type' => [],
            'resolved' => 0,
            'active' => 0,
            'average_resolution_time' => 0
        ];
        
        $total_resolution_time = 0;
        $resolved_count = 0;
        
        foreach ($this->active_alerts as $alert) {
            if ($alert['created_at'] < $cutoff_time) {
                continue;
            }
            
            $stats['total']++;
            $stats['by_severity'][$alert['severity']] = ($stats['by_severity'][$alert['severity']] ?? 0) + 1;
            $stats['by_type'][$alert['type']] = ($stats['by_type'][$alert['type']] ?? 0) + 1;
            
            if ($alert['status'] === self::STATUS_RESOLVED) {
                $stats['resolved']++;
                if ($alert['resolved_at']) {
                    $total_resolution_time += ($alert['resolved_at'] - $alert['created_at']);
                    $resolved_count++;
                }
            } else {
                $stats['active']++;
            }
        }
        
        if ($resolved_count > 0) {
            $stats['average_resolution_time'] = $total_resolution_time / $resolved_count;
        }
        
        return $stats;
    }
    
    /**
     * AJAX handler for getting alerts
     */
    public function ajax_get_alerts() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ai_news_dashboard_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $filters = [
            'status' => $_POST['status'] ?? null,
            'severity' => $_POST['severity'] ?? null,
            'type' => $_POST['type'] ?? null
        ];
        
        // Remove null values
        $filters = array_filter($filters);
        
        $alerts = $this->get_alerts($filters);
        $stats = $this->get_alert_statistics($_POST['time_range'] ?? '24h');
        
        wp_send_json_success([
            'alerts' => $alerts,
            'statistics' => $stats
        ]);
    }
    
    /**
     * AJAX handler for acknowledging alert
     */
    public function ajax_acknowledge_alert() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ai_news_dashboard_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $alert_id = $_POST['alert_id'] ?? '';
        if (empty($alert_id)) {
            wp_send_json_error('Alert ID is required');
        }
        
        $success = $this->acknowledge_alert($alert_id);
        
        if ($success) {
            wp_send_json_success(['message' => 'Alert acknowledged successfully']);
        } else {
            wp_send_json_error('Failed to acknowledge alert');
        }
    }
    
    /**
     * AJAX handler for resolving alert
     */
    public function ajax_resolve_alert() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ai_news_dashboard_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $alert_id = $_POST['alert_id'] ?? '';
        $resolution_note = $_POST['resolution_note'] ?? '';
        
        if (empty($alert_id)) {
            wp_send_json_error('Alert ID is required');
        }
        
        $success = $this->resolve_alert($alert_id, null, $resolution_note);
        
        if ($success) {
            wp_send_json_success(['message' => 'Alert resolved successfully']);
        } else {
            wp_send_json_error('Failed to resolve alert');
        }
    }
    
    /**
     * AJAX handler for suppressing alert
     */
    public function ajax_suppress_alert() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ai_news_dashboard_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $alert_id = $_POST['alert_id'] ?? '';
        $duration = intval($_POST['duration'] ?? 3600);
        $reason = $_POST['reason'] ?? '';
        
        if (empty($alert_id)) {
            wp_send_json_error('Alert ID is required');
        }
        
        $success = $this->suppress_alert($alert_id, $duration, $reason);
        
        if ($success) {
            wp_send_json_success(['message' => 'Alert suppressed successfully']);
        } else {
            wp_send_json_error('Failed to suppress alert');
        }
    }
    
    /**
     * AJAX handler for updating alert settings
     */
    public function ajax_update_alert_settings() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ai_news_dashboard_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $settings = [
            'alert_rules' => $_POST['alert_rules'] ?? [],
            'notification_channels' => $_POST['notification_channels'] ?? [],
            'escalation_schedule' => $_POST['escalation_schedule'] ?? []
        ];
        
        update_option('ai_news_alert_settings', $settings);
        
        wp_send_json_success(['message' => 'Alert settings updated successfully']);
    }
    
    // Utility methods
    
    private function load_active_alerts() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_news_monitoring_alerts';
        
        $results = $wpdb->get_results(
            "SELECT * FROM {$table_name} 
             WHERE status IN ('active', 'acknowledged') 
             ORDER BY created_at DESC"
        );
        
        foreach ($results as $row) {
            $alert = [
                'id' => $row->id,
                'type' => $row->type,
                'severity' => $row->severity,
                'title' => $row->title,
                'message' => $row->message,
                'status' => $row->status,
                'created_at' => strtotime($row->created_at),
                'acknowledged_at' => $row->acknowledged_at ? strtotime($row->acknowledged_at) : null,
                'resolved_at' => $row->resolved_at ? strtotime($row->resolved_at) : null,
                'context' => maybe_unserialize($row->context)
            ];
            
            $this->active_alerts[$row->id] = $alert;
        }
    }
    
    private function store_alert($alert) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_news_monitoring_alerts';
        
        $wpdb->insert(
            $table_name,
            [
                'id' => $alert['id'],
                'type' => $alert['type'],
                'severity' => $alert['severity'],
                'title' => $alert['title'],
                'message' => $alert['message'],
                'status' => $alert['status'],
                'context' => maybe_serialize($alert['context']),
                'created_at' => date('Y-m-d H:i:s', $alert['created_at']),
                'acknowledged_at' => $alert['acknowledged_at'] ? date('Y-m-d H:i:s', $alert['acknowledged_at']) : null,
                'resolved_at' => $alert['resolved_at'] ? date('Y-m-d H:i:s', $alert['resolved_at']) : null
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
    }
    
    private function update_alert($alert) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_news_monitoring_alerts';
        
        $wpdb->update(
            $table_name,
            [
                'status' => $alert['status'],
                'context' => maybe_serialize($alert['context']),
                'acknowledged_at' => $alert['acknowledged_at'] ? date('Y-m-d H:i:s', $alert['acknowledged_at']) : null,
                'resolved_at' => $alert['resolved_at'] ? date('Y-m-d H:i:s', $alert['resolved_at']) : null,
                'updated_at' => date('Y-m-d H:i:s', $alert['updated_at'])
            ],
            ['id' => $alert['id']],
            ['%s', '%s', '%s', '%s', '%s'],
            ['%s']
        );
    }
    
    private function generate_alert_id($type, $severity) {
        return uniqid("alert_{$type}_{$severity}_", true);
    }
    
    private function should_suppress_alert($type, $severity, $context) {
        foreach ($this->suppression_rules as $rule) {
            if ($this->rule_matches($rule, $type, $severity, $context)) {
                return true;
            }
        }
        return false;
    }
    
    private function is_duplicate_alert($alert) {
        // Check for recent similar alerts (within 5 minutes)
        $time_threshold = time() - 300;
        
        foreach ($this->active_alerts as $existing_alert) {
            if ($existing_alert['created_at'] > $time_threshold &&
                $existing_alert['type'] === $alert['type'] &&
                $existing_alert['severity'] === $alert['severity'] &&
                $existing_alert['title'] === $alert['title']) {
                return true;
            }
        }
        
        return false;
    }
    
    private function get_notification_channels_for_alert($alert) {
        $channels = [];
        
        foreach ($this->channels as $channel) {
            if ($this->channel_should_receive_alert($channel, $alert)) {
                $channels[] = $channel;
            }
        }
        
        return $channels;
    }
    
    private function channel_should_receive_alert($channel, $alert) {
        // Check if channel is enabled
        if (!$channel['enabled'] ?? true) {
            return false;
        }
        
        // Check severity filter
        $min_severity = $channel['min_severity'] ?? self::SEVERITY_INFO;
        if (!$this->is_severity_meets_threshold($alert['severity'], $min_severity)) {
            return false;
        }
        
        // Check type filter
        $allowed_types = $channel['alert_types'] ?? [];
        if (!empty($allowed_types) && !in_array($alert['type'], $allowed_types)) {
            return false;
        }
        
        return true;
    }
    
    private function is_severity_meets_threshold($alert_severity, $min_severity) {
        $severity_levels = [
            self::SEVERITY_INFO => 0,
            self::SEVERITY_LOW => 1,
            self::SEVERITY_MEDIUM => 2,
            self::SEVERITY_HIGH => 3,
            self::SEVERITY_CRITICAL => 4
        ];
        
        return ($severity_levels[$alert_severity] ?? 0) >= ($severity_levels[$min_severity] ?? 0);
    }
    
    private function calculate_metric_severity($metric_name, $value, $threshold) {
        $percentage = ($value / $threshold) * 100;
        
        if ($percentage > 200) return self::SEVERITY_CRITICAL;
        if ($percentage > 150) return self::SEVERITY_HIGH;
        if ($percentage > 120) return self::SEVERITY_MEDIUM;
        if ($percentage > 100) return self::SEVERITY_LOW;
        
        return self::SEVERITY_INFO;
    }
    
    private function determine_error_severity($error_code, $context) {
        // Critical errors
        if (in_array($error_code, [500, 502, 503, 504])) {
            return self::SEVERITY_CRITICAL;
        }
        
        // High priority errors
        if (in_array($error_code, [401, 403, 404])) {
            return self::SEVERITY_HIGH;
        }
        
        // Check for security-related context
        if (isset($context['security']) && $context['security']) {
            return self::SEVERITY_HIGH;
        }
        
        return self::SEVERITY_MEDIUM;
    }
    
    private function get_escalation_time($alert) {
        if (!isset($this->escalation_schedule[$alert['severity']])) {
            return false;
        }
        
        $escalation_intervals = $this->escalation_schedule[$alert['severity']];
        $escalation_index = min($alert['escalation_count'], count($escalation_intervals) - 1);
        
        return $alert['created_at'] + $escalation_intervals[$escalation_index];
    }
    
    private function get_notification_subject($alert) {
        $prefix = match($alert['severity']) {
            self::SEVERITY_CRITICAL => '[CRITICAL]',
            self::SEVERITY_HIGH => '[HIGH]',
            self::SEVERITY_MEDIUM => '[MEDIUM]',
            self::SEVERITY_LOW => '[LOW]',
            default => '[INFO]'
        };
        
        return "{$prefix} AI News Alert: {$alert['title']}";
    }
    
    private function get_notification_message($alert, $format = 'email') {
        $message = "Alert Type: {$alert['type']}\n";
        $message .= "Severity: {$alert['severity']}\n";
        $message .= "Title: {$alert['title']}\n";
        $message .= "Message: {$alert['message']}\n";
        $message .= "Time: " . date('Y-m-d H:i:s', $alert['created_at']) . "\n";
        
        if (!empty($alert['context'])) {
            $message .= "\nContext:\n";
            foreach ($alert['context'] as $key => $value) {
                $message .= "  {$key}: {$value}\n";
            }
        }
        
        return $message;
    }
    
    private function get_email_headers() {
        return [
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        ];
    }
    
    private function get_slack_color_for_severity($severity) {
        return match($severity) {
            self::SEVERITY_CRITICAL => 'danger',
            self::SEVERITY_HIGH => 'warning',
            self::SEVERITY_MEDIUM => '#ffcc00',
            self::SEVERITY_LOW => 'good',
            default => '#439FE0'
        };
    }
    
    private function format_alert_fields($alert) {
        $fields = [
            [
                'title' => 'Type',
                'value' => $alert['type'],
                'short' => true
            ],
            [
                'title' => 'Severity',
                'value' => $alert['severity'],
                'short' => true
            ]
        ];
        
        if (!empty($alert['context'])) {
            foreach (array_slice($alert['context'], 0, 5) as $key => $value) {
                $fields[] = [
                    'title' => ucfirst(str_replace('_', ' ', $key)),
                    'value' => is_array($value) ? json_encode($value) : (string) $value,
                    'short' => false
                ];
            }
        }
        
        return $fields;
    }
    
    private function generate_alert_tags($type, $severity, $context) {
        $tags = [$type, $severity];
        
        if (isset($context['component'])) {
            $tags[] = $context['component'];
        }
        
        if (isset($context['environment'])) {
            $tags[] = $context['environment'];
        }
        
        return $tags;
    }
    
    private function rule_matches($rule, $type, $severity, $context) {
        if ($rule['type'] !== $type) return false;
        if ($rule['severity'] !== $severity) return false;
        
        if (isset($rule['conditions'])) {
            foreach ($rule['conditions'] as $key => $expected_value) {
                if (isset($context[$key]) && $context[$key] !== $expected_value) {
                    return false;
                }
            }
        }
        
        return true;
    }
    
    private function parse_time_range($time_range) {
        switch ($time_range) {
            case '1h':
                return time() - 3600;
            case '24h':
                return time() - 86400;
            case '7d':
                return time() - 604800;
            case '30d':
                return time() - 2592000;
            default:
                return time() - 86400;
        }
    }
    
    private function get_metric_value($metric_name) {
        // This would integrate with RealTimeMonitor or MetricsCollector
        return 0; // Placeholder
    }
    
    private function is_threshold_exceeded($value, $threshold, $operator) {
        switch ($operator) {
            case '>':
                return $value > $threshold;
            case '>=':
                return $value >= $threshold;
            case '<':
                return $value < $threshold;
            case '<=':
                return $value <= $threshold;
            case '==':
                return $value == $threshold;
            default:
                return false;
        }
    }
    
    private function log_alert_event($event, $alert) {
        $log_entry = [
            'event' => $event,
            'alert_id' => $alert['id'],
            'timestamp' => time(),
            'user_id' => get_current_user_id()
        ];
        
        $log_key = 'ai_news_alert_log';
        $events = get_transient($log_key) ?: [];
        
        array_unshift($events, $log_entry);
        $events = array_slice($events, 0, 1000);
        
        set_transient($log_key, $events, 86400);
    }
    
    private function log_notification_sent($channel_type, $alert_id, $recipient) {
        // Log notification sending for audit trail
        $log_entry = [
            'channel' => $channel_type,
            'alert_id' => $alert_id,
            'recipient' => $recipient,
            'timestamp' => time()
        ];
        
        $log_key = 'ai_news_notification_log';
        $logs = get_transient($log_key) ?: [];
        
        array_unshift($logs, $log_entry);
        $logs = array_slice($logs, 0, 500);
        
        set_transient($log_key, $logs, 86400);
    }
    
    private function schedule_maintenance() {
        // Schedule periodic maintenance tasks
        if (!wp_next_scheduled('ai_news_process_alert_escalations')) {
            wp_schedule_event(time(), 'every_5_minutes', 'ai_news_process_alert_escalations');
        }
        
        if (!wp_next_scheduled('ai_news_check_alert_thresholds')) {
            wp_schedule_event(time(), 'every_1_minute', 'ai_news_check_alert_thresholds');
        }
        
        if (!wp_next_scheduled('ai_news_cleanup_old_alerts')) {
            wp_schedule_event(time(), 'daily', 'ai_news_cleanup_old_alerts');
        }
    }
    
    public function cleanup_old_alerts() {
        global $wpdb;
        
        $retention_days = 30;
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));
        
        $table_name = $wpdb->prefix . 'ai_news_monitoring_alerts';
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table_name} 
             WHERE status = %s AND created_at < %s",
            self::STATUS_RESOLVED,
            $cutoff_date
        ));
    }
    
    private function get_default_alert_rules() {
        return [
            'high_response_time' => [
                'metric' => 'response_time',
                'threshold' => 2000,
                'operator' => '>',
                'severity' => self::SEVERITY_HIGH
            ],
            'high_memory_usage' => [
                'metric' => 'memory_percentage',
                'threshold' => 80,
                'operator' => '>',
                'severity' => self::SEVERITY_HIGH
            ],
            'high_error_rate' => [
                'metric' => 'error_rate',
                'threshold' => 5,
                'operator' => '>',
                'severity' => self::SEVERITY_CRITICAL
            ]
        ];
    }
    
    private function get_default_channels() {
        return [
            [
                'type' => self::CHANNEL_DASHBOARD,
                'enabled' => true,
                'name' => 'Dashboard Notifications'
            ],
            [
                'type' => self::CHANNEL_EMAIL,
                'enabled' => true,
                'name' => 'Email Notifications',
                'recipients' => [get_option('admin_email')],
                'min_severity' => self::SEVERITY_MEDIUM
            ]
        ];
    }
    
    private function get_default_escalation_schedule() {
        return [
            self::SEVERITY_CRITICAL => [300, 900, 3600], // 5min, 15min, 1hr
            self::SEVERITY_HIGH => [1800, 3600], // 30min, 1hr
            self::SEVERITY_MEDIUM => [3600], // 1hr
            self::SEVERITY_LOW => [7200], // 2hr
        ];
    }
}

// Initialize the alerts manager
AlertsManager::get_instance();