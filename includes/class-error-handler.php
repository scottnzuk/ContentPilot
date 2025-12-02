<?php
/**
 * Centralized Error Handler Class
 *
 * @package ContentPilot
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Centralized Error Handler for the AI Auto News Poster plugin
 * 
 * Provides consistent error handling, logging, and recovery mechanisms
 * across all plugin components. Integrates with WordPress debugging
 * and provides user-friendly error messages.
 */
class CP_Error_Handler {
    
    /**
     * Error Handler instance (Singleton)
     */
    private static $instance = null;
    
    /**
     * Logger instance
     */
    private $logger;
    
    /**
     * Error notification settings
     */
    private $notification_settings;
    
    /**
     * Error context tracking
     */
    private $error_context = array();
    
    /**
     * Recovery strategies registry
     */
    private $recovery_strategies = array();
    
    /**
     * Log levels for error filtering
     */
    const LOG_LEVELS = array(
        'DEBUG' => 0,
        'INFO' => 1,
        'WARNING' => 2,
        'ERROR' => 3,
        'CRITICAL' => 4
    );
    
    /**
     * Error categories for better organization
     */
    const ERROR_CATEGORIES = array(
        'SYSTEM' => 'system',
        'NETWORK' => 'network',
        'API' => 'api',
        'DATABASE' => 'database',
        'SECURITY' => 'security',
        'USER_INPUT' => 'user_input',
        'CONFIGURATION' => 'configuration',
        'PERFORMANCE' => 'performance'
    );
    
    /**
     * Get Error Handler instance (Singleton pattern)
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor for singleton pattern
     */
    private function __construct() {
        $this->init_logger();
        $this->init_notification_settings();
        $this->register_recovery_strategies();
        $this->setup_error_handlers();
        
        // Log initialization
        $this->log_info('Error Handler initialized', array(
            'php_version' => PHP_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'plugin_version' => defined('CP_VERSION') ? CP_VERSION : 'unknown'
        ));
    }
    
    /**
     * Initialize logger with error handling
     */
    private function init_logger() {
        try {
            if (class_exists('CP_Logger')) {
                $this->logger = CP_Logger::getInstance();
            } else {
                // Fallback to basic error logging
                $this->logger = null;
            }
        } catch (Exception $e) {
            error_log('ContentPilot Error Handler: Failed to initialize logger - ' . $e->getMessage());
            $this->logger = null;
        }
    }
    
    /**
     * Initialize notification settings
     */
    private function init_notification_settings() {
        $this->notification_settings = array(
            'admin_notices' => true,
            'email_notifications' => false,
            'critical_only' => true,
            'debug_mode' => defined('WP_DEBUG') && WP_DEBUG
        );
        
        // Allow filtering of notification settings
        $this->notification_settings = apply_filters('cp_error_notification_settings', $this->notification_settings);
    }
    
    /**
     * Register recovery strategies for different error types
     */
    private function register_recovery_strategies() {
        $this->recovery_strategies = array(
            'cache_failure' => array($this, 'recovery_cache_failure'),
            'api_failure' => array($this, 'recovery_api_failure'),
            'network_failure' => array($this, 'recovery_network_failure'),
            'database_failure' => array($this, 'recovery_database_failure')
        );
        
        // Allow plugins to register additional strategies
        $this->recovery_strategies = apply_filters('cp_recovery_strategies', $this->recovery_strategies);
    }
    
    /**
     * Setup PHP and WordPress error handlers
     */
    private function setup_error_handlers() {
        // Set custom error handler
        set_error_handler(array($this, 'handle_php_error'), E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR);
        
        // Setup fatal error handler
        register_shutdown_function(array($this, 'handle_shutdown'));
        
        // Set exception handler for uncaught exceptions
        set_exception_handler(array($this, 'handle_uncaught_exception'));
        
        // WordPress admin notices hook
        add_action('admin_notices', array($this, 'display_admin_notices'));
    }
    
