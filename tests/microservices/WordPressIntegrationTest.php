<?php
/**
 * PHPUnit tests for WordPress Integration
 *
 * Tests the integration of the AI Auto News Poster plugin with WordPress
 * including plugin initialization, service registration, and backward compatibility.
 *
 * @package AI_Auto_News_Poster\Tests\Microservices
 */

require_once __DIR__ . '/MicroservicesTestBase.php';

class WordPressIntegrationTest extends MicroservicesTestBase
{
    /**
     * Test main plugin class initialization
     */
    public function testMainPluginClassInitialization()
    {
        $this->logInfo('Testing Main plugin class initialization');

        $this->assertTrue(class_exists('AI_Auto_News_Poster'), 'Main plugin class should exist');
    }

    /**
     * Test service registry integration with WordPress
     */
    public function testServiceRegistryIntegration()
    {
        $this->logInfo('Testing Service registry integration');

        $registry = new AANP_ServiceRegistry();
        $this->assertInstanceOf('AANP_ServiceRegistry', $registry, 'Service registry should be initialized');

        // Test that WordPress hooks are available
        $this->assertTrue(function_exists('add_action'), 'WordPress add_action function should be available');
        $this->assertTrue(function_exists('add_filter'), 'WordPress add_filter function should be available');
    }

    /**
     * Test backward compatibility hooks
     */
    public function testBackwardCompatibilityHooks()
    {
        $this->logInfo('Testing Backward compatibility hooks');

        // Test that legacy hooks are registered
        $legacy_hook_available = has_action('aanp_fetch_news_legacy') !== false;
        $this->assertTrue($legacy_hook_available, 'Legacy hook aanp_fetch_news_legacy should be available');

        $legacy_ai_hook_available = has_action('aanp_generate_content_legacy') !== false;
        $this->assertTrue($legacy_ai_hook_available, 'Legacy hook aanp_generate_content_legacy should be available');
    }

    /**
     * Test WordPress functions usage
     */
    public function testWordPressFunctionsUsage()
    {
        $this->logInfo('Testing WordPress functions usage');

        // Test that essential WordPress functions are available
        $wp_functions_available = function_exists('wp_insert_post') &&
                                function_exists('wp_verify_nonce') &&
                                function_exists('add_option') &&
                                function_exists('get_option') &&
                                function_exists('wp_update_post');

        $this->assertTrue($wp_functions_available, 'Essential WordPress functions should be available');
    }

    /**
     * Test plugin activation
     */
    public function testPluginActivation()
    {
        $this->logInfo('Testing Plugin activation');

        // Test that activation hooks are registered
        $activation_hook = has_action('activate_contentpilot/contentpilot.php');
        $this->assertNotFalse($activation_hook, 'Plugin activation hook should be registered');
    }

    /**
     * Test plugin deactivation
     */
    public function testPluginDeactivation()
    {
        $this->logInfo('Testing Plugin deactivation');

        // Test that deactivation hooks are registered
        $deactivation_hook = has_action('deactivate_contentpilot/contentpilot.php');
        $this->assertNotFalse($deactivation_hook, 'Plugin deactivation hook should be registered');
    }

    /**
     * Test plugin options
     */
    public function testPluginOptions()
    {
        $this->logInfo('Testing Plugin options');

        // Test that plugin options can be set and retrieved
        $test_option_name = 'aanp_test_option';
        $test_option_value = 'test_value';

        add_option($test_option_name, $test_option_value);
        $retrieved_value = get_option($test_option_name);

        $this->assertEquals($test_option_value, $retrieved_value, 'Plugin options should be set and retrieved correctly');

        // Clean up
        delete_option($test_option_name);
    }

    /**
     * Test user capabilities
     */
    public function testUserCapabilities()
    {
        $this->logInfo('Testing User capabilities');

        // Test that current user has required capabilities
        $current_user = wp_get_current_user();
        $this->assertNotEmpty($current_user->ID, 'Current user should be available');

        // Test that user can manage options (typical admin capability)
        $can_manage_options = current_user_can('manage_options');
        $this->assertTrue($can_manage_options, 'Current user should be able to manage options');
    }

    /**
     * Test plugin settings page
     */
    public function testPluginSettingsPage()
    {
        $this->logInfo('Testing Plugin settings page');

        // Test that settings page is registered
        $settings_page = has_action('admin_menu', array('AANP_Admin_Settings', 'add_settings_page'));
        $this->assertNotFalse($settings_page, 'Plugin settings page should be registered');
    }

    /**
     * Test plugin hooks and filters
     */
    public function testPluginHooksAndFilters()
    {
        $this->logInfo('Testing Plugin hooks and filters');

        // Test that essential hooks are registered
        $content_filter = has_filter('aanp_content_filter');
        $this->assertNotFalse($content_filter, 'Content filter should be registered');

        $post_creation_hook = has_action('aanp_post_created');
        $this->assertNotFalse($post_creation_hook, 'Post creation hook should be registered');
    }

    /**
     * Test plugin version compatibility
     */
    public function testPluginVersionCompatibility()
    {
        $this->logInfo('Testing Plugin version compatibility');

        // Test that plugin version constant is defined
        $this->assertTrue(defined('AANP_VERSION'), 'Plugin version constant should be defined');

        // Test that WordPress version is compatible
        global $wp_version;
        $this->assertNotEmpty($wp_version, 'WordPress version should be available');

        // Basic version compatibility check (WordPress 5.0+)
        $this->assertGreaterThanOrEqual('5.0', $wp_version, 'WordPress version should be 5.0 or higher');
    }

    /**
     * Test plugin performance in WordPress environment
     */
    public function testPluginPerformance()
    {
        $this->logInfo('Testing Plugin performance in WordPress environment');

        $start_time = microtime(true);

        // Test plugin initialization performance
        $plugin = new AI_Auto_News_Poster();

        $initialization_time = microtime(true) - $start_time;
        $this->assertLessThan(1.0, $initialization_time, 'Plugin initialization should complete in less than 1 second');
    }
}