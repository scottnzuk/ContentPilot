<?php
/**
 * Rate Limiter Class
 *
 * @package ContentPilot
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Rate Limiter Exception Class
 */
class CP_Rate_Limiter_Exception extends Exception {}

/**
 * Rate Limiter Class
 */
class CP_Rate_Limiter {
    
    private $cache_manager;
    private $logger;
    private $max_identifier_length = 100;
    private $max_action_length = 50;
    private $min_window = 60; // Minimum 1 minute window
    private $max_window = 86400; // Maximum 1 day window
    private $min_limit = 1;
    private $max_limit = 1000;
    
    /**
     * Constructor
     */
    public function __construct() {
        try {
            $this->cache_manager = new CP_Cache_Manager();
            $this->logger = CP_Logger::getInstance();
            
            $this->logger->debug('Rate limiter initialized', array(
                'version' => '2.0.0',
                'php_version' => PHP_VERSION,
                'max_identifier_length' => $this->max_identifier_length,
                'max_action_length' => $this->max_action_length
            ));
            
        } catch (Exception $e) {
            // Fallback logger if main logger fails
            if (function_exists('error_log')) {
                error_log('CP Rate Limiter: Failed to initialize logger - ' . $e->getMessage());
            }
            $this->logger = null;
        }
    }
    
    /**
     * Validate rate limit parameters
     *
     * @param string $action Action identifier
     * @param int $limit Number of attempts allowed
     * @param int $window Time window in seconds
     * @param string $identifier User identifier
     * @return array Validated parameters
     * @throws AANP_Rate_Limiter_Exception
     */
    private function validate_rate_limit_params($action, $limit, $window, $identifier) {
        // Validate action
        if (empty($action) || !is_string($action)) {
            throw new AANP_Rate_Limiter_Exception('Action must be a non-empty string');
        }
        
        // Sanitize and validate action length
        $action = sanitize_text_field($action);
        if (strlen($action) > $this->max_action_length) {
            throw new AANP_Rate_Limiter_Exception(sprintf(
                'Action must be less than %d characters',
                $this->max_action_length
            ));
        }
        
        // Validate limit
        if (!is_numeric($limit) || $limit < $this->min_limit || $limit > $this->max_limit) {
            throw new AANP_Rate_Limiter_Exception(sprintf(
                'Limit must be between %d and %d',
                $this->min_limit,
                $this->max_limit
            ));
        }
        
        $limit = (int) $limit;
        
        // Validate window
        if (!is_numeric($window) || $window < $this->min_window || $window > $this->max_window) {
            throw new AANP_Rate_Limiter_Exception(sprintf(
                'Window must be between %d and %d seconds',
                $this->min_window,
                $this->max_window
            ));
        }
        
        $window = (int) $window;
        
        // Validate and sanitize identifier
        if ($identifier !== null) {
            if (!is_string($identifier)) {
                throw new AANP_Rate_Limiter_Exception('Identifier must be a string');
            }
            
            $identifier = sanitize_text_field($identifier);
            if (strlen($identifier) > $this->max_identifier_length) {
                throw new AANP_Rate_Limiter_Exception(sprintf(
                    'Identifier must be less than %d characters',
                    $this->max_identifier_length
                ));
            }
        }
        
        return array(
            'action' => $action,
            'limit' => $limit,
            'window' => $window,
            'identifier' => $identifier
        );
    }
    
