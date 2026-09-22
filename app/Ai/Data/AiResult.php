<?php

namespace App\Ai\Data;

use App\Ai\Enums\AiDriver;

/**
 * Ergebnis eines AI-Agenten-Laufs.
 *
 * Unveränderliches Value Object. "usage" folgt dem Format der
 * Laravel-AI-SDK (prompt_tokens, completion_tokens, ...). "structured"
 * enthält die strukturierte Antwort, sofern der Treiber eine liefert.
 */
final readonly class AiResult
{
    /**
     * @param  array<string, mixed>|null  $structured
     * @param  array{prompt_tokens: int, completion_tokens: int, cache_write_input_tokens: int, cache_read_input_tokens: int, reasoning_tokens: int}|null  $usage
     * @param  array<string, mixed>|null  $rawResponse
     */
    public function __construct(
        public string $agent,
        public AiDriver $driver,
        public string $text,
        public ?array $structured = null,
        public ?array $usage = null,
        public int $executionTimeMs = 0,
        public ?string $conversationId = null,
        public ?array $rawResponse = null,
    ) {}

    /**
     * Rekonstruiert das Ergebnis aus einem gespeicherten raw_response-Array
     * (T14-Ruling: conversation_id und Antworttext liegen dort). Einzige Quelle
     * für ShiftDispatch und ShiftProposalAcceptService – vermeidet Drift.
     *
     * @param  array<string, mixed>|null  $raw
     */
    public static function fromRawResponse(?array $raw): self
    {
        $raw ??= [];
        $conversationId = $raw['conversation_id'] ?? null;

        return new self(
            agent: (string) ($raw['agent'] ?? 'shift-optimizer'),
            driver: AiDriver::tryFrom((string) ($raw['driver'] ?? 'mock')) ?? AiDriver::Mock,
            text: (string) ($raw['text'] ?? ''),
            conversationId: is_string($conversationId) ? $conversationId : null,
            rawResponse: $raw,
        );
    }

    /**
     * Liefert eine Kopie mit gesetzter Konversations-ID (immutable).
     */
    public function withConversationId(string $conversationId): self
    {
        return new self(
            agent: $this->agent,
            driver: $this->driver,
            text: $this->text,
            structured: $this->structured,
            usage: $this->usage,
            executionTimeMs: $this->executionTimeMs,
            conversationId: $conversationId,
            rawResponse: $this->rawResponse,
        );
    }
}
