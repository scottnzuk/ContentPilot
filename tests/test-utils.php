<?php
/**
 * Test utilities for PHPUnit tests
 *
 * Provides common test helpers and assertions for the
 * AI Auto News Poster plugin tests.
 *
 * @package AI_Auto_News_Poster\Tests
 */

class AANP_Test_Utils
{
    /**
     * Create a mock service for testing
     *
     * @param string $class_name Class name to mock
     * @param array $methods Methods to mock
     * @return PHPUnit\Framework\MockObject\MockObject
     */
    public static function createMockService($class_name, $methods = array())
    {
        $mock = $this->getMockBuilder($class_name)
                    ->disableOriginalConstructor()
                    ->onlyMethods($methods)
                    ->getMock();
        return $mock;
    }

    /**
     * Create a test service registry with mock services
     *
     * @param array $services Array of service names and their mocks
     * @return AANP_ServiceRegistry
     */
    public static function createTestServiceRegistry($services = array())
    {
        $registry = new AANP_ServiceRegistry();

        foreach ($services as $name => $service) {
            $registry->register($name, get_class($service), array());
        }

        return $registry;
    }

    /**
     * Assert that a service is properly registered and retrievable
     *
     * @param AANP_ServiceRegistry $registry
     * @param string $service_name
     * @param string $expected_class
     */
    public static function assertServiceRegistered($registry, $service_name, $expected_class)
    {
        $service = $registry->get($service_name);
        PHPUnit\Framework\Assert::assertInstanceOf($expected_class, $service, "Service {$service_name} should be an instance of {$expected_class}");
        return $service;
    }

    /**
     * Create test content data
     *
     * @return array Test content data
     */
    public static function createTestContent()
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
    public static function createTestAnalyticsData()
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
    public static function createTestPostData()
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
    public static function createTestFeedData()
    {
        return array(
            'name' => 'Test Feed',
            'url' => 'https://test-feed.com/rss.xml',
            'region' => 'US',
            'category' => 'General',
            'reliability' => 90
        );
    }
}