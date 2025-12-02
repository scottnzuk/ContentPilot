<?php
/**
 * PHPUnit tests for NewsFetchService
 *
 * Tests the news fetching functionality including RSS feed processing,
 * batch processing, and integration with other services.
 *
 * @package AI_Auto_News_Poster\Tests\Microservices
 */

require_once __DIR__ . '/MicroservicesTestBase.php';

class NewsFetchServiceTest extends MicroservicesTestBase
{
    /** @var AANP_NewsFetchService */
    private $newsFetchService;

    /**
     * Set up before each test
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->newsFetchService = new AANP_NewsFetchService($this->cacheManager, $this->queueManager);
    }

    /**
     * Test news fetching
     */
    public function testNewsFetching()
    {
        $this->logInfo('Testing News fetching');

        $sources = array('https://test-feed.com/rss.xml');

        $fetch_result = $this->newsFetchService->fetch_news($sources, array('limit' => 5));

        $this->assertIsArray($fetch_result, 'News fetching should return an array');
        $this->assertArrayHasKey('success', $fetch_result, 'Result should have success key');
        $this->assertArrayHasKey('items', $fetch_result, 'Result should have items');
        $this->assertArrayHasKey('total_found', $fetch_result, 'Result should have total found count');
        $this->assertTrue($fetch_result['success'], 'News fetching should succeed');
    }

    /**
     * Test batch processing
     */
    public function testBatchProcessing()
    {
        $this->logInfo('Testing Batch processing');

        $sources = array(
            'https://test-feed.com/rss.xml',
            'https://another-feed.com/rss.xml'
        );

        $batch_result = $this->newsFetchService->process_news_batch($sources, 10);

        $this->assertIsArray($batch_result, 'Batch processing should return an array');
        $this->assertArrayHasKey('success', $batch_result, 'Result should have success key');
        $this->assertArrayHasKey('processed_items', $batch_result, 'Result should have processed items');
        $this->assertArrayHasKey('total_processed', $batch_result, 'Result should have total processed count');
        $this->assertTrue($batch_result['success'], 'Batch processing should succeed');
    }

    /**
     * Test news service health check
     */
    public function testNewsServiceHealthCheck()
    {
        $this->logInfo('Testing News service health check');

        $health = $this->newsFetchService->health_check();
        $this->assertIsBool($health, 'Health check should return a boolean');
        $this->assertTrue($health, 'News service should be healthy');
    }

    /**
     * Test feed validation
     */
    public function testFeedValidation()
    {
        $this->logInfo('Testing Feed validation');

        $validation_result = $this->newsFetchService->validate_feed('https://test-feed.com/rss.xml');

        $this->assertIsArray($validation_result, 'Feed validation should return an array');
        $this->assertArrayHasKey('valid', $validation_result, 'Validation result should have valid key');
        $this->assertArrayHasKey('item_count', $validation_result, 'Validation result should have item count');
        $this->assertTrue($validation_result['valid'], 'Valid feed should pass validation');
    }

    /**
     * Test content extraction
     */
    public function testContentExtraction()
    {
        $this->logInfo('Testing Content extraction');

        $rss_content = '<rss><channel>
            <item>
                <title>Test Article</title>
                <description>Test description</description>
                <link>https://test.com/article</link>
                <pubDate>Mon, 01 Jan 2023 12:00:00 GMT</pubDate>
            </item>
        </channel></rss>';

        $extracted_items = $this->newsFetchService->extract_items_from_feed($rss_content);

        $this->assertIsArray($extracted_items, 'Content extraction should return an array');
        $this->assertCount(1, $extracted_items, 'Should extract one item from feed');
        $this->assertArrayHasKey('title', $extracted_items[0], 'Extracted item should have title');
        $this->assertArrayHasKey('description', $extracted_items[0], 'Extracted item should have description');
        $this->assertArrayHasKey('link', $extracted_items[0], 'Extracted item should have link');
    }

    /**
     * Test duplicate detection
     */
    public function testDuplicateDetection()
    {
        $this->logInfo('Testing Duplicate detection');

        $item1 = array(
            'title' => 'Test Article',
            'link' => 'https://test.com/article1',
            'content_hash' => md5('Test Article' . 'https://test.com/article1')
        );

        $item2 = array(
            'title' => 'Test Article',
            'link' => 'https://test.com/article1', // Same URL
            'content_hash' => md5('Test Article' . 'https://test.com/article1')
        );

        $item3 = array(
            'title' => 'Different Article',
            'link' => 'https://test.com/article2',
            'content_hash' => md5('Different Article' . 'https://test.com/article2')
        );

        $is_duplicate1 = $this->newsFetchService->is_duplicate($item1, array($item2));
        $is_duplicate2 = $this->newsFetchService->is_duplicate($item1, array($item3));

        $this->assertTrue($is_duplicate1, 'Items with same URL should be detected as duplicates');
        $this->assertFalse($is_duplicate2, 'Items with different URLs should not be detected as duplicates');
    }

    /**
     * Test content filtering
     */
    public function testContentFiltering()
    {
        $this->logInfo('Testing Content filtering');

        $items = array(
            array(
                'title' => 'Valid Article',
                'description' => 'This is a valid article about technology',
                'keywords' => array('technology', 'innovation')
            ),
            array(
                'title' => 'Invalid Article',
                'description' => 'This article contains spam content',
                'keywords' => array('spam', 'advertisement')
            )
        );

        $filtered_items = $this->newsFetchService->filter_items($items, array('allowed_keywords' => array('technology')));

        $this->assertCount(1, $filtered_items, 'Should filter out invalid items');
        $this->assertEquals('Valid Article', $filtered_items[0]['title'], 'Should keep valid items');
    }

    /**
     * Test performance metrics
     */
    public function testPerformanceMetrics()
    {
        $this->logInfo('Testing Performance metrics');

        $sources = array('https://test-feed.com/rss.xml');

        $fetch_result = $this->newsFetchService->fetch_news($sources, array('limit' => 5));

        $this->assertArrayHasKey('performance', $fetch_result, 'Result should have performance metrics');
        $this->assertIsArray($fetch_result['performance'], 'Performance metrics should be an array');
        $this->assertArrayHasKey('fetch_time', $fetch_result['performance'], 'Performance metrics should include fetch time');
        $this->assertArrayHasKey('processing_time', $fetch_result['performance'], 'Performance metrics should include processing time');
    }

    /**
     * Test error handling
     */
    public function testErrorHandling()
    {
        $this->logInfo('Testing Error handling');

        // Test with invalid feed URL
        $fetch_result = $this->newsFetchService->fetch_news(array('https://invalid-feed.com/rss.xml'), array('limit' => 5));

        $this->assertIsArray($fetch_result, 'Error handling should return an array');
        $this->assertFalse($fetch_result['success'], 'Fetching from invalid feed should fail');
        $this->assertArrayHasKey('error', $fetch_result, 'Result should have error information');
    }
}