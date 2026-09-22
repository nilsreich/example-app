<?php

namespace App\Providers;

use App\Audit\AuditLedger;
use App\Audit\Enums\AuditEventType;
use App\Enums\UserRole;
use App\Filament\Auth\RoleBasedLoginResponse;
use App\Identity\EntraGroupRoleMapper;
use App\Identity\EntraUserResolver;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Microsoft\MicrosoftExtendSocialite;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Rollenbasierter Login-Redirect (GF → Überblick, Bereichsleiter → Schichten, Nutzer → Meine Schichten).
        $this->app->bind(LoginResponseContract::class, RoleBasedLoginResponse::class);

        // GoBD-Export des Audit-Trails: nur Web-Admin und Geschäftsführung.
        Gate::define('audit.export', static function (User $user): bool {
            return in_array($user->role, [UserRole::WebAdmin, UserRole::Geschaeftsfuehrer], true);
        });

        // Entra-Gruppen-Mapping: config-gesteuert (pro Kundenprojekt anpassbar).
        $this->app->bind(EntraGroupRoleMapper::class, fn (): EntraGroupRoleMapper => new EntraGroupRoleMapper(
            mapping: config('entra.group_mapping', []),
            fallbackRole: config('entra.fallback_role'),
        ));
        $this->app->bind(EntraUserResolver::class, fn (): EntraUserResolver => new EntraUserResolver(
            $this->app->make(EntraGroupRoleMapper::class),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // Entra-ID-Provider (SocialiteProviders\Microsoft) beim Socialite-Manager registrieren.
        Event::listen(
            SocialiteWasCalled::class,
            MicrosoftExtendSocialite::class,
        );

        // GoBD: Jede Abmeldung (SSO wie lokal) landet im Audit-Trail.
        Event::listen(Logout::class, static function (Logout $event): void {
            if ($event->user instanceof User) {
                app(AuditLedger::class)->record(
                    eventType: AuditEventType::Logout,
                    previousState: [],
                    newState: ['email' => $event->user->email],
                    auditable: $event->user,
                    actor: $event->user,
                );
            }
        });
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
