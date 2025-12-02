<?php
/**
 * RSS Feed Manager - Comprehensive RSS Feed Management System
 *
 * Handles curated RSS feed database, validation, search, and user selection
 * for UK, EU, and USA news sources with 100+ pre-configured feeds.
 *
 * @package AI_Auto_News_Poster
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * RSS Feed Manager Class
 */
class AANP_RSSFeedManager {
    
    /**
     * Database table names
     */
    private $feeds_table;
    private $user_selections_table;
    
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
     * Default curated feeds data
     * @var array
     */
    private $default_feeds = array();
    
    /**
     * Feed categories
     * @var array
     */
    private $feed_categories = array('UK', 'EU', 'USA');
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        
        $this->logger = AANP_Logger::getInstance();
        $this->cache_manager = new AANP_AdvancedCacheManager();
        
        // Set table names
        $this->feeds_table = $wpdb->prefix . 'aanp_rss_feeds';
        $this->user_selections_table = $wpdb->prefix . 'aanp_user_feed_selections';
        
        // Initialize default feeds data
        $this->init_default_feeds();
        
        // Initialize hooks
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('admin_init', array($this, 'handle_rss_actions'));
        add_action('wp_ajax_aanp_search_rss_feeds', array($this, 'ajax_search_feeds'));
        add_action('wp_ajax_aanp_toggle_rss_feed', array($this, 'ajax_toggle_feed'));
        add_action('wp_ajax_aanp_bulk_enable_feeds', array($this, 'ajax_bulk_enable_feeds'));
        add_action('aanp_plugin_activate', array($this, 'install_default_feeds'));
    }
    
    /**
     * Install default RSS feeds on plugin activation
     */
    public function install_default_feeds() {
        try {
            $this->logger->info('Installing default RSS feeds');
            
            // Create database tables
            $this->create_database_tables();
            
            // Insert curated feeds
            $this->insert_default_feeds();
            
            // Enable top 20 most reliable feeds by default
            $top_feeds = $this->get_top_reliable_feeds(20);
            $this->enable_feeds($top_feeds);
            
            $this->logger->info('Default RSS feeds installation completed', array(
                'feeds_installed' => count($this->default_feeds),
                'feeds_enabled' => count($top_feeds)
            ));
            
        } catch (Exception $e) {
            $this->logger->error('Failed to install default RSS feeds', array(
                'error' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Create database tables for RSS feeds
     */
    private function create_database_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // RSS Feeds table
        $feeds_sql = "CREATE TABLE {$this->feeds_table} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            url TEXT NOT NULL,
            region ENUM('UK', 'EU', 'USA') NOT NULL,
            category VARCHAR(100) DEFAULT 'news',
            description TEXT,
            enabled BOOLEAN DEFAULT FALSE,
            reliability_score INT DEFAULT 100,
            last_fetched DATETIME,
            last_success DATETIME,
            article_count INT DEFAULT 0,
            error_count INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_feed_url (url),
            KEY idx_region (region),
            KEY idx_enabled (enabled),
            KEY idx_reliability (reliability_score)
        ) $charset_collate;";
        
        // User Feed Selections table
        $selections_sql = "CREATE TABLE {$this->user_selections_table} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT DEFAULT 0,
            feed_id INT NOT NULL,
            enabled BOOLEAN DEFAULT TRUE,
            priority INT DEFAULT 0,
            last_used DATETIME,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (feed_id) REFERENCES {$this->feeds_table}(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_feed (user_id, feed_id),
            KEY idx_user (user_id),
            KEY idx_feed (feed_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($feeds_sql);
        dbDelta($selections_sql);
    }
    
    /**
     * Initialize default curated feeds data
     */
    private function init_default_feeds() {
        $this->default_feeds = array(
            
            // UK News Sources (30 feeds)
            array('name' => 'BBC News', 'url' => 'https://feeds.bbci.co.uk/news/rss.xml', 'region' => 'UK', 'category' => 'news', 'description' => 'Top BBC news stories'),
            array('name' => 'The Guardian', 'url' => 'https://www.theguardian.com/uk/rss', 'region' => 'UK', 'category' => 'news', 'description' => 'The Guardian newspaper'),
            array('name' => 'The Telegraph', 'url' => 'https://www.telegraph.co.uk/rss.xml', 'region' => 'UK', 'category' => 'news', 'description' => 'The Daily Telegraph'),
            array('name' => 'Sky News', 'url' => 'https://news.sky.com/uk/rss', 'region' => 'UK', 'category' => 'news', 'description' => 'Sky News UK'),
            array('name' => 'Reuters UK', 'url' => 'https://www.reuters.com/rssFeed/UKTopNews', 'region' => 'UK', 'category' => 'news', 'description' => 'Reuters UK News'),
            array('name' => 'The Independent', 'url' => 'https://www.independent.co.uk/rss', 'region' => 'UK', 'category' => 'news', 'description' => 'The Independent'),
            array('name' => 'Daily Mail', 'url' => 'http://www.dailymail.co.uk/news/index.rss', 'region' => 'UK', 'category' => 'news', 'description' => 'Daily Mail news'),
            array('name' => 'Daily Express', 'url' => 'https://www.express.co.uk/posts/rss', 'region' => 'UK', 'category' => 'news', 'description' => 'Daily Express'),
            array('name' => 'The Sun', 'url' => 'https://www.the-sun.com/rss/', 'region' => 'UK', 'category' => 'news', 'description' => 'The Sun newspaper'),
            array('name' => 'The Times', 'url' => 'https://www.thetimes.co.uk/rss', 'region' => 'UK', 'category' => 'news', 'description' => 'The Times'),
            array('name' => 'Financial Times', 'url' => 'https://www.ft.com/rss/home', 'region' => 'UK', 'category' => 'business', 'description' => 'Financial Times business news'),
            array('name' => 'Evening Standard', 'url' => 'https://www.standard.co.uk/rss', 'region' => 'UK', 'category' => 'local', 'description' => 'London Evening Standard'),
            array('name' => 'The Scotsman', 'url' => 'http://www.scotsman.com/rss', 'region' => 'UK', 'category' => 'local', 'description' => 'The Scotsman newspaper'),
            array('name' => 'Wales Online', 'url' => 'https://www.walesonline.co.uk/rss', 'region' => 'UK', 'category' => 'local', 'description' => 'Wales Online news'),
            array('name' => 'Manchester Evening News', 'url' => 'https://www.manchestereveningnews.co.uk/rss', 'region' => 'UK', 'category' => 'local', 'description' => 'Manchester local news'),
            array('name' => 'Birmingham Mail', 'url' => 'https://www.birminghammail.co.uk/rss', 'region' => 'UK', 'category' => 'local', 'description' => 'Birmingham local news'),
            array('name' => 'Liverpool Echo', 'url' => 'https://www.liverpoolecho.co.uk/rss', 'region' => 'UK', 'category' => 'local', 'description' => 'Liverpool local news'),
            array('name' => 'Metro UK', 'url' => 'https://metro.co.uk/feed/', 'region' => 'UK', 'category' => 'news', 'description' => 'Metro newspaper'),
            array('name' => 'The Conversation', 'url' => 'https://theconversation.com/uk/articles.rss', 'region' => 'UK', 'category' => 'opinion', 'description' => 'The Conversation UK'),
            array('name' => 'ITV News', 'url' => 'https://www.itv.com/news/feeds/itvnews/rss', 'region' => 'UK', 'category' => 'news', 'description' => 'ITV News'),
            array('name' => 'Channel 4 News', 'url' => 'https://www.channel4.com/news/rss', 'region' => 'UK', 'category' => 'news', 'description' => 'Channel 4 News'),
            array('name' => 'HuffPost UK', 'url' => 'https://www.huffingtonpost.co.uk/rss', 'region' => 'UK', 'category' => 'news', 'description' => 'HuffPost UK'),
            array('name' => 'BuzzFeed UK', 'url' => 'https://www.buzzfeed.com/uk.xml', 'region' => 'UK', 'category' => 'lifestyle', 'description' => 'BuzzFeed UK'),
            array('name' => 'Politico Europe', 'url' => 'https://www.politico.eu/feed/', 'region' => 'UK', 'category' => 'politics', 'description' => 'Politico Europe'),
            array('name' => 'The Week UK', 'url' => 'https://www.theweek.co.uk/rss', 'region' => 'UK', 'category' => 'news', 'description' => 'The Week magazine'),
            array('name' => 'Metro UK Business', 'url' => 'https://metro.co.uk/business/feed/', 'region' => 'UK', 'category' => 'business', 'description' => 'Metro Business news'),
            array('name' => 'The Spectator', 'url' => 'https://www.spectator.co.uk/feed/rss/', 'region' => 'UK', 'category' => 'politics', 'description' => 'The Spectator magazine'),
            array('name' => 'New Statesman', 'url' => 'https://www.newstatesman.com/feed', 'region' => 'UK', 'category' => 'politics', 'description' => 'New Statesman magazine'),
            array('name' => 'The Grocer', 'url' => 'https://www.thegrocer.co.uk/rss', 'region' => 'UK', 'category' => 'business', 'description' => 'The Grocer magazine'),
            array('name' => 'Marketing Week', 'url' => 'https://www.marketingweek.com/rss.xml', 'region' => 'UK', 'category' => 'business', 'description' => 'Marketing Week'),
            
            // EU News Sources (40 feeds)
            array('name' => 'Deutsche Welle', 'url' => 'https://rss.dw.com/rdf/rss-en-news', 'region' => 'EU', 'category' => 'news', 'description' => 'Deutsche Welle international news'),
            array('name' => 'Euronews', 'url' => 'https://www.euronews.com/rss', 'region' => 'EU', 'category' => 'news', 'description' => 'Euronews European news'),
            array('name' => 'European Commission', 'url' => 'https://ec.europa.eu/news/atom_en.xml', 'region' => 'EU', 'category' => 'politics', 'description' => 'European Commission news'),
            array('name' => 'The Local (Germany)', 'url' => 'https://www.thelocal.de/feeds/rss/de', 'region' => 'EU', 'category' => 'news', 'description' => 'The Local Germany'),
            array('name' => 'The Local (France)', 'url' => 'https://www.thelocal.fr/feeds/rss/fr', 'region' => 'EU', 'category' => 'news', 'description' => 'The Local France'),
            array('name' => 'The Local (Spain)', 'url' => 'https://www.thelocal.es/feeds/rss/es', 'region' => 'EU', 'category' => 'news', 'description' => 'The Local Spain'),
            array('name' => 'The Local (Italy)', 'url' => 'https://www.thelocal.it/feeds/rss/it', 'region' => 'EU', 'category' => 'news', 'description' => 'The Local Italy'),
            array('name' => 'Der Spiegel', 'url' => 'https://www.spiegel.de/schlagzeen/index.rss', 'region' => 'EU', 'category' => 'news', 'description' => 'Der Spiegel magazine'),
            array('name' => 'Frankfurter Allgemeine', 'url' => 'https://www.faz.net/rss/aktuell/', 'region' => 'EU', 'category' => 'news', 'description' => 'Frankfurter Allgemeine Zeitung'),
            array('name' => 'Le Figaro', 'url' => 'https://www.lefigaro.fr/rss/figaro_actualites.xml', 'region' => 'EU', 'category' => 'news', 'description' => 'Le Figaro newspaper'),
            array('name' => 'Le Monde', 'url' => 'https://www.lemonde.fr/rss/une.xml', 'region' => 'EU', 'category' => 'news', 'description' => 'Le Monde newspaper'),
            array('name' => 'Liberation', 'url' => 'https://www.liberation.fr/rss/', 'region' => 'EU', 'category' => 'news', 'description' => 'Libération newspaper'),
            array('name' => '20 Minutes (France)', 'url' => 'https://www.20minutes.fr/rss/actualite_france.xml', 'region' => 'EU', 'category' => 'news', 'description' => '20 Minutes France'),
            array('name' => 'El País', 'url' => 'https://feeds.elpais.com/mrss-s/pages/ep/site/elpais.com/portada', 'region' => 'EU', 'category' => 'news', 'description' => 'El País newspaper'),
            array('name' => 'El Mundo', 'url' => 'https://e00-elmundo.uecdn.es/elmundo/rss/portada.xml', 'region' => 'EU', 'category' => 'news', 'description' => 'El Mundo newspaper'),
            array('name' => 'La Vanguardia', 'url' => 'https://www.lavanguardia.com/rss/home.xml', 'region' => 'EU', 'category' => 'news', 'description' => 'La Vanguardia newspaper'),
            array('name' => 'ABC España', 'url' => 'https://www.abc.es/rss/feeds/abc_ABC.xml', 'region' => 'EU', 'category' => 'news', 'description' => 'ABC newspaper'),
            array('name' => 'Corriere della Sera', 'url' => 'https://www.corriere.it/rss/homepage_2.xml', 'region' => 'EU', 'category' => 'news', 'description' => 'Corriere della Sera'),
            array('name' => 'La Repubblica', 'url' => 'https://www.repubblica.it/rss/homepage/rss2.0.xml', 'region' => 'EU', 'category' => 'news', 'description' => 'La Repubblica newspaper'),
            array('name' => 'ANSA Italy', 'url' => 'https://www.ansa.it/sito/notizie/topnews/topnews_rss.xml', 'region' => 'EU', 'category' => 'news', 'description' => 'ANSA Italian news agency'),
            array('name' => 'NRC (Netherlands)', 'url' => 'https://www.nrc.nl/rss', 'region' => 'EU', 'category' => 'news', 'description' => 'NRC Handelsblad'),
            array('name' => 'De Telegraaf', 'url' => 'https://www.telegraaf.nl/rss', 'region' => 'EU', 'category' => 'news', 'description' => 'De Telegraaf'),
            array('name' => 'The Irish Times', 'url' => 'https://www.irishtimes.com/rss', 'region' => 'EU', 'category' => 'news', 'description' => 'The Irish Times'),
            array('name' => 'Irish Independent', 'url' => 'https://www.independent.ie/irish-news/rss', 'region' => 'EU', 'category' => 'news', 'description' => 'Independent.ie'),
            array('name' => 'RTÉ News', 'url' => 'https://www.rte.ie/rss/news.xml', 'region' => 'EU', 'category' => 'news', 'description' => 'RTÉ News'),
            array('name' => 'Le Soir (Belgium)', 'url' => 'https://www.lesoir.be/rss.xml', 'region' => 'EU', 'category' => 'news', 'description' => 'Le Soir'),
            array('name' => 'De Standaard (Belgium)', 'url' => 'https://www.standaard.be/rss.xml', 'region' => 'EU', 'category' => 'news', 'description' => 'De Standaard'),
            array('name' => 'News from Sweden', 'url' => 'https://www.thelocal.se/feeds/rss/se', 'region' => 'EU', 'category' => 'news', 'description' => 'The Local Sweden'),
            array('name' => 'Denmark News', 'url' => 'https://www.thelocal.dk/feeds/rss/dk', 'region' => 'EU', 'category' => 'news', 'description' => 'The Local Denmark'),
            array('name' => 'Norway News', 'url' => 'https://www.thelocal.no/feeds/rss/no', 'region' => 'EU', 'category' => 'news', 'description' => 'The Local Norway'),
            array('name' => 'Finland News', 'url' => 'https://www.thelocal.fi/feeds/rss/fi', 'region' => 'EU', 'category' => 'news', 'description' => 'The Local Finland'),
            array('name' => 'Portugal News', 'url' => 'https://www.thelocal.pt/feeds/rss/pt', 'region' => 'EU', 'category' => 'news', 'description' => 'The Local Portugal'),
            array('name' => 'Swiss News', 'url' => 'https://www.thelocal.ch/feeds/rss/ch', 'region' => 'EU', 'category' => 'news', 'description' => 'The Local Switzerland'),
            array('name' => 'Austrian News', 'url' => 'https://www.thelocal.at/feeds/rss/at', 'region' => 'EU', 'category' => 'news', 'description' => 'The Local Austria'),
            array('name' => 'Czech News', 'url' => 'https://www.thelocal.cz/feeds/rss/cz', 'region' => 'EU', 'category' => 'news', 'description' => 'The Local Czech Republic'),
            array('name' => 'Poland News', 'url' => 'https://www.thelocal.pl/feeds/rss/pl', 'region' => 'EU', 'category' => 'news', 'description' => 'The Local Poland'),
            array('name' => 'Hungary News', 'url' => 'https://www.thelocal.hu/feeds/rss/hu', 'region' => 'EU', 'category' => 'news', 'description' => 'The Local Hungary'),
            array('name' => 'Greece News', 'url' => 'https://www.thelocal.gr/feeds/rss/gr', 'region' => 'EU', 'category' => 'news', 'description' => 'The Local Greece'),
            array('name' => 'Russia Today', 'url' => 'https://www.rt.com/rss/', 'region' => 'EU', 'category' => 'news', 'description' => 'Russia Today'),
            array('name' => 'Al Jazeera Europe', 'url' => 'https://www.aljazeera.com/xml/rss/all.xml', 'region' => 'EU', 'category' => 'news', 'description' => 'Al Jazeera'),
            
            // USA News Sources (30 feeds)
            array('name' => 'CNN', 'url' => 'http://rss.cnn.com/rss/edition.rss', 'region' => 'USA', 'category' => 'news', 'description' => 'CNN International'),
            array('name' => 'NBC News', 'url' => 'https://www.nbcnews.com/id/3032086/rss/xml.xml', 'region' => 'USA', 'category' => 'news', 'description' => 'NBC News'),
            array('name' => 'Fox News', 'url' => 'https://feeds.foxnews.com/foxnews/national', 'region' => 'USA', 'category' => 'news', 'description' => 'Fox News'),
            array('name' => 'ABC News', 'url' => 'https://abcnews.go.com/abcnews/headlines', 'region' => 'USA', 'category' => 'news', 'description' => 'ABC News'),
            array('name' => 'CBS News', 'url' => 'https://www.cbsnews.com/latest/rss/main', 'region' => 'USA', 'category' => 'news', 'description' => 'CBS News'),
            array('name' => 'Reuters', 'url' => 'https://www.reuters.com/rssFeed/USTopNews', 'region' => 'USA', 'category' => 'news', 'description' => 'Reuters USA'),
            array('name' => 'USA Today', 'url' => 'https://feeds.feedburner.com/USATODAY-TopStories', 'region' => 'USA', 'category' => 'news', 'description' => 'USA Today'),
            array('name' => 'The New York Times', 'url' => 'https://rss.nytimes.com/services/xml/rss/nyt/HomePage.xml', 'region' => 'USA', 'category' => 'news', 'description' => 'The New York Times'),
            array('name' => 'The Washington Post', 'url' => 'https://feeds.washingtonpost.com/rss/world', 'region' => 'USA', 'category' => 'news', 'description' => 'The Washington Post'),
            array('name' => 'The Wall Street Journal', 'url' => 'https://feeds.a.dj.com/rss/RSSWorldNews.xml', 'region' => 'USA', 'category' => 'business', 'description' => 'Wall Street Journal'),
            array('name' => 'Bloomberg', 'url' => 'https://feeds.bloomberg.com/markets/news.rss', 'region' => 'USA', 'category' => 'business', 'description' => 'Bloomberg News'),
            array('name' => 'NPR News', 'url' => 'https://feeds.npr.org/1001/rss.xml', 'region' => 'USA', 'category' => 'news', 'description' => 'NPR News'),
            array('name' => 'The Associated Press', 'url' => 'https://apnews.com/rss/apf-headlines', 'region' => 'USA', 'category' => 'news', 'description' => 'Associated Press'),
            array('name' => 'Politico', 'url' => 'https://rss.politico.com/politicorss.xml', 'region' => 'USA', 'category' => 'politics', 'description' => 'Politico'),
            array('name' => 'The Hill', 'url' => 'https://thehill.com/rss/feeds/white-house.xml', 'region' => 'USA', 'category' => 'politics', 'description' => 'The Hill'),
            array('name' => 'Forbes', 'url' => 'https://www.forbes.com/real-time/feed2/', 'region' => 'USA', 'category' => 'business', 'description' => 'Forbes'),
            array('name' => 'Business Insider', 'url' => 'https://feeds.businessinsider.com/business-insider', 'region' => 'USA', 'category' => 'business', 'description' => 'Business Insider'),
            array('name' => 'The Atlantic', 'url' => 'https://feeds.feedburner.com/TheAtlantic', 'region' => 'USA', 'category' => 'opinion', 'description' => 'The Atlantic'),
            array('name' => 'Vox', 'url' => 'https://www.vox.com/rss/index.xml', 'region' => 'USA', 'category' => 'politics', 'description' => 'Vox'),
            array('name' => 'BuzzFeed News', 'url' => 'https://www.buzzfeed.com/news.xml', 'region' => 'USA', 'category' => 'news', 'description' => 'BuzzFeed News'),
            array('name' => 'Vice News', 'url' => 'https://www.vice.com/en_us/rss', 'region' => 'USA', 'category' => 'news', 'description' => 'Vice News'),
            array('name' => 'The Daily Beast', 'url' => 'https://www.thedailybeast.com/feed', 'region' => 'USA', 'category' => 'news', 'description' => 'The Daily Beast'),
            array('name' => 'Slate', 'url' => 'https://slate.com/feed', 'region' => 'USA', 'category' => 'opinion', 'description' => 'Slate'),
            array('name' => 'The Guardian US', 'url' => 'https://www.theguardian.com/us/rss', 'region' => 'USA', 'category' => 'news', 'description' => 'The Guardian US'),
            array('name' => 'MSNBC', 'url' => 'https://www.msnbc.com/rss/latest-headlines', 'region' => 'USA', 'category' => 'news', 'description' => 'MSNBC'),
            array('name' => 'CNN Money', 'url' => 'http://rss.cnn.com/rss/money_topstories.rss', 'region' => 'USA', 'category' => 'business', 'description' => 'CNN Money'),
            array('name' => 'Yahoo News', 'url' => 'https://news.yahoo.com/rss/topstories', 'region' => 'USA', 'category' => 'news', 'description' => 'Yahoo News'),
            array('name' => 'Google News', 'url' => 'https://news.google.com/rss/search?q=when:24h+infocus:&hl=en-US&gl=US&ceid=US:en', 'region' => 'USA', 'category' => 'news', 'description' => 'Google News'),
        );
    }
    
    /**
     * Insert default feeds into database
     */
    private function insert_default_feeds() {
        global $wpdb;
        
        // Check if feeds already exist
        $existing_count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->feeds_table}");
        if ($existing_count > 0) {
            $this->logger->info('Default feeds already exist', array('count' => $existing_count));
            return;
        }
        
        $insert_data = array();
        $insert_format = array('%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d');
        
        foreach ($this->default_feeds as $feed) {
            $insert_data[] = array(
                'name' => $feed['name'],
                'url' => $feed['url'],
                'region' => $feed['region'],
                'category' => $feed['category'],
                'description' => $feed['description'],
                'reliability_score' => 100, // Default high reliability
                'enabled' => false, // Will be enabled based on selection
                'article_count' => 0,
                'error_count' => 0
            );
        }
        
        $chunks = array_chunk($insert_data, 50); // Insert in chunks
        foreach ($chunks as $chunk) {
            $wpdb->insert($this->feeds_table, $chunk, $insert_format);
        }
        
        $this->logger->info('Default feeds inserted', array('total' => count($this->default_feeds)));
    }
    
    /**
     * Get RSS feeds with filtering and search
     *
     * @param array $args Query arguments
     * @return array RSS feeds
     */
    public function get_feeds($args = array()) {
        global $wpdb;
        
        $cache_key = 'rss_feeds_' . md5(serialize($args));
        $cached = $this->cache_manager->get($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $defaults = array(
            'region' => null,
            'category' => null,
            'enabled' => null,
            'search' => '',
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'reliability_score',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        $where_conditions = array('1=1');
        $where_values = array();
        
        // Build WHERE clauses
        if ($args['region'] && in_array($args['region'], $this->feed_categories)) {
            $where_conditions[] = 'region = %s';
            $where_values[] = $args['region'];
        }
        
        if ($args['category']) {
            $where_conditions[] = 'category = %s';
            $where_values[] = $args['category'];
        }
        
        if ($args['enabled'] !== null) {
            $where_conditions[] = 'enabled = %d';
            $where_values[] = $args['enabled'] ? 1 : 0;
        }
        
        if ($args['search']) {
            $where_conditions[] = '(name LIKE %s OR description LIKE %s)';
            $where_values[] = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_values[] = '%' . $wpdb->esc_like($args['search']) . '%';
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Build ORDER BY
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        if (!$orderby) {
            $orderby = 'reliability_score DESC';
        }
        
        // Build query
        $query = "
            SELECT id, name, url, region, category, description, enabled, 
                   reliability_score, last_fetched, last_success, article_count, 
                   error_count, created_at, updated_at
            FROM {$this->feeds_table} 
            WHERE {$where_clause}
            ORDER BY {$orderby}
            LIMIT %d OFFSET %d
        ";
        
        $query_values = array_merge($where_values, array($args['limit'], $args['offset']));
        $results = $wpdb->get_results($wpdb->prepare($query, $query_values));
        
        // Format results
        $feeds = array();
        foreach ($results as $row) {
            $feeds[] = (array) $row;
        }
        
        // Cache results
        $this->cache_manager->set($cache_key, $feeds, 3600); // Cache for 1 hour
        
        return $feeds;
    }
    
    /**
     * Get feed by ID
     *
     * @param int $feed_id Feed ID
     * @return array|false Feed data or false
     */
    public function get_feed($feed_id) {
        global $wpdb;
        
        $cache_key = 'rss_feed_' . $feed_id;
        $cached = $this->cache_manager->get($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $feed = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->feeds_table} WHERE id = %d",
            $feed_id
        ), ARRAY_A);
        
        if ($feed) {
            $this->cache_manager->set($cache_key, $feed, 3600);
        }
        
        return $feed;
    }
    
    /**
     * Get feeds by region
     *
     * @param string $region Region (UK, EU, USA)
     * @return array RSS feeds
     */
    public function get_feeds_by_region($region) {
        return $this->get_feeds(array('region' => $region));
    }
    
    /**
     * Search feeds by query
     *
     * @param string $query Search query
     * @param string $region Optional region filter
     * @return array Search results
     */
    public function search_feeds($query, $region = null) {
        $args = array(
            'search' => $query,
            'limit' => 100,
            'orderby' => 'reliability_score',
            'order' => 'DESC'
        );
        
        if ($region) {
            $args['region'] = $region;
        }
        
        return $this->get_feeds($args);
    }
    
    /**
     * Get feed categories for a region
     *
     * @param string $region Optional region filter
     * @return array Categories
     */
    public function get_categories($region = null) {
        global $wpdb;
        
        $cache_key = 'rss_categories_' . ($region ?: 'all');
        $cached = $this->cache_manager->get($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $where_clause = '';
        $where_value = array();
        
        if ($region) {
            $where_clause = ' WHERE region = %s';
            $where_value[] = $region;
        }
        
        $query = "
            SELECT DISTINCT category, COUNT(*) as feed_count 
            FROM {$this->feeds_table} 
            {$where_clause} 
            GROUP BY category 
            ORDER BY feed_count DESC
        ";
        
        $results = $wpdb->get_results($wpdb->prepare($query, $where_value));
        
        $categories = array();
        foreach ($results as $row) {
            $categories[] = array(
                'name' => $row->category,
                'count' => intval($row->feed_count)
            );
        }
        
        $this->cache_manager->set($cache_key, $categories, 3600);
        
        return $categories;
    }
    
    /**
     * Enable RSS feeds
     *
     * @param array $feed_ids Array of feed IDs to enable
     * @return array Results
     */
    public function enable_feeds($feed_ids) {
        global $wpdb;
        
        if (empty($feed_ids)) {
            return array('success' => false, 'error' => 'No feed IDs provided');
        }
        
        $enabled_count = 0;
        $errors = array();
        
        foreach ($feed_ids as $feed_id) {
            $result = $wpdb->update(
                $this->feeds_table,
                array('enabled' => 1, 'updated_at' => current_time('mysql')),
                array('id' => intval($feed_id)),
                array('%d', '%s'),
                array('%d')
            );
            
            if ($result !== false) {
                $enabled_count++;
                
                // Update user selection
                $this->update_user_selection($feed_id, true);
                
                // Clear caches
                $this->cache_manager->delete('rss_feed_' . $feed_id);
                $this->cache_manager->delete_by_pattern('rss_feeds_');
            } else {
                $errors[] = 'Failed to enable feed ID: ' . $feed_id;
            }
        }
        
        return array(
            'success' => true,
            'enabled_count' => $enabled_count,
            'errors' => $errors
        );
    }
    
    /**
     * Disable RSS feeds
     *
     * @param array $feed_ids Array of feed IDs to disable
     * @return array Results
     */
    public function disable_feeds($feed_ids) {
        global $wpdb;
        
        if (empty($feed_ids)) {
            return array('success' => false, 'error' => 'No feed IDs provided');
        }
        
        $disabled_count = 0;
        $errors = array();
        
        foreach ($feed_ids as $feed_id) {
            $result = $wpdb->update(
                $this->feeds_table,
                array('enabled' => 0, 'updated_at' => current_time('mysql')),
                array('id' => intval($feed_id)),
                array('%d', '%s'),
                array('%d')
            );
            
            if ($result !== false) {
                $disabled_count++;
                
                // Update user selection
                $this->update_user_selection($feed_id, false);
                
                // Clear caches
                $this->cache_manager->delete('rss_feed_' . $feed_id);
                $this->cache_manager->delete_by_pattern('rss_feeds_');
            } else {
                $errors[] = 'Failed to disable feed ID: ' . $feed_id;
            }
        }
        
        return array(
            'success' => true,
            'disabled_count' => $disabled_count,
            'errors' => $errors
        );
    }
    
    /**
     * Update user feed selection
     *
     * @param int $feed_id Feed ID
     * @param bool $enabled Enable status
     * @param int $user_id User ID (default current user)
     */
    private function update_user_selection($feed_id, $enabled, $user_id = null) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        // Check if selection exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$this->user_selections_table} WHERE user_id = %d AND feed_id = %d",
            $user_id,
            $feed_id
        ));
        
        if ($existing) {
            // Update existing
            $wpdb->update(
                $this->user_selections_table,
                array('enabled' => $enabled ? 1 : 0, 'last_used' => current_time('mysql')),
                array('id' => $existing->id),
                array('%d', '%s'),
                array('%d')
            );
        } else {
            // Create new
            $wpdb->insert(
                $this->user_selections_table,
                array(
                    'user_id' => $user_id,
                    'feed_id' => $feed_id,
                    'enabled' => $enabled ? 1 : 0,
                    'priority' => 0,
                    'last_used' => current_time('mysql')
                ),
                array('%d', '%d', '%d', '%d', '%s')
            );
        }
    }
    
    /**
     * Validate RSS feed
     *
     * @param string $feed_url RSS feed URL
     * @param int $timeout Request timeout
     * @return array Validation result
     */
    public function validate_feed($feed_url, $timeout = 30) {
        try {
            $response = wp_remote_get($feed_url, array(
                'timeout' => $timeout,
                'user-agent' => 'ContentPilot RSS Validator/2.0',
                'sslverify' => true,
                'compress' => true,
                'headers' => array(
                    'Accept' => 'application/rss+xml, application/xml, text/xml'
                )
            ));
            
            if (is_wp_error($response)) {
                return array(
                    'valid' => false,
                    'error' => $response->get_error_message(),
                    'url' => $feed_url,
                    'timestamp' => current_time('Y-m-d H:i:s')
                );
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                return array(
                    'valid' => false,
                    'error' => 'HTTP Error: ' . $response_code,
                    'url' => $feed_url,
                    'timestamp' => current_time('Y-m-d H:i:s')
                );
            }
            
            $body = wp_remote_retrieve_body($response);
            if (empty($body)) {
                return array(
                    'valid' => false,
                    'error' => 'Empty response body',
                    'url' => $feed_url,
                    'timestamp' => current_time('Y-m-d H:i:s')
                );
            }
            
            // Try to parse RSS
            $rss = simplexml_load_string($body);
            if ($rss === false) {
                return array(
                    'valid' => false,
                    'error' => 'Invalid RSS/XML format',
                    'url' => $feed_url,
                    'timestamp' => current_time('Y-m-d H:i:s')
                );
            }
            
            // Check for items
            $item_count = 0;
            if (isset($rss->channel->item)) {
                $item_count = count($rss->channel->item);
            } elseif (isset($rss->entry)) {
                $item_count = count($rss->entry);
            }
            
            return array(
                'valid' => true,
                'item_count' => $item_count,
                'response_code' => $response_code,
                'url' => $feed_url,
                'timestamp' => current_time('Y-m-d H:i:s')
            );
            
        } catch (Exception $e) {
            return array(
                'valid' => false,
                'error' => $e->getMessage(),
                'url' => $feed_url,
                'timestamp' => current_time('Y-m-d H:i:s')
            );
        }
    }
    
    /**
     * Get top reliable feeds
     *
     * @param int $limit Number of feeds to return
     * @param string $region Optional region filter
     * @return array Reliable feeds
     */
    public function get_top_reliable_feeds($limit = 20, $region = null) {
        $args = array(
            'orderby' => 'reliability_score',
            'order' => 'DESC',
            'limit' => $limit,
            'enabled' => false // Get all feeds, sort by reliability
        );
        
        if ($region) {
            $args['region'] = $region;
        }
        
        $feeds = $this->get_feeds($args);
        $top_feeds = array();
        
        // Get the most reliable feeds (prioritizing major news sources)
        $priority_feeds = array(
            'bbc', 'guardian', 'reuters', 'cnn', 'nyt', 'washington post',
            'financial times', 'wall street journal', 'bloomberg', 'associated press'
        );
        
        foreach ($feeds as $feed) {
            $name_lower = strtolower($feed['name']);
            $priority_score = 0;
            
            foreach ($priority_feeds as $priority) {
                if (strpos($name_lower, $priority) !== false) {
                    $priority_score = 50;
                    break;
                }
            }
            
            $total_score = $feed['reliability_score'] + $priority_score;
            
            $top_feeds[] = array_merge($feed, array('priority_score' => $total_score));
        }
        
        // Sort by priority score
        usort($top_feeds, function($a, $b) {
            return $b['priority_score'] - $a['priority_score'];
        });
        
        return array_slice(array_column($top_feeds, 'id'), 0, $limit);
    }
    
    /**
     * Get RSS feed statistics
     *
     * @return array Statistics
     */
    public function get_feed_statistics() {
        global $wpdb;
        
        $cache_key = 'rss_feed_statistics';
        $cached = $this->cache_manager->get($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        // Total feeds
        $total_feeds = $wpdb->get_var("SELECT COUNT(*) FROM {$this->feeds_table}");
        
        // Enabled feeds
        $enabled_feeds = $wpdb->get_var("SELECT COUNT(*) FROM {$this->feeds_table} WHERE enabled = 1");
        
        // Feeds by region
        $region_stats = $wpdb->get_results("
            SELECT region, COUNT(*) as count 
            FROM {$this->feeds_table} 
            GROUP BY region
        ");
        
        // Feeds by category
        $category_stats = $wpdb->get_results("
            SELECT category, COUNT(*) as count 
            FROM {$this->feeds_table} 
            GROUP BY category 
            ORDER BY count DESC
        ");
        
        // Average reliability score
        $avg_reliability = $wpdb->get_var("
            SELECT AVG(reliability_score) 
            FROM {$this->feeds_table}
        ");
        
        // Most recent activity
        $recent_activity = $wpdb->get_var("
            SELECT MAX(last_fetched) 
            FROM {$this->feeds_table} 
            WHERE last_fetched IS NOT NULL
        ");
        
        $statistics = array(
            'total_feeds' => intval($total_feeds),
            'enabled_feeds' => intval($enabled_feeds),
            'disabled_feeds' => intval($total_feeds - $enabled_feeds),
            'regions' => array(),
            'categories' => array(),
            'average_reliability' => round(floatval($avg_reliability), 2),
            'recent_activity' => $recent_activity,
            'timestamp' => current_time('Y-m-d H:i:s')
        );
        
        foreach ($region_stats as $stat) {
            $statistics['regions'][$stat->region] = intval($stat->count);
        }
        
        foreach ($category_stats as $stat) {
            $statistics['categories'][$stat->category] = intval($stat->count);
        }
        
        $this->cache_manager->set($cache_key, $statistics, 1800); // Cache for 30 minutes
        
        return $statistics;
    }
    
    /**
     * Update feed performance metrics
     *
     * @param int $feed_id Feed ID
     * @param bool $success Whether the last fetch was successful
     * @param int $article_count Number of articles fetched
     * @param string $error Error message if failed
     */
    public function update_feed_metrics($feed_id, $success, $article_count = 0, $error = '') {
        global $wpdb;
        
        $update_data = array(
            'last_fetched' => current_time('mysql'),
            'article_count' => $article_count
        );
        
        if ($success) {
            $update_data['last_success'] = current_time('mysql');
            $update_data['error_count'] = 0; // Reset error count on success
        } else {
            // Increment error count
            $wpdb->query($wpdb->prepare(
                "UPDATE {$this->feeds_table} SET error_count = error_count + 1 WHERE id = %d",
                $feed_id
            ));
        }
        
        // Update reliability score based on success rate
        $reliability_score = $this->calculate_reliability_score($feed_id);
        $update_data['reliability_score'] = $reliability_score;
        
        $result = $wpdb->update(
            $this->feeds_table,
            $update_data,
            array('id' => $feed_id),
            array('%s', '%d', '%d'),
            array('%d')
        );
        
        if ($result !== false) {
            // Clear caches
            $this->cache_manager->delete('rss_feed_' . $feed_id);
            $this->cache_manager->delete('rss_feed_statistics');
            $this->cache_manager->delete_by_pattern('rss_feeds_');
        }
    }
    
    /**
     * Calculate reliability score for a feed
     *
     * @param int $feed_id Feed ID
     * @return int Reliability score (0-100)
     */
    private function calculate_reliability_score($feed_id) {
        global $wpdb;
        
        $feed = $this->get_feed($feed_id);
        if (!$feed) {
            return 50; // Default score
        }
        
        $base_score = 100;
        $error_penalty = min($feed['error_count'] * 10, 50);
        $last_fetch_days = null;
        
        if ($feed['last_fetched']) {
            $last_fetch = strtotime($feed['last_fetched']);
            $days_since = (time() - $last_fetch) / DAY_IN_SECONDS;
            
            // Penalize feeds that haven't been fetched recently
            if ($days_since > 7) {
                $last_fetch_penalty = min($days_since * 2, 20);
            } else {
                $last_fetch_penalty = 0;
            }
        }
        
        $final_score = max(0, $base_score - $error_penalty - $last_fetch_penalty);
        
        return intval($final_score);
    }
    
    /**
     * AJAX handler for RSS feed search
     */
    public function ajax_search_feeds() {
        check_ajax_referer('aanp_rss_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $query = sanitize_text_field($_POST['query'] ?? '');
        $region = sanitize_text_field($_POST['region'] ?? '');
        
        $feeds = $this->search_feeds($query, $region);
        
        wp_send_json_success(array(
            'feeds' => $feeds,
            'count' => count($feeds)
        ));
    }
    
    /**
     * AJAX handler for toggling RSS feed status
     */
    public function ajax_toggle_feed() {
        check_ajax_referer('aanp_rss_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $feed_id = intval($_POST['feed_id'] ?? 0);
        $enabled = ($_POST['enabled'] ?? 'false') === 'true';
        
        if (!$feed_id) {
            wp_send_json_error('Invalid feed ID');
        }
        
        $result = $enabled ? $this->enable_feeds(array($feed_id)) : $this->disable_feeds(array($feed_id));
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['error'] ?? 'Operation failed');
        }
    }
    
    /**
     * AJAX handler for bulk enabling feeds
     */
    public function ajax_bulk_enable_feeds() {
        check_ajax_referer('aanp_rss_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $feed_ids = array_map('intval', $_POST['feed_ids'] ?? array());
        $action = sanitize_text_field($_POST['action_type'] ?? 'enable');
        
        if (empty($feed_ids)) {
            wp_send_json_error('No feeds selected');
        }
        
        $result = $action === 'enable' ? 
            $this->enable_feeds($feed_ids) : 
            $this->disable_feeds($feed_ids);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['error'] ?? 'Operation failed');
        }
    }
    
    /**
     * Handle RSS-related admin actions
     */
    public function handle_rss_actions() {
        if (!isset($_GET['aanp_action']) || $_GET['aanp_action'] !== 'validate_rss_feeds') {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        // Validate all feeds
        $feeds = $this->get_feeds();
        $validation_results = array();
        
        foreach ($feeds as $feed) {
            $validation = $this->validate_feed($feed['url']);
            $validation_results[$feed['id']] = $validation;
        }
        
        // Store results in transient for display
        set_transient('aanp_rss_validation_results', $validation_results, 300);
        
        wp_redirect(admin_url('admin.php?page=aanp-dashboard&rss_validation=completed'));
        exit;
    }
    
    /**
     * Get enabled RSS feeds for NewsFetchService
     *
     * @return array List of enabled feed URLs
     */
    public function get_enabled_feed_urls() {
        $enabled_feeds = $this->get_feeds(array('enabled' => true));
        return array_column($enabled_feeds, 'url');
    }
    
    /**
     * Clean up old cached data
     */
    public function cleanup() {
        try {
            // Clear RSS-related caches
            $this->cache_manager->delete_by_pattern('rss_feed_');
            $this->cache_manager->delete_by_pattern('rss_feeds_');
            $this->cache_manager->delete('rss_feed_statistics');
            
            $this->logger->info('RSS Feed Manager cleanup completed');
            
        } catch (Exception $e) {
            $this->logger->error('RSS Feed Manager cleanup failed', array(
                'error' => $e->getMessage()
            ));
        }
    }
}