<?php

namespace App\Http\Controllers;

use App\Models\FeedbackReport;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Liefert Screenshots aus dem privaten Storage erst nach Policy-Prüfung aus.
 * Die Dateien sind damit nie über eine öffentliche URL erreichbar.
 */
class FeedbackScreenshotController extends Controller
{
    public function __invoke(FeedbackReport $feedbackReport): Response
    {
        Gate::authorize('view', $feedbackReport);

        $contents = $feedbackReport->screenshotContents();

        abort_if($contents === null, 404);

        $mime = str_ends_with((string) $feedbackReport->screenshot_path, '.png') ? 'image/png' : 'image/jpeg';

        return response($contents, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="feedback-'.$feedbackReport->id.'"',
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
