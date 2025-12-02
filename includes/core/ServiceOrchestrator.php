<?php
/**
 * Service Orchestrator for Microservices Coordination
 *
 * Coordinates interactions between microservices, manages complex workflows,
 * handles service failures and retries, and provides unified operations.
 * Implements the Facade pattern for simplified service coordination.
 *
 * @package AI_Auto_News_Poster\Core
 * @since 1.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Service Orchestrator Class
 *
 * Central coordinator for microservices architecture
 */
class CP_ServiceOrchestrator {
    
    /**
     * Service Registry instance
     * @var AANP_ServiceRegistry
     */
    private $service_registry;
    
    /**
     * Event Manager for service communication
     * @var AANP_Event_Manager
     */
    private $event_manager;
    
    /**
     * Logger instance
     * @var AANP_Logger
     */
    private $logger;
    
    /**
     * Active workflows tracking
     * @var array
     */
    private $active_workflows = array();
    
    /**
     * Retry configurations
     * @var array
     */
    private $retry_configs = array(
        'default_max_retries' => 3,
        'default_retry_delay' => 1000, // milliseconds
        'exponential_backoff' => true
    );
    
    /**
     * Workflow execution statistics
     * @var array
     */
    private $workflow_stats = array();
    
    /**
     * Constructor
     */
    public function __construct(AANP_ServiceRegistry $service_registry = null) {
        $this->logger = CP_Logger::getInstance();
        $this->service_registry = $service_registry ?: new AANP_ServiceRegistry();
        
        // Initialize event manager if available
        if (class_exists('AANP_Event_Manager')) {
            $this->event_manager = new AANP_Event_Manager();
        }
        
        $this->init_orchestrator_hooks();
    }
    
    /**
     * Initialize WordPress hooks for orchestrator
     */
    private function init_orchestrator_hooks() {
        add_action('init', array($this, 'init_workflows'));
        add_action('aanp_service_error', array($this, 'handle_service_error'));
    }
    
