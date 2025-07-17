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
    return Inertia::render('AutoBetting');
})->name('dashboard');

// 预测系统页面路由 - 已移除，功能整合到主Dashboard

Route::get('/backtest-dashboard', function () {
    return Inertia::render('BacktestDashboard');
})->name('backtest-dashboard');

Route::get('/ab-testing-dashboard', function () {
    return Inertia::render('ABTestingDashboard');
})->name('ab-testing-dashboard');




