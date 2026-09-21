<?php

namespace App\Enums;

enum ShiftStatus: string
{
    case Open = 'open';
    case Assigned = 'assigned';
    case Cancelled = 'cancelled';

    /**
     * Anzeige-Label für Badges und Tabellen (kiventro Corporate Language: DE).
     */
    public function label(): string
    {
        return match ($this) {
            self::Open => 'Offen',
            self::Assigned => 'Besetzt',
            self::Cancelled => 'Storniert',
        };
    }
}
