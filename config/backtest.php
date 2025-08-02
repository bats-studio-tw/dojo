<?php

// config/backtest.php

return [
    /**
     * 參數網格 (Parameter Grid)
     * 在此定義所有希望被自動化回測的參數及其可能的取值。
     * backtest:parameters 指令將會遍歷這些值的所有可能組合。
     */
    'parameter_grid' => [
        'elo_weight' => [0.4, 0.5, 0.6, 0.7],
        'momentum_weight' => [0.6, 0.5, 0.4, 0.3],
        'h2h_min_games_threshold' => [3, 5],
        'enhanced_stability_penalty' => [0.1, 0.25, 0.5],
        // 貝式平均H2H參數 (Beta-Binomial smoothing)
        'h2h_bayesian_alpha' => [1, 2, 3],  // 先驗勝利次數
        'h2h_bayesian_beta' => [1, 2, 3],   // 先驗失敗次數
        // 在此加入更多你想測試的參數...
    ],

    /**
     * 策略晉升的最低門檻
     * 只有達到這些標準的回測結果，才有資格被 `strategy:promote-best` 指令晉升。
     */
    'promotion_thresholds' => [
        'min_breakeven_rate' => 65.0, // 最低保本率要求
        'min_total_trades_ratio' => 0.4, // 最低出手頻次比例 (相對於總局數)
    ],

    /**
     * 快取鍵名
     * 定義線上服務讀取策略時使用的快取鍵。
     */
    'cache_key' => 'active_prediction_strategy',

    /**
     * 回測配置
     */
    'backtest_config' => [
        'default_game_count' => 1000, // 預設回測遊戲數量
        'max_game_count' => 2000, // 最大回測遊戲數量
        'min_game_count' => 500, // 最小回測遊戲數量
        'queue_name' => 'backtesting', // 隊列名稱
        'batch_size' => 100, // 批量處理大小
    ],

    /**
     * 性能優化配置
     */
    'performance' => [
        'enable_caching' => true,
        'cache_ttl' => 3600, // 1小時
        'max_concurrent_jobs' => 10, // 最大並發任務數
        'memory_limit' => '512M', // 記憶體限制
    ],
];
