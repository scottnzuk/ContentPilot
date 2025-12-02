<?php
/**
 * PHPUnit tests for RSSFeedManager
 *
 * Tests the RSS feed management functionality including database operations,
 * feed retrieval, search, and validation.
 *
 * @package AI_Auto_News_Poster\Tests\RSS
 */

require_once __DIR__ . '/RSSFeedSystemTestBase.php';

class RSSFeedManagerTest extends RSSFeedSystemTestBase
{
    /**
     * Test database creation
     */
    public function testDatabaseCreation()
    {
        $this->logInfo('Testing Database creation');

        // Check if tables exist
        $feeds_table = $this->wpdb->prefix . 'aanp_rss_feeds';
        $table_exists = $this->wpdb->get_var($this->wpdb->prepare("SHOW TABLES LIKE %s", $feeds_table));

        $this->assertEquals($feeds_table, $table_exists, 'Feeds table should be created');

        // Check if RSS manager can be instantiated
        $this->assertInstanceOf('AANP_RSSFeedManager', $this->rssManager, 'RSSFeedManager should be instantiated successfully');
    }

    /**
     * Test feed insertion and retrieval
     */
    public function testFeedInsertionAndRetrieval()
    {
        $this->logInfo('Testing Feed insertion and retrieval');

        // Insert test feed
        $test_feed = $this->createTestFeedData();
        $this->insertTestFeeds(array($test_feed));

        // Retrieve feeds
        $feeds = $this->rssManager->get_feeds();

        $this->assertIsArray($feeds, 'Feeds should be returned as array');
        $this->assertCount(1, $feeds, 'Should retrieve 1 feed');
        $this->assertEquals('Test Feed', $feeds[0]['name'], 'Feed name should match');
        $this->assertEquals('https://test-feed.com/rss.xml', $feeds[0]['url'], 'Feed URL should match');
    }

    /**
     * Test feed retrieval with pagination
     */
    public function testFeedPagination()
    {
        $this->logInfo('Testing Feed pagination');

        // Insert multiple test feeds
        $feeds = array();
        for ($i = 1; $i <= 10; $i++) {
            $feeds[] = array(
                'name' => "Test Feed {$i}",
                'url' => "https://test-feed-{$i}.com/rss.xml",
                'region' => 'US',
                'category' => 'General',
                'reliability' => 90,
                'is_enabled' => 1
            );
        }
        $this->insertTestFeeds($feeds);

        // Test pagination
        $page1 = $this->rssManager->get_feeds(array('limit' => 5, 'offset' => 0));
        $page2 = $this->rssManager->get_feeds(array('limit' => 5, 'offset' => 5));

        $this->assertCount(5, $page1, 'First page should have 5 feeds');
        $this->assertCount(5, $page2, 'Second page should have 5 feeds');
        $this->assertEquals('Test Feed 1', $page1[0]['name'], 'First feed on page 1 should be correct');
        $this->assertEquals('Test Feed 6', $page2[0]['name'], 'First feed on page 2 should be correct');
    }

    /**
     * Test regional feed filtering
     */
    public function testRegionalFeedFiltering()
    {
        $this->logInfo('Testing Regional feed filtering');

        // Insert test feeds with different regions
        $feeds = array(
            array(
                'name' => 'US Feed',
                'url' => 'https://us-feed.com/rss.xml',
                'region' => 'US',
                'category' => 'General',
                'reliability' => 90,
                'is_enabled' => 1
            ),
            array(
                'name' => 'UK Feed',
                'url' => 'https://uk-feed.com/rss.xml',
                'region' => 'UK',
                'category' => 'General',
                'reliability' => 85,
                'is_enabled' => 1
            )
        );
        $this->insertTestFeeds($feeds);

        // Test regional filtering
        $us_feeds = $this->rssManager->get_feeds_by_region('US');
        $uk_feeds = $this->rssManager->get_feeds_by_region('UK');

        $this->assertCount(1, $us_feeds, 'Should retrieve 1 US feed');
        $this->assertCount(1, $uk_feeds, 'Should retrieve 1 UK feed');
        $this->assertEquals('US Feed', $us_feeds[0]['name'], 'US feed should be correct');
        $this->assertEquals('UK Feed', $uk_feeds[0]['name'], 'UK feed should be correct');
    }

