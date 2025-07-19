<?php

namespace App\Console\Commands;

use App\Models\PredictionStrategy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ActivateStrategy extends Command
{
    protected $signature = 'strategy:activate
                            {strategy-id : 要激活的策略ID}
                            {--force : 强制激活，忽略分数检查}';

    protected $description = '手动激活指定的预测策略';

    public function handle(): int
    {
        $strategyId = $this->argument('strategy-id');
        $force = $this->option('force');

        $this->info("🔍 查找策略 ID: {$strategyId}");

        // 查找策略
        $strategy = PredictionStrategy::find($strategyId);

        if (!$strategy) {
            $this->error("❌ 未找到策略 ID: {$strategyId}");
            return 1;
        }

        $this->info("📋 策略信息:");
        $this->line("  名称: {$strategy->strategy_name}");
        $this->line("  分数: {$strategy->score}");
        $this->line("  当前状态: {$strategy->status}");
        $this->line("  参数: " . json_encode($strategy->getParameters()));

        // 检查分数（除非强制激活）
        if (!$force && $strategy->score < 40) {
            $this->warn("⚠️  策略分数较低 ({$strategy->score})，建议使用 --force 参数强制激活");
            if (!$this->confirm('是否继续激活？')) {
                return 0;
            }
        }

        try {
            // 先将所有现有策略设为非活跃
            $deactivatedCount = PredictionStrategy::where('status', 'active')->update([
                'status' => 'inactive',
                'activated_at' => null,
            ]);

            if ($deactivatedCount > 0) {
                $this->info("✅ 已停用 {$deactivatedCount} 个现有活跃策略");
            }

            // 激活指定策略
            $strategy->update([
                'status' => 'active',
                'activated_at' => now(),
            ]);

            $this->info("✅ 策略激活成功！");
            $this->line("  策略名称: {$strategy->strategy_name}");
            $this->line("  激活时间: {$strategy->activated_at}");
            $this->line("  分数: {$strategy->score}");

            // 清除相关缓存
            $this->clearRelatedCaches();

            Log::info('策略手动激活完成', [
                'strategy_id' => $strategyId,
                'strategy_name' => $strategy->strategy_name,
                'score' => $strategy->score,
                'activated_by' => 'manual',
            ]);

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ 策略激活失败: " . $e->getMessage());
            Log::error('策略激活失败', [
                'strategy_id' => $strategyId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 1;
        }
    }

    /**
     * 清除相关缓存
     */
    private function clearRelatedCaches(): void
    {
        $cachesToClear = [
            'prediction_strategy_active',
            'prediction_parameters',
            'game_prediction_cache',
            config('backtest.cache_key'),
        ];

        foreach ($cachesToClear as $cacheKey) {
            Cache::forget($cacheKey);
        }

        $this->info("�� 已清除相关缓存");
    }
}
