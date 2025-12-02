<?php
/**
 * Queue Manager for Asynchronous Task Processing
 *
 * Handles task queuing, priority processing, worker management,
 * and background job execution with retry mechanisms.
 *
 * @package AI_Auto_News_Poster\Performance
 * @since 1.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Queue Manager Class
 */
class AANP_QueueManager {
    
    /**
     * Queue storage backend
     * @var AANP_AdvancedCacheManager
     */
    private $cache_manager;
    
    /**
     * Connection pool manager
     * @var AANP_ConnectionPoolManager
     */
    private $connection_pool;
    
    /**
     * Logger instance
     * @var AANP_Logger
     */
    private $logger;
    
    /**
     * Queue configuration
     * @var array
     */
    private $config = array();
    
    /**
     * Active queues
     * @var array
     */
    private $queues = array();
    
    /**
     * Worker processes
     * @var array
     */
    private $workers = array();
    
    /**
     * Queue statistics
     * @var array
     */
    private $stats = array();
    
    /**
     * Processing locks
     * @var array
     */
    private $processing_locks = array();
    
    /**
     * Retry configurations
     * @var array
     */
    private $retry_configs = array(
        'max_attempts' => 3,
        'initial_delay' => 60, // 1 minute
        'max_delay' => 3600, // 1 hour
        'backoff_multiplier' => 2
    );
    
    /**
     * Queue priorities (higher number = higher priority)
     * @var array
     */
    private $priorities = array(
        'critical' => 100,
        'high' => 75,
        'normal' => 50,
        'low' => 25,
        'bulk' => 10
    );
    
    /**
     * Constructor
     *
     * @param AANP_AdvancedCacheManager $cache_manager
     * @param AANP_ConnectionPoolManager $connection_pool
     */
    public function __construct(
        AANP_AdvancedCacheManager $cache_manager = null,
        AANP_ConnectionPoolManager $connection_pool = null
    ) {
        $this->cache_manager = $cache_manager ?: new AANP_AdvancedCacheManager();
        $this->connection_pool = $connection_pool;
        $this->logger = AANP_Logger::getInstance();
        
        $this->init_config();
        $this->init_hooks();
        $this->init_default_queues();
    }
    
    /**
     * Initialize queue configuration
     */
    private function init_config() {
        $options = get_option('aanp_settings', array());
        
        $this->config = array(
            'default_queue' => 'general',
            'max_workers' => isset($options['queue_max_workers']) ? intval($options['queue_max_workers']) : 3,
            'max_tasks_per_worker' => isset($options['queue_max_tasks_per_worker']) ? intval($options['queue_max_tasks_per_worker']) : 100,
            'task_timeout' => isset($options['queue_task_timeout']) ? intval($options['queue_task_timeout']) : 300, // 5 minutes
            'queue_cleanup_interval' => isset($options['queue_cleanup_interval']) ? intval($options['queue_cleanup_interval']) : 3600, // 1 hour
            'enable_persistent_workers' => isset($options['enable_persistent_workers']) ? (bool) $options['enable_persistent_workers'] : false,
            'batch_processing' => isset($options['enable_batch_processing']) ? (bool) $options['enable_batch_processing'] : true,
            'batch_size' => isset($options['queue_batch_size']) ? intval($options['queue_batch_size']) : 10,
            'enable_monitoring' => true
        );
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('aanp_process_queue', array($this, 'process_queue'));
        add_action('aanp_queue_worker', array($this, 'process_worker_tasks'));
        add_action('init', array($this, 'schedule_queue_processing'));
        
        // Schedule regular queue processing
        $this->schedule_queue_processing();
        
        // Register AJAX handlers for queue management
        add_action('wp_ajax_aanp_queue_status', array($this, 'ajax_get_queue_status'));
        add_action('wp_ajax_aanp_retry_task', array($this, 'ajax_retry_task'));
        add_action('wp_ajax_aanp_clear_queue', array($this, 'ajax_clear_queue'));
    }
    
