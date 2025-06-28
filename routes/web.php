<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AutoBettingController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// 首页路由
Route::get('/', function () {
    return Inertia::render('Dashboard');
})->name('dashboard');

// 自动下注控制页面路由
Route::get('/auto-betting', [AutoBettingController::class, 'index'])->name('auto-betting');

require __DIR__.'/auth.php';
