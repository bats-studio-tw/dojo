<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BacktestReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'batch_id',
        'strategy_tag',
        'strategy_config',
        'param_matrix',
        'total_rounds',
        'successful_rounds',
        'win_rate',
        'breakeven_rate',
        'sharpe_ratio',
        'sortino_ratio',
        'calmar_ratio',
        'max_drawdown',
        'max_profit',
        'max_loss',
        'avg_profit_loss_ratio',
        'total_profit',
        'profit_rate',
        'volatility',
        'profit_factor',
        'consecutive_wins',
        'consecutive_losses',
        'status',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'strategy_config' => 'array',
        'param_matrix' => 'array',
        'win_rate' => 'decimal:4',
        'breakeven_rate' => 'decimal:4',
        'sharpe_ratio' => 'decimal:4',
        'sortino_ratio' => 'decimal:4',
        'calmar_ratio' => 'decimal:4',
        'max_drawdown' => 'decimal:4',
        'max_profit' => 'decimal:4',
        'max_loss' => 'decimal:4',
        'avg_profit_loss_ratio' => 'decimal:4',
        'total_profit' => 'decimal:4',
        'profit_rate' => 'decimal:4',
        'volatility' => 'decimal:4',
        'profit_factor' => 'decimal:4',
        'consecutive_wins' => 'integer',
        'consecutive_losses' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * 關聯用戶
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 獲取格式化的勝率顯示
     */
    public function getWinRatePercentageAttribute(): string
    {
        return number_format($this->win_rate * 100, 2) . '%';
    }

    /**
     * 獲取格式化的保本率顯示
     */
    public function getBreakevenRatePercentageAttribute(): string
    {
        return number_format($this->breakeven_rate * 100, 2) . '%';
    }

    /**
     * 獲取格式化的夏普比率顯示
     */
    public function getSharpeRatioFormattedAttribute(): string
    {
        return number_format($this->sharpe_ratio, 2);
    }

    /**
     * 獲取格式化的Sortino比率顯示
     */
    public function getSortinoRatioFormattedAttribute(): string
    {
        return number_format($this->sortino_ratio, 2);
    }

    /**
     * 獲取格式化的Calmar比率顯示
     */
    public function getCalmarRatioFormattedAttribute(): string
    {
        return number_format($this->calmar_ratio, 2);
    }

    /**
     * 獲取格式化的波動率顯示
     */
    public function getVolatilityFormattedAttribute(): string
    {
        return number_format($this->volatility, 4);
    }

    /**
     * 獲取格式化的盈利因子顯示
     */
    public function getProfitFactorFormattedAttribute(): string
    {
        return number_format($this->profit_factor, 2);
    }

    /**
     * 獲取格式化的最大回撤顯示
     */
    public function getMaxDrawdownPercentageAttribute(): string
    {
        return number_format($this->max_drawdown * 100, 2) . '%';
    }

    /**
     * 獲取格式化的最大回撤顯示
     */
    public function getMaxDrawdownFormattedAttribute(): string
    {
        return number_format($this->max_drawdown, 4);
    }

    /**
     * 獲取執行時間（秒）
     */
    public function getExecutionTimeAttribute(): ?int
    {
        if ($this->started_at && $this->completed_at) {
            return $this->started_at->diffInSeconds($this->completed_at);
        }
        return null;
    }

    /**
     * 檢查是否為Grid Search結果
     */
    public function isGridSearch(): bool
    {
        return !empty($this->param_matrix);
    }

    /**
     * 獲取策略配置的簡要描述
     */
    public function getStrategyDescriptionAttribute(): string
    {
        if ($this->isGridSearch()) {
            return "Grid Search - {$this->strategy_tag}";
        }

        $config = $this->strategy_config;
        $weights = $config['weights'] ?? [];
        $weightDesc = collect($weights)
            ->map(fn($weight, $feature) => "{$feature}:{$weight}")
            ->join(', ');

        return "{$this->strategy_tag} ({$weightDesc})";
    }

    /**
     * 範圍查詢：按狀態篩選
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * 範圍查詢：按策略標籤篩選
     */
    public function scopeByStrategy($query, string $strategyTag)
    {
        return $query->where('strategy_tag', $strategyTag);
    }

    /**
     * 範圍查詢：按用戶篩選
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * 範圍查詢：按時間範圍篩選
     */
    public function scopeByDateRange($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * 範圍查詢：按勝率排序
     */
    public function scopeOrderByWinRate($query, string $direction = 'desc')
    {
        return $query->orderBy('win_rate', $direction);
    }

    /**
     * 範圍查詢：按夏普比率排序
     */
    public function scopeOrderBySharpeRatio($query, string $direction = 'desc')
    {
        return $query->orderBy('sharpe_ratio', $direction);
    }
}
