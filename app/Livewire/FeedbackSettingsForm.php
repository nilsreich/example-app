<?php

namespace App\Livewire;

use App\Models\Setting;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Toggle für das In-App-Feedback-Widget (Setting: feedback_widget_enabled).
 * Default: aus – Feature wird bewusst per Opt-in aktiviert.
 */
class FeedbackSettingsForm extends Component
{
    public bool $enabled = false;

    public ?string $notice = null;

    public function mount(): void
    {
        $this->enabled = Setting::feedbackWidgetEnabled();
    }

    public function save(): void
    {
        Setting::set(Setting::FEEDBACK_WIDGET_ENABLED, $this->enabled ? '1' : '0');

        $this->notice = $this->enabled
            ? 'In-App-Feedback ist aktiviert – das Widget erscheint für angemeldete Nutzer.'
            : 'In-App-Feedback ist deaktiviert.';
    }

    public function render(): View
    {
        return view('livewire.feedback-settings-form');
    }
}
