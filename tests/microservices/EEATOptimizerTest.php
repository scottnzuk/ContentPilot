<?php
/**
 * PHPUnit tests for EEATOptimizer
 *
 * Tests the EEAT (Expertise, Authoritativeness, Trustworthiness) optimization
 * functionality for content.
 *
 * @package AI_Auto_News_Poster\Tests\Microservices
 */

require_once __DIR__ . '/MicroservicesTestBase.php';

class EEATOptimizerTest extends MicroservicesTestBase
{
    /** @var AANP_EEATOptimizer */
    private $eeatOptimizer;

    /** @var AANP_ContentAnalyzer */
    private $contentAnalyzer;

    /**
     * Set up before each test
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->contentAnalyzer = new AANP_ContentAnalyzer();
        $this->eeatOptimizer = new AANP_EEATOptimizer($this->contentAnalyzer);
    }

    /**
     * Test EEAT optimization
     */
    public function testEeatOptimization()
    {
        $this->logInfo('Testing EEAT optimization');

        $test_content = $this->createTestContent();

        $optimization_result = $this->eeatOptimizer->optimize_for_eeat($test_content, array(
            'optimization_level' => 'basic',
            'user_id' => 1
        ));

        $this->assertIsArray($optimization_result, 'EEAT optimization should return an array');
        $this->assertArrayHasKey('success', $optimization_result, 'Result should have success key');
        $this->assertArrayHasKey('optimized_content', $optimization_result, 'Result should have optimized content');
        $this->assertArrayHasKey('improvement_score', $optimization_result, 'Result should have improvement score');
        $this->assertTrue($optimization_result['success'], 'EEAT optimization should succeed');
    }

    /**
     * Test optimization with different levels
     */
    public function testOptimizationLevels()
    {
        $this->logInfo('Testing Optimization with different levels');

        $test_content = $this->createTestContent();

        // Test basic optimization
        $basic_result = $this->eeatOptimizer->optimize_for_eeat($test_content, array(
            'optimization_level' => 'basic',
            'user_id' => 1
        ));

        // Test advanced optimization
        $advanced_result = $this->eeatOptimizer->optimize_for_eeat($test_content, array(
            'optimization_level' => 'advanced',
            'user_id' => 1
        ));

        $this->assertGreaterThanOrEqual($basic_result['improvement_score'], $advanced_result['improvement_score'], 'Advanced optimization should have higher or equal improvement score');
    }

    /**
     * Test EEAT health check
     */
    public function testEeatHealthCheck()
    {
        $this->logInfo('Testing EEAT health check');

        $health = $this->eeatOptimizer->health_check();
        $this->assertIsBool($health, 'Health check should return a boolean');
        $this->assertTrue($health, 'EEAT optimizer should be healthy');
    }

    /**
     * Test content improvement scoring
     */
    public function testContentImprovementScoring()
    {
        $this->logInfo('Testing Content improvement scoring');

        $test_content = $this->createTestContent();

        $optimization_result = $this->eeatOptimizer->optimize_for_eeat($test_content, array(
            'optimization_level' => 'basic',
            'user_id' => 1
        ));

        $this->assertArrayHasKey('improvement_score', $optimization_result, 'Result should have improvement score');
        $this->assertIsNumeric($optimization_result['improvement_score'], 'Improvement score should be numeric');
        $this->assertGreaterThanOrEqual(0, $optimization_result['improvement_score'], 'Improvement score should be >= 0');
        $this->assertLessThanOrEqual(100, $optimization_result['improvement_score'], 'Improvement score should be <= 100');
    }

    /**
     * Test EEAT component scoring
     */
    public function testEeatComponentScoring()
    {
        $this->logInfo('Testing EEAT component scoring');

        $test_content = $this->createTestContent();

        $optimization_result = $this->eeatOptimizer->optimize_for_eeat($test_content, array(
            'optimization_level' => 'basic',
            'user_id' => 1
        ));

        $this->assertArrayHasKey('eeat_scores', $optimization_result, 'Result should have EEAT scores');
        $this->assertIsArray($optimization_result['eeat_scores'], 'EEAT scores should be an array');

        $eeat_scores = $optimization_result['eeat_scores'];
        $this->assertArrayHasKey('expertise', $eeat_scores, 'EEAT scores should include expertise');
        $this->assertArrayHasKey('authoritativeness', $eeat_scores, 'EEAT scores should include authoritativeness');
        $this->assertArrayHasKey('trustworthiness', $eeat_scores, 'EEAT scores should include trustworthiness');
    }

