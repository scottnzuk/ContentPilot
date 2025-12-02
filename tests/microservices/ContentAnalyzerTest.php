<?php
/**
 * PHPUnit tests for ContentAnalyzer
 *
 * Tests the content analysis functionality including SEO scoring,
 * readability analysis, and EEAT optimization.
 *
 * @package AI_Auto_News_Poster\Tests\Microservices
 */

require_once __DIR__ . '/MicroservicesTestBase.php';

class ContentAnalyzerTest extends MicroservicesTestBase
{
    /** @var AANP_ContentAnalyzer */
    private $contentAnalyzer;

    /**
     * Set up before each test
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->contentAnalyzer = new AANP_ContentAnalyzer();
    }

    /**
     * Test content analysis
     */
    public function testContentAnalysis()
    {
        $this->logInfo('Testing Content analysis');

        $test_content = $this->createTestContent();

        $analysis_result = $this->contentAnalyzer->analyze_content($test_content, array('primary_keyword' => 'test'));

        $this->assertIsArray($analysis_result, 'Content analysis should return an array');
        $this->assertArrayHasKey('readability_score', $analysis_result, 'Analysis should include readability score');
        $this->assertArrayHasKey('seo_score', $analysis_result, 'Analysis should include SEO score');
        $this->assertArrayHasKey('keyword_density', $analysis_result, 'Analysis should include keyword density');
        $this->assertArrayHasKey('eeat_score', $analysis_result, 'Analysis should include EEAT score');
    }

    /**
     * Test readability scoring
     */
    public function testReadabilityScoring()
    {
        $this->logInfo('Testing Readability scoring');

        $test_content = $this->createTestContent();

        $analysis_result = $this->contentAnalyzer->analyze_content($test_content);

        $this->assertArrayHasKey('readability_score', $analysis_result, 'Analysis should include readability score');
        $this->assertIsNumeric($analysis_result['readability_score'], 'Readability score should be numeric');
        $this->assertGreaterThanOrEqual(0, $analysis_result['readability_score'], 'Readability score should be >= 0');
        $this->assertLessThanOrEqual(100, $analysis_result['readability_score'], 'Readability score should be <= 100');
    }

    /**
     * Test SEO scoring
     */
    public function testSeoScoring()
    {
        $this->logInfo('Testing SEO scoring');

        $test_content = $this->createTestContent();

        $analysis_result = $this->contentAnalyzer->analyze_content($test_content, array('primary_keyword' => 'test'));

        $this->assertArrayHasKey('seo_score', $analysis_result, 'Analysis should include SEO score');
        $this->assertIsNumeric($analysis_result['seo_score'], 'SEO score should be numeric');
        $this->assertGreaterThanOrEqual(0, $analysis_result['seo_score'], 'SEO score should be >= 0');
        $this->assertLessThanOrEqual(100, $analysis_result['seo_score'], 'SEO score should be <= 100');
    }

    /**
     * Test keyword analysis
     */
    public function testKeywordAnalysis()
    {
        $this->logInfo('Testing Keyword analysis');

        $test_content = $this->createTestContent();

        $analysis_result = $this->contentAnalyzer->analyze_content($test_content, array('primary_keyword' => 'test'));

        $this->assertArrayHasKey('keyword_density', $analysis_result, 'Analysis should include keyword density');
        $this->assertIsArray($analysis_result['keyword_density'], 'Keyword density should be an array');
        $this->assertArrayHasKey('test', $analysis_result['keyword_density'], 'Keyword density should include primary keyword');
    }

    /**
     * Test EEAT scoring
     */
    public function testEeatScoring()
    {
        $this->logInfo('Testing EEAT scoring');

        $test_content = $this->createTestContent();

        $analysis_result = $this->contentAnalyzer->analyze_content($test_content);

        $this->assertArrayHasKey('eeat_score', $analysis_result, 'Analysis should include EEAT score');
        $this->assertIsArray($analysis_result['eeat_score'], 'EEAT score should be an array');
        $this->assertArrayHasKey('expertise', $analysis_result['eeat_score'], 'EEAT score should include expertise');
        $this->assertArrayHasKey('authoritativeness', $analysis_result['eeat_score'], 'EEAT score should include authoritativeness');
        $this->assertArrayHasKey('trustworthiness', $analysis_result['eeat_score'], 'EEAT score should include trustworthiness');
    }

    /**
     * Test content health check
     */
    public function testContentHealthCheck()
    {
        $this->logInfo('Testing Content health check');

        $health = $this->contentAnalyzer->health_check();
        $this->assertIsBool($health, 'Health check should return a boolean');
        $this->assertTrue($health, 'Content analyzer should be healthy');
    }

    /**
     * Test content improvement suggestions
     */
    public function testContentImprovementSuggestions()
    {
        $this->logInfo('Testing Content improvement suggestions');

        $test_content = $this->createTestContent();

        $analysis_result = $this->contentAnalyzer->analyze_content($test_content);

        $this->assertArrayHasKey('suggestions', $analysis_result, 'Analysis should include suggestions');
        $this->assertIsArray($analysis_result['suggestions'], 'Suggestions should be an array');

        if (!empty($analysis_result['suggestions'])) {
            $this->assertArrayHasKey('type', $analysis_result['suggestions'][0], 'Suggestion should have type');
            $this->assertArrayHasKey('message', $analysis_result['suggestions'][0], 'Suggestion should have message');
            $this->assertArrayHasKey('severity', $analysis_result['suggestions'][0], 'Suggestion should have severity');
        }
    }

    /**
     * Test content with different keyword densities
     */
    public function testKeywordDensityVariations()
    {
        $this->logInfo('Testing Keyword density variations');

        // Test with low keyword density
        $low_density_content = array(
            'title' => 'Test Article',
            'content' => '<p>This is some content without the keyword.</p>',
            'meta_description' => 'Description without keyword'
        );

        $low_density_result = $this->contentAnalyzer->analyze_content($low_density_content, array('primary_keyword' => 'test'));

        // Test with high keyword density
        $high_density_content = array(
            'title' => 'Test Article Test Test',
            'content' => '<p>Test test test test test test test test test test.</p>',
            'meta_description' => 'Test test test test test'
        );

        $high_density_result = $this->contentAnalyzer->analyze_content($high_density_content, array('primary_keyword' => 'test'));

        $this->assertLessThan($high_density_result['keyword_density']['test'], $low_density_result['keyword_density']['test'], 'Low density content should have lower keyword density');
    }

    /**
     * Test content structure analysis
     */
    public function testContentStructureAnalysis()
    {
        $this->logInfo('Testing Content structure analysis');

        $test_content = $this->createTestContent();

        $analysis_result = $this->contentAnalyzer->analyze_content($test_content);

        $this->assertArrayHasKey('structure_analysis', $analysis_result, 'Analysis should include structure analysis');
        $this->assertIsArray($analysis_result['structure_analysis'], 'Structure analysis should be an array');
        $this->assertArrayHasKey('heading_count', $analysis_result['structure_analysis'], 'Structure analysis should include heading count');
        $this->assertArrayHasKey('paragraph_count', $analysis_result['structure_analysis'], 'Structure analysis should include paragraph count');
        $this->assertArrayHasKey('image_count', $analysis_result['structure_analysis'], 'Structure analysis should include image count');
    }
}