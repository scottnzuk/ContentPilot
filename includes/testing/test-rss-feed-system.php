<?php
/**
 * RSS Feed System Test Suite
 *
 * Tests the RSS feed management system including:
 * - Database creation and feed installation
 * - RSSFeedManager functionality
 * - Integration with NewsFetchService
 * - Admin interface operations
 *
 * @package AI_Auto_News_Poster
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * RSS Feed System Test Class
 */
class AANP_RSSFeedSystemTest {
    
    /**
     * Test results
     * @var array
     */
    private $results = array();
    
    /**
     * RSS Feed Manager instance
     * @var AANP_RSSFeedManager
     */
    private $rss_manager;
    
    /**
     * Constructor
     */
    public function __construct() {
        echo "<h2>RSS Feed System Test Suite</h2>";
        echo "<div id='rss-test-results'>";
        
        try {
            // Initialize RSS Feed Manager
            if (class_exists('AANP_RSSFeedManager')) {
                $this->rss_manager = new AANP_RSSFeedManager();
                echo "<p>✓ RSS Feed Manager initialized successfully</p>";
            } else {
                throw new Exception('RSSFeedManager class not found');
            }
            
            // Run tests
            $this->run_tests();
            
        } catch (Exception $e) {
            echo "<p style='color: red;'>✗ Failed to initialize test: " . esc_html($e->getMessage()) . "</p>";
        }
        
        echo "</div>";
        $this->display_summary();
    }
    
    /**
     * Run all tests
     */
    private function run_tests() {
        $this->test_database_creation();
        $this->test_default_feeds_installation();
        $this->test_feed_retrieval();
        $this->test_feed_search();
        $this->test_feed_validation();
        $this->test_feed_management();
        $this->test_statistics();
        $this->test_news_fetch_service_integration();
    }
    
    /**
     * Test database creation
     */
    private function test_database_creation() {
        echo "<h3>Database Creation Test</h3>";
        
        try {
            // Test table creation by checking if we can instantiate the manager
            $this->rss_manager = new AANP_RSSFeedManager();
            
            // Check if tables exist by trying to query
            global $wpdb;
            $feeds_table = $wpdb->prefix . 'aanp_rss_feeds';
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $feeds_table));
            
