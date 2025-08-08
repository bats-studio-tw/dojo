<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;
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

// 预测展示页面路由 - 纯展示用
Route::get('/views', function () {
    return Inertia::render('PredictionView');
})->name('prediction.view');

// v3 特征排名页（每个特征一个排行榜）
Route::get('/feature-rankings', function () {
    return Inertia::render('FeatureRankings');
})->name('feature.rankings');
