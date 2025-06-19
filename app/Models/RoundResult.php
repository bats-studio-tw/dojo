<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class RoundResult extends Model
{
    /**
     * 此表不使用 created_at 和 updated_at 字段
     */
    public $timestamps = false;

    /**
     * 可批量赋值的属性
     */
    protected $fillable = [
        'game_round_id',
        'token_symbol',
        'rank',
        'value',
    ];

    /**
     * 属性类型转换
     */
    protected $casts = [
        'rank' => 'integer',
        'value' => 'decimal:4',
    ];

    /**
     * 所属的游戏轮次
     */
    public function gameRound(): BelongsTo
    {
        return $this->belongsTo(GameRound::class, 'game_round_id');
    }

    /**
     * 按排名排序（升序）
     */
    public function scopeOrderByRank(Builder $query): Builder
    {
        return $query->orderBy('rank');
    }

    /**
     * 按价值排序（降序）
     */
    public function scopeOrderByValue(Builder $query): Builder
    {
        return $query->orderBy('value', 'desc');
    }

    /**
     * 根据代币符号筛选
     */
    public function scopeByToken(Builder $query, string $tokenSymbol): Builder
    {
        return $query->where('token_symbol', $tokenSymbol);
    }

    /**
     * 根据排名筛选
     */
    public function scopeByRank(Builder $query, int $rank): Builder
    {
        return $query->where('rank', $rank);
    }

    /**
     * 获取前N名结果
     */
    public function scopeTopRanked(Builder $query, int $limit = 3): Builder
    {
        return $query->orderBy('rank')->limit($limit);
    }

    /**
     * 根据轮次ID筛选
     */
    public function scopeByRoundId(Builder $query, int $gameRoundId): Builder
    {
        return $query->where('game_round_id', $gameRoundId);
    }

    /**
     * 格式化显示代币符号和排名
     */
    public function getDisplayNameAttribute(): string
    {
        return "{$this->token_symbol} (#{$this->rank})";
    }

    /**
     * 检查是否为第一名
     */
    public function isWinner(): bool
    {
        return $this->rank === 1;
    }
}
