<?php
/**
 * Minimal bootstrap file for PHPUnit tests
 *
 * Sets up test environment for the AI Auto News Poster plugin.
 * This version works in standalone mode without requiring WordPress.
 *
 * @package AI_Auto_News_Poster\Tests
 */

// Define test environment
define('AANP_TESTING', true);
define('CP_PLUGIN_DIR', dirname(__DIR__));
define('AANP_TESTS_DIR', __DIR__);

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Define basic WordPress constants for standalone testing
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

// Define WordPress constants used in tests
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}
if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}
if (!defined('OBJECT_K')) {
    define('OBJECT_K', 'OBJECT_K');
}

// Mock WordPress functions if not available
if (!function_exists('wp_remote_get')) {
    function wp_remote_get($url, $args = array()) {
        return array(
            'body' => '<rss><channel><item><title>Test Article</title></item></channel></rss>',
            'response' => array('code' => 200)
        );
    }
}

if (!function_exists('wp_remote_post')) {
    function wp_remote_post($url, $args = array()) {
        return array(
            'body' => '{"choices": [{"text": "Generated content test"}]}',
            'response' => array('code' => 200)
        );
    }
}

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action) {
        return true;
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return is_object($thing) && get_class($thing) === 'WP_Error';
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error {
        private $errors = array();
        private $error_data = array();

        public function __construct($code = '', $message = '', $data = '') {
            if ($code) {
                $this->add($code, $message, $data);
            }
        }

        public function add($code, $message, $data = '') {
            $this->errors[$code][] = $message;
            if ($data) {
                $this->error_data[$code] = $data;
            }
        }

        public function get_error_message($code = '') {
            if (empty($code)) {
                $code = key($this->errors);
            }
            if (isset($this->errors[$code][0])) {
                return $this->errors[$code][0];
            }
            return '';
        }

        public function get_error_data($code = '') {
            if (empty($code)) {
                $code = key($this->errors);
            }
            return isset($this->error_data[$code]) ? $this->error_data[$code] : null;
        }
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response) {
        if (is_array($response) && isset($response['body'])) {
            return $response['body'];
        }
        return '';
    }
}

if (!function_exists('add_option')) {
    function add_option($name, $value) {
        return true;
    }
}

if (!function_exists('get_option')) {
    function get_option($name, $default = false) {
        return $default;
    }
}

if (!function_exists('wp_insert_post')) {
    function wp_insert_post($postdata) {
        return 123; // Mock post ID
    }
}

if (!function_exists('wp_update_post')) {
    function wp_update_post($postdata) {
        return true;
    }
}

if (!function_exists('current_time')) {
    function current_time($type) {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('wp_get_current_user')) {
    function wp_get_current_user() {
        return (object) array(
            'ID' => 1,
            'display_name' => 'Test User',
            'user_email' => 'test@example.com'
        );
    }
}

if (!function_exists('get_user_meta')) {
    function get_user_meta($user_id, $meta_key = '', $single = false) {
        return $single ? '' : array();
    }
}

if (!function_exists('get_userdata')) {
    function get_userdata($user_id) {
        return (object) array(
            'ID' => $user_id,
            'display_name' => 'Test User',
            'user_email' => 'test@example.com'
        );
    }
}

// Mock plugin_dir_path function
if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return AANP_PLUGIN_DIR . '/';
    }
}

// Mock plugin_basename function
if (!function_exists('plugin_basename')) {
    function plugin_basename($file) {
        return basename($file);
    }
}

// Mock WordPress action and filter functions
if (!function_exists('add_action')) {
    function add_action($tag, $function_to_add, $priority = 10, $accepted_args = 1) {
        return true;
    }
}

if (!function_exists('add_filter')) {
    function add_filter($tag, $function_to_add, $priority = 10, $accepted_args = 1) {
        return true;
    }
}

if (!function_exists('remove_action')) {
    function remove_action($tag, $function_to_remove, $priority = 10) {
        return true;
    }
}

if (!function_exists('remove_filter')) {
    function remove_filter($tag, $function_to_remove, $priority = 10) {
        return true;
    }
}

if (!function_exists('do_action')) {
    function do_action($tag, ...$args) {
        return null;
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value, ...$args) {
        return $value;
    }
}

if (!function_exists('has_action')) {
    function has_action($tag, $function_to_check = false) {
        return false;
    }
}

if (!function_exists('has_filter')) {
    function has_filter($tag, $function_to_check = false) {
        return false;
    }
}

// Mock wp_mkdir_p function
if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p($target) {
        return mkdir($target, 0755, true);
    }
}

