# Project Documentation Rules (Non-Obvious Only)

## Critical Non-Obvious Documentation Context

### 1. Code Organization and Structure
- **Counterintuitive Structure**: [`includes/`](includes/) contains the core plugin functionality, not just "includes"
  - `includes/core/` - Microservices architecture core
  - `includes/services/` - Business logic services
  - `includes/patterns/` - Design pattern implementations
  - `includes/performance/` - Performance optimization components
  - `includes/seo/` - SEO and EEAT optimization features

- **Admin Interface**: [`admin/`](admin/) directory contains WordPress admin pages
  - `admin/settings-page.php` - Main settings page
  - `admin/rss-feeds-page.php` - RSS feed management
  - `admin/verification-page.php` - Content verification dashboard
  - `admin/dashboard/` - Modern dashboard interface (PWA-ready)

- **Humanizer Integration**: [`ai-auto-news-poster-humanizer/`](ai-auto-news-poster-humanizer/) contains Python script for offline content humanization
  - Requires Python 3 and `humano` package
  - Communicates via JSON interface
  - Has separate system requirements

### 2. Hidden Documentation Sources
- **Implementation Reports**: Comprehensive documentation in root directory files:
  - [`CONTENT_VERIFICATION_SYSTEM_IMPLEMENTATION_REPORT.md`](CONTENT_VERIFICATION_SYSTEM_IMPLEMENTATION_REPORT.md) - Content verification system details
  - [`RSS_FEED_SYSTEM_DOCUMENTATION.md`](RSS_FEED_SYSTEM_DOCUMENTATION.md) - RSS feed management documentation
  - [`RANKMATH_SEO_INTEGRATION_REPORT.md`](RANKMATH_SEO_INTEGRATION_REPORT.md) - SEO integration details
  - [`SECURITY_IMPROVEMENTS_REPORT.md`](SECURITY_IMPROVEMENTS_REPORT.md) - Security implementation details

- **Test Reports**: Comprehensive testing documentation:
  - [`COMPREHENSIVE_TESTING_REPORT.md`](COMPREHENSIVE_TESTING_REPORT.md) - Full test suite results
  - [`MICROSERVICES_TESTING_VALIDATION_REPORT.md`](MICROSERVICES_TESTING_VALIDATION_REPORT.md) - Microservices test results
  - [`PERFORMANCE_BENCHMARK_VALIDATION.md`](PERFORMANCE_BENCHMARK_VALIDATION.md) - Performance testing results

### 3. Microservices Architecture Documentation
- **Service Registry**: [`includes/core/ServiceRegistry.php`](includes/core/ServiceRegistry.php)
  - Custom dependency injection container
  - Services must be registered with dependencies and priorities
  - Health check method available: `health_check()`

- **Service Orchestrator**: [`includes/core/ServiceOrchestrator.php`](includes/core/ServiceOrchestrator.php)
  - Workflow coordination engine
  - Supports parallel execution
  - Implements retry logic with exponential backoff
  - Workflow configuration patterns

- **Service Directories**:
  - [`includes/services/`](includes/services/) - Business services
    - `AIGenerationService.php` - AI content generation
    - `NewsFetchService.php` - RSS feed processing
    - `ContentCreationService.php` - WordPress post creation
    - `AnalyticsService.php` - Performance analytics

### 4. Content Processing Pipeline
- **RSS Feed Processing**: [`includes/class-news-fetch.php`](includes/class-news-fetch.php)
  - Feed discovery and validation
  - Content extraction and normalization
  - Source credibility scoring
  - Retraction detection

- **Content Verification**: [`includes/class-content-verifier.php`](includes/class-content-verifier.php)
  - Keyword-based retraction detection
  - Source credibility scoring (0-100 scale)
  - Domain reputation tracking
  - Historical verification records

- **AI Content Generation**: [`includes/class-ai-generator.php`](includes/class-ai-generator.php)
  - Multi-provider support (OpenAI, Anthropic, OpenRouter)
  - Strategy pattern for provider switching
  - Content optimization parameters

