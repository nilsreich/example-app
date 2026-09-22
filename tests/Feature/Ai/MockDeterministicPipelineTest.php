<?php

namespace Tests\Feature\Ai;

use App\Ai\Data\AiResult;
use App\Ai\Enums\AiDriver;
use App\Ai\Pipelines\MockDeterministicPipeline;
use Tests\TestCase;

/**
 * Die Mock-Pipeline ist der standardmäßige, deterministische Fake-Agent.
 * Sie benötigt keinerlei API-Keys oder Netzwerkzugriff.
 */
class MockDeterministicPipelineTest extends TestCase
{
    public function test_mock_agent_is_deterministic(): void
    {
        $pipeline = new MockDeterministicPipeline;

        $first = $pipeline->run('Wie plane ich eine Schicht?', ['shift' => 'A']);
        $second = $pipeline->run('Wie plane ich eine Schicht?', ['shift' => 'A']);

        $this->assertInstanceOf(AiResult::class, $first);
        $this->assertSame($first->text, $second->text);
        $this->assertSame($first->structured, $second->structured);
        $this->assertSame($first->executionTimeMs, $second->executionTimeMs);
    }

    public function test_mock_agent_echoes_prompt_and_context(): void
    {
        $result = (new MockDeterministicPipeline)->run('Frage an den Agenten', ['key' => 'value']);

        $this->assertSame('Frage an den Agenten', $result->structured['prompt']);
        $this->assertSame(['key' => 'value'], $result->structured['context']);
        $this->assertTrue($result->structured['simulated']);
        $this->assertStringContainsString('Frage an den Agenten', $result->text);
    }

    public function test_mock_agent_reports_mock_driver_and_usage(): void
    {
        $result = (new MockDeterministicPipeline)->run('Test', []);

        $this->assertSame(AiDriver::Mock, $result->driver);
        $this->assertNull($result->usage);
        $this->assertNull($result->conversationId);
        $this->assertGreaterThanOrEqual(0, $result->executionTimeMs);
    }

    public function test_mock_agent_deterministic_with_different_inputs(): void
    {
        $pipeline = new MockDeterministicPipeline;

        $result = $pipeline->run('Anderer Prompt', ['andere' => 'daten']);

        $this->assertSame('Anderer Prompt', $result->structured['prompt']);
        $this->assertSame(['andere' => 'daten'], $result->structured['context']);
    }
}
