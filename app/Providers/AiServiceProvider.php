<?php

namespace App\Providers;

use App\Ai\Services\AgentRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Service-Provider des AI-Moduls.
 *
 * Bindet die AgentRegistry in den Container (Singleton), damit Agenten
 * über die Konfiguration (config/ai.php) aufgelöst werden können.
 */
final class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AgentRegistry::class);
    }

    public function boot(): void
    {
        //
    }
}
