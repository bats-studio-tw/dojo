<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Request;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // 注册游戏数据处理服务
        $this->app->singleton(\App\Services\GameDataProcessorService::class);

        // 注册游戏预测服务
        $this->app->singleton(\App\Services\GamePredictionService::class);

        // 注册DexScreener价格客户端服务
        $this->app->singleton(\App\Services\DexPriceClient::class);

        // 注册Hybrid-Edge v1.0预测服务
        $this->app->singleton(\App\Services\EloRatingEngine::class);
        $this->app->singleton(\App\Services\ScoreMixer::class);

        // 注册WebSocket服务
        $this->app->singleton(\App\Services\GameWebSocketService::class, function ($app) {
            return new \App\Services\GameWebSocketService(
                $app->make(\App\Services\GameDataProcessorService::class),
                $app->make(\App\Services\GamePredictionService::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        if (config('app.env') === 'production') {
            URL::forceScheme('https');

            Request::setTrustedProxies(['*'], Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO);
        }

    }
}
