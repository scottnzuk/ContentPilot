<?php
/**
 * Humanizer Manager Class
 *
 * Handles AI content humanization using the offline "humano" Python package.
 * Integrates seamlessly with ContentCreationService for automatic content humanization.
 *
 * @package AI_Auto_News_Poster
 * @since 1.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Humanizer Manager Class
 * 
 * Manages offline AI content humanization using the humano Python package.
 * Provides system compatibility checking, graceful degradation, and admin interface integration.
 */
class AANP_HumanizerManager {
    
    /**
     * Humanization enabled status
     * @var bool
     */
    private $enabled = false;
    
    /**
     * Humanization strength setting
     * @var string
     */
    private $strength = 'medium';
    
    /**
     * Custom personality setting
     * @var string
     */
    private $custom_personality = '';
    
    /**
     * Python executable path
     * @var string
     */
    private $python_path = 'python3';
    
    /**
     * Humanizer script path
     * @var string
     */
    private $script_path;
    
    /**
     * Logger instance
     * @var AANP_Logger
     */
    private $logger;
    
    /**
     * Cache manager instance
     * @var AANP_AdvancedCacheManager
     */
    private $cache_manager;
    
    /**
     * System requirements status
     * @var array
     */
    private $system_status = array();
    
    /**
     * Constructor
     *
     * @param AANP_AdvancedCacheManager $cache_manager
     */
    public function __construct(AANP_AdvancedCacheManager $cache_manager = null) {
        $this->logger = AANP_Logger::getInstance();
        $this->cache_manager = $cache_manager ?: new AANP_AdvancedCacheManager();
        
        // Set script path
        $this->script_path = CP_PLUGIN_DIR . 'contentpilot-humanizer/humanizer.py';
        
        // Load settings
        $this->load_settings();
        
        // Initialize system requirements check
        $this->check_system_requirements();
        
        // Log initialization
        $this->logger->info('HumanizerManager initialized', array(
            'enabled' => $this->enabled,
            'strength' => $this->strength,
            'system_status' => $this->system_status
        ));
    }
    
    /**
     * Load humanizer settings
     */
    private function load_settings() {
        $options = get_option('aanp_settings', array());
        
        $this->enabled = isset($options['humanizer_enabled']) ? (bool) $options['humanizer_enabled'] : false;
        $this->strength = isset($options['humanizer_strength']) ? sanitize_text_field($options['humanizer_strength']) : 'medium';
        $this->custom_personality = isset($options['humanizer_personality']) ? sanitize_text_field($options['humanizer_personality']) : '';
        
        // Validate strength setting
        $valid_strengths = array('low', 'medium', 'high', 'maximum');
        if (!in_array($this->strength, $valid_strengths, true)) {
            $this->strength = 'medium';
        }
    }
    
    /**
     * Check system requirements and dependencies
     *
     * @return array System status
     */
    public function check_system_requirements() {
        $this->system_status = array(
            'python_available' => false,
            'python_version' => null,
            'humano_available' => false,
            'script_exists' => false,
            'script_executable' => false,
            'overall_status' => false,
            'missing_requirements' => array(),
            'error_messages' => array()
        );
        
        // Check Python availability
        $python_check = $this->check_python_availability();
        $this->system_status['python_available'] = $python_check['available'];
        $this->system_status['python_version'] = $python_check['version'];
        
        if (!$python_check['available']) {
            $this->system_status['missing_requirements'][] = 'Python 3';
            $this->system_status['error_messages'][] = 'Python 3 is required but not available';
        }
        
        // Check humano package availability
        $humano_check = $this->check_humano_package();
        $this->system_status['humano_available'] = $humano_check['available'];
        
        if (!$humano_check['available']) {
            $this->system_status['missing_requirements'][] = 'humano Python package';
            $this->system_status['error_messages'][] = 'The "humano" Python package is not installed';
        }
        
        // Check script file
        $this->system_status['script_exists'] = file_exists($this->script_path);
        if (!$this->system_status['script_exists']) {
            $this->system_status['missing_requirements'][] = 'Humanizer script';
            $this->system_status['error_messages'][] = 'Humanizer script not found';
        }
        
        // Check script executability
        if ($this->system_status['script_exists']) {
            $this->system_status['script_executable'] = is_readable($this->script_path) && is_executable($this->script_path);
            if (!$this->system_status['script_executable']) {
                $this->system_status['error_messages'][] = 'Humanizer script is not executable';
            }
        }
        
        // Overall status
        $this->system_status['overall_status'] = (
            $this->system_status['python_available'] &&
            $this->system_status['humano_available'] &&
            $this->system_status['script_exists'] &&
            $this->system_status['script_executable']
        );
        
        // Cache system status
        $this->cache_manager->set('humanizer_system_status', $this->system_status, 3600);
        
        return $this->system_status;
    }
    
