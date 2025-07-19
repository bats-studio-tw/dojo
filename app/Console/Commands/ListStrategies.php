<?php

namespace App\Console\Commands;

use App\Models\PredictionStrategy;
use Illuminate\Console\Command;

class ListStrategies extends Command
{
    protected $signature = 'strategy:list
                            {--active : 只显示活跃策略}
                            {--inactive : 只显示非活跃策略}
                            {--detailed : 显示详细信息}';

    protected $description = '列出所有预测策略';

    public function handle(): int
    {
        $activeOnly = $this->option('active');
        $inactiveOnly = $this->option('inactive');
        $detailed = $this->option('detailed');

        $query = PredictionStrategy::query();

        if ($activeOnly) {
            $query->where('status', 'active');
        } elseif ($inactiveOnly) {
            $query->where('status', 'inactive');
        }

        $strategies = $query->orderBy('created_at', 'desc')->get();

        if ($strategies->isEmpty()) {
            $this->info('📭 没有找到策略');
            return 0;
        }

        $this->info("📋 找到 {$strategies->count()} 个策略:");
        $this->newLine();

        foreach ($strategies as $strategy) {
            $this->displayStrategy($strategy, $detailed);
            $this->newLine();
        }

        // 显示统计信息
        $this->showStatistics();

        return 0;
    }

    private function displayStrategy(PredictionStrategy $strategy, bool $detailed): void
    {
        $statusIcon = match ($strategy->status) {
            'active' => '🟢',
            'inactive' => '⚪',
            'deprecated' => '🔴',
            default => '❓'
        };

        $this->line("{$statusIcon} ID: {$strategy->id} - {$strategy->strategy_name}");
        $this->line("   分数: {$strategy->score}");
        $this->line("   状态: {$strategy->status}");
        $this->line("   回测ID: {$strategy->run_id}");
        $this->line("   创建时间: {$strategy->created_at}");

        if ($strategy->activated_at) {
            $this->line("   激活时间: {$strategy->activated_at}");
            $this->line("   运行时长: " . $strategy->activated_at->diffForHumans());
        }

        if ($detailed) {
            $this->line("   参数: " . json_encode($strategy->getParameters(), JSON_PRETTY_PRINT));
        } else {
            $params = $strategy->getParameters();
            $this->line("   参数: Elo权重={$params['elo_weight']}, 动能权重={$params['momentum_weight']}");
        }
    }

    private function showStatistics(): void
    {
        $this->info('📊 策略统计:');

        $total = PredictionStrategy::count();
        $active = PredictionStrategy::where('status', 'active')->count();
        $inactive = PredictionStrategy::where('status', 'inactive')->count();
        $deprecated = PredictionStrategy::where('status', 'deprecated')->count();

        $this->line("   总数: {$total}");
        $this->line("   活跃: {$active}");
        $this->line("   非活跃: {$inactive}");
        $this->line("   已弃用: {$deprecated}");

        if ($active > 0) {
            $activeStrategy = PredictionStrategy::where('status', 'active')->first();
            $this->line("   当前活跃策略: {$activeStrategy->strategy_name} (分数: {$activeStrategy->score})");
        }
    }
}
