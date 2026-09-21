<div class="max-w-xl space-y-4">
    @if ($notice)
        <div class="rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ $notice }}</div>
    @endif

    <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-gray-200 px-4 py-3 hover:bg-gray-50">
        <input type="checkbox" wire:model="enabled" class="mt-1 text-amber-600" />
        <span class="text-sm">
            <strong>In-App-Feedback aktivieren</strong><br />
            Zeigt angemeldeten Nutzern einen Feedback-Button (Element-Markierung, Screenshot, Kontextdaten).
            Meldungen landen ausschließlich lokal in der Datenbank; Screenshots liegen im privaten Storage.
        </span>
    </label>

    <button type="button" wire:click="save"
        class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">
        Einstellung speichern
    </button>

    <p class="text-xs text-gray-500">
        Triage der Meldungen im Bereich „Betrieb“ → „Feedback-Meldungen“. Keine externen Dienste, keine CDNs.
    </p>
</div>
