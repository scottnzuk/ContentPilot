<?php
/**
 * Custom Exception Classes for AI Auto News Poster
 *
 * @package ContentPilot
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Base exception class for all plugin-specific exceptions
 */
abstract class CP_Exception extends Exception {
    
    /**
     * Additional context data for the exception
     */
    protected $context = array();
    
    /**
     * Error category for classification
     */
    protected $category = 'SYSTEM';
    
    /**
     * Constructor
     * 
     * @param string $message Exception message
     * @param int $code Exception code
     * @param Exception|null $previous Previous exception
     * @param array $context Additional context data
     * @param string $category Error category
     */
    public function __construct($message = '', $code = 0, Exception $previous = null, $context = array(), $category = 'SYSTEM') {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
        $this->category = $category;
    }
    
    /**
     * Get exception context data
     * 
     * @return array Context data
     */
    public function get_context() {
        return $this->context;
    }
    
    /**
     * Get error category
     * 
     * @return string Error category
     */
    public function get_category() {
        return $this->category;
    }
    
    /**
     * Get user-friendly message
     * 
     * @param CP_Error_Handler|null $error_handler Error handler instance
     * @return string User-friendly message
     */
    public function get_user_friendly_message($error_handler = null) {
        if ($error_handler && method_exists($error_handler, 'get_user_friendly_message')) {
            return $error_handler->get_user_friendly_message($this->getMessage(), $this->category);
        }
        
        return $this->getDefaultUserFriendlyMessage();
    }
    
    /**
     * Get default user-friendly message based on exception type
     * 
     * @return string Default user-friendly message
     */
    protected function getDefaultUserFriendlyMessage() {
        return __('An unexpected error occurred while using the plugin.', 'cp');
    }
    
    /**
     * Log exception using error handler
     * 
     * @param CP_Error_Handler|null $error_handler Error handler instance
     * @param string $level Log level
     */
    public function log($error_handler = null, $level = 'ERROR') {
        if ($error_handler && method_exists($error_handler, 'log_error')) {
            $error_handler->log_error(
                $this->getMessage(),
                array_merge($this->context, array(
                    'exception_class' => get_class($this),
                    'file' => $this->getFile(),
                    'line' => $this->getLine(),
                    'trace' => $this->getTraceAsString()
                )),
                $this->category,
                $level
            );
        } else {
            // Fallback to PHP error log
            error_log(sprintf(
                'ContentPilot %s: %s in %s:%d - Context: %s',
                $level,
                $this->getMessage(),
                $this->getFile(),
                $this->getLine(),
                wp_json_encode($this->context)
            ));
        }
    }
}

/**
 * Network-related exceptions
 */
class CP_Network_Exception extends CP_Exception {
    protected $category = 'NETWORK';
    
    protected function getDefaultUserFriendlyMessage() {
        return __('Network connectivity issue occurred. Please check your internet connection and try again.', 'cp');
    }
}

/**
 * API-related exceptions
 */
class CP_API_Exception extends CP_Exception {
    protected $category = 'API';
    
    protected function getDefaultUserFriendlyMessage() {
        return __('The AI service is temporarily unavailable. Please try again later.', 'cp');
    }
}

/**
 * Database-related exceptions
 */
class CP_Database_Exception extends CP_Exception {
    protected $category = 'DATABASE';
    
    protected function getDefaultUserFriendlyMessage() {
        return __('A database error occurred. Please contact support if the issue persists.', 'cp');
    }
}

/**
 * Security-related exceptions
 */
class CP_Security_Exception extends CP_Exception {
    protected $category = 'SECURITY';
    
    protected function getDefaultUserFriendlyMessage() {
        return __('A security check failed. Please try again or contact support.', 'cp');
    }
}

/**
 * Configuration-related exceptions
 */
class CP_Configuration_Exception extends CP_Exception {
    protected $category = 'CONFIGURATION';
    
    protected function getDefaultUserFriendlyMessage() {
        return __('A configuration issue was detected. Please check your plugin settings.', 'cp');
    }
}

/**
 * User input validation exceptions
 */
class CP_Validation_Exception extends CP_Exception {
    protected $category = 'USER_INPUT';
    
    protected function getDefaultUserFriendlyMessage() {
        return __('There was an issue with the provided information. Please check your input and try again.', 'cp');
    }
}

/**
 * Cache-related exceptions
 */
class CP_Cache_Exception extends CP_Exception {
    protected $category = 'SYSTEM';
    
    protected function getDefaultUserFriendlyMessage() {
        return __('A caching system error occurred. Your data is safe and the system will attempt to recover.', 'cp');
    }
}

/**
 * Rate limiting exceptions
 */
class CP_Rate_Limit_Exception extends CP_Exception {
    protected $category = 'SYSTEM';
    
    /**
     * Remaining time before limit resets (in seconds)
     */
    private $retry_after = 0;
    
