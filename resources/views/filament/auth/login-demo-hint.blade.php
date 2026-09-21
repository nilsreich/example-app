{{-- Demo-Hinweis auf der Login-Seite: vier Rollen, ein Szenario (Template-Charakter). --}}
<x-filament::section compact>
    <x-slot name="heading">Demo-Zugänge</x-slot>
    <p class="text-sm text-gray-600 dark:text-gray-300">
        Ein Login je Rolle – Passwort jeweils <strong>kiventro-demo</strong>:
    </p>
    <div class="mt-3 grid gap-2 text-sm md:grid-cols-2">
        <div><code>admin@kiventro.de</code> – Web-Admin (System &amp; Triage)</div>
        <div><code>gf@kiventro.de</code> – Geschäftsführung (ROI zuerst)</div>
        <div><code>leitung.logistik@kiventro.de</code> – Bereichsleitung Logistik</div>
        <div><code>mitarbeiter@kiventro.de</code> – Mitarbeiter (Self-Service)</div>
    </div>
    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
        Szenario: kurzfristiger Personalausfall – KI-Ersatzvorschläge, 1-Klick-Zuweisung,
        Forward-Rollback und ROI-Auswertung.
    </p>
</x-filament::section>