    /**
     * Check Python availability and version
     *
     * @return array Python availability status
     */
    private function check_python_availability() {
        $result = array(
            'available' => false,
            'version' => null,
            'error' => null
        );
        
        try {
            // Check if python3 is available
            $output = shell_exec($this->python_path . ' --version 2>&1');
            if ($output) {
                preg_match('/Python (\d+\.\d+\.\d+)/', $output, $matches);
                if (isset($matches[1])) {
                    $version = $matches[1];
                    $result['version'] = $version;
                    
                    // Check if version is 3.x
                    $version_parts = explode('.', $version);
                    if (intval($version_parts[0]) >= 3) {
                        $result['available'] = true;
                    } else {
                        $result['error'] = 'Python 2.x detected, Python 3.x required';
                    }
                } else {
                    $result['error'] = 'Unable to parse Python version';
                }
            } else {
                $result['error'] = 'Python 3 not found in PATH';
            }
        } catch (Exception $e) {
            $result['error'] = 'Error checking Python: ' . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Check humano package availability
     *
     * @return array Humano package status
     */
    private function check_humano_package() {
        $result = array(
            'available' => false,
            'error' => null
        );
        
        try {
            // Check if humano package is installed
            $output = shell_exec($this->python_path . ' -c "import humano; print(humano.__version__)" 2>&1');
            if ($output && !strpos($output, 'ImportError') && !strpos($output, 'ModuleNotFoundError')) {
                $result['available'] = true;
            } else {
                $result['error'] = 'humano package not installed or importable';
            }
        } catch (Exception $e) {
            $result['error'] = 'Error checking humano package: ' . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Humanize content using the humano Python package
     *
     * @param string $content Content to humanize
     * @return array Humanization result
     */
    public function humanize_content($content) {
        $start_time = microtime(true);
        
        try {
            // Check if humanizer is enabled
            if (!$this->enabled) {
                return array(
                    'success' => false,
                    'error' => 'Humanizer is disabled',
                    'original_content' => $content,
                    'humanized_content' => $content,
                    'execution_time_ms' => 0
                );
            }
            
            // Check system requirements
            if (!$this->system_status['overall_status']) {
                $this->check_system_requirements(); // Refresh status
                if (!$this->system_status['overall_status']) {
                    return array(
                        'success' => false,
                        'error' => 'Humanization unavailable: ' . implode(', ', $this->system_status['missing_requirements']),
                        'original_content' => $content,
                        'humanized_content' => $content,
                        'execution_time_ms' => 0,
                        'missing_requirements' => $this->system_status['missing_requirements']
                    );
                }
            }
            
            // Sanitize input content
            $content = $this->sanitize_input_content($content);
            if (empty($content)) {
                throw new Exception('Content is empty after sanitization');
            }
            
            // Call Python script
            $result = $this->call_python_script($content);
            
            $execution_time = (microtime(true) - $start_time) * 1000;
            
            if ($result['success']) {
                $this->logger->info('Content humanized successfully', array(
                    'original_length' => strlen($content),
                    'humanized_length' => strlen($result['content']),
                    'strength' => $this->strength,
                    'execution_time_ms' => $execution_time
                ));
                
                return array(
                    'success' => true,
                    'original_content' => $content,
                    'humanized_content' => $result['content'],
                    'execution_time_ms' => $execution_time,
                    'metadata' => array(
                        'strength' => $this->strength,
                        'personality' => $this->custom_personality,
                        'python_version' => $this->system_status['python_version']
                    )
                );
            } else {
                throw new Exception($result['error']);
            }
            
        } catch (Exception $e) {
            $execution_time = (microtime(true) - $start_time) * 1000;
            
            $this->logger->error('Content humanization failed', array(
                'error' => $e->getMessage(),
                'execution_time_ms' => $execution_time
            ));
            
            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'original_content' => $content,
                'humanized_content' => $content, // Fallback to original
                'execution_time_ms' => $execution_time
            );
        }
    }
    
    /**
     * Call Python script for humanization
     *
     * @param string $content Content to humanize
     * @return array Script execution result
     */
    private function call_python_script($content) {
        $result = array(
            'success' => false,
            'content' => '',
            'error' => null
        );
        
        try {
            // Prepare input data
            $input_data = array(
                'text' => $content,
                'strength' => $this->strength,
                'personality' => $this->custom_personality
            );
            
            $input_json = json_encode($input_data);
            if ($input_json === false) {
                throw new Exception('Failed to encode input data');
            }
            
            // Prepare command
            $command = sprintf(
                'echo %s | %s %s 2>&1',
                escapeshellarg($input_json),
                escapeshellarg($this->python_path),
                escapeshellarg($this->script_path)
            );
            
            // Execute with timeout
            $timeout = 30; // 30 second timeout
            $output = shell_exec(sprintf('timeout %d %s', $timeout, $command));
            
            if ($output === null) {
                throw new Exception('Script execution timed out');
            }
            
            // Check for errors in output
            if (strpos($output, 'Error:') === 0) {
                $error_message = trim(substr($output, 6)); // Remove "Error: " prefix
                throw new Exception($error_message);
            }
            
            // Check for successful output
            $trimmed_output = trim($output);
            if (empty($trimmed_output)) {
                throw new Exception('No output received from humanizer script');
            }
            
            // Parse output
            $parsed_output = json_decode($trimmed_output, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($parsed_output['humanized_text'])) {
                $result['success'] = true;
                $result['content'] = $parsed_output['humanized_text'];
            } else {
                // If not JSON, treat as direct text output
                $result['success'] = true;
                $result['content'] = $trimmed_output;
            }
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
            $this->logger->warning('Python script execution failed', array(
                'error' => $e->getMessage(),
                'script_path' => $this->script_path
            ));
        }
        
        return $result;
    }
    
    /**
     * Sanitize input content before humanization
     *
     * @param string $content Content to sanitize
     * @return string Sanitized content
     */
    private function sanitize_input_content($content) {
        // Basic sanitization - remove potentially harmful content
        $content = strip_tags($content);
        $content = htmlspecialchars_decode($content, ENT_QUOTES | ENT_HTML5);
        
        // Remove excessive whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);
        
        // Limit content length to prevent issues
        if (strlen($content) > 50000) {
            $content = substr($content, 0, 50000);
        }
        
        return $content;
    }
    
    /**
     * Test humanizer with sample text
     *
     * @param string $sample_text Sample text to test with
     * @return array Test result
     */
    public function test_humanizer($sample_text = null) {
        if (!$sample_text) {
            $sample_text = "Artificial intelligence has revolutionized the way we approach content creation. The integration of AI-powered tools into various industries has demonstrated significant improvements in efficiency and productivity.";
        }
        
        $result = $this->humanize_content($sample_text);
        
        // Calculate estimated human score (GPTZero-style estimation)
        $human_score = $this->estimate_human_score($sample_text, $result['humanized_content']);
        
        $result['test_metadata'] = array(
            'sample_text_length' => strlen($sample_text),
            'humanized_text_length' => strlen($result['humanized_content']),
            'estimated_human_score' => $human_score,
            'system_status' => $this->system_status
        );
        
        return $result;
    }
    
    /**
     * Estimate human-like score (GPTZero-style estimation)
     *
     * @param string $original Original text
     * @param string $humanized Humanized text
     * @return float Estimated human score (0-1)
     */
    private function estimate_human_score($original, $humanized) {
        try {
            // Simple heuristic-based scoring
            // This is a basic implementation - real GPTZero uses more sophisticated ML
            
            $score = 0.5; // Base score
            
            // Text variation indicator
            $original_words = str_word_count(strtolower($original));
            $humanized_words = str_word_count(strtolower($humanized));
            
            if ($original_words > 0) {
                $variation_ratio = abs($humanized_words - $original_words) / $original_words;
                $score += min($variation_ratio * 0.3, 0.3);
            }
            
            // Sentence structure variation
            $original_sentences = preg_split('/[.!?]+/', $original, -1, PREG_SPLIT_NO_EMPTY);
            $humanized_sentences = preg_split('/[.!?]+/', $humanized, -1, PREG_SPLIT_NO_EMPTY);
            
            if (count($original_sentences) > 0 && count($humanized_sentences) > 0) {
                $sentence_variation = abs(count($humanized_sentences) - count($original_sentences)) / count($original_sentences);
                $score += min($sentence_variation * 0.2, 0.2);
            }
            
            // Word choice variation (simple synonym detection)
            $original_unique_words = array_unique(str_word_count(strtolower($original), 1));
            $humanized_unique_words = array_unique(str_word_count(strtolower($humanized), 1));
            
            if (count($original_unique_words) > 0) {
                $word_overlap = count(array_intersect($original_unique_words, $humanized_unique_words)) / count($original_unique_words);
                $score += (1 - $word_overlap) * 0.3;
            }
            
            // Ensure score is within bounds
            return max(0, min(1, $score));
            
        } catch (Exception $e) {
            return 0.5; // Default score on error
        }
    }
    
    /**
     * Get current system status
     *
     * @return array System status
     */
    public function get_system_status() {
        return $this->system_status;
    }
    
    /**
     * Check if humanizer is enabled
     *
     * @return bool
     */
    public function is_enabled() {
        return $this->enabled && $this->system_status['overall_status'];
    }
    
    /**
     * Get humanizer settings
     *
     * @return array Current settings
     */
    public function get_settings() {
        return array(
            'enabled' => $this->enabled,
            'strength' => $this->strength,
            'custom_personality' => $this->custom_personality,
            'system_status' => $this->system_status
        );
    }
    
    /**
     * Update humanizer settings
     *
     * @param array $settings New settings
     * @return bool Success status
     */
    public function update_settings($settings) {
        try {
            $old_enabled = $this->enabled;
            
            // Validate and update settings
            if (isset($settings['enabled'])) {
                $this->enabled = (bool) $settings['enabled'];
            }
            
            if (isset($settings['strength'])) {
                $valid_strengths = array('low', 'medium', 'high', 'maximum');
                if (in_array($settings['strength'], $valid_strengths, true)) {
                    $this->strength = $settings['strength'];
                }
            }
            
            if (isset($settings['custom_personality'])) {
                $this->custom_personality = sanitize_text_field($settings['custom_personality']);
            }
            
            // Re-check system requirements if enabling
            if ($this->enabled && !$old_enabled) {
                $this->check_system_requirements();
            }
            
            // Save to WordPress options
            $options = get_option('cp_settings', array());
            $options['humanizer_enabled'] = $this->enabled;
            $options['humanizer_strength'] = $this->strength;
            $options['humanizer_personality'] = $this->custom_personality;
            update_option('cp_settings', $options);
            
            $this->logger->info('Humanizer settings updated', array(
                'new_settings' => $this->get_settings()
            ));
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to update humanizer settings', array(
                'error' => $e->getMessage()
            ));
            return false;
        }
    }
    
    /**
     * Get installation instructions for missing dependencies
     *
     * @return array Installation instructions
     */
    public function get_installation_instructions() {
        $instructions = array();
        
        if (!$this->system_status['python_available']) {
            $instructions[] = array(
                'requirement' => 'Python 3',
                'instructions' => array(
                    'Ubuntu/Debian: sudo apt update && sudo apt install python3 python3-pip',
                    'CentOS/RHEL: sudo yum install python3 python3-pip',
                    'macOS: brew install python3',
                    'Windows: Download from https://python.org/downloads/'
                )
            );
        }
        
        if (!$this->system_status['humano_available']) {
            $instructions[] = array(
                'requirement' => 'humano Python package',
                'instructions' => array(
                    'pip3 install humano',
                    'or: python3 -m pip install humano',
                    'For virtual environments: activate venv && pip install humano'
                )
            );
        }
        
        if (!$this->system_status['script_exists']) {
            $instructions[] = array(
                'requirement' => 'Humanizer script',
                'instructions' => array(
                    'Ensure the humanizer.py script is in the plugin directory',
                    'Check file permissions: chmod +x ' . $this->script_path
                )
            );
        }
        
        return $instructions;
    }
    
    /**
     * Health check method
     *
     * @return bool Health status
     */
    public function health_check() {
        try {
            // Test basic functionality
            $test_result = $this->test_humanizer();
            return $test_result['success'];
        } catch (Exception $e) {
            return false;
        }
    }
}