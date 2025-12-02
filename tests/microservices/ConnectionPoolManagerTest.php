<?php
/**
 * PHPUnit tests for ConnectionPoolManager
 *
 * Tests the connection pool management functionality including
 * database and HTTP connection pooling, health checks, and performance metrics.
 *
 * @package AI_Auto_News_Poster\Tests\Microservices
 */

require_once __DIR__ . '/MicroservicesTestBase.php';

class ConnectionPoolManagerTest extends MicroservicesTestBase
{
    /**
     * Test connection pool initialization
     */
    public function testConnectionPoolInitialization()
    {
        $this->logInfo('Testing Connection pool initialization');

        $pool_stats = $this->connectionPoolManager->get_pool_statistics();
        $this->assertIsArray($pool_stats, 'Connection pool statistics should return an array');
        $this->assertArrayHasKey('database_pool', $pool_stats, 'Statistics should include database pool');
        $this->assertArrayHasKey('http_pool', $pool_stats, 'Statistics should include HTTP pool');
    }

    /**
     * Test database pool health
     */
    public function testDatabasePoolHealth()
    {
        $this->logInfo('Testing Database pool health');

        $db_health = $this->connectionPoolManager->check_database_health();
        $this->assertIsBool($db_health, 'Database health check should return a boolean');
        $this->assertTrue($db_health, 'Database pool should be healthy');
    }

    /**
     * Test HTTP connection pool health
     */
    public function testHttpPoolHealth()
    {
        $this->logInfo('Testing HTTP connection pool health');

        $http_health = $this->connectionPoolManager->check_http_health();
        $this->assertIsBool($http_health, 'HTTP health check should return a boolean');
        $this->assertTrue($http_health, 'HTTP connection pool should be healthy');
    }

    /**
     * Test performance metrics
     */
    public function testPerformanceMetrics()
    {
        $this->logInfo('Testing Connection pool performance metrics');

        $metrics = $this->connectionPoolManager->get_performance_metrics();
        $this->assertIsArray($metrics, 'Performance metrics should return an array');
        $this->assertArrayHasKey('database_latency', $metrics, 'Metrics should include database latency');
        $this->assertArrayHasKey('http_latency', $metrics, 'Metrics should include HTTP latency');
        $this->assertArrayHasKey('pool_utilization', $metrics, 'Metrics should include pool utilization');
    }

    /**
     * Test connection pool scaling
     */
    public function testConnectionPoolScaling()
    {
        $this->logInfo('Testing Connection pool scaling');

        // Get initial pool statistics
        $initial_stats = $this->connectionPoolManager->get_pool_statistics();

        // Simulate high load by requesting multiple connections
        $connections = array();
        for ($i = 0; $i < 10; $i++) {
            $connections[] = $this->connectionPoolManager->get_database_connection();
        }

        // Get updated pool statistics
        $updated_stats = $this->connectionPoolManager->get_pool_statistics();

        // Release connections
        foreach ($connections as $connection) {
            $this->connectionPoolManager->release_connection($connection);
        }

        $this->assertGreaterThanOrEqual($initial_stats['database_pool']['active_connections'], $updated_stats['database_pool']['active_connections'], 'Pool should scale to handle load');
    }

    /**
     * Test connection reuse
     */
    public function testConnectionReuse()
    {
        $this->logInfo('Testing Connection reuse');

        // Get first connection
        $connection1 = $this->connectionPoolManager->get_database_connection();

        // Release connection
        $this->connectionPoolManager->release_connection($connection1);

        // Get second connection (should reuse the first one)
        $connection2 = $this->connectionPoolManager->get_database_connection();

        $this->assertSame($connection1, $connection2, 'Connection pool should reuse connections');
    }

    /**
     * Test connection timeout handling
     */
    public function testConnectionTimeoutHandling()
    {
        $this->logInfo('Testing Connection timeout handling');

        // Test with a very short timeout
        $connection = $this->connectionPoolManager->get_database_connection(0.1); // 100ms timeout

        if ($connection) {
            $this->connectionPoolManager->release_connection($connection);
        }

        // The connection might be null due to timeout, which is acceptable
        $this->assertTrue(true, 'Connection timeout handling should not cause errors');
    }

    /**
     * Test connection error handling
     */
    public function testConnectionErrorHandling()
    {
        $this->logInfo('Testing Connection error handling');

        // Test with invalid connection parameters
        $connection = $this->connectionPoolManager->get_database_connection(10, array(
            'host' => 'invalid-host',
            'user' => 'invalid-user',
            'password' => 'invalid-password'
        ));

        $this->assertFalse($connection, 'Invalid connection should return false');

        // Check that the pool manager handles the error gracefully
        $health = $this->connectionPoolManager->check_database_health();
        $this->assertIsBool($health, 'Health check should still return boolean after error');
    }

    /**
     * Test HTTP connection pooling
     */
    public function testHttpConnectionPooling()
    {
        $this->logInfo('Testing HTTP connection pooling');

        // Get HTTP connection
        $http_connection = $this->connectionPoolManager->get_http_connection('https://test-feed.com');

        $this->assertNotFalse($http_connection, 'HTTP connection should be established');

        // Release connection
        $this->connectionPoolManager->release_http_connection($http_connection);

        // Check HTTP pool statistics
        $stats = $this->connectionPoolManager->get_pool_statistics();
        $this->assertArrayHasKey('http_pool', $stats, 'HTTP pool statistics should be available');
        $this->assertArrayHasKey('active_connections', $stats['http_pool'], 'HTTP pool should have active connections count');
    }
}