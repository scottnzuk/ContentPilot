<?php
/**
 * PHPUnit tests for AIGenerationService
 *
 * Tests the AI content generation functionality including
 * content generation, model selection, and integration with
 * other services.
 *
 * @package AI_Auto_News_Poster\Tests\Microservices
 */

require_once __DIR__ . '/MicroservicesTestBase.php';

class AIGenerationServiceTest extends MicroservicesTestBase
{
    /** @var AANP_AIGenerationService */
    private $aiGenerationService;

    /**
     * Set up before each test
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->aiGenerationService = new AANP_AIGenerationService($this->cacheManager, $this->connectionPoolManager);
    }

    /**
     * Test content generation
     */
    public function testContentGeneration()
    {
        $this->logInfo('Testing Content generation');

        $source_content = array(
            'title' => 'Test Source',
            'content' => 'This is test source content for AI generation.'
        );

        $generation_result = $this->aiGenerationService->generate_content($source_content, array(
            'word_count' => 'medium',
            'tone' => 'professional'
        ), 'openai');

        $this->assertIsArray($generation_result, 'Content generation should return an array');
        $this->assertArrayHasKey('success', $generation_result, 'Result should have success key');
        $this->assertArrayHasKey('content', $generation_result, 'Result should have generated content');
        $this->assertTrue($generation_result['success'], 'Content generation should succeed');
    }

    /**
     * Test AI service health check
     */
    public function testAiServiceHealthCheck()
    {
        $this->logInfo('Testing AI service health check');

        $health = $this->aiGenerationService->health_check();
        $this->assertIsBool($health, 'Health check should return a boolean');
        $this->assertTrue($health, 'AI service should be healthy');
    }

    /**
     * Test model selection
     */
    public function testModelSelection()
    {
        $this->logInfo('Testing Model selection');

        $source_content = array(
            'title' => 'Test Source',
            'content' => 'This is test source content.'
        );

        // Test with different models
        $openai_result = $this->aiGenerationService->generate_content($source_content, array(
            'word_count' => 'short',
            'tone' => 'professional'
        ), 'openai');

        $anthropic_result = $this->aiGenerationService->generate_content($source_content, array(
            'word_count' => 'short',
            'tone' => 'professional'
        ), 'anthropic');

        $this->assertTrue($openai_result['success'], 'OpenAI model should generate content successfully');
        $this->assertTrue($anthropic_result['success'], 'Anthropic model should generate content successfully');
    }

    /**
     * Test content formatting
     */
    public function testContentFormatting()
    {
        $this->logInfo('Testing Content formatting');

        $source_content = array(
            'title' => 'Test Source',
            'content' => 'This is test source content.'
        );

        $generation_result = $this->aiGenerationService->generate_content($source_content, array(
            'word_count' => 'medium',
            'tone' => 'professional',
            'format' => 'html'
        ));

        $this->assertStringContainsString('<p>', $generation_result['content'], 'Generated content should be in HTML format');
        $this->assertStringContainsString('</p>', $generation_result['content'], 'Generated content should be in HTML format');
    }

    /**
     * Test content customization
     */
    public function testContentCustomization()
    {
        $this->logInfo('Testing Content customization');

        $source_content = array(
            'title' => 'Test Source',
            'content' => 'This is test source content.'
        );

        // Test with different tones
        $professional_result = $this->aiGenerationService->generate_content($source_content, array(
            'word_count' => 'short',
            'tone' => 'professional'
        ));

        $casual_result = $this->aiGenerationService->generate_content($source_content, array(
            'word_count' => 'short',
            'tone' => 'casual'
        ));

        $this->assertNotEquals($professional_result['content'], $casual_result['content'], 'Different tones should produce different content');
    }

    /**
     * Test content length control
     */
    public function testContentLengthControl()
    {
        $this->logInfo('Testing Content length control');

        $source_content = array(
            'title' => 'Test Source',
            'content' => 'This is test source content for length testing.'
        );

        // Test with different word counts
        $short_result = $this->aiGenerationService->generate_content($source_content, array(
            'word_count' => 'short',
            'tone' => 'professional'
        ));

        $medium_result = $this->aiGenerationService->generate_content($source_content, array(
            'word_count' => 'medium',
            'tone' => 'professional'
        ));

        $long_result = $this->aiGenerationService->generate_content($source_content, array(
            'word_count' => 'long',
            'tone' => 'professional'
        ));

        $short_length = str_word_count(strip_tags($short_result['content']));
        $medium_length = str_word_count(strip_tags($medium_result['content']));
        $long_length = str_word_count(strip_tags($long_result['content']));

        $this->assertLessThan($medium_length, $short_length, 'Short content should be shorter than medium content');
        $this->assertLessThan($long_length, $medium_length, 'Medium content should be shorter than long content');
    }

    /**
     * Test error handling
     */
    public function testErrorHandling()
    {
        $this->logInfo('Testing Error handling');

        // Test with invalid parameters
        $result = $this->aiGenerationService->generate_content(array(), array(), 'invalid_model');

        $this->assertIsArray($result, 'Error handling should return an array');
        $this->assertFalse($result['success'], 'Invalid parameters should cause generation to fail');
        $this->assertArrayHasKey('error', $result, 'Result should have error information');
    }

    /**
     * Test content caching
     */
    public function testContentCaching()
    {
        $this->logInfo('Testing Content caching');

        $source_content = array(
            'title' => 'Test Source',
            'content' => 'This is test source content for caching.'
        );

        // Generate content (should be cached)
        $first_result = $this->aiGenerationService->generate_content($source_content, array(
            'word_count' => 'short',
            'tone' => 'professional'
        ));

        // Generate same content again (should come from cache)
        $second_result = $this->aiGenerationService->generate_content($source_content, array(
            'word_count' => 'short',
            'tone' => 'professional'
        ));

        $this->assertEquals($first_result['content'], $second_result['content'], 'Cached content should be identical');
    }

    /**
     * Test content validation
     */
    public function testContentValidation()
    {
        $this->logInfo('Testing Content validation');

        $source_content = array(
            'title' => 'Test Source',
            'content' => 'This is test source content.'
        );

        $generation_result = $this->aiGenerationService->generate_content($source_content, array(
            'word_count' => 'short',
            'tone' => 'professional'
        ));

        $this->assertArrayHasKey('validation', $generation_result, 'Result should have validation information');
        $this->assertIsArray($generation_result['validation'], 'Validation should be an array');
        $this->assertArrayHasKey('quality_score', $generation_result['validation'], 'Validation should include quality score');
        $this->assertArrayHasKey('relevance_score', $generation_result['validation'], 'Validation should include relevance score');
    }
}