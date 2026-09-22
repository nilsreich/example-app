<?php

namespace App\Providers;

use App\Feedback\Console\Commands\FeedbackRetention;
use App\Feedback\Livewire\FeedbackSettingsForm;
use App\Feedback\Models\FeedbackReport;
use App\Feedback\Policies\FeedbackReportPolicy;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * Bindet das feedback-Modul in die Anwendung ein:
 * - explizite Policy-Registrierung (Modul-Entity, kein Konventions-Pfad),
 * - Blade-Component <livewire:feedback-settings-form /> ans Modul,
 * - Aufbewahrungs-Command (feedback:retention),
 * - Floating-Widget im Filament-Panel (Render-Hook statt Demo-Provider).
 */
class FeedbackServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->commands([
            FeedbackRetention::class,
        ]);

        Gate::policy(FeedbackReport::class, FeedbackReportPolicy::class);

        // Blade-Component <livewire:feedback-settings-form /> ans Modul binden.
        Livewire::component('feedback-settings-form', FeedbackSettingsForm::class);

        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            fn (): string => Blade::render('<x-feedback-widget />'),
        );
    }
}
