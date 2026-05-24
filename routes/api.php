<?php

use App\Http\Controllers\Api\DataController;
use App\Http\Controllers\QcSignalApiController;
use Illuminate\Support\Facades\Route;


Route::get('/status', function () {
    return response()->json([
        'app' => 'DragonFortune API',
        'version' => app()->version(),
        'status' => 'running',
    ]);
});

Route::get('/getdata', [DataController::class, 'getData']);

Route::prefix('v1')->group(function () {
    Route::post('/qc-signals/price-notification', [QcSignalApiController::class, 'dispatchPriceNotification'])
        ->middleware('throttle:120,1')
        ->name('api.v1.qc-signals.price-notification');
});
