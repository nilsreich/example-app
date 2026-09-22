<?php

namespace Tests;

use App\Audit\Support\AuditGuards;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    /**
     * Führt eine gezielte Ledger-Manipulation aus, die die DB-Trigger umgehen
     * müsste. Die Append-only-Trigger sind dafür nur in diesem Callback deaktiviert
     * (Test-Setup), danach wieder aktiv. Die Transaktion des Tests macht die
     * Änderung ohnehin rückgängig.
     */
    protected function withoutAuditGuards(callable $callback): mixed
    {
        AuditGuards::disable();

        try {
            return $callback();
        } finally {
            AuditGuards::enable();
        }
    }
}
