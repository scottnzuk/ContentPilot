<?php
/**
 * AI Generation Service for Microservices Architecture
 *
 * Handles AI-powered content generation using multiple providers,
 * with advanced caching, rate limiting, and content optimization.
 *
 * @package AI_Auto_News_Poster\Services
 * @since 1.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI Generation Service Class
 */
class AANP_AIGenerationService {
    
    /**
     * AI Generator Context (existing strategy pattern)
     * @var AANP_AI_Generator_Context
     */
    private $ai_generator;
    
    /**
     * Cache manager instance
     * @var AANP_AdvancedCacheManager
     */
    private $cache_manager;
    
    /**
     * Logger instance
     * @var AANP_Logger
     */
    private $logger;
    
    /**
     * Rate limiter instance
     * @var AANP_Rate_Limiter
     */
    private $rate_limiter;
    
    /**
     * Performance metrics
     * @var array
     */
    private $metrics = array();
    
    /**
     * Service configuration
     * @var array
     */
    private $config = array();
    
    /**
     * Content templates
     * @var array
     */
    private $templates = array();
    
    /**
     * Constructor
     *
     * @param AANP_NewsFetchService $news_fetch_service
     * @param AANP_AdvancedCacheManager $cache_manager
     */
    public function __construct(
        AANP_NewsFetchService $news_fetch_service,
        AANP_AdvancedCacheManager $cache_manager = null
    ) {
        $this->logger = AANP_Logger::getInstance();
        $this->cache_manager = $cache_manager ?: new AANP_AdvancedCacheManager();
        
        // Initialize existing AI generator with strategy pattern
        if (class_exists('AANP_AI_Generator_Context')) {
            $this->ai_generator = new AANP_AI_Generator_Context();
        }
        
        // Initialize rate limiter
        if (class_exists('AANP_Rate_Limiter')) {
            $this->rate_limiter = new AANP_Rate_Limiter();
        }
        
        $this->init_config();
        $this->init_templates();
    }
    
    /**
     * Initialize service configuration
     */
    private function init_config() {
        $options = get_option('aanp_settings', array());
        
        $this->config = array(
            'default_provider' => isset($options['llm_provider']) ? $options['llm_provider'] : 'openai',
            'word_count_target' => isset($options['word_count']) ? $options['word_count'] : 'medium',
            'tone' => isset($options['tone']) ? $options['tone'] : 'neutral',
            'temperature' => isset($options['temperature']) ? floatval($options['temperature']) : 0.7,
            'max_tokens' => isset($options['max_tokens']) ? intval($options['max_tokens']) : 2000,
            'retry_attempts' => 3,
            'timeout' => 60,
            'enable_caching' => true,
            'cache_duration' => 86400, // 24 hours
            'enhance_prompts' => true,
            'seo_optimization' => true,
            'readability_target' => 'good' // poor, fair, good, excellent
        );
        
        // Word count targets
        $word_count_map = array(
            'short' => 300,
            'medium' => 800,
            'long' => 1500
        );
        
        $this->config['target_word_count'] = $word_count_map[$this->config['word_count_target']] ?? 800;
    }
    
    /**
     * Initialize content templates
     */
    private function init_templates() {
        $this->templates = array(
            'blog_post' => array(
                'title' => 'Comprehensive analysis: {topic}',
                'structure' => array(
                    'introduction' => 'Introduction about {topic}',
                    'main_content' => 'Detailed analysis of {topic}',
                    'conclusion' => 'Summary and implications of {topic}',
                    'call_to_action' => 'Engagement prompt about {topic}'
                ),
                'tone' => 'professional',
                'seo_focus' => true
            ),
            'news_article' => array(
                'title' => '{headline}: Key insights and analysis',
                'structure' => array(
                    'lead' => 'Breaking down the latest on {topic}',
                    'details' => 'Key facts and analysis about {topic}',
                    'impact' => 'What this means for stakeholders',
                    'follow_up' => 'What to watch next on {topic}'
                ),
                'tone' => 'informative',
                'seo_focus' => true
            ),
            'opinion_piece' => array(
                'title' => 'Opinion: Understanding {topic} implications',
                'structure' => array(
                    'stance' => 'Position on {topic}',
                    'evidence' => 'Supporting arguments about {topic}',
                    'counterargument' => 'Acknowledging other perspectives',
                    'final_position' => 'Strong conclusion on {topic}'
                ),
                'tone' => 'thoughtful',
                'seo_focus' => false
            )
        );
    }
    
