<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoundPredict extends Model
{
    /**
     * 不使用 created_at 和 updated_at 字段，使用 predicted_at
     */
    public $timestamps = false;

    /**
     * 可批量赋值的属性
     */
    protected $fillable = [
        'game_round_id',
        'token_symbol',
        'predicted_rank',
        'prediction_score',
        'prediction_data',
        'predicted_at',
    ];

    /**
     * 属性类型转换
     */
    protected $casts = [
        'predicted_rank' => 'integer',
        'prediction_score' => 'decimal:2',
        'prediction_data' => 'array',
        'predicted_at' => 'datetime',
    ];

    /**
     * 所属的游戏轮次
     */
    public function gameRound(): BelongsTo
    {
        return $this->belongsTo(GameRound::class, 'game_round_id');
    }

    /**
     * 按预测排名排序（升序）
     */
    public function scopeOrderByPredictedRank(Builder $query): Builder
    {
        return $query->orderBy('predicted_rank');
    }

    /**
     * 按预测评分排序（降序）
     */
    public function scopeOrderByScore(Builder $query): Builder
    {
        return $query->orderBy('prediction_score', 'desc');
    }

    /**
     * 根据代币符号筛选
     */
    public function scopeByToken(Builder $query, string $tokenSymbol): Builder
    {
        return $query->where('token_symbol', $tokenSymbol);
    }

    /**
     * 根据预测排名筛选
     */
    public function scopeByPredictedRank(Builder $query, int $rank): Builder
    {
        return $query->where('predicted_rank', $rank);
    }

    /**
     * 获取预测前N名结果
     */
    public function scopeTopPredicted(Builder $query, int $limit = 3): Builder
    {
        return $query->orderBy('predicted_rank')->limit($limit);
    }

    /**
     * 根据轮次ID筛选
     */
    public function scopeByRoundId(Builder $query, int $gameRoundId): Builder
    {
        return $query->where('game_round_id', $gameRoundId);
    }

    /**
     * 格式化显示代币符号和预测排名
     */
    public function getDisplayNameAttribute(): string
    {
        return "{$this->token_symbol} (预测#{$this->predicted_rank})";
    }

    /**
     * 检查是否预测为第一名
     */
    public function isPredictedWinner(): bool
    {
        return $this->predicted_rank === 1;
    }

    /**
     * 获取预测准确度（需要与实际结果比较）
     */
    public function getAccuracy(int $actualRank): array
    {
        $rankDifference = abs($this->predicted_rank - $actualRank);

        return [
            'predicted_rank' => $this->predicted_rank,
            'actual_rank' => $actualRank,
            'rank_difference' => $rankDifference,
            'is_exact_match' => $rankDifference === 0,
            'is_close_match' => $rankDifference <= 1, // 排名差距在1以内算接近
            'accuracy_score' => max(0, 100 - ($rankDifference * 20)), // 简单的准确度评分
        ];
    }
}
