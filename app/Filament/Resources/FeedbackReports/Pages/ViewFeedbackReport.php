<?php

namespace App\Filament\Resources\FeedbackReports\Pages;

use App\Filament\Resources\FeedbackReports\FeedbackReportResource;
use Filament\Resources\Pages\ViewRecord;

class ViewFeedbackReport extends ViewRecord
{
    protected static string $resource = FeedbackReportResource::class;

    protected function getHeaderActions(): array
    {
        // Statuswechsel läuft über die Tabellen-Actions (Triage-Workflow).
        return [];
    }
}
