<?php
/**
 * Command Interface for Implementing Command Pattern
 *
 * @package AI_Auto_News_Poster\Patterns
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Command Interface
 * 
 * Defines the contract for all executable commands in the system
 */
interface AANP_Command_Interface {
    
    /**
     * Execute the command
     * 
     * @param array $parameters Command parameters
     * @return mixed Command result
     * @throws AANP_Command_Exception If command execution fails
     */
    public function execute($parameters = array());
    
    /**
     * Undo the command (if supported)
     * 
     * @return bool True if successfully undone
     * @throws AANP_Command_Exception If undo operation fails
     */
    public function undo();
    
    /**
     * Check if command can be undone
     * 
     * @return bool True if command supports undo
     */
    public function can_undo();
    
    /**
     * Get command metadata
     * 
     * @return array Command information
     */
    public function get_command_info();
    
    /**
     * Validate command parameters
     * 
     * @param array $parameters Parameters to validate
     * @return bool True if valid
     */
    public function validate_parameters($parameters);
}

/**
 * Command Base Class
 * 
 * Provides common functionality for all commands
 */
abstract class AANP_Command_Base implements AANP_Command_Interface {
    
    /**
     * Logger instance
     * @var AANP_Logger
     */
    protected $logger;
    
    /**
     * Command name
     * @var string
     */
    protected $command_name;
    
    /**
     * Constructor
     * 
     * @param string $command_name Command identifier
     */
    public function __construct($command_name = '') {
        $this->command_name = $command_name;
        $this->logger = AANP_Logger::getInstance();
    }
    
    /**
     * Execute the command
     * 
     * @param array $parameters Command parameters
     * @return mixed Command result
     */
    public function execute($parameters = array()) {
        try {
            // Log command execution
            $this->logger->debug("Executing command: {$this->command_name}", array(
                'parameters' => $this->sanitize_parameters($parameters)
            ));
            
            // Validate parameters
            if (!$this->validate_parameters($parameters)) {
                throw new AANP_Command_Exception(
                    "Invalid parameters for command: {$this->command_name}"
                );
            }
            
            // Execute the specific command logic
            $result = $this->execute_command($parameters);
            
            // Log successful execution
            $this->logger->debug("Command executed successfully: {$this->command_name}");
            
            return $result;
            
        } catch (Exception $e) {
            $this->logger->error("Command execution failed: {$this->command_name}", array(
                'error' => $e->getMessage(),
                'parameters' => $this->sanitize_parameters($parameters)
            ));
            
            throw new AANP_Command_Exception(
                "Command execution failed: {$e->getMessage()}",
                $e->getCode(),
                $e
            );
        }
    }
    
    /**
     * Execute specific command logic (to be implemented by subclasses)
     * 
     * @param array $parameters Command parameters
     * @return mixed Command result
     */
    abstract protected function execute_command($parameters);
    
    /**
     * Sanitize parameters for logging
     * 
     * @param array $parameters Raw parameters
     * @return array Sanitized parameters
     */
    protected function sanitize_parameters($parameters) {
        $sanitized = array();
        
        foreach ($parameters as $key => $value) {
            if (is_string($value)) {
                // Remove sensitive data
                $sanitized[$key] = preg_replace(
                    '/(password|secret|key|token|auth)\s*[=:]\s*[^,\s]+/i',
                    '$1=[REDACTED]',
                    $value
                );
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Get command info
     * 
     * @return array Command information
     */
    public function get_command_info() {
        return array(
            'name' => $this->command_name,
            'class' => get_class($this),
            'can_undo' => $this->can_undo(),
            'timestamp' => current_time('mysql')
        );
    }
}

/**
 * Command Exception Class
 */
class AANP_Command_Exception extends Exception {}