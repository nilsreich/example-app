<?php

namespace App\Identity;

use App\Audit\AuditLedger;
use App\Audit\Enums\AuditEventType;
use App\Audit\Enums\AuditSource;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;

/**
 * Findet/erstellt den lokalen User aus den Entra-Attributen.
 *
 * Match-Reihenfolge: entra_object_id (stabil über E-Mail-Wechsel), dann E-Mail
 * (Übernahme bestehender lokaler Konten in den SSO-Pfad). Ein Konto, das bereits
 * an eine andere Entra-Object-Id gebunden ist, wird durch E-Mail-Kollision nie
 * übernommen (Konto-Übernahme-Schutz). Rolle und Abteilung werden aus dem
 * Gruppen-Mapping gesetzt; Wechsel werden als eigene Audit-Events festgehalten.
 * Ohne Berechtigung wird null zurückgegeben und der Login nicht fortgesetzt.
 */
final readonly class EntraUserResolver
{
    public function __construct(
        private EntraGroupRoleMapper $groupRoleMapper,
        private ?AuditLedger $ledger = null,
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
            ->first();

        if ($user === null) {
            $user = User::query()->where('email', $entraUser->getEmail())->first();

            // Die E-Mail gehört bereits zu einem Konto, das an eine andere
            // Entra-Identität gebunden ist → nicht adoptieren, Login verweigern.
            if ($user !== null && $user->entra_object_id !== null) {
                return null;
            }
        }

        if ($user === null) {
            $user = new User;
        }

        $previousRole = $user->exists ? $user->getOriginal('role') : null;
        $previousDepartment = $user->exists ? $user->getOriginal('department') : null;

        $user->entra_object_id = $entraUser->getId();
        $user->name = $entraUser->getName() ?? $entraUser->getNickname() ?? $entraUser->getEmail();
        $user->email = $entraUser->getEmail();
        $user->role = UserRole::from($mapped['role']);
        $user->department = $mapped['department'];
        $user->email_verified_at ??= Carbon::now();
        $user->save();

        if ($this->ledger !== null && ! $user->wasRecentlyCreated) {
            if ($previousRole !== null && $previousRole !== $user->role->value) {
                $this->ledger->record(
                    eventType: AuditEventType::RoleChanged,
                    previousState: ['role' => $previousRole],
                    newState: ['role' => $user->role->value],
                    auditable: $user,
                    actor: $user,
                    source: AuditSource::Entra,
                );
            }

            if ($previousDepartment !== $user->department) {
                $this->ledger->record(
                    eventType: AuditEventType::DepartmentChanged,
                    previousState: ['department' => $previousDepartment],
                    newState: ['department' => $user->department],
                    auditable: $user,
                    actor: $user,
                    source: AuditSource::Entra,
                );
            }
        }

        return $user;
    }
}
