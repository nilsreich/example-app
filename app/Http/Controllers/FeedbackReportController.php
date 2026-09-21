<?php

namespace App\Http\Controllers;

use App\Enums\FeedbackCategory;
use App\Models\FeedbackReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Nimmt In-App-Feedback entgegen (JSON, authentifiziert).
 * Alles bleibt lokal: Meldung + Kontext in der DB, Screenshot auf dem
 * privaten "local"-Disk. Keine externen Dienste, keine CDNs.
 */
class FeedbackReportController extends Controller
{
    /**
     * Maximale Screenshot-Größe (dekodiert) in Bytes.
     */
    private const MAX_SCREENSHOT_BYTES = 2_500_000;

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => ['required', Rule::enum(FeedbackCategory::class)],
            'message' => ['required', 'string', 'min:3', 'max:2000'],
            'page_url' => ['required', 'string', 'max:2048'],
            'page_title' => ['nullable', 'string', 'max:500'],
            'element_selector' => ['nullable', 'string', 'max:500'],
            'element_text' => ['nullable', 'string', 'max:500'],
            'browser_info' => ['nullable', 'array'],
            // Data-URL eines JPEG/PNG-Screenshots (Client-Capture, z. B. getDisplayMedia).
            'screenshot' => ['nullable', 'string'],
        ]);

        $report = new FeedbackReport([
            'user_id' => $request->user()->id,
            'category' => $validated['category'],
            'message' => $validated['message'],
            'page_url' => $validated['page_url'],
            'page_title' => $validated['page_title'] ?? null,
            'element_selector' => $validated['element_selector'] ?? null,
            'element_text' => $validated['element_text'] ?? null,
            'browser_info' => $validated['browser_info'] ?? null,
        ]);

        if (! empty($validated['screenshot'])) {
            $report->screenshot_path = $this->storeScreenshot($validated['screenshot']);
        }

        $report->save();

        return response()->json([
            'ok' => true,
            'id' => $report->id,
            'message' => 'Danke! Dein Feedback ist eingegangen.',
        ], 201);
    }

    /**
     * Dekodiert und validiert den Screenshot strikt (kein Blind-Vertrauen
     * in den Data-URL-Header: echte Bilddaten werden per getimagesizefromstring geprüft).
     */
    private function storeScreenshot(string $dataUrl): string
    {
        if (! preg_match('/^data:image\/(jpeg|png);base64,(?<data>.+)$/s', $dataUrl, $matches)) {
            throw ValidationException::withMessages(['screenshot' => 'Ungültiges Bildformat (nur JPEG/PNG).']);
        }

        $binary = base64_decode($matches['data'], strict: true);

        if ($binary === false || $binary === '') {
            throw ValidationException::withMessages(['screenshot' => 'Screenshot konnte nicht dekodiert werden.']);
        }

        if (strlen($binary) > self::MAX_SCREENSHOT_BYTES) {
            throw ValidationException::withMessages(['screenshot' => 'Screenshot ist zu groß (max. 2,5 MB).']);
        }

        $imageInfo = @getimagesizefromstring($binary);

        if ($imageInfo === false || ! in_array($imageInfo[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            throw ValidationException::withMessages(['screenshot' => 'Datei ist kein gültiges Bild.']);
        }

        $extension = $imageInfo[2] === IMAGETYPE_PNG ? '.png' : '.jpg';
        $path = 'feedback/'.Str::ulid().$extension;

        Storage::disk('local')->put($path, $binary);

        return $path;
    }
}
