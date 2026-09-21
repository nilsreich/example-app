<?php

namespace App\Filament\Widgets;

use App\Enums\FeedbackStatus;
use App\Enums\PipelineDriver;
use App\Enums\ShiftStatus;
use App\Filament\Pages\FeedbackSettings;
use App\Filament\Pages\MyShifts;
use App\Filament\Pages\PipelineSettings;
use App\Filament\Resources\FeedbackReports\FeedbackReportResource;
use App\Filament\Resources\Shifts\ShiftResource;
use App\Models\FeedbackReport;
use App\Models\Setting;
use App\Models\Shift;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Artisan;

/**
 * Rollen-Leitfaden (adaptives Dashboard): Jede Rolle sieht zuerst, wo sie ist,
 * was sie hier tut und welcher Klick zum Ziel führt.
 */
class RoleGuideWidget extends Widget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.role-guide';

    /**
     * Demo-Reset direkt vom Dashboard (nur Web-Admin).
     */
    public function resetDemo(): void
    {
        abort_unless(auth()->user()?->role->managesSettings(), 403);

        Artisan::call('db:seed-kiventro-demo --with-history');

        Notification::make()
            ->title('Demo-Szenario neu geladen')
            ->body(trim(Artisan::output()))
            ->success()
            ->send();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $user = auth()->user();
        $departments = $user?->visibleDepartments();

        $openShifts = Shift::where('status', ShiftStatus::Open)
            ->when($departments !== null, fn ($query) => $query->whereIn('department', $departments))
            ->count();

        return [
            'role' => $user?->role,
            'department' => $user?->department,
            'openShifts' => $openShifts,
            'newFeedback' => $user?->role->managesSettings()
                ? FeedbackReport::where('status', FeedbackStatus::New)->count()
                : 0,
            'pipelineMode' => Setting::aiPipelineDriver(),
            'feedbackEnabled' => Setting::feedbackWidgetEnabled(),
            'shiftsUrl' => ShiftResource::getUrl(panel: 'admin'),
            'myShiftsUrl' => MyShifts::getUrl(panel: 'admin'),
            'feedbackUrl' => FeedbackReportResource::getUrl(panel: 'admin'),
            'pipelineUrl' => PipelineSettings::getUrl(panel: 'admin'),
            'feedbackToggleUrl' => FeedbackSettings::getUrl(panel: 'admin'),
            'pipelineLabel' => Setting::aiPipelineDriver() === PipelineDriver::Live ? 'Live AI SDK' : 'Deterministic Mock',
        ];
    }
}
