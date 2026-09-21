<?php

namespace App\Data;

/**
 * Ein einzelner Kandidatenvorschlag (treiberunabhängig normalisiert).
 */
final readonly class ProposedMatch
{
    /**
     * @param  array<int, string>  $matchReasons
     * @param  array<int, string>  $risksOrTradeoffs
     */
    public function __construct(
        public int $employeeId,
        public int $score,
        public array $matchReasons = [],
        public array $risksOrTradeoffs = [],
        public ?string $draftMessage = null,
    ) {}

    /**
     * Baut einen Match aus Treiber-Rohdaten (Mock-Fixture oder AI-Structured-Output).
     * Unvollständige Einträge werden defensiv normalisiert statt zu werfen.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            employeeId: (int) ($data['employee_id'] ?? 0),
            // Konfidenz immer im gültigen 0-100-Korridor halten.
            score: max(0, min(100, (int) ($data['score'] ?? 0))),
            matchReasons: array_values(array_filter(array_map(strval(...), (array) ($data['match_reasons'] ?? [])))),
            risksOrTradeoffs: array_values(array_filter(array_map(strval(...), (array) ($data['risks_or_tradeoffs'] ?? [])))),
            draftMessage: isset($data['draft_message']) && $data['draft_message'] !== '' ? (string) $data['draft_message'] : null,
        );
    }

    /**
     * @return array{employee_id: int, score: int, match_reasons: array<int, string>, risks_or_tradeoffs: array<int, string>, draft_message: string|null}
     */
    public function toArray(): array
    {
        return [
            'employee_id' => $this->employeeId,
            'score' => $this->score,
            'match_reasons' => $this->matchReasons,
            'risks_or_tradeoffs' => $this->risksOrTradeoffs,
            'draft_message' => $this->draftMessage,
        ];
    }
}
