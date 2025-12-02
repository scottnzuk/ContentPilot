<?php
/**
 * RankMath SEO Integration for AI Auto News Poster
 *
 * Provides seamless integration with RankMath SEO plugin,
 * enabling automatic optimization and compatibility with RankMath's
 * best practices and scoring system.
 *
 * @package AI_Auto_News_Poster\SEO
 * @since 1.3.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * RankMath Integration Class
 */
class AANP_RankMathIntegration {
    
    /**
     * RankMath detection status
     * @var bool
     */
    private $rankmath_detected = false;
    
    /**
     * RankMath version
     * @var string
     */
    private $rankmath_version = '';
    
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
     * Integration configuration
     * @var array
     */
    private $config = array();
    
    /**
     * RankMath API compatibility layer
     * @var array
     */
    private $api_compatibility = array();
    
    /**
     * Constructor
     *
     * @param AANP_AdvancedCacheManager $cache_manager
     */
    public function __construct(AANP_AdvancedCacheManager $cache_manager = null) {
        $this->logger = AANP_Logger::getInstance();
        $this->cache_manager = $cache_manager ?: new AANP_AdvancedCacheManager();
        
        $this->init_integration();
        $this->detect_rankmath_plugin();
        $this->setup_api_compatibility();
    }
    
    /**
     * Initialize integration settings
     */
    private function init_integration() {
        $options = get_option('aanp_settings', array());
        
        $this->config = array(
            'auto_optimization_enabled' => isset($options['auto_rankmath_optimization']) ? (bool) $options['auto_rankmath_optimization'] : true,
            'score_monitoring' => isset($options['rankmath_score_monitoring']) ? (bool) $options['rankmath_score_monitoring'] : true,
            'title_optimization' => isset($options['rankmath_title_optimization']) ? (bool) $options['rankmath_title_optimization'] : true,
            'meta_optimization' => isset($options['rankmath_meta_optimization']) ? (bool) $options['rankmath_meta_optimization'] : true,
            'keyword_optimization' => isset($options['rankmath_keyword_optimization']) ? (bool) $options['rankmath_keyword_optimization'] : true,
            'content_optimization' => isset($options['rankmath_content_optimization']) ? (bool) $options['rankmath_content_optimization'] : true,
            'red_indicator_auto_fix' => isset($options['rankmath_red_indicator_fix']) ? (bool) $options['rankmath_red_indicator_fix'] : true,
            'yellow_indicator_auto_fix' => isset($options['rankmath_yellow_indicator_fix']) ? (bool) $options['rankmath_yellow_indicator_fix'] : false,
            'integration_level' => isset($options['rankmath_integration_level']) ? $options['rankmath_integration_level'] : 'full', // basic, full, advanced
            'api_timeout' => isset($options['rankmath_api_timeout']) ? intval($options['rankmath_api_timeout']) : 30
        );
    }
    
    /**
     * Detect RankMath plugin
     */
    private function detect_rankmath_plugin() {
        // Check if RankMath is active
        $this->rankmath_detected = class_exists('RankMath');
        
        if ($this->rankmath_detected) {
            $this->rankmath_version = defined('RANK_MATH_VERSION') ? RANK_MATH_VERSION : 'unknown';
            
            $this->logger->info('RankMath plugin detected', array(
                'version' => $this->rankmath_version,
                'integration_level' => $this->config['integration_level']
            ));
        } else {
            $this->logger->warning('RankMath plugin not detected - integration will work in compatibility mode');
        }
    }
    
    /**
     * Setup API compatibility layer
     */
    private function setup_api_compatibility() {
        $this->api_compatibility = array(
            'meta_box_class' => 'RankMath\\Helper',
            'seo_analysis_class' => 'RankMath\\Helper',
            'focus_keyword_class' => 'RankMath\\Helper',
            'schema_class' => 'RankMath\\Helper',
            'sitemap_class' => 'RankMath\\Helper'
        );
        
        // Try to detect available RankMath classes
        if ($this->rankmath_detected) {
            $this->detect_available_apis();
        }
    }
    
