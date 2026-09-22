<x-layouts::app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <div>
            <flux:heading size="xl">Dispatch-Cockpit</flux:heading>
            <flux:subheading>
                @if ($isSelfService)
                    Deine Schichten und Verfügbarkeit – ein Klick genügt.
                @else
                    Intelligente Schicht- &amp; Kapazitätsumplanung – Kennzahlen aus dem Forward-Ledger.
                @endif
            </flux:subheading>
        </div>

        @if ($isSelfService)
            {{-- Self-Service-Sicht: kein Reporting, sondern die persönlichen Schichten. --}}
            <flux:card>
                <flux:heading size="lg">Meine Schichten</flux:heading>
                <flux:text class="mt-1">
                    @if ($employee)
                        Angemeldet als <strong>{{ $employee->name }}</strong> ({{ $employee->department }}) –
                        Status: {{ $employee->is_active ? 'verfügbar' : 'krankgemeldet' }}.
                        Krankmeldungen geben betroffene Schichten automatisch zurück in die Disposition.
                    @else
                        Deinem Login ist noch kein Mitarbeiter-Profil zugeordnet.
                    @endif
                </flux:text>
                <div class="mt-4">
                    <flux:button variant="primary" icon="calendar-days" :href="url('/admin/my-shifts')">
                        Meine Schichten öffnen
                    </flux:button>
                </div>
            </flux:card>
        @else
            <div class="grid auto-rows-min gap-4 md:grid-cols-4">
                <flux:card size="sm">
                    <flux:text>Eingesparte Disponentenkosten</flux:text>
                    <flux:heading size="xl">{{ number_format($savedCostsEur, 2, ',', '.') }} €</flux:heading>
                    <flux:text class="text-sm text-zinc-500">{{ $resolvedConflicts }} gelöste Konflikte × 45 Min × 65 €/h</flux:text>
                </flux:card>

                <flux:card size="sm">
                    <flux:text>Automatisierungsquote</flux:text>
                    <flux:heading size="xl">{{ $automationRate === null ? '–' : number_format($automationRate, 1, ',', '.').' %' }}</flux:heading>
                    <flux:text class="text-sm text-zinc-500">Matches ohne Nacharbeit übernommen</flux:text>
                </flux:card>

                <flux:card size="sm">
                    <flux:text>Ø Match-Konfidenz</flux:text>
                    <flux:heading size="xl">{{ $averageConfidence === null ? '–' : number_format($averageConfidence, 1, ',', '.').' %' }}</flux:heading>
                    <flux:text class="text-sm text-zinc-500">Durchschnitt aller KI-Scores</flux:text>
                </flux:card>

                <flux:card size="sm">
                    <flux:text>Offene Schichten</flux:text>
                    <flux:heading size="xl">{{ $openShifts }}</flux:heading>
                    <flux:text class="text-sm text-zinc-500">{{ $role === \App\Enums\UserRole::Bereichsleiter ? 'In deiner Abteilung' : 'Im System' }}</flux:text>
                </flux:card>
            </div>

            <flux:card>
                <flux:heading size="lg">{{ $role === \App\Enums\UserRole::Bereichsleiter ? 'Jetzt disponieren' : 'Dispatching & Auswertung' }}</flux:heading>
                <flux:text class="mt-1">
                    @if ($role === \App\Enums\UserRole::Bereichsleiter)
                        Offene Schichten deiner Abteilung prüfen, KI-Ersatzvorschläge berechnen und per 1-Klick zuweisen –
                        jede Änderung landet revisionssicher im Forward-Ledger.
                    @else
                        Schichten disponieren, ROI-Kennzahlen und Feedback-Triage im Admin-Panel.
                    @endif
                </flux:text>
                <div class="mt-4 flex flex-wrap gap-2">
                    <flux:button variant="primary" icon="sparkles" :href="url('/admin/shifts')">Zu den Schichten</flux:button>
                    <flux:button icon="chart-bar" :href="url('/admin')">ROI-Dashboard (Admin)</flux:button>
                    @if ($role === \App\Enums\UserRole::WebAdmin)
                        <flux:button icon="cpu-chip" :href="url('/admin/pipeline-settings')">KI-Pipeline-Modus</flux:button>
                    @endif
                </div>
            </flux:card>
        @endif

        <flux:card>
            <flux:heading size="lg">Demo-Szenario</flux:heading>
            <flux:text class="mt-1">
                Personalausfall in der Logistik – kritisch unbesetzte Schichten warten auf KI-gestützte Umplanung.
                Demo-Logins (Passwort <strong>kiventro-demo</strong>): <strong>gf@kiventro.de</strong> (Geschäftsführung),
                <strong> leitung.logistik@kiventro.de</strong> (Bereichsleitung),
                <strong> mitarbeiter@kiventro.de</strong> (Self-Service), <strong>admin@kiventro.de</strong> (System).
            </flux:text>
        </flux:card>
    </div>
</x-layouts::app>