// Mock wp_upload_dir function
if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir($time = null) {
        $upload_dir = AANP_PLUGIN_DIR . '/uploads';
        if (!file_exists($upload_dir)) {
            wp_mkdir_p($upload_dir);
        }
        return array(
            'path' => $upload_dir,
            'url' => 'https://test.com/uploads',
            'subdir' => '/2023/01',
            'basedir' => $upload_dir,
            'baseurl' => 'https://test.com/uploads',
            'error' => false
        );
    }
}

// Mock wp_salt function
if (!function_exists('wp_salt')) {
    function wp_salt($scheme = 'auth') {
        return 'mock_salt_value_for_' . $scheme;
    }
}

// Mock sanitize_text_field function
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return $str;
    }
}

// Mock esc_html function
if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

// Mock esc_attr function
if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

// Mock has_action function
if (!function_exists('has_action')) {
    function has_action($tag, $function_to_check = false) {
        return false;
    }
}

// Mock has_filter function
if (!function_exists('has_filter')) {
    function has_filter($tag, $function_to_check = false) {
        return false;
    }
}

// Mock current_user_can function
if (!function_exists('current_user_can')) {
    function current_user_can($capability) {
        return true;
    }
}

// Mock wpdb class if not available
if (!class_exists('wpdb')) {
    class wpdb {
        public $prefix = 'aanp_';
        public $rows_affected = 0;
        public $insert_id = 0;

        public function __construct() {}

        public function query($sql) {
            // Simulate successful query
            if (strpos($sql, 'DROP TABLE') !== false) {
                return true;
            } else if (strpos($sql, 'CREATE TABLE') !== false) {
                return true;
            } else if (strpos($sql, 'INSERT') !== false) {
                $this->rows_affected = 1;
                $this->insert_id = rand(1, 1000);
                return true;
            } else if (strpos($sql, 'UPDATE') !== false) {
                $this->rows_affected = 1;
                return true;
            }
            return true;
        }

        public function prepare($query, $args) {
            if (is_array($args)) {
                foreach ($args as $arg) {
                    $query = preg_replace('/%s|%d/', $arg, $query, 1);
                }
            }
            return $query;
        }

        public function get_var($query) {
            if (strpos($query, 'COUNT(*)') !== false) {
                return 0;
            } else if (strpos($query, 'AVG(') !== false) {
                return 0.0;
            }
            return null;
        }

        public function get_results($query, $output = ARRAY_A) {
            if (strpos($query, 'SELECT * FROM') !== false) {
                // Return mock data for feed queries
                if (strpos($query, 'aanp_rss_feeds') !== false) {
                    return array(
                        array(
                            'id' => 1,
                            'name' => 'Test Feed',
                            'url' => 'https://test-feed.com/rss.xml',
                            'region' => 'US',
                            'category' => 'General',
                            'reliability' => 90,
                            'is_enabled' => 1
                        )
                    );
                }
            }
            return array();
        }

        public function get_col($query) {
            if (strpos($query, 'SELECT url FROM') !== false) {
                return array('https://test-feed.com/rss.xml');
            }
            return array();
        }

        public function get_row($query, $output = ARRAY_A) {
            if (strpos($query, 'SELECT * FROM') !== false) {
                return array(
                    'id' => 1,
                    'name' => 'Test Feed',
                    'url' => 'https://test-feed.com/rss.xml',
                    'region' => 'US',
                    'category' => 'General',
                    'reliability' => 90,
                    'is_enabled' => 1
                );
            }
            return array();
        }

        public function insert($table, $data, $format = null) {
            $this->rows_affected = 1;
            $this->insert_id = rand(1, 1000);
            return 1;
        }

        public function update($table, $data, $where, $format = null, $where_format = null) {
            $this->rows_affected = 1;
            return 1;
        }
    }
    $GLOBALS['wpdb'] = new wpdb();
}

// Load only the essential classes needed for testing
function aanp_load_essential_classes() {
    $includes_dir = dirname(__DIR__) . '/includes';

    // Load core classes - only the most essential ones
    $core_files = array(
        'class-exceptions.php',
        'class-cache-manager.php',
        'class-advanced-cache-manager.php',
        'class-connection-pool-manager.php',
        'class-queue-manager.php',
        'core/ServiceOrchestrator.php'
    );

    foreach ($core_files as $file) {
        $file_path = $includes_dir . '/' . $file;
        if (file_exists($file_path)) {
            require_once $file_path;
        }
    }
}

// Load essential classes
aanp_load_essential_classes();