    /**
     * Detect available RankMath APIs
     */
    private function detect_available_apis() {
        $available_classes = array();
        
        // Check for common RankMath classes
        $rankmath_classes = array(
            'RankMath\\Helper' => 'Helper',
            'RankMath\\Paper\\Paper' => 'Paper',
            'RankMath\\Frontend\\Frontend' => 'Frontend',
            'RankMath\\Admin\\Admin' => 'Admin'
        );
        
        foreach ($rankmath_classes as $class => $name) {
            if (class_exists($class)) {
                $available_classes[$name] = $class;
            }
        }
        
        $this->api_compatibility['available_classes'] = $available_classes;
    }
    
    /**
     * Get RankMath integration status
     *
     * @return array Integration status
     */
    public function get_integration_status() {
        return array(
            'rankmath_detected' => $this->rankmath_detected,
            'rankmath_version' => $this->rankmath_version,
            'integration_level' => $this->config['integration_level'],
            'available_apis' => $this->api_compatibility['available_classes'] ?? array(),
            'configuration' => $this->config,
            'compatible' => $this->is_compatible()
        );
    }
    
    /**
     * Check if RankMath integration is compatible
     *
     * @return bool Compatibility status
     */
    public function is_compatible() {
        if (!$this->rankmath_detected) {
            return true; // Compatibility mode
        }
        
        // Check minimum RankMath version (assume version 2.0+)
        if (version_compare($this->rankmath_version, '2.0.0', '<')) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get current RankMath SEO score
     *
     * @param int $post_id Post ID
     * @return array SEO score data
     */
    public function get_seo_score($post_id) {
        if (!$this->rankmath_detected) {
            return $this->calculate_fallback_score($post_id);
        }
        
        try {
            // Try to get score using RankMath methods
            if (class_exists('RankMath\\Helper')) {
                $score = RankMath\Helper::get_post_meta($post_id, 'rank_math_seo_score');
                $focus_keyword = RankMath\Helper::get_post_meta($post_id, 'rank_math_focus_keyword');
                
                return array(
                    'score' => $score ?: 0,
                    'focus_keyword' => $focus_keyword ?: '',
                    'analysis' => $this->get_detailed_analysis($post_id),
                    'source' => 'RankMath',
                    'timestamp' => current_time('Y-m-d H:i:s')
                );
            }
            
            return $this->calculate_fallback_score($post_id);
            
        } catch (Exception $e) {
            $this->logger->error('Failed to get RankMath SEO score', array(
                'post_id' => $post_id,
                'error' => $e->getMessage()
            ));
            
            return $this->calculate_fallback_score($post_id);
        }
    }
    
    /**
     * Get detailed SEO analysis
     *
     * @param int $post_id Post ID
     * @return array Detailed analysis
     */
    public function get_detailed_analysis($post_id) {
        if (!$this->rankmath_detected) {
            return $this->generate_fallback_analysis($post_id);
        }
        
        $post = get_post($post_id);
        if (!$post) {
            return array();
        }
        
        $analysis = array();
        
        // Title analysis
        $analysis['title'] = array(
            'length' => strlen($post->post_title),
            'contains_focus_keyword' => $this->check_title_keyword_match($post_id),
            'optimization_status' => $this->get_title_optimization_status($post_id)
        );
        
        // Meta description analysis
        $meta_description = RankMath\Helper::get_post_meta($post_id, 'rank_math_description');
        $analysis['meta_description'] = array(
            'length' => strlen($meta_description),
            'exists' => !empty($meta_description),
            'optimization_status' => $this->get_meta_description_status($post_id)
        );
        
        // Content analysis
        $content = apply_filters('the_content', $post->post_content);
        $analysis['content'] = array(
            'word_count' => str_word_count(strip_tags($content)),
            'heading_structure' => $this->analyze_heading_structure($content),
            'keyword_density' => $this->calculate_keyword_density($post_id, $content),
            'optimization_status' => $this->get_content_optimization_status($post_id)
        );
        
        // Focus keyword analysis
        $focus_keyword = RankMath\Helper::get_post_meta($post_id, 'rank_math_focus_keyword');
        $analysis['focus_keyword'] = array(
            'set' => !empty($focus_keyword),
            'keyword' => $focus_keyword,
            'usage_analysis' => $this->analyze_keyword_usage($post_id, $focus_keyword)
        );
        
        return $analysis;
    }
    
    /**
     * Set focus keyword with optimization
     *
     * @param int $post_id Post ID
     * @param string $focus_keyword Focus keyword
     * @return bool Success status
     */
    public function set_focus_keyword($post_id, $focus_keyword) {
        if (!$this->rankmath_detected) {
            return false;
        }
        
        try {
            // Validate keyword
            if (!$this->is_valid_keyword($focus_keyword)) {
                throw new Exception('Invalid focus keyword');
            }
            
            // Set focus keyword using RankMath
            if (class_exists('RankMath\\Helper')) {
                RankMath\Helper::update_post_meta($post_id, 'rank_math_focus_keyword', sanitize_text_field($focus_keyword));
                
                $this->logger->info('Focus keyword set via RankMath integration', array(
                    'post_id' => $post_id,
                    'focus_keyword' => $focus_keyword
                ));
                
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to set focus keyword', array(
                'post_id' => $post_id,
                'focus_keyword' => $focus_keyword,
                'error' => $e->getMessage()
            ));
            
            return false;
        }
    }
    
