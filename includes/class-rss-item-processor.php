<?php
/**
 * RSS Item Processor
 *
 * Handles extraction of original article URLs from RSS items, publisher information,
 * and comprehensive RSS item processing with verification integration.
 *
 * @package AI_Auto_News_Poster
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AANP_RSSItemProcessor {
    
    private $content_verifier;
    private $cache_manager;
    private $logger;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->content_verifier = new AANP_ContentVerifier();
        $this->cache_manager = new AANP_Cache_Manager();
        $this->logger = AANP_Logger::getInstance();
        
        $this->logger->debug('RSS Item Processor initialized');
    }
    
    /**
     * Extract original link from RSS item with verification
     *
     * @param SimpleXMLElement $rss_item RSS item
     * @return array Extracted URL information
     */
    public function extract_original_link($rss_item) {
        try {
            $original_urls = array();
            
            // Primary extraction from link element
            $primary_link = (string) $rss_item->link;
            if (!empty($primary_link)) {
                $original_urls[] = array(
                    'url' => $primary_link,
                    'priority' => 1,
                    'source' => 'link'
                );
            }
            
            // Fallback extraction from guid
            if (isset($rss_item->guid) && !empty($rss_item->guid)) {
                $guid = (string) $rss_item->guid;
                if (filter_var($guid, FILTER_VALIDATE_URL)) {
                    $original_urls[] = array(
                        'url' => $guid,
                        'priority' => 2,
                        'source' => 'guid'
                    );
                }
            }
            
            // Check comments URL
            if (isset($rss_item->comments) && !empty($rss_item->comments)) {
                $comments = (string) $rss_item->comments;
                if (filter_var($comments, FILTER_VALIDATE_URL)) {
                    $original_urls[] = array(
                        'url' => $comments,
                        'priority' => 3,
                        'source' => 'comments'
                    );
                }
            }
            
            // Check source element
            if (isset($rss_item->source) && !empty($rss_item->source)) {
                $source = (string) $rss_item->source;
                if (filter_var($source, FILTER_VALIDATE_URL)) {
                    $original_urls[] = array(
                        'url' => $source,
                        'priority' => 4,
                        'source' => 'source'
                    );
                }
            }
            
            // Check for enclosure URLs (sometimes used for articles)
            if (isset($rss_item->enclosure)) {
                foreach ($rss_item->enclosure as $enclosure) {
                    $type = (string) $enclosure['type'];
                    $url = (string) $enclosure['url'];
                    
                    // Look for HTML content types
                    if (!empty($url) && (strpos($type, 'text/html') !== false || empty($type))) {
                        $original_urls[] = array(
                            'url' => $url,
                            'priority' => 5,
                            'source' => 'enclosure'
                        );
                    }
                }
            }
            
            // Sort by priority and validate
            usort($original_urls, function($a, $b) {
                return $a['priority'] - $b['priority'];
            });
            
            // Find the best URL (not RSS feed)
            foreach ($original_urls as $candidate) {
                if ($this->content_verifier->is_direct_article_url($candidate['url'])) {
                    $result = array(
                        'original_url' => $candidate['url'],
                        'url_source' => $candidate['source'],
                        'verified' => false,
                        'verification_result' => null
                    );
                    
                    // Verify the URL
                    $verification = $this->content_verifier->validate_source_url($candidate['url']);
                    $result['verified'] = $verification['status'] === 'verified';
                    $result['verification_result'] = $verification;
                    
                    return $result;
                }
            }
            
            // If no direct article found, return the highest priority URL
            if (!empty($original_urls)) {
                $result = array(
                    'original_url' => $original_urls[0]['url'],
                    'url_source' => $original_urls[0]['source'],
                    'verified' => false,
                    'verification_result' => array(
                        'status' => 'warning',
                        'message' => 'RSS feed URL detected, not direct article'
                    )
                );
                
                return $result;
            }
            
            return array(
                'original_url' => null,
                'url_source' => null,
                'verified' => false,
                'verification_result' => array(
                    'status' => 'error',
                    'message' => 'No valid URL found in RSS item'
                )
            );
            
        } catch (Exception $e) {
            $this->logger->error('Failed to extract original link', array(
                'error' => $e->getMessage()
            ));
            
            return array(
                'original_url' => null,
                'url_source' => null,
                'verified' => false,
                'verification_result' => array(
                    'status' => 'error',
                    'message' => 'Extraction failed: ' . $e->getMessage()
                )
            );
        }
    }
    
    /**
     * Get article publisher information
     *
     * @param string $original_url Original article URL
     * @return array Publisher information
     */
    public function get_article_publisher_info($original_url) {
        try {
            $cache_key = 'publisher_info_' . md5($original_url);
            $cached_info = $this->cache_manager->get($cache_key);
            if ($cached_info !== false) {
                return $cached_info;
            }
            
            $domain = parse_url($original_url, PHP_URL_HOST);
            if (!$domain) {
                return array(
                    'publisher_name' => 'Unknown Publisher',
                    'publisher_url' => '',
                    'domain' => null,
                    'credibility_score' => 50
                );
            }
            
            // Attempt to extract publisher info from the article itself
            $publisher_info = array(
                'publisher_name' => $this->extract_publisher_name($domain),
                'publisher_url' => 'https://' . $domain,
                'domain' => $domain,
                'credibility_score' => $this->get_domain_credibility($domain)
            );
            
            // Cache for 24 hours
            $this->cache_manager->set($cache_key, $publisher_info, 86400);
            
            return $publisher_info;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to get publisher info', array(
                'url' => $original_url,
                'error' => $e->getMessage()
            ));
            
            return array(
                'publisher_name' => 'Unknown Publisher',
                'publisher_url' => '',
                'domain' => parse_url($original_url, PHP_URL_HOST),
                'credibility_score' => 50
            );
        }
    }
    
    /**
     * Validate article legitimacy
     *
     * @param string $original_url Original article URL
     * @param string $content Optional article content
     * @return array Validation result
     */
    public function validate_article_legitimacy($original_url, $content = '') {
        try {
            $validation = $this->content_verifier->detect_retracted_content($original_url, $content);
            
            // Additional legitimacy checks
            $url_validation = $this->content_verifier->validate_source_url($original_url);
            
            $result = array(
                'legitimate' => true,
                'issues' => array(),
                'retracted' => $validation['retracted'],
                'retraction_confidence' => $validation['confidence'] ?? 0,
                'accessibility' => $url_validation['accessible'],
                'direct_article' => $url_validation['is_direct_article'] ?? false
            );
            
            // Add issues based on validation
            if ($validation['retracted']) {
                $result['legitimate'] = false;
                $result['issues'][] = 'Content appears to be retracted';
            }
            
            if (!$url_validation['accessible']) {
                $result['issues'][] = 'Article not accessible';
            }
            
            if (!$url_validation['is_direct_article']) {
                $result['issues'][] = 'URL points to RSS feed, not direct article';
            }
            
            return $result;
            
        } catch (Exception $e) {
            return array(
                'legitimate' => false,
                'error' => $e->getMessage(),
                'issues' => array('Validation failed')
            );
        }
    }
    
    /**
     * Extract publication date from RSS item
     *
     * @param SimpleXMLElement $rss_item RSS item
     * @return array Date information
     */
    public function extract_publication_date($rss_item) {
        try {
            $date_sources = array();
            
            // Check pubDate (RSS 2.0)
            if (isset($rss_item->pubDate)) {
                $date_sources['pubDate'] = (string) $rss_item->pubDate;
            }
            
            // Check updated (Atom)
            if (isset($rss_item->updated)) {
                $date_sources['updated'] = (string) $rss_item->updated;
            }
            
            // Check published (Atom)
            if (isset($rss_item->published)) {
                $date_sources['published'] = (string) $rss_item->published;
            }
            
            // Check DC date
            if (isset($rss_item->children('http://purl.org/dc/elements/1.1/')->date)) {
                $date_sources['dc:date'] = (string) $rss_item->children('http://purl.org/dc/elements/1.1/')->date;
            }
            
            $parsed_date = null;
            $date_source = null;
            
            // Try to parse dates in order of preference
            foreach ($date_sources as $source => $date_string) {
                $timestamp = strtotime($date_string);
                if ($timestamp !== false && $timestamp > 0) {
                    $parsed_date = date('Y-m-d H:i:s', $timestamp);
                    $date_source = $source;
                    break;
                }
            }
            
            // If no valid date found, return current time
            if (!$parsed_date) {
                return array(
                    'publication_date' => current_time('mysql'),
                    'date_source' => 'current_time_fallback',
                    'date_format' => 'Y-m-d H:i:s'
                );
            }
            
            return array(
                'publication_date' => $parsed_date,
                'date_source' => $date_source,
                'date_format' => 'Y-m-d H:i:s'
            );
            
        } catch (Exception $e) {
            return array(
                'publication_date' => current_time('mysql'),
                'date_source' => 'error_fallback',
                'date_format' => 'Y-m-d H:i:s'
            );
        }
    }
    
    /**
     * Get author information from RSS item
     *
     * @param SimpleXMLElement $rss_item RSS item
     * @return array Author information
     */
    public function get_author_information($rss_item) {
        try {
            $author_info = array(
                'author_name' => null,
                'author_email' => null,
                'author_source' => null
            );
            
            // Check author element (RSS 2.0)
            if (isset($rss_item->author)) {
                $author_info['author_name'] = (string) $rss_item->author;
                $author_info['author_source'] = 'author';
            }
            
            // Check DC author
            if (!$author_info['author_name'] && isset($rss_item->children('http://purl.org/dc/elements/1.1/')->creator)) {
                $author_info['author_name'] = (string) $rss_item->children('http://purl.org/dc/elements/1.1/')->creator;
                $author_info['author_source'] = 'dc:creator';
            }
            
            // Check Atom author
            if (!$author_info['author_name'] && isset($rss_item->author->name)) {
                $author_info['author_name'] = (string) $rss_item->author->name;
                $author_info['author_source'] = 'atom:author';
            }
            
            // Check for email in author field
            if ($author_info['author_name'] && strpos($author_info['author_name'], '@') !== false) {
                // Looks like email
                $parts = explode('@', $author_info['author_name']);
                if (count($parts) === 2) {
                    $author_info['author_email'] = $author_info['author_name'];
                    $author_info['author_name'] = $parts[0];
                }
            }
            
            return $author_info;
            
        } catch (Exception $e) {
            return array(
                'author_name' => null,
                'author_email' => null,
                'author_source' => 'error'
            );
        }
    }
    
    /**
     * Process RSS item with full verification
     *
     * @param SimpleXMLElement $rss_item RSS item
     * @param string $feed_url Source feed URL
     * @return array Processed item with verification
     */
    public function process_rss_item($rss_item, $feed_url) {
        try {
            $processed = array();
            
            // Extract basic information
            $processed['title'] = (string) $rss_item->title;
            $processed['description'] = isset($rss_item->description) ? (string) $rss_item->description : '';
            $processed['guid'] = isset($rss_item->guid) ? (string) $rss_item->guid : '';
            
            // Extract and verify original URL
            $url_info = $this->extract_original_link($rss_item);
            $processed['original_url'] = $url_info['original_url'];
            $processed['url_source'] = $url_info['url_source'];
            $processed['url_verified'] = $url_info['verified'];
            
            // Get publisher information
            if ($processed['original_url']) {
                $processed['publisher_info'] = $this->get_article_publisher_info($processed['original_url']);
            } else {
                $processed['publisher_info'] = array(
                    'publisher_name' => 'Unknown Publisher',
                    'publisher_url' => '',
                    'domain' => parse_url($feed_url, PHP_URL_HOST),
                    'credibility_score' => 50
                );
            }
            
            // Extract publication date
            $processed['publication_date'] = $this->extract_publication_date($rss_item);
            
            // Get author information
            $processed['author_info'] = $this->get_author_information($rss_item);
            
            // Validate article legitimacy
            if ($processed['original_url']) {
                $processed['legitimacy'] = $this->validate_article_legitimacy(
                    $processed['original_url'], 
                    $processed['description']
                );
            } else {
                $processed['legitimacy'] = array(
                    'legitimate' => false,
                    'issues' => array('No original URL found')
                );
            }
            
            // Verify RSS item itself
            $processed['rss_verification'] = $this->content_verifier->verify_rss_item_legitimacy($rss_item);
            
            // Calculate overall quality score
            $processed['quality_score'] = $this->calculate_quality_score($processed);
            
            return $processed;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to process RSS item', array(
                'error' => $e->getMessage()
            ));
            
            return array(
                'title' => (string) $rss_item->title,
                'processed' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Extract publisher name from domain
     *
     * @param string $domain Domain name
     * @return string Publisher name
     */
    private function extract_publisher_name($domain) {
        // Remove www prefix
        $domain = preg_replace('/^www\./', '', $domain);
        
        // Extract base domain name
        $parts = explode('.', $domain);
        if (count($parts) >= 2) {
            $base_domain = $parts[count($parts) - 2];
        } else {
            $base_domain = $domain;
        }
        
        // Capitalize and format
        $publisher_name = ucfirst($base_domain);
        
        // Known mappings for common news sites
        $publisher_mappings = array(
            'bbc' => 'BBC News',
            'cnn' => 'CNN',
            'reuters' => 'Reuters',
            'guardian' => 'The Guardian',
            'nytimes' => 'The New York Times',
            'washingtonpost' => 'The Washington Post',
            'apnews' => 'Associated Press',
            'sky' => 'Sky News',
            'telegraph' => 'The Daily Telegraph',
            'independent' => 'The Independent'
        );
        
        return isset($publisher_mappings[$base_domain]) ? 
            $publisher_mappings[$base_domain] : $publisher_name;
    }
    
    /**
     * Get domain credibility score
     *
     * @param string $domain Domain name
     * @return int Credibility score (0-100)
     */
    private function get_domain_credibility($domain) {
        $credibility_scores = array(
            'bbc.co.uk' => 95,
            'reuters.com' => 90,
            'cnn.com' => 85,
            'apnews.com' => 90,
            'guardian.co.uk' => 88,
            'nytimes.com' => 90,
            'washingtonpost.com' => 85,
            'theatlantic.com' => 80,
            'economist.com' => 85,
            'bloomberg.com' => 85,
            'wsj.com' => 85,
            'ft.com' => 85,
            'independent.co.uk' => 75,
            'sky.com' => 70,
            'itv.com' => 70,
            'telegraph.co.uk' => 70
        );
        
        if (isset($credibility_scores[$domain])) {
            return $credibility_scores[$domain];
        }
        
        // Default score based on TLD
        if (strpos($domain, '.gov') !== false) {
            return 85;
        } elseif (strpos($domain, '.edu') !== false) {
            return 75;
        } elseif (strpos($domain, '.com') !== false) {
            return 60;
        } else {
            return 50;
        }
    }
    
    /**
     * Calculate overall quality score for processed item
     *
     * @param array $processed_item Processed item
     * @return float Quality score (0-1)
     */
    private function calculate_quality_score($processed_item) {
        $score = 0.0;
        $max_score = 0.0;
        
        // URL verification (20 points)
        $max_score += 0.20;
        if ($processed_item['url_verified']) {
            $score += 0.20;
        }
        
        // Legitimacy check (30 points)
        $max_score += 0.30;
        if ($processed_item['legitimacy']['legitimate']) {
            $score += 0.30;
        }
        
        // Publisher credibility (25 points)
        $max_score += 0.25;
        $credibility = $processed_item['publisher_info']['credibility_score'];
        $score += ($credibility / 100) * 0.25;
        
        // RSS verification (15 points)
        $max_score += 0.15;
        if ($processed_item['rss_verification']['legitimate']) {
            $score += 0.15;
        }
        
        // Publication date (10 points)
        $max_score += 0.10;
        if ($processed_item['publication_date']['date_source'] !== 'current_time_fallback') {
            $score += 0.10;
        }
        
        return $max_score > 0 ? ($score / $max_score) : 0.0;
    }
}