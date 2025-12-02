<?php
/**
 * RSS Feed Discoverer - Automatic RSS Feed Discovery Engine
 *
 * Discovers, validates, and categorizes RSS feeds for content bundles
 * with quality scoring and automatic feed management.
 *
 * @package AI_Auto_News_Poster\Services
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * RSS Feed Discoverer Class
 */
class AANP_RSSFeedDiscoverer {
    
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
     * Feed categories and their keywords
     * @var array
     */
    private $category_keywords = array(
        'main_news' => array('news', 'breaking', 'latest', 'report', 'update', 'announcement'),
        'business' => array('business', 'finance', 'economy', 'market', 'trade', 'investment'),
        'technology' => array('technology', 'tech', 'digital', 'software', 'innovation'),
        'science' => array('science', 'research', 'study', 'discovery', 'medical', 'health'),
        'sports' => array('sports', 'football', 'basketball', 'baseball', 'tennis', 'olympics'),
        'entertainment' => array('entertainment', 'celebrity', 'music', 'movies', 'tv'),
        'politics' => array('politics', 'government', 'election', 'policy', 'political'),
        'lifestyle' => array('lifestyle', 'travel', 'food', 'fashion', 'health', 'fitness'),
        'regional' => array('regional', 'local', 'community', 'city', 'regional news'),
        'specialized' => array('automotive', 'aviation', 'real estate', 'legal', 'education')
    );
    
