<div class="space-y-6">
    @if (! $employee)
        <div class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Deinem Login ist noch kein Mitarbeiter-Profil zugeordnet. Bitte an die Web-Administration wenden.
        </div>
    @else
        @if ($notice)
            <div class="rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ $notice }}</div>
        @endif

        <div class="flex flex-wrap items-center gap-3">
            <span class="text-sm text-gray-600">{{ $employee->name }} · {{ $employee->role }} · {{ $employee->department }}</span>
            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold
                {{ $employee->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                {{ $employee->is_active ? 'Verfügbar' : 'Krankgemeldet' }}
            </span>
        </div>

        {{-- Ein-Klick-Verfügbarkeit: der Auslöser des Szenarios. --}}
        <div class="flex flex-wrap items-end gap-3 rounded-xl border border-gray-200 p-4">
            @if ($employee->is_active)
                <div>
                    <label class="block text-xs font-medium text-gray-500">Krankmeldung – Grund (intern)</label>
                    <input type="text" wire:model="sickReason" class="mt-1 w-72 rounded-lg border-gray-300 text-sm" />
                    @error('sickReason') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <button type="button" wire:click="reportSick"
                    class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">
                    Krank melden
                </button>
                @if ($openShifts > 0)
                    <p class="text-xs text-gray-500">
                        {{ $openShifts }} offene Schicht(en) in {{ $employee->department }} warten auf Besetzung.
                    </p>
                @endif
            @else
                <button type="button" wire:click="reportAvailable"
                    class="rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700">
                    Wieder verfügbar melden
                </button>
            @endif
        </div>

        <div>
            <h3 class="text-sm font-semibold text-gray-700">Meine Schichten</h3>
            @if ($shifts->isEmpty())
                <p class="mt-1 text-sm text-gray-500">Aktuell sind dir keine Schichten zugewiesen.</p>
            @else
                <div class="mt-2 overflow-hidden rounded-xl border border-gray-200">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-4 py-2">Schicht</th>
                                <th class="px-4 py-2">Beginn</th>
                                <th class="px-4 py-2">Ende</th>
                                <th class="px-4 py-2">Abteilung</th>
                                <th class="px-4 py-2">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($shifts as $shift)
                                <tr wire:key="my-shift-{{ $shift->id }}">
                                    <td class="px-4 py-2 font-medium">{{ $shift->title }}</td>
                                    <td class="px-4 py-2">{{ $shift->starts_at->format('d.m.Y H:i') }}</td>
                                    <td class="px-4 py-2">{{ $shift->ends_at->format('d.m.Y H:i') }}</td>
                                    <td class="px-4 py-2">{{ $shift->department }}</td>
                                    <td class="px-4 py-2">
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold
                                            {{ $shift->status === \App\Enums\ShiftStatus::Assigned ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}">
                                            {{ $shift->status->label() }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
</div>
