<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AutoBettingController;
use App\Http\Controllers\WebSocketController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// 首页路由
Route::get('/', function () {
    return Inertia::render('Dashboard');
})->name('dashboard');

// 自动下注控制页面路由
Route::get('/auto-betting', [AutoBettingController::class, 'index'])->name('auto-betting');



// WebSocket 相关路由
Route::prefix('websocket')->name('websocket.')->group(function () {
    Route::post('/broadcast/game-data', [WebSocketController::class, 'broadcastGameData'])->name('broadcast.game-data');
    Route::post('/broadcast/prediction', [WebSocketController::class, 'broadcastPrediction'])->name('broadcast.prediction');
    Route::post('/notification', [WebSocketController::class, 'sendUserNotification'])->name('notification');
    Route::get('/latest-data', [WebSocketController::class, 'getLatestData'])->name('latest-data');
    Route::get('/status', [WebSocketController::class, 'connectionStatus'])->name('status');
});



require __DIR__.'/auth.php';
