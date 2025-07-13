<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AutoBettingController;
use App\Http\Controllers\WebSocketController;
use App\Http\Controllers\GameDataController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// 首页路由
Route::get('/', function () {
    return Inertia::render('Dashboard');
})->name('dashboard');

// 自动下注控制页面路由
Route::get('/auto-betting', [AutoBettingController::class, 'index'])->name('auto-betting');

// 新一代预测系统页面路由
Route::get('/prediction', function () {
    return Inertia::render('Prediction');
})->name('prediction');

// 回測分析中心頁面路由
Route::get('/backtest', function () {
    return Inertia::render('BacktestDashboard');
})->name('backtest');

// WebSocket 相关路由
Route::prefix('websocket')->name('websocket.')->group(function () {
    Route::post('/broadcast/game-data', [WebSocketController::class, 'broadcastGameData'])->name('broadcast.game-data');
    Route::post('/broadcast/prediction', [WebSocketController::class, 'broadcastPrediction'])->name('broadcast.prediction');
    Route::post('/notification', [WebSocketController::class, 'sendUserNotification'])->name('notification');
});

// 游戏数据相关路由
Route::prefix('game')->name('game.')->group(function () {
    Route::get('/current-analysis', [GameDataController::class, 'getCurrentAnalysis'])->name('current-analysis');
    Route::get('/prediction-history', [GameDataController::class, 'getPredictionHistory'])->name('prediction-history');
    Route::get('/hybrid-analysis', [GameDataController::class, 'getHybridAnalysis'])->name('hybrid-analysis');
});

Route::get('/ab-testing', function () {
    return Inertia::render('ABTestingDashboard');
})->middleware(['auth', 'verified'])->name('ab-testing');

require __DIR__.'/auth.php';
