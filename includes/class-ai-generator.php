<?php
/**
 * AI Generator Class
 *
 * @package ContentPilot
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class CP_AI_Generator {
    
    private $api_key;
    private $provider;
    private $word_count;
    private $tone;
    private $logger;
    private $security_manager;
    private $rate_limiter;
    private $max_retries = 3;
    private $timeout = 60;
    
    /**
     * Constructor
     */
    public function __construct() {
        try {
            // Initialize dependencies with error handling
            $this->init_dependencies();
            
            // Initialize configuration with validation
            $this->init_configuration();
            
        } catch (Exception $e) {
            $this->log_error('AI Generator initialization failed', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
            throw $e;
        }
    }
    
    /**
     * Initialize dependencies with proper error handling
     *
     * @throws Exception If dependency initialization fails
     */
    private function init_dependencies() {
        // Initialize logger
        if (class_exists('CP_Logger')) {
            try {
                $this->logger = CP_Logger::getInstance();
            } catch (Exception $e) {
                $this->logger = null;
                error_log('CP Logger initialization failed: ' . $e->getMessage());
            }
        } elseif (class_exists('AANP_Logger')) {
            try {
                $this->logger = AANP_Logger::getInstance();
            } catch (Exception $e) {
                $this->logger = null;
                error_log('AANP Logger initialization failed: ' . $e->getMessage());
            }
        } else {
            $this->logger = null;
        }
        
        // Initialize security manager
        if (class_exists('CP_Security_Manager')) {
            try {
                $this->security_manager = new CP_Security_Manager();
            } catch (Exception $e) {
                $this->security_manager = null;
                $this->log_error('Security Manager initialization failed', array(
                    'error' => $e->getMessage()
                ));
            }
        } elseif (class_exists('AANP_Security_Manager')) {
            try {
                $this->security_manager = new AANP_Security_Manager();
            } catch (Exception $e) {
                $this->security_manager = null;
                $this->log_error('Security Manager initialization failed', array(
                    'error' => $e->getMessage()
                ));
            }
        } else {
            $this->security_manager = null;
        }
        
        // Initialize rate limiter
        if (class_exists('CP_Rate_Limiter')) {
            try {
                $this->rate_limiter = new CP_Rate_Limiter();
            } catch (Exception $e) {
                $this->rate_limiter = null;
                $this->log_error('Rate Limiter initialization failed', array(
                    'error' => $e->getMessage()
                ));
            }
        } elseif (class_exists('AANP_Rate_Limiter')) {
            try {
                $this->rate_limiter = new AANP_Rate_Limiter();
            } catch (Exception $e) {
                $this->rate_limiter = null;
                $this->log_error('Rate Limiter initialization failed', array(
                    'error' => $e->getMessage()
                ));
            }
        } else {
            $this->rate_limiter = null;
        }
    }
    
    /**
     * Initialize configuration with comprehensive validation
     *
     * @throws Exception If configuration initialization fails
     */
    private function init_configuration() {
        try {
            $options = get_option('cp_settings', array());
            
            // Validate and decrypt API key
            $encrypted_key = isset($options['api_key']) ? $options['api_key'] : '';
            $this->api_key = $this->validate_and_decrypt_api_key($encrypted_key);
            
            // Validate LLM provider
            $this->provider = $this->validate_provider(isset($options['llm_provider']) ? $options['llm_provider'] : 'openai');
            
            // Validate word count
            $this->word_count = $this->validate_word_count(isset($options['word_count']) ? $options['word_count'] : 'medium');
            
            // Validate tone
            $this->tone = $this->validate_tone(isset($options['tone']) ? $options['tone'] : 'neutral');
            
        } catch (Exception $e) {
            $this->log_error('Configuration initialization failed', array(
                'error' => $e->getMessage(),
                'options' => array_keys($options)
            ));
            throw $e;
        }
    }
    
    /**
     * Validate and decrypt API key with error handling
     *
     * @param string $encrypted_key Encrypted API key from settings
     * @return string Decrypted API key
     * @throws Exception If API key validation or decryption fails
     */
    private function validate_and_decrypt_api_key($encrypted_key) {
        try {
            if (empty($encrypted_key)) {
                throw new Exception('API key is not configured');
            }
            
            if (!class_exists('CP_Admin_Settings')) {
                throw new Exception('CP_Admin_Settings class not available');
            }
            
            if (class_exists('CP_Admin_Settings')) {
                $decrypted = CP_Admin_Settings::decrypt_api_key($encrypted_key);
            } else {
                $decrypted = CP_Admin_Settings::decrypt_api_key($encrypted_key);
            }
            
            if (empty($decrypted)) {
                throw new Exception('Failed to decrypt API key - key may be corrupted');
            }
            
            // Validate API key format
            if (!$this->validate_api_key_format($decrypted, $this->provider)) {
                throw new Exception('API key format validation failed');
            }
            
            return $decrypted;
            
        } catch (Exception $e) {
            $this->log_error('API key validation/decryption failed', array(
                'error' => $e->getMessage(),
                'key_length' => strlen($encrypted_key)
            ));
            throw $e;
        }
    }
    
    /**
     * Validate LLM provider
     *
     * @param string $provider LLM provider name (e.g., 'openai', 'anthropic')
     * @return string Validated provider name
     */
    private function validate_provider($provider) {
        $allowed_providers = array('openai', 'anthropic', 'openrouter', 'custom');
        
        if (!in_array($provider, $allowed_providers, true)) {
            $this->log_warning('Invalid LLM provider, using default', array(
                'invalid_provider' => $provider,
                'default_provider' => 'openai'
            ));
            return 'openai';
        }
        
        return $provider;
    }
    
    /**
     * Validate word count setting
     *
     * @param string $word_count Word count setting (e.g., 'short', 'medium', 'long')
     * @return string Validated word count setting
     */
    private function validate_word_count($word_count) {
        $allowed_counts = array('short', 'medium', 'long');
        
        if (!in_array($word_count, $allowed_counts, true)) {
            $this->log_warning('Invalid word count, using default', array(
                'invalid_count' => $word_count,
                'default_count' => 'medium'
            ));
            return 'medium';
        }
        
        return $word_count;
    }
    
    /**
     * Validate tone setting
     *
     * @param string $tone Tone setting (e.g., 'neutral', 'professional', 'friendly')
     * @return string Validated tone setting
     */
    private function validate_tone($tone) {
        $allowed_tones = array('neutral', 'professional', 'friendly');
        
        if (!in_array($tone, $allowed_tones, true)) {
            $this->log_warning('Invalid tone, using default', array(
                'invalid_tone' => $tone,
                'default_tone' => 'neutral'
            ));
            return 'neutral';
        }
        
        return $tone;
    }
    
    /**
     * Validate API key format for different providers
     *
     * @param string $api_key API key to validate
     * @param string $provider LLM provider name
     * @return bool True if API key format is valid, false otherwise
     * @throws Exception If validation fails
     */
    private function validate_api_key_format($api_key, $provider) {
        try {
            if (empty($api_key) || strlen($api_key) < 10) {
                return false;
            }
            
            switch ($provider) {
                case 'openai':
                    return (substr($api_key, 0, 3) === 'sk-' && strlen($api_key) >= 51);
                    
                case 'anthropic':
                    return (substr($api_key, 0, 7) === 'sk-ant-' && strlen($api_key) >= 48);
                    
                case 'openrouter':
                    return (substr($api_key, 0, 5) === 'sk-or-' && strlen($api_key) >= 30);
                    
                case 'custom':
                    return strlen($api_key) >= 10;
                    
                default:
                    return false;
            }
        } catch (Exception $e) {
            $this->log_error('API key format validation error', array(
                'error' => $e->getMessage(),
                'provider' => $provider
            ));
            return false;
        }
    }
    
    /**
     * Generate content from news article with comprehensive error handling
     *
     * @param array $article Article data
     * @return array|false Generated content or false on failure
     */
    public function generate_content($article) {
        try {
            // Validate input parameters
            $this->validate_article_input($article);
            
            // Check rate limiting
            if ($this->rate_limiter && ($this->rate_limiter instanceof CP_Rate_Limiter || $this->rate_limiter instanceof AANP_Rate_Limiter) && $this->rate_limiter->is_rate_limited('ai_generation', 10, 3600)) {
                $this->log_warning('Rate limit exceeded for AI generation', array(
                    'user_id' => get_current_user_id()
                ));
                return false;
            }
            
            // Record attempt for rate limiting
            if ($this->rate_limiter && ($this->rate_limiter instanceof CP_Rate_Limiter || $this->rate_limiter instanceof AANP_Rate_Limiter)) {
                $this->rate_limiter->record_attempt('ai_generation', 3600);
            }
            
            $this->log_info('Starting AI content generation', array(
                'provider' => $this->provider,
                'article_url' => $article['link'] ?? 'unknown'
            ));
            
            // Validate API key before proceeding
            if (empty($this->api_key)) {
                $this->log_error('API key not configured');
                return false;
            }
            
            // Build prompt with validation
            $prompt = $this->build_prompt($article);
            
            if (empty($prompt)) {
                $this->log_error('Failed to build AI prompt');
                return false;
            }
            
            // Generate content based on provider with retry logic
            $max_retries = $this->max_retries;
            $last_error = null;
            
            for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
                try {
                    $this->log_info("AI generation attempt {$attempt}/{$max_retries}", array(
                        'provider' => $this->provider,
                        'article_url' => $article['link'] ?? 'unknown'
                    ));
                    
                    $result = null;
                    
                    switch ($this->provider) {
                        case 'openai':
                            $result = $this->generate_with_openai($prompt, $article);
                            break;
                        case 'anthropic':
                            $result = $this->generate_with_anthropic($prompt, $article);
                            break;
                        case 'openrouter':
                            $result = $this->generate_with_openrouter($prompt, $article);
                            break;
                        case 'custom':
                            $result = $this->generate_with_custom_api($prompt, $article);
                            break;
                        default:
                            $this->log_error('Unknown LLM provider: ' . $this->provider);
                            return false;
                    }
                    
                    if ($result) {
                        $this->log_info('AI content generation successful', array(
                            'provider' => $this->provider,
                            'attempt' => $attempt,
                            'title_length' => strlen($result['title'] ?? ''),
                            'content_length' => strlen($result['content'] ?? '')
                        ));
                        return $result;
                    }
                    
                } catch (Exception $e) {
                    $last_error = $e;
                    $this->log_error("AI generation attempt {$attempt} failed", array(
                        'error' => $e->getMessage(),
                        'provider' => $this->provider,
                        'attempt' => $attempt
                    ));
                    
                    // If it's a rate limit error, wait longer before retry
                    if ($this->is_rate_limit_error($e)) {
                        sleep(($attempt * 2)); // Exponential backoff for rate limits
                    } else {
                        sleep(1); // Short delay for other errors
                    }
                }
            }
            
            // All retries failed
            $this->log_error('AI content generation failed after all retries', array(
                'provider' => $this->provider,
                'max_retries' => $max_retries,
                'last_error' => $last_error ? $last_error->getMessage() : 'Unknown error'
            ));
            
            // Return fallback content
            return $this->generate_fallback_content($article);
            
        } catch (Exception $e) {
            $this->log_error('Critical error in content generation', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'article_url' => $article['link'] ?? 'unknown'
            ));
            return false;
        }
    }
    
    /**
     * Validate article input parameters
     *
     * @param array $article Article data to validate
     * @return array Validated and sanitized article data
     * @throws Exception If validation fails
     */
    private function validate_article_input($article) {
        if (!is_array($article)) {
            throw new Exception('Article must be an array');
        }
        
        if (empty($article)) {
            throw new Exception('Article cannot be empty');
        }
        
        $required_fields = array('title', 'description', 'link');
        foreach ($required_fields as $field) {
            if (!isset($article[$field]) || empty(trim($article[$field]))) {
                throw new Exception("Missing required field: {$field}");
            }
        }
        
        // Validate URL if provided
        if (isset($article['link']) && !filter_var($article['link'], FILTER_VALIDATE_URL)) {
            throw new Exception('Invalid article URL format');
        }
        
        // Sanitize input data
        $article['title'] = sanitize_text_field($article['title']);
        $article['description'] = sanitize_textarea_field($article['description']);
        if (isset($article['link'])) {
            $article['link'] = esc_url_raw($article['link']);
        }
        
        return $article;
    }
    
    /**
     * Check if error is related to rate limiting
     *
     * @param Exception $exception Exception to check
     * @return bool True if error is rate limit related, false otherwise
     */
    private function is_rate_limit_error($exception) {
        $error_message = strtolower($exception->getMessage());
        $rate_limit_indicators = array(
            'rate limit',
            'too many requests',
            '429',
            'quota exceeded',
            'billing hard limit'
        );
        
        foreach ($rate_limit_indicators as $indicator) {
            if (strpos($error_message, $indicator) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Build prompt for AI generation with validation
     *
     * @param array $article Article data
     * @return string Generated prompt
     * @throws Exception If prompt building fails
     */
    private function build_prompt($article) {
        try {
            // Validate article data
            if (empty($article['title']) || empty($article['description'])) {
                $this->log_error('Invalid article data for prompt building', array(
                    'has_title' => !empty($article['title']),
                    'has_description' => !empty($article['description'])
                ));
                throw new Exception('Invalid article data - missing title or description');
            }
            
            // Sanitize article content
            $title = sanitize_text_field($article['title']);
            $description = sanitize_textarea_field($article['description']);
            $source_domain = isset($article['source_domain']) ? sanitize_text_field($article['source_domain']) : 'unknown source';
            
            // Validate word count setting
            $word_counts = array(
                'short' => '300-400',
                'medium' => '500-600',
                'long' => '800-1000'
            );
            
            $word_range = isset($word_counts[$this->word_count]) ? $word_counts[$this->word_count] : '500-600';
            
            // Validate tone setting
            $tone_descriptions = array(
                'neutral' => 'neutral and informative',
                'professional' => 'professional and authoritative',
                'friendly' => 'friendly and conversational'
            );
            
            $tone_desc = isset($tone_descriptions[$this->tone]) ? $tone_descriptions[$this->tone] : 'neutral and informative';
            
            // Build the prompt with proper escaping
            $prompt = "You are a professional content writer. Your task is to rewrite the following news article into a unique, engaging blog post.

";
            $prompt .= "ORIGINAL ARTICLE:
";
            $prompt .= "Title: " . $title . "
";
            $prompt .= "Summary: " . $description . "
";
            $prompt .= "Source: " . $source_domain . "

";
            
            $prompt .= "REQUIREMENTS:
";
            $prompt .= "- Write a {$word_range} word blog post
";
            $prompt .= "- Use a {$tone_desc} tone
";
            $prompt .= "- Create an engaging, SEO-friendly title
";
            $prompt .= "- Include a compelling introduction
";
            $prompt .= "- Provide detailed analysis and context
";
            $prompt .= "- Add a thoughtful conclusion
";
            $prompt .= "- Do NOT copy text directly from the original
";
            $prompt .= "- Make the content unique and valuable
";
            $prompt .= "- Use proper paragraph structure

";
            
            $prompt .= "Please provide your response in the following JSON format:
";
            $prompt .= "{
";
            $prompt .= "  \"title\": \"Your generated title here\",
";
            $prompt .= "  \"content\": \"Your full blog post content here\"
";
            $prompt .= "}";
            
            // Validate prompt length (prevent overly long prompts)
            if (strlen($prompt) > 8000) {
                $this->log_warning('Prompt length exceeds recommended limit', array(
                    'prompt_length' => strlen($prompt),
                    'recommended_limit' => 8000
                ));
            }
            
            return $prompt;
            
        } catch (Exception $e) {
            $this->log_error('Failed to build AI prompt', array(
                'error' => $e->getMessage(),
                'article_title' => $article['title'] ?? 'unknown'
            ));
            throw $e;
        }
    }
    
    /**
     * Log info message with fallback
     *
     * @param string $message Log message
     * @param array $context Additional context data
     */
    private function log_info($message, $context = array()) {
        if ($this->logger) {
            $this->logger->log('info', $message, $context);
        } else {
            error_log('CP Info: ' . $message);
        }
    }
    
    /**
     * Log warning message with fallback
     *
     * @param string $message Log message
     * @param array $context Additional context data
     */
    private function log_warning($message, $context = array()) {
        if ($this->logger) {
            $this->logger->log('warning', $message, $context);
        } else {
            error_log('CP Warning: ' . $message);
        }
    }
    
    /**
     * Log error message with fallback
     *
     * @param string $message Log message
     * @param array $context Additional context data
     */
    private function log_error($message, $context = array()) {
        if ($this->logger) {
            $this->logger->log('error', $message, $context);
        } else {
            error_log('CP Error: ' . $message);
        }
    }
    
    /**
     * Generate content using OpenAI with comprehensive error handling
     *
     * @param string $prompt AI prompt
     * @param array $article Original article
     * @return array|false Generated content
     * @throws Exception If OpenAI API request fails
     */
    private function generate_with_openai($prompt, $article) {
        try {
            $this->log_info('Starting OpenAI API request', array(
                'prompt_length' => strlen($prompt),
                'article_url' => $article['link'] ?? 'unknown'
            ));
            
            // Validate input parameters
            if (empty($prompt) || strlen($prompt) < 10) {
                throw new Exception('Invalid prompt for OpenAI API');
            }
            
            if (empty($this->api_key)) {
                throw new Exception('OpenAI API key is not configured');
            }
            
            $url = 'https://api.openai.com/v1/chat/completions';
            
            $data = array(
                'model' => 'gpt-3.5-turbo',
                'messages' => array(
                    array(
                        'role' => 'user',
                        'content' => $prompt
                    )
                ),
                'max_tokens' => 2000,
                'temperature' => 0.7
            );
            
            $headers = array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            );
            
            $this->log_info('Making OpenAI API request', array(
                'url' => $url,
                'model' => 'gpt-3.5-turbo',
                'max_tokens' => 2000
            ));
            
            $response = wp_remote_post($url, array(
                'headers' => $headers,
                'body' => json_encode($data),
                'timeout' => $this->timeout
            ));
            
            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                $this->log_error('OpenAI API request failed', array(
                    'error' => $error_message,
                    'error_code' => $response->get_error_code()
                ));
                throw new Exception('OpenAI API request failed: ' . $error_message);
            }
            
            // Check response status
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                $error_message = 'HTTP ' . $response_code . ': ' . wp_remote_retrieve_response_message($response);
                $this->log_error('OpenAI API non-200 response', array(
                    'status_code' => $response_code,
                    'status_message' => wp_remote_retrieve_response_message($response)
                ));
                throw new Exception($error_message);
            }
            
            $body = wp_remote_retrieve_body($response);
            
            if (empty($body)) {
                $this->log_error('OpenAI API returned empty response body');
                throw new Exception('OpenAI API returned empty response');
            }
            
            $result = json_decode($body, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->log_error('OpenAI API response JSON decode failed', array(
                    'json_error' => json_last_error_msg(),
                    'response_snippet' => substr($body, 0, 500)
                ));
                throw new Exception('Failed to decode OpenAI API response: ' . json_last_error_msg());
            }
            
            // Check for API-specific errors
            if (isset($result['error'])) {
                $api_error = $result['error'];
                $error_message = isset($api_error['message']) ? $api_error['message'] : 'Unknown API error';
                $this->log_error('OpenAI API returned error', array(
                    'error_type' => $api_error['type'] ?? 'unknown',
                    'error_message' => $error_message
                ));
                throw new Exception('OpenAI API error: ' . $error_message);
            }
            
            // Validate response structure
            if (!isset($result['choices']) || !is_array($result['choices']) || empty($result['choices'])) {
                $this->log_error('OpenAI API response missing choices', array(
                    'response_keys' => array_keys($result)
                ));
                throw new Exception('Invalid OpenAI API response structure');
            }
            
            if (!isset($result['choices'][0]['message']['content'])) {
                $this->log_error('OpenAI API response missing content', array(
                    'choices_count' => count($result['choices']),
                    'response_keys' => array_keys($result['choices'][0] ?? array())
                ));
                throw new Exception('OpenAI API response missing content field');
            }
            
            $content = $result['choices'][0]['message']['content'];
            
            if (empty(trim($content))) {
                $this->log_error('OpenAI API returned empty content');
                throw new Exception('OpenAI API returned empty content');
            }
            
            $this->log_info('OpenAI API request successful', array(
                'content_length' => strlen($content),
                'usage' => $result['usage'] ?? 'unknown'
            ));
            
            return $this->parse_ai_response($content, $article);
            
        } catch (Exception $e) {
            $this->log_error('OpenAI API generation failed', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
            throw $e;
        }
    }
    
    /**
     * Generate content using Anthropic with comprehensive error handling
     *
     * @param string $prompt AI prompt
     * @param array $article Original article
     * @return array|false Generated content
     * @throws Exception If Anthropic API request fails
     */
    private function generate_with_anthropic($prompt, $article) {
        try {
            $this->log_info('Starting Anthropic API request', array(
                'prompt_length' => strlen($prompt),
                'article_url' => $article['link'] ?? 'unknown'
            ));
            
            // Validate input parameters
            if (empty($prompt) || strlen($prompt) < 10) {
                throw new Exception('Invalid prompt for Anthropic API');
            }
            
            if (empty($this->api_key)) {
                throw new Exception('Anthropic API key is not configured');
            }
            
            $url = 'https://api.anthropic.com/v1/messages';
            
            $data = array(
                'model' => 'claude-3-sonnet-20240229',
                'max_tokens' => 2000,
                'messages' => array(
                    array(
                        'role' => 'user',
                        'content' => $prompt
                    )
                )
            );
            
            $headers = array(
                'x-api-key' => $this->api_key,
                'Content-Type' => 'application/json',
                'anthropic-version' => '2023-06-01'
            );
            
            $this->log_info('Making Anthropic API request', array(
                'url' => $url,
                'model' => 'claude-3-sonnet-20240229',
                'max_tokens' => 2000
            ));
            
            $response = wp_remote_post($url, array(
                'headers' => $headers,
                'body' => json_encode($data),
                'timeout' => $this->timeout
            ));
            
            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                $this->log_error('Anthropic API request failed', array(
                    'error' => $error_message,
                    'error_code' => $response->get_error_code()
                ));
                throw new Exception('Anthropic API request failed: ' . $error_message);
            }
            
            // Check response status
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                $error_message = 'HTTP ' . $response_code . ': ' . wp_remote_retrieve_response_message($response);
                $this->log_error('Anthropic API non-200 response', array(
                    'status_code' => $response_code,
                    'status_message' => wp_remote_retrieve_response_message($response)
                ));
                throw new Exception($error_message);
            }
            
            $body = wp_remote_retrieve_body($response);
            
            if (empty($body)) {
                $this->log_error('Anthropic API returned empty response body');
                throw new Exception('Anthropic API returned empty response');
            }
            
            $result = json_decode($body, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->log_error('Anthropic API response JSON decode failed', array(
                    'json_error' => json_last_error_msg(),
                    'response_snippet' => substr($body, 0, 500)
                ));
                throw new Exception('Failed to decode Anthropic API response: ' . json_last_error_msg());
            }
            
            // Check for API-specific errors
            if (isset($result['error'])) {
                $api_error = $result['error'];
                $error_message = isset($api_error['message']) ? $api_error['message'] : 'Unknown API error';
                $this->log_error('Anthropic API returned error', array(
                    'error_type' => $api_error['type'] ?? 'unknown',
                    'error_message' => $error_message
                ));
                throw new Exception('Anthropic API error: ' . $error_message);
            }
            
            // Validate response structure
            if (!isset($result['content']) || !is_array($result['content']) || empty($result['content'])) {
                $this->log_error('Anthropic API response missing content', array(
                    'response_keys' => array_keys($result)
                ));
                throw new Exception('Invalid Anthropic API response structure');
            }
            
            if (!isset($result['content'][0]['text'])) {
                $this->log_error('Anthropic API response missing text field', array(
                    'content_count' => count($result['content']),
                    'response_keys' => array_keys($result['content'][0] ?? array())
                ));
                throw new Exception('Anthropic API response missing text field');
            }
            
            $content = $result['content'][0]['text'];
            
            if (empty(trim($content))) {
                $this->log_error('Anthropic API returned empty content');
                throw new Exception('Anthropic API returned empty content');
            }
            
            $this->log_info('Anthropic API request successful', array(
                'content_length' => strlen($content),
                'usage' => $result['usage'] ?? 'unknown'
            ));
            
            return $this->parse_ai_response($content, $article);
            
        } catch (Exception $e) {
            $this->log_error('Anthropic API generation failed', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
            throw $e;
        }
    }
    
    /**
     * Generate content using OpenRouter with comprehensive error handling
     *
     * @param string $prompt AI prompt
     * @param array $article Original article
     * @return array|false Generated content
     * @throws Exception If OpenRouter API request fails
     */
    private function generate_with_openrouter($prompt, $article) {
        try {
            $this->log_info('Starting OpenRouter API request', array(
                'prompt_length' => strlen($prompt),
                'article_url' => $article['link'] ?? 'unknown'
            ));
            
            // Validate input parameters
            if (empty($prompt) || strlen($prompt) < 10) {
                throw new Exception('Invalid prompt for OpenRouter API');
            }
            
            if (empty($this->api_key)) {
                throw new Exception('OpenRouter API key is not configured');
            }
            
            $url = 'https://openrouter.ai/api/v1/chat/completions';
            
            $data = array(
                'model' => 'openai/gpt-3.5-turbo',
                'messages' => array(
                    array(
                        'role' => 'user',
                        'content' => $prompt
                    )
                ),
                'max_tokens' => 2000,
                'temperature' => 0.7
            );
            
            $headers = array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => home_url(),
                'X-Title' => get_bloginfo('name')
            );
            
            $this->log_info('Making OpenRouter API request', array(
                'url' => $url,
                'model' => 'openai/gpt-3.5-turbo',
                'max_tokens' => 2000,
                'referer' => home_url()
            ));
            
            $response = wp_remote_post($url, array(
                'headers' => $headers,
                'body' => json_encode($data),
                'timeout' => $this->timeout
            ));
            
            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                $this->log_error('OpenRouter API request failed', array(
                    'error' => $error_message,
                    'error_code' => $response->get_error_code()
                ));
                throw new Exception('OpenRouter API request failed: ' . $error_message);
            }
            
            // Check response status
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                $error_message = 'HTTP ' . $response_code . ': ' . wp_remote_retrieve_response_message($response);
                $this->log_error('OpenRouter API non-200 response', array(
                    'status_code' => $response_code,
                    'status_message' => wp_remote_retrieve_response_message($response)
                ));
                throw new Exception($error_message);
            }
            
            $body = wp_remote_retrieve_body($response);
            
            if (empty($body)) {
                $this->log_error('OpenRouter API returned empty response body');
                throw new Exception('OpenRouter API returned empty response');
            }
            
            $result = json_decode($body, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->log_error('OpenRouter API response JSON decode failed', array(
                    'json_error' => json_last_error_msg(),
                    'response_snippet' => substr($body, 0, 500)
                ));
                throw new Exception('Failed to decode OpenRouter API response: ' . json_last_error_msg());
            }
            
            // Check for API-specific errors
            if (isset($result['error'])) {
                $api_error = $result['error'];
                $error_message = isset($api_error['message']) ? $api_error['message'] : 'Unknown API error';
                $this->log_error('OpenRouter API returned error', array(
                    'error_type' => $api_error['type'] ?? 'unknown',
                    'error_message' => $error_message
                ));
                throw new Exception('OpenRouter API error: ' . $error_message);
            }
            
            // Validate response structure
            if (!isset($result['choices']) || !is_array($result['choices']) || empty($result['choices'])) {
                $this->log_error('OpenRouter API response missing choices', array(
                    'response_keys' => array_keys($result)
                ));
                throw new Exception('Invalid OpenRouter API response structure');
            }
            
            if (!isset($result['choices'][0]['message']['content'])) {
                $this->log_error('OpenRouter API response missing content', array(
                    'choices_count' => count($result['choices']),
                    'response_keys' => array_keys($result['choices'][0] ?? array())
                ));
                throw new Exception('OpenRouter API response missing content field');
            }
            
            $content = $result['choices'][0]['message']['content'];
            
            if (empty(trim($content))) {
                $this->log_error('OpenRouter API returned empty content');
                throw new Exception('OpenRouter API returned empty content');
            }
            
            $this->log_info('OpenRouter API request successful', array(
                'content_length' => strlen($content),
                'usage' => $result['usage'] ?? 'unknown'
            ));
            
            return $this->parse_ai_response($content, $article);
            
        } catch (Exception $e) {
            $this->log_error('OpenRouter API generation failed', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
            throw $e;
        }
    }
    
    /**
     * Generate content using custom API with error handling
     *
     * @param string $prompt AI prompt
     * @param array $article Original article
     * @return array Generated content
     * @throws Exception If custom API generation fails
     */
    private function generate_with_custom_api($prompt, $article) {
        try {
            $this->log_info('Using custom API (fallback mode)', array(
                'prompt_length' => strlen($prompt),
                'article_url' => $article['link'] ?? 'unknown'
            ));
            
            // This is a placeholder for custom API implementation
            // Users can modify this method to integrate with their preferred API
            
            $this->log_warning('Custom API not implemented yet - using fallback', array(
                'instructions' => 'Users should implement custom API integration in generate_with_custom_api method'
            ));
            
            // For now, return a fallback response
            $fallback_content = $this->generate_fallback_content($article);
            
            $this->log_info('Custom API fallback content generated', array(
                'title_length' => strlen('Breaking: ' . $article['title']),
                'content_length' => strlen($fallback_content)
            ));
            
            return array(
                'title' => 'Breaking: ' . $article['title'],
                'content' => $fallback_content,
                'source_url' => $article['link'],
                'source_domain' => $article['source_domain']
            );
            
        } catch (Exception $e) {
            $this->log_error('Custom API generation failed', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
            throw $e;
        }
    }
    
    /**
     * Parse AI response with comprehensive error handling
     *
     * @param string $response AI response
     * @param array $article Original article
     * @return array Parsed content
     * @throws Exception If response parsing fails
     */
    private function parse_ai_response($response, $article) {
        try {
            $this->log_info('Starting AI response parsing', array(
                'response_length' => strlen($response),
                'article_url' => $article['link'] ?? 'unknown'
            ));
            
            // Validate input parameters
            if (empty($response) || !is_string($response)) {
                $this->log_error('Invalid response for parsing');
                throw new Exception('Invalid AI response - empty or not a string');
            }
            
            // Security validation using existing security manager
            if ($this->security_manager) {
                try {
                    if ($this->security_manager && ($this->security_manager instanceof CP_Security_Manager || $this->security_manager instanceof AANP_Security_Manager) && !$this->security_manager->validate_api_response($response)) {
                        $this->log_warning('Suspicious content detected in AI response', array(
                            'response_snippet' => substr($response, 0, 200)
                        ));
                        throw new Exception('Suspicious content detected in AI response');
                    }
                } catch (Exception $e) {
                    $this->log_error('Security validation failed', array(
                        'error' => $e->getMessage()
                    ));
                    throw new Exception('Content security validation failed: ' . $e->getMessage());
                }
            } else {
                $this->log_warning('Security manager not available, skipping validation');
            }
            
            // Try to parse JSON response
            $json_data = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->log_warning('JSON parsing failed, trying manual extraction', array(
                    'json_error' => json_last_error_msg()
                ));
            }
            
            if ($json_data && isset($json_data['title']) && isset($json_data['content'])) {
                // Validate parsed data
                if (empty(trim($json_data['title'])) || empty(trim($json_data['content']))) {
                    $this->log_warning('Parsed JSON contains empty title or content');
                    throw new Exception('Parsed AI response contains empty content');
                }
                
                $result = array(
                    'title' => sanitize_text_field($json_data['title']),
                    'content' => wp_kses_post($json_data['content']),
                    'source_url' => $article['link'],
                    'source_domain' => $article['source_domain']
                );
                
                $this->log_info('JSON response parsed successfully', array(
                    'title_length' => strlen($result['title']),
                    'content_length' => strlen($result['content'])
                ));
                
                return $result;
            }
            
            $this->log_info('JSON parsing failed or invalid, trying manual extraction');
            
            // If JSON parsing fails, try to extract title and content manually
            return $this->extract_content_manually($response, $article);
            
        } catch (Exception $e) {
            $this->log_error('AI response parsing failed', array(
                'error' => $e->getMessage(),
                'response_snippet' => substr($response, 0, 500)
            ));
            
            // Return fallback content
            $this->log_info('Returning fallback content due to parsing failure');
            return array(
                'title' => 'Breaking: ' . $article['title'],
                'content' => $this->generate_fallback_content($article),
                'source_url' => $article['link'],
                'source_domain' => $article['source_domain']
            );
        }
    }
    
    /**
     * Extract content manually when JSON parsing fails
     *
     * @param string $response AI response text
     * @param array $article Original article data
     * @return array Extracted content with title and content
     * @throws Exception If manual extraction fails
     */
    private function extract_content_manually($response, $article) {
        $lines = explode("\n", $response);
        $title = '';
        $content = '';
        $parsing_method = 'unknown';
        
        try {
            // Method 1: Look for structured patterns
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                
                // Look for title patterns
                if (preg_match('/^(title|headline):\s*(.+)$/i', $line, $matches)) {
                    $title = $matches[2];
                    $parsing_method = 'pattern_match';
                    break;
                }
            }
            
            // Method 2: If no pattern found, use first short line as title
            if (empty($title)) {
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;
                    
                    if (strlen($line) < 100 && strlen($line) > 5) {
                        $title = $line;
                        $parsing_method = 'first_short_line';
                        break;
                    }
                }
            }
            
            // Extract content (everything after title or all content if no title found)
            $found_title = false;
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) {
                    $content .= "\n";
                    continue;
                }
                
                if (!$found_title && !empty($title)) {
                    if (strpos($line, $title) !== false) {
                        $found_title = true;
                        continue;
                    }
                }
                
                $content .= $line . "\n\n";
            }
            
            $this->log_info('Manual content extraction completed', array(
                'method' => $parsing_method,
                'title_length' => strlen($title),
                'content_length' => strlen($content)
            ));
            
            // If still no valid content, use fallback
            if (empty($title) || empty(trim($content))) {
                $this->log_warning('Manual extraction failed, using fallback');
                return array(
                    'title' => 'Breaking: ' . $article['title'],
                    'content' => $this->generate_fallback_content($article),
                    'source_url' => $article['link'],
                    'source_domain' => $article['source_domain']
                );
            }
            
            return array(
                'title' => sanitize_text_field($title),
                'content' => wp_kses_post(trim($content)),
                'source_url' => $article['link'],
                'source_domain' => $article['source_domain']
            );
            
        } catch (Exception $e) {
            $this->log_error('Manual content extraction failed', array(
                'error' => $e->getMessage(),
                'method' => $parsing_method
            ));
            
            // Final fallback
            return array(
                'title' => 'Breaking: ' . $article['title'],
                'content' => $this->generate_fallback_content($article),
                'source_url' => $article['link'],
                'source_domain' => $article['source_domain']
            );
        }
    }
    
    /**
     * Generate fallback content when AI fails with sanitization
     *
     * @param array $article Original article data
     * @return string Fallback content with proper HTML structure
     * @throws Exception If fallback content generation fails
     */
    private function generate_fallback_content($article) {
        try {
            // Validate and sanitize input
            $title = isset($article['title']) ? sanitize_text_field($article['title']) : 'Unknown Article';
            $description = isset($article['description']) ? sanitize_textarea_field($article['description']) : 'No description available.';
            $link = isset($article['link']) ? esc_url_raw($article['link']) : '#';
            $source_domain = isset($article['source_domain']) ? sanitize_text_field($article['source_domain']) : 'Unknown Source';
            
            // Escape all variables for HTML output
            $title_escaped = esc_html($title);
            $description_escaped = esc_html($description);
            $link_escaped = esc_url($link);
            $source_domain_escaped = esc_html($source_domain);
            
            $content = "<p>In recent news, {$title_escaped} has been making headlines.</p>\n\n";
            $content .= "<p>{$description_escaped}</p>\n\n";
            $content .= "<p>This developing story continues to unfold, and we will provide updates as more information becomes available.</p>\n\n";
            $content .= "<p>For more details, you can read the original article at <a href=\"{$link_escaped}\" target=\"_blank\" rel=\"noopener\">{$source_domain_escaped}</a>.</p>";
            
            $this->log_info('Fallback content generated successfully', array(
                'title_length' => strlen($title_escaped),
                'content_length' => strlen($content)
            ));
            
            return $content;
            
        } catch (Exception $e) {
            $this->log_error('Failed to generate fallback content', array(
                'error' => $e->getMessage(),
                'article_keys' => array_keys($article)
            ));
            
            // Return minimal safe fallback
            return '<p>Content temporarily unavailable. Please check back later.</p>';
        }
    }
    
    /**
     * Test API connection with comprehensive error handling
     *
     * @return array Test result
     */
    public function test_api_connection() {
        try {
            $this->log_info('Starting API connection test', array(
                'provider' => $this->provider
            ));
            
            if (empty($this->api_key)) {
                $this->log_warning('API connection test failed - no API key configured');
                return array(
                    'status' => 'error',
                    'message' => 'API key not configured'
                );
            }
            
            $test_article = array(
                'title' => 'Test Article',
                'description' => 'This is a test article for API connection.',
                'link' => 'https://example.com',
                'source_domain' => 'example.com'
            );
            
            // Set shorter timeout for testing
            $original_timeout = $this->timeout;
            $this->timeout = 30;
            
            $result = $this->generate_content($test_article);
            
            // Restore original timeout
            $this->timeout = $original_timeout;
            
            if ($result && !empty($result['title']) && !empty($result['content'])) {
                $this->log_info('API connection test successful', array(
                    'provider' => $this->provider,
                    'response_title' => strlen($result['title']),
                    'response_content' => strlen($result['content'])
                ));
                return array(
                    'status' => 'success',
                    'message' => 'API connection successful'
                );
            } else {
                $this->log_warning('API connection test failed - invalid response', array(
                    'provider' => $this->provider,
                    'result_empty' => empty($result)
                ));
                return array(
                    'status' => 'error',
                    'message' => 'API connection failed - invalid response'
                );
            }
            
        } catch (Exception $e) {
            $this->log_error('API connection test failed with exception', array(
                'error' => $e->getMessage(),
                'provider' => $this->provider,
                'trace' => $e->getTraceAsString()
            ));
            return array(
                'status' => 'error',
                'message' => 'API connection test failed: ' . $e->getMessage()
            );
        }
    }
}