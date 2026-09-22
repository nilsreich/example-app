<?php

namespace Tests\Feature\Audit;

use App\Audit\AuditLedger;
use App\Audit\Enums\AuditEventType;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AuditLogPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_admin_can_view_audit_log(): void
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

        $this->get('/admin/audit-log')
            ->assertOk()
            ->assertSee('Audit-Protokoll')
            ->assertSee('Angelegt');
    }

    public function test_employee_role_cannot_view_audit_log(): void
    {
        $user = User::factory()->nutzer()->create();
        $this->actingAs($user);

        $this->get('/admin/audit-log')->assertForbidden();
    }
}