    /**
     * Handle PHP errors
     * 
     * @param int $severity Error severity level
     * @param string $message Error message
     * @param string $file File where error occurred
     * @param int $line Line number where error occurred
     * @return bool True to prevent default PHP error handling
     */
    public function handle_php_error($severity, $message, $file, $line) {
        // Only handle errors we care about
        if (!(error_reporting() & $severity)) {
            return false;
        }
        
        // Determine error category based on message and file
        $category = $this->determine_error_category($message, $file);
        
        // Build error context
        $context = array(
            'severity' => $severity,
            'file' => basename($file),
            'line' => $line,
            'category' => $category,
            'memory_usage' => $this->format_bytes(memory_get_usage(true)),
            'memory_peak' => $this->format_bytes(memory_get_peak_usage(true))
        );
        
        // Add request context if available
        if (isset($_SERVER['REQUEST_URI'])) {
            $context['request_uri'] = $_SERVER['REQUEST_URI'];
        }
        
        // Log error
        $this->log_error("PHP Error: {$message}", $context, $category);
        
        // Add admin notice for critical errors
        if ($this->should_notify_admin($severity, $category)) {
            $this->add_admin_notice(
                $this->get_user_friendly_message($message, $category),
                'error'
            );
        }
        
        // Attempt recovery
        $this->attempt_recovery($category, $context);
        
        return true;
    }
    
    /**
     * Handle uncaught exceptions
     * 
     * @param Exception $exception The uncaught exception
     */
    public function handle_uncaught_exception($exception) {
        $category = $this->determine_error_category($exception->getMessage(), $exception->getFile());
        
        $context = array(
            'exception_class' => get_class($exception),
            'file' => basename($exception->getFile()),
            'line' => $exception->getLine(),
            'category' => $category,
            'memory_usage' => $this->format_bytes(memory_get_usage(true)),
            'trace' => $exception->getTraceAsString()
        );
        
        $this->log_critical("Uncaught Exception: {$exception->getMessage()}", $context, $category);
        
        // Always notify admin for uncaught exceptions
        $this->add_admin_notice(
            $this->get_user_friendly_message($exception->getMessage(), $category),
            'error'
        );
        
        $this->attempt_recovery($category, $context);
    }
    
