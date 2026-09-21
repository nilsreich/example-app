<?php

namespace App\Enums;

enum FeedbackRating: string
{
    case Positive = 'positive';
    case Negative = 'negative';

    public function label(): string
    {
        return match ($this) {
            self::Positive => 'Hilfreich',
            self::Negative => 'Nicht hilfreich',
        };
    }
}