    /**
     * Test feed search functionality
     */
    public function testFeedSearch()
    {
        $this->logInfo('Testing Feed search');

        // Insert test feeds
        $feeds = array(
            array(
                'name' => 'BBC News',
                'url' => 'https://bbc.com/rss.xml',
                'region' => 'UK',
                'category' => 'News',
                'reliability' => 95,
                'is_enabled' => 1
            ),
            array(
                'name' => 'Guardian UK',
                'url' => 'https://guardian.co.uk/rss.xml',
                'region' => 'UK',
                'category' => 'News',
                'reliability' => 90,
                'is_enabled' => 1
            ),
            array(
                'name' => 'CNN News',
                'url' => 'https://cnn.com/rss.xml',
                'region' => 'US',
                'category' => 'News',
                'reliability' => 85,
                'is_enabled' => 1
            )
        );
        $this->insertTestFeeds($feeds);

        // Test search
        $bbc_feeds = $this->rssManager->search_feeds('bbc');
        $guardian_feeds = $this->rssManager->search_feeds('guardian');
        $uk_feeds = $this->rssManager->search_feeds('news', 'UK');

        $this->assertCount(1, $bbc_feeds, 'Should find 1 BBC feed');
        $this->assertCount(1, $guardian_feeds, 'Should find 1 Guardian feed');
        $this->assertCount(2, $uk_feeds, 'Should find 2 UK news feeds');
        $this->assertEquals('BBC News', $bbc_feeds[0]['name'], 'BBC feed should be correct');
    }

    /**
     * Test feed validation
     */
    public function testFeedValidation()
    {
        $this->logInfo('Testing Feed validation');

        // Insert test feed
        $test_feed = $this->createTestFeedData();
        $this->insertTestFeeds(array($test_feed));

        // Get the feed
        $feeds = $this->rssManager->get_feeds();
        $feed = $feeds[0];

        // Test validation (this will use the mocked wp_remote_get function)
        $validation = $this->rssManager->validate_feed($feed['url'], 10);

        $this->assertIsArray($validation, 'Validation should return array');
        $this->assertArrayHasKey('valid', $validation, 'Validation should have valid key');
        $this->assertTrue($validation['valid'], 'Feed should be valid');
        $this->assertArrayHasKey('item_count', $validation, 'Validation should have item count');
    }

    /**
     * Test feed management (enable/disable)
     */
    public function testFeedManagement()
    {
        $this->logInfo('Testing Feed management');

        // Insert test feeds
        $feeds = array(
            array(
                'name' => 'Test Feed 1',
                'url' => 'https://test-feed-1.com/rss.xml',
                'region' => 'US',
                'category' => 'General',
                'reliability' => 90,
                'is_enabled' => 1
            ),
            array(
                'name' => 'Test Feed 2',
                'url' => 'https://test-feed-2.com/rss.xml',
                'region' => 'US',
                'category' => 'General',
                'reliability' => 85,
                'is_enabled' => 1
            )
        );
        $this->insertTestFeeds($feeds);

        // Get feed IDs
        $all_feeds = $this->rssManager->get_feeds();
        $feed_ids = array_column($all_feeds, 'id');

        // Test enabling feeds
        $enable_result = $this->rssManager->enable_feeds($feed_ids);
        $this->assertIsArray($enable_result, 'Enable result should be array');
        $this->assertTrue($enable_result['success'], 'Enable operation should succeed');
        $this->assertEquals(2, $enable_result['enabled_count'], 'Should enable 2 feeds');

        // Test disabling feeds
        $disable_result = $this->rssManager->disable_feeds($feed_ids);
        $this->assertIsArray($disable_result, 'Disable result should be array');
        $this->assertTrue($disable_result['success'], 'Disable operation should succeed');
        $this->assertEquals(2, $disable_result['disabled_count'], 'Should disable 2 feeds');
    }