    /**
     * Schedule queue processing
     */
    public function schedule_queue_processing() {
        // Schedule main queue processing
        if (!wp_next_scheduled('aanp_process_queue')) {
            wp_schedule_event(time(), 'every_5_minutes', 'aanp_process_queue');
        }
        
        // Schedule worker processing if persistent workers are enabled
        if ($this->config['enable_persistent_workers']) {
            if (!wp_next_scheduled('aanp_queue_worker')) {
                wp_schedule_event(time(), 'every_minute', 'aanp_queue_worker');
            }
        }
    }
    
    /**
     * Initialize default queues
     */
    private function init_default_queues() {
        $default_queues = array(
            'general' => array(
                'priority' => $this->priorities['normal'],
                'max_workers' => 2,
                'batch_size' => 5,
                'timeout' => 300
            ),
            'ai_generation' => array(
                'priority' => $this->priorities['high'],
                'max_workers' => 1,
                'batch_size' => 3,
                'timeout' => 600
            ),
            'content_creation' => array(
                'priority' => $this->priorities['high'],
                'max_workers' => 2,
                'batch_size' => 5,
                'timeout' => 450
            ),
            'analytics' => array(
                'priority' => $this->priorities['low'],
                'max_workers' => 1,
                'batch_size' => 20,
                'timeout' => 180
            ),
            'bulk_processing' => array(
                'priority' => $this->priorities['bulk'],
                'max_workers' => 1,
                'batch_size' => 50,
                'timeout' => 900
            )
        );
        
        foreach ($default_queues as $queue_name => $config) {
            $this->register_queue($queue_name, $config);
        }
        
        $this->logger->info('Default queues initialized', array(
            'queues' => array_keys($default_queues)
        ));
    }
    
    /**
     * Register a new queue
     *
     * @param string $queue_name Queue name
     * @param array $config Queue configuration
     * @return bool Success status
     */
    public function register_queue($queue_name, $config = array()) {
        try {
            $default_config = array(
                'priority' => $this->priorities['normal'],
                'max_workers' => 1,
                'batch_size' => 10,
                'timeout' => 300,
                'retry_enabled' => true,
                'max_retries' => $this->retry_configs['max_attempts'],
                'dead_letter_queue' => true
            );
            
            $queue_config = array_merge($default_config, $config);
            
            $this->queues[$queue_name] = $queue_config;
            
            // Initialize queue statistics
            $this->stats[$queue_name] = array(
                'total_tasks' => 0,
                'pending_tasks' => 0,
                'processing_tasks' => 0,
                'completed_tasks' => 0,
                'failed_tasks' => 0,
                'avg_processing_time' => 0,
                'last_activity' => null
            );
            
            $this->logger->debug("Queue '{$queue_name}' registered successfully", array(
                'config' => $queue_config
            ));
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error("Failed to register queue '{$queue_name}'", array(
                'error' => $e->getMessage()
            ));
            
            return false;
        }
    }
    
    /**
     * Add task to queue
     *
     * @param string $queue_name Queue name
     * @param callable $task_function Task function/callable
     * @param array $task_data Task data
     * @param array $options Task options
     * @return string|false Task ID or false on failure
     */
    public function add_task($queue_name, $task_function, $task_data = array(), $options = array()) {
        try {
            if (!isset($this->queues[$queue_name])) {
                throw new Exception("Queue '{$queue_name}' not registered");
            }
            
            // Validate task function
            if (!is_callable($task_function) && !is_string($task_function)) {
                throw new Exception('Task function must be callable or string method name');
            }
            
            $task_id = uniqid('task_', true);
            $queue_config = $this->queues[$queue_name];
            
            // Prepare task options
            $task_options = array_merge(array(
                'priority' => $queue_config['priority'],
                'delay' => 0,
                'max_attempts' => $queue_config['max_retries'],
                'timeout' => $queue_config['timeout'],
                'metadata' => array(),
                'created_at' => current_time('Y-m-d H:i:s')
            ), $options);
            
            // Create task object
            $task = array(
                'id' => $task_id,
                'queue' => $queue_name,
                'function' => $task_function,
                'data' => $task_data,
                'options' => $task_options,
                'status' => 'pending',
                'attempts' => 0,
                'created_at' => current_time('Y-m-d H:i:s'),
                'scheduled_at' => date('Y-m-d H:i:s', time() + $task_options['delay']),
                'processing_started_at' => null,
                'completed_at' => null,
                'error_message' => null,
                'result' => null
            );
            
            // Store task in queue
            $stored = $this->store_task($queue_name, $task);
            
            if (!$stored) {
                throw new Exception('Failed to store task');
            }
            
            // Update statistics
            $this->stats[$queue_name]['total_tasks']++;
            $this->stats[$queue_name]['pending_tasks']++;
            $this->stats[$queue_name]['last_activity'] = current_time('Y-m-d H:i:s');
            
            $this->logger->debug("Task added to queue '{$queue_name}'", array(
                'task_id' => $task_id,
                'priority' => $task_options['priority'],
                'delay' => $task_options['delay']
            ));
            
            return $task_id;
            
        } catch (Exception $e) {
            $this->logger->error("Failed to add task to queue '{$queue_name}'", array(
                'error' => $e->getMessage()
            ));
            
            return false;
        }
    }
    
