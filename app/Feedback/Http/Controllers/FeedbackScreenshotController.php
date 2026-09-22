<?php

namespace App\Feedback\Http\Controllers;

use App\Feedback\Models\FeedbackReport;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Liefert den privaten Screenshot aus. Auslieferung nur über diese
 * policy-geschützte Route — nie direkt aus storage/app/private.
 */
final class FeedbackScreenshotController
{
    public function __invoke(FeedbackReport $feedbackReport): Response
    {
        Gate::authorize('view', $feedbackReport);

        $contents = $feedbackReport->screenshotContents();
        abort_if($contents === null, 404);

        $isPng = str_ends_with($feedbackReport->screenshot_path, '.png');

        return response($contents, 200)
            ->header('Content-Type', $isPng ? 'image/png' : 'image/jpeg')
            ->header('Content-Disposition', 'inline; filename="feedback-'.$feedbackReport->id.'"')
            ->header('Cache-Control', 'private, max-age=300')
            ->header('X-Content-Type-Options', 'nosniff');
    }
}
