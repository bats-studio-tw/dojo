<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PredictionStrategy;
use Illuminate\Support\Facades\Cache;

class ShowCurrentStrategy extends Command
{
    protected $signature = 'strategy:show-current {--detailed : 显示详细信息}';
    protected $description = '显示当前活跃的预测策略参数';

    public function handle(): int
    {
        $this->info('🔍 查询当前活跃策略...');

        // 从缓存获取
        $cachedParams = Cache::get(config('backtest.cache_key'));

        // 从数据库获取活跃策略
        $activeStrategy = PredictionStrategy::where('status', 'active')
            ->latest('activated_at')
            ->first();

        if (!$activeStrategy) {
            $this->warn('⚠️  没有找到活跃策略');
            $this->info('📋 使用默认参数:');
            $this->showDefaultParameters();
            return 0;
        }

        $this->info('✅ 找到活跃策略');
        $this->newLine();

        // 显示基本信息
        $this->info('📊 策略基本信息:');
        $this->line("  策略名称: {$activeStrategy->strategy_name}");
        $this->line("  回测ID: {$activeStrategy->run_id}");
        $this->line("  策略分数: {$activeStrategy->score}");
        $this->line("  激活时间: {$activeStrategy->activated_at}");
        $this->line("  创建时间: {$activeStrategy->created_at}");
        $this->newLine();

        // 显示参数
        $parameters = $activeStrategy->getParameters();
        $this->info('⚙️  当前参数配置:');
        foreach ($parameters as $key => $value) {
            $this->line("  {$key}: {$value}");
        }
        $this->newLine();

        // 显示缓存状态
        $this->info('💾 缓存状态:');
        if ($cachedParams) {
            $this->line('  ✅ 参数已缓存');
            $this->line('  📅 缓存时间: ' . now()->format('Y-m-d H:i:s'));
        } else {
            $this->line('  ⚠️  参数未缓存');
        }
        $this->newLine();

        // 显示详细信息
        if ($this->option('detailed')) {
            $this->showDetailedInfo($activeStrategy);
        }

        // 显示性能摘要
        $performanceSummary = $activeStrategy->getPerformanceSummary();
        if (!empty($performanceSummary)) {
            $this->info('📈 性能摘要:');
            foreach ($performanceSummary as $key => $value) {
                if (is_array($value)) {
                    $this->line("  {$key}:");
                    foreach ($value as $subKey => $subValue) {
                        $this->line("    {$subKey}: {$subValue}");
                    }
                } else {
                    $this->line("  {$key}: {$value}");
                }
            }
            $this->newLine();
        }

        // 显示其他策略
        $otherStrategies = PredictionStrategy::where('status', '!=', 'active')
            ->latest('created_at')
            ->limit(5)
            ->get();

        if ($otherStrategies->isNotEmpty()) {
            $this->info('📚 其他策略 (最近5个):');
            foreach ($otherStrategies as $strategy) {
                $status = match($strategy->status) {
                    'inactive' => '⏸️',
                    'deprecated' => '🗑️',
                    default => '❓'
                };
                $this->line("  {$status} {$strategy->strategy_name} (分数: {$strategy->score}, 状态: {$strategy->status})");
            }
        }

        return 0;
    }

    private function showDefaultParameters(): void
    {
        $defaultParams = [
            'elo_weight' => 0.65,
            'momentum_weight' => 0.35,
            'h2h_min_games_threshold' => 5,
            'enhanced_stability_penalty' => 0.25,
        ];

        foreach ($defaultParams as $key => $value) {
            $this->line("  {$key}: {$value}");
        }
    }

    private function showDetailedInfo(PredictionStrategy $strategy): void
    {
        $this->info('🔍 详细信息:');

        // 显示参数网格配置
        $this->line('📋 参数网格配置:');
        $parameterGrid = config('backtest.parameter_grid');
        foreach ($parameterGrid as $param => $values) {
            $this->line("  {$param}: [" . implode(', ', $values) . "]");
        }
        $this->newLine();

        // 显示晋升门槛
        $this->line('🎯 晋升门槛:');
        $thresholds = config('backtest.promotion_thresholds');
        foreach ($thresholds as $key => $value) {
            $this->line("  {$key}: {$value}");
        }
        $this->newLine();

        // 显示缓存配置
        $this->line('💾 缓存配置:');
        $this->line("  缓存键: " . config('backtest.cache_key'));
        $this->line("  性能缓存TTL: " . config('backtest.performance.cache_ttl') . "秒");
        $this->newLine();
    }
}
