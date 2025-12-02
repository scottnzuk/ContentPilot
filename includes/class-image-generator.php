<?php
/**
 * AI Image Generation System for Featured Images
 * 
 * Handles AI-powered image generation using multiple providers,
 * with image processing, optimization, and WordPress media library integration.
 *
 * @package AI_Auto_News_Poster
 * @since 1.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI Image Generator Class
 */
class AANP_Image_Generator {
    
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
     * Service configuration
     * @var array
     */
    private $config = array();
    
    /**
     * Image generation metrics
     * @var array
     */
    private $metrics = array();
    
    /**
     * Supported image styles
     * @var array
     */
    private $image_styles = array();
    
    /**
     * Supported image providers
     * @var array
     */
    private $providers = array();
    
    /**
     * Constructor
     *
     * @param AANP_AdvancedCacheManager $cache_manager
     */
    public function __construct(AANP_AdvancedCacheManager $cache_manager = null) {
        $this->logger = AANP_Logger::getInstance();
        $this->cache_manager = $cache_manager ?: new AANP_AdvancedCacheManager();
        
        // Initialize rate limiter
        if (class_exists('AANP_Rate_Limiter')) {
            $this->rate_limiter = new AANP_Rate_Limiter();
        }
        
        $this->init_config();
        $this->init_providers();
        $this->init_image_styles();
        $this->init_hooks();
    }
    
    /**
     * Initialize service configuration
     */
    private function init_config() {
        $options = get_option('aanp_settings', array());
        
        $this->config = array(
            'default_provider' => isset($options['image_generation_provider']) ? $options['image_generation_provider'] : 'openai',
            'default_style' => isset($options['image_style']) ? $options['image_style'] : 'professional',
            'image_size' => isset($options['image_size']) ? $options['image_size'] : '1024x1024',
            'image_format' => isset($options['image_format']) ? $options['image_format'] : 'jpg',
            'enable_image_optimization' => isset($options['enable_image_optimization']) ? (bool) $options['enable_image_optimization'] : true,
            'enable_webp_conversion' => isset($options['enable_webp']) ? (bool) $options['enable_webp'] : true,
            'fallback_stock_images' => isset($options['fallback_stock_images']) ? (bool) $options['fallback_stock_images'] : true,
            'max_generation_attempts' => 3,
            'timeout' => 120,
            'cache_duration' => 86400, // 24 hours
            'retry_attempts' => 2
        );
    }
    
    /**
     * Initialize AI providers
     */
    private function init_providers() {
        $this->providers = array(
            'openai' => array(
                'name' => 'OpenAI DALL-E',
                'available' => class_exists('AANP_AI_Generator'),
                'api_endpoint' => 'https://api.openai.com/v1/images/generations',
                'max_size' => '1024x1024',
                'formats' => array('png', 'jpg'),
                'cost' => 'paid'
            ),
            'stability' => array(
                'name' => 'Stability AI',
                'available' => false, // Will check API key
                'api_endpoint' => 'https://api.stability.ai/v1/generation/stable-diffusion-xl-1024-v1-0/text-to-image',
                'max_size' => '1024x1024',
                'formats' => array('png', 'jpg'),
                'cost' => 'paid'
            ),
            'replicate' => array(
                'name' => 'Replicate',
                'available' => false, // Will check API key
                'api_endpoint' => 'https://api.replicate.com/v1/predictions',
                'max_size' => '1024x1024',
                'formats' => array('png', 'jpg'),
                'cost' => 'paid'
            ),
            'huggingface' => array(
                'name' => 'Hugging Face',
                'available' => true, // Free tier available
                'api_endpoint' => 'https://api-inference.huggingface.co/models/runwayml/stable-diffusion-v1-5',
                'max_size' => '512x512',
                'formats' => array('png', 'jpg'),
                'cost' => 'free'
            )
        );
    }
    
