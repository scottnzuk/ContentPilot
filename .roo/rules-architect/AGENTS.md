# Project Architecture Rules (Non-Obvious Only)

## Critical Non-Obvious Architectural Patterns

### 1. Microservices Architecture within WordPress
- **Service Registry Pattern**: [`includes/core/ServiceRegistry.php`](includes/core/ServiceRegistry.php)
  - Custom dependency injection container for WordPress
  - Services must be registered with dependencies and priorities
  - Circular dependency detection and resolution
  - Health monitoring and service lifecycle management

- **Service Orchestrator Pattern**: [`includes/core/ServiceOrchestrator.php`](includes/core/ServiceOrchestrator.php)
  - Workflow coordination engine with retry logic
  - Parallel execution capabilities
  - Exponential backoff for failed services
  - Workflow rollback and recovery mechanisms

- **Service Communication**: Services communicate through the orchestrator, not directly
  - Prevents tight coupling between services
  - Enables centralized error handling and monitoring
  - Facilitates service replacement and scaling

### 2. Event-Driven Architecture
- **Observer Pattern**: [`includes/patterns/interface-observer.php`](includes/patterns/interface-observer.php)
  - Priority-based observer execution (higher numbers execute first)
  - Centralized event manager for event subscription and triggering
  - Event categories for better organization
  - Asynchronous event processing capabilities

- **Event Flow**:
  - `post_created` - Triggered after successful post creation
  - `content_verified` - Triggered after content verification
  - `ai_generation_complete` - Triggered after AI content generation
  - `rss_feed_processed` - Triggered after RSS feed processing
  - `error_occurred` - Triggered when errors occur

### 3. Content Processing Pipeline
- **Multi-Stage Processing**: Content flows through multiple verification and processing stages
  - RSS feed discovery and validation
  - Content extraction and normalization
  - Source credibility verification
  - Retraction detection
  - AI content generation
  - Humanization (optional)
  - SEO optimization
  - WordPress post creation

- **Verification Database**: [`includes/class-verification-database.php`](includes/class-verification-database.php)
  - Tracks source credibility scores (0-100 scale)
  - Records retraction detection history
  - Maintains content legitimacy records
  - Provides statistics for reporting

- **Content State Management**: Each content item maintains state throughout pipeline
  - `discovered` - RSS item discovered
  - `validated` - Content validated
  - `verified` - Source credibility verified
  - `retraction_checked` - Retraction detection completed
  - `ai_generated` - AI content generated
  - `humanized` - Content humanized (optional)
  - `seo_optimized` - SEO optimization completed
  - `post_created` - WordPress post created

### 4. AI Provider Abstraction
- **Strategy Pattern**: [`includes/patterns/interface-strategy.php`](includes/patterns/interface-strategy.php)
  - Interchangeable AI providers (OpenAI, Anthropic, OpenRouter)
  - Runtime strategy switching
  - Provider-specific configuration and validation
  - Fallback mechanisms between providers

- **Provider Integration**:
  - OpenAI: GPT-3.5, GPT-4, and custom models
  - Anthropic: Claude and Claude Instant
  - OpenRouter: Multiple provider routing
  - Custom providers can be added via strategy pattern

### 5. Performance Optimization Architecture
- **Dual-Layer Caching**: [`includes/class-cache-manager.php`](includes/class-cache-manager.php)
  - WordPress object cache (fast, in-memory)
  - Transients (persistent, database-backed)
  - Cache invalidation strategies
  - External cache integration (Cloudflare, LiteSpeed, etc.)

- **Connection Pooling**: [`includes/performance/ConnectionPoolManager.php`](includes/performance/ConnectionPoolManager.php)
  - Database connection pooling
  - HTTP connection pooling
  - Connection health monitoring
  - Automatic reconnection

- **Queue Management**: [`includes/performance/QueueManager.php`](includes/performance/QueueManager.php)
  - Task queue system for background processing
  - Worker health monitoring
  - Queue statistics and performance tracking
  - Priority-based task execution

### 6. Security Architecture
- **Defense in Depth**: Multiple layers of security protection
  - Input sanitization at all entry points
  - API key encryption using WordPress salts
  - Security headers (X-Content-Type-Options, X-Frame-Options, etc.)
  - Nonce verification for admin actions
  - Rate limiting for API calls
  - Comprehensive error handling without exposing sensitive information

