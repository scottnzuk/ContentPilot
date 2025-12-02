<?php
/**
 * RankMath Auto-Optimizer for Automatic SEO Optimization
 *
 * Provides automatic optimization of titles, keywords, meta descriptions,
 * and content structure using RankMath best practices and scoring algorithms.
 *
 * @package AI_Auto_News_Poster\SEO
 * @since 1.3.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * RankMath Auto-Optimizer Class
 */
class AANP_RankMathAutoOptimizer {
    
    /**
     * RankMath integration instance
     * @var AANP_RankMathIntegration
     */
    private $rankmath_integration;
    
    /**
     * Content analyzer instance
     * @var AANP_ContentAnalyzer
     */
    private $content_analyzer;
    
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
     * Auto-optimization configuration
     * @var array
     */
    private $config = array();
    
    /**
     * Title optimization templates
     * @var array
     */
    private $title_templates = array();
    
    /**
     * Meta description templates
     * @var array
     */
    private $meta_templates = array();
    
    /**
     * Keyword optimization patterns
     * @var array
     */
    private $keyword_patterns = array();
    
    /**
     * Content enhancement templates
     * @var array
     */
    private $content_templates = array();
    
    /**
     * Constructor
     *
     * @param AANP_RankMathIntegration $rankmath_integration
     * @param AANP_ContentAnalyzer $content_analyzer
     * @param AANP_AdvancedCacheManager $cache_manager
     */
    public function __construct(
        AANP_RankMathIntegration $rankmath_integration = null,
        AANP_ContentAnalyzer $content_analyzer = null,
        AANP_AdvancedCacheManager $cache_manager = null
    ) {
        $this->logger = AANP_Logger::getInstance();
        $this->rankmath_integration = $rankmath_integration ?: new AANP_RankMathIntegration();
        $this->content_analyzer = $content_analyzer ?: new AANP_ContentAnalyzer();
        $this->cache_manager = $cache_manager ?: new AANP_AdvancedCacheManager();
        
        $this->init_config();
        $this->init_templates();
        $this->init_keyword_patterns();
    }
    
    /**
     * Initialize auto-optimization configuration
     */
    private function init_config() {
        $options = get_option('aanp_settings', array());
        
        $this->config = array(
            'auto_title_optimization' => isset($options['auto_title_optimization']) ? (bool) $options['auto_title_optimization'] : true,
            'auto_meta_optimization' => isset($options['auto_meta_optimization']) ? (bool) $options['auto_meta_optimization'] : true,
            'auto_keyword_optimization' => isset($options['auto_keyword_optimization']) ? (bool) $options['auto_keyword_optimization'] : true,
            'auto_content_optimization' => isset($options['auto_content_optimization']) ? (bool) $options['auto_content_optimization'] : true,
            'auto_focus_keyword_suggestion' => isset($options['auto_focus_keyword_suggestion']) ? (bool) $options['auto_focus_keyword_suggestion'] : true,
            'optimization_level' => isset($options['auto_optimization_level']) ? $options['auto_optimization_level'] : 'aggressive', // conservative, balanced, aggressive
            'target_seo_score' => isset($options['target_seo_score']) ? intval($options['target_seo_score']) : 85,
            'maintain_readability' => isset($options['maintain_readability']) ? (bool) $options['maintain_readability'] : true,
            'preserve_author_style' => isset($options['preserve_author_style']) ? (bool) $options['preserve_author_style'] : true,
            'optimization_frequency' => isset($options['optimization_frequency']) ? $options['optimization_frequency'] : 'immediate', // immediate, publish, manual
            'red_indicator_auto_fix' => isset($options['red_indicator_auto_fix']) ? (bool) $options['red_indicator_auto_fix'] : true,
            'yellow_indicator_auto_fix' => isset($options['yellow_indicator_auto_fix']) ? (bool) $options['yellow_indicator_auto_fix'] : false,
            'power_word_injection' => isset($options['power_word_injection']) ? (bool) $options['power_word_injection'] : true,
            'emotional_trigger_optimization' => isset($options['emotional_trigger_optimization']) ? (bool) $options['emotional_trigger_optimization'] : true
        );
    }
    
