<div class="max-w-xl space-y-4">
    @if ($notice)
        <div class="rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ $notice }}</div>
    @endif

    <label class="block text-sm font-medium text-gray-700">Aktiver Treiber</label>
    <div class="space-y-2">
        @foreach ($options as $value => $label)
            <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-gray-200 px-4 py-3 hover:bg-gray-50">
                <input type="radio" wire:model="mode" value="{{ $value }}" class="text-amber-600" />
                <span class="text-sm">{{ $label }}</span>
            </label>
        @endforeach
    </div>
    @error('mode') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

    <button type="button" wire:click="save"
        class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">
        Modus speichern
    </button>
</div>