    /**
     * Constructor
     * 
     * @param string $message Exception message
     * @param int $code Exception code
     * @param Exception|null $previous Previous exception
     * @param array $context Additional context data
     * @param int $retry_after Time to wait before retrying (seconds)
     */
    public function __construct($message = '', $code = 0, Exception $previous = null, $context = array(), $retry_after = 0) {
        parent::__construct($message, $code, $previous, $context, 'SYSTEM');
        $this->retry_after = $retry_after;
    }
    
    /**
     * Get retry after time
     * 
     * @return int Retry after time in seconds
     */
    public function get_retry_after() {
        return $this->retry_after;
    }
    
    protected function getDefaultUserFriendlyMessage() {
        if ($this->retry_after > 0) {
            return sprintf(
                /* translators: %d: number of seconds */
                __('Rate limit exceeded. Please wait %d seconds before trying again.', 'cp'),
                $this->retry_after
            );
        }
        
        return __('Rate limit exceeded. Please wait before trying again.', 'cp');
    }
}

/**
 * RSS feed-related exceptions
 */
class CP_RSS_Exception extends CP_Exception {
    protected $category = 'NETWORK';
    
    protected function getDefaultUserFriendlyMessage() {
        return __('Unable to fetch content from RSS feed. The feed may be temporarily unavailable.', 'cp');
    }
}

/**
 * AI content generation exceptions
 */
class CP_AI_Exception extends CP_Exception {
    protected $category = 'API';
    
    protected function getDefaultUserFriendlyMessage() {
        return __('Failed to generate content using AI service. Please check your API settings and try again.', 'cp');
    }
}

/**
 * Post creation exceptions
 */
class CP_Post_Creation_Exception extends CP_Exception {
    protected $category = 'SYSTEM';
    
    protected function getDefaultUserFriendlyMessage() {
        return __('Failed to create blog post. Please check your WordPress permissions and try again.', 'cp');
    }
}

/**
 * Plugin initialization exceptions
 */
class CP_Initialization_Exception extends CP_Exception {
    protected $category = 'SYSTEM';
    
    protected function getDefaultUserFriendlyMessage() {
        return __('Plugin initialization failed. Please check your server configuration and try again.', 'cp');
    }
}

/**
 * Feature availability exceptions (e.g., Pro features)
 */
class CP_Feature_Exception extends CP_Exception {
    protected $category = 'SYSTEM';
    
    protected function getDefaultUserFriendlyMessage() {
        return __('This feature is now included in the core plugin.', 'cp');
    }
}

/**
 * HTTP request exceptions
 */
class CP_HTTP_Exception extends CP_Exception {
    protected $category = 'NETWORK';
    
    /**
     * HTTP status code
     */
    private $status_code = 0;
    
    /**
     * Constructor
     * 
     * @param string $message Exception message
     * @param int $code Exception code
     * @param Exception|null $previous Previous exception
     * @param array $context Additional context data
     * @param int $status_code HTTP status code
     */
    public function __construct($message = '', $code = 0, Exception $previous = null, $context = array(), $status_code = 0) {
        parent::__construct($message, $code, $previous, $context, 'NETWORK');
        $this->status_code = $status_code;
    }
    
    /**
     * Get HTTP status code
     * 
     * @return int HTTP status code
     */
    public function get_status_code() {
        return $this->status_code;
    }
    
    protected function getDefaultUserFriendlyMessage() {
        switch ($this->status_code) {
            case 400:
                return __('Bad request. Please check your API configuration.', 'cp');
            case 401:
                return __('Authentication failed. Please check your API key.', 'cp');
            case 403:
                return __('Access forbidden. Please check your API permissions.', 'cp');
            case 404:
                return __('Requested resource not found.', 'cp');
            case 429:
                return __('Too many requests. Please wait and try again later.', 'cp');
            case 500:
                return __('Server error occurred. Please try again later.', 'cp');
            case 502:
            case 503:
            case 504:
                return __('Service temporarily unavailable. Please try again later.', 'cp');
            default:
                if ($this->status_code >= 400 && $this->status_code < 500) {
                    return __('Client error occurred. Please check your request.', 'cp');
                } elseif ($this->status_code >= 500) {
                    return __('Server error occurred. Please try again later.', 'cp');
                }
                return parent::getDefaultUserFriendlyMessage();
        }
    }
}

/**
 * File system exceptions
 */
class CP_File_Exception extends CP_Exception {
    protected $category = 'SYSTEM';
    
    protected function getDefaultUserFriendlyMessage() {
        return __('File system error occurred. Please check file permissions and try again.', 'cp');
    }
}

/**
 * Plugin version compatibility exceptions
 */
class CP_Compatibility_Exception extends CP_Exception {
    protected $category = 'SYSTEM';
    
    protected function getDefaultUserFriendlyMessage() {
        return __('Version compatibility issue detected. Please update the plugin or your system.', 'cp');
    }
}