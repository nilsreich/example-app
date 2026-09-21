<?php

namespace App\Filament\Auth;

use App\Enums\UserRole;
use App\Filament\Pages\MyShifts;
use App\Filament\Resources\Shifts\ShiftResource;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * Rollenbasierter Einstieg: Jede Rolle landet direkt auf ihrer Arbeitsfläche
 * (klare, schnelle Wege statt generischer Panel-Startseite).
 */
class RoleBasedLoginResponse implements LoginResponse
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        $role = auth()->user()?->role;

        $destination = match ($role) {
            // Self-Service: eigene Schichten zuerst.
            UserRole::Nutzer => MyShifts::getUrl(panel: 'admin'),
            // Disposition: offene Schichten der eigenen Abteilung.
            UserRole::Bereichsleiter => ShiftResource::getUrl(panel: 'admin'),
            // GF + Web-Admin: Überblick (adaptives Dashboard).
            default => Filament::getUrl(),
        };

        return redirect()->intended($destination);
    }
}
