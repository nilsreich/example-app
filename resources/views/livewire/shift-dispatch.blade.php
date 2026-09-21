<div class="space-y-6">
    {{-- Statusleiste --}}
    <div class="flex flex-wrap items-center gap-2 text-sm">
        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 font-medium
            {{ $shift->status === \App\Enums\ShiftStatus::Open ? 'bg-red-100 text-red-800' : ($shift->status === \App\Enums\ShiftStatus::Assigned ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800') }}">
            {{ $shift->status->label() }}
        </span>
        <span class="text-gray-500">{{ $shift->department }} · {{ $shift->starts_at->format('d.m.Y H:i') }} – {{ $shift->ends_at->format('H:i') }} Uhr</span>
        @if ($shift->assignedEmployee)
            <span class="text-gray-700">Besetzt mit: <strong>{{ $shift->assignedEmployee->name }}</strong></span>
        @endif
    </div>

    @if ($notice)
        <div class="rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ $notice }}</div>
    @endif

    {{-- Aktionen --}}
    <div class="flex flex-wrap gap-2">
        <button type="button" wire:click="runOptimization" wire:loading.attr="disabled"
            class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-600 disabled:opacity-50">
            <span wire:loading.remove>KI-Ersatzvorschläge berechnen</span>
            <span wire:loading>Pipeline läuft …</span>
        </button>
        @if ($shift->status === \App\Enums\ShiftStatus::Assigned)
            <button type="button" wire:click="$set('showRollbackModal', true)"
                class="rounded-lg border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">
                Zuweisung zurückrollen
            </button>
        @endif
    </div>

    {{-- Matches --}}
    @if ($optimization)
        <div class="text-xs text-gray-500">
            Lauf #{{ $optimization->id }} · {{ $optimization->driver_used->label() }} · {{ $optimization->execution_time_ms }} ms
            @if ($optimization->tokens_used) · {{ number_format($optimization->tokens_used, 0, ',', '.') }} Tokens @endif
            @if ($optimization->cost_estimate_eur) · ca. {{ $optimization->cost_estimate_eur }} € @endif
        </div>

        <div class="space-y-4">
            @foreach ($optimization->proposals as $proposal)
                @php($tier = $proposal->confidenceTier())
                <div class="rounded-xl border border-gray-200 p-4 shadow-sm" wire:key="proposal-{{ $proposal->id }}">
                    <div class="flex items-center justify-between gap-3">
                        <div class="font-semibold">{{ $proposal->employee->name }}</div>
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-sm font-bold
                            {{ $tier === 'high' ? 'bg-green-100 text-green-800' : ($tier === 'medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                            {{ $proposal->score }} %
                        </span>
                    </div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-gray-100">
                        <div class="h-full rounded-full {{ $tier === 'high' ? 'bg-green-500' : ($tier === 'medium' ? 'bg-yellow-500' : 'bg-red-500') }}"
                            style="width: {{ $proposal->score }}%"></div>
                    </div>

                    @if ($proposal->match_reasons)
                        <ul class="mt-3 space-y-1 text-sm text-gray-700">
                            @foreach ($proposal->match_reasons as $reason)
                                <li>✓ {{ $reason }}</li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($proposal->risks_or_tradeoffs)
                        <ul class="mt-2 space-y-1 text-sm text-amber-700">
                            @foreach ($proposal->risks_or_tradeoffs as $risk)
                                <li>⚠ {{ $risk }}</li>
                            @endforeach
                        </ul>
                    @endif

                    <label class="mt-3 block text-xs font-medium text-gray-500">Benachrichtigungstext (editierbar)</label>
                    <textarea wire:model="drafts.{{ $proposal->id }}" rows="2"
                        class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm"></textarea>

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <button type="button" wire:click="assignProposal({{ $proposal->id }})"
                            class="rounded-lg bg-gray-900 px-3 py-1.5 text-sm font-semibold text-white hover:bg-gray-700">
                            Schicht zuweisen
                        </button>
                        <button type="button" wire:click="openFeedback({{ $proposal->id }}, 'positive')" title="Hilfreich"
                            class="rounded-lg border px-2.5 py-1.5 text-sm hover:bg-green-50">👍</button>
                        <button type="button" wire:click="openFeedback({{ $proposal->id }}, 'negative')" title="Nicht hilfreich"
                            class="rounded-lg border px-2.5 py-1.5 text-sm hover:bg-red-50">👎</button>
                        @if ($proposal->feedbacks->isNotEmpty())
                            <span class="text-xs text-gray-500">{{ $proposal->feedbacks->count() }}× bewertet</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <p class="text-sm text-gray-500">Noch keine Vorschläge berechnet. Starte oben den Pipeline-Lauf.</p>
    @endif

    {{-- Negativ-Feedback-Modal --}}
    @if ($showFeedbackModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                <h3 class="text-base font-semibold">Warum war der Vorschlag nicht hilfreich?</h3>
                <label class="mt-4 block text-xs font-medium text-gray-500">Kategorie *</label>
                <select wire:model="feedbackCategory" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                    <option value="">Bitte wählen …</option>
                    @foreach (\App\Livewire\ShiftDispatch::FEEDBACK_CATEGORIES as $category)
                        <option value="{{ $category }}">{{ $category }}</option>
                    @endforeach
                </select>
                @error('feedbackCategory') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                <label class="mt-3 block text-xs font-medium text-gray-500">Notiz (optional)</label>
                <textarea wire:model="feedbackComment" rows="2" class="mt-1 w-full rounded-lg border-gray-300 text-sm"></textarea>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" wire:click="$set('showFeedbackModal', false)"
                        class="rounded-lg border px-3 py-1.5 text-sm">Abbrechen</button>
                    <button type="button" wire:click="submitFeedback"
                        class="rounded-lg bg-gray-900 px-3 py-1.5 text-sm font-semibold text-white">Feedback speichern</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Rollback-Modal --}}
    @if ($showRollbackModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                <h3 class="text-base font-semibold">Zuweisung zurückrollen (Forward-Rollback)</h3>
                <p class="mt-1 text-xs text-gray-500">Es wird ein neues Rollback-Event angehängt – die Historie bleibt erhalten.</p>
                <label class="mt-4 block text-xs font-medium text-gray-500">Rückrollgrund *</label>
                <input type="text" wire:model="rollbackReason" class="mt-1 w-full rounded-lg border-gray-300 text-sm"
                    placeholder="z. B. Mitarbeiter erneut erkrankt" />
                @error('rollbackReason') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                <label class="mt-3 block text-xs font-medium text-gray-500">Storno-Nachricht (optional)</label>
                <textarea wire:model="cancellationMessage" rows="2" class="mt-1 w-full rounded-lg border-gray-300 text-sm"></textarea>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" wire:click="$set('showRollbackModal', false)"
                        class="rounded-lg border px-3 py-1.5 text-sm">Abbrechen</button>
                    <button type="button" wire:click="submitRollback"
                        class="rounded-lg bg-red-600 px-3 py-1.5 text-sm font-semibold text-white">Rollback ausführen</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Ledger-Historie --}}
    <div>
        <h3 class="text-sm font-semibold text-gray-700">Zustandshistorie (Forward-Ledger)</h3>
        @if ($auditEvents->isEmpty())
            <p class="mt-1 text-xs text-gray-500">Noch keine Ereignisse protokolliert.</p>
        @else
            <ul class="mt-2 space-y-2">
                @foreach ($auditEvents as $event)
                    <li class="rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-600" wire:key="event-{{ $event->id }}">
                        <span class="font-semibold text-gray-800">#{{ $event->version }} {{ $event->event_type->label() }}</span>
                        <span class="text-gray-400">· {{ $event->created_at->format('d.m.Y H:i') }}</span>
                        @if (($event->new_state['rollback_reason'] ?? null))
                            <div>Grund: {{ $event->new_state['rollback_reason'] }}</div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
