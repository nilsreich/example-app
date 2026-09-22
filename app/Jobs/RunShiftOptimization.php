<?php

namespace App\Jobs;

use App\Models\Shift;
use App\Services\ShiftOptimizationRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Führt einen Live-Pipeline-Lauf asynchron in der Queue aus.
 *
 * Der Provider-Aufruf (Laravel-AI-SDK) kann mehrere Sekunden dauern; er darf
 * den HTTP-/Livewire-Request nicht blockieren. Der Mock-Treiber läuft
 * weiterhin synchron (deterministisch, ohne Netzwerk) – siehe
 * ShiftOptimizationRunner::runOrQueue().
 */
final class RunShiftOptimization implements ShouldQueue
{
    use Queueable;

    /** Anzahl Versuche bei transienten Provider-Fehlern. */
    public int $tries = 3;

    /** Harte Obergrenze je Versuch (Sekunden). */
    public int $timeout = 120;

    public function __construct(public readonly int $shiftId) {}

    public function handle(ShiftOptimizationRunner $runner): void
    {
        $shift = Shift::find($this->shiftId);

        if ($shift === null) {
            return;
        }

        $runner->run($shift);
    }

    /**
     * Endgültig fehlgeschlagener Lauf: sichtbar protokollieren, damit der
     * Disponent nicht auf ein Ergebnis wartet, das nie kommt.
     */
    public function failed(?Throwable $exception): void
    {
        logger()->error('Shift-Optimierungs-Lauf fehlgeschlagen.', [
            'shift_id' => $this->shiftId,
            'exception' => $exception?->getMessage(),
        ]);
    }
}
