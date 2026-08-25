<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Tools\BatteryToolController;
use App\Http\Controllers\Api\Tools\PhoneToolController;
use App\Http\Controllers\Api\Tools\RobotToolController;
use App\Http\Controllers\Api\Tools\TimeToolController;
use App\Http\Controllers\Api\Tools\VoiceToolController;
use App\Http\Controllers\Api\AiCore\AiCoreController;
use App\Http\Controllers\Api\AiCore\CommandController;
use App\Http\Controllers\Api\AiCore\ConversationController;
use App\Http\Controllers\Api\AiCore\IntentController;
use App\Http\Controllers\Api\AiCore\MemoryController;
use App\Http\Controllers\Api\AiCore\ToolFlowchartController;

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

    // P1-P5 commands are returned to the mobile client after it checks permissions.
    Route::post('phone/p1/open-app', [PhoneToolController::class, 'openApp']);
    Route::post('phone/p2/calls', [PhoneToolController::class, 'phoneCall']);
    Route::post('phone/p3/contacts', [PhoneToolController::class, 'contacts']);
    Route::post('phone/p4/device-actions', [PhoneToolController::class, 'deviceAction']);
    Route::post('phone/p5/notifications', [PhoneToolController::class, 'notifications']);

    Route::post('battery/b1/charging-started', [BatteryToolController::class, 'chargingStarted']);
    Route::post('battery/b2/charging-complete', [BatteryToolController::class, 'chargingComplete']);
    Route::post('battery/b3/low-battery', [BatteryToolController::class, 'lowBattery']);
    Route::post('battery/b4/charger-removed', [BatteryToolController::class, 'chargerRemoved']);

    Route::post('time/t1/reminders', [TimeToolController::class, 'setReminder']);
    Route::post('time/t2/alarms', [TimeToolController::class, 'setAlarm']);
    Route::get('time/t3/date-time', [TimeToolController::class, 'dateTime']);

    Route::post('robot/r1/idle', [RobotToolController::class, 'idle']);
    Route::post('robot/r2/talking', [RobotToolController::class, 'talking']);
    Route::post('robot/r3/wake', [RobotToolController::class, 'wake']);
});

Route::prefix('ai-core')->group(function () {
    Route::post('chat', [AiCoreController::class, 'chat']);

    Route::prefix('conversation')->group(function () {
        Route::post('start', [ConversationController::class, 'start']);
        Route::get('list', [ConversationController::class, 'list']);
        Route::get('{id}/messages', [ConversationController::class, 'show']);
        Route::delete('{id}', [ConversationController::class, 'destroy']);
    });

    Route::prefix('command')->group(function () {
        Route::post('parse', [CommandController::class, 'parse']);
        Route::post('execute', [CommandController::class, 'execute']);
    });

    Route::prefix('memory')->group(function () {
        Route::post('store', [MemoryController::class, 'store']);
        Route::get('retrieve', [MemoryController::class, 'retrieve']);
        Route::get('search', [MemoryController::class, 'search']);
        Route::get('list', [MemoryController::class, 'index']);
        Route::delete('{id}', [MemoryController::class, 'destroy']);
    });

    Route::prefix('intent')->group(function () {
        Route::post('detect', [IntentController::class, 'detect']);
        Route::get('list', [IntentController::class, 'index']);
    });

    Route::prefix('tools')->group(function () {
        Route::post('execute', [ToolFlowchartController::class, 'execute']);
        Route::post('resolve', [ToolFlowchartController::class, 'resolve']);
        Route::get('flowchart', [ToolFlowchartController::class, 'flowchart']);
    });
});
