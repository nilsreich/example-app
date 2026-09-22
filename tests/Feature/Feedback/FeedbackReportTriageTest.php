<?php

namespace Tests\Feature\Feedback;

use App\Audit\Enums\AuditEventType;
use App\Audit\Models\AuditEvent;
use App\Feedback\Enums\FeedbackCategory;
use App\Feedback\Enums\FeedbackStatus;
use App\Feedback\Filament\Resources\FeedbackReports\Pages\ListFeedbackReports;
use App\Feedback\Filament\Resources\FeedbackReports\Pages\ViewFeedbackReport;
use App\Feedback\Models\FeedbackReport;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * T8: Triage-Löschfunktion (DSGVO) + Aufbewahrungsfrist + Statusänderungen im Audit.
 */
class FeedbackReportTriageTest extends TestCase
{
    use RefreshDatabase;

    private function report(array $attributes = []): FeedbackReport
    {
        return FeedbackReport::create(array_merge([
            'user_id' => User::factory()->create()->id,
            'category' => FeedbackCategory::Bug,
            'message' => 'Eine Testmeldung.',
            'page_url' => '/dashboard',
        ], $attributes));
    }

    private function reportWithScreenshot(): array
    {
        Storage::fake('local');
        $png = UploadedFile::fake()->image('shot.png', 60, 40)->getContent();
        Storage::disk('local')->put('feedback/keep.png', $png);

        $report = $this->report(['screenshot_path' => 'feedback/keep.png']);

        return [$report, 'feedback/keep.png'];
    }

    public function test_transition_to_in_progress_is_audited(): void
    {
        $admin = User::factory()->create();
        $report = $this->report();

        $report->transitionTo(FeedbackStatus::InProgress, actor: $admin);

        $this->assertSame(FeedbackStatus::InProgress, $report->fresh()->status);

        $event = AuditEvent::where('event_type', AuditEventType::StatusChanged->value)
            ->where('auditable_id', $report->id)
            ->sole();

        $this->assertSame(FeedbackReport::class, $event->auditable_type);
        $this->assertSame($admin->id, $event->actor_user_id);
        $this->assertSame(['status' => FeedbackStatus::New->value, 'resolution_note' => null], $event->previous_state);
        $this->assertSame(['status' => FeedbackStatus::InProgress->value, 'resolution_note' => null], $event->new_state);
    }

    public function test_transition_to_resolved_stores_note_and_audits_previous_step(): void
    {
        $admin = User::factory()->create();
        $report = $this->report();

        $report->transitionTo(FeedbackStatus::InProgress, actor: $admin);
        $report->transitionTo(FeedbackStatus::Resolved, 'Absturz behoben – Cache invalidieren', actor: $admin);

        $fresh = $report->fresh();
        $this->assertSame(FeedbackStatus::Resolved, $fresh->status);
        $this->assertSame('Absturz behoben – Cache invalidieren', $fresh->resolution_note);

        $lastEvent = AuditEvent::where('event_type', AuditEventType::StatusChanged->value)
            ->where('auditable_id', $report->id)
            ->orderByDesc('version')
            ->first();

        $this->assertSame(FeedbackStatus::InProgress->value, $lastEvent->previous_state['status']);
        $this->assertSame(FeedbackStatus::Resolved->value, $lastEvent->new_state['status']);
        $this->assertSame('Absturz behoben – Cache invalidieren', $lastEvent->new_state['resolution_note']);
    }

    public function test_transition_without_actual_change_is_not_audited(): void
    {
        $admin = User::factory()->create();
        $report = $this->report();

        $report->transitionTo(FeedbackStatus::InProgress, actor: $admin);
        $report->transitionTo(FeedbackStatus::InProgress, actor: $admin);

        $this->assertSame(1, AuditEvent::where('event_type', AuditEventType::StatusChanged->value)->count());
    }

    public function test_purge_removes_report_and_screenshot(): void
    {
        Storage::fake('local');
        [$report, $path] = $this->reportWithScreenshot();

        $this->assertTrue($report->purge());

        $this->assertDatabaseMissing('feedback_reports', ['id' => $report->id]);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_deleting_report_also_removes_screenshot_file(): void
    {
        Storage::fake('local');
        [$report, $path] = $this->reportWithScreenshot();

        $report->delete();

        $this->assertDatabaseMissing('feedback_reports', ['id' => $report->id]);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_retention_command_removes_only_expired_reports(): void
    {
        Storage::fake('local');
        [$report, $path] = $this->reportWithScreenshot();

        $expired = $this->report(['screenshot_path' => 'feedback/expired.png']);
        Storage::disk('local')->put('feedback/expired.png', 'x');

        $expired->forceFill(['created_at' => Carbon::now()->subMonths(13)])->save();

        $this->artisan('feedback:retention')->assertSuccessful();

        $this->assertDatabaseMissing('feedback_reports', ['id' => $expired->id]);
        Storage::disk('local')->assertMissing('feedback/expired.png');

        // Frische Meldung bleibt samt Screenshot erhalten.
        $this->assertDatabaseHas('feedback_reports', ['id' => $report->id]);
        Storage::disk('local')->assertExists($path);
    }

    public function test_page_url_column_does_not_render_non_http_links(): void
    {
        $admin = User::factory()->create();
        $report = $this->report(['page_url' => 'javascript:alert(document.cookie)']);

        $this->actingAs($admin);

        Livewire::test(ListFeedbackReports::class)
            ->assertOk()
            ->assertSee($report->message)
            ->assertDontSee('href="javascript:', escape: false);
    }

    public function test_page_url_infolist_does_not_render_non_http_links(): void
    {
        $admin = User::factory()->create();
        $report = $this->report(['page_url' => 'javascript:alert(document.cookie)']);

        $this->actingAs($admin);

        Livewire::test(ViewFeedbackReport::class, ['record' => $report->getRouteKey()])
            ->assertOk()
            ->assertDontSee('href="javascript:', escape: false);
    }

    public function test_resolve_table_action_routes_through_transition_to(): void
    {
        $admin = User::factory()->create();
        $report = $this->report();

        $this->actingAs($admin);

        Livewire::test(ListFeedbackReports::class)
            ->callTableAction('resolve', $report, data: ['resolution_note' => 'Fertig über UI'])
            ->assertHasNoTableActionErrors();

        $this->assertSame(FeedbackStatus::Resolved, $report->fresh()->status);
        $this->assertSame('Fertig über UI', $report->fresh()->resolution_note);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::StatusChanged->value,
            'auditable_id' => $report->id,
            'actor_user_id' => $admin->id,
        ]);
    }
}
