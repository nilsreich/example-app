<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <x-filament::badge color="primary">{{ $role?->label() }}</x-filament::badge>
                    @if ($department)
                        <x-filament::badge color="gray">Abteilung {{ $department }}</x-filament::badge>
                    @endif
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-300">{{ $role?->description() }}</p>
            </div>

            <div class="flex flex-wrap gap-2">
                @if ($role?->canDispatch())
                    <x-filament::button tag="a" :href="$shiftsUrl" icon="heroicon-o-sparkles" color="primary">
                        {{ $openShifts }} offene Schicht(en) disponieren
                    </x-filament::button>
                @endif

                @if ($role === \App\Enums\UserRole::Geschaeftsfuehrer)
                    <x-filament::button tag="a" :href="$shiftsUrl" icon="heroicon-o-list-bullet" color="primary">
                        {{ $openShifts }} offene Schicht(en) ansehen
                    </x-filament::button>
                @endif

                @if ($role === \App\Enums\UserRole::Nutzer)
                    <x-filament::button tag="a" :href="$myShiftsUrl" icon="heroicon-o-calendar-days" color="primary">
                        Meine Schichten &amp; Verfügbarkeit
                    </x-filament::button>
                @endif
            </div>
        </div>
    </x-filament::section>

    {{-- Systemstatus nur für den Web-Admin: Toggles, Feedback-Eingang, Demo-Reset. --}}
    @if ($role?->managesSettings())
        <div class="mt-4 grid gap-4 md:grid-cols-3">
            <x-filament::section compact>
                <x-slot name="heading">KI-Pipeline</x-slot>
                <p class="text-sm">{{ $pipelineLabel }}</p>
                <div class="mt-2">
                    <x-filament::button tag="a" :href="$pipelineUrl" size="sm" color="gray" icon="heroicon-o-cpu-chip">
                        Modus wechseln
                    </x-filament::button>
                </div>
            </x-filament::section>

            <x-filament::section compact>
                <x-slot name="heading">In-App-Feedback</x-slot>
                <p class="text-sm">{{ $feedbackEnabled ? 'Widget aktiv' : 'Widget deaktiviert' }} · {{ $newFeedback }} neue Meldung(en)</p>
                <div class="mt-2 flex gap-2">
                    <x-filament::button tag="a" :href="$feedbackToggleUrl" size="sm" color="gray" icon="heroicon-o-chat-bubble-left-right">
                        Einstellung
                    </x-filament::button>
                    <x-filament::button tag="a" :href="$feedbackUrl" size="sm" color="gray" icon="heroicon-o-inbox">
                        Triage
                    </x-filament::button>
                </div>
            </x-filament::section>

            <x-filament::section compact>
                <x-slot name="heading">Demo-Daten</x-slot>
                <p class="text-sm">{{ $openShifts }} offene Schichten im System</p>
                <div class="mt-2">
                    <x-filament::button wire:click="resetDemo" wire:confirm="Demo-Szenario zurücksetzen? Alle Demo-Daten werden neu erzeugt."
                        size="sm" color="danger" icon="heroicon-o-arrow-path">
                        Szenario neu laden
                    </x-filament::button>
                </div>
            </x-filament::section>
        </div>
    @endif
</x-filament-widgets::widget>
