<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Entra-ID-SSO (B2E-Template)
    |--------------------------------------------------------------------------
    |
    | Ein Microsoft-Mandant pro Instanz (keine Multi-Tenancy). Der Socialite
    | Microsoft-Provider (config/services.php) liest dieselben ENV-Werte.
    | Gruppen-Mapping und Rollen-Default-Set sind pro Kundenprojekt anpassbar.
    |
    */

    'enabled' => (bool) env('ENTRA_ENABLED', false),

    'tenant' => env('ENTRA_TENANT_ID'),

    'client_id' => env('ENTRA_CLIENT_ID'),

    'client_secret' => env('ENTRA_CLIENT_SECRET'),

    'redirect' => env('ENTRA_REDIRECT_URI', '/auth/entra/callback'),

    'scope' => array_values(array_filter(explode(',', (string) env('ENTRA_SCOPE', 'openid,profile,email,User.Read')))),

    /*
    |--------------------------------------------------------------------------
    | Rollen-Default-Set (identity-Modul)
    |--------------------------------------------------------------------------
    |
    | Die vier Template-Rollen als Referenz für das Gruppen-Mapping oben.
    | Die konkreten Fähigkeiten (Panel-Zugriff, Abteilungs-Scope, Disposition)
    | leben im App\Enums\UserRole-Enum und gelten unverändert.
    |
    */

    'roles' => [
        'web-admin',
        'geschaeftsfuehrer',
        'bereichsleiter',
        'nutzer',
    ],

    /*
    |--------------------------------------------------------------------------
    | Gruppen-Mapping (Entra Security Group Object-ID -> Rolle/Abteilung)
    |--------------------------------------------------------------------------
    |
    | Schlüssel = Entra-Gruppen-Object-Id, Wert = ['role' => UserRole, 'department' => string|null].
    | Kein Default-Rollen-Verfall: ohne Treffer wird der Login abgelehnt
    | (außer expliziter Fallback über 'fallback_role').
    |
    */

    'group_mapping' => [],

    'fallback_role' => null,
];
