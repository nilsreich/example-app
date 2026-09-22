<?php

namespace App\Ai\Enums;

/**
 * Entscheidung des Menschen über eine AI-Empfehlung.
 */
enum AiDecision: string
{
    case Accepted = 'accepted';
    case Rejected = 'rejected';
}
