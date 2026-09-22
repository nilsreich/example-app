<?php

use App\Audit\Http\Controllers\AuditExportController;
use App\Feedback\Http\Controllers\FeedbackReportController;
use App\Feedback\Http\Controllers\FeedbackScreenshotController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
});

Route::middleware('auth')->group(function () {
    // In-App-Feedback: Entgegennahme gedrosselt, Screenshot-Auslieferung policy-geschützt.
    Route::post('feedback', [FeedbackReportController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('feedback.store');

    Route::get('feedback/{feedbackReport}/screenshot', FeedbackScreenshotController::class)
        ->name('feedback.screenshot');

    // GoBD-Export des Audit-Trails – Berechtigung via Gate `audit.export`.
    Route::get('audit/export', AuditExportController::class)->name('audit.export');
});

require __DIR__.'/settings.php';
