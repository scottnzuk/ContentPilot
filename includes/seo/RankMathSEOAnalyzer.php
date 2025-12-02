<?php
/**
 * RankMath SEO Analyzer for Comprehensive SEO Analysis
 *
 * Provides detailed SEO analysis using RankMath's scoring algorithms,
 * SEO audit integration, indicator handling, and optimization recommendations.
 *
 * @package AI_Auto_News_Poster\SEO
 * @since 1.3.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * RankMath SEO Analyzer Class
 */
class AANP_RankMathSEOAnalyzer {
    
    /**
     * RankMath integration instance
     * @var AANP_RankMathIntegration
     */
    private $rankmath_integration;
    
    /**
     * Auto-optimizer instance
     * @var AANP_RankMathAutoOptimizer
     */
    private $auto_optimizer;
    
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
     * Analyzer configuration
     * @var array
     */
    private $config = array();
    
    /**
     * RankMath scoring algorithms
     * @var array
     */
    private $scoring_algorithms = array();
    
    /**
     * SEO audit checklist
     * @var array
     */
    private $audit_checklist = array();
    
    /**
     * Indicator thresholds
     * @var array
     */
    private $indicator_thresholds = array();
    
    /**
     * Optimization recommendations database
     * @var array
     */
    private $recommendations_db = array();
    
    /**
     * Constructor
     *
     * @param AANP_RankMathIntegration $rankmath_integration
     * @param AANP_RankMathAutoOptimizer $auto_optimizer
     * @param AANP_ContentAnalyzer $content_analyzer
     * @param AANP_AdvancedCacheManager $cache_manager
     */
    public function __construct(
        AANP_RankMathIntegration $rankmath_integration = null,
        AANP_RankMathAutoOptimizer $auto_optimizer = null,
        AANP_ContentAnalyzer $content_analyzer = null,
        AANP_AdvancedCacheManager $cache_manager = null
    ) {
        $this->logger = AANP_Logger::getInstance();
        $this->rankmath_integration = $rankmath_integration ?: new AANP_RankMathIntegration();
        $this->auto_optimizer = $auto_optimizer ?: new AANP_RankMathAutoOptimizer();
        $this->content_analyzer = $content_analyzer ?: new AANP_ContentAnalyzer();
        $this->cache_manager = $cache_manager ?: new AANP_AdvancedCacheManager();
        
        $this->init_config();
        $this->init_scoring_algorithms();
        $this->init_audit_checklist();
        $this->init_indicator_thresholds();
        $this->init_recommendations_db();
    }
    
    /**
     * Initialize analyzer configuration
     */
    private function init_config() {
        $options = get_option('aanp_settings', array());
        
        $this->config = array(
            'real_time_analysis' => isset($options['real_time_seo_analysis']) ? (bool) $options['real_time_seo_analysis'] : true,
            'auto_audit' => isset($options['auto_seo_audit']) ? (bool) $options['auto_seo_audit'] : true,
            'indicator_monitoring' => isset($options['seo_indicator_monitoring']) ? (bool) $options['seo_indicator_monitoring'] : true,
            'recommendation_engine' => isset($options['seo_recommendation_engine']) ? (bool) $options['seo_recommendation_engine'] : true,
            'score_tracking' => isset($options['seo_score_tracking']) ? (bool) $options['seo_score_tracking'] : true,
            'competitor_analysis' => isset($options['competitor_seo_analysis']) ? (bool) $options['competitor_seo_analysis'] : false,
            'advanced_metrics' => isset($options['advanced_seo_metrics']) ? (bool) $options['advanced_seo_metrics'] : true,
            'bulk_analysis' => isset($options['bulk_seo_analysis']) ? (bool) $options['bulk_seo_analysis'] : true,
            'analysis_depth' => isset($options['seo_analysis_depth']) ? $options['seo_analysis_depth'] : 'comprehensive', // basic, standard, comprehensive
            'auto_optimization_triggers' => isset($options['auto_optimization_triggers']) ? (bool) $options['auto_optimization_triggers'] : true,
            'score_threshold_green' => isset($options['score_threshold_green']) ? intval($options['score_threshold_green']) : 80,
            'score_threshold_yellow' => isset($options['score_threshold_yellow']) ? intval($options['score_threshold_yellow']) : 60
        );
    }
    
    /**
     * Initialize RankMath scoring algorithms
     */
    private function init_scoring_algorithms() {
        $this->scoring_algorithms = array(
            'title_optimization' => array(
                'weight' => 20,
                'factors' => array(
                    'keyword_in_title' => 40,
                    'title_length' => 25,
                    'title_case' => 15,
                    'power_words' => 10,
                    'emotional_triggers' => 10
                )
            ),
            'meta_description' => array(
                'weight' => 15,
                'factors' => array(
                    'meta_exists' => 30,
                    'meta_length' => 25,
                    'keyword_in_meta' => 20,
                    'call_to_action' => 15,
                    'compelling_copy' => 10
                )
            ),
            'content_quality' => array(
                'weight' => 25,
                'factors' => array(
                    'content_length' => 30,
                    'readability_score' => 25,
                    'keyword_density' => 20,
                    'content_structure' => 15,
                    'originality' => 10
                )
            ),
            'keyword_optimization' => array(
                'weight' => 20,
                'factors' => array(
                    'focus_keyword_set' => 35,
                    'keyword_in_headings' => 25,
                    'keyword_distribution' => 20,
                    'long_tail_keywords' => 10,
                    'keyword_variations' => 10
                )
            ),
            'technical_seo' => array(
                'weight' => 10,
                'factors' => array(
                    'url_structure' => 30,
                    'image_optimization' => 25,
                    'internal_linking' => 25,
                    'page_speed_indicators' => 20
                )
            ),
            'user_experience' => array(
                'weight' => 10,
                'factors' => array(
                    'mobile_friendliness' => 30,
                    'page_structure' => 25,
                    'scannability' => 25,
                    'engagement_elements' => 20
                )
            )
        );
    }
    