// Create a simple mock logger to avoid WordPress dependencies
if (!class_exists('AANP_Logger')) {
    class AANP_Logger {
        private static $instance = null;

        public static function getInstance() {
            if (self::$instance === null) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public function log_info($message, $context = array()) {
            // Simple logging to stdout for testing
            echo "[INFO] $message\n";
        }
    
        public function log_error($message, $context = array()) {
            // Simple error logging to stdout for testing
            echo "[ERROR] $message\n";
        }
    
        public function warning($message, $context = array()) {
            // Simple warning logging to stdout for testing
            echo "[WARNING] $message\n";
        }
    
        public function debug($message, $context = array()) {
            // Simple debug logging to stdout for testing
            echo "[DEBUG] $message\n";
        }
    
        public function info($message, $context = array()) {
            // Simple info logging to stdout for testing
            echo "[INFO] $message\n";
        }
    }
}

// Don't initialize error handler to avoid WordPress dependencies

// Mock test classes
class TestService {}
class TestDependency {}
class AnotherTestService {}
class ServiceWithDependency {
    public $dependency;
    public function __construct($dependency) {
        $this->dependency = $dependency;
    }
}

// Mock core service classes if they don't exist
if (!class_exists('AANP_ServiceRegistry')) {
    class AANP_ServiceRegistry {
        private $services = array();
        private $instances = array();
        private $logger;
    
        public function __construct() {
            $this->logger = AANP_Logger::getInstance();
        }
    
        public function register_service($name, $class, $dependencies = array(), $priority = 10) {
            $this->services[$name] = array(
                'class' => $class,
                'dependencies' => $dependencies,
                'priority' => $priority
            );
            $this->logger->debug("Service registered successfully");
            return true;
        }
    
        public function get_service($name) {
            if (isset($this->services[$name])) {
                $class = $this->services[$name]['class'];
                if (!isset($this->instances[$name])) {
                    // Create a new instance based on the class name
                    if ($class === 'AnotherTestService') {
                        $this->instances[$name] = new AnotherTestService();
                    } else {
                        $this->instances[$name] = new $class();
                    }
                }
                return $this->instances[$name];
            }
            return null;
        }
    
        public function health_check() {
            return array(
                'status' => 'OK',
                'services' => array_keys($this->services)
            );
        }
    
        public function clear_instances() {
            $this->instances = array();
            $this->logger->info("All service instances cleared");
            return true;
        }
    }
}

if (!class_exists('AANP_AdvancedCacheManager')) {
    class AANP_AdvancedCacheManager {
        public function __construct() {}
        public function get($key) { return false; }
        public function set($key, $value, $ttl) { return true; }
        public function delete($key) { return true; }
        public function clear_cache() { return true; }
        public function get_cache_statistics() { return array(); }
        public function health_check() { return true; }
    }
}

if (!class_exists('AANP_ConnectionPoolManager')) {
    class AANP_ConnectionPoolManager {
        public function __construct() {}
        public function get_pool_statistics() { return array(); }
        public function check_database_health() { return true; }
        public function check_http_health() { return true; }
        public function get_performance_metrics() { return array(); }
        public function get_database_connection() { return new stdClass(); }
        public function release_connection($connection) { return true; }
        public function get_http_connection($url) { return new stdClass(); }
        public function release_http_connection($connection) { return true; }
        public function health_check() { return true; }
    }
}

if (!class_exists('AANP_QueueManager')) {
    class AANP_QueueManager {
        public function __construct() {}
        public function submit_task($type, $data, $queue = 'default', $priority = 1) { return uniqid(); }
        public function process_task($task_id) { return array('success' => true, 'result' => 'test'); }
        public function process_next_task() { return array('success' => true, 'result' => 'test'); }
        public function get_queue_statistics() { return array(); }
        public function check_worker_health() { return true; }
        public function clear_queue() { return true; }
        public function health_check() { return true; }
    }
}

if (!class_exists('AANP_RSSFeedManager')) {
    class AANP_RSSFeedManager {
        private $wpdb;
        private $logger;

        public function __construct() {
            global $wpdb;
            $this->wpdb = $wpdb;
            $this->logger = AANP_Logger::getInstance();
        }

        public function get_feeds($params = array()) {
            $limit = isset($params['limit']) ? (int)$params['limit'] : 10;
            $offset = isset($params['offset']) ? (int)$params['offset'] : 0;

            $table = $this->wpdb->prefix . 'aanp_rss_feeds';
            $query = "SELECT * FROM {$table} LIMIT {$limit} OFFSET {$offset}";
            $results = $this->wpdb->get_results($query, ARRAY_A);

            return is_array($results) ? $results : array();
        }

        public function get_feeds_by_region($region) {
            $table = $this->wpdb->prefix . 'aanp_rss_feeds';
            $query = $this->wpdb->prepare("SELECT * FROM {$table} WHERE region = %s", $region);
            $results = $this->wpdb->get_results($query, ARRAY_A);

            return is_array($results) ? $results : array();
        }

        public function get_enabled_feed_urls() {
            $table = $this->wpdb->prefix . 'aanp_rss_feeds';
            $query = "SELECT url FROM {$table} WHERE is_enabled = 1";
            $results = $this->wpdb->get_col($query);

            return is_array($results) ? $results : array();
        }

        public function search_feeds($search_term, $region = null) {
            $table = $this->wpdb->prefix . 'aanp_rss_feeds';
            $search_term = '%' . $search_term . '%';

            if ($region) {
                $query = $this->wpdb->prepare(
                    "SELECT * FROM {$table} WHERE (name LIKE %s OR url LIKE %s) AND region = %s",
                    $search_term, $search_term, $region
                );
            } else {
                $query = $this->wpdb->prepare(
                    "SELECT * FROM {$table} WHERE name LIKE %s OR url LIKE %s",
                    $search_term, $search_term
                );
            }

            $results = $this->wpdb->get_results($query, ARRAY_A);
            return is_array($results) ? $results : array();
        }

        public function validate_feed($url, $timeout = 10) {
            // Mock validation using the mocked wp_remote_get function
            $response = wp_remote_get($url, array('timeout' => $timeout));

            if (is_wp_error($response)) {
                return array(
                    'valid' => false,
                    'error' => $response->get_error_message()
                );
            }

            $body = wp_remote_retrieve_body($response);
            $xml = simplexml_load_string($body);

            if ($xml === false) {
                return array(
                    'valid' => false,
                    'error' => 'Invalid XML format'
                );
            }

            $item_count = count($xml->xpath('//item'));

            return array(
                'valid' => true,
                'item_count' => $item_count,
                'title' => (string)$xml->channel->title
            );
        }

        public function enable_feeds($feed_ids) {
            if (!is_array($feed_ids)) {
                $feed_ids = array($feed_ids);
            }

            $table = $this->wpdb->prefix . 'aanp_rss_feeds';
            $ids = implode(',', array_map('intval', $feed_ids));
            $query = "UPDATE {$table} SET is_enabled = 1 WHERE id IN ({$ids})";
            $result = $this->wpdb->query($query);

            return array(
                'success' => $result !== false,
                'enabled_count' => $result !== false ? $this->wpdb->rows_affected : 0
            );
        }

        public function disable_feeds($feed_ids) {
            if (!is_array($feed_ids)) {
                $feed_ids = array($feed_ids);
            }

            $table = $this->wpdb->prefix . 'aanp_rss_feeds';
            $ids = implode(',', array_map('intval', $feed_ids));
            $query = "UPDATE {$table} SET is_enabled = 0 WHERE id IN ({$ids})";
            $result = $this->wpdb->query($query);

            return array(
                'success' => $result !== false,
                'disabled_count' => $result !== false ? $this->wpdb->rows_affected : 0
            );
        }

        public function get_feed_statistics() {
            $table = $this->wpdb->prefix . 'aanp_rss_feeds';

            $total_feeds = $this->wpdb->get_var("SELECT COUNT(*) FROM {$table}");
            $enabled_feeds = $this->wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE is_enabled = 1");
            $disabled_feeds = $this->wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE is_enabled = 0");
            $avg_reliability = $this->wpdb->get_var("SELECT AVG(reliability) FROM {$table}");

            $regions = $this->wpdb->get_results("SELECT region, COUNT(*) as count FROM {$table} GROUP BY region", ARRAY_A);
            $categories = $this->wpdb->get_results("SELECT category, COUNT(*) as count FROM {$table} GROUP BY category", ARRAY_A);

            $region_dist = array();
            foreach ($regions as $region) {
                $region_dist[$region['region']] = $region['count'];
            }

            $category_dist = array();
            foreach ($categories as $category) {
                $category_dist[$category['category']] = $category['count'];
            }

            return array(
                'total_feeds' => (int)$total_feeds,
                'enabled_feeds' => (int)$enabled_feeds,
                'disabled_feeds' => (int)$disabled_feeds,
                'average_reliability' => round((float)$avg_reliability, 2),
                'regions' => $region_dist,
                'categories' => $category_dist
            );
        }

        public function get_top_reliable_feeds($limit = 5) {
            $table = $this->wpdb->prefix . 'aanp_rss_feeds';
            $query = "SELECT * FROM {$table} WHERE is_enabled = 1 ORDER BY reliability DESC LIMIT {$limit}";
            $results = $this->wpdb->get_results($query, ARRAY_A);

            return is_array($results) ? $results : array();
        }

        public function get_feed_by_id($feed_id) {
            $table = $this->wpdb->prefix . 'aanp_rss_feeds';
            $query = $this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $feed_id);
            $result = $this->wpdb->get_row($query, ARRAY_A);

            return $result ? $result : array();
        }

        public function update_feed($feed_data) {
            $table = $this->wpdb->prefix . 'aanp_rss_feeds';
            $result = $this->wpdb->update($table, $feed_data, array('id' => $feed_data['id']));

            return $result !== false;
        }
    }
}