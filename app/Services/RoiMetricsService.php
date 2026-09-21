<?php

namespace App\Services;

use App\Enums\AuditEventType;
use App\Enums\FeedbackRating;
use App\Enums\ShiftStatus;
use App\Models\Shift;
use App\Models\ShiftAuditEvent;
use App\Models\ShiftFeedback;
use App\Models\ShiftProposal;

/**
 * Kennzahlen-Ebene für ROI-Dashboard: Aggregiert DB-Fakten und delegiert
 * die reine Arithmetik an RoiCalculatorService (unit-testbar).
 *
 * "Gelöster Konflikt" = initiale Zuweisung (Append im Forward-Ledger).
 * "Ohne Nacharbeit übernommen" = besetzte Schicht, deren jüngstes
 * Top-Match dem Zugewiesenen entspricht UND kein Negativ-Feedback trägt.
 */
class RoiMetricsService
{
    public function __construct(
        private readonly RoiCalculatorService $calculator,
    ) {}

    public function resolvedConflicts(): int
    {
        return ShiftAuditEvent::where('event_type', AuditEventType::InitialAssignment)->count();
    }

    public function savedCostsEur(): float
    {
        return $this->calculator->savedCostsEur($this->resolvedConflicts());
    }

    public function automationRate(): ?float
    {
        $adopted = 0;
        $total = 0;

        Shift::where('status', ShiftStatus::Assigned)
            ->with('optimizations.proposals.feedbacks')
            ->chunk(100, function ($shifts) use (&$adopted, &$total): void {
                foreach ($shifts as $shift) {
                    $latest = $shift->optimizations->first();

                    if (! $latest || $latest->proposals->isEmpty()) {
                        continue;
                    }

                    $total++;
                    $top = $latest->proposals->first();
                    $hasNegativeFeedback = $top->feedbacks->contains(
                        fn (ShiftFeedback $feedback): bool => $feedback->rating === FeedbackRating::Negative,
                    );

                    if ($top->employee_id === $shift->assigned_employee_id && ! $hasNegativeFeedback) {
                        $adopted++;
                    }
                }
            });

        return $this->calculator->automationRate($adopted, $total);
    }

    public function averageConfidence(): ?float
    {
        return $this->calculator->averageConfidence(ShiftProposal::pluck('score')->all());
    }

    /**
     * Eingesparte Kosten je Tag (letzte N Tage, inkl. Null-Tage für Charts).
     *
     * @return array{labels: list<string>, costs: list<float>}
     */
    public function savingsPerDay(int $days = 14): array
    {
        $since = now()->subDays($days - 1)->startOfDay();

        $perDay = ShiftAuditEvent::where('event_type', AuditEventType::InitialAssignment)
            ->where('created_at', '>=', $since)
            ->selectRaw('date(created_at) as day, count(*) as conflicts')
            ->groupBy('day')
            ->pluck('conflicts', 'day')
            ->all();

        $labels = [];
        $costs = [];

        for ($i = 0; $i < $days; $i++) {
            $date = $since->copy()->addDays($i);
            $labels[] = $date->format('d.m.');
            $costs[] = $this->calculator->savedCostsEur((int) ($perDay[$date->format('Y-m-d')] ?? 0));
        }

        return ['labels' => $labels, 'costs' => $costs];
    }

    /**
     * @return array{labels: list<string>, data: list<int>}
     */
    public function shiftStatusDistribution(): array
    {
        $counts = Shift::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->all();

        return [
            'labels' => [ShiftStatus::Open->label(), ShiftStatus::Assigned->label(), ShiftStatus::Cancelled->label()],
            'data' => [
                (int) ($counts[ShiftStatus::Open->value] ?? 0),
                (int) ($counts[ShiftStatus::Assigned->value] ?? 0),
                (int) ($counts[ShiftStatus::Cancelled->value] ?? 0),
            ],
        ];
    }

    /**
     * @return array{labels: list<string>, data: list<int>}
     */
    public function feedbackDistribution(): array
    {
        $counts = ShiftFeedback::selectRaw('rating, count(*) as total')->groupBy('rating')->pluck('total', 'rating')->all();

        return [
            'labels' => [FeedbackRating::Positive->label(), FeedbackRating::Negative->label()],
            'data' => [
                (int) ($counts[FeedbackRating::Positive->value] ?? 0),
                (int) ($counts[FeedbackRating::Negative->value] ?? 0),
            ],
        ];
    }
}
