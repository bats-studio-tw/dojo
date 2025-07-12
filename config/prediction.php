<?php

return [
    /*
    |--------------------------------------------------------------------------
    | 默认数据提供者
    |--------------------------------------------------------------------------
    |
    | 指定默认使用的市场数据提供者
    |
    */
    'default_data_provider' => \App\Services\Prediction\Providers\DexScreenerProvider::class,

    /*
    |--------------------------------------------------------------------------
    | 启用的特征
    |--------------------------------------------------------------------------
    |
    | 指定哪些特征计算器将被启用
    |
    */
    'features' => [
        'elo' => true,
        'momentum' => true,
        'volume' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | 特征标准化策略
    |--------------------------------------------------------------------------
    |
    | 为每个特征指定标准化策略
    |
    */
    'feature_normalization' => [
        'elo' => \App\Services\Prediction\Normalization\ZScoreNormalization::class,
        'momentum' => \App\Services\Prediction\Normalization\MinMaxNormalization::class,
        'volume' => \App\Services\Prediction\Normalization\MinMaxNormalization::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | 预测策略配置
    |--------------------------------------------------------------------------
    |
    | 定义不同的预测策略及其参数
    |
    */
    'strategies' => [
        'conservative' => [
            'weights' => [
                'elo' => 0.7,
                'momentum' => 0.2,
                'volume' => 0.1,
            ],
            'normalization' => [
                'elo' => \App\Services\Prediction\Normalization\ZScoreNormalization::class,
                'momentum' => \App\Services\Prediction\Normalization\MinMaxNormalization::class,
                'volume' => \App\Services\Prediction\Normalization\MinMaxNormalization::class,
            ],
        ],

        'aggressive' => [
            'weights' => [
                'elo' => 0.3,
                'momentum' => 0.6,
                'volume' => 0.1,
            ],
            'normalization' => [
                'elo' => \App\Services\Prediction\Normalization\ZScoreNormalization::class,
                'momentum' => \App\Services\Prediction\Normalization\MinMaxNormalization::class,
                'volume' => \App\Services\Prediction\Normalization\MinMaxNormalization::class,
            ],
        ],

        'balanced' => [
            'weights' => [
                'elo' => 0.5,
                'momentum' => 0.3,
                'volume' => 0.2,
            ],
            'normalization' => [
                'elo' => \App\Services\Prediction\Normalization\ZScoreNormalization::class,
                'momentum' => \App\Services\Prediction\Normalization\MinMaxNormalization::class,
                'volume' => \App\Services\Prediction\Normalization\MinMaxNormalization::class,
            ],
        ],

        'volume_focused' => [
            'weights' => [
                'elo' => 0.2,
                'momentum' => 0.2,
                'volume' => 0.6,
            ],
            'normalization' => [
                'elo' => \App\Services\Prediction\Normalization\ZScoreNormalization::class,
                'momentum' => \App\Services\Prediction\Normalization\MinMaxNormalization::class,
                'volume' => \App\Services\Prediction\Normalization\MinMaxNormalization::class,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 默认策略
    |--------------------------------------------------------------------------
    |
    | 指定默认使用的策略
    |
    */
    'default_strategy' => 'conservative',

    /*
    |--------------------------------------------------------------------------
    | 回测配置
    |--------------------------------------------------------------------------
    |
    | 回测相关的配置参数
    |
    */
    'backtest' => [
        'min_rounds' => 10,
        'max_rounds' => 1000,
        'default_rounds' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | 网格搜索参数矩阵
    |--------------------------------------------------------------------------
    |
    | 用于参数优化的网格搜索配置
    |
    */
    'grid_search' => [
        'weights' => [
            'elo' => [0.3, 0.5, 0.7, 0.9],
            'momentum' => [0.1, 0.3, 0.5, 0.7],
            'volume' => [0.1, 0.2, 0.3, 0.4],
        ],
        'normalization' => [
            'elo' => [
                \App\Services\Prediction\Normalization\ZScoreNormalization::class,
                \App\Services\Prediction\Normalization\MinMaxNormalization::class,
            ],
            'momentum' => [
                \App\Services\Prediction\Normalization\ZScoreNormalization::class,
                \App\Services\Prediction\Normalization\MinMaxNormalization::class,
            ],
            'volume' => [
                \App\Services\Prediction\Normalization\ZScoreNormalization::class,
                \App\Services\Prediction\Normalization\MinMaxNormalization::class,
            ],
        ],
    ],
];
