<?php
/**
 * Analytics Service for Microservices Architecture
 *
 * Handles performance monitoring, metrics collection, data analysis,
 * and reporting for the microservices architecture.
 *
 * @package AI_Auto_News_Poster\Services
 * @since 1.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Analytics Service Class
 */
class AANP_AnalyticsService {
    
    /**
     * Cache manager instance
     * @var AANP_AdvancedCacheManager
     */
    private $cache_manager;
    
    /**
     * Logger instance
     * @var AANP_Logger
     */
    private $logger;
    
    /**
     * Performance metrics storage
     * @var array
     */
    private $metrics_data = array();
    
    /**
     * Service configuration
     * @var array
     */
    private $config = array();
    
    /**
     * Analytics data collectors
     * @var array
     */
    private $collectors = array();
    
    /**
     * Dashboard data cache
     * @var array
     */
    private $dashboard_cache = array();
    
    /**
     * Constructor
     *
     * @param AANP_AdvancedCacheManager $cache_manager
     */
    public function __construct(AANP_AdvancedCacheManager $cache_manager = null) {
        $this->logger = AANP_Logger::getInstance();
        $this->cache_manager = $cache_manager ?: new AANP_AdvancedCacheManager();
        
        $this->init_config();
        $this->init_collectors();
        $this->init_hooks();
    }
    
    /**
     * Initialize service configuration
     */
    private function init_config() {
        $options = get_option('aanp_settings', array());
        
        $this->config = array(
            'retention_period_days' => isset($options['analytics_retention_days']) ? intval($options['analytics_retention_days']) : 90,
            'collection_interval' => isset($options['analytics_interval']) ? $options['analytics_interval'] : 'hourly',
            'dashboard_refresh_interval' => 300, // 5 minutes
            'enable_real_time' => isset($options['enable_realtime_analytics']) ? (bool) $options['enable_realtime_analytics'] : true,
            'enable_performance_tracking' => isset($options['enable_performance_tracking']) ? (bool) $options['enable_performance_tracking'] : true,
            'enable_user_tracking' => isset($options['enable_user_tracking']) ? (bool) $options['enable_user_tracking'] : false,
            'export_formats' => array('json', 'csv', 'pdf'),
            'alert_thresholds' => array(
                'error_rate' => 0.05, // 5% error rate threshold
                'response_time' => 5000, // 5 seconds response time threshold
                'memory_usage' => 80 // 80% memory usage threshold
            )
        );
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('aanp_collect_metrics', array($this, 'collect_all_metrics'));
        add_action('aanp_update_dashboard', array($this, 'update_dashboard_data'));
        
        // Schedule regular metric collection
        $this->schedule_metric_collection();
        
        // Register real-time analytics hooks
        if ($this->config['enable_real_time']) {
            add_action('wp_footer', array($this, 'inject_realtime_tracking'));
        }
    }
    
    /**
     * Schedule metric collection
     */
    private function schedule_metric_collection() {
        $interval_map = array(
            'realtime' => 'every_minute',
            'hourly' => 'hourly',
            'daily' => 'daily',
            'weekly' => 'weekly'
        );
        
        $interval = $interval_map[$this->config['collection_interval']] ?? 'hourly';
        
        if (!wp_next_scheduled('aanp_collect_metrics')) {
            wp_schedule_event(time(), $interval, 'aanp_collect_metrics');
        }
    }
    
    /**
     * Initialize analytics collectors
     */
    private function init_collectors() {
        $this->collectors = array(
            'performance' => new AANP_Performance_Collector($this->logger),
            'content' => new AANP_Content_Analytics_Collector($this->logger),
            'services' => new AANP_Service_Metrics_Collector($this->logger),
            'user' => new AANP_User_Analytics_Collector($this->logger),
            'system' => new AANP_System_Analytics_Collector($this->logger)
        );
    }
    
