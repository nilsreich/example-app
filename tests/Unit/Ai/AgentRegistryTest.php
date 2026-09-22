<?php

namespace Tests\Unit\Ai;

use App\Ai\Contracts\AiAgent;
use App\Ai\Data\AiResult;
use App\Ai\Enums\AiDriver;
use App\Ai\Pipelines\LaravelAiSdkPipeline;
use App\Ai\Pipelines\MockDeterministicPipeline;
use App\Ai\Services\AgentRegistry;
use App\Ai\Services\ConversationRecorder;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Der AgentRegistry löst Agenten rein über die Konfiguration (config/ai.php)
 * auf. Kein Test benötigt API-Keys oder einen laufenden Provider.
 */
class AgentRegistryTest extends TestCase
{
    /**
     * Frische Registry-Instanz je Test (der Container liefert ein Singleton,
     * dessen Resolve-Cache sonst über Tests hinweg bestehen bliebe).
     */
    private function registry(): AgentRegistry
    {
        return new AgentRegistry(app(ConversationRecorder::class));
    }

    public function test_registry_resolves_mock_agent_by_default(): void
    {
        config(['ai.agents.example' => ['driver' => 'mock']]);

        $agent = $this->registry()->agent('example');

        $this->assertInstanceOf(MockDeterministicPipeline::class, $agent);
    }

    public function test_registry_resolves_sdk_pipeline_when_configured(): void
    {
        config(['ai.agents.example' => [
            'driver' => 'laravel-ai',
            'instructions' => 'Du bist ein hilfreicher Assistent.',
        ]]);

        $agent = $this->registry()->agent('example');

        $this->assertInstanceOf(LaravelAiSdkPipeline::class, $agent);
    }

    public function test_registry_falls_back_to_global_default_driver(): void
    {
        config(['ai.agent_driver' => 'laravel-ai']);
        config(['ai.agents.example' => []]);

        $agent = $this->registry()->agent('example');

        $this->assertInstanceOf(LaravelAiSdkPipeline::class, $agent);
    }

    public function test_registry_throws_for_unknown_agent(): void
    {
        config(['ai.agents' => []]);

        $this->expectException(InvalidArgumentException::class);

        $this->registry()->agent('gibt-es-nicht');
    }

    public function test_registry_caches_resolved_agent_instances(): void
    {
        config(['ai.agents.example' => ['driver' => 'mock']]);

        $registry = $this->registry();
        $first = $registry->agent('example');
        $second = $registry->agent('example');

        $this->assertSame($first, $second);
    }

    public function test_registry_passes_provider_and_model_to_class_based_agent(): void
    {
        config(['ai.agents.configurable' => [
            'class' => ConfigurableTestAgent::class,
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
        ]]);

        $agent = $this->registry()->agent('configurable');

        $this->assertInstanceOf(ConfigurableTestAgent::class, $agent);
        $this->assertSame('openai', $agent->provider);
        $this->assertSame('gpt-4o-mini', $agent->model);
    }
}

/**
 * Test-Double: nimmt provider/model als Konstruktorparameter an.
 */
final class ConfigurableTestAgent implements AiAgent
{
    public function __construct(
        public ?string $provider = null,
        public ?string $model = null,
    ) {}

    public function run(string $prompt, array $context = []): AiResult
    {
        return new AiResult(agent: 'configurable', driver: AiDriver::Mock, text: 'ok');
    }
}