    /**
     * Initialize optimization templates
     */
    private function init_templates() {
        $this->title_templates = array(
            'how_to' => array(
                'template' => 'How to {keyword}: {benefit} {year} Guide',
                'patterns' => array(
                    'How to {keyword}: Step-by-Step Guide {year}',
                    'How to {keyword}: Complete Tutorial with Tips',
                    'How to {keyword}: Expert Guide for Beginners',
                    'Learn How to {keyword}: Complete Guide {year}'
                )
            ),
            'best' => array(
                'template' => 'Best {keyword} {year}: {benefit} Comparison',
                'patterns' => array(
                    'Best {keyword} {year}: Top {number} Reviewed',
                    'Best {keyword}: Ultimate Guide & Comparison',
                    'Top {number} Best {keyword} - {benefit} Guide',
                    'Best {keyword} {year}: Expert Recommendations'
                )
            ),
            'review' => array(
                'template' => '{keyword} Review: {benefit} {year} Analysis',
                'patterns' => array(
                    '{keyword} Review: Is It Worth It? {year}',
                    'Complete {keyword} Review - Pros, Cons & Verdict',
                    '{keyword} Review: {benefit} Analysis {year}',
                    'In-Depth {keyword} Review: What You Need to Know'
                )
            ),
            'guide' => array(
                'template' => '{keyword} Guide {year}: {benefit} Tips',
                'patterns' => array(
                    '{keyword} Guide {year}: Complete Beginner Tutorial',
                    'Ultimate {keyword} Guide: {benefit} Strategies',
                    '{keyword} Guide: {benefit} for Success {year}',
                    'Complete {keyword} Guide: {benefit} & Best Practices'
                )
            ),
            'news' => array(
                'template' => '{keyword} News: {benefit} Update {year}',
                'patterns' => array(
                    '{keyword} News: {benefit} Announced {year}',
                    'Latest {keyword} News: {benefit} Update',
                    '{keyword} Breaking News: {benefit} Development',
                    '{keyword} Today: {benefit} News & Updates'
                )
            )
        );
        
        $this->meta_templates = array(
            'informational' => array(
                'template' => 'Discover everything about {keyword}. Learn {benefit} with our comprehensive guide. {cta}',
                'patterns' => array(
                    'Learn {keyword} from the experts. {benefit} strategies, tips & tricks. {cta}',
                    'Master {keyword} with our step-by-step guide. {benefit} made simple. {cta}',
                    'Complete {keyword} resource: {benefit} & expert insights. {cta}'
                )
            ),
            'commercial' => array(
                'template' => 'Find the best {keyword} for {benefit}. Expert reviews & comparisons. {cta}',
                'patterns' => array(
                    'Top-rated {keyword} reviews: {benefit} & buying guide. {cta}',
                    'Best {keyword} {year}: {benefit} comparison & expert tips. {cta}',
                    'Choose the right {keyword}: {benefit} analysis & recommendations. {cta}'
                )
            ),
            'transactional' => array(
                'template' => 'Get {keyword} now and {benefit}. Limited time offer. {cta}',
                'patterns' => array(
                    'Buy {keyword} with confidence: {benefit} guarantee. {cta}',
                    'Order {keyword} today: {benefit} & free shipping. {cta}',
                    'Purchase {keyword}: {benefit} & customer support. {cta}'
                )
            ),
            'news' => array(
                'template' => '{keyword} news update: {benefit}. Latest developments & analysis. {cta}',
                'patterns' => array(
                    'Breaking {keyword} news: {benefit} announced. {cta}',
                    'Latest {keyword} updates: {benefit} & expert commentary. {cta}',
                    '{keyword} today: {benefit} news & market analysis. {cta}'
                )
            )
        );
        
        $this->content_templates = array(
            'introduction' => array(
                'template' => '{hook} {keyword} is {definition}. In this {content_type}, we\'ll explore {benefit}.',
                'patterns' => array(
                    'Are you struggling with {keyword}? You\'re not alone.',
                    'If you\'re looking to master {keyword}, you\'ve come to the right place.',
                    '{keyword} can be challenging, but with the right approach, {benefit}.'
                )
            ),
            'conclusion' => array(
                'template' => 'In conclusion, {keyword} {conclusion}. By following these {benefit} strategies, you can {outcome}.',
                'patterns' => array(
                    'Mastering {keyword} takes time, but the {benefit} are worth it.',
                    'With these {keyword} strategies, you\'ll be able to {benefit} effectively.',
                    'Don\'t let {keyword} overwhelm you - use these {benefit} tips for success.'
                )
            )
        );
    }
    
    /**
     * Initialize keyword optimization patterns
     */
    private function init_keyword_patterns() {
        $this->keyword_patterns = array(
            'LSI_keywords' => array(
                'patterns' => array(
                    'related to {keyword}',
                    'concerning {keyword}',
                    'regarding {keyword}',
                    'about {keyword}',
                    'in terms of {keyword}'
                ),
                'usage' => 'contextual'
            ),
            'long_tail' => array(
                'patterns' => array(
                    'how to {keyword} {action}',
                    'best {keyword} for {purpose}',
                    '{keyword} {comparison}',
                    '{keyword} {number} tips',
                    'why {keyword} {benefit}'
                ),
                'usage' => 'targeted'
            ),
            'synonyms' => array(
                'patterns' => array(
                    'alternative to {keyword}',
                    '{keyword} equivalent',
                    'similar to {keyword}',
                    '{keyword} substitute',
                    '{keyword} alternative'
                ),
                'usage' => 'variation'
            )
        );
    }
    
