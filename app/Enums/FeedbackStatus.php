<?php

namespace App\Enums;

enum FeedbackStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Neu',
            self::InProgress => 'In Arbeit',
            self::Resolved => 'Erledigt',
        };
    }

    /**
     * Filament-Badge-Farbe.
     */
    public function color(): string
    {
        return match ($this) {
            self::New => 'warning',
            self::InProgress => 'info',
            self::Resolved => 'success',
        };
    }
}
