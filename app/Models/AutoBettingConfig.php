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
            'bankroll' => 1000,
            'bet_amount' => 200,
            'daily_stop_loss_percentage' => 15,
            'confidence_threshold' => 88,
            'score_gap_threshold' => 6.0,
            'min_total_games' => 25,
            'strategy' => 'single_bet'
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
                'is_active' => false
            ]
        );
    }
}