    /**
     * Initialize SEO audit checklist
     */
    private function init_audit_checklist() {
        $this->audit_checklist = array(
            'critical' => array(
                array(
                    'id' => 'focus_keyword_missing',
                    'title' => 'Focus Keyword Not Set',
                    'description' => 'No focus keyword has been defined for this content.',
                    'impact' => 'high',
                    'category' => 'keyword_optimization',
                    'auto_fixable' => true,
                    'fix_difficulty' => 'easy'
                ),
                array(
                    'id' => 'title_missing',
                    'title' => 'Title Missing or Empty',
                    'description' => 'Content lacks a proper title.',
                    'impact' => 'high',
                    'category' => 'title_optimization',
                    'auto_fixable' => false,
                    'fix_difficulty' => 'manual'
                ),
                array(
                    'id' => 'meta_description_missing',
                    'title' => 'Meta Description Missing',
                    'description' => 'No meta description found for this content.',
                    'impact' => 'high',
                    'category' => 'meta_description',
                    'auto_fixable' => true,
                    'fix_difficulty' => 'easy'
                ),
                array(
                    'id' => 'content_too_short',
                    'title' => 'Content Too Short',
                    'description' => 'Content length is insufficient for proper SEO.',
                    'impact' => 'high',
                    'category' => 'content_quality',
                    'auto_fixable' => true,
                    'fix_difficulty' => 'medium'
                )
            ),
            'important' => array(
                array(
                    'id' => 'title_too_long',
                    'title' => 'Title Too Long',
                    'description' => 'Title exceeds optimal length for search results.',
                    'impact' => 'medium',
                    'category' => 'title_optimization',
                    'auto_fixable' => true,
                    'fix_difficulty' => 'easy'
                ),
                array(
                    'id' => 'title_too_short',
                    'title' => 'Title Too Short',
                    'description' => 'Title is shorter than recommended minimum length.',
                    'impact' => 'medium',
                    'category' => 'title_optimization',
                    'auto_fixable' => true,
                    'fix_difficulty' => 'easy'
                ),
                array(
                    'id' => 'meta_description_too_long',
                    'title' => 'Meta Description Too Long',
                    'description' => 'Meta description exceeds search result display limit.',
                    'impact' => 'medium',
                    'category' => 'meta_description',
                    'auto_fixable' => true,
                    'fix_difficulty' => 'easy'
                ),
                array(
                    'id' => 'no_h1_heading',
                    'title' => 'Missing H1 Heading',
                    'description' => 'Content lacks proper H1 heading structure.',
                    'impact' => 'medium',
                    'category' => 'content_structure',
                    'auto_fixable' => true,
                    'fix_difficulty' => 'easy'
                ),
                array(
                    'id' => 'keyword_density_too_low',
                    'title' => 'Low Keyword Density',
                    'description' => 'Focus keyword appears infrequently in content.',
                    'impact' => 'medium',
                    'category' => 'keyword_optimization',
                    'auto_fixable' => true,
                    'fix_difficulty' => 'medium'
                ),
                array(
                    'id' => 'keyword_density_too_high',
                    'title' => 'Keyword Density Too High',
                    'description' => 'Focus keyword appears too frequently (keyword stuffing).',
                    'impact' => 'medium',
                    'category' => 'keyword_optimization',
                    'auto_fixable' => true,
                    'fix_difficulty' => 'medium'
                )
            ),
            'recommended' => array(
                array(
                    'id' => 'images_missing_alt',
                    'title' => 'Images Missing Alt Text',
                    'description' => 'Some images lack alternative text descriptions.',
                    'impact' => 'low',
                    'category' => 'technical_seo',
                    'auto_fixable' => true,
                    'fix_difficulty' => 'easy'
                ),
                array(
                    'id' => 'few_internal_links',
                    'title' => 'Limited Internal Linking',
                    'description' => 'Content has few internal links to related pages.',
                    'impact' => 'low',
                    'category' => 'technical_seo',
                    'auto_fixable' => false,
                    'fix_difficulty' => 'manual'
                ),
                array(
                    'id' => 'no_schema_markup',
                    'title' => 'Missing Schema Markup',
                    'description' => 'Content could benefit from structured data markup.',
                    'impact' => 'low',
                    'category' => 'technical_seo',
                    'auto_fixable' => true,
                    'fix_difficulty' => 'medium'
                ),
                array(
                    'id' => 'content_readability_low',
                    'title' => 'Content Readability Could Be Improved',
                    'description' => 'Content may be difficult for users to read and understand.',
                    'impact' => 'low',
                    'category' => 'user_experience',
                    'auto_fixable' => false,
                    'fix_difficulty' => 'manual'
                )
            )
        );
    }
    
    /**
     * Initialize indicator thresholds
     */
    private function init_indicator_thresholds() {
        $this->indicator_thresholds = array(
            'red_indicators' => array(
                'score_range' => array('min' => 0, 'max' => 59),
                'criteria' => array(
                    'no_focus_keyword',
                    'no_title',
                    'no_meta_description',
                    'content_too_short',
                    'severe_keyword_stuffing'
                ),
                'auto_fix_actions' => true,
                'urgency' => 'high'
            ),
            'yellow_indicators' => array(
                'score_range' => array('min' => 60, 'max' => 79),
                'criteria' => array(
                    'title_length_suboptimal',
                    'meta_length_issues',
                    'keyword_density_suboptimal',
                    'missing_h1',
                    'low_readability_score',
                    'poor_content_structure'
                ),
                'auto_fix_actions' => false,
                'urgency' => 'medium'
            ),
            'green_indicators' => array(
                'score_range' => array('min' => 80, 'max' => 100),
                'criteria' => array(
                    'optimal_title_length',
                    'good_keyword_usage',
                    'proper_content_structure',
                    'excellent_readability',
                    'complete_optimization'
                ),
                'auto_fix_actions' => false,
                'urgency' => 'low'
            )
        );
    }
    
