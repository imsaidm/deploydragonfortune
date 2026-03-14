<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DataController;


Route::get('/status', function () {
    return response()->json([
        'app' => 'DragonFortune API',
        'version' => app()->version(),
        'status' => 'running'
    ]);
});

Route::get('/getdata', [DataController::class, 'getData']);
