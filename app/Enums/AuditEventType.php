<?php

namespace App\Enums;

enum AuditEventType: string
{
    case InitialAssignment = 'initial_assignment';
    case ManualOverride = 'manual_override';
    case Rollback = 'rollback';

    public function label(): string
    {
        return match ($this) {
            self::InitialAssignment => 'Erstzuweisung',
            self::ManualOverride => 'Manuelle Umbesetzung',
            self::Rollback => 'Rollback (Forward)',
        };
    }
}
