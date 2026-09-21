<?php

namespace App\Pipelines;

use App\Contracts\ShiftOptimizerPipelineInterface;
use App\Data\OptimizationResult;
use App\Data\ProposedMatch;
use App\Enums\PipelineDriver;
use App\Models\Shift;
use App\Services\ShiftCandidateContextBuilder;

/**
 * Deterministischer Demo-Treiber ohne API-Key: Bewertet Kandidaten nach
 * transparenter, testbarer Formel (Qualifikation 40 + Ruhezeit 25 +
 * Überstunden 20 + Abteilung 15 = max. 100 Punkte).
 */
class MockDeterministicPipeline implements ShiftOptimizerPipelineInterface
{
    /**
     * @param  int  $simulatedLatencyMs  Simulierte Inferenz-Latenz (Tests: 0 übergeben).
     */
    public function __construct(
        private readonly ShiftCandidateContextBuilder $context,
        private readonly int $simulatedLatencyMs = 800,
    ) {}

    public function optimize(Shift $shift): OptimizationResult
    {
        $startedAt = hrtime(true);

        if ($this->simulatedLatencyMs > 0) {
            usleep($this->simulatedLatencyMs * 1000);
        }

        $payload = $this->context->for($shift->fresh() ?? $shift);
        $matches = [];

        foreach ($payload['candidates'] as $candidate) {
            $matches[] = $this->scoreCandidate($payload, $candidate);
        }

        // Deterministisch: Score absteigend, Name als Tie-Breaker.
        usort($matches, fn (ProposedMatch $a, ProposedMatch $b): int => $b->score <=> $a->score ?: $a->employeeId <=> $b->employeeId);

        $rawResponse = ['driver' => PipelineDriver::Mock->value, 'matches' => array_map(fn (ProposedMatch $match): array => $match->toArray(), $matches)];

        return new OptimizationResult(
            driver: PipelineDriver::Mock,
            matches: $matches,
            executionTimeMs: (int) ((hrtime(true) - $startedAt) / 1_000_000),
            tokensUsed: null,
            costEstimateEur: '0.000000',
            promptPayload: $payload,
            rawResponse: $rawResponse,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $candidate
     */
    private function scoreCandidate(array $payload, array $candidate): ProposedMatch
    {
        $required = $payload['required_qualifications'];
        $has = $candidate['qualifications'];
        $missing = array_values(array_diff($required, $has));
        $reasons = [];
        $tradeoffs = [];

        // 1) Qualifikation (Gewicht 40): Anteil erfüllter Pflicht-Skills.
        $qualificationScore = $required === []
            ? 40
            : (int) round(40 * (count($required) - count($missing)) / count($required));

        if ($missing === []) {
            $reasons[] = $required === []
                ? 'Keine Pflichtqualifikation gefordert – universell einsetzbar.'
                : 'Verfügt über alle '.count($required).' geforderten Qualifikationen ('.implode(', ', $required).').';
        } else {
            $tradeoffs[] = 'Fehlende Qualifikation: '.implode(', ', $missing).' (–'.(40 - $qualificationScore).' Punkte).';
        }

        // 2) Ruhezeit (Gewicht 25): 11 h als Richtwert (Arbeitszeitgesetz).
        $restHours = $candidate['rest_hours'];
        $restScore = match (true) {
            $restHours === null => 15,
            $restHours >= 11 => 25,
            $restHours >= 9 => 15,
            default => 5,
        };

        if ($restHours === null) {
            $reasons[] = 'Keine Vor-Schicht bekannt – maximale Flexibilität.';
        } elseif ($restHours >= 11) {
            $reasons[] = number_format($restHours, 1, ',', '.').' h Ruhezeit seit letzter Schicht (≥ 11 h Ideal).';
        } else {
            $tradeoffs[] = 'Nur '.number_format($restHours, 1, ',', '.').' h Ruhezeit – unter 11-h-Richtwert.';
        }

        // 3) Überstunden (Gewicht 20): niedriges Konto bevorzugen.
        $overtime = $candidate['weekly_overtime_minutes'];
        $overtimeScore = match (true) {
            $overtime <= 120 => 20,
            $overtime <= 300 => 10,
            default => 0,
        };

        if ($overtime <= 120) {
            $reasons[] = 'Nur '.$this->formatMinutes($overtime).' Wochenüberstunden.';
        } else {
            $tradeoffs[] = 'Erhöht das Wochenüberstundenkonto ('.$this->formatMinutes($overtime).' aktuell).';
        }

        // 4) Abteilung (Gewicht 15): eingearbeitetes Umfeld schlägt Einarbeitung.
        $departmentScore = $candidate['department'] === $payload['shift']['department'] ? 15 : 0;

        if ($departmentScore > 0) {
            $reasons[] = 'Gleiche Abteilung ('.$candidate['department'].') – keine Einarbeitung nötig.';
        } else {
            $tradeoffs[] = 'Fremde Abteilung ('.$candidate['department'].' statt '.$payload['shift']['department'].') – kurze Einweisung einplanen.';
        }

        return new ProposedMatch(
            employeeId: $candidate['employee_id'],
            score: $qualificationScore + $restScore + $overtimeScore + $departmentScore,
            matchReasons: $reasons,
            risksOrTradeoffs: $tradeoffs,
            draftMessage: $this->draftMessage($payload, $candidate),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $candidate
     */
    private function draftMessage(array $payload, array $candidate): string
    {
        $shift = $payload['shift'];
        $start = new \DateTimeImmutable($shift['starts_at']);

        return 'Hallo '.$candidate['name'].', hier die Disposition (kiventro): Für die „'.$shift['title']
            .'“ am '.$start->format('d.m.Y').' ('.$start->format('H:i').' Uhr, '.$shift['department']
            .') suchen wir kurzfristig Ersatz. Passt es bei dir? Kurze Rückmeldung genügt – danke!';
    }

    private function formatMinutes(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes.' Min.';
        }

        return number_format($minutes / 60, 1, ',', '.').' h';
    }
}