    /**
     * Test content with different EEAT characteristics
     */
    public function testContentWithDifferentEeatCharacteristics()
    {
        $this->logInfo('Testing Content with different EEAT characteristics');

        // Test content with low EEAT characteristics
        $low_eeat_content = array(
            'title' => 'Random Article',
            'content' => '<p>This is some random content without any expertise indicators.</p>',
            'meta_description' => 'Random content description',
            'author_id' => 0
        );

        // Test content with high EEAT characteristics
        $high_eeat_content = array(
            'title' => 'Expert Analysis: Advanced SEO Techniques',
            'content' => '<p>This article provides expert analysis on advanced SEO techniques based on 10 years of experience in the field. The author is a certified SEO professional with multiple industry awards.</p>
                          <p>Key points include:</p>
                          <ul>
                            <li>Technical SEO best practices</li>
                            <li>Content optimization strategies</li>
                            <li>EEAT compliance guidelines</li>
                          </ul>
                          <p>References:</p>
                          <ul>
                            <li>Google Search Quality Guidelines</li>
                            <li>SEO industry research papers</li>
                          </ul>',
            'meta_description' => 'Expert analysis on advanced SEO techniques by certified professional',
            'author_id' => 1,
            'author_expertise' => 'SEO Specialist, 10 years experience'
        );

        $low_eeat_result = $this->eeatOptimizer->optimize_for_eeat($low_eeat_content, array(
            'optimization_level' => 'basic',
            'user_id' => 1
        ));

        $high_eeat_result = $this->eeatOptimizer->optimize_for_eeat($high_eeat_content, array(
            'optimization_level' => 'basic',
            'user_id' => 1
        ));

        $this->assertLessThan($high_eeat_result['improvement_score'], $low_eeat_result['improvement_score'], 'Low EEAT content should have higher improvement potential');
    }

    /**
     * Test author expertise integration
     */
    public function testAuthorExpertiseIntegration()
    {
        $this->logInfo('Testing Author expertise integration');

        $test_content = $this->createTestContent();
        $test_content['author_id'] = 1;
        $test_content['author_expertise'] = 'SEO Specialist';

        $optimization_result = $this->eeatOptimizer->optimize_for_eeat($test_content, array(
            'optimization_level' => 'basic',
            'user_id' => 1
        ));

        $this->assertArrayHasKey('optimized_content', $optimization_result, 'Result should have optimized content');
        $this->assertStringContainsString('SEO Specialist', $optimization_result['optimized_content']['content'], 'Optimized content should include author expertise');
    }

    /**
     * Test content validation
     */
    public function testContentValidation()
    {
        $this->logInfo('Testing Content validation');

        // Test with invalid content
        $invalid_content = array(
            'title' => '',
            'content' => '',
            'meta_description' => ''
        );

        $result = $this->eeatOptimizer->optimize_for_eeat($invalid_content, array(
            'optimization_level' => 'basic',
            'user_id' => 1
        ));

        $this->assertFalse($result['success'], 'Optimization of invalid content should fail');
        $this->assertArrayHasKey('error', $result, 'Result should have error information');
    }

    /**
     * Test optimization suggestions
     */
    public function testOptimizationSuggestions()
    {
        $this->logInfo('Testing Optimization suggestions');

        $test_content = $this->createTestContent();

        $optimization_result = $this->eeatOptimizer->optimize_for_eeat($test_content, array(
            'optimization_level' => 'basic',
            'user_id' => 1
        ));

        $this->assertArrayHasKey('suggestions', $optimization_result, 'Result should have suggestions');
        $this->assertIsArray($optimization_result['suggestions'], 'Suggestions should be an array');

        if (!empty($optimization_result['suggestions'])) {
            $this->assertArrayHasKey('type', $optimization_result['suggestions'][0], 'Suggestion should have type');
            $this->assertArrayHasKey('message', $optimization_result['suggestions'][0], 'Suggestion should have message');
            $this->assertArrayHasKey('severity', $optimization_result['suggestions'][0], 'Suggestion should have severity');
        }
    }
}