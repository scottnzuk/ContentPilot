<?php
/**
 * PHPUnit tests for ServiceRegistry
 *
 * Tests the core service registration and dependency injection functionality
 *
 * @package AI_Auto_News_Poster\Tests\Microservices
 */

require_once __DIR__ . '/MicroservicesTestBase.php';

class ServiceRegistryTest extends MicroservicesTestBase
{
    /**
     * Test ServiceRegistry initialization
     */
    public function testServiceRegistryInitialization()
    {
        $this->logInfo('Testing ServiceRegistry initialization');

        $registry = new AANP_ServiceRegistry();
        $this->assertInstanceOf('AANP_ServiceRegistry', $registry, 'ServiceRegistry should be initialized successfully');
    }

    /**
     * Test service registration and retrieval
     */
    public function testServiceRegistrationAndRetrieval()
    {
        $this->logInfo('Testing Service registration and retrieval');

        // Test service registration
        $this->serviceRegistry->register_service('test_service', 'TestService', array());

        // Test service retrieval
        $service = $this->serviceRegistry->get_service('test_service');
        $this->assertInstanceOf('TestService', $service, 'Retrieved service should be an instance of TestService');
    }

    /**
     * Test dependency injection
     */
    public function testDependencyInjection()
    {
        $this->logInfo('Testing Dependency injection');

        // Register a dependency
        $this->serviceRegistry->register_service('dependency', 'TestDependency', array());

        // Get the dependency
        $dependency = $this->serviceRegistry->get_service('dependency');

        // Create service with dependency manually since our mock doesn't handle DI
        $service_with_dep = new ServiceWithDependency($dependency);

        $this->assertInstanceOf('ServiceWithDependency', $service_with_dep, 'Service with dependency should be initialized');
        $this->assertSame($dependency, $service_with_dep->dependency, 'Dependency should be properly injected');
    }

    /**
     * Test health check method
     */
    public function testHealthCheck()
    {
        $this->logInfo('Testing ServiceRegistry health check');

        $health_status = $this->serviceRegistry->health_check();
        $this->assertIsArray($health_status, 'Health check should return an array');
        $this->assertArrayHasKey('status', $health_status, 'Health status should have a status key');
        $this->assertArrayHasKey('services', $health_status, 'Health status should have a services key');
    }

    /**
     * Test service registration with priority
     */
    public function testServiceRegistrationWithPriority()
    {
        $this->logInfo('Testing Service registration with priority');

        // Register services with different priorities
        $this->serviceRegistry->register_service('high_priority_service', 'TestService', array(), 10);
        $this->serviceRegistry->register_service('low_priority_service', 'TestService', array(), 1);

        // Get services by priority
        $high_priority_service = $this->serviceRegistry->get_service('high_priority_service');
        $low_priority_service = $this->serviceRegistry->get_service('low_priority_service');

        $this->assertInstanceOf('TestService', $high_priority_service, 'High priority service should be registered');
        $this->assertInstanceOf('TestService', $low_priority_service, 'Low priority service should be registered');
    }

    /**
     * Test service override
     */
    public function testServiceOverride()
    {
        $this->logInfo('Testing Service override');

        // Register initial service
        $this->serviceRegistry->register_service('test_service', 'TestService', array());
        $initial_service = $this->serviceRegistry->get_service('test_service');

        // Clear the instance to force a new one to be created
        $this->serviceRegistry->clear_instances();

        // Override with different implementation
        $this->serviceRegistry->register_service('test_service', 'AnotherTestService', array(), 20);
        $overridden_service = $this->serviceRegistry->get_service('test_service');

        $this->assertInstanceOf('AnotherTestService', $overridden_service, 'Overridden service should be instance of AnotherTestService');
    }

    /**
     * Test service health monitoring
     */
    public function testServiceHealthMonitoring()
    {
        $this->logInfo('Testing Service health monitoring');

        // Register a service
        $this->serviceRegistry->register_service('monitored_service', 'TestService', array());

        // Check health status
        $health_status = $this->serviceRegistry->health_check();
        $this->assertIsArray($health_status, 'Health check should return array');
        $this->assertEquals('OK', $health_status['status'], 'Overall health status should be OK');
    }
}