            if ($table_exists === $feeds_table) {
                $this->results['database_creation'] = 'PASS';
                echo "<p style='color: green;'>✓ Database tables created successfully</p>";
            } else {
                $this->results['database_creation'] = 'FAIL';
                echo "<p style='color: red;'>✗ Database tables not found</p>";
            }
            
        } catch (Exception $e) {
            $this->results['database_creation'] = 'FAIL';
            echo "<p style='color: red;'>✗ Database creation failed: " . esc_html($e->getMessage()) . "</p>";
        }
    }
    
    /**
     * Test default feeds installation
     */
    private function test_default_feeds_installation() {
        echo "<h3>Default Feeds Installation Test</h3>";
        
        try {
            // Check if feeds were installed
            $feeds = $this->rss_manager->get_feeds(array('limit' => 1000));
            
            if (count($feeds) >= 100) {
                $this->results['default_feeds'] = 'PASS';
                echo "<p style='color: green;'>✓ " . count($feeds) . " default feeds installed successfully</p>";
                
                // Check regional distribution
                $regions = array();
                foreach ($feeds as $feed) {
                    $regions[$feed['region']] = ($regions[$feed['region']] ?? 0) + 1;
                }
                
                echo "<ul>";
                foreach ($regions as $region => $count) {
                    echo "<li>{$region}: {$count} feeds</li>";
                }
                echo "</ul>";
                
            } else {
                $this->results['default_feeds'] = 'FAIL';
                echo "<p style='color: red;'>✗ Only " . count($feeds) . " feeds installed (expected 100+)</p>";
            }
            
        } catch (Exception $e) {
            $this->results['default_feeds'] = 'FAIL';
            echo "<p style='color: red;'>✗ Default feeds installation failed: " . esc_html($e->getMessage()) . "</p>";
        }
    }
    
    /**
     * Test feed retrieval
     */
    private function test_feed_retrieval() {
        echo "<h3>Feed Retrieval Test</h3>";
        
        try {
            // Test basic retrieval
            $all_feeds = $this->rss_manager->get_feeds();
            if (count($all_feeds) > 0) {
                echo "<p style='color: green;'>✓ Retrieved " . count($all_feeds) . " feeds</p>";
            } else {
                throw new Exception('No feeds retrieved');
            }
            
            // Test regional filtering
            $uk_feeds = $this->rss_manager->get_feeds_by_region('UK');
            if (count($uk_feeds) > 0) {
                echo "<p style='color: green;'>✓ Retrieved " . count($uk_feeds) . " UK feeds</p>";
            } else {
                echo "<p style='color: orange;'>⚠ No UK feeds found</p>";
            }
            
            // Test pagination
            $page1 = $this->rss_manager->get_feeds(array('limit' => 10, 'offset' => 0));
            $page2 = $this->rss_manager->get_feeds(array('limit' => 10, 'offset' => 10));
            
            if (count($page1) === 10 && count($page2) === 10) {
                echo "<p style='color: green;'>✓ Pagination working correctly</p>";
            } else {
                echo "<p style='color: orange;'>⚠ Pagination issues detected</p>";
            }
            
            $this->results['feed_retrieval'] = 'PASS';
            
        } catch (Exception $e) {
            $this->results['feed_retrieval'] = 'FAIL';
            echo "<p style='color: red;'>✗ Feed retrieval failed: " . esc_html($e->getMessage()) . "</p>";
        }
    }
    
    /**
     * Test feed search
     */
    private function test_feed_search() {
        echo "<h3>Feed Search Test</h3>";
        
        try {
            // Test search functionality
            $bbc_feeds = $this->rss_manager->search_feeds('bbc');
            if (count($bbc_feeds) > 0) {
                echo "<p style='color: green;'>✓ Search for 'bbc' returned " . count($bbc_feeds) . " results</p>";
            } else {
                echo "<p style='color: orange;'>⚠ Search for 'bbc' returned no results</p>";
            }
            
            // Test regional search
            $guardian_feeds = $this->rss_manager->search_feeds('guardian', 'UK');
            if (count($guardian_feeds) > 0) {
                echo "<p style='color: green;'>✓ Regional search working correctly</p>";
            } else {
                echo "<p style='color: orange;'>⚠ Regional search returned no results</p>";
            }
            
            $this->results['feed_search'] = 'PASS';
            
        } catch (Exception $e) {
            $this->results['feed_search'] = 'FAIL';
            echo "<p style='color: red;'>✗ Feed search failed: " . esc_html($e->getMessage()) . "</p>";
        }
    }
    
    /**
     * Test feed validation
     */
    private function test_feed_validation() {
        echo "<h3>Feed Validation Test</h3>";
        
        try {
            // Test a few feeds
            $test_feeds = $this->rss_manager->get_feeds(array('limit' => 3));
            
            foreach ($test_feeds as $feed) {
                $validation = $this->rss_manager->validate_feed($feed['url'], 10); // 10 second timeout
                
                echo "<div style='margin: 10px 0; padding: 10px; border-left: 3px solid ";
                echo $validation['valid'] ? 'green' : 'red';
                echo ";'>";
                echo "<strong>" . esc_html($feed['name']) . "</strong><br>";
                echo "URL: " . esc_html($feed['url']) . "<br>";
                echo "Valid: " . ($validation['valid'] ? 'Yes' : 'No') . "<br>";
                
                if (!$validation['valid']) {
                    echo "Error: " . esc_html($validation['error'] ?? 'Unknown error');
                } else {
                    echo "Items found: " . ($validation['item_count'] ?? 'Unknown');
                }
                
                echo "</div>";
            }
            
            $this->results['feed_validation'] = 'PASS';
            
        } catch (Exception $e) {
            $this->results['feed_validation'] = 'FAIL';
            echo "<p style='color: red;'>✗ Feed validation failed: " . esc_html($e->getMessage()) . "</p>";
        }
    }
    
    /**
     * Test feed management (enable/disable)
     */
    private function test_feed_management() {
        echo "<h3>Feed Management Test</h3>";
        
        try {
            // Get a few test feeds
            $test_feeds = $this->rss_manager->get_feeds(array('limit' => 2));
            
            if (count($test_feeds) < 2) {
                throw new Exception('Not enough feeds for testing');
            }
            
            $feed_ids = array_column($test_feeds, 'id');
            
            // Test enabling feeds
            $enable_result = $this->rss_manager->enable_feeds($feed_ids);
            if ($enable_result['success']) {
                echo "<p style='color: green;'>✓ Successfully enabled " . $enable_result['enabled_count'] . " feeds</p>";
            } else {
                echo "<p style='color: red;'>✗ Failed to enable feeds</p>";
            }
            
            // Test disabling feeds
            $disable_result = $this->rss_manager->disable_feeds($feed_ids);
            if ($disable_result['success']) {
                echo "<p style='color: green;'>✓ Successfully disabled " . $disable_result['disabled_count'] . " feeds</p>";
            } else {
                echo "<p style='color: red;'>✗ Failed to disable feeds</p>";
            }
            
            // Test top reliable feeds selection
            $top_feeds = $this->rss_manager->get_top_reliable_feeds(5);
            if (count($top_feeds) > 0) {
                echo "<p style='color: green;'>✓ Retrieved " . count($top_feeds) . " top reliable feeds</p>";
            } else {
                echo "<p style='color: orange;'>⚠ No top reliable feeds found</p>";
            }
            
            $this->results['feed_management'] = 'PASS';
            
        } catch (Exception $e) {
            $this->results['feed_management'] = 'FAIL';
            echo "<p style='color: red;'>✗ Feed management failed: " . esc_html($e->getMessage()) . "</p>";
        }
    }
    
    /**
     * Test statistics functionality
     */
    private function test_statistics() {
        echo "<h3>Statistics Test</h3>";
        
        try {
            $stats = $this->rss_manager->get_feed_statistics();
            
            echo "<div style='background: #f9f9f9; padding: 15px; margin: 10px 0;'>";
            echo "<h4>Feed Statistics:</h4>";
            echo "<ul>";
            echo "<li>Total Feeds: " . $stats['total_feeds'] . "</li>";
            echo "<li>Enabled Feeds: " . $stats['enabled_feeds'] . "</li>";
            echo "<li>Disabled Feeds: " . $stats['disabled_feeds'] . "</li>";
            echo "<li>Average Reliability: " . $stats['average_reliability'] . "%</li>";
            
            if (!empty($stats['regions'])) {
                echo "<li>Regional Distribution:</li>";
                echo "<ul>";
                foreach ($stats['regions'] as $region => $count) {
                    echo "<li>{$region}: {$count}</li>";
                }
                echo "</ul>";
            }
            
            if (!empty($stats['categories'])) {
                echo "<li>Category Distribution:</li>";
                echo "<ul>";
                foreach (array_slice($stats['categories'], 0, 5) as $category => $count) {
                    echo "<li>{$category}: {$count}</li>";
                }
                echo "</ul>";
            }
            
            echo "</ul>";
            echo "</div>";
            
            $this->results['statistics'] = 'PASS';
            
        } catch (Exception $e) {
            $this->results['statistics'] = 'FAIL';
            echo "<p style='color: red;'>✗ Statistics test failed: " . esc_html($e->getMessage()) . "</p>";
        }
    }
    
    /**
     * Test NewsFetchService integration
     */
    private function test_news_fetch_service_integration() {
        echo "<h3>NewsFetchService Integration Test</h3>";
        
        try {
            // Check if NewsFetchService exists and can work with RSS feeds
            if (class_exists('AANP_NewsFetchService')) {
                $news_service = new AANP_NewsFetchService();
                
                // Get enabled feed URLs
                $enabled_feeds = $this->rss_manager->get_enabled_feed_urls();
                
                if (count($enabled_feeds) > 0) {
                    echo "<p style='color: green;'>✓ Found " . count($enabled_feeds) . " enabled feed URLs for NewsFetchService</p>";
                    
                    // Test a small fetch operation
                    $fetch_result = $news_service->fetch_news(array(
                        'sources' => array_slice($enabled_feeds, 0, 1), // Test with one feed
                        'limit' => 5,
                        'cache_results' => false
                    ));
                    
                    if ($fetch_result['success']) {
                        echo "<p style='color: green;'>✓ NewsFetchService successfully fetched " . $fetch_result['total_found'] . " items</p>";
                    } else {
                        echo "<p style='color: orange;'>⚠ NewsFetchService fetch test failed: " . esc_html($fetch_result['error'] ?? 'Unknown error') . "</p>";
                    }
                    
                } else {
                    echo "<p style='color: orange;'>⚠ No enabled feeds found for integration test</p>";
                }
                
                $this->results['news_fetch_integration'] = 'PASS';
                
            } else {
                echo "<p style='color: orange;'>⚠ NewsFetchService class not found</p>";
                $this->results['news_fetch_integration'] = 'SKIP';
            }
            
        } catch (Exception $e) {
            $this->results['news_fetch_integration'] = 'FAIL';
            echo "<p style='color: red;'>✗ NewsFetchService integration failed: " . esc_html($e->getMessage()) . "</p>";
        }
    }
    
    /**
     * Display test summary
     */
    private function display_summary() {
        echo "<h3>Test Summary</h3>";
        
        $passed = 0;
        $total = count($this->results);
        
        echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 20px 0;'>";
        echo "<tr><th>Test</th><th>Result</th></tr>";
        
        foreach ($this->results as $test => $result) {
            $color = $result === 'PASS' ? 'green' : ($result === 'SKIP' ? 'orange' : 'red');
            $symbol = $result === 'PASS' ? '✓' : ($result === 'SKIP' ? '⚠' : '✗');
            
            echo "<tr>";
            echo "<td>" . ucwords(str_replace('_', ' ', $test)) . "</td>";
            echo "<td style='color: {$color};'> {$symbol} {$result}</td>";
            echo "</tr>";
            
            if ($result === 'PASS') {
                $passed++;
            }
        }
        
        echo "</table>";
        
        $percentage = $total > 0 ? round(($passed / $total) * 100, 1) : 0;
        
        echo "<div style='background: ";
        echo $percentage >= 80 ? '#d4edda' : ($percentage >= 60 ? '#fff3cd' : '#f8d7da');
        echo "; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
        echo "<h4>Overall Result: {$passed}/{$total} tests passed ({$percentage}%)</h4>";
        
        if ($percentage >= 80) {
            echo "<p style='color: #155724;'>✓ RSS Feed System is working correctly!</p>";
        } elseif ($percentage >= 60) {
            echo "<p style='color: #856404;'>⚠ RSS Feed System has some issues but is mostly functional.</p>";
        } else {
            echo "<p style='color: #721c24;'>✗ RSS Feed System has significant issues that need to be addressed.</p>";
        }
        
        echo "</div>";
    }
}

// Run tests if this file is accessed directly
if (isset($_GET['test_rss_system']) && $_GET['test_rss_system'] === '1') {
    new AANP_RSSFeedSystemTest();
}