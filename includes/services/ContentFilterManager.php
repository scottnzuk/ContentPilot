<?php
/**
 * Content Filter Manager for Smart RSS Filtering System
 *
 * Handles keyword-based content filtering, niche bundles management,
 * user preferences, and live preview functionality.
 *
 * @package AI_Auto_News_Poster\Services
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Content Filter Manager Class
 */
class AANP_ContentFilterManager {
    
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
     * Available content bundles
     * @var array
     */
    private $available_bundles = array();
    
    /**
     * Constructor
     *
     * @param AANP_AdvancedCacheManager $cache_manager
     */
    public function __construct(AANP_AdvancedCacheManager $cache_manager = null) {
        $this->logger = AANP_Logger::getInstance();
        $this->cache_manager = $cache_manager ?: new AANP_AdvancedCacheManager();
        
        $this->init_available_bundles();
        $this->init_hooks();
        $this->create_database_tables();
    }
    
    /**
     * Initialize available content bundles
     */
    private function init_available_bundles() {
        $this->available_bundles = array(
            
            // MAIN NEWS & GENERAL (Top Priority)
            'recent_world_news' => array(
                'name' => 'Recent World News',
                'description' => 'General interest news from reputable sources worldwide',
                'category' => 'main_news',
                'visibility' => 'visible',
                'sort_order' => 1,
                'is_default' => true,
                'positive_keywords' => 'news, breaking, latest, report, update, announcement, official, government, international, politics, economy, technology, health, sports',
                'negative_keywords' => '-opinion, -editorial, -satire, -entertainment, -celebrity, -gossip, -sports commentary',
                'priority_regions' => 'UK,USA,EU',
                'content_age_limit' => 7,
                'enabled_feeds' => array(
                    'https://feeds.bbci.co.uk/news/rss.xml',
                    'https://rss.cnn.com/rss/edition.rss',
                    'https://feeds.reuters.com/reuters/topNews',
                    'https://www.theguardian.com/uk/rss',
                    'https://www.reuters.com/rssFeed/UKTopNews',
                    'https://www.nytimes.com/services/xml/rss/nyt/HomePage.xml',
                    'https://www.associatedpress.com/rss/apf-headlines'
                )
            ),
            'us_news_politics' => array(
                'name' => 'US News & Politics',
                'description' => 'American news, politics, and policy developments',
                'category' => 'main_news',
                'visibility' => 'visible',
                'sort_order' => 2,
                'is_default' => false,
                'positive_keywords' => 'USA, America, American, congress, senate, president, election, politics, policy, federal, government, White House, Washington',
                'negative_keywords' => '-sports, -entertainment, -celebrity, -fashion, -travel, -sports commentary',
                'priority_regions' => 'USA',
                'content_age_limit' => 7,
                'enabled_feeds' => array(
                    'https://feeds.foxnews.com/foxnews/national',
                    'https://www.nbcnews.com/id/3032086/rss/xml.xml',
                    'https://abcnews.go.com/abcnews/headlines',
                    'https://www.cbsnews.com/latest/rss/main',
                    'https://feeds.washingtonpost.com/rss/world',
                    'https://rss.politico.com/politicorss.xml'
                )
            ),
            'uk_news' => array(
                'name' => 'UK News',
                'description' => 'British news, politics, and current affairs',
                'category' => 'main_news',
                'visibility' => 'visible',
                'sort_order' => 3,
                'is_default' => false,
                'positive_keywords' => 'UK, Britain, British, England, Scotland, Wales, Northern Ireland, Westminster, Parliament, London, government, politics',
                'negative_keywords' => '-sports, -entertainment, -celebrity, -fashion, -travel, -sports commentary',
                'priority_regions' => 'UK',
                'content_age_limit' => 7,
                'enabled_feeds' => array(
                    'https://feeds.bbci.co.uk/news/rss.xml',
                    'https://news.sky.com/uk/rss',
                    'https://www.theguardian.com/uk/rss',
                    'https://www.telegraph.co.uk/rss.xml',
                    'https://www.independent.co.uk/rss'
                )
            ),
            'european_news' => array(
                'name' => 'European News',
                'description' => 'European Union and continental European news',
                'category' => 'main_news',
                'visibility' => 'visible',
                'sort_order' => 4,
                'is_default' => false,
                'positive_keywords' => 'Europe, European, EU, European Union, Germany, France, Italy, Spain, Netherlands, Brussels',
                'negative_keywords' => '-sports, -entertainment, -celebrity, -fashion, -travel, -sports commentary',
                'priority_regions' => 'EU',
                'content_age_limit' => 7,
                'enabled_feeds' => array(
                    'https://rss.dw.com/rdf/rss-en-news',
                    'https://www.euronews.com/rss',
                    'https://www.lemonde.fr/rss/une.xml',
                    'https://www.spiegel.de/schlagzeen/index.rss'
                )
            ),
            'global_business' => array(
                'name' => 'Global Business',
                'description' => 'International business, finance, and economic news',
                'category' => 'main_news',
                'visibility' => 'visible',
                'sort_order' => 5,
                'is_default' => false,
                'positive_keywords' => 'business, finance, economy, market, trade, investment, corporate, company, earnings, revenue, profit, stock',
                'negative_keywords' => '-sports, -entertainment, -celebrity, -politics, -fashion, -travel',
                'priority_regions' => 'UK,USA,EU',
                'content_age_limit' => 7,
                'enabled_feeds' => array(
                    'https://www.ft.com/rss/home',
                    'https://feeds.bloomberg.com/markets/news.rss',
                    'https://feeds.a.dj.com/rss/RSSWorldNews.xml',
                    'https://www.forbes.com/real-time/feed2/'
                )
            ),
            'technology_news' => array(
                'name' => 'Technology News',
                'description' => 'Technology developments, startups, and digital innovation',
                'category' => 'main_news',
                'visibility' => 'visible',
                'sort_order' => 6,
                'is_default' => false,
                'positive_keywords' => 'technology, tech, startup, innovation, digital, software, hardware, internet, mobile, smartphone, app, AI, artificial intelligence',
                'negative_keywords' => '-sports, -entertainment, -celebrity, -politics, -fashion, -travel',
                'priority_regions' => 'UK,USA,EU',
                'content_age_limit' => 7,
                'enabled_feeds' => array(
                    'https://techcrunch.com/feed/',
                    'https://www.theverge.com/rss/index.xml',
                    'https://feeds.arstechnica.com/arstechnica/index',
                    'https://www.wired.com/feed'
                )
            ),
            'science_health' => array(
                'name' => 'Science & Health',
                'description' => 'Scientific research, medical breakthroughs, and health news',
                'category' => 'main_news',
                'visibility' => 'visible',
                'sort_order' => 7,
                'is_default' => false,
                'positive_keywords' => 'science, research, study, discovery, health, medical, medicine, healthcare, treatment, disease, drug, vaccine',
                'negative_keywords' => '-sports, -entertainment, -celebrity, -politics, -fashion, -travel',
                'priority_regions' => 'UK,USA,EU',
                'content_age_limit' => 14,
                'enabled_feeds' => array(
                    'https://www.nature.com/nature.rss',
                    'https://www.scientificamerican.com/rss/',
                    'https://feeds.bbci.co.uk/news/science_and_environment/rss.xml',
                    'https://www.sciencedaily.com/rss.xml'
                )
            ),

            // BUSINESS & FINANCE
            'cryptocurrency_blockchain' => array(
                'name' => 'Cryptocurrency & Blockchain',
                'description' => 'Digital currency and blockchain technology news',
                'category' => 'business',
                'visibility' => 'visible',
                'sort_order' => 8,
                'is_default' => false,
                'positive_keywords' => 'bitcoin, cryptocurrency, blockchain, crypto, ethereum, altcoin, defi, nft, mining, trading, exchange, wallet, bitcoin price, market, investment, token, coin, smart contract, web3',
                'negative_keywords' => '-sports, -entertainment, -celebrity, -politics, -war, -fashion, -travel',
                'priority_regions' => 'USA,EU',
                'content_age_limit' => 1,
                'enabled_feeds' => array(
                    'https://cointelegraph.com/rss',
                    'https://bitcoinmagazine.com/feed',
                    'https://decrypt.co/feed',
                    'https://www.coindesk.com/arc/outboundfeeds/rss/',
                    'https://ambcrypto.com/feed/',
                    'https://cryptonews.com/feed/'
                )
            ),
            'stock_market_investing' => array(
                'name' => 'Stock Market & Investing',
                'description' => 'Financial markets, investing strategies, and economic analysis',
                'category' => 'business',
                'visibility' => 'visible',
                'sort_order' => 9,
                'is_default' => false,
                'positive_keywords' => 'stock market, investing, shares, stocks, portfolio, investment, trading, analysis, financial, economy, market',
                'negative_keywords' => '-sports, -entertainment, -celebrity, -politics, -fashion, -travel',
                'priority_regions' => 'UK,USA,EU',
                'content_age_limit' => 1,
                'enabled_feeds' => array(
                    'https://www.marketwatch.com/rss/home',
                    'https://seekingalpha.com/feed',
                    'https://www.morningstar.com/rss',
                    'https://feeds.fool.com/rss/headlines.xml'
                )
            ),
            'real_estate_property' => array(
                'name' => 'Real Estate & Property',
                'description' => 'Property markets, real estate investment, and housing news',
                'category' => 'business',
                'visibility' => 'visible',
                'sort_order' => 10,
                'is_default' => false,
                'positive_keywords' => 'real estate, property, house, home, mortgage, rent, landlord, tenant, market, price, value, commercial, residential, buying, selling',
                'negative_keywords' => '-sports, -entertainment, -celebrity, -politics, -war, -fashion, -travel',
                'priority_regions' => 'UK,USA,EU',
                'content_age_limit' => 7,
                'enabled_feeds' => array(
                    'https://www.investopedia.com/feed',
                    'https://www.realtor.com/rss/',
                    'https://www.kiplinger.com/rss',
                    'https://www.biggerpockets.com/blog/feed'
                )
            ),
            'entrepreneurship_startups' => array(
                'name' => 'Entrepreneurship & Startups',
                'description' => 'Startup news, entrepreneurship, and innovation',
                'category' => 'business',
                'visibility' => 'visible',
                'sort_order' => 11,
                'is_default' => false,
                'positive_keywords' => 'startup, entrepreneurship, entrepreneur, business plan, funding, venture capital, angel investor, pitch deck, innovation',
                'negative_keywords' => '-sports, -entertainment, -celebrity, -politics, -war, -fashion, -travel',
                'priority_regions' => 'UK,USA,EU',
                'content_age_limit' => 7,
                'enabled_feeds' => array(
                    'https://techcrunch.com/feed/',
                    'https://news.ycombinator.com/rss',
                    'https://steveblank.com/feed/',
                    'https://bothsidesofthetable.com/feed/'
                )
            ),
            'personal_finance' => array(
                'name' => 'Personal Finance',
                'description' => 'Personal finance, budgeting, and financial planning',
                'category' => 'business',
                'visibility' => 'visible',
                'sort_order' => 12,
                'is_default' => false,
                'positive_keywords' => 'personal finance, budget, savings, retirement, pension, financial planning, money management, debt, credit',
                'negative_keywords' => '-sports, -entertainment, -celebrity, -politics, -war, -fashion, -travel',
                'priority_regions' => 'UK,USA,EU',
                'content_age_limit' => 14,
                'enabled_feeds' => array(
                    'https://www.kiplinger.com/rss',
                    'https://www.nerdwallet.com/blog/rss/',
                    'https://www.investopedia.com/feed',
                    'https://www.wisebread.com/feed'
                )
            ),

            // TECHNOLOGY
            'artificial_intelligence_ml' => array(
                'name' => 'Artificial Intelligence & ML',
                'description' => 'AI/ML developments, research, and industry applications',
                'category' => 'technology',
                'visibility' => 'visible',
                'sort_order' => 13,
                'is_default' => false,
                'positive_keywords' => 'artificial intelligence, machine learning, AI, ML, deep learning, neural network, GPT, LLM, chatbot, automation, robotics, computer vision, natural language processing, data science, algorithm, model, training, inference, TensorFlow, PyTorch',
                'negative_keywords' => '-sports, -entertainment, -celebrity, -politics, -war, -fashion, -travel, -crypto',
                'priority_regions' => 'USA,EU',
                'content_age_limit' => 7,
                'enabled_feeds' => array(
                    'https://venturebeat.com/ai/feed/',
                    'https://www.technologyreview.com/topic/artificial-intelligence/feed/',
                    'https://www.artificialintelligence-news.com/feed/',
                    'https://www.sciencedaily.com/rss/computer_info/artificial_intelligence.xml'
                )
            ),
            'cybersecurity' => array(
                'name' => 'Cybersecurity',
                'description' => 'Information security, cyber threats, and data protection',
                'category' => 'technology',
                'visibility' => 'specialized',
                'sort_order' => 14,
                'is_default' => false,
                'positive_keywords' => 'cybersecurity, cyber security, hacking, malware, virus, ransomware, data breach, vulnerability, security, privacy, encryption, firewall',
                'negative_keywords' => '-sports, -entertainment, -celebrity, -politics, -war, -fashion, -travel, -crypto',
                'priority_regions' => 'UK,USA,EU',
                'content_age_limit' => 7,
                'enabled_feeds' => array(
                    'https://krebsonsecurity.com/feed/',
                    'https://www.securityweek.com/feed/',
                    'https://cyberscoop.com/feed/',
                    'https://www.bleepingcomputer.com/feed/'
                )
            ),
            'gaming_industry' => array(
                'name' => 'Gaming Industry',
                'description' => 'Video game industry news, reviews, and developments',
                'category' => 'technology',
                'visibility' => 'specialized',
                'sort_order' => 15,
                'is_default' => false,
                'positive_keywords' => 'gaming, video games, esports, console, PC gaming, mobile games, Nintendo, PlayStation, Xbox, Steam, game development',
                'negative_keywords' => '-sports, -politics, -war, -fashion, -travel, -crypto',
                'priority_regions' => 'UK,USA,EU',
                'content_age_limit' => 14,
                'enabled_feeds' => array(
                    'https://kotaku.com/rss',
                    'https://www.polygon.com/rss',
                    'https://www.rockpapershotgun.com/feed/',
                    'https://www.gamasutra.com/rss.xml'
                )
            ),
            'mobile_apps' => array(
                'name' => 'Mobile & Apps',
                'description' => 'Mobile technology, smartphone apps, and app development',
                'category' => 'technology',
                'visibility' => 'specialized',
                'sort_order' => 16,
                'is_default' => false,
                'positive_keywords' => 'mobile, smartphone, iPhone, Android, app, apps, application, iOS, Google Play, App Store, mobile development',
                'negative_keywords' => '-sports, -politics, -war, -fashion, -travel, -crypto',
                'priority_regions' => 'UK,USA,EU',
                'content_age_limit' => 14,
                'enabled_feeds' => array(
                    'https://androidpolice.com/feed/',
                    'https://www.imore.com/rss',
                    'https://9to5mac.com/feed/',
                    'https://techcrunch.com/category/mobile/feed/'
                )
            ),
            'software_development' => array(
                'name' => 'Software Development',
                'description' => 'Programming, software engineering, and development tools',
                'category' => 'technology',
                'visibility' => 'specialized',
                'sort_order' => 17,
                'is_default' => false,
                'positive_keywords' => 'programming, software development, coding, developer, GitHub, code, development, framework, library, API, web development',
                'negative_keywords' => '-sports, -entertainment, -celebrity, -politics, -war, -fashion, -travel, -crypto',
                'priority_regions' => 'UK,USA,EU',
                'content_age_limit' => 14,
                'enabled_feeds' => array(
                    'https://stackoverflow.blog/feed/',
                    'https://github.blog/feed/',
                    'https://news.ycombinator.com/rss',
                    'https://dev.to/feed'
                )
            ),
            'cloud_computing' => array(
                'name' => 'Cloud Computing',
                'description' => 'Cloud services, infrastructure, and cloud technology',
                'category' => 'technology',
                'visibility' => 'specialized',
                'sort_order' => 18,
                'is_default' => false,
                'positive_keywords' => 'cloud computing, AWS, Azure, Google Cloud, cloud infrastructure, serverless, container, Docker, Kubernetes',
                'negative_keywords' => '-sports, -entertainment, -celebrity, -politics, -war, -fashion, -travel, -crypto',
                'priority_regions' => 'UK,USA,EU',
                'content_age_limit' => 14,
                'enabled_feeds' => array(
                    'https://aws.amazon.com/blogs/news/feed/',
                    'https://azure.microsoft.com/en-us/blog/feed/',
                    'https://cloud.google.com/blog/feeds/posts/default',
                    'https://www.docker.com/blog/feed/'
                )
            ),
            'web_development' => array(
                'name' => 'Web Development',
                'description' => 'Frontend and backend web development technologies',
                'category' => 'technology',
                'visibility' => 'specialized',
                'sort_order' => 19,
                'is_default' => false,
                'positive_keywords' => 'web development, HTML, CSS, JavaScript, React, Angular, Vue, frontend, backend, full stack, responsive design',
                'negative_keywords' => '-sports, -entertainment, -celebrity, -politics, -war, -fashion, -travel, -crypto',
                'priority_regions' => 'UK,USA,EU',
                'content_age_limit' => 14,
                'enabled_feeds' => array(
                    'https://www.smashingmagazine.com/feed/',
                    'https://css-tricks.com/feed/',
                    'https://web.dev/feed/',
                    'https://alistapart.com/main/feed/'
                )
            ),
            'data_science' => array(
                'name' => 'Data Science',
                'description' => 'Data analytics, big data, and data science tools',
                'category' => 'technology',
                'visibility' => 'specialized',
                'sort_order' => 20,
                'is_default' => false,
                'positive_keywords' => 'data science, data analytics, big data, machine learning, statistics, data visualization, Python, R, SQL, analytics',
                'negative_keywords' => '-sports, -entertainment, -celebrity, -politics, -war, -fashion, -travel, -crypto',
                'priority_regions' => 'UK,USA,EU',
                'content_age_limit' => 14,
                'enabled_feeds' => array(
                    'https://www.kdnuggets.com/feed',
                    'https://www.datasciencecentral.com/feed',
                    'https://towardsdatascience.com/feed',
                    'https://blog.feedspot.com/data_science_blogs/'
                )
            ),

            // LIFESTYLE & HOBBIES
            'sailing_boating_rya' => array(
                'name' => 'Sailing, Boating & RYA',
                'description' => 'Complete sailing ecosystem with maritime news, racing, training, and equipment',
                'category' => 'lifestyle',
                'visibility' => 'hidden',
                'sort_order' => 47,
                'is_default' => false,
                'positive_keywords' => 'sailing, yacht, boat, marine, RYA, dinghy, keelboat, wind, sails, hull, rigging, navigation, weather, harbor, port, maritime, ocean, sea, coastal, sailing club, race, regatta, skipper, crew, captain, vessel, ship, cruising, racing, flotilla, anchor, mooring, chart, gps, radar, lifejacket, safety, rescue, yacht club, sailing school, maritime law',
                'negative_keywords' => '-powerboat, -motorboat, -speedboat, -jet-ski, -water-ski, -fishing, -diving, -surfing, -crypto, -politics, -war, -football, -celebrity, -entertainment',
                'priority_regions' => 'UK,EU',
                'content_age_limit' => 30,
                'enabled_feeds' => array(
                    'https://www.sailingworld.com/feed/',
                    'https://www.yachtracingnews.com/rss/',
                    'https://www.rya.org.uk/news/rss',
                    'https://www.metoffice.gov.uk/weather/rss',
                    'https://www.pbo.co.uk/rss',
                    'https://www.yachtandboat.com.au/rss',
                    'https://www.superyachttimes.com/rss/rss',
                    'https://oceanconservancy.org/feed/',
                    'https://www.mcga.gov.uk/rss',
                    'https://www.dinghysailing.com/rss',
                    'https://www.naval-technology.com/rss'
                ),
                'categories' => array(
                    'Sailing News',
                    'Yacht Racing',
                    'RYA Training & Certifications',
                    'Marine Weather',
                    'Navigation & Electronics',
                    'Sailing Gear Reviews',
                    'Boat Building & Refit',
                    'Ocean Conservation',
                    'Maritime Safety & Regulations',
                    'Superyachts',
                    'Dinghy Sailing'
                )
            ),
            'travel_tourism' => array(
                'name' => 'Travel & Tourism',
                'description' => 'Travel destinations, tourism industry, and travel tips',
                'category' => 'lifestyle',
                'visibility' => 'visible',
                'sort_order' => 21,
                'is_default' => false,
                'positive_keywords' => 'travel, tourism, destination, vacation, holiday, trip, flight, hotel, resort, adventure, culture, exploration',
                'negative_keywords' => '-politics, -war, -sports, -entertainment, -celebrity, -crypto',
                'priority_regions' => 'UK,USA,EU',
                'content_age_limit' => 30,
                'enabled_feeds' => array(
                    'https://www.lonelyplanet.com/news/feed',
                    'https://www.travelandleisure.com/feed',
                    'https://www.cntraveler.com/rss',
                    'https://www.nationalgeographic.com/travel/feed/'
                )
            ),
            'food_cooking' => array(
                'name' => 'Food & Cooking',
                'description' => 'Culinary news, recipes, and food culture',
                'category' => 'lifestyle',
                'visibility' => 'visible',
                'sort_order' => 22,
                'is_default' => false,
                'positive_keywords' => 'food, cooking, recipes, cuisine, chef, restaurant, culinary, gastronomy, ingredient, kitchen, baking, cuisine',
                'negative_keywords' => '-politics, -war, -sports, -entertainment, -celebrity, -crypto',
                'priority_regions' => 'UK,USA,EU',
                'content_age_limit' => 30,
                'enabled_feeds' => array(
                    'https://www.bonappetit.com/feed',
                    'https://www.seriouseats.com/feed',
                    'https://www.foodnetwork.com/rss',
                    'https://www.eater.com/rss'
                )
            ),
            'fashion_beauty' => array(
                'name' => 'Fashion & Beauty',
                'description' => 'Fashion trends, beauty products, and style news',
                'category' => 'lifestyle',
                'visibility' => 'visible',
                'sort_order' => 23,
                'is_default' => false,
                'positive_keywords' => 'fashion, beauty, style, makeup, skincare, clothing, designer, runway, trend, cosmetics, accessories',
                'negative_keywords' => '-politics, -war, -sports, -entertainment, -celebrity gossip, -crypto',
                'priority_regions' => 'UK,USA,EU',
                'content_age_limit' => 30,
                'enabled_feeds' => array(
                    'https://www.vogue.com/rss',
                    'https://www.elle.com/rss',
                    'https://www.allure.com/rss',
                    'https://hypebeast.com/rss'
                )
            ),
            'fitness_wellness' => array(
                'name' => 'Fitness & Wellness',
                'description' => 'Fitness, health, and wellness lifestyle content',
                'category' => 'lifestyle',
                'visibility' => 'visible',
                'sort_order' => 24,
                'is_default' => false,
                'positive_keywords' => 'fitness, wellness, exercise, workout, health, nutrition, diet, yoga, meditation, running, gym, strength training',
                'negative_keywords' => '-politics, -war, -entertainment, -celebrity, -sports commentary',
                'priority_regions' => 'UK,USA,EU',
                'content_age_limit' => 14,
                'enabled_feeds' => array(
                    'https://www.runnersworld.com/rss',
                    'https://www.menshealth.com/rss',
                    'https://www.prevention.com/feed/',
                    'https://www.healthline.com/rss'
                )
            ),
            'parenting_family' => array(
                'name' => 'Parenting & Family',
                'description' => 'Family life, parenting tips, and child development',
                'category' => 'lifestyle',
                'visibility' => 'visible',
                'sort_order' => 25,
                'is_default' => false,
                'positive_keywords' => 'parenting, children, kids, family, toddler, baby, infant, child development, family life, motherhood, fatherhood',
                'negative_keywords' => '-politics, -war, -entertainment, -celebrity, -sports commentary, -crypto',
                'priority_regions' => 'UK,USA,EU',
                'content_age_limit' => 30,
                'enabled_feeds' => array(
                    'https://www.parents.com/rss/',
                    'https://www.pbs.org/parents/feed/',
                    'https://www.educationalappstore.com/blog/feed/',
                    'https://www.momtastic.com/feed/'
                )
            ),
            'photography' => array(
                'name' => 'Photography',
                'description' => 'Photography techniques, equipment, and photojournalism',
                'category' => 'lifestyle',
                'visibility' => 'specialized',
                'sort_order' => 26,
                'is_default' => false,
                'positive_keywords' => 'photography, photo, camera, lens, DSLR, mirrorless, portrait, landscape, photojournalism, photographer',
                'negative_keywords' => '-politics, -war, -sports, -entertainment, -celebrity, -crypto',
                'priority_regions' => 'UK,USA,EU',
                'content_age_limit' => 30,
                'enabled_feeds' => array(
                    'https://www.digitalphotographyreview.com/feed/',
                    'https://petapixel.com/feed/',
                    'https://fstoppers.com/feed',
                    'https://www.practicalphotography.com/feed/'
                )
            ),

            // INDUSTRIES & PROFESSIONS
            'healthcare_medical' => array(
                'name' => 'Healthcare & Medical',
                'description' => 'Medical news, healthcare industry, and clinical research',
                'category' => 'industries',
                'visibility' => 'specialized',
                'sort_order' => 27,
                'is_default' => false,
                'positive_keywords' => 'healthcare, medical, doctor, hospital, patient, treatment, disease, diagnosis, therapy, clinical, healthcare industry',
                'negative_keywords' => '-politics, -war, -sports, -entertainment, -celebrity, -crypto',
                'priority_regions' => 'UK,USA,EU',
                'content_age_limit' => 14,
                'enabled_feeds' => array(
                    'https://www.webmd.com/rss/rss.aspx?RSSSource=RSS_PUBLIC',
                    'https://www.mayoclinic.org/about-this-site/rss-content',
                    'https://www.healthline.com/rss',
                    'https://www.cdc.gov/rss/'
                )
            ),
            'legal_industry' => array(
                'name' => 'Legal Industry',
                'description' => 'Legal news, court decisions, and law practice',
                'category' => 'industries',
                'visibility' => 'specialized',
                'sort_order' => 28,
                'is_default' => false,
                'positive_keywords' => 'legal, law, lawyer, attorney, court, judge, case, legislation, regulation, legal industry, law practice',
                'negative_keywords' => '-politics, -war, -sports, -entertainment, -celebrity, -crypto',
                'priority_regions' => 'UK,USA,EU',
                'content_age_limit' => 30,
                'enabled_feeds' => array(
                    'https://www.abajournal.com/news/feed/',
                    'https://www.law.com/rss/',
                    'https://www.findlaw.com/feed/',
                    'https://www.legalnews.com/rss/'
                )
            ),
            'education' => array(
                'name' => 'Education',
                'description' => 'Education news, teaching, and academic research',
                'category' => 'industries',
                'visibility' => 'specialized',
                'sort_order' => 29,
                'is_default' => false,
                'positive_keywords' => 'education, school, teacher, student, academic, university, college, learning, teaching, curriculum, EdTech',
                'negative_keywords' => '-politics, -war, -sports, -entertainment, -celebrity, -crypto',
                'priority_regions' => 'UK,USA,EU',
                'content_age_limit' => 30,
                'enabled_feeds' => array(
                    'https://www.edtechmagazine.com/rss/',
                    'https://www.insidehighered.com/rss/all',
                    'https://www.chronicle.com/section/News/6/rss',
                    'https://www.educationweek.org/rss/'
                )
            ),
            'automotive' => array(
                'name' => 'Automotive',
                'description' => 'Automotive industry, car news, and vehicle technology',
                'category' => 'industries',
                'visibility' => 'specialized',
                'sort_order' => 30,
                'is_default' => false,
                'positive_keywords' => 'automotive, car, automobile, vehicle, motor, automotive industry, car review, auto technology, electric vehicle',
                'negative_keywords' => '-politics, -war, -sports, -entertainment, -celebrity, -crypto',
                'priority_regions' => 'UK,USA,EU',
                'content_age_limit' => 30,
                'enabled_feeds' => array(
                    'https://www.caranddriver.com/rss',
                    'https://www.motortrend.com/rss/',
                    'https://jalopnik.com/rss',
                    'https://www.autoblog.com/rss.xml'
                )
            ),
            'aviation_aerospace' => array(
                'name' => 'Aviation & Aerospace',
                'description' => 'Aviation industry, aerospace technology, and space news',
                'category' => 'industries',
                'visibility' => 'specialized',
                'sort_order' => 31,
                'is_default' => false,
                'positive_keywords' => 'aviation, aerospace, aircraft, airline, pilot, flying, flight, airport, space, satellite, rocket',
                'negative_keywords' => '-politics, -war, -sports, -entertainment, -celebrity, -crypto',
                'priority_regions' => 'UK,USA,EU',
                'content_age_limit' => 30,
                'enabled_feeds' => array(
                    'https://aviationweek.com/rss',
                    'https://www.flightglobal.com/rss',
                    'https://www.aopa.org/rss',
                    'https://www.aviationtoday.com/rss/'
                )
            ),
            'energy_environment' => array(
                'name' => 'Energy & Environment',
                'description' => 'Energy industry, renewable energy, and environmental news',
                'category' => 'industries',
                'visibility' => 'specialized',
                'sort_order' => 32,
                'is_default' => false,
                'positive_keywords' => 'energy, renewable energy, solar, wind, environment, climate, sustainability, green energy, oil, gas, nuclear',
                'negative_keywords' => '-politics, -war, -sports, -entertainment, -celebrity, -crypto',
                'priority_regions' => 'UK,USA,EU',
                'content_age_limit' => 30,
                'enabled_feeds' => array(
                    'https://www.energy.gov/rss',
                    'https://www.scientificamerican.com/rss/',
                    'https://cleantechnica.com/feed/',
                    'https://www.carbonbrief.org/rss/'
                )
            ),
            'construction_architecture' => array(
                'name' => 'Construction & Architecture',
                'description' => 'Construction industry, architecture, and building technology',
                'category' => 'industries',
                'visibility' => 'specialized',
                'sort_order' => 33,
                'is_default' => false,
                'positive_keywords' => 'construction, architecture, building, architect, contractor, design, project, building technology, civil engineering',
                'negative_keywords' => '-politics, -war, -sports, -entertainment, -celebrity, -crypto',
                'priority_regions' => 'UK,USA,EU',
                'content_age_limit' => 30,
                'enabled_feeds' => array(
                    'https://www.constructiondigger.com/rss',
                    'https://www.archdaily.com/rss',
                    'https://www.engineering.com/rss',
                    'https://www.building.co.uk/rss'
                )
            ),
            'manufacturing' => array(
                'name' => 'Manufacturing',
                'description' => 'Manufacturing industry, production, and industrial technology',
                'category' => 'industries',
                'visibility' => 'specialized',
                'sort_order' => 34,
                'is_default' => false,
                'positive_keywords' => 'manufacturing, production, factory, industrial, supply chain, manufacturing technology, automation, quality control',
                'negative_keywords' => '-politics, -war, -sports, -entertainment, -celebrity, -crypto',
                'priority_regions' => 'UK,USA,EU',
                'content_age_limit' => 30,
                'enabled_feeds' => array(
                    'https://www.manufacturing.net/rss',
                    'https://www.industryweek.com/rss',
                    'https://www.themanufacturer.com/rss/',
                    'https://www.mfg.com/rss'
                )
            ),
            'agriculture' => array(
                'name' => 'Agriculture',
                'description' => 'Agricultural news, farming, and food production',
                'category' => 'industries',
                'visibility' => 'specialized',
                'sort_order' => 35,
                'is_default' => false,
                'positive_keywords' => 'agriculture, farming, crop, harvest, farmer, food production, agricultural technology, livestock, sustainable farming',
                'negative_keywords' => '-politics, -war, -sports, -entertainment, -celebrity, -crypto',
                'priority_regions' => 'UK,USA,EU',
                'content_age_limit' => 30,
                'enabled_feeds' => array(
                    'https://www.farmprogress.com/rss',
                    'https://www.agriculture.com/rss',
                    'https://www.usda.gov/rss',
                    'https://www.agweb.com/rss'
                )
            ),

            // SPECIALIZED INTERESTS
            'sports_news' => array(
                'name' => 'Sports News',
                'description' => 'Sports news, scores, and athletic updates',
                'category' => 'specialized',
                'visibility' => 'specialized',
                'sort_order' => 36,
                'is_default' => false,
                'positive_keywords' => 'sports, football, soccer, basketball, baseball, tennis, golf, hockey, olympics, athletic, competition, league',
                'negative_keywords' => '-politics, -entertainment, -celebrity gossip, -crypto',
                'priority_regions' => 'UK,USA,EU',
                'content_age_limit' => 1,
                'enabled_feeds' => array(
                    'https://www.espn.com/espn/rss/news',
                    'https://www.theathletic.com/feed/',
                    'https://sbnation.com/rss',
                    'https://www.skysports.com/rss/12040'
                )
            ),
            'arts_culture' => array(
                'name' => 'Arts & Culture',
                'description' => 'Art, culture, museums, and creative industries',
                'category' => 'specialized',
                'visibility' => 'specialized',
                'sort_order' => 37,
                'is_default' => false,
                'positive_keywords' => 'art, culture, museum, gallery, exhibition, artist, painting, sculpture, cultural, creative, artistic',
                'negative_keywords' => '-politics, -war, -sports, -entertainment celebrity, -crypto',
                'priority_regions' => 'UK,USA,EU',
                'content_age_limit' => 30,
                'enabled_feeds' => array(
                    'https://www.artforum.com/rss',
                    'https://news.artnet.com/rss',
                    'https://hyperallergic.com/feed/',
                    'https://www.theartnewspaper.com/rss'
                )
            ),
            'music_industry' => array(
                'name' => 'Music Industry',
                'description' => 'Music news, artists, and industry developments',
                'category' => 'specialized',
                'visibility' => 'specialized',
                'sort_order' => 38,
                'is_default' => false,
                'positive_keywords' => 'music, artist, song, album, concert, festival, musician, singer, band, record label, music industry',
                'negative_keywords' => '-politics, -war, -sports, -entertainment celebrity gossip, -crypto',
                'priority_regions' => 'UK,USA,EU',
                'content_age_limit' => 14,
                'enabled_feeds' => array(
                    'https://www.billboard.com/rss/',
                    'https://www.rollingstone.com/rss/',
                    'https://pitchfork.com/rss/',
                    'https://musicfeeds.com.au/feed/'
                )
            ),
            'literature_books' => array(
                'name' => 'Literature & Books',
                'description' => 'Book news, literary criticism, and publishing',
                'category' => 'specialized',
                'visibility' => 'specialized',
                'sort_order' => 39,
                'is_default' => false,
                'positive_keywords' => 'books, literature, author, novel, poetry, reading, publisher, publishing, literary, fiction, non-fiction',
                'negative_keywords' => '-politics, -war, -sports, -entertainment celebrity, -crypto',
                'priority_regions' => 'UK,USA,EU',
                'content_age_limit' => 30,
                'enabled_feeds' => array(
                    'https://www.publishersweekly.com/pw/rss/index.xml',
                    'https://www.nytimes.com/section/books/review/rss',
                    'https://www.goodreads.com/feeds/rss',
                    'https://bookmarks.reviews/feed/'
                )
            ),
            'climate_environment' => array(
                'name' => 'Climate & Environment',
                'description' => 'Climate change, environmental news, and conservation',
                'category' => 'specialized',
                'visibility' => 'specialized',
                'sort_order' => 40,
                'is_default' => false,
                'positive_keywords' => 'climate change, environment, conservation, global warming, carbon emissions, sustainability, eco-friendly, green',
                'negative_keywords' => '-politics, -war, -sports, -entertainment, -celebrity, -crypto',
                'priority_regions' => 'UK,USA,EU',
                'content_age_limit' => 14,
                'enabled_feeds' => array(
                    'https://www.carbonbrief.org/rss/',
                    'https://www.climatecentral.org/rss',
                    'https://www.worldwildlife.org/rss',
                    'https://www.unep.org/rss'
                )
            ),
            'space_astronomy' => array(
                'name' => 'Space & Astronomy',
                'description' => 'Space exploration, astronomy, and space science',
                'category' => 'specialized',
                'visibility' => 'specialized',
                'sort_order' => 41,
                'is_default' => false,
                'positive_keywords' => 'space, astronomy, telescope, satellite, rocket, spacecraft, planet, star, galaxy, cosmic, astrophysics',
                'negative_keywords' => '-politics, -war, -sports, -entertainment, -celebrity, -crypto',
                'priority_regions' => 'UK,USA,EU',
                'content_age_limit' => 30,
                'enabled_feeds' => array(
                    'https://www.nasa.gov/rss/breaking_news.rss',
                    'https://www.space.com/feeds/news',
                    'https://www.universetoday.com/feed/',
                    'https://www.skyandtelescope.com/feed/'
                )
            ),
            'mental_health' => array(
                'name' => 'Mental Health',
                'description' => 'Mental health awareness, psychology, and wellness',
                'category' => 'specialized',
                'visibility' => 'specialized',
                'sort_order' => 42,
                'is_default' => false,
                'positive_keywords' => 'mental health, psychology, therapy, counseling, depression, anxiety, wellness, mindfulness, self-care',
                'negative_keywords' => '-politics, -war, -sports, -entertainment, -celebrity, -crypto',
                'priority_regions' => 'UK,USA,EU',
                'content_age_limit' => 30,
                'enabled_feeds' => array(
                    'https://www.psychologytoday.com/rss',
                    'https://www.mind.org.uk/rss.xml',
                    'https://www.nimh.nih.gov/rss/news.xml',
                    'https://www.healthline.com/rss'
                )
            ),
            'personal_development' => array(
                'name' => 'Personal Development',
                'description' => 'Self-improvement, productivity, and personal growth',
                'category' => 'specialized',
                'visibility' => 'specialized',
                'sort_order' => 43,
                'is_default' => false,
                'positive_keywords' => 'personal development, self improvement, productivity, goal setting, success, motivation, leadership, career',
                'negative_keywords' => '-politics, -war, -sports, -entertainment, -celebrity, -crypto',
                'priority_regions' => 'UK,USA,EU',
                'content_age_limit' => 30,
                'enabled_feeds' => array(
                    'https://www.success.com/rss',
                    'https://www.mindtools.com/rss',
                    'https://www.tonyrobbins.com/rss/',
                    'https://positivepsychology.com/feed/'
                )
            ),

            // REGIONAL & CULTURAL
            'asian_news' => array(
                'name' => 'Asian News',
                'description' => 'News from Asia-Pacific region',
                'category' => 'regional',
                'visibility' => 'regional',
                'sort_order' => 44,
                'is_default' => false,
                'positive_keywords' => 'Asia, Asian, China, Japan, India, South Korea, Southeast Asia, Pacific, Asia-Pacific, Asian news',
                'negative_keywords' => '-sports, -entertainment, -celebrity, -crypto',
                'priority_regions' => 'AU',
                'content_age_limit' => 7,
                'enabled_feeds' => array(
                    'https://www.straitstimes.com/rss',
                    'https://www.scmp.com/rss',
                    'https://asia.nikkei.com/rss',
                    'https://www.channelnewsasia.com/rss'
                )
            ),
            'latin_american_news' => array(
                'name' => 'Latin American News',
                'description' => 'News from Latin America and the Caribbean',
                'category' => 'regional',
                'visibility' => 'regional',
                'sort_order' => 45,
                'is_default' => false,
                'positive_keywords' => 'Latin America, South America, Central America, Caribbean, Mexico, Brazil, Argentina, Colombia, Latin American news',
                'negative_keywords' => '-sports, -entertainment, -celebrity, -crypto',
                'priority_regions' => 'LATAM',
                'content_age_limit' => 7,
                'enabled_feeds' => array(
                    'https://elpais.com/rss',
                    'https://oglobo.globo.com/rss.xml',
                    'https://www.lanacion.com.ar/rss',
                    'https://www.eltiempo.com/rss'
                )
            ),
            'middle_east_news' => array(
                'name' => 'Middle East News',
                'description' => 'News from Middle East region',
                'category' => 'regional',
                'visibility' => 'regional',
                'sort_order' => 46,
                'is_default' => false,
                'positive_keywords' => 'Middle East, Middle Eastern, Israel, Palestine, Saudi Arabia, Iran, Iraq, Syria, Lebanon, Jordan',
                'negative_keywords' => '-sports, -entertainment, -celebrity, -crypto',
                'priority_regions' => 'ME',
                'content_age_limit' => 7,
                'enabled_feeds' => array(
                    'https://www.aljazeera.com/xml/rss/all.xml',
                    'https://www.jpost.com/rss',
                    'https://www.haaretz.com/feed',
                    'https://www.thenational.ae/rss'
                )
            ),
            'african_news' => array(
                'name' => 'African News',
                'description' => 'News from Africa',
                'category' => 'regional',
                'visibility' => 'regional',
                'sort_order' => 46,
                'is_default' => false,
                'positive_keywords' => 'Africa, African, Nigeria, South Africa, Kenya, Egypt, Ghana, Ethiopia, African news, continent',
                'negative_keywords' => '-sports, -entertainment, -celebrity, -crypto',
                'priority_regions' => 'AF',
                'content_age_limit' => 7,
                'enabled_feeds' => array(
                    'https://mg.co.za/rss/',
                    'https://allafrica.com/rss/allafrica-topstories.rss',
                    'https://www.bbc.com/news/world/africa/rss',
                    'https://africa.businessinsider.com/rss'
                )
            ),
            'local_regional' => array(
                'name' => 'Local & Regional',
                'description' => 'Generic template for local news feeds',
                'category' => 'regional',
                'visibility' => 'hidden',
                'sort_order' => 48,
                'is_default' => false,
                'positive_keywords' => 'local, regional, community, city, town, neighborhood, local news, community news',
                'negative_keywords' => '-national, -international, -world news, -politics',
                'priority_regions' => 'UK,USA,EU',
                'content_age_limit' => 7,
                'enabled_feeds' => array()
            ),
            'breaking_news_alerts' => array(
                'name' => 'Breaking News Alerts',
                'description' => 'Real-time breaking news from major sources',
                'category' => 'regional',
                'visibility' => 'specialized',
                'sort_order' => 50,
                'is_default' => false,
                'positive_keywords' => 'breaking news, urgent, developing, developing story, latest, just in, alert',
                'negative_keywords' => '-opinion, -editorial, -analysis, -commentary',
                'priority_regions' => 'UK,USA,EU',
                'content_age_limit' => 1,
                'enabled_feeds' => array(
                    'https://news.google.com/rss/search?q=breaking+news&hl=en&gl=US&ceid=US:en',
                    'https://feeds.reuters.com/reuters/topNews',
                    'https://rss.cnn.com/rss/edition.rss',
                    'https://feeds.bbci.co.uk/news/rss.xml'
                )
            )
        );
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('admin_init', array($this, 'handle_filter_actions'));
        add_action('wp_ajax_aanp_get_bundle_feeds', array($this, 'ajax_get_bundle_feeds'));
        add_action('wp_ajax_aanp_preview_filtering', array($this, 'ajax_preview_filtering'));
        add_action('wp_ajax_aanp_save_filter_preset', array($this, 'ajax_save_filter_preset'));
        add_action('wp_ajax_aanp_load_filter_preset', array($this, 'ajax_load_filter_preset'));
        add_action('wp_ajax_aanp_create_custom_bundle', array($this, 'ajax_create_custom_bundle'));
        add_action('wp_ajax_aanp_edit_existing_bundle', array($this, 'ajax_edit_existing_bundle'));
        add_action('wp_ajax_aanp_delete_custom_bundle', array($this, 'ajax_delete_custom_bundle'));
        add_action('wp_ajax_aanp_discover_rss_feeds', array($this, 'ajax_discover_rss_feeds'));
        add_action('wp_ajax_aanp_validate_rss_feed', array($this, 'ajax_validate_rss_feed'));
        add_action('aanp_plugin_activate', array($this, 'initialize_default_bundle'));
    }
    