- **Post Creation**: [`includes/class-post-creator.php`](includes/class-post-creator.php)
  - WordPress post creation with transaction support
  - Enhanced source attribution
  - Verification status tracking
  - SEO optimization integration

### 5. Hidden Configuration Options
- **WordPress Settings**: Stored in `wp_options` table with prefix `aanp_`
  - `aanp_settings` - Main plugin settings
  - `aanp_rss_feeds` - RSS feed configuration
  - `aanp_verification_records` - Content verification history
  - `aanp_analytics` - Performance analytics data

- **Environment Variables**:
  - `CP_DEBUG` - Enable debug mode (set to `true`)
  - `CP_HUMANIZER_DISABLE` - Disable humanizer integration
  - `CP_CACHE_DISABLE` - Disable caching system

- **Hidden Admin Pages**:
  - `/wp-admin/admin.php?page=aanp-verification` - Content verification dashboard
  - `/wp-admin/admin.php?page=aanp-rss-feeds` - RSS feed management
  - `/wp-admin/admin.php?page=aanp-performance` - Performance monitoring
  - `/wp-admin/admin.php?page=aanp-dashboard` - Modern dashboard interface

### 6. Testing and Validation Documentation
- **Test Suites**: [`includes/testing/`](includes/testing/)
  - [`test-microservices-suite.php`](includes/testing/test-microservices-suite.php) - Comprehensive microservices testing
  - [`test-rss-feed-system.php`](includes/testing/test-rss-feed-system.php) - RSS feed system testing
  - Access via WordPress admin with `?test_rss_system=1` parameter

- **Test Patterns**:
  - Performance benchmarking included in tests
  - Health check validation for all services
  - Integration testing between components
  - Mock WordPress functions for isolated testing

- **Test Reports**:
  - [`COMPREHENSIVE_TESTING_REPORT.md`](COMPREHENSIVE_TESTING_REPORT.md) - Full test results
  - [`MICROSERVICES_TESTING_VALIDATION_REPORT.md`](MICROSERVICES_TESTING_VALIDATION_REPORT.md) - Microservices validation
  - [`PERFORMANCE_BENCHMARK_VALIDATION.md`](PERFORMANCE_BENCHMARK_VALIDATION.md) - Performance metrics

### 7. Security Implementation Details
- **API Key Encryption**: [`includes/class-admin-settings.php`](includes/class-admin-settings.php)
  - AES-256-CBC encryption using `wp_salt('auth')` as key
  - Proper initialization vector (IV) generation
  - Secure storage in WordPress options

- **Input Sanitization**: [`includes/class-security-manager.php`](includes/class-security-manager.php)
  - Deep sanitization of nested arrays
  - Security headers (X-Content-Type-Options, X-Frame-Options, etc.)
  - API response validation to prevent XSS

- **Security Reports**:
  - [`SECURITY_IMPROVEMENTS_REPORT.md`](SECURITY_IMPROVEMENTS_REPORT.md) - Security implementation details
  - [`SECURITY_VULNERABILITY_ASSESSMENT.md`](SECURITY_VULNERABILITY_ASSESSMENT.md) - Vulnerability assessment

### 8. Performance Optimization
- **Caching System**: [`includes/class-cache-manager.php`](includes/class-cache-manager.php)
  - Dual-layer caching (WordPress object cache + transients)
  - External cache integration (Cloudflare, LiteSpeed, etc.)
  - Cache invalidation strategies

- **Connection Pooling**: [`includes/performance/ConnectionPoolManager.php`](includes/performance/ConnectionPoolManager.php)
  - Database connection pooling
  - HTTP connection pooling
  - Connection health monitoring

- **Queue Management**: [`includes/performance/QueueManager.php`](includes/performance/QueueManager.php)
  - Task queue system
  - Worker health monitoring
  - Queue statistics tracking

