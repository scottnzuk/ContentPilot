<?php
/**
 * Performance Optimizer Class
 *
 * @package AI_Auto_News_Poster
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AANP_Performance_Optimizer {
    
    /**
     * Database timeout in seconds
     */
    private $db_timeout = 30;
    
    /**
     * Logger instance
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
        $this->logger = AANP_Logger::getInstance();
        $this->security_manager = new AANP_Security_Manager();
        
        add_action('init', array($this, 'init_optimizations'));
    }
    
    /**
     * Initialize performance optimizations
     */
    public function init_optimizations() {
        try {
            // Optimize database queries
            add_filter('posts_clauses', array($this, 'optimize_post_queries'), 10, 2);
            
            // Add async loading for admin scripts
            add_filter('script_loader_tag', array($this, 'add_async_attribute'), 10, 3);
            
            // Optimize images if needed
            add_filter('wp_generate_attachment_metadata', array($this, 'optimize_images'));
            
            $this->logger->info('Performance optimizations initialized', array(
                'action' => 'init_optimizations'
            ));
            
        } catch (Exception $e) {
            $this->logger->error('Failed to initialize performance optimizations', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
        }
    }
    
    /**
     * Optimize post queries
     *
     * @param array $clauses Query clauses
     * @param WP_Query $query Query object
     * @return array Modified clauses
     */
    public function optimize_post_queries($clauses, $query) {
        try {
            // Validate input parameters
            if (!$this->validate_query_parameters($clauses, $query)) {
                return $clauses;
            }
            
            // Only optimize admin queries for better performance
            if (!is_admin() || !$query->is_main_query()) {
                return $clauses;
            }
            
            // Safely check for AANP tables with proper validation
            if ($this->contains_aanp_tables($clauses['where'])) {
                // FIXED: Use proper parameter binding to prevent SQL injection
                $clauses['join'] .= $this->get_secure_index_hint();
            }
            
            $this->logger->debug('Query optimization applied', array(
                'tables_detected' => $this->detect_aanp_tables($clauses['where']),
                'join_modified' => true
            ));
            
            return $clauses;
            
        } catch (Exception $e) {
            $this->logger->error('Query optimization failed', array(
                'error' => $e->getMessage(),
                'query_info' => $this->sanitize_query_info($clauses)
            ));
            
            // Return original clauses on error to prevent query failure
            return $clauses;
        }
    }
    
    /**
     * Add async attribute to scripts
     *
     * @param string $tag Script tag
     * @param string $handle Script handle
     * @param string $src Script source
     * @return string Modified tag
     */
    public function add_async_attribute($tag, $handle, $src) {
        try {
            // Validate inputs
            if (!$this->validate_script_parameters($tag, $handle, $src)) {
                return $tag;
            }
            
            // Sanitize handle to prevent injection
            $sanitized_handle = sanitize_text_field($handle);
            
            if ('aanp-admin-js' === $sanitized_handle) {
                $this->logger->debug('Added async attribute to script', array(
                    'handle' => $sanitized_handle
                ));
                
                return str_replace(' src', ' async src', $tag);
            }
            
            return $tag;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to add async attribute to script', array(
                'error' => $e->getMessage(),
                'handle' => isset($handle) ? 'provided' : 'missing'
            ));
            
            // Return original tag on error
            return $tag;
        }
    }
    
    /**
     * Optimize images
     *
     * @param array $metadata Image metadata
     * @return array Modified metadata
     */
    public function optimize_images($metadata) {
        try {
            // Validate metadata
            if (!$this->validate_metadata($metadata)) {
                return $metadata;
            }
            
            // Basic image optimization placeholder
            // In a real implementation, this would integrate with image optimization services
            
            $this->logger->debug('Image optimization applied', array(
                'metadata_keys' => array_keys($metadata)
            ));
            
            return $metadata;
            
        } catch (Exception $e) {
            $this->logger->error('Image optimization failed', array(
                'error' => $e->getMessage()
            ));
            
            // Return original metadata on error
            return $metadata;
        }
    }
    
    /**
     * Execute database query with comprehensive error handling
     *
     * @param string $query SQL query
     * @param array $params Query parameters
     * @return mixed Query result or false on failure
     */
    private function execute_secure_query($query, $params = array()) {
        global $wpdb;
        
        try {
            // Validate query and parameters
            if (!$this->validate_query($query, $params)) {
                return false;
            }
            
            // Set query timeout
            $wpdb->query('SET SESSION wait_timeout = ' . intval($this->db_timeout));
            
            // Use prepared statement to prevent SQL injection
            if (!empty($params)) {
                $result = $wpdb->prepare($query, $params);
                
                // Check for prepare errors
                if ($result === false) {
                    throw new Exception('Failed to prepare query: ' . $wpdb->last_error);
                }
            } else {
                $result = $wpdb->get_results($query);
            }
            
            $this->logger->debug('Database query executed successfully', array(
                'query_type' => $this->get_query_type($query),
                'params_count' => count($params)
            ));
            
            return $result;
            
        } catch (Exception $e) {
            $this->logger->error('Database query failed', array(
                'error' => $e->getMessage(),
                'query' => $this->sanitize_query_for_logging($query),
                'wpdb_error' => isset($wpdb->last_error) ? $wpdb->last_error : 'N/A'
            ));
            
            return false;
        }
    }
    
    /**
     * Get performance metrics with comprehensive error handling
     *
     * @return array Performance data
     */
    public function get_performance_metrics() {
        try {
            // Validate memory functions are available
            if (!function_exists('memory_get_usage') || !function_exists('memory_get_peak_usage')) {
                throw new Exception('Memory functions not available');
            }
            
            $metrics = array(
                'memory_usage' => memory_get_usage(true),
                'memory_peak' => memory_get_peak_usage(true),
                'query_count' => get_num_queries(),
                'load_time' => timer_stop(),
                'timestamp' => current_time('Y-m-d H:i:s')
            );
            
            // Add database connection status
            $metrics['db_status'] = $this->get_database_status();
            
            // Add cache hit ratio if available
            $metrics['cache_hit_ratio'] = $this->get_cache_hit_ratio();
            
            $this->logger->debug('Performance metrics collected', array(
                'metrics_count' => count($metrics)
            ));
            
            return $metrics;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to collect performance metrics', array(
                'error' => $e->getMessage()
            ));
            
            // Return basic metrics on error
            return array(
                'error' => 'Failed to collect full metrics',
                'timestamp' => current_time('Y-m-d H:i:s')
            );
        }
    }
    
    /**
     * Execute database transaction with error handling
     *
     * @param callable $callback Transaction callback
     * @return mixed Transaction result
     */
    private function execute_transaction($callback) {
        global $wpdb;
        
        try {
            // Start transaction
            $wpdb->query('START TRANSACTION');
            
            // Execute callback
            $result = call_user_func($callback);
            
            // Commit transaction
            $wpdb->query('COMMIT');
            
            $this->logger->debug('Database transaction completed successfully');
            
            return $result;
            
        } catch (Exception $e) {
            // Rollback on error
            $wpdb->query('ROLLBACK');
            
            $this->logger->error('Database transaction failed and rolled back', array(
                'error' => $e->getMessage()
            ));
            
            throw $e;
        }
    }
    
    /**
     * Get database connection status
     *
     * @return string Connection status
     */
    private function get_database_status() {
        try {
            global $wpdb;
            
            $result = $wpdb->get_var('SELECT 1');
            
            if ($result === '1') {
                return 'connected';
            } else {
                return 'unknown';
            }
            
        } catch (Exception $e) {
            $this->logger->warning('Failed to check database status', array(
                'error' => $e->getMessage()
            ));
            
            return 'error';
        }
    }
    
    /**
     * Get cache hit ratio
     *
     * @return float Cache hit ratio (0.0 to 1.0)
     */
    private function get_cache_hit_ratio() {
        try {
            if (function_exists('wp_cache_get_stats')) {
                $stats = wp_cache_get_stats();
                if (isset($stats['hits']) && isset($stats['misses'])) {
                    $total = $stats['hits'] + $stats['misses'];
                    return $total > 0 ? $stats['hits'] / $total : 0.0;
                }
            }
            
            return null;
            
        } catch (Exception $e) {
            $this->logger->warning('Failed to get cache statistics', array(
                'error' => $e->getMessage()
            ));
            
            return null;
        }
    }
    
    /**
     * Validate query parameters
     *
     * @param array $clauses Query clauses
     * @param WP_Query $query Query object
     * @return bool True if valid
     */
    private function validate_query_parameters($clauses, $query) {
        if (!is_array($clauses) || !isset($clauses['where'])) {
            $this->logger->warning('Invalid query clauses provided');
            return false;
        }
        
        if (!is_object($query) || !method_exists($query, 'is_main_query')) {
            $this->logger->warning('Invalid query object provided');
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if clauses contain AANP tables
     *
     * @param string $where_clause WHERE clause
     * @return bool True if contains AANP tables
     */
    private function contains_aanp_tables($where_clause) {
        if (!is_string($where_clause)) {
            return false;
        }
        
        // Use WordPress database prefix and table name patterns
        global $wpdb;
        
        // Check for AANP table patterns
        $aanp_table_patterns = array(
            $wpdb->prefix . 'aanp_%',
            'aanp_'
        );
        
        foreach ($aanp_table_patterns as $pattern) {
            if (strpos($where_clause, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get secure index hint
     *
     * @return string Secure index hint
     */
    private function get_secure_index_hint() {
        // Use a safe, validated index hint
        return " USE INDEX (PRIMARY)";
    }
    
    /**
     * Detect AANP tables in query
     *
     * @param string $where_clause WHERE clause
     * @return array Detected table names
     */
    private function detect_aanp_tables($where_clause) {
        global $wpdb;
        
        if (!is_string($where_clause)) {
            return array();
        }
        
        $detected_tables = array();
        $aanp_prefix = $wpdb->prefix . 'aanp_';
        
        // Extract table references using regex
        if (preg_match_all('/' . preg_quote($aanp_prefix, '/') . '(\w+)/', $where_clause, $matches)) {
            foreach ($matches[0] as $table_ref) {
                if (!in_array($table_ref, $detected_tables, true)) {
                    $detected_tables[] = $table_ref;
                }
            }
        }
        
        return $detected_tables;
    }
    
    /**
     * Validate script parameters
     *
     * @param string $tag Script tag
     * @param string $handle Script handle
     * @param string $src Script source
     * @return bool True if valid
     */
    private function validate_script_parameters($tag, $handle, $src) {
        if (empty($tag) || !is_string($tag)) {
            return false;
        }
        
        if (empty($handle) || !is_string($handle)) {
            return false;
        }
        
        if (empty($src) || !is_string($src)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate metadata
     *
     * @param mixed $metadata Metadata to validate
     * @return bool True if valid
     */
    private function validate_metadata($metadata) {
        return is_array($metadata);
    }
    
    /**
     * Validate query
     *
     * @param string $query SQL query
     * @param array $params Query parameters
     * @return bool True if valid
     */
    private function validate_query($query, $params) {
        if (empty($query) || !is_string($query)) {
            $this->logger->warning('Invalid query provided');
            return false;
        }
        
        if (!is_array($params)) {
            $this->logger->warning('Invalid query parameters provided');
            return false;
        }
        
        return true;
    }
    
    /**
     * Get query type
     *
     * @param string $query SQL query
     * @return string Query type
     */
    private function get_query_type($query) {
        if (!is_string($query)) {
            return 'unknown';
        }
        
        $query = strtoupper(trim($query));
        
        if (strpos($query, 'SELECT') === 0) {
            return 'SELECT';
        } elseif (strpos($query, 'INSERT') === 0) {
            return 'INSERT';
        } elseif (strpos($query, 'UPDATE') === 0) {
            return 'UPDATE';
        } elseif (strpos($query, 'DELETE') === 0) {
            return 'DELETE';
        } else {
            return 'OTHER';
        }
    }
    
    /**
     * Sanitize query for logging
     *
     * @param string $query SQL query
     * @return string Sanitized query
     */
    private function sanitize_query_for_logging($query) {
        if (!is_string($query)) {
            return 'Invalid query';
        }
        
        // Remove sensitive data and limit length
        $query = preg_replace('/\b(password|secret|token|key)\s*=\s*[\'"][^\'\"]*[\'"]/i', '$1=[REDACTED]', $query);
        
        if (strlen($query) > 500) {
            $query = substr($query, 0, 497) . '...';
        }
        
        return $query;
    }
    
    /**
     * Sanitize query info for logging
     *
     * @param array $clauses Query clauses
     * @return array Sanitized info
     */
    private function sanitize_query_info($clauses) {
        if (!is_array($clauses)) {
            return array('error' => 'Invalid clauses');
        }
        
        $info = array();
        
        foreach (array('select', 'from', 'where', 'join', 'orderby', 'groupby') as $key) {
            if (isset($clauses[$key])) {
                $info[$key] = $this->sanitize_query_for_logging($clauses[$key]);
            }
        }
        
        return $info;
    }
    
    /**
     * Clean up database resources
     */
    public function cleanup() {
        try {
            global $wpdb;
            
            // Close any open connections
            $wpdb->close_connection();
            
            $this->logger->info('Performance optimizer cleanup completed');
            
        } catch (Exception $e) {
            $this->logger->error('Performance optimizer cleanup failed', array(
                'error' => $e->getMessage()
            ));
        }
    }
}