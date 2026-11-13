<?php

use App\Http\Controllers\Api\BoothApiController;
use Illuminate\Support\Facades\Route;

// API voor photobooth-machines. Per-token auth + rate limiting.
Route::middleware(['auth.booth', 'throttle:booth'])->prefix('booth')->group(function () {
    Route::get('bookings', [BoothApiController::class, 'bookings']);
    Route::get('config', [BoothApiController::class, 'config']);
    Route::post('heartbeat', [BoothApiController::class, 'heartbeat']);
    Route::post('events', [BoothApiController::class, 'event']);
});
