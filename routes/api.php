<?php

use App\Http\Controllers\GameDataController;
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
    Route::get('/current-analysis', [GameDataController::class, 'getCurrentRoundAnalysis'])->name('api.game.current-analysis');
    // 保留旧API以防兼容性问题
    Route::get('/market-data', [GameDataController::class, 'getTokenMarketData'])->name('api.game.market-data');
    Route::get('/prediction', [GameDataController::class, 'getCurrentRoundAnalysis'])->name('api.game.prediction');
});
