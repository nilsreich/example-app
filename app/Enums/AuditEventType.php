<?php

namespace App\Enums;

enum AuditEventType: string
{
    case InitialAssignment = 'initial_assignment';
    case ManualOverride = 'manual_override';
    case Rollback = 'rollback';
    // Self-Service: Mitarbeiter meldet sich krank/verfügbar; betroffene Schichten
    // werden wieder offen (Auslöser des Dispositions-Workflows).
    case AvailabilityReported = 'availability_reported';

    public function label(): string
    {
        return match ($this) {
            self::InitialAssignment => 'Erstzuweisung',
            self::ManualOverride => 'Manuelle Umbesetzung',
            self::Rollback => 'Rollback (Forward)',
            self::AvailabilityReported => 'Verfügbarkeitsmeldung',
        };
    }
}
