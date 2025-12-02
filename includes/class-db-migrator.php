<?php
/**
 * Database Migrator Class
 *
 * Implements a versioned database schema migration system for the AI Auto News Poster plugin.
 * Handles schema creation, updates, and version tracking for all plugin database tables.
 *
 * @package AI_Auto_News_Poster
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database Migrator Class
 *
 * Provides versioned database schema migrations with transaction support and error handling.
 */
class AANP_DB_Migrator {

    /**
     * Current database version
     * @var string
     */
    private static $current_version = '1.0';

    /**
     * Latest database version
     * @var string
     */
    private static $latest_version = '2.0';

    /**
     * Error handler instance
     * @var AANP_Error_Handler
     */
    private $error_handler;

    /**
     * Constructor
     */
    public function __construct() {
        $this->error_handler = AANP_Error_Handler::getInstance();
    }

    /**
     * Run all pending database migrations
     *
     * Checks current database version and runs all pending migrations up to the latest version.
     * Uses transactions for safety and error handling via AANP_Error_Handler.
     */
    public function run_migrations() {
        try {
            global $wpdb;

            // Get current database version from options
            $current_version = get_option('aanp_db_version', '1.0');

            // If already at latest version, nothing to do
            if (version_compare($current_version, self::$latest_version, '>=')) {
                return;
            }

            // Start transaction
            $wpdb->query('START TRANSACTION');

            // Run migrations based on current version
            if (version_compare($current_version, '1.0', '<')) {
                $this->migrate_v1();
            }

            if (version_compare($current_version, '2.0', '<')) {
                $this->migrate_v2();
            }

            // Update database version
            update_option('aanp_db_version', self::$latest_version, false);

            // Commit transaction
            $wpdb->query('COMMIT');

            $this->error_handler->log_info('Database migrations completed successfully', array(
                'from_version' => $current_version,
                'to_version' => self::$latest_version
            ));

        } catch (Exception $e) {
            global $wpdb;

            // Rollback transaction on error
            $wpdb->query('ROLLBACK');

            $this->error_handler->handle_error(
                'Database migration failed: ' . $e->getMessage(),
                array(
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ),
                'DATABASE',
                'ERROR',
                true,
                false
            );

            throw $e;
        }
    }

    /**
     * Migration to version 1.0
     *
     * Creates initial database tables for content verification system.
     * This includes verified sources and content verification tables.
     */
    private function migrate_v1() {
        try {
            global $wpdb;
            $charset_collate = $wpdb->get_charset_collate();

            // Create verified sources table
            $verified_sources_table = $wpdb->prefix . 'aanp_verified_sources';
            $sql = "CREATE TABLE {$verified_sources_table} (
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

            // Verify table creation
            if ($wpdb->get_var("SHOW TABLES LIKE '{$verified_sources_table}'") !== $verified_sources_table) {
                throw new Exception('Failed to create verified sources table');
            }

            // Create content verification table
            $content_verification_table = $wpdb->prefix . 'aanp_content_verification';
            $sql = "CREATE TABLE {$content_verification_table} (
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

            dbDelta($sql);

            // Verify table creation
            if ($wpdb->get_var("SHOW TABLES LIKE '{$content_verification_table}'") !== $content_verification_table) {
                throw new Exception('Failed to create content verification table');
            }

            $this->error_handler->log_info('Database migration v1 completed successfully');

        } catch (Exception $e) {
            $this->error_handler->handle_error(
                'Database migration v1 failed: ' . $e->getMessage(),
                array(
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ),
                'DATABASE'
            );
            throw $e;
        }
    }

