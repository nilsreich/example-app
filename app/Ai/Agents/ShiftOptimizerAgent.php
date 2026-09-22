<?php

namespace App\Ai\Agents;

use App\Ai\Contracts\AiAgent;
use App\Ai\Data\AiResult;
use App\Ai\Enums\AiDriver;
use App\Data\ProposedMatch;
use App\Enums\PipelineDriver;
use App\Models\Setting;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Stringable;

/**
 * kiventro Schicht-Optimierer: Bewertet Ersatzkandidaten bei Personalausfall
 * und liefert strukturierte Vorschläge (JSON-Schema statt Freitext-Parsing).
 *
 * Implementiert bewusst den AiAgent-Vertrag der Software-Manufaktur (T9):
 * Im Mock-Modus bewertet der Agent deterministisch anhand der Dispositions-
 * Regeln, im Live-Modus läuft die Bewertung über den Laravel-AI-SDK-Provider
 * (strukturierte Antworten werden über das Promptable-Schema erzwungen).
 */
class ShiftOptimizerAgent implements Agent, AiAgent, HasStructuredOutput
{
    use Promptable;

    /**
     * @param  array<string, mixed>  $shift  Schicht-Snapshot (Payload-Struktur)
     * @param  list<array<string, mixed>>  $candidates  Ersatzkandidaten (Payload-Struktur)
     */
    public function __construct(
        public array $shift = [],
        public array $candidates = [],
        private readonly int $simulatedLatencyMs = 0,
        private readonly ?string $provider = null,
        private readonly ?string $model = null,
    ) {}

