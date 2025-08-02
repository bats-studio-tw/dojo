<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BacktestResult extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'run_id',
        'params_hash',
        'parameters',
        'score',
        'total_games',
        'correct_predictions',
        'top3_correct_predictions',
        'accuracy',
        'weighted_accuracy',
        'top3_accuracy',
        'top3_weighted_accuracy',
        'precision_at_3',
        'avg_confidence',
        'detailed_results',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'parameters' => 'array',
        'detailed_results' => 'array',
        'score' => 'decimal:4',
        'accuracy' => 'decimal:2',
        'weighted_accuracy' => 'decimal:2',
        'top3_accuracy' => 'decimal:2',
        'top3_weighted_accuracy' => 'decimal:2',
        'precision_at_3' => 'decimal:2',
        'avg_confidence' => 'decimal:2',
    ];

    /**
     * 獲取參數值
     */
    public function getParameter(string $key, $default = null)
    {
        return $this->parameters[$key] ?? $default;
    }

    /**
     * 檢查是否包含特定參數
     */
    public function hasParameter(string $key): bool
    {
        return isset($this->parameters[$key]);
    }

    /**
     * 獲取權重參數
     */
    public function getWeightParameters(): array
    {
        $weightKeys = ['elo_weight', 'momentum_weight', 'volume_weight'];
        $weights = [];

        foreach ($weightKeys as $key) {
            if ($this->hasParameter($key)) {
                $weights[$key] = $this->getParameter($key);
            }
        }

        return $weights;
    }

    /**
     * 檢查權重是否有效（總和接近1.0）
     */
    public function hasValidWeights(): bool
    {
        $weights = $this->getWeightParameters();
        if (empty($weights)) {
            return true; // 沒有權重參數時認為有效
        }

        $sum = array_sum($weights);

        return abs($sum - 1.0) < 1e-6;
    }

    /**
     * 獲取性能摘要
     */
    public function getPerformanceSummary(): array
    {
        return [
            'score' => $this->score,
            'accuracy' => $this->accuracy,
            'weighted_accuracy' => $this->weighted_accuracy,
            'top3_accuracy' => $this->top3_accuracy,
            'top3_weighted_accuracy' => $this->top3_weighted_accuracy,
            'precision_at_3' => $this->precision_at_3,
            'total_games' => $this->total_games,
            'correct_predictions' => $this->correct_predictions,
            'top3_correct_predictions' => $this->top3_correct_predictions,
            'avg_confidence' => $this->avg_confidence,
            'success_rate' => $this->total_games > 0 ?
                round(($this->correct_predictions / $this->total_games) * 100, 2) : 0,
            'top3_success_rate' => $this->total_games > 0 ?
                round(($this->top3_correct_predictions / $this->total_games) * 100, 2) : 0,
        ];
    }

    /**
     * 範圍查詢：按分數範圍
     */
    public function scopeScoreRange($query, float $min, float $max)
    {
        return $query->whereBetween('score', [$min, $max]);
    }

    /**
     * 範圍查詢：按準確率範圍
     */
    public function scopeAccuracyRange($query, float $min, float $max)
    {
        return $query->whereBetween('accuracy', [$min, $max]);
    }

    /**
     * 範圍查詢：按加權準確率範圍
     */
    public function scopeWeightedAccuracyRange($query, float $min, float $max)
    {
        return $query->whereBetween('weighted_accuracy', [$min, $max]);
    }

    /**
     * 範圍查詢：按遊戲數量範圍
     */
    public function scopeGameCountRange($query, int $min, int $max)
    {
        return $query->whereBetween('total_games', [$min, $max]);
    }

    /**
     * 範圍查詢：按參數值
     */
    public function scopeWithParameter($query, string $key, $value)
    {
        return $query->where("parameters->{$key}", $value);
    }

    /**
     * 範圍查詢：按參數範圍
     */
    public function scopeWithParameterRange($query, string $key, $min, $max)
    {
        return $query->whereRaw("JSON_EXTRACT(parameters, '$.{$key}') BETWEEN ? AND ?", [$min, $max]);
    }

    /**
     * 範圍查詢：按 Top3 準確率範圍
     */
    public function scopeTop3AccuracyRange($query, float $min, float $max)
    {
        return $query->whereBetween('top3_accuracy', [$min, $max]);
    }

    /**
     * 範圍查詢：按 Top3 加權準確率範圍
     */
    public function scopeTop3WeightedAccuracyRange($query, float $min, float $max)
    {
        return $query->whereBetween('top3_weighted_accuracy', [$min, $max]);
    }

    /**
     * 範圍查詢：按 Precision@3 範圍
     */
    public function scopePrecisionAt3Range($query, float $min, float $max)
    {
        return $query->whereBetween('precision_at_3', [$min, $max]);
    }

    /**
     * 獲取 Top3 相關指標
     */
    public function getTop3Metrics(): array
    {
        return [
            'top3_accuracy' => $this->top3_accuracy,
            'top3_weighted_accuracy' => $this->top3_weighted_accuracy,
            'precision_at_3' => $this->precision_at_3,
            'top3_correct_predictions' => $this->top3_correct_predictions,
            'top3_success_rate' => $this->total_games > 0 ?
                round(($this->top3_correct_predictions / $this->total_games) * 100, 2) : 0,
            'traditional_vs_top3_improvement' => $this->top3_accuracy - $this->accuracy,
        ];
    }

    /**
     * 比較傳統準確率與 Top3 準確率的提升幅度
     */
    public function getTop3Improvement(): float
    {
        return round($this->top3_accuracy - $this->accuracy, 2);
    }
}
