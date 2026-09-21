<?php

namespace App\Contracts;

use App\Data\OptimizationResult;
use App\Models\Shift;

/**
 * Strategiegrenze des Dispatching: Beide Treiber (Mock + Live AI SDK)
 * liefern dasselbe Ergebnisformat und sind dadurch austauschbar.
 */
interface ShiftOptimizerPipelineInterface
{
    /**
     * Berechnet Ersatzkandidaten für eine (typischerweise offene) Schicht.
     */
    public function optimize(Shift $shift): OptimizationResult;
}