    /**
     * Initialize image styles
     */
    private function init_image_styles() {
        $this->image_styles = array(
            'professional' => array(
                'name' => 'Professional',
                'description' => 'Clean, business-oriented imagery',
                'prompt_enhancement' => 'professional, clean, modern, business, high quality, detailed'
            ),
            'abstract' => array(
                'name' => 'Abstract',
                'description' => 'Abstract and artistic representations',
                'prompt_enhancement' => 'abstract, artistic, creative, colorful, modern design, high quality'
            ),
            'realistic' => array(
                'name' => 'Realistic',
                'description' => 'Photorealistic imagery',
                'prompt_enhancement' => 'realistic, photographic, high quality, detailed, professional photography'
            ),
            'minimalist' => array(
                'name' => 'Minimalist',
                'description' => 'Simple and clean designs',
                'prompt_enhancement' => 'minimalist, simple, clean, white space, elegant, modern'
            ),
            'news' => array(
                'name' => 'News Style',
                'description' => 'News article imagery',
                'prompt_enhancement' => 'news, journalism, informative, documentary style, professional, informative'
            )
        );
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('aanp_generate_featured_image', array($this, 'handle_generate_featured_image'), 10, 2);
        add_action('aanp_optimize_featured_image', array($this, 'handle_optimize_featured_image'), 10, 2);
    }
    
