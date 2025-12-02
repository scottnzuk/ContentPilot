<?php
/**
 * Pro Features Class - All Features Now Free
 *
 * @package AI_Auto_News_Poster
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Pro Features Class - Now All Features Are Free
 * 
 * This class has been emptied as all features are now included
 * in the core plugin with no license validation or restrictions.
 */
class AANP_Pro_Features {
    
    /**
     * Constructor - All features now available
     */
    public function __construct() {
        // All features are now free - no initialization needed
        // Previously pro features are now part of core functionality
    }
    
    /**
     * Legacy method - Always returns true as all features are free
     *
     * @return bool Always true
     */
    public static function is_pro_active() {
        return true;
    }
    
    /**
     * Legacy method - Always returns all features
     *
     * @return array All features available
     */
    public static function get_pro_features_status() {
        return array(
            'all_features' => array(
                'available' => true,
                'title' => __('All Features Free', 'ai-auto-news-poster'),
                'description' => __('All features are now included in the core plugin!', 'ai-auto-news-poster')
            )
        );
    }
}

// Initialize (now empty) Pro features class
new AANP_Pro_Features();