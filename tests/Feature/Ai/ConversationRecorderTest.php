<?php

namespace Tests\Feature\Ai;

use App\Ai\Models\AgentConversation;
use App\Ai\Models\AgentMessage;
use App\Ai\Services\ConversationRecorder;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationRecorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_creates_conversation_with_title(): void
    {
        $recorder = app(ConversationRecorder::class);

        $conversation = $recorder->start('disposition-agent');

        $this->assertInstanceOf(AgentConversation::class, $conversation);
        $this->assertSame('disposition-agent', $conversation->title);
        $this->assertNotNull($conversation->getKey());
        $this->assertDatabaseHas('agent_conversations', ['id' => $conversation->getKey()]);
    }

    public function test_start_accepts_participant(): void
    {
        $employee = Employee::factory()->create();

        $conversation = app(ConversationRecorder::class)->start('disposition-agent', $employee);

        $this->assertTrue($conversation->participant->is($employee));
    }

    public function test_record_message_persists_role_content_and_meta(): void
    {
        $recorder = app(ConversationRecorder::class);

        $conversation = $recorder->start('disposition-agent');

        $userMessage = $recorder->recordMessage(
            conversation: $conversation,
            role: 'user',
            content: 'Welche Schicht ist zu besetzen?',
            agent: 'disposition-agent',
            meta: ['shift_id' => 7],
        );

        $assistantMessage = $recorder->recordMessage(
            conversation: $conversation,
            role: 'assistant',
            content: 'Die Frühschicht.',
            agent: 'disposition-agent',
            meta: ['driver' => 'mock'],
            usage: ['prompt_tokens' => 12, 'completion_tokens' => 4],
        );

        $this->assertSame('user', $userMessage->role);
        $this->assertSame('disposition-agent', $userMessage->agent);
        $this->assertSame(['shift_id' => 7], $userMessage->meta);
        $this->assertSame([], $userMessage->usage);

        $this->assertSame('assistant', $assistantMessage->role);
        $this->assertSame('Die Frühschicht.', $assistantMessage->content);
        $this->assertSame(['driver' => 'mock'], $assistantMessage->meta);
        $this->assertSame(['prompt_tokens' => 12, 'completion_tokens' => 4], $assistantMessage->usage);

        $this->assertSame(2, AgentMessage::query()->where('conversation_id', $conversation->getKey())->count());
        $this->assertTrue($userMessage->conversation->is($conversation));
        $this->assertTrue($assistantMessage->conversation->is($conversation));
    }

    public function test_record_message_generates_unique_ids(): void
    {
        $recorder = app(ConversationRecorder::class);
        $conversation = $recorder->start('disposition-agent');

        $first = $recorder->recordMessage($conversation, 'user', 'Eins', agent: 'a');
        $second = $recorder->recordMessage($conversation, 'user', 'Zwei', agent: 'a');

        $this->assertNotSame($first->getKey(), $second->getKey());
    }
}
