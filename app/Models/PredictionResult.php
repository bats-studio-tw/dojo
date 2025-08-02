<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PredictionResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_round_id',
        'token',
        'predict_rank',
        'predict_score',
        'elo_score',
        'momentum_score',
        'short_term_momentum_score',
        'volume_score',
        'norm_elo',
        'norm_momentum',
        'norm_short_term_momentum',
        'norm_volume',
        'used_weights',
        'used_normalization',
        'strategy_tag',
        'config_snapshot',
    ];

    protected $casts = [
        'used_weights' => 'array',
        'used_normalization' => 'array',
        'config_snapshot' => 'array',
        'elo_score' => 'float',
        'momentum_score' => 'float',
        'short_term_momentum_score' => 'float',
        'volume_score' => 'float',
        'norm_elo' => 'float',
        'norm_momentum' => 'float',
        'norm_short_term_momentum' => 'float',
        'norm_volume' => 'float',
        'predict_score' => 'float',
    ];

    /**
     * 关联游戏回合
     */
    public function gameRound()
    {
        return $this->belongsTo(GameRound::class, 'game_round_id');
    }

    /**
     * 根据策略标签查询
     */
    public function scopeByStrategy($query, string $strategyTag)
    {
        return $query->where('strategy_tag', $strategyTag);
    }

    /**
     * 根据游戏回合查询
     */
    public function scopeByGameRound($query, int $gameRoundId)
    {
        return $query->where('game_round_id', $gameRoundId);
    }

    /**
     * 根据代币查询
     */
    public function scopeByToken($query, string $token)
    {
        return $query->where('token', $token);
    }
}