    /**
     * Migration to version 2.0
     *
     * Creates additional database tables for content filtering, analytics, and other plugin features.
     * Adds indexes to existing tables for better performance.
     */
    private function migrate_v2() {
        try {
            global $wpdb;
            $charset_collate = $wpdb->get_charset_collate();

            // Create content bundles enhanced table
            $content_bundles_table = $wpdb->prefix . 'aanp_content_bundles_enhanced';
            $sql = "CREATE TABLE {$content_bundles_table} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL UNIQUE,
                description TEXT,
                category VARCHAR(100) NOT NULL,
                visibility ENUM('visible', 'hidden', 'specialized', 'regional', 'custom') DEFAULT 'visible',
                sort_order INT DEFAULT 0,
                positive_keywords TEXT,
                negative_keywords TEXT,
                priority_regions VARCHAR(255),
                content_age_limit INT DEFAULT 90,
                is_default BOOLEAN DEFAULT FALSE,
                is_custom BOOLEAN DEFAULT FALSE,
                is_active BOOLEAN DEFAULT TRUE,
                categories JSON,
                enabled_feeds JSON,
                settings JSON,
                created_by INT DEFAULT 0,
                usage_count INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_visibility (visibility),
                INDEX idx_sort_order (sort_order),
                INDEX idx_category (category),
                INDEX idx_active (is_active)
            ) {$charset_collate};";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);

            // Verify table creation
            if ($wpdb->get_var("SHOW TABLES LIKE '{$content_bundles_table}'") !== $content_bundles_table) {
                throw new Exception('Failed to create content bundles enhanced table');
            }

            // Create RSS feeds enhanced table
            $rss_feeds_table = $wpdb->prefix . 'aanp_rss_feeds_enhanced';
            $sql = "CREATE TABLE {$rss_feeds_table} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                bundle_id INT,
                feed_url VARCHAR(500) NOT NULL,
                feed_name VARCHAR(255),
                category VARCHAR(100),
                region VARCHAR(10),
                quality_score DECIMAL(3,2) DEFAULT 0.00,
                last_validated TIMESTAMP,
                is_active BOOLEAN DEFAULT TRUE,
                discovered_by ENUM('manual', 'auto', 'user') DEFAULT 'manual',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (bundle_id) REFERENCES {$wpdb->prefix}aanp_content_bundles_enhanced(id) ON DELETE CASCADE,
                INDEX idx_bundle (bundle_id),
                INDEX idx_quality (quality_score),
                INDEX idx_active (is_active)
            ) {$charset_collate};";

            dbDelta($sql);

            // Verify table creation
            if ($wpdb->get_var("SHOW TABLES LIKE '{$rss_feeds_table}'") !== $rss_feeds_table) {
                throw new Exception('Failed to create RSS feeds enhanced table');
            }

