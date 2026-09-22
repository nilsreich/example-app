<?php

namespace App\Identity;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;

/**
 * Findet/erstellt den lokalen User aus den Entra-Attributen.
 *
 * Match-Reihenfolge: entra_object_id (stabil über E-Mail-Wechsel), dann E-Mail
 * (Übernahme bestehender lokaler Konten in den SSO-Pfad). Rolle und Abteilung
 * werden aus dem Gruppen-Mapping gesetzt; ohne Berechtigung wird null
 * zurückgegeben und der Login nicht fortgesetzt.
 */
final readonly class EntraUserResolver
{
    public function __construct(
        private EntraGroupRoleMapper $groupRoleMapper,
    ) {}

    /**
     * @param  list<string>  $groupObjectIds  Entra-Gruppen-Object-Ids (aus ID-Token/graph)
     * @return User|null null = kein Zugriff (keine passende Gruppe, kein Fallback)
     */
    public function resolve(SocialiteUserContract $entraUser, array $groupObjectIds): ?User
    {
        $mapped = $this->groupRoleMapper->map($groupObjectIds);

        if ($mapped['role'] === null) {
            return null;
        }

        $user = User::query()
            ->where('entra_object_id', $entraUser->getId())
            ->orWhere('email', $entraUser->getEmail())
            ->first();

        if ($user === null) {
            $user = new User;
        }

        $user->entra_object_id = $entraUser->getId();
        $user->name = $entraUser->getName() ?? $entraUser->getNickname() ?? $entraUser->getEmail();
        $user->email = $entraUser->getEmail();
        $user->role = UserRole::from($mapped['role']);
        $user->department = $mapped['department'];
        $user->email_verified_at ??= Carbon::now();
        $user->save();

        return $user;
    }
}
