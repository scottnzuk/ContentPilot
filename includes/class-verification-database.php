<?php
/**
 * Verification Database Manager
 *
 * Handles database operations for content verification system
 * including source tracking and verification results storage.
 *
 * @package AI_Auto_News_Poster
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AANP_VerificationDatabase {
    
    private $logger;
    private $verified_sources_table;
    private $content_verification_table;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        
        $this->logger = AANP_Logger::getInstance();
        $this->verified_sources_table = $wpdb->prefix . 'aanp_verified_sources';
        $this->content_verification_table = $wpdb->prefix . 'aanp_content_verification';
        
        $this->logger->debug('Verification database manager initialized');
    }
    
    /**
     * Create verification database tables
     */
    public function create_tables() {
        try {
            global $wpdb;
            
            $charset_collate = $wpdb->get_charset_collate();
            
            // Create verified sources table
            $this->create_verified_sources_table($charset_collate);
            
            // Create content verification table
            $this->create_content_verification_table($charset_collate);
            
            // Insert default trusted sources
            $this->insert_default_trusted_sources();
            
            $this->logger->info('Verification database tables created successfully');
            
        } catch (Exception $e) {
            $this->logger->error('Failed to create verification database tables', array(
                'error' => $e->getMessage()
            ));
            throw $e;
        }
    }
    
    /**
     * Create verified sources table
     *
     * @param string $charset_collate Database charset
     */
    private function create_verified_sources_table($charset_collate) {
        global $wpdb;
        
        $sql = "CREATE TABLE {$this->verified_sources_table} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            domain VARCHAR(255) NOT NULL,
            source_name VARCHAR(255) NOT NULL,
            credibility_score DECIMAL(3,2) DEFAULT 0.00,
            verification_status ENUM('verified', 'warning', 'error', 'unknown') DEFAULT 'unknown',
            last_checked TIMESTAMP NULL DEFAULT NULL,
            verification_details TEXT NULL,
            metadata JSON NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_domain (domain),
            KEY idx_credibility_score (credibility_score),
            KEY idx_verification_status (verification_status),
            KEY idx_last_checked (last_checked)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Check if table was created
        if ($wpdb->get_var("SHOW TABLES LIKE '{$this->verified_sources_table}'") !== $this->verified_sources_table) {
            throw new Exception('Failed to create verified sources table');
        }
    }
    
    /**
     * Create content verification table
     *
     * @param string $charset_collate Database charset
     */
    private function create_content_verification_table($charset_collate) {
        global $wpdb;
        
        $sql = "CREATE TABLE {$this->content_verification_table} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            post_id BIGINT(20) UNSIGNED NULL,
            rss_item_hash VARCHAR(64) NULL,
            original_url VARCHAR(500) NOT NULL,
            verification_status ENUM('verified', 'warning', 'error', 'pending') NOT NULL,
            verification_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            verification_details TEXT NULL,
            publisher_info JSON NULL,
            retraction_detected BOOLEAN DEFAULT FALSE,
            retraction_confidence DECIMAL(3,2) DEFAULT 0.00,
            source_legitimate BOOLEAN DEFAULT TRUE,
            content_accessible BOOLEAN DEFAULT TRUE,
            metadata JSON NULL,
            processed_at TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_post_id (post_id),
            INDEX idx_original_url (original_url(191)),
            INDEX idx_verification_status (verification_status),
            INDEX idx_verification_date (verification_date),
            INDEX idx_retraction_detected (retraction_detected),
            FOREIGN KEY (post_id) REFERENCES {$wpdb->posts}(ID) ON DELETE SET NULL
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Check if table was created
        if ($wpdb->get_var("SHOW TABLES LIKE '{$this->content_verification_table}'") !== $this->content_verification_table) {
            throw new Exception('Failed to create content verification table');
        }
    }
    
    /**
     * Insert default trusted sources
     */
    private function insert_default_trusted_sources() {
        global $wpdb;
        
        $default_sources = array(
            // UK Sources
            array('bbc.co.uk', 'BBC News', 95.00),
            array('theguardian.com', 'The Guardian', 88.00),
            array('telegraph.co.uk', 'The Daily Telegraph', 70.00),
            array('independent.co.uk', 'The Independent', 75.00),
            array('ft.com', 'Financial Times', 85.00),
            array('dailymail.co.uk', 'Daily Mail', 60.00),
            array('mirror.co.uk', 'Daily Mirror', 60.00),
            array('sky.com', 'Sky News', 70.00),
            array('itv.com', 'ITV News', 70.00),
            array('thetimes.co.uk', 'The Times', 75.00),
            
            // US Sources
            array('nytimes.com', 'The New York Times', 90.00),
            array('washingtonpost.com', 'The Washington Post', 85.00),
            array('cnn.com', 'CNN', 85.00),
            array('apnews.com', 'Associated Press', 90.00),
            array('reuters.com', 'Reuters', 90.00),
            array('wsj.com', 'The Wall Street Journal', 85.00),
            array('bloomberg.com', 'Bloomberg', 85.00),
            array('usatoday.com', 'USA Today', 75.00),
            array('abcnews.go.com', 'ABC News', 80.00),
            array('cbsnews.com', 'CBS News', 80.00),
            array('nbcnews.com', 'NBC News', 80.00),
            array('foxnews.com', 'Fox News', 70.00),
            
            // International Sources
            array('dw.com', 'Deutsche Welle', 80.00),
            array('aljazeera.com', 'Al Jazeera', 75.00),
            array('france24.com', 'France 24', 80.00),
            array('thelocal.fr', 'The Local France', 70.00),
            array('thelocal.de', 'The Local Germany', 70.00),
            array('thelocal.es', 'The Local Spain', 70.00),
            array('thelocal.it', 'The Local Italy', 70.00),
            
            // Tech/Business
            array('techcrunch.com', 'TechCrunch', 75.00),
            array('theverge.com', 'The Verge', 75.00),
            array('wired.com', 'Wired', 80.00),
            array('arstechnica.com', 'Ars Technica', 80.00),
            array('engadget.com', 'Engadget', 70.00),
            array('venturebeat.com', 'VentureBeat', 70.00),
            
            // Sports
            array('espn.com', 'ESPN', 80.00),
            array('bbc.com', 'BBC Sport', 85.00),
            array('skysports.com', 'Sky Sports', 75.00),
            array('goal.com', 'Goal.com', 70.00),
            
            // Science/Health
            array('nature.com', 'Nature', 95.00),
            array('sciencemag.org', 'Science', 95.00),
            array('newcientist.com', 'New Scientist', 80.00),
            array('scientificamerican.com', 'Scientific American', 85.00),
            array('mayoclinic.org', 'Mayo Clinic', 90.00),
            array('webmd.com', 'WebMD', 75.00)
        );
        
        foreach ($default_sources as $source) {
            list($domain, $source_name, $credibility_score) = $source;
            
            // Check if source already exists
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->verified_sources_table} WHERE domain = %s",
                $domain
            ));
            
            if (!$existing) {
                $result = $wpdb->insert(
                    $this->verified_sources_table,
                    array(
                        'domain' => $domain,
                        'source_name' => $source_name,
                        'credibility_score' => $credibility_score,
                        'verification_status' => 'verified',
                        'last_checked' => current_time('mysql'),
                        'verification_details' => 'Pre-configured trusted source'
                    ),
                    array('%s', '%s', '%f', '%s', '%s', '%s')
                );
                
                if ($result === false) {
                    $this->logger->warning('Failed to insert default source', array(
                        'domain' => $domain,
                        'error' => $wpdb->last_error
                    ));
                }
            }
        }
    }
    
    /**
     * Record content verification result
     *
     * @param array $verification_data Verification data
     * @return int|false Insert ID or false on failure
     */
    public function record_verification($verification_data) {
        try {
            global $wpdb;
            
            $data = array(
                'post_id' => isset($verification_data['post_id']) ? intval($verification_data['post_id']) : null,
                'rss_item_hash' => isset($verification_data['rss_item_hash']) ? sanitize_text_field($verification_data['rss_item_hash']) : null,
                'original_url' => esc_url_raw($verification_data['original_url']),
                'verification_status' => sanitize_text_field($verification_data['status']),
                'verification_details' => isset($verification_data['details']) ? wp_json_encode($verification_data['details']) : null,
                'publisher_info' => isset($verification_data['publisher_info']) ? wp_json_encode($verification_data['publisher_info']) : null,
                'retraction_detected' => isset($verification_data['retraction_detected']) ? (bool) $verification_data['retraction_detected'] : false,
                'retraction_confidence' => isset($verification_data['retraction_confidence']) ? floatval($verification_data['retraction_confidence']) : 0.00,
                'source_legitimate' => isset($verification_data['source_legitimate']) ? (bool) $verification_data['source_legitimate'] : true,
                'content_accessible' => isset($verification_data['content_accessible']) ? (bool) $verification_data['content_accessible'] : true,
                'metadata' => isset($verification_data['metadata']) ? wp_json_encode($verification_data['metadata']) : null,
                'processed_at' => current_time('mysql')
            );
            
            $result = $wpdb->insert(
                $this->content_verification_table,
                $data,
                array('%d', '%s', '%s', '%s', '%s', '%s', '%d', '%f', '%d', '%d', '%s', '%s')
            );
            
            if ($result === false) {
                $this->logger->error('Failed to record verification', array(
                    'error' => $wpdb->last_error,
                    'data' => $data
                ));
                return false;
            }
            
            return $wpdb->insert_id;
            
        } catch (Exception $e) {
            $this->logger->error('Exception recording verification', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
            return false;
        }
    }
    
    /**
     * Update source credibility score
     *
     * @param string $domain Source domain
     * @param float $credibility_score New credibility score
     * @param string $status Verification status
     * @param array $details Additional details
     * @return bool Success status
     */
    public function update_source_credibility($domain, $credibility_score, $status = 'verified', $details = array()) {
        try {
            global $wpdb;
            
            $result = $wpdb->update(
                $this->verified_sources_table,
                array(
                    'credibility_score' => floatval($credibility_score),
                    'verification_status' => sanitize_text_field($status),
                    'last_checked' => current_time('mysql'),
                    'verification_details' => !empty($details) ? wp_json_encode($details) : null
                ),
                array('domain' => $domain),
                array('%f', '%s', '%s', '%s'),
                array('%s')
            );
            
            if ($result === false) {
                $this->logger->error('Failed to update source credibility', array(
                    'domain' => $domain,
                    'error' => $wpdb->last_error
                ));
                return false;
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('Exception updating source credibility', array(
                'domain' => $domain,
                'error' => $e->getMessage()
            ));
            return false;
        }
    }
    
    /**
     * Get source credibility information
     *
     * @param string $domain Source domain
     * @return array|null Source information or null if not found
     */
    public function get_source_credibility($domain) {
        try {
            global $wpdb;
            
            $result = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->verified_sources_table} WHERE domain = %s",
                $domain
            ), ARRAY_A);
            
            return $result ?: null;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to get source credibility', array(
                'domain' => $domain,
                'error' => $e->getMessage()
            ));
            return null;
        }
    }
    
    /**
     * Get verification statistics
     *
     * @param int $days Number of days to look back
     * @return array Statistics
     */
    public function get_verification_stats($days = 30) {
        try {
            global $wpdb;
            
            $stats = array();
            
            // Total verifications
            $stats['total_verifications'] = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->content_verification_table} WHERE verification_date >= DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            ));
            
            // Verification status breakdown
            $status_breakdown = $wpdb->get_results($wpdb->prepare(
                "SELECT verification_status, COUNT(*) as count 
                 FROM {$this->content_verification_table} 
                 WHERE verification_date >= DATE_SUB(NOW(), INTERVAL %d DAY)
                 GROUP BY verification_status",
                $days
            ), ARRAY_A);
            
            $stats['status_breakdown'] = array();
            foreach ($status_breakdown as $row) {
                $stats['status_breakdown'][$row['verification_status']] = intval($row['count']);
            }
            
            // Retraction statistics
            $stats['retraction_detected'] = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->content_verification_table} 
                 WHERE retraction_detected = 1 AND verification_date >= DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            ));
            
            // Average retraction confidence
            $stats['avg_retraction_confidence'] = $wpdb->get_var($wpdb->prepare(
                "SELECT AVG(retraction_confidence) FROM {$this->content_verification_table} 
                 WHERE retraction_detected = 1 AND verification_date >= DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            ));
            
            // Top problematic domains
            $top_problematic = $wpdb->get_results($wpdb->prepare(
                "SELECT 
                    SUBSTRING_INDEX(SUBSTRING_INDEX(original_url, '/', 3), '://', -1) as domain,
                    COUNT(*) as issues_count,
                    AVG(CASE WHEN retraction_detected = 1 THEN 1 ELSE 0 END) as retraction_rate
                 FROM {$this->content_verification_table} 
                 WHERE verification_date >= DATE_SUB(NOW(), INTERVAL %d DAY)
                 GROUP BY domain
                 HAVING issues_count > 2
                 ORDER BY retraction_rate DESC, issues_count DESC
                 LIMIT 10",
                $days
            ), ARRAY_A);
            
            $stats['top_problematic_domains'] = $top_problematic;
            
            return $stats;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to get verification stats', array(
                'days' => $days,
                'error' => $e->getMessage()
            ));
            return array();
        }
    }
    
    /**
     * Get verification records for admin
     *
     * @param array $args Query arguments
     * @return array Verification records
     */
    public function get_verification_records($args = array()) {
        try {
            global $wpdb;
            
            $defaults = array(
                'limit' => 50,
                'offset' => 0,
                'status' => null,
                'domain' => null,
                'days' => 30,
                'search' => null
            );
            
            $args = wp_parse_args($args, $defaults);
            
            $where_conditions = array("verification_date >= DATE_SUB(NOW(), INTERVAL {$args['days']} DAY)");
            
            if ($args['status']) {
                $where_conditions[] = $wpdb->prepare('verification_status = %s', $args['status']);
            }
            
            if ($args['domain']) {
                $where_conditions[] = $wpdb->prepare(
                    'SUBSTRING_INDEX(SUBSTRING_INDEX(original_url, "/", 3), "://", -1) = %s',
                    $args['domain']
                );
            }
            
            if ($args['search']) {
                $where_conditions[] = $wpdb->prepare(
                    '(original_url LIKE %s OR verification_details LIKE %s)',
                    '%' . $wpdb->esc_like($args['search']) . '%',
                    '%' . $wpdb->esc_like($args['search']) . '%'
                );
            }
            
            $where_clause = implode(' AND ', $where_conditions);
            
            $sql = $wpdb->prepare(
                "SELECT 
                    cv.*,
                    p.post_title,
                    SUBSTRING_INDEX(SUBSTRING_INDEX(cv.original_url, '/', 3), '://', -1) as source_domain
                 FROM {$this->content_verification_table} cv
                 LEFT JOIN {$wpdb->posts} p ON cv.post_id = p.ID
                 WHERE {$where_clause}
                 ORDER BY cv.verification_date DESC
                 LIMIT %d OFFSET %d",
                $args['limit'],
                $args['offset']
            );
            
            $results = $wpdb->get_results($sql, ARRAY_A);
            
            // Decode JSON fields
            foreach ($results as &$result) {
                if (!empty($result['verification_details'])) {
                    $details = json_decode($result['verification_details'], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $result['verification_details_array'] = $details;
                    }
                }
                
                if (!empty($result['publisher_info'])) {
                    $publisher = json_decode($result['publisher_info'], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $result['publisher_info_array'] = $publisher;
                    }
                }
                
                if (!empty($result['metadata'])) {
                    $metadata = json_decode($result['metadata'], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $result['metadata_array'] = $metadata;
                    }
                }
            }
            
            return $results;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to get verification records', array(
                'args' => $args,
                'error' => $e->getMessage()
            ));
            return array();
        }
    }
    
    /**
     * Clean up old verification records
     *
     * @param int $days_to_keep Number of days to keep records
     * @return int Number of records deleted
     */
    public function cleanup_old_records($days_to_keep = 90) {
        try {
            global $wpdb;
            
            $result = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$this->content_verification_table} 
                 WHERE verification_date < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days_to_keep
            ));
            
            $this->logger->info('Cleaned up old verification records', array(
                'days_to_keep' => $days_to_keep,
                'deleted_count' => $result
            ));
            
            return intval($result);
            
        } catch (Exception $e) {
            $this->logger->error('Failed to cleanup old verification records', array(
                'days_to_keep' => $days_to_keep,
                'error' => $e->getMessage()
            ));
            return 0;
        }
    }
}