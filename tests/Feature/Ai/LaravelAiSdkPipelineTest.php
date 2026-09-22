<?php

namespace Tests\Feature\Ai;

use App\Ai\Data\AiResult;
use App\Ai\Enums\AiDriver;
use App\Ai\Pipelines\LaravelAiSdkPipeline;
use Laravel\Ai\AnonymousAgent;
use Tests\TestCase;

/**
 * Die Laravel-AI-SDK-Pipeline wird ausschließlich gegen den Fake-Gateway
 * getestet (Laravel\Ai\AnonymousAgent::fake([...])). Es werden niemals
 * echte Provider-Aufrufe oder API-Keys benötigt.
 */
class LaravelAiSdkPipelineTest extends TestCase
{
    public function test_sdk_pipeline_maps_text_response_to_ai_result(): void
    {
        AnonymousAgent::fake(['Deterministische Fake-Antwort.']);

        $pipeline = new LaravelAiSdkPipeline(
            agent: new AnonymousAgent('Du bist ein hilfreicher Assistent.', [], []),
        );
        $result = $pipeline->run('Was ist 2+2?', []);

        $this->assertInstanceOf(AiResult::class, $result);
        $this->assertSame('Deterministische Fake-Antwort.', $result->text);
        $this->assertSame(AiDriver::Live, $result->driver);
        $this->assertNull($result->structured);
        $this->assertIsArray($result->usage);
        $this->assertArrayHasKey('prompt_tokens', $result->usage);
        $this->assertArrayHasKey('completion_tokens', $result->usage);
        $this->assertGreaterThanOrEqual(0, $result->executionTimeMs);

        AnonymousAgent::assertPrompted(
            fn ($prompt): bool => str_contains($prompt->prompt, '2+2')
        );
    }

    public function test_sdk_pipeline_maps_structured_response(): void
    {
        AnonymousAgent::fake([['match' => 'ja', 'score' => 42]]);

        $pipeline = new LaravelAiSdkPipeline(
            agent: new AnonymousAgent('Antworte strukturiert.', [], []),
        );
        $result = $pipeline->run('Soll ich zustimmen?', []);

        $this->assertSame(['match' => 'ja', 'score' => 42], $result->structured);
        $this->assertSame(AiDriver::Live, $result->driver);

        AnonymousAgent::assertPrompted(
            fn ($prompt): bool => str_contains($prompt->prompt, 'zustimmen')
        );
    }
}
