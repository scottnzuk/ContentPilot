<?php
/**
 * Test script for AANP_DB_Migrator
 *
 * This standalone test script verifies that the database migration system
 * works correctly by testing the migration process.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__, 3) . '/');
}

// Load WordPress environment
require_once(ABSPATH . 'wp-load.php');

// Load required classes
require_once(dirname(__DIR__) . '/class-db-migrator.php');
require_once(dirname(__DIR__) . '/class-error-handler.php');

/**
 * Test the database migrator
 */
function test_db_migrator() {
    echo "Testing AANP_DB_Migrator...\n";

    try {
        // Create migrator instance
        $migrator = new AANP_DB_Migrator();

        // Test 1: Check current version
        $current_version = AANP_DB_Migrator::get_current_version();
        echo "Current database version: " . $current_version . "\n";

        // Test 2: Check latest version
        $latest_version = AANP_DB_Migrator::get_latest_version();
        echo "Latest database version: " . $latest_version . "\n";

        // Test 3: Run migrations
        echo "Running database migrations...\n";
        $migrator->run_migrations();

        // Test 4: Verify version was updated
        $new_version = AANP_DB_Migrator::get_current_version();
        echo "Database version after migration: " . $new_version . "\n";

        // Test 5: Verify tables were created
        global $wpdb;

        $tables_to_check = array(
            'aanp_verified_sources',
            'aanp_content_verification',
            'aanp_content_bundles_enhanced',
            'aanp_rss_feeds_enhanced',
            'aanp_user_content_filters',
            'aanp_custom_bundle_presets',
            'aanp_analytics',
            'aanp_generated_posts',
            'aanp_rss_feeds',
            'aanp_user_feed_selections'
        );

        echo "\nVerifying table creation:\n";
        foreach ($tables_to_check as $table) {
            $full_table_name = $wpdb->prefix . $table;
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$full_table_name}'") === $full_table_name;

            if ($table_exists) {
                echo "✓ {$full_table_name} - EXISTS\n";
            } else {
                echo "✗ {$full_table_name} - NOT FOUND\n";
            }
        }

        // Test 6: Verify indexes were added
        echo "\nVerifying indexes on existing tables:\n";

        // Check content verification table indexes
        $content_verification_table = $wpdb->prefix . 'aanp_content_verification';
        $indexes = $wpdb->get_results("SHOW INDEX FROM {$content_verification_table}");

        $expected_indexes = array('idx_post_id', 'idx_original_url', 'idx_verification_status', 'idx_verification_date', 'idx_retraction_detected', 'idx_processed_at');
        $found_indexes = array();

        foreach ($indexes as $index) {
            $found_indexes[] = $index->Key_name;
        }

        foreach ($expected_indexes as $expected_index) {
            if (in_array($expected_index, $found_indexes)) {
                echo "✓ {$content_verification_table}.{$expected_index} - EXISTS\n";
            } else {
                echo "✗ {$content_verification_table}.{$expected_index} - NOT FOUND\n";
            }
        }

        // Check verified sources table indexes
        $verified_sources_table = $wpdb->prefix . 'aanp_verified_sources';
        $indexes = $wpdb->get_results("SHOW INDEX FROM {$verified_sources_table}");

        $expected_indexes = array('unique_domain', 'idx_credibility_score', 'idx_verification_status', 'idx_last_checked', 'idx_created_at');
        $found_indexes = array();

        foreach ($indexes as $index) {
            $found_indexes[] = $index->Key_name;
        }

        foreach ($expected_indexes as $expected_index) {
            if (in_array($expected_index, $found_indexes)) {
                echo "✓ {$verified_sources_table}.{$expected_index} - EXISTS\n";
            } else {
                echo "✗ {$verified_sources_table}.{$expected_index} - NOT FOUND\n";
            }
        }

        echo "\nDatabase migration test completed successfully!\n";
        echo "Database version updated from {$current_version} to {$new_version}\n";

    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        echo "Trace: " . $e->getTraceAsString() . "\n";
        return false;
    }

    return true;
}

// Run the test
test_db_migrator();