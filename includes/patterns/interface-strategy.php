<?php
/**
 * Strategy Pattern for AI Provider Abstraction
 *
 * @package AI_Auto_News_Poster\Patterns
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI Provider Strategy Interface
 * 
 * Defines the contract for different AI provider strategies
 */
interface AANP_AI_Strategy_Interface {
    
    /**
     * Generate content using AI
     * 
     * @param string $prompt Content generation prompt
     * @param array $options Generation options
     * @return array Generated content
     * @throws AANP_AI_Exception If generation fails
     */
    public function generate_content($prompt, $options = array());
    
    /**
     * Validate API key
     * 
     * @param string $api_key API key to validate
     * @return bool True if valid
     */
    public function validate_api_key($api_key);
    
    /**
     * Get provider information
     * 
     * @return array Provider metadata
     */
    public function get_provider_info();
    
    /**
     * Get usage statistics
     * 
     * @return array Usage stats
     */
    public function get_usage_stats();
    
    /**
     * Configure provider
     * 
     * @param array $config Configuration options
     * @return bool True if configured successfully
     */
    public function configure($config);
}

/**
 * AI Content Generator Context
 * 
 * Uses strategy pattern to switch between different AI providers
 */
class AANP_AI_Generator_Context {
    
    /**
     * Current strategy
     * @var AANP_AI_Strategy_Interface
     */
    private $strategy;
    
    /**
     * Provider configurations
     * @var array
     */
    private $configurations = array();
    
    /**
     * Usage statistics
     * @var array
     */
    private $usage_stats = array();
    
    /**
     * Logger instance
     * @var AANP_Logger
     */
    private $logger;
    
    /**
     * Cache manager
     * @var AANP_Cache_Manager
     */
    private $cache_manager;
    
    /**
     * Rate limiter
     * @var AANP_Rate_Limiter
     */
    private $rate_limiter;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = AANP_Logger::getInstance();
        $this->cache_manager = new AANP_Cache_Manager();
        $this->rate_limiter = new AANP_Rate_Limiter();
        
