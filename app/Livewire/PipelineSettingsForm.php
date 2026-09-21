<?php

namespace App\Livewire;

use App\Enums\PipelineDriver;
use App\Filament\Pages\PipelineSettings;
use App\Models\Setting;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Umschalter zwischen Deterministic Mock und Live AI SDK.
 * Schreibt Setting::AI_PIPELINE_MODE (Default: mock).
 */
class PipelineSettingsForm extends Component
{
    public string $mode = 'mock';

    public ?string $notice = null;

    public function mount(): void
    {
        $this->mode = Setting::aiPipelineDriver()->value;
    }

    public function save(): void
    {
        $this->validate(['mode' => ['required', 'in:mock,live']]);

        Setting::set(Setting::AI_PIPELINE_MODE, $this->mode);

        $this->notice = 'Pipeline-Modus gespeichert: '.PipelineDriver::from($this->mode)->label().'.';
    }

    public function render(): View
    {
        return view('livewire.pipeline-settings-form', [
            'options' => PipelineSettings::getDriverOptions(),
        ]);
    }
}
