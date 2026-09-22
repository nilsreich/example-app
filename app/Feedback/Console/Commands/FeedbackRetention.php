<?php

namespace App\Feedback\Console\Commands;

use App\Feedback\Models\FeedbackReport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Aufbewahrungslauf (DSGVO): Entfernt Feedback-Meldungen samt privater
 * Screenshots, deren Aufbewahrungsfrist (config/feedback.php → retention_months)
 * abgelaufen ist. Aufruf von Hand oder über den Scheduler.
 */
#[Signature('feedback:retention')]
#[Description('Löscht Feedback-Meldungen älter als die Aufbewahrungsfrist (samt Screenshots) – DSGVO')]
class FeedbackRetention extends Command
{
    public function handle(): int
    {
        $cutoff = Carbon::now()->subMonths((int) config('feedback.retention_months', 12));

        $count = 0;

        FeedbackReport::where('created_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(100, function ($reports) use (&$count): void {
                foreach ($reports as $report) {
                    $report->purge();
                    $count++;
                }
            });

        $this->info(sprintf('%d Feedback-Meldung(en) nach Ablauf der Aufbewahrungsfrist entfernt.', $count));

        return self::SUCCESS;
    }
}