    /**
     * Initialize predefined workflows
     */
    public function init_workflows() {
        try {
            // Define standard workflows
            $this->register_workflow('generate_post', array(
                'name' => 'Generate Post Workflow',
                'description' => 'Complete workflow for generating blog posts from news sources',
                'steps' => array(
                    array('service' => 'news-fetch', 'method' => 'fetch_news', 'timeout' => 30),
                    array('service' => 'ai-generation', 'method' => 'generate_content', 'timeout' => 60),
                    array('service' => 'seo-analyzer', 'method' => 'analyze_content', 'timeout' => 15),
                    array('service' => 'eeat-optimizer', 'method' => 'optimize_for_eeat', 'timeout' => 20),
                    array('service' => 'content-creation', 'method' => 'create_post', 'timeout' => 25)
                ),
                'rollback_steps' => array(
                    array('service' => 'content-creation', 'method' => 'cleanup_failed_post', 'timeout' => 10)
                )
            ));
            
            $this->register_workflow('batch_process', array(
                'name' => 'Batch Processing Workflow',
                'description' => 'Process multiple news items in batches',
                'parallel' => true,
                'max_parallel' => 3,
                'steps' => array(
                    array('service' => 'news-fetch', 'method' => 'fetch_multiple_news', 'timeout' => 45),
                    array('service' => 'ai-generation', 'method' => 'process_batch', 'timeout' => 180),
                    array('service' => 'content-creation', 'method' => 'create_batch_posts', 'timeout' => 120)
                )
            ));
            
            $this->register_workflow('analytics_update', array(
                'name' => 'Analytics Update Workflow',
                'description' => 'Update analytics and performance metrics',
                'steps' => array(
                    array('service' => 'analytics', 'method' => 'collect_metrics', 'timeout' => 30),
                    array('service' => 'analytics', 'method' => 'update_dashboard', 'timeout' => 15)
                ),
                'background' => true
            ));
            
            $this->logger->info('Service Orchestrator workflows initialized', array(
                'workflow_count' => count($this->active_workflows)
            ));
            
        } catch (Exception $e) {
            $this->logger->error('Failed to initialize workflows', array(
                'error' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Register a new workflow
     *
     * @param string $workflow_name Workflow identifier
     * @param array $workflow_config Workflow configuration
     * @return bool Success status
     */
    public function register_workflow($workflow_name, $workflow_config) {
        try {
            if (empty($workflow_name) || !is_string($workflow_name)) {
                throw new InvalidArgumentException('Workflow name must be a non-empty string');
            }
            
            if (!is_array($workflow_config)) {
                throw new InvalidArgumentException('Workflow config must be an array');
            }
            
            // Validate required configuration
            if (!isset($workflow_config['steps']) || !is_array($workflow_config['steps'])) {
                throw new InvalidArgumentException('Workflow must have steps defined');
            }
            
            // Validate each step
            foreach ($workflow_config['steps'] as $index => $step) {
                if (!isset($step['service']) || !isset($step['method'])) {
                    throw new InvalidArgumentException(
                        "Step {$index} must have service and method defined"
                    );
                }
            }
            
            // Set default configuration
            $workflow_config = array_merge(array(
                'name' => $workflow_name,
                'description' => '',
                'parallel' => false,
                'max_parallel' => 1,
                'timeout' => 300, // 5 minutes default
                'retry_count' => $this->retry_configs['default_max_retries'],
                'retry_delay' => $this->retry_configs['default_retry_delay'],
                'background' => false,
                'created_at' => current_time('Y-m-d H:i:s')
            ), $workflow_config);
            
            // Store workflow
            $this->active_workflows[$workflow_name] = $workflow_config;
            
            $this->logger->debug('Workflow registered successfully', array(
                'workflow' => $workflow_name,
                'steps_count' => count($workflow_config['steps']),
                'parallel' => $workflow_config['parallel']
            ));
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to register workflow', array(
                'workflow' => $workflow_name,
                'error' => $e->getMessage()
            ));
            return false;
        }
    }
    
    /**
     * Execute a workflow
     *
     * @param string $workflow_name Workflow name
     * @param array $parameters Workflow parameters
     * @param array $options Execution options
     * @return array Workflow execution result
     */
    public function execute_workflow($workflow_name, $parameters = array(), $options = array()) {
        $execution_id = uniqid('wf_', true);
        $start_time = microtime(true);
        
        try {
            // Validate workflow exists
            if (!isset($this->active_workflows[$workflow_name])) {
                throw new InvalidArgumentException("Workflow not found: {$workflow_name}");
            }
            
            $workflow = $this->active_workflows[$workflow_name];
            $execution_context = array(
                'id' => $execution_id,
                'name' => $workflow_name,
                'start_time' => $start_time,
                'parameters' => $parameters,
                'options' => $options,
                'steps_executed' => array(),
                'current_step' => 0
            );
            
            $this->logger->info('Workflow execution started', array(
                'execution_id' => $execution_id,
                'workflow' => $workflow_name,
                'steps_count' => count($workflow['steps'])
            ));
            
            // Start event
            $this->trigger_event('workflow_started', array(
                'execution_id' => $execution_id,
                'workflow' => $workflow_name,
                'parameters' => $parameters
            ));
            
            // Execute workflow steps
            $result = $this->execute_workflow_steps($workflow, $execution_context);
            
            $end_time = microtime(true);
            $execution_time = ($end_time - $start_time) * 1000; // ms
            
            // Update statistics
            $this->update_workflow_stats($workflow_name, true, $execution_time);
            
            $response = array(
                'success' => true,
                'execution_id' => $execution_id,
                'workflow' => $workflow_name,
                'result' => $result,
                'execution_time_ms' => $execution_time,
                'steps_executed' => count($execution_context['steps_executed']),
                'timestamp' => current_time('Y-m-d H:i:s')
            );
            
            $this->logger->info('Workflow execution completed successfully', array(
                'execution_id' => $execution_id,
                'workflow' => $workflow_name,
                'execution_time_ms' => $execution_time,
                'steps_executed' => count($execution_context['steps_executed'])
            ));
            
            // Trigger completion event
            $this->trigger_event('workflow_completed', $response);
            
            return $response;
            
        } catch (Exception $e) {
            $end_time = microtime(true);
            $execution_time = ($end_time - $start_time) * 1000;
            
            // Update failure statistics
            $this->update_workflow_stats($workflow_name, false, $execution_time, $e->getMessage());
            
            $error_response = array(
                'success' => false,
                'execution_id' => $execution_id,
                'workflow' => $workflow_name,
                'error' => $e->getMessage(),
                'execution_time_ms' => $execution_time,
                'steps_executed' => count($execution_context['steps_executed'] ?? array()),
                'timestamp' => current_time('Y-m-d H:i:s')
            );
            
            $this->logger->error('Workflow execution failed', array(
                'execution_id' => $execution_id,
                'workflow' => $workflow_name,
                'error' => $e->getMessage(),
                'execution_time_ms' => $execution_time
            ));
            
            // Trigger failure event
            $this->trigger_event('workflow_failed', $error_response);
            
            // Attempt rollback if defined
            if (isset($this->active_workflows[$workflow_name]['rollback_steps'])) {
                $this->execute_rollback($workflow_name, $execution_id, $e, $execution_context);
            }
            
            return $error_response;
        }
    }
    
    /**
     * Execute workflow steps
     *
     * @param array $workflow Workflow configuration
     * @param array $context Execution context
     * @return array Step results
     */
    private function execute_workflow_steps($workflow, $context) {
        $results = array();
        
        if ($workflow['parallel']) {
            // Execute steps in parallel
            $results = $this->execute_parallel_steps($workflow['steps'], $context, $workflow);
        } else {
            // Execute steps sequentially
            foreach ($workflow['steps'] as $index => $step) {
                $context['current_step'] = $index;
                
                $step_result = $this->execute_step($step, $context, $workflow);
                $results[] = $step_result;
                
                $context['steps_executed'][] = array(
                    'step' => $index,
                    'service' => $step['service'],
                    'method' => $step['method'],
                    'result' => $step_result,
                    'timestamp' => current_time('Y-m-d H:i:s')
                );
                
                // Check if step failed and workflow should stop
                if (!$step_result['success']) {
                    if (!isset($step['continue_on_error']) || !$step['continue_on_error']) {
                        throw new Exception(
                            "Workflow step failed: {$step['service']}::{$step['method']} - {$step_result['error']}"
                        );
                    }
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Execute a single workflow step
     *
     * @param array $step Step configuration
     * @param array $context Execution context
     * @param array $workflow Workflow configuration
     * @return array Step execution result
     */
    private function execute_step($step, $context, $workflow) {
        $max_retries = isset($step['retry_count']) ? $step['retry_count'] : $workflow['retry_count'];
        $retry_delay = isset($step['retry_delay']) ? $step['retry_delay'] : $workflow['retry_delay'];
        $timeout = isset($step['timeout']) ? $step['timeout'] : $workflow['timeout'];
        
        $attempt = 0;
        $last_error = null;
        
        while ($attempt <= $max_retries) {
            try {
                $start_time = microtime(true);
                
                // Get service instance
                $service = $this->service_registry->get_service($step['service']);
                if (!$service) {
                    throw new Exception("Service not available: {$step['service']}");
                }
                
                // Check if method exists
                if (!method_exists($service, $step['method'])) {
                    throw new Exception("Method not found: {$step['service']}::{$step['method']}");
                }
                
                // Execute method with timeout
                $result = $this->execute_with_timeout(
                    array($service, $step['method']),
                    array($context['parameters'], $context),
                    $timeout
                );
                
                $execution_time = (microtime(true) - $start_time) * 1000;
                
                $step_result = array(
                    'success' => true,
                    'result' => $result,
                    'execution_time_ms' => $execution_time,
                    'attempt' => $attempt + 1,
                    'service' => $step['service'],
                    'method' => $step['method'],
                    'timestamp' => current_time('Y-m-d H:i:s')
                );
                
                $this->logger->debug('Workflow step executed successfully', array(
                    'execution_id' => $context['id'],
                    'service' => $step['service'],
                    'method' => $step['method'],
                    'attempt' => $attempt + 1,
                    'execution_time_ms' => $execution_time
                ));
                
                return $step_result;
                
            } catch (Exception $e) {
                $last_error = $e;
                $attempt++;
                
                if ($attempt <= $max_retries) {
                    // Calculate delay with exponential backoff
                    $delay = $this->calculate_retry_delay($attempt, $retry_delay, $workflow);
                    
                    $this->logger->warning('Workflow step failed, retrying', array(
                        'execution_id' => $context['id'],
                        'service' => $step['service'],
                        'method' => $step['method'],
                        'attempt' => $attempt,
                        'max_retries' => $max_retries,
                        'error' => $e->getMessage(),
                        'retry_delay_ms' => $delay
                    ));
                    
                    // Wait before retry
                    usleep($delay * 1000);
                    
                    // Trigger retry event
                    $this->trigger_event('workflow_step_retry', array(
                        'execution_id' => $context['id'],
                        'step' => $step,
                        'attempt' => $attempt,
                        'error' => $e->getMessage(),
                        'delay_ms' => $delay
                    ));
                }
            }
        }
        
        // All retries failed
        return array(
            'success' => false,
            'error' => $last_error ? $last_error->getMessage() : 'Unknown error',
            'attempts' => $attempt,
            'service' => $step['service'],
            'method' => $step['method'],
            'timestamp' => current_time('Y-m-d H:i:s')
        );
    }
    
    /**
     * Execute steps in parallel
     *
     * @param array $steps Steps to execute
     * @param array $context Execution context
     * @param array $workflow Workflow configuration
     * @return array Step results
     */
    private function execute_parallel_steps($steps, $context, $workflow) {
        $results = array();
        $max_parallel = isset($workflow['max_parallel']) ? $workflow['max_parallel'] : 3;
        
        // Process steps in batches
        for ($i = 0; $i < count($steps); $i += $max_parallel) {
            $batch = array_slice($steps, $i, $max_parallel);
            $batch_results = array();
            
            // Execute batch in parallel
            foreach ($batch as $index => $step) {
                $context['current_step'] = $i + $index;
                
                // Use WordPress async processing for parallel execution
                $batch_results[] = $this->execute_step_async($step, $context, $workflow);
            }
            
            // Collect batch results
            foreach ($batch_results as $result) {
                if (is_array($result) && isset($result['success'])) {
                    $results[] = $result;
                    $context['steps_executed'][] = $result;
                } else {
                    // For now, mark as failed if async execution failed
                    $results[] = array(
                        'success' => false,
                        'error' => 'Async execution failed',
                        'service' => $result['service'] ?? 'unknown',
                        'method' => $result['method'] ?? 'unknown'
                    );
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Execute step asynchronously (placeholder for future implementation)
     *
     * @param array $step Step configuration
     * @param array $context Execution context
     * @param array $workflow Workflow configuration
     * @return array Step result
     */
    private function execute_step_async($step, $context, $workflow) {
        // For now, execute synchronously
        // In a full implementation, this would use WordPress cron or queue system
        return $this->execute_step($step, $context, $workflow);
    }
    
    /**
     * Execute with timeout
     *
     * @param callable $callback Function to execute
     * @param array $args Function arguments
     * @param int $timeout Timeout in seconds
     * @return mixed Function result
     */
    private function execute_with_timeout($callback, $args, $timeout) {
        $start_time = time();
        
        // For simple execution (timeout handling could be improved with signals)
        $result = call_user_func_array($callback, $args);
        
        // Check if execution exceeded timeout
        if (time() - $start_time > $timeout) {
            throw new Exception('Execution timeout exceeded');
        }
        
        return $result;
    }
    
    /**
     * Calculate retry delay with exponential backoff
     *
     * @param int $attempt Current attempt number
     * @param int $base_delay Base delay in milliseconds
     * @param array $workflow Workflow configuration
     * @return int Delay in milliseconds
     */
    private function calculate_retry_delay($attempt, $base_delay, $workflow) {
        if (isset($workflow['exponential_backoff']) && $workflow['exponential_backoff']) {
            return $base_delay * pow(2, $attempt - 1);
        }
        
        return $base_delay;
    }
    
    /**
     * Execute rollback steps
     *
     * @param string $workflow_name Workflow name
     * @param string $execution_id Execution ID
     * @param Exception $exception Original exception
     * @param array $context Execution context
     */
    private function execute_rollback($workflow_name, $execution_id, $exception, $context) {
        try {
            $workflow = $this->active_workflows[$workflow_name];
            
            if (!isset($workflow['rollback_steps'])) {
                return;
            }
            
            $this->logger->info('Executing rollback steps', array(
                'execution_id' => $execution_id,
                'workflow' => $workflow_name,
                'rollback_steps_count' => count($workflow['rollback_steps'])
            ));
            
            foreach ($workflow['rollback_steps'] as $step) {
                try {
                    $service = $this->service_registry->get_service($step['service']);
                    if ($service && method_exists($service, $step['method'])) {
                        call_user_func(array($service, $step['method']), $context);
                        
                        $this->logger->debug('Rollback step executed successfully', array(
                            'execution_id' => $execution_id,
                            'service' => $step['service'],
                            'method' => $step['method']
                        ));
                    }
                } catch (Exception $rollback_exception) {
                    $this->logger->error('Rollback step failed', array(
                        'execution_id' => $execution_id,
                        'service' => $step['service'],
                        'method' => $step['method'],
                        'error' => $rollback_exception->getMessage()
                    ));
                }
            }
            
        } catch (Exception $e) {
            $this->logger->error('Rollback execution failed', array(
                'execution_id' => $execution_id,
                'workflow' => $workflow_name,
                'error' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Update workflow execution statistics
     *
     * @param string $workflow_name Workflow name
     * @param bool $success Execution success
     * @param float $execution_time Execution time in milliseconds
     * @param string $error Error message if failed
     */
    private function update_workflow_stats($workflow_name, $success, $execution_time, $error = '') {
        if (!isset($this->workflow_stats[$workflow_name])) {
            $this->workflow_stats[$workflow_name] = array(
                'total_executions' => 0,
                'successful_executions' => 0,
                'failed_executions' => 0,
                'total_execution_time' => 0,
                'average_execution_time' => 0,
                'last_execution' => null
            );
        }
        
        $stats = &$this->workflow_stats[$workflow_name];
        
        $stats['total_executions']++;
        $stats['total_execution_time'] += $execution_time;
        $stats['average_execution_time'] = $stats['total_execution_time'] / $stats['total_executions'];
        $stats['last_execution'] = array(
            'success' => $success,
            'execution_time' => $execution_time,
            'error' => $error,
            'timestamp' => current_time('Y-m-d H:i:s')
        );
        
        if ($success) {
            $stats['successful_executions']++;
        } else {
            $stats['failed_executions']++;
        }
    }
    
    /**
     * Trigger event through event manager
     *
     * @param string $event Event name
     * @param array $data Event data
     */
    private function trigger_event($event, $data = array()) {
        if ($this->event_manager) {
            try {
                $this->event_manager->trigger($event, $data);
            } catch (Exception $e) {
                $this->logger->warning('Failed to trigger event', array(
                    'event' => $event,
                    'error' => $e->getMessage()
                ));
            }
        }
    }
    
    /**
     * Handle service error event
     *
     * @param array $error_data Error data
     */
    public function handle_service_error($error_data) {
        try {
            $this->logger->warning('Service error detected by orchestrator', $error_data);
            
            // Implement error recovery strategies here
            // For example, switch to backup service, notify administrators, etc.
            
        } catch (Exception $e) {
            $this->logger->error('Failed to handle service error', array(
                'error' => $e->getMessage(),
                'original_error_data' => $error_data
            ));
        }
    }
    
    /**
     * Get workflow statistics
     *
     * @param string $workflow_name Optional workflow name
     * @return array Workflow statistics
     */
    public function get_workflow_stats($workflow_name = null) {
        if ($workflow_name) {
            return isset($this->workflow_stats[$workflow_name]) 
                ? $this->workflow_stats[$workflow_name] 
                : null;
        }
        
        return $this->workflow_stats;
    }
    
    /**
     * Get active workflows
     *
     * @return array Active workflows
     */
    public function get_active_workflows() {
        return $this->active_workflows;
    }
    
    /**
     * Cancel active workflow execution
     *
     * @param string $execution_id Execution ID
     * @return bool Success status
     */
    public function cancel_workflow($execution_id) {
        try {
            // Implementation would depend on how workflow executions are tracked
            // For now, this is a placeholder
            
            $this->logger->info('Workflow cancellation requested', array(
                'execution_id' => $execution_id
            ));
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to cancel workflow', array(
                'execution_id' => $execution_id,
                'error' => $e->getMessage()
            ));
            return false;
        }
    }
    
    /**
     * Health check for all services
     *
     * @return array Health check results
     */
    public function health_check() {
        $results = array();
        
        try {
            $registered_services = $this->service_registry->get_registered_services();
            
            foreach ($registered_services as $service_name => $service_config) {
                try {
                    $service = $this->service_registry->get_service($service_name);
                    $status = $service ? 'healthy' : 'unhealthy';
                    
                    // Try to call a health check method if it exists
                    if ($service && method_exists($service, 'health_check')) {
                        $health_result = $service->health_check();
                        $status = $health_result ? 'healthy' : 'unhealthy';
                    }
                    
                    $results[$service_name] = array(
                        'status' => $status,
                        'timestamp' => current_time('Y-m-d H:i:s')
                    );
                    
                } catch (Exception $e) {
                    $results[$service_name] = array(
                        'status' => 'error',
                        'error' => $e->getMessage(),
                        'timestamp' => current_time('Y-m-d H:i:s')
                    );
                }
            }
            
        } catch (Exception $e) {
            $this->logger->error('Health check failed', array(
                'error' => $e->getMessage()
            ));
        }
        
        return $results;
    }
}