<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ABTestConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'strategies',
        'traffic_distribution',
        'start_date',
        'end_date',
        'status',
        'created_by',
    ];

    protected $casts = [
        'strategies' => 'array',
        'traffic_distribution' => 'array',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    /**
     * 關聯創建者
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * 關聯測試結果
     */
    public function results(): HasMany
    {
        return $this->hasMany(ABTestResult::class, 'ab_test_id');
    }

    /**
     * 獲取活躍的測試
     */
    public static function getActiveTests()
    {
        return self::where('status', 'active')
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->get();
    }

    /**
     * 檢查測試是否活躍
     */
    public function isActive(): bool
    {
        return $this->status === 'active' &&
               $this->start_date <= now() &&
               $this->end_date >= now();
    }

    /**
     * 獲取策略列表
     */
    public function getStrategyNames(): array
    {
        return array_keys($this->strategies ?? []);
    }

    /**
     * 獲取流量分配摘要
     */
    public function getTrafficSummary(): string
    {
        $summary = [];
        foreach ($this->traffic_distribution as $strategy => $percentage) {
            $summary[] = "{$strategy}: {$percentage}%";
        }
        return implode(', ', $summary);
    }
}
