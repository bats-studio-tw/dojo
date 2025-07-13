<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GameDataController;
use App\Http\Controllers\AutoBettingController;
use App\Http\Controllers\PredictionAnalysisController;
use App\Http\Controllers\PredictionController;
use App\Http\Controllers\ABTestingController;

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

// 新一代预测系统API路由组 (v2)
Route::prefix('v2')->group(function () {
    Route::post('/predictions', [PredictionController::class, 'executePrediction'])->name('api.v2.predictions.execute');
    Route::get('/strategies', [PredictionController::class, 'listStrategies'])->name('api.v2.strategies.list');
    Route::post('/backtest', [PredictionController::class, 'runBacktest'])->name('api.v2.backtest.run');
    Route::post('/backtest/async', [PredictionController::class, 'asyncBacktest'])->name('api.v2.backtest.async');
    Route::post('/backtest/grid-search', [PredictionController::class, 'gridSearchBacktest'])->name('api.v2.backtest.grid-search');
    Route::post('/backtest/batch-status', [PredictionController::class, 'getBacktestBatchStatus'])->name('api.v2.backtest.batch-status');
    Route::post('/backtest/report-detail', [PredictionController::class, 'getBacktestReportDetail'])->name('api.v2.backtest.report-detail');
    Route::get('/predictions/history', [PredictionController::class, 'getPredictionHistory'])->name('api.v2.predictions.history');
    Route::get('/strategies/performance', [PredictionController::class, 'getStrategyPerformance'])->name('api.v2.strategies.performance');

    // WebSocket 测试端点
    Route::post('/websocket/test-broadcast', [PredictionController::class, 'testWebSocketBroadcast'])->name('api.v2.websocket.test-broadcast');
});

// 旧版预测API路由组 (已弃用，保留兼容性)
Route::prefix('prediction')->group(function () {
    Route::post('/predict', [PredictionController::class, 'predict'])->name('api.prediction.predict');
    Route::post('/backtest', [PredictionController::class, 'backtest'])->name('api.prediction.backtest');
    Route::post('/grid-search', [PredictionController::class, 'gridSearch'])->name('api.prediction.grid-search');
    Route::get('/strategies', [PredictionController::class, 'getStrategies'])->name('api.prediction.strategies');
    Route::get('/history', [PredictionController::class, 'getHistory'])->name('api.prediction.history');
});

// A/B測試API路由組
Route::prefix('ab-testing')->group(function () {
    Route::post('/start', [ABTestingController::class, 'startABTest'])->name('api.ab-testing.start');
    Route::get('/list', [ABTestingController::class, 'listABTests'])->name('api.ab-testing.list');
    Route::post('/report', [ABTestingController::class, 'getABTestReport'])->name('api.ab-testing.report');
    Route::post('/stop', [ABTestingController::class, 'stopABTest'])->name('api.ab-testing.stop');
    Route::get('/active', [ABTestingController::class, 'getActiveABTests'])->name('api.ab-testing.active');
    Route::get('/detail', [ABTestingController::class, 'getABTestDetail'])->name('api.ab-testing.detail');
});