    /**
     * Optimize title using RankMath recommendations
     *
     * @param int $post_id Post ID
     * @param string $title Title to optimize
     * @return array Optimization result
     */
    public function optimize_title($post_id, $title) {
        $original_title = $title;
        $optimizations = array();
        
        // Get focus keyword
        $focus_keyword = RankMath\Helper::get_post_meta($post_id, 'rank_math_focus_keyword');
        
        // Apply RankMath title optimization rules
        if (!empty($focus_keyword)) {
            // Add focus keyword if not present
            if (stripos($title, $focus_keyword) === false) {
                $title = $this->add_keyword_to_title($title, $focus_keyword);
                $optimizations[] = "Added focus keyword '{$focus_keyword}' to title";
            }
        }
        
        // Optimize length (50-60 characters optimal)
        $title_length = strlen($title);
        if ($title_length < 50) {
            $title = $this->enhance_title_length($title);
            $optimizations[] = "Enhanced title length for better SEO impact";
        } elseif ($title_length > 60) {
            $title = $this->shorten_title($title);
            $optimizations[] = "Shortened title to optimal length";
        }
        
        // Add power words and emotional triggers
        $title = $this->add_power_words($title);
        $optimizations[] = "Added engaging language elements";
        
        return array(
            'original_title' => $original_title,
            'optimized_title' => $title,
            'optimizations' => $optimizations,
            'length' => strlen($title),
            'contains_focus_keyword' => !empty($focus_keyword) && stripos($title, $focus_keyword) !== false,
            'seo_impact' => $this->calculate_title_seo_impact($title)
        );
    }
    
    /**
     * Generate meta description using RankMath best practices
     *
     * @param int $post_id Post ID
     * @param array $content_data Content data
     * @return array Meta description result
     */
    public function generate_meta_description($post_id, $content_data) {
        $focus_keyword = RankMath\Helper::get_post_meta($post_id, 'rank_math_focus_keyword');
        $existing_meta = RankMath\Helper::get_post_meta($post_id, 'rank_math_description');
        
        if (!empty($existing_meta)) {
            return array(
                'existing_meta' => $existing_meta,
                'status' => 'already_exists',
                'optimization_suggestions' => $this->optimize_existing_meta($existing_meta, $focus_keyword)
            );
        }
        
        // Generate new meta description
        $content = $content_data['content'] ?? '';
        $title = $content_data['title'] ?? '';
        
        $meta_description = $this->generate_seo_meta_description($content, $focus_keyword, $title);
        
        // Optimize length (150-160 characters)
        if (strlen($meta_description) > 160) {
            $meta_description = $this->shorten_meta_description($meta_description);
        }
        
        // Add call-to-action
        $meta_description = $this->add_meta_cta($meta_description);
        
        return array(
            'generated_meta' => $meta_description,
            'length' => strlen($meta_description),
            'contains_keyword' => !empty($focus_keyword) && stripos($meta_description, $focus_keyword) !== false,
            'has_cta' => $this->has_call_to_action($meta_description),
            'seo_score' => $this->calculate_meta_description_score($meta_description)
        );
    }
    