    /**
     * Add batch tasks to queue
     *
     * @param string $queue_name Queue name
     * @param array $tasks Array of task definitions
     * @param array $global_options Global task options
     * @return array Array of task IDs
     */
    public function add_batch_tasks($queue_name, $tasks, $global_options = array()) {
        $task_ids = array();
        
        try {
            if (!is_array($tasks)) {
                throw new Exception('Tasks must be an array');
            }
            
            // Process tasks in batches to avoid memory issues
            $batch_size = $this->queues[$queue_name]['batch_size'];
            
            for ($i = 0; $i < count($tasks); $i += $batch_size) {
                $batch = array_slice($tasks, $i, $batch_size);
                
                foreach ($batch as $task) {
                    $task_function = $task['function'] ?? null;
                    $task_data = $task['data'] ?? array();
                    $task_options = array_merge($global_options, $task['options'] ?? array());
                    
                    $task_id = $this->add_task($queue_name, $task_function, $task_data, $task_options);
                    
                    if ($task_id) {
                        $task_ids[] = $task_id;
                    }
                }
                
                // Brief pause between batches
                if (count($batch) >= $batch_size) {
                    usleep(100000); // 0.1 seconds
                }
            }
            
            $this->logger->info("Batch tasks added to queue '{$queue_name}'", array(
                'total_tasks' => count($tasks),
                'successfully_added' => count($task_ids),
                'batch_size' => $batch_size
            ));
            
            return $task_ids;
            
        } catch (Exception $e) {
            $this->logger->error("Failed to add batch tasks to queue '{$queue_name}'", array(
                'error' => $e->getMessage()
            ));
            
            return $task_ids; // Return what was successfully added
        }
    }
    
    /**
     * Store task in queue storage
     *
     * @param string $queue_name Queue name
     * @param array $task Task object
     * @return bool Success status
     */
    private function store_task($queue_name, $task) {
        $cache_key = "queue_task_{$queue_name}_{$task['id']}";
        
        // Use cache with appropriate TTL based on task priority
        $ttl = $this->calculate_task_ttl($task);
        
        return $this->cache_manager->set($cache_key, $task, $ttl);
    }
    
    /**
     * Get task from queue storage
     *
     * @param string $queue_name Queue name
     * @param string $task_id Task ID
     * @return array|null Task object or null
     */
    private function get_task($queue_name, $task_id) {
        $cache_key = "queue_task_{$queue_name}_{$task_id}";
        return $this->cache_manager->get($cache_key);
    }
    
    /**
     * Calculate task TTL based on priority and options
     *
     * @param array $task Task object
     * @return int TTL in seconds
     */
    private function calculate_task_ttl($task) {
        // Base TTL based on priority
        $priority_ttl_map = array(
            'critical' => 86400, // 24 hours
            'high' => 43200,     // 12 hours
            'normal' => 86400,   // 24 hours
            'low' => 172800,     // 48 hours
            'bulk' => 604800     // 7 days
        );
        
        $priority = $this->get_priority_name($task['options']['priority']);
        $base_ttl = $priority_ttl_map[$priority] ?? 86400;
        
        // Add time for scheduled delay if any
        if ($task['options']['delay'] > 0) {
            $base_ttl += $task['options']['delay'];
        }
        
        return $base_ttl;
    }
    
