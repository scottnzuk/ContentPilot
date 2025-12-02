<?php
/**
 * Logger Class
 *
 * @package ContentPilot
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class CP_Logger {
    
    /**
     * Logger instance
     */
    private static $instance = null;
    
    /**
     * Log levels
     */
    const LEVELS = array(
        'debug' => 0,
        'info' => 1,
        'warning' => 2,
        'error' => 3,
        'critical' => 4
    );
    
    /**
     * Minimum log level to record
     */
    private $min_level = 1; // info
    
    /**
     * Log file path
     */
    private $log_file;
    
    /**
     * Get instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $upload_dir = wp_upload_dir();
        $this->log_file = $upload_dir['basedir'] . '/cp.log';
        
        // Ensure upload directory exists
        if (!file_exists($upload_dir['basedir'])) {
            wp_mkdir_p($upload_dir['basedir']);
        }
    }
    
    /**
     * Log message
     */
    public function log($level, $message, $context = array()) {
        try {
            // Validate log level
            if (!array_key_exists($level, self::LEVELS)) {
                $level = 'info';
            }
            
            // Check if we should log this level
            if (self::LEVELS[$level] < $this->min_level) {
                return;
            }
            
            // Sanitize sensitive data from context
            $sanitized_context = $this->sanitize_context($context);
            
            // Format log entry
            $timestamp = current_time('Y-m-d H:i:s');
            $user_id = get_current_user_id();
            $ip_address = $this->get_client_ip();
            
            $log_entry = array(
                'timestamp' => $timestamp,
                'level' => strtoupper($level),
                'message' => $this->sanitize_message($message),
                'user_id' => $user_id,
                'ip_address' => $ip_address,
                'context' => $sanitized_context
            );
            
            // Write to file
            $this->write_log_entry($log_entry);
            
            // For critical errors, also log to PHP error log
            if ($level === 'critical') {
                error_log(sprintf(
                    'CP Critical: %s in %s on line %s',
                    $log_entry['message'],
                    isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'unknown',
                    isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : 'unknown'
                ));
            }
            
        } catch (Exception $e) {
            // Fallback to PHP error log
            error_log('CP Logger Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Log debug message
     */
    public function debug($message, $context = array()) {
        $this->log('debug', $message, $context);
    }
    
    /**
     * Log info message
     */
    public function info($message, $context = array()) {
        $this->log('info', $message, $context);
    }
    
    /**
     * Log warning message
     */
    public function warning($message, $context = array()) {
        $this->log('warning', $message, $context);
    }
    
    /**
     * Log error message
     */
    public function error($message, $context = array()) {
        $this->log('error', $message, $context);
    }
    
    /**
     * Log critical message
     */
    public function critical($message, $context = array()) {
        $this->log('critical', $message, $context);
    }
    
    /**
     * Sanitize context data to remove sensitive information
     */
    private function sanitize_context($context) {
        $sensitive_keys = array(
            'api_key', 'password', 'token', 'secret', 'key', 'auth',
            'user_pass', 'user_nicename', 'user_email', 'display_name'
        );
        
        if (!is_array($context)) {
            return array();
        }
        
        $sanitized = array();
        foreach ($context as $key => $value) {
            if (in_array($key, $sensitive_keys, true)) {
                $sanitized[$key] = '[REDACTED]';
            } else {
                // Recursively sanitize nested arrays
                if (is_array($value)) {
                    $sanitized[$key] = $this->sanitize_context($value);
                } else {
                    $sanitized[$key] = $this->sanitize_value($value);
                }
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize individual values
     */
    private function sanitize_value($value) {
        if (is_string($value)) {
            // Remove potential XSS
            $value = wp_strip_all_tags($value);
            // Remove potential SQL injection patterns
            $value = preg_replace('/(\'|")/u', '\\$1', $value);
        }
        
        return $value;
    }
    
    /**
     * Sanitize log message
     */
    private function sanitize_message($message) {
        // Remove any potential script tags or HTML
        $message = wp_strip_all_tags($message);
        // Escape quotes
        $message = preg_replace('/(\'|")/u', '\\$1', $message);
        // Limit message length to prevent log file bloat
        if (strlen($message) > 1000) {
            $message = substr($message, 0, 997) . '...';
        }
        return $message;
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (array_map('trim', explode(',', $_SERVER[$key])) as $ip) {
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }
    
    /**
     * Write log entry to file
     */
    private function write_log_entry($log_entry) {
        $log_line = sprintf(
            "[%s] %s: %s",
            $log_entry['timestamp'],
            $log_entry['level'],
            $log_entry['message']
        );
        
        // Add user and IP context
        if ($log_entry['user_id'] || $log_entry['ip_address']) {
            $context_parts = array();
            if ($log_entry['user_id']) {
                $context_parts[] = 'user:' . $log_entry['user_id'];
            }
            if ($log_entry['ip_address']) {
                $context_parts[] = 'ip:' . $log_entry['ip_address'];
            }
            $log_line .= ' (' . implode(', ', $context_parts) . ')';
        }
        
        // Add context if present
        if (!empty($log_entry['context'])) {
            $log_line .= ' | Context: ' . wp_json_encode($log_entry['context']);
        }
        
        $log_line .= PHP_EOL;
        
        // Use WordPress file system if available
        if (function_exists('WP_Filesystem')) {
            WP_Filesystem();
            global $wp_filesystem;
            if ($wp_filesystem) {
                $wp_filesystem->put_contents($this->log_file, $log_line, FILE_APPEND);
                return;
            }
        }
        
        // Fallback to standard file operations
        if (file_put_contents($this->log_file, $log_line, FILE_APPEND | LOCK_EX) === false) {
            error_log('CP Logger: Failed to write to log file');
        }
    }
    
    /**
     * Get recent log entries
     */
    public function get_recent_logs($lines = 100) {
        if (!file_exists($this->log_file)) {
            return array();
        }
        
        $logs = array();
        $file = fopen($this->log_file, 'r');
        if (!$file) {
            return array();
        }
        
        $log_lines = array();
        while (($line = fgets($file)) !== false) {
            $log_lines[] = $line;
        }
        fclose($file);
        
        // Get last N lines
        $log_lines = array_slice($log_lines, -$lines);
        
        foreach ($log_lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            
            // Parse log line
            if (preg_match('/\[([^\]]+)\] (\w+): (.+)/', $line, $matches)) {
                $logs[] = array(
                    'timestamp' => $matches[1],
                    'level' => $matches[2],
                    'message' => $matches[3]
                );
            }
        }
        
        return array_reverse($logs); // Latest first
    }
    
    /**
     * Clear log file
     */
    public function clear_logs() {
        if (function_exists('WP_Filesystem')) {
            WP_Filesystem();
            global $wp_filesystem;
            if ($wp_filesystem) {
                $wp_filesystem->put_contents($this->log_file, '');
                return true;
            }
        }
        
        return file_put_contents($this->log_file, '') !== false;
    }
    
    /**
     * Get log file size
     */
    public function get_log_file_size() {
        if (!file_exists($this->log_file)) {
            return 0;
        }
        
        return filesize($this->log_file);
    }
    
    /**
     * Rotate log file if it's too large
     */
    public function rotate_log_file($max_size_mb = 10) {
        if (!file_exists($this->log_file)) {
            return;
        }
        
        $max_size = $max_size_mb * 1024 * 1024; // Convert to bytes
        $current_size = filesize($this->log_file);
        
        if ($current_size > $max_size) {
            $backup_file = $this->log_file . '.backup.' . date('Y-m-d-H-i-s');
            rename($this->log_file, $backup_file);
            
            $this->log('info', 'Log file rotated', array(
                'original_size' => $current_size,
                'backup_file' => basename($backup_file)
            ));
        }
    }
    
    /**
     * Set minimum log level
     */
    public function set_min_level($level) {
        if (array_key_exists($level, self::LEVELS)) {
            $this->min_level = self::LEVELS[$level];
        }
    }
    
    /**
     * Get minimum log level
     */
    public function get_min_level() {
        return $this->min_level;
    }
}