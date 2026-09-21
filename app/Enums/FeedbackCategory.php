<?php

namespace App\Enums;

enum FeedbackCategory: string
{
    case Bug = 'bug';
    case Idea = 'idea';
    case Question = 'question';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Bug => 'Fehler',
            self::Idea => 'Idee',
            self::Question => 'Frage',
            self::Other => 'Sonstiges',
        };
    }

    /**
     * Filament-Badge-Farbe.
     */
    public function color(): string
    {
        return match ($this) {
            self::Bug => 'danger',
            self::Idea => 'info',
            self::Question => 'warning',
            self::Other => 'gray',
        };
    }
}
