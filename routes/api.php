<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Tools\BatteryToolController;
use App\Http\Controllers\Api\Tools\PhoneToolController;
use App\Http\Controllers\Api\Tools\ReminderController;
use App\Http\Controllers\Api\Tools\VoiceToolController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('tools')->group(function () {
    Route::post('voice/v1/wake-word', [VoiceToolController::class, 'detectWakeWord']);
    Route::post('voice/v2/transcribe', [VoiceToolController::class, 'transcribe']);
    Route::post('voice/v3/synthesize', [VoiceToolController::class, 'synthesize']);

    // These commands are returned to the mobile client, which performs the
    // requested device action after checking its own permissions.
    Route::post('phone/commands', [PhoneToolController::class, 'execute']);

    Route::post('battery/report', [BatteryToolController::class, 'report']);

    Route::apiResource('reminders', ReminderController::class)
        ->only(['index', 'store', 'show', 'update', 'destroy']);
});
