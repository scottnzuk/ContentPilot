<?php
/**
 * PHPUnit tests for ContentCreationService
 *
 * Tests the content creation functionality including post creation,
 * batch processing, and integration with other services.
 *
 * @package AI_Auto_News_Poster\Tests\Microservices
 */

require_once __DIR__ . '/MicroservicesTestBase.php';

class ContentCreationServiceTest extends MicroservicesTestBase
{
    /** @var AANP_ContentCreationService */
    private $contentCreationService;

    /** @var AANP_EEATOptimizer */
    private $eeatOptimizer;

    /**
     * Set up before each test
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->eeatOptimizer = new AANP_EEATOptimizer();
        $this->contentCreationService = new AANP_ContentCreationService($this->cacheManager, $this->eeatOptimizer);
    }

    /**
     * Test post creation
     */
    public function testPostCreation()
    {
        $this->logInfo('Testing Post creation');

        $post_data = $this->createTestPostData();

        $creation_result = $this->contentCreationService->create_post($post_data, array());

        $this->assertIsArray($creation_result, 'Post creation should return an array');
        $this->assertArrayHasKey('success', $creation_result, 'Result should have success key');
        $this->assertArrayHasKey('post_id', $creation_result, 'Result should have post ID');
        $this->assertTrue($creation_result['success'], 'Post creation should succeed');
    }

    /**
     * Test batch post creation
     */
    public function testBatchPostCreation()
    {
        $this->logInfo('Testing Batch post creation');

        $batch_data = array(
            $this->createTestPostData(),
            $this->createTestPostData(array('title' => 'Second Test Post'))
        );

        $batch_result = $this->contentCreationService->create_batch_posts($batch_data);

        $this->assertIsArray($batch_result, 'Batch post creation should return an array');
        $this->assertArrayHasKey('success', $batch_result, 'Result should have success key');
        $this->assertArrayHasKey('created_posts', $batch_result, 'Result should have created posts count');
        $this->assertTrue($batch_result['success'], 'Batch post creation should succeed');
        $this->assertCount(2, $batch_result['post_ids'], 'Should create 2 posts');
    }

    /**
     * Test content service health check
     */
    public function testContentServiceHealthCheck()
    {
        $this->logInfo('Testing Content service health check');

        $health = $this->contentCreationService->health_check();
        $this->assertIsBool($health, 'Health check should return a boolean');
        $this->assertTrue($health, 'Content service should be healthy');
    }

    /**
     * Test post data validation
     */
    public function testPostDataValidation()
    {
        $this->logInfo('Testing Post data validation');

        // Test with valid data
        $valid_data = $this->createTestPostData();
        $valid_result = $this->contentCreationService->validate_post_data($valid_data);

        // Test with invalid data
        $invalid_data = array('title' => '', 'content' => '');
        $invalid_result = $this->contentCreationService->validate_post_data($invalid_data);

        $this->assertTrue($valid_result['valid'], 'Valid post data should pass validation');
        $this->assertFalse($invalid_result['valid'], 'Invalid post data should fail validation');
        $this->assertArrayHasKey('errors', $invalid_result, 'Invalid result should have errors');
    }

    /**
     * Test post status handling
     */
    public function testPostStatusHandling()
    {
        $this->logInfo('Testing Post status handling');

        // Test with different statuses
        $draft_data = $this->createTestPostData(array('status' => 'draft'));
        $published_data = $this->createTestPostData(array('status' => 'publish'));

        $draft_result = $this->contentCreationService->create_post($draft_data, array());
        $published_result = $this->contentCreationService->create_post($published_data, array());

        $this->assertTrue($draft_result['success'], 'Draft post creation should succeed');
        $this->assertTrue($published_result['success'], 'Published post creation should succeed');
    }

    /**
     * Test post metadata handling
     */
    public function testPostMetadataHandling()
    {
        $this->logInfo('Testing Post metadata handling');

        $post_data = $this->createTestPostData();
        $post_data['metadata'] = array(
            'custom_field' => 'custom_value',
            'another_field' => 'another_value'
        );

        $creation_result = $this->contentCreationService->create_post($post_data, array());

        $this->assertTrue($creation_result['success'], 'Post with metadata should be created successfully');
    }

    /**
     * Test content optimization integration
     */
    public function testContentOptimizationIntegration()
    {
        $this->logInfo('Testing Content optimization integration');

        $post_data = $this->createTestPostData();

        $creation_result = $this->contentCreationService->create_post($post_data, array(
            'optimize_for_eeat' => true,
            'optimization_level' => 'basic'
        ));

        $this->assertTrue($creation_result['success'], 'Post with optimization should be created successfully');
        $this->assertArrayHasKey('optimization_result', $creation_result, 'Result should have optimization information');
    }

    /**
     * Test error handling
     */
    public function testErrorHandling()
    {
        $this->logInfo('Testing Error handling');

        // Test with invalid post data
        $invalid_data = array('title' => '', 'content' => '');
        $result = $this->contentCreationService->create_post($invalid_data, array());

        $this->assertIsArray($result, 'Error handling should return an array');
        $this->assertFalse($result['success'], 'Invalid post data should cause creation to fail');
        $this->assertArrayHasKey('error', $result, 'Result should have error information');
    }

    /**
     * Test transaction handling
     */
    public function testTransactionHandling()
    {
        $this->logInfo('Testing Transaction handling');

        $post_data = $this->createTestPostData();

        // Start transaction
        $this->contentCreationService->begin_transaction();

        // Create post within transaction
        $creation_result = $this->contentCreationService->create_post($post_data, array());

        // Rollback transaction
        $this->contentCreationService->rollback_transaction();

        $this->assertTrue($creation_result['success'], 'Post creation within transaction should succeed');

        // Note: In a real test environment, we would verify that the post was not actually created
        // due to the rollback, but this requires database access
    }

    /**
     * Test performance metrics
     */
    public function testPerformanceMetrics()
    {
        $this->logInfo('Testing Performance metrics');

        $post_data = $this->createTestPostData();

        $creation_result = $this->contentCreationService->create_post($post_data, array());

        $this->assertArrayHasKey('performance', $creation_result, 'Result should have performance metrics');
        $this->assertIsArray($creation_result['performance'], 'Performance metrics should be an array');
        $this->assertArrayHasKey('creation_time', $creation_result['performance'], 'Performance metrics should include creation time');
        $this->assertArrayHasKey('memory_usage', $creation_result['performance'], 'Performance metrics should include memory usage');
    }
}