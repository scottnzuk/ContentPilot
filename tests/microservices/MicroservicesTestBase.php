<?php
/**
 * Base test class for Microservices tests
 *
 * Provides common setup and utilities for all microservices tests
 *
 * @package AI_Auto_News_Poster\Tests\Microservices
 */

use PHPUnit\Framework\TestCase;

class MicroservicesTestBase extends TestCase
{
    /** @var AANP_ServiceRegistry */
    protected $serviceRegistry;

    /** @var AANP_ServiceOrchestrator */
    protected $serviceOrchestrator;

    /** @var AANP_AdvancedCacheManager */
    protected $cacheManager;

    /** @var AANP_ConnectionPoolManager */
    protected $connectionPoolManager;

    /** @var AANP_QueueManager */
    protected $queueManager;

    /** @var AANP_Logger */
    protected $logger;

    /**
     * Set up before each test
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Initialize core services
        $this->serviceRegistry = new AANP_ServiceRegistry();
        $this->serviceOrchestrator = new AANP_ServiceOrchestrator($this->serviceRegistry);
        $this->cacheManager = new AANP_AdvancedCacheManager();
        $this->connectionPoolManager = new AANP_ConnectionPoolManager();
        $this->queueManager = new AANP_QueueManager();

        // Initialize logger
        $this->logger = AANP_Logger::getInstance();

        // Register core services
        $this->serviceRegistry->register_service('service_registry', 'AANP_ServiceRegistry', array());
        $this->serviceRegistry->register_service('service_orchestrator', 'AANP_ServiceOrchestrator', array($this->serviceRegistry));
        $this->serviceRegistry->register_service('cache_manager', 'AANP_AdvancedCacheManager', array());
        $this->serviceRegistry->register_service('connection_pool_manager', 'AANP_ConnectionPoolManager', array());
        $this->serviceRegistry->register_service('queue_manager', 'AANP_QueueManager', array());
    }

    /**
     * Tear down after each test
     */
    protected function tearDown(): void
    {
        // Clean up cache
        if ($this->cacheManager) {
            $this->cacheManager->clear_cache();
        }

        // Clean up queue
        if ($this->queueManager) {
            $this->queueManager->clear_queue();
        }

        parent::tearDown();
    }

    /**
     * Assert that a service is properly registered and retrievable
     *
     * @param string $service_name
     * @param string $expected_class
     */
    protected function assertServiceRegistered($service_name, $expected_class)
    {
        $service = $this->serviceRegistry->get($service_name);
        $this->assertInstanceOf($expected_class, $service, "Service {$service_name} should be an instance of {$expected_class}");
        return $service;
    }

    /**
     * Create test content data
     *
     * @return array Test content data
     */
    protected function createTestContent()
    {
        return array(
            'title' => 'Test Article Title',
            'content' => '<p>This is test content with some keywords for analysis testing purposes.</p>',
            'meta_description' => 'This is a test meta description for SEO analysis.'
        );
    }

    /**
     * Create test analytics data
     *
     * @return array Test analytics data
     */
    protected function createTestAnalyticsData()
    {
        return array(
            'posts_created' => 5,
            'generation_time' => 1500,
            'success_rate' => 0.95
        );
    }

    /**
     * Create test post data
     *
     * @return array Test post data
     */
    protected function createTestPostData()
    {
        return array(
            'title' => 'Test Post Title',
            'content' => '<p>This is test post content.</p>',
            'status' => 'draft'
        );
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
            'reliability' => 90
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