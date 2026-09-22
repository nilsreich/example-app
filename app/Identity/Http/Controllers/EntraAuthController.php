<?php

namespace App\Identity\Http\Controllers;

use App\Audit\AuditLedger;
use App\Audit\Enums\AuditEventType;
use App\Audit\Enums\AuditSource;
use App\Identity\EntraUserResolver;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;
use Symfony\Component\HttpFoundation\RedirectResponse;

class EntraAuthController extends Controller
{
    public function __construct(
        private readonly SocialiteFactory $socialite,
        private readonly EntraUserResolver $userResolver,
        private readonly AuditLedger $ledger,
    ) {}

    /**
     * Redirect zum Microsoft-Entra-Login (OAuth2/OpenID via Socialite).
     */
    public function redirect(): RedirectResponse
    {
        return $this->socialite->driver('microsoft')->redirect();
    }

    /**
     * OAuth-Callback: User provisionieren/matchen, Rollen-Mapping, Login.
     * Ohne Berechtigung (keine Gruppe, kein Fallback) zurück zum lokalen Login.
     */
    public function callback(Request $request): RedirectResponse
    {
        try {
            $socialiteUser = $this->socialite->driver('microsoft')->user();
        } catch (\Throwable $e) {
            // Consent-Deny, abgelaufener State, Provider-Fehler: kein 500er,
            // sondern wie eine verweigerte Anmeldung behandeln.
            report($e);

            $this->ledger->record(
                eventType: AuditEventType::LoginFailed,
                previousState: [],
                newState: ['reason' => 'oauth_callback_failed'],
                source: AuditSource::Entra,
                ip: $request->ip(),
                userAgent: $request->userAgent(),
            );

            return redirect()->route('login')->with('error', 'Anmeldung über Entra fehlgeschlagen.');
        }

        $user = $this->userResolver->resolve($socialiteUser, $this->groupObjectIds($socialiteUser));

        if ($user === null) {
            $this->ledger->record(
                eventType: AuditEventType::LoginFailed,
                previousState: [],
                newState: ['email' => $socialiteUser->getEmail()],
                source: AuditSource::Entra,
                ip: $request->ip(),
                userAgent: $request->userAgent(),
            );

            return redirect()->route('login')->with('error', 'Zugriff über Entra nicht berechtigt.');
        }

        if ($user->wasRecentlyCreated) {
            $this->ledger->record(
                eventType: AuditEventType::Provisioned,
                previousState: [],
                newState: [
                    'email' => $user->email,
                    'role' => $user->role->value,
                    'department' => $user->department,
                ],
                auditable: $user,
                actor: $user,
                source: AuditSource::Entra,
                ip: $request->ip(),
                userAgent: $request->userAgent(),
            );
        }

        $this->ledger->record(
            eventType: AuditEventType::Login,
            previousState: [],
            newState: ['email' => $user->email],
            auditable: $user,
            actor: $user,
            source: AuditSource::Entra,
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        auth()->login($user);

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Gruppen-Object-Ids aus dem Entra-ID-Token (Optional Claim "groups").
     * Provider-spezifisch: raw-Slot des Socialite-Users.
     *
     * @return list<string>
     */
    private function groupObjectIds(SocialiteUserContract $socialiteUser): array
    {
        if (! method_exists($socialiteUser, 'getRaw')) {
            return [];
        }

        $groups = $socialiteUser->getRaw()['groups'] ?? [];

        return is_array($groups) ? array_values(array_map('strval', $groups)) : [];
    }
}
