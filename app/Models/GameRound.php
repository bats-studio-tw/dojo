<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameRound extends Model
{
    /**
     * 可批量赋值的属性
     */
    protected $fillable = [
        'round_id',
        'settled_at',
    ];

    /**
     * 属性类型转换
     */
    protected $casts = [
        'settled_at' => 'datetime',
    ];

    /**
     * 一个游戏轮次有多个代币结果
     */
    public function roundResults(): HasMany
    {
        return $this->hasMany(RoundResult::class, 'game_round_id');
    }

    /**
     * 一个游戏轮次有多个预测数据
     */
    public function roundPredicts(): HasMany
    {
        return $this->hasMany(RoundPredict::class, 'game_round_id');
    }

    /**
     * 一个游戏轮次有多个Hybrid预测数据
     */
    public function hybridRoundPredicts(): HasMany
    {
        return $this->hasMany(\App\Models\HybridRoundPredict::class, 'game_round_id');
    }

    /**
     * 查询未结算的轮次
     */
    public function scopeUnsettled(Builder $query): Builder
    {
        return $query->whereNull('settled_at');
    }

    /**
     * 查询已结算的轮次
     */
    public function scopeSettled(Builder $query): Builder
    {
        return $query->whereNotNull('settled_at');
    }

    /**
     * 根据round_id查找轮次
     */
    public function scopeByRoundId(Builder $query, string $roundId): Builder
    {
        return $query->where('round_id', $roundId);
    }

    /**
     * 检查是否已结算
     */
    public function isSettled(): bool
    {
        return ! is_null($this->settled_at);
    }

    /**
     * 标记为已结算
     */
    public function markAsSettled(): bool
    {
        return $this->update(['settled_at' => now()]);
    }
}
