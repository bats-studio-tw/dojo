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

// WebSocket 测试页面
Route::get('/websocket-test', function () {
    return Inertia::render('WebSocketTest');
})->name('websocket-test');

// WebSocket 相关路由
Route::prefix('websocket')->name('websocket.')->group(function () {
    Route::post('/broadcast/game-data', [WebSocketController::class, 'broadcastGameData'])->name('broadcast.game-data');
    Route::post('/broadcast/prediction', [WebSocketController::class, 'broadcastPrediction'])->name('broadcast.prediction');
    Route::post('/notification', [WebSocketController::class, 'sendUserNotification'])->name('notification');
    Route::get('/latest-data', [WebSocketController::class, 'getLatestData'])->name('latest-data');
    Route::get('/status', [WebSocketController::class, 'connectionStatus'])->name('status');
});

// 添加测试WebSocket广播的路由
Route::get('/test-websocket-prediction', function () {
    try {
        // 模拟预测数据
        $testPredictionData = [
            [
                'symbol' => 'TEST1',
                'predicted_rank' => 1,
                'prediction_score' => 85.5,
                'win_rate' => 25.0,
                'top3_rate' => 65.0,
                'avg_rank' => 2.1
            ],
            [
                'symbol' => 'TEST2',
                'predicted_rank' => 2,
                'prediction_score' => 72.3,
                'win_rate' => 15.0,
                'top3_rate' => 45.0,
                'avg_rank' => 2.8
            ]
        ];

        // 构造与current-analysis API相同的数据结构
        $broadcastData = [
            'success' => true,
            'data' => $testPredictionData,
            'meta' => [
                'round_id' => 'test-round-' . time(),
                'status' => 'bet',
                'current_tokens' => ['TEST1', 'TEST2'],
                'analysis_rounds_count' => 20,
                'prediction_algorithm' => 'test_algorithm_v1.0',
                'algorithm_description' => '测试算法',
                'timestamp' => now()->toISOString(),
                'generated_at' => now()->toISOString(),
                'source' => 'manual_test'
            ]
        ];

        // 广播预测数据更新事件
        broadcast(new \App\Events\PredictionUpdated($broadcastData, 'test-round-' . time(), 'current_analysis'));

        return response()->json([
            'success' => true,
            'message' => '测试预测数据已广播',
            'data' => $broadcastData
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => '广播失败: ' . $e->getMessage()
        ], 500);
    }
});

require __DIR__.'/auth.php';
