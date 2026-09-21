@props([])

@php
    // Feature-Toggle: nur wenn im Admin aktiviert UND Nutzer angemeldet.
    $feedbackEnabled = auth()->check() && \App\Models\Setting::feedbackWidgetEnabled();
@endphp

@if ($feedbackEnabled)
    @vite('resources/js/feedback-widget.js')

    <div data-feedback-widget data-fw-endpoint="{{ route('feedback.store') }}" class="fw-root">
        <button type="button" data-fw-open aria-expanded="false" class="fw-fab" title="Feedback geben">
            <span aria-hidden="true">💬</span>
            <span class="fw-fab-label">Feedback</span>
        </button>

        <div data-fw-panel hidden class="fw-panel" role="dialog" aria-label="In-App-Feedback">
            <div class="fw-header">
                <strong>Feedback geben</strong>
                <button type="button" data-fw-close class="fw-close" aria-label="Schließen">×</button>
            </div>

            <div class="fw-body">
                <label class="fw-label" for="fw-category">Kategorie</label>
                <select id="fw-category" data-fw-category class="fw-input">
                    @foreach (\App\Enums\FeedbackCategory::cases() as $category)
                        <option value="{{ $category->value }}">{{ $category->label() }}</option>
                    @endforeach
                </select>

                <label class="fw-label" for="fw-message">Beschreibung</label>
                <textarea id="fw-message" data-fw-message rows="4" class="fw-input"
                    placeholder="Was ist passiert? Was hast du erwartet?"></textarea>

                <div class="fw-tools">
                    <button type="button" data-fw-pick class="fw-tool">🎯 Element markieren</button>
                    <button type="button" data-fw-shot class="fw-tool">📷 Screenshot</button>
                    <label class="fw-tool fw-file">
                        🖼️ Bild wählen
                        <input type="file" accept="image/png,image/jpeg" data-fw-file hidden />
                    </label>
                </div>

                <p data-fw-element-info class="fw-hint">Kein Element markiert.
                    <button type="button" data-fw-clear-element class="fw-clear">zurücksetzen</button>
                </p>

                <div data-fw-preview hidden class="fw-preview">
                    <img data-fw-preview-image alt="Screenshot-Vorschau" />
                    <button type="button" data-fw-remove-shot class="fw-clear">Screenshot entfernen</button>
                </div>

                <p data-fw-status class="fw-status" aria-live="polite"></p>
            </div>

            <div class="fw-footer">
                <button type="button" data-fw-submit class="fw-submit" disabled>Feedback senden</button>
            </div>
        </div>
    </div>

    @once
        <style>
            /* Bewusst eigene, isolierte Styles: funktionieren im App-Layout UND im Filament-Panel. */
            .fw-root { position: fixed; right: 1.25rem; bottom: 1.25rem; z-index: 60; font-family: inherit; }
            .fw-fab { display: inline-flex; align-items: center; gap: .5rem; border-radius: 9999px;
                background: #18181b; color: #fff; padding: .625rem 1rem; font-size: .875rem; font-weight: 600;
                box-shadow: 0 10px 25px -5px rgb(0 0 0 / .35); cursor: pointer; border: 0; }
            .fw-fab:hover { background: #3f3f46; }
            .fw-panel { position: absolute; right: 0; bottom: 3.5rem; width: min(24rem, calc(100vw - 2.5rem));
                background: #fff; color: #18181b; border: 1px solid #e4e4e7; border-radius: .75rem;
                box-shadow: 0 25px 50px -12px rgb(0 0 0 / .35); overflow: hidden; }
            .fw-header { display: flex; align-items: center; justify-content: space-between;
                padding: .75rem 1rem; border-bottom: 1px solid #f4f4f5; }
            .fw-close { border: 0; background: transparent; font-size: 1.25rem; line-height: 1; cursor: pointer; color: #71717a; }
            .fw-body { display: flex; flex-direction: column; gap: .5rem; padding: 1rem; }
            .fw-label { font-size: .75rem; font-weight: 600; color: #52525b; }
            .fw-input { width: 100%; border: 1px solid #d4d4d8; border-radius: .5rem; padding: .5rem .625rem;
                font-size: .875rem; background: #fff; color: inherit; }
            .fw-input:focus { outline: 2px solid #f59e0b; outline-offset: 1px; }
            .fw-tools { display: flex; flex-wrap: wrap; gap: .5rem; }
            .fw-tool { border: 1px solid #d4d4d8; border-radius: .5rem; background: #fafafa; padding: .375rem .625rem;
                font-size: .8125rem; cursor: pointer; }
            .fw-tool:hover, .fw-tool.fw-active { background: #fef3c7; border-color: #f59e0b; }
            .fw-hint, .fw-status { font-size: .75rem; color: #71717a; margin: 0; }
            .fw-status.fw-error { color: #dc2626; }
            .fw-clear { border: 0; background: transparent; color: #71717a; text-decoration: underline;
                font-size: .75rem; cursor: pointer; padding: 0; }
            .fw-preview img { width: 100%; border-radius: .5rem; border: 1px solid #e4e4e7; }
            .fw-footer { padding: .75rem 1rem; border-top: 1px solid #f4f4f5; background: #fafafa; text-align: right; }
            .fw-submit { border: 0; border-radius: .5rem; background: #18181b; color: #fff; padding: .5rem .875rem;
                font-size: .875rem; font-weight: 600; cursor: pointer; }
            .fw-submit:disabled { opacity: .5; cursor: not-allowed; }
            .fw-root.fw-picking ~ * { cursor: crosshair; }
        </style>
    @endonce
@endif
