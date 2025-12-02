# Changelog

## Version 1.1.0 (Enhanced) - 2025-01-XX

### 🚀 New Features
- **OpenRouter Support**: Added OpenRouter as a new LLM provider option
- **Advanced Caching System**: Full cache support for OpenLiteSpeed, W3 Total Cache, WP Super Cache, WP Rocket, and Cloudflare
- **Rate Limiting**: Implemented rate limiting to prevent API abuse and improve security
- **Security Manager**: Enhanced security with input validation, response filtering, and security headers
- **Performance Optimizer**: Database query optimization and async script loading
- **Cache Management UI**: Admin interface for cache statistics and purging

### 🔒 Security Improvements
- **Enhanced API Key Encryption**: Improved encryption using SHA-256 hashed keys
- **Input Sanitization**: Comprehensive input validation and sanitization
- **Security Headers**: Added X-Content-Type-Options, X-Frame-Options, X-XSS-Protection headers
- **Response Validation**: AI response content filtering for suspicious patterns
- **CSRF Protection**: Enhanced nonce verification across all AJAX endpoints

### ⚡ Performance Enhancements
- **Multi-level Caching**: WordPress object cache + transient cache fallback
- **RSS Feed Caching**: 30-minute cache for RSS feed data
- **Statistics Caching**: 5-minute cache for dashboard statistics
- **Database Optimization**: Query optimization with proper indexing
- **Async Script Loading**: Non-blocking JavaScript execution

### 🛠️ Technical Improvements
- **Modular Architecture**: Separated concerns into dedicated classes
- **Cache Purging**: Automatic cache invalidation on content updates
- **External Cache Integration**: Support for popular caching plugins
- **Error Handling**: Improved error logging and user feedback
- **Code Quality**: Enhanced code structure and documentation

### 🔧 Configuration Options
- **Cache Settings**: New admin section for cache management
- **Performance Metrics**: Real-time performance monitoring
- **Security Validation**: Configurable security checks
- **Rate Limit Controls**: Customizable rate limiting parameters

### 📁 New Files Added
- `includes/class-cache-manager.php` - Comprehensive caching system
- `includes/class-rate-limiter.php` - API rate limiting functionality
- `includes/class-security-manager.php` - Security enhancements
- `includes/class-performance-optimizer.php` - Performance optimizations
- `.htaccess` - Security and performance rules

### 🐛 Bug Fixes
- Fixed API key encryption fallback vulnerability
- Improved error handling for failed API requests
- Enhanced input validation for RSS feeds
- Better handling of malformed AI responses

### 🔄 Breaking Changes
- API keys now require OpenSSL encryption (no base64 fallback)
- Minimum PHP version remains 7.4+
- Enhanced security may require re-entering API keys

---

## Version 1.0.6 (Original)
- Initial release with basic functionality
- OpenAI and Anthropic API support
- RSS feed integration
- Basic post generation (5 posts limit)
- WordPress admin interface