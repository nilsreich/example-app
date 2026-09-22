<?php

namespace App\Ai\Models;

use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Schlanker Wrapper auf der Laravel-AI-Nachrichtentabelle.
 *
 * Dient als Persistenzschicht für AiAgent-Läufe: user-Message (Prompt)
 * und assistant-Message (Antwort) je Konversation. Agent-Name, Provider,
 * Request/Response, Token-Verbrauch und weitere Metadaten werden in den
 * vorhandenen Spalten agent, role, content, usage und meta abgelegt —
 * die Tabelle bleibt unverändert (keine Schemamigration nötig).
 *
 * @property string $id
 * @property string $conversation_id
 * @property string|null $participant_type
 * @property int|null $participant_id
 * @property string $agent
 * @property string $role
 * @property string $content
 * @property array<int, mixed> $attachments
 * @property array<int, mixed> $tool_calls
 * @property array<int, mixed> $tool_results
 * @property array<string, mixed> $usage
 * @property array<string, mixed> $meta
 * @property array<string, mixed>|null $approval_state
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[WithoutIncrementing]
class AgentMessage extends Model
{
    /**
     * The data type of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'attachments' => 'array',
        'tool_calls' => 'array',
        'tool_results' => 'array',
        'usage' => 'array',
        'meta' => 'array',
        'approval_state' => 'array',
    ];

    /**
     * Get the conversation that owns the message.
     *
     * @return BelongsTo<AgentConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AgentConversation::class, 'conversation_id');
    }

    /**
     * Get the table associated with the model.
     */
    #[\Override]
    public function getTable(): string
    {
        return config('ai.conversations.tables.messages', 'agent_conversation_messages');
    }

    /**
     * Get the database connection for the model.
     */
    #[\Override]
    public function getConnectionName(): ?string
    {
        return config('ai.conversations.connection');
    }

    protected static function booted(): void
    {
        static::creating(function (AgentMessage $message): void {
            if ($message->getKey() === null) {
                $message->id = (string) Str::ulid();
            }
        });
    }
}
