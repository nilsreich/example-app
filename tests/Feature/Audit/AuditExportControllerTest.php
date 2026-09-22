<?php

namespace Tests\Feature\Audit;

use App\Audit\AuditLedger;
use App\Audit\Enums\AuditEventType;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AuditExportControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/audit/export')->assertRedirect('/login');
    }

    public function test_employee_role_is_forbidden(): void
    {
        $user = User::factory()->nutzer()->create();
        $this->actingAs($user);

        $this->get('/audit/export')->assertForbidden();
    }

    public function test_web_admin_can_download_csv(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        app(AuditLedger::class)->record(
            eventType: AuditEventType::Created,
            previousState: [],
            newState: ['name' => 'Mia'],
            actor: $user,
            auditable: Employee::factory()->create(),
        );

        $response = $this->get('/audit/export');

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader('Content-Disposition', 'attachment; filename=audit-export.csv')
            ->assertStreamed();

        $this->assertStringContainsString('event_type', $response->streamedContent());
    }

    public function test_management_role_can_download_json(): void
    {
        $user = User::factory()->create(['role' => 'geschaeftsfuehrer']);
        $this->actingAs($user);

        $response = $this->get('/audit/export?format=json');

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertStreamed();

        $this->assertStringContainsString('"events":', $response->streamedContent());
    }

    public function test_unknown_format_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get('/audit/export?format=xml')->assertInvalid(['format']);
    }

    public function test_filter_parameters_are_applied(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get('/audit/export?event_type=created')->assertOk();
        $this->get('/audit/export?from=2026-01-01')->assertOk();
        $this->get('/audit/export?actor='.$user->id)->assertOk();
    }

    public function test_export_is_recorded_in_the_ledger(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get('/audit/export')->assertOk();

        $this->assertDatabaseHas('audit_events', [
            'event_type' => AuditEventType::Exported->value,
            'actor_user_id' => $user->id,
        ]);
    }
}