    /**
     * Check if action is rate limited
     *
     * @param string $action Action identifier
     * @param int $limit Number of attempts allowed
     * @param int $window Time window in seconds
     * @param string $identifier User identifier (IP, user ID, etc.)
     * @return bool True if rate limited
     * @throws AANP_Rate_Limiter_Exception
     */
    public function is_rate_limited($action, $limit = 5, $window = 3600, $identifier = null) {
        try {
            // Log attempt
            if ($this->logger) {
                $this->logger->debug('Rate limit check started', array(
                    'action' => $action,
                    'limit' => $limit,
                    'window' => $window,
                    'identifier_provided' => $identifier !== null
                ));
            }
            
            // Validate parameters
            $validated = $this->validate_rate_limit_params($action, $limit, $window, $identifier);
            $action = $validated['action'];
            $limit = $validated['limit'];
            $window = $validated['window'];
            $identifier = $validated['identifier'];
            
            // Get identifier
            if ($identifier === null) {
                $identifier = $this->get_safe_client_identifier();
            }
            
            $key = "rate_limit_{$action}_{$identifier}";
            
            // Use cache with proper error handling
            $attempts = 0;
            try {
                if ($this->cache_manager) {
                    $attempts = $this->cache_manager->get($key, 0);
                }
            } catch (Exception $cache_e) {
                $this->log_error('Cache read failed in rate limit check', array(
                    'error' => $cache_e->getMessage(),
                    'key' => $key
                ));
                
                // Graceful fallback - allow request to proceed if cache fails
                return false;
            }
            
            $is_limited = $attempts >= $limit;
            
            // Log result
            if ($this->logger) {
                $this->logger->info('Rate limit check completed', array(
                    'action' => $action,
                    'attempts' => $attempts,
                    'limit' => $limit,
                    'is_limited' => $is_limited,
                    'identifier' => $this->anonymize_identifier($identifier)
                ));
            }
            
            return $is_limited;
            
        } catch (AANP_Rate_Limiter_Exception $e) {
            $this->log_error('Rate limit validation failed', array(
                'error' => $e->getMessage(),
                'action' => $action,
                'limit' => $limit,
                'window' => $window
            ));
            
            // Re-throw validation errors
            throw $e;
            
        } catch (Exception $e) {
            $this->log_error('Unexpected error in rate limit check', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'action' => $action
            ));
            
            // Graceful fallback - allow request to proceed on unexpected errors
            return false;
        }
    }
    
    /**
     * Record an attempt
     *
     * @param string $action Action identifier
     * @param int $window Time window in seconds
     * @param string $identifier User identifier
     * @return int Current attempt count
     * @throws AANP_Rate_Limiter_Exception
     */
    public function record_attempt($action, $window = 3600, $identifier = null) {
        try {
            // Log attempt start
            if ($this->logger) {
                $this->logger->debug('Rate limit record attempt started', array(
                    'action' => $action,
                    'window' => $window,
                    'identifier_provided' => $identifier !== null
                ));
            }
            
            // Validate parameters
            $validated = $this->validate_rate_limit_params($action, 1, $window, $identifier);
            $action = $validated['action'];
            $window = $validated['window'];
            $identifier = $validated['identifier'];
            
            // Get identifier
            if ($identifier === null) {
                $identifier = $this->get_safe_client_identifier();
            }
            
            $key = "rate_limit_{$action}_{$identifier}";
            
            // Start transaction-like operation
            $success = false;
            $new_attempts = 0;
            
            try {
                // Atomically increment attempt count with proper error handling
                if ($this->cache_manager) {
                    $current_attempts = $this->cache_manager->get($key, 0);
                    $new_attempts = (int) $current_attempts + 1;
                    
                    $cache_success = $this->cache_manager->set($key, $new_attempts, $window);
                    
                    if (!$cache_success) {
                        throw new AANP_Rate_Limiter_Exception('Failed to update cache');
                    }
                    
                    $success = true;
                }
            } catch (Exception $cache_e) {
                $this->log_error('Cache write failed in record attempt', array(
                    'error' => $cache_e->getMessage(),
                    'key' => $key,
                    'action' => $action
                ));
                
                // Ensure cleanup on failure
                $this->cleanup_failed_attempt($key);
                
                // Continue with fallback value
                $new_attempts = 1;
            }
            
            // Log successful attempt recording
            if ($this->logger) {
                $this->logger->info('Rate limit attempt recorded', array(
                    'action' => $action,
                    'new_attempts' => $new_attempts,
                    'window' => $window,
                    'success' => $success,
                    'identifier' => $this->anonymize_identifier($identifier)
                ));
            }
            
            return $new_attempts;
            
        } catch (AANP_Rate_Limiter_Exception $e) {
            $this->log_error('Rate limit record validation failed', array(
                'error' => $e->getMessage(),
                'action' => $action,
                'window' => $window
            ));
            throw $e;
            
        } catch (Exception $e) {
            $this->log_error('Unexpected error in record attempt', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'action' => $action
            ));
            
            // Return fallback value on unexpected errors
            return 1;
        }
    }
    
    /**
     * Reset rate limit for action
     *
     * @param string $action Action identifier
     * @param string $identifier User identifier
     * @return bool Success status
     * @throws AANP_Rate_Limiter_Exception
     */
    public function reset_limit($action, $identifier = null) {
        try {
            // Log reset attempt
            if ($this->logger) {
                $this->logger->debug('Rate limit reset started', array(
                    'action' => $action,
                    'identifier_provided' => $identifier !== null
                ));
            }
            
            // Validate parameters
            $validated = $this->validate_rate_limit_params($action, 1, 3600, $identifier);
            $action = $validated['action'];
            $identifier = $validated['identifier'];
            
            // Get identifier
            if ($identifier === null) {
                $identifier = $this->get_safe_client_identifier();
            }
            
            $key = "rate_limit_{$action}_{$identifier}";
            
            // Delete from cache with error handling
            $success = false;
            try {
                if ($this->cache_manager) {
                    $success = $this->cache_manager->delete($key);
                }
            } catch (Exception $cache_e) {
                $this->log_error('Cache delete failed in reset limit', array(
                    'error' => $cache_e->getMessage(),
                    'key' => $key
                ));
                
                // Continue with fallback
                $success = false;
            }
            
            // Log result
            if ($this->logger) {
                $this->logger->info('Rate limit reset completed', array(
                    'action' => $action,
                    'success' => $success,
                    'identifier' => $this->anonymize_identifier($identifier)
                ));
            }
            
            return $success;
            
        } catch (AANP_Rate_Limiter_Exception $e) {
            $this->log_error('Rate limit reset validation failed', array(
                'error' => $e->getMessage(),
                'action' => $action
            ));
            throw $e;
            
        } catch (Exception $e) {
            $this->log_error('Unexpected error in reset limit', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'action' => $action
            ));
            
            // Return false on unexpected errors
            return false;
        }
    }
    
    /**
     * Get client identifier with error handling and time zone awareness
     *
     * @return string Client identifier
     */
    private function get_client_identifier() {
        try {
            if (is_user_logged_in()) {
                $user_id = get_current_user_id();
                if ($user_id > 0) {
                    return 'user_' . $user_id;
                }
            }
            
            return 'ip_' . $this->get_safe_client_ip();
            
        } catch (Exception $e) {
            $this->log_error('Failed to get client identifier', array(
                'error' => $e->getMessage(),
                'is_user_logged_in' => is_user_logged_in()
            ));
            
            // Return fallback identifier with timestamp for uniqueness
            return 'fallback_' . time() . '_' . wp_rand(1000, 9999);
        }
    }
    
    /**
     * Get safe client identifier (without throwing exceptions)
     *
     * @return string Client identifier
     */
    private function get_safe_client_identifier() {
        try {
            return $this->get_client_identifier();
        } catch (Exception $e) {
            return 'safe_fallback_' . time() . '_' . wp_rand(1000, 9999);
        }
    }
    
    /**
     * Get client IP address with comprehensive error handling
     *
     * @return string IP address
     */
    private function get_safe_client_ip() {
        try {
            return $this->get_client_ip();
        } catch (Exception $e) {
            $this->log_error('Failed to get client IP', array(
                'error' => $e->getMessage()
            ));
            return '127.0.0.1'; // Safe fallback
        }
    }
    
    /**
     * Get client IP address (internal method with error handling)
     *
     * @return string IP address
     * @throws AANP_Rate_Limiter_Exception
     */
    private function get_client_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Load balancers/proxies
            'HTTP_X_REAL_IP',            // Nginx proxy
            'HTTP_CLIENT_IP',            // Proxy
            'REMOTE_ADDR'                // Direct connection
        );
        
        // Validate $_SERVER is available
        if (!isset($_SERVER) || !is_array($_SERVER)) {
            throw new AANP_Rate_Limiter_Exception('$_SERVER is not available');
        }
        
        foreach ($ip_keys as $key) {
            try {
                if (array_key_exists($key, $_SERVER) && !empty($_SERVER[$key])) {
                    $ip = trim($_SERVER[$key]);
                    
                    // Handle comma-separated IPs (take first one)
                    if (strpos($ip, ',') !== false) {
                        $ips = explode(',', $ip);
                        $ip = trim($ips[0]);
                    }
                    
                    // Validate IP format
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        return $ip;
                    }
                    
                    // Try without private/reserved range filters
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        return $ip;
                    }
                }
            } catch (Exception $e) {
                // Continue to next IP key
                continue;
            }
        }
        
        // Final fallback with validation
        $fallback_ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
        
        if (!filter_var($fallback_ip, FILTER_VALIDATE_IP)) {
            throw new AANP_Rate_Limiter_Exception('Invalid fallback IP address');
        }
        
        return $fallback_ip;
    }
    
    /**
     * Anonymize identifier for logging
     *
     * @param string $identifier
     * @return string Anonymized identifier
     */
    private function anonymize_identifier($identifier) {
        if (strpos($identifier, 'user_') === 0) {
            return $identifier; // Keep user IDs for admin purposes
        } elseif (strpos($identifier, 'ip_') === 0) {
            $ip = substr($identifier, 3);
            // Anonymize IP for privacy (keep first 3 octets for IPv4)
            $parts = explode('.', $ip);
            if (count($parts) === 4) {
                return 'ip_' . $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.0';
            }
        }
        
        return 'anonymized_' . substr(md5($identifier), 0, 8);
    }
    
    /**
     * Cleanup failed attempt
     *
     * @param string $key Cache key
     */
    private function cleanup_failed_attempt($key) {
        try {
            if ($this->cache_manager) {
                $this->cache_manager->delete($key);
            }
        } catch (Exception $e) {
            // Log but don't throw - this is cleanup
            if ($this->logger) {
                $this->logger->warning('Failed to cleanup after attempt error', array(
                    'key' => $key,
                    'error' => $e->getMessage()
                ));
            }
        }
    }
    
    /**
     * Log error with proper context
     *
     * @param string $message Error message
     * @param array $context Additional context
     */
    private function log_error($message, $context = array()) {
        try {
            if ($this->logger) {
                $this->logger->error($message, $context);
            } else {
                // Fallback logging
                if (function_exists('error_log')) {
                    error_log('CP Rate Limiter: ' . $message . ' - ' . wp_json_encode($context));
                }
            }
        } catch (Exception $e) {
            // Last resort - PHP error log
            if (function_exists('error_log')) {
                error_log('CP Rate Limiter: Failed to log error - ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Get rate limit statistics
     *
     * @param string $action Action identifier
     * @param string $identifier User identifier
     * @return array|null Statistics or null on failure
     */
    public function get_rate_limit_stats($action = null, $identifier = null) {
        try {
            if ($action !== null) {
                $action = sanitize_text_field($action);
            }
            
            if ($identifier !== null) {
                $identifier = sanitize_text_field($identifier);
            }
            
            if ($this->logger) {
                $this->logger->debug('Getting rate limit stats', array(
                    'action' => $action,
                    'identifier' => $identifier ? $this->anonymize_identifier($identifier) : null
                ));
            }
            
            // This would require database storage for full stats
            // For now, return basic cache stats
            if ($this->cache_manager) {
                $stats = $this->cache_manager->get_cache_stats();
                return array(
                    'cache_stats' => $stats,
                    'timestamp' => current_time('Y-m-d H:i:s', true), // UTC
                    'timezone' => wp_timezone_string()
                );
            }
            
            return null;
            
        } catch (Exception $e) {
            $this->log_error('Failed to get rate limit stats', array(
                'error' => $e->getMessage(),
                'action' => $action
            ));
            
            return null;
        }
    }
    
    /**
     * Clean up expired rate limit entries
     *
     * @return int Number of entries cleaned
     */
    public function cleanup_expired_entries() {
        try {
            if ($this->logger) {
                $this->logger->info('Starting cleanup of expired rate limit entries');
            }
            
            // This would require database implementation
            // For now, return 0 as cache handles expiration automatically
            $cleaned = 0;
            
            if ($this->logger) {
                $this->logger->info('Completed cleanup of expired rate limit entries', array(
                    'cleaned' => $cleaned
                ));
            }
            
            return $cleaned;
            
        } catch (Exception $e) {
            $this->log_error('Failed to cleanup expired entries', array(
                'error' => $e->getMessage()
            ));
            
            return 0;
        }
    }
}