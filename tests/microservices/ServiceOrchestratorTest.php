<?php
/**
 * PHPUnit tests for ServiceOrchestrator
 *
 * Tests the service orchestration and workflow execution functionality
 *
 * @package AI_Auto_News_Poster\Tests\Microservices
 */

require_once __DIR__ . '/MicroservicesTestBase.php';

class ServiceOrchestratorTest extends MicroservicesTestBase
{
    /**
     * Test ServiceOrchestrator initialization
     */
    public function testServiceOrchestratorInitialization()
    {
        $this->logInfo('Testing ServiceOrchestrator initialization');

        $orchestrator = new AANP_ServiceOrchestrator($this->serviceRegistry);
        $this->assertInstanceOf('AANP_ServiceOrchestrator', $orchestrator, 'ServiceOrchestrator should be initialized successfully');
    }

    /**
     * Test workflow execution
     */
    public function testWorkflowExecution()
    {
        $this->logInfo('Testing Workflow execution');

        // Register test services
        $this->serviceRegistry->register('news_fetch', 'AANP_NewsFetchService', array($this->cacheManager, $this->queueManager));
        $this->serviceRegistry->register('ai_generation', 'AANP_AIGenerationService', array($this->cacheManager, $this->connectionPoolManager));

        // Define workflow configuration
        $workflow_config = array(
            'name' => 'test_workflow',
            'services' => array('news_fetch', 'ai_generation'),
            'parallel' => false
        );

        // Execute workflow
        $result = $this->serviceOrchestrator->execute_workflow('test_workflow', array(), $workflow_config);

        $this->assertIsArray($result, 'Workflow execution should return an array');
        $this->assertArrayHasKey('success', $result, 'Result should have success key');
        $this->assertArrayHasKey('results', $result, 'Result should have results key');
        $this->assertTrue($result['success'], 'Workflow should execute successfully');
    }

    /**
     * Test parallel processing
     */
    public function testParallelProcessing()
    {
        $this->logInfo('Testing Parallel processing');

        // Register test services
        $this->serviceRegistry->register('news_fetch', 'AANP_NewsFetchService', array($this->cacheManager, $this->queueManager));
        $this->serviceRegistry->register('ai_generation', 'AANP_AIGenerationService', array($this->cacheManager, $this->connectionPoolManager));

        // Define parallel tasks
        $parallel_tasks = array(
            'task1' => array('service' => 'news_fetch', 'params' => array()),
            'task2' => array('service' => 'ai_generation', 'params' => array())
        );

        // Execute parallel tasks
        $parallel_result = $this->serviceOrchestrator->execute_parallel($parallel_tasks);

        $this->assertIsArray($parallel_result, 'Parallel processing should return an array');
        $this->assertArrayHasKey('task1', $parallel_result, 'Result should have task1 key');
        $this->assertArrayHasKey('task2', $parallel_result, 'Result should have task2 key');
        $this->assertTrue($parallel_result['task1']['success'], 'Task 1 should execute successfully');
        $this->assertTrue($parallel_result['task2']['success'], 'Task 2 should execute successfully');
    }

    /**
     * Test workflow with dependencies
     */
    public function testWorkflowWithDependencies()
    {
        $this->logInfo('Testing Workflow with dependencies');

        // Register services with dependencies
        $this->serviceRegistry->register('cache_manager', 'AANP_AdvancedCacheManager', array());
        $this->serviceRegistry->register('content_creation', 'AANP_ContentCreationService', array(
            'cache_manager' => $this->cacheManager,
            'eeat_optimizer' => new AANP_EEATOptimizer()
        ));

        // Define workflow with dependencies
        $workflow_config = array(
            'name' => 'content_creation_workflow',
            'services' => array('cache_manager', 'content_creation'),
            'parallel' => false,
            'dependencies' => array(
                'content_creation' => array('cache_manager')
            )
        );

        // Execute workflow
        $result = $this->serviceOrchestrator->execute_workflow('content_creation_workflow', array(), $workflow_config);

        $this->assertIsArray($result, 'Workflow with dependencies should return an array');
        $this->assertTrue($result['success'], 'Workflow with dependencies should execute successfully');
    }

    /**
     * Test error handling in workflows
     */
    public function testWorkflowErrorHandling()
    {
        $this->logInfo('Testing Workflow error handling');

        // Register a service that will fail
        $this->serviceRegistry->register('failing_service', 'FailingService', array());

        // Define workflow with failing service
        $workflow_config = array(
            'name' => 'failing_workflow',
            'services' => array('failing_service'),
            'parallel' => false
        );

        // Execute workflow
        $result = $this->serviceOrchestrator->execute_workflow('failing_workflow', array(), $workflow_config);

        $this->assertIsArray($result, 'Failing workflow should return an array');
        $this->assertFalse($result['success'], 'Failing workflow should not succeed');
        $this->assertArrayHasKey('error', $result, 'Result should have error information');
    }

    /**
     * Test performance monitoring
     */
    public function testPerformanceMonitoring()
    {
        $this->logInfo('Testing Performance monitoring');

        // Register test service
        $this->serviceRegistry->register('news_fetch', 'AANP_NewsFetchService', array($this->cacheManager, $this->queueManager));

        // Execute workflow
        $workflow_config = array(
            'name' => 'performance_test_workflow',
            'services' => array('news_fetch'),
            'parallel' => false
        );

        $result = $this->serviceOrchestrator->execute_workflow('performance_test_workflow', array(), $workflow_config);

        $this->assertIsArray($result, 'Performance test should return an array');
        $this->assertArrayHasKey('performance', $result, 'Result should have performance data');
        $this->assertArrayHasKey('execution_time', $result['performance'], 'Performance data should include execution time');
        $this->assertArrayHasKey('memory_usage', $result['performance'], 'Performance data should include memory usage');
    }
}

// Mock failing service for testing
class FailingService
{
    public function execute($params = array())
    {
        throw new Exception("Simulated service failure");
    }
}