<?php
/**
 * Main test suite for Microservices
 *
 * Organizes all microservices tests into a single test suite
 * for easy execution and reporting.
 *
 * @package AI_Auto_News_Poster\Tests\Microservices
 */

use PHPUnit\Framework\TestSuite;

class MicroservicesTestSuite extends TestSuite
{
    /**
     * Create the test suite
     *
     * @return MicroservicesTestSuite
     */
    public static function suite()
    {
        $suite = new self('Microservices Test Suite');

        // Add core service tests
        $suite->addTestSuite('ServiceRegistryTest');
        $suite->addTestSuite('ServiceOrchestratorTest');

        // Add performance service tests
        $suite->addTestSuite('AdvancedCacheManagerTest');
        $suite->addTestSuite('ConnectionPoolManagerTest');
        $suite->addTestSuite('QueueManagerTest');

        // Add SEO service tests
        $suite->addTestSuite('ContentAnalyzerTest');
        $suite->addTestSuite('EEATOptimizerTest');

        // Add business service tests
        $suite->addTestSuite('NewsFetchServiceTest');
        $suite->addTestSuite('AIGenerationServiceTest');
        $suite->addTestSuite('ContentCreationServiceTest');
        $suite->addTestSuite('AnalyticsServiceTest');

        // Add integration tests
        $suite->addTestSuite('WordPressIntegrationTest');

        // Add performance benchmarks
        $suite->addTestSuite('PerformanceBenchmarksTest');

        return $suite;
    }
}