    /**
     * Test feed statistics
     */
    public function testFeedStatistics()
    {
        $this->logInfo('Testing Feed statistics');

        // Insert test feeds
        $feeds = array(
            array(
                'name' => 'Test Feed 1',
                'url' => 'https://test-feed-1.com/rss.xml',
                'region' => 'US',
                'category' => 'News',
                'reliability' => 90,
                'is_enabled' => 1
            ),
            array(
                'name' => 'Test Feed 2',
                'url' => 'https://test-feed-2.com/rss.xml',
                'region' => 'UK',
                'category' => 'Sports',
                'reliability' => 85,
                'is_enabled' => 0
            )
        );
        $this->insertTestFeeds($feeds);

        // Get statistics
        $stats = $this->rssManager->get_feed_statistics();

        $this->assertIsArray($stats, 'Statistics should be array');
        $this->assertArrayHasKey('total_feeds', $stats, 'Should have total feeds count');
        $this->assertArrayHasKey('enabled_feeds', $stats, 'Should have enabled feeds count');
        $this->assertArrayHasKey('disabled_feeds', $stats, 'Should have disabled feeds count');
        $this->assertArrayHasKey('average_reliability', $stats, 'Should have average reliability');
        $this->assertArrayHasKey('regions', $stats, 'Should have regions distribution');
        $this->assertArrayHasKey('categories', $stats, 'Should have categories distribution');

        $this->assertEquals(2, $stats['total_feeds'], 'Should have 2 total feeds');
        $this->assertEquals(1, $stats['enabled_feeds'], 'Should have 1 enabled feed');
        $this->assertEquals(1, $stats['disabled_feeds'], 'Should have 1 disabled feed');
    }

    /**
     * Test top reliable feeds selection
     */
    public function testTopReliableFeeds()
    {
        $this->logInfo('Testing Top reliable feeds');

        // Insert test feeds with different reliability
        $feeds = array(
            array(
                'name' => 'High Reliability Feed',
                'url' => 'https://high-reliability.com/rss.xml',
                'region' => 'US',
                'category' => 'News',
                'reliability' => 95,
                'is_enabled' => 1
            ),
            array(
                'name' => 'Medium Reliability Feed',
                'url' => 'https://medium-reliability.com/rss.xml',
                'region' => 'US',
                'category' => 'News',
                'reliability' => 80,
                'is_enabled' => 1
            ),
            array(
                'name' => 'Low Reliability Feed',
                'url' => 'https://low-reliability.com/rss.xml',
                'region' => 'US',
                'category' => 'News',
                'reliability' => 60,
                'is_enabled' => 1
            )
        );
        $this->insertTestFeeds($feeds);

        // Get top reliable feeds
        $top_feeds = $this->rssManager->get_top_reliable_feeds(2);

        $this->assertCount(2, $top_feeds, 'Should return 2 top feeds');
        $this->assertEquals('High Reliability Feed', $top_feeds[0]['name'], 'First feed should be most reliable');
        $this->assertEquals('Medium Reliability Feed', $top_feeds[1]['name'], 'Second feed should be medium reliable');
    }

    /**
     * Test feed update functionality
     */
    public function testFeedUpdate()
    {
        $this->logInfo('Testing Feed update');

        // Insert test feed
        $test_feed = $this->createTestFeedData();
        $this->insertTestFeeds(array($test_feed));

        // Get the feed
        $feeds = $this->rssManager->get_feeds();
        $feed = $feeds[0];

        // Update feed reliability
        $update_data = array(
            'id' => $feed['id'],
            'reliability' => 99,
            'is_enabled' => 0
        );

        $result = $this->rssManager->update_feed($update_data);

        $this->assertTrue($result, 'Feed update should succeed');

        // Verify update
        $updated_feed = $this->rssManager->get_feed_by_id($feed['id']);
        $this->assertEquals(99, $updated_feed['reliability'], 'Reliability should be updated');
        $this->assertEquals(0, $updated_feed['is_enabled'], 'Enabled status should be updated');
    }
}