    /**
     * Analyze content structure for RankMath optimization
     *
     * @param string $content HTML content
     * @param string $focus_keyword Focus keyword
     * @return array Content structure analysis
     */
    public function analyze_content_structure($content, $focus_keyword = '') {
        $structure = array(
            'headings' => array(),
            'paragraphs' => 0,
            'images' => array(),
            'links' => array(),
            'keyword_distribution' => array(),
            'readability_score' => 0,
            'optimization_suggestions' => array()
        );
        
        // Analyze heading structure
        $structure['headings'] = $this->extract_heading_structure($content);
        $structure['paragraphs'] = preg_match_all('/<p[^>]*>/i', $content);
        
        // Analyze images
        $structure['images'] = $this->analyze_images($content);
        
        // Analyze links
        $structure['links'] = $this->analyze_links($content);
        
        // Keyword distribution analysis
        if (!empty($focus_keyword)) {
            $structure['keyword_distribution'] = $this->analyze_keyword_distribution($content, $focus_keyword);
        }
        
        // Readability analysis
        $structure['readability_score'] = $this->calculate_readability_score($content);
        
        // Generate optimization suggestions
        $structure['optimization_suggestions'] = $this->generate_structure_optimization_suggestions($structure);
        
        return $structure;
    }
    
    /**
     * Get RankMath schema suggestions
     *
     * @param int $post_id Post ID
     * @param string $content_type Content type
     * @return array Schema suggestions
     */
    public function get_schema_suggestions($post_id, $content_type = 'article') {
        $post = get_post($post_id);
        if (!$post) {
            return array();
        }
        
        $schema_suggestions = array();
        
        switch ($content_type) {
            case 'article':
                $schema_suggestions[] = array(
                    'type' => 'Article',
                    'properties' => array(
                        'headline',
                        'author',
                        'datePublished',
                        'dateModified',
                        'image'
                    ),
                    'priority' => 'high'
                );
                break;
                
            case 'news':
                $schema_suggestions[] = array(
                    'type' => 'NewsArticle',
                    'properties' => array(
                        'headline',
                        'author',
                        'datePublished',
                        'dateModified',
                        'image',
                        'articleSection'
                    ),
                    'priority' => 'high'
                );
                break;
        }
        
        // Add organization schema if not present
        $schema_suggestions[] = array(
            'type' => 'Organization',
            'properties' => array(
                'name',
                'logo',
                'url'
            ),
            'priority' => 'medium'
        );
        
        return $schema_suggestions;
    }
    
    /**
     * Apply RankMath optimization suggestions automatically
     *
     * @param int $post_id Post ID
     * @param array $suggestions Optimization suggestions
     * @return array Auto-optimization result
     */
    public function apply_auto_optimizations($post_id, $suggestions) {
        $results = array(
            'applied' => array(),
            'skipped' => array(),
            'errors' => array(),
            'improvements' => array()
        );
        
        foreach ($suggestions as $suggestion) {
            try {
                switch ($suggestion['type']) {
                    case 'title_optimization':
                        $result = $this->apply_title_optimization($post_id, $suggestion);
                        break;
                        
                    case 'meta_description':
                        $result = $this->apply_meta_description_optimization($post_id, $suggestion);
                        break;
                        
                    case 'focus_keyword':
                        $result = $this->apply_focus_keyword_optimization($post_id, $suggestion);
                        break;
                        
                    case 'content_structure':
                        $result = $this->apply_content_structure_optimization($post_id, $suggestion);
                        break;
                        
                    default:
                        $result = array('success' => false, 'reason' => 'Unknown optimization type');
                }
                
                if ($result['success']) {
                    $results['applied'][] = $suggestion['type'];
                    $results['improvements'][] = $result['improvement'];
                } else {
                    $results['skipped'][] = array('type' => $suggestion['type'], 'reason' => $result['reason']);
                }
                
            } catch (Exception $e) {
                $results['errors'][] = array(
                    'type' => $suggestion['type'],
                    'error' => $e->getMessage()
                );
            }
        }
        
        return $results;
    }
    
