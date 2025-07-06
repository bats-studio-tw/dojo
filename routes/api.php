<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GameDataController;
use App\Http\Controllers\AutoBettingController;
use App\Http\Controllers\PredictionAnalysisController;

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

// 游戏数据API路由组
Route::prefix('game')->group(function () {
    Route::get('/prediction-history', [GameDataController::class, 'getPredictionHistory'])->name('api.game.prediction-history');
    Route::get('/current-analysis', [GameDataController::class, 'getCurrentRoundAnalysis'])->name('api.game.current-analysis');
    Route::get('/hybrid-predictions', [GameDataController::class, 'getHybridPredictions'])->name('api.game.hybrid-predictions');
    Route::get('/hybrid-analysis', [GameDataController::class, 'getHybridAnalysis'])->name('api.game.hybrid-analysis');
    Route::get('/momentum-prediction-stats', [GameDataController::class, 'getMomentumPredictionStats'])->name('api.game.momentum-prediction-stats');
    Route::get('/momentum-prediction-history', [GameDataController::class, 'getMomentumPredictionHistory'])->name('api.game.momentum-prediction-history');
    // 保留旧API以防兼容性问题
    Route::get('/market-data', [GameDataController::class, 'getTokenMarketData'])->name('api.game.market-data');
    Route::get('/prediction', [GameDataController::class, 'getCurrentRoundAnalysis'])->name('api.game.prediction');
});

// 预测分析API路由组
Route::prefix('prediction-analysis')->group(function () {
    Route::get('/accuracy', [PredictionAnalysisController::class, 'getOverallAccuracy'])->name('api.prediction.accuracy');
    Route::get('/performance', [PredictionAnalysisController::class, 'getPerformanceSummary'])->name('api.prediction.performance');
    Route::get('/token/{tokenSymbol}/history', [PredictionAnalysisController::class, 'getTokenHistory'])->name('api.prediction.token-history');
});

// 自动下注API路由组
Route::prefix('auto-betting')->group(function () {
    Route::get('/user-stats', [AutoBettingController::class, 'getUserStats'])->name('api.auto-betting.user-stats');
    Route::get('/status', [AutoBettingController::class, 'getStatus'])->name('api.auto-betting.status');
    Route::post('/toggle', [AutoBettingController::class, 'toggleAutoBetting'])->name('api.auto-betting.toggle');


    Route::post('/execute', [AutoBettingController::class, 'executeAutoBetting'])->name('api.auto-betting.execute');
    Route::post('/record-result', [AutoBettingController::class, 'recordBetResult'])->name('api.auto-betting.record-result');

    // 防重复下注：检查指定轮次是否已经下过注
    Route::get('/check-round-bet', [AutoBettingController::class, 'checkRoundBet'])->name('api.auto-betting.check-round-bet');

    // 配置管理接口（基于uid，无需登录认证）
    Route::get('/config', [AutoBettingController::class, 'getConfig'])->name('api.auto-betting.config.get');
    Route::post('/config', [AutoBettingController::class, 'saveConfig'])->name('api.auto-betting.config.save');
});

// 投注表现分析API
Route::prefix('betting-analysis')->group(function () {
    Route::get('/performance', [PredictionAnalysisController::class, 'getUserBettingPerformance'])->name('api.betting-analysis.performance');
});
