<?php

namespace Tests\Feature;

use App\Enums\PipelineDriver;
use App\Livewire\PipelineSettingsForm;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PipelineSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_mode_is_mock(): void
    {
        Livewire::test(PipelineSettingsForm::class)
            ->assertSet('mode', 'mock');

        $this->assertSame(PipelineDriver::Mock, Setting::aiPipelineDriver());
    }

    public function test_saving_live_mode_switches_pipeline(): void
    {
        Livewire::test(PipelineSettingsForm::class)
            ->set('mode', 'live')
            ->call('save')
            ->assertSee('Live AI SDK');

        $this->assertSame('live', Setting::get(Setting::AI_PIPELINE_MODE));
        $this->assertSame(PipelineDriver::Live, Setting::aiPipelineDriver());
    }

    public function test_invalid_mode_is_rejected(): void
    {
        Livewire::test(PipelineSettingsForm::class)
            ->set('mode', 'skynet')
            ->call('save')
            ->assertHasErrors(['mode']);

        $this->assertNull(Setting::get(Setting::AI_PIPELINE_MODE));
    }
}
