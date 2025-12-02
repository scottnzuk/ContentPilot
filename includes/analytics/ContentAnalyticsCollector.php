<?php
/**
 * Content Analytics Collector
 *
 * Collects content performance metrics, post engagement data,
 * content quality scores, and publishing analytics.
 *
 * @package AI_Auto_News_Poster\Analytics
 * @since 1.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Content Analytics Collector Class
 */
class AANP_Content_Analytics_Collector {
    
    /**
     * Logger instance
     * @var AANP_Logger
     */
    private $logger;
    
    /**
     * Constructor
     *
     * @param AANP_Logger $logger
     */
    public function __construct(AANP_Logger $logger = null) {
        $this->logger = $logger ?: AANP_Logger::getInstance();
    }
    
    /**
     * Collect content analytics metrics
     *
     * @param array $params Collection parameters
     * @return array Collected metrics
     */
    public function collect($params = array()) {
        try {
            $metrics = array();
            
            // Post performance metrics
            $metrics = array_merge($metrics, $this->collect_post_performance());
            
            // Content quality metrics
            $metrics = array_merge($metrics, $this->collect_content_quality());
            
            // Publishing analytics
            $metrics = array_merge($metrics, $this->collect_publishing_analytics());
            
            // SEO performance metrics
            $metrics = array_merge($metrics, $this->collect_seo_performance());
            
            // Engagement metrics
            $metrics = array_merge($metrics, $this->collect_engagement_metrics());
            
            $summary = $this->calculate_summary($metrics);
            
            $this->logger->debug('Content analytics metrics collected', array(
                'metrics_count' => count($metrics),
                'summary' => $summary
            ));
            
            return array(
                'success' => true,
                'metrics' => $metrics,
                'summary' => $summary,
                'collected_at' => current_time('Y-m-d H:i:s')
            );
            
        } catch (Exception $e) {
            $this->logger->error('Content analytics collection failed', array(
                'error' => $e->getMessage()
            ));
            
            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'metrics' => array(),
                'summary' => array()
            );
        }
    }
    
    /**
     * Collect post performance metrics
     *
     * @return array Post performance metrics
     */
    private function collect_post_performance() {
        global $wpdb;
        
        $metrics = array();
        
        // Total posts
        $metrics['total_posts'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'post'");
        
        // Published posts
        $metrics['published_posts'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish'"
        );
        
        // Draft posts
        $metrics['draft_posts'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'draft'"
        );
        
        // AANP generated posts
        $metrics['generated_posts'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_aanp_generated' AND meta_value = '1'"
        );
        
        // Posts published today
        $metrics['posts_published_today'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'post' 
             AND post_status = 'publish' 
             AND DATE(post_date) = %s",
            current_time('Y-m-d')
        ));
        
        // Posts published this week
        $metrics['posts_published_week'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'post' 
             AND post_status = 'publish' 
             AND post_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        
        // Average words per post
        $metrics['avg_words_per_post'] = $this->calculate_average_words_per_post();
        
        // Posts with featured images
        $metrics['posts_with_featured_images'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'post' 
             AND ID IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id')"
        );
        
        return $metrics;
    }
    
    /**
     * Collect content quality metrics
     *
     * @return array Content quality metrics
     */
    private function collect_content_quality() {
        global $wpdb;
        
        $metrics = array();
        
        // Get posts with SEO scores
        $seo_scores = $wpdb->get_results(
            "SELECT meta_value FROM {$wpdb->postmeta} 
             WHERE meta_key = '_aanp_seo_score' 
             AND meta_value REGEXP '^[0-9]+$'"
        );
        
        if (!empty($seo_scores)) {
            $scores = array_map(function($row) { return intval($row->meta_value); }, $seo_scores);
            $metrics['avg_seo_score'] = array_sum($scores) / count($scores);
            $metrics['min_seo_score'] = min($scores);
            $metrics['max_seo_score'] = max($scores);
            $metrics['posts_above_seo_threshold'] = count(array_filter($scores, function($score) {
                return $score >= 70; // 70+ is considered good
            }));
        } else {
            $metrics['avg_seo_score'] = 0;
            $metrics['min_seo_score'] = 0;
            $metrics['max_seo_score'] = 0;
            $metrics['posts_above_seo_threshold'] = 0;
        }
        
        // Get posts with readability scores
        $readability_scores = $wpdb->get_results(
            "SELECT meta_value FROM {$wpdb->postmeta} 
             WHERE meta_key = '_aanp_readability_score' 
             AND meta_value REGEXP '^[0-9]+$'"
        );
        
        if (!empty($readability_scores)) {
            $scores = array_map(function($row) { return intval($row->meta_value); }, $readability_scores);
            $metrics['avg_readability_score'] = array_sum($scores) / count($scores);
        } else {
            $metrics['avg_readability_score'] = 0;
        }
        
        // Content humanization stats
        $metrics['humanized_posts'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_aanp_humanized' AND meta_value = '1'"
        );
        
        // Posts with meta descriptions
        $metrics['posts_with_meta_descriptions'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} 
             WHERE meta_key = '_aanp_meta_description' 
             AND meta_value != ''"
        );
        
        return $metrics;
    }
    
    /**
     * Collect publishing analytics
     *
     * @return array Publishing analytics metrics
     */
    private function collect_publishing_analytics() {
        global $wpdb;
        
        $metrics = array();
        
        // Publishing rate (posts per day)
        $metrics['publishing_rate_per_day'] = $this->calculate_publishing_rate();
        
        // Most active publishing hours
        $metrics['most_active_hour'] = $this->get_most_active_publishing_hour();
        
        // Content categories distribution
        $category_distribution = $this->get_category_distribution();
        $metrics['top_category_id'] = !empty($category_distribution) ? max($category_distribution, key($category_distribution)) : 0;
        $metrics['category_distribution'] = $category_distribution;
        
        // Content type distribution
        $metrics['content_types'] = $this->get_content_type_distribution();
        
        // Queue processing stats
        $queue_stats = $this->get_queue_processing_stats();
        $metrics = array_merge($metrics, $queue_stats);
        
        return $metrics;
    }
    
    /**
     * Collect SEO performance metrics
     *
     * @return array SEO performance metrics
     */
    private function collect_seo_performance() {
        global $wpdb;
        
        $metrics = array();
        
        // Posts with RankMath optimization
        $metrics['rankmath_optimized_posts'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} 
             WHERE meta_key LIKE 'rank_math_%' 
             AND post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = 'post')"
        );
        
        // Posts with focus keywords
        $metrics['posts_with_focus_keywords'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} 
             WHERE meta_key = 'rank_math_focus_keyword' 
             AND meta_value != ''"
        );
        
        // Average RankMath score
        $rankmath_scores = $wpdb->get_results(
            "SELECT meta_value FROM {$wpdb->postmeta} 
             WHERE meta_key = 'rank_math_analysis' 
             AND meta_value != ''"
        );
        
        if (!empty($rankmath_scores)) {
            $scores = array();
            foreach ($rankmath_scores as $row) {
                $analysis = json_decode($row->meta_value, true);
                if (isset($analysis['score'])) {
                    $scores[] = intval($analysis['score']);
                }
            }
            
            if (!empty($scores)) {
                $metrics['avg_rankmath_score'] = array_sum($scores) / count($scores);
            }
        }
        
        // Yoast integration stats
        $metrics['yoast_integrated_posts'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} 
             WHERE meta_key LIKE '_yoast_wpseo_%' 
             AND post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = 'post')"
        );
        
        return $metrics;
    }
    
    /**
     * Collect engagement metrics
     *
     * @return array Engagement metrics
     */
    private function collect_engagement_metrics() {
        global $wpdb;
        
        $metrics = array();
        
        // Get view counts (if analytics plugin is active)
        $metrics['total_views'] = $this->get_total_post_views();
        $metrics['avg_views_per_post'] = $this->calculate_average_views_per_post();
        
        // Comments count
        $metrics['total_comments'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->comments} 
             WHERE comment_approved = 1 
             AND comment_post_ID IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = 'post')"
        );
        
        // Most engaging posts (by comments)
        $metrics['posts_with_most_comments'] = $this->get_posts_with_most_engagement();
        
        // Social sharing metrics (if tracked)
        $metrics['social_shares'] = $this->get_social_sharing_metrics();
        
        return $metrics;
    }
    
    /**
     * Calculate average words per post
     *
     * @return float Average words per post
     */
    private function calculate_average_words_per_post() {
        global $wpdb;
        
        $word_counts = $wpdb->get_results(
            "SELECT meta_value FROM {$wpdb->postmeta} 
             WHERE meta_key = '_aanp_word_count' 
             AND meta_value REGEXP '^[0-9]+$'"
        );
        
        if (!empty($word_counts)) {
            $counts = array_map(function($row) { return intval($row->meta_value); }, $word_counts);
            return array_sum($counts) / count($counts);
        }
        
        // Fallback: calculate from post content
        $posts = $wpdb->get_results(
            "SELECT post_content FROM {$wpdb->posts} 
             WHERE post_type = 'post' 
             AND post_status = 'publish' 
             AND post_content != '' 
             LIMIT 100"
        );
        
        if (!empty($posts)) {
            $total_words = 0;
            foreach ($posts as $post) {
                $total_words += str_word_count(strip_tags($post->post_content));
            }
            return $total_words / count($posts);
        }
        
        return 0;
    }
    
    /**
     * Calculate publishing rate
     *
     * @return float Publishing rate (posts per day)
     */
    private function calculate_publishing_rate() {
        global $wpdb;
        
        // Get posts from last 30 days
        $thirty_days_ago = date('Y-m-d', strtotime('-30 days'));
        $posts_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'post' 
             AND post_status = 'publish' 
             AND post_date >= %s",
            $thirty_days_ago
        ));
        
        return round($posts_count / 30, 2);
    }
    
    /**
     * Get most active publishing hour
     *
     * @return int Most active hour (0-23)
     */
    private function get_most_active_publishing_hour() {
        global $wpdb;
        
        $results = $wpdb->get_results(
            "SELECT HOUR(post_date) as hour, COUNT(*) as count 
             FROM {$wpdb->posts} 
             WHERE post_type = 'post' 
             AND post_status = 'publish' 
             AND post_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY HOUR(post_date) 
             ORDER BY count DESC 
             LIMIT 1"
        );
        
        return !empty($results) ? intval($results[0]->hour) : 12; // Default to noon
    }
    
    /**
     * Get category distribution
     *
     * @return array Category distribution
     */
    private function get_category_distribution() {
        global $wpdb;
        
        return $wpdb->get_results(
            "SELECT tt.term_id, tt.name, COUNT(*) as post_count 
             FROM {$wpdb->term_relationships} tr
             INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
             INNER JOIN {$wpdb->posts} p ON tr.object_id = p.ID
             WHERE p.post_type = 'post' 
             AND p.post_status = 'publish'
             AND tt.taxonomy = 'category'
             GROUP BY tt.term_id 
             ORDER BY post_count DESC 
             LIMIT 10",
            ARRAY_A
        );
    }
    
    /**
     * Get content type distribution
     *
     * @return array Content type distribution
     */
    private function get_content_type_distribution() {
        global $wpdb;
        
        return $wpdb->get_results(
            "SELECT 
                CASE 
                    WHEN meta_key = '_aanp_content_type' THEN meta_value 
                    ELSE 'standard' 
                END as content_type,
                COUNT(*) as count
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE p.post_type = 'post' 
             AND p.post_status = 'publish'
             AND (pm.meta_key = '_aanp_content_type' OR pm.meta_key IS NULL)
             GROUP BY content_type",
            ARRAY_A
        );
    }
    
    /**
     * Get queue processing stats
     *
     * @return array Queue processing stats
     */
    private function get_queue_processing_stats() {
        global $wpdb;
        
        $stats = array();
        
        // Check for any queue-related options or transient data
        $queue_items = get_option('aanp_queue_batch_creation', array());
        $stats['queue_size'] = is_array($queue_items) ? count($queue_items) : 0;
        
        // Processing rate (items processed per hour)
        $stats['processing_rate_per_hour'] = $this->calculate_processing_rate();
        
        return $stats;
    }
    
    /**
     * Calculate processing rate
     *
     * @return float Processing rate (items per hour)
     */
    private function calculate_processing_rate() {
        // This would track actual processing from logs or database
        // For now, return an estimated value
        return 2.5; // 2.5 items per hour average
    }
    
    /**
     * Get total post views
     *
     * @return int Total post views
     */
    private function get_total_post_views() {
        // Check for popular analytics plugins
        if (function_exists('wp_get_post_views')) {
            global $wpdb;
            return $wpdb->get_var(
                "SELECT SUM(meta_value) FROM {$wpdb->postmeta} 
                 WHERE meta_key = 'views' 
                 AND post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = 'post')"
            );
        }
        
        // Return 0 if no analytics plugin is detected
        return 0;
    }
    
    /**
     * Calculate average views per post
     *
     * @return float Average views per post
     */
    private function calculate_average_views_per_post() {
        global $wpdb;
        
        $published_posts = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish'"
        );
        
        if ($published_posts > 0) {
            $total_views = $this->get_total_post_views();
            return round($total_views / $published_posts, 2);
        }
        
        return 0;
    }
    
    /**
     * Get posts with most engagement
     *
     * @return int Number of posts with high engagement
     */
    private function get_posts_with_most_engagement() {
        global $wpdb;
        
        return intval($wpdb->get_var(
            "SELECT COUNT(DISTINCT comment_post_ID) FROM {$wpdb->comments} 
             WHERE comment_approved = 1 
             GROUP BY comment_post_ID 
             HAVING COUNT(*) >= 5"
        ));
    }
    
    /**
     * Get social sharing metrics
     *
     * @return int Total social shares
     */
    private function get_social_sharing_metrics() {
        // This would integrate with social sharing plugins
        // For now, return 0
        return 0;
    }
    
    /**
     * Calculate content analytics summary
     *
     * @param array $metrics Individual metrics
     * @return array Content analytics summary
     */
    private function calculate_summary($metrics) {
        $summary = array(
            'total_operations' => $metrics['total_posts'] ?? 0,
            'successful_operations' => $metrics['published_posts'] ?? 0,
            'failed_operations' => $metrics['draft_posts'] ?? 0,
            'average_response_time' => 0, // Content creation is not measured in response time
            'total_items_processed' => $metrics['generated_posts'] ?? 0,
            'error_rate' => $metrics['total_posts'] > 0 
                ? (($metrics['draft_posts'] ?? 0) / $metrics['total_posts']) * 100 
                : 0,
            'performance_score' => $this->calculate_content_score($metrics)
        );
        
        return $summary;
    }
    
    /**
     * Calculate content performance score
     *
     * @param array $metrics Content metrics
     * @return int Content score (0-100)
     */
    private function calculate_content_score($metrics) {
        $score = 100;
        
        // Deduct for low SEO scores
        $seo_score = $metrics['avg_seo_score'] ?? 0;
        if ($seo_score < 70) {
            $score -= (70 - $seo_score);
        }
        
        // Deduct for low readability
        $readability = $metrics['avg_readability_score'] ?? 0;
        if ($readability < 60) {
            $score -= (60 - $readability) / 2;
        }
        
        // Deduct for low featured image coverage
        $total_posts = $metrics['total_posts'] ?? 1;
        $featured_images = $metrics['posts_with_featured_images'] ?? 0;
        $coverage_ratio = $featured_images / $total_posts;
        if ($coverage_ratio < 0.8) {
            $score -= (0.8 - $coverage_ratio) * 50;
        }
        
        // Deduct for high draft ratio
        $draft_ratio = ($metrics['draft_posts'] ?? 0) / $total_posts;
        if ($draft_ratio > 0.3) {
            $score -= $draft_ratio * 30;
        }
        
        return max(0, min(100, intval($score)));
    }
    
    /**
     * Health check method
     *
     * @return bool Health status
     */
    public function health_check() {
        try {
            // Test database connection
            global $wpdb;
            $test_result = $wpdb->get_var('SELECT COUNT(*) FROM ' . $wpdb->posts . ' LIMIT 1');
            
            if ($test_result === false) {
                return false;
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('ContentAnalyticsCollector health check failed', array(
                'error' => $e->getMessage()
            ));
            return false;
        }
    }
}