<?php

namespace Tests\Feature\Ai;

use App\Ai\Models\AgentConversation;
use App\Ai\Models\AgentMessage;
use App\Ai\Services\AgentRegistry;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentConversationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registry_run_persists_conversation_and_messages_for_mock(): void
    {
        config()->set('ai.agents.test-agent', ['driver' => 'mock']);

        $registry = app(AgentRegistry::class);

        $result = $registry->run(
            name: 'test-agent',
            prompt: 'Berechne 3 + 4.',
            context: ['shift_id' => 42],
        );

        $this->assertNotNull($result->conversationId);

        $conversation = AgentConversation::query()->findOrFail($result->conversationId);
        $this->assertSame('test-agent', $conversation->title);

        $messages = AgentMessage::query()
            ->where('conversation_id', $conversation->getKey())
            ->orderBy('created_at')
            ->get();

        $this->assertCount(2, $messages);

        $this->assertSame('user', $messages[0]->role);
        $this->assertSame('Berechne 3 + 4.', $messages[0]->content);

        $this->assertSame('assistant', $messages[1]->role);
        $this->assertStringContainsString($result->text, $messages[1]->content);
    }

    public function test_registry_run_persists_participant(): void
    {
        config()->set('ai.agents.test-agent', ['driver' => 'mock']);

        $employee = Employee::factory()->create();

        $result = app(AgentRegistry::class)->run(
            name: 'test-agent',
            prompt: 'Fasse zusammen.',
            participant: $employee,
        );

        $conversation = AgentConversation::query()->findOrFail($result->conversationId);

        $this->assertTrue($conversation->participant->is($employee));

        $this->assertDatabaseHas('agent_conversation_messages', [
            'conversation_id' => $conversation->getKey(),
            'participant_type' => $employee->getMorphClass(),
            'participant_id' => $employee->getKey(),
        ]);
    }

    public function test_registry_run_without_participant_keeps_columns_null(): void
    {
        config()->set('ai.agents.test-agent', ['driver' => 'mock']);

        $result = app(AgentRegistry::class)->run('test-agent', 'Hallo.');

        $conversation = AgentConversation::query()->findOrFail($result->conversationId);

        $this->assertNull($conversation->participant_type);
        $this->assertNull($conversation->participant_id);
    }

    public function test_models_use_configured_tables(): void
    {
        $this->assertSame('agent_conversations', (new AgentConversation)->getTable());
        $this->assertSame('agent_conversation_messages', (new AgentMessage)->getTable());
    }
}
