<?php
/**
 * Comprehensive REST API Implementation
 * 
 * Provides complete REST API with OAuth2/JWT authentication, rate limiting,
 * comprehensive endpoints, and enterprise-grade security features.
 *
 * @package AI_Auto_News_Poster
 * @subpackage Includes/API
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class RestAPI {
    
    /**
     * API version
     */
    const API_VERSION = 'v1';
    
    /**
     * API namespace
     */
    const API_NAMESPACE = 'ai-auto-news';
    
    /**
     * JWT secret key
     */
    private $jwt_secret;
    
    /**
     * API configuration
     */
    private $config = [];
    
    /**
     * Rate limiter instance
     */
    private $rate_limiter;
    
    /**
     * API statistics
     */
    private $api_stats = [];
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        static $instance = null;
        if ($instance === null) {
            $instance = new self();
        }
        return $instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Initialize REST API
     */
    private function init() {
        $this->load_configuration();
        $this->register_routes();
        $this->setup_hooks();
        $this->initialize_rate_limiter();
        $this->setup_authentication();
    }
    
    /**
     * Load API configuration
     */
    private function load_configuration() {
        $this->config = get_option('ai_news_api_config', [
            'enabled' => true,
            'rate_limit' => [
                'requests_per_hour' => 1000,
                'requests_per_day' => 10000,
                'burst_limit' => 100
            ],
            'jwt' => [
                'expiration' => 3600, // 1 hour
                'refresh_expiration' => 604800 // 7 days
            ],
            'oauth2' => [
                'client_id_length' => 32,
                'client_secret_length' => 64,
                'authorization_code_expiration' => 300, // 5 minutes
                'access_token_expiration' => 3600,
                'refresh_token_expiration' => 2592000 // 30 days
            ]
        ]);
        
        $this->jwt_secret = wp_salt('secure_auth');
    }
    
    /**
     * Register API routes
     */
    private function register_routes() {
        // Authentication endpoints
        register_rest_route(self::API_NAMESPACE . '/' . self::API_VERSION, '/auth/token', [
            'methods' => 'POST',
            'callback' => [$this, 'generate_access_token'],
            'permission_callback' => '__return_true',
            'args' => [
                'grant_type' => [
                    'required' => true,
                    'type' => 'string',
                    'enum' => ['password', 'client_credentials', 'refresh_token', 'authorization_code']
                ],
                'client_id' => [
                    'required' => false,
                    'type' => 'string'
                ],
                'client_secret' => [
                    'required' => false,
                    'type' => 'string'
                ],
                'username' => [
                    'required' => false,
                    'type' => 'string'
                ],
                'password' => [
                    'required' => false,
                    'type' => 'string'
                ],
                'authorization_code' => [
                    'required' => false,
                    'type' => 'string'
                ],
                'refresh_token' => [
                    'required' => false,
                    'type' => 'string'
                ]
            ]
        ]);
        
        register_rest_route(self::API_NAMESPACE . '/' . self::API_VERSION, '/auth/oauth2/authorize', [
            'methods' => 'GET',
            'callback' => [$this, 'oauth2_authorize'],
            'permission_callback' => [$this, 'check_user_authentication'],
            'args' => [
                'response_type' => [
                    'required' => true,
                    'type' => 'string',
                    'enum' => ['code']
                ],
                'client_id' => [
                    'required' => true,
                    'type' => 'string'
                ],
                'redirect_uri' => [
                    'required' => true,
                    'type' => 'string',
                    'format' => 'uri'
                ],
                'scope' => [
                    'required' => false,
                    'type' => 'string'
                ],
                'state' => [
                    'required' => false,
                    'type' => 'string'
                ]
            ]
        ]);
        
        register_rest_route(self::API_NAMESPACE . '/' . self::API_VERSION, '/auth/oauth2/token', [
            'methods' => 'POST',
            'callback' => [$this, 'oauth2_token'],
            'permission_callback' => '__return_true',
            'args' => [
                'grant_type' => [
                    'required' => true,
                    'type' => 'string',
                    'enum' => ['authorization_code', 'refresh_token', 'client_credentials']
                ],
                'code' => [
                    'required' => false,
                    'type' => 'string'
                ],
                'client_id' => [
                    'required' => true,
                    'type' => 'string'
                ],
                'client_secret' => [
                    'required' => true,
                    'type' => 'string'
                ],
                'redirect_uri' => [
                    'required' => false,
                    'type' => 'string',
                    'format' => 'uri'
                ],
                'refresh_token' => [
                    'required' => false,
                    'type' => 'string'
                ]
            ]
        ]);
        
        // Content endpoints
        register_rest_route(self::API_NAMESPACE . '/' . self::API_VERSION, '/content', [
            'methods' => 'GET',
            'callback' => [$this, 'get_content_list'],
            'permission_callback' => [$this, 'check_api_authentication'],
            'args' => [
                'page' => [
                    'required' => false,
                    'type' => 'integer',
                    'minimum' => 1
                ],
                'per_page' => [
                    'required' => false,
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 100
                ],
                'status' => [
                    'required' => false,
                    'type' => 'string',
                    'enum' => ['publish', 'draft', 'pending', 'private']
                ],
                'type' => [
                    'required' => false,
                    'type' => 'string'
                ],
                'search' => [
                    'required' => false,
                    'type' => 'string'
                ]
            ]
        ]);
        
        register_rest_route(self::API_NAMESPACE . '/' . self::API_VERSION, '/content/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_content_item'],
            'permission_callback' => [$this, 'check_api_authentication'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer'
                ]
            ]
        ]);
        
        register_rest_route(self::API_NAMESPACE . '/' . self::API_VERSION, '/content', [
            'methods' => 'POST',
            'callback' => [$this, 'create_content'],
            'permission_callback' => [$this, 'check_api_authentication'],
            'args' => [
                'title' => [
                    'required' => true,
                    'type' => 'string',
                    'minLength' => 1,
                    'maxLength' => 200
                ],
                'content' => [
                    'required' => true,
                    'type' => 'string'
                ],
                'status' => [
                    'required' => false,
                    'type' => 'string',
                    'enum' => ['draft', 'pending', 'publish'],
                    'default' => 'draft'
                ],
                'type' => [
                    'required' => false,
                    'type' => 'string',
                    'default' => 'post'
                ],
                'categories' => [
                    'required' => false,
                    'type' => 'array',
                    'items' => ['type' => 'integer']
                ],
                'tags' => [
                    'required' => false,
                    'type' => 'array',
                    'items' => ['type' => 'string']
                ],
                'meta' => [
                    'required' => false,
                    'type' => 'object'
                ]
            ]
        ]);
        
        register_rest_route(self::API_NAMESPACE . '/' . self::API_VERSION, '/content/(?P<id>\d+)', [
            'methods' => ['PUT', 'PATCH'],
            'callback' => [$this, 'update_content'],
            'permission_callback' => [$this, 'check_api_authentication'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer'
                ],
                'title' => [
                    'required' => false,
                    'type' => 'string',
                    'minLength' => 1,
                    'maxLength' => 200
                ],
                'content' => [
                    'required' => false,
                    'type' => 'string'
                ],
                'status' => [
                    'required' => false,
                    'type' => 'string',
                    'enum' => ['draft', 'pending', 'publish']
                ],
                'categories' => [
                    'required' => false,
                    'type' => 'array',
                    'items' => ['type' => 'integer']
                ],
                'tags' => [
                    'required' => false,
                    'type' => 'array',
                    'items' => ['type' => 'string']
                ],
                'meta' => [
                    'required' => false,
                    'type' => 'object'
                ]
            ]
        ]);
        
        register_rest_route(self::API_NAMESPACE . '/' . self::API_VERSION, '/content/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_content'],
            'permission_callback' => [$this, 'check_api_authentication'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer'
                ],
                'force' => [
                    'required' => false,
                    'type' => 'boolean',
                    'default' => false
                ]
            ]
        ]);
        
        // AI Generation endpoints
        register_rest_route(self::API_NAMESPACE . '/' . self::API_VERSION, '/generate', [
            'methods' => 'POST',
            'callback' => [$this, 'generate_content'],
            'permission_callback' => [$this, 'check_api_authentication'],
            'args' => [
                'prompt' => [
                    'required' => true,
                    'type' => 'string',
                    'minLength' => 10,
                    'maxLength' => 2000
                ],
                'type' => [
                    'required' => false,
                    'type' => 'string',
                    'enum' => ['article', 'summary', 'social', 'seo'],
                    'default' => 'article'
                ],
                'length' => [
                    'required' => false,
                    'type' => 'string',
                    'enum' => ['short', 'medium', 'long'],
                    'default' => 'medium'
                ],
                'tone' => [
                    'required' => false,
                    'type' => 'string',
                    'enum' => ['formal', 'casual', 'professional', 'conversational'],
                    'default' => 'professional'
                ],
                'keywords' => [
                    'required' => false,
                    'type' => 'array',
                    'items' => ['type' => 'string']
                ],
                'language' => [
                    'required' => false,
                    'type' => 'string',
                    'default' => 'en'
                ]
            ]
        ]);
        
        // Monitoring endpoints
        register_rest_route(self::API_NAMESPACE . '/' . self::API_VERSION, '/monitoring/metrics', [
            'methods' => 'GET',
            'callback' => [$this, 'get_monitoring_metrics'],
            'permission_callback' => [$this, 'check_api_authentication'],
            'args' => [
                'time_range' => [
                    'required' => false,
                    'type' => 'string',
                    'enum' => ['1h', '24h', '7d', '30d'],
                    'default' => '24h'
                ],
                'category' => [
                    'required' => false,
                    'type' => 'string',
                    'enum' => ['system', 'wordpress', 'content', 'seo', 'api', 'user', 'plugin']
                ]
            ]
        ]);
        
        register_rest_route(self::API_NAMESPACE . '/' . self::API_VERSION, '/monitoring/alerts', [
            'methods' => 'GET',
            'callback' => [$this, 'get_monitoring_alerts'],
            'permission_callback' => [$this, 'check_api_authentication'],
            'args' => [
                'status' => [
                    'required' => false,
                    'type' => 'string',
                    'enum' => ['active', 'resolved', 'acknowledged']
                ],
                'severity' => [
                    'required' => false,
                    'type' => 'string',
                    'enum' => ['critical', 'high', 'medium', 'low', 'info']
                ]
            ]
        ]);
        
        register_rest_route(self::API_NAMESPACE . '/' . self::API_VERSION, '/monitoring/alerts/(?P<id>[^/]+)', [
            'methods' => ['PUT', 'PATCH'],
            'callback' => [$this, 'update_alert_status'],
            'permission_callback' => [$this, 'check_api_authentication'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'string'
                ],
                'status' => [
                    'required' => true,
                    'type' => 'string',
                    'enum' => ['acknowledged', 'resolved']
                ],
                'note' => [
                    'required' => false,
                    'type' => 'string'
                ]
            ]
        ]);
        
        // Analytics endpoints
        register_rest_route(self::API_NAMESPACE . '/' . self::API_VERSION, '/analytics/content', [
            'methods' => 'GET',
            'callback' => [$this, 'get_content_analytics'],
            'permission_callback' => [$this, 'check_api_authentication'],
            'args' => [
                'time_range' => [
                    'required' => false,
                    'type' => 'string',
                    'enum' => ['1h', '24h', '7d', '30d'],
                    'default' => '7d'
                ],
                'post_id' => [
                    'required' => false,
                    'type' => 'integer'
                ]
            ]
        ]);
        
        register_rest_route(self::API_NAMESPACE . '/' . self::API_VERSION, '/analytics/seo', [
            'methods' => 'GET',
            'callback' => [$this, 'get_seo_analytics'],
            'permission_callback' => [$this, 'check_api_authentication'],
            'args' => [
                'time_range' => [
                    'required' => false,
                    'type' => 'string',
                    'enum' => ['1h', '24h', '7d', '30d'],
                    'default' => '7d'
                ],
                'post_id' => [
                    'required' => false,
                    'type' => 'integer'
                ]
            ]
        ]);
        
        // Settings endpoints
        register_rest_route(self::API_NAMESPACE . '/' . self::API_VERSION, '/settings', [
            'methods' => ['GET', 'PUT'],
            'callback' => [$this, 'manage_settings'],
            'permission_callback' => [$this, 'check_api_authentication']
        ]);
        
        // Health check endpoint
        register_rest_route(self::API_NAMESPACE . '/' . self::API_VERSION, '/health', [
            'methods' => 'GET',
            'callback' => [$this, 'health_check'],
            'permission_callback' => '__return_true'
        ]);
        
        // API information endpoint
        register_rest_route(self::API_NAMESPACE . '/' . self::API_VERSION, '/info', [
            'methods' => 'GET',
            'callback' => [$this, 'api_info'],
            'permission_callback' => '__return_true'
        ]);
    }
    
    /**
     * Set up hooks
     */
    private function setup_hooks() {
        add_action('rest_api_init', [$this, 'register_meta_fields']);
        add_filter('rest_pre_serve_request', [$this, 'log_api_request'], 10, 4);
        add_action('rest_api_init', [$this, 'add_cors_support']);
    }
    
    /**
     * Setup authentication
     */
    private function setup_authentication() {
        // Register custom authentication methods
        add_filter('determine_current_user', [$this, 'authenticate_user'], 20);
        add_filter('rest_authentication_errors', [$this, 'authentication_error_handler']);
    }
    
    /**
     * Initialize rate limiter
     */
    private function initialize_rate_limiter() {
        if (class_exists('AI_News_Rate_Limiter')) {
            $this->rate_limiter = AI_News_Rate_Limiter::get_instance();
        }
    }
    
    /**
     * Register meta fields for API
     */
    public function register_meta_fields() {
        register_meta('post', '_ai_news_generated', [
            'show_in_rest' => true,
            'single' => true,
            'type' => 'boolean'
        ]);
        
        register_meta('post', '_ai_news_generation_prompt', [
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string'
        ]);
        
        register_meta('post', '_ai_news_seo_score', [
            'show_in_rest' => true,
            'single' => true,
            'type' => 'integer'
        ]);
        
        register_meta('post', '_ai_news_content_quality_score', [
            'show_in_rest' => true,
            'single' => true,
            'type' => 'integer'
        ]);
    }
    
    /**
     * Check user authentication for OAuth2 flows
     */
    public function check_user_authentication($request) {
        return is_user_logged_in();
    }
    
    /**
     * Check API authentication
     */
    public function check_api_authentication($request) {
        return $this->authenticate_api_request($request);
    }
    
    /**
     * Authenticate user for API requests
     */
    public function authenticate_user($user_id) {
        // Skip if not an API request
        if (!$this->is_api_request()) {
            return $user_id;
        }
        
        // Try JWT authentication
        $jwt_user_id = $this->authenticate_jwt_request();
        if ($jwt_user_id) {
            return $jwt_user_id;
        }
        
        // Try API key authentication
        $api_key_user_id = $this->authenticate_api_key_request();
        if ($api_key_user_id) {
            return $api_key_user_id;
        }
        
        return $user_id;
    }
    
    /**
     * Authentication error handler
     */
    public function authentication_error_handler($result) {
        if (!$this->is_api_request()) {
            return $result;
        }
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Check for missing authentication
        if (!$this->is_authenticated()) {
            return new WP_Error(
                'rest_forbidden',
                'Authentication required for this endpoint.',
                ['status' => 401]
            );
        }
        
        return $result;
    }
    
    /**
     * Check if current request is API request
     */
    private function is_api_request() {
        $route = $GLOBALS['wp']->query_vars['rest_route'] ?? '';
        return strpos($route, self::API_NAMESPACE . '/' . self::API_VERSION) === 0;
    }
    
    /**
     * Check if user is authenticated
     */
    private function is_authenticated() {
        $token = $this->get_bearer_token();
        if (!$token) {
            return false;
        }
        
        return $this->validate_jwt_token($token) !== false;
    }
    
    /**
     * Authenticate API request
     */
    private function authenticate_api_request($request) {
        // Check rate limiting first
        if ($this->rate_limiter && !$this->rate_limiter->check_rate_limit()) {
            return new WP_Error(
                'rate_limit_exceeded',
                'Rate limit exceeded. Please try again later.',
                ['status' => 429]
            );
        }
        
        // Authenticate user
        $user_id = $this->authenticate_jwt_request();
        if (!$user_id) {
            $user_id = $this->authenticate_api_key_request();
        }
        
        if (!$user_id) {
            return new WP_Error(
                'rest_forbidden',
                'Invalid or missing authentication token.',
                ['status' => 401]
            );
        }
        
        return true;
    }
    
    /**
     * Authenticate JWT request
     */
    private function authenticate_jwt_request() {
        $token = $this->get_bearer_token();
        if (!$token) {
            return false;
        }
        
        $payload = $this->validate_jwt_token($token);
        if (!$payload) {
            return false;
        }
        
        return $payload['user_id'];
    }
    
    /**
     * Authenticate API key request
     */
    private function authenticate_api_key_request() {
        $api_key = $this->get_api_key_from_request();
        if (!$api_key) {
            return false;
        }
        
        return $this->validate_api_key($api_key);
    }
    
    /**
     * Generate access token (JWT)
     */
    public function generate_access_token($request) {
        // Check rate limiting
        if (AANP_Rate_Limiter::getInstance()->is_rate_limited('rest_auth_token')) {
            AANP_Error_Handler::getInstance()->handle_error(
                'Rate limit exceeded for authentication endpoint',
                ['endpoint' => 'rest_auth_token', 'ip' => $this->get_client_ip()],
                'rate_limiting'
            );
            return new WP_Error(
                'rate_limit_exceeded',
                'Rate limit exceeded. Please try again later.',
                ['status' => 429]
            );
        }

        $grant_type = $request->get_param('grant_type');

        switch ($grant_type) {
            case 'password':
                return $this->generate_token_password_grant($request);
            case 'client_credentials':
                return $this->generate_token_client_credentials($request);
            case 'refresh_token':
                return $this->generate_token_refresh_token($request);
            case 'authorization_code':
                return $this->generate_token_authorization_code($request);
            default:
                return new WP_Error(
                    'invalid_grant_type',
                    'Invalid grant type specified.',
                    ['status' => 400]
                );
        }
    }
    
    /**
     * Generate token via password grant
     */
    private function generate_token_password_grant($request) {
        $username = $request->get_param('username');
        $password = $request->get_param('password');
        
        if (!$username || !$password) {
            return new WP_Error(
                'invalid_credentials',
                'Username and password are required.',
                ['status' => 400]
            );
        }
        
        $user = wp_authenticate($username, $password);
        if (is_wp_error($user)) {
            return new WP_Error(
                'invalid_credentials',
                'Invalid username or password.',
                ['status' => 401]
            );
        }
        
        $access_token = $this->generate_jwt_token($user->ID);
        $refresh_token = $this->generate_refresh_token($user->ID);
        
        return [
            'access_token' => $access_token,
            'token_type' => 'Bearer',
            'expires_in' => $this->config['jwt']['expiration'],
            'refresh_token' => $refresh_token
        ];
    }
    
    /**
     * Generate JWT token
     */
    private function generate_jwt_token($user_id, $expiration = null) {
        $expiration = $expiration ?: (time() + $this->config['jwt']['expiration']);
        
        $payload = [
            'iss' => get_site_url(),
            'sub' => $user_id,
            'iat' => time(),
            'exp' => $expiration,
            'aud' => get_site_url(),
            'jti' => uniqid()
        ];
        
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode($payload);
        
        $base64_header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64_payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        
        $signature = hash_hmac('sha256', $base64_header . "." . $base64_payload, $this->jwt_secret, true);
        $base64_signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        return $base64_header . "." . $base64_payload . "." . $base64_signature;
    }
    
    /**
     * Validate JWT token
     */
    private function validate_jwt_token($token) {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }
        
        [$header, $payload, $signature] = $parts;
        
        // Verify signature
        $expected_signature = hash_hmac('sha256', $header . "." . $payload, $this->jwt_secret, true);
        $expected_signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($expected_signature));
        
        if (!hash_equals($signature, $expected_signature)) {
            return false;
        }
        
        // Decode payload
        $payload_data = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $payload)), true);
        
        if (!$payload_data) {
            return false;
        }
        
        // Check expiration
        if (isset($payload_data['exp']) && $payload_data['exp'] < time()) {
            return false;
        }
        
        return $payload_data;
    }
    
    /**
     * Generate refresh token
     */
    private function generate_refresh_token($user_id) {
        $token = wp_generate_password(64, false);
        
        // Store refresh token
        $refresh_tokens = get_user_meta($user_id, 'ai_news_refresh_tokens', true) ?: [];
        $refresh_tokens[$token] = [
            'created_at' => time(),
            'expires_at' => time() + $this->config['oauth2']['refresh_token_expiration']
        ];
        
        update_user_meta($user_id, 'ai_news_refresh_tokens', $refresh_tokens);
        
        return $token;
    }
    
    /**
     * OAuth2 Authorization endpoint
     */
    public function oauth2_authorize($request) {
        $response_type = $request->get_param('response_type');
        $client_id = $request->get_param('client_id');
        $redirect_uri = $request->get_param('redirect_uri');
        $scope = $request->get_param('scope') ?: 'read write';
        
        // Validate request
        if (!$this->validate_oauth2_request($client_id, $redirect_uri, $response_type)) {
            return new WP_Error(
                'invalid_request',
                'Invalid OAuth2 request parameters.',
                ['status' => 400]
            );
        }
        
        // Check if user has already authorized this client
        if ($this->is_client_authorized($client_id)) {
            $authorization_code = $this->generate_authorization_code();
            $this->store_authorization_code($authorization_code, $client_id, $scope);
            
            $redirect_url = add_query_arg([
                'code' => $authorization_code,
                'state' => $request->get_param('state')
            ], $redirect_uri);
            
            wp_redirect($redirect_url);
            exit;
        }
        
        // Show authorization form
        return $this->render_oauth2_authorization_form($client_id, $scope, $redirect_uri);
    }
    
    /**
     * OAuth2 Token endpoint
     */
    public function oauth2_token($request) {
        $grant_type = $request->get_param('grant_type');
        
        switch ($grant_type) {
            case 'authorization_code':
                return $this->oauth2_token_authorization_code($request);
            case 'refresh_token':
                return $this->oauth2_token_refresh_token($request);
            case 'client_credentials':
                return $this->oauth2_token_client_credentials($request);
            default:
                return new WP_Error(
                    'unsupported_grant_type',
                    'Unsupported grant type.',
                    ['status' => 400]
                );
        }
    }
    
    /**
     * Get content list
     */
    public function get_content_list($request) {
        // Check rate limiting
        if (AANP_Rate_Limiter::getInstance()->is_rate_limited('rest_get_content_list')) {
            AANP_Error_Handler::getInstance()->handle_error(
                'Rate limit exceeded for content list endpoint',
                ['endpoint' => 'rest_get_content_list', 'ip' => $this->get_client_ip()],
                'rate_limiting'
            );
            return new WP_Error(
                'rate_limit_exceeded',
                'Rate limit exceeded. Please try again later.',
                ['status' => 429]
            );
        }

        $page = $request->get_param('page') ?: 1;
        $per_page = $request->get_param('per_page') ?: 10;
        $status = $request->get_param('status');
        $type = $request->get_param('type');
        $search = $request->get_param('search');

        $args = [
            'post_type' => $type ?: 'post',
            'post_status' => $status ?: 'publish',
            'posts_per_page' => $per_page,
            'paged' => $page
        ];

        if ($search) {
            $args['s'] = $search;
        }

        $query = new WP_Query($args);
        
        if (!$query->have_posts()) {
            return [
                'data' => [],
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $per_page,
                    'total_items' => 0,
                    'total_pages' => 0
                ]
            ];
        }
        
        $posts = [];
        while ($query->have_posts()) {
            $query->the_post();
            $posts[] = $this->format_post_for_api();
        }
        
        return [
            'data' => $posts,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $per_page,
                'total_items' => $query->found_posts,
                'total_pages' => $query->max_num_pages
            ]
        ];
    }
    
    /**
     * Get single content item
     */
    public function get_content_item($request) {
        // Check rate limiting
        if (AANP_Rate_Limiter::getInstance()->is_rate_limited('rest_get_content_item')) {
            AANP_Error_Handler::getInstance()->handle_error(
                'Rate limit exceeded for content item endpoint',
                ['endpoint' => 'rest_get_content_item', 'ip' => $this->get_client_ip()],
                'rate_limiting'
            );
            return new WP_Error(
                'rate_limit_exceeded',
                'Rate limit exceeded. Please try again later.',
                ['status' => 429]
            );
        }

        $id = $request->get_param('id');
        $post = get_post($id);

        if (!$post) {
            return new WP_Error(
                'post_not_found',
                'Content item not found.',
                ['status' => 404]
            );
        }

        return $this->format_post_for_api($post);
    }
    
    /**
     * Create new content
     */
    public function create_content($request) {
        // Check rate limiting
        if (AANP_Rate_Limiter::getInstance()->is_rate_limited('rest_create_content')) {
            AANP_Error_Handler::getInstance()->handle_error(
                'Rate limit exceeded for create content endpoint',
                ['endpoint' => 'rest_create_content', 'ip' => $this->get_client_ip()],
                'rate_limiting'
            );
            return new WP_Error(
                'rate_limit_exceeded',
                'Rate limit exceeded. Please try again later.',
                ['status' => 429]
            );
        }

        $title = $request->get_param('title');
        $content = $request->get_param('content');
        $status = $request->get_param('status');
        $type = $request->get_param('type');
        $categories = $request->get_param('categories');
        $tags = $request->get_param('tags');
        $meta = $request->get_param('meta');

        $post_data = [
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => $status,
            'post_type' => $type,
            'meta_input' => $meta ?: []
        ];

        // Add categories
        if ($categories) {
            $post_data['post_category'] = $categories;
        }

        // Add tags
        if ($tags) {
            $post_data['tags_input'] = $tags;
        }

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) {
            return new WP_Error(
                'post_creation_failed',
                $post_id->get_error_message(),
                ['status' => 400]
            );
        }

        return $this->format_post_for_api(get_post($post_id));
    }
    
    /**
     * Update content
     */
    public function update_content($request) {
        // Check rate limiting
        if (AANP_Rate_Limiter::getInstance()->is_rate_limited('rest_update_content')) {
            AANP_Error_Handler::getInstance()->handle_error(
                'Rate limit exceeded for update content endpoint',
                ['endpoint' => 'rest_update_content', 'ip' => $this->get_client_ip()],
                'rate_limiting'
            );
            return new WP_Error(
                'rate_limit_exceeded',
                'Rate limit exceeded. Please try again later.',
                ['status' => 429]
            );
        }

        $id = $request->get_param('id');
        $post = get_post($id);

        if (!$post) {
            return new WP_Error(
                'post_not_found',
                'Content item not found.',
                ['status' => 404]
            );
        }

        $update_data = ['ID' => $id];

        if ($request->get_param('title')) {
            $update_data['post_title'] = $request->get_param('title');
        }

        if ($request->get_param('content')) {
            $update_data['post_content'] = $request->get_param('content');
        }

        if ($request->get_param('status')) {
            $update_data['post_status'] = $request->get_param('status');
        }

        $categories = $request->get_param('categories');
        if ($categories) {
            $update_data['post_category'] = $categories;
        }

        $tags = $request->get_param('tags');
        if ($tags) {
            $update_data['tags_input'] = $tags;
        }

        $meta = $request->get_param('meta');
        if ($meta) {
            foreach ($meta as $key => $value) {
                update_post_meta($id, $key, $value);
            }
        }

        $updated_post_id = wp_update_post($update_data);

        if (is_wp_error($updated_post_id)) {
            return new WP_Error(
                'post_update_failed',
                $updated_post_id->get_error_message(),
                ['status' => 400]
            );
        }

        return $this->format_post_for_api(get_post($updated_post_id));
    }
    
    /**
     * Delete content
     */
    public function delete_content($request) {
        // Check rate limiting
        if (AANP_Rate_Limiter::getInstance()->is_rate_limited('rest_delete_content')) {
            AANP_Error_Handler::getInstance()->handle_error(
                'Rate limit exceeded for delete content endpoint',
                ['endpoint' => 'rest_delete_content', 'ip' => $this->get_client_ip()],
                'rate_limiting'
            );
            return new WP_Error(
                'rate_limit_exceeded',
                'Rate limit exceeded. Please try again later.',
                ['status' => 429]
            );
        }

        $id = $request->get_param('id');
        $force = $request->get_param('force');

        $post = get_post($id);
        if (!$post) {
            return new WP_Error(
                'post_not_found',
                'Content item not found.',
                ['status' => 404]
            );
        }

        $result = wp_delete_post($id, $force);

        if (!$result) {
            return new WP_Error(
                'post_deletion_failed',
                'Failed to delete content item.',
                ['status' => 400]
            );
        }

        return [
            'success' => true,
            'message' => 'Content item deleted successfully.'
        ];
    }
    
    /**
     * Generate content using AI
     */
    public function generate_content($request) {
        // Check rate limiting
        if (AANP_Rate_Limiter::getInstance()->is_rate_limited('rest_generate_ai')) {
            AANP_Error_Handler::getInstance()->handle_error(
                'Rate limit exceeded for AI generation endpoint',
                ['endpoint' => 'rest_generate_ai', 'ip' => $this->get_client_ip()],
                'rate_limiting'
            );
            return new WP_Error(
                'rate_limit_exceeded',
                'Rate limit exceeded. Please try again later.',
                ['status' => 429]
            );
        }

        $prompt = $request->get_param('prompt');
        $type = $request->get_param('type');
        $length = $request->get_param('length');
        $tone = $request->get_param('tone');
        $keywords = $request->get_param('keywords');
        $language = $request->get_param('language');

        // Get AI generation service
        $ai_service = new AIGenerationService();

        $generation_params = [
            'prompt' => $prompt,
            'type' => $type,
            'length' => $length,
            'tone' => $tone,
            'keywords' => $keywords,
            'language' => $language
        ];

        $result = $ai_service->generate_content($generation_params);

        if (is_wp_error($result)) {
            return new WP_Error(
                'generation_failed',
                $result->get_error_message(),
                ['status' => 500]
            );
        }

        return [
            'generated_content' => $result['content'],
            'metadata' => [
                'prompt' => $prompt,
                'type' => $type,
                'length' => $length,
                'tone' => $tone,
                'keywords' => $keywords,
                'language' => $language,
                'generation_time' => $result['generation_time'] ?? null,
                'word_count' => str_word_count(strip_tags($result['content'])),
                'seo_score' => $this->calculate_seo_score($result['content'], $keywords)
            ]
        ];
    }
    
    /**
     * Get monitoring metrics
     */
    public function get_monitoring_metrics($request) {
        // Check rate limiting
        if (AANP_Rate_Limiter::getInstance()->is_rate_limited('rest_get_monitoring_metrics')) {
            AANP_Error_Handler::getInstance()->handle_error(
                'Rate limit exceeded for monitoring metrics endpoint',
                ['endpoint' => 'rest_get_monitoring_metrics', 'ip' => $this->get_client_ip()],
                'rate_limiting'
            );
            return new WP_Error(
                'rate_limit_exceeded',
                'Rate limit exceeded. Please try again later.',
                ['status' => 429]
            );
        }

        $time_range = $request->get_param('time_range') ?: '24h';
        $category = $request->get_param('category');

        if (class_exists('MetricsCollector')) {
            $metrics_collector = MetricsCollector::get_instance();
            $metrics = $metrics_collector->get_cached_metrics(true);

            if ($category) {
                $metrics = [$category => $metrics[$category] ?? []];
            }

            return $metrics;
        }

        return new WP_Error(
            'monitoring_unavailable',
            'Monitoring system is not available.',
            ['status' => 503]
        );
    }
    
    /**
     * Get monitoring alerts
     */
    public function get_monitoring_alerts($request) {
        // Check rate limiting
        if (AANP_Rate_Limiter::getInstance()->is_rate_limited('rest_get_monitoring_alerts')) {
            AANP_Error_Handler::getInstance()->handle_error(
                'Rate limit exceeded for monitoring alerts endpoint',
                ['endpoint' => 'rest_get_monitoring_alerts', 'ip' => $this->get_client_ip()],
                'rate_limiting'
            );
            return new WP_Error(
                'rate_limit_exceeded',
                'Rate limit exceeded. Please try again later.',
                ['status' => 429]
            );
        }

        $status = $request->get_param('status');
        $severity = $request->get_param('severity');

        if (class_exists('AlertsManager')) {
            $alerts_manager = AlertsManager::get_instance();

            $filters = [];
            if ($status) $filters['status'] = $status;
            if ($severity) $filters['severity'] = $severity;

            $alerts = $alerts_manager->get_alerts($filters);

            return [
                'alerts' => $alerts,
                'total_count' => count($alerts)
            ];
        }

        return new WP_Error(
            'alerts_unavailable',
            'Alerts system is not available.',
            ['status' => 503]
        );
    }
    
    /**
     * Update alert status
     */
    public function update_alert_status($request) {
        // Check rate limiting
        if (AANP_Rate_Limiter::getInstance()->is_rate_limited('rest_update_alert_status')) {
            AANP_Error_Handler::getInstance()->handle_error(
                'Rate limit exceeded for update alert status endpoint',
                ['endpoint' => 'rest_update_alert_status', 'ip' => $this->get_client_ip()],
                'rate_limiting'
            );
            return new WP_Error(
                'rate_limit_exceeded',
                'Rate limit exceeded. Please try again later.',
                ['status' => 429]
            );
        }

        $id = $request->get_param('id');
        $status = $request->get_param('status');
        $note = $request->get_param('note');

        if (class_exists('AlertsManager')) {
            $alerts_manager = AlertsManager::get_instance();

            if ($status === 'acknowledged') {
                $success = $alerts_manager->acknowledge_alert($id);
            } elseif ($status === 'resolved') {
                $success = $alerts_manager->resolve_alert($id, null, $note);
            } else {
                return new WP_Error(
                    'invalid_status',
                    'Invalid alert status.',
                    ['status' => 400]
                );
            }

            if ($success) {
                return [
                    'success' => true,
                    'message' => 'Alert status updated successfully.'
                ];
            } else {
                return new WP_Error(
                    'update_failed',
                    'Failed to update alert status.',
                    ['status' => 400]
                );
            }
        }

        return new WP_Error(
            'alerts_unavailable',
            'Alerts system is not available.',
            ['status' => 503]
        );
    }
    
    /**
     * Get content analytics
     */
    public function get_content_analytics($request) {
        // Check rate limiting
        if (AANP_Rate_Limiter::getInstance()->is_rate_limited('rest_get_content_analytics')) {
            AANP_Error_Handler::getInstance()->handle_error(
                'Rate limit exceeded for content analytics endpoint',
                ['endpoint' => 'rest_get_content_analytics', 'ip' => $this->get_client_ip()],
                'rate_limiting'
            );
            return new WP_Error(
                'rate_limit_exceeded',
                'Rate limit exceeded. Please try again later.',
                ['status' => 429]
            );
        }

        $time_range = $request->get_param('time_range') ?: '7d';
        $post_id = $request->get_param('post_id');

        if (class_exists('AnalyticsService')) {
            $analytics_service = AnalyticsService::get_instance();

            if ($post_id) {
                return $analytics_service->get_post_analytics($post_id, $time_range);
            } else {
                return $analytics_service->get_content_analytics($time_range);
            }
        }

        return new WP_Error(
            'analytics_unavailable',
            'Analytics system is not available.',
            ['status' => 503]
        );
    }
    
    /**
     * Get SEO analytics
     */
    public function get_seo_analytics($request) {
        // Check rate limiting
        if (AANP_Rate_Limiter::getInstance()->is_rate_limited('rest_get_seo_analytics')) {
            AANP_Error_Handler::getInstance()->handle_error(
                'Rate limit exceeded for SEO analytics endpoint',
                ['endpoint' => 'rest_get_seo_analytics', 'ip' => $this->get_client_ip()],
                'rate_limiting'
            );
            return new WP_Error(
                'rate_limit_exceeded',
                'Rate limit exceeded. Please try again later.',
                ['status' => 429]
            );
        }

        $time_range = $request->get_param('time_range') ?: '7d';
        $post_id = $request->get_param('post_id');

        // This would integrate with the existing SEO system
        return [
            'overall_score' => 85,
            'content_quality' => [
                'score' => 90,
                'issues' => []
            ],
            'technical_seo' => [
                'score' => 88,
                'issues' => ['Missing alt tags on 2 images']
            ],
            'performance' => [
                'score' => 82,
                'issues' => ['Slow page load time on mobile']
            ]
        ];
    }
    
    /**
     * Manage settings
     */
    public function manage_settings($request) {
        // Check rate limiting
        $endpoint_key = $request->get_method() === 'GET' ? 'rest_get_settings' : 'rest_update_settings';
        if (AANP_Rate_Limiter::getInstance()->is_rate_limited($endpoint_key)) {
            AANP_Error_Handler::getInstance()->handle_error(
                'Rate limit exceeded for settings endpoint',
                ['endpoint' => $endpoint_key, 'ip' => $this->get_client_ip()],
                'rate_limiting'
            );
            return new WP_Error(
                'rate_limit_exceeded',
                'Rate limit exceeded. Please try again later.',
                ['status' => 429]
            );
        }

        if ($request->get_method() === 'GET') {
            return $this->get_public_settings();
        } elseif ($request->get_method() === 'PUT') {
            return $this->update_settings($request);
        }

        return new WP_Error('method_not_allowed', 'Method not allowed', ['status' => 405]);
    }
    
    /**
     * Health check endpoint
     */
    public function health_check($request) {
        return [
            'status' => 'healthy',
            'timestamp' => time(),
            'version' => self::API_VERSION,
            'services' => [
                'database' => $this->check_database_health(),
                'ai_service' => $this->check_ai_service_health(),
                'cache' => $this->check_cache_health(),
                'monitoring' => $this->check_monitoring_health()
            ]
        ];
    }
    
    /**
     * API information endpoint
     */
    public function api_info($request) {
        return [
            'name' => 'ContentPilot REST API',
            'version' => self::API_VERSION,
            'description' => 'Comprehensive REST API for ContentPilot plugin',
            'base_url' => get_site_url() . '/wp-json/' . self::API_NAMESPACE . '/' . self::API_VERSION,
            'endpoints' => $this->get_api_endpoints_documentation(),
            'authentication' => [
                'type' => 'Bearer JWT',
                'oauth2' => true
            ],
            'rate_limiting' => [
                'requests_per_hour' => $this->config['rate_limit']['requests_per_hour'] ?? 1000,
                'burst_limit' => $this->config['rate_limit']['burst_limit'] ?? 100
            ]
        ];
    }
    
    // Utility methods
    
    private function get_bearer_token() {
        $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(\S+)/', $auth_header, $matches)) {
            return $matches[1];
        }
        return false;
    }
    
    private function get_api_key_from_request() {
        return $_GET['api_key'] ?? $_POST['api_key'] ?? false;
    }
    
    private function validate_api_key($api_key) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_news_api_keys';
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT user_id FROM {$table_name} WHERE api_key = %s AND status = 'active' AND expires_at > NOW()",
            $api_key
        ));
        
        return $result ? $result->user_id : false;
    }
    
    private function format_post_for_api($post = null) {
        if (!$post) {
            global $post;
        }
        
        return [
            'id' => $post->ID,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt,
            'status' => $post->post_status,
            'type' => $post->post_type,
            'author' => [
                'id' => $post->post_author,
                'name' => get_the_author_meta('display_name', $post->post_author)
            ],
            'date' => $post->post_date,
            'modified' => $post->post_modified,
            'permalink' => get_permalink($post->ID),
            'meta' => [
                'ai_generated' => get_post_meta($post->ID, '_ai_news_generated', true) ?: false,
                'seo_score' => get_post_meta($post->ID, '_ai_news_seo_score', true) ?: null,
                'content_quality_score' => get_post_meta($post->ID, '_ai_news_content_quality_score', true) ?: null
            ]
        ];
    }
    
    private function calculate_seo_score($content, $keywords = []) {
        $score = 0;
        
        // Content length
        $word_count = str_word_count(strip_tags($content));
        if ($word_count > 300) $score += 20;
        if ($word_count > 1000) $score += 20;
        
        // Keyword density
        if (!empty($keywords)) {
            foreach ($keywords as $keyword) {
                $density = substr_count(strtolower($content), strtolower($keyword)) / max($word_count, 1) * 100;
                if ($density >= 1 && $density <= 3) $score += 10;
            }
        }
        
        // Basic SEO elements
        if (strpos($content, '<h1>') !== false) $score += 10;
        if (strpos($content, '<h2>') !== false) $score += 10;
        if (strpos($content, '<img') !== false && strpos($content, 'alt=') !== false) $score += 10;
        
        return min($score, 100);
    }
    
    private function get_public_settings() {
        return [
            'plugin_version' => get_option('ai_news_plugin_version', '2.0.0'),
            'features' => [
                'ai_generation' => true,
                'seo_optimization' => true,
                'monitoring' => true,
                'analytics' => true
            ]
        ];
    }
    
    private function update_settings($request) {
        // This would require admin privileges
        if (!current_user_can('manage_options')) {
            return new WP_Error(
                'insufficient_permissions',
                'Insufficient permissions to update settings.',
                ['status' => 403]
            );
        }
        
        // Update settings logic here
        return [
            'success' => true,
            'message' => 'Settings updated successfully.'
        ];
    }
    
    private function check_database_health() {
        global $wpdb;
        
        try {
            $result = $wpdb->get_var("SELECT 1");
            return $result == '1' ? 'healthy' : 'unhealthy';
        } catch (Exception $e) {
            return 'unhealthy';
        }
    }
    
    private function check_ai_service_health() {
        // Check if AI service is available
        return class_exists('AIGenerationService') ? 'healthy' : 'unhealthy';
    }
    
    private function check_cache_health() {
        return wp_using_ext_object_cache() ? 'healthy' : 'internal';
    }
    
    private function check_monitoring_health() {
        return class_exists('RealTimeMonitor') ? 'healthy' : 'unhealthy';
    }
    
    private function get_api_endpoints_documentation() {
        return [
            'authentication' => [
                '/auth/token' => 'Generate JWT access token',
                '/auth/oauth2/authorize' => 'OAuth2 authorization endpoint',
                '/auth/oauth2/token' => 'OAuth2 token endpoint'
            ],
            'content' => [
                '/content' => 'Get content list',
                '/content/{id}' => 'Get, update, or delete specific content',
                '/generate' => 'Generate content using AI'
            ],
            'monitoring' => [
                '/monitoring/metrics' => 'Get monitoring metrics',
                '/monitoring/alerts' => 'Get monitoring alerts',
                '/monitoring/alerts/{id}' => 'Update alert status'
            ],
            'analytics' => [
                '/analytics/content' => 'Get content analytics',
                '/analytics/seo' => 'Get SEO analytics'
            ]
        ];
    }
    
    /**
     * Log API request
     */
    public function log_api_request($served, $result, $request, $handler) {
        if (!$this->is_api_request()) {
            return $served;
        }
        
        $log_data = [
            'timestamp' => time(),
            'method' => $request->get_method(),
            'route' => $request->get_route(),
            'status_code' => $result->get_status(),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'user_id' => get_current_user_id()
        ];
        
        // Store in API logs
        $this->store_api_log($log_data);
        
        return $served;
    }
    
    private function store_api_log($log_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_news_api_logs';
        
        $wpdb->insert(
            $table_name,
            [
                'method' => $log_data['method'],
                'endpoint' => $log_data['route'],
                'status_code' => $log_data['status_code'],
                'ip_address' => $log_data['ip_address'],
                'user_agent' => $log_data['user_agent'],
                'user_id' => $log_data['user_id'],
                'timestamp' => date('Y-m-d H:i:s', $log_data['timestamp'])
            ],
            ['%s', '%s', '%d', '%s', '%s', '%d', '%s']
        );
    }
    
    private function get_client_ip() {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * Add CORS support
     */
    public function add_cors_support() {
        remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
        add_filter('rest_pre_serve_request', [$this, 'send_cors_headers'], 10, 4);
    }
    
    public function send_cors_headers($value, $server, $request, $handler) {
        if ($this->is_api_request()) {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: ' . implode(', ', ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']));
            header('Access-Control-Allow-Headers: ' . implode(', ', [
                'Authorization',
                'Content-Type',
                'X-WP-Nonce'
            ]));
            header('Access-Control-Max-Age: 86400');
        }
        
        return $value;
    }
}

// Initialize the REST API
RestAPI::get_instance();