    /**
     * Collect all metrics
     *
     * @param array $parameters Collection parameters
     * @return array Collection results
     */
    public function collect_metrics($parameters = array()) {
        $start_time = microtime(true);
        
        try {
            $params = array_merge(array(
                'collectors' => array_keys($this->collectors),
                'time_range' => 'last_hour',
                'detailed' => true,
                'store_data' => true
            ), $parameters);
            
            $this->logger->info('Starting metrics collection', array(
                'collectors' => $params['collectors'],
                'time_range' => $params['time_range'],
                'detailed' => $params['detailed']
            ));
            
            $collected_data = array();
            $collection_errors = array();
            
            foreach ($params['collectors'] as $collector_name) {
                try {
                    if (!isset($this->collectors[$collector_name])) {
                        throw new Exception("Unknown collector: {$collector_name}");
                    }
                    
                    $collector = $this->collectors[$collector_name];
                    $collector_data = $collector->collect($params);
                    
                    $collected_data[$collector_name] = $collector_data;
                    
                    $this->logger->debug("Collector '{$collector_name}' completed successfully");
                    
                } catch (Exception $e) {
                    $error_msg = "Collector '{$collector_name}' failed: " . $e->getMessage();
                    $collection_errors[] = $error_msg;
                    
                    $this->logger->error($error_msg, array(
                        'collector' => $collector_name,
                        'error' => $e->getMessage()
                    ));
                }
            }
            
            // Process and aggregate collected data
            $processed_data = $this->process_collected_data($collected_data, $params);
            
            // Store data if requested
            if ($params['store_data']) {
                $this->store_metrics_data($processed_data, $params);
            }
            
            $execution_time = (microtime(true) - $start_time) * 1000;
            
            $response = array(
                'success' => true,
                'data' => $processed_data,
                'collectors_used' => array_keys($collected_data),
                'collection_errors' => $collection_errors,
                'execution_time_ms' => $execution_time,
                'timestamp' => current_time('Y-m-d H:i:s')
            );
            
            $this->logger->info('Metrics collection completed', array(
                'collectors_count' => count($collected_data),
                'errors_count' => count($collection_errors),
                'execution_time_ms' => $execution_time
            ));
            
            return $response;
            
        } catch (Exception $e) {
            $execution_time = (microtime(true) - $start_time) * 1000;
            
            $this->logger->error('Metrics collection failed', array(
                'error' => $e->getMessage(),
                'execution_time_ms' => $execution_time
            ));
            
            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'execution_time_ms' => $execution_time,
                'timestamp' => current_time('Y-m-d H:i:s')
            );
        }
    }
    
    /**
     * Process and aggregate collected data
     *
     * @param array $collected_data Raw collected data
     * @param array $params Processing parameters
     * @return array Processed data
     */
    private function process_collected_data($collected_data, $params) {
        $processed = array(
            'summary' => array(),
            'details' => array(),
            'trends' => array(),
            'alerts' => array()
        );
        
        // Generate summary statistics
        $processed['summary'] = $this->generate_summary_stats($collected_data);
        
        // Store detailed data
        if ($params['detailed']) {
            $processed['details'] = $collected_data;
        }
        
        // Calculate trends
        $processed['trends'] = $this->calculate_trends($collected_data, $params);
        
        // Check for alerts
        $processed['alerts'] = $this->check_alerts($collected_data);
        
        return $processed;
    }
    
    /**
     * Generate summary statistics
     *
     * @param array $collected_data Collected data
     * @return array Summary statistics
     */
    private function generate_summary_stats($collected_data) {
        $summary = array(
            'total_operations' => 0,
            'successful_operations' => 0,
            'failed_operations' => 0,
            'average_response_time' => 0,
            'total_items_processed' => 0,
            'error_rate' => 0,
            'performance_score' => 0
        );
        
        $total_response_time = 0;
        $operation_count = 0;
        
        foreach ($collected_data as $collector_name => $data) {
            if (isset($data['summary'])) {
                $collector_summary = $data['summary'];
                
                $summary['total_operations'] += $collector_summary['total_operations'] ?? 0;
                $summary['successful_operations'] += $collector_summary['successful_operations'] ?? 0;
                $summary['failed_operations'] += $collector_summary['failed_operations'] ?? 0;
                $summary['total_items_processed'] += $collector_summary['total_items_processed'] ?? 0;
                
                if (isset($collector_summary['average_response_time'])) {
                    $total_response_time += $collector_summary['average_response_time'];
                    $operation_count++;
                }
            }
        }
        
        // Calculate derived metrics
        if ($summary['total_operations'] > 0) {
            $summary['error_rate'] = ($summary['failed_operations'] / $summary['total_operations']) * 100;
        }
        
        if ($operation_count > 0) {
            $summary['average_response_time'] = $total_response_time / $operation_count;
        }
        
        // Calculate performance score (0-100)
        $summary['performance_score'] = $this->calculate_performance_score($summary);
        
        return $summary;
    }
    
    /**
     * Calculate performance score
     *
     * @param array $summary Summary statistics
     * @return int Performance score (0-100)
     */
    private function calculate_performance_score($summary) {
        $score = 100;
        
        // Deduct points for errors
        $score -= min(50, $summary['error_rate'] * 2);
        
        // Deduct points for slow response times
        if ($summary['average_response_time'] > 1000) {
            $score -= min(20, ($summary['average_response_time'] - 1000) / 100);
        }
        
        // Bonus for high throughput
        if ($summary['total_items_processed'] > 100) {
            $score += min(10, $summary['total_items_processed'] / 50);
        }
        
        return max(0, min(100, intval($score)));
    }
    
    /**
     * Calculate trends from collected data
     *
     * @param array $collected_data Collected data
     * @param array $params Parameters
     * @return array Trends data
     */
    private function calculate_trends($collected_data, $params) {
        $trends = array();
        
        // Get historical data for comparison
        $historical_data = $this->get_historical_data($params['time_range']);
        
        foreach ($collected_data as $collector_name => $data) {
            if (isset($data['metrics'])) {
                $trends[$collector_name] = $this->calculate_collector_trends(
                    $data['metrics'],
                    isset($historical_data[$collector_name]) ? $historical_data[$collector_name] : array(),
                    $params
                );
            }
        }
        
        return $trends;
    }
    
    /**
     * Calculate trends for a specific collector
     *
     * @param array $current_data Current data
     * @param array $historical_data Historical data
     * @param array $params Parameters
     * @return array Collector trends
     */
    private function calculate_collector_trends($current_data, $historical_data, $params) {
        $trends = array();
        
        foreach ($current_data as $metric_name => $metric_value) {
            if (is_numeric($metric_value) && isset($historical_data[$metric_name])) {
                $historical_values = array_column($historical_data[$metric_name], 'value');
                
                if (!empty($historical_values)) {
                    $average = array_sum($historical_values) / count($historical_values);
                    $change_percent = (($metric_value - $average) / $average) * 100;
                    
                    $trends[$metric_name] = array(
                        'current' => $metric_value,
                        'historical_average' => $average,
                        'change_percent' => $change_percent,
                        'trend_direction' => $change_percent > 0 ? 'up' : ($change_percent < 0 ? 'down' : 'stable')
                    );
                }
            }
        }
        
        return $trends;
    }
    
    /**
     * Get historical data for trend analysis
     *
     * @param string $time_range Time range for historical data
     * @return array Historical data
     */
    private function get_historical_data($time_range) {
        $cache_key = "historical_analytics_{$time_range}";
        
        // Check cache first
        $cached_data = $this->cache_manager->get($cache_key);
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        // Get from database
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'aanp_analytics';
        
        $time_conditions = array(
            'last_hour' => 'DATE_ADD(NOW(), INTERVAL -1 HOUR)',
            'last_day' => 'DATE_ADD(NOW(), INTERVAL -1 DAY)',
            'last_week' => 'DATE_ADD(NOW(), INTERVAL -1 WEEK)',
            'last_month' => 'DATE_ADD(NOW(), INTERVAL -1 MONTH)'
        );
        
        if (!isset($time_conditions[$time_range])) {
            $time_range = 'last_day';
        }
        
        $query = $wpdb->prepare(
            "SELECT collector, metric_name, metric_value, timestamp 
             FROM {$table_name} 
             WHERE timestamp >= %s 
             ORDER BY timestamp DESC",
            $time_conditions[$time_range]
        );
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        // Group data by collector and metric
        $historical_data = array();
        foreach ($results as $row) {
            if (!isset($historical_data[$row['collector']])) {
                $historical_data[$row['collector']] = array();
            }
            
            if (!isset($historical_data[$row['collector']][$row['metric_name']])) {
                $historical_data[$row['collector']][$row['metric_name']] = array();
            }
            
            $historical_data[$row['collector']][$row['metric_name']][] = array(
                'value' => floatval($row['metric_value']),
                'timestamp' => $row['timestamp']
            );
        }
        
        // Cache the result
        $this->cache_manager->set($cache_key, $historical_data, 1800); // 30 minutes
        
        return $historical_data;
    }
    
    /**
     * Check for alerts based on thresholds
     *
     * @param array $collected_data Collected data
     * @return array Alerts
     */
    private function check_alerts($collected_data) {
        $alerts = array();
        
        foreach ($collected_data as $collector_name => $data) {
            if (isset($data['summary'])) {
                $summary = $data['summary'];
                
                // Check error rate
                if (isset($summary['error_rate']) && $summary['error_rate'] > $this->config['alert_thresholds']['error_rate'] * 100) {
                    $alerts[] = array(
                        'type' => 'error_rate',
                        'severity' => 'warning',
                        'message' => "Error rate ({$summary['error_rate']}%) exceeds threshold",
                        'collector' => $collector_name,
                        'value' => $summary['error_rate'],
                        'threshold' => $this->config['alert_thresholds']['error_rate'] * 100,
                        'timestamp' => current_time('Y-m-d H:i:s')
                    );
                }
                
                // Check response time
                if (isset($summary['average_response_time']) && $summary['average_response_time'] > $this->config['alert_thresholds']['response_time']) {
                    $alerts[] = array(
                        'type' => 'response_time',
                        'severity' => 'warning',
                        'message' => "Average response time ({$summary['average_response_time']}ms) exceeds threshold",
                        'collector' => $collector_name,
                        'value' => $summary['average_response_time'],
                        'threshold' => $this->config['alert_thresholds']['response_time'],
                        'timestamp' => current_time('Y-m-d H:i:s')
                    );
                }
            }
        }
        
        // Check system metrics
        $system_alerts = $this->check_system_alerts();
        $alerts = array_merge($alerts, $system_alerts);
        
        return $alerts;
    }
    
    /**
     * Check system-level alerts
     *
     * @return array System alerts
     */
    private function check_system_alerts() {
        $alerts = array();
        
        // Check memory usage
        $memory_usage_percent = (memory_get_usage(true) / memory_get_usage()) * 100;
        if ($memory_usage_percent > $this->config['alert_thresholds']['memory_usage']) {
            $alerts[] = array(
                'type' => 'memory_usage',
                'severity' => 'warning',
                'message' => "Memory usage ({$memory_usage_percent}%) exceeds threshold",
                'value' => $memory_usage_percent,
                'threshold' => $this->config['alert_thresholds']['memory_usage'],
                'timestamp' => current_time('Y-m-d H:i:s')
            );
        }
        
        return $alerts;
    }
    
    /**
     * Store metrics data in database
     *
     * @param array $processed_data Processed metrics data
     * @param array $params Storage parameters
     */
    private function store_metrics_data($processed_data, $params) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'aanp_analytics';
        
        // Ensure table exists
        $this->ensure_analytics_table();
        
        // Store summary data
        if (isset($processed_data['summary'])) {
            foreach ($processed_data['summary'] as $metric_name => $value) {
                if (is_numeric($value)) {
                    $this->insert_metric($table_name, 'summary', $metric_name, $value);
                }
            }
        }
        
        // Store detailed data
        if (isset($processed_data['details'])) {
            foreach ($processed_data['details'] as $collector_name => $collector_data) {
                if (isset($collector_data['metrics'])) {
                    foreach ($collector_data['metrics'] as $metric_name => $value) {
                        if (is_numeric($value)) {
                            $this->insert_metric($table_name, $collector_name, $metric_name, $value);
                        }
                    }
                }
            }
        }
        
        // Store alerts
        if (isset($processed_data['alerts'])) {
            foreach ($processed_data['alerts'] as $alert) {
                $this->insert_alert($table_name, $alert);
            }
        }
    }
    
    /**
     * Ensure analytics table exists
     */
    private function ensure_analytics_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'aanp_analytics';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            collector varchar(50) NOT NULL,
            metric_name varchar(100) NOT NULL,
            metric_value decimal(15,4) NOT NULL,
            metadata longtext,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_collector_metric (collector, metric_name),
            KEY idx_timestamp (timestamp)
        ) {$charset_collate}";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Insert a single metric
     *
     * @param string $table_name Table name
     * @param string $collector Collector name
     * @param string $metric_name Metric name
     * @param float $value Metric value
     */
    private function insert_metric($table_name, $collector, $metric_name, $value) {
        global $wpdb;
        
        $wpdb->insert(
            $table_name,
            array(
                'collector' => $collector,
                'metric_name' => $metric_name,
                'metric_value' => $value,
                'timestamp' => current_time('mysql')
            ),
            array('%s', '%s', '%f', '%s')
        );
    }
    
    /**
     * Insert alert data
     *
     * @param string $table_name Table name
     * @param array $alert Alert data
     */
    private function insert_alert($table_name, $alert) {
        global $wpdb;
        
        $wpdb->insert(
            $table_name,
            array(
                'collector' => 'alerts',
                'metric_name' => $alert['type'],
                'metric_value' => $alert['value'],
                'metadata' => json_encode($alert),
                'timestamp' => current_time('mysql')
            ),
            array('%s', '%s', '%f', '%s', '%s')
        );
    }
    
    /**
     * Update dashboard data
     */
    public function update_dashboard_data() {
        try {
            $this->logger->debug('Updating dashboard data');
            
            // Collect current metrics
            $metrics_result = $this->collect_metrics(array(
                'collectors' => array('performance', 'services', 'content'),
                'detailed' => false,
                'store_data' => false
            ));
            
            if ($metrics_result['success']) {
                // Cache dashboard data
                $dashboard_data = array(
                    'summary' => $metrics_result['data']['summary'],
                    'alerts' => $metrics_result['data']['alerts'],
                    'last_updated' => current_time('Y-m-d H:i:s')
                );
                
                $this->cache_manager->set('dashboard_data', $dashboard_data, $this->config['dashboard_refresh_interval']);
                
                // Trigger dashboard update hook
                do_action('aanp_dashboard_updated', $dashboard_data);
                
                $this->logger->debug('Dashboard data updated successfully');
            }
            
        } catch (Exception $e) {
            $this->logger->error('Dashboard data update failed', array(
                'error' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Get dashboard data
     *
     * @return array Dashboard data
     */
    public function get_dashboard_data() {
        $cached_data = $this->cache_manager->get('dashboard_data');
        
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        // If no cached data, collect fresh data
        $this->update_dashboard_data();
        
        return $this->cache_manager->get('dashboard_data') ?: array();
    }
    
    /**
     * Generate analytics report
     *
     * @param array $parameters Report parameters
     * @return array Report data
     */
    public function generate_report($parameters = array()) {
        $params = array_merge(array(
            'report_type' => 'performance',
            'time_range' => 'last_week',
            'format' => 'json',
            'include_trends' => true,
            'include_alerts' => true
        ), $parameters);
        
        try {
            $this->logger->info('Generating analytics report', array(
                'type' => $params['report_type'],
                'time_range' => $params['time_range'],
                'format' => $params['format']
            ));
            
            // Collect relevant metrics
            $metrics_data = $this->collect_metrics(array(
                'collectors' => $this->get_collectors_for_report($params['report_type']),
                'time_range' => $params['time_range'],
                'detailed' => true,
                'store_data' => false
            ));
            
            // Generate report based on type
            $report_data = $this->generate_report_content($metrics_data, $params);
            
            return array(
                'success' => true,
                'report' => $report_data,
                'metadata' => array(
                    'type' => $params['report_type'],
                    'time_range' => $params['time_range'],
                    'generated_at' => current_time('Y-m-d H:i:s'),
                    'format' => $params['format']
                )
            );
            
        } catch (Exception $e) {
            $this->logger->error('Report generation failed', array(
                'error' => $e->getMessage(),
                'report_type' => $params['report_type']
            ));
            
            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'timestamp' => current_time('Y-m-d H:i:s')
            );
        }
    }
    
    /**
     * Get collectors relevant for a report type
     *
     * @param string $report_type Report type
     * @return array Relevant collectors
     */
    private function get_collectors_for_report($report_type) {
        $collector_map = array(
            'performance' => array('performance', 'services'),
            'content' => array('content', 'services'),
            'system' => array('system', 'performance'),
            'user' => array('user', 'content'),
            'comprehensive' => array_keys($this->collectors)
        );
        
        return $collector_map[$report_type] ?? array('performance', 'services');
    }
    
    /**
     * Generate report content based on data and parameters
     *
     * @param array $metrics_data Metrics data
     * @param array $params Parameters
     * @return array Report content
     */
    private function generate_report_content($metrics_data, $params) {
        $content = array(
            'executive_summary' => $this->generate_executive_summary($metrics_data),
            'detailed_analysis' => $this->generate_detailed_analysis($metrics_data),
            'recommendations' => $this->generate_recommendations($metrics_data),
            'charts_data' => $this->generate_charts_data($metrics_data)
        );
        
        if ($params['include_trends']) {
            $content['trends'] = $metrics_data['data']['trends'] ?? array();
        }
        
        if ($params['include_alerts']) {
            $content['alerts'] = $metrics_data['data']['alerts'] ?? array();
        }
        
        return $content;
    }
    
    /**
     * Generate executive summary for report
     *
     * @param array $metrics_data Metrics data
     * @return string Executive summary
     */
    private function generate_executive_summary($metrics_data) {
        if (!$metrics_data['success']) {
            return 'Analytics data collection failed. Please check system logs.';
        }
        
        $summary = $metrics_data['data']['summary'];
        $alerts = $metrics_data['data']['alerts'];
        
        $summary_text = "Performance Overview: ";
        $summary_text .= "Total operations: {$summary['total_operations']}, ";
        $summary_text .= "Success rate: " . (100 - $summary['error_rate']) . "%, ";
        $summary_text .= "Average response time: " . round($summary['average_response_time'], 2) . "ms, ";
        $summary_text .= "Performance score: {$summary['performance_score']}/100. ";
        
        if (!empty($alerts)) {
            $summary_text .= count($alerts) . " alerts require attention.";
        }
        
        return $summary_text;
    }
    
    /**
     * Generate detailed analysis section
     *
     * @param array $metrics_data Metrics data
     * @return array Detailed analysis
     */
    private function generate_detailed_analysis($metrics_data) {
        $analysis = array();
        
        foreach ($metrics_data['data']['details'] as $collector_name => $collector_data) {
            if (isset($collector_data['summary'])) {
                $analysis[$collector_name] = array(
                    'summary' => $collector_data['summary'],
                    'insights' => $this->generate_collector_insights($collector_data)
                );
            }
        }
        
        return $analysis;
    }
    
    /**
     * Generate insights for a collector
     *
     * @param array $collector_data Collector data
     * @return array Insights
     */
    private function generate_collector_insights($collector_data) {
        $insights = array();
        
        if (isset($collector_data['summary']['error_rate'])) {
            $error_rate = $collector_data['summary']['error_rate'];
            if ($error_rate > 5) {
                $insights[] = "High error rate detected ({$error_rate}%). Investigate root causes.";
            } elseif ($error_rate > 0) {
                $insights[] = "Minor errors present ({$error_rate}%). Monitor for increases.";
            }
        }
        
        if (isset($collector_data['summary']['average_response_time'])) {
            $response_time = $collector_data['summary']['average_response_time'];
            if ($response_time > 3000) {
                $insights[] = "Slow response times detected. Consider performance optimization.";
            }
        }
        
        return $insights;
    }
    
    /**
     * Generate recommendations based on metrics
     *
     * @param array $metrics_data Metrics data
     * @return array Recommendations
     */
    private function generate_recommendations($metrics_data) {
        $recommendations = array();
        $summary = $metrics_data['data']['summary'];
        $alerts = $metrics_data['data']['alerts'];
        
        // Performance recommendations
        if ($summary['error_rate'] > 5) {
            $recommendations[] = array(
                'category' => 'Reliability',
                'priority' => 'High',
                'recommendation' => 'Investigate and resolve error sources to improve system reliability.',
                'impact' => 'High'
            );
        }
        
        if ($summary['average_response_time'] > 3000) {
            $recommendations[] = array(
                'category' => 'Performance',
                'priority' => 'Medium',
                'recommendation' => 'Optimize slow operations and consider implementing caching strategies.',
                'impact' => 'Medium'
            );
        }
        
        if ($summary['performance_score'] < 70) {
            $recommendations[] = array(
                'category' => 'Overall Performance',
                'priority' => 'Medium',
                'recommendation' => 'Conduct comprehensive performance audit to identify improvement areas.',
                'impact' => 'High'
            );
        }
        
        // Alert-based recommendations
        foreach ($alerts as $alert) {
            if ($alert['severity'] === 'warning') {
                $recommendations[] = array(
                    'category' => 'Alert Response',
                    'priority' => 'Low',
                    'recommendation' => "Address alert: {$alert['message']}",
                    'impact' => 'Low'
                );
            }
        }
        
        return $recommendations;
    }
    
    /**
     * Generate chart data for visualization
     *
     * @param array $metrics_data Metrics data
     * @return array Chart data
     */
    private function generate_charts_data($metrics_data) {
        $charts = array();
        
        // Performance over time chart
        $charts['performance_timeline'] = array(
            'type' => 'line',
            'title' => 'Performance Metrics Over Time',
            'data' => $this->generate_timeline_data($metrics_data)
        );
        
        // Error distribution chart
        $charts['error_distribution'] = array(
            'type' => 'pie',
            'title' => 'Error Distribution by Service',
            'data' => $this->generate_error_distribution_data($metrics_data)
        );
        
        return $charts;
    }
    
    /**
     * Generate timeline data for charts
     *
     * @param array $metrics_data Metrics data
     * @return array Timeline data
     */
    private function generate_timeline_data($metrics_data) {
        // This would generate time-series data for charts
        // For now, return placeholder structure
        return array(
            'labels' => array('Hour -3', 'Hour -2', 'Hour -1', 'Current'),
            'datasets' => array(
                array(
                    'label' => 'Response Time (ms)',
                    'data' => array(1200, 1500, 1100, 1300)
                ),
                array(
                    'label' => 'Error Rate (%)',
                    'data' => array(2.1, 1.8, 2.5, 1.9)
                )
            )
        );
    }
    
    /**
     * Generate error distribution data
     *
     * @param array $metrics_data Metrics data
     * @return array Error distribution data
     */
    private function generate_error_distribution_data($metrics_data) {
        $distribution = array();
        
        foreach ($metrics_data['data']['details'] as $collector_name => $collector_data) {
            if (isset($collector_data['summary']['failed_operations'])) {
                $distribution[] = array(
                    'label' => $collector_name,
                    'value' => $collector_data['summary']['failed_operations']
                );
            }
        }
        
        return $distribution;
    }
    
    /**
     * Inject real-time tracking script
     */
    public function inject_realtime_tracking() {
        if (!is_admin()) {
            ?>
            <script>
                // Real-time analytics tracking
                (function() {
                    // Track page load time
                    window.addEventListener('load', function() {
                        var loadTime = performance.now();
                        
                        // Send analytics data
                        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'action=aanp_realtime_track&load_time=' + loadTime
                        });
                    });
                })();
            </script>
            <?php
        }
    }
    
    /**
     * Handle AJAX realtime tracking
     */
    public function handle_realtime_tracking() {
        // Validate nonce
        if (!wp_verify_nonce($_POST['_wpnonce'], 'aanp_realtime_nonce')) {
            wp_die('Security check failed');
        }
        
        // Store real-time data
        $tracking_data = array(
            'load_time' => floatval($_POST['load_time']),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'timestamp' => current_time('mysql'),
            'ip_address' => $this->get_user_ip()
        );
        
        // Store in cache for real-time processing
        $this->cache_manager->set('realtime_track_' . time(), $tracking_data, 300);
        
        wp_send_json_success('Tracking data received');
    }
    
    /**
     * Get user IP address
     *
     * @return string IP address
     */
    private function get_user_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Clean up old analytics data
     */
    public function cleanup() {
        try {
            global $wpdb;
            
            $table_name = $wpdb->prefix . 'aanp_analytics';
            $cutoff_date = date('Y-m-d H:i:s', time() - ($this->config['retention_period_days'] * 24 * 60 * 60));
            
            $deleted = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$table_name} WHERE timestamp < %s",
                $cutoff_date
            ));
            
            // Clear caches
            $this->cache_manager->delete_by_pattern('historical_analytics_');
            $this->cache_manager->delete_by_pattern('realtime_track_');
            
            $this->logger->info('Analytics cleanup completed', array(
                'deleted_records' => $deleted,
                'cutoff_date' => $cutoff_date
            ));
            
        } catch (Exception $e) {
            $this->logger->error('Analytics cleanup failed', array(
                'error' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Get service metrics
     *
     * @return array Service metrics
     */
    public function get_metrics() {
        return array(
            'service' => 'AnalyticsService',
            'config' => $this->config,
            'collectors_count' => count($this->collectors),
            'collectors' => array_keys($this->collectors),
            'dashboard_cache_size' => count($this->dashboard_cache),
            'memory_usage' => memory_get_usage(true),
            'timestamp' => current_time('Y-m-d H:i:s')
        );
    }
    
    /**
     * Health check method
     *
     * @return bool Health status
     */
    public function health_check() {
        try {
            // Test database connection
            global $wpdb;
            $test_result = $wpdb->get_var('SELECT 1');
            
            if ($test_result !== '1') {
                return false;
            }
            
            // Test cache functionality
            $test_key = 'analytics_health_check_' . time();
            $test_data = array('test' => 'value');
            $this->cache_manager->set($test_key, $test_data, 60);
            $retrieved = $this->cache_manager->get($test_key);
            
            if ($retrieved !== $test_data) {
                return false;
            }
            
            // Test collectors
            foreach ($this->collectors as $collector_name => $collector) {
                if (method_exists($collector, 'health_check')) {
                    if (!$collector->health_check()) {
                        return false;
                    }
                }
            }
            
            // Clean up test data
            $this->cache_manager->delete($test_key);
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('AnalyticsService health check failed', array(
                'error' => $e->getMessage()
            ));
            return false;
        }
    }
}