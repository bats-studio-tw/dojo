<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutoBettingConfig extends Model
{
    protected $fillable = [
        'uid',
        'is_active',
        'config_payload',
        'encrypted_jwt_token',
    ];

    protected $casts = [
        'config_payload' => 'array', // 核心！自动将 JSON 转为 PHP 数组
        'is_active' => 'boolean',
    ];

    /**
     * 获取默认配置
     */
    public static function getDefaultConfig(): array
    {
        return [
            'bet_amount' => 200,
            'daily_stop_loss_percentage' => 15,
            'confidence_threshold' => 88,
            'score_gap_threshold' => 6.0,
            'min_total_games' => 25,
            'strategy' => 'single_bet',

            // 高级策略参数 - 统一使用0-100格式
            'historical_accuracy_threshold' => 70,
            'min_sample_count' => 40,
            'max_bet_percentage' => 15,
            'enable_trend_analysis' => true,
            'enable_volume_filter' => true,
            'stop_loss_consecutive' => 4,

            // 资金管理参数
            'enable_kelly_criterion' => false,
            'kelly_fraction' => 0.25,
            'enable_martingale' => false,
            'martingale_multiplier' => 2.0,
            'max_martingale_steps' => 3,

            // 时间过滤参数
            'enable_time_filter' => false,
            'allowed_hours_start' => 9,
            'allowed_hours_end' => 21,

            // 市场条件过滤
            'enable_volatility_filter' => false,
            'max_volatility_threshold' => 0.8,
            'min_liquidity_threshold' => 1000000,

            // 指定排名下注相关配置
            'rank_betting_enabled_ranks' => [1, 2, 3],
            'rank_betting_amount_per_rank' => 200,
            'rank_betting_different_amounts' => false,
            'rank_betting_rank1_amount' => 200,
            'rank_betting_rank2_amount' => 200,
            'rank_betting_rank3_amount' => 200,
            'rank_betting_max_ranks' => 5,
        ];
    }

    /**
     * 根据uid获取或创建配置
     */
    public static function getByUid(string $uid): self
    {
        return self::firstOrCreate(
            ['uid' => $uid],
            [
                'config_payload' => self::getDefaultConfig(),
                'is_active' => false,
            ]
        );
    }
}
