<?php

namespace Tests\Feature\Feedback;

use App\Feedback\Enums\FeedbackCategory;
use App\Feedback\Enums\FeedbackStatus;
use App\Feedback\Models\FeedbackReport;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FeedbackReportTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'category' => FeedbackCategory::Bug->value,
            'message' => 'Der Rollback-Button verschwindet nach dem Speichern.',
            'page_url' => 'http://localhost/admin/shifts',
            'page_title' => 'Schichten',
            'element_selector' => 'button.fi-btn',
            'element_text' => 'Zuweisung zurückrollen',
            'browser_info' => ['viewport' => '1440×900'],
        ], $overrides);
    }

    public function test_guests_cannot_submit_feedback(): void
    {
        $this->postJson(route('feedback.store'), $this->validPayload())
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_submit_feedback(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('feedback.store'), $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('ok', true);

        $report = FeedbackReport::firstOrFail();

        $this->assertSame($user->id, $report->user_id);
        $this->assertSame(FeedbackCategory::Bug, $report->category);
        $this->assertSame(FeedbackStatus::New, $report->status);
        $this->assertSame('button.fi-btn', $report->element_selector);
        $this->assertFalse($report->hasScreenshot());
    }

    public function test_feedback_requires_message_and_valid_category(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('feedback.store'), $this->validPayload(['message' => 'ab']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('message');

        $this->postJson(route('feedback.store'), $this->validPayload(['category' => 'panic']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category');

        $this->assertSame(0, FeedbackReport::count());
    }

    public function test_png_screenshot_is_stored_on_private_disk(): void
    {
        Storage::fake('local');

        $png = base64_encode(UploadedFile::fake()->image('shot.png', 60, 40)->getContent());

        $this->actingAs(User::factory()->create())
            ->postJson(route('feedback.store'), $this->validPayload([
                'screenshot' => 'data:image/png;base64,'.$png,
            ]))
            ->assertCreated();

        $report = FeedbackReport::firstOrFail();

        $this->assertNotNull($report->screenshot_path);
        $this->assertStringStartsWith('feedback/', $report->screenshot_path);
        $this->assertStringEndsWith('.png', $report->screenshot_path);
        Storage::disk('local')->assertExists($report->screenshot_path);
    }

    public function test_fake_or_oversized_screenshot_is_rejected(): void
    {
        Storage::fake('local');

        $this->actingAs(User::factory()->create())
            ->postJson(route('feedback.store'), $this->validPayload([
                'screenshot' => 'data:image/png;base64,'.base64_encode('definitiv kein bild'),
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('screenshot');

        $this->postJson(route('feedback.store'), $this->validPayload([
            // Gültiges PNG, aber über dem 2,5-MB-Limit.
            'screenshot' => 'data:image/png;base64,'.base64_encode(random_bytes(2_600_000)),
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('screenshot');

        $this->assertSame(0, FeedbackReport::count());
    }

    public function test_screenshot_route_is_guarded_by_policy(): void
    {
        Storage::fake('local');

        $png = UploadedFile::fake()->image('shot.png', 60, 40)->getContent();
        Storage::disk('local')->put('feedback/test.png', $png);

        $report = FeedbackReport::create([
            'user_id' => User::factory()->create()->id,
            'category' => FeedbackCategory::Other,
            'message' => 'Mit Screenshot',
            'page_url' => '/',
            'screenshot_path' => 'feedback/test.png',
        ]);

        // Gast → Login-Redirect.
        $this->get(route('feedback.screenshot', $report))->assertRedirect(route('login'));

        // Bereichsleiter darf ins Panel, aber nicht in die Feedback-Triage → 403.
        $this->actingAs(User::factory()->bereichsleiter('Logistik')->create())
            ->get(route('feedback.screenshot', $report))
            ->assertForbidden();

        // Web-Admin → Bild wird ausgeliefert.
        $this->actingAs(User::factory()->create())
            ->get(route('feedback.screenshot', $report))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_widget_markup_follows_global_toggle(): void
    {
        $this->actingAs(User::factory()->create());

        // Default: aus.
        $this->get(route('dashboard'))->assertOk()->assertDontSee('data-feedback-widget', escape: false);

        Setting::set(Setting::FEEDBACK_WIDGET_ENABLED, '1');

        $this->get(route('dashboard'))->assertOk()->assertSee('data-feedback-widget', escape: false);
    }

    public function test_widget_styles_expose_focus_visible_state(): void
    {
        $this->actingAs(User::factory()->create());

        Setting::set(Setting::FEEDBACK_WIDGET_ENABLED, '1');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('.fw-submit:focus-visible', escape: false);
    }

    public function test_widget_is_injected_into_the_admin_panel_too(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin')->assertOk()->assertDontSee('data-feedback-widget', escape: false);

        Setting::set(Setting::FEEDBACK_WIDGET_ENABLED, '1');

        $this->get('/admin')->assertOk()->assertSee('data-feedback-widget', escape: false);
    }

    public function test_module_can_be_disabled_via_config(): void
    {
        config(['feedback.enabled' => false]);

        $user = User::factory()->create();
        $this->actingAs($user);

        // Widget bleibt ausgeblendet, selbst wenn das Setting aktiv wäre.
        Setting::set(Setting::FEEDBACK_WIDGET_ENABLED, '1');
        $this->get(route('dashboard'))->assertOk()->assertDontSee('data-feedback-widget', escape: false);

        // Triage ist für den Web-Admin trotz Berechtigung nicht erreichbar.
        $this->get('/admin/feedback-reports')->assertForbidden();
    }

    public function test_page_url_must_be_a_valid_http_url(): void
    {
        $this->actingAs(User::factory()->create());

        // javascript:-URLs (stored-XSS-/Phishing-Vektor) werden abgelehnt.
        $this->postJson(route('feedback.store'), $this->validPayload(['page_url' => 'javascript:alert(1)']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('page_url');

        // Andere Schemen ebenfalls.
        $this->postJson(route('feedback.store'), $this->validPayload(['page_url' => 'ftp://example.test/x']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('page_url');

        $this->assertSame(0, FeedbackReport::count());
    }

    public function test_store_is_rejected_when_module_is_disabled(): void
    {
        config(['feedback.enabled' => false]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('feedback.store'), $this->validPayload())
            ->assertNotFound();

        $this->assertSame(0, FeedbackReport::count());
    }

    public function test_category_list_is_configurable(): void
    {
        config(['feedback.categories' => ['bug']]);

        $user = User::factory()->create();
        $this->actingAs($user);

        // 'idea' ist aus der Config geflogen → Validierungsfehler.
        $this->postJson(route('feedback.store'), $this->validPayload(['category' => 'idea']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category');

        // 'bug' bleibt erlaubt.
        $this->postJson(route('feedback.store'), $this->validPayload(['category' => 'bug']))
            ->assertCreated();

        $this->assertSame(1, FeedbackReport::count());
    }
}
