<?php

use App\Http\Controllers\GameDataController;
use App\Http\Controllers\PredictionAnalysisController;
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

// 游戏数据API路由组
Route::prefix('game')->group(function () {
    Route::get('/history', [GameDataController::class, 'getHistoryData'])->name('api.game.history');
    Route::get('/prediction-history', [GameDataController::class, 'getPredictionHistory'])->name('api.game.prediction-history');
    Route::get('/current-analysis', [GameDataController::class, 'getCurrentRoundAnalysis'])->name('api.game.current-analysis');
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
