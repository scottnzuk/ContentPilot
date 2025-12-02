<?php
/**
 * Service Registry for Dependency Management
 *
 * Provides centralized service registration, dependency injection, and lifecycle management
 * for the microservices architecture. Implements the Dependency Inversion Principle
 * with comprehensive error handling and performance monitoring.
 *
 * @package AI_Auto_News_Poster\Core
 * @since 1.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Service Registry Class
 *
 * Manages all service instances, their dependencies, and lifecycle
 */
class CP_ServiceRegistry {
    
    /**
     * Registered services
     * @var array
     */
    private $services = array();
    
    /**
     * Service instances (singleton pattern)
     * @var array
     */
    private $instances = array();
    
    /**
     * Service configurations
     * @var array
     */
    private $configurations = array();
    
    /**
     * Service dependencies mapping
     * @var array
     */
    private $dependencies = array();
    
    /**
     * Logger instance
     * @var AANP_Logger
     */
    private $logger;
    
    /**
     * Performance metrics
     * @var array
     */
    private $metrics = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = CP_Logger::getInstance();
        $this->init_builtin_services();
    }
    
    /**
     * Initialize built-in services
     */
    private function init_builtin_services() {
        try {
            // Register core services with their dependencies
            $this->register_service('news-fetch', 'AANP_NewsFetchService', array(), 10);
            $this->register_service('ai-generation', 'AANP_AIGenerationService', array('news-fetch'), 15);
            $this->register_service('content-creation', 'AANP_ContentCreationService', array('ai-generation', 'news-fetch'), 20);
            $this->register_service('analytics', 'AANP_AnalyticsService', array(), 5);
            $this->register_service('cache-manager', 'AANP_AdvancedCacheManager', array(), 8);
            $this->register_service('connection-pool', 'AANP_ConnectionPoolManager', array(), 12);
            $this->register_service('queue', 'AANP_QueueManager', array('connection-pool'), 18);
            $this->register_service('seo-analyzer', 'AANP_ContentAnalyzer', array(), 14);
            $this->register_service('eeat-optimizer', 'AANP_EEATOptimizer', array('seo-analyzer'), 16);
            
            $this->logger->info('Built-in services initialized in ServiceRegistry', array(
                'service_count' => count($this->services)
            ));
            
        } catch (Exception $e) {
            $this->logger->error('Failed to initialize built-in services', array(
                'error' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Register a service
     *
     * @param string $name Service name/identifier
     * @param string $class Service class name
     * @param array $dependencies Required dependencies
     * @param int $priority Loading priority (higher loads first)
     * @return bool Success status
     */
    public function register_service($name, $class, $dependencies = array(), $priority = 10) {
        try {
            // Validate parameters
            if (empty($name) || !is_string($name)) {
                throw new InvalidArgumentException('Service name must be a non-empty string');
            }
            
            if (empty($class) || !is_string($class)) {
                throw new InvalidArgumentException('Service class must be a non-empty string');
            }
            
            if (!is_array($dependencies)) {
                throw new InvalidArgumentException('Dependencies must be an array');
            }
            
            // Check if service already exists
            if (isset($this->services[$name])) {
                $this->logger->warning('Service already registered', array(
                    'service' => $name,
                    'existing_class' => $this->services[$name]['class'],
                    'new_class' => $class
                ));
                return false;
            }
            
            // Validate class exists or can be autoloaded
            if (!class_exists($class) && !$this->can_autoload_class($class)) {
                $this->logger->warning('Service class not found or cannot be autoloaded', array(
                    'service' => $name,
                    'class' => $class
                ));
                // Continue registration but log warning - class might be loaded later
            }
            
            // Register the service
            $this->services[$name] = array(
                'class' => $class,
                'dependencies' => $dependencies,
                'priority' => $priority,
                'registered_at' => current_time('Y-m-d H:i:s'),
                'status' => 'registered'
            );
            
            // Store dependencies mapping
            $this->dependencies[$name] = $dependencies;
            
            $this->logger->debug('Service registered successfully', array(
                'service' => $name,
                'class' => $class,
                'dependencies_count' => count($dependencies),
                'priority' => $priority
            ));
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to register service', array(
                'service' => $name,
                'class' => $class,
                'error' => $e->getMessage()
            ));
            return false;
        }
    }
    
    /**
     * Get a service instance (lazy loading with dependency injection)
     *
     * @param string $name Service name
     * @return mixed Service instance or null
     */
    public function get_service($name) {
        try {
            // Check if service is already instantiated
            if (isset($this->instances[$name])) {
                return $this->instances[$name];
            }
            
            // Check if service is registered
            if (!isset($this->services[$name])) {
                throw new InvalidArgumentException("Service not registered: {$name}");
            }
            
            $service_config = $this->services[$name];
            
            // Check if class can be loaded
            if (!class_exists($service_config['class'])) {
                if (!$this->autoload_class($service_config['class'])) {
                    throw new InvalidArgumentException("Service class not found: {$service_config['class']}");
                }
            }
            
            // Check dependencies
            $dependencies = $this->resolve_dependencies($name, $service_config['dependencies']);
            
            // Create service instance
            $start_time = microtime(true);
            
            $instance = $this->create_service_instance(
                $service_config['class'],
                $dependencies
            );
            
            $creation_time = (microtime(true) - $start_time) * 1000; // ms
            
            // Store instance
            $this->instances[$name] = $instance;
            
            // Update service status
            $this->services[$name]['status'] = 'active';
            $this->services[$name]['initialized_at'] = current_time('Y-m-d H:i:s');
            
            // Store metrics
            $this->metrics['services'][$name] = array(
                'creation_time' => $creation_time,
                'initialized_at' => current_time('Y-m-d H:i:s')
            );
            
            $this->logger->debug('Service instance created successfully', array(
                'service' => $name,
                'class' => $service_config['class'],
                'creation_time_ms' => $creation_time
            ));
            
            return $instance;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to get service instance', array(
                'service' => $name,
                'error' => $e->getMessage()
            ));
            return null;
        }
    }
    
    /**
     * Resolve service dependencies recursively
     *
     * @param string $service_name Service name
     * @param array $dependencies Required dependencies
     * @return array Resolved dependencies
     */
    private function resolve_dependencies($service_name, $dependencies) {
        $resolved = array();
        
        foreach ($dependencies as $dep_name) {
            // Prevent circular dependencies
            if ($this->has_circular_dependency($service_name, $dep_name)) {
                throw new InvalidArgumentException(
                    "Circular dependency detected: {$service_name} -> {$dep_name}"
                );
            }
            
            $dep_instance = $this->get_service($dep_name);
            if ($dep_instance === null) {
                throw new InvalidArgumentException(
                    "Failed to resolve dependency: {$dep_name} for service {$service_name}"
                );
            }
            
            $resolved[$dep_name] = $dep_instance;
        }
        
        return $resolved;
    }
    
    /**
     * Check for circular dependencies
     *
     * @param string $service_name Current service name
     * @param string $dep_name Dependency service name
     * @param array $visited Visited services (for recursion)
     * @return bool True if circular dependency exists
     */
    private function has_circular_dependency($service_name, $dep_name, $visited = array()) {
        if ($service_name === $dep_name) {
            return true;
        }
        
        if (in_array($service_name, $visited)) {
            return true;
        }
        
        $visited[] = $service_name;
        
        if (!isset($this->dependencies[$dep_name])) {
            return false;
        }
        
        foreach ($this->dependencies[$dep_name] as $sub_dep) {
            if ($this->has_circular_dependency($dep_name, $sub_dep, $visited)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Create service instance with dependency injection
     *
     * @param string $class_name Class name
     * @param array $dependencies Injected dependencies
     * @return mixed Service instance
     */
    private function create_service_instance($class_name, $dependencies) {
        // Use reflection to determine constructor parameters
        $reflection = new ReflectionClass($class_name);
        
        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            // No constructor, create instance directly
            return new $class_name();
        }
        
        $parameters = $constructor->getParameters();
        if (empty($parameters)) {
            // No parameters, create instance directly
            return new $class_name();
        }
        
        // Try to inject dependencies based on parameter names
        $args = array();
        foreach ($parameters as $parameter) {
            $param_name = $parameter->getName();
            
            // Check if we have a dependency with this name
            if (isset($dependencies[$param_name])) {
                $args[] = $dependencies[$param_name];
            } else {
                // Try parameter type hinting
                $type = $parameter->getType();
                if ($type && !$type->isBuiltin()) {
                    $type_name = $type->getName();
                    
                    // Try to find dependency by class name
                    foreach ($dependencies as $dep_name => $dep_instance) {
                        if ($dep_instance instanceof $type_name) {
                            $args[] = $dep_instance;
                            continue 2;
                        }
                    }
                }
                
                // Check if parameter has default value
                if ($parameter->isDefaultValueAvailable()) {
                    $args[] = $parameter->getDefaultValue();
                } else {
                    // Try to resolve by service name matching
                    if (isset($dependencies[$type_name])) {
                        $args[] = $dependencies[$type_name];
                    } else {
                        throw new InvalidArgumentException(
                            "Cannot resolve parameter {$param_name} for service {$class_name}"
                        );
                    }
                }
            }
        }
        
        return $reflection->newInstanceArgs($args);
    }
    
    /**
     * Check if class can be autoloaded
     *
     * @param string $class_name Class name
     * @return bool True if can be autoloaded
     */
    private function can_autoload_class($class_name) {
        // Check common autoload paths for our plugin
        $possible_paths = array(
            AANP_PLUGIN_DIR . "includes/services/{$class_name}.php",
            AANP_PLUGIN_DIR . "includes/{$class_name}.php",
            AANP_PLUGIN_DIR . "includes/core/{$class_name}.php",
            AANP_PLUGIN_DIR . "includes/performance/{$class_name}.php",
            AANP_PLUGIN_DIR . "includes/seo/{$class_name}.php"
        );
        
        foreach ($possible_paths as $path) {
            if (file_exists($path)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Autoload class file
     *
     * @param string $class_name Class name
     * @return bool Success status
     */
    private function autoload_class($class_name) {
        $possible_paths = array(
            AANP_PLUGIN_DIR . "includes/services/{$class_name}.php",
            AANP_PLUGIN_DIR . "includes/{$class_name}.php",
            AANP_PLUGIN_DIR . "includes/core/{$class_name}.php",
            AANP_PLUGIN_DIR . "includes/performance/{$class_name}.php",
            AANP_PLUGIN_DIR . "includes/seo/{$class_name}.php"
        );
        
        foreach ($possible_paths as $path) {
            if (file_exists($path)) {
                try {
                    require_once $path;
                    return true;
                } catch (Exception $e) {
                    $this->logger->warning('Failed to autoload class file', array(
                        'class' => $class_name,
                        'path' => $path,
                        'error' => $e->getMessage()
                    ));
                }
            }
        }
        
        return false;
    }
    
    /**
     * Unregister a service
     *
     * @param string $name Service name
     * @return bool Success status
     */
    public function unregister_service($name) {
        try {
            if (!isset($this->services[$name])) {
                return false;
            }
            
            // Remove instance if it exists
            if (isset($this->instances[$name])) {
                unset($this->instances[$name]);
            }
            
            // Remove from services registry
            unset($this->services[$name]);
            
            // Remove from dependencies
            unset($this->dependencies[$name]);
            
            // Remove from other services' dependencies
            foreach ($this->dependencies as $dep_service => $deps) {
                $this->dependencies[$dep_service] = array_filter(
                    $deps,
                    function($dep) use ($name) {
                        return $dep !== $name;
                    }
                );
            }
            
            $this->logger->debug('Service unregistered successfully', array(
                'service' => $name
            ));
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to unregister service', array(
                'service' => $name,
                'error' => $e->getMessage()
            ));
            return false;
        }
    }
    
    /**
     * Get all registered services
     *
     * @return array Registered services
     */
    public function get_registered_services() {
        return $this->services;
    }
    
    /**
     * Get service information
     *
     * @param string $name Service name
     * @return array Service information or null
     */
    public function get_service_info($name) {
        if (!isset($this->services[$name])) {
            return null;
        }
        
        $info = $this->services[$name];
        $info['has_instance'] = isset($this->instances[$name]);
        $info['metrics'] = isset($this->metrics['services'][$name]) 
            ? $this->metrics['services'][$name] 
            : null;
        
        return $info;
    }
    
    /**
     * Get registry performance metrics
     *
     * @return array Performance metrics
     */
    public function get_metrics() {
        return array(
            'registered_services' => count($this->services),
            'active_instances' => count($this->instances),
            'services' => $this->metrics['services'] ?? array(),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'timestamp' => current_time('Y-m-d H:i:s')
        );
    }
    
    /**
     * Clear all service instances (for testing or reset)
     *
     * @return bool Success status
     */
    public function clear_instances() {
        try {
            $this->instances = array();
            
            // Update service statuses
            foreach ($this->services as $name => $config) {
                $this->services[$name]['status'] = 'registered';
                unset($this->services[$name]['initialized_at']);
            }
            
            $this->logger->info('All service instances cleared');
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to clear service instances', array(
                'error' => $e->getMessage()
            ));
            return false;
        }
    }
    
    /**
     * Validate service configuration
     *
     * @param string $name Service name
     * @param array $config Service configuration
     * @return bool True if valid
     */
    private function validate_service_config($name, $config) {
        $required_fields = array('class');
        
        foreach ($required_fields as $field) {
            if (!isset($config[$field]) || empty($config[$field])) {
                return false;
            }
        }
        
        return true;
    }
}