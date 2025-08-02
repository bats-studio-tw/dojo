<?php

return [
    /*
    |--------------------------------------------------------------------------
    | 预测系统配置
    |--------------------------------------------------------------------------
    |
    | 这里定义了预测系统的各种配置，包括策略、特征、标准化方法等
    |
    */

    // 默认数据提供者
    'default_data_provider' => \App\Services\Prediction\Providers\DexScreenerProvider::class,

    // 启用的特征
    'features' => [
        'elo' => true,
        'momentum' => true,
        'short_term_momentum' => true,  // 新增短期动能特征
    ],

    // 特征标准化方法
    'feature_normalization' => [
        'elo' => 'z-score',
        'momentum' => 'min-max',
        'short_term_momentum' => 'min-max',  // 短期动能使用min-max标准化
    ],

    // 预测策略配置
    'strategies' => [
        'conservative' => [
            'name' => '保守型策略',
            'description' => '采用70% Elo评分和30%动量分数，适合稳健投资',
            'weights' => [
                'elo' => 0.7,
                'momentum' => 0.3,
            ],
            'feature_normalization' => [
                'elo' => 'z-score',
                'momentum' => 'min-max',
            ],
            'risk_level' => 'low',
        ],

        'aggressive' => [
            'name' => '进攻型策略',
            'description' => '采用30% Elo评分和70%动量分数，适合激进投资',
            'weights' => [
                'elo' => 0.3,
                'momentum' => 0.7,
            ],
            'feature_normalization' => [
                'elo' => 'z-score',
                'momentum' => 'min-max',
            ],
            'risk_level' => 'high',
        ],

        'balanced' => [
            'name' => '平衡型策略',
            'description' => '采用50% Elo评分和50%动量分数，平衡风险收益',
            'weights' => [
                'elo' => 0.5,
                'momentum' => 0.5,
            ],
            'feature_normalization' => [
                'elo' => 'z-score',
                'momentum' => 'min-max',
            ],
            'risk_level' => 'medium',
        ],

        'momentum' => [
            'name' => '动量策略',
            'description' => '专注于动量指标，适合趋势跟踪',
            'weights' => [
                'elo' => 0.0,
                'momentum' => 1.0,
            ],
            'feature_normalization' => [
                'elo' => 'z-score',
                'momentum' => 'min-max',
            ],
            'risk_level' => 'high',
        ],

        'elo' => [
            'name' => 'Elo评分策略',
            'description' => '专注于Elo评分系统，适合长期投资',
            'weights' => [
                'elo' => 1.0,
                'momentum' => 0.0,
            ],
            'feature_normalization' => [
                'elo' => 'z-score',
                'momentum' => 'min-max',
            ],
            'risk_level' => 'low',
        ],



        'short_term' => [
            'name' => '短期动能策略',
            'description' => '专注于短期价格动能，适合30秒游戏预测',
            'weights' => [
                'elo' => 0.2,
                'momentum' => 0.2,
                'short_term_momentum' => 0.6,  // 主要依赖短期动能
            ],
            'feature_normalization' => [
                'elo' => 'z-score',
                'momentum' => 'min-max',
                'short_term_momentum' => 'min-max',
            ],
            'risk_level' => 'high',
        ],

        'hybrid_momentum' => [
            'name' => '混合动能策略',
            'description' => '结合长期和短期动能，平衡趋势和即时性',
            'weights' => [
                'elo' => 0.3,
                'momentum' => 0.3,
                'short_term_momentum' => 0.4,
            ],
            'feature_normalization' => [
                'elo' => 'z-score',
                'momentum' => 'min-max',
                'short_term_momentum' => 'min-max',
            ],
            'risk_level' => 'medium',
        ],
    ],

    // 特征提供者配置
    'feature_providers' => [
        'elo' => \App\Services\Prediction\Features\EloFeatureProvider::class,
        'momentum' => \App\Services\Prediction\Features\MomentumFeatureProvider::class,
        'short_term_momentum' => \App\Services\Prediction\Features\ShortTermMomentumFeatureProvider::class,
    ],

    // 标准化策略配置
    'normalization_strategies' => [
        'z-score' => \App\Services\Prediction\Normalization\ZScoreNormalization::class,
        'min-max' => \App\Services\Prediction\Normalization\MinMaxNormalization::class,
        'identity' => \App\Services\Prediction\Normalization\IdentityNormalization::class,
    ],

    // 数据提供者配置
    'data_providers' => [
        'alchemy' => \App\Services\Prediction\Providers\AlchemyProvider::class,
        'dexscreener' => \App\Services\Prediction\Providers\DexScreenerProvider::class,
    ],

    // 缓存配置
    'cache' => [
        'enabled' => true,
        'ttl' => 300, // 5分钟
        'prefix' => 'prediction:',
    ],

    // 日志配置
    'logging' => [
        'enabled' => true,
        'level' => 'info',
        'channel' => 'prediction',
    ],

    // 性能监控配置
    'monitoring' => [
        'enabled' => true,
        'metrics' => [
            'prediction_execution_time',
            'feature_extraction_time',
            'normalization_time',
            'aggregation_time',
        ],
    ],
];
