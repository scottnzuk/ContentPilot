<?php
/**
 * PHPUnit tests for Performance Benchmarks
 *
 * Tests the performance characteristics of the microservices architecture
 * including service registry operations, cache performance, and content analysis.
 *
 * @package AI_Auto_News_Poster\Tests\Microservices
 */

require_once __DIR__ . '/MicroservicesTestBase.php';

class PerformanceBenchmarksTest extends MicroservicesTestBase
{
    /**
     * Test service registry performance
     */
    public function testServiceRegistryPerformance()
    {
        $this->logInfo('Testing Service registry performance');

        $start_time = microtime(true);

        // Perform multiple service registry operations
        for ($i = 0; $i < 100; $i++) {
            $this->serviceRegistry->register('bench_' . $i, 'TestService' . $i, array());
            $this->serviceRegistry->get('bench_' . $i);
        }

        $execution_time = (microtime(true) - $start_time) * 1000; // Convert to milliseconds

        $this->assertLessThan(100, $execution_time, '100 service registry operations should complete in less than 100ms');
    }

    /**
     * Test cache performance
     */
    public function testCachePerformance()
    {
        $this->logInfo('Testing Cache performance');

        $start_time = microtime(true);

        // Perform multiple cache operations
        for ($i = 0; $i < 1000; $i++) {
            $this->cacheManager->set('bench_key_' . $i, 'value_' . $i, 300);
            $this->cacheManager->get('bench_key_' . $i);
        }

        $execution_time = (microtime(true) - $start_time) * 1000; // Convert to milliseconds

        $this->assertLessThan(500, $execution_time, '1000 cache operations should complete in less than 500ms');
    }

    /**
     * Test content analysis performance
     */
    public function testContentAnalysisPerformance()
    {
        $this->logInfo('Testing Content analysis performance');

        $analyzer = new AANP_ContentAnalyzer();
        $start_time = microtime(true);

        // Perform multiple content analysis operations
        for ($i = 0; $i < 10; $i++) {
            $test_content = array(
                'title' => 'Test ' . $i,
                'content' => '<p>Test content for benchmark ' . $i . '</p>',
                'meta_description' => 'Test meta description ' . $i
            );
            $analyzer->analyze_content($test_content, array('primary_keyword' => 'test'));
        }

        $execution_time = (microtime(true) - $start_time) * 1000; // Convert to milliseconds

        $this->assertLessThan(2000, $execution_time, '10 content analysis operations should complete in less than 2000ms');
    }

    /**
     * Test overall system performance
     */
    public function testOverallSystemPerformance()
    {
        $this->logInfo('Testing Overall system performance');

        $start_time = microtime(true);

        // Test service registry
        $registry = new AANP_ServiceRegistry();
        for ($i = 0; $i < 50; $i++) {
            $registry->register('perf_test_' . $i, 'TestService', array());
        }

        // Test cache manager
        $cache_manager = new AANP_AdvancedCacheManager();
        for ($i = 0; $i < 500; $i++) {
            $cache_manager->set('perf_cache_' . $i, 'cache_value_' . $i, 300);
        }

        // Test content analyzer
        $analyzer = new AANP_ContentAnalyzer();
        for ($i = 0; $i < 5; $i++) {
            $test_content = array(
                'title' => 'Performance Test ' . $i,
                'content' => '<p>Performance test content ' . $i . '</p>',
                'meta_description' => 'Performance test meta description ' . $i
            );
            $analyzer->analyze_content($test_content, array('primary_keyword' => 'performance'));
        }

        $total_execution_time = (microtime(true) - $start_time) * 1000; // Convert to milliseconds

        $this->assertLessThan(3000, $total_execution_time, 'Overall system performance test should complete in less than 3000ms');
    }

    /**
     * Test service orchestration performance
     */
    public function testServiceOrchestrationPerformance()
    {
        $this->logInfo('Testing Service orchestration performance');

        $orchestrator = new AANP_ServiceOrchestrator($this->serviceRegistry);

        // Register test services
        $this->serviceRegistry->register('news_fetch', 'AANP_NewsFetchService', array($this->cacheManager, $this->queueManager));
        $this->serviceRegistry->register('ai_generation', 'AANP_AIGenerationService', array($this->cacheManager, $this->connectionPoolManager));

        $start_time = microtime(true);

        // Execute multiple workflows
        for ($i = 0; $i < 10; $i++) {
            $workflow_config = array(
                'name' => 'perf_test_workflow_' . $i,
                'services' => array('news_fetch', 'ai_generation'),
                'parallel' => false
            );

            $orchestrator->execute_workflow('perf_test_workflow_' . $i, array(), $workflow_config);
        }

        $execution_time = (microtime(true) - $start_time) * 1000; // Convert to milliseconds

        $this->assertLessThan(2000, $execution_time, '10 service orchestration workflows should complete in less than 2000ms');
    }

