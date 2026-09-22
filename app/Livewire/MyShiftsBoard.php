<?php

namespace App\Livewire;

use App\Enums\ShiftStatus;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\User;
use App\Services\EmployeeAvailabilityService;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Self-Service "Meine Schichten": read-only Übersicht der eigenen Einsätze,
 * plus Ein-Klick-Krankmeldung. Die Krankmeldung ist der Auslöser des
 * Demo-Szenarios: betroffene Schichten gehen zurück in die Disposition.
 */
class MyShiftsBoard extends Component
{
    use WithPagination;

    public ?string $notice = null;

    public string $sickReason = 'Krankmeldung';

    public function reportSick(EmployeeAvailabilityService $availability): void
    {
        $employee = $this->employee();

        abort_if($employee === null, 403, 'Kein Mitarbeiter-Profil verknüpft.');

        $this->validate(['sickReason' => ['required', 'string', 'max:200']]);

        $released = $availability->reportSick($employee, $this->sickReason);

        $this->notice = $released > 0
            ? "Krankmeldung erfasst. {$released} Schicht(en) wurden zur Neu-Disposition freigegeben."
            : 'Krankmeldung erfasst. Keine künftigen Schichten betroffen.';
    }

    public function reportAvailable(EmployeeAvailabilityService $availability): void
    {
        $employee = $this->employee();

        abort_if($employee === null, 403, 'Kein Mitarbeiter-Profil verknüpft.');

        $availability->reportAvailable($employee);

        $this->notice = 'Du bist wieder verfügbar und erscheinst im Kandidatenpool der Disposition.';
    }

    public function render(): View
    {
        $employee = $this->employee();

        return view('livewire.my-shifts-board', [
            'employee' => $employee,
            'shifts' => $employee
                ? Shift::where('assigned_employee_id', $employee->id)
                    ->orderBy('starts_at')
                    ->paginate(20)
                : new LengthAwarePaginator([], 0, 20),
            'openShifts' => $employee
                ? Shift::where('department', $employee->department)->where('status', ShiftStatus::Open)->count()
                : 0,
        ]);
    }

    private function employee(): ?Employee
    {
        $user = auth()->user();

        return $user instanceof User ? $user->employee : null;
    }
}