    public function instructions(): Stringable|string
    {
        return 'Du bist der Dispositions-Assistent der Software-Manufaktur kiventro. Bei kurzfristigem Personalausfall bewertest du Ersatzkandidaten für eine Schicht. Regeln: Nur aktive (verfügbare) Mitarbeiter vorschlagen. Fehlende Pflichtqualifikationen senken den Score stark. Ruhezeit unter 11 Stunden seit Schichtende ist ein Risiko (Arbeitszeitgesetz-Richtwert). Hohe Wochenüberstunden sind ein Trade-off. Antworte ausschließlich mit dem geforderten JSON-Schema auf Deutsch.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'matches' => $schema->array()->items(
                $schema->object(fn (JsonSchema $schema) => [
                    'employee_id' => $schema->integer()->required(),
                    'score' => $schema->integer()->min(0)->max(100)->required(),
                    'match_reasons' => $schema->array()->items($schema->string()),
                    'risks_or_tradeoffs' => $schema->array()->items($schema->string()),
                    'draft_message' => $schema->string(),
                ]),
            )->required(),
        ];
    }

    public function run(string $prompt, array $context = []): AiResult
    {
        $payload = $context['payload'] ?? ['shift' => $this->shift, 'candidates' => $this->candidates, 'required_qualifications' => []];
        $shift = $payload['shift'] ?? $this->shift;
        $candidates = $payload['candidates'] ?? $this->candidates;
        $required = $payload['required_qualifications'] ?? ($shift['required_qualifications'] ?? []);

        $driver = $context['driver'] ?? (Setting::aiPipelineDriver() === PipelineDriver::Live ? AiDriver::Live : AiDriver::Mock);

        return $driver === AiDriver::Live
            ? $this->runLive($prompt, $shift, $candidates)
            : $this->runMock($shift, $candidates, $required);
    }

    /**
     * Deterministische Mock-Bewertung: formelbasierte Punktvergabe durch
     * die kiventro Dispositionsregeln, ohne Netzwerk oder API-Keys.
     *
     * @param  array<string, mixed>  $shift
     * @param  list<array<string, mixed>>  $candidates
     * @param  list<string>  $required
     */
    private function runMock(array $shift, array $candidates, array $required): AiResult
    {
        if ($this->simulatedLatencyMs > 0) {
            usleep($this->simulatedLatencyMs * 1000);
        }

        $start = hrtime(true);
        $payload = [
            'shift' => $shift,
            'required_qualifications' => $required,
            'candidates' => $candidates,
        ];

        $matches = [];

        foreach ($candidates as $candidate) {
            $matches[] = $this->scoreCandidate($payload, $candidate);
        }

        usort($matches, fn (ProposedMatch $a, ProposedMatch $b) => $b->score <=> $a->score ?: $a->employeeId <=> $b->employeeId);

        $executionTimeMs = (int) ((hrtime(true) - $start) / 1_000_000);

        return new AiResult(
            agent: 'shift-optimizer',
            driver: AiDriver::Mock,
            text: sprintf('Deterministische Mock-Antwort: %d Top-Matches für die Schicht.', count($matches)),
            structured: ['matches' => array_map(fn (ProposedMatch $match) => $match->toArray(), $matches)],
            executionTimeMs: $executionTimeMs,
        );
    }

    /**
     * Live-Bewertung über den Laravel-AI-SDK-Provider (strukturierte Antwort).
     * Unbekannte Kandidaten-IDs werden verworfen (keine Phantom-Vorschläge).
     *
     * @param  array<string, mixed>  $shift
     * @param  list<array<string, mixed>>  $candidates
     */
    private function runLive(string $prompt, array $shift, array $candidates): AiResult
    {
        $start = hrtime(true);
        $response = $this->prompt($prompt, [], $this->provider, $this->model);

        $knownIds = array_map(fn (array $candidate) => $candidate['employee_id'], $candidates);

        // Strukturierte Antworten liefert nur die StructuredAgentResponse.
        $structured = $response instanceof StructuredAgentResponse ? $response->structured : null;

        /** @var list<array<string, mixed>> $rawMatches */
        $rawMatches = is_array($structured) ? ($structured['matches'] ?? []) : [];

        $matches = Collection::make($rawMatches)
            ->map(fn (array $match) => ProposedMatch::fromArray($match))
            ->filter(fn (ProposedMatch $match) => in_array($match->employeeId, $knownIds, true))
            ->sortByDesc(fn (ProposedMatch $match) => $match->score)
            ->values()
            ->all();

        $executionTimeMs = (int) ((hrtime(true) - $start) / 1_000_000);

        /** @var array{prompt_tokens: int, completion_tokens: int, cache_write_input_tokens: int, cache_read_input_tokens: int, reasoning_tokens: int} $usage */
        $usage = $response->usage->toArray();

        return new AiResult(
            agent: 'shift-optimizer',
            driver: AiDriver::Live,
            text: $response->text,
            structured: ['matches' => array_map(fn (ProposedMatch $match) => $match->toArray(), $matches)],
            usage: $usage,
            executionTimeMs: $executionTimeMs,
            conversationId: $response->conversationId,
            rawResponse: $structured,
        );
    }

    /**
     * Formelbasierte Kandidatenbewertung (Qualifikation 40, Ruhezeit 25,
     * Überstunden 20, Abteilung 15 – max. 100 Punkte).
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $candidate
     */
    private function scoreCandidate(array $payload, array $candidate): ProposedMatch
    {
        $required = $payload['required_qualifications'] ?? [];
        $has = $candidate['qualifications'] ?? [];
        $missing = array_values(array_diff($required, $has));

        $reasons = [];
        $tradeoffs = [];
        $score = 0;

        // (1) Pflichtqualifikationen (40 Punkte).
        if ($required === []) {
            $score += 40;
            $reasons[] = 'Keine Pflichtqualifikation gefordert – universell einsetzbar.';
        } else {
            $points = (int) round(40 * (count($required) - count($missing)) / count($required));
            $score += $points;

            if ($missing === []) {
                $reasons[] = sprintf('Verfügt über alle %d geforderten Qualifikationen (%s).', count($required), implode(', ', $required));
            } else {
                foreach ($missing as $skill) {
                    $tradeoffs[] = sprintf('Fehlende Qualifikation: %s (–%d Punkte).', $skill, (int) round(40 / count($required)));
                }
            }
        }

        // (2) Ruhezeit seit letzter Schicht (25 Punkte).
        $restHours = $candidate['rest_hours'] ?? null;

        if ($restHours === null) {
            $score += 15;
            $reasons[] = 'Keine Vor-Schicht bekannt – maximale Flexibilität.';
        } elseif ($restHours >= 11) {
            $score += 25;
            $reasons[] = sprintf('%s h Ruhezeit seit letzter Schicht (≥ 11 h Ideal).', number_format($restHours, 1, ',', '.'));
        } else {
            // 9–10,9 h: 15 Punkte, darunter: 5 Punkte – beide unter dem 11-h-Richtwert.
            $score += $restHours >= 9 ? 15 : 5;
            $tradeoffs[] = sprintf('Nur %s h Ruhezeit – unter 11-h-Richtwert.', number_format($restHours, 1, ',', '.'));
        }

        // (3) Wochenüberstunden (20 Punkte): wenig Überstunden = schneller einsetzbar.
        $overtime = (int) ($candidate['weekly_overtime_minutes'] ?? 0);

        if ($overtime <= 120) {
            $score += 20;
            $reasons[] = sprintf('Nur %s Wochenüberstunden.', $this->formatMinutes($overtime));
        } elseif ($overtime <= 300) {
            $score += 10;
            $tradeoffs[] = sprintf('Erhöht das Wochenüberstundenkonto (%s aktuell).', $this->formatMinutes($overtime));
        } else {
            $tradeoffs[] = sprintf('Erhöht das Wochenüberstundenkonto (%s aktuell).', $this->formatMinutes($overtime));
        }

        // (4) Abteilungs-Passung (15 Punkte).
        $shiftDepartment = $payload['shift']['department'] ?? null;

        if ($candidate['department'] === $shiftDepartment) {
            $score += 15;
            $reasons[] = sprintf('Gleiche Abteilung (%s) – keine Einarbeitung nötig.', $candidate['department']);
        } else {
            $tradeoffs[] = sprintf('Fremde Abteilung (%s statt %s) – kurze Einweisung einplanen.', $candidate['department'], $shiftDepartment ?? 'unbekannt');
        }

        return new ProposedMatch(
            employeeId: (int) $candidate['employee_id'],
            score: $score,
            matchReasons: $reasons,
            risksOrTradeoffs: $tradeoffs,
            draftMessage: $this->draftMessage($payload, $candidate),
        );
    }

    /**
     * Kurzer deutscher Ansprache-Text für den Kandidaten.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $candidate
     */
    private function draftMessage(array $payload, array $candidate): string
    {
        $shift = $payload['shift'];
        $start = new \DateTimeImmutable($shift['starts_at']);

        return sprintf(
            'Hallo %s, hier die Disposition (kiventro): Für die „%s“ am %s (%s Uhr, %s) suchen wir kurzfristig Ersatz. Passt es bei dir? Kurze Rückmeldung genügt – danke!',
            $candidate['name'],
            $shift['title'],
            $start->format('d.m.Y'),
            $start->format('H:i'),
            $shift['department'],
        );
    }

    private function formatMinutes(int $minutes): string
    {
        return $minutes < 60
            ? sprintf('%d Min.', $minutes)
            : number_format($minutes / 60, 1, ',', '.').' h';
    }
}
