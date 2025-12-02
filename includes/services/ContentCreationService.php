<?php
/**
 * Content Creation Service for Microservices Architecture
 *
 * Handles WordPress post creation, scheduling, metadata management,
 * and integration with existing post creation functionality.
 *
 * @package AI_Auto_News_Poster\Services
 * @since 1.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Content Creation Service Class
 */
class AANP_ContentCreationService {
    
    /**
     * Existing post creator instance
     * @var AANP_Post_Creator
     */
    private $post_creator;
    
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
     * RankMath integration instance
     * @var AANP_RankMathIntegration
     */
    private $rankmath_integration;
    
    /**
     * RankMath auto-optimizer instance
     * @var AANP_RankMathAutoOptimizer
     */
    private $rankmath_auto_optimizer;
    
    /**
     * RankMath SEO analyzer instance
     * @var AANP_RankMathSEOAnalyzer
     */
    private $rankmath_seo_analyzer;
    
    /**
     * Content analyzer instance
     * @var AANP_ContentAnalyzer
     */
    private $content_analyzer;
    
    /**
     * EEAT optimizer instance
     * @var AANP_EEATOptimizer
     */
    private $eeat_optimizer;
    
    /**
     * Humanizer manager instance
     * @var AANP_HumanizerManager
     */
    private $humanizer_manager;
    
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
     * Post templates for different content types
     * @var array
     */
    private $post_templates = array();
    
    /**
     * Constructor
     *
     * @param AANP_AIGenerationService $ai_generation_service
     * @param AANP_AdvancedCacheManager $cache_manager
     * @param AANP_RankMathIntegration $rankmath_integration
     * @param AANP_RankMathAutoOptimizer $rankmath_auto_optimizer
     * @param AANP_RankMathSEOAnalyzer $rankmath_seo_analyzer
     * @param AANP_HumanizerManager $humanizer_manager
     */
    public function __construct(
        AANP_AIGenerationService $ai_generation_service,
        AANP_AdvancedCacheManager $cache_manager = null,
        AANP_RankMathIntegration $rankmath_integration = null,
        AANP_RankMathAutoOptimizer $rankmath_auto_optimizer = null,
        AANP_RankMathSEOAnalyzer $rankmath_seo_analyzer = null,
        AANP_HumanizerManager $humanizer_manager = null
    ) {
        $this->logger = AANP_Logger::getInstance();
        $this->cache_manager = $cache_manager ?: new AANP_AdvancedCacheManager();
        
        // Initialize existing post creator
        if (class_exists('AANP_Post_Creator')) {
            $this->post_creator = new AANP_Post_Creator();
        }
        
        // Initialize rate limiter
        if (class_exists('AANP_Rate_Limiter')) {
            $this->rate_limiter = new AANP_Rate_Limiter();
        }
        
        // Initialize RankMath SEO services
        $this->rankmath_integration = $rankmath_integration ?: new AANP_RankMathIntegration($this->cache_manager);
        $this->rankmath_auto_optimizer = $rankmath_auto_optimizer ?: new AANP_RankMathAutoOptimizer(
            $this->rankmath_integration,
            null,
            $this->cache_manager
        );
        $this->rankmath_seo_analyzer = $rankmath_seo_analyzer ?: new AANP_RankMathSEOAnalyzer(
            $this->rankmath_integration,
            $this->rankmath_auto_optimizer,
            null,
            $this->cache_manager
        );
        
        // Initialize supporting SEO services
        if (class_exists('AANP_ContentAnalyzer')) {
            $this->content_analyzer = new AANP_ContentAnalyzer($this->cache_manager);
        }
        
        if (class_exists('AANP_EEATOptimizer')) {
            $this->eeat_optimizer = new AANP_EEATOptimizer($this->content_analyzer, $this->cache_manager);
        }
        
        // Initialize humanizer manager
        $this->humanizer_manager = $humanizer_manager ?: new AANP_HumanizerManager($this->cache_manager);
        
        $this->init_config();
        $this->init_post_templates();
        $this->init_hooks();
    }
    
