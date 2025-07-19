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
        'volume' => true,
    ],

    // 特征标准化方法
    'feature_normalization' => [
        'elo' => 'z-score',
        'momentum' => 'min-max',
        'volume' => 'z-score',
    ],

    // 预测策略配置
    'strategies' => [
        'conservative' => [
            'name' => '保守型策略',
            'description' => '采用70% Elo评分和30%动量分数，适合稳健投资',
            'weights' => [
                'elo' => 0.7,
                'momentum' => 0.3,
                'volume' => 0.0,
            ],
            'feature_normalization' => [
                'elo' => 'z-score',
                'momentum' => 'min-max',
                'volume' => 'z-score',
            ],
            'risk_level' => 'low',
        ],

        'aggressive' => [
            'name' => '进攻型策略',
            'description' => '采用30% Elo评分和70%动量分数，适合激进投资',
            'weights' => [
                'elo' => 0.3,
                'momentum' => 0.7,
                'volume' => 0.0,
            ],
            'feature_normalization' => [
                'elo' => 'z-score',
                'momentum' => 'min-max',
                'volume' => 'z-score',
            ],
            'risk_level' => 'high',
        ],

        'balanced' => [
            'name' => '平衡型策略',
            'description' => '采用50% Elo评分和50%动量分数，平衡风险收益',
            'weights' => [
                'elo' => 0.5,
                'momentum' => 0.5,
                'volume' => 0.0,
            ],
            'feature_normalization' => [
                'elo' => 'z-score',
                'momentum' => 'min-max',
                'volume' => 'z-score',
            ],
            'risk_level' => 'medium',
        ],

        'momentum' => [
            'name' => '动量策略',
            'description' => '专注于动量指标，适合趋势跟踪',
            'weights' => [
                'elo' => 0.0,
                'momentum' => 1.0,
                'volume' => 0.0,
            ],
            'feature_normalization' => [
                'elo' => 'z-score',
                'momentum' => 'min-max',
                'volume' => 'z-score',
            ],
            'risk_level' => 'high',
        ],

        'elo' => [
            'name' => 'Elo评分策略',
            'description' => '专注于Elo评分系统，适合长期投资',
            'weights' => [
                'elo' => 1.0,
                'momentum' => 0.0,
                'volume' => 0.0,
            ],
            'feature_normalization' => [
                'elo' => 'z-score',
                'momentum' => 'min-max',
                'volume' => 'z-score',
            ],
            'risk_level' => 'low',
        ],

        'volume_weighted' => [
            'name' => '交易量加权策略',
            'description' => '结合交易量指标，适合市场活跃度分析',
            'weights' => [
                'elo' => 0.4,
                'momentum' => 0.4,
                'volume' => 0.2,
            ],
            'feature_normalization' => [
                'elo' => 'z-score',
                'momentum' => 'min-max',
                'volume' => 'z-score',
            ],
            'risk_level' => 'medium',
        ],
    ],

    // 特征提供者配置
    'feature_providers' => [
        'elo' => \App\Services\Prediction\Features\EloFeatureProvider::class,
        'momentum' => \App\Services\Prediction\Features\MomentumFeatureProvider::class,
        'volume' => \App\Services\Prediction\Features\VolumeFeatureProvider::class,
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