    /**
     * Generate featured image for post
     *
     * @param int $post_id Post ID
     * @param array $article Article data
     * @return array Generation result
     */
    public function generate_featured_image($post_id, $article) {
        $start_time = microtime(true);
        
        try {
            // Validate parameters
            if (!$this->validate_parameters($post_id, $article)) {
                throw new Exception('Invalid parameters provided');
            }
            
            $this->logger->info('Starting featured image generation', array(
                'post_id' => $post_id,
                'article_title' => $article['title'] ?? 'No title'
            ));
            
            // Check rate limiting
            if ($this->rate_limiter && $this->rate_limiter->is_rate_limited('image_generation', 5, 3600)) {
                throw new Exception('Rate limit exceeded for image generation');
            }
            
            // Generate optimized prompt
            $prompt = $this->create_optimized_prompt($article);
            
            // Check cache
            $cache_key = 'featured_image_' . md5($prompt . $this->config['default_style']);
            $cached_result = $this->cache_manager->get($cache_key);
            if ($cached_result !== false) {
                $this->logger->debug('Returning cached featured image', array('cache_key' => $cache_key));
                return $cached_result;
            }
            
            // Generate image using provider
            $generation_result = $this->generate_image_with_provider($prompt);
            
            if (!$generation_result['success']) {
                throw new Exception('Image generation failed: ' . $generation_result['error']);
            }
            
            // Process and optimize image
            $processed_result = $this->process_generated_image($generation_result['image_data'], $post_id, $article);
            
            if (!$processed_result['success']) {
                throw new Exception('Image processing failed: ' . $processed_result['error']);
            }
            
            $execution_time = (microtime(true) - $start_time) * 1000;
            
            // Record rate limit attempt
            if ($this->rate_limiter) {
                $this->rate_limiter->record_attempt('image_generation', 3600);
            }
            
            // Cache result
            $this->cache_manager->set($cache_key, $processed_result, $this->config['cache_duration']);
            
            // Update metrics
            $this->update_metrics('generate_featured_image', true, $execution_time, 1);
            
            $this->logger->info('Featured image generated successfully', array(
                'post_id' => $post_id,
                'attachment_id' => $processed_result['attachment_id'],
                'execution_time_ms' => $execution_time
            ));
            
            return $processed_result;
            
        } catch (Exception $e) {
            $execution_time = (microtime(true) - $start_time) * 1000;
            
            // Update failure metrics
            $this->update_metrics('generate_featured_image', false, $execution_time, 0, $e->getMessage());
            
            $this->logger->error('Featured image generation failed', array(
                'post_id' => $post_id,
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
     * Handle featured image generation via hook
     *
     * @param int $post_id Post ID
     * @param array $article Article data
     */
    public function handle_generate_featured_image($post_id, $article) {
        $result = $this->generate_featured_image($post_id, $article);
        
        // Trigger completion hook
        if ($result['success']) {
            do_action('aanp_featured_image_generated', $post_id, $result);
        } else {
            do_action('aanp_featured_image_generation_failed', $post_id, $result);
        }
    }
    
    /**
     * Generate image using specified provider
     *
     * @param string $prompt Image generation prompt
     * @param string $provider Provider name
     * @return array Generation result
     */
    private function generate_image_with_provider($prompt, $provider = null) {
        $provider = $provider ?: $this->config['default_provider'];
        
        if (!isset($this->providers[$provider])) {
            throw new Exception("Unknown provider: {$provider}");
        }
        
        $provider_config = $this->providers[$provider];
        
        if (!$provider_config['available']) {
            throw new Exception("Provider '{$provider}' is not available");
        }
        
        switch ($provider) {
            case 'openai':
                return $this->generate_with_openai($prompt);
            case 'stability':
                return $this->generate_with_stability($prompt);
            case 'replicate':
                return $this->generate_with_replicate($prompt);
            case 'huggingface':
                return $this->generate_with_huggingface($prompt);
            default:
                throw new Exception("Provider '{$provider}' not implemented");
        }
    }
    
    /**
     * Generate image with OpenAI DALL-E
     *
     * @param string $prompt Image prompt
     * @return array Generation result
     */
    private function generate_with_openai($prompt) {
        try {
            $api_key = $this->get_api_key('openai');
            if (empty($api_key)) {
                throw new Exception('OpenAI API key not configured');
            }
            
            $endpoint = $this->providers['openai']['api_endpoint'];
            $size = $this->config['image_size'];
            
            $data = array(
                'model' => 'dall-e-3',
                'prompt' => $prompt,
                'n' => 1,
                'size' => $size,
                'quality' => 'hd',
                'response_format' => 'url'
            );
            
            $response = wp_remote_post($endpoint, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode($data),
                'timeout' => $this->config['timeout']
            ));
            
            if (is_wp_error($response)) {
                throw new Exception('OpenAI API request failed: ' . $response->get_error_message());
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            $result = json_decode($response_body, true);
            
            if ($response_code !== 200) {
                throw new Exception('OpenAI API error: ' . ($result['error']['message'] ?? 'Unknown error'));
            }
            
            if (!isset($result['data'][0]['url'])) {
                throw new Exception('Invalid response format from OpenAI');
            }
            
            // Download image
            $image_url = $result['data'][0]['url'];
            $image_data = $this->download_image($image_url);
            
            return array(
                'success' => true,
                'image_data' => $image_data,
                'provider' => 'openai',
                'metadata' => array(
                    'revised_prompt' => $result['data'][0]['revised_prompt'] ?? $prompt,
                    'size' => $size
                )
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Generate image with Stability AI
     *
     * @param string $prompt Image prompt
     * @return array Generation result
     */
    private function generate_with_stability($prompt) {
        try {
            $api_key = $this->get_api_key('stability');
            if (empty($api_key)) {
                throw new Exception('Stability AI API key not configured');
            }
            
            $endpoint = $this->providers['stability']['api_endpoint'];
            
            $data = array(
                'text_prompts' => array(
                    array(
                        'text' => $prompt,
                        'weight' => 1
                    )
                ),
                'cfg_scale' => 7,
                'height' => 1024,
                'width' => 1024,
                'samples' => 1,
                'steps' => 30
            );
            
            $response = wp_remote_post($endpoint, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ),
                'body' => json_encode($data),
                'timeout' => $this->config['timeout']
            ));
            
            if (is_wp_error($response)) {
                throw new Exception('Stability AI API request failed: ' . $response->get_error_message());
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            $result = json_decode($response_body, true);
            
            if ($response_code !== 200) {
                $error_msg = isset($result['message']) ? $result['message'] : 'Unknown error';
                throw new Exception('Stability AI API error: ' . $error_msg);
            }
            
            if (!isset($result['artifacts'][0]['base64'])) {
                throw new Exception('Invalid response format from Stability AI');
            }
            
            // Decode base64 image
            $image_data = base64_decode($result['artifacts'][0]['base64']);
            
            return array(
                'success' => true,
                'image_data' => $image_data,
                'provider' => 'stability',
                'metadata' => array(
                    'seed' => $result['artifacts'][0]['seed'] ?? null,
                    'size' => '1024x1024'
                )
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Generate image with Hugging Face (Free option)
     *
     * @param string $prompt Image prompt
     * @return array Generation result
     */
    private function generate_with_huggingface($prompt) {
        try {
            $endpoint = $this->providers['huggingface']['api_endpoint'];
            
            $data = array(
                'inputs' => $prompt,
                'options' => array(
                    'wait_for_model' => true
                )
            );
            
            $response = wp_remote_post($endpoint, array(
                'headers' => array(
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode($data),
                'timeout' => 60 // Hugging Face can be slow
            ));
            
            if (is_wp_error($response)) {
                throw new Exception('Hugging Face API request failed: ' . $response->get_error_message());
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            
            if ($response_code !== 200) {
                throw new Exception('Hugging Face API error: ' . $response_body);
            }
            
            // Response should be image data
            return array(
                'success' => true,
                'image_data' => $response_body,
                'provider' => 'huggingface',
                'metadata' => array(
                    'model' => 'stable-diffusion-v1-5',
                    'size' => '512x512'
                )
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Generate image with Replicate
     *
     * @param string $prompt Image prompt
     * @return array Generation result
     */
    private function generate_with_replicate($prompt) {
        try {
            $api_key = $this->get_api_key('replicate');
            if (empty($api_key)) {
                throw new Exception('Replicate API key not configured');
            }
            
            $endpoint = $this->providers['replicate']['api_endpoint'];
            
            $data = array(
                'version' => 'ac732df83cea7fff18b8472768c88ad041fa750ff7682a21affe81863cbe77e4',
                'input' => array(
                    'prompt' => $prompt,
                    'width' => 1024,
                    'height' => 1024,
                    'num_outputs' => 1,
                    'scheduler' => 'K_EULER',
                    'num_inference_steps' => 50,
                    'guidance_scale' => 7.5,
                    'seed' => null
                )
            );
            
            $response = wp_remote_post($endpoint, array(
                'headers' => array(
                    'Authorization' => 'Token ' . $api_key,
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode($data),
                'timeout' => $this->config['timeout']
            ));
            
            if (is_wp_error($response)) {
                throw new Exception('Replicate API request failed: ' . $response->get_error_message());
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            $result = json_decode($response_body, true);
            
            if ($response_code !== 201) {
                $error_msg = isset($result['detail']) ? $result['detail'] : 'Unknown error';
                throw new Exception('Replicate API error: ' . $error_msg);
            }
            
            // Poll for completion
            $prediction_id = $result['id'];
            return $this->poll_replicate_prediction($prediction_id, $api_key);
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Poll Replicate prediction for completion
     *
     * @param string $prediction_id Prediction ID
     * @param string $api_key API key
     * @return array Generation result
     */
    private function poll_replicate_prediction($prediction_id, $api_key) {
        $max_attempts = 30;
        $attempt = 0;
        
        while ($attempt < $max_attempts) {
            $response = wp_remote_get("https://api.replicate.com/v1/predictions/{$prediction_id}", array(
                'headers' => array(
                    'Authorization' => 'Token ' . $api_key
                ),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                break;
            }
            
            $result = json_decode(wp_remote_retrieve_body($response), true);
            
            if (isset($result['status'])) {
                if ($result['status'] === 'succeeded') {
                    if (isset($result['output'][0])) {
                        $image_url = $result['output'][0];
                        $image_data = $this->download_image($image_url);
                        
                        return array(
                            'success' => true,
                            'image_data' => $image_data,
                            'provider' => 'replicate',
                            'metadata' => array(
                                'prediction_id' => $prediction_id,
                                'size' => '1024x1024'
                            )
                        );
                    }
                } elseif ($result['status'] === 'failed') {
                    throw new Exception('Replicate generation failed: ' . ($result['error'] ?? 'Unknown error'));
                }
            }
            
            $attempt++;
            sleep(2);
        }
        
        throw new Exception('Replicate prediction timed out');
    }
    
    /**
     * Download image from URL
     *
     * @param string $image_url Image URL
     * @return string Image data
     */
    private function download_image($image_url) {
        $response = wp_remote_get($image_url, array(
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            throw new Exception('Failed to download image: ' . $response->get_error_message());
        }
        
        return wp_remote_retrieve_body($response);
    }
    
    /**
     * Create optimized prompt for image generation
     *
     * @param array $article Article data
     * @return string Optimized prompt
     */
    private function create_optimized_prompt($article) {
        // Extract key information
        $title = $article['title'] ?? '';
        $description = $article['description'] ?? '';
        $content = $article['content'] ?? '';
        
        // Create base prompt from article content
        $topic = $this->extract_topic_from_content($title . ' ' . $description . ' ' . substr($content, 0, 500));
        
        // Get style enhancement
        $style_config = $this->image_styles[$this->config['default_style']] ?? $this->image_styles['professional'];
        $style_enhancement = $style_config['prompt_enhancement'];
        
        // Create optimized prompt
        $prompt_parts = array();
        $prompt_parts[] = "A high-quality image representing: {$topic}";
        $prompt_parts[] = $style_enhancement;
        $prompt_parts[] = "suitable for a news article or blog post";
        $prompt_parts[] = "professional photography style";
        $prompt_parts[] = "wide aspect ratio";
        
        return implode(', ', $prompt_parts);
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
            if (strlen($word) > 3 && !in_array($word, $stop_words)) {
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
     * Process and optimize generated image
     *
     * @param string $image_data Raw image data
     * @param int $post_id Post ID
     * @param array $article Article data
     * @return array Processing result
     */
    private function process_generated_image($image_data, $post_id, $article) {
        try {
            // Optimize image if enabled
            if ($this->config['enable_image_optimization']) {
                $image_data = $this->optimize_image_data($image_data);
            }
            
            // Convert to WebP if enabled
            if ($this->config['enable_webp_conversion']) {
                $webp_data = $this->convert_to_webp($image_data);
                if ($webp_data !== false) {
                    $image_data = $webp_data;
                }
            }
            
            // Upload to WordPress media library
            $attachment_id = $this->upload_to_media_library($image_data, $post_id, $article);
            
            if (!$attachment_id) {
                throw new Exception('Failed to upload image to media library');
            }
            
            // Set as featured image
            set_post_thumbnail($post_id, $attachment_id);
            
            return array(
                'success' => true,
                'attachment_id' => $attachment_id,
                'image_url' => wp_get_attachment_image_url($attachment_id, 'full'),
                'image_alt' => $this->generate_alt_text($article),
                'provider_used' => $this->config['default_provider'],
                'generated_at' => current_time('Y-m-d H:i:s')
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Optimize image data
     *
     * @param string $image_data Raw image data
     * @return string Optimized image data
     */
    private function optimize_image_data($image_data) {
        // Create temporary file
        $temp_file = wp_tempnam();
        file_put_contents($temp_file, $image_data);
        
        // Get image info
        $image_info = getimagesize($temp_file);
        if (!$image_info) {
            unlink($temp_file);
            return $image_data;
        }
        
        $width = $image_info[0];
        $height = $image_info[1];
        $type = $image_info[2];
        
        // Create image resource
        switch ($type) {
            case IMAGETYPE_JPEG:
                $source = imagecreatefromjpeg($temp_file);
                break;
            case IMAGETYPE_PNG:
                $source = imagecreatefrompng($temp_file);
                break;
            case IMAGETYPE_GIF:
                $source = imagecreatefromgif($temp_file);
                break;
            default:
                unlink($temp_file);
                return $image_data;
        }
        
        if (!$source) {
            unlink($temp_file);
            return $image_data;
        }
        
        // Resize if too large (max 1920x1080)
        $max_width = 1920;
        $max_height = 1080;
        
        if ($width > $max_width || $height > $max_height) {
            $ratio = min($max_width / $width, $max_height / $height);
            $new_width = intval($width * $ratio);
            $new_height = intval($height * $ratio);
            
            $resized = imagecreatetruecolor($new_width, $new_height);
            
            // Preserve transparency for PNG
            if ($type == IMAGETYPE_PNG) {
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
                imagefill($resized, 0, 0, $transparent);
            }
            
            imagecopyresampled($resized, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
            
            // Save optimized image
            ob_start();
            imagejpeg($resized, null, 85); // 85% quality
            $optimized_data = ob_get_clean();
            
            imagedestroy($source);
            imagedestroy($resized);
            unlink($temp_file);
            
            return $optimized_data;
        }
        
        imagedestroy($source);
        unlink($temp_file);
        
        return $image_data;
    }
    
    /**
     * Convert image to WebP format
     *
     * @param string $image_data Image data
     * @return string|false WebP data or false if failed
     */
    private function convert_to_webp($image_data) {
        if (!function_exists('imagewebp')) {
            return false;
        }
        
        // Create temporary file
        $temp_file = wp_tempnam();
        file_put_contents($temp_file, $image_data);
        
        // Get image info
        $image_info = getimagesize($temp_file);
        if (!$image_info) {
            unlink($temp_file);
            return false;
        }
        
        $width = $image_info[0];
        $height = $image_info[1];
        $type = $image_info[2];
        
        // Create image resource
        switch ($type) {
            case IMAGETYPE_JPEG:
                $source = imagecreatefromjpeg($temp_file);
                break;
            case IMAGETYPE_PNG:
                $source = imagecreatefrompng($temp_file);
                if ($source) {
                    imagealphablending($source, false);
                    imagesavealpha($source, true);
                }
                break;
            default:
                unlink($temp_file);
                return false;
        }
        
        if (!$source) {
            unlink($temp_file);
            return false;
        }
        
        // Convert to WebP
        ob_start();
        $success = imagewebp($source, null, 80); // 80% quality
        $webp_data = ob_get_clean();
        
        imagedestroy($source);
        unlink($temp_file);
        
        return $success ? $webp_data : false;
    }
    
    /**
     * Upload image to WordPress media library
     *
     * @param string $image_data Image data
     * @param int $post_id Post ID
     * @param array $article Article data
     * @return int|false Attachment ID
     */
    private function upload_to_media_library($image_data, $post_id, $article) {
        // Generate filename
        $title = sanitize_file_name($article['title'] ?? 'featured-image');
        $filename = $title . '-' . $post_id . '.jpg';
        
        // Upload to WordPress
        $upload = wp_upload_bits($filename, null, $image_data);
        
        if ($upload['error']) {
            return false;
        }
        
        // Create attachment
        $attachment = array(
            'post_mime_type' => 'image/jpeg',
            'post_title' => $title,
            'post_content' => '',
            'post_status' => 'inherit',
            'post_alt' => $this->generate_alt_text($article),
            'meta_input' => array(
                '_aanp_generated_image' => true,
                '_aanp_generated_at' => current_time('Y-m-d H:i:s'),
                '_aanp_article_title' => $article['title'] ?? '',
                '_aanp_image_provider' => $this->config['default_provider']
            )
        );
        
        $attachment_id = wp_insert_attachment($attachment, $upload['file'], $post_id);
        
        if ($attachment_id) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attachment_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
            wp_update_attachment_metadata($attachment_id, $attachment_data);
        }
        
        return $attachment_id;
    }
    
    /**
     * Generate alt text for image
     *
     * @param array $article Article data
     * @return string Alt text
     */
    private function generate_alt_text($article) {
        $title = $article['title'] ?? '';
        $topic = $this->extract_topic_from_content($title . ' ' . ($article['description'] ?? ''));
        
        return sprintf(__('Featured image for: %s', 'ai-auto-news-poster'), $topic);
    }
    
    /**
     * Get API key for provider
     *
     * @param string $provider Provider name
     * @return string API key
     */
    private function get_api_key($provider) {
        $options = get_option('aanp_settings', array());
        
        switch ($provider) {
            case 'openai':
                $encrypted_key = $options['api_key'] ?? '';
                break;
            case 'stability':
                $encrypted_key = $options['stability_api_key'] ?? '';
                break;
            case 'replicate':
                $encrypted_key = $options['replicate_api_key'] ?? '';
                break;
            default:
                return '';
        }
        
        if (empty($encrypted_key)) {
            return '';
        }
        
        // Decrypt API key
        if (class_exists('AANP_Admin_Settings')) {
            return AANP_Admin_Settings::decrypt_api_key($encrypted_key);
        }
        
        return $encrypted_key;
    }
    
    /**
     * Validate parameters
     *
     * @param int $post_id Post ID
     * @param array $article Article data
     * @return bool True if valid
     */
    private function validate_parameters($post_id, $article) {
        if (!is_numeric($post_id) || intval($post_id) <= 0) {
            return false;
        }
        
        if (!is_array($article) || empty($article)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Handle image optimization via hook
     *
     * @param int $attachment_id Attachment ID
     * @param array $options Optimization options
     */
    public function handle_optimize_featured_image($attachment_id, $options = array()) {
        // Implementation for optimizing existing featured images
        // This can be called to optimize images that were generated without optimization
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
     * Get available providers
     *
     * @return array Available providers
     */
    public function get_available_providers() {
        $available = array();
        
        foreach ($this->providers as $name => $config) {
            if ($config['available']) {
                // Check API key for paid providers
                if (in_array($name, array('openai', 'stability', 'replicate'))) {
                    $api_key = $this->get_api_key($name);
                    if (!empty($api_key)) {
                        $available[$name] = $config;
                    }
                } else {
                    // Free providers
                    $available[$name] = $config;
                }
            }
        }
        
        return $available;
    }
    
    /**
     * Get service metrics
     *
     * @return array Service metrics
     */
    public function get_metrics() {
        return array(
            'service' => 'ImageGenerator',
            'metrics' => $this->metrics,
            'config' => $this->config,
            'available_providers' => $this->get_available_providers(),
            'image_styles' => array_keys($this->image_styles),
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
            // Test provider availability
            $available_providers = $this->get_available_providers();
            if (empty($available_providers)) {
                return false;
            }
            
            // Test cache functionality
            $test_key = 'image_health_check_' . time();
            $test_data = array('test' => 'value');
            $this->cache_manager->set($test_key, $test_data, 60);
            $retrieved = $this->cache_manager->get($test_key);
            
            if ($retrieved !== $test_data) {
                return false;
            }
            
            // Test image processing capabilities
            if (!function_exists('imagecreatefromjpeg')) {
                return false;
            }
            
            // Clean up test data
            $this->cache_manager->delete($test_key);
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('ImageGenerator health check failed', array(
                'error' => $e->getMessage()
            ));
            return false;
        }
    }
    
    /**
     * Cleanup service resources
     */
    public function cleanup() {
        try {
            // Clear image generation cache
            $this->cache_manager->delete_by_pattern('featured_image_');
            
            $this->logger->info('ImageGenerator cleanup completed');
            
        } catch (Exception $e) {
            $this->logger->error('ImageGenerator cleanup failed', array(
                'error' => $e->getMessage()
            ));
        }
    }
}