    /**
     * Handle fatal errors on shutdown
     */
    public function handle_shutdown() {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $category = $this->determine_error_category($error['message'], $error['file']);
            
            $context = array(
                'type' => $error['type'],
                'message' => $error['message'],
                'file' => basename($error['file']),
                'line' => $error['line'],
                'category' => $category,
                'memory_usage' => $this->format_bytes(memory_get_usage(true))
            );
            
            $this->log_critical("Fatal Error: {$error['message']}", $context, $category);
            
            $this->attempt_recovery($category, $context);
        }
    }
    
    /**
     * Log error message with context
     * 
     * @param string $message Error message
     * @param array $context Additional context
     * @param string $category Error category
     * @param string $level Log level
     */
    public function log_error($message, $context = array(), $category = 'SYSTEM', $level = 'ERROR') {
        $this->perform_logging($message, $context, $category, $level);
    }
    
    /**
     * Log warning message
     * 
     * @param string $message Warning message
     * @param array $context Additional context
     * @param string $category Error category
     */
    public function log_warning($message, $context = array(), $category = 'SYSTEM') {
        $this->perform_logging($message, $context, $category, 'WARNING');
    }
    
    /**
     * Log info message
     * 
     * @param string $message Info message
     * @param array $context Additional context
     * @param string $category Error category
     */
    public function log_info($message, $context = array(), $category = 'SYSTEM') {
        $this->perform_logging($message, $context, $category, 'INFO');
    }
    
    /**
     * Log critical message
     * 
     * @param string $message Critical message
     * @param array $context Additional context
     * @param string $category Error category
     */
    public function log_critical($message, $context = array(), $category = 'SYSTEM') {
        $this->perform_logging($message, $context, $category, 'CRITICAL');
    }
    
    /**
     * Perform actual logging
     */
    private function perform_logging($message, $context, $category, $level) {
        // Add global context
        $full_context = array_merge($this->get_global_context(), $context);
        $full_context['error_category'] = $category;
        
        // Use logger if available
        if ($this->logger) {
            $log_method = strtolower($level);
            if (method_exists($this->logger, $log_method)) {
                $this->logger->$log_method($message, $full_context);
            } else {
                $this->logger->log(strtolower($level), $message, $full_context);
            }
        } else {
            // Fallback to PHP error log
            $log_entry = sprintf(
                '[ContentPilot %s] %s: %s',
                $level,
                $message,
                wp_json_encode($full_context)
            );
            error_log($log_entry);
        }
    }
    
    /**
     * Get global context for all log entries
     */
    private function get_global_context() {
        return array(
            'plugin_version' => defined('CP_VERSION') ? CP_VERSION : 'unknown',
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version'),
            'memory_limit' => ini_get('memory_limit'),
            'user_id' => get_current_user_id(),
            'timestamp' => current_time('Y-m-d H:i:s', true) // UTC
        );
    }
    
    /**
     * Determine error category based on message and file
     */
    private function determine_error_category($message, $file) {
        $file_lower = strtolower($file);
        $message_lower = strtolower($message);
        
        // Check file patterns
        if (strpos($file_lower, 'cache') !== false) {
            return self::ERROR_CATEGORIES['SYSTEM'];
        }
        
        if (strpos($file_lower, 'network') !== false || strpos($file_lower, 'http') !== false) {
            return self::ERROR_CATEGORIES['NETWORK'];
        }
        
        if (strpos($file_lower, 'api') !== false || strpos($file_lower, 'ai') !== false) {
            return self::ERROR_CATEGORIES['API'];
        }
        
        if (strpos($file_lower, 'database') !== false || strpos($file_lower, 'wpdb') !== false) {
            return self::ERROR_CATEGORIES['DATABASE'];
        }
        
        if (strpos($file_lower, 'security') !== false || strpos($file_lower, 'auth') !== false) {
            return self::ERROR_CATEGORIES['SECURITY'];
        }
        
        // Check message patterns
        if (strpos($message_lower, 'timeout') !== false || strpos($message_lower, 'connection') !== false) {
            return self::ERROR_CATEGORIES['NETWORK'];
        }
        
        if (strpos($message_lower, 'permission') !== false || strpos($message_lower, 'forbidden') !== false) {
            return self::ERROR_CATEGORIES['SECURITY'];
        }
        
        if (strpos($message_lower, 'sql') !== false || strpos($message_lower, 'database') !== false) {
            return self::ERROR_CATEGORIES['DATABASE'];
        }
        
        return self::ERROR_CATEGORIES['SYSTEM'];
    }
    
    /**
     * Get user-friendly error message
     * 
     * @param string $technical_message Technical error message
     * @param string $category Error category
     * @return string User-friendly message
     */
    public function get_user_friendly_message($technical_message, $category = 'SYSTEM') {
        $friendly_messages = array(
            self::ERROR_CATEGORIES['NETWORK'] => __(
                'A network connectivity issue occurred. Please check your internet connection and try again.',
                'cp'
            ),
            self::ERROR_CATEGORIES['API'] => __(
                'The AI service is temporarily unavailable. Please try again later.',
                'cp'
            ),
            self::ERROR_CATEGORIES['DATABASE'] => __(
                'A database error occurred. Please contact support if the issue persists.',
                'cp'
            ),
            self::ERROR_CATEGORIES['SECURITY'] => __(
                'A security check failed. Please try again or contact support.',
                'cp'
            ),
            self::ERROR_CATEGORIES['USER_INPUT'] => __(
                'There was an issue with the provided information. Please check your input and try again.',
                'cp'
            ),
            self::ERROR_CATEGORIES['CONFIGURATION'] => __(
                'A configuration issue was detected. Please check your plugin settings.',
                'cp'
            ),
            self::ERROR_CATEGORIES['SYSTEM'] => __(
                'An unexpected error occurred. Please try again or contact support if the issue persists.',
                'cp'
            )
        );
        
        return isset($friendly_messages[$category]) 
            ? $friendly_messages[$category] 
            : $friendly_messages[self::ERROR_CATEGORIES['SYSTEM']];
    }
    
    /**
     * Determine if admin should be notified
     * 
     * @param int $severity Error severity
     * @param string $category Error category
     * @return bool True if admin should be notified
     */
    private function should_notify_admin($severity, $category) {
        // Always notify for critical errors
        if (in_array($severity, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR])) {
            return true;
        }
        
        // Notify for security issues
        if ($category === self::ERROR_CATEGORIES['SECURITY']) {
            return true;
        }
        
        // Notify based on settings
        return $this->notification_settings['admin_notices'];
    }
    
    /**
     * Add admin notice
     * 
     * @param string $message Notice message
     * @param string $type Notice type (error, warning, info, success)
     */
    private function add_admin_notice($message, $type = 'error') {
        $this->error_context[] = array(
            'message' => $message,
            'type' => $type,
            'timestamp' => current_time('Y-m-d H:i:s', true)
        );
    }
    
    /**
     * Display admin notices
     */
    public function display_admin_notices() {
        if (empty($this->error_context)) {
            return;
        }
        
        // Filter duplicate messages
        $unique_notices = array();
        $seen_messages = array();
        
        foreach ($this->error_context as $notice) {
            if (!in_array($notice['message'], $seen_messages, true)) {
                $unique_notices[] = $notice;
                $seen_messages[] = $notice['message'];
            }
        }
        
        // Display notices
        foreach ($unique_notices as $notice) {
            $class = 'notice notice-' . esc_attr($notice['type']);
            printf(
                '<div class="%s"><p>%s</p></div>',
                $class,
                esc_html($notice['message'])
            );
        }
        
        // Clear displayed notices
        $this->error_context = array();
    }
    
    /**
     * Attempt error recovery
     * 
     * @param string $category Error category
     * @param array $context Error context
     */
    private function attempt_recovery($category, $context) {
        $strategy_key = $this->get_recovery_strategy_key($category);
        
        if (isset($this->recovery_strategies[$strategy_key]) && is_callable($this->recovery_strategies[$strategy_key])) {
            try {
                call_user_func($this->recovery_strategies[$strategy_key], $context);
            } catch (Exception $e) {
                self::getInstance()->handle_error(
                    'Recovery strategy failed: ' . $e->getMessage(),
                    array(
                        'strategy' => $strategy_key,
                        'recovery_error' => $e->getMessage()
                    ),
                    self::ERROR_CATEGORIES['SYSTEM'],
                    'ERROR',
                    false,
                    false
                );
            }
        }
    }
    
    /**
     * Get recovery strategy key for category
     * 
     * @param string $category Error category
     * @return string Strategy key
     */
    private function get_recovery_strategy_key($category) {
        $mapping = array(
            self::ERROR_CATEGORIES['SYSTEM'] => 'cache_failure',
            self::ERROR_CATEGORIES['NETWORK'] => 'network_failure',
            self::ERROR_CATEGORIES['API'] => 'api_failure',
            self::ERROR_CATEGORIES['DATABASE'] => 'database_failure'
        );
        
        return isset($mapping[$category]) ? $mapping[$category] : 'cache_failure';
    }
    
    /**
     * Recovery strategy: Cache failure
     */
    private function recovery_cache_failure($context) {
        $this->log_info('Attempting cache recovery', $context);
        
        // Clear object cache
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        // Clear transients
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_cp_%'");
        
        $this->log_info('Cache recovery completed');
    }
    
    /**
     * Recovery strategy: API failure
     */
    private function recovery_api_failure($context) {
        $this->log_info('Attempting API recovery', $context);
        
        // Reset any API rate limiting or retry counters
        if (class_exists('CP_Rate_Limiter')) {
            $rate_limiter = new CP_Rate_Limiter();
            // Reset API-related rate limits
            $rate_limiter->reset_limit('api_request');
        }
    }
    
    /**
     * Recovery strategy: Network failure
     */
    private function recovery_network_failure($context) {
        $this->log_info('Attempting network recovery', $context);
        
        // Clear any HTTP request cache
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }
    
    /**
     * Recovery strategy: Database failure
     */
    private function recovery_database_failure($context) {
        $this->log_info('Attempting database recovery', $context);
        
        // Force database connection reset
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        // This could be expanded to include database connection retry logic
    }
    
    /**
     * Handle plugin-specific errors
     * 
     * @param string $message Error message
     * @param array $context Additional context
     * @param string $category Error category
     * @param string $level Log level
     * @param bool $notify_admin Whether to notify admin
     * @param bool $attempt_recovery Whether to attempt recovery
     */
    public function handle_error($message, $context = array(), $category = 'SYSTEM', $level = 'ERROR', $notify_admin = false, $attempt_recovery = true) {
        $this->perform_logging($message, $context, $category, $level);
        
        if ($notify_admin || $this->should_notify_admin(E_WARNING, $category)) {
            $this->add_admin_notice(
                $this->get_user_friendly_message($message, $category),
                'error'
            );
        }
        
        if ($attempt_recovery) {
            $this->attempt_recovery($category, $context);
        }
    }
    
    /**
     * Get error statistics
     * 
     * @return array Error statistics
     */
    public function get_error_statistics() {
        // This would typically query the log database or files
        // For now, return basic statistics
        return array(
            'total_errors' => count($this->error_context),
            'last_error_time' => !empty($this->error_context) ? end($this->error_context)['timestamp'] : null,
            'memory_usage' => $this->format_bytes(memory_get_usage(true)),
            'peak_memory' => $this->format_bytes(memory_get_peak_usage(true))
        );
    }
    
    /**
     * Clear error context
     */
    public function clear_errors() {
        $this->error_context = array();
        
        if ($this->logger && method_exists($this->logger, 'clear_logs')) {
            $this->logger->clear_logs();
        }
    }
    
    /**
     * Format bytes to human readable format
     * 
     * @param int $bytes Bytes to format
     * @param int $precision Decimal precision
     * @return string Formatted string
     */
    private function format_bytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Prevent cloning of singleton
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization of singleton
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize a singleton.");
    }
}