    /**
     * Generate content from news items
     *
     * @param array $news_items News items to generate content from
     * @param array $parameters Generation parameters
     * @return array Generation results
     */
    public function generate_content($parameters = array()) {
        $start_time = microtime(true);

        try {
            // Default parameters
            $params = array_merge(array(
                'content_type' => 'blog_post',
                'provider' => $this->config['default_provider'],
                'word_count' => $this->config['target_word_count'],
                'tone' => $this->config['tone'],
                'seo_optimize' => true,
                'readability_target' => $this->config['readability_target'],
                'batch_mode' => false,
                'news_items' => array()
            ), $parameters);

            $this->logger->info('Starting AI content generation', array(
                'content_type' => $params['content_type'],
                'provider' => $params['provider'],
                'news_items_count' => count($params['news_items']),
                'word_count_target' => $params['word_count']
            ));

            // Check rate limiting
            if (AANP_Rate_Limiter::getInstance()->is_rate_limited('ai_generation')) {
                AANP_Error_Handler::getInstance()->handle_error(
                    'Rate limit exceeded for AI generation service',
                    ['endpoint' => 'ai_generation', 'ip' => $this->get_client_ip()],
                    'rate_limiting'
                );
                throw new Exception('Rate limit exceeded for AI generation');
            }
            
            // Check if AI generator is available
            if (!$this->ai_generator) {
                throw new Exception('AI generator not available');
            }
            
            // Validate provider
            if (!$this->ai_generator->set_strategy($params['provider'])) {
                throw new Exception("AI provider '{$params['provider']}' not available or not configured");
            }
            
            $generated_content = array();
            
            if ($params['batch_mode']) {
                // Batch processing mode
                $generated_content = $this->process_batch_content($params['news_items'], $params);
            } else {
                // Single content generation
                $news_item = !empty($params['news_items']) ? $params['news_items'][0] : null;
                if (!$news_item) {
                    throw new Exception('No news items provided for content generation');
                }
                
                $generated_content = $this->generate_single_content($news_item, $params);
            }
            
            $execution_time = (microtime(true) - $start_time) * 1000;
            
            // Record rate limit attempt
            if ($this->rate_limiter) {
                $this->rate_limiter->record_attempt('ai_generation', 3600);
            }
            
            // Update metrics
            $this->update_metrics('generate_content', true, $execution_time, count($generated_content));
            
            $response = array(
                'success' => true,
                'content' => $generated_content,
                'execution_time_ms' => $execution_time,
                'provider_used' => $params['provider'],
                'word_count_target' => $params['word_count'],
                'seo_optimized' => $params['seo_optimize'],
                'timestamp' => current_time('Y-m-d H:i:s')
            );
            
            $this->logger->info('AI content generation completed successfully', array(
                'items_generated' => count($generated_content),
                'execution_time_ms' => $execution_time,
                'provider' => $params['provider']
            ));
            
            return $response;
            
        } catch (Exception $e) {
            $execution_time = (microtime(true) - $start_time) * 1000;
            
            // Update failure metrics
            $this->update_metrics('generate_content', false, $execution_time, 0, $e->getMessage());
            
            $this->logger->error('AI content generation failed', array(
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
     * Generate single content piece
     *
     * @param array $news_item News item
     * @param array $params Parameters
     * @return array Generated content
     */
    private function generate_single_content($news_item, $params) {
        // Generate enhanced prompt
        $prompt = $this->create_enhanced_prompt($news_item, $params);
        
        // Check cache
        $cache_key = 'ai_content_' . md5($prompt . serialize($params));
        if ($this->config['enable_caching']) {
            $cached_content = $this->cache_manager->get($cache_key);
            if ($cached_content !== false) {
                $this->logger->debug('Returning cached AI content', array('cache_key' => $cache_key));
                return $cached_content;
            }
        }
        
        // Generate content
        $options = array(
            'max_tokens' => $params['word_count'] * 1.5, // Allow some buffer
            'temperature' => $this->config['temperature'],
            'top_p' => 1,
            'frequency_penalty' => 0,
            'presence_penalty' => 0
        );
        
        $result = $this->ai_generator->generate_content($prompt, $options);
        
        if (!isset($result['content'])) {
            throw new Exception('AI generation returned invalid result');
        }
        
        $content = array(
            'title' => $this->extract_title($result['content'], $news_item),
            'content' => $result['content'],
            'excerpt' => $this->create_excerpt($result['content']),
            'meta_description' => $this->create_meta_description($result['content'], $news_item),
            'word_count' => str_word_count(strip_tags($result['content'])),
            'reading_time' => ceil(str_word_count(strip_tags($result['content'])) / 200), // 200 WPM average
            'seo_score' => $params['seo_optimize'] ? $this->calculate_seo_score($result['content'], $news_item) : 0,
            'readability_score' => $this->calculate_readability_score($result['content']),
            'provider_used' => $params['provider'],
            'generated_at' => current_time('Y-m-d H:i:s'),
            'source_news_item' => $news_item
        );
        
        // Cache the result
        if ($this->config['enable_caching']) {
            $this->cache_manager->set($cache_key, $content, $this->config['cache_duration']);
        }
        
        return $content;
    }
    
    /**
     * Process content in batch
     *
     * @param array $news_items News items
     * @param array $params Parameters
     * @return array Generated contents
     */
    private function process_batch_content($news_items, $params) {
        $batch_results = array();
        $batch_size = 5; // Process in smaller batches to avoid overwhelming AI API
        
        for ($i = 0; $i < count($news_items); $i += $batch_size) {
            $batch = array_slice($news_items, $i, $batch_size);
            
            foreach ($batch as $news_item) {
                try {
                    $content = $this->generate_single_content($news_item, $params);
                    $batch_results[] = $content;
                    
                } catch (Exception $e) {
                    $this->logger->warning('Failed to generate content for news item', array(
                        'news_title' => $news_item['title'] ?? 'Unknown',
                        'error' => $e->getMessage()
                    ));
                    
                    // Add error entry instead of failing the entire batch
                    $batch_results[] = array(
                        'error' => true,
                        'error_message' => $e->getMessage(),
                        'news_item' => $news_item,
                        'generated_at' => current_time('Y-m-d H:i:s')
                    );
                }
            }
            
            // Brief pause between items in batch
            if (count($batch) >= $batch_size) {
                usleep(1000000); // 1 second pause between batches
            }
        }
        
        return $batch_results;
    }
    
    /**
     * Create enhanced prompt from news item
     *
     * @param array $news_item News item
     * @param array $params Parameters
     * @return string Enhanced prompt
     */
    private function create_enhanced_prompt($news_item, $params) {
        $template = $this->templates[$params['content_type']] ?? $this->templates['blog_post'];
        
        // Extract key information
        $title = $news_item['title'];
        $description = $news_item['description'];
        $topic = $this->extract_topic_from_content($title . ' ' . $description);
        
        // Create base prompt
        $prompt_parts = array();
        
        // Content requirements
        $prompt_parts[] = "Create a {$params['word_count']}-word {$params['content_type']} with a {$params['tone']} tone.";
        $prompt_parts[] = "Topic: {$topic}";
        $prompt_parts[] = "Source material: {$title}";
        $prompt_parts[] = "Additional context: " . substr($description, 0, 300) . "...";
        
        // Structure requirements
        if (isset($template['structure'])) {
            $prompt_parts[] = "Structure the content as follows:";
            foreach ($template['structure'] as $section => $description) {
                $section_template = str_replace('{topic}', $topic, $description);
                $prompt_parts[] = "- {$section}: {$section_template}";
            }
        }
        
        // SEO optimization
        if ($params['seo_optimize'] && $template['seo_focus']) {
            $prompt_parts[] = "SEO Requirements:";
            $prompt_parts[] = "- Include relevant keywords naturally throughout the content";
            $prompt_parts[] = "- Create an engaging meta description (150-160 characters)";
            $prompt_parts[] = "- Use proper headings (H1, H2, H3)";
            $prompt_parts[] = "- Include internal linking opportunities";
        }
        
        // Readability requirements
        $prompt_parts[] = "Write for {$params['readability_target']} readability level.";
        $prompt_parts[] = "Use clear, engaging language appropriate for a general audience.";
        
        // Quality requirements
        $prompt_parts[] = "Ensure accuracy, originality, and value for readers.";
        $prompt_parts[] = "Include actionable insights or takeaways where appropriate.";
        
        $base_prompt = implode("\n\n", $prompt_parts);
        
        // Add provider-specific enhancements
        if ($this->config['enhance_prompts']) {
            $enhanced_prompt = $this->enhance_prompt_for_provider($base_prompt, $params['provider']);
        } else {
            $enhanced_prompt = $base_prompt;
        }
        
        return $enhanced_prompt;
    }
    
    /**
     * Enhance prompt for specific AI provider
     *
     * @param string $base_prompt Base prompt
     * @param string $provider AI provider
     * @return string Enhanced prompt
     */
    private function enhance_prompt_for_provider($base_prompt, $provider) {
        switch (strtolower($provider)) {
            case 'openai':
                return $base_prompt . "\n\n" . "Additional guidance: Focus on creating engaging, well-structured content that provides clear value to readers. Use storytelling elements and examples where appropriate.";
                
            case 'anthropic':
                return $base_prompt . "\n\n" . "Additional guidance: Emphasize factual accuracy and provide balanced perspectives. Consider multiple viewpoints and acknowledge nuances in the topic.";
                
            case 'openrouter':
                return $base_prompt . "\n\n" . "Additional guidance: Create content that is both informative and accessible. Use clear transitions and logical flow between ideas.";
                
            default:
                return $base_prompt;
        }
    }
    
    /**
     * Extract title from generated content
     *
     * @param string $content Generated content
     * @param array $news_item Original news item
     * @return string Extracted or generated title
     */
    private function extract_title($content, $news_item) {
        // Try to find title in content
        $lines = explode("\n", $content);
        
        // Look for a short, title-like line at the beginning
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line) && strlen($line) < 100 && strpos($line, ':') !== false) {
                return $line;
            }
        }
        
        // If no good title found, create one from news item
        $topic = $this->extract_topic_from_content($news_item['title'] . ' ' . $news_item['description']);
        return "Analysis: {$topic}";
    }
    
    /**
     * Create excerpt from content
     *
     * @param string $content Generated content
     * @return string Excerpt
     */
    private function create_excerpt($content) {
        $clean_content = strip_tags($content);
        $clean_content = preg_replace('/\s+/', ' ', $clean_content);
        
        // Get first 160 characters for excerpt
        if (strlen($clean_content) > 160) {
            $excerpt = substr($clean_content, 0, 160);
            $last_space = strrpos($excerpt, ' ');
            if ($last_space !== false) {
                $excerpt = substr($excerpt, 0, $last_space);
            }
            return $excerpt . '...';
        }
        
        return $clean_content;
    }
    
    /**
     * Create meta description
     *
     * @param string $content Generated content
     * @param array $news_item News item
     * @return string Meta description
     */
    private function create_meta_description($content, $news_item) {
        $excerpt = $this->create_excerpt($content);
        
        // Ensure meta description is within Google guidelines (150-160 characters)
        if (strlen($excerpt) > 160) {
            $excerpt = substr($excerpt, 0, 157) . '...';
        }
        
        return $excerpt;
    }
    
    /**
     * Calculate SEO score for content
     *
     * @param string $content Generated content
     * @param array $news_item News item
     * @return int SEO score (0-100)
     */
    private function calculate_seo_score($content, $news_item) {
        $score = 0;
        $max_score = 100;
        
        // Basic SEO factors
        $word_count = str_word_count(strip_tags($content));
        
        // Word count score (20 points)
        if ($word_count >= 300) $score += 15;
        if ($word_count >= 600) $score += 5;
        
        // Heading structure (15 points)
        if (preg_match('/<h[1-6][^>]*>/i', $content)) $score += 10;
        if (preg_match('/<h1[^>]*>/i', $content)) $score += 5;
        
        // Link structure (10 points)
        if (preg_match('/<a[^>]+href=/i', $content)) $score += 10;
        
        // Paragraph structure (10 points)
        if (preg_match_all('/<p[^>]*>/i', $content) >= 3) $score += 10;
        
        // Content quality indicators (45 points)
        $content_lower = strtolower($content);
        $quality_words = array('analysis', 'important', 'significant', 'according', 'research', 'study');
        $quality_matches = 0;
        
        foreach ($quality_words as $word) {
            if (strpos($content_lower, $word) !== false) {
                $quality_matches++;
            }
        }
        
        $score += min(25, $quality_matches * 5);
        
        // Readability (20 points)
        $readability = $this->calculate_readability_score($content);
        if ($readability >= 60) $score += 15;
        if ($readability >= 80) $score += 5;
        
        return min($score, $max_score);
    }
    
    /**
     * Calculate readability score
     *
     * @param string $content Content to analyze
     * @return int Readability score (0-100)
     */
    private function calculate_readability_score($content) {
        // Simplified Flesch Reading Ease calculation
        $text = strip_tags($content);
        $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $sentence_count = count($sentences);
        
        if ($sentence_count === 0) return 0;
        
        $words = preg_split('/\s+/', $text);
        $word_count = count($words);
        
        if ($word_count === 0) return 0;
        
        $syllable_count = 0;
        foreach ($words as $word) {
            $syllable_count += $this->count_syllables($word);
        }
        
        // Flesch Reading Ease formula
        $avg_sentence_length = $word_count / $sentence_count;
        $avg_syllables_per_word = $syllable_count / $word_count;
        
        $score = 206.835 - (1.015 * $avg_sentence_length) - (84.6 * $avg_syllables_per_word);
        
        // Normalize to 0-100 scale
        return max(0, min(100, $score));
    }
    
    /**
     * Count syllables in a word (simplified)
     *
     * @param string $word Word to count syllables
     * @return int Syllable count
     */
    private function count_syllables($word) {
        $word = strtolower($word);
        $word = preg_replace('/[^a-z]/', '', $word);
        
        if (strlen($word) <= 3) return 1;
        
        $word = preg_replace('/(?:[^laeiouy]es|ed|[^laeiouy]e)$/', '', $word);
        $word = preg_replace('/^y/', '', $word);
        
        $syllables = preg_match_all('/[aeiouy]{1,2}/', $word);
        
        return max(1, $syllables);
    }
    
    /**
     * Extract topic from content
     *
     * @param string $content Content to analyze
     * @return string Extracted topic
     */
    private function extract_topic_from_content($content) {
        // Simple keyword extraction
        $words = preg_split('/\s+/', strtolower($content));
        $stop_words = array('the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'must', 'can', 'this', 'that', 'these', 'those', 'a', 'an', 'as', 'it', 'its', 'they', 'them', 'their', 'he', 'him', 'his', 'she', 'her', 'hers', 'we', 'us', 'our', 'ours', 'you', 'your', 'yours');
        
        $filtered_words = array();
        foreach ($words as $word) {
            $word = preg_replace('/[^a-zA-Z]/', '', $word);
            if (strlen($word) > 2 && !in_array($word, $stop_words)) {
                $filtered_words[] = $word;
            }
        }
        
        // Count word frequency
        $word_count = array();
        foreach ($filtered_words as $word) {
            $word_count[$word] = isset($word_count[$word]) ? $word_count[$word] + 1 : 1;
        }
        
        // Sort by frequency
        arsort($word_count);
        
        // Take top 3 words as topic
        $top_words = array_slice(array_keys($word_count), 0, 3);
        
        return implode(' ', $top_words);
    }
    
    /**
     * Update service metrics
     *
     * @param string $operation Operation name
     * @param bool $success Success status
     * @param float $execution_time Execution time in milliseconds
     * @param int $items_processed Number of items processed
     * @param string $error Error message if failed
     */
    private function update_metrics($operation, $success, $execution_time, $items_processed, $error = '') {
        if (!isset($this->metrics[$operation])) {
            $this->metrics[$operation] = array(
                'total_calls' => 0,
                'successful_calls' => 0,
                'failed_calls' => 0,
                'total_execution_time' => 0,
                'average_execution_time' => 0,
                'total_items_processed' => 0,
                'last_call' => null
            );
        }
        
        $metric = &$this->metrics[$operation];
        
        $metric['total_calls']++;
        $metric['total_execution_time'] += $execution_time;
        $metric['average_execution_time'] = $metric['total_execution_time'] / $metric['total_calls'];
        $metric['total_items_processed'] += $items_processed;
        $metric['last_call'] = array(
            'success' => $success,
            'execution_time' => $execution_time,
            'items_processed' => $items_processed,
            'error' => $error,
            'timestamp' => current_time('Y-m-d H:i:s')
        );
        
        if ($success) {
            $metric['successful_calls']++;
        } else {
            $metric['failed_calls']++;
        }
    }
    
    /**
     * Get available AI providers
     *
     * @return array Available providers
     */
    public function get_available_providers() {
        if ($this->ai_generator) {
            return $this->ai_generator->get_available_providers();
        }
        
        return array();
    }
    
    /**
     * Get service metrics
     *
     * @return array Service metrics
     */
    public function get_metrics() {
        return array(
            'service' => 'AIGenerationService',
            'metrics' => $this->metrics,
            'config' => $this->config,
            'available_providers' => $this->get_available_providers(),
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
            // Test AI generator availability
            if (!$this->ai_generator) {
                return false;
            }
            
            // Test provider availability
            $providers = $this->get_available_providers();
            if (empty($providers)) {
                return false;
            }
            
            // Test cache functionality
            $test_key = 'ai_health_check_' . time();
            $test_data = array('test' => 'value');
            $this->cache_manager->set($test_key, $test_data, 60);
            $retrieved = $this->cache_manager->get($test_key);
            
            if ($retrieved !== $test_data) {
                return false;
            }
            
            // Clean up test data
            $this->cache_manager->delete($test_key);
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('AIGenerationService health check failed', array(
                'error' => $e->getMessage()
            ));
            return false;
        }
    }
    
    /**
     * Process batch content generation (for workflow orchestrator)
     *
     * @param array $parameters Batch parameters
     * @return array Processing result
     */
    public function process_batch($parameters = array()) {
        $params = array_merge(array(
            'news_items' => array(),
            'batch_mode' => true,
            'content_type' => 'blog_post'
        ), $parameters);
        
        return $this->generate_content($params);
    }
    
    /**
     * Clean up service resources
     */
    public function cleanup() {
        try {
            // Clear AI generation cache
            $this->cache_manager->delete_by_pattern('ai_content_');
            
            $this->logger->info('AIGenerationService cleanup completed');
            
        } catch (Exception $e) {
            $this->logger->error('AIGenerationService cleanup failed', array(
                'error' => $e->getMessage()
            ));
        }
    }

    /**
     * Get client IP address for rate limiting
     */
    private function get_client_ip() {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];

        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }
}