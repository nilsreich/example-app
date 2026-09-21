<?php

namespace App\Http\Controllers;

use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Models\Shift;
use App\Services\RoiMetricsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * B2E-Einstiegsseite außerhalb des Admin-Panels: rollenabhängige Sicht.
 *  - Reporting-Rollen: ROI-Kennzahlen (abteilungsgescoped) + Einstieg ins Dispatching.
 *  - Mitarbeiter (nutzer): direkter Weg zu "Meine Schichten" inkl. Verfügbarkeit.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, RoiMetricsService $metrics): View
    {
        $user = $request->user();
        $metrics->forUser($user);

        $departments = $user->visibleDepartments();

        return view('dashboard', [
            'role' => $user->role,
            'isSelfService' => $user->role === UserRole::Nutzer,
            'employee' => $user->employee,
            'savedCostsEur' => $metrics->savedCostsEur(),
            'resolvedConflicts' => $metrics->resolvedConflicts(),
            'automationRate' => $metrics->automationRate(),
            'averageConfidence' => $metrics->averageConfidence(),
            'openShifts' => Shift::where('status', ShiftStatus::Open)
                ->when($departments !== null, fn ($query) => $query->whereIn('department', $departments))
                ->count(),
        ]);
    }
}
