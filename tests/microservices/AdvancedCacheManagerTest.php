<?php
/**
 * PHPUnit tests for AdvancedCacheManager
 *
 * Tests the advanced caching functionality including cache operations,
 * statistics, and health monitoring.
 *
 * @package AI_Auto_News_Poster\Tests\Microservices
 */

require_once __DIR__ . '/MicroservicesTestBase.php';

class AdvancedCacheManagerTest extends MicroservicesTestBase
{
    /**
     * Test basic caching operations
     */
    public function testBasicCacheOperations()
    {
        $this->logInfo('Testing Basic cache operations');

        // Test cache set and get
        $this->cacheManager->set('test_key', 'test_value', 300);
        $cached_value = $this->cacheManager->get('test_key');

        $this->assertEquals('test_value', $cached_value, 'Cached value should match the set value');

        // Test cache expiration
        $this->cacheManager->set('expiring_key', 'expiring_value', 1); // 1 second expiration
        sleep(2); // Wait for expiration
        $expired_value = $this->cacheManager->get('expiring_key');

        $this->assertFalse($expired_value, 'Expired cache value should return false');
    }

    /**
     * Test cache statistics
     */
    public function testCacheStatistics()
    {
        $this->logInfo('Testing Cache statistics');

        // Set some test values
        $this->cacheManager->set('stats_key_1', 'value_1', 300);
        $this->cacheManager->set('stats_key_2', 'value_2', 300);

        // Get cache statistics
        $stats = $this->cacheManager->get_cache_statistics();

        $this->assertIsArray($stats, 'Cache statistics should return an array');
        $this->assertArrayHasKey('total_items', $stats, 'Statistics should include total items');
        $this->assertArrayHasKey('hit_rate', $stats, 'Statistics should include hit rate');
        $this->assertArrayHasKey('memory_usage', $stats, 'Statistics should include memory usage');
    }

    /**
     * Test cache invalidation
     */
    public function testCacheInvalidation()
    {
        $this->logInfo('Testing Cache invalidation');

        // Set test value
        $this->cacheManager->set('delete_key', 'delete_value', 300);
        $this->assertEquals('delete_value', $this->cacheManager->get('delete_key'), 'Value should be cached');

        // Delete the value
        $this->cacheManager->delete('delete_key');
        $deleted_value = $this->cacheManager->get('delete_key');

        $this->assertFalse($deleted_value, 'Deleted cache value should return false');
    }

    /**
     * Test cache health check
     */
    public function testCacheHealthCheck()
    {
        $this->logInfo('Testing Cache health check');

        $health = $this->cacheManager->health_check();
        $this->assertIsBool($health, 'Health check should return a boolean');
        $this->assertTrue($health, 'Cache should be healthy');
    }

    /**
     * Test cache batch operations
     */
    public function testCacheBatchOperations()
    {
        $this->logInfo('Testing Cache batch operations');

        // Set multiple values
        $batch_data = array(
            'batch_key_1' => 'batch_value_1',
            'batch_key_2' => 'batch_value_2',
            'batch_key_3' => 'batch_value_3'
        );

        foreach ($batch_data as $key => $value) {
            $this->cacheManager->set($key, $value, 300);
        }

        // Get multiple values
        $retrieved_values = array();
        foreach (array_keys($batch_data) as $key) {
            $retrieved_values[$key] = $this->cacheManager->get($key);
        }

        $this->assertEquals($batch_data, $retrieved_values, 'Batch retrieved values should match batch set values');
    }

    /**
     * Test cache performance
     */
    public function testCachePerformance()
    {
        $this->logInfo('Testing Cache performance');

        $start_time = microtime(true);

        // Perform multiple cache operations
        for ($i = 0; $i < 100; $i++) {
            $this->cacheManager->set('perf_key_' . $i, 'perf_value_' . $i, 300);
            $this->cacheManager->get('perf_key_' . $i);
        }

        $execution_time = microtime(true) - $start_time;
        $this->assertLessThan(1.0, $execution_time, '100 cache operations should complete in less than 1 second');
    }

    /**
     * Test cache memory management
     */
    public function testCacheMemoryManagement()
    {
        $this->logInfo('Testing Cache memory management');

        // Set large value to test memory management
        $large_value = str_repeat('x', 1024 * 1024); // 1MB value
        $this->cacheManager->set('large_value', $large_value, 300);

        $retrieved_large_value = $this->cacheManager->get('large_value');
        $this->assertEquals($large_value, $retrieved_large_value, 'Large value should be cached and retrieved correctly');

        // Check memory usage
        $stats = $this->cacheManager->get_cache_statistics();
        $this->assertGreaterThan(1024 * 1024, $stats['memory_usage'], 'Memory usage should reflect large value storage');
    }

    /**
     * Test cache namespace functionality
     */
    public function testCacheNamespaces()
    {
        $this->logInfo('Testing Cache namespaces');

        // Set values in different namespaces
        $this->cacheManager->set('namespace_key', 'namespace_value_1', 300, 'namespace1');
        $this->cacheManager->set('namespace_key', 'namespace_value_2', 300, 'namespace2');

        // Retrieve values from different namespaces
        $value1 = $this->cacheManager->get('namespace_key', 'namespace1');
        $value2 = $this->cacheManager->get('namespace_key', 'namespace2');

        $this->assertEquals('namespace_value_1', $value1, 'Value from namespace1 should be correct');
        $this->assertEquals('namespace_value_2', $value2, 'Value from namespace2 should be correct');
        $this->assertNotEquals($value1, $value2, 'Values from different namespaces should be different');
    }
}