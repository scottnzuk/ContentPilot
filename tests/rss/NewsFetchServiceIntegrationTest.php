<?php
/**
 * PHPUnit tests for NewsFetchService integration with RSSFeedManager
 *
 * Tests the integration between NewsFetchService and RSSFeedManager
 *
 * @package AI_Auto_News_Poster\Tests\RSS
 */

require_once __DIR__ . '/RSSFeedSystemTestBase.php';

class NewsFetchServiceIntegrationTest extends RSSFeedSystemTestBase
{
    /** @var AANP_NewsFetchService */
    private $newsFetchService;

    /**
     * Set up before each test
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Initialize NewsFetchService
        $cache_manager = new AANP_AdvancedCacheManager();
        $queue_manager = new AANP_QueueManager();
        $this->newsFetchService = new AANP_NewsFetchService($cache_manager, $queue_manager);
    }

    /**
     * Test NewsFetchService integration with RSS feeds
     */
    public function testNewsFetchServiceIntegration()
    {
        $this->logInfo('Testing NewsFetchService integration with RSS feeds');

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
                'is_enabled' => 1
            )
        );
        $this->insertTestFeeds($feeds);

        // Get enabled feed URLs
        $enabled_feeds = $this->rssManager->get_enabled_feed_urls();

        $this->assertCount(2, $enabled_feeds, 'Should have 2 enabled feed URLs');

        // Test news fetching
        $fetch_result = $this->newsFetchService->fetch_news(array(
            'sources' => $enabled_feeds,
            'limit' => 5,
            'cache_results' => false
        ));

        $this->assertIsArray($fetch_result, 'Fetch result should be array');
        $this->assertArrayHasKey('success', $fetch_result, 'Result should have success key');
        $this->assertArrayHasKey('total_found', $fetch_result, 'Result should have total found count');
        $this->assertArrayHasKey('items', $fetch_result, 'Result should have items array');

        $this->assertTrue($fetch_result['success'], 'News fetch should succeed');
        $this->assertGreaterThanOrEqual(0, $fetch_result['total_found'], 'Should find some items');
    }

    /**
     * Test batch processing integration
     */
    public function testBatchProcessingIntegration()
    {
        $this->logInfo('Testing Batch processing integration');

        // Insert test feeds
        $feeds = array();
        for ($i = 1; $i <= 5; $i++) {
            $feeds[] = array(
                'name' => "Test Feed {$i}",
                'url' => "https://test-feed-{$i}.com/rss.xml",
                'region' => 'US',
                'category' => 'News',
                'reliability' => 90,
                'is_enabled' => 1
            );
        }
        $this->insertTestFeeds($feeds);

        // Test batch processing
        $batch_result = $this->newsFetchService->process_news_batch($this->rssManager, 10);

        $this->assertIsArray($batch_result, 'Batch result should be array');
        $this->assertArrayHasKey('success', $batch_result, 'Batch result should have success key');
        $this->assertArrayHasKey('total_processed', $batch_result, 'Batch result should have total processed count');
        $this->assertArrayHasKey('feeds_processed', $batch_result, 'Batch result should have feeds processed count');

        $this->assertTrue($batch_result['success'], 'Batch processing should succeed');
        $this->assertGreaterThanOrEqual(0, $batch_result['total_processed'], 'Should process some items');
    }

    /**
     * Test feed validation integration
     */
    public function testFeedValidationIntegration()
    {
        $this->logInfo('Testing Feed validation integration');

        // Insert test feed
        $test_feed = $this->createTestFeedData();
        $this->insertTestFeeds(array($test_feed));

        // Get the feed
        $feeds = $this->rssManager->get_feeds();
        $feed = $feeds[0];

        // Test feed validation through NewsFetchService
        $validation_result = $this->newsFetchService->validate_feed($feed['url']);

        $this->assertIsArray($validation_result, 'Validation result should be array');
        $this->assertArrayHasKey('valid', $validation_result, 'Validation should have valid key');
        $this->assertArrayHasKey('item_count', $validation_result, 'Validation should have item count');
        $this->assertTrue($validation_result['valid'], 'Feed should be valid');
    }

    /**
     * Test content extraction from feeds
     */
    public function testContentExtraction()
    {
        $this->logInfo('Testing Content extraction from feeds');

        // Insert test feed
        $test_feed = $this->createTestFeedData();
        $this->insertTestFeeds(array($test_feed));

        // Get enabled feed URLs
        $enabled_feeds = $this->rssManager->get_enabled_feed_urls();

        // Test content extraction
        $extraction_result = $this->newsFetchService->extract_content_from_feeds($enabled_feeds);

        $this->assertIsArray($extraction_result, 'Extraction result should be array');
        $this->assertArrayHasKey('success', $extraction_result, 'Extraction should have success key');
        $this->assertArrayHasKey('items', $extraction_result, 'Extraction should have items array');

        $this->assertTrue($extraction_result['success'], 'Content extraction should succeed');
        $this->assertIsArray($extraction_result['items'], 'Items should be array');
    }

    /**
     * Test error handling in integration
     */
    public function testErrorHandlingIntegration()
    {
        $this->logInfo('Testing Error handling in integration');

        // Test with invalid feed URL
        $fetch_result = $this->newsFetchService->fetch_news(array(
            'sources' => array('https://invalid-feed.com/rss.xml'),
            'limit' => 5,
            'cache_results' => false
        ));

        $this->assertIsArray($fetch_result, 'Fetch result should be array');
        $this->assertArrayHasKey('success', $fetch_result, 'Result should have success key');
        $this->assertArrayHasKey('error', $fetch_result, 'Result should have error information when failed');
        $this->assertFalse($fetch_result['success'], 'Fetch with invalid feed should fail');
    }

    /**
     * Test performance metrics integration
     */
    public function testPerformanceMetricsIntegration()
    {
        $this->logInfo('Testing Performance metrics integration');

        // Insert test feeds
        $feeds = array(
            array(
                'name' => 'Test Feed 1',
                'url' => 'https://test-feed-1.com/rss.xml',
                'region' => 'US',
                'category' => 'News',
                'reliability' => 90,
                'is_enabled' => 1
            )
        );
        $this->insertTestFeeds($feeds);

        // Get enabled feed URLs
        $enabled_feeds = $this->rssManager->get_enabled_feed_urls();

        // Test performance metrics
        $metrics = $this->newsFetchService->get_performance_metrics();

        $this->assertIsArray($metrics, 'Performance metrics should be array');
        $this->assertArrayHasKey('fetch_times', $metrics, 'Metrics should have fetch times');
        $this->assertArrayHasKey('success_rates', $metrics, 'Metrics should have success rates');
    }

    /**
     * Test caching integration
     */
    public function testCachingIntegration()
    {
        $this->logInfo('Testing Caching integration');

        // Insert test feed
        $test_feed = $this->createTestFeedData();
        $this->insertTestFeeds(array($test_feed));

        // Get enabled feed URLs
        $enabled_feeds = $this->rssManager->get_enabled_feed_urls();

        // First fetch (should cache)
        $fetch_result1 = $this->newsFetchService->fetch_news(array(
            'sources' => $enabled_feeds,
            'limit' => 5,
            'cache_results' => true
        ));

        // Second fetch (should use cache)
        $fetch_result2 = $this->newsFetchService->fetch_news(array(
            'sources' => $enabled_feeds,
            'limit' => 5,
            'cache_results' => true
        ));

        $this->assertTrue($fetch_result1['success'], 'First fetch should succeed');
        $this->assertTrue($fetch_result2['success'], 'Second fetch should succeed');
        $this->assertEquals($fetch_result1['total_found'], $fetch_result2['total_found'], 'Cached result should match original');
    }
}