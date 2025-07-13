<?php

use App\Http\Controllers\ABTestingController;
use App\Http\Controllers\AutoBettingController;
use App\Http\Controllers\PredictionController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// 预测系统页面路由
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/prediction', function () {
        return Inertia::render('Prediction');
    })->name('prediction');

    Route::get('/auto-betting', function () {
        return Inertia::render('AutoBetting');
    })->name('auto-betting');

    Route::get('/backtest-dashboard', function () {
        return Inertia::render('BacktestDashboard');
    })->name('backtest-dashboard');

    Route::get('/ab-testing-dashboard', function () {
        return Inertia::render('ABTestingDashboard');
    })->name('ab-testing-dashboard');
});

// 已移除的旧路由 - 重定向到新页面
Route::prefix('game')->name('game.')->group(function () {
    Route::get('/current-analysis', function () {
        return redirect()->route('prediction');
    })->name('current-analysis');

    Route::get('/prediction-history', function () {
        return redirect()->route('prediction');
    })->name('prediction-history');

    Route::get('/hybrid-analysis', function () {
        return redirect()->route('prediction');
    })->name('hybrid-analysis');
});

require __DIR__.'/auth.php';