- **API Key Management**: [`includes/class-admin-settings.php`](includes/class-admin-settings.php)
  - AES-256-CBC encryption using `wp_salt('auth')` as key
  - Proper initialization vector (IV) generation
  - Secure storage in WordPress options
  - Encrypted transmission to AI providers

### 7. Error Handling and Recovery Architecture
- **Centralized Error Handler**: [`includes/class-error-handler.php`](includes/class-error-handler.php)
  - Singleton pattern for global access
  - Error categorization (SYSTEM, NETWORK, API, DATABASE, SECURITY, etc.)
  - Automatic recovery strategies for different error types
  - Admin notification system
  - Comprehensive error context tracking

- **Recovery Strategies**:
  - Cache failure: Automatic cache clearing and reset
  - API failure: Rate limit reset and provider fallback
  - Network failure: Connection reset and retry
  - Database failure: Transaction rollback and reconnection

- **Graceful Degradation**: System continues functioning with reduced capabilities when components fail
  - Fallback to simpler algorithms
  - Reduced functionality modes
  - User-friendly error messages
  - Automatic recovery attempts

### 8. Data Integrity Architecture
- **Database Transactions**: Comprehensive transaction support for data integrity
  - Post creation with proper rollback on failure
  - Verification record creation with atomic operations
  - Analytics data collection with transaction support
  - Error recovery with transaction rollback

- **Data Validation**: Comprehensive data validation at all stages
  - Input validation for user-provided data
  - Content validation for RSS feeds
  - Verification validation for credibility scores
  - Output validation for generated content

### 9. SEO and EEAT Architecture
- **Content Analysis**: [`includes/seo/ContentAnalyzer.php`](includes/seo/ContentAnalyzer.php)
  - Readability scoring (Flesch-Kincaid, etc.)
  - SEO scoring (keyword density, meta tags, etc.)
  - EEAT scoring (Expertise, Authoritativeness, Trustworthiness)
  - Content structure analysis

- **EEAT Optimization**: [`includes/seo/EEATOptimizer.php`](includes/seo/EEATOptimizer.php)
  - Content optimization for EEAT compliance
  - Optimization level configuration (basic, advanced, expert)
  - Improvement scoring and recommendations
  - Author expertise integration

- **RankMath Integration**: [`includes/seo/RankMathIntegration.php`](includes/seo/RankMathIntegration.php)
  - Automatic SEO optimization
  - SERP analysis and recommendations
  - Content structure optimization
  - Schema markup generation

### 10. Testing and Validation Architecture
- **Comprehensive Test Coverage**: [`includes/testing/`](includes/testing/)
  - Microservices architecture testing
  - RSS feed system testing
  - Performance benchmarking
  - Integration testing between components
  - Health check validation

- **Test Patterns**:
  - Performance benchmarking included in tests
  - Health check validation for all services
  - Integration testing between components
  - Mock WordPress functions for isolated testing
  - Comprehensive error handling testing

## Critical Architectural Constraints

1. **WordPress Plugin Constraints**
   - Must follow WordPress plugin development patterns
   - Limited to WordPress hooks and filters for integration
   - Must work within WordPress security model
   - Must support WordPress multisite installations
   - Must be compatible with various WordPress hosting environments

2. **Service Dependency Constraints**
   - Services must be stateless to work with the orchestrator
   - Services must implement health check methods
   - Services must handle their own error recovery
   - Services must be registered with proper dependencies and priorities

3. **Content Processing Constraints**
   - Content must be verified before processing
   - Retracted content must be filtered out
   - Source credibility must be tracked
   - Content state must be maintained throughout pipeline

4. **Performance Constraints**
   - Must handle high volume of RSS feeds efficiently
   - Must process content generation within reasonable time limits
   - Must support caching at multiple levels
   - Must handle rate limiting gracefully
   - Must support external cache integration

5. **Security Constraints**
   - All inputs must be sanitized
   - API keys must be encrypted
   - Sensitive data must be redacted from logs
   - Security headers must be implemented
   - Nonce verification must be used for admin actions

6. **AI Provider Constraints**
   - Must support multiple AI providers
   - Must handle provider-specific rate limits
   - Must implement fallback mechanisms between providers
   - Must support provider-specific configuration
   - Must handle provider-specific error conditions

