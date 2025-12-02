<?php
/**
 * Service Metrics Collector
 *
 * Collects metrics from all AANP services including AI generation,
 * content creation, news fetching, and other microservices.
 *
 * @package AI_Auto_News_Poster\Analytics
 * @since 1.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Service Metrics Collector Class
 */
class AANP_Service_Metrics_Collector {
    
    /**
     * Logger instance
     * @var AANP_Logger
     */
    private $logger;
    
    /**
     * Cache manager instance
     * @var AANP_AdvancedCacheManager
     */
    private $cache_manager;
    
    /**
     * Constructor
     *
     * @param AANP_Logger $logger
     * @param AANP_AdvancedCacheManager $cache_manager
     */
    public function __construct(AANP_Logger $logger = null, AANP_AdvancedCacheManager $cache_manager = null) {
        $this->logger = $logger ?: AANP_Logger::getInstance();
        $this->cache_manager = $cache_manager ?: new AANP_AdvancedCacheManager();
    }
    
    /**
     * Collect service metrics
     *
     * @param array $params Collection parameters
     * @return array Collected metrics
     */
    public function collect($params = array()) {
        try {
            $metrics = array();
            
            // AI Generation Service metrics
            $metrics = array_merge($metrics, $this->collect_ai_generation_metrics());
            
            // Content Creation Service metrics
            $metrics = array_merge($metrics, $this->collect_content_creation_metrics());
            
            // News Fetch Service metrics
            $metrics = array_merge($metrics, $this->collect_news_fetch_metrics());
            
            // Analytics Service metrics
            $metrics = array_merge($metrics, $this->collect_analytics_service_metrics());
            
            // RankMath integration metrics
            $metrics = array_merge($metrics, $this->collect_rankmath_metrics());
            
            // Humanizer metrics
            $metrics = array_merge($metrics, $this->collect_humanizer_metrics());
            
            // Rate limiter metrics
            $metrics = array_merge($metrics, $this->collect_rate_limiter_metrics());
            
            $summary = $this->calculate_summary($metrics);
            
            $this->logger->debug('Service metrics collected', array(
                'metrics_count' => count($metrics),
                'summary' => $summary
            ));
            
            return array(
                'success' => true,
                'metrics' => $metrics,
                'summary' => $summary,
                'collected_at' => current_time('Y-m-d H:i:s')
            );
            
        } catch (Exception $e) {
            $this->logger->error('Service metrics collection failed', array(
                'error' => $e->getMessage()
            ));
            
            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'metrics' => array(),
                'summary' => array()
            );
        }
    }
    
    /**
     * Collect AI Generation Service metrics
     *
     * @return array AI generation metrics
     */
    private function collect_ai_generation_metrics() {
        $metrics = array();
        
        try {
            if (class_exists('AANP_AIGenerationService')) {
                $ai_service = new AANP_AIGenerationService(
                    new AANP_NewsFetchService(),
                    $this->cache_manager
                );
                
                $service_metrics = $ai_service->get_metrics();
                $metrics['ai_generation'] = array(
                    'service_healthy' => $ai_service->health_check(),
                    'available_providers' => count($service_metrics['available_providers'] ?? array()),
                    'cache_enabled' => $service_metrics['config']['enable_caching'] ?? false,
                    'default_provider' => $service_metrics['config']['default_provider'] ?? 'unknown',
                    'target_word_count' => $service_metrics['config']['target_word_count'] ?? 0,
                    'metrics_recorded' => !empty($service_metrics['metrics'])
                );
                
                // Process AI generation performance metrics
                if (isset($service_metrics['metrics'])) {
                    foreach ($service_metrics['metrics'] as $operation => $data) {
                        $metrics['ai_generation_' . $operation . '_calls'] = $data['total_calls'] ?? 0;
                        $metrics['ai_generation_' . $operation . '_success_rate'] = $data['total_calls'] > 0 
                            ? (($data['successful_calls'] ?? 0) / $data['total_calls']) * 100 
                            : 0;
                        $metrics['ai_generation_' . $operation . '_avg_time'] = $data['average_execution_time'] ?? 0;
                    }
                }
            } else {
                $metrics['ai_generation'] = array(
                    'service_healthy' => false,
                    'error' => 'Service not available'
                );
            }
        } catch (Exception $e) {
            $metrics['ai_generation'] = array(
                'service_healthy' => false,
                'error' => $e->getMessage()
            );
        }
        
        return $metrics;
    }
    
    /**
     * Collect Content Creation Service metrics
     *
     * @return array Content creation metrics
     */
    private function collect_content_creation_metrics() {
        $metrics = array();
        
        try {
            if (class_exists('AANP_ContentCreationService')) {
                $content_service = new AANP_ContentCreationService(
                    new AANP_AIGenerationService(new AANP_NewsFetchService(), $this->cache_manager),
                    $this->cache_manager
                );
                
                $service_metrics = $content_service->get_metrics();
                $metrics['content_creation'] = array(
                    'service_healthy' => $content_service->health_check(),
                    'featured_image_enabled' => $service_metrics['config']['featured_image_enabled'] ?? false,
                    'seo_optimization_enabled' => $service_metrics['config']['seo_optimization'] ?? false,
                    'auto_categorization_enabled' => $service_metrics['config']['auto_categorization'] ?? false,
                    'humanization_enabled' => $service_metrics['config']['humanization_enabled'] ?? false,
                    'rankmath_integration_enabled' => $service_metrics['config']['rankmath_integration'] ?? false,
                    'available_templates' => count($service_metrics['post_templates'] ?? array()),
                    'metrics_recorded' => !empty($service_metrics['metrics'])
                );
                
                // Process content creation performance metrics
                if (isset($service_metrics['metrics'])) {
                    foreach ($service_metrics['metrics'] as $operation => $data) {
                        $metrics['content_creation_' . $operation . '_calls'] = $data['total_calls'] ?? 0;
                        $metrics['content_creation_' . $operation . '_success_rate'] = $data['total_calls'] > 0 
                            ? (($data['successful_calls'] ?? 0) / $data['total_calls']) * 100 
                            : 0;
                        $metrics['content_creation_' . $operation . '_items_processed'] = $data['total_items_processed'] ?? 0;
                    }
                }
                
                // Get RankMath status
                $rankmath_status = $content_service->get_rankmath_status();
                $metrics['content_creation']['rankmath_available'] = $rankmath_status['available'] ?? false;
                
            } else {
                $metrics['content_creation'] = array(
                    'service_healthy' => false,
                    'error' => 'Service not available'
                );
            }
        } catch (Exception $e) {
            $metrics['content_creation'] = array(
                'service_healthy' => false,
                'error' => $e->getMessage()
            );
        }
        
        return $metrics;
    }
    
    /**
     * Collect News Fetch Service metrics
     *
     * @return array News fetch metrics
     */
    private function collect_news_fetch_metrics() {
        $metrics = array();
        
        try {
            if (class_exists('AANP_NewsFetchService')) {
                $news_service = new AANP_NewsFetchService($this->cache_manager);
                
                $service_metrics = $news_service->get_metrics();
                $metrics['news_fetch'] = array(
                    'service_healthy' => $news_service->health_check(),
                    'configured_feeds_count' => count($service_metrics['configured_feeds'] ?? array()),
                    'cache_enabled' => $service_metrics['cache_enabled'] ?? false,
                    'retry_attempts' => $service_metrics['retry_attempts'] ?? 0,
                    'timeout' => $service_metrics['timeout'] ?? 0,
                    'metrics_recorded' => !empty($service_metrics['metrics'])
                );
                
                // Process news fetch performance metrics
                if (isset($service_metrics['metrics'])) {
                    foreach ($service_metrics['metrics'] as $operation => $data) {
                        $metrics['news_fetch_' . $operation . '_calls'] = $data['total_calls'] ?? 0;
                        $metrics['news_fetch_' . $operation . '_success_rate'] = $data['total_calls'] > 0 
                            ? (($data['successful_calls'] ?? 0) / $data['total_calls']) * 100 
                            : 0;
                        $metrics['news_fetch_' . $operation . '_avg_time'] = $data['average_execution_time'] ?? 0;
                    }
                }
                
            } else {
                $metrics['news_fetch'] = array(
                    'service_healthy' => false,
                    'error' => 'Service not available'
                );
            }
        } catch (Exception $e) {
            $metrics['news_fetch'] = array(
                'service_healthy' => false,
                'error' => $e->getMessage()
            );
        }
        
        return $metrics;
    }
    
    /**
     * Collect Analytics Service metrics
     *
     * @return array Analytics service metrics
     */
    private function collect_analytics_service_metrics() {
        $metrics = array();
        
        try {
            if (class_exists('AANP_AnalyticsService')) {
                $analytics_service = new AANP_AnalyticsService($this->cache_manager);
                
                $service_metrics = $analytics_service->get_metrics();
                $metrics['analytics_service'] = array(
                    'service_healthy' => $analytics_service->health_check(),
                    'collectors_count' => $service_metrics['collectors_count'] ?? 0,
                    'available_collectors' => $service_metrics['collectors'] ?? array(),
                    'real_time_enabled' => $service_metrics['config']['enable_real_time'] ?? false,
                    'performance_tracking_enabled' => $service_metrics['config']['enable_performance_tracking'] ?? false,
                    'retention_period_days' => $service_metrics['config']['retention_period_days'] ?? 0,
                    'dashboard_cache_size' => $service_metrics['dashboard_cache_size'] ?? 0
                );
                
            } else {
                $metrics['analytics_service'] = array(
                    'service_healthy' => false,
                    'error' => 'Service not available'
                );
            }
        } catch (Exception $e) {
            $metrics['analytics_service'] = array(
                'service_healthy' => false,
                'error' => $e->getMessage()
            );
        }
        
        return $metrics;
    }
    
    /**
     * Collect RankMath integration metrics
     *
     * @return array RankMath metrics
     */
    private function collect_rankmath_metrics() {
        $metrics = array();
        
        try {
            if (class_exists('AANP_RankMathIntegration')) {
                $rankmath_integration = new AANP_RankMathIntegration($this->cache_manager);
                $integration_status = $rankmath_integration->get_integration_status();
                
                $metrics['rankmath'] = array(
                    'integration_healthy' => $rankmath_integration->health_check(),
                    'integration_available' => $integration_status['available'] ?? false,
                    'plugin_version' => $integration_status['plugin_version'] ?? 'unknown',
                    'integration_status' => $integration_status['status'] ?? 'unknown',
                    'seo_analysis_available' => $rankmath_integration->is_seo_analysis_available(),
                    'auto_optimization_available' => $rankmath_integration->is_auto_optimization_available()
                );
                
                // Auto-optimizer metrics
                if (class_exists('AANP_RankMathAutoOptimizer')) {
                    $auto_optimizer = new AANP_RankMathAutoOptimizer($rankmath_integration, null, $this->cache_manager);
                    $metrics['rankmath']['auto_optimizer_healthy'] = $auto_optimizer->health_check();
                }
                
                // SEO analyzer metrics
                if (class_exists('AANP_RankMathSEOAnalyzer')) {
                    $seo_analyzer = new AANP_RankMathSEOAnalyzer($rankmath_integration, null, null, $this->cache_manager);
                    $metrics['rankmath']['seo_analyzer_healthy'] = $seo_analyzer->health_check();
                }
                
            } else {
                $metrics['rankmath'] = array(
                    'integration_healthy' => false,
                    'error' => 'Service not available'
                );
            }
        } catch (Exception $e) {
            $metrics['rankmath'] = array(
                'integration_healthy' => false,
                'error' => $e->getMessage()
            );
        }
        
        return $metrics;
    }
    
    /**
     * Collect Humanizer metrics
     *
     * @return array Humanizer metrics
     */
    private function collect_humanizer_metrics() {
        $metrics = array();
        
        try {
            if (class_exists('AANP_HumanizerManager')) {
                $humanizer = new AANP_HumanizerManager($this->cache_manager);
                
                $metrics['humanizer'] = array(
                    'service_healthy' => $humanizer->health_check(),
                    'system_ready' => $humanizer->check_system_requirements()['ready'] ?? false,
                    'python_available' => $humanizer->check_system_requirements()['python_available'] ?? false,
                    'humano_package_available' => $humanizer->check_system_requirements()['humano_package'] ?? false,
                    'settings_available' => !empty($humanizer->get_settings())
                );
                
                // Test humanizer if system is ready
                if ($metrics['humanizer']['system_ready']) {
                    $test_result = $humanizer->test_humanizer();
                    $metrics['humanizer']['test_successful'] = $test_result['success'] ?? false;
                    $metrics['humanizer']['test_execution_time'] = $test_result['execution_time_ms'] ?? 0;
                }
                
            } else {
                $metrics['humanizer'] = array(
                    'service_healthy' => false,
                    'error' => 'Service not available'
                );
            }
        } catch (Exception $e) {
            $metrics['humanizer'] = array(
                'service_healthy' => false,
                'error' => $e->getMessage()
            );
        }
        
        return $metrics;
    }
    
    /**
     * Collect Rate Limiter metrics
     *
     * @return array Rate limiter metrics
     */
    private function collect_rate_limiter_metrics() {
        $metrics = array();
        
        try {
            if (class_exists('AANP_Rate_Limiter')) {
                $rate_limiter = new AANP_Rate_Limiter();
                
                // Get rate limit stats for different actions
                $actions = array('ai_generation', 'post_creation', 'image_generation', 'news_fetch');
                
                foreach ($actions as $action) {
                    $stats = $rate_limiter->get_rate_limit_stats($action);
                    $metrics['rate_limiter_' . $action] = array(
                        'total_attempts' => $stats['total_attempts'] ?? 0,
                        'current_window_attempts' => $stats['current_window_attempts'] ?? 0,
                        'is_limited' => $rate_limiter->is_rate_limited($action, 5, 3600),
                        'stats_available' => !empty($stats)
                    );
                }
                
            } else {
                $metrics['rate_limiter'] = array(
                    'service_healthy' => false,
                    'error' => 'Service not available'
                );
            }
        } catch (Exception $e) {
            $metrics['rate_limiter'] = array(
                'service_healthy' => false,
                'error' => $e->getMessage()
            );
        }
        
        return $metrics;
    }
    
    /**
     * Calculate service metrics summary
     *
     * @param array $metrics Individual metrics
     * @return array Service metrics summary
     */
    private function calculate_summary($metrics) {
        $total_services = 0;
        $healthy_services = 0;
        $total_operations = 0;
        $successful_operations = 0;
        $total_response_time = 0;
        $response_time_count = 0;
        
        foreach ($metrics as $service_name => $service_data) {
            if (is_array($service_data) && isset($service_data['service_healthy'])) {
                $total_services++;
                if ($service_data['service_healthy']) {
                    $healthy_services++;
                }
            }
            
            // Count operations and response times
            foreach ($service_data as $metric_name => $value) {
                if (strpos($metric_name, '_calls') !== false) {
                    $total_operations += $value;
                }
                if (strpos($metric_name, '_success_rate') !== false) {
                    $successful_operations += ($value / 100) * ($metrics[str_replace('_success_rate', '_calls', $metric_name)] ?? 0);
                }
                if (strpos($metric_name, '_avg_time') !== false) {
                    $total_response_time += $value;
                    $response_time_count++;
                }
            }
        }
        
        return array(
            'total_operations' => $total_operations,
            'successful_operations' => intval($successful_operations),
            'failed_operations' => $total_operations - intval($successful_operations),
            'average_response_time' => $response_time_count > 0 ? $total_response_time / $response_time_count : 0,
            'total_items_processed' => 0, // Not tracked at this level
            'error_rate' => $total_operations > 0 ? (($total_operations - $successful_operations) / $total_operations) * 100 : 0,
            'service_health_ratio' => $total_services > 0 ? $healthy_services / $total_services : 0,
            'healthy_services_count' => $healthy_services,
            'total_services_count' => $total_services,
            'performance_score' => $this->calculate_service_score($metrics)
        );
    }
    
    /**
     * Calculate service performance score
     *
     * @param array $metrics Service metrics
     * @return int Service score (0-100)
     */
    private function calculate_service_score($metrics) {
        $score = 100;
        $total_services = 0;
        $healthy_services = 0;
        
        foreach ($metrics as $service_data) {
            if (is_array($service_data) && isset($service_data['service_healthy'])) {
                $total_services++;
                if ($service_data['service_healthy']) {
                    $healthy_services++;
                }
            }
        }
        
        if ($total_services > 0) {
            $health_ratio = $healthy_services / $total_services;
            $score = $health_ratio * 100;
        }
        
        return max(0, min(100, intval($score)));
    }
    
    /**
     * Health check method
     *
     * @return bool Health status
     */
    public function health_check() {
        try {
            // Test cache functionality
            $test_key = 'service_health_check_' . time();
            $test_data = array('test' => 'value');
            $this->cache_manager->set($test_key, $test_data, 60);
            $retrieved = $this->cache_manager->get($test_key);
            
            if ($retrieved !== $test_data) {
                return false;
            }
            
            // Clean up test data
            $this->cache_manager->delete($test_key);
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('ServiceMetricsCollector health check failed', array(
                'error' => $e->getMessage()
            ));
            return false;
        }
    }
}