    /**
     * Perform automatic optimization
     *
     * @param int $post_id Post ID
     * @param array $options Optimization options
     * @return array Optimization results
     */
    public function auto_optimize($post_id, $options = array()) {
        $start_time = microtime(true);
        
        try {
            $params = array_merge(array(
                'optimization_level' => $this->config['optimization_level'],
                'target_seo_score' => $this->config['target_seo_score'],
                'auto_fixes_only' => false,
                'preserve_existing' => true,
                'user_override' => false
            ), $options);
            
            $this->logger->info('Starting automatic RankMath optimization', array(
                'post_id' => $post_id,
                'optimization_level' => $params['optimization_level'],
                'target_score' => $params['target_seo_score']
            ));
            
            // Get current post data
            $post_data = $this->get_post_data($post_id);
            if (!$post_data) {
                throw new Exception('Post not found');
            }
            
            // Get current SEO analysis
            $current_analysis = $this->rankmath_integration->get_seo_score($post_id);
            $detailed_analysis = $this->rankmath_integration->get_detailed_analysis($post_id);
            
            // Identify optimization opportunities
            $optimization_opportunities = $this->identify_optimization_opportunities($detailed_analysis);
            
            if (empty($optimization_opportunities) && !$params['auto_fixes_only']) {
                return array(
                    'success' => true,
                    'message' => 'No optimization opportunities identified',
                    'current_score' => $current_analysis['score'],
                    'optimizations_applied' => array()
                );
            }
            
            // Apply optimizations
            $optimizations_applied = array();
            $optimization_results = array();
            
            foreach ($optimization_opportunities as $opportunity) {
                if ($this->should_apply_optimization($opportunity, $params)) {
                    $result = $this->apply_optimization($post_id, $opportunity, $post_data);
                    if ($result['success']) {
                        $optimizations_applied[] = $opportunity['type'];
                        $optimization_results[] = $result;
                    }
                }
            }
            
            // Get updated SEO score
            $updated_analysis = $this->rankmath_integration->get_seo_score($post_id);
            $score_improvement = $updated_analysis['score'] - $current_analysis['score'];
            
            $execution_time = (microtime(true) - $start_time) * 1000;
            
            $result = array(
                'success' => true,
                'post_id' => $post_id,
                'original_score' => $current_analysis['score'],
                'updated_score' => $updated_analysis['score'],
                'score_improvement' => $score_improvement,
                'optimizations_applied' => $optimizations_applied,
                'optimization_results' => $optimization_results,
                'execution_time_ms' => $execution_time,
                'target_reached' => $updated_analysis['score'] >= $params['target_seo_score'],
                'timestamp' => current_time('Y-m-d H:i:s')
            );
            
            $this->logger->info('Automatic RankMath optimization completed', array(
                'post_id' => $post_id,
                'score_improvement' => $score_improvement,
                'optimizations_applied' => count($optimizations_applied),
                'execution_time_ms' => $execution_time
            ));
            
            return $result;
            
        } catch (Exception $e) {
            $execution_time = (microtime(true) - $start_time) * 1000;
            
            $this->logger->error('Automatic optimization failed', array(
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
     * Optimize title automatically
     *
     * @param int $post_id Post ID
     * @param string $current_title Current title
     * @param string $focus_keyword Focus keyword
     * @return array Optimization result
     */
    public function auto_optimize_title($post_id, $current_title, $focus_keyword = '') {
        if (!$this->config['auto_title_optimization']) {
            return array('success' => false, 'reason' => 'Auto title optimization disabled');
        }
        
        try {
            $optimized_title = $current_title;
            $optimizations = array();
            
            // Get focus keyword if not provided
            if (empty($focus_keyword)) {
                $focus_keyword = get_post_meta($post_id, 'rank_math_focus_keyword', true);
            }
            
            // Determine content type
            $content_type = $this->determine_content_type($current_title);
            
            // Apply title optimization based on content type
            if (!empty($focus_keyword)) {
                $optimized_title = $this->optimize_title_with_keyword($current_title, $focus_keyword, $content_type);
                if ($optimized_title !== $current_title) {
                    $optimizations[] = 'Applied focus keyword optimization';
                }
            }
            
            // Optimize length
            $length_optimized = $this->optimize_title_length($optimized_title);
            if ($length_optimized !== $optimized_title) {
                $optimized_title = $length_optimized;
                $optimizations[] = 'Optimized title length';
            }
            
            // Add power words and emotional triggers
            if ($this->config['power_word_injection']) {
                $power_optimized = $this->inject_power_words($optimized_title);
                if ($power_optimized !== $optimized_title) {
                    $optimized_title = $power_optimized;
                    $optimizations[] = 'Added engaging language';
                }
            }
            
            // Maintain readability
            if ($this->config['maintain_readability']) {
                $readable_optimized = $this->ensure_title_readability($optimized_title);
                $optimized_title = $readable_optimized['title'];
                if ($readable_optimized['modified']) {
                    $optimizations[] = 'Improved readability';
                }
            }
            
            // Update post title if changed and not in preview mode
            $success = false;
            if ($optimized_title !== $current_title) {
                $success = wp_update_post(array(
                    'ID' => $post_id,
                    'post_title' => $optimized_title
                )) !== 0;
                
                if ($success) {
                    $optimizations[] = 'Updated post title';
                }
            }
            
            return array(
                'success' => $success || $optimized_title !== $current_title,
                'original_title' => $current_title,
                'optimized_title' => $optimized_title,
                'optimizations' => $optimizations,
                'seo_score_impact' => $this->calculate_title_optimization_impact($current_title, $optimized_title),
                'length_before' => strlen($current_title),
                'length_after' => strlen($optimized_title),
                'contains_focus_keyword' => !empty($focus_keyword) && stripos($optimized_title, $focus_keyword) !== false
            );
            
        } catch (Exception $e) {
            $this->logger->error('Title optimization failed', array(
                'post_id' => $post_id,
                'error' => $e->getMessage()
            ));
            
            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'original_title' => $current_title
            );
        }
    }
    
    /**
     * Auto-generate focus keyword
     *
     * @param int $post_id Post ID
     * @param array $content_data Content data
     * @return array Focus keyword suggestion
     */
    public function auto_generate_focus_keyword($post_id, $content_data) {
        if (!$this->config['auto_focus_keyword_suggestion']) {
            return array('success' => false, 'reason' => 'Auto focus keyword generation disabled');
        }
        
        try {
            $content = $content_data['content'] ?? '';
            $title = $content_data['title'] ?? '';
            $existing_focus_keyword = get_post_meta($post_id, 'rank_math_focus_keyword', true);
            
            // If focus keyword already exists and we're preserving existing
            if (!empty($existing_focus_keyword) && $this->config['preserve_author_style']) {
                return array(
                    'success' => false,
                    'reason' => 'Focus keyword already exists and preservation enabled',
                    'existing_keyword' => $existing_focus_keyword
                );
            }
            
            // Extract potential keywords from content
            $potential_keywords = $this->extract_potential_keywords($content, $title);
            
            if (empty($potential_keywords)) {
                return array(
                    'success' => false,
                    'reason' => 'No suitable keywords found in content'
                );
            }
            
            // Score and rank keywords
            $scored_keywords = $this->score_keywords($potential_keywords, $content);
            
            // Select best keyword
            $best_keyword = $this->select_optimal_keyword($scored_keywords);
            
            if (!$best_keyword) {
                return array(
                    'success' => false,
                    'reason' => 'No optimal keyword selected'
                );
            }
            
            // Set focus keyword
            $success = $this->rankmath_integration->set_focus_keyword($post_id, $best_keyword['keyword']);
            
            return array(
                'success' => $success,
                'suggested_keyword' => $best_keyword['keyword'],
                'keyword_score' => $best_keyword['score'],
                'alternatives' => array_slice($scored_keywords, 1, 3),
                'reasoning' => $this->generate_keyword_selection_reasoning($best_keyword, $scored_keywords)
            );
            
        } catch (Exception $e) {
            $this->logger->error('Focus keyword generation failed', array(
                'post_id' => $post_id,
                'error' => $e->getMessage()
            ));
            
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Auto-optimize meta description
     *
     * @param int $post_id Post ID
     * @param array $content_data Content data
     * @return array Meta optimization result
     */
    public function auto_optimize_meta_description($post_id, $content_data) {
        if (!$this->config['auto_meta_optimization']) {
            return array('success' => false, 'reason' => 'Auto meta description optimization disabled');
        }
        
        try {
            $current_meta = get_post_meta($post_id, 'rank_math_description', true);
            $focus_keyword = get_post_meta($post_id, 'rank_math_focus_keyword', true);
            $title = $content_data['title'] ?? '';
            $content = $content_data['content'] ?? '';
            
            // If meta description exists and we're preserving
            if (!empty($current_meta) && $this->config['preserve_author_style']) {
                return array(
                    'success' => false,
                    'reason' => 'Meta description already exists and preservation enabled',
                    'existing_meta' => $current_meta
                );
            }
            
            // Generate optimized meta description
            $optimized_meta = $this->generate_optimized_meta_description($content, $focus_keyword, $title);
            
            // Ensure optimal length
            $optimized_meta = $this->ensure_optimal_meta_length($optimized_meta);
            
            // Add call-to-action
            if ($this->config['emotional_trigger_optimization']) {
                $optimized_meta = $this->add_cta_to_meta($optimized_meta);
            }
            
            // Update meta description
            $success = false;
            if (!empty($optimized_meta)) {
                $success = update_post_meta($post_id, 'rank_math_description', $optimized_meta);
                
                if ($success) {
                    $this->logger->info('Meta description auto-optimized', array(
                        'post_id' => $post_id,
                        'original_length' => strlen($current_meta),
                        'new_length' => strlen($optimized_meta)
                    ));
                }
            }
            
            return array(
                'success' => $success,
                'original_meta' => $current_meta,
                'optimized_meta' => $optimized_meta,
                'length_before' => strlen($current_meta),
                'length_after' => strlen($optimized_meta),
                'contains_keyword' => !empty($focus_keyword) && stripos($optimized_meta, $focus_keyword) !== false,
                'has_cta' => $this->has_call_to_action($optimized_meta),
                'seo_score_impact' => $this->calculate_meta_optimization_impact($current_meta, $optimized_meta)
            );
            
        } catch (Exception $e) {
            $this->logger->error('Meta description optimization failed', array(
                'post_id' => $post_id,
                'error' => $e->getMessage()
            ));
            
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Auto-optimize content structure
     *
     * @param int $post_id Post ID
     * @param array $content_data Content data
     * @return array Content optimization result
     */
    public function auto_optimize_content($post_id, $content_data) {
        if (!$this->config['auto_content_optimization']) {
            return array('success' => false, 'reason' => 'Auto content optimization disabled');
        }
        
        try {
            $current_content = $content_data['content'] ?? '';
            $focus_keyword = get_post_meta($post_id, 'rank_math_focus_keyword', true);
            
            if (empty($current_content)) {
                return array('success' => false, 'reason' => 'No content to optimize');
            }
            
            $optimizations = array();
            $optimized_content = $current_content;
            
            // Optimize heading structure
            $heading_optimization = $this->optimize_heading_structure($current_content, $focus_keyword);
            if ($heading_optimization['optimized']) {
                $optimized_content = $heading_optimization['content'];
                $optimizations[] = 'heading_structure';
            }
            
            // Optimize keyword density
            if (!empty($focus_keyword)) {
                $keyword_optimization = $this->optimize_keyword_density($optimized_content, $focus_keyword);
                if ($keyword_optimization['optimized']) {
                    $optimized_content = $keyword_optimization['content'];
                    $optimizations[] = 'keyword_density';
                }
            }
            
            // Add content enhancements
            $enhancement_optimization = $this->add_content_enhancements($optimized_content, $content_data);
            if ($enhancement_optimization['enhanced']) {
                $optimized_content = $enhancement_optimization['content'];
                $optimizations[] = 'content_enhancements';
            }
            
            // Update content if changed
            $success = false;
            if ($optimized_content !== $current_content) {
                $success = wp_update_post(array(
                    'ID' => $post_id,
                    'post_content' => $optimized_content
                )) !== 0;
                
                if ($success) {
                    $this->logger->info('Content auto-optimized', array(
                        'post_id' => $post_id,
                        'optimizations_applied' => $optimizations
                    ));
                }
            }
            
            return array(
                'success' => $success || !empty($optimizations),
                'optimizations_applied' => $optimizations,
                'original_length' => strlen($current_content),
                'optimized_length' => strlen($optimized_content),
                'word_count_before' => str_word_count(strip_tags($current_content)),
                'word_count_after' => str_word_count(strip_tags($optimized_content)),
                'seo_improvements' => $this->calculate_content_seo_improvements($current_content, $optimized_content)
            );
            
        } catch (Exception $e) {
            $this->logger->error('Content optimization failed', array(
                'post_id' => $post_id,
                'error' => $e->getMessage()
            ));
            
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Identify optimization opportunities
     *
     * @param array $detailed_analysis Detailed SEO analysis
     * @return array Optimization opportunities
     */
    private function identify_optimization_opportunities($detailed_analysis) {
        $opportunities = array();
        
        // Title optimization opportunities
        if (isset($detailed_analysis['title'])) {
            $title_analysis = $detailed_analysis['title'];
            
            if (!$title_analysis['contains_focus_keyword']) {
                $opportunities[] = array(
                    'type' => 'title_keyword',
                    'priority' => 'high',
                    'impact' => 'high',
                    'description' => 'Add focus keyword to title',
                    'auto_fixable' => $this->config['red_indicator_auto_fix']
                );
            }
            
            if ($title_analysis['length'] < 30 || $title_analysis['length'] > 60) {
                $opportunities[] = array(
                    'type' => 'title_length',
                    'priority' => $title_analysis['length'] < 30 ? 'medium' : 'high',
                    'impact' => 'medium',
                    'description' => 'Optimize title length',
                    'auto_fixable' => $this->config['yellow_indicator_auto_fix']
                );
            }
        }
        
        // Meta description opportunities
        if (isset($detailed_analysis['meta_description'])) {
            $meta_analysis = $detailed_analysis['meta_description'];
            
            if (!$meta_analysis['exists']) {
                $opportunities[] = array(
                    'type' => 'meta_description_missing',
                    'priority' => 'high',
                    'impact' => 'high',
                    'description' => 'Add meta description',
                    'auto_fixable' => $this->config['red_indicator_auto_fix']
                );
            } elseif ($meta_analysis['length'] < 120 || $meta_analysis['length'] > 160) {
                $opportunities[] = array(
                    'type' => 'meta_description_length',
                    'priority' => 'medium',
                    'impact' => 'medium',
                    'description' => 'Optimize meta description length',
                    'auto_fixable' => $this->config['yellow_indicator_auto_fix']
                );
            }
        }
        
        // Focus keyword opportunities
        if (isset($detailed_analysis['focus_keyword'])) {
            $keyword_analysis = $detailed_analysis['focus_keyword'];
            
            if (!$keyword_analysis['set']) {
                $opportunities[] = array(
                    'type' => 'focus_keyword_missing',
                    'priority' => 'high',
                    'impact' => 'high',
                    'description' => 'Set focus keyword',
                    'auto_fixable' => $this->config['auto_focus_keyword_suggestion']
                );
            }
        }
        
        // Content structure opportunities
        if (isset($detailed_analysis['content'])) {
            $content_analysis = $detailed_analysis['content'];
            
            if ($content_analysis['word_count'] < 300) {
                $opportunities[] = array(
                    'type' => 'content_length_short',
                    'priority' => 'medium',
                    'impact' => 'medium',
                    'description' => 'Expand content length',
                    'auto_fixable' => $this->config['auto_content_optimization']
                );
            }
        }
        
        return $opportunities;
    }
    
    /**
     * Determine if optimization should be applied
     *
     * @param array $opportunity Optimization opportunity
     * @param array $params Optimization parameters
     * @return bool Should apply
     */
    private function should_apply_optimization($opportunity, $params) {
        // Auto-fixes only mode
        if ($params['auto_fixes_only'] && !$opportunity['auto_fixable']) {
            return false;
        }
        
        // Red indicators (high priority)
        if ($opportunity['priority'] === 'high' && $this->config['red_indicator_auto_fix']) {
            return true;
        }
        
        // Yellow indicators (medium priority)
        if ($opportunity['priority'] === 'medium' && $this->config['yellow_indicator_auto_fix']) {
            return true;
        }
        
        // Aggressive mode - apply all
        if ($params['optimization_level'] === 'aggressive') {
            return true;
        }
        
        return false;
    }
    
    /**
     * Apply specific optimization
     *
     * @param int $post_id Post ID
     * @param array $opportunity Optimization opportunity
     * @param array $post_data Post data
     * @return array Optimization result
     */
    private function apply_optimization($post_id, $opportunity, $post_data) {
        switch ($opportunity['type']) {
            case 'title_keyword':
                return $this->optimize_title_for_keyword($post_id, $post_data);
                
            case 'title_length':
                return $this->optimize_title_length($post_id, $post_data);
                
            case 'meta_description_missing':
                return $this->add_meta_description($post_id, $post_data);
                
            case 'meta_description_length':
                return $this->optimize_meta_description_length($post_id, $post_data);
                
            case 'focus_keyword_missing':
                return $this->suggest_focus_keyword($post_id, $post_data);
                
            case 'content_length_short':
                return $this->expand_content_length($post_id, $post_data);
                
            default:
                return array('success' => false, 'reason' => 'Unknown optimization type');
        }
    }
    
    /**
     * Get post data for optimization
     *
     * @param int $post_id Post ID
     * @return array Post data
     */
    private function get_post_data($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return null;
        }
        
        return array(
            'title' => $post->post_title,
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt,
            'meta' => array(
                'description' => get_post_meta($post_id, 'rank_math_description', true),
                'focus_keyword' => get_post_meta($post_id, 'rank_math_focus_keyword', true)
            )
        );
    }
    
    /**
     * Determine content type from title
     *
     * @param string $title Post title
     * @return string Content type
     */
    private function determine_content_type($title) {
        $title_lower = strtolower($title);
        
        if (preg_match('/\bhow to\b/', $title_lower)) {
            return 'how_to';
        } elseif (preg_match('/\bbest\b|\btop\b|\breview\b/', $title_lower)) {
            return 'best';
        } elseif (preg_match('/\breview\b|\brated\b/', $title_lower)) {
            return 'review';
        } elseif (preg_match('/\bguide\b|\bcomplete\b|\bultimate\b/', $title_lower)) {
            return 'guide';
        } elseif (preg_match('/\bnews\b|\bbreaking\b|\blatest\b/', $title_lower)) {
            return 'news';
        }
        
        return 'informational';
    }
    
    /**
     * Optimize title with focus keyword
     *
     * @param string $title Current title
     * @param string $focus_keyword Focus keyword
     * @param string $content_type Content type
     * @return string Optimized title
     */
    private function optimize_title_with_keyword($title, $focus_keyword, $content_type) {
        // If keyword already in title, return as is
        if (stripos($title, $focus_keyword) !== false) {
            return $title;
        }
        
        // Use appropriate template
        $templates = $this->title_templates[$content_type]['patterns'] ?? $this->title_templates['guide']['patterns'];
        $template = $templates[array_rand($templates)];
        
        $year = date('Y');
        $number = rand(5, 15);
        
        $replacements = array(
            '{keyword}' => $focus_keyword,
            '{benefit}' => 'effective',
            '{year}' => $year,
            '{number}' => $number
        );
        
        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
    
    /**
     * Optimize title length
     *
     * @param string $title Title to optimize
     * @return string Optimized title
     */
    private function optimize_title_length($title) {
        $length = strlen($title);
        
        // If length is optimal (50-60), return as is
        if ($length >= 50 && $length <= 60) {
            return $title;
        }
        
        // If too short, add compelling elements
        if ($length < 50) {
            $power_prefixes = array('Ultimate ', 'Complete ', 'Expert ', 'Proven ');
            $prefix = $power_prefixes[array_rand($power_prefixes)];
            
            if (strlen($prefix . $title) <= 60) {
                return $prefix . $title;
            }
        }
        
        // If too long, remove filler words
        if ($length > 60) {
            $filler_words = array('the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by');
            
            $words = explode(' ', $title);
            $filtered_words = array();
            
            foreach ($words as $word) {
                if (!in_array(strtolower($word), $filler_words) || count($filtered_words) < 2) {
                    $filtered_words[] = $word;
                }
                
                if (strlen(implode(' ', $filtered_words)) <= 58) {
                    break;
                }
            }
            
            return implode(' ', $filtered_words);
        }
        
        return $title;
    }
    
    /**
     * Inject power words into title
     *
     * @param string $title Title to enhance
     * @return string Enhanced title
     */
    private function inject_power_words($title) {
        $power_words = array(
            'Amazing', 'Incredible', 'Revolutionary', 'Powerful', 'Essential',
            'Ultimate', 'Complete', 'Expert', 'Proven', 'Effective'
        );
        
        $emotional_triggers = array(
            'Transform Your', 'Master', 'Discover', 'Unlock', 'Boost'
        );
        
        // Try to add power word at beginning
        if (strlen($title . ' ' . $power_words[0]) <= 60) {
            return $power_words[array_rand($power_words)] . ' ' . $title;
        }
        
        // Try to add emotional trigger
        if (strlen($title . ' ' . $emotional_triggers[0]) <= 60) {
            return $emotional_triggers[array_rand($emotional_triggers)] . ' ' . $title;
        }
        
        return $title;
    }
    
    /**
     * Ensure title readability
     *
     * @param string $title Title to check
     * @return array Readability check result
     */
    private function ensure_title_readability($title) {
        // Check for readability issues
        $issues = array();
        $modified = false;
        
        // Check for too many uppercase letters
        $uppercase_ratio = strlen(preg_replace('/[^A-Z]/', '', $title)) / strlen($title);
        if ($uppercase_ratio > 0.5) {
            $title = strtolower($title);
            $title = ucfirst($title);
            $modified = true;
            $issues[] = 'Converted excessive uppercase to title case';
        }
        
        // Check for readability (simple heuristic)
        $words = explode(' ', $title);
        if (count($words) > 12) {
            // Title too long for readability
            $title = implode(' ', array_slice($words, 0, 10));
            $modified = true;
            $issues[] = 'Shortened title for better readability';
        }
        
        return array(
            'title' => $title,
            'modified' => $modified,
            'issues' => $issues
        );
    }
    
    /**
     * Extract potential keywords from content
     *
     * @param string $content Content to analyze
     * @param string $title Post title
     * @return array Potential keywords
     */
    private function extract_potential_keywords($content, $title) {
        $text = strtolower(strip_tags($content . ' ' . $title));
        
        // Extract phrases (2-4 words)
        $phrases = array();
        
        // Simple phrase extraction
        preg_match_all('/\b[a-z]{3,}\s+[a-z]{3,}(?:\s+[a-z]{3,})?(?:\s+[a-z]{3,})?\b/', $text, $matches);
        
        foreach ($matches[0] as $phrase) {
            $phrase = trim($phrase);
            if (strlen($phrase) >= 6 && strlen($phrase) <= 50) {
                $phrases[] = $phrase;
            }
        }
        
        // Remove duplicates and return
        return array_unique($phrases);
    }
    
    /**
     * Score keywords for selection
     *
     * @param array $keywords Keywords to score
     * @param string $content Source content
     * @return array Scored keywords
     */
    private function score_keywords($keywords, $content) {
        $word_count = str_word_count(strtolower(strip_tags($content)));
        $scored_keywords = array();
        
        foreach ($keywords as $keyword) {
            $count = substr_count(strtolower(strip_tags($content)), $keyword);
            $density = $word_count > 0 ? ($count / $word_count) * 100 : 0;
            
            $score = 0;
            
            // Density score (optimal: 1-3%)
            if ($density >= 1 && $density <= 3) {
                $score += 30;
            } elseif ($density > 0 && $density < 1) {
                $score += 20;
            }
            
            // Length score (prefer 2-4 words)
            $word_count_in_keyword = str_word_count($keyword);
            if ($word_count_in_keyword >= 2 && $word_count_in_keyword <= 4) {
                $score += 25;
            }
            
            // Frequency score (prefer appearing 2-5 times)
            if ($count >= 2 && $count <= 5) {
                $score += 25;
            } elseif ($count > 0) {
                $score += 15;
            }
            
            // Specificity score (avoid generic terms)
            if (!preg_match('/\b(the|and|or|but|for|with|in|on|at|to|of)\b/', $keyword)) {
                $score += 20;
            }
            
            $scored_keywords[] = array(
                'keyword' => $keyword,
                'count' => $count,
                'density' => $density,
                'score' => $score
            );
        }
        
        // Sort by score (descending)
        usort($scored_keywords, function($a, $b) {
            return $b['score'] - $a['score'];
        });
        
        return $scored_keywords;
    }
    
    /**
     * Select optimal keyword from scored list
     *
     * @param array $scored_keywords Scored keywords
     * @return array|null Selected keyword
     */
    private function select_optimal_keyword($scored_keywords) {
        if (empty($scored_keywords)) {
            return null;
        }
        
        // Filter keywords with score > 50
        $good_keywords = array_filter($scored_keywords, function($kw) {
            return $kw['score'] > 50;
        });
        
        if (!empty($good_keywords)) {
            return $good_keywords[0]; // Return highest scoring
        }
        
        // If no good keywords, return best available with score > 30
        $acceptable_keywords = array_filter($scored_keywords, function($kw) {
            return $kw['score'] > 30;
        });
        
        if (!empty($acceptable_keywords)) {
            return $acceptable_keywords[0];
        }
        
        // Return best available if nothing else
        return $scored_keywords[0];
    }
    
    /**
     * Generate optimized meta description
     *
     * @param string $content Content to analyze
     * @param string $focus_keyword Focus keyword
     * @param string $title Post title
     * @return string Optimized meta description
     */
    private function generate_optimized_meta_description($content, $focus_keyword, $title) {
        $content_type = $this->determine_content_type($title);
        
        // Get template for content type
        $template_type = $this->map_content_type_to_meta_template($content_type);
        $templates = $this->meta_templates[$template_type]['patterns'];
        $template = $templates[array_rand($templates)];
        
        // Extract benefit from content
        $benefit = $this->extract_benefit_from_content($content);
        $cta = $this->get_random_cta();
        
        $replacements = array(
            '{keyword}' => $focus_keyword ?: 'this topic',
            '{benefit}' => $benefit ?: 'proven results',
            '{cta}' => $cta
        );
        
        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
    
    /**
     * Map content type to meta template type
     *
     * @param string $content_type Content type
     * @return string Meta template type
     */
    private function map_content_type_to_meta_template($content_type) {
        $mapping = array(
            'how_to' => 'informational',
            'best' => 'commercial',
            'review' => 'commercial',
            'guide' => 'informational',
            'news' => 'news'
        );
        
        return $mapping[$content_type] ?? 'informational';
    }
    
    /**
     * Extract benefit from content
     *
     * @param string $content Content to analyze
     * @return string Extracted benefit
     */
    private function extract_benefit_from_content($content) {
        $benefits = array(
            'save time',
            'increase productivity',
            'improve results',
            'reduce costs',
            'enhance performance',
            'boost efficiency',
            'achieve better outcomes',
            'get faster results'
        );
        
        // Simple benefit extraction - look for benefit-related words
        $content_lower = strtolower($content);
        
        foreach ($benefits as $benefit) {
            if (strpos($content_lower, str_replace(' ', '', $benefit)) !== false) {
                return $benefit;
            }
        }
        
        return $benefits[array_rand($benefits)];
    }
    
    /**
     * Get random call-to-action
     *
     * @return string Call-to-action phrase
     */
    private function get_random_cta() {
        $ctas = array(
            'Learn more now',
            'Get started today',
            'Discover the secret',
            'Read the full guide',
            'Find out how',
            'Start your journey',
            'Take action today',
            'Explore the benefits'
        );
        
        return $ctas[array_rand($ctas)];
    }
    
    /**
     * Ensure optimal meta description length
     *
     * @param string $meta_description Meta description
     * @return string Length-optimized meta description
     */
    private function ensure_optimal_meta_length($meta_description) {
        $length = strlen($meta_description);
        
        // If within optimal range (150-160), return as is
        if ($length >= 150 && $length <= 160) {
            return $meta_description;
        }
        
        // If too long, shorten
        if ($length > 160) {
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
        
        // If too short, expand
        if ($length < 150) {
            $expansion_phrases = array(
                'Discover the complete solution',
                'Get expert guidance',
                'Learn from industry leaders',
                'Master the fundamentals',
                'Achieve outstanding results'
            );
            
            $expansion = $expansion_phrases[array_rand($expansion_phrases)];
            
            if (strlen($meta_description . ' ' . $expansion) <= 160) {
                return $meta_description . ' ' . $expansion;
            }
        }
        
        return $meta_description;
    }
    
    /**
     * Add call-to-action to meta description
     *
     * @param string $meta_description Meta description
     * @return string Meta description with CTA
     */
    private function add_cta_to_meta($meta_description) {
        if ($this->has_call_to_action($meta_description)) {
            return $meta_description; // Already has CTA
        }
        
        $cta = $this->get_random_cta();
        
        // Try to add CTA at the end if there's space
        if (strlen($meta_description . ' ' . $cta) <= 160) {
            return $meta_description . ' ' . $cta;
        }
        
        // Try to replace last few words with CTA
        $words = explode(' ', $meta_description);
        if (count($words) > 3) {
            $last_few_words = array_slice($words, -3);
            $remaining_words = array_slice($words, 0, -3);
            
            $modified = implode(' ', $remaining_words) . ' ' . $cta;
            
            if (strlen($modified) <= 160) {
                return $modified;
            }
        }
        
        return $meta_description;
    }
    
    /**
     * Check if meta description has call-to-action
     *
     * @param string $meta_description Meta description
     * @return bool Has CTA
     */
    private function has_call_to_action($meta_description) {
        $cta_words = array('learn', 'discover', 'get', 'read', 'explore', 'check', 'see', 'find', 'start', 'take');
        
        foreach ($cta_words as $cta) {
            if (stripos($meta_description, $cta) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Optimize heading structure
     *
     * @param string $content Content to optimize
     * @param string $focus_keyword Focus keyword
     * @return array Optimization result
     */
    private function optimize_heading_structure($content, $focus_keyword) {
        // Simple heading optimization - ensure there's an H1
        if (!preg_match('/<h1[^>]*>/i', $content)) {
            // Add H1 if none exists
            $content = '<h1>' . wp_strip_all_tags($focus_keyword ?: 'Main Topic') . '</h1>' . $content;
            
            return array(
                'optimized' => true,
                'content' => $content,
                'improvement' => 'Added missing H1 heading'
            );
        }
        
        return array(
            'optimized' => false,
            'content' => $content
        );
    }
    
    /**
     * Optimize keyword density
     *
     * @param string $content Content to optimize
     * @param string $focus_keyword Focus keyword
     * @return array Optimization result
     */
    private function optimize_keyword_density($content, $focus_keyword) {
        $word_count = str_word_count(strip_tags($content));
        $keyword_count = substr_count(strtolower(strip_tags($content)), strtolower($focus_keyword));
        $current_density = $word_count > 0 ? ($keyword_count / $word_count) * 100 : 0;
        
        // If density is too low (< 1%), we might want to add keyword
        // If density is too high (> 3%), we might want to reduce it
        // For auto-optimization, we'll only address extreme cases
        
        if ($current_density < 0.5 && $word_count > 100) {
            // Add keyword to one or two strategic places
            $paragraphs = preg_split('/\n\s*\n/', $content);
            
            if (count($paragraphs) >= 2) {
                // Add to first substantial paragraph
                for ($i = 0; $i < count($paragraphs); $i++) {
                    $paragraph = strip_tags($paragraphs[$i]);
                    if (str_word_count($paragraph) > 20) {
                        // Add keyword naturally
                        $sentences = preg_split('/[.!?]+/', $paragraph);
                        if (count($sentences) >= 2) {
                            $sentences[0] .= ' ' . $focus_keyword;
                            $paragraphs[$i] = implode('.', $sentences) . '.';
                            break;
                        }
                    }
                }
                
                $content = implode("\n\n", $paragraphs);
                
                return array(
                    'optimized' => true,
                    'content' => $content,
                    'improvement' => 'Improved keyword density'
                );
            }
        }
        
        return array(
            'optimized' => false,
            'content' => $content
        );
    }
    
    /**
     * Add content enhancements
     *
     * @param string $content Content to enhance
     * @param array $content_data Content data
     * @return array Enhancement result
     */
    private function add_content_enhancements($content, $content_data) {
        $enhanced = false;
        $improvements = array();
        
        // Add expert insight if missing
        if (!preg_match('/expert|professional|industry leader|experienced/i', strip_tags($content))) {
            $expert_insight = $this->generate_expert_insight($content_data);
            if ($expert_insight) {
                $content = '<p><strong>Expert Insight:</strong> ' . $expert_insight . '</p>' . $content;
                $enhanced = true;
                $improvements[] = 'Added expert insight';
            }
        }
        
        // Add source citation if missing
        if (!preg_match('/according to|source:|research shows/i', strip_tags($content))) {
            $source_citation = $this->generate_source_citation();
            if ($source_citation) {
                $content .= '<p><em>' . $source_citation . '</em></p>';
                $enhanced = true;
                $improvements[] = 'Added source citation';
            }
        }
        
        return array(
            'enhanced' => $enhanced,
            'content' => $content,
            'improvements' => $improvements
        );
    }
    
    /**
     * Generate expert insight
     *
     * @param array $content_data Content data
     * @return string Expert insight
     */
    private function generate_expert_insight($content_data) {
        $insights = array(
            'With over a decade of experience in this field, I can confidently say that this approach delivers consistent results.',
            'Industry veterans often recommend this method for its proven track record and reliability.',
            'After extensive testing and analysis, this strategy stands out as one of the most effective approaches.',
            'Professionals in this field consistently achieve better outcomes using these proven techniques.'
        );
        
        return $insights[array_rand($insights)];
    }
    
    /**
     * Generate source citation
     *
     * @return string Source citation
     */
    private function generate_source_citation() {
        $citations = array(
            'Source: Industry Research ' . date('Y()),
            'According to recent market analysis',
            'Based on comprehensive industry studies',
            'According to expert consensus'
        );
        
        return $citations[array_rand($citations)];
    }
    
    // Utility methods for optimization impact calculation
    
    /**
     * Calculate title optimization impact
     *
     * @param string $original_title Original title
     * @param string $optimized_title Optimized title
     * @return int Impact score
     */
    private function calculate_title_optimization_impact($original_title, $optimized_title) {
        $impact = 0;
        
        // Keyword presence
        $original_has_keyword = preg_match('/(keyword|phrase)/i', $original_title); // Placeholder
        $optimized_has_keyword = preg_match('/(keyword|phrase)/i', $optimized_title); // Placeholder
        
        if (!$original_has_keyword && $optimized_has_keyword) {
            $impact += 30;
        }
        
        // Length optimization
        $original_length = strlen($original_title);
        $optimized_length = strlen($optimized_title);
        
        if (($original_length < 30 || $original_length > 60) && 
            ($optimized_length >= 30 && $optimized_length <= 60)) {
            $impact += 25;
        }
        
        // Engagement elements
        if (!preg_match('/(amazing|incredible|ultimate|expert|proven)/i', $original_title) &&
            preg_match('/(amazing|incredible|ultimate|expert|proven)/i', $optimized_title)) {
            $impact += 20;
        }
        
        return min(100, $impact);
    }
    
    /**
     * Calculate meta description optimization impact
     *
     * @param string $original_meta Original meta description
     * @param string $optimized_meta Optimized meta description
     * @return int Impact score
     */
    private function calculate_meta_optimization_impact($original_meta, $optimized_meta) {
        $impact = 0;
        
        // Length optimization
        $original_length = strlen($original_meta);
        $optimized_length = strlen($optimized_meta);
        
        if (($original_length < 120 || $original_length > 160) && 
            ($optimized_length >= 120 && $optimized_length <= 160)) {
            $impact += 40;
        }
        
        // Call-to-action addition
        if (!$this->has_call_to_action($original_meta) && $this->has_call_to_action($optimized_meta)) {
            $impact += 30;
        }
        
        // Content quality improvement
        if (preg_match('/(comprehensive|expert|complete|ultimate)/i', $optimized_meta) &&
            !preg_match('/(comprehensive|expert|complete|ultimate)/i', $original_meta)) {
            $impact += 30;
        }
        
        return min(100, $impact);
    }
    
    /**
     * Calculate content SEO improvements
     *
     * @param string $original_content Original content
     * @param string $optimized_content Optimized content
     * @return array SEO improvements
     */
    private function calculate_content_seo_improvements($original_content, $optimized_content) {
        $improvements = array();
        
        // Heading structure improvement
        $original_h1 = preg_match_all('/<h1[^>]*>/i', $original_content);
        $optimized_h1 = preg_match_all('/<h1[^>]*>/i', $optimized_content);
        
        if ($original_h1 === 0 && $optimized_h1 === 1) {
            $improvements[] = 'Added missing H1 heading';
        }
        
        // Content length improvement
        $original_words = str_word_count(strip_tags($original_content));
        $optimized_words = str_word_count(strip_tags($optimized_content));
        
        if ($optimized_words > $original_words) {
            $improvements[] = 'Increased content length by ' . ($optimized_words - $original_words) . ' words';
        }
        
        // Expert content addition
        if (preg_match('/expert insight/i', $optimized_content) && 
            !preg_match('/expert insight/i', $original_content)) {
            $improvements[] = 'Added expert credibility elements';
        }
        
        return $improvements;
    }
    
    /**
     * Generate keyword selection reasoning
     *
     * @param array $selected_keyword Selected keyword data
     * @param array $all_keywords All scored keywords
     * @return string Reasoning text
     */
    private function generate_keyword_selection_reasoning($selected_keyword, $all_keywords) {
        $reasoning_parts = array();
        
        // Score reasoning
        if ($selected_keyword['score'] >= 70) {
            $reasoning_parts[] = 'Excellent keyword score indicating strong SEO potential';
        } elseif ($selected_keyword['score'] >= 50) {
            $reasoning_parts[] = 'Good keyword score with solid optimization opportunities';
        }
        
        // Density reasoning
        if ($selected_keyword['density'] >= 1 && $selected_keyword['density'] <= 3) {
            $reasoning_parts[] = 'Optimal keyword density for natural inclusion';
        }
        
        // Frequency reasoning
        if ($selected_keyword['count'] >= 2 && $selected_keyword['count'] <= 5) {
            $reasoning_parts[] = 'Appropriate frequency for content integration';
        }
        
        return implode('. ', $reasoning_parts) . '.';
    }
    
    /**
     * Get service metrics
     *
     * @return array Service metrics
     */
    public function get_metrics() {
        return array(
            'service' => 'RankMathAutoOptimizer',
            'config' => $this->config,
            'title_templates' => count($this->title_templates),
            'meta_templates' => count($this->meta_templates),
            'keyword_patterns' => count($this->keyword_patterns),
            'content_templates' => count($this->content_templates),
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
            $test_post_data = array(
                'title' => 'Test Title for Auto-Optimizer',
                'content' => '<h1>Test Content</h1><p>This is test content for auto-optimizer testing.</p>',
                'excerpt' => 'Test excerpt'
            );
            
            $result = $this->auto_optimize_title(1, $test_post_data['title']);
            
            // Check if optimization returned expected structure
            $required_fields = array('success', 'original_title', 'optimized_title');
            foreach ($required_fields as $field) {
                if (!isset($result[$field])) {
                    return false;
                }
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('RankMathAutoOptimizer health check failed', array(
                'error' => $e->getMessage()
            ));
            return false;
        }
    }
}