- **Performance Reports**:
  - [`PERFORMANCE_OPTIMIZATION_IMPROVEMENTS.md`](PERFORMANCE_OPTIMIZATION_IMPROVEMENTS.md) - Performance optimizations
  - [`PERFORMANCE_BENCHMARK_VALIDATION.md`](PERFORMANCE_BENCHMARK_VALIDATION.md) - Benchmark results

### 9. SEO and EEAT Optimization
- **Content Analyzer**: [`includes/seo/ContentAnalyzer.php`](includes/seo/ContentAnalyzer.php)
  - Readability scoring
  - SEO scoring
  - Keyword density analysis
  - EEAT (Expertise, Authoritativeness, Trustworthiness) scoring

- **EEAT Optimizer**: [`includes/seo/EEATOptimizer.php`](includes/seo/EEATOptimizer.php)
  - Content optimization for EEAT compliance
  - Optimization level configuration
  - Improvement scoring

- **SEO Integration**: [`includes/seo/RankMathIntegration.php`](includes/seo/RankMathIntegration.php)
  - RankMath SEO plugin integration
  - Automatic SEO optimization
  - SERP analysis

- **SEO Reports**:
  - [`RANKMATH_SEO_INTEGRATION_REPORT.md`](RANKMATH_SEO_INTEGRATION_REPORT.md) - SEO integration details
  - [`ENHANCED_ADMIN_INTERFACE_IMPLEMENTATION_FINAL_REPORT.md`](ENHANCED_ADMIN_INTERFACE_IMPLEMENTATION_FINAL_REPORT.md) - SEO dashboard features

### 10. Hidden Features and Capabilities
- **Offline Humanization**: [`includes/class-humanizer.php`](includes/class-humanizer.php)
  - Python-based content humanization
  - Strength level configuration (low, medium, high, maximum)
  - Custom personality settings
  - System requirements check

- **Realtime Monitoring**: [`includes/class-realtime-monitor.php`](includes/class-realtime-monitor.php)
  - System health monitoring
  - Performance metrics tracking
  - Alert generation

- **Analytics System**: [`includes/analytics/`](includes/analytics/)
  - Content analytics collection
  - Performance metrics collection
  - Service metrics collection
  - User analytics collection

- **GraphQL API**: [`includes/api/GraphQLEndpoint.php`](includes/api/GraphQLEndpoint.php)
  - GraphQL endpoint for data access
  - Query-based data retrieval
  - Authentication and authorization

## Critical Documentation Gotchas

1. **Misleading Directory Names**: The `includes/` directory contains core functionality, not just includes
2. **Hidden Documentation**: Comprehensive documentation exists in root-level `.md` files, not just in code comments
3. **Counterintuitive Architecture**: This is a microservices architecture implemented within a WordPress plugin
4. **Humanizer Dependencies**: Offline humanization requires Python 3 and the humano package
5. **Cache Invalidation**: External cache systems (Cloudflare, etc.) require manual configuration
6. **Test Access**: Some tests are only accessible via WordPress admin interface
7. **Security Implementation**: API keys are encrypted using WordPress salts, not plaintext
8. **Performance Features**: Connection pooling and queue management are implemented but not obvious
9. **SEO Integration**: RankMath integration provides automatic SEO optimization
10. **Hidden Admin Pages**: Several admin pages are not linked from the main menu

## Documentation Access Patterns

- **View implementation reports**: `cat CONTENT_VERIFICATION_SYSTEM_IMPLEMENTATION_REPORT.md`
- **View test reports**: `cat COMPREHENSIVE_TESTING_REPORT.md`
- **View security reports**: `cat SECURITY_IMPROVEMENTS_REPORT.md`
- **View performance reports**: `cat PERFORMANCE_BENCHMARK_VALIDATION.md`
- **Access hidden admin pages**: `/wp-admin/admin.php?page=aanp-verification`
- **View log files**: `tail -n 100 wp-content/uploads/ai-auto-news-poster.log`