# Project Debug Rules (Non-Obvious Only)

## Critical Debugging Extensions

### Service Debugging
- **Service Health Checks**: `$registry->health_check()` for service registry debugging [`includes/core/ServiceRegistry.php`](includes/core/ServiceRegistry.php)
- **Workflow Statistics**: ServiceOrchestrator tracks execution time and success/failure rates [`includes/core/ServiceOrchestrator.php`](includes/core/ServiceOrchestrator.php)

### Cache Debugging
- **Cache Statistics**: `$cache_manager->get_cache_stats()` for cache debugging [`includes/class-cache-manager.php`](includes/class-cache-manager.php)

### Rate Limit Debugging
- **Rate Limit Debugging**: `$rate_limiter->is_rate_limited('ai_generation', 10, 3600)` for rate limit checks [`includes/class-rate-limiter.php`](includes/class-rate-limiter.php)

### Humanizer Debugging
- **Humanizer Debugging**: `$humanizer->get_system_status()` and `$humanizer->get_installation_instructions()` [`includes/class-humanizer.php`](includes/class-humanizer.php)

### AI Provider Debugging
- **AI Provider Debugging**: `$ai_context->get_available_providers()` and `$ai_context->validate_api_key('openai', $api_key)` [`includes/class-ai-generator.php`](includes/class-ai-generator.php)

## Critical Non-Obvious Debugging Patterns

### 1. Error Handling System
- **Centralized Error Handler**: All errors flow through [`includes/class-error-handler.php`](includes/class-error-handler.php)
  - Singleton pattern: `CP_Error_Handler::getInstance()`
  - Error categories: SYSTEM, NETWORK, API, DATABASE, SECURITY, USER_INPUT, CONFIGURATION, PERFORMANCE
  - Admin notices automatically generated for critical errors
  - Recovery strategies automatically triggered for specific error types

- **Error Context Tracking**: Comprehensive context stored with each error
  - Memory usage, request URI, user ID, timestamp
  - Stack traces for uncaught exceptions
  - Error categorization based on file patterns and message content

### 2. Logging System
- **Logger**: [`includes/class-logger.php`](includes/class-logger.php) implements comprehensive logging
  - Log levels: DEBUG, INFO, WARNING, ERROR, CRITICAL
  - Sensitive data redaction (API keys, passwords, tokens)
  - Log file location: `wp-content/uploads/ai-auto-news-poster.log`
  - Log rotation when file exceeds 10MB

- **Log Access**:
  - Recent logs available via `ContentPilot_Logger->get_recent_logs($lines = 100)`
  - Log file can be cleared with `ContentPilot_Logger->clear_logs()`
  - Log level can be adjusted with `ContentPilot_Logger->set_min_level($level)`

### 3. Debugging Hidden Issues

#### Cache-Related Issues
- **Cache Invalidation**: Stale cache can cause data inconsistencies
  - Clear object cache: `wp_cache_flush()`
  - Clear transients: `delete_transient('aanp_*')`
  - Clear external caches: Cloudflare, LiteSpeed, etc.

- **Cache Debugging**:
  ```php
  $cache_manager = new ContentPilot_Cache_Manager();
  $stats = $cache_manager->get_cache_stats();
  // Check cache hit/miss ratios
  ```

#### Rate Limiting Issues
- **Rate Limit Debugging**:
  ```php
  $rate_limiter = new ContentPilot_Rate_Limiter();
  $is_limited = $rate_limiter->is_rate_limited('ai_generation', 10, 3600);
  $stats = $rate_limiter->get_rate_limit_stats();
  ```

- **Rate Limit Reset**:
  ```php
  $rate_limiter->reset_limit('api_request');
  ```

#### Service Dependency Issues
- **Service Registry Debugging**:
  ```php
  $registry = new ContentPilot_ServiceRegistry();
  $health_status = $registry->health_check();
  $services = $registry->get_registered_services();
  ```

- **Service Orchestrator Debugging**:
  ```php
  $orchestrator = new ContentPilot_ServiceOrchestrator($registry);
  $workflow_result = $orchestrator->execute_workflow('workflow_name', $params);
  // Check $workflow_result for execution details
  ```

### 4. Content Verification Debugging
- **Retraction Detection**:
  ```php
  $verifier = new ContentPilot_ContentVerifier();
  $result = $verifier->detect_retracted_content($url, $content);
  // Check $result['retracted'], $result['confidence'], $result['keywords_found']
  ```

- **Source Credibility**:
  ```php
  $credibility = $verifier->get_domain_credibility($domain);
  // Check $credibility['score'], $credibility['status']
  ```

- **Verification Database**:
  ```php
  $verification_db = new ContentPilot_VerificationDatabase();
  $stats = $verification_db->get_verification_stats();
  $records = $verification_db->get_verification_records(array('limit' => 10));
  ```