7. **Humanizer Constraints**
   - Offline humanization requires Python 3 and humano package
   - Must handle humanizer failures gracefully
   - Must support different humanization strength levels
   - Must handle timeout conditions (30 seconds)
   - Must provide fallback to original content

8. **Database Constraints**
   - Must use WordPress database abstraction (`$wpdb`)
   - Must use prepared statements for all queries
   - Must implement proper transaction handling
   - Must support proper table prefixing
   - Must handle database connection failures gracefully

## Architectural Decision Records

### 1. Microservices Architecture Decision
- **Decision**: Implement custom microservices architecture within WordPress
- **Rationale**: Provides modularity, scalability, and maintainability within WordPress constraints
- **Alternatives Considered**: Monolithic architecture, external microservices
- **Tradeoffs**: Increased complexity but better separation of concerns

### 2. Event-Driven Architecture Decision
- **Decision**: Implement observer pattern for event-driven architecture
- **Rationale**: Enables loose coupling between components and better extensibility
- **Alternatives Considered**: Direct method calls, WordPress hooks only
- **Tradeoffs**: Slightly increased complexity but better flexibility

### 3. AI Provider Abstraction Decision
- **Decision**: Implement strategy pattern for AI provider switching
- **Rationale**: Enables provider independence and easy switching between providers
- **Alternatives Considered**: Direct API calls, single provider integration
- **Tradeoffs**: Additional abstraction layer but better flexibility

### 4. Caching Architecture Decision
- **Decision**: Implement dual-layer caching with external cache integration
- **Rationale**: Provides both performance and persistence with fallback capabilities
- **Alternatives Considered**: Single-layer caching, no caching
- **Tradeoffs**: Increased complexity but better performance

### 5. Error Handling Architecture Decision
- **Decision**: Implement centralized error handler with recovery strategies
- **Rationale**: Provides consistent error handling and automatic recovery
- **Alternatives Considered**: Decentralized error handling, WordPress error handling only
- **Tradeoffs**: Additional complexity but better reliability

### 6. Content Verification Decision
- **Decision**: Implement comprehensive content verification system
- **Rationale**: Prevents publication of retracted or unreliable content
- **Alternatives Considered**: Basic verification, no verification
- **Tradeoffs**: Additional processing overhead but better content quality

### 7. Humanizer Integration Decision
- **Decision**: Implement offline humanization using Python script
- **Rationale**: Provides content humanization without external API dependencies
- **Alternatives Considered**: Online humanization services, no humanization
- **Tradeoffs**: Additional system requirements but better content quality

### 8. SEO and EEAT Optimization Decision
- **Decision**: Implement comprehensive SEO and EEAT optimization
- **Rationale**: Improves search engine rankings and content credibility
- **Alternatives Considered**: Basic SEO only, no SEO optimization
- **Tradeoffs**: Additional complexity but better content performance

## Architectural Recommendations

1. **Service Design Recommendations**
   - Keep services stateless for better scalability
   - Implement comprehensive health checks for all services
   - Design services with clear input/output contracts
   - Implement proper error handling and recovery
   - Document service dependencies and priorities

2. **Performance Optimization Recommendations**
   - Implement caching at all appropriate levels
   - Use connection pooling for database and HTTP connections
   - Implement queue management for background tasks
   - Monitor and optimize cache hit ratios
   - Implement proper cache invalidation strategies

3. **Security Recommendations**
   - Sanitize all inputs at entry points
   - Encrypt all sensitive data
   - Implement proper security headers
   - Use nonce verification for all admin actions
   - Redact sensitive data from logs
   - Implement rate limiting for all API calls

4. **Content Processing Recommendations**
   - Verify all content before processing
   - Track content state throughout pipeline
   - Implement proper error handling at each stage
   - Maintain source credibility records
   - Filter out retracted content

5. **Testing Recommendations**
   - Implement comprehensive test coverage
   - Include performance benchmarking in tests
   - Implement health check validation
   - Test integration between components
   - Mock WordPress functions for isolated testing
   - Test error handling and recovery scenarios

6. **Monitoring Recommendations**
   - Implement comprehensive logging
   - Monitor service health and performance
   - Track error rates and recovery success
   - Monitor cache performance and hit ratios
   - Track content processing metrics