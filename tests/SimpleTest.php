<?php
/**
 * Simple test to validate PHPUnit setup
 *
 * @package AI_Auto_News_Poster\Tests
 */

use PHPUnit\Framework\TestCase;

class SimpleTest extends TestCase
{
    public function testBasicAssertions()
    {
        $this->assertTrue(true, 'True should be true');
        $this->assertEquals(1, 1, '1 should equal 1');
        $this->assertFalse(false, 'False should be false');
    }

    public function testClassLoading()
    {
        $this->assertTrue(class_exists('AANP_ServiceRegistry'), 'AANP_ServiceRegistry class should exist');
        $this->assertTrue(class_exists('AANP_AdvancedCacheManager'), 'AANP_AdvancedCacheManager class should exist');
    }

    public function testServiceRegistry()
    {
        $registry = new AANP_ServiceRegistry();
        $this->assertInstanceOf('AANP_ServiceRegistry', $registry, 'ServiceRegistry should be instantiated');

        $registry->register('test_service', 'TestService', array());
        $service = $registry->get('test_service');
        $this->assertInstanceOf('TestService', $service, 'Service should be retrievable');
    }
}