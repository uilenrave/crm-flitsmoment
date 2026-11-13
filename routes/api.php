<?php

use App\Http\Controllers\Api\BoothApiController;
use Illuminate\Support\Facades\Route;

// API voor photobooth-machines. Per-token auth + rate limiting.
Route::middleware(['auth.booth', 'throttle:booth'])->prefix('booth')->group(function () {
    Route::get('bookings', [BoothApiController::class, 'bookings']);
    Route::get('bookings/{id}/production-file', [BoothApiController::class, 'productionFile'])->whereNumber('id');
    Route::get('config', [BoothApiController::class, 'config']);
    Route::post('heartbeat', [BoothApiController::class, 'heartbeat']);
    Route::get('update/latest.yml', [BoothApiController::class, 'updateFeed']);
    Route::get('update/v/{version}/latest.yml', [BoothApiController::class, 'updateFeed'])->where('version', '[0-9A-Za-z.+\-]+');
    Route::get('update/files/{filename}', [BoothApiController::class, 'updateFile'])->where('filename', '[A-Za-z0-9._\-]+');
    Route::post('events', [BoothApiController::class, 'event']);
});
