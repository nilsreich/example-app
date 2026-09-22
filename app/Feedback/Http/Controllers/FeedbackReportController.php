<?php

namespace App\Feedback\Http\Controllers;

use App\Feedback\Models\FeedbackReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Annahme von In-App-Feedback (POST-Endpoint des Widgets).
 */
final class FeedbackReportController
{
    private const MAX_SCREENSHOT_BYTES = 2_500_000;

    public function store(Request $request): JsonResponse
    {
        abort_unless(config('feedback.enabled', true), 404);

        $validated = $request->validate([
            // Kategorien sind über config/feedback.php konfigurierbar.
            'category' => ['required', Rule::in(config('feedback.categories', ['bug', 'idea', 'question', 'other']))],
            'message' => ['required', 'string', 'min:3', 'max:2000'],
            'page_url' => [
                'required',
                'string',
                'max:2048',
                'url',
                fn (string $attribute, mixed $value, \Closure $fail): mixed => preg_match('~^https?://~i', (string) $value)
                    ? null
                    : $fail('Die Seite muss eine gültige http(s)-URL sein.'),
            ],
            'page_title' => ['nullable', 'string', 'max:500'],
            'element_selector' => ['nullable', 'string', 'max:500'],
            'element_text' => ['nullable', 'string', 'max:500'],
            'browser_info' => ['nullable', 'array'],
            'screenshot' => ['nullable', 'string'],
        ]);

        $report = FeedbackReport::create([
            'user_id' => $request->user()->id,
            'category' => $validated['category'],
            'message' => $validated['message'],
            'page_url' => $validated['page_url'],
            'page_title' => $validated['page_title'] ?? null,
            'element_selector' => $validated['element_selector'] ?? null,
            'element_text' => $validated['element_text'] ?? null,
            'browser_info' => $validated['browser_info'] ?? null,
            'screenshot_path' => isset($validated['screenshot']) ? $this->storeScreenshot($validated['screenshot']) : null,
        ]);

        return response()->json([
            'ok' => true,
            'id' => $report->id,
            'message' => 'Danke! Dein Feedback ist eingegangen.',
        ], 201);
    }

    /**
     * Nimmt eine Base64-Data-URL (jpeg/png) an, prüft Echtheit und Größe
     * und legt sie privat unter storage/app/private/feedback/ ab.
     */
    private function storeScreenshot(string $dataUrl): string
    {
        if (! preg_match('/^data:image\/(jpeg|png);base64,/', $dataUrl)) {
            throw ValidationException::withMessages(['screenshot' => 'Ungültiges Bildformat.']);
        }

        $binary = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);

        if ($binary === false) {
            throw ValidationException::withMessages(['screenshot' => 'Ungültige Bilddaten.']);
        }

        if (strlen($binary) > self::MAX_SCREENSHOT_BYTES) {
            throw ValidationException::withMessages(['screenshot' => 'Screenshot ist zu groß (max. 2,5 MB).']);
        }

        $info = getimagesizefromstring($binary);

        if ($info === false) {
            throw ValidationException::withMessages(['screenshot' => 'Keine gültige Bilddatei.']);
        }

        $extension = match ($info[2]) {
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_JPEG => 'jpg',
            default => throw ValidationException::withMessages(['screenshot' => 'Nur JPEG/PNG werden unterstützt.']),
        };
        $path = 'feedback/'.Str::ulid().'.'.$extension;

        if (! Storage::disk('local')->put($path, $binary)) {
            throw ValidationException::withMessages(['screenshot' => 'Screenshot konnte nicht gespeichert werden.']);
        }

        return $path;
    }
}
