<?php

namespace App\Policies;

use App\Models\Shift;
use App\Models\User;

/**
 * Rechtematrix der Schicht-Disposition:
 *  - Web-Admin:    volle Rechte
 *  - GF:           lesen (alle Abteilungen)
 *  - Bereichsleiter: lesen + disponieren in der eigenen Abteilung
 *  - Nutzer:       kein Zugriff (hat die Seite "Meine Schichten")
 */
class ShiftPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->canDispatch() || $user->role->seesAllDepartments();
    }

    public function view(User $user, Shift $shift): bool
    {
        if ($user->role->seesAllDepartments()) {
            return true;
        }

        if ($user->role->canDispatch()) {
            return $shift->department === $user->department;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->role->canDispatch();
    }

    public function update(User $user, Shift $shift): bool
    {
        return $this->view($user, $shift) && $user->role->canDispatch();
    }

    public function delete(User $user, Shift $shift): bool
    {
        return $this->view($user, $shift) && $user->role->managesSettings();
    }

    public function restore(User $user, Shift $shift): bool
    {
        return false;
    }

    public function forceDelete(User $user, Shift $shift): bool
    {
        return false;
    }
}
