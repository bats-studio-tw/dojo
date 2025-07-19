<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PredictionStrategy;
use Illuminate\Support\Facades\Cache;

class ShowParams extends Command
{
    protected $signature = 'params:show';
    protected $description = '快速显示当前预测参数';

    public function handle(): int
    {
        // 从缓存获取
        $cachedParams = Cache::get(config('backtest.cache_key'));

        // 从数据库获取活跃策略
        $activeStrategy = PredictionStrategy::where('status', 'active')
            ->latest('activated_at')
            ->first();

        if (!$activeStrategy) {
            $this->warn('⚠️  无活跃策略，使用默认参数');
            $defaultParams = [
                'elo_weight' => 0.65,
                'momentum_weight' => 0.35,
                'h2h_min_games_threshold' => 5,
                'enhanced_stability_penalty' => 0.25,
            ];

            foreach ($defaultParams as $key => $value) {
                $this->line("{$key}: {$value}");
            }
            return 0;
        }

        $parameters = $activeStrategy->getParameters();

        $this->info("📊 当前策略: {$activeStrategy->strategy_name} (分数: {$activeStrategy->score})");
        $this->line("🕐 激活时间: {$activeStrategy->activated_at}");
        $this->newLine();

        $this->info('⚙️  参数配置:');
        foreach ($parameters as $key => $value) {
            $this->line("  {$key}: {$value}");
        }

        return 0;
    }
}
