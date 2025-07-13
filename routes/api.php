<?php

use App\Http\Controllers\ABTestingController;
use App\Http\Controllers\AutoBettingController;
use App\Http\Controllers\PredictionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

// 新一代预测系统API路由组 (v2) - 主要API
Route::prefix('v2')->group(function () {
    // 预测相关API
    Route::post('/predictions', [PredictionController::class, 'executePrediction'])->name('api.v2.predictions.execute');
    Route::get('/predictions/current-analysis', [PredictionController::class, 'getCurrentAnalysis'])->name('api.v2.predictions.current-analysis');
    Route::get('/predictions/history', [PredictionController::class, 'getPredictionHistory'])->name('api.v2.predictions.history');
    Route::get('/predictions/hybrid', [PredictionController::class, 'getHybridPredictions'])->name('api.v2.predictions.hybrid');
    Route::get('/predictions/momentum-stats', [PredictionController::class, 'getMomentumPredictionStats'])->name('api.v2.predictions.momentum-stats');
    Route::get('/predictions/momentum-history', [PredictionController::class, 'getMomentumPredictionHistory'])->name('api.v2.predictions.momentum-history');

    // 策略相关API
    Route::get('/strategies', [PredictionController::class, 'listStrategies'])->name('api.v2.strategies.list');
    Route::get('/strategies/performance', [PredictionController::class, 'getStrategyPerformance'])->name('api.v2.strategies.performance');

    // 回测相关API
    Route::post('/backtest', [PredictionController::class, 'runBacktest'])->name('api.v2.backtest.run');
    Route::post('/backtest/async', [PredictionController::class, 'asyncBacktest'])->name('api.v2.backtest.async');
    Route::post('/backtest/grid-search', [PredictionController::class, 'gridSearchBacktest'])->name('api.v2.backtest.grid-search');
    Route::post('/backtest/batch-status', [PredictionController::class, 'getBacktestBatchStatus'])->name('api.v2.backtest.batch-status');
    Route::post('/backtest/report-detail', [PredictionController::class, 'getBacktestReportDetail'])->name('api.v2.backtest.report-detail');
    Route::get('/backtest/historical-rounds', [PredictionController::class, 'getHistoricalRounds'])->name('api.v2.backtest.historical-rounds');

    // 分析相关API
    Route::get('/analysis/accuracy', [PredictionController::class, 'getOverallAccuracy'])->name('api.v2.analysis.accuracy');
    Route::get('/analysis/performance', [PredictionController::class, 'getPerformanceSummary'])->name('api.v2.analysis.performance');
    Route::get('/analysis/token/{tokenSymbol}/history', [PredictionController::class, 'getTokenHistory'])->name('api.v2.analysis.token-history');
    Route::get('/analysis/betting-performance', [PredictionController::class, 'getUserBettingPerformance'])->name('api.v2.analysis.betting-performance');

    // 市场数据API
    Route::get('/market-data', [PredictionController::class, 'getTokenMarketData'])->name('api.v2.market-data');

    // WebSocket 测试端点
    Route::post('/websocket/test-broadcast', [PredictionController::class, 'testWebSocketBroadcast'])->name('api.v2.websocket.test-broadcast');
});

// 自动下注API路由组 (保持兼容性)
Route::prefix('auto-betting')->group(function () {
    Route::get('/user-stats', [AutoBettingController::class, 'getUserStats'])->name('api.auto-betting.user-stats');
    Route::get('/status', [AutoBettingController::class, 'getStatus'])->name('api.auto-betting.status');
    Route::post('/toggle', [AutoBettingController::class, 'toggleAutoBetting'])->name('api.auto-betting.toggle');
    Route::post('/execute', [AutoBettingController::class, 'executeAutoBetting'])->name('api.auto-betting.execute');
    Route::post('/record-result', [AutoBettingController::class, 'recordBetResult'])->name('api.auto-betting.record-result');
    Route::get('/check-round-bet', [AutoBettingController::class, 'checkRoundBet'])->name('api.auto-betting.check-round-bet');
    Route::get('/config', [AutoBettingController::class, 'getConfig'])->name('api.auto-betting.config.get');
    Route::post('/config', [AutoBettingController::class, 'saveConfig'])->name('api.auto-betting.config.save');
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

// 已移除的旧API路由 - 统一返回404错误
Route::fallback(function () {
    return response()->json([
        'error' => 'API端点不存在',
        'message' => '请使用 /api/v2 端点访问新一代预测系统API',
        'available_endpoints' => [
            '/api/v2/predictions',
            '/api/v2/strategies',
            '/api/v2/backtest',
            '/api/v2/analysis',
            '/api/auto-betting',
            '/api/ab-testing'
        ],
        'code' => 404
    ], 404);
});