            // Create user content filters table
            $user_filters_table = $wpdb->prefix . 'aanp_user_content_filters';
            $sql = "CREATE TABLE {$user_filters_table} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT DEFAULT 0,
                bundle_slug VARCHAR(255),
                positive_keywords TEXT,
                negative_keywords TEXT,
                advanced_settings JSON,
                is_active BOOLEAN DEFAULT TRUE,
                preset_name VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) {$charset_collate};";

            dbDelta($sql);

            // Verify table creation
            if ($wpdb->get_var("SHOW TABLES LIKE '{$user_filters_table}'") !== $user_filters_table) {
                throw new Exception('Failed to create user content filters table');
            }

            // Create custom bundle presets table
            $presets_table = $wpdb->prefix . 'aanp_custom_bundle_presets';
            $sql = "CREATE TABLE {$presets_table} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                preset_name VARCHAR(255) NOT NULL,
                bundle_data JSON NOT NULL,
                keywords_data JSON,
                feeds_data JSON,
                settings_data JSON,
                is_public BOOLEAN DEFAULT FALSE,
                usage_count INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_user_preset (user_id, preset_name),
                INDEX idx_user (user_id),
                INDEX idx_public (is_public)
            ) {$charset_collate};";

            dbDelta($sql);

            // Verify table creation
            if ($wpdb->get_var("SHOW TABLES LIKE '{$presets_table}'") !== $presets_table) {
                throw new Exception('Failed to create custom bundle presets table');
            }

            // Create analytics table
            $analytics_table = $wpdb->prefix . 'aanp_analytics';
            $sql = "CREATE TABLE {$analytics_table} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                event_type VARCHAR(100) NOT NULL,
                event_category VARCHAR(100) NOT NULL,
                event_action VARCHAR(100) NOT NULL,
                event_label VARCHAR(255),
                event_value DECIMAL(10,2) DEFAULT 0.00,
                user_id INT DEFAULT 0,
                session_id VARCHAR(100),
                ip_address VARCHAR(45),
                user_agent TEXT,
                referrer VARCHAR(500),
                page_url VARCHAR(500),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_event_type (event_type),
                INDEX idx_event_category (event_category),
                INDEX idx_user_id (user_id),
                INDEX idx_created_at (created_at)
            ) {$charset_collate};";

            dbDelta($sql);

            // Verify table creation
            if ($wpdb->get_var("SHOW TABLES LIKE '{$analytics_table}'") !== $analytics_table) {
                throw new Exception('Failed to create analytics table');
            }

            // Create generated posts table
            $generated_posts_table = $wpdb->prefix . 'aanp_generated_posts';
            $sql = "CREATE TABLE {$generated_posts_table} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                post_id BIGINT(20) UNSIGNED NOT NULL,
                original_url VARCHAR(500) NOT NULL,
                source_domain VARCHAR(255),
                source_feed VARCHAR(500),
                content_hash VARCHAR(64),
                verification_status ENUM('verified', 'warning', 'error', 'pending') DEFAULT 'pending',
                retraction_detected BOOLEAN DEFAULT FALSE,
                retraction_confidence DECIMAL(3,2) DEFAULT 0.00,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_post_id (post_id),
                INDEX idx_original_url (original_url(191)),
                INDEX idx_verification_status (verification_status),
                INDEX idx_retraction_detected (retraction_detected),
                FOREIGN KEY (post_id) REFERENCES {$wpdb->posts}(ID) ON DELETE CASCADE
            ) {$charset_collate};";

            dbDelta($sql);

            // Verify table creation
            if ($wpdb->get_var("SHOW TABLES LIKE '{$generated_posts_table}'") !== $generated_posts_table) {
                throw new Exception('Failed to create generated posts table');
            }

            // Create RSS feeds table
            $rss_feeds_table = $wpdb->prefix . 'aanp_rss_feeds';
            $sql = "CREATE TABLE {$rss_feeds_table} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                feed_url VARCHAR(500) NOT NULL,
                feed_name VARCHAR(255),
                category VARCHAR(100),
                region VARCHAR(10),
                is_active BOOLEAN DEFAULT TRUE,
                last_fetched TIMESTAMP NULL DEFAULT NULL,
                last_updated TIMESTAMP NULL DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_feed_url (feed_url),
                INDEX idx_category (category),
                INDEX idx_region (region),
                INDEX idx_is_active (is_active)
            ) {$charset_collate};";

            dbDelta($sql);

            // Verify table creation
            if ($wpdb->get_var("SHOW TABLES LIKE '{$rss_feeds_table}'") !== $rss_feeds_table) {
                throw new Exception('Failed to create RSS feeds table');
            }

            // Create user feed selections table
            $user_selections_table = $wpdb->prefix . 'aanp_user_feed_selections';
            $sql = "CREATE TABLE {$user_selections_table} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                feed_id INT NOT NULL,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_user_feed (user_id, feed_id),
                INDEX idx_user_id (user_id),
                INDEX idx_feed_id (feed_id),
                INDEX idx_is_active (is_active)
            ) {$charset_collate};";

            dbDelta($sql);

            // Verify table creation
            if ($wpdb->get_var("SHOW TABLES LIKE '{$user_selections_table}'") !== $user_selections_table) {
                throw new Exception('Failed to create user feed selections table');
            }

            // Add indexes to existing tables for better performance
            $this->add_indexes_to_existing_tables();

            $this->error_handler->log_info('Database migration v2 completed successfully');

        } catch (Exception $e) {
            $this->error_handler->handle_error(
                'Database migration v2 failed: ' . $e->getMessage(),
                array(
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ),
                'DATABASE'
            );
            throw $e;
        }
    }

    /**
     * Add indexes to existing tables for better performance
     */
    private function add_indexes_to_existing_tables() {
        try {
            global $wpdb;

            // Add index to content verification table for better query performance
            $content_verification_table = $wpdb->prefix . 'aanp_content_verification';

            // Check if index exists before adding
            $index_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
                 WHERE table_schema = DATABASE() AND table_name = %s AND index_name = 'idx_processed_at'",
                $content_verification_table
            ));

            if ($index_exists == 0) {
                $wpdb->query("ALTER TABLE {$content_verification_table} ADD INDEX idx_processed_at (processed_at)");
            }

            // Add index to verified sources table for better query performance
            $verified_sources_table = $wpdb->prefix . 'aanp_verified_sources';

            // Check if index exists before adding
            $index_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
                 WHERE table_schema = DATABASE() AND table_name = %s AND index_name = 'idx_created_at'",
                $verified_sources_table
            ));

            if ($index_exists == 0) {
                $wpdb->query("ALTER TABLE {$verified_sources_table} ADD INDEX idx_created_at (created_at)");
            }

        } catch (Exception $e) {
            $this->error_handler->log_warning('Failed to add indexes to existing tables: ' . $e->getMessage(), array(
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ));
            // Don't throw - this is non-critical
        }
    }

    /**
     * Get current database version
     *
     * @return string Current database version
     */
    public static function get_current_version() {
        return get_option('aanp_db_version', '1.0');
    }

    /**
     * Get latest database version
     *
     * @return string Latest database version
     */
    public static function get_latest_version() {
        return self::$latest_version;
    }

    /**
     * Hook-ready method for register_activation_hook
     *
     * This method can be used with WordPress activation hooks to run migrations
     * when the plugin is activated.
     */
    public static function aanp_run_db_migrations() {
        $migrator = new self();
        $migrator->run_migrations();
    }
}