# Project Coding Rules (Non-Obvious Only)

## Service Architecture
- **Service Registration**: Services must be registered with priorities (news-fetch:10, ai-generation:15, content-creation:20) via [`includes/core/ServiceRegistry.php`](includes/core/ServiceRegistry.php): `$registry->register_service('name', 'Class', $deps, 10)`
- **Workflow Orchestration**: Use [`includes/core/ServiceOrchestrator.php`](includes/core/ServiceOrchestrator.php) with retry logic (3 retries, 1000ms delay) and rollback capabilities
- **Command Pattern**: Commands must implement undo support and parameter sanitization [`includes/patterns/interface-command.php`](includes/patterns/interface-command.php)
- **Observer Pattern**: Priority-based observer execution with event-specific interest filtering [`includes/patterns/interface-observer.php`](includes/patterns/interface-observer.php)
- **Strategy Pattern**: AI provider abstraction with provider-specific prompt optimization [`includes/patterns/interface-strategy.php`](includes/patterns/interface-strategy.php)

## Content Processing
- **Content Verification**: Mandatory verification before processing via [`includes/class-content-verifier.php`](includes/class-content-verifier.php): `$verifier->detect_retracted_content($url, $content)`
- **Content Templates**: SEO-focused content templates with readability scoring [`includes/services/ContentTemplateManager.php`](includes/services/ContentTemplateManager.php)
- **Duplicate Detection**: Content similarity scoring for duplicate prevention [`includes/services/ContentFilterManager.php`](includes/services/ContentFilterManager.php)

## Error Handling
- Centralized: `CP_Error_Handler::getInstance()->handle_error($msg, $context, $category)` [`includes/class-error-handler.php`](includes/class-error-handler.php)
- Custom exceptions from [`includes/class-exceptions.php`](includes/class-exceptions.php)

## Performance Optimization
- **Rate Limiting**: Check before API calls via [`includes/class-rate-limiter.php`](includes/class-rate-limiter.php): `$rate_limiter->is_rate_limited('action', $limit, $window)`
- **Caching**: Invalidate after modifications via [`includes/class-cache-manager.php`](includes/class-cache-manager.php): `$cache_manager->delete($key)`

## Security
- Sanitize: `ContentPilot_Security_Manager->deep_sanitize($data)` [`includes/class-security-manager.php`](includes/class-security-manager.php)
- API keys encrypted with `wp_salt('auth')`

## Database
- Transactions for posts: `$wpdb->query('START TRANSACTION'); ... COMMIT/ROLLBACK`

## Humanizer
- Python3 + `pip install humano`; `ai-auto-news-poster-humanizer/humanizer.py`

## Gotchas
- **WordPress Plugin Constraints**: Must use WordPress hooks/filters instead of direct function calls
- **Service Dependencies**: Services must be stateless and implement health checks
- **Content Processing**: Mandatory verification before processing (retraction detection, source credibility)
- No Composer; pure PHP
- Tests: standalone PHP scripts