    /**
     * Test memory usage
     */
    public function testMemoryUsage()
    {
        $this->logInfo('Testing Memory usage');

        $initial_memory = memory_get_usage();

        // Perform memory-intensive operations
        $large_array = array();
        for ($i = 0; $i < 10000; $i++) {
            $large_array[] = array(
                'id' => $i,
                'data' => str_repeat('x', 1000),
                'metadata' => array('key' => 'value')
            );
        }

        $cache_manager = new AANP_AdvancedCacheManager();
        foreach ($large_array as $item) {
            $cache_manager->set('memory_test_' . $item['id'], $item, 300);
        }

        $final_memory = memory_get_usage();
        $memory_used = $final_memory - $initial_memory;

        $this->assertLessThan(50 * 1024 * 1024, $memory_used, 'Memory usage should be less than 50MB for intensive operations');
    }

    /**
     * Test database connection performance
     */
    public function testDatabaseConnectionPerformance()
    {
        $this->logInfo('Testing Database connection performance');

        $pool_manager = new AANP_ConnectionPoolManager();
        $start_time = microtime(true);

        // Get and release multiple database connections
        for ($i = 0; $i < 50; $i++) {
            $connection = $pool_manager->get_database_connection();
            if ($connection) {
                $pool_manager->release_connection($connection);
            }
        }

        $execution_time = (microtime(true) - $start_time) * 1000; // Convert to milliseconds

        $this->assertLessThan(1000, $execution_time, '50 database connection operations should complete in less than 1000ms');
    }

    /**
     * Test HTTP connection performance
     */
    public function testHttpConnectionPerformance()
    {
        $this->logInfo('Testing HTTP connection performance');

        $pool_manager = new AANP_ConnectionPoolManager();
        $start_time = microtime(true);

        // Get and release multiple HTTP connections
        for ($i = 0; $i < 20; $i++) {
            $connection = $pool_manager->get_http_connection('https://test-feed.com');
            if ($connection) {
                $pool_manager->release_http_connection($connection);
            }
        }

        $execution_time = (microtime(true) - $start_time) * 1000; // Convert to milliseconds

        $this->assertLessThan(2000, $execution_time, '20 HTTP connection operations should complete in less than 2000ms');
    }

    /**
     * Test content creation performance
     */
    public function testContentCreationPerformance()
    {
        $this->logInfo('Testing Content creation performance');

        $eeat_optimizer = new AANP_EEATOptimizer();
        $content_service = new AANP_ContentCreationService($this->cacheManager, $eeat_optimizer);

        $start_time = microtime(true);

        // Create multiple posts
        for ($i = 0; $i < 10; $i++) {
            $post_data = array(
                'title' => 'Performance Test Post ' . $i,
                'content' => '<p>This is a performance test post content for post ' . $i . '</p>',
                'status' => 'draft'
            );

            $content_service->create_post($post_data, array());
        }

        $execution_time = (microtime(true) - $start_time) * 1000; // Convert to milliseconds

        $this->assertLessThan(3000, $execution_time, '10 content creation operations should complete in less than 3000ms');
    }

    /**
     * Test performance monitoring
     */
    public function testPerformanceMonitoring()
    {
        $this->logInfo('Testing Performance monitoring');

        $analytics_service = new AANP_AnalyticsService($this->cacheManager, $this->connectionPoolManager);

        // Collect performance data
        $performance_data = array(
            'service_registry_100ops_ms' => 85,
            'cache_1000ops_ms' => 420,
            'content_analysis_10ops_ms' => 1800,
            'total_execution_time_ms' => 2305
        );

        $collection_result = $analytics_service->collect_data($performance_data);

        $this->assertTrue($collection_result, 'Performance data collection should succeed');

        // Get performance metrics
        $metrics = $analytics_service->get_performance_metrics();

        $this->assertIsArray($metrics, 'Performance metrics should return an array');
        $this->assertArrayHasKey('system_performance', $metrics, 'Metrics should include system performance');
    }
}