<?php

namespace App\Ai\Enums;

/**
 * Treiber, über den ein AI-Agent ausgeführt wird.
 *
 * - Mock: deterministischer Fake ohne Provider-Aufruf (Default, keine Keys nötig)
 * - Live:  Laravel-AI-SDK mit echtem Provider
 */
enum AiDriver: string
{
    case Mock = 'mock';

    case Live = 'laravel-ai';
}
