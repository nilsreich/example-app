<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use App\Services\RoiMetricsService;
use Illuminate\Contracts\View\View;

/**
 * B2E-Einstiegsseite außerhalb des Admin-Panels: zeigt dieselben kiventro
 * ROI-Kennzahlen wie das Filament-Dashboard und verlinkt ins Dispatching.
 */
class DashboardController extends Controller
{
    public function __invoke(RoiMetricsService $metrics): View
    {
        return view('dashboard', [
            'savedCostsEur' => $metrics->savedCostsEur(),
            'resolvedConflicts' => $metrics->resolvedConflicts(),
            'automationRate' => $metrics->automationRate(),
            'averageConfidence' => $metrics->averageConfidence(),
            'openShifts' => Shift::open()->count(),
        ]);
    }
}
