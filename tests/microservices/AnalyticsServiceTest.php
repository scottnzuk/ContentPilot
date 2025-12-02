<?php
/**
 * PHPUnit tests for AnalyticsService
 *
 * Tests the analytics data collection and reporting functionality
 * including performance metrics, user analytics, and system monitoring.
 *
 * @package AI_Auto_News_Poster\Tests\Microservices
 */

require_once __DIR__ . '/MicroservicesTestBase.php';

class AnalyticsServiceTest extends MicroservicesTestBase
{
    /** @var AANP_AnalyticsService */
    private $analyticsService;

    /**
     * Set up before each test
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->analyticsService = new AANP_AnalyticsService($this->cacheManager, $this->connectionPoolManager);
    }

    /**
     * Test data collection
     */
    public function testDataCollection()
    {
        $this->logInfo('Testing Data collection');

        $analytics_data = $this->createTestAnalyticsData();

        $collection_result = $this->analyticsService->collect_data($analytics_data);

        $this->assertIsBool($collection_result, 'Data collection should return a boolean');
        $this->assertTrue($collection_result, 'Data collection should succeed');
    }

    /**
     * Test performance metrics
     */
    public function testPerformanceMetrics()
    {
        $this->logInfo('Testing Performance metrics');

        $metrics = $this->analyticsService->get_performance_metrics();

        $this->assertIsArray($metrics, 'Performance metrics should return an array');
        $this->assertArrayHasKey('system_performance', $metrics, 'Metrics should include system performance');
        $this->assertArrayHasKey('service_performance', $metrics, 'Metrics should include service performance');
        $this->assertArrayHasKey('content_performance', $metrics, 'Metrics should include content performance');
    }

    /**
     * Test analytics service health check
     */
    public function testAnalyticsServiceHealthCheck()
    {
        $this->logInfo('Testing Analytics service health check');

        $health = $this->analyticsService->health_check();
        $this->assertIsBool($health, 'Health check should return a boolean');
        $this->assertTrue($health, 'Analytics service should be healthy');
    }

    /**
     * Test data aggregation
     */
    public function testDataAggregation()
    {
        $this->logInfo('Testing Data aggregation');

        // Collect some test data
        $test_data = array(
            array(
                'posts_created' => 5,
                'generation_time' => 1500,
                'success_rate' => 0.95
            ),
            array(
                'posts_created' => 3,
                'generation_time' => 1200,
                'success_rate' => 0.90
            )
        );

        foreach ($test_data as $data) {
            $this->analyticsService->collect_data($data);
        }

        $aggregated_data = $this->analyticsService->get_aggregated_data();

        $this->assertIsArray($aggregated_data, 'Aggregated data should return an array');
        $this->assertArrayHasKey('total_posts_created', $aggregated_data, 'Aggregated data should include total posts created');
        $this->assertArrayHasKey('average_generation_time', $aggregated_data, 'Aggregated data should include average generation time');
        $this->assertArrayHasKey('average_success_rate', $aggregated_data, 'Aggregated data should include average success rate');
    }

    /**
     * Test report generation
     */
    public function testReportGeneration()
    {
        $this->logInfo('Testing Report generation');

        // Collect some test data
        $this->analyticsService->collect_data($this->createTestAnalyticsData());

        $report = $this->analyticsService->generate_report('daily');

        $this->assertIsArray($report, 'Report generation should return an array');
        $this->assertArrayHasKey('report_type', $report, 'Report should have type');
        $this->assertArrayHasKey('period', $report, 'Report should have period');
        $this->assertArrayHasKey('data', $report, 'Report should have data');
        $this->assertEquals('daily', $report['report_type'], 'Report type should be daily');
    }

    /**
     * Test data persistence
     */
    public function testDataPersistence()
    {
        $this->logInfo('Testing Data persistence');

        // Collect data
        $test_data = $this->createTestAnalyticsData();
        $this->analyticsService->collect_data($test_data);

        // Create new analytics service instance (simulates restart)
        $new_analytics_service = new AANP_AnalyticsService($this->cacheManager, $this->connectionPoolManager);

        // Get aggregated data
        $aggregated_data = $new_analytics_service->get_aggregated_data();

        $this->assertIsArray($aggregated_data, 'Persistent data should be retrievable');
        $this->assertGreaterThanOrEqual(5, $aggregated_data['total_posts_created'], 'Persistent data should include collected values');
    }

    /**
     * Test error handling
     */
    public function testErrorHandling()
    {
        $this->logInfo('Testing Error handling');

        // Test with invalid data
        $invalid_data = array(
            'posts_created' => 'invalid', // Should be numeric
            'generation_time' => 'invalid', // Should be numeric
            'success_rate' => 1.5 // Should be between 0 and 1
        );

        $result = $this->analyticsService->collect_data($invalid_data);

        $this->assertFalse($result, 'Invalid data should cause collection to fail');
    }

    /**
     * Test data filtering
     */
    public function testDataFiltering()
    {
        $this->logInfo('Testing Data filtering');

        // Collect data with different timestamps
        $now = time();
        $old_data = array(
            'posts_created' => 2,
            'generation_time' => 800,
            'success_rate' => 0.85,
            'timestamp' => $now - 86400 // 1 day ago
        );

        $recent_data = array(
            'posts_created' => 3,
            'generation_time' => 900,
            'success_rate' => 0.90,
            'timestamp' => $now // Now
        );

        $this->analyticsService->collect_data($old_data);
        $this->analyticsService->collect_data($recent_data);

        // Get filtered data (last 12 hours)
        $filtered_data = $this->analyticsService->get_filtered_data($now - 43200, $now);

        $this->assertIsArray($filtered_data, 'Filtered data should return an array');
        $this->assertCount(1, $filtered_data, 'Should return only recent data');
    }

    /**
     * Test performance trend analysis
     */
    public function testPerformanceTrendAnalysis()
    {
        $this->logInfo('Testing Performance trend analysis');

        // Collect data with different timestamps
        $now = time();
        $this->analyticsService->collect_data(array(
            'posts_created' => 5,
            'generation_time' => 1500,
            'success_rate' => 0.95,
            'timestamp' => $now - 86400 // 1 day ago
        ));

        $this->analyticsService->collect_data(array(
            'posts_created' => 6,
            'generation_time' => 1400,
            'success_rate' => 0.97,
            'timestamp' => $now // Now
        ));

        $trends = $this->analyticsService->get_performance_trends();

        $this->assertIsArray($trends, 'Performance trends should return an array');
        $this->assertArrayHasKey('generation_time_trend', $trends, 'Trends should include generation time trend');
        $this->assertArrayHasKey('success_rate_trend', $trends, 'Trends should include success rate trend');
    }
}