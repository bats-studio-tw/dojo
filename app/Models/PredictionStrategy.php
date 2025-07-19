<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PredictionStrategy extends Model
{
    use HasFactory;

    protected $fillable = [
        'strategy_name',
        'run_id',
        'parameters',
        'score',
        'status',
        'activated_at',
    ];

    protected $casts = [
        'parameters' => 'array',
        'performance_summary' => 'array',
        'activated_at' => 'datetime',
    ];

    /**
     * 获取活跃策略
     */
    public static function getActiveStrategy(): ?self
    {
        return self::where('status', 'active')
            ->latest('activated_at')
            ->first();
    }

    /**
     * 获取策略参数
     */
    public function getParameters(): array
    {
        if (is_string($this->parameters)) {
            return json_decode($this->parameters, true) ?? [];
        }

        return $this->parameters ?? [];
    }

    /**
     * 获取绩效摘要
     */
    public function getPerformanceSummary(): array
    {
        return $this->performance_summary ?? [];
    }

    /**
     * 检查策略是否活跃
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * 激活策略
     */
    public function activate(): void
    {
        // 将其他策略设为非活跃
        self::where('status', 'active')->update(['status' => 'inactive']);

        // 激活当前策略
        $this->update([
            'status' => 'active',
            'activated_at' => now(),
        ]);
    }

    /**
     * 停用策略
     */
    public function deactivate(): void
    {
        $this->update(['status' => 'inactive']);
    }

    /**
     * 归档策略
     */
    public function archive(): void
    {
        $this->update(['status' => 'archived']);
    }
}
