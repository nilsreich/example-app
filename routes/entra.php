<?php

use App\Identity\Http\Controllers\EntraAuthController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/auth/entra', [EntraAuthController::class, 'redirect'])
        ->name('entra.redirect');

    Route::get('/auth/entra/callback', [EntraAuthController::class, 'callback'])
        ->middleware('throttle:10,1')
        ->name('entra.callback');
});