        $this->init_strategies();
        $this->load_configurations();
    }
    
    /**
     * Initialize available AI strategies
     */
    private function init_strategies() {
        try {
            // Load strategy classes
            $strategy_files = array(
                'openai-strategy.php',
                'anthropic-strategy.php',
                'openrouter-strategy.php',
                'custom-strategy.php'
            );
            
            foreach ($strategy_files as $file) {
                $file_path = AANP_PLUGIN_DIR . 'includes/strategies/' . $file;
                if (file_exists($file_path)) {
                    require_once $file_path;
                }
            }
            
            $this->logger->debug('AI strategies initialized');
            
        } catch (Exception $e) {
            $this->logger->error('Failed to initialize AI strategies', array(
                'error' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Load provider configurations
     */
    private function load_configurations() {
        $options = get_option('aanp_settings', array());
        
        $this->configurations = array(
            'openai' => array(
                'api_key' => isset($options['openai_api_key']) ? $options['openai_api_key'] : '',
                'model' => isset($options['openai_model']) ? $options['openai_model'] : 'gpt-3.5-turbo',
                'max_tokens' => isset($options['openai_max_tokens']) ? $options['openai_max_tokens'] : 2000,
                'temperature' => isset($options['openai_temperature']) ? $options['openai_temperature'] : 0.7
            ),
            'anthropic' => array(
                'api_key' => isset($options['anthropic_api_key']) ? $options['anthropic_api_key'] : '',
                'model' => isset($options['anthropic_model']) ? $options['anthropic_model'] : 'claude-3-sonnet-20240229',
                'max_tokens' => isset($options['anthropic_max_tokens']) ? $options['anthropic_max_tokens'] : 2000
            ),
            'openrouter' => array(
                'api_key' => isset($options['openrouter_api_key']) ? $options['openrouter_api_key'] : '',
                'model' => isset($options['openrouter_model']) ? $options['openrouter_model'] : 'openai/gpt-3.5-turbo'
            )
        );
    }
    
    /**
     * Set strategy
     * 
     * @param string $provider Provider name
     * @return bool True if strategy set successfully
     */
    public function set_strategy($provider) {
        try {
            $strategy_class = $this->get_strategy_class($provider);
            
            if (!class_exists($strategy_class)) {
                throw new AANP_AI_Exception("Strategy class not found: {$strategy_class}");
            }
            
            // Check if API key is configured
            if (!$this->is_api_key_configured($provider)) {
                throw new AANP_AI_Exception("API key not configured for provider: {$provider}");
            }
            
            // Create strategy instance
            $config = isset($this->configurations[$provider]) ? $this->configurations[$provider] : array();
            $this->strategy = new $strategy_class($config);
            
            // Configure the strategy
            if (!$this->strategy->configure($config)) {
                throw new AANP_AI_Exception("Failed to configure strategy for provider: {$provider}");
            }
            
            $this->logger->info("AI strategy set to: {$provider}");
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to set AI strategy', array(
                'provider' => $provider,
                'error' => $e->getMessage()
            ));
            
            return false;
        }
    }
    
    /**
     * Generate content
     * 
     * @param string $prompt Generation prompt
     * @param array $options Generation options
     * @return array Generated content
     */
    public function generate_content($prompt, $options = array()) {
        if (!$this->strategy) {
            throw new AANP_AI_Exception('No AI strategy configured');
        }
        
        // Check rate limiting
        if ($this->rate_limiter->is_rate_limited('ai_generation', 10, 3600)) {
            throw new AANP_AI_Exception('Rate limit exceeded for AI generation');
        }
        
        // Generate cache key
        $cache_key = 'ai_generation_' . md5($prompt . serialize($options));
        
        // Check cache first
        $cached_result = $this->cache_manager->get($cache_key);
        if ($cached_result !== false) {
            $this->logger->debug('Returning cached AI generation result');
            return $cached_result;
        }
        
        try {
            $start_time = microtime(true);
            
            // Record rate limit attempt
            $this->rate_limiter->record_attempt('ai_generation', 3600);
            
            // Generate content using strategy
            $result = $this->strategy->generate_content($prompt, $options);
            
            $end_time = microtime(true);
            $generation_time = ($end_time - $start_time) * 1000; // ms
            
            // Update usage statistics
            $this->update_usage_stats($generation_time);
            
            // Cache the result for 1 hour
            $this->cache_manager->set($cache_key, $result, 3600);
            
            $this->logger->info('AI content generated successfully', array(
                'generation_time_ms' => $generation_time,
                'content_length' => strlen($result['content'] ?? ''),
                'strategy' => get_class($this->strategy)
            ));
            
            return $result;
            
        } catch (Exception $e) {
            $this->logger->error('AI content generation failed', array(
                'error' => $e->getMessage(),
                'strategy' => get_class($this->strategy)
            ));
            
            throw new AANP_AI_Exception('Content generation failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Get strategy class name for provider
     * 
     * @param string $provider Provider name
     * @return string Class name
     */
    private function get_strategy_class($provider) {
        $class_map = array(
            'openai' => 'AANP_OpenAI_Strategy',
            'anthropic' => 'AANP_Anthropic_Strategy',
            'openrouter' => 'AANP_OpenRouter_Strategy',
            'custom' => 'AANP_Custom_Strategy'
        );
        
        if (!isset($class_map[$provider])) {
            throw new AANP_AI_Exception("Unknown AI provider: {$provider}");
        }
        
        return $class_map[$provider];
    }
    
    /**
     * Check if API key is configured for provider
     * 
     * @param string $provider Provider name
     * @return bool
     */
    private function is_api_key_configured($provider) {
        if (!isset($this->configurations[$provider])) {
            return false;
        }
        
        $config = $this->configurations[$provider];
        return !empty($config['api_key']);
    }
    
    /**
     * Update usage statistics
     * 
     * @param float $generation_time Generation time in milliseconds
     */
    private function update_usage_stats($generation_time) {
        if (!isset($this->usage_stats['total_generations'])) {
            $this->usage_stats = array(
                'total_generations' => 0,
                'total_time' => 0,
                'average_time' => 0,
                'last_generation' => current_time('mysql')
            );
        }
        
        $this->usage_stats['total_generations']++;
        $this->usage_stats['total_time'] += $generation_time;
        $this->usage_stats['average_time'] = $this->usage_stats['total_time'] / $this->usage_stats['total_generations'];
        $this->usage_stats['last_generation'] = current_time('mysql');
        
        // Save to cache
        $this->cache_manager->set('ai_generator_usage_stats', $this->usage_stats, 3600);
    }
    
    /**
     * Get usage statistics
     * 
     * @return array
     */
    public function get_usage_stats() {
        if (empty($this->usage_stats)) {
            $cached_stats = $this->cache_manager->get('ai_generator_usage_stats');
            if ($cached_stats !== false) {
                $this->usage_stats = $cached_stats;
            }
        }
        
        return $this->usage_stats;
    }
    
    /**
     * Get available providers
     * 
     * @return array
     */
    public function get_available_providers() {
        $providers = array();
        
        foreach ($this->configurations as $provider => $config) {
            if ($this->is_api_key_configured($provider)) {
                try {
                    $strategy_class = $this->get_strategy_class($provider);
                    if (class_exists($strategy_class)) {
                        $strategy = new $strategy_class($config);
                        $providers[$provider] = $strategy->get_provider_info();
                    }
                } catch (Exception $e) {
                    $this->logger->warning('Failed to get provider info', array(
                        'provider' => $provider,
                        'error' => $e->getMessage()
                    ));
                }
            }
        }
        
        return $providers;
    }
    
    /**
     * Validate API key for provider
     * 
     * @param string $provider Provider name
     * @param string $api_key API key
     * @return bool
     */
    public function validate_api_key($provider, $api_key) {
        try {
            $strategy_class = $this->get_strategy_class($provider);
            
            if (!class_exists($strategy_class)) {
                return false;
            }
            
            $strategy = new $strategy_class(array('api_key' => $api_key));
            return $strategy->validate_api_key($api_key);
            
        } catch (Exception $e) {
            $this->logger->error('API key validation failed', array(
                'provider' => $provider,
                'error' => $e->getMessage()
            ));
            
            return false;
        }
    }
}

/**
 * Base AI Strategy Class
 */
abstract class AANP_AI_Strategy_Base implements AANP_AI_Strategy_Interface {
    
    /**
     * Configuration
     * @var array
     */
    protected $config = array();
    
    /**
     * Logger instance
     * @var AANP_Logger
     */
    protected $logger;
    
    /**
     * Constructor
     * 
     * @param array $config Configuration options
     */
    public function __construct($config = array()) {
        $this->config = $config;
        $this->logger = AANP_Logger::getInstance();
    }
    
    /**
     * Configure the strategy
     * 
     * @param array $config
     * @return bool
     */
    public function configure($config) {
        $this->config = array_merge($this->config, $config);
        return true;
    }
    
    /**
     * Get provider info
     * 
     * @return array
     */
    public function get_provider_info() {
        return array(
            'name' => $this->get_provider_name(),
            'class' => get_class($this),
            'configured' => $this->is_configured()
        );
    }
    
    /**
     * Get usage statistics
     * 
     * @return array
     */
    public function get_usage_stats() {
        return array();
    }
    
    /**
     * Check if strategy is configured
     * 
     * @return bool
     */
    protected function is_configured() {
        return !empty($this->config['api_key']);
    }
    
    /**
     * Get provider name (to be implemented by subclasses)
     * 
     * @return string
     */
    abstract protected function get_provider_name();
}