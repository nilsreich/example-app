<?php

namespace App\Ai\Services;

use App\Ai\Models\AgentConversation;
use App\Ai\Models\AgentMessage;
use Illuminate\Database\Eloquent\Model;

/**
 * Persistiert AiAgent-Konversationen auf den Laravel-AI-Tabellen.
 *
 * Einzige Schreibstelle für Konversationen und Nachrichten des ai-Moduls.
 * Bewusst unabhängig vom SDK-Fake-Pfad: Der Laravel-AI-Fake persistiert
 * keine Konversationen, daher läuft die Aufzeichnung immer (Mock & Live).
 */
final class ConversationRecorder
{
    public function start(string $title, ?Model $participant = null): AgentConversation
    {
        $conversation = new AgentConversation;
        $conversation->title = $title;

        if ($participant !== null) {
            $conversation->participant()->associate($participant);
        }

        $conversation->save();

        return $conversation;
    }

    /**
     * Zeichnet eine Nachricht auf. Participant-Referenz und der Agent-Name
     * werden von der Konversation bzw. dem Aufrufer übernommen; die
     * JSON-Spalten der Tabelle sind NOT NULL und werden immer gesetzt.
     *
     * @param  array<string, mixed>  $meta
     * @param  array<string, int>|null  $usage
     */
    public function recordMessage(
        AgentConversation $conversation,
        string $role,
        string $content,
        ?string $agent = null,
        array $meta = [],
        ?array $usage = null,
    ): AgentMessage {
        $message = new AgentMessage;
        $message->conversation_id = $conversation->getKey();
        $message->participant_type = $conversation->participant_type;
        $message->participant_id = $conversation->participant_id;
        $message->role = $role;
        $message->content = $content;
        $message->agent = $agent;
        $message->attachments = [];
        $message->tool_calls = [];
        $message->tool_results = [];
        $message->usage = $usage ?? [];
        $message->meta = $meta;
        $message->approval_state = null;
        $message->save();

        return $message;
    }
}
