<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * kiventro Schicht-Optimierer: Bewertet Ersatzkandidaten bei Personalausfall
 * und liefert Matches mit Score, Begründung, Trade-offs und Nachrichtentext
 * als strukturiertes JSON (kein Freitext-Parsing nötig).
 */
class ShiftOptimizerAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * @param  array<string, mixed>  $shift  Schicht-Snapshot (siehe Shift::snapshot()).
     * @param  list<array<string, mixed>>  $candidates  Kandidaten-Kontext (siehe ShiftCandidateContextBuilder).
     */
    public function __construct(
        public array $shift,
        public array $candidates,
    ) {}

    public function instructions(): Stringable|string
    {
        return 'Du bist der Dispositions-Assistent der Software-Manufaktur kiventro. '
            .'Bei kurzfristigem Personalausfall bewertest du Ersatzkandidaten für eine Schicht. '
            .'Regeln: Nur aktive (verfügbare) Mitarbeiter vorschlagen. Fehlende Pflichtqualifikationen senken den Score stark. '
            .'Ruhezeit unter 11 Stunden seit Schichtende ist ein Risiko (Arbeitszeitgesetz-Richtwert). '
            .'Hohe Wochenüberstunden sind ein Trade-off. Antworte ausschließlich mit dem geforderten JSON-Schema auf Deutsch.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'matches' => $schema->array()->items(
                $schema->object(fn (JsonSchema $schema): array => [
                    'employee_id' => $schema->integer()->required(),
                    'score' => $schema->integer()->min(0)->max(100)->required(),
                    'match_reasons' => $schema->array()->items($schema->string()),
                    'risks_or_tradeoffs' => $schema->array()->items($schema->string()),
                    'draft_message' => $schema->string(),
                ])
            )->required(),
        ];
    }
}