### 5. Humanizer Debugging
- **System Requirements Check**:
  ```php
  $humanizer = new ContentPilot_HumanizerManager();
  $status = $humanizer->get_system_status();
  // Check $status['python_available'], $status['humano_available'], etc.
  ```

- **Humanizer Test**:
  ```php
  $test_result = $humanizer->test_humanizer();
  // Check $test_result['success'], $test_result['test_metadata']
  ```

- **Installation Instructions**:
  ```php
  $instructions = $humanizer->get_installation_instructions();
  // Provides step-by-step installation commands
  ```

### 6. AI Provider Debugging
- **Provider Configuration**:
  ```php
  $ai_context = new ContentPilot_AI_Generator_Context();
  $providers = $ai_context->get_available_providers();
  // Check which providers are configured and available
  ```

- **API Key Validation**:
  ```php
  $is_valid = $ai_context->validate_api_key('openai', $api_key);
  // Test API key validity before use
  ```

- **Usage Statistics**:
  ```php
  $stats = $ai_context->get_usage_stats();
  // Check generation counts, average times, etc.
  ```

### 7. Database Debugging
- **Transaction Debugging**: Always check for proper transaction handling
  ```php
  global $wpdb;
  // Check if transaction is active
  $in_transaction = $wpdb->get_var("SELECT @@in_transaction");
  ```

- **Verification Database Tables**:
  - `wp_aanp_verified_sources` - Source credibility tracking
  - `wp_aanp_content_verification` - Content verification records
  - Check table existence with `SHOW TABLES LIKE 'wp_aanp_%'`

### 8. Webhook and API Debugging
- **REST API Endpoints**:
  - Check if endpoints are registered: `GET /wp-json/aanp/v1/*`
  - Test with `wp_remote_get()` or `wp_remote_post()`

- **GraphQL Endpoint**:
  - Check if GraphQL endpoint is available: `GET /wp-json/aanp/graphql`
  - Test with GraphQL queries

### 9. Performance Debugging
- **Connection Pool Debugging**:
  ```php
  $pool_manager = new ContentPilot_ConnectionPoolManager();
  $stats = $pool_manager->get_pool_statistics();
  $db_health = $pool_manager->check_database_health();
  $http_health = $pool_manager->check_http_health();
  ```

- **Queue Manager Debugging**:
  ```php
  $queue_manager = new ContentPilot_QueueManager();
  $stats = $queue_manager->get_queue_statistics();
  $worker_health = $queue_manager->check_worker_health();
  ```

### 10. Admin Interface Debugging
- **Hidden Debug Pages**:
  - Verification system: `/wp-admin/admin.php?page=aanp-verification`
  - RSS feed management: `/wp-admin/admin.php?page=aanp-rss-feeds`
  - Performance monitoring: `/wp-admin/admin.php?page=aanp-performance`

- **Debug Mode**: Enable with `define('CP_DEBUG', true);` in `wp-config.php`
  - Provides additional error details
  - Enables debug logging
  - Shows performance metrics

## Critical Debugging Gotchas

1. **Silent Failures**: Many systems fail silently with graceful degradation
   - Always check return values for `success` flags
   - Look for `error` keys in response arrays
   - Check logs for suppressed errors

2. **Cache Issues**: Stale cache can cause data inconsistencies
   - Always clear cache after data modifications
   - Check cache hit/miss ratios
   - Verify external cache systems (Cloudflare, etc.)

3. **Rate Limiting**: API calls fail silently when rate limited
   - Check rate limit status before making calls
   - Monitor rate limit usage
   - Implement proper fallback mechanisms

4. **Humanizer Dependencies**: Offline humanization requires Python 3 and humano package
   - Check system requirements before enabling
   - Test with `ContentPilot_HumanizerManager->test_humanizer()`
   - Verify Python script is executable

5. **Service Dependencies**: Missing dependencies cause silent service failures
   - Check service registry health
   - Verify all dependencies are registered
   - Test service orchestration workflows

6. **Database Transactions**: Failed transactions can leave data in inconsistent state
   - Always use proper transaction patterns
   - Check for active transactions
   - Verify rollback on failure

7. **Content Verification**: False positives/negatives in retraction detection
   - Check confidence scores
   - Review keywords detected
   - Verify source credibility scores

8. **WordPress Integration**: Plugin-specific hooks and filters
   - Check if hooks are properly registered
   - Verify nonce validation
   - Test admin interface integration

## Debugging Commands

- **View recent logs**: `tail -n 100 wp-content/uploads/ai-auto-news-poster.log`
- **Clear logs**: `truncate -s 0 wp-content/uploads/ai-auto-news-poster.log`
- **Check Python version**: `python3 --version`
- **Check humano package**: `python3 -c "import humano; print(humano.__version__)"`
- **Test database connection**: `wp db check` (WP-CLI)
- **Check table existence**: `wp db query "SHOW TABLES LIKE 'wp_aanp_%'"` (WP-CLI)