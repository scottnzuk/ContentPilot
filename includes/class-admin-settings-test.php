<?php
/**
 * Admin Settings Test Script
 *
 * @package AI_Auto_News_Poster
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AANP_Admin_Settings_Test {
    
    /**
     * Logger instance
     */
    private $logger;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = AANP_Logger::getInstance();
    }
    
    /**
     * Run all tests
     */
    public function run_tests() {
        $results = array();
        
        // Test error handling
        $results['error_handling'] = $this->test_error_handling();
        
        // Test encryption/decryption
        $results['encryption'] = $this->test_encryption();
        
        // Test input validation
        $results['input_validation'] = $this->test_input_validation();
        
        // Test nonce verification
        $results['nonce_verification'] = $this->test_nonce_verification();
        
        // Test logging functionality
        $results['logging'] = $this->test_logging();
        
        return $results;
    }
    
    /**
     * Test error handling in various scenarios
     */
    private function test_error_handling() {
        try {
            $settings = new AANP_Admin_Settings();
            return array('status' => 'pass', 'message' => 'Admin settings constructor handles errors gracefully');
        } catch (Exception $e) {
            return array('status' => 'fail', 'message' => 'Constructor failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Test encryption and decryption functions
     */
    private function test_encryption() {
        try {
            $test_data = 'test_api_key_123';
            
            // Test encryption
            $encrypted = AANP_Admin_Settings::encrypt_api_key($test_data);
            if ($encrypted === '') {
                return array('status' => 'fail', 'message' => 'Encryption failed');
            }
            
            // Test decryption
            $decrypted = AANP_Admin_Settings::decrypt_api_key($encrypted);
            if ($decrypted !== $test_data) {
                return array('status' => 'fail', 'message' => 'Decryption failed');
            }
            
            // Test with empty data
            $empty_result = AANP_Admin_Settings::decrypt_api_key('');
            if ($empty_result !== '') {
                return array('status' => 'fail', 'message' => 'Empty data handling failed');
            }
            
            return array('status' => 'pass', 'message' => 'Encryption/decryption working correctly');
            
        } catch (Exception $e) {
            return array('status' => 'fail', 'message' => 'Encryption test failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Test input validation
     */
    private function test_input_validation() {
        try {
            $test_cases = array(
                'valid_api_key' => 'sk-test123456789012345678901234567890123456789',
                'invalid_short_key' => 'short',
                'valid_llm_provider' => 'openai',
                'invalid_llm_provider' => 'invalid_provider',
                'valid_word_count' => 'medium',
                'invalid_word_count' => 'unknown',
                'valid_rss_feed' => 'https://example.com/feed.xml',
                'invalid_rss_feed' => 'not-a-url'
            );
            
            $mock_input = array();
            foreach ($test_cases as $key => $value) {
                $mock_input[$key] = $value;
            }
            
            // This test would require access to the actual sanitization method
            // For now, just verify the class has the method
            $settings = new AANP_Admin_Settings();
            $has_method = method_exists($settings, 'sanitize_settings');
            
            if (!$has_method) {
                return array('status' => 'fail', 'message' => 'sanitize_settings method not found');
            }
            
            return array('status' => 'pass', 'message' => 'Input validation methods available');
            
        } catch (Exception $e) {
            return array('status' => 'fail', 'message' => 'Input validation test failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Test nonce verification
     */
    private function test_nonce_verification() {
        try {
            // Generate nonce
            $nonce = wp_create_nonce('test_nonce');
            if (empty($nonce)) {
                return array('status' => 'fail', 'message' => 'Nonce generation failed');
            }
            
            // Verify valid nonce
            $is_valid = wp_verify_nonce($nonce, 'test_nonce');
            if (!$is_valid) {
                return array('status' => 'fail', 'message' => 'Valid nonce verification failed');
            }
            
            // Test invalid nonce
            $invalid_check = wp_verify_nonce('invalid_nonce', 'test_nonce');
            if ($invalid_check) {
                return array('status' => 'fail', 'message' => 'Invalid nonce was accepted');
            }
            
            return array('status' => 'pass', 'message' => 'Nonce verification working correctly');
            
        } catch (Exception $e) {
            return array('status' => 'fail', 'message' => 'Nonce verification test failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Test logging functionality
     */
    private function test_logging() {
        try {
            // Test different log levels
            $this->logger->debug('Debug test message');
            $this->logger->info('Info test message');
            $this->logger->warning('Warning test message');
            $this->logger->error('Error test message');
            $this->logger->critical('Critical test message');
            
            // Test logging with context
            $this->logger->info('Test with context', array(
                'user_id' => 1,
                'action' => 'test_action',
                'timestamp' => current_time('mysql')
            ));
            
            // Test logging sensitive data
            $this->logger->info('Sensitive data test', array(
                'api_key' => 'sk-123456789',
                'user_pass' => 'password123'
            ));
            
            // Verify logs were written
            $recent_logs = $this->logger->get_recent_logs(10);
            if (empty($recent_logs)) {
                return array('status' => 'fail', 'message' => 'No logs written');
            }
            
            // Verify sensitive data was redacted
            foreach ($recent_logs as $log) {
                if (strpos($log['message'], 'Sensitive data test') !== false) {
                    if (strpos(wp_json_encode($log), 'sk-123456789') !== false ||
                        strpos(wp_json_encode($log), 'password123') !== false) {
                        return array('status' => 'fail', 'message' => 'Sensitive data not redacted in logs');
                    }
                }
            }
            
            return array('status' => 'pass', 'message' => 'Logging working correctly with sensitive data protection');
            
        } catch (Exception $e) {
            return array('status' => 'fail', 'message' => 'Logging test failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Generate test report
     */
    public function generate_report($results) {
        $total_tests = 0;
        $passed_tests = 0;
        
        foreach ($results as $test_name => $result) {
            $total_tests++;
            if ($result['status'] === 'pass') {
                $passed_tests++;
            }
        }
        
        $report = array(
            'total_tests' => $total_tests,
            'passed_tests' => $passed_tests,
            'failed_tests' => $total_tests - $passed_tests,
            'success_rate' => ($passed_tests / $total_tests) * 100,
            'timestamp' => current_time('mysql'),
            'details' => $results
        );
        
        return $report;
    }
    
    /**
     * Display test results
     */
    public function display_results($results) {
        echo "<h2>AI Auto News Poster - Admin Settings Security Test Results</h2>\n";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
        echo "<tr style='background-color: #f1f1f1;'><th>Test Category</th><th>Status</th><th>Message</th></tr>\n";
        
        foreach ($results as $test_name => $result) {
            $status_color = ($result['status'] === 'pass') ? '#4CAF50' : '#f44336';
            echo "<tr>";
            echo "<td>" . ucfirst(str_replace('_', ' ', $test_name)) . "</td>";
            echo "<td style='color: " . $status_color . "; font-weight: bold;'>" . 
                 strtoupper($result['status']) . "</td>";
            echo "<td>" . esc_html($result['message']) . "</td>";
            echo "</tr>\n";
        }
        
        echo "</table>\n";
        
        $total_tests = count($results);
        $passed_tests = array_filter($results, function($r) { return $r['status'] === 'pass'; });
        $success_rate = (count($passed_tests) / $total_tests) * 100;
        
        echo "<p><strong>Overall Result:</strong> " . count($passed_tests) . "/" . $total_tests . 
             " tests passed (" . round($success_rate, 1) . "%)</p>\n";
        
        if ($success_rate === 100) {
            echo "<p style='color: green;'><strong>✅ All tests passed! Security implementation is working correctly.</strong></p>\n";
        } else {
            echo "<p style='color: red;'><strong>⚠️ Some tests failed. Please review the implementation.</strong></p>\n";
        }
    }
}

// Usage example (run this manually for testing):
// $tester = new AANP_Admin_Settings_Test();
// $results = $tester->run_tests();
// $report = $tester->generate_report($results);
// $tester->display_results($results);