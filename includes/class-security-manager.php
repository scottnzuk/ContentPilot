<?php
/**
 * Security Manager Class
 *
 * @package AI_Auto_News_Poster
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class CP_Security_Manager {
    private static $csp_nonce = null;
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'init_security'));
    }
    
    /**
     * Initialize security measures
     */
    public function init_security() {
        // Add security headers
        add_action('send_headers', array($this, 'add_security_headers'));
        add_action('admin_head', array($this, 'output_csp_nonce'));
        
        // Sanitize all inputs
        add_action('admin_init', array($this, 'sanitize_inputs'));
    }
    
    /**
     * Add security headers
     */
    public function add_security_headers() {
        if (!is_admin()) {
            return;
        }
        
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        $nonce = $this->get_csp_nonce();
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'nonce-{$nonce}'; style-src 'self' 'unsafe-inline' 'nonce-{$nonce}'; img-src 'self' data: https:; object-src 'none'; frame-src 'none';");
    }
    
    /**
     * Sanitize all inputs
     */
    public function sanitize_inputs() {
        if (isset($_POST['aanp_settings'])) {
            $_POST['aanp_settings'] = $this->deep_sanitize($_POST['aanp_settings']);
        }
    }
    
    /**
     * Deep sanitize array
     *
     * @param mixed $data Data to sanitize
     * @return mixed Sanitized data
     */
    private function deep_sanitize($data) {
        if (is_array($data)) {
            return array_map(array($this, 'deep_sanitize'), $data);
        }
        
        return sanitize_text_field($data);
    }
    
    /**
     * Get CSP nonce
     *
     * @return string The CSP nonce
     */
    private function get_csp_nonce() {
        if (self::$csp_nonce === null) {
            self::$csp_nonce = wp_create_nonce('aanp_csp_nonce');
        }
        return self::$csp_nonce;
    }
    
    /**
     * Output CSP nonce in admin head
     */
    public function output_csp_nonce() {
        if (!is_admin()) {
            return;
        }
        $nonce = $this->get_csp_nonce();
        ?>
        <meta name="csp-nonce" content="<?php echo esc_attr($nonce); ?>">
        <script nonce="<?php echo esc_attr($nonce); ?>">window.aanpCspNonce = <?php echo json_encode($nonce); ?>;</script>
        <?php
    }
    
    /**
     * Validate API response
     *
     * @param string $response API response
     * @return bool True if valid
     */
    public function validate_api_response($response) {
        // Check for suspicious content
        $suspicious_patterns = array(
            '/<script[^>]*>.*?<\/script>/is',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<iframe[^>]*>.*?<\/iframe>/is'
        );
        
        foreach ($suspicious_patterns as $pattern) {
            if (preg_match($pattern, $response)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Hash sensitive data
     *
     * @param string $data Data to hash
     * @return string Hashed data
     */
    public function hash_data($data) {
        return hash('sha256', $data . wp_salt('auth'));
    }
    
    /**
     * Generate secure token
     *
     * @return string Secure token
     */
    public function generate_token() {
        return wp_generate_password(32, false);
    }
}