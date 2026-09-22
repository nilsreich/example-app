<?php

return [
    /*
    |--------------------------------------------------------------------------
    | feedback-Modul (In-App-Feedback)
    |--------------------------------------------------------------------------
    | Das Modul ist pro Kundeninstanz abschaltbar und konfigurierbar.
    |
    | - enabled: false blendet Floating-Widget und Triage vollständig aus.
    | - categories: im Widget angebotene Kategorien (Werte aus FeedbackCategory).
    | - retention_months: Meldungen älter als dieser Zeitraum werden vom
    |   Aufbewahrungslauf (feedback:retention) samt Screenshot entfernt (DSGVO).
    */

    'enabled' => true,

    'categories' => ['bug', 'idea', 'question', 'other'],

    'retention_months' => 12,
];