    /**
     * Initialize service configuration
     */
    private function init_config() {
        $options = get_option('aanp_settings', array());
        
        $this->config = array(
            'default_post_status' => isset($options['default_post_status']) ? $options['default_post_status'] : 'draft',
            'default_post_author' => isset($options['default_post_author']) ? intval($options['default_post_author']) : get_current_user_id(),
            'featured_image_enabled' => isset($options['featured_image_enabled']) ? (bool) $options['featured_image_enabled'] : false,
            'auto_categorization' => isset($options['auto_categorization']) ? (bool) $options['auto_categorization'] : true,
            'auto_tagging' => isset($options['auto_tagging']) ? (bool) $options['auto_tagging'] : true,
            'seo_optimization' => isset($options['seo_optimization']) ? (bool) $options['seo_optimization'] : true,
            'rankmath_integration' => isset($options['rankmath_integration']) ? (bool) $options['rankmath_integration'] : true,
            'auto_rankmath_optimization' => isset($options['auto_rankmath_optimization']) ? (bool) $options['auto_rankmath_optimization'] : true,
            'seo_score_monitoring' => isset($options['seo_score_monitoring']) ? (bool) $options['seo_score_monitoring'] : true,
            'real_time_seo_analysis' => isset($options['real_time_seo_analysis']) ? (bool) $options['real_time_seo_analysis'] : true,
            'eeat_optimization' => isset($options['eeat_optimization']) ? (bool) $options['eeat_optimization'] : true,
            'social_sharing' => isset($options['social_sharing']) ? (bool) $options['social_sharing'] : false,
            'duplicate_detection' => isset($options['duplicate_detection']) ? (bool) $options['duplicate_detection'] : true,
            'humanization_enabled' => isset($options['humanizer_enabled']) ? (bool) $options['humanizer_enabled'] : false,
            'humanization_strength' => isset($options['humanizer_strength']) ? $options['humanizer_strength'] : 'medium',
            'max_posts_per_day' => isset($options['max_posts_per_day']) ? intval($options['max_posts_per_day']) : 10,
            'queue_processing' => true,
            'async_processing' => true,
            'target_seo_score' => isset($options['target_seo_score']) ? intval($options['target_seo_score']) : 85,
            'auto_optimization_level' => isset($options['auto_optimization_level']) ? $options['auto_optimization_level'] : 'aggressive'
        );
        
        // Get categories if auto categorization is enabled
        if ($this->config['auto_categorization']) {
            $this->config['default_categories'] = isset($options['categories']) ? $options['categories'] : array();
        }
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('aanp_create_post_from_content', array($this, 'create_post_from_content'));
        add_action('aanp_batch_create_posts', array($this, 'batch_create_posts'));
        add_action('aanp_process_post_queue', array($this, 'process_post_queue'));
        
        // Schedule queue processing if enabled
        if ($this->config['queue_processing']) {
            add_action('init', array($this, 'schedule_queue_processing'));
        }
    }
    
    /**
     * Schedule queue processing
     */
    public function schedule_queue_processing() {
        if (!wp_next_scheduled('aanp_process_post_queue')) {
            wp_schedule_event(time(), 'every_5_minutes', 'aanp_process_post_queue');
        }
    }
    
    /**
     * Initialize post templates
     */
    private function init_post_templates() {
        $this->post_templates = array(
            'standard' => array(
                'template' => '{content}',
                'featured_image' => true,
                'meta_description' => true,
                'social_sharing' => false,
                'custom_fields' => array()
            ),
            'news' => array(
                'template' => '<div class="news-article"><h2>Latest News</h2>{content}<div class="source-reference">Source: {source}</div></div>',
                'featured_image' => true,
                'meta_description' => true,
                'social_sharing' => true,
                'custom_fields' => array(
                    'news_source' => '{source}',
                    'news_url' => '{url}',
                    'published_date' => '{date}'
                )
            ),
            'analysis' => array(
                'template' => '<div class="analysis-article"><div class="analysis-header"><h1>{title}</h1></div>{content}<div class="analysis-footer"><p>Analysis completed on {date}</p></div></div>',
                'featured_image' => true,
                'meta_description' => true,
                'social_sharing' => true,
                'custom_fields' => array(
                    'analysis_type' => 'automated',
                    'word_count' => '{word_count}',
                    'readability_score' => '{readability}'
                )
            )
        );
    }
    