    /**
     * Get priority name from priority value
     *
     * @param int $priority Priority value
     * @return string Priority name
     */
    private function get_priority_name($priority) {
        foreach ($this->priorities as $name => $value) {
            if ($value === $priority) {
                return $name;
            }
        }
        return 'normal';
    }
    
    /**
     * Process queue tasks
     *
     * @param string $queue_name Optional specific queue name
     * @return array Processing results
     */
    public function process_queue($queue_name = null) {
        $start_time = microtime(true);
        
        try {
            $queues_to_process = $queue_name ? array($queue_name) : array_keys($this->queues);
            $processing_results = array();
            
            foreach ($queues_to_process as $q_name) {
                if (!isset($this->queues[$q_name])) {
                    continue;
                }
                
                $result = $this->process_queue_tasks($q_name);
                $processing_results[$q_name] = $result;
                
                $this->logger->debug("Processed queue '{$q_name}'", array(
                    'tasks_processed' => $result['processed'],
                    'tasks_failed' => $result['failed']
                ));
            }
            
            $execution_time = (microtime(true) - $start_time) * 1000;
            
            $response = array(
                'success' => true,
                'results' => $processing_results,
                'execution_time_ms' => $execution_time,
                'timestamp' => current_time('Y-m-d H:i:s')
            );
            
            $this->logger->info('Queue processing completed', array(
                'queues_processed' => count($queues_to_process),
                'execution_time_ms' => $execution_time
            ));
            
            return $response;
            
        } catch (Exception $e) {
            $execution_time = (microtime(true) - $start_time) * 1000;
            
            $this->logger->error('Queue processing failed', array(
                'error' => $e->getMessage(),
                'execution_time_ms' => $execution_time
            ));
            
            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'execution_time_ms' => $execution_time,
                'timestamp' => current_time('Y-m-d H:i:s')
            );
        }
    }
    
    /**
     * Process tasks in a specific queue
     *
     * @param string $queue_name Queue name
     * @return array Processing results
     */
    private function process_queue_tasks($queue_name) {
        $queue_config = $this->queues[$queue_name];
        $processed = 0;
        $failed = 0;
        $max_workers = min($queue_config['max_workers'], $this->config['max_workers']);
        
        // Get pending tasks sorted by priority
        $pending_tasks = $this->get_pending_tasks($queue_name);
        
        if (empty($pending_tasks)) {
            return array('processed' => 0, 'failed' => 0, 'tasks_available' => 0);
        }
        
        // Process tasks based on available workers
        $tasks_per_worker = min(count($pending_tasks), $this->config['max_tasks_per_worker']);
        $workers_needed = min($max_workers, ceil(count($pending_tasks) / $tasks_per_worker));
        
        for ($worker_id = 0; $worker_id < $workers_needed; $worker_id++) {
            $worker_tasks = array_slice($pending_tasks, $worker_id * $tasks_per_worker, $tasks_per_worker);
            
            foreach ($worker_tasks as $task) {
                $result = $this->process_single_task($task, $queue_name);
                
                if ($result['success']) {
                    $processed++;
                    $this->stats[$queue_name]['completed_tasks']++;
                } else {
                    $failed++;
                    $this->stats[$queue_name]['failed_tasks']++;
                    
                    // Handle retry if enabled
                    if ($task['options']['max_attempts'] > $task['attempts']) {
                        $this->schedule_task_retry($task, $queue_name, $result['error']);
                    }
                }
                
                // Update queue stats
                $this->stats[$queue_name]['pending_tasks']--;
                $this->stats[$queue_name]['processing_tasks'] = max(0, $this->stats[$queue_name]['processing_tasks'] - 1);
            }
        }
        
        return array(
            'processed' => $processed,
            'failed' => $failed,
            'tasks_available' => count($pending_tasks)
        );
    }
    
    /**
     * Get pending tasks for a queue
     *
     * @param string $queue_name Queue name
     * @return array Pending tasks sorted by priority
     */
    private function get_pending_tasks($queue_name) {
        $tasks = array();
        $prefix = "queue_task_{$queue_name}_";
        
        // This is a simplified implementation
        // In a real-world scenario, you might want to use a more efficient storage system
        // that can query by status and scheduled time
        
        // For now, we'll scan cache keys (this is not optimal for large queues)
        $cache_pattern = $prefix . '*';
        
        // Get all tasks from cache (this would be improved with proper indexing)
        $all_task_keys = $this->get_all_task_keys($queue_name);
        
        foreach ($all_task_keys as $task_key) {
            $task = $this->cache_manager->get(str_replace($prefix, '', $task_key));
            if (!$task) continue;
            
            // Check if task is ready to process
            if ($task['status'] === 'pending' && strtotime($task['scheduled_at']) <= time()) {
                $tasks[] = $task;
            }
        }
        
        // Sort by priority (higher priority first)
        usort($tasks, function($a, $b) {
            return $b['options']['priority'] - $a['options']['priority'];
        });
        
        return $tasks;
    }
    
    /**
     * Get all task keys for a queue
     *
     * @param string $queue_name Queue name
     * @return array Task keys
     */
    private function get_all_task_keys($queue_name) {
        // This would ideally use a more efficient method
        // For now, we'll use a simple pattern-based lookup
        
        $keys = array();
        $prefix = "queue_task_{$queue_name}_";
        
        // In a real implementation, you might maintain an index of task IDs
        // or use a database with proper querying capabilities
        
        // For demonstration, we'll return a placeholder
        // In practice, this would be implemented with proper indexing
        
        return $keys;
    }
    
    /**
     * Process a single task
     *
     * @param array $task Task object
     * @param string $queue_name Queue name
     * @return array Processing result
     */
    private function process_single_task($task, $queue_name) {
        $task_start_time = microtime(true);
        
        try {
            // Mark task as processing
            $task['status'] = 'processing';
            $task['processing_started_at'] = current_time('Y-m-d H:i:s');
            $this->stats[$queue_name]['processing_tasks']++;
            
            // Store updated task
            $this->store_task($queue_name, $task);
            
            // Set up timeout
            $timeout = $task['options']['timeout'];
            $start_time = time();
            
            // Execute task function
            $result = $this->execute_task_function($task);
            
            $execution_time = time() - $start_time;
            
            if ($execution_time > $timeout) {
                throw new Exception("Task execution timeout ({$execution_time}s > {$timeout}s)");
            }
            
            // Mark task as completed
            $task['status'] = 'completed';
            $task['completed_at'] = current_time('Y-m-d H:i:s');
            $task['result'] = $result;
            
            // Update average processing time
            $processing_time = (microtime(true) - $task_start_time) * 1000;
            $this->update_avg_processing_time($queue_name, $processing_time);
            
            // Store completed task (briefly, for reference)
            $this->store_completed_task($queue_name, $task);
            
            return array(
                'success' => true,
                'result' => $result,
                'processing_time_ms' => $processing_time
            );
            
        } catch (Exception $e) {
            // Mark task as failed
            $task['status'] = 'failed';
            $task['completed_at'] = current_time('Y-m-d H:i:s');
            $task['error_message'] = $e->getMessage();
            
            // Store failed task in dead letter queue if enabled
            if ($this->queues[$queue_name]['dead_letter_queue']) {
                $this->store_dead_letter_task($queue_name, $task);
            }
            
            $this->logger->error('Task processing failed', array(
                'task_id' => $task['id'],
                'queue' => $queue_name,
                'error' => $e->getMessage(),
                'attempts' => $task['attempts']
            ));
            
            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'attempts' => $task['attempts']
            );
        }
    }
    
    /**
     * Execute task function with error handling
     *
     * @param array $task Task object
     * @return mixed Task result
     */
    private function execute_task_function($task) {
        $function = $task['function'];
        $data = $task['data'];
        
        if (is_callable($function)) {
            return call_user_func($function, $data, $task);
        } elseif (is_string($function)) {
            // Handle static method calls or function names
            if (strpos($function, '::') !== false) {
                return call_user_func($function, $data, $task);
            } else {
                if (function_exists($function)) {
                    return call_user_func($function, $data, $task);
                } else {
                    throw new Exception("Function '{$function}' not found");
                }
            }
        } else {
            throw new Exception('Invalid task function');
        }
    }
    
    /**
     * Schedule task retry
     *
     * @param array $task Failed task
     * @param string $queue_name Queue name
     * @param string $error Error message
     */
    private function schedule_task_retry($task, $queue_name, $error) {
        $task['attempts']++;
        $task['status'] = 'pending';
        
        // Calculate retry delay with exponential backoff
        $delay = $this->calculate_retry_delay($task['attempts']);
        $task['scheduled_at'] = date('Y-m-d H:i:s', time() + $delay);
        
        // Add retry metadata
        if (!isset $task['options']['retry_history']) {
            $task['options']['retry_history'] = array();
        }
        
        $task['options']['retry_history'][] = array(
            'attempt' => $task['attempts'],
            'error' => $error,
            'scheduled_at' => $task['scheduled_at']
        );
        
        // Store task for retry
        $this->store_task($queue_name, $task);
        
        $this->logger->debug('Task scheduled for retry', array(
            'task_id' => $task['id'],
            'attempt' => $task['attempts'],
            'delay_seconds' => $delay
        ));
    }
    
    /**
     * Calculate retry delay with exponential backoff
     *
     * @param int $attempt_number Attempt number
     * @return int Delay in seconds
     */
    private function calculate_retry_delay($attempt_number) {
        $delay = $this->retry_configs['initial_delay'] * pow($this->retry_configs['backoff_multiplier'], $attempt_number - 1);
        return min($delay, $this->retry_configs['max_delay']);
    }
    
    /**
     * Update average processing time for a queue
     *
     * @param string $queue_name Queue name
     * @param float $processing_time Processing time in milliseconds
     */
    private function update_avg_processing_time($queue_name, $processing_time) {
        $current_avg = $this->stats[$queue_name]['avg_processing_time'];
        $completed_tasks = $this->stats[$queue_name]['completed_tasks'];
        
        // Calculate new average
        if ($completed_tasks > 1) {
            $this->stats[$queue_name]['avg_processing_time'] = 
                (($current_avg * ($completed_tasks - 1)) + $processing_time) / $completed_tasks;
        } else {
            $this->stats[$queue_name]['avg_processing_time'] = $processing_time;
        }
    }
    
    /**
     * Store completed task briefly for reference
     *
     * @param string $queue_name Queue name
     * @param array $task Completed task
     */
    private function store_completed_task($queue_name, $task) {
        $cache_key = "queue_completed_{$queue_name}_{$task['id']}";
        
        // Store for 1 hour for reference
        $this->cache_manager->set($cache_key, $task, 3600);
        
        // Remove from main queue
        $main_cache_key = "queue_task_{$queue_name}_{$task['id']}";
        $this->cache_manager->delete($main_cache_key);
    }
    
    /**
     * Store task in dead letter queue
     *
     * @param string $queue_name Queue name
     * @param array $task Failed task
     */
    private function store_dead_letter_task($queue_name, $task) {
        $dlq_name = "dead_letter_{$queue_name}";
        
        if (!isset($this->queues[$dlq_name])) {
            $this->register_queue($dlq_name, array(
                'priority' => $this->priorities['low'],
                'max_workers' => 1,
                'batch_size' => 1,
                'timeout' => 60
            ));
        }
        
        $task['queue'] = $dlq_name;
        $task['status'] = 'dead_letter';
        $task['dead_letter_reason'] = 'max_attempts_exceeded';
        
        $this->store_task($dlq_name, $task);
    }
    
    /**
     * Process worker tasks (for persistent workers)
     */
    public function process_worker_tasks() {
        if (!$this->config['enable_persistent_workers']) {
            return;
        }
        
        foreach ($this->queues as $queue_name => $config) {
            if ($config['max_workers'] > 0) {
                $this->start_worker_processing($queue_name);
            }
        }
    }
    
    /**
     * Start worker processing for a queue
     *
     * @param string $queue_name Queue name
     */
    private function start_worker_processing($queue_name) {
        $queue_config = $this->queues[$queue_name];
        $worker_id = getmypid(); // Use process ID as worker ID
        
        // Check if worker already processing
        if (isset($this->processing_locks[$queue_name])) {
            return;
        }
        
        // Set processing lock
        $this->processing_locks[$queue_name] = array(
            'worker_id' => $worker_id,
            'started_at' => current_time('Y-m-d H:i:s')
        );
        
        try {
            // Get a small batch of tasks
            $tasks = $this->get_pending_tasks($queue_name);
            $tasks_to_process = array_slice($tasks, 0, 5); // Process max 5 tasks per worker cycle
            
            foreach ($tasks_to_process as $task) {
                $result = $this->process_single_task($task, $queue_name);
                
                // Log result
                if ($result['success']) {
                    $this->logger->debug("Worker task completed", array(
                        'task_id' => $task['id'],
                        'queue' => $queue_name,
                        'worker_id' => $worker_id
                    ));
                } else {
                    $this->logger->warning("Worker task failed", array(
                        'task_id' => $task['id'],
                        'queue' => $queue_name,
                        'worker_id' => $worker_id,
                        'error' => $result['error']
                    ));
                }
            }
            
        } finally {
            // Remove processing lock
            unset($this->processing_locks[$queue_name]);
        }
    }
    
    /**
     * Get queue status
     *
     * @param string $queue_name Optional specific queue name
     * @return array Queue status information
     */
    public function get_queue_status($queue_name = null) {
        if ($queue_name) {
            return isset($this->stats[$queue_name]) ? $this->stats[$queue_name] : array();
        }
        
        return array(
            'queues' => $this->stats,
            'total_queues' => count($this->queues),
            'total_tasks' => array_sum(array_column($this->stats, 'total_tasks')),
            'pending_tasks' => array_sum(array_column($this->stats, 'pending_tasks')),
            'processing_tasks' => array_sum(array_column($this->stats, 'processing_tasks')),
            'completed_tasks' => array_sum(array_column($this->stats, 'completed_tasks')),
            'failed_tasks' => array_sum(array_column($this->stats, 'failed_tasks'))
        );
    }
    
    /**
     * Get task status
     *
     * @param string $task_id Task ID
     * @return array|null Task status or null if not found
     */
    public function get_task_status($task_id) {
        // Search through all queues for the task
        foreach ($this->queues as $queue_name => $config) {
            $task = $this->get_task($queue_name, $task_id);
            if ($task) {
                return array_merge($task, array('current_queue' => $queue_name));
            }
            
            // Check completed tasks
            $completed_task = $this->cache_manager->get("queue_completed_{$queue_name}_{$task_id}");
            if ($completed_task) {
                return array_merge($completed_task, array(
                    'current_queue' => $queue_name,
                    'status' => 'completed'
                ));
            }
        }
        
        return null;
    }
    
    /**
     * Retry a failed task
     *
     * @param string $task_id Task ID
     * @param string $queue_name Queue name
     * @return bool Success status
     */
    public function retry_task($task_id, $queue_name = null) {
        try {
            $task = null;
            $actual_queue = null;
            
            // Find the task
            if ($queue_name) {
                $task = $this->get_task($queue_name, $task_id);
                $actual_queue = $queue_name;
            } else {
                // Search through all queues
                foreach ($this->queues as $q_name => $config) {
                    $found_task = $this->get_task($q_name, $task_id);
                    if ($found_task) {
                        $task = $found_task;
                        $actual_queue = $q_name;
                        break;
                    }
                }
            }
            
            if (!$task) {
                throw new Exception("Task '{$task_id}' not found");
            }
            
            if ($task['status'] !== 'failed') {
                throw new Exception("Task '{$task_id}' is not in failed status");
            }
            
            // Reset task for retry
            $task['status'] = 'pending';
            $task['attempts'] = 0;
            $task['scheduled_at'] = current_time('Y-m-d H:i:s');
            $task['completed_at'] = null;
            $task['error_message'] = null;
            unset($task['result']);
            
            // Clear retry history
            $task['options']['retry_history'] = array();
            
            // Store task back in queue
            $this->store_task($actual_queue, $task);
            
            // Update statistics
            $this->stats[$actual_queue]['failed_tasks']--;
            $this->stats[$actual_queue]['pending_tasks']++;
            
            $this->logger->info("Task '{$task_id}' queued for retry", array(
                'queue' => $actual_queue
            ));
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error("Failed to retry task '{$task_id}'", array(
                'error' => $e->getMessage()
            ));
            
            return false;
        }
    }
    
    /**
     * Clear all tasks from a queue
     *
     * @param string $queue_name Queue name
     * @param string $status Optional status filter
     * @return int Number of tasks cleared
     */
    public function clear_queue($queue_name, $status = null) {
        try {
            if (!isset($this->queues[$queue_name])) {
                throw new Exception("Queue '{$queue_name}' not found");
            }
            
            $cleared_count = 0;
            $all_task_keys = $this->get_all_task_keys($queue_name);
            
            foreach ($all_task_keys as $task_key) {
                $task = $this->get_task($queue_name, str_replace("queue_task_{$queue_name}_", '', $task_key));
                
                if ($task && ($status === null || $task['status'] === $status)) {
                    $cache_key = "queue_task_{$queue_name}_{$task['id']}";
                    $this->cache_manager->delete($cache_key);
                    $cleared_count++;
                    
                    // Update statistics
                    switch ($task['status']) {
                        case 'pending':
                            $this->stats[$queue_name]['pending_tasks']--;
                            break;
                        case 'processing':
                            $this->stats[$queue_name]['processing_tasks']--;
                            break;
                        case 'completed':
                            $this->stats[$queue_name]['completed_tasks']--;
                            break;
                        case 'failed':
                            $this->stats[$queue_name]['failed_tasks']--;
                            break;
                    }
                    
                    $this->stats[$queue_name]['total_tasks']--;
                }
            }
            
            $this->logger->info("Cleared {$cleared_count} tasks from queue '{$queue_name}'", array(
                'status_filter' => $status
            ));
            
            return $cleared_count;
            
        } catch (Exception $e) {
            $this->logger->error("Failed to clear queue '{$queue_name}'", array(
                'error' => $e->getMessage()
            ));
            
            return 0;
        }
    }
    
    /**
     * Handle AJAX request for queue status
     */
    public function ajax_get_queue_status() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'aanp_queue_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $queue_name = sanitize_text_field($_POST['queue_name'] ?? '');
        $status = $this->get_queue_status($queue_name);
        
        wp_send_json_success($status);
    }
    
    /**
     * Handle AJAX request for task retry
     */
    public function ajax_retry_task() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'aanp_queue_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $task_id = sanitize_text_field($_POST['task_id']);
        $queue_name = sanitize_text_field($_POST['queue_name'] ?? '');
        
        $success = $this->retry_task($task_id, $queue_name);
        
        if ($success) {
            wp_send_json_success('Task queued for retry');
        } else {
            wp_send_json_error('Failed to retry task');
        }
    }
    
    /**
     * Handle AJAX request for queue clearing
     */
    public function ajax_clear_queue() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'aanp_queue_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $queue_name = sanitize_text_field($_POST['queue_name']);
        $status = sanitize_text_field($_POST['status'] ?? '');
        
        $cleared_count = $this->clear_queue($queue_name, $status ?: null);
        
        wp_send_json_success(array(
            'message' => "Cleared {$cleared_count} tasks from queue '{$queue_name}'",
            'cleared_count' => $cleared_count
        ));
    }
    
    /**
     * Get service metrics
     *
     * @return array Service metrics
     */
    public function get_metrics() {
        return array(
            'service' => 'QueueManager',
            'queues' => array_keys($this->queues),
            'stats' => $this->stats,
            'config' => $this->config,
            'processing_locks' => count($this->processing_locks),
            'memory_usage' => memory_get_usage(true),
            'timestamp' => current_time('Y-m-d H:i:s')
        );
    }
    
    /**
     * Health check method
     *
     * @return bool Health status
     */
    public function health_check() {
        try {
            // Test cache functionality
            $test_key = 'queue_health_check_' . time();
            $test_task = array('id' => $test_key, 'status' => 'test');
            
            $this->cache_manager->set($test_key, $test_task, 60);
            $retrieved = $this->cache_manager->get($test_key);
            
            if ($retrieved !== $test_task) {
                return false;
            }
            
            // Test queue registration
            if (empty($this->queues)) {
                return false;
            }
            
            // Clean up test data
            $this->cache_manager->delete($test_key);
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('QueueManager health check failed', array(
                'error' => $e->getMessage()
            ));
            return false;
        }
    }
}