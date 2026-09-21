<?php

namespace Tests\Feature;

use App\Livewire\FeedbackSettingsForm;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FeedbackSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_is_disabled_by_default(): void
    {
        Livewire::test(FeedbackSettingsForm::class)
            ->assertSet('enabled', false);

        $this->assertFalse(Setting::feedbackWidgetEnabled());
    }

    public function test_enabling_and_disabling_persists_setting(): void
    {
        Livewire::test(FeedbackSettingsForm::class)
            ->set('enabled', true)
            ->call('save')
            ->assertSee('aktiviert');

        $this->assertTrue(Setting::feedbackWidgetEnabled());

        Livewire::test(FeedbackSettingsForm::class)
            ->set('enabled', false)
            ->call('save')
            ->assertSee('deaktiviert');

        $this->assertFalse(Setting::feedbackWidgetEnabled());
    }
}