    /**
     * Initialize recommendations database
     */
    private function init_recommendations_db() {
        $this->recommendations_db = array(
            'title_optimization' => array(
                'add_keyword' => array(
                    'title' => 'Add Focus Keyword to Title',
                    'description' => 'Include your focus keyword in the title for better search visibility.',
                    'priority' => 'high',
                    'implementation' => 'Insert focus keyword naturally within the first 60 characters.',
                    'impact' => 'high',
                    'effort' => 'low'
                ),
                'optimize_length' => array(
                    'title' => 'Optimize Title Length',
                    'description' => 'Adjust title length to 50-60 characters for optimal display.',
                    'priority' => 'medium',
                    'implementation' => 'Add compelling words if too short, remove filler words if too long.',
                    'impact' => 'medium',
                    'effort' => 'low'
                ),
                'add_power_words' => array(
                    'title' => 'Add Power Words',
                    'description' => 'Include engaging words that increase click-through rates.',
                    'priority' => 'low',
                    'implementation' => 'Add words like "Ultimate", "Complete", "Expert", "Proven" when appropriate.',
                    'impact' => 'medium',
                    'effort' => 'low'
                )
            ),
            'meta_description' => array(
                'add_meta_description' => array(
                    'title' => 'Add Meta Description',
                    'description' => 'Create a compelling meta description that encourages clicks.',
                    'priority' => 'high',
                    'implementation' => 'Write 150-160 character description with focus keyword and call-to-action.',
                    'impact' => 'high',
                    'effort' => 'medium'
                ),
                'optimize_meta_length' => array(
                    'title' => 'Optimize Meta Description Length',
                    'description' => 'Adjust meta description to optimal length for search display.',
                    'priority' => 'medium',
                    'implementation' => 'Shorten if over 160 characters, expand if under 120 characters.',
                    'impact' => 'medium',
                    'effort' => 'low'
                ),
                'add_cta' => array(
                    'title' => 'Add Call-to-Action',
                    'description' => 'Include compelling call-to-action to increase click-through rates.',
                    'priority' => 'medium',
                    'implementation' => 'Add action words like "Learn", "Discover", "Get", "Read".',
                    'impact' => 'medium',
                    'effort' => 'low'
                )
            ),
            'keyword_optimization' => array(
                'set_focus_keyword' => array(
                    'title' => 'Set Focus Keyword',
                    'description' => 'Define a primary keyword for this content to optimize around.',
                    'priority' => 'high',
                    'implementation' => 'Select keyword that best represents the content topic and search intent.',
                    'impact' => 'high',
                    'effort' => 'medium'
                ),
                'improve_keyword_density' => array(
                    'title' => 'Optimize Keyword Density',
                    'description' => 'Adjust keyword usage to optimal density for SEO without stuffing.',
                    'priority' => 'medium',
                    'implementation' => 'Target 1-2% keyword density, distribute naturally throughout content.',
                    'impact' => 'medium',
                    'effort' => 'medium'
                ),
                'add_long_tail_keywords' => array(
                    'title' => 'Add Long-tail Keywords',
                    'description' => 'Include specific, longer keyword phrases for better targeting.',
                    'priority' => 'low',
                    'implementation' => 'Add variations and related longer phrases that users might search.',
                    'impact' => 'medium',
                    'effort' => 'medium'
                )
            ),
            'content_quality' => array(
                'expand_content' => array(
                    'title' => 'Expand Content Length',
                    'description' => 'Increase content length to provide more value and improve rankings.',
                    'priority' => 'medium',
                    'implementation' => 'Add more comprehensive information, examples, and detailed explanations.',
                    'impact' => 'medium',
                    'effort' => 'high'
                ),
                'improve_readability' => array(
                    'title' => 'Improve Content Readability',
                    'description' => 'Make content easier to read and understand for your audience.',
                    'priority' => 'medium',
                    'implementation' => 'Use shorter sentences, simpler words, and better structure.',
                    'impact' => 'medium',
                    'effort' => 'medium'
                ),
                'add_headings' => array(
                    'title' => 'Add Proper Heading Structure',
                    'description' => 'Implement H1, H2, H3 headings for better content organization.',
                    'priority' => 'medium',
                    'implementation' => 'Add descriptive headings that break up content and improve scanning.',
                    'impact' => 'medium',
                    'effort' => 'low'
                )
            ),
            'technical_seo' => array(
                'optimize_images' => array(
                    'title' => 'Optimize Images',
                    'description' => 'Add alt text and optimize images for better accessibility and SEO.',
                    'priority' => 'low',
                    'implementation' => 'Add descriptive alt text to all images and use relevant filenames.',
                    'impact' => 'low',
                    'effort' => 'low'
                ),
                'add_schema_markup' => array(
                    'title' => 'Add Schema Markup',
                    'description' => 'Implement structured data for rich snippets and better search visibility.',
                    'priority' => 'low',
                    'implementation' => 'Add appropriate schema markup based on content type.',
                    'impact' => 'medium',
                    'effort' => 'medium'
                ),
                'improve_internal_linking' => array(
                    'title' => 'Improve Internal Linking',
                    'description' => 'Add relevant internal links to improve site navigation and SEO.',
                    'priority' => 'low',
                    'implementation' => 'Link to related content within your site where relevant.',
                    'impact' => 'medium',
                    'effort' => 'medium'
                )
            )
        );
    }
    
