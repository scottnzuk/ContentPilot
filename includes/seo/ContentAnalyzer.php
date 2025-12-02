<?php
/**
 * Content Analyzer for SEO and EEAT Optimization
 *
 * Analyzes content quality, readability, SEO factors, and provides
 * optimization recommendations for Google EEAT compliance.
 *
 * @package AI_Auto_News_Poster\SEO
 * @since 1.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Content Analyzer Class
 */
class AANP_ContentAnalyzer {
    
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
     * Analysis configuration
     * @var array
     */
    private $config = array();
    
    /**
     * SEO analysis rules
     * @var array
     */
    private $seo_rules = array();
    
    /**
     * Readability thresholds
     * @var array
     */
    private $readability_thresholds = array(
        'poor' => 30,
        'fair' => 50,
        'good' => 70,
        'excellent' => 80
    );
    
    /**
     * Keyword importance weights
     * @var array
     */
    private $keyword_weights = array(
        'title' => 3.0,
        'meta_description' => 2.5,
        'headings' => 2.0,
        'first_paragraph' => 1.8,
        'content' => 1.0,
        'alt_text' => 1.5,
        'url' => 2.2
    );
    
    /**
     * Constructor
     *
     * @param AANP_AdvancedCacheManager $cache_manager
     */
    public function __construct(AANP_AdvancedCacheManager $cache_manager = null) {
        $this->logger = AANP_Logger::getInstance();
        $this->cache_manager = $cache_manager ?: new AANP_AdvancedCacheManager();
        
        $this->init_config();
        $this->init_seo_rules();
    }
    
    /**
     * Initialize analysis configuration
     */
    private function init_config() {
        $options = get_option('aanp_settings', array());
        
        $this->config = array(
            'min_content_length' => isset($options['min_content_length']) ? intval($options['min_content_length']) : 300,
            'max_content_length' => isset($options['max_content_length']) ? intval($options['max_content_length']) : 2500,
            'target_keyword_density' => isset($options['target_keyword_density']) ? floatval($options['target_keyword_density']) : 2.5,
            'readability_target' => isset($options['readability_target']) ? $options['readability_target'] : 'good',
            'seo_analysis_enabled' => isset($options['seo_analysis_enabled']) ? (bool) $options['seo_analysis_enabled'] : true,
            'eeat_analysis_enabled' => isset($options['eeat_analysis_enabled']) ? (bool) $options['eeat_analysis_enabled'] : true,
            'serp_optimization' => isset($options['serp_optimization']) ? (bool) $options['serp_optimization'] : true,
            'content_authenticity_check' => isset($options['content_authenticity_check']) ? (bool) $options['content_authenticity_check'] : true,
            'expertise_tracking' => isset($options['expertise_tracking']) ? (bool) $options['expertise_tracking'] : true
        );
    }
    
    /**
     * Initialize SEO analysis rules
     */
    private function init_seo_rules() {
        $this->seo_rules = array(
            'title_length' => array(
                'min' => 30,
                'max' => 60,
                'weight' => 15,
                'description' => 'Title length optimization'
            ),
            'meta_description_length' => array(
                'min' => 120,
                'max' => 160,
                'weight' => 10,
                'description' => 'Meta description length'
            ),
            'heading_structure' => array(
                'min_h1' => 1,
                'max_h1' => 1,
                'min_h2' => 2,
                'max_h3' => 6,
                'weight' => 12,
                'description' => 'Proper heading hierarchy'
            ),
            'content_length' => array(
                'min' => $this->config['min_content_length'],
                'max' => $this->config['max_content_length'],
                'weight' => 8,
                'description' => 'Content length optimization'
            ),
            'keyword_density' => array(
                'min' => 1.0,
                'max' => 3.0,
                'target' => $this->config['target_keyword_density'],
                'weight' => 10,
                'description' => 'Keyword density optimization'
            )
        );
    }
    
