<?php
/**
 * PHPUnit tests for QueueManager
 *
 * Tests the task queue management functionality including
 * task submission, processing, and worker health monitoring.
 *
 * @package AI_Auto_News_Poster\Tests\Microservices
 */

require_once __DIR__ . '/MicroservicesTestBase.php';

class QueueManagerTest extends MicroservicesTestBase
{
    /**
     * Test task submission
     */
    public function testTaskSubmission()
    {
        $this->logInfo('Testing Task submission');

        $task_id = $this->queueManager->submit_task('test_task', 'Test task data', 'default', 1);
        $this->assertIsString($task_id, 'Task submission should return a string task ID');
        $this->assertNotEmpty($task_id, 'Task ID should not be empty');
    }

    /**
     * Test task processing
     */
    public function testTaskProcessing()
    {
        $this->logInfo('Testing Task processing');

        // Submit a test task
        $task_id = $this->queueManager->submit_task('test_task', 'Test task data', 'default', 1);

        // Process the task
        $task_result = $this->queueManager->process_task($task_id);

        $this->assertIsArray($task_result, 'Task processing should return an array');
        $this->assertArrayHasKey('success', $task_result, 'Result should have success key');
        $this->assertArrayHasKey('result', $task_result, 'Result should have result data');
        $this->assertTrue($task_result['success'], 'Task should process successfully');
    }

    /**
     * Test queue statistics
     */
    public function testQueueStatistics()
    {
        $this->logInfo('Testing Queue statistics');

        // Submit some tasks
        for ($i = 0; $i < 3; $i++) {
            $this->queueManager->submit_task('test_task_' . $i, 'Test data ' . $i, 'default', 1);
        }

        // Get queue statistics
        $stats = $this->queueManager->get_queue_statistics();

        $this->assertIsArray($stats, 'Queue statistics should return an array');
        $this->assertArrayHasKey('total_tasks', $stats, 'Statistics should include total tasks');
        $this->assertArrayHasKey('pending_tasks', $stats, 'Statistics should include pending tasks');
        $this->assertArrayHasKey('processing_tasks', $stats, 'Statistics should include processing tasks');
        $this->assertArrayHasKey('completed_tasks', $stats, 'Statistics should include completed tasks');
    }

    /**
     * Test worker health
     */
    public function testWorkerHealth()
    {
        $this->logInfo('Testing Worker health');

        $worker_health = $this->queueManager->check_worker_health();
        $this->assertIsBool($worker_health, 'Worker health check should return a boolean');
        $this->assertTrue($worker_health, 'Worker should be healthy');
    }

    /**
     * Test task priority
     */
    public function testTaskPriority()
    {
        $this->logInfo('Testing Task priority');

        // Submit tasks with different priorities
        $high_priority_id = $this->queueManager->submit_task('high_priority_task', 'High priority data', 'default', 10);
        $low_priority_id = $this->queueManager->submit_task('low_priority_task', 'Low priority data', 'default', 1);

        // Process tasks and check order
        $high_priority_result = $this->queueManager->process_next_task();
        $low_priority_result = $this->queueManager->process_next_task();

        $this->assertEquals('high_priority_task', $high_priority_result['task_type'], 'High priority task should be processed first');
    }

    /**
     * Test task retry mechanism
     */
    public function testTaskRetry()
    {
        $this->logInfo('Testing Task retry mechanism');

        // Submit a task that will fail
        $task_id = $this->queueManager->submit_task('failing_task', 'Test data', 'default', 1);

        // Process the task (should fail)
        $result = $this->queueManager->process_task($task_id);

        $this->assertFalse($result['success'], 'Failing task should not succeed');

        // Check if task is scheduled for retry
        $stats = $this->queueManager->get_queue_statistics();
        $this->assertGreaterThan(0, $stats['pending_tasks'], 'Failed task should be scheduled for retry');
    }

    /**
     * Test queue persistence
     */
    public function testQueuePersistence()
    {
        $this->logInfo('Testing Queue persistence');

        // Submit tasks
        $task_ids = array();
        for ($i = 0; $i < 3; $i++) {
            $task_ids[] = $this->queueManager->submit_task('persistent_task_' . $i, 'Persistent data ' . $i, 'default', 1);
        }

        // Simulate queue restart by creating a new QueueManager instance
        $new_queue_manager = new AANP_QueueManager();

        // Check if tasks are still in queue
        $stats = $new_queue_manager->get_queue_statistics();
        $this->assertGreaterThanOrEqual(3, $stats['pending_tasks'], 'Persistent tasks should remain in queue after restart');
    }

    /**
     * Test queue cleanup
     */
    public function testQueueCleanup()
    {
        $this->logInfo('Testing Queue cleanup');

        // Submit some tasks
        for ($i = 0; $i < 5; $i++) {
            $this->queueManager->submit_task('cleanup_task_' . $i, 'Cleanup data ' . $i, 'default', 1);
        }

        // Clean up completed tasks
        $this->queueManager->cleanup_completed_tasks();

        // Check queue statistics
        $stats = $this->queueManager->get_queue_statistics();
        $this->assertGreaterThanOrEqual(5, $stats['total_tasks'], 'Queue should still have tasks after cleanup');
    }

    /**
     * Test task timeout
     */
    public function testTaskTimeout()
    {
        $this->logInfo('Testing Task timeout');

        // Submit a long-running task
        $task_id = $this->queueManager->submit_task('long_running_task', 'Long running data', 'default', 1, 1); // 1 second timeout

        // Process the task (should timeout)
        $result = $this->queueManager->process_task($task_id);

        $this->assertArrayHasKey('error', $result, 'Timeout should produce error information');
        $this->assertStringContainsString('timeout', strtolower($result['error']), 'Error should indicate timeout');
    }
}

// Mock failing task for testing
class FailingTask
{
    public function execute($data)
    {
        throw new Exception("Simulated task failure");
    }
}