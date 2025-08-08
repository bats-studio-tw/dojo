<?php

return [
    'enabled' => env('PREDICTION_V3_ENABLED', true),
    'cache_ttl' => env('PREDICTION_V3_CACHE_TTL', 60),
    // Provider 列表（可根据需要增减）
    'providers' => [
        App\Services\Prediction\Features\EloFeatureProvider::class,
        App\Services\Prediction\Features\MomentumFeatureProvider::class,
        App\Services\Prediction\Features\ShortTermMomentumFeatureProvider::class,
        App\Services\Prediction\Features\PTop3FromEloFeatureProvider::class,
        // 新增的短窗与稳定性类特征
        App\Services\Prediction\Features\StReturnFeatureProvider::class,
        App\Services\Prediction\Features\StTrendFeatureProvider::class,
        App\Services\Prediction\Features\StVolatilityFeatureProvider::class,
        App\Services\Prediction\Features\DrawdownShortFeatureProvider::class,
        App\Services\Prediction\Features\EloProbDecayedFeatureProvider::class,
    ],
];