    /**
     * Create database tables for content filtering
     */
    private function create_database_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Enhanced content filter bundles table
        $bundles_sql = "CREATE TABLE {$wpdb->prefix}aanp_content_bundles_enhanced (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL UNIQUE,
            description TEXT,
            category VARCHAR(100) NOT NULL,
            visibility ENUM('visible', 'hidden', 'specialized', 'regional', 'custom') DEFAULT 'visible',
            sort_order INT DEFAULT 0,
            positive_keywords TEXT,
            negative_keywords TEXT,
            priority_regions VARCHAR(255),
            content_age_limit INT DEFAULT 90,
            is_default BOOLEAN DEFAULT FALSE,
            is_custom BOOLEAN DEFAULT FALSE,
            is_active BOOLEAN DEFAULT TRUE,
            categories JSON,
            enabled_feeds JSON,
            settings JSON,
            created_by INT DEFAULT 0,
            usage_count INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_visibility (visibility),
            INDEX idx_sort_order (sort_order),
            INDEX idx_category (category),
            INDEX idx_active (is_active)
        ) $charset_collate;";
        
        // Enhanced RSS feeds table
        $feeds_sql = "CREATE TABLE {$wpdb->prefix}aanp_rss_feeds_enhanced (
            id INT AUTO_INCREMENT PRIMARY KEY,
            bundle_id INT,
            feed_url VARCHAR(500) NOT NULL,
            feed_name VARCHAR(255),
            category VARCHAR(100),
            region VARCHAR(10),
            quality_score DECIMAL(3,2) DEFAULT 0.00,
            last_validated TIMESTAMP,
            is_active BOOLEAN DEFAULT TRUE,
            discovered_by ENUM('manual', 'auto', 'user') DEFAULT 'manual',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (bundle_id) REFERENCES {$wpdb->prefix}aanp_content_bundles_enhanced(id) ON DELETE CASCADE,
            INDEX idx_bundle (bundle_id),
            INDEX idx_quality (quality_score),
            INDEX idx_active (is_active)
        ) $charset_collate;";
        
        // User content filters table
        $filters_sql = "CREATE TABLE {$wpdb->prefix}aanp_user_content_filters (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT DEFAULT 0,
            bundle_slug VARCHAR(255),
            positive_keywords TEXT,
            negative_keywords TEXT,
            advanced_settings JSON,
            is_active BOOLEAN DEFAULT TRUE,
            preset_name VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) $charset_collate;";
        
        // Custom bundles presets table
        $presets_sql = "CREATE TABLE {$wpdb->prefix}aanp_custom_bundle_presets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            preset_name VARCHAR(255) NOT NULL,
            bundle_data JSON NOT NULL,
            keywords_data JSON,
            feeds_data JSON,
            settings_data JSON,
            is_public BOOLEAN DEFAULT FALSE,
            usage_count INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_preset (user_id, preset_name),
            INDEX idx_user (user_id),
            INDEX idx_public (is_public)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($bundles_sql);
        dbDelta($feeds_sql);
        dbDelta($filters_sql);
        dbDelta($presets_sql);
    }
    
    /**
     * Initialize default bundle for new users
     */
    public function initialize_default_bundle() {
        global $wpdb;

        try {
            $wpdb->query('START TRANSACTION');

            // Check if enhanced bundles already exist
            $existing_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}aanp_content_bundles_enhanced");
            if ($existing_count > 50) {
                $wpdb->query('COMMIT');
                return;
            }

            // Insert all 50 bundles
            $insert_errors = array();
            foreach ($this->available_bundles as $slug => $bundle_data) {
                $result = $wpdb->insert(
                    $wpdb->prefix . 'aanp_content_bundles_enhanced',
                    array(
                        'name' => $bundle_data['name'],
                        'slug' => $slug,
                        'description' => $bundle_data['description'],
                        'category' => $bundle_data['category'],
                        'visibility' => $bundle_data['visibility'],
                        'sort_order' => $bundle_data['sort_order'],
                        'positive_keywords' => $bundle_data['positive_keywords'],
                        'negative_keywords' => $bundle_data['negative_keywords'],
                        'priority_regions' => $bundle_data['priority_regions'],
                        'content_age_limit' => $bundle_data['content_age_limit'],
                        'is_default' => $bundle_data['is_default'] ? 1 : 0,
                        'is_custom' => 0, // These are system bundles
                        'is_active' => 1,
                        'categories' => json_encode($bundle_data['categories'] ?? array()),
                        'enabled_feeds' => json_encode($bundle_data['enabled_feeds']),
                        'settings' => json_encode(array(
                            'language_priority' => 'en',
                            'region_bias' => 'balanced',
                            'minimum_quality_score' => 70,
                            'duplicate_detection' => true
                        )),
                        'created_by' => 0 // System created
                    ),
                    array('%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%d')
                );

                if ($result === false) {
                    $insert_errors[] = array(
                        'slug' => $slug,
                        'name' => $bundle_data['name'],
                        'error' => $wpdb->last_error
                    );
                }
            }

            // Activate default bundle for current user
            $default_bundle = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}aanp_content_bundles_enhanced WHERE is_default = 1 LIMIT 1"
            ));

            if ($default_bundle) {
                $result = $wpdb->insert(
                    $wpdb->prefix . 'aanp_user_content_filters',
                    array(
                        'user_id' => get_current_user_id(),
                        'bundle_slug' => $default_bundle->slug,
                        'positive_keywords' => $default_bundle->positive_keywords,
                        'negative_keywords' => $default_bundle->negative_keywords,
                        'advanced_settings' => $default_bundle->settings,
                        'is_active' => 1,
                        'preset_name' => 'Default'
                    ),
                    array('%d', '%s', '%s', '%s', '%s', '%d', '%s')
                );

                if ($result === false) {
                    $insert_errors[] = array(
                        'operation' => 'user_filter_insert',
                        'error' => $wpdb->last_error
                    );
                }
            }

            if (!empty($insert_errors)) {
                $wpdb->query('ROLLBACK');
                foreach ($insert_errors as $error) {
                    $this->logger->error('Failed to insert bundle', $error);
                }
                AANP_Error_Handler::getInstance()->handle_error(
                    'Failed to initialize default bundles: ' . count($insert_errors) . ' errors occurred',
                    array('method' => 'initialize_default_bundle', 'errors' => $insert_errors),
                    'DATABASE'
                );
                return;
            }

            $wpdb->query('COMMIT');

            $this->logger->info('Enhanced content filter bundles initialized', array(
                'total_bundles' => count($this->available_bundles),
                'default_bundle' => $default_bundle->slug ?? 'none'
            ));

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            AANP_Error_Handler::getInstance()->handle_error($e->getMessage(), array('method' => 'initialize_default_bundle'), 'DATABASE');
            $this->logger->error('Transaction failed in initialize_default_bundle', array(
                'error' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Get available content bundles
     *
     * @param bool $active_only Only return active bundles
     * @param string $visibility Filter by visibility (visible, hidden, specialized, etc.)
     * @return array Available bundles
     */
    public function get_available_bundles($active_only = true, $visibility = null) {
        global $wpdb;
        
        $cache_key = 'content_bundles_' . ($active_only ? 'active' : 'all') . '_' . ($visibility ?: 'all');
        $cached = $this->cache_manager->get($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $where_conditions = array();
        $where_values = array();
        
        if ($active_only) {
            $where_conditions[] = "is_active = 1";
        }
        
        if ($visibility) {
            $where_conditions[] = "visibility = %s";
            $where_values[] = $visibility;
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        $query = "SELECT * FROM {$wpdb->prefix}aanp_content_bundles_enhanced {$where_clause} ORDER BY sort_order ASC, is_default DESC, name ASC";
        
        if (!empty($where_values)) {
            $bundles = $wpdb->get_results($wpdb->prepare($query, $where_values));
        } else {
            $bundles = $wpdb->get_results($query);
        }
        
        $formatted_bundles = array();
        foreach ($bundles as $bundle) {
            $formatted_bundles[$bundle->slug] = array(
                'id' => $bundle->id,
                'name' => $bundle->name,
                'slug' => $bundle->slug,
                'description' => $bundle->description,
                'category' => $bundle->category,
                'visibility' => $bundle->visibility,
                'sort_order' => $bundle->sort_order,
                'positive_keywords' => $bundle->positive_keywords,
                'negative_keywords' => $bundle->negative_keywords,
                'priority_regions' => $bundle->priority_regions,
                'content_age_limit' => $bundle->content_age_limit,
                'is_default' => (bool) $bundle->is_default,
                'is_custom' => (bool) $bundle->is_custom,
                'categories' => json_decode($bundle->categories, true) ?: array(),
                'enabled_feeds' => json_decode($bundle->enabled_feeds, true) ?: array(),
                'settings' => json_decode($bundle->settings, true) ?: array()
            );
        }
        
        $this->cache_manager->set($cache_key, $formatted_bundles, 3600);
        return $formatted_bundles;
    }
    
    /**
     * Apply a content bundle
     *
     * @param string $bundle_slug Bundle slug
     * @param array $custom_filters Custom filters to override
     * @return array Application result
     */
    public function apply_bundle($bundle_slug, $custom_filters = array()) {
        global $wpdb;

        try {
            $wpdb->query('START TRANSACTION');

            // Get bundle data from enhanced table
            $bundle = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}aanp_content_bundles_enhanced WHERE slug = %s AND is_active = 1",
                $bundle_slug
            ));

            if (!$bundle) {
                $wpdb->query('ROLLBACK');
                throw new Exception('Bundle not found or inactive');
            }

            // Merge with custom filters
            $filters = array(
                'positive_keywords' => $bundle->positive_keywords,
                'negative_keywords' => $bundle->negative_keywords,
                'priority_regions' => $bundle->priority_regions,
                'content_age_limit' => $bundle->content_age_limit,
                'category' => $bundle->category,
                'visibility' => $bundle->visibility,
                'categories' => json_decode($bundle->categories, true) ?: array(),
                'enabled_feeds' => json_decode($bundle->enabled_feeds, true) ?: array(),
                'settings' => json_decode($bundle->settings, true) ?: array()
            );

            $filters = array_merge($filters, $custom_filters);

            // Update or insert user filter
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}aanp_user_content_filters
                 WHERE user_id = %d AND is_active = 1",
                get_current_user_id()
            ));

            if ($existing) {
                $result = $wpdb->update(
                    $wpdb->prefix . 'aanp_user_content_filters',
                    array(
                        'bundle_slug' => $bundle_slug,
                        'positive_keywords' => $filters['positive_keywords'],
                        'negative_keywords' => $filters['negative_keywords'],
                        'advanced_settings' => json_encode($filters),
                        'updated_at' => current_time('mysql')
                    ),
                    array('id' => $existing->id),
                    array('%s', '%s', '%s', '%s', '%s'),
                    array('%d')
                );
            } else {
                $result = $wpdb->insert(
                    $wpdb->prefix . 'aanp_user_content_filters',
                    array(
                        'user_id' => get_current_user_id(),
                        'bundle_slug' => $bundle_slug,
                        'positive_keywords' => $filters['positive_keywords'],
                        'negative_keywords' => $filters['negative_keywords'],
                        'advanced_settings' => json_encode($filters),
                        'is_active' => 1,
                        'preset_name' => 'Current'
                    ),
                    array('%d', '%s', '%s', '%s', '%s', '%d', '%s')
                );
            }

            if ($result === false) {
                $wpdb->query('ROLLBACK');
                throw new Exception('Failed to save user filters');
            }

            // Update usage count for bundle
            $update_result = $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}aanp_content_bundles_enhanced
                 SET usage_count = usage_count + 1
                 WHERE id = %d",
                $bundle->id
            ));

            if ($update_result === false) {
                $wpdb->query('ROLLBACK');
                throw new Exception('Failed to update bundle usage count');
            }

            $wpdb->query('COMMIT');

            // Update RSS feed selections (non-transactional)
            $this->update_rss_feed_selections($filters['enabled_feeds']);

            // Clear relevant caches
            $this->clear_filter_caches();

            $this->logger->info('Content bundle applied', array(
                'bundle' => $bundle_slug,
                'bundle_id' => $bundle->id,
                'user_id' => get_current_user_id(),
                'feeds_enabled' => count($filters['enabled_feeds'])
            ));

            return array(
                'success' => true,
                'bundle' => $bundle->name,
                'bundle_id' => $bundle->id,
                'filters_applied' => $filters,
                'feeds_enabled' => count($filters['enabled_feeds']),
                'timestamp' => current_time('Y-m-d H:i:s')
            );

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            AANP_Error_Handler::getInstance()->handle_error($e->getMessage(), array('method' => 'apply_bundle', 'bundle' => $bundle_slug), 'DATABASE');
            $this->logger->error('Failed to apply content bundle', array(
                'bundle' => $bundle_slug,
                'error' => $e->getMessage()
            ));

            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'timestamp' => current_time('Y-m-d H:i:s')
            );
        }
    }
    
    /**
     * Filter article content
     *
     * @param array $article_data Article data to filter
     * @param array $user_filters User filter settings
     * @return array Filter result
     */
    public function filter_article($article_data, $user_filters = null) {
        try {
            // Get current user filters if not provided
            if (!$user_filters) {
                $user_filters = $this->get_current_user_filters();
            }
            
            if (!$user_filters) {
                return array('passed' => true, 'reason' => 'No filters configured');
            }
            
            $cache_key = 'article_filter_' . md5(serialize($article_data) . serialize($user_filters));
            $cached = $this->cache_manager->get($cache_key);
            
            if ($cached !== false) {
                return $cached;
            }
            
            $passed = true;
            $matched_keywords = array();
            $rejection_reasons = array();
            
            // Combine content for filtering
            $content_text = strtolower(
                $article_data['title'] . ' ' . 
                $article_data['description'] . ' ' . 
                ($article_data['category'] ?? '') . ' ' . 
                ($article_data['author'] ?? '')
            );
            
            // Check positive keywords
            if (!empty($user_filters['positive_keywords'])) {
                $positive_keywords = array_map('trim', explode(',', $user_filters['positive_keywords']));
                $has_positive_match = false;
                
                foreach ($positive_keywords as $keyword) {
                    $keyword = strtolower(trim($keyword));
                    if (strpos($content_text, $keyword) !== false) {
                        $has_positive_match = true;
                        $matched_keywords[] = $keyword;
                        break; // At least one positive match is enough
                    }
                }
                
                if (!$has_positive_match) {
                    $passed = false;
                    $rejection_reasons[] = 'No positive keywords matched';
                }
            }
            
            // Check negative keywords
            if (!empty($user_filters['negative_keywords'])) {
                $negative_keywords = array_map('trim', explode(',', $user_filters['negative_keywords']));
                
                foreach ($negative_keywords as $keyword) {
                    $keyword = strtolower(trim($keyword));
                    if (strpos($content_text, $keyword) !== false) {
                        $passed = false;
                        $rejection_reasons[] = "Negative keyword matched: {$keyword}";
                        break;
                    }
                }
            }
            
            // Check content age
            if (isset($user_filters['content_age_limit']) && $user_filters['content_age_limit'] > 0) {
                if (isset($article_data['timestamp'])) {
                    $age_days = (time() - $article_data['timestamp']) / DAY_IN_SECONDS;
                    if ($age_days > $user_filters['content_age_limit']) {
                        $passed = false;
                        $rejection_reasons[] = "Content too old: {$age_days} days";
                    }
                }
            }
            
            // Check region preference
            if (!empty($user_filters['priority_regions'])) {
                $regions = explode(',', $user_filters['priority_regions']);
                $source_region = $this->detect_source_region($article_data);
                
                if ($source_region && !in_array($source_region, $regions)) {
                    // Soft rejection - don't block but note the mismatch
                    $rejection_reasons[] = "Source region ({$source_region}) not in priority regions";
                }
            }
            
            $result = array(
                'passed' => $passed,
                'matched_keywords' => $matched_keywords,
                'rejection_reasons' => $rejection_reasons,
                'content_score' => $this->calculate_content_score($article_data, $user_filters),
                'timestamp' => current_time('Y-m-d H:i:s')
            );
            
            // Cache the result
            $this->cache_manager->set($cache_key, $result, 1800); // Cache for 30 minutes
            
            return $result;
            
        } catch (Exception $e) {
            $this->logger->error('Content filtering failed', array(
                'error' => $e->getMessage()
            ));
            
            return array(
                'passed' => true, // Fail open - don't block content on filtering errors
                'error' => $e->getMessage(),
                'timestamp' => current_time('Y-m-d H:i:s')
            );
        }
    }
    
    /**
     * Get current user filters
     *
     * @return array|null User filters or null
     */
    public function get_current_user_filters() {
        global $wpdb;
        
        $cache_key = 'user_filters_' . get_current_user_id();
        $cached = $this->cache_manager->get($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $filters = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aanp_user_content_filters 
             WHERE user_id = %d AND is_active = 1 
             ORDER BY updated_at DESC LIMIT 1",
            get_current_user_id()
        ), ARRAY_A);
        
        if ($filters) {
            $filters['advanced_settings'] = json_decode($filters['advanced_settings'], true) ?: array();
            $this->cache_manager->set($cache_key, $filters, 1800);
            return $filters;
        }
        
        return null;
    }
    
    /**
     * Update RSS feed selections based on bundle
     *
     * @param array $enabled_feed_urls Feed URLs to enable
     */
    private function update_rss_feed_selections($enabled_feed_urls) {
        if (!class_exists('AANP_RSSFeedManager')) {
            return;
        }
        
        $rss_manager = new AANP_RSSFeedManager();
        $all_feeds = $rss_manager->get_feeds(array('limit' => 1000));
        
        $feed_ids_to_enable = array();
        $feed_ids_to_disable = array();
        
        // Map URLs to IDs
        $url_to_id_map = array();
        foreach ($all_feeds as $feed) {
            $url_to_id_map[$feed['url']] = $feed['id'];
        }
        
        // Determine which feeds to enable/disable
        foreach ($all_feeds as $feed) {
            if (in_array($feed['url'], $enabled_feed_urls)) {
                if (!$feed['enabled']) {
                    $feed_ids_to_enable[] = $feed['id'];
                }
            } else {
                if ($feed['enabled']) {
                    $feed_ids_to_disable[] = $feed['id'];
                }
            }
        }
        
        // Apply changes
        if (!empty($feed_ids_to_enable)) {
            $rss_manager->enable_feeds($feed_ids_to_enable);
        }
        
        if (!empty($feed_ids_to_disable)) {
            $rss_manager->disable_feeds($feed_ids_to_disable);
        }
    }
    
    /**
     * Detect source region from article data
     *
     * @param array $article_data Article data
     * @return string|null Detected region
     */
    private function detect_source_region($article_data) {
        $source_url = $article_data['source_url'] ?? '';
        
        // Simple region detection based on domain
        $region_patterns = array(
            'UK' => array('bbc.co.uk', 'guardian.co.uk', 'telegraph.co.uk', 'reuters.com', 'ft.com'),
            'USA' => array('cnn.com', 'nytimes.com', 'washingtonpost.com', 'wsj.com', 'bloomberg.com'),
            'EU' => array('dw.com', 'euronews.com', 'lemonde.fr', 'elpais.com', 'corriere.it')
        );
        
        foreach ($region_patterns as $region => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($source_url, $pattern) !== false) {
                    return $region;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Calculate content score
     *
     * @param array $article_data Article data
     * @param array $filters Filter settings
     * @return float Content score (0-100)
     */
    private function calculate_content_score($article_data, $filters) {
        $score = 50; // Base score
        
        // Bonus for positive keyword matches
        if (!empty($filters['positive_keywords'])) {
            $positive_keywords = array_map('trim', explode(',', $filters['positive_keywords']));
            $content_text = strtolower($article_data['title'] . ' ' . $article_data['description']);
            
            $matches = 0;
            foreach ($positive_keywords as $keyword) {
                if (strpos($content_text, strtolower(trim($keyword))) !== false) {
                    $matches++;
                }
            }
            
            $score += min($matches * 10, 30); // Max 30 points for keyword matches
        }
        
        // Penalty for negative keyword matches
        if (!empty($filters['negative_keywords'])) {
            $negative_keywords = array_map('trim', explode(',', $filters['negative_keywords']));
            $content_text = strtolower($article_data['title'] . ' ' . $article_data['description']);
            
            foreach ($negative_keywords as $keyword) {
                if (strpos($content_text, strtolower(trim($keyword))) !== false) {
                    $score -= 20; // 20 point penalty per negative match
                    break;
                }
            }
        }
        
        return max(0, min(100, $score)); // Clamp between 0-100
    }
    
    /**
     * Clear filter-related caches
     */
    private function clear_filter_caches() {
        $this->cache_manager->delete_by_pattern('content_bundles_');
        $this->cache_manager->delete_by_pattern('user_filters_');
        $this->cache_manager->delete_by_pattern('article_filter_');
    }
    
    /**
     * AJAX handler for getting bundle feeds
     */
    public function ajax_get_bundle_feeds() {
        check_ajax_referer('aanp_filter_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $bundle_slug = sanitize_text_field($_POST['bundle_slug'] ?? '');
        $bundles = $this->get_available_bundles();
        
        if (isset($bundles[$bundle_slug])) {
            $bundle = $bundles[$bundle_slug];
            wp_send_json_success(array(
                'bundle' => $bundle,
                'enabled_feeds' => count($bundle['enabled_feeds']),
                'feed_list' => $bundle['enabled_feeds']
            ));
        } else {
            wp_send_json_error('Bundle not found');
        }
    }
    
    /**
     * Handle filter-related admin actions
     */
    public function handle_filter_actions() {
        if (!isset($_GET['aanp_action']) || $_GET['aanp_action'] !== 'apply_content_bundle') {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $bundle_slug = sanitize_text_field($_GET['bundle'] ?? '');
        $result = $this->apply_bundle($bundle_slug);
        
        if ($result['success']) {
            add_action('admin_notices', function() use ($result) {
                echo '<div class="notice notice-success"><p>Content bundle "' . 
                     esc_html($result['bundle']) . '" applied successfully. ' .
                     esc_html($result['feeds_enabled']) . ' feeds enabled.</p></div>';
            });
        } else {
            add_action('admin_notices', function() use ($result) {
                echo '<div class="notice notice-error"><p>Failed to apply content bundle: ' . 
                     esc_html($result['error']) . '</p></div>';
            });
        }
    }
    
    /**
     * Get filter statistics
     *
     * @return array Filter statistics
     */
    public function get_filter_statistics() {
        global $wpdb;

        try {
            $wpdb->query('START TRANSACTION');

            $stats = array(
                'total_bundles' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}aanp_content_bundles_enhanced WHERE is_active = 1"),
                'visible_bundles' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}aanp_content_bundles_enhanced WHERE visibility = 'visible' AND is_active = 1"),
                'specialized_bundles' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}aanp_content_bundles_enhanced WHERE visibility = 'specialized' AND is_active = 1"),
                'hidden_bundles' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}aanp_content_bundles_enhanced WHERE visibility = 'hidden' AND is_active = 1"),
                'custom_bundles' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}aanp_content_bundles_enhanced WHERE is_custom = 1 AND is_active = 1"),
                'active_filters' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}aanp_user_content_filters WHERE is_active = 1"),
                'user_presets' => $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}aanp_user_content_filters
                     WHERE user_id = %d AND preset_name IS NOT NULL",
                    get_current_user_id()
                )),
                'bundle_categories' => $wpdb->get_results(
                    "SELECT category, COUNT(*) as count FROM {$wpdb->prefix}aanp_content_bundles_enhanced
                     WHERE is_active = 1 GROUP BY category ORDER BY count DESC"
                ),
                'timestamp' => current_time('Y-m-d H:i:s')
            );

            // Check if any queries failed
            $failed_queries = array();
            foreach ($stats as $key => $value) {
                if ($value === false && $key !== 'timestamp' && $key !== 'bundle_categories') {
                    $failed_queries[] = $key;
                }
            }

            // For bundle_categories, check if it's false or empty array
            if ($stats['bundle_categories'] === false) {
                $failed_queries[] = 'bundle_categories';
            }

            if (!empty($failed_queries)) {
                $wpdb->query('ROLLBACK');
                AANP_Error_Handler::getInstance()->handle_error(
                    'Failed to retrieve filter statistics: ' . implode(', ', $failed_queries) . ' queries failed',
                    array('method' => 'get_filter_statistics', 'failed_queries' => $failed_queries),
                    'DATABASE'
                );
                // Return default values for failed queries to fail gracefully
                $default_stats = array(
                    'total_bundles' => 0,
                    'visible_bundles' => 0,
                    'specialized_bundles' => 0,
                    'hidden_bundles' => 0,
                    'custom_bundles' => 0,
                    'active_filters' => 0,
                    'user_presets' => 0,
                    'bundle_categories' => array(),
                    'timestamp' => current_time('Y-m-d H:i:s')
                );
                return $default_stats;
            }

            $wpdb->query('COMMIT');
            return $stats;

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            AANP_Error_Handler::getInstance()->handle_error($e->getMessage(), array('method' => 'get_filter_statistics'), 'DATABASE');
            // Return default values to fail gracefully
            return array(
                'total_bundles' => 0,
                'visible_bundles' => 0,
                'specialized_bundles' => 0,
                'hidden_bundles' => 0,
                'custom_bundles' => 0,
                'active_filters' => 0,
                'user_presets' => 0,
                'bundle_categories' => array(),
                'timestamp' => current_time('Y-m-d H:i:s')
            );
        }
    }
    
    /**
     * AJAX handler for creating custom bundle
     */
    public function ajax_create_custom_bundle() {
        check_ajax_referer('aanp_filter_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        try {
            $bundle_data = array(
                'name' => sanitize_text_field($_POST['bundle_name'] ?? ''),
                'description' => sanitize_textarea_field($_POST['bundle_description'] ?? ''),
                'positive_keywords' => sanitize_textarea_field($_POST['positive_keywords'] ?? ''),
                'negative_keywords' => sanitize_textarea_field($_POST['negative_keywords'] ?? ''),
                'priority_regions' => sanitize_text_field($_POST['priority_regions'] ?? 'UK,USA,EU'),
                'content_age_limit' => intval($_POST['content_age_limit'] ?? 7),
                'category' => sanitize_text_field($_POST['bundle_category'] ?? 'custom'),
                'visibility' => 'custom'
            );
            
            if (empty($bundle_data['name'])) {
                throw new Exception('Bundle name is required');
            }
            
            $result = $this->create_custom_bundle($bundle_data);
            
            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result['error']);
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * AJAX handler for editing existing bundle
     */
    public function ajax_edit_existing_bundle() {
        check_ajax_referer('aanp_filter_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        try {
            $bundle_id = intval($_POST['bundle_id'] ?? 0);
            $updates = array(
                'name' => sanitize_text_field($_POST['bundle_name'] ?? ''),
                'description' => sanitize_textarea_field($_POST['bundle_description'] ?? ''),
                'positive_keywords' => sanitize_textarea_field($_POST['positive_keywords'] ?? ''),
                'negative_keywords' => sanitize_textarea_field($_POST['negative_keywords'] ?? ''),
                'priority_regions' => sanitize_text_field($_POST['priority_regions'] ?? 'UK,USA,EU'),
                'content_age_limit' => intval($_POST['content_age_limit'] ?? 7)
            );
            
            $result = $this->edit_existing_bundle($bundle_id, $updates);
            
            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result['error']);
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * AJAX handler for deleting custom bundle
     */
    public function ajax_delete_custom_bundle() {
        check_ajax_referer('aanp_filter_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        try {
            $bundle_id = intval($_POST['bundle_id'] ?? 0);
            $result = $this->delete_custom_bundle($bundle_id);
            
            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result['error']);
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * AJAX handler for discovering RSS feeds
     */
    public function ajax_discover_rss_feeds() {
        check_ajax_referer('aanp_filter_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        try {
            $category = sanitize_text_field($_POST['category'] ?? '');
            $keywords = sanitize_text_field($_POST['keywords'] ?? '');
            
            $result = $this->discover_rss_feeds_for_bundle($category, $keywords);
            
            wp_send_json_success($result);
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * AJAX handler for validating RSS feed
     */
    public function ajax_validate_rss_feed() {
        check_ajax_referer('aanp_filter_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        try {
            $feed_url = esc_url_raw($_POST['feed_url'] ?? '');
            $result = $this->validate_rss_feed($feed_url);
            
            wp_send_json_success($result);
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Create custom bundle
     *
     * @param array $bundle_data Bundle data
     * @return array Creation result
     */
    public function create_custom_bundle($bundle_data) {
        global $wpdb;

        try {
            $wpdb->query('START TRANSACTION');

            // Generate unique slug
            $slug = sanitize_title($bundle_data['name']);
            $original_slug = $slug;
            $counter = 1;

            while ($wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}aanp_content_bundles_enhanced WHERE slug = %s",
                $slug
            ))) {
                $slug = $original_slug . '-' . $counter;
                $counter++;
            }

            // Insert bundle
            $result = $wpdb->insert(
                $wpdb->prefix . 'aanp_content_bundles_enhanced',
                array(
                    'name' => $bundle_data['name'],
                    'slug' => $slug,
                    'description' => $bundle_data['description'],
                    'category' => $bundle_data['category'],
                    'visibility' => $bundle_data['visibility'],
                    'positive_keywords' => $bundle_data['positive_keywords'],
                    'negative_keywords' => $bundle_data['negative_keywords'],
                    'priority_regions' => $bundle_data['priority_regions'],
                    'content_age_limit' => $bundle_data['content_age_limit'],
                    'is_custom' => 1,
                    'created_by' => get_current_user_id(),
                    'is_active' => 1,
                    'settings' => json_encode(array(
                        'language_priority' => 'en',
                        'region_bias' => 'balanced',
                        'minimum_quality_score' => 70,
                        'duplicate_detection' => true
                    ))
                ),
                array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s')
            );

            if ($result === false) {
                $wpdb->query('ROLLBACK');
                throw new Exception('Failed to create custom bundle');
            }

            $bundle_id = $wpdb->insert_id;
            $wpdb->query('COMMIT');

            $this->clear_filter_caches();

            $this->logger->info('Custom bundle created', array(
                'bundle_id' => $bundle_id,
                'bundle_name' => $bundle_data['name'],
                'user_id' => get_current_user_id()
            ));

            return array(
                'success' => true,
                'bundle_id' => $bundle_id,
                'bundle_slug' => $slug,
                'message' => 'Custom bundle created successfully'
            );

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            AANP_Error_Handler::getInstance()->handle_error($e->getMessage(), array('method' => 'create_custom_bundle'), 'DATABASE');
            $this->logger->error('Failed to create custom bundle', array(
                'error' => $e->getMessage(),
                'user_id' => get_current_user_id()
            ));
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Edit existing bundle
     *
     * @param int $bundle_id Bundle ID
     * @param array $updates Updates to apply
     * @return array Edit result
     */
    public function edit_existing_bundle($bundle_id, $updates) {
        global $wpdb;

        try {
            $wpdb->query('START TRANSACTION');

            // Check if bundle exists and user can edit it
            $bundle = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}aanp_content_bundles_enhanced WHERE id = %d",
                $bundle_id
            ));

            if (!$bundle) {
                $wpdb->query('ROLLBACK');
                throw new Exception('Bundle not found');
            }

            // Only allow editing custom bundles
            if (!$bundle->is_custom) {
                $wpdb->query('ROLLBACK');
                throw new Exception('Only custom bundles can be edited');
            }

            // Update bundle
            $result = $wpdb->update(
                $wpdb->prefix . 'aanp_content_bundles_enhanced',
                array(
                    'name' => $updates['name'],
                    'description' => $updates['description'],
                    'positive_keywords' => $updates['positive_keywords'],
                    'negative_keywords' => $updates['negative_keywords'],
                    'priority_regions' => $updates['priority_regions'],
                    'content_age_limit' => $updates['content_age_limit'],
                    'updated_at' => current_time('mysql')
                ),
                array('id' => $bundle_id),
                array('%s', '%s', '%s', '%s', '%s', '%d', '%s'),
                array('%d')
            );

            if ($result === false) {
                $wpdb->query('ROLLBACK');
                throw new Exception('Failed to update bundle');
            }

            $wpdb->query('COMMIT');

            $this->clear_filter_caches();

            $this->logger->info('Custom bundle updated', array(
                'bundle_id' => $bundle_id,
                'bundle_name' => $updates['name'],
                'user_id' => get_current_user_id()
            ));

            return array(
                'success' => true,
                'bundle_id' => $bundle_id,
                'message' => 'Bundle updated successfully'
            );

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            AANP_Error_Handler::getInstance()->handle_error($e->getMessage(), array('method' => 'edit_existing_bundle', 'bundle_id' => $bundle_id), 'DATABASE');
            $this->logger->error('Failed to update custom bundle', array(
                'error' => $e->getMessage(),
                'bundle_id' => $bundle_id,
                'user_id' => get_current_user_id()
            ));
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Delete custom bundle
     *
     * @param int $bundle_id Bundle ID
     * @return array Delete result
     */
    public function delete_custom_bundle($bundle_id) {
        global $wpdb;

        try {
            $wpdb->query('START TRANSACTION');

            // Check if bundle exists and user can delete it
            $bundle = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}aanp_content_bundles_enhanced WHERE id = %d",
                $bundle_id
            ));

            if (!$bundle) {
                $wpdb->query('ROLLBACK');
                throw new Exception('Bundle not found');
            }

            // Only allow deleting custom bundles
            if (!$bundle->is_custom) {
                $wpdb->query('ROLLBACK');
                throw new Exception('Only custom bundles can be deleted');
            }

            // Delete bundle
            $result = $wpdb->delete(
                $wpdb->prefix . 'aanp_content_bundles_enhanced',
                array('id' => $bundle_id),
                array('%d')
            );

            if ($result === false) {
                $wpdb->query('ROLLBACK');
                throw new Exception('Failed to delete bundle');
            }

            $wpdb->query('COMMIT');

            $this->clear_filter_caches();

            $this->logger->info('Custom bundle deleted', array(
                'bundle_id' => $bundle_id,
                'bundle_name' => $bundle->name,
                'user_id' => get_current_user_id()
            ));

            return array(
                'success' => true,
                'bundle_id' => $bundle_id,
                'message' => 'Bundle deleted successfully'
            );

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            AANP_Error_Handler::getInstance()->handle_error($e->getMessage(), array('method' => 'delete_custom_bundle', 'bundle_id' => $bundle_id), 'DATABASE');
            $this->logger->error('Failed to delete custom bundle', array(
                'error' => $e->getMessage(),
                'bundle_id' => $bundle_id,
                'user_id' => get_current_user_id()
            ));
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Discover RSS feeds for bundle
     *
     * @param string $category Bundle category
     * @param string $keywords Bundle keywords
     * @return array Discovery results
     */
    public function discover_rss_feeds_for_bundle($category, $keywords) {
        $discovery_engine = new AANP_RSSFeedDiscoverer();
        return $discovery_engine->discover_feeds_for_bundle($category, $keywords);
    }
    
    /**
     * Validate RSS feed
     *
     * @param string $feed_url Feed URL
     * @return array Validation result
     */
    public function validate_rss_feed($feed_url) {
        $discovery_engine = new AANP_RSSFeedDiscoverer();
        return $discovery_engine->validate_rss_feed($feed_url);
    }
    
    /**
     * Get bundles by visibility
     *
     * @param string $visibility Visibility filter
     * @return array Bundles
     */
    public function get_bundles_by_visibility($visibility = 'visible') {
        global $wpdb;
        
        $cache_key = 'bundles_visibility_' . $visibility;
        $cached = $this->cache_manager->get($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $bundles = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aanp_content_bundles_enhanced
             WHERE visibility = %s AND is_active = 1
             ORDER BY sort_order ASC, name ASC",
            $visibility
        ));
        
        $formatted_bundles = array();
        foreach ($bundles as $bundle) {
            $formatted_bundles[$bundle->slug] = array(
                'id' => $bundle->id,
                'name' => $bundle->name,
                'slug' => $bundle->slug,
                'description' => $bundle->description,
                'category' => $bundle->category,
                'positive_keywords' => $bundle->positive_keywords,
                'negative_keywords' => $bundle->negative_keywords,
                'priority_regions' => $bundle->priority_regions,
                'content_age_limit' => $bundle->content_age_limit,
                'is_custom' => (bool) $bundle->is_custom,
                'sort_order' => $bundle->sort_order,
                'categories' => json_decode($bundle->categories, true) ?: array(),
                'enabled_feeds' => json_decode($bundle->enabled_feeds, true) ?: array(),
                'settings' => json_decode($bundle->settings, true) ?: array()
            );
        }
        
        $this->cache_manager->set($cache_key, $formatted_bundles, 3600);
        return $formatted_bundles;
    }
}