    /**
     * Perform comprehensive SEO analysis
     *
     * @param int $post_id Post ID to analyze
     * @param array $options Analysis options
     * @return array Comprehensive analysis results
     */
    public function analyze_seo($post_id, $options = array()) {
        $start_time = microtime(true);
        
        try {
            $params = array_merge(array(
                'analysis_depth' => $this->config['analysis_depth'],
                'include_recommendations' => true,
                'include_auto_fixes' => $this->config['auto_optimization_triggers'],
                'track_history' => true,
                'competitor_analysis' => false,
                'generate_report' => false
            ), $options);
            
            $this->logger->info('Starting comprehensive SEO analysis', array(
                'post_id' => $post_id,
                'analysis_depth' => $params['analysis_depth']
            ));
            
            // Get post data
            $post_data = $this->get_post_data($post_id);
            if (!$post_data) {
                throw new Exception('Post not found');
            }
            
            // Perform different levels of analysis
            $analysis_results = array(
                'overall_score' => 0,
                'score_breakdown' => array(),
                'indicators' => array(),
                'audit_results' => array(),
                'recommendations' => array(),
                'auto_fixes_applied' => array(),
                'optimization_history' => array(),
                'performance_metrics' => array()
            );
            
            // Core SEO analysis
            $core_analysis = $this->perform_core_analysis($post_id, $post_data);
            $analysis_results = array_merge($analysis_results, $core_analysis);
            
            // Advanced analysis if requested
            if ($params['analysis_depth'] === 'comprehensive') {
                $advanced_analysis = $this->perform_advanced_analysis($post_id, $post_data);
                $analysis_results = $this->merge_advanced_results($analysis_results, $advanced_analysis);
            }
            
            // SEO audit
            $audit_results = $this->perform_seo_audit($post_id, $post_data);
            $analysis_results['audit_results'] = $audit_results;
            
            // Generate recommendations
            if ($params['include_recommendations']) {
                $recommendations = $this->generate_recommendations($analysis_results);
                $analysis_results['recommendations'] = $recommendations;
            }
            
            // Apply auto-fixes if enabled
            if ($params['include_auto_fixes']) {
                $auto_fixes = $this->apply_auto_fixes($post_id, $analysis_results);
                $analysis_results['auto_fixes_applied'] = $auto_fixes;
            }
            
            // Update overall score
            $analysis_results['overall_score'] = $this->calculate_overall_score($analysis_results);
            
            // Determine indicators
            $analysis_results['indicators'] = $this->determine_indicators($analysis_results['overall_score'], $analysis_results);
            
            // Track history if enabled
            if ($params['track_history']) {
                $analysis_results['optimization_history'] = $this->track_optimization_history($post_id, $analysis_results);
            }
            
            $execution_time = (microtime(true) - $start_time) * 1000;
            
            // Add performance metrics
            $analysis_results['performance_metrics'] = array(
                'execution_time_ms' => $execution_time,
                'analysis_timestamp' => current_time('Y-m-d H:i:s'),
                'analysis_depth' => $params['analysis_depth'],
                'post_id' => $post_id
            );
            
            $this->logger->info('Comprehensive SEO analysis completed', array(
                'post_id' => $post_id,
                'overall_score' => $analysis_results['overall_score'],
                'execution_time_ms' => $execution_time
            ));
            
            return $analysis_results;
            
        } catch (Exception $e) {
            $execution_time = (microtime(true) - $start_time) * 1000;
            
            $this->logger->error('SEO analysis failed', array(
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
     * Perform core SEO analysis
     *
     * @param int $post_id Post ID
     * @param array $post_data Post data
     * @return array Core analysis results
     */
    private function perform_core_analysis($post_id, $post_data) {
        $results = array();
        
        // Use RankMath integration for primary scoring
        $rankmath_score = $this->rankmath_integration->get_seo_score($post_id);
        $detailed_analysis = $this->rankmath_integration->get_detailed_analysis($post_id);
        
        // Calculate individual category scores
        $results['score_breakdown'] = array(
            'title_optimization' => $this->analyze_title_optimization($detailed_analysis),
            'meta_description' => $this->analyze_meta_description($detailed_analysis),
            'content_quality' => $this->analyze_content_quality($detailed_analysis),
            'keyword_optimization' => $this->analyze_keyword_optimization($detailed_analysis),
            'technical_seo' => $this->analyze_technical_seo($post_id, $post_data),
            'user_experience' => $this->analyze_user_experience($detailed_analysis)
        );
        
        // Add RankMath source data
        $results['rankmath_data'] = array(
            'score' => $rankmath_score['score'],
            'focus_keyword' => $rankmath_score['focus_keyword'],
            'source' => $rankmath_score['source'],
            'timestamp' => $rankmath_score['timestamp']
        );
        
        return $results;
    }
    
    /**
     * Perform advanced SEO analysis
     *
     * @param int $post_id Post ID
     * @param array $post_data Post data
     * @return array Advanced analysis results
     */
    private function perform_advanced_analysis($post_id, $post_data) {
        $results = array();
        
        // Competitor analysis (if enabled)
        if ($this->config['competitor_analysis']) {
            $results['competitor_analysis'] = $this->analyze_competitor_seo($post_id, $post_data);
        }
        
        // Advanced content metrics
        $results['content_metrics'] = $this->analyze_advanced_content_metrics($post_data);
        
        // SERP optimization analysis
        $results['serp_optimization'] = $this->analyze_serp_optimization($post_data);
        
        // Schema markup analysis
        $results['schema_analysis'] = $this->analyze_schema_markup($post_id, $post_data);
        
        // Link analysis
        $results['link_analysis'] = $this->analyze_linking_strategy($post_id, $post_data);
        
        return $results;
    }
    
    /**
     * Perform SEO audit
     *
     * @param int $post_id Post ID
     * @param array $post_data Post data
     * @return array Audit results
     */
    private function perform_seo_audit($post_id, $post_data) {
        $audit_results = array(
            'critical_issues' => array(),
            'important_issues' => array(),
            'recommended_improvements' => array(),
            'passed_checks' => array(),
            'audit_score' => 0
        );
        
        $total_checks = 0;
        $passed_checks = 0;
        
        // Run through all audit items
        foreach ($this->audit_checklist as $priority => $items) {
            foreach ($items as $item) {
                $total_checks++;
                $check_result = $this->run_audit_check($post_id, $post_data, $item);
                
                if ($check_result['passed']) {
                    $passed_checks++;
                    $audit_results['passed_checks'][] = array(
                        'id' => $item['id'],
                        'title' => $item['title'],
                        'description' => $item['description']
                    );
                } else {
                    switch ($priority) {
                        case 'critical':
                            $audit_results['critical_issues'][] = array_merge($item, $check_result);
                            break;
                        case 'important':
                            $audit_results['important_issues'][] = array_merge($item, $check_result);
                            break;
                        case 'recommended':
                            $audit_results['recommended_improvements'][] = array_merge($item, $check_result);
                            break;
                    }
                }
            }
        }
        
        // Calculate audit score
        $audit_results['audit_score'] = $total_checks > 0 ? ($passed_checks / $total_checks) * 100 : 0;
        
        return $audit_results;
    }
    
    /**
     * Run individual audit check
     *
     * @param int $post_id Post ID
     * @param array $post_data Post data
     * @param array $audit_item Audit item configuration
     * @return array Check result
     */
    private function run_audit_check($post_id, $post_data, $audit_item) {
        switch ($audit_item['id']) {
            case 'focus_keyword_missing':
                $focus_keyword = get_post_meta($post_id, 'rank_math_focus_keyword', true);
                return array(
                    'passed' => !empty($focus_keyword),
                    'current_value' => $focus_keyword,
                    'recommendation' => 'Set a focus keyword for this content'
                );
                
            case 'title_missing':
                return array(
                    'passed' => !empty($post_data['title']),
                    'current_value' => $post_data['title'],
                    'recommendation' => 'Add a descriptive title'
                );
                
            case 'meta_description_missing':
                $meta_description = get_post_meta($post_id, 'rank_math_description', true);
                return array(
                    'passed' => !empty($meta_description),
                    'current_value' => $meta_description,
                    'recommendation' => 'Add a compelling meta description'
                );
                
            case 'content_too_short':
                $word_count = str_word_count(strip_tags($post_data['content']));
                return array(
                    'passed' => $word_count >= 300,
                    'current_value' => $word_count,
                    'recommendation' => 'Expand content to at least 300 words'
                );
                
            case 'title_too_long':
                $title_length = strlen($post_data['title']);
                return array(
                    'passed' => $title_length <= 60,
                    'current_value' => $title_length,
                    'recommendation' => 'Shorten title to 60 characters or less'
                );
                
            case 'title_too_short':
                $title_length = strlen($post_data['title']);
                return array(
                    'passed' => $title_length >= 30,
                    'current_value' => $title_length,
                    'recommendation' => 'Extend title to at least 30 characters'
                );
                
            case 'meta_description_too_long':
                $meta_description = get_post_meta($post_id, 'rank_math_description', true);
                $meta_length = strlen($meta_description);
                return array(
                    'passed' => $meta_length <= 160,
                    'current_value' => $meta_length,
                    'recommendation' => 'Shorten meta description to 160 characters or less'
                );
                
            case 'no_h1_heading':
                $has_h1 = preg_match('/<h1[^>]*>/i', $post_data['content']);
                return array(
                    'passed' => $has_h1,
                    'current_value' => $has_h1 ? 'Found' : 'Missing',
                    'recommendation' => 'Add an H1 heading to your content'
                );
                
            case 'keyword_density_too_low':
            case 'keyword_density_too_high':
                $focus_keyword = get_post_meta($post_id, 'rank_math_focus_keyword', true);
                if (empty($focus_keyword)) {
                    return array('passed' => true, 'current_value' => 'N/A', 'recommendation' => 'Set focus keyword first');
                }
                
                $word_count = str_word_count(strip_tags($post_data['content']));
                $keyword_count = substr_count(strtolower(strip_tags($post_data['content'])), strtolower($focus_keyword));
                $density = $word_count > 0 ? ($keyword_count / $word_count) * 100 : 0;
                
                $is_low = $audit_item['id'] === 'keyword_density_too_low';
                $passed = $is_low ? ($density >= 1.0) : ($density <= 3.0);
                
                return array(
                    'passed' => $passed,
                    'current_value' => round($density, 2) . '%',
                    'recommendation' => $is_low ? 'Increase keyword usage' : 'Reduce keyword usage'
                );
                
            case 'images_missing_alt':
                preg_match_all('/<img[^>]*>/i', $post_data['content'], $matches);
                $images_without_alt = 0;
                
                foreach ($matches[0] as $img) {
                    if (!preg_match('/alt=["\']?[^"\']*["\']?/', $img)) {
                        $images_without_alt++;
                    }
                }
                
                return array(
                    'passed' => $images_without_alt === 0,
                    'current_value' => $images_without_alt . ' images missing alt text',
                    'recommendation' => 'Add alt text to all images'
                );
                
            case 'few_internal_links':
                preg_match_all('/<a[^>]*href=["\']?[^"\']*' . preg_quote(get_site_url(), '/') . '[^"\']*["\']?[^>]*>/i', $post_data['content'], $matches);
                $internal_links = count($matches[0]);
                
                return array(
                    'passed' => $internal_links >= 3,
                    'current_value' => $internal_links . ' internal links',
                    'recommendation' => 'Add more internal links to related content'
                );
                
            case 'content_readability_low':
                $readability_score = $this->content_analyzer->analyze_content($post_data, array(
                    'analyze_readability' => true
                ));
                $flesch_score = $readability_score['analysis_details']['readability']['flesch_score'];
                
                return array(
                    'passed' => $flesch_score >= 60,
                    'current_value' => $flesch_score,
                    'recommendation' => 'Improve readability with shorter sentences and simpler words'
                );
                
            default:
                return array('passed' => true, 'current_value' => 'Unknown check', 'recommendation' => '');
        }
    }
    
    /**
     * Generate optimization recommendations
     *
     * @param array $analysis_results Analysis results
     * @return array Recommendations
     */
    private function generate_recommendations($analysis_results) {
        $recommendations = array();
        
        // Analyze score breakdown for recommendations
        foreach ($analysis_results['score_breakdown'] as $category => $score_data) {
            if ($score_data['score'] < 70) {
                $category_recommendations = $this->get_category_recommendations($category, $score_data);
                $recommendations = array_merge($recommendations, $category_recommendations);
            }
        }
        
        // Analyze audit results
        foreach ($analysis_results['audit_results']['critical_issues'] as $issue) {
            $recommendations[] = array(
                'type' => 'critical_fix',
                'priority' => 'high',
                'category' => $issue['category'],
                'title' => $issue['title'],
                'description' => $issue['description'],
                'recommendation' => $issue['recommendation'],
                'impact' => 'high',
                'effort' => $issue['fix_difficulty'],
                'auto_fixable' => $issue['auto_fixable']
            );
        }
        
        // Sort recommendations by priority
        usort($recommendations, function($a, $b) {
            $priority_order = array('high' => 3, 'medium' => 2, 'low' => 1);
            return $priority_order[$b['priority']] - $priority_order[$a['priority']];
        });
        
        return array_slice($recommendations, 0, 10); // Return top 10 recommendations
    }
    
    /**
     * Get recommendations for specific category
     *
     * @param string $category Category name
     * @param array $score_data Score data for category
     * @return array Category recommendations
     */
    private function get_category_recommendations($category, $score_data) {
        $recommendations = array();
        
        if (!isset($this->recommendations_db[$category])) {
            return $recommendations;
        }
        
        foreach ($this->recommendations_db[$category] as $rec_id => $rec_data) {
            // Determine if recommendation applies based on current score
            if ($this->recommendation_applies($category, $rec_id, $score_data)) {
                $recommendations[] = array(
                    'type' => 'optimization',
                    'category' => $category,
                    'id' => $rec_id,
                    'priority' => $rec_data['priority'],
                    'title' => $rec_data['title'],
                    'description' => $rec_data['description'],
                    'implementation' => $rec_data['implementation'],
                    'impact' => $rec_data['impact'],
                    'effort' => $rec_data['effort']
                );
            }
        }
        
        return $recommendations;
    }
    
    /**
     * Determine if recommendation applies
     *
     * @param string $category Category
     * @param string $rec_id Recommendation ID
     * @param array $score_data Score data
     * @return bool Whether recommendation applies
     */
    private function recommendation_applies($category, $rec_id, $score_data) {
        // Specific logic for each recommendation type
        switch ($rec_id) {
            case 'add_keyword':
                return !$score_data['has_keyword'];
                
            case 'optimize_length':
                return $score_data['length_issues'];
                
            case 'add_meta_description':
                return !$score_data['has_meta'];
                
            case 'set_focus_keyword':
                return !$score_data['has_focus_keyword'];
                
            case 'expand_content':
                return $score_data['word_count'] < 500;
                
            default:
                return true;
        }
    }
    
    /**
     * Apply automatic fixes
     *
     * @param int $post_id Post ID
     * @param array $analysis_results Analysis results
     * @return array Auto-fixes applied
     */
    private function apply_auto_fixes($post_id, $analysis_results) {
        $applied_fixes = array();
        
        // Only apply fixes for critical issues and when explicitly enabled
        if (!$this->config['auto_optimization_triggers']) {
            return $applied_fixes;
        }
        
        foreach ($analysis_results['audit_results']['critical_issues'] as $issue) {
            if ($issue['auto_fixable']) {
                $fix_result = $this->apply_single_auto_fix($post_id, $issue);
                if ($fix_result['success']) {
                    $applied_fixes[] = array(
                        'issue_id' => $issue['id'],
                        'issue_title' => $issue['title'],
                        'fix_applied' => $fix_result['fix'],
                        'timestamp' => current_time('Y-m-d H:i:s')
                    );
                }
            }
        }
        
        return $applied_fixes;
    }
    
    /**
     * Apply single automatic fix
     *
     * @param int $post_id Post ID
     * @param array $issue Audit issue
     * @return array Fix result
     */
    private function apply_single_auto_fix($post_id, $issue) {
        switch ($issue['id']) {
            case 'focus_keyword_missing':
                return $this->auto_optimizer->auto_generate_focus_keyword($post_id, $this->get_post_data($post_id));
                
            case 'meta_description_missing':
                return $this->auto_optimizer->auto_optimize_meta_description($post_id, $this->get_post_data($post_id));
                
            case 'content_too_short':
                return $this->auto_optimizer->auto_optimize_content($post_id, $this->get_post_data($post_id));
                
            default:
                return array('success' => false, 'reason' => 'Auto-fix not implemented');
        }
    }
    
    /**
     * Calculate overall SEO score
     *
     * @param array $analysis_results Analysis results
     * @return int Overall score (0-100)
     */
    private function calculate_overall_score($analysis_results) {
        $total_score = 0;
        $total_weight = 0;
        
        foreach ($this->scoring_algorithms as $category => $algorithm) {
            if (isset($analysis_results['score_breakdown'][$category])) {
                $category_score = $analysis_results['score_breakdown'][$category]['score'];
                $weight = $algorithm['weight'];
                
                $total_score += $category_score * $weight;
                $total_weight += $weight;
            }
        }
        
        return $total_weight > 0 ? round($total_score / $total_weight) : 0;
    }
    
    /**
     * Determine SEO indicators based on score
     *
     * @param int $score Overall SEO score
     * @param array $analysis_results Analysis results
     * @return array SEO indicators
     */
    private function determine_indicators($score, $analysis_results) {
        $indicators = array(
            'status' => 'unknown',
            'color' => 'unknown',
            'level' => 'unknown',
            'summary' => '',
            'details' => array()
        );
        
        // Determine status based on score
        if ($score >= $this->config['score_threshold_green']) {
            $indicators['status'] = 'excellent';
            $indicators['color'] = 'green';
            $indicators['level'] = 'high';
            $indicators['summary'] = 'Excellent SEO optimization';
        } elseif ($score >= $this->config['score_threshold_yellow']) {
            $indicators['status'] = 'good';
            $indicators['color'] = 'yellow';
            $indicators['level'] = 'medium';
            $indicators['summary'] = 'Good SEO with room for improvement';
        } else {
            $indicators['status'] = 'needs_improvement';
            $indicators['color'] = 'red';
            $indicators['level'] = 'low';
            $indicators['summary'] = 'SEO needs significant improvement';
        }
        
        // Add detailed indicators
        $indicators['details'] = $this->get_detailed_indicators($analysis_results);
        
        return $indicators;
    }
    
    /**
     * Get detailed indicators from analysis
     *
     * @param array $analysis_results Analysis results
     * @return array Detailed indicators
     */
    private function get_detailed_indicators($analysis_results) {
        $details = array();
        
        // Check critical indicators
        if (!empty($analysis_results['audit_results']['critical_issues'])) {
            $details[] = array(
                'type' => 'critical',
                'count' => count($analysis_results['audit_results']['critical_issues']),
                'description' => 'Critical SEO issues require immediate attention'
            );
        }
        
        // Check important indicators
        if (!empty($analysis_results['audit_results']['important_issues'])) {
            $details[] = array(
                'type' => 'important',
                'count' => count($analysis_results['audit_results']['important_issues']),
                'description' => 'Important SEO improvements recommended'
            );
        }
        
        // Check score improvements needed
        $low_score_categories = array();
        foreach ($analysis_results['score_breakdown'] as $category => $score_data) {
            if ($score_data['score'] < 60) {
                $low_score_categories[] = $category;
            }
        }
        
        if (!empty($low_score_categories)) {
            $details[] = array(
                'type' => 'score_improvement',
                'categories' => $low_score_categories,
                'description' => 'Several SEO categories need improvement'
            );
        }
        
        return $details;
    }
    
    /**
     * Track optimization history
     *
     * @param int $post_id Post ID
     * @param array $analysis_results Current analysis results
     * @return array Optimization history
     */
    private function track_optimization_history($post_id, $analysis_results) {
        $cache_key = 'seo_optimization_history_' . $post_id;
        $history = $this->cache_manager->get($cache_key);
        
        if ($history === false) {
            $history = array();
        }
        
        // Add current analysis to history
        $history[] = array(
            'timestamp' => current_time('Y-m-d H:i:s'),
            'overall_score' => $analysis_results['overall_score'],
            'indicators' => $analysis_results['indicators']['status'],
            'critical_issues' => count($analysis_results['audit_results']['critical_issues']),
            'auto_fixes_applied' => count($analysis_results['auto_fixes_applied'])
        );
        
        // Keep only last 10 entries
        $history = array_slice($history, -10);
        
        // Cache updated history
        $this->cache_manager->set($cache_key, $history, 86400); // 24 hours
        
        return $history;
    }
    
    // Individual analysis methods
    
    /**
     * Analyze title optimization score
     *
     * @param array $detailed_analysis Detailed analysis data
     * @return array Title optimization score
     */
    private function analyze_title_optimization($detailed_analysis) {
        $score = 0;
        $factors = array();
        
        if (isset($detailed_analysis['title'])) {
            $title_analysis = $detailed_analysis['title'];
            
            // Keyword in title (40 points)
            if ($title_analysis['contains_focus_keyword']) {
                $score += 40;
                $factors[] = 'Focus keyword in title';
            } else {
                $factors[] = 'Focus keyword missing from title';
            }
            
            // Title length (25 points)
            $length = $title_analysis['length'];
            if ($length >= 50 && $length <= 60) {
                $score += 25;
                $factors[] = 'Optimal title length';
            } elseif ($length >= 30 && $length < 50) {
                $score += 15;
                $factors[] = 'Acceptable title length';
            } else {
                $factors[] = 'Title length needs optimization';
            }
            
            // Power words (15 points)
            if (preg_match('/\b(ultimate|complete|expert|proven|best|amazing|incredible)\b/i', $detailed_analysis['title'])) {
                $score += 15;
                $factors[] = 'Contains power words';
            } else {
                $factors[] = 'Could benefit from power words';
            }
        }
        
        return array(
            'score' => min(100, $score),
            'has_keyword' => $detailed_analysis['title']['contains_focus_keyword'] ?? false,
            'length_issues' => isset($detailed_analysis['title']) && 
                ($detailed_analysis['title']['length'] < 30 || $detailed_analysis['title']['length'] > 60),
            'factors' => $factors
        );
    }
    
    /**
     * Analyze meta description score
     *
     * @param array $detailed_analysis Detailed analysis data
     * @return array Meta description score
     */
    private function analyze_meta_description($detailed_analysis) {
        $score = 0;
        $factors = array();
        
        if (isset($detailed_analysis['meta_description'])) {
            $meta_analysis = $detailed_analysis['meta_description'];
            
            // Meta exists (30 points)
            if ($meta_analysis['exists']) {
                $score += 30;
                $factors[] = 'Meta description present';
            } else {
                $factors[] = 'Meta description missing';
            }
            
            // Meta length (25 points)
            $length = $meta_analysis['length'];
            if ($length >= 150 && $length <= 160) {
                $score += 25;
                $factors[] = 'Optimal meta description length';
            } elseif ($length >= 120 && $length < 150) {
                $score += 15;
                $factors[] = 'Acceptable meta description length';
            } else {
                $factors[] = 'Meta description length needs optimization';
            }
            
            // Call-to-action (15 points)
            if ($meta_analysis['optimization_status'] === 'good') {
                $score += 15;
                $factors[] = 'Contains effective call-to-action';
            } else {
                $factors[] = 'Could benefit from call-to-action';
            }
        }
        
        return array(
            'score' => min(100, $score),
            'has_meta' => $detailed_analysis['meta_description']['exists'] ?? false,
            'factors' => $factors
        );
    }
    
    /**
     * Analyze content quality score
     *
     * @param array $detailed_analysis Detailed analysis data
     * @return array Content quality score
     */
    private function analyze_content_quality($detailed_analysis) {
        $score = 0;
        $factors = array();
        
        if (isset($detailed_analysis['content'])) {
            $content_analysis = $detailed_analysis['content'];
            
            // Content length (30 points)
            $word_count = $content_analysis['word_count'];
            if ($word_count >= 1000) {
                $score += 30;
                $factors[] = 'Excellent content length';
            } elseif ($word_count >= 500) {
                $score += 20;
                $factors[] = 'Good content length';
            } elseif ($word_count >= 300) {
                $score += 10;
                $factors[] = 'Minimum content length met';
            } else {
                $factors[] = 'Content too short';
            }
            
            // Readability (25 points)
            $readability = $content_analysis['readability_score'] ?? 0;
            if ($readability >= 80) {
                $score += 25;
                $factors[] = 'Excellent readability';
            } elseif ($readability >= 60) {
                $score += 15;
                $factors[] = 'Good readability';
            } else {
                $factors[] = 'Readability could be improved';
            }
            
            // Structure (15 points)
            if ($content_analysis['heading_structure']['h1_count'] === 1 && $content_analysis['heading_structure']['h2_count'] >= 2) {
                $score += 15;
                $factors[] = 'Good heading structure';
            } else {
                $factors[] = 'Heading structure needs improvement';
            }
        }
        
        return array(
            'score' => min(100, $score),
            'word_count' => $detailed_analysis['content']['word_count'] ?? 0,
            'factors' => $factors
        );
    }
    
    /**
     * Analyze keyword optimization score
     *
     * @param array $detailed_analysis Detailed analysis data
     * @return array Keyword optimization score
     */
    private function analyze_keyword_optimization($detailed_analysis) {
        $score = 0;
        $factors = array();
        
        if (isset($detailed_analysis['focus_keyword'])) {
            $keyword_analysis = $detailed_analysis['focus_keyword'];
            
            // Focus keyword set (35 points)
            if ($keyword_analysis['set']) {
                $score += 35;
                $factors[] = 'Focus keyword defined';
            } else {
                $factors[] = 'Focus keyword not set';
            }
            
            // Keyword in headings (25 points)
            if ($keyword_analysis['usage_analysis']['in_headings']) {
                $score += 25;
                $factors[] = 'Keyword in headings';
            } else {
                $factors[] = 'Keyword not found in headings';
            }
            
            // Keyword distribution (20 points)
            $density = $keyword_analysis['usage_analysis']['density'] ?? 0;
            if ($density >= 1.0 && $density <= 2.0) {
                $score += 20;
                $factors[] = 'Optimal keyword density';
            } else {
                $factors[] = 'Keyword density needs adjustment';
            }
        }
        
        return array(
            'score' => min(100, $score),
            'has_focus_keyword' => $detailed_analysis['focus_keyword']['set'] ?? false,
            'factors' => $factors
        );
    }
    
    /**
     * Analyze technical SEO score
     *
     * @param int $post_id Post ID
     * @param array $post_data Post data
     * @return array Technical SEO score
     */
    private function analyze_technical_seo($post_id, $post_data) {
        $score = 0;
        $factors = array();
        
        // URL structure (30 points)
        $post = get_post($post_id);
        if ($post && !empty($post->post_name)) {
            $score += 30;
            $factors[] = 'SEO-friendly URL structure';
        } else {
            $factors[] = 'URL structure issues';
        }
        
        // Image optimization (25 points)
        preg_match_all('/<img[^>]*>/i', $post_data['content'], $matches);
        $images = $matches[0];
        $images_with_alt = 0;
        
        foreach ($images as $img) {
            if (preg_match('/alt=["\']?[^"\']*["\']?/', $img)) {
                $images_with_alt++;
            }
        }
        
        if (count($images) === 0 || $images_with_alt === count($images)) {
            $score += 25;
            $factors[] = 'Images properly optimized';
        } else {
            $factors[] = 'Images need alt text';
        }
        
        // Internal linking (25 points)
        preg_match_all('/<a[^>]*href=["\']?[^"\']*' . preg_quote(get_site_url(), '/') . '[^"\']*["\']?[^>]*>/i', $post_data['content'], $matches);
        $internal_links = count($matches[0]);
        
        if ($internal_links >= 3) {
            $score += 25;
            $factors[] = 'Good internal linking';
        } else {
            $factors[] = 'Limited internal linking';
        }
        
        return array(
            'score' => min(100, $score),
            'factors' => $factors
        );
    }
    
    /**
     * Analyze user experience score
     *
     * @param array $detailed_analysis Detailed analysis data
     * @return array User experience score
     */
    private function analyze_user_experience($detailed_analysis) {
        $score = 0;
        $factors = array();
        
        // Content structure (25 points)
        if (isset($detailed_analysis['content']['heading_structure'])) {
            $heading_structure = $detailed_analysis['content']['heading_structure'];
            if ($heading_structure['total_headings'] >= 3) {
                $score += 25;
                $factors[] = 'Good content structure';
            } else {
                $factors[] = 'Content structure could be improved';
            }
        }
        
        // Scannability (25 points)
        $content = $detailed_analysis['content']['readability_score'] ?? 0;
        if ($content >= 70) {
            $score += 25;
            $factors[] = 'Content is scannable';
        } else {
            $factors[] = 'Content could be more scannable';
        }
        
        // Engagement elements (20 points)
        $content_text = strip_tags($detailed_analysis['content']['content'] ?? '');
        if (preg_match('/<ul|<ol|<blockquote/i', $detailed_analysis['content']['content'] ?? '')) {
            $score += 20;
            $factors[] = 'Contains engagement elements';
        } else {
            $factors[] = 'Could benefit from engagement elements';
        }
        
        return array(
            'score' => min(100, $score),
            'factors' => $factors
        );
    }
    
    /**
     * Analyze competitor SEO (placeholder implementation)
     */
    private function analyze_competitor_seo($post_id, $post_data) {
        // This would require external API integration for competitor analysis
        // For now, return placeholder data
        return array(
            'competitor_count' => 0,
            'average_score' => 0,
            'gaps_identified' => array(),
            'opportunities' => array()
        );
    }
    
    /**
     * Analyze advanced content metrics
     */
    private function analyze_advanced_content_metrics($post_data) {
        $content = $post_data['content'] ?? '';
        
        return array(
            'sentiment_score' => $this->analyze_sentiment($content),
            'complexity_score' => $this->analyze_complexity($content),
            'engagement_potential' => $this->analyze_engagement_potential($content),
            'uniqueness_score' => $this->analyze_uniqueness($content)
        );
    }
    
    /**
     * Analyze SERP optimization potential
     */
    private function analyze_serp_optimization($post_data) {
        $content = $post_data['content'] ?? '';
        $title = $post_data['title'] ?? '';
        
        return array(
            'featured_snippet_potential' => $this->analyze_featured_snippet_potential($content),
            'rich_snippets_potential' => $this->analyze_rich_snippets_potential($content),
            'people_also_ask_potential' => $this->analyze_paa_potential($content),
            'image_pack_potential' => $this->analyze_image_pack_potential($content)
        );
    }
    
    /**
     * Analyze schema markup potential
     */
    private function analyze_schema_markup($post_id, $post_data) {
        return array(
            'current_schema' => 'none', // Would need to check existing schema
            'recommended_schema' => $this->get_recommended_schema($post_data),
            'implementation_effort' => 'medium',
            'impact_potential' => 'high'
        );
    }
    
    /**
     * Analyze linking strategy
     */
    private function analyze_linking_strategy($post_id, $post_data) {
        $content = $post_data['content'] ?? '';
        
        preg_match_all('/<a[^>]*href=["\']?([^"\']*?)["\']?[^>]*>(.*?)<\/a>/i', $content, $matches);
        
        $links = array();
        for ($i = 0; $i < count($matches[0]); $i++) {
            $links[] = array(
                'url' => $matches[1][$i],
                'text' => strip_tags($matches[2][$i]),
                'is_external' => strpos($matches[1][$i], get_site_url()) === false
            );
        }
        
        return array(
            'total_links' => count($links),
            'internal_links' => count(array_filter($links, function($link) { return !$link['is_external']; })),
            'external_links' => count(array_filter($links, function($link) { return $link['is_external']; })),
            'anchor_text_diversity' => $this->analyze_anchor_text_diversity($links)
        );
    }
    
    // Helper analysis methods
    
    private function analyze_sentiment($content) {
        // Simple sentiment analysis - would use more sophisticated NLP in production
        $positive_words = array('good', 'great', 'excellent', 'amazing', 'wonderful', 'fantastic');
        $negative_words = array('bad', 'terrible', 'awful', 'horrible', 'worst', 'hate');
        
        $content_lower = strtolower($content);
        $positive_count = 0;
        $negative_count = 0;
        
        foreach ($positive_words as $word) {
            $positive_count += substr_count($content_lower, $word);
        }
        
        foreach ($negative_words as $word) {
            $negative_count += substr_count($content_lower, $word);
        }
        
        $total_words = str_word_count($content);
        $positive_ratio = $total_words > 0 ? ($positive_count / $total_words) * 100 : 0;
        $negative_ratio = $total_words > 0 ? ($negative_count / $total_words) * 100 : 0;
        
        if ($positive_ratio > $negative_ratio) {
            return 'positive';
        } elseif ($negative_ratio > $positive_ratio) {
            return 'negative';
        } else {
            return 'neutral';
        }
    }
    
    private function analyze_complexity($content) {
        $sentences = preg_split('/[.!?]+/', $content, -1, PREG_SPLIT_NO_EMPTY);
        $words = preg_split('/\s+/', $content);
        
        $avg_sentence_length = count($sentences) > 0 ? count($words) / count($sentences) : 0;
        
        if ($avg_sentence_length <= 15) {
            return 'simple';
        } elseif ($avg_sentence_length <= 25) {
            return 'moderate';
        } else {
            return 'complex';
        }
    }
    
    private function analyze_engagement_potential($content) {
        $engagement_score = 0;
        
        // Check for questions
        if (preg_match('/\?/', $content)) {
            $engagement_score += 20;
        }
        
        // Check for lists
        if (preg_match('/<ul|<ol/i', $content)) {
            $engagement_score += 20;
        }
        
        // Check for bold/strong text
        if (preg_match('/<strong|<b/i', $content)) {
            $engagement_score += 15;
        }
        
        // Check for short paragraphs
        $paragraphs = preg_split('/\n\s*\n/', $content);
        $short_paragraphs = count(array_filter($paragraphs, function($p) {
            return str_word_count(strip_tags($p)) < 50;
        }));
        
        if ($short_paragraphs > count($paragraphs) * 0.6) {
            $engagement_score += 20;
        }
        
        return $engagement_score >= 50 ? 'high' : ($engagement_score >= 25 ? 'medium' : 'low');
    }
    
    private function analyze_uniqueness($content) {
        // This would require external plagiarism detection API
        // For now, return a placeholder
        return array(
            'score' => 85,
            'similar_content_found' => false,
            'uniqueness_percentage' => 85
        );
    }
    
    private function analyze_featured_snippet_potential($content) {
        $score = 0;
        
        // Check for definition patterns
        if (preg_match('/\b(is|are|means|definition of)\b.+/i', $content)) {
            $score += 30;
        }
        
        // Check for step-by-step content
        if (preg_match('/\b(step \d+|first|second|third|next|finally)\b/i', $content)) {
            $score += 25;
        }
        
        // Check for lists
        if (preg_match('/<ul|<ol/i', $content)) {
            $score += 20;
        }
        
        // Check for table format
        if (preg_match('/<table/i', $content)) {
            $score += 15;
        }
        
        return $score >= 60 ? 'high' : ($score >= 30 ? 'medium' : 'low');
    }
    
    private function analyze_rich_snippets_potential($content) {
        $score = 0;
        
        // Check for rating/reviews
        if (preg_match('/\b(\d+\.?\d*)\s*(stars?|rating|review)/i', $content)) {
            $score += 30;
        }
        
        // Check for prices
        if (preg_match('/\$[\d,]+/', $content)) {
            $score += 25;
        }
        
        // Check for events
        if (preg_match('/\b(when|where|date|time)\b/i', $content)) {
            $score += 20;
        }
        
        // Check for recipes
        if (preg_match('/\b(recipe|ingredients|cook|preparation)\b/i', $content)) {
            $score += 25;
        }
        
        return $score >= 50 ? 'high' : ($score >= 25 ? 'medium' : 'low');
    }
    
    private function analyze_paa_potential($content) {
        $score = 0;
        
        // Check for question patterns
        $question_words = array('what', 'how', 'why', 'when', 'where', 'who', 'which');
        foreach ($question_words as $word) {
            if (preg_match('/\b' . $word . '\b.*\?/i', $content)) {
                $score += 15;
            }
        }
        
        // Check for FAQ section
        if (preg_match('/(frequently asked questions|faq|common questions)/i', $content)) {
            $score += 25;
        }
        
        return $score >= 30 ? 'high' : ($score >= 15 ? 'medium' : 'low');
    }
    
    private function analyze_image_pack_potential($content) {
        // Check for image-related keywords and presence of images
        $image_keywords = array('photo', 'picture', 'image', 'gallery', 'screenshot');
        $content_lower = strtolower($content);
        
        $keyword_score = 0;
        foreach ($image_keywords as $keyword) {
            if (strpos($content_lower, $keyword) !== false) {
                $keyword_score += 10;
            }
        }
        
        preg_match_all('/<img[^>]*>/i', $content, $matches);
        $image_score = min(30, count($matches[0]) * 5);
        
        $total_score = $keyword_score + $image_score;
        return $total_score >= 40 ? 'high' : ($total_score >= 20 ? 'medium' : 'low');
    }
    
    private function get_recommended_schema($post_data) {
        $title = strtolower($post_data['title'] ?? '');
        
        if (strpos($title, 'review') !== false || strpos($title, 'rating') !== false) {
            return 'Review';
        } elseif (strpos($title, 'how to') !== false) {
            return 'HowTo';
        } elseif (strpos($title, 'recipe') !== false) {
            return 'Recipe';
        } else {
            return 'Article';
        }
    }
    
    private function analyze_anchor_text_diversity($links) {
        $anchor_texts = array();
        foreach ($links as $link) {
            $anchor_texts[] = strtolower(trim($link['text']));
        }
        
        $unique_anchors = count(array_unique($anchor_texts));
        $total_anchors = count($anchor_texts);
        
        if ($total_anchors === 0) return 'none';
        
        $diversity_ratio = $unique_anchors / $total_anchors;
        
        if ($diversity_ratio >= 0.8) {
            return 'excellent';
        } elseif ($diversity_ratio >= 0.6) {
            return 'good';
        } elseif ($diversity_ratio >= 0.4) {
            return 'fair';
        } else {
            return 'poor';
        }
    }
    
    /**
     * Merge advanced results with main analysis
     */
    private function merge_advanced_results($main_results, $advanced_results) {
        foreach ($advanced_results as $key => $value) {
            $main_results[$key] = $value;
        }
        return $main_results;
    }
    
    /**
     * Get post data for analysis
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
            'post_type' => $post->post_type
        );
    }
    
    /**
     * Get service metrics
     */
    public function get_metrics() {
        return array(
            'service' => 'RankMathSEOAnalyzer',
            'config' => $this->config,
            'scoring_algorithms' => count($this->scoring_algorithms),
            'audit_checklist_items' => array_sum(array_map('count', $this->audit_checklist)),
            'recommendations_db' => count($this->recommendations_db),
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
            $test_analysis = $this->analyze_seo(1, array(
                'analysis_depth' => 'basic',
                'include_recommendations' => false,
                'include_auto_fixes' => false
            ));
            
            // Check if analysis returned expected structure
            $required_fields = array('overall_score', 'score_breakdown', 'indicators');
            foreach ($required_fields as $field) {
                if (!isset($test_analysis[$field])) {
                    return false;
                }
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('RankMathSEOAnalyzer health check failed', array(
                'error' => $e->getMessage()
            ));
            return false;
        }
    }
}