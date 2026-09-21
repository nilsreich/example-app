<?php

namespace App\Enums;

enum PipelineDriver: string
{
    case Mock = 'mock';
    case Live = 'live';

    /**
     * Anzeige-Label für den Pipeline-Toggle im Admin-Panel.
     */
    public function label(): string
    {
        return match ($this) {
            self::Mock => 'Deterministic Mock',
            self::Live => 'Live AI SDK',
        };
    }
}
