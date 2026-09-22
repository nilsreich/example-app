<?php

namespace Tests\Feature\Feedback;

use App\Feedback\Livewire\FeedbackSettingsForm;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FeedbackSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_is_disabled_by_default(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(FeedbackSettingsForm::class)
            ->assertSet('enabled', false);

        $this->assertFalse(Setting::feedbackWidgetEnabled());
    }

    public function test_enabling_and_disabling_persists_setting(): void
    {
        $this->actingAs(User::factory()->create());

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

    public function test_save_is_rejected_for_non_admins(): void
    {
        $this->actingAs(User::factory()->bereichsleiter('Logistik')->create());

        Livewire::test(FeedbackSettingsForm::class)
            ->set('enabled', true)
            ->call('save')
            ->assertStatus(403);

        $this->assertFalse(Setting::feedbackWidgetEnabled());
    }
}
