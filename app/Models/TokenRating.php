<?php

namespace App\Models;

use App\Services\TimeDecayCalculatorService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TokenRating extends Model
{
    use HasFactory;

    protected $primaryKey = 'symbol'; // 設定 primary key

    public $incrementing = false; // primary key 不是自增長整數

    protected $keyType = 'string'; // primary key 的類型是字串

    protected $fillable = [
        'symbol',
        'elo',
        'games',
        'decayed_top3_rate',
        'decayed_win_rate',
        'decayed_avg_rank',
        'decay_applied',
        'decay_calculated_at',
    ];

    /**
     * 属性类型转换
     */
    protected $casts = [
        'elo' => 'float',
        'games' => 'integer',
        'decayed_top3_rate' => 'float',
        'decayed_win_rate' => 'float',
        'decayed_avg_rank' => 'float',
        'decay_applied' => 'boolean',
        'decay_calculated_at' => 'datetime',
    ];

    /**
     * 模型属性默认值
     */
    protected $attributes = [
        'elo' => 1500,
        'games' => 0,
    ];

    /**
     * 获取 games 属性，确保不为 null
     */
    public function getGamesAttribute($value): int
    {
        return $value ?? 0;
    }

    /**
     * 获取时间衰减的 top3_rate
     *
     * @param int|null $maxRounds 最大考虑的游戏轮数
     * @return array 时间衰减的 top3_rate 数据
     */
    public function getDecayedTop3Rate(?int $maxRounds = null): array
    {
        $calculator = app(TimeDecayCalculatorService::class);
        return $calculator->calculateDecayedTop3Rate($this->symbol, $maxRounds);
    }

    /**
     * 获取时间衰减的 Elo 统计
     *
     * @param int|null $maxRounds 最大考虑的游戏轮数
     * @return array 时间衰减的 Elo 统计数据
     */
    public function getDecayedEloStats(?int $maxRounds = null): array
    {
        $calculator = app(TimeDecayCalculatorService::class);
        return $calculator->calculateDecayedEloStats($this->symbol, $maxRounds);
    }

    /**
     * 获取所有时间衰减指标
     *
     * @param int|null $maxRounds 最大考虑的游戏轮数
     * @return array 完整的时间衰减指标
     */
    public function getAllDecayedMetrics(?int $maxRounds = null): array
    {
        $top3Data = $this->getDecayedTop3Rate($maxRounds);
        $eloData = $this->getDecayedEloStats($maxRounds);

        return [
            'symbol' => $this->symbol,
            'current_elo' => $this->elo,
            'total_games' => $this->games,
            'top3_metrics' => $top3Data,
            'elo_metrics' => $eloData,
            'decay_applied' => $top3Data['decay_applied'] || $eloData['decay_applied'],
        ];
    }

    /**
     * 范围查询：根据时间衰减的 top3_rate 筛选
     */
    public function scopeByDecayedTop3Rate($query, float $minRate, float $maxRate = 100)
    {
        return $query->whereHas('roundResults', function ($q) use ($minRate, $maxRate) {
            // 这是一个复杂查询，实际实现可能需要通过应用层过滤
            // 这里只是提供接口定义
        });
    }

    /**
     * 获取该代币的游戏结果关联
     */
    public function roundResults()
    {
        return $this->hasMany(\App\Models\RoundResult::class, 'token_symbol', 'symbol');
    }
}
