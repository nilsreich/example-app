<?php

namespace App\Ai\Contracts;

use App\Ai\Data\AiResult;

/**
 * Generischer Vertrag für einen AI-Agenten im Modul.
 *
 * Agenten werden über die AgentRegistry anhand der Konfiguration
 * (config/ai.php -> "agents") aufgelöst. Neue Agenten lassen sich so
 * ohne Eingriff in Template-Klassen per Konfiguration beilegen.
 */
interface AiAgent
{
    /**
     * Führt den Agenten für einen Prompt aus.
     *
     * @param  array<string, mixed>  $context  Zusatzkontext (z. B. Domänendaten)
     */
    public function run(string $prompt, array $context = []): AiResult;
}
