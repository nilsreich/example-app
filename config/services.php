<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Entra-ID-SSO (B2E-Template): gleiche ENV-Quelle wie config/entra.php.
    // Der Microsoft-Provider liest 'tenant' als zusätzlichen Config-Key.
    //
    // WICHTIG (tenant): kein 'common'-Fallback. Ohne explizite
    // ENTRA_TENANT_ID bleibt der Tenant leer und der Provider baut keine
    // gültige Authorize-URL (fail-closed) statt persönliche/Cross-Tenant-
    // Konten zu akzeptieren. Für feste Mandanten hier die Entra-Tenant-ID
    // setzen (ENTRA_TENANT_ID).
    // 'groups' im ID-Token liefert Entra nur, wenn das Azure-App-Manifest
    // 'groupMembershipClaims' aktiviert hat; ohne dieses Claim werden Logins
    // mit konfiguriertem group_mapping bewusst abgelehnt (Fail-closed).
    'microsoft' => [
        'client_id' => env('ENTRA_CLIENT_ID'),
        'client_secret' => env('ENTRA_CLIENT_SECRET'),
        'redirect' => env('ENTRA_REDIRECT_URI', '/auth/entra/callback'),
        'tenant' => env('ENTRA_TENANT_ID'),
    ],

];
