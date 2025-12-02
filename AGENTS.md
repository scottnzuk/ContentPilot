# AGENTS.md

This file provides guidance to agents when working with code in this repository.

## Build/Lint/Test Commands
- Microservices tests: `php includes/testing/test-microservices-suite.php`
- RSS system tests: WP admin `?test_rss_system=1`

No package manager/build tools (pure PHP WordPress plugin). No linter configs found.

## Code Style (Non-Obvious)
- Classes: `ContentPilot_ClassName` prefix
- Files: `class-[name].php`
- Error handling: `ContentPilot_Error_Handler::getInstance()->handle_error()` (not raw exceptions)
- DB: Transactions mandatory for post creation w/ rollback

## Critical Patterns

### Core Architecture
- **Universal Installation System**: Version 1.3.0+ includes sophisticated installation wizard [`includes/class-installation-wizard.php`](includes/class-installation-wizard.php) with system checks, database setup, and hosting optimization for any environment
- **Service Registration**: Services must be registered with priorities via [`includes/core/ServiceRegistry.php`](includes/core/ServiceRegistry.php): `$registry->register_service('name', 'Class', $deps, 10)`
- **Workflow Orchestration**: Use [`includes/core/ServiceOrchestrator.php`](includes/core/ServiceOrchestrator.php) with retry logic (3 retries, 1000ms delay) and rollback capabilities

### Content Processing
- **Content Verification**: Mandatory verification before processing via [`includes/class-content-verifier.php`](includes/class-content-verifier.php): `$verifier->detect_retracted_content($url, $content)`
- **Content Humanization**: Offline Python-based humanization with configurable strength levels [`contentpilot-humanizer/humanizer.py`](contentpilot-humanizer/humanizer.py)
- **Duplicate Detection**: Content similarity scoring for duplicate prevention [`includes/services/ContentFilterManager.php`](includes/services/ContentFilterManager.php)

### Performance Optimization
- **Performance Architecture**: Connection pooling [`includes/performance/ConnectionPoolManager.php`](includes/performance/ConnectionPoolManager.php), queue management [`includes/performance/QueueManager.php`](includes/performance/QueueManager.php), and dual-layer caching system
- **Rate Limiting**: Check before API calls via [`includes/class-rate-limiter.php`](includes/class-rate-limiter.php): `$rate_limiter->is_rate_limited('action', $limit, $window)`
- **Cache Management**: Invalidate after modifications via [`includes/class-cache-manager.php`](includes/class-cache-manager.php): `$cache_manager->delete($key)`

### SEO and Analytics
- **Advanced SEO Integration**: Deep RankMath SEO plugin integration [`includes/seo/RankMathIntegration.php`](includes/seo/RankMathIntegration.php) with auto-optimization of titles, meta descriptions, and content structure
- **Analytics System**: Comprehensive metrics collection with trend analysis, alert thresholds, and real-time tracking [`includes/analytics/`](includes/analytics/)

## Gotchas
- **WordPress Plugin Constraints**: Must use WordPress hooks/filters instead of direct function calls
- **Service Dependencies**: Services must be stateless and implement health checks
- **Content Processing**: Mandatory verification before processing (retraction detection, source credibility)
- No Composer/Package.json; pure PHP
- Tests are standalone PHP scripts (no PHPUnit)
- Microservices in WP: Use orchestrator for workflows