    /**
     * Common RSS feed domains and their categories
     * @var array
     */
    private $known_feed_patterns = array(
        // News & Politics
        'bbc.co.uk' => 'main_news',
        'reuters.com' => 'main_news',
        'ap.org' => 'main_news',
        'cnn.com' => 'main_news',
        'nytimes.com' => 'main_news',
        'washingtonpost.com' => 'main_news',
        'guardian.co.uk' => 'main_news',
        'telegraph.co.uk' => 'main_news',
        'wsj.com' => 'business',
        'bloomberg.com' => 'business',
        'ft.com' => 'business',
        
        // Technology
        'techcrunch.com' => 'technology',
        'theverge.com' => 'technology',
        'arstechnica.com' => 'technology',
        'wired.com' => 'technology',
        'venturebeat.com' => 'technology',
        'thenextweb.com' => 'technology',
        
        // Business & Finance
        'coindesk.com' => 'business',
        'cointelegraph.com' => 'business',
        'marketwatch.com' => 'business',
        'seekingalpha.com' => 'business',
        
        // Science & Health
        'nature.com' => 'science',
        'scientificamerican.com' => 'science',
        'healthline.com' => 'science',
        'webmd.com' => 'science',
        'mayoclinic.org' => 'science',
        
        // Sports
        'espn.com' => 'sports',
        'skysports.com' => 'sports',
        'theathletic.com' => 'sports',
        
        // Lifestyle
        'vogue.com' => 'lifestyle',
        'elle.com' => 'lifestyle',
        'lonelyplanet.com' => 'lifestyle',
        'bonappetit.com' => 'lifestyle',
        
        // Regional
        'straitstimes.com' => 'regional',
        'scmp.com' => 'regional',
        'nikkei.com' => 'regional',
        'aljazeera.com' => 'regional'
    );
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = AANP_Logger::getInstance();
        $this->cache_manager = new AANP_AdvancedCacheManager();
    }
    
    /**
     * Discover RSS feeds for a bundle
     *
     * @param string $bundle_name Bundle name
     * @param array $category_keywords Category keywords
     * @return array Discovery results
     */
    public function discover_feeds_for_bundle($bundle_name, $category_keywords) {
        try {
            $cache_key = 'feed_discovery_' . md5($bundle_name . implode('_', $category_keywords));
            $cached = $this->cache_manager->get($cache_key);
            
            if ($cached !== false) {
                return $cached;
            }
            
            $discovered_feeds = array();
            
            // Get feeds from known patterns
            $known_feeds = $this->get_feeds_from_known_patterns($category_keywords);
            $discovered_feeds = array_merge($discovered_feeds, $known_feeds);
            
            // Validate discovered feeds
            $validated_feeds = array();
            foreach ($discovered_feeds as $feed) {
                $validation = $this->validate_rss_feed($feed['url']);
                if ($validation['valid']) {
                    $feed['quality_score'] = $this->calculate_feed_quality_score($feed, $validation);
                    $feed['last_validated'] = current_time('Y-m-d H:i:s');
                    $validated_feeds[] = $feed;
                }
            }
            
            // Sort by quality score
            usort($validated_feeds, function($a, $b) {
                return $b['quality_score'] - $a['quality_score'];
            });
            
            $result = array(
                'success' => true,
                'discovered_feeds' => $validated_feeds,
                'total_feeds' => count($validated_feeds),
                'bundle_name' => $bundle_name,
                'discovery_method' => 'automatic',
                'timestamp' => current_time('Y-m-d H:i:s')
            );
            
            // Cache the results for 6 hours
            $this->cache_manager->set($cache_key, $result, 21600);
            
            return $result;
            
        } catch (Exception $e) {
            $this->logger->error('RSS feed discovery failed', array(
                'bundle_name' => $bundle_name,
                'error' => $e->getMessage()
            ));
            
            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'discovered_feeds' => array(),
                'total_feeds' => 0
            );
        }
    }
    
    /**
     * Validate RSS feed
     *
     * @param string $feed_url Feed URL
     * @return array Validation result
     */
    public function validate_rss_feed($feed_url) {
        try {
            // Basic URL validation
            if (!filter_var($feed_url, FILTER_VALIDATE_URL)) {
                return array(
                    'valid' => false,
                    'error' => 'Invalid URL format',
                    'url' => $feed_url
                );
            }
            
            // Check if URL is accessible
            $response = wp_remote_get($feed_url, array(
                'timeout' => 30,
                'user-agent' => 'ContentPilot RSS Validator/2.0',
                'sslverify' => true,
                'headers' => array(
                    'Accept' => 'application/rss+xml, application/xml, text/xml, */*'
                )
            ));
            
            if (is_wp_error($response)) {
                return array(
                    'valid' => false,
                    'error' => $response->get_error_message(),
                    'url' => $feed_url
                );
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                return array(
                    'valid' => false,
                    'error' => 'HTTP Error: ' . $response_code,
                    'url' => $feed_url
                );
            }
            
            $body = wp_remote_retrieve_body($response);
            if (empty($body)) {
                return array(
                    'valid' => false,
                    'error' => 'Empty response body',
                    'url' => $feed_url
                );
            }
            
            // Validate XML structure
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($body);
            
            if ($xml === false) {
                return array(
                    'valid' => false,
                    'error' => 'Invalid XML structure',
                    'url' => $feed_url
                );
            }
            
            // Check for RSS/Atom feed indicators
            $feed_type = $this->detect_feed_type($xml, $body);
            if (!$feed_type) {
                return array(
                    'valid' => false,
                    'error' => 'Not a valid RSS or Atom feed',
                    'url' => $feed_url
                );
            }
            
            // Extract feed information
            $feed_info = $this->extract_feed_info($xml, $feed_type);
            $item_count = $this->count_feed_items($xml, $feed_type);
            
            return array(
                'valid' => true,
                'feed_type' => $feed_type,
                'feed_info' => $feed_info,
                'item_count' => $item_count,
                'url' => $feed_url,
                'response_code' => $response_code,
                'timestamp' => current_time('Y-m-d H:i:s')
            );
            
        } catch (Exception $e) {
            return array(
                'valid' => false,
                'error' => $e->getMessage(),
                'url' => $feed_url
            );
        }
    }
    
    /**
     * Get feeds from known patterns
     *
     * @param array $keywords Category keywords
     * @return array Known feeds
     */
    private function get_feeds_from_known_patterns($keywords) {
        $matched_feeds = array();
        $keywords_text = implode(' ', $keywords);
        
        foreach ($this->known_feed_patterns as $domain => $category) {
            $relevance_score = 0;
            
            // Calculate relevance based on keyword matches
            foreach ($keywords as $keyword) {
                if (stripos($domain, $keyword) !== false || stripos($category, $keyword) !== false) {
                    $relevance_score++;
                }
            }
            
            if ($relevance_score > 0) {
                $matched_feeds[] = array(
                    'url' => $this->generate_feed_url($domain),
                    'domain' => $domain,
                    'category' => $category,
                    'relevance_score' => $relevance_score,
                    'source' => 'known_patterns'
                );
            }
        }
        
        return $matched_feeds;
    }
    
    /**
     * Detect feed type (RSS or Atom)
     *
     * @param SimpleXMLElement $xml XML content
     * @param string $body Raw XML body
     * @return string|null Feed type
     */
    private function detect_feed_type($xml, $body) {
        // Check for RSS indicators
        if (isset($xml->channel) || isset($xml->item)) {
            return 'rss';
        }
        
        // Check for Atom indicators
        if (isset($xml->entry) || strpos($body, 'http://www.w3.org/2005/Atom') !== false) {
            return 'atom';
        }
        
        return null;
    }
    
    /**
     * Extract feed information
     *
     * @param SimpleXMLElement $xml XML content
     * @param string $feed_type Feed type
     * @return array Feed information
     */
    private function extract_feed_info($xml, $feed_type) {
        $info = array();
        
        if ($feed_type === 'rss') {
            $info['title'] = isset($xml->channel->title) ? (string) $xml->channel->title : '';
            $info['description'] = isset($xml->channel->description) ? (string) $xml->channel->description : '';
            $info['link'] = isset($xml->channel->link) ? (string) $xml->channel->link : '';
            $info['language'] = isset($xml->channel->language) ? (string) $xml->channel->language : '';
        } elseif ($feed_type === 'atom') {
            $info['title'] = isset($xml->title) ? (string) $xml->title : '';
            $info['description'] = isset($xml->subtitle) ? (string) $xml->subtitle : '';
            $info['link'] = isset($xml->link['href']) ? (string) $xml->link['href'] : '';
            $info['language'] = isset($xml->lang) ? (string) $xml->lang : '';
        }
        
        return $info;
    }
    
    /**
     * Count feed items
     *
     * @param SimpleXMLElement $xml XML content
     * @param string $feed_type Feed type
     * @return int Item count
     */
    private function count_feed_items($xml, $feed_type) {
        if ($feed_type === 'rss') {
            return isset($xml->channel->item) ? count($xml->channel->item) : 0;
        } elseif ($feed_type === 'atom') {
            return isset($xml->entry) ? count($xml->entry) : 0;
        }
        
        return 0;
    }
    
    /**
     * Calculate feed quality score
     *
     * @param array $feed Feed data
     * @param array $validation Validation result
     * @return float Quality score
     */
    private function calculate_feed_quality_score($feed, $validation) {
        $score = 50; // Base score
        
        // Bonus for having content
        if (isset($validation['item_count']) && $validation['item_count'] > 0) {
            $score += min($validation['item_count'] * 2, 20);
        }
        
        // Bonus for having description
        if (isset($validation['feed_info']['description']) && !empty($validation['feed_info']['description'])) {
            $score += 10;
        }
        
        // Bonus for relevance
        if (isset($feed['relevance_score'])) {
            $score += $feed['relevance_score'] * 5;
        }
        
        return max(0, min(100, $score));
    }
    
    /**
     * Generate RSS feed URL from domain
     *
     * @param string $domain Domain
     * @return string Feed URL
     */
    private function generate_feed_url($domain) {
        $common_paths = array('/rss', '/rss.xml', '/feed', '/feed.xml', '/atom.xml');
        
        foreach ($common_paths as $path) {
            $url = 'https://' . $domain . $path;
            $validation = $this->validate_rss_feed($url);
            if ($validation['valid']) {
                return $url;
            }
        }
        
        return 'https://' . $domain . '/rss.xml'; // Fallback
    }
}