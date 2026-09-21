<x-layouts::app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <div>
            <flux:heading size="xl">kiventro Dispatch-Cockpit</flux:heading>
            <flux:subheading>
                Intelligente Schicht- &amp; Kapazitätsumplanung – Kennzahlen aus dem Forward-Ledger.
            </flux:subheading>
        </div>

        {{-- KPI-Kacheln: identische Werte wie im Admin-Dashboard (eine Datenquelle: RoiMetricsService). --}}
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
                <flux:text class="text-sm text-zinc-500">Warten auf Ersatzbesetzung</flux:text>
            </flux:card>
        </div>

        <flux:card>
            <flux:heading size="lg">Dispatching starten</flux:heading>
            <flux:text class="mt-1">
                Der operative Workflow läuft im Admin-Panel: Schicht öffnen, KI-Ersatzvorschläge berechnen,
                per 1-Klick zuweisen – jede Änderung landet revisionssicher im Forward-Ledger.
            </flux:text>
            <div class="mt-4 flex flex-wrap gap-2">
                <flux:button variant="primary" icon="sparkles" :href="url('/admin/shifts')">Zu den Schichten</flux:button>
                <flux:button icon="chart-bar" :href="url('/admin')">ROI-Dashboard (Admin)</flux:button>
                <flux:button icon="cpu-chip" :href="url('/admin/pipeline-settings')">KI-Pipeline-Modus</flux:button>
            </div>
        </flux:card>

        <flux:card>
            <flux:heading size="lg">Demo-Szenario</flux:heading>
            <flux:text class="mt-1">
                Personalausfall Julia Brandt (Logistik) – drei kritisch unbesetzte Schichten warten auf
                KI-gestützte Umplanung. Demo-Login: <strong>admin@kiventro.de</strong> bzw.
                <strong>disponent@kiventro.de</strong>.
            </flux:text>
        </flux:card>
    </div>
</x-layouts::app>
