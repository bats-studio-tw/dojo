<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ABTestResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'ab_test_id',
        'strategy',
        'prediction_data',
        'actual_result',
        'user_id',
        'round_id',
        'is_correct',
    ];

    protected $casts = [
        'prediction_data' => 'array',
        'actual_result' => 'array',
        'is_correct' => 'boolean',
    ];

    /**
     * 關聯A/B測試配置
     */
    public function abTestConfig(): BelongsTo
    {
        return $this->belongsTo(ABTestConfig::class, 'ab_test_id');
    }

    /**
     * 關聯用戶
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 獲取預測的獲勝者
     */
    public function getPredictedWinner(): ?string
    {
        return $this->prediction_data['predictions'][0]['symbol'] ?? null;
    }

    /**
     * 獲取實際獲勝者
     */
    public function getActualWinner(): ?string
    {
        return $this->actual_result['winner'] ?? null;
    }

    /**
     * 獲取預測排名
     */
    public function getPredictedRankings(): array
    {
        return collect($this->prediction_data['predictions'] ?? [])
            ->pluck('symbol')
            ->toArray();
    }

    /**
     * 檢查是否為精確匹配
     */
    public function isExactMatch(): bool
    {
        return $this->getPredictedWinner() === $this->getActualWinner();
    }

    /**
     * 獲取預測分數
     */
    public function getPredictionScore(): float
    {
        return $this->prediction_data['predictions'][0]['score'] ?? 0;
    }
}
