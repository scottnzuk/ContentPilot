<?php
/**
 * Post Creator Class
 *
 * @package AI_Auto_News_Poster
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class CP_Post_Creator {
    
    private $cache_manager;
    private $logger;
    private $security_manager;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->cache_manager = new CP_Cache_Manager();
        $this->logger = CP_Logger::getInstance();
        $this->security_manager = new CP_Security_Manager();
    }
    
    /**
     * Create WordPress post from generated content with enhanced security and error handling
     *
     * @param array $generated_content Generated content data
     * @param array $source_article Original article data
     * @param string $nonce Nonce for security verification
     * @return int|array Post ID on success, error array on failure
     */
    public function create_post($generated_content, $source_article, $nonce = '') {
        try {
            // Verify nonce for security
            if (!$this->verify_nonce($nonce, 'cp_create_post')) {
                $error = 'Security verification failed';
                $this->logger->warning('Post creation attempt with invalid nonce', array(
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ));
                return array('error' => $error);
            }
            
            // Validate input parameters
            $validation_result = $this->validate_post_data($generated_content, $source_article);
            if (!$validation_result['valid']) {
                $this->logger->warning('Post creation validation failed', array(
                    'errors' => $validation_result['errors']
                ));
                return array('error' => 'Validation failed: ' . implode(', ', $validation_result['errors']));
            }
            
            // Sanitize input data
            $sanitized_content = $this->sanitize_input_data($generated_content, $source_article);
            
            $this->logger->info('Starting post creation process', array(
                'title_length' => strlen($sanitized_content['title']),
                'content_length' => strlen($sanitized_content['content'])
            ));
            
            // Start database transaction for consistency
            global $wpdb;
            $wpdb->query('START TRANSACTION');
            
            try {
                // Create the post
                $post_id = $this->create_post_internal($sanitized_content, $source_article);
                
                if (is_wp_error($post_id)) {
                    throw new Exception('Post creation failed: ' . $post_id->get_error_message());
                }
                
                if (!$post_id) {
                    throw new Exception('Post creation returned invalid ID');
                }
                
                // Set post categories with error handling
                $this->set_post_categories($post_id, $sanitized_content);
                
                // Add post tags
                $this->add_post_tags($post_id, $sanitized_content, $source_article);
                
                // Log the creation
                $this->log_post_creation($post_id, $source_article);
                
                // Cache management
                $this->invalidate_related_caches();
                
                // Trigger actions
                do_action('cp_post_created', $post_id, $sanitized_content);
                
                // Commit transaction
                $wpdb->query('COMMIT');
                
                $this->logger->info('Post created successfully', array(
                    'post_id' => $post_id,
                    'source_domain' => $source_article['source_domain'] ?? 'unknown'
                ));
                
                return $post_id;
                
            } catch (Exception $e) {
                // Rollback on any error
                $wpdb->query('ROLLBACK');
                throw $e;
            }
            
        } catch (Exception $e) {
            $error_message = $e->getMessage();
            $this->logger->error('Post creation failed', array(
                'error' => $error_message,
                'trace' => $e->getTraceAsString()
            ));
            
            return array('error' => $error_message);
        }
    }
    
    /**
     * Internal post creation method
     *
     * @param array $sanitized_content Sanitized content data with title and content
     * @param array $source_article Source article data with link and source_domain
     * @return int|WP_Error Post ID on success, WP_Error on failure
     * @throws Exception If post creation fails
     */
    private function create_post_internal($sanitized_content, $source_article) {
        try {
            // Get plugin settings with fallback
            $options = get_option('cp_settings', array());
            $selected_categories = isset($options['categories']) && is_array($options['categories']) 
                ? array_map('intval', $options['categories']) : array();
            
            // Prepare post data with comprehensive validation
            $post_data = array(
                'post_title' => $sanitized_content['title'],
                'post_content' => $sanitized_content['content'],
                'post_status' => 'draft',
                'post_type' => 'post',
                'post_author' => $this->get_valid_author_id(),
                'post_category' => $selected_categories,
                'post_excerpt' => wp_trim_words(wp_strip_all_tags($sanitized_content['content']), 55),
                'meta_input' => array(
                    '_cp_source_url' => esc_url_raw($source_article['link']),
                    '_cp_source_domain' => sanitize_text_field($source_article['source_domain']),
                    '_cp_generated_at' => current_time('mysql'),
                    '_cp_version' => defined('CP_VERSION') ? CP_VERSION : '1.0.0',
                    '_cp_content_hash' => hash('sha256', $sanitized_content['content'])
                )
            );
            
            // Insert the post with error handling
            $post_id = wp_insert_post($post_data, true);
            
            if (is_wp_error($post_id)) {
                $this->logger->error('WordPress post insertion failed', array(
                    'wp_error' => $post_id->get_error_message(),
                    'error_code' => $post_id->get_error_code()
                ));
                return $post_id;
            }
            
            if (!$post_id) {
                $this->logger->error('WordPress post insertion returned false', array(
                    'post_data' => array_keys($post_data)
                ));
                return new WP_Error('post_creation_failed', 'Failed to create post');
            }
            
            return $post_id;
            
        } catch (Exception $e) {
            $this->logger->error('Exception in post creation', array(
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ));
            
            return new WP_Error('post_creation_exception', $e->getMessage());
        }
    }
    
    /**
     * Format post content with proper structure and security
     *
     * @param array $generated_content Generated content with title and content
     * @param array $source_article Source article data
     * @return string Formatted and sanitized content with proper HTML structure
     * @throws Exception If content formatting fails
     */
    private function format_post_content($generated_content, $source_article) {
        try {
            $content = $generated_content['content'];
            
            // Security: Sanitize content before processing
            $content = $this->security_manager->validate_api_response($content) ? $content : '';
            
            // Ensure proper paragraph formatting
            $content = wpautop($content);
            
            // Add source attribution with XSS protection
            $attribution = $this->create_source_attribution($source_article);
            $content .= $attribution;
            
            return $content;
            
        } catch (Exception $e) {
            $this->logger->error('Content formatting failed', array(
                'error' => $e->getMessage()
            ));
            
            // Return basic content without attribution on failure
            return wpautop($generated_content['content']);
        }
    }
    
    /**
     * Create enhanced source attribution with verification status
     *
     * @param array $source_article Source article data with verification information
     * @return string Attribution HTML (properly escaped) with source information and verification status
     * @throws Exception If attribution creation fails
     */
    private function create_source_attribution($source_article) {
        try {
            // Initialize verification components
            $content_verifier = new CP_ContentVerifier();
            $verification_db = new CP_VerificationDatabase();
            
            // Extract and escape data
            $source_domain = esc_html($source_article['source_domain'] ?? 'Unknown Source');
            $original_url = esc_url($source_article['link'] ?? '#');
            $article_title = esc_html($source_article['title'] ?? 'Original Article');
            $publisher_info = $source_article['publisher_info'] ?? array();
            $author_info = $source_article['author_info'] ?? array();
            $publication_date = $source_article['publication_date']['publication_date'] ?? '';
            $verification_status = $source_article['verification_status'] ?? 'unknown';
            $quality_score = $source_article['quality_score'] ?? 0.5;
            $retraction_detected = $source_article['retraction_detected'] ?? false;
            $url_verified = $source_article['url_verified'] ?? false;
            
            // Get publisher information
            $publisher_name = esc_html($publisher_info['publisher_name'] ?? 'Unknown Publisher');
            $publisher_url = esc_url($publisher_info['publisher_url'] ?? '');
            $credibility_score = floatval($publisher_info['credibility_score'] ?? 50);
            
            // Format publication date
            $formatted_date = !empty($publication_date) ? date('F j, Y', strtotime($publication_date)) : 'Unknown';
            
            // Get verification icon and class
            $verification_data = $this->get_verification_display_data($verification_status, $url_verified, $retraction_detected, $quality_score);
            
            // Build enhanced attribution HTML
            $attribution = "
 <div class=\"cp-content-source\" style=\"margin-top: 40px; padding: 0;\">";
            
            $attribution .= "<hr class=\"cp-source-divider\" style=\"border: none; height: 1px; background: #e0e0e0; margin: 30px 0;\">";
            
            $attribution .= "<div class=\"cp-source-section\" style=\"background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px; padding: 20px;\">";
            
            $attribution .= "<h4 class=\"cp-source-title\" style=\"margin: 0 0 15px 0; color: #495057; font-size: 16px; font-weight: 600;\">";
            $attribution .= "<span class=\"aanp-source-icon\" style=\"margin-right: 8px;\">📰</span>";
            $attribution .= "Source Information";
            $attribution .= "</h4>";
            
            $attribution .= "<div class=\"aanp-source-content\" style=\"font-size: 14px; line-height: 1.6;\">";
            
            // Original article link
            $attribution .= "<div class=\"cp-source-link\" style=\"margin-bottom: 12px;\">";
            $attribution .= "<strong>Original Article:</strong> ";
            $attribution .= "<a href=\"{$original_url}\" target=\"_blank\" rel=\"noopener nofollow\" class=\"aanp-external-link\" style=\"color: #007bff; text-decoration: none;\">";
            $attribution .= esc_html($article_title);
            $attribution .= " <span class=\"aanp-external-icon\" style=\"font-size: 12px; margin-left: 4px;\">↗</span>";
            $attribution .= "</a>";
            $attribution .= "</div>";
            
            // Publisher information
            if (!empty($publisher_url)) {
                $attribution .= "<div class=\"cp-publisher\" style=\"margin-bottom: 8px;\">";
                $attribution .= "<strong>Published by:</strong> ";
                $attribution .= "<a href=\"{$publisher_url}\" target=\"_blank\" style=\"color: #007bff; text-decoration: none;\">";
                $attribution .= $publisher_name;
                $attribution .= "</a>";
                $attribution .= "</div>";
            }
            
            // Publication date
            $attribution .= "<div class=\"cp-publication-date\" style=\"margin-bottom: 8px;\">";
            $attribution .= "<strong>Published:</strong> " . $formatted_date;
            $attribution .= "</div>";
            
            // Author information (if available)
            if (!empty($author_info['author_name'])) {
                $author_name = esc_html($author_info['author_name']);
                $attribution .= "<div class=\"cp-author\" style=\"margin-bottom: 8px;\">";
                $attribution .= "<strong>Author:</strong> " . $author_name;
                $attribution .= "</div>";
            }
            
            // Verification status
            $attribution .= "<div class=\"cp-verification-status\" style=\"margin-bottom: 12px;\">";
            $attribution .= "<strong>Verified:</strong> ";
            $attribution .= "<span class=\"verification-badge {$verification_data['class']}\" style=\"";
            $attribution .= "display: inline-block; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 500; ";
            $attribution .= "background-color: {$verification_data['bg_color']}; color: {$verification_data['text_color']};";
            $attribution .= "\">";
            $attribution .= "<span class=\"verification-icon\" style=\"margin-right: 4px;\">{$verification_data['icon']}</span>";
            $attribution .= $verification_data['text'];
            $attribution .= "</span>";
            
            // Add quality score for verified content
            if ($verification_status === 'verified' && $quality_score > 0) {
                $attribution .= "<span style=\"margin-left: 8px; font-size: 12px; color: #6c757d;\">";
                $attribution .= "(Quality: " . round($quality_score * 100) . "%)";
                $attribution .= "</span>";
            }
            
            $attribution .= "</div>";
            
            // Source credibility information
            if ($credibility_score > 0) {
                $credibility_class = $credibility_score >= 80 ? 'high' : ($credibility_score >= 60 ? 'medium' : 'low');
                $credibility_color = $credibility_score >= 80 ? '#28a745' : ($credibility_score >= 60 ? '#ffc107' : '#dc3545');
                
                $attribution .= "<div class=\"cp-source-credibility\" style=\"margin-bottom: 8px;\">";
                $attribution .= "<strong>Source Credibility:</strong> ";
                $attribution .= "<span style=\"color: {$credibility_color}; font-weight: 500;\">";
                $attribution .= $credibility_score . "%";
                $attribution .= "</span>";
                $attribution .= "</div>";
            }
            
            // Disclaimer
            $attribution .= "<div class=\"cp-disclaimer\" style=\"margin-top: 15px; padding-top: 12px; border-top: 1px solid #dee2e6; font-size: 12px; color: #6c757d; font-style: italic;\">";
            $attribution .= "Content verified by AI Auto News Poster. This article was generated based on the original source material.";
            $attribution .= "</div>";
            
            $attribution .= "</div>"; // end aanp-source-content
            $attribution .= "</div>"; // end aanp-source-section
            $attribution .= "</div>"; // end aanp-content-source
            
            return $attribution;
            
        } catch (Exception $e) {
            $this->logger->error('Enhanced source attribution creation failed', array(
                'error' => $e->getMessage()
            ));
            
            // Return fallback attribution
            return '
 <div class="cp-content-source" style="margin-top: 40px; padding: 0;">
    <hr class="cp-source-divider" style="border: none; height: 1px; background: #e0e0e0; margin: 30px 0;">
    <div class="cp-source-section" style="background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px; padding: 20px;">
        <h4 class="cp-source-title" style="margin: 0 0 15px 0; color: #495057; font-size: 16px; font-weight: 600;">
            <span style="margin-right: 8px;">📰</span>Source Information
        </h4>
        <div style="font-size: 14px; line-height: 1.6;">
            <strong>Source:</strong> This article was generated based on source material.
            <div style="margin-top: 10px; font-size: 12px; color: #6c757d; font-style: italic;">
                Generated by AI Auto News Poster
            </div>
        </div>
    </div>
</div>';
        }
    }
    
    /**
     * Get verification display data for badges
     *
     * @param string $status Verification status (e.g., 'verified', 'warning', 'error')
     * @param bool $url_verified Whether URL was verified
     * @param bool $retraction_detected Whether retraction was detected
     * @param float $quality_score Quality score (0-1)
     * @return array Display data with class, icon, text, bg_color, and text_color
     */
    private function get_verification_display_data($status, $url_verified, $retraction_detected, $quality_score) {
        // Handle retracted content
        if ($retraction_detected) {
            return array(
                'class' => 'retracted',
                'icon' => '❌',
                'text' => 'Retracted Content',
                'bg_color' => '#f8d7da',
                'text_color' => '#721c24'
            );
        }
        
        // Handle various verification statuses
        switch ($status) {
            case 'verified':
                if ($url_verified && $quality_score >= 0.7) {
                    return array(
                        'class' => 'fully-verified',
                        'icon' => '✅',
                        'text' => 'Fully Verified',
                        'bg_color' => '#d4edda',
                        'text_color' => '#155724'
                    );
                } else {
                    return array(
                        'class' => 'verified',
                        'icon' => '✔️',
                        'text' => 'Verified',
                        'bg_color' => '#d1ecf1',
                        'text_color' => '#0c5460'
                    );
                }
                
            case 'warning':
                return array(
                    'class' => 'warning',
                    'icon' => '⚠️',
                    'text' => 'Minor Issues',
                    'bg_color' => '#fff3cd',
                    'text_color' => '#856404'
                );
                
            case 'error':
                return array(
                    'class' => 'error',
                    'icon' => '❌',
                    'text' => 'Content Issues',
                    'bg_color' => '#f8d7da',
                    'text_color' => '#721c24'
                );
                
            default:
                return array(
                    'class' => 'unknown',
                    'icon' => '❓',
                    'text' => 'Not Verified',
                    'bg_color' => '#e2e3e5',
                    'text_color' => '#383d41'
                );
        }
    }
    
    /**
     * Set post categories with error handling
     *
     * @param int $post_id Post ID to set categories for
     * @param array $sanitized_content Sanitized content data (unused but kept for interface consistency)
     * @return bool True on success, false on failure
     * @throws Exception If category setting fails
     */
    private function set_post_categories($post_id, $sanitized_content) {
        try {
            $options = get_option('cp_settings', array());
            $selected_categories = isset($options['categories']) && is_array($options['categories']) 
                ? array_map('intval', $options['categories']) : array();
            
            if (!empty($selected_categories)) {
                $result = wp_set_post_categories($post_id, $selected_categories);
                
                if (is_wp_error($result)) {
                    $this->logger->warning('Failed to set post categories', array(
                        'post_id' => $post_id,
                        'categories' => $selected_categories,
                        'wp_error' => $result->get_error_message()
                    ));
                    return false;
                }
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('Exception setting post categories', array(
                'post_id' => $post_id,
                'error' => $e->getMessage()
            ));
            return false;
        }
    }
    
    /**
     * Add relevant tags to the post with enhanced security
     *
     * @param int $post_id Post ID to add tags to
     * @param array $sanitized_content Sanitized content data for tag extraction
     * @param array $source_article Source article data for domain tag
     * @throws Exception If tag addition fails
     */
    private function add_post_tags($post_id, $sanitized_content, $source_article) {
        try {
            $tags = array();
            
            // Extract potential tags from title and content
            $text = $sanitized_content['title'] . ' ' . strip_tags($sanitized_content['content']);
            
            // Common news tags with validation
            $common_news_tags = array(
                'breaking news', 'update', 'report', 'analysis', 'latest',
                'technology', 'business', 'politics', 'health', 'science',
                'sports', 'entertainment', 'world news', 'economy', 'finance'
            );
            
            foreach ($common_news_tags as $tag) {
                if (stripos($text, $tag) !== false) {
                    $sanitized_tag = sanitize_text_field($tag);
                    if (!empty($sanitized_tag)) {
                        $tags[] = $sanitized_tag;
                    }
                }
            }
            
            // Add source domain as tag (safely sanitized)
            if (!empty($source_article['source_domain'])) {
                $domain_tag = sanitize_text_field($source_article['source_domain']);
                if (!empty($domain_tag) && strlen($domain_tag) < 50) {
                    $tags[] = $domain_tag;
                }
            }
            
            // Add AI generated tag
            $tags[] = 'AI Generated';
            
            // Remove duplicates and validate
            $tags = array_unique(array_filter($tags, function($tag) {
                return !empty($tag) && strlen($tag) < 50;
            }));
            
            if (!empty($tags)) {
                $result = wp_set_post_tags($post_id, $tags);
                
                if (is_wp_error($result)) {
                    $this->logger->warning('Failed to set post tags', array(
                        'post_id' => $post_id,
                        'tags' => $tags,
                        'wp_error' => $result->get_error_message()
                    ));
                }
            }
            
        } catch (Exception $e) {
            $this->logger->error('Exception adding post tags', array(
                'post_id' => $post_id,
                'error' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Log post creation for tracking with enhanced error handling
     *
     * @param int $post_id Created post ID
     * @param array $source_article Source article data with link and source_domain
     * @throws Exception If logging fails
     */
    private function log_post_creation($post_id, $source_article) {
        try {
            global $wpdb;
            
            $table_name = $wpdb->prefix . 'cp_generated_posts';
            
            // Ensure table exists
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
                $this->create_tracking_table();
            }
            
            $result = $wpdb->insert(
                $table_name,
                array(
                    'post_id' => intval($post_id),
                    'source_url' => esc_url_raw($source_article['link']),
                    'source_domain' => sanitize_text_field($source_article['source_domain']),
                    'generated_at' => current_time('mysql')
                ),
                array('%d', '%s', '%s', '%s')
            );
            
            if ($result === false) {
                $this->logger->warning('Failed to log post creation', array(
                    'post_id' => $post_id,
                    'wpdb_error' => $wpdb->last_error
                ));
            }
            
        } catch (Exception $e) {
            $this->logger->error('Exception logging post creation', array(
                'post_id' => $post_id,
                'error' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Validate post data before creation with comprehensive checks
     *
     * @param array $generated_content Generated content
     * @param array $source_article Source article
     * @return array Validation result
     */
    public function validate_post_data($generated_content, $source_article) {
        $errors = array();
        
        // Enhanced title validation
        if (empty($generated_content['title'])) {
            $errors[] = 'Title is required';
        } else {
            $title = trim($generated_content['title']);
            if (strlen($title) < 5) {
                $errors[] = 'Title is too short (min 5 characters)';
            } elseif (strlen($title) > 200) {
                $errors[] = 'Title is too long (max 200 characters)';
            } elseif (!wp_strip_all_tags($title)) {
                $errors[] = 'Title contains invalid characters';
            }
        }
        
        // Enhanced content validation
        if (empty($generated_content['content'])) {
            $errors[] = 'Content is required';
        } else {
            $content = trim($generated_content['content']);
            $clean_content = wp_strip_all_tags($content);
            
            if (strlen($clean_content) < 100) {
                $errors[] = 'Content is too short (min 100 characters)';
            } elseif (strlen($clean_content) > 50000) {
                $errors[] = 'Content is too long (max 50,000 characters)';
            }
            
            // Security validation for content
            if (!$this->security_manager->validate_api_response($content)) {
                $errors[] = 'Content contains suspicious patterns';
            }
        }
        
        // Enhanced source URL validation
        if (empty($source_article['link']) || !filter_var($source_article['link'], FILTER_VALIDATE_URL)) {
            $errors[] = 'Valid source URL is required';
        } else {
            $url = $source_article['link'];
            // Additional URL security checks
            if (strpos($url, 'javascript:') !== false || strpos($url, 'data:') !== false) {
                $errors[] = 'Invalid URL scheme detected';
            }
        }
        
        // Validate source domain
        if (empty($source_article['source_domain'])) {
            $errors[] = 'Source domain is required';
        } else {
            $domain = trim($source_article['source_domain']);
            if (strlen($domain) > 100 || !preg_match('/^[a-zA-Z0-9.-]+$/', $domain)) {
                $errors[] = 'Invalid source domain format';
            }
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors
        );
    }
    
    /**
     * Sanitize input data comprehensively
     *
     * @param array $generated_content Generated content with title and content
     * @param array $source_article Source article data with link and source_domain
     * @return array Sanitized data with title, content, and source_article
     */
    private function sanitize_input_data($generated_content, $source_article) {
        // Sanitize title
        $sanitized_title = sanitize_text_field($generated_content['title']);
        $sanitized_title = wp_strip_all_tags($sanitized_title);
        
        // Sanitize content while preserving structure
        $sanitized_content = $this->sanitize_content_html($generated_content['content']);
        
        // Sanitize source article data
        $sanitized_source = array(
            'link' => esc_url_raw($source_article['link']),
            'source_domain' => sanitize_text_field($source_article['source_domain'])
        );
        
        return array(
            'title' => $sanitized_title,
            'content' => $sanitized_content,
            'source_article' => $sanitized_source
        );
    }
    
    /**
     * Sanitize HTML content while preserving safe structure
     *
     * @param string $content HTML content to sanitize
     * @return string Sanitized content with only allowed HTML tags
     */
    private function sanitize_content_html($content) {
        // Remove script tags and dangerous attributes
        $content = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $content);
        $content = preg_replace('/on\w+\s*="[^"]*"/i', '', $content);
        $content = preg_replace('/javascript:/i', '', $content);
        
        // Allow basic HTML tags
        $allowed_tags = array(
            'p', 'br', 'strong', 'em', 'u', 'i', 'b', 'ul', 'ol', 'li',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote'
        );
        
        $content = wp_kses($content, array_fill_keys($allowed_tags, array()));
        
        return $content;
    }
    
    /**
     * Verify nonce for security
     *
     * @param string $nonce Nonce to verify (optional, will check $_POST if empty)
     * @param string $action Action name to verify nonce against
     * @return bool True if nonce is valid, false otherwise
     */
    private function verify_nonce($nonce, $action) {
        if (empty($nonce)) {
            // Check if nonce is in request
            $nonce = isset($_POST['cp_nonce']) ? sanitize_text_field($_POST['cp_nonce']) : '';
        }
        
        return wp_verify_nonce($nonce, $action);
    }
    
    /**
     * Get valid author ID with fallback
     *
     * @return int Valid author ID (current user, administrator, or default)
     */
    private function get_valid_author_id() {
        $author_id = get_current_user_id();
        
        // If no user logged in, use default author
        if (!$author_id) {
            // Get the first administrator or the blog author
            $users = get_users(array('role' => 'administrator', 'number' => 1));
            if (!empty($users)) {
                $author_id = $users[0]->ID;
            } else {
                $author_id = 1; // Default to user 1
            }
        }
        
        return intval($author_id);
    }
    
    /**
     * Create tracking table if it doesn't exist
     *
     * @throws Exception If table creation fails
     */
    private function create_tracking_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cp_generated_posts';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) UNSIGNED NOT NULL,
            source_url varchar(500) NOT NULL,
            source_domain varchar(255) NOT NULL,
            generated_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY source_url (source_url)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Invalidate related caches
     *
     * @throws Exception If cache invalidation fails
     */
    private function invalidate_related_caches() {
        try {
            // Clear post stats cache
            $this->cache_manager->delete('post_stats');
            
            // Clear recent posts cache
            $this->cache_manager->delete('recent_posts');
            
            // Clear object cache if available
            wp_cache_flush();
            
        } catch (Exception $e) {
            $this->logger->warning('Cache invalidation failed', array(
                'error' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Update post with enhanced error handling
     *
     * @param int $post_id Post ID
     * @param array $updates Updates to apply
     * @param string $nonce Nonce for security verification
     * @return bool Success status
     */
    public function update_post($post_id, $updates, $nonce = '') {
        try {
            // Verify nonce
            if (!$this->verify_nonce($nonce, 'cp_update_post')) {
                $this->logger->warning('Post update attempt with invalid nonce', array(
                    'post_id' => $post_id,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ));
                return false;
            }
            
            // Validate post ID
            if (!$this->validate_post_id($post_id)) {
                return false;
            }
            
            $post_data = array('ID' => intval($post_id));
            
            // Sanitize and validate update fields
            if (isset($updates['title'])) {
                $title = sanitize_text_field($updates['title']);
                if (strlen($title) > 0 && strlen($title) <= 200) {
                    $post_data['post_title'] = $title;
                }
            }
            
            if (isset($updates['content'])) {
                $content = $this->sanitize_content_html($updates['content']);
                if (!empty($content)) {
                    $post_data['post_content'] = $content;
                }
            }
            
            if (isset($updates['status'])) {
                $valid_statuses = array('draft', 'publish', 'private', 'pending');
                if (in_array($updates['status'], $valid_statuses)) {
                    $post_data['post_status'] = $updates['status'];
                }
            }
            
            if (isset($updates['categories'])) {
                if (is_array($updates['categories'])) {
                    $post_data['post_category'] = array_map('intval', $updates['categories']);
                }
            }
            
            if (empty($post_data) || count($post_data) === 1) {
                return false; // No valid updates
            }
            
            // Start transaction
            global $wpdb;
            $wpdb->query('START TRANSACTION');
            
            try {
                $result = wp_update_post($post_data, true);
                
                if (is_wp_error($result)) {
                    throw new Exception('Post update failed: ' . $result->get_error_message());
                }
                
                if ($result === 0) {
                    throw new Exception('Post update returned 0, no changes made');
                }
                
                $wpdb->query('COMMIT');
                
                // Clear related caches
                $this->invalidate_related_caches();
                
                $this->logger->info('Post updated successfully', array(
                    'post_id' => $post_id,
                    'changes' => array_keys(array_slice($post_data, 1))
                ));
                
                return true;
                
            } catch (Exception $e) {
                $wpdb->query('ROLLBACK');
                throw $e;
            }
            
        } catch (Exception $e) {
            $this->logger->error('Post update failed', array(
                'post_id' => $post_id,
                'error' => $e->getMessage()
            ));
            return false;
        }
    }
    
    /**
     * Delete generated post with enhanced security
     *
     * @param int $post_id Post ID
     * @param bool $force_delete Force delete (bypass trash)
     * @param string $nonce Nonce for security verification
     * @return bool Success status
     */
    public function delete_post($post_id, $force_delete = false, $nonce = '') {
        try {
            // Verify nonce
            if (!$this->verify_nonce($nonce, 'cp_delete_post')) {
                $this->logger->warning('Post deletion attempt with invalid nonce', array(
                    'post_id' => $post_id,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ));
                return false;
            }
            
            // Validate post ID
            if (!$this->validate_post_id($post_id)) {
                return false;
            }
            
            // Verify this is an AANP generated post
            $source_url = get_post_meta($post_id, '_cp_source_url', true);
            
            if (empty($source_url)) {
                $this->logger->warning('Attempted to delete non-AANP post', array(
                    'post_id' => $post_id,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ));
                return false;
            }
            
            // Start transaction
            global $wpdb;
            $wpdb->query('START TRANSACTION');
            
            try {
                // Delete the post
                $result = wp_delete_post($post_id, $force_delete);
                
                if (!$result) {
                    throw new Exception('Failed to delete post');
                }
                
                // Remove from tracking table
                $table_name = $wpdb->prefix . 'cp_generated_posts';
                $delete_result = $wpdb->delete(
                    $table_name,
                    array('post_id' => $post_id),
                    array('%d')
                );
                
                if ($delete_result === false) {
                    $this->logger->warning('Failed to remove post from tracking table', array(
                        'post_id' => $post_id,
                        'wpdb_error' => $wpdb->last_error
                    ));
                }
                
                $wpdb->query('COMMIT');
                
                // Clear related caches
                $this->invalidate_related_caches();
                
                $this->logger->info('Post deleted successfully', array(
                    'post_id' => $post_id,
                    'force_delete' => $force_delete
                ));
                
                return true;
                
            } catch (Exception $e) {
                $wpdb->query('ROLLBACK');
                throw $e;
            }
            
        } catch (Exception $e) {
            $this->logger->error('Post deletion failed', array(
                'post_id' => $post_id,
                'error' => $e->getMessage()
            ));
            return false;
        }
    }
    
    /**
     * Validate post ID
     *
     * @param int $post_id Post ID to validate
     * @return bool True if post ID is valid and post exists, false otherwise
     */
    private function validate_post_id($post_id) {
        $post_id = intval($post_id);
        
        if ($post_id <= 0) {
            return false;
        }
        
        // Check if post exists and is accessible
        $post = get_post($post_id);
        
        return $post !== null;
    }
    
   /**
    * Get generated posts statistics with error handling
    *
    * @return array Statistics
    */
   public function get_stats() {
       try {
           // Check cache first
           $cached_stats = $this->cache_manager->get('post_stats');
           if ($cached_stats !== false) {
               return $cached_stats;
           }

           global $wpdb;
           $wpdb->query('START TRANSACTION');

           try {
               $table_name = $wpdb->prefix . 'cp_generated_posts';

               // Ensure table exists
               if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
                   $this->create_tracking_table();
                   $wpdb->query('COMMIT');
                   return array('total' => 0, 'today' => 0, 'week' => 0, 'month' => 0);
               }

               // Total generated posts
               $total_posts = $wpdb->get_var("SELECT COUNT(*) FROM $table_name") ?: 0;

               // Posts generated today
               $today_posts = $wpdb->get_var(
                   $wpdb->prepare(
                       "SELECT COUNT(*) FROM $table_name WHERE DATE(generated_at) = %s",
                       current_time('Y-m-d')
                   )
               ) ?: 0;

               // Posts generated this week
               $week_start = date('Y-m-d', strtotime('monday this week'));
               $week_posts = $wpdb->get_var(
                   $wpdb->prepare(
                       "SELECT COUNT(*) FROM $table_name WHERE generated_at >= %s",
                       $week_start . ' 00:00:00'
                   )
               ) ?: 0;

               // Posts generated this month
               $month_start = date('Y-m-01');
               $month_posts = $wpdb->get_var(
                   $wpdb->prepare(
                       "SELECT COUNT(*) FROM $table_name WHERE generated_at >= %s",
                       $month_start . ' 00:00:00'
                   )
               ) ?: 0;

               $wpdb->query('COMMIT');

               $stats = array(
                   'total' => intval($total_posts),
                   'today' => intval($today_posts),
                   'week' => intval($week_posts),
                   'month' => intval($month_posts)
               );

               // Cache for 5 minutes
               $this->cache_manager->set('post_stats', $stats, 300);

               return $stats;

           } catch (Exception $e) {
               $wpdb->query('ROLLBACK');
               CP_Error_Handler::getInstance()->handle_error($e->getMessage(), ['method' => 'get_stats'], 'DATABASE');
               return array('total' => 0, 'today' => 0, 'week' => 0, 'month' => 0);
           }

       } catch (Exception $e) {
           $this->logger->error('Failed to get post stats', array(
               'error' => $e->getMessage()
           ));

           return array('total' => 0, 'today' => 0, 'week' => 0, 'month' => 0);
       }
    }
    
   /**
    * Get recent generated posts with enhanced error handling
    *
    * @param int $limit Number of posts to retrieve
    * @return array Recent posts
    */
   public function get_recent_posts($limit = 10) {
       try {
           global $wpdb;
           $wpdb->query('START TRANSACTION');

           try {
               $table_name = $wpdb->prefix . 'cp_generated_posts';

               // Ensure table exists
               if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
                   $wpdb->query('COMMIT');
                   return array();
               }

               $limit = max(1, min(100, intval($limit))); // Sanitize limit

               $results = $wpdb->get_results(
                   $wpdb->prepare(
                       "SELECT gp.*, p.post_title, p.post_status, p.post_date
                        FROM $table_name gp
                        JOIN {$wpdb->posts} p ON gp.post_id = p.ID
                        ORDER BY gp.generated_at DESC
                        LIMIT %d",
                       $limit
                   )
               );

               $wpdb->query('COMMIT');

               $posts = array();

               foreach ($results as $result) {
                   $posts[] = array(
                       'id' => intval($result->post_id),
                       'title' => sanitize_text_field($result->post_title),
                       'status' => sanitize_text_field($result->post_status),
                       'source_url' => esc_url($result->source_url),
                       'source_domain' => sanitize_text_field($result->source_domain),
                       'generated_at' => $result->generated_at,
                       'edit_link' => get_edit_post_link($result->post_id)
                   );
               }

               return $posts;

           } catch (Exception $e) {
               $wpdb->query('ROLLBACK');
               CP_Error_Handler::getInstance()->handle_error($e->getMessage(), ['method' => 'get_recent_posts'], 'DATABASE');
               return array();
           }

       } catch (Exception $e) {
           $this->logger->error('Failed to get recent posts', array(
               'error' => $e->getMessage()
           ));

           return array();
       }
    }
}