    /**
     * Analyze content comprehensively
     *
     * @param array $content_data Content data to analyze
     * @param array $options Analysis options
     * @return array Analysis results
     */
    public function analyze_content($content_data, $options = array()) {
        $start_time = microtime(true);
        
        try {
            $params = array_merge(array(
                'analyze_seo' => $this->config['seo_analysis_enabled'],
                'analyze_eeat' => $this->config['eeat_analysis_enabled'],
                'analyze_readability' => true,
                'analyze_serp' => $this->config['serp_optimization'],
                'check_authenticity' => $this->config['content_authenticity_check'],
                'primary_keyword' => '',
                'secondary_keywords' => array(),
                'target_audience' => 'general',
                'content_type' => 'blog_post'
            ), $options);
            
            $this->logger->info('Starting content analysis', array(
                'content_type' => $params['content_type'],
                'primary_keyword' => $params['primary_keyword'],
                'word_count' => str_word_count(strip_tags($content_data['content'] ?? ''))
            ));
            
            $analysis_results = array(
                'overall_score' => 0,
                'seo_score' => 0,
                'readability_score' => 0,
                'eeat_score' => 0,
                'serp_optimization_score' => 0,
                'analysis_details' => array(),
                'recommendations' => array(),
                'optimization_suggestions' => array(),
                'performance_metrics' => array()
            );
            
            // Perform different types of analysis
            if ($params['analyze_readability']) {
                $readability_analysis = $this->analyze_readability($content_data);
                $analysis_results['readability_score'] = $readability_analysis['score'];
                $analysis_results['analysis_details']['readability'] = $readability_analysis;
            }
            
            if ($params['analyze_seo']) {
                $seo_analysis = $this->analyze_seo($content_data, $params);
                $analysis_results['seo_score'] = $seo_analysis['score'];
                $analysis_results['analysis_details']['seo'] = $seo_analysis;
            }
            
            if ($params['analyze_eeat']) {
                $eeat_analysis = $this->analyze_eeat($content_data, $params);
                $analysis_results['eeat_score'] = $eeat_analysis['score'];
                $analysis_results['analysis_details']['eeat'] = $eeat_analysis;
            }
            
            if ($params['analyze_serp']) {
                $serp_analysis = $this->analyze_serp_optimization($content_data, $params);
                $analysis_results['serp_optimization_score'] = $serp_analysis['score'];
                $analysis_results['analysis_details']['serp'] = $serp_analysis;
            }
            
            if ($params['check_authenticity']) {
                $authenticity_analysis = $this->check_content_authenticity($content_data, $params);
                $analysis_results['analysis_details']['authenticity'] = $authenticity_analysis;
            }
            
            // Calculate overall score
            $analysis_results['overall_score'] = $this->calculate_overall_score($analysis_results);
            
            // Generate recommendations
            $analysis_results['recommendations'] = $this->generate_recommendations($analysis_results);
            
            // Generate optimization suggestions
            $analysis_results['optimization_suggestions'] = $this->generate_optimization_suggestions($content_data, $analysis_results, $params);
            
            $execution_time = (microtime(true) - $start_time) * 1000;
            
            // Add performance metrics
            $analysis_results['performance_metrics'] = array(
                'execution_time_ms' => $execution_time,
                'analysis_timestamp' => current_time('Y-m-d H:i:s'),
                'content_length' => strlen($content_data['content'] ?? ''),
                'word_count' => str_word_count(strip_tags($content_data['content'] ?? ''))
            );
            
            $this->logger->info('Content analysis completed successfully', array(
                'overall_score' => $analysis_results['overall_score'],
                'execution_time_ms' => $execution_time
            ));
            
            return $analysis_results;
            
        } catch (Exception $e) {
            $execution_time = (microtime(true) - $start_time) * 1000;
            
            $this->logger->error('Content analysis failed', array(
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
     * Analyze SEO factors
     *
     * @param array $content_data Content data
     * @param array $params Analysis parameters
     * @return array SEO analysis results
     */
    private function analyze_seo($content_data, $params) {
        $seo_score = 0;
        $analysis_details = array();
        $max_possible_score = 100;
        
        // Title analysis
        $title_analysis = $this->analyze_title_seo($content_data['title'] ?? '', $params);
        $seo_score += $title_analysis['score'];
        $analysis_details['title'] = $title_analysis;
        
        // Meta description analysis
        $meta_analysis = $this->analyze_meta_description($content_data['meta_description'] ?? '', $params);
        $seo_score += $meta_analysis['score'];
        $analysis_details['meta_description'] = $meta_analysis;
        
        // Heading structure analysis
        $heading_analysis = $this->analyze_heading_structure($content_data['content'] ?? '');
        $seo_score += $heading_analysis['score'];
        $analysis_details['heading_structure'] = $heading_analysis;
        
        // Content length analysis
        $length_analysis = $this->analyze_content_length($content_data['content'] ?? '');
        $seo_score += $length_analysis['score'];
        $analysis_details['content_length'] = $length_analysis;
        
        // Keyword analysis
        $keyword_analysis = $this->analyze_keywords($content_data, $params);
        $seo_score += $keyword_analysis['score'];
        $analysis_details['keywords'] = $keyword_analysis;
        
        // Normalize score to 0-100 scale
        $normalized_score = min(100, max(0, $seo_score));
        
        return array(
            'score' => $normalized_score,
            'details' => $analysis_details,
            'grade' => $this->get_seo_grade($normalized_score),
            'improvement_potential' => 100 - $normalized_score
        );
    }
    
    /**
     * Analyze title SEO factors
     *
     * @param string $title Page title
     * @param array $params Analysis parameters
     * @return array Title analysis results
     */
    private function analyze_title_seo($title, $params) {
        $score = 0;
        $issues = array();
        $suggestions = array();
        
        if (empty($title)) {
            $issues[] = 'Title is missing';
            $suggestions[] = 'Add a descriptive title';
            return array('score' => 0, 'issues' => $issues, 'suggestions' => $suggestions);
        }
        
        $title_length = strlen($title);
        $rules = $this->seo_rules['title_length'];
        
        // Length check
        if ($title_length >= $rules['min'] && $title_length <= $rules['max']) {
            $score += $rules['weight'] * 0.6;
        } else {
            $issues[] = 'Title length is not optimal';
            if ($title_length < $rules['min']) {
                $suggestions[] = "Extend title to at least {$rules['min']} characters";
            } else {
                $suggestions[] = "Shorten title to {$rules['max']} characters or less";
            }
        }
        
        // Primary keyword check
        if (!empty($params['primary_keyword'])) {
            $keyword = strtolower($params['primary_keyword']);
            $title_lower = strtolower($title);
            
            if (strpos($title_lower, $keyword) !== false) {
                $score += $rules['weight'] * 0.4;
            } else {
                $issues[] = 'Primary keyword not found in title';
                $suggestions[] = "Include the primary keyword '{$params['primary_keyword']}' in the title";
            }
        }
        
        return array(
            'score' => $score,
            'length' => $title_length,
            'issues' => $issues,
            'suggestions' => $suggestions,
            'contains_primary_keyword' => !empty($params['primary_keyword']) && 
                strpos(strtolower($title), strtolower($params['primary_keyword'])) !== false
        );
    }
    
    /**
     * Analyze meta description
     *
     * @param string $meta_description Meta description
     * @param array $params Analysis parameters
     * @return array Meta description analysis results
     */
    private function analyze_meta_description($meta_description, $params) {
        $score = 0;
        $issues = array();
        $suggestions = array();
        
        if (empty($meta_description)) {
            $issues[] = 'Meta description is missing';
            $suggestions[] = 'Add a compelling meta description';
            return array('score' => 0, 'issues' => $issues, 'suggestions' => $suggestions);
        }
        
        $length = strlen($meta_description);
        $rules = $this->seo_rules['meta_description_length'];
        
        // Length check
        if ($length >= $rules['min'] && $length <= $rules['max']) {
            $score += $rules['weight'] * 0.7;
        } else {
            $issues[] = 'Meta description length is not optimal';
            if ($length < $rules['min']) {
                $suggestions[] = "Extend meta description to at least {$rules['min']} characters";
            } else {
                $suggestions[] = "Shorten meta description to {$rules['max']} characters or less";
            }
        }
        
        // Call-to-action presence
        $cta_words = array('learn', 'discover', 'find', 'get', 'read', 'explore', 'check', 'see');
        $has_cta = false;
        
        foreach ($cta_words as $cta) {
            if (stripos($meta_description, $cta) !== false) {
                $has_cta = true;
                break;
            }
        }
        
        if ($has_cta) {
            $score += $rules['weight'] * 0.3;
        } else {
            $issues[] = 'No clear call-to-action in meta description';
            $suggestions[] = 'Add an engaging call-to-action to encourage clicks';
        }
        
        return array(
            'score' => $score,
            'length' => $length,
            'issues' => $issues,
            'suggestions' => $suggestions,
            'has_cta' => $has_cta
        );
    }
    
    /**
     * Analyze heading structure
     *
     * @param string $content HTML content
     * @return array Heading structure analysis
     */
    private function analyze_heading_structure($content) {
        $score = 0;
        $issues = array();
        $suggestions = array();
        
        // Count heading tags
        $h1_count = preg_match_all('/<h1[^>]*>/i', $content);
        $h2_count = preg_match_all('/<h2[^>]*>/i', $content);
        $h3_count = preg_match_all('/<h3[^>]*>/i', $content);
        $total_headings = $h1_count + $h2_count + $h3_count;
        
        $rules = $this->seo_rules['heading_structure'];
        
        // H1 count check
        if ($h1_count === $rules['min_h1']) {
            $score += $rules['weight'] * 0.4;
        } else {
            $issues[] = "H1 count is {$h1_count}, should be exactly {$rules['min_h1']}";
            if ($h1_count === 0) {
                $suggestions[] = 'Add an H1 heading to your content';
            } elseif ($h1_count > $rules['max_h1']) {
                $suggestions[] = 'Use only one H1 heading per page';
            }
        }
        
        // Subheading structure check
        if ($h2_count >= $rules['min_h2']) {
            $score += $rules['weight'] * 0.3;
        } else {
            $issues[] = 'Insufficient H2 headings for proper structure';
            $suggestions[] = 'Add more H2 headings to improve content structure';
        }
        
        // H3+ usage check
        if ($h3_count <= $rules['max_h3']) {
            $score += $rules['weight'] * 0.3;
        } else {
            $issues[] = 'Too many H3+ headings may indicate deep nesting';
            $suggestions[] = 'Consider flattening heading hierarchy or combining some sections';
        }
        
        return array(
            'score' => $score,
            'h1_count' => $h1_count,
            'h2_count' => $h2_count,
            'h3_count' => $h3_count,
            'total_headings' => $total_headings,
            'issues' => $issues,
            'suggestions' => $suggestions
        );
    }
    
    /**
     * Analyze content length
     *
     * @param string $content Content to analyze
     * @return array Content length analysis
     */
    private function analyze_content_length($content) {
        $score = 0;
        $issues = array();
        $suggestions = array();
        
        $word_count = str_word_count(strip_tags($content));
        $char_count = strlen(strip_tags($content));
        
        $rules = $this->seo_rules['content_length'];
        
        // Word count check
        if ($word_count >= $rules['min'] && $word_count <= $rules['max']) {
            $score += $rules['weight'];
        } else {
            $issues[] = 'Content length is not optimal for SEO';
            if ($word_count < $rules['min']) {
                $suggestions[] = "Expand content to at least {$rules['min']} words";
            } else {
                $suggestions[] = "Consider shortening content to {$rules['max']} words or less";
            }
        }
        
        return array(
            'score' => $score,
            'word_count' => $word_count,
            'character_count' => $char_count,
            'issues' => $issues,
            'suggestions' => $suggestions
        );
    }
    
    /**
     * Analyze keyword usage
     *
     * @param array $content_data Content data
     * @param array $params Analysis parameters
     * @return array Keyword analysis results
     */
    private function analyze_keywords($content_data, $params) {
        $score = 0;
        $issues = array();
        $suggestions = array();
        $keyword_analysis = array();
        
        $content = $content_data['content'] ?? '';
        $plain_text = strip_tags(strtolower($content));
        $word_count = str_word_count($plain_text);
        
        if (empty($params['primary_keyword']) && empty($params['secondary_keywords'])) {
            return array(
                'score' => 25, // Partial score if no keywords specified
                'keyword_analysis' => array(),
                'issues' => array('No target keywords specified'),
                'suggestions' => array('Define primary and secondary keywords for better optimization')
            );
        }
        
        $rules = $this->seo_rules['keyword_density'];
        
        // Analyze primary keyword
        if (!empty($params['primary_keyword'])) {
            $primary_keyword = strtolower($params['primary_keyword']);
            $keyword_count = substr_count($plain_text, $primary_keyword);
            $keyword_density = $word_count > 0 ? ($keyword_count / $word_count) * 100 : 0;
            
            $keyword_analysis['primary'] = array(
                'keyword' => $params['primary_keyword'],
                'count' => $keyword_count,
                'density' => round($keyword_density, 2),
                'in_title' => isset($content_data['title']) && 
                    stripos($content_data['title'], $params['primary_keyword']) !== false,
                'in_headings' => $this->keyword_in_headings($content, $primary_keyword)
            );
            
            // Score based on optimal density
            if ($keyword_density >= $rules['min'] && $keyword_density <= $rules['max']) {
                $score += $rules['weight'] * 0.6;
            } else {
                $issues[] = "Primary keyword density ({$keyword_density}%) is not optimal";
                if ($keyword_density < $rules['min']) {
                    $suggestions[] = "Increase primary keyword usage to achieve {$rules['min']}-{$rules['max']}% density";
                } else {
                    $suggestions[] = "Reduce primary keyword usage to stay within {$rules['max']}% density";
                }
            }
            
            // Bonus points for keyword in title and headings
            if ($keyword_analysis['primary']['in_title']) {
                $score += $rules['weight'] * 0.2;
            }
            
            if ($keyword_analysis['primary']['in_headings']) {
                $score += $rules['weight'] * 0.2;
            }
        }
        
        // Analyze secondary keywords
        foreach ($params['secondary_keywords'] as $secondary_keyword) {
            $keyword_count = substr_count($plain_text, strtolower($secondary_keyword));
            $keyword_density = $word_count > 0 ? ($keyword_count / $word_count) * 100 : 0;
            
            $keyword_analysis['secondary'][] = array(
                'keyword' => $secondary_keyword,
                'count' => $keyword_count,
                'density' => round($keyword_density, 2)
            );
        }
        
        return array(
            'score' => $score,
            'keyword_analysis' => $keyword_analysis,
            'issues' => $issues,
            'suggestions' => $suggestions,
            'total_word_count' => $word_count
        );
    }
    
    /**
     * Check if keyword appears in headings
     *
     * @param string $content HTML content
     * @param string $keyword Keyword to search for
     * @return bool True if found in headings
     */
    private function keyword_in_headings($content, $keyword) {
        preg_match_all('/<(h[1-6])[^>]*>(.*?)<\/\1>/i', $content, $matches);
        
        foreach ($matches[0] as $heading) {
            if (stripos($heading, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Analyze content readability
     *
     * @param array $content_data Content data
     * @return array Readability analysis results
     */
    private function analyze_readability($content_data) {
        $content = $content_data['content'] ?? '';
        $plain_text = strip_tags($content);
        
        if (empty($plain_text)) {
            return array(
                'score' => 0,
                'flesch_score' => 0,
                'grade_level' => 'Unknown',
                'issues' => array('No content to analyze'),
                'suggestions' => array('Add readable content')
            );
        }
        
        // Calculate Flesch Reading Ease score
        $flesch_score = $this->calculate_flesch_score($plain_text);
        
        // Determine grade level
        $grade_level = $this->determine_grade_level($flesch_score);
        
        // Calculate score based on readability
        $score = $this->calculate_readability_score($flesch_score);
        
        $issues = array();
        $suggestions = array();
        
        if ($flesch_score < 60) {
            $issues[] = 'Content may be difficult to read';
            $suggestions[] = 'Use shorter sentences and simpler words';
        }
        
        if (strlen($plain_text) > 0) {
            $avg_sentence_length = $this->calculate_average_sentence_length($plain_text);
            if ($avg_sentence_length > 25) {
                $issues[] = 'Average sentence length is too long';
                $suggestions[] = 'Break long sentences into shorter ones';
            }
        }
        
        return array(
            'score' => $score,
            'flesch_score' => round($flesch_score, 2),
            'grade_level' => $grade_level,
            'average_sentence_length' => $this->calculate_average_sentence_length($plain_text),
            'issues' => $issues,
            'suggestions' => $suggestions
        );
    }
    
    /**
     * Calculate Flesch Reading Ease score
     *
     * @param string $text Text to analyze
     * @return float Flesch score (0-100, higher is easier)
     */
    private function calculate_flesch_score($text) {
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
        
        return 206.835 - (1.015 * $avg_sentence_length) - (84.6 * $avg_syllables_per_word);
    }
    
    /**
     * Count syllables in a word
     *
     * @param string $word Word to analyze
     * @return int Syllable count
     */
    private function count_syllables($word) {
        $word = strtolower(preg_replace('/[^a-z]/', '', $word));
        
        if (strlen($word) <= 3) return 1;
        
        // Remove common suffixes
        $word = preg_replace('/(?:[^laeiouy]es|ed|[^laeiouy]e)$/', '', $word);
        $word = preg_replace('/^y/', '', $word);
        
        $syllables = preg_match_all('/[aeiouy]{1,2}/', $word);
        
        return max(1, $syllables);
    }
    
    /**
     * Determine grade level from Flesch score
     *
     * @param float $flesch_score Flesch Reading Ease score
     * @return string Grade level
     */
    private function determine_grade_level($flesch_score) {
        if ($flesch_score >= 90) return 'Very Easy (5th grade)';
        if ($flesch_score >= 80) return 'Easy (6th grade)';
        if ($flesch_score >= 70) return 'Fairly Easy (7th grade)';
        if ($flesch_score >= 60) return 'Standard (8-9th grade)';
        if ($flesch_score >= 50) return 'Fairly Difficult (10-12th grade)';
        if ($flesch_score >= 30) return 'Difficult (College)';
        return 'Very Difficult (College graduate)';
    }
    
    /**
     * Calculate readability score based on Flesch score
     *
     * @param float $flesch_score Flesch Reading Ease score
     * @return int Readability score (0-100)
     */
    private function calculate_readability_score($flesch_score) {
        // Convert Flesch score to 0-100 scale for our purposes
        // Target: 60-80 for good readability
        $target_min = 60;
        $target_max = 80;
        
        if ($flesch_score >= $target_min && $flesch_score <= $target_max) {
            return 100; // Perfect readability range
        } elseif ($flesch_score >= 50 && $flesch_score < $target_min) {
            return 75 + (($flesch_score - 50) / 10) * 25; // 75-100
        } elseif ($flesch_score > $target_max && $flesch_score <= 90) {
            return 100 - (($flesch_score - $target_max) / 10) * 25; // 100-75
        } else {
            return max(25, 50 - abs($flesch_score - 65) * 2); // Scale down for very easy or very difficult
        }
    }
    
    /**
     * Calculate average sentence length
     *
     * @param string $text Text to analyze
     * @return float Average words per sentence
     */
    private function calculate_average_sentence_length($text) {
        $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $sentence_count = count($sentences);
        
        if ($sentence_count === 0) return 0;
        
        $words = preg_split('/\s+/', $text);
        $word_count = count($words);
        
        return $word_count / $sentence_count;
    }
    
    /**
     * Analyze EEAT factors
     *
     * @param array $content_data Content data
     * @param array $params Analysis parameters
     * @return array EEAT analysis results
     */
    private function analyze_eeat($content_data, $params) {
        $eeat_score = 0;
        $analysis_details = array();
        
        // Experience factors
        $experience_score = $this->analyze_experience_factors($content_data);
        $eeat_score += $experience_score['score'];
        $analysis_details['experience'] = $experience_score;
        
        // Expertise factors
        $expertise_score = $this->analyze_expertise_factors($content_data, $params);
        $eeat_score += $expertise_score['score'];
        $analysis_details['expertise'] = $expertise_score;
        
        // Authoritativeness factors
        $authority_score = $this->analyze_authority_factors($content_data);
        $eeat_score += $authority_score['score'];
        $analysis_details['authority'] = $authority_score;
        
        // Trustworthiness factors
        $trust_score = $this->analyze_trust_factors($content_data);
        $eeat_score += $trust_score['score'];
        $analysis_details['trust'] = $trust_score;
        
        return array(
            'score' => min(100, $eeat_score),
            'details' => $analysis_details,
            'grade' => $this->get_eeat_grade(min(100, $eeat_score)),
            'improvement_potential' => 100 - min(100, $eeat_score)
        );
    }
    
    /**
     * Analyze experience factors
     *
     * @param array $content_data Content data
     * @return array Experience analysis results
     */
    private function analyze_experience_factors($content_data) {
        $score = 0;
        $factors = array();
        
        $content = $content_data['content'] ?? '';
        
        // Look for first-hand experience indicators
        $experience_indicators = array(
            'i experienced' => 5,
            'personally' => 3,
            'in my experience' => 4,
            'i tried' => 4,
            'i tested' => 5,
            'hands-on' => 3,
            'real-world' => 3
        );
        
        $experience_score = 0;
        $content_lower = strtolower($content);
        
        foreach ($experience_indicators as $indicator => $points) {
            if (strpos($content_lower, $indicator) !== false) {
                $experience_score += $points;
                $factors[] = "Found experience indicator: '{$indicator}'";
            }
        }
        
        // Bonus for specific details
        if (preg_match('/\d+%|\d+ years|\d+ months|\d+ days/i', $content)) {
            $experience_score += 3;
            $factors[] = 'Contains specific metrics and numbers';
        }
        
        // Bonus for before/after comparisons
        if (preg_match('/before|after|compared|versus/i', $content)) {
            $experience_score += 2;
            $factors[] = 'Contains comparative analysis';
        }
        
        return array(
            'score' => min(25, $experience_score),
            'factors' => $factors,
            'experience_level' => $experience_score >= 15 ? 'High' : ($experience_score >= 8 ? 'Medium' : 'Low')
        );
    }
    
    /**
     * Analyze expertise factors
     *
     * @param array $content_data Content data
     * @param array $params Analysis parameters
     * @return array Expertise analysis results
     */
    private function analyze_expertise_factors($content_data, $params) {
        $score = 0;
        $factors = array();
        
        $content = $content_data['content'] ?? '';
        
        // Look for technical accuracy indicators
        $expertise_indicators = array(
            'research' => 3,
            'study' => 4,
            'data' => 3,
            'statistics' => 4,
            'analysis' => 3,
            'according to' => 3,
            'experts say' => 4,
            'studies show' => 5
        );
        
        $expertise_score = 0;
        $content_lower = strtolower($content);
        
        foreach ($expertise_indicators as $indicator => $points) {
            if (strpos($content_lower, $indicator) !== false) {
                $expertise_score += $points;
                $factors[] = "Found expertise indicator: '{$indicator}'";
            }
        }
        
        // Check for credible sources
        if (preg_match('/https?:\/\/(edu|gov|org)\./i', $content)) {
            $expertise_score += 5;
            $factors[] = 'Contains links to authoritative sources';
        }
        
        // Check for technical terminology (appropriate for topic)
        $technical_terms = $this->extract_technical_terms($content);
        if (count($technical_terms) > 5) {
            $expertise_score += 3;
            $factors[] = 'Uses appropriate technical terminology';
        }
        
        return array(
            'score' => min(25, $expertise_score),
            'factors' => $factors,
            'technical_terms_found' => count($technical_terms),
            'expertise_level' => $expertise_score >= 15 ? 'High' : ($expertise_score >= 8 ? 'Medium' : 'Low')
        );
    }
    
    /**
     * Extract technical terms from content
     *
     * @param string $content Content to analyze
     * @return array Technical terms found
     */
    private function extract_technical_terms($content) {
        // Simple pattern for identifying potential technical terms
        // In a real implementation, this would use more sophisticated NLP
        preg_match_all('/\b[A-Z][a-z]+(?:\s+[A-Z][a-z]+)*\b/', $content, $matches);
        
        // Filter out common words
        $common_words = array('The', 'This', 'That', 'With', 'From', 'When', 'Where', 'What', 'How', 'Why');
        $technical_terms = array_filter($matches[0], function($term) use ($common_words) {
            return !in_array($term, $common_words) && strlen($term) > 3;
        });
        
        return array_unique($technical_terms);
    }
    
    /**
     * Analyze authoritativeness factors
     *
     * @param array $content_data Content data
     * @return array Authority analysis results
     */
    private function analyze_authority_factors($content_data) {
        $score = 0;
        $factors = array();
        
        $content = $content_data['content'] ?? '';
        
        // Look for authority-building elements
        $authority_indicators = array(
            'leading' => 2,
            'expert' => 3,
            'professional' => 2,
            'certified' => 4,
            'accredited' => 4,
            'award-winning' => 3,
            'recognized' => 2,
            'established' => 2
        );
        
        $authority_score = 0;
        $content_lower = strtolower($content);
        
        foreach ($authority_indicators as $indicator => $points) {
            if (strpos($content_lower, $indicator) !== false) {
                $authority_score += $points;
                $factors[] = "Found authority indicator: '{$indicator}'";
            }
        }
        
        // Check for quotes from experts
        if (preg_match('/["\'].*["\']/s', $content)) {
            $authority_score += 3;
            $factors[] = 'Contains expert quotes';
        }
        
        // Check for industry-specific language
        if (preg_match('/\b(industry|sector|field|domain|discipline)\b/i', $content)) {
            $authority_score += 2;
            $factors[] = 'Uses industry-specific language';
        }
        
        return array(
            'score' => min(25, $authority_score),
            'factors' => $factors,
            'authority_level' => $authority_score >= 15 ? 'High' : ($authority_score >= 8 ? 'Medium' : 'Low')
        );
    }
    
    /**
     * Analyze trustworthiness factors
     *
     * @param array $content_data Content data
     * @return array Trust analysis results
     */
    private function analyze_trust_factors($content_data) {
        $score = 0;
        $factors = array();
        
        $content = $content_data['content'] ?? '';
        
        // Look for trust-building elements
        $trust_indicators = array(
            'accurate' => 3,
            'verified' => 4,
            'reliable' => 3,
            'transparent' => 3,
            'honest' => 3,
            'ethical' => 2,
            'responsible' => 2,
            'fact-checked' => 5
        );
        
        $trust_score = 0;
        $content_lower = strtolower($content);
        
        foreach ($trust_indicators as $indicator => $points) {
            if (strpos($content_lower, $indicator) !== false) {
                $trust_score += $points;
                $factors[] = "Found trust indicator: '{$indicator}'";
            }
        }
        
        // Check for disclosure statements
        if (preg_match('/disclosure|affiliate| sponsored| compensation/i', $content)) {
            $trust_score += 4;
            $factors[] = 'Contains appropriate disclosures';
        }
        
        // Check for balanced perspective
        if (preg_match('/however|although|on the other hand|alternatively|versus/i', $content)) {
            $trust_score += 3;
            $factors[] = 'Shows balanced perspective';
        }
        
        // Check for source citations
        $source_indicators = preg_match_all('/\([^)]*\d{4}[^)]*\)/', $content);
        if ($source_indicators > 0) {
            $trust_score += min(5, $source_indicators);
            $factors[] = "Contains {$source_indicators} source citations";
        }
        
        return array(
            'score' => min(25, $trust_score),
            'factors' => $factors,
            'trust_level' => $trust_score >= 15 ? 'High' : ($trust_score >= 8 ? 'Medium' : 'Low')
        );
    }
    
    /**
     * Analyze SERP optimization factors
     *
     * @param array $content_data Content data
     * @param array $params Analysis parameters
     * @return array SERP analysis results
     */
    private function analyze_serp_optimization($content_data, $params) {
        $score = 0;
        $analysis_details = array();
        
        // Rich snippets potential
        $rich_snippets_score = $this->analyze_rich_snippets_potential($content_data);
        $score += $rich_snippets_score['score'];
        $analysis_details['rich_snippets'] = $rich_snippets_score;
        
        // Featured snippet optimization
        $featured_snippet_score = $this->analyze_featured_snippet_optimization($content_data);
        $score += $featured_snippet_score['score'];
        $analysis_details['featured_snippet'] = $featured_snippet_score;
        
        // People Also Ask optimization
        $paa_score = $this->analyze_paa_optimization($content_data);
        $score += $paa_score['score'];
        $analysis_details['people_also_ask'] = $paa_score;
        
        return array(
            'score' => min(100, $score),
            'details' => $analysis_details,
            'optimization_potential' => 100 - min(100, $score)
        );
    }
    
    /**
     * Analyze rich snippets potential
     *
     * @param array $content_data Content data
     * @return array Rich snippets analysis
     */
    private function analyze_rich_snippets_potential($content_data) {
        $score = 0;
        $factors = array();
        
        $content = $content_data['content'] ?? '';
        
        // Check for structured data indicators
        $structured_data_indicators = array(
            'rating' => 'Reviews/Rating',
            'price' => 'Product/Service',
            'date' => 'Event/Article',
            'author' => 'Article',
            'recipe' => 'Recipe',
            'how to' => 'How-to'
        );
        
        foreach ($structured_data_indicators as $indicator => $type) {
            if (preg_match('/\b' . preg_quote($indicator, '/') . '\b/i', $content)) {
                $score += 10;
                $factors[] = "Potential for {$type} rich snippets";
            }
        }
        
        // Check for lists (good for rich snippets)
        if (preg_match('/<ul|<ol|<li/i', $content)) {
            $score += 5;
            $factors[] = 'Contains list structure for rich snippets';
        }
        
        // Check for Q&A format
        if (preg_match('/\b(what|how|why|when|where|who)\b.*\?/i', $content)) {
            $score += 8;
            $factors[] = 'Q&A format suitable for rich snippets';
        }
        
        return array(
            'score' => min(30, $score),
            'factors' => $factors,
            'rich_snippet_types' => count($factors)
        );
    }
    
    /**
     * Analyze featured snippet optimization
     *
     * @param array $content_data Content data
     * @return array Featured snippet analysis
     */
    private function analyze_featured_snippet_optimization($content_data) {
        $score = 0;
        $factors = array();
        
        $content = $content_data['content'] ?? '';
        
        // Check for direct answers
        $answer_patterns = array(
            '/\b(is|are|means|definition|definition of)\b.+/i' => 'Definition format',
            '/\b(step \d+|first|second|third|next|finally)\b/i' => 'Step-by-step format',
            '/\b(yes|no|true|false)\b/i' => 'Direct yes/no answer',
            '/\b(\d+)\b.*\b(years?|months?|days?|hours?|minutes?)\b/i' => 'Time-based answer'
        );
        
        foreach ($answer_patterns as $pattern => $type) {
            if (preg_match($pattern, $content)) {
                $score += 8;
                $factors[] = "Optimized for {$type} featured snippets";
            }
        }
        
        // Check for concise explanations
        $paragraphs = preg_split('/\n\s*\n/', $content);
        foreach ($paragraphs as $paragraph) {
            if (strlen(strip_tags($paragraph)) < 150 && strlen(strip_tags($paragraph)) > 50) {
                $score += 3;
                $factors[] = 'Contains concise paragraph suitable for featured snippet';
                break;
            }
        }
        
        return array(
            'score' => min(25, $score),
            'factors' => $factors,
            'snippet_optimization' => count($factors) > 0 ? 'Good' : 'Needs improvement'
        );
    }
    
    /**
     * Analyze People Also Ask optimization
     *
     * @param array $content_data Content data
     * @return array PAA analysis
     */
    private function analyze_paa_optimization($content_data) {
        $score = 0;
        $factors = array();
        
        $content = $content_data['content'] ?? '';
        
        // Check for question patterns
        $question_patterns = array(
            '/\bwhat is\b.+\?/i' => 'What is question',
            '/\bhow to\b.+\?/i' => 'How-to question',
            '/\bwhy does\b.+\?/i' => 'Why question',
            '/\bwhen should\b.+\?/i' => 'When question',
            '/\bwhere can\b.+\?/i' => 'Where question',
            '/\bwho is\b.+\?/i' => 'Who question'
        );
        
        foreach ($question_patterns as $pattern => $type) {
            if (preg_match($pattern, $content)) {
                $score += 6;
                $factors[] = "Optimized for {$type} PAA";
            }
        }
        
        // Check for FAQ format
        if (preg_match('/(frequently asked questions|faq|common questions)/i', $content)) {
            $score += 10;
            $factors[] = 'Contains FAQ section';
        }
        
        return array(
            'score' => min(20, $score),
            'factors' => $factors,
            'question_optimization' => count($factors) > 0 ? 'Good' : 'Needs improvement'
        );
    }
    
    /**
     * Check content authenticity
     *
     * @param array $content_data Content data
     * @param array $params Analysis parameters
     * @return array Authenticity analysis
     */
    private function check_content_authenticity($content_data, $params) {
        $authenticity_score = 100;
        $flags = array();
        
        $content = $content_data['content'] ?? '';
        $title = $content_data['title'] ?? '';
        
        // Check for AI-generated content indicators (basic heuristics)
        $ai_indicators = array(
            'it\'s important to note that' => -10,
            'in conclusion' => -5,
            'furthermore' => -5,
            'additionally' => -5,
            'moreover' => -5,
            'in summary' => -5
        );
        
        foreach ($ai_indicators as $indicator => $penalty) {
            if (stripos($content, $indicator) !== false) {
                $authenticity_score += $penalty;
                $flags[] = "Found AI-like phrase: '{$indicator}'";
            }
        }
        
        // Check for repetitive patterns
        $word_count = str_word_count(strtolower($content));
        $unique_words = count(array_unique(explode(' ', strtolower($content))));
        $repetition_ratio = $unique_words / $word_count;
        
        if ($repetition_ratio < 0.3) {
            $authenticity_score -= 15;
            $flags[] = 'Low word variety may indicate AI generation';
        }
        
        // Bonus for source citations
        if (preg_match('/\([^)]*\d{4}[^)]*\)/', $content) || preg_match('/according to/i', $content)) {
            $authenticity_score += 10;
            $flags[] = 'Contains source citations';
        }
        
        // Bonus for personal anecdotes
        $personal_indicators = array('i remember', 'i think', 'in my opinion', 'personally');
        foreach ($personal_indicators as $indicator) {
            if (stripos($content, $indicator) !== false) {
                $authenticity_score += 5;
                $flags[] = 'Contains personal perspective';
                break;
            }
        }
        
        return array(
            'score' => max(0, $authenticity_score),
            'flags' => $flags,
            'authenticity_level' => $authenticity_score >= 80 ? 'High' : ($authenticity_score >= 60 ? 'Medium' : 'Low'),
            'recommendation' => $authenticity_score < 70 ? 'Review content for authenticity' : 'Content appears authentic'
        );
    }
    
    /**
     * Calculate overall content score
     *
     * @param array $analysis_results Analysis results
     * @return int Overall score (0-100)
     */
    private function calculate_overall_score($analysis_results) {
        $weights = array(
            'seo_score' => 0.3,
            'readability_score' => 0.25,
            'eeat_score' => 0.3,
            'serp_optimization_score' => 0.15
        );
        
        $total_score = 0;
        $total_weight = 0;
        
        foreach ($weights as $score_type => $weight) {
            if (isset($analysis_results[$score_type]) && $analysis_results[$score_type] > 0) {
                $total_score += $analysis_results[$score_type] * $weight;
                $total_weight += $weight;
            }
        }
        
        return $total_weight > 0 ? round($total_score / $total_weight) : 0;
    }
    
    /**
     * Get SEO grade from score
     *
     * @param int $score SEO score
     * @return string SEO grade
     */
    private function get_seo_grade($score) {
        if ($score >= 90) return 'A+';
        if ($score >= 80) return 'A';
        if ($score >= 70) return 'B';
        if ($score >= 60) return 'C';
        if ($score >= 50) return 'D';
        return 'F';
    }
    
    /**
     * Get EEAT grade from score
     *
     * @param int $score EEAT score
     * @return string EEAT grade
     */
    private function get_eeat_grade($score) {
        if ($score >= 90) return 'Excellent';
        if ($score >= 75) return 'Good';
        if ($score >= 60) return 'Fair';
        if ($score >= 45) return 'Poor';
        return 'Needs Improvement';
    }
    
    /**
     * Generate comprehensive recommendations
     *
     * @param array $analysis_results Analysis results
     * @return array Recommendations
     */
    private function generate_recommendations($analysis_results) {
        $recommendations = array();
        
        // SEO recommendations
        if (isset($analysis_results['analysis_details']['seo'])) {
            $seo_details = $analysis_results['analysis_details']['seo']['details'];
            foreach ($seo_details as $factor => $details) {
                if (!empty($details['suggestions'])) {
                    foreach ($details['suggestions'] as $suggestion) {
                        $recommendations[] = array(
                            'category' => 'SEO',
                            'factor' => $factor,
                            'priority' => 'High',
                            'recommendation' => $suggestion,
                            'impact' => 'High'
                        );
                    }
                }
            }
        }
        
        // Readability recommendations
        if (isset($analysis_results['analysis_details']['readability'])) {
            $readability_details = $analysis_results['analysis_details']['readability'];
            if (!empty($readability_details['suggestions'])) {
                foreach ($readability_details['suggestions'] as $suggestion) {
                    $recommendations[] = array(
                        'category' => 'Readability',
                        'factor' => 'Content Structure',
                        'priority' => 'Medium',
                        'recommendation' => $suggestion,
                        'impact' => 'Medium'
                    );
                }
            }
        }
        
        // EEAT recommendations
        if (isset($analysis_results['analysis_details']['eeat'])) {
            $eeat_details = $analysis_results['analysis_details']['eeat']['details'];
            foreach ($eeat_details as $factor => $details) {
                if ($details['score'] < 15) { // Less than 60% of possible score
                    $improvement = $this->get_eeat_improvement_suggestions($factor, $details);
                    foreach ($improvement as $suggestion) {
                        $recommendations[] = array(
                            'category' => 'EEAT',
                            'factor' => ucfirst($factor),
                            'priority' => 'High',
                            'recommendation' => $suggestion,
                            'impact' => 'High'
                        );
                    }
                }
            }
        }
        
        // Sort by priority
        usort($recommendations, function($a, $b) {
            $priority_order = array('High' => 3, 'Medium' => 2, 'Low' => 1);
            return $priority_order[$b['priority']] - $priority_order[$a['priority']];
        });
        
        return array_slice($recommendations, 0, 10); // Return top 10 recommendations
    }
    
    /**
     * Get EEAT improvement suggestions
     *
     * @param string $factor EEAT factor
     * @param array $details Factor details
     * @return array Improvement suggestions
     */
    private function get_eeat_improvement_suggestions($factor, $details) {
        $suggestions = array();
        
        switch ($factor) {
            case 'experience':
                $suggestions[] = 'Add personal experiences and first-hand accounts';
                $suggestions[] = 'Include specific examples and case studies';
                $suggestions[] = 'Share detailed observations and insights';
                break;
                
            case 'expertise':
                $suggestions[] = 'Cite credible sources and studies';
                $suggestions[] = 'Use technical terminology appropriately';
                $suggestions[] = 'Reference industry standards and best practices';
                break;
                
            case 'authority':
                $suggestions[] = 'Mention relevant credentials and qualifications';
                $suggestions[] = 'Reference recognized experts in the field';
                $suggestions[] = 'Use industry-specific language and concepts';
                break;
                
            case 'trust':
                $suggestions[] = 'Add transparent disclosures and disclaimers';
                $suggestions[] = 'Provide balanced perspectives and multiple viewpoints';
                $suggestions[] = 'Include proper citations and source attributions';
                break;
        }
        
        return $suggestions;
    }
    
    /**
     * Generate optimization suggestions
     *
     * @param array $content_data Original content data
     * @param array $analysis_results Analysis results
     * @param array $params Analysis parameters
     * @return array Optimization suggestions
     */
    private function generate_optimization_suggestions($content_data, $analysis_results, $params) {
        $suggestions = array();
        
        // Title optimization
        if (isset($analysis_results['analysis_details']['seo']['details']['title'])) {
            $title_details = $analysis_results['analysis_details']['seo']['details']['title'];
            if ($title_details['length'] < 30) {
                $suggestions[] = array(
                    'type' => 'title',
                    'current' => $content_data['title'] ?? '',
                    'suggestion' => 'Extend title to include primary keyword and compelling hook',
                    'improved' => $this->suggest_improved_title($content_data['title'] ?? '', $params)
                );
            }
        }
        
        // Meta description optimization
        if (isset($analysis_results['analysis_details']['seo']['details']['meta_description'])) {
            $meta_details = $analysis_results['analysis_details']['seo']['details']['meta_description'];
            if ($meta_details['length'] < 120 || $meta_details['length'] > 160) {
                $suggestions[] = array(
                    'type' => 'meta_description',
                    'current' => $content_data['meta_description'] ?? '',
                    'suggestion' => 'Optimize meta description length and add compelling CTA',
                    'improved' => $this->suggest_improved_meta_description($content_data, $params)
                );
            }
        }
        
        // Content structure suggestions
        if (isset($analysis_results['analysis_details']['seo']['details']['heading_structure'])) {
            $heading_details = $analysis_results['analysis_details']['seo']['details']['heading_structure'];
            if ($heading_details['h2_count'] < 2) {
                $suggestions[] = array(
                    'type' => 'content_structure',
                    'suggestion' => 'Add more H2 headings to improve content organization',
                    'improved' => $this->suggest_heading_structure($content_data['content'] ?? '')
                );
            }
        }
        
        return $suggestions;
    }
    
    /**
     * Suggest improved title
     *
     * @param string $current_title Current title
     * @param array $params Analysis parameters
     * @return string Improved title suggestion
     */
    private function suggest_improved_title($current_title, $params) {
        $primary_keyword = $params['primary_keyword'] ?? '';
        
        if (empty($primary_keyword)) {
            return $current_title . ' - Expert Guide';
        }
        
        $templates = array(
            "How to {keyword}: Complete Expert Guide {year}",
            "The Ultimate {keyword} Guide: Expert Tips & Strategies",
            "{keyword} Explained: Expert Analysis & Best Practices",
            "Master {keyword}: Expert Insights & Proven Methods"
        );
        
        $template = $templates[array_rand($templates)];
        $year = date('Y');
        
        return str_replace(['{keyword}', '{year}'], [$primary_keyword, $year], $template);
    }
    
    /**
     * Suggest improved meta description
     *
     * @param array $content_data Content data
     * @param array $params Analysis parameters
     * @return string Improved meta description
     */
    private function suggest_improved_meta_description($content_data, $params) {
        $primary_keyword = $params['primary_keyword'] ?? '';
        $content_excerpt = wp_trim_words(strip_tags($content_data['content'] ?? ''), 20);
        
        $templates = array(
            "Discover everything you need to know about {keyword}. Expert insights, proven strategies, and actionable tips in this comprehensive guide. Read more to learn {excerpt}.",
            "Learn {keyword} from the experts. This detailed guide covers essential strategies, best practices, and real-world examples. {excerpt} Get started today!",
            "Master {keyword} with our expert guide. Find proven methods, insider tips, and step-by-step instructions. {excerpt} Click to read more!"
        );
        
        $template = $templates[array_rand($templates)];
        
        return str_replace(['{keyword}', '{excerpt}'], [$primary_keyword, $content_excerpt], $template);
    }
    
    /**
     * Suggest heading structure improvements
     *
     * @param string $content Current content
     * @return array Improved heading structure
     */
    private function suggest_heading_structure($content) {
        $suggestions = array();
        
        // Extract main topics from content
        $sentences = preg_split('/[.!?]+/', strip_tags($content));
        $main_topics = array();
        
        foreach (array_slice($sentences, 0, 10) as $sentence) {
            $words = explode(' ', trim($sentence));
            if (count($words) > 3 && count($words) < 15) {
                $main_topics[] = trim($sentence);
                if (count($main_topics) >= 4) break;
            }
        }
        
        foreach ($main_topics as $index => $topic) {
            $suggestions[] = "<h2>" . trim($topic) . "</h2>";
        }
        
        return $suggestions;
    }
    
    /**
     * Get service metrics
     *
     * @return array Service metrics
     */
    public function get_metrics() {
        return array(
            'service' => 'ContentAnalyzer',
            'config' => $this->config,
            'seo_rules' => array_keys($this->seo_rules),
            'readability_thresholds' => $this->readability_thresholds,
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
            // Test basic functionality
            $test_content = array(
                'title' => 'Test Title for Health Check',
                'content' => '<h1>Test Content</h1><p>This is a test paragraph with some content to analyze.</p>',
                'meta_description' => 'This is a test meta description for health checking purposes.'
            );
            
            $result = $this->analyze_content($test_content, array(
                'primary_keyword' => 'test keyword',
                'analyze_eeat' => false,
                'analyze_serp' => false,
                'check_authenticity' => false
            ));
            
            // Check if analysis returned expected structure
            $required_fields = array('overall_score', 'seo_score', 'readability_score', 'analysis_details');
            foreach ($required_fields as $field) {
                if (!isset($result[$field])) {
                    return false;
                }
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('ContentAnalyzer health check failed', array(
                'error' => $e->getMessage()
            ));
            return false;
        }
    }
}