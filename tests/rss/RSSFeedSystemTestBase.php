<?php
/**
 * Base test class for RSS Feed System tests
 *
 * Provides common setup and utilities for RSS feed system tests
 *
 * @package AI_Auto_News_Poster\Tests\RSS
 */

use PHPUnit\Framework\TestCase;

class RSSFeedSystemTestBase extends TestCase
{
    /** @var AANP_RSSFeedManager */
    protected $rssManager;

    /** @var wpdb */
    protected $wpdb;

    /** @var AANP_Logger */
    protected $logger;

    /**
     * Set up before each test
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Initialize logger
        $this->logger = AANP_Logger::getInstance();

        // Initialize database
        global $wpdb;
        $this->wpdb = $wpdb;

        // Create RSS feed manager instance
        $this->rssManager = new AANP_RSSFeedManager();

        // Create test tables
        $this->createTestTables();
    }

    /**
     * Tear down after each test
     */
    protected function tearDown(): void
    {
        // Clean up test tables
        $this->cleanupTestTables();

        parent::tearDown();
    }

    /**
     * Create test tables
     */
    protected function createTestTables()
    {
        $feeds_table = $this->wpdb->prefix . 'aanp_rss_feeds';
        $stats_table = $this->wpdb->prefix . 'aanp_rss_feed_stats';

        // Create feeds table
        $this->wpdb->query("DROP TABLE IF EXISTS {$feeds_table}");
        $this->wpdb->query("
            CREATE TABLE {$feeds_table} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                url VARCHAR(512) NOT NULL,
                region VARCHAR(100) NOT NULL,
                category VARCHAR(100) NOT NULL,
                reliability INT DEFAULT 50,
                is_enabled TINYINT(1) DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_url (url)
            )
        ");

        // Create stats table
        $this->wpdb->query("DROP TABLE IF EXISTS {$stats_table}");
        $this->wpdb->query("
            CREATE TABLE {$stats_table} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                feed_id INT NOT NULL,
                last_fetch_success TINYINT(1) DEFAULT 0,
                last_fetch_time DATETIME NULL,
                last_fetch_items INT DEFAULT 0,
                consecutive_failures INT DEFAULT 0,
                last_error VARCHAR(255) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (feed_id) REFERENCES {$feeds_table}(id) ON DELETE CASCADE
            )
        ");
    }

    /**
     * Clean up test tables
     */
    protected function cleanupTestTables()
    {
        $feeds_table = $this->wpdb->prefix . 'aanp_rss_feeds';
        $stats_table = $this->wpdb->prefix . 'aanp_rss_feed_stats';

        $this->wpdb->query("DROP TABLE IF EXISTS {$stats_table}");
        $this->wpdb->query("DROP TABLE IF EXISTS {$feeds_table}");
    }

    /**
     * Insert test feeds
     *
     * @param array $feeds Array of feed data
     */
    protected function insertTestFeeds($feeds)
    {
        foreach ($feeds as $feed) {
            $this->wpdb->insert(
                $this->wpdb->prefix . 'aanp_rss_feeds',
                $feed
            );
        }
    }

    /**
     * Create test feed data
     *
     * @return array Test feed data
     */
    protected function createTestFeedData()
    {
        return array(
            'name' => 'Test Feed',
            'url' => 'https://test-feed.com/rss.xml',
            'region' => 'US',
            'category' => 'General',
            'reliability' => 90,
            'is_enabled' => 1
        );
    }

    /**
     * Log test information
     *
     * @param string $message
     * @param array $context
     */
    protected function logInfo($message, $context = array())
    {
        if ($this->logger) {
            $this->logger->log_info($message, $context);
        }
    }

    /**
     * Log test error
     *
     * @param string $message
     * @param array $context
     */
    protected function logError($message, $context = array())
    {
        if ($this->logger) {
            $this->logger->log_error($message, $context);
        }
    }
}