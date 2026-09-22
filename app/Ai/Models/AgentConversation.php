<?php

namespace App\Ai\Models;

use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Schlanker Wrapper auf der Laravel-AI-Konversationstabelle.
 *
 * Der Recorder (App\Ai\Services\ConversationRecorder) ist die einzige
 * Schreibstelle für Konversationen und Nachrichten.
 *
 * @property string $id
 * @property string|null $participant_type
 * @property int|null $participant_id
 * @property string $title
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[WithoutIncrementing]
class AgentConversation extends Model
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
     * Get the messages for the conversation.
     *
     * @return HasMany<AgentMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(AgentMessage::class, 'conversation_id');
    }

    /**
     * Get the participant that owns the conversation.
     *
     * @return MorphTo<Model, $this>
     */
    public function participant(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the table associated with the model.
     */
    #[\Override]
    public function getTable(): string
    {
        return config('ai.conversations.tables.conversations', 'agent_conversations');
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
        static::creating(function (AgentConversation $conversation): void {
            if ($conversation->getKey() === null) {
                $conversation->id = (string) Str::ulid();
            }
        });
    }
}
