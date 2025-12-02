<?php
/**
 * Connection Pool Manager for Database and External Service Connections
 *
 * Manages connection pooling for databases, APIs, and external services
 * to improve performance and resource utilization.
 *
 * @package AI_Auto_News_Poster\Performance
 * @since 1.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Connection Pool Manager Class
 */
class AANP_ConnectionPoolManager {
    
    /**
     * Active connections
     * @var array
     */
    private $pools = array();
    
    /**
     * Connection configurations
     * @var array
     */
    private $configurations = array();
    
    /**
     * Pool statistics
     * @var array
     */
    private $stats = array();
    
    /**
     * Logger instance
     * @var AANP_Logger
     */
    private $logger;
    
    /**
     * Connection health monitor
     * @var array
     */
    private $health_monitor = array();
    
    /**
     * Maximum pool size
     * @var int
     */
    private $max_pool_size = 10;
    
    /**
     * Default connection timeout
     * @var int
     */
    private $default_timeout = 30;
    
    /**
     * Idle connection timeout
     * @var int
     */
    private $idle_timeout = 300; // 5 minutes
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = AANP_Logger::getInstance();
        
        $this->init_config();
        $this->init_hooks();
    }
    
    /**
     * Initialize configuration
     */
    private function init_config() {
        $options = get_option('aanp_settings', array());
        
        $this->max_pool_size = isset($options['connection_pool_size']) ? intval($options['connection_pool_size']) : 10;
        $this->default_timeout = isset($options['connection_timeout']) ? intval($options['connection_timeout']) : 30;
        $this->idle_timeout = isset($options['idle_timeout']) ? intval($options['idle_timeout']) : 300;
        
        // Initialize default configurations
        $this->configurations = array(
            'wordpress_db' => array(
                'type' => 'mysql',
                'host' => DB_HOST,
                'port' => 3306,
                'database' => DB_NAME,
                'username' => DB_USER,
                'password' => DB_PASSWORD,
                'charset' => defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4',
                'max_connections' => 5,
                'idle_timeout' => 180, // 3 minutes for DB
                'health_check_interval' => 60,
                'retry_attempts' => 3
            ),
            'redis' => array(
                'type' => 'redis',
                'host' => isset($options['redis_host']) ? $options['redis_host'] : '127.0.0.1',
                'port' => isset($options['redis_port']) ? intval($options['redis_port']) : 6379,
                'password' => isset($options['redis_password']) ? $options['redis_password'] : '',
                'database' => isset($options['redis_database']) ? intval($options['redis_database']) : 0,
                'max_connections' => 8,
                'idle_timeout' => $this->idle_timeout,
                'health_check_interval' => 30,
                'retry_attempts' => 3
            ),
            'http_client' => array(
                'type' => 'http',
                'max_connections' => 15,
                'idle_timeout' => $this->idle_timeout,
                'health_check_interval' => 120,
                'retry_attempts' => 3,
                'default_headers' => array(
                    'User-Agent' => 'ContentPilot/1.2.0',
                    'Accept' => 'application/json, text/html, */*',
                    'Accept-Encoding' => 'gzip, deflate'
                )
            )
        );
        
        // Initialize statistics
        foreach ($this->configurations as $name => $config) {
            $this->stats[$name] = array(
                'total_connections' => 0,
                'active_connections' => 0,
                'idle_connections' => 0,
                'failed_connections' => 0,
                'avg_connection_time' => 0,
                'total_requests' => 0,
                'success_requests' => 0,
                'failed_requests' => 0
            );
        }
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('init', array($this, 'initialize_pools'));
        add_action('aanp_connection_health_check', array($this, 'perform_health_checks'));
        
        // Schedule regular health checks
        if (!wp_next_scheduled('aanp_connection_health_check')) {
            wp_schedule_event(time(), 'every_5_minutes', 'aanp_connection_health_check');
        }
        
        // Clean up on shutdown
        register_shutdown_function(array($this, 'cleanup_all_pools'));
    }
    
    /**
     * Initialize connection pools
     */
    public function initialize_pools() {
        try {
            foreach ($this->configurations as $pool_name => $config) {
                if (!isset($this->pools[$pool_name])) {
                    $this->create_pool($pool_name, $config);
                }
            }
            
            $this->logger->info('Connection pools initialized', array(
                'pools' => array_keys($this->pools)
            ));
            
        } catch (Exception $e) {
            $this->logger->error('Failed to initialize connection pools', array(
                'error' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Create a new connection pool
     *
     * @param string $pool_name Pool name
     * @param array $config Pool configuration
     * @return bool Success status
     */
    private function create_pool($pool_name, $config) {
        try {
            $this->pools[$pool_name] = array(
                'config' => $config,
                'connections' => array(),
                'available_connections' => array(),
                'active_connections' => array(),
                'metadata' => array(
                    'created_at' => current_time('Y-m-d H:i:s'),
                    'last_used' => current_time('Y-m-d H:i:s'),
                    'total_connections_created' => 0
                )
            );
            
            // Initialize pool with minimum connections
            $min_connections = isset($config['min_connections']) ? intval($config['min_connections']) : 1;
            for ($i = 0; $i < $min_connections; $i++) {
                $connection = $this->create_connection($pool_name);
                if ($connection) {
                    $this->return_connection_to_pool($pool_name, $connection, true); // Initial connections go to available pool
                }
            }
            
            $this->logger->debug("Connection pool '{$pool_name}' created successfully");
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error("Failed to create connection pool '{$pool_name}'", array(
                'error' => $e->getMessage()
            ));
            
            return false;
        }
    }
    
    /**
     * Create a new connection
     *
     * @param string $pool_name Pool name
     * @return mixed Connection object or false on failure
     */
    private function create_connection($pool_name) {
        $start_time = microtime(true);
        
        try {
            $config = $this->configurations[$pool_name];
            $connection = false;
            
            switch ($config['type']) {
                case 'mysql':
                    $connection = $this->create_mysql_connection($config);
                    break;
                    
                case 'redis':
                    $connection = $this->create_redis_connection($config);
                    break;
                    
                case 'http':
                    $connection = $this->create_http_connection($config);
                    break;
                    
                default:
                    throw new Exception("Unknown connection type: {$config['type']}");
            }
            
            if ($connection) {
                // Add connection metadata
                $connection_id = uniqid('conn_', true);
                $connection->connection_id = $connection_id;
                $connection->created_at = current_time('Y-m-d H:i:s');
                $connection->last_used = current_time('Y-m-d H:i:s');
                $connection->pool_name = $pool_name;
                
                $creation_time = (microtime(true) - $start_time) * 1000; // ms
                
                // Update statistics
                $this->update_connection_stats($pool_name, 'connection_created', $creation_time);
                
                $this->logger->debug("Connection created for pool '{$pool_name}'", array(
                    'connection_id' => $connection_id,
                    'creation_time_ms' => $creation_time
                ));
                
                return $connection;
            }
            
            return false;
            
        } catch (Exception $e) {
            $creation_time = (microtime(true) - $start_time) * 1000;
            
            $this->update_connection_stats($pool_name, 'connection_failed', $creation_time, $e->getMessage());
            
            $this->logger->error("Failed to create connection for pool '{$pool_name}'", array(
                'error' => $e->getMessage(),
                'creation_time_ms' => $creation_time
            ));
            
            return false;
        }
    }
    
    /**
     * Create MySQL connection
     *
     * @param array $config Connection configuration
     * @return PDO|false MySQL connection or false on failure
     */
    private function create_mysql_connection($config) {
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $config['host'],
                $config['port'],
                $config['database'],
                $config['charset']
            );
            
            $options = array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => $this->default_timeout,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$config['charset']} COLLATE {$config['charset']}_unicode_ci"
            );
            
            $pdo = new PDO($dsn, $config['username'], $config['password'], $options);
            
            // Test connection
            $pdo->query('SELECT 1');
            
            return $pdo;
            
        } catch (Exception $e) {
            throw new Exception("MySQL connection failed: " . $e->getMessage());
        }
    }
    
    /**
     * Create Redis connection
     *
     * @param array $config Connection configuration
     * @return Redis|false Redis connection or false on failure
     */
    private function create_redis_connection($config) {
        if (!class_exists('Redis')) {
            throw new Exception('Redis extension not available');
        }
        
        try {
            $redis = new Redis();
            
            // Try different connection methods
            $connected = false;
            
            // Try persistent connection first
            try {
                $connected = $redis->pconnect($config['host'], $config['port']);
            } catch (Exception $e) {
                // Fallback to regular connection
                $connected = $redis->connect($config['host'], $config['port'], $this->default_timeout);
            }
            
            if (!$connected) {
                throw new Exception('Failed to connect to Redis');
            }
            
            // Authenticate if password is set
            if (!empty($config['password'])) {
                $redis->auth($config['password']);
            }
            
            // Select database
            if ($config['database'] > 0) {
                $redis->select($config['database']);
            }
            
            // Test connection
            $redis->ping();
            
            return $redis;
            
        } catch (Exception $e) {
            throw new Exception("Redis connection failed: " . $e->getMessage());
        }
    }
    
    /**
     * Create HTTP connection (wrapper for cURL)
     *
     * @param array $config Connection configuration
     * @return array HTTP configuration array
     */
    private function create_http_connection($config) {
        return array(
            'type' => 'http',
            'base_url' => '',
            'headers' => $config['default_headers'],
            'timeout' => $this->default_timeout,
            'max_redirects' => 5,
            'config' => $config
        );
    }
    
    /**
     * Get connection from pool
     *
     * @param string $pool_name Pool name
     * @param int $timeout Timeout in seconds
     * @return mixed Connection object or false on timeout
     */
    public function get_connection($pool_name, $timeout = null) {
        $timeout = $timeout ?? $this->default_timeout;
        $start_time = microtime(true);
        
        try {
            if (!isset($this->pools[$pool_name])) {
                throw new Exception("Connection pool '{$pool_name}' not found");
            }
            
            $pool = $this->pools[$pool_name];
            $max_wait_time = $timeout;
            
            while (microtime(true) - $start_time < $max_wait_time) {
                // Check if we have available connections
                if (!empty($pool['available_connections'])) {
                    $connection = array_shift($pool['available_connections']);
                    
                    // Verify connection is still healthy
                    if ($this->is_connection_healthy($pool_name, $connection)) {
                        // Mark as active
                        $pool['active_connections'][] = $connection;
                        $connection->last_used = current_time('Y-m-d H:i:s');
                        
                        // Update statistics
                        $this->update_pool_stats($pool_name, 'connection_borrowed');
                        
                        $this->logger->debug("Connection borrowed from pool '{$pool_name}'", array(
                            'connection_id' => $connection->connection_id ?? 'unknown'
                        ));
                        
                        return $connection;
                    } else {
                        // Remove unhealthy connection
                        $this->remove_unhealthy_connection($pool_name, $connection);
                    }
                }
                
                // Try to create new connection if pool not at max capacity
                if ($this->can_create_new_connection($pool_name)) {
                    $new_connection = $this->create_connection($pool_name);
                    if ($new_connection) {
                        // Immediately mark as active and return
                        $pool['active_connections'][] = $new_connection;
                        $new_connection->last_used = current_time('Y-m-d H:i:s');
                        
                        $this->update_pool_stats($pool_name, 'connection_created_and_borrowed');
                        
                        return $new_connection;
                    }
                }
                
                // Wait a bit before retrying
                usleep(100000); // 0.1 seconds
            }
            
            // Timeout reached
            $this->update_pool_stats($pool_name, 'connection_timeout');
            
            throw new Exception("Timeout waiting for connection from pool '{$pool_name}'");
            
        } catch (Exception $e) {
            $this->update_pool_stats($pool_name, 'connection_error', 0, $e->getMessage());
            
            $this->logger->error("Failed to get connection from pool '{$pool_name}'", array(
                'error' => $e->getMessage(),
                'timeout' => $timeout
            ));
            
            return false;
        }
    }
    
    /**
     * Return connection to pool
     *
     * @param string $pool_name Pool name
     * @param mixed $connection Connection object
     * @param bool $to_available_pool Whether to return to available pool (false means to return to pool entirely)
     * @return bool Success status
     */
    public function return_connection_to_pool($pool_name, $connection, $to_available_pool = true) {
        try {
            if (!isset($this->pools[$pool_name])) {
                throw new Exception("Connection pool '{$pool_name}' not found");
            }
            
            $pool = $this->pools[$pool_name];
            
            // Remove from active connections
            $active_index = array_search($connection, $pool['active_connections'], true);
            if ($active_index !== false) {
                unset($pool['active_connections'][$active_index]);
                $pool['active_connections'] = array_values($pool['active_connections']);
            }
            
            // Check if connection is still healthy
            if ($this->is_connection_healthy($pool_name, $connection)) {
                if ($to_available_pool) {
                    // Return to available pool for reuse
                    if (count($pool['available_connections']) < $pool['config']['max_connections']) {
                        $pool['available_connections'][] = $connection;
                    } else {
                        // Pool is full, close the connection
                        $this->close_connection($pool_name, $connection);
                    }
                }
                
                $this->update_pool_stats($pool_name, 'connection_returned');
                
                $this->logger->debug("Connection returned to pool '{$pool_name}'", array(
                    'connection_id' => $connection->connection_id ?? 'unknown'
                ));
                
                return true;
            } else {
                // Unhealthy connection, remove it
                $this->remove_unhealthy_connection($pool_name, $connection);
                return false;
            }
            
        } catch (Exception $e) {
            $this->logger->error("Failed to return connection to pool '{$pool_name}'", array(
                'error' => $e->getMessage()
            ));
            
            return false;
        }
    }
    
    /**
     * Check if connection is healthy
     *
     * @param string $pool_name Pool name
     * @param mixed $connection Connection object
     * @return bool True if healthy
     */
    private function is_connection_healthy($pool_name, $connection) {
        try {
            if (!$connection) {
                return false;
            }
            
            $config = $this->configurations[$pool_name];
            
            switch ($config['type']) {
                case 'mysql':
                    if ($connection instanceof PDO) {
                        // Simple query to test connection
                        $connection->query('SELECT 1');
                        return true;
                    }
                    break;
                    
                case 'redis':
                    if ($connection instanceof Redis) {
                        $result = $connection->ping();
                        return $result === 'PONG';
                    }
                    break;
                    
                case 'http':
                    // HTTP connections are stateless, always considered healthy
                    return true;
                    
                default:
                    return true; // Assume healthy if unknown type
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->logger->debug("Connection health check failed", array(
                'pool' => $pool_name,
                'connection_id' => $connection->connection_id ?? 'unknown',
                'error' => $e->getMessage()
            ));
            
            return false;
        }
    }
    
    /**
     * Remove unhealthy connection
     *
     * @param string $pool_name Pool name
     * @param mixed $connection Connection object
     */
    private function remove_unhealthy_connection($pool_name, $connection) {
        $this->close_connection($pool_name, $connection);
        $this->update_pool_stats($pool_name, 'connection_unhealthy');
        
        $this->logger->warning("Unhealthy connection removed from pool '{$pool_name}'", array(
            'connection_id' => $connection->connection_id ?? 'unknown'
        ));
    }
    
    /**
     * Close connection
     *
     * @param string $pool_name Pool name
     * @param mixed $connection Connection object
     */
    private function close_connection($pool_name, $connection) {
        try {
            $config = $this->configurations[$pool_name];
            
            switch ($config['type']) {
                case 'mysql':
                    if ($connection instanceof PDO) {
                        $connection = null; // PDO connections are closed automatically
                    }
                    break;
                    
                case 'redis':
                    if ($connection instanceof Redis) {
                        $connection->close();
                    }
                    break;
                    
                case 'http':
                    // HTTP connections don't need explicit closing
                    break;
            }
            
        } catch (Exception $e) {
            $this->logger->debug("Error closing connection", array(
                'pool' => $pool_name,
                'error' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Check if we can create a new connection
     *
     * @param string $pool_name Pool name
     * @return bool True if can create new connection
     */
    private function can_create_new_connection($pool_name) {
        if (!isset($this->pools[$pool_name])) {
            return false;
        }
        
        $pool = $this->pools[$pool_name];
        $total_connections = count($pool['available_connections']) + count($pool['active_connections']);
        
        return $total_connections < $pool['config']['max_connections'];
    }
    
    /**
     * Perform health checks on all pools
     */
    public function perform_health_checks() {
        try {
            foreach ($this->pools as $pool_name => $pool) {
                $this->check_pool_health($pool_name);
            }
            
            $this->logger->debug('Connection pool health checks completed');
            
        } catch (Exception $e) {
            $this->logger->error('Connection pool health check failed', array(
                'error' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Check health of a specific pool
     *
     * @param string $pool_name Pool name
     */
    private function check_pool_health($pool_name) {
        $pool = $this->pools[$pool_name];
        $config = $pool['config'];
        
        // Check idle connections
        $idle_threshold = isset($config['idle_timeout']) ? $config['idle_timeout'] : $this->idle_timeout;
        $current_time = current_time('timestamp');
        $connections_to_remove = array();
        
        foreach ($pool['available_connections'] as $index => $connection) {
            $last_used = strtotime($connection->last_used);
            if ($current_time - $last_used > $idle_threshold) {
                $connections_to_remove[] = $index;
            }
        }
        
        // Remove idle connections
        foreach ($connections_to_remove as $index) {
            $connection = $pool['available_connections'][$index];
            $this->close_connection($pool_name, $connection);
            unset($pool['available_connections'][$index]);
        }
        
        if (!empty($connections_to_remove)) {
            $pool['available_connections'] = array_values($pool['available_connections']);
            
            $this->logger->debug("Removed idle connections from pool '{$pool_name}'", array(
                'removed_count' => count($connections_to_remove)
            ));
        }
        
        // Ensure minimum connections
        $min_connections = isset($config['min_connections']) ? intval($config['min_connections']) : 1;
        $total_connections = count($pool['available_connections']) + count($pool['active_connections']);
        
        if ($total_connections < $min_connections) {
            $connections_to_create = $min_connections - $total_connections;
            
            for ($i = 0; $i < $connections_to_create; $i++) {
                $connection = $this->create_connection($pool_name);
                if ($connection) {
                    $pool['available_connections'][] = $connection;
                }
            }
        }
    }
    
    /**
     * Update connection statistics
     *
     * @param string $pool_name Pool name
     * @param string $operation Operation type
     * @param float $response_time Response time in milliseconds
     * @param string $error Error message if any
     */
    private function update_connection_stats($pool_name, $operation, $response_time = 0, $error = '') {
        if (!isset($this->stats[$pool_name])) {
            return;
        }
        
        $stat = &$this->stats[$pool_name];
        
        switch ($operation) {
            case 'connection_created':
                $stat['total_connections']++;
                break;
                
            case 'connection_failed':
                $stat['failed_connections']++;
                break;
                
            case 'request_success':
                $stat['success_requests']++;
                $stat['total_requests']++;
                break;
                
            case 'request_failed':
                $stat['failed_requests']++;
                $stat['total_requests']++;
                break;
        }
        
        // Update average connection time
        if ($response_time > 0) {
            $stat['avg_connection_time'] = ($stat['avg_connection_time'] + $response_time) / 2;
        }
    }
    
    /**
     * Update pool statistics
     *
     * @param string $pool_name Pool name
     * @param string $operation Operation type
     */
    private function update_pool_stats($pool_name, $operation) {
        if (!isset($this->pools[$pool_name])) {
            return;
        }
        
        $pool = $this->pools[$pool_name];
        $stat = &$this->stats[$pool_name];
        
        $stat['active_connections'] = count($pool['active_connections']);
        $stat['idle_connections'] = count($pool['available_connections']);
    }
    
    /**
     * Execute query using connection pool
     *
     * @param string $pool_name Pool name
     * @param string $query SQL query
     * @param array $params Query parameters
     * @param int $max_retries Maximum retry attempts
     * @return mixed Query result or false on failure
     */
    public function execute_query($pool_name, $query, $params = array(), $max_retries = 3) {
        $connection = null;
        $retry_count = 0;
        
        while ($retry_count <= $max_retries) {
            $connection = $this->get_connection($pool_name);
            
            if (!$connection) {
                $retry_count++;
                if ($retry_count > $max_retries) {
                    return false;
                }
                
                // Wait before retry
                usleep(500000); // 0.5 seconds
                continue;
            }
            
            try {
                $result = null;
                
                if ($this->configurations[$pool_name]['type'] === 'mysql') {
                    if ($connection instanceof PDO) {
                        $stmt = $connection->prepare($query);
                        $stmt->execute($params);
                        $result = $stmt->fetchAll();
                    }
                } elseif ($this->configurations[$pool_name]['type'] === 'redis') {
                    if ($connection instanceof Redis) {
                        // This would be Redis-specific query handling
                        $result = $connection->info();
                    }
                }
                
                $this->update_connection_stats($pool_name, 'request_success', 0);
                
                // Return connection to pool
                $this->return_connection_to_pool($pool_name, $connection);
                
                return $result;
                
            } catch (Exception $e) {
                $this->update_connection_stats($pool_name, 'request_failed', 0, $e->getMessage());
                
                // Connection might be broken, don't return it to pool
                $this->close_connection($pool_name, $connection);
                
                if ($retry_count >= $max_retries) {
                    throw $e;
                }
                
                $retry_count++;
                usleep(1000000); // 1 second wait before retry
            }
        }
        
        return false;
    }
    
    /**
     * Get connection pool statistics
     *
     * @param string $pool_name Optional specific pool name
     * @return array Pool statistics
     */
    public function get_pool_stats($pool_name = null) {
        if ($pool_name) {
            return isset($this->stats[$pool_name]) ? $this->stats[$pool_name] : array();
        }
        
        return $this->stats;
    }
    
    /**
     * Get connection pool information
     *
     * @param string $pool_name Pool name
     * @return array Pool information
     */
    public function get_pool_info($pool_name) {
        if (!isset($this->pools[$pool_name])) {
            return array();
        }
        
        $pool = $this->pools[$pool_name];
        
        return array(
            'name' => $pool_name,
            'config' => $pool['config'],
            'active_connections' => count($pool['active_connections']),
            'idle_connections' => count($pool['available_connections']),
            'total_connections' => count($pool['active_connections']) + count($pool['available_connections']),
            'max_connections' => $pool['config']['max_connections'],
            'utilization_percent' => (
                (count($pool['active_connections']) + count($pool['available_connections'])) / 
                $pool['config']['max_connections']
            ) * 100,
            'metadata' => $pool['metadata']
        );
    }
    
    /**
     * Clear all connection pools
     *
     * @return bool Success status
     */
    public function clear_all_pools() {
        try {
            foreach ($this->pools as $pool_name => $pool) {
                $this->clear_pool($pool_name);
            }
            
            $this->logger->info('All connection pools cleared');
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to clear connection pools', array(
                'error' => $e->getMessage()
            ));
            
            return false;
        }
    }
    
    /**
     * Clear a specific connection pool
     *
     * @param string $pool_name Pool name
     * @return bool Success status
     */
    public function clear_pool($pool_name) {
        if (!isset($this->pools[$pool_name])) {
            return false;
        }
        
        try {
            $pool = $this->pools[$pool_name];
            
            // Close all connections
            foreach ($pool['available_connections'] as $connection) {
                $this->close_connection($pool_name, $connection);
            }
            
            foreach ($pool['active_connections'] as $connection) {
                $this->close_connection($pool_name, $connection);
            }
            
            // Reset pool
            $this->pools[$pool_name]['available_connections'] = array();
            $this->pools[$pool_name]['active_connections'] = array();
            
            $this->logger->debug("Connection pool '{$pool_name}' cleared");
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error("Failed to clear connection pool '{$pool_name}'", array(
                'error' => $e->getMessage()
            ));
            
            return false;
        }
    }
    
    /**
     * Cleanup all pools on shutdown
     */
    public function cleanup_all_pools() {
        $this->clear_all_pools();
    }
    
    /**
     * Health check method
     *
     * @return bool Health status
     */
    public function health_check() {
        try {
            foreach ($this->pools as $pool_name => $pool) {
                // Check if pool has any healthy connections
                $healthy_connections = 0;
                
                foreach ($pool['available_connections'] as $connection) {
                    if ($this->is_connection_healthy($pool_name, $connection)) {
                        $healthy_connections++;
                    }
                }
                
                foreach ($pool['active_connections'] as $connection) {
                    if ($this->is_connection_healthy($pool_name, $connection)) {
                        $healthy_connections++;
                    }
                }
                
                if ($healthy_connections === 0) {
                    $this->logger->warning("No healthy connections in pool '{$pool_name}'");
                    return false;
                }
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('Connection pool manager health check failed', array(
                'error' => $e->getMessage()
            ));
            return false;
        }
    }
    
    /**
     * Get service metrics
     *
     * @return array Service metrics
     */
    public function get_metrics() {
        return array(
            'service' => 'ConnectionPoolManager',
            'pools' => array_keys($this->pools),
            'stats' => $this->stats,
            'max_pool_size' => $this->max_pool_size,
            'default_timeout' => $this->default_timeout,
            'idle_timeout' => $this->idle_timeout,
            'memory_usage' => memory_get_usage(true),
            'timestamp' => current_time('Y-m-d H:i:s')
        );
    }
}