    /**
     * Get RankMath SEO indicators
     *
     * @param int $post_id Post ID
     * @return array SEO indicators
     */
    public function get_seo_indicators($post_id) {
        $analysis = $this->get_detailed_analysis($post_id);
        
        $indicators = array();
        
        // Red indicators
        $red_indicators = array();
        
        if (!$analysis['focus_keyword']['set']) {
            $red_indicators[] = array(
                'indicator' => 'focus_keyword_missing',
                'message' => 'Focus keyword not set',
                'impact' => 'high',
                'auto_fixable' => $this->config['red_indicator_auto_fix']
            );
        }
        
        if ($analysis['meta_description']['length'] < 50 || $analysis['meta_description']['length'] > 160) {
            $red_indicators[] = array(
                'indicator' => 'meta_description_issues',
                'message' => 'Meta description length issue',
                'impact' => 'high',
                'auto_fixable' => $this->config['red_indicator_auto_fix']
            );
        }
        
        // Yellow indicators
        $yellow_indicators = array();
        
        if ($analysis['title']['length'] < 30 || $analysis['title']['length'] > 60) {
            $yellow_indicators[] = array(
                'indicator' => 'title_length_suboptimal',
                'message' => 'Title length could be optimized',
                'impact' => 'medium',
                'auto_fixable' => $this->config['yellow_indicator_auto_fix']
            );
        }
        
        if ($analysis['content']['word_count'] < 300) {
            $yellow_indicators[] = array(
                'indicator' => 'content_length_short',
                'message' => 'Content could be expanded',
                'impact' => 'medium',
                'auto_fixable' => $this->config['yellow_indicator_auto_fix']
            );
        }
        
        return array(
            'red_indicators' => $red_indicators,
            'yellow_indicators' => $yellow_indicators,
            'green_indicators' => $this->get_green_indicators($analysis),
            'score_factors' => $this->calculate_score_factors($analysis)
        );
    }
    
    // Private helper methods
    
    /**
     * Calculate fallback score when RankMath is not available
     */
    private function calculate_fallback_score($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return array('score' => 0, 'source' => 'Fallback');
        }
        
        $score = 0;
        
        // Title score
        if (!empty($post->post_title)) {
            $score += 20;
        }
        
        // Meta description score
        if (!empty($post->post_excerpt)) {
            $score += 20;
        }
        
        // Content length score
        $word_count = str_word_count(strip_tags($post->post_content));
        if ($word_count >= 300) {
            $score += 30;
        }
        
        // Focus keyword score
        $focus_keyword = get_post_meta($post_id, 'aanp_focus_keyword', true);
        if (!empty($focus_keyword)) {
            $score += 30;
        }
        