    /**
     * Create post from generated content
     *
     * @param array $content_data Generated content data
     * @param array $parameters Creation parameters
     * @return array Creation result
     */
    public function create_post($parameters = array()) {
        $start_time = microtime(true);
        
        try {
            // Default parameters
            $params = array_merge(array(
                'content_data' => array(),
                'post_template' => 'standard',
                'schedule_post' => false,
                'schedule_time' => null,
                'seo_optimize' => true,
                'featured_image' => true,
                'social_share' => false,
                'create_draft' => ($this->config['default_post_status'] === 'draft')
            ), $parameters);
            
            if (empty($params['content_data'])) {
                throw new Exception('No content data provided for post creation');
            }
            
            $content_data = $params['content_data'];
            
            $this->logger->info('Starting post creation', array(
                'title' => $content_data['title'] ?? 'Untitled',
                'template' => $params['post_template'],
                'word_count' => $content_data['word_count'] ?? 0,
                'scheduled' => $params['schedule_post']
            ));
            
            // Check rate limiting
            if ($this->rate_limiter && $this->rate_limiter->is_rate_limited('post_creation', 5, 3600)) {
                throw new Exception('Rate limit exceeded for post creation');
            }
            
            // Check daily post limit
            if (!$this->check_daily_post_limit()) {
                throw new Exception('Daily post creation limit exceeded');
            }
            
            // Check for duplicate content
            if ($this->config['duplicate_detection']) {
                $duplicate_check = $this->check_duplicate_content($content_data);
                if ($duplicate_check['is_duplicate']) {
                    $this->logger->warning('Duplicate content detected', array(
                        'existing_post_id' => $duplicate_check['post_id'],
                        'similarity_score' => $duplicate_check['similarity_score']
                    ));
                    
                    return array(
                        'success' => false,
                        'error' => 'Content appears to be duplicate',
                        'duplicate_post_id' => $duplicate_check['post_id'],
                        'similarity_score' => $duplicate_check['similarity_score'],
                        'timestamp' => current_time('Y-m-d H:i:s')
                    );
                }
            }
            
            // Humanize content if enabled
            if ($this->config['humanization_enabled']) {
                $content_data = $this->humanize_content_data($content_data);
            }
            
            // Prepare post data
            $post_data = $this->prepare_post_data($content_data, $params);
            
            // Create the post
            $post_id = $this->create_wordpress_post($post_data, $params);
            
            if (!$post_id) {
                throw new Exception('Failed to create WordPress post');
            }
            
            // Process post metadata and features
            $this->process_post_metadata($post_id, $content_data, $params);
            
            // Schedule if needed
            if ($params['schedule_post'] && $params['schedule_time']) {
                $this->schedule_post($post_id, $params['schedule_time']);
            }
            
            // Trigger additional processing
            $this->trigger_post_processing_hooks($post_id, $content_data, $params);
            
            $execution_time = (microtime(true) - $start_time) * 1000;
            
            // Record rate limit attempt
            if ($this->rate_limiter) {
                $this->rate_limiter->record_attempt('post_creation', 3600);
            }
            
            // Update metrics
            $this->update_metrics('create_post', true, $execution_time, 1);
            
            $response = array(
                'success' => true,
                'post_id' => $post_id,
                'post_url' => get_permalink($post_id),
                'execution_time_ms' => $execution_time,
                'word_count' => $content_data['word_count'] ?? 0,
                'seo_score' => $content_data['seo_score'] ?? 0,
                'timestamp' => current_time('Y-m-d H:i:s')
            );
            
            $this->logger->info('Post created successfully', array(
                'post_id' => $post_id,
                'post_title' => $post_data['post_title'],
                'execution_time_ms' => $execution_time
            ));
            
            return $response;
            
        } catch (Exception $e) {
            $execution_time = (microtime(true) - $start_time) * 1000;
            
            // Update failure metrics
            $this->update_metrics('create_post', false, $execution_time, 0, $e->getMessage());
            
            $this->logger->error('Post creation failed', array(
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
     * Create posts in batch
     *
     * @param array $contents_data Array of content data
     * @param array $parameters Batch parameters
     * @return array Batch creation result
     */
    public function create_batch_posts($parameters = array()) {
        $params = array_merge(array(
            'contents_data' => array(),
            'batch_template' => 'standard',
            'async_processing' => $this->config['async_processing'],
            'max_concurrent' => 3,
            'schedule_posts' => false
        ), $parameters);
        
        if (empty($params['contents_data'])) {
            throw new Exception('No content data provided for batch creation');
        }
        
        $results = array();
        $errors = array();
        
        if ($params['async_processing']) {
            // Use queue system for async processing
            return $this->queue_batch_creation($params['contents_data'], $params);
        } else {
            // Process synchronously
            foreach ($params['contents_data'] as $content_data) {
                try {
                    $result = $this->create_post(array(
                        'content_data' => $content_data,
                        'post_template' => $params['batch_template'],
                        'schedule_post' => $params['schedule_posts']
                    ));
                    
                    if ($result['success']) {
                        $results[] = $result;
                    } else {
                        $errors[] = array(
                            'content' => $content_data,
                            'error' => $result['error']
                        );
                    }
                    
                    // Brief pause between posts
                    usleep(500000); // 0.5 second pause
                    
                } catch (Exception $e) {
                    $errors[] = array(
                        'content' => $content_data,
                        'error' => $e->getMessage()
                    );
                }
            }
        }
        
        return array(
            'success' => true,
            'created_posts' => count($results),
            'failed_posts' => count($errors),
            'results' => $results,
            'errors' => $errors,
            'timestamp' => current_time('Y-m-d H:i:s')
        );
    }
    
    /**
     * Queue batch creation for async processing
     *
     * @param array $contents_data Content data array
     * @param array $params Batch parameters
     * @return array Queue result
     */
    private function queue_batch_creation($contents_data, $params) {
        // This would integrate with a queue system
        // For now, we'll implement a simple queue in WordPress options
        
        $queue_id = uniqid('batch_', true);
        $queue_data = array(
            'id' => $queue_id,
            'contents_data' => $contents_data,
            'params' => $params,
            'status' => 'queued',
            'created_at' => current_time('Y-m-d H:i:s'),
            'progress' => 0
        );
        
        // Store in queue
        $this->cache_manager->set("batch_queue_{$queue_id}", $queue_data, 3600);
        
        // Add to processing queue
        $this->add_to_processing_queue('batch_creation', $queue_id);
        
        $this->logger->info('Batch creation queued', array(
            'queue_id' => $queue_id,
            'items_count' => count($contents_data)
        ));
        
        return array(
            'success' => true,
            'queue_id' => $queue_id,
            'status' => 'queued',
            'items_count' => count($contents_data),
            'timestamp' => current_time('Y-m-d H:i:s')
        );
    }
    
    /**
     * Humanize content data using the humanizer manager
     *
     * @param array $content_data Content data to humanize
     * @return array Updated content data
     */
    private function humanize_content_data($content_data) {
        try {
            // Combine all text content for humanization
            $text_to_humanize = array();
            
            if (!empty($content_data['title'])) {
                $text_to_humanize[] = $content_data['title'];
            }
            
            if (!empty($content_data['content'])) {
                $text_to_humanize[] = $content_data['content'];
            }
            
            if (!empty($content_data['excerpt'])) {
                $text_to_humanize[] = $content_data['excerpt'];
            }
            
            $combined_text = implode("\n\n", $text_to_humanize);
            
            if (empty($combined_text)) {
                return $content_data;
            }
            
            // Humanize the combined text
            $humanization_result = $this->humanizer_manager->humanize_content($combined_text);
            
            if ($humanization_result['success']) {
                $humanized_text = $humanization_result['humanized_content'];
                
                // Split back into components
                $humanized_parts = explode("\n\n", $humanized_text, 3);
                
                // Update content data with humanized text
                if (isset($humanized_parts[0])) {
                    $content_data['title'] = $humanized_parts[0];
                }
                
                if (isset($humanized_parts[1])) {
                    $content_data['content'] = $humanized_parts[1];
                }
                
                if (isset($humanized_parts[2])) {
                    $content_data['excerpt'] = $humanized_parts[2];
                }
                
                // Add humanization metadata
                $content_data['humanized'] = true;
                $content_data['humanization_metadata'] = array(
                    'strength' => $this->config['humanization_strength'],
                    'execution_time_ms' => $humanization_result['execution_time_ms'],
                    'original_length' => strlen($combined_text),
                    'humanized_length' => strlen($humanized_text)
                );
                
                $this->logger->info('Content humanized successfully', array(
                    'original_length' => strlen($combined_text),
                    'humanized_length' => strlen($humanized_text),
                    'execution_time_ms' => $humanization_result['execution_time_ms']
                ));
                
            } else {
                $this->logger->warning('Content humanization failed', array(
                    'error' => $humanization_result['error']
                ));
                
                // Add failure metadata but continue with original content
                $content_data['humanization_failed'] = true;
                $content_data['humanization_error'] = $humanization_result['error'];
            }
            
            return $content_data;
            
        } catch (Exception $e) {
            $this->logger->error('Content humanization error', array(
                'error' => $e->getMessage()
            ));
            
            // On error, return original content data
            $content_data['humanization_error'] = $e->getMessage();
            return $content_data;
        }
    }
    
    /**
     * Prepare post data for WordPress
     *
     * @param array $content_data Generated content data
     * @param array $params Creation parameters
     * @return array WordPress post data
     */
    private function prepare_post_data($content_data, $params) {
        // Get template
        $template = $this->post_templates[$params['post_template']] ?? $this->post_templates['standard'];
        
        // Prepare content
        $content = $template['template'];
        $content = $this->replace_template_variables($content, $content_data);
        
        // Add metadata
        if ($template['meta_description'] && isset($content_data['meta_description'])) {
            $meta_description = $content_data['meta_description'];
        } else {
            $meta_description = $content_data['excerpt'] ?? '';
        }
        
        // Prepare WordPress post data
        $post_data = array(
            'post_title' => $content_data['title'],
            'post_content' => $content,
            'post_excerpt' => $content_data['excerpt'] ?? '',
            'post_status' => $params['create_draft'] ? 'draft' : 'publish',
            'post_author' => $this->config['default_post_author'],
            'post_type' => 'post',
            'meta_input' => array(
                '_aanp_generated' => true,
                '_aanp_generated_at' => current_time('Y-m-d H:i:s'),
                '_aanp_source_url' => $content_data['source_news_item']['source_url'] ?? '',
                '_aanp_ai_provider' => $content_data['provider_used'] ?? 'unknown',
                '_aanp_word_count' => $content_data['word_count'] ?? 0,
                '_aanp_seo_score' => $content_data['seo_score'] ?? 0,
                '_aanp_readability_score' => $content_data['readability_score'] ?? 0,
                '_aanp_meta_description' => $meta_description
            )
        );
        
        // Add humanization metadata if available
        if (isset($content_data['humanized']) && $content_data['humanized']) {
            $post_data['meta_input']['_aanp_humanized'] = true;
            $post_data['meta_input']['_aanp_humanization_strength'] = $content_data['humanization_metadata']['strength'] ?? 'medium';
            $post_data['meta_input']['_aanp_humanization_time_ms'] = $content_data['humanization_metadata']['execution_time_ms'] ?? 0;
        }
        
        // Add categories if auto categorization is enabled
        if ($this->config['auto_categorization']) {
            $categories = $this->determine_post_categories($content_data);
            if (!empty($categories)) {
                $post_data['post_category'] = $categories;
            }
        }
        
        // Add tags if auto tagging is enabled
        if ($this->config['auto_tagging']) {
            $tags = $this->generate_post_tags($content_data);
            if (!empty($tags)) {
                $post_data['tags_input'] = $tags;
            }
        }
        
        // Add custom fields from template
        if (!empty($template['custom_fields'])) {
            foreach ($template['custom_fields'] as $field_name => $field_template) {
                $field_value = $this->replace_template_variables($field_template, $content_data);
                $post_data['meta_input'][$field_name] = $field_value;
            }
        }
        
        return $post_data;
    }
    
    /**
     * Replace template variables in content
     *
     * @param string $template Template with variables
     * @param array $data Data to replace variables with
     * @return string Processed template
     */
    private function replace_template_variables($template, $data) {
        $replacements = array(
            '{title}' => $data['title'] ?? '',
            '{content}' => $data['content'] ?? '',
            '{excerpt}' => $data['excerpt'] ?? '',
            '{source}' => $data['source_news_item']['source_url'] ?? '',
            '{url}' => $data['source_news_item']['link'] ?? '',
            '{date}' => $data['source_news_item']['pub_date'] ?? current_time('Y-m-d'),
            '{word_count}' => $data['word_count'] ?? 0,
            '{readability}' => $data['readability_score'] ?? 0,
            '{seo_score}' => $data['seo_score'] ?? 0,
            '{provider}' => $data['provider_used'] ?? 'unknown'
        );
        
        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
    
    /**
     * Create WordPress post
     *
     * @param array $post_data Post data
     * @param array $params Creation parameters
     * @return int|false Post ID on success, false on failure
     */
    private function create_wordpress_post($post_data, $params) {
        try {
            // Use existing post creator if available
            if ($this->post_creator && method_exists($this->post_creator, 'create_post')) {
                $result = $this->post_creator->create_post($post_data);
                return is_numeric($result) ? intval($result) : false;
            }
            
            // Fallback to direct WordPress function
            $post_id = wp_insert_post($post_data, true);
            
            if (is_wp_error($post_id)) {
                throw new Exception('WordPress post creation failed: ' . $post_id->get_error_message());
            }
            
            return $post_id;
            
        } catch (Exception $e) {
            $this->logger->error('WordPress post creation failed', array(
                'error' => $e->getMessage(),
                'post_title' => $post_data['post_title']
            ));
            return false;
        }
    }
    
    /**
     * Process post metadata and features
     *
     * @param int $post_id Post ID
     * @param array $content_data Content data
     * @param array $params Creation parameters
     */
    private function process_post_metadata($post_id, $content_data, $params) {
        try {
            // Set featured image if enabled and available
            if ($params['featured_image'] && $this->config['featured_image_enabled']) {
                $this->set_featured_image($post_id, $content_data);
            }
            
            // SEO optimization
            if ($params['seo_optimize'] && $this->config['seo_optimization']) {
                $this->optimize_post_seo($post_id, $content_data);
            }
            
            // Social sharing setup
            if ($params['social_share'] && $this->config['social_sharing']) {
                $this->setup_social_sharing($post_id, $content_data);
            }
            
            $this->logger->debug('Post metadata processed successfully', array(
                'post_id' => $post_id
            ));
            
        } catch (Exception $e) {
            $this->logger->warning('Some post metadata processing failed', array(
                'post_id' => $post_id,
                'error' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Set featured image for post
     *
     * @param int $post_id Post ID
     * @param array $content_data Content data
     */
    private function set_featured_image($post_id, $content_data) {
        // This would integrate with featured image generation service
        // For now, we'll skip if no image URL is available
        if (!isset($content_data['featured_image_url'])) {
            return;
        }
        
        // Download and set featured image
        $image_id = $this->download_and_set_featured_image($post_id, $content_data['featured_image_url']);
        
        if ($image_id) {
            set_post_thumbnail($post_id, $image_id);
        }
    }
    
    /**
     * Download and set featured image
     *
     * @param int $post_id Post ID
     * @param string $image_url Image URL
     * @return int|false Attachment ID on success
     */
    private function download_and_set_featured_image($post_id, $image_url) {
        try {
            // Download image
            $response = wp_remote_get($image_url, array('timeout' => 30));
            
            if (is_wp_error($response)) {
                return false;
            }
            
            $image_data = wp_remote_retrieve_body($response);
            $filename = basename(parse_url($image_url, PHP_URL_PATH));
            
            // Upload to WordPress media library
            $upload = wp_upload_bits($filename, null, $image_data);
            
            if ($upload['error']) {
                return false;
            }
            
            // Create attachment
            $attachment = array(
                'post_mime_type' => $upload['type'],
                'post_title' => preg_replace('/\.[^.]+$/', '', $filename),
                'post_content' => '',
                'post_status' => 'inherit'
            );
            
            $attachment_id = wp_insert_attachment($attachment, $upload['file'], $post_id);
            
            if ($attachment_id) {
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                $attachment_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
                wp_update_attachment_metadata($attachment_id, $attachment_data);
                
                return $attachment_id;
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->logger->error('Featured image setup failed', array(
                'post_id' => $post_id,
                'image_url' => $image_url,
                'error' => $e->getMessage()
            ));
            return false;
        }
    }
    
    /**
     * Optimize post for SEO with RankMath integration
     *
     * @param int $post_id Post ID
     * @param array $content_data Content data
     */
    private function optimize_post_seo($post_id, $content_data) {
        try {
            // Basic SEO optimizations (Yoast compatibility)
            if (isset($content_data['meta_description'])) {
                update_post_meta($post_id, '_yoast_wpseo_metadesc', $content_data['meta_description']);
            }
            
            if (isset($content_data['focus_keyword'])) {
                update_post_meta($post_id, '_yoast_wpseo_focuskw', $content_data['focus_keyword']);
            }
            
            // RankMath SEO optimization if enabled
            if ($this->config['rankmath_integration'] && $this->rankmath_integration->is_compatible()) {
                $this->optimize_with_rankmath($post_id, $content_data);
            }
            
            // Additional SEO optimizations
            $this->add_seo_schema_markup($post_id, $content_data);
            
        } catch (Exception $e) {
            $this->logger->warning('SEO optimization failed', array(
                'post_id' => $post_id,
                'error' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Optimize post with RankMath integration
     *
     * @param int $post_id Post ID
     * @param array $content_data Content data
     */
    private function optimize_with_rankmath($post_id, $content_data) {
        try {
            // Set RankMath meta description
            if (isset($content_data['meta_description'])) {
                update_post_meta($post_id, 'rank_math_description', $content_data['meta_description']);
            }
            
            // Generate and set focus keyword if not provided
            if (empty($content_data['focus_keyword']) && $this->config['auto_rankmath_optimization']) {
                $focus_keyword_result = $this->rankmath_auto_optimizer->auto_generate_focus_keyword($post_id, $content_data);
                if ($focus_keyword_result['success']) {
                    $content_data['focus_keyword'] = $focus_keyword_result['suggested_keyword'];
                }
            }
            
            // Set focus keyword
            if (isset($content_data['focus_keyword'])) {
                $this->rankmath_integration->set_focus_keyword($post_id, $content_data['focus_keyword']);
            }
            
            // Auto-optimize title if enabled
            if ($this->config['auto_rankmath_optimization'] && isset($content_data['title'])) {
                $title_optimization = $this->rankmath_auto_optimizer->auto_optimize_title(
                    $post_id,
                    $content_data['title'],
                    $content_data['focus_keyword'] ?? ''
                );
                
                if ($title_optimization['success']) {
                    // Update post title if optimized
                    wp_update_post(array(
                        'ID' => $post_id,
                        'post_title' => $title_optimization['optimized_title']
                    ));
                }
            }
            
            // Auto-optimize meta description if enabled
            if ($this->config['auto_rankmath_optimization']) {
                $meta_optimization = $this->rankmath_auto_optimizer->auto_optimize_meta_description($post_id, $content_data);
                if ($meta_optimization['success']) {
                    update_post_meta($post_id, 'rank_math_description', $meta_optimization['optimized_meta']);
                }
            }
            
            // Auto-optimize content structure if enabled
            if ($this->config['auto_rankmath_optimization']) {
                $content_optimization = $this->rankmath_auto_optimizer->auto_optimize_content($post_id, $content_data);
                if ($content_optimization['success']) {
                    // Content was already updated in the optimization process
                }
            }
            
            $this->logger->info('RankMath SEO optimization completed', array(
                'post_id' => $post_id,
                'focus_keyword' => $content_data['focus_keyword'] ?? 'none'
            ));
            
        } catch (Exception $e) {
            $this->logger->warning('RankMath SEO optimization failed', array(
                'post_id' => $post_id,
                'error' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Add SEO schema markup
     *
     * @param int $post_id Post ID
     * @param array $content_data Content data
     */
    private function add_seo_schema_markup($post_id, $content_data) {
        // This would add structured data markup for better SEO
        // Implementation would depend on specific SEO plugins used
    }
    
    /**
     * Perform comprehensive SEO analysis
     *
     * @param int $post_id Post ID
     * @param array $content_data Content data
     * @return array SEO analysis results
     */
    private function perform_comprehensive_seo_analysis($post_id, $content_data) {
        try {
            $analysis_results = array();
            
            // RankMath SEO analysis
            if ($this->config['real_time_seo_analysis'] && $this->rankmath_seo_analyzer) {
                $rankmath_analysis = $this->rankmath_seo_analyzer->analyze_seo($post_id, array(
                    'analysis_depth' => 'comprehensive',
                    'include_recommendations' => true,
                    'include_auto_fixes' => false
                ));
                
                $analysis_results['rankmath'] = $rankmath_analysis;
            }
            
            // Content analysis
            if ($this->content_analyzer) {
                $content_analysis = $this->content_analyzer->analyze_content($content_data, array(
                    'primary_keyword' => $content_data['focus_keyword'] ?? '',
                    'analyze_seo' => true,
                    'analyze_eeat' => $this->config['eeat_optimization'],
                    'analyze_serp' => true
                ));
                
                $analysis_results['content'] = $content_analysis;
            }
            
            // EEAT optimization
            if ($this->config['eeat_optimization'] && $this->eeat_optimizer) {
                $eeat_optimization = $this->eeat_optimizer->optimize_for_eeat($content_data, array(
                    'content_type' => $content_data['content_type'] ?? 'blog_post',
                    'primary_keyword' => $content_data['focus_keyword'] ?? '',
                    'user_id' => $this->config['default_post_author']
                ));
                
                $analysis_results['eeat'] = $eeat_optimization;
            }
            
            return $analysis_results;
            
        } catch (Exception $e) {
            $this->logger->warning('SEO analysis failed', array(
                'post_id' => $post_id,
                'error' => $e->getMessage()
            ));
            
            return array();
        }
    }
    
    /**
     * Get SEO optimization recommendations
     *
     * @param int $post_id Post ID
     * @param array $analysis_results Analysis results
     * @return array Recommendations
     */
    private function get_seo_recommendations($post_id, $analysis_results) {
        $recommendations = array();
        
        // Process RankMath recommendations
        if (isset($analysis_results['rankmath']['recommendations'])) {
            foreach ($analysis_results['rankmath']['recommendations'] as $recommendation) {
                $recommendations[] = array(
                    'source' => 'RankMath',
                    'priority' => $recommendation['priority'] ?? 'medium',
                    'category' => $recommendation['category'] ?? 'general',
                    'title' => $recommendation['title'] ?? '',
                    'description' => $recommendation['description'] ?? '',
                    'implementation' => $recommendation['implementation'] ?? '',
                    'impact' => $recommendation['impact'] ?? 'medium',
                    'effort' => $recommendation['effort'] ?? 'medium'
                );
            }
        }
        
        // Process content analysis recommendations
        if (isset($analysis_results['content']['recommendations'])) {
            foreach ($analysis_results['content']['recommendations'] as $recommendation) {
                $recommendations[] = array(
                    'source' => 'Content Analysis',
                    'priority' => $recommendation['priority'] ?? 'medium',
                    'category' => $recommendation['category'] ?? 'general',
                    'title' => $recommendation['recommendation'] ?? '',
                    'description' => $recommendation['recommendation'] ?? '',
                    'impact' => $recommendation['impact'] ?? 'medium'
                );
            }
        }
        
        // Sort by priority
        usort($recommendations, function($a, $b) {
            $priority_order = array('high' => 3, 'medium' => 2, 'low' => 1);
            return ($priority_order[$b['priority']] ?? 2) - ($priority_order[$a['priority']] ?? 2);
        });
        
        return array_slice($recommendations, 0, 10); // Return top 10 recommendations
    }
    
    /**
     * Get RankMath integration status
     *
     * @return array Integration status
     */
    public function get_rankmath_status() {
        if (!$this->rankmath_integration) {
            return array(
                'available' => false,
                'reason' => 'RankMath integration not initialized'
            );
        }
        
        return $this->rankmath_integration->get_integration_status();
    }
    
    /**
     * Get SEO metrics for dashboard
     *
     * @return array SEO metrics
     */
    public function get_seo_metrics() {
        $metrics = array(
            'rankmath_status' => $this->get_rankmath_status(),
            'config' => array(
                'integration_enabled' => $this->config['rankmath_integration'],
                'auto_optimization_enabled' => $this->config['auto_rankmath_optimization'],
                'real_time_analysis_enabled' => $this->config['real_time_seo_analysis'],
                'eeat_optimization_enabled' => $this->config['eeat_optimization'],
                'target_seo_score' => $this->config['target_seo_score']
            ),
            'service_health' => array(
                'rankmath_integration' => $this->rankmath_integration ? $this->rankmath_integration->health_check() : false,
                'rankmath_auto_optimizer' => $this->rankmath_auto_optimizer ? $this->rankmath_auto_optimizer->health_check() : false,
                'rankmath_seo_analyzer' => $this->rankmath_seo_analyzer ? $this->rankmath_seo_analyzer->health_check() : false,
                'content_analyzer' => $this->content_analyzer ? $this->content_analyzer->health_check() : false,
                'eeat_optimizer' => $this->eeat_optimizer ? $this->eeat_optimizer->health_check() : false
            )
        );
        
        return $metrics;
    }
    
    /**
     * Setup social sharing
     *
     * @param int $post_id Post ID
     * @param array $content_data Content data
     */
    private function setup_social_sharing($post_id, $content_data) {
        // This would integrate with social sharing plugins
        // and set up sharing buttons, Open Graph tags, etc.
    }
    
    /**
     * Determine post categories
     *
     * @param array $content_data Content data
     * @return array Category IDs
     */
    private function determine_post_categories($content_data) {
        if (empty($this->config['default_categories'])) {
            return array();
        }
        
        // Simple keyword-based categorization
        $content_text = strtolower($content_data['title'] . ' ' . $content_data['excerpt'] . ' ' . strip_tags($content_data['content']));
        $matched_categories = array();
        
        foreach ($this->config['default_categories'] as $category_id => $category_name) {
            // Simple keyword matching
            if (strpos($content_text, strtolower($category_name)) !== false) {
                $matched_categories[] = $category_id;
            }
        }
        
        // If no matches, use first default category
        if (empty($matched_categories) && !empty($this->config['default_categories'])) {
            $matched_categories = array(array_keys($this->config['default_categories'])[0]);
        }
        
        return $matched_categories;
    }
    
    /**
     * Generate post tags
     *
     * @param array $content_data Content data
     * @return array Generated tags
     */
    private function generate_post_tags($content_data) {
        $tags = array();
        
        // Extract keywords from content
        $content_text = strtolower($content_data['title'] . ' ' . $content_data['excerpt'] . ' ' . strip_tags($content_data['content']));
        
        // Simple keyword extraction
        $words = preg_split('/\s+/', $content_text);
        $word_frequency = array();
        
        foreach ($words as $word) {
            $word = preg_replace('/[^a-zA-Z]/', '', $word);
            if (strlen($word) > 3) {
                $word_frequency[$word] = isset($word_frequency[$word]) ? $word_frequency[$word] + 1 : 1;
            }
        }
        
        // Sort by frequency and take top tags
        arsort($word_frequency);
        $tags = array_slice(array_keys($word_frequency), 0, 5);
        
        return $tags;
    }
    
    /**
     * Check daily post limit
     *
     * @return bool True if within limits
     */
    private function check_daily_post_limit() {
        global $wpdb;
        
        $today = current_time('Y-m-d');
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'post' 
             AND post_status = 'publish' 
             AND DATE(post_date) = %s 
             AND post_author = %d",
            $today,
            $this->config['default_post_author']
        ));
        
        return intval($count) < $this->config['max_posts_per_day'];
    }
    
    /**
     * Check for duplicate content
     *
     * @param array $content_data Content data
     * @return array Duplicate check result
     */
    private function check_duplicate_content($content_data) {
        global $wpdb;

        try {
            // Start transaction for read consistency
            $wpdb->query('START TRANSACTION');

            // Simple duplicate check based on title similarity
            $title_words = explode(' ', strtolower($content_data['title']));
            $title_where = implode(' OR ', array_map(function($word) {
                return "post_title LIKE %s";
            }, array_slice($title_words, 0, 3))); // Use first 3 words

            $like_patterns = array();
            foreach (array_slice($title_words, 0, 3) as $word) {
                $like_patterns[] = '%' . $wpdb->esc_like($word) . '%';
            }

            $existing_posts = $wpdb->get_results($wpdb->prepare(
                "SELECT ID, post_title FROM {$wpdb->posts}
                 WHERE post_type = 'post'
                 AND post_status IN ('publish', 'draft')
                 AND ({$title_where})",
                ...$like_patterns
            ));

            if (empty($existing_posts)) {
                $wpdb->query('COMMIT');
                return array('is_duplicate' => false);
            }

            // Calculate similarity score
            foreach ($existing_posts as $post) {
                $similarity = $this->calculate_similarity($content_data['title'], $post->post_title);

                if ($similarity > 0.8) { // 80% similarity threshold
                    $wpdb->query('COMMIT');
                    return array(
                        'is_duplicate' => true,
                        'post_id' => $post->ID,
                        'similarity_score' => $similarity
                    );
                }
            }

            $wpdb->query('COMMIT');
            return array('is_duplicate' => false);

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            AANP_Error_Handler::getInstance()->handle_error($e->getMessage(), array(
                'method' => 'check_duplicate_content',
                'content_title' => $content_data['title'] ?? 'unknown'
            ), 'DATABASE');
            return array('is_duplicate' => false); // Fail open - allow creation on error
        }
    }
    
    /**
     * Calculate similarity between two texts
     *
     * @param string $text1 First text
     * @param string $text2 Second text
     * @return float Similarity score (0-1)
     */
    private function calculate_similarity($text1, $text2) {
        $words1 = explode(' ', strtolower($text1));
        $words2 = explode(' ', strtolower($text2));
        
        $common_words = array_intersect($words1, $words2);
        $total_words = count(array_unique(array_merge($words1, $words2)));
        
        return count($common_words) / $total_words;
    }
    
    /**
     * Schedule post for future publishing
     *
     * @param int $post_id Post ID
     * @param string $schedule_time Schedule time
     */
    private function schedule_post($post_id, $schedule_time) {
        $timestamp = strtotime($schedule_time);
        
        if ($timestamp > time()) {
            wp_schedule_single_event($timestamp, 'aanp_publish_scheduled_post', array($post_id));
        }
    }
    
    /**
     * Trigger post processing hooks
     *
     * @param int $post_id Post ID
     * @param array $content_data Content data
     * @param array $params Creation parameters
     */
    private function trigger_post_processing_hooks($post_id, $content_data, $params) {
        // Trigger WordPress action for post creation
        do_action('aanp_post_created', $post_id, $content_data, $params);
        
        // Trigger SEO optimization hook
        if ($params['seo_optimize']) {
            do_action('aanp_post_seo_optimized', $post_id, $content_data);
        }
    }
    
    /**
     * Add to processing queue
     *
     * @param string $type Queue type
     * @param mixed $data Queue data
     */
    private function add_to_processing_queue($type, $data) {
        $queue_key = "aanp_queue_{$type}";
        $queue = get_option($queue_key, array());
        
        $queue[] = array(
            'data' => $data,
            'timestamp' => time(),
            'priority' => 10
        );
        
        update_option($queue_key, $queue);
    }
    
    /**
     * Process post creation queue
     */
    public function process_post_queue() {
        $queue_key = 'aanp_queue_batch_creation';
        $queue = get_option($queue_key, array());
        
        if (empty($queue)) {
            return;
        }
        
        // Process next item in queue
        $item = array_shift($queue);
        
        if ($item) {
            try {
                $queue_data = $this->cache_manager->get("batch_queue_{$item['data']}");
                
                if ($queue_data && $queue_data['status'] === 'queued') {
                    $this->process_batch_queue_item($queue_data);
                }
                
            } catch (Exception $e) {
                $this->logger->error('Queue processing failed', array(
                    'error' => $e->getMessage()
                ));
            }
        }
        
        update_option($queue_key, $queue);
    }
    
    /**
     * Process individual batch queue item
     *
     * @param array $queue_data Queue data
     */
    private function process_batch_queue_item($queue_data) {
        // Process the batch creation
        // This is a placeholder for the actual implementation
    }
    
    /**
     * Cleanup failed post creation
     *
     * @param array $context Execution context
     */
    public function cleanup_failed_post($context) {
        // Clean up any temporary data or drafts created during failed workflow
        $this->logger->info('Cleaning up failed post creation', array(
            'execution_id' => $context['id'] ?? 'unknown'
        ));
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
     * Get service metrics
     *
     * @return array Service metrics
     */
    public function get_metrics() {
        return array(
            'service' => 'ContentCreationService',
            'metrics' => $this->metrics,
            'config' => $this->config,
            'post_templates' => array_keys($this->post_templates),
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
            // Test WordPress post creation capability
            $test_post = array(
                'post_title' => 'Health Check Test',
                'post_content' => 'This is a test post for health check.',
                'post_status' => 'draft'
            );
            
            $test_id = wp_insert_post($test_post, true);
            
            if (is_wp_error($test_id)) {
                return false;
            }
            
            // Clean up test post
            wp_delete_post($test_id, true);
            
            // Test cache functionality
            $test_key = 'content_health_check_' . time();
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
            $this->logger->error('ContentCreationService health check failed', array(
                'error' => $e->getMessage()
            ));
            return false;
        }
    }
}