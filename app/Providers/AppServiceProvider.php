<?php

namespace App\Providers;

use App\Contracts\ShiftOptimizerPipelineInterface;
use App\Enums\PipelineDriver;
use App\Filament\Auth\RoleBasedLoginResponse;
use App\Models\Setting;
use App\Pipelines\LaravelAiSdkPipeline;
use App\Pipelines\MockDeterministicPipeline;
use Carbon\CarbonImmutable;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Pipeline Driver Switch: "mock" (Default, ohne API-Key) oder "live"
        // (Laravel AI SDK). Umschaltbar im Admin-Panel (Settings-Page).
        // Der Schema-Guard hält Artisan-Befehle vor der Settings-Migration lauffähig.
        $this->app->bind(ShiftOptimizerPipelineInterface::class, function (): ShiftOptimizerPipelineInterface {
            try {
                $driver = Schema::hasTable('settings')
                    ? Setting::aiPipelineDriver()
                    : PipelineDriver::Mock;
            } catch (\Throwable) {
                $driver = PipelineDriver::Mock;
            }

            return $driver === PipelineDriver::Live
                ? $this->app->make(LaravelAiSdkPipeline::class)
                : $this->app->make(MockDeterministicPipeline::class);
        });

        // Rollenbasierter Login-Redirect (GF → Überblick, Bereichsleiter → Schichten, Nutzer → Meine Schichten).
        $this->app->bind(LoginResponseContract::class, RoleBasedLoginResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