        return array(
            'score' => $score,
            'source' => 'Fallback',
            'timestamp' => current_time('Y-m-d H:i:s')
        );
    }
    
    /**
     * Generate fallback analysis
     */
    private function generate_fallback_analysis($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return array();
        }
        
        return array(
            'title' => array(
                'length' => strlen($post->post_title),
                'exists' => !empty($post->post_title)
            ),
            'content' => array(
                'word_count' => str_word_count(strip_tags($post->post_content))
            ),
            'focus_keyword' => array(
                'set' => false
            )
        );
    }
    
    /**
     * Check if keyword is valid
     */
    private function is_valid_keyword($keyword) {
        if (empty($keyword) || strlen($keyword) < 2 || strlen($keyword) > 100) {
            return false;
        }
        
        // Check for invalid characters
        if (preg_match('/[^a-zA-Z0-9\s\-\_]/', $keyword)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Add keyword to title
     */
    private function add_keyword_to_title($title, $keyword) {
        // Try to add keyword at the beginning
        if (strlen($title . ' ' . $keyword) <= 60) {
            return $keyword . ': ' . $title;
        }
        
        // Or at the end
        if (strlen($title . ' ' . $keyword) <= 60) {
            return $title . ' - ' . $keyword;
        }
        
        // If title is too long, replace part of it
        $max_length = 60 - strlen(' - ' . $keyword);
        return substr($title, 0, $max_length) . ' - ' . $keyword;
    }
    
    /**
     * Enhance title length
     */
    private function enhance_title_length($title) {
        $power_words = array('Ultimate', 'Complete', 'Expert', 'Proven', 'Essential', 'Best', 'Advanced');
        $word = $power_words[array_rand($power_words)];
        
        return $word . ' ' . $title;
    }
    
    /**
     * Shorten title to optimal length
     */
    private function shorten_title($title) {
        // Remove filler words
        $filler_words = array('the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by');
        
        $words = explode(' ', $title);
        $filtered_words = array();
        
        foreach ($words as $word) {
            if (!in_array(strtolower($word), $filler_words) || count($filtered_words) < 3) {
                $filtered_words[] = $word;
            }
            
            if (strlen(implode(' ', $filtered_words)) <= 58) {
                break;
            }
        }
        
        return implode(' ', $filtered_words);
    }
    
    /**
     * Add power words to title
     */
    private function add_power_words($title) {
        $power_words = array('Powerful', 'Amazing', 'Incredible', 'Revolutionary', 'Breakthrough', 'Game-changing');
        
        // Add power word if it fits
        if (strlen($title . ' ' . $power_words[0]) <= 60) {
            return $title . ' ' . $power_words[array_rand($power_words)];
        }
        
        return $title;
    }
    
    /**
     * Calculate title SEO impact
     */
    private function calculate_title_seo_impact($title) {
        $impact = 0;
        
        // Length impact
        $length = strlen($title);
        if ($length >= 50 && $length <= 60) {
            $impact += 30;
        } elseif ($length >= 30 && $length < 50) {
            $impact += 20;
        }
        
        // Keyword presence impact
        $impact += 25;
        
        // Power word impact
        if (preg_match('/\b(amazing|incredible|ultimate|complete|expert|proven|best|advanced|powerful|revolutionary)\b/i', $title)) {
            $impact += 15;
        }
        
        return min(100, $impact);
    }
    
    /**
     * Generate SEO meta description
     */
    private function generate_seo_meta_description($content, $focus_keyword, $title) {
        $content_excerpt = wp_trim_words(strip_tags($content), 20);
        $title_part = !empty($title) ? $title : '';
        
        if (!empty($focus_keyword)) {
            return "Discover everything about {$focus_keyword}. Learn expert strategies and proven methods. {$content_excerpt} Get started today!";
        }
        
        return "Learn from our comprehensive guide. Expert insights and proven strategies for success. {$content_excerpt} Read more to get started!";
    }
    
    /**
     * Shorten meta description
     */
    private function shorten_meta_description($meta_description) {
        if (strlen($meta_description) <= 160) {
            return $meta_description;
        }
        
        // Find last complete sentence within limit
        $sentences = preg_split('/([.!?]+)/', $meta_description, -1, PREG_SPLIT_DELIM_CAPTURE);
        $result = '';
        
        foreach ($sentences as $sentence) {
            if (strlen($result . $sentence) <= 157) {
                $result .= $sentence;
            } else {
                break;
            }
        }
        
        return trim($result) . '...';
    }
    
    /**
     * Add call-to-action to meta description
     */
    private function add_meta_cta($meta_description) {
        $ctas = array('Learn more', 'Get started', 'Discover now', 'Read more', 'Find out');
        $cta = $ctas[array_rand($ctas)];
        
        // Add CTA if there's space
        if (strlen($meta_description . ' ' . $cta) <= 160) {
            return $meta_description . ' ' . $cta;
        }
        
        return $meta_description;
    }
    
    /**
     * Check if meta description has call-to-action
     */
    private function has_call_to_action($meta_description) {
        $cta_words = array('learn', 'discover', 'get', 'read', 'explore', 'check', 'see', 'find');
        
        foreach ($cta_words as $cta) {
            if (stripos($meta_description, $cta) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Calculate meta description score
     */
    private function calculate_meta_description_score($meta_description) {
        $score = 0;
        
        // Length score
        $length = strlen($meta_description);
        if ($length >= 150 && $length <= 160) {
            $score += 40;
        } elseif ($length >= 120 && $length < 150) {
            $score += 30;
        }
        
        // Call-to-action score
        if ($this->has_call_to_action($meta_description)) {
            $score += 30;
        }
        
        // Engagement score
        if (preg_match('/\b(amazing|incredible|best|top|expert|proven|ultimate)\b/i', $meta_description)) {
            $score += 30;
        }
        
        return min(100, $score);
    }
    
    /**
     * Extract heading structure from content
     */
    private function extract_heading_structure($content) {
        $structure = array();
        
        preg_match_all('/<(h[1-6])[^>]*>(.*?)<\/\1>/i', $content, $matches);
        
        for ($i = 0; $i < count($matches[0]); $i++) {
            $structure[] = array(
                'level' => $matches[1][$i],
                'text' => strip_tags($matches[2][$i]),
                'full_tag' => $matches[0][$i]
            );
        }
        
        return $structure;
    }
    
    /**
     * Analyze images in content
     */
    private function analyze_images($content) {
        $images = array();
        
        preg_match_all('/<img[^>]*>/i', $content, $matches);
        
        for ($i = 0; $i < count($matches[0]); $i++) {
            $img_tag = $matches[0][$i];
            
            $images[] = array(
                'has_alt' => preg_match('/alt=["\']?([^"\']*?)["\']?/i', $img_tag, $alt_match),
                'alt_text' => isset($alt_match[1]) ? $alt_match[1] : '',
                'tag' => $img_tag
            );
        }
        
        return $images;
    }
    
    /**
     * Analyze links in content
     */
    private function analyze_links($content) {
        $links = array();
        
        preg_match_all('/<a[^>]*href=["\']?([^"\']*?)["\']?[^>]*>(.*?)<\/a>/i', $content, $matches);
        
        for ($i = 0; $i < count($matches[0]); $i++) {
            $links[] = array(
                'url' => $matches[1][$i],
                'text' => strip_tags($matches[2][$i]),
                'is_external' => strpos($matches[1][$i], get_site_url()) === false,
                'tag' => $matches[0][$i]
            );
        }
        
        return $links;
    }
    
    /**
     * Analyze keyword distribution in content
     */
    private function analyze_keyword_distribution($content, $keyword) {
        $plain_text = strtolower(strip_tags($content));
        $keyword_lower = strtolower($keyword);
        
        $paragraphs = preg_split('/\n\s*\n/', $content);
        $distribution = array();
        
        foreach ($paragraphs as $index => $paragraph) {
            $keyword_count = substr_count(strtolower(strip_tags($paragraph)), $keyword_lower);
            if ($keyword_count > 0) {
                $distribution[$index + 1] = $keyword_count;
            }
        }
        
        return $distribution;
    }
    
    /**
     * Calculate readability score
     */
    private function calculate_readability_score($content) {
        $plain_text = strip_tags($content);
        $sentences = preg_split('/[.!?]+/', $plain_text, -1, PREG_SPLIT_NO_EMPTY);
        $words = preg_split('/\s+/', $plain_text);
        
        $sentence_count = count($sentences);
        $word_count = count($words);
        
        if ($sentence_count === 0 || $word_count === 0) {
            return 0;
        }
        
        // Simple readability calculation
        $avg_sentence_length = $word_count / $sentence_count;
        
        if ($avg_sentence_length <= 17) {
            return 100;
        } elseif ($avg_sentence_length <= 25) {
            return 80;
        } elseif ($avg_sentence_length <= 35) {
            return 60;
        } else {
            return 40;
        }
    }
    
    /**
     * Generate structure optimization suggestions
     */
    private function generate_structure_optimization_suggestions($structure) {
        $suggestions = array();
        
        // Check heading structure
        if (count($structure['headings']) === 0) {
            $suggestions[] = 'Add headings to improve content structure';
        }
        
        // Check for H1
        $has_h1 = false;
        foreach ($structure['headings'] as $heading) {
            if ($heading['level'] === 'h1') {
                $has_h1 = true;
                break;
            }
        }
        
        if (!$has_h1) {
            $suggestions[] = 'Add an H1 heading to your content';
        }
        
        // Check images
        if (count($structure['images']) > 0) {
            $images_without_alt = 0;
            foreach ($structure['images'] as $image) {
                if (!$image['has_alt'] || empty($image['alt_text'])) {
                    $images_without_alt++;
                }
            }
            
            if ($images_without_alt > 0) {
                $suggestions[] = "Add alt text to {$images_without_alt} image(s)";
            }
        }
        
        // Check keyword distribution
        if (!empty($structure['keyword_distribution'])) {
            $total_occurrences = array_sum($structure['keyword_distribution']);
            if ($total_occurrences < 3) {
                $suggestions[] = 'Increase keyword usage in content';
            }
        }
        
        return $suggestions;
    }
    
    /**
     * Get green indicators from analysis
     */
    private function get_green_indicators($analysis) {
        $green_indicators = array();
        
        if ($analysis['focus_keyword']['set']) {
            $green_indicators[] = 'Focus keyword is set';
        }
        
        if ($analysis['title']['length'] >= 30 && $analysis['title']['length'] <= 60) {
            $green_indicators[] = 'Title length is optimal';
        }
        
        if ($analysis['meta_description']['length'] >= 120 && $analysis['meta_description']['length'] <= 160) {
            $green_indicators[] = 'Meta description length is good';
        }
        
        if ($analysis['content']['word_count'] >= 300) {
            $green_indicators[] = 'Content length is sufficient';
        }
        
        return $green_indicators;
    }
    
    /**
     * Calculate score factors
     */
    private function calculate_score_factors($analysis) {
        $factors = array(
            'focus_keyword' => $analysis['focus_keyword']['set'] ? 25 : 0,
            'title' => min(25, ($analysis['title']['length'] / 60) * 25),
            'meta_description' => min(25, ($analysis['meta_description']['length'] / 160) * 25),
            'content' => min(25, ($analysis['content']['word_count'] / 1000) * 25)
        );
        
        return $factors;
    }
    
    /**
     * Get service metrics
     */
    public function get_metrics() {
        return array(
            'service' => 'RankMathIntegration',
            'rankmath_detected' => $this->rankmath_detected,
            'rankmath_version' => $this->rankmath_version,
            'config' => $this->config,
            'available_apis' => $this->api_compatibility['available_classes'] ?? array(),
            'memory_usage' => memory_get_usage(true),
            'timestamp' => current_time('Y-m-d H:i:s')
        );
    }
    
    /**
     * Health check method
     */
    public function health_check() {
        try {
            // Test basic functionality
            $status = $this->get_integration_status();
            
            // Check required methods exist
            $required_methods = array('get_seo_score', 'get_detailed_analysis', 'optimize_title');
            foreach ($required_methods as $method) {
                if (!method_exists($this, $method)) {
                    return false;
                }
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('RankMathIntegration health check failed', array(
                'error' => $e->getMessage()
            ));
            return false;
        }
    }
}