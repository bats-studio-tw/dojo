<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PredictionStrategy;
use App\Models\GameRound;
use App\Models\BacktestResult;
use App\Models\TokenPrice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ShowCurrentStrategy extends Command
{
    protected $signature = 'strategy:show-current
                            {--detailed : 显示详细信息}
                            {--real-time : 显示实时状态}
                            {--monitoring : 显示监控数据}';
    protected $description = '显示当前活跃的预测策略参数和系统状态';

    public function handle(): int
    {
        $this->info('🔍 查询当前活跃策略和系统状态...');
        $this->newLine();

        // 显示策略信息
        $this->showStrategyInfo();

        // 显示调度系统状态
        $this->showScheduleStatus();

        // 显示市场监控数据
        $this->showMarketMonitoring();

        // 显示队列状态
        $this->showQueueStatus();

        // 显示系统性能
        $this->showSystemPerformance();

        // 显示缓存状态
        $this->showCacheStatus();

        // 显示详细信息
        if ($this->option('detailed')) {
            $this->showDetailedInfo();
        }

        // 显示实时状态
        if ($this->option('real-time')) {
            $this->showRealTimeStatus();
        }

        // 显示监控数据
        if ($this->option('monitoring')) {
            $this->showMonitoringData();
        }

        return 0;
    }

    private function showStrategyInfo(): void
    {
        $this->info('📊 策略基本信息:');

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
            return;
        }

        $this->line("  策略名称: {$activeStrategy->strategy_name}");
        $this->line("  回测ID: {$activeStrategy->run_id}");
        $this->line("  策略分数: {$activeStrategy->score}");
        $this->line("  激活时间: {$activeStrategy->activated_at}");
        $this->line("  运行时长: " . $activeStrategy->activated_at->diffForHumans());
        $this->line("  创建时间: {$activeStrategy->created_at}");
        $this->newLine();

        // 显示参数
        $parameters = $activeStrategy->getParameters();
        $this->info('⚙️  当前参数配置:');
        foreach ($parameters as $key => $value) {
            $this->line("  {$key}: {$value}");
        }
        $this->newLine();

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
    }

    private function showScheduleStatus(): void
    {
        $this->info('🕐 调度系统状态:');

        // 获取最近的回测记录
        $recentBacktests = BacktestResult::latest('created_at')->limit(5)->get();

        $this->line('📊 最近回测记录:');
        foreach ($recentBacktests as $backtest) {
            $status = $backtest->status ?? 'unknown';
            $statusIcon = match($status) {
                'completed' => '✅',
                'running' => '🔄',
                'failed' => '❌',
                default => '❓'
            };
            $createdAt = $backtest->created_at ? $backtest->created_at->diffForHumans() : '未知时间';
            $this->line("  {$statusIcon} {$backtest->run_id} - {$createdAt} ({$status})");
        }
        $this->newLine();

        // 显示调度任务状态
        $this->line('⏰ 调度任务配置:');
        $scheduleConfig = [
            '超快速回测' => '每2小时 (120局)',
            '快速回测' => '每4小时 (240局)',
            '标准回测' => '每8小时 (480局)',
            '全面回测' => '每日 (1200局)',
            '实时策略晋升' => '每3小时 (门槛48分)',
            '快速策略晋升' => '每6小时 (门槛52分)',
            '标准策略晋升' => '每12小时 (门槛58分)',
            '价格更新' => '每分钟',
            '动能计算' => '每2分钟 (实时) + 每5分钟 (兜底)',
            '市场监控' => '每5分钟',
            '队列监控' => '每5分钟',
            '系统监控' => '每30分钟',
            '健康报告' => '每小时',
        ];

        foreach ($scheduleConfig as $task => $schedule) {
            $this->line("  {$task}: {$schedule}");
        }
        $this->newLine();
    }

    private function showMarketMonitoring(): void
    {
        $this->info('📈 市场监控数据:');

        // 游戏数据统计
        $hourlyGames = GameRound::where('created_at', '>=', now()->subHour())->count();
        $dailyGames = GameRound::whereDate('created_at', today())->count();
        $weeklyGames = GameRound::where('created_at', '>=', now()->subWeek())->count();

        $this->line('🎮 游戏数据统计:');
        $this->line("  最近1小时: {$hourlyGames} 局");
        $this->line("  今日: {$dailyGames} 局");
        $this->line("  本周: {$weeklyGames} 局");
        $this->line("  预期小时率: 60 局/小时");
        $this->line("  活跃度比例: " . round($hourlyGames / 60, 2));
        $this->newLine();

        // 市场活跃度分析
        $recentRounds10min = GameRound::where('created_at', '>=', now()->subMinutes(10))->count();
        $normalRange = [8, 12];
        $activityStatus = $recentRounds10min >= $normalRange[0] && $recentRounds10min <= $normalRange[1] ? '正常' : '异常';
        $statusIcon = $activityStatus === '正常' ? '✅' : '⚠️';

        $this->line('📊 市场活跃度:');
        $this->line("  {$statusIcon} 最近10分钟: {$recentRounds10min} 局 ({$activityStatus})");
        $this->line("  正常范围: {$normalRange[0]}-{$normalRange[1]} 局/10分钟");
        $this->newLine();

        // 价格数据统计
        $recentPrices = TokenPrice::where('created_at', '>=', now()->subHour())->count();
        $uniqueTokens = TokenPrice::where('created_at', '>=', now()->subHour())->distinct('symbol')->count();

        $this->line('💰 价格数据统计:');
        $this->line("  最近1小时价格记录: {$recentPrices} 条");
        $this->line("  活跃代币数量: {$uniqueTokens} 个");
        $this->newLine();
    }

    private function showQueueStatus(): void
    {
        $this->info('🔄 队列状态:');

        $backtestingQueueSize = Queue::size('backtesting');
        $highQueueSize = Queue::size('high');
        $defaultQueueSize = Queue::size('default');
        $failedJobs = DB::table('failed_jobs')->count();

        $this->line('📋 队列积压情况:');
        $this->line("  回测队列: {$backtestingQueueSize} 个任务");
        $this->line("  高优先级队列: {$highQueueSize} 个任务");
        $this->line("  默认队列: {$defaultQueueSize} 个任务");
        $this->line("  失败任务: {$failedJobs} 个");
        $this->newLine();

        // 队列健康状态
        $this->line('🏥 队列健康状态:');
        $backtestStatus = $backtestingQueueSize > 15 ? '⚠️ 积压严重' : ($backtestingQueueSize > 5 ? '⚠️ 轻微积压' : '✅ 正常');
        $highStatus = $highQueueSize > 30 ? '⚠️ 积压严重' : ($highQueueSize > 10 ? '⚠️ 轻微积压' : '✅ 正常');
        $failedStatus = $failedJobs > 5 ? '❌ 失败过多' : '✅ 正常';

        $this->line("  回测队列: {$backtestStatus}");
        $this->line("  高优先级队列: {$highStatus}");
        $this->line("  失败任务: {$failedStatus}");
        $this->newLine();

        // 暂停状态检查
        $pauseStatus = Cache::get('pause_non_urgent_backtest');
        if ($pauseStatus) {
            $this->warn('⚠️  非紧急回测任务已暂停');
        }
    }

    private function showSystemPerformance(): void
    {
        $this->info('💻 系统性能:');

        $memoryUsage = memory_get_usage(true);
        $memoryPeak = memory_get_peak_usage(true);
        $memoryLimit = ini_get('memory_limit');

        $storagePath = storage_path();
        $diskFree = disk_free_space($storagePath);
        $diskTotal = disk_total_space($storagePath);
        $diskUsagePercent = $diskTotal > 0 ? (($diskTotal - $diskFree) / $diskTotal) * 100 : 0;

        $this->line('🧠 内存使用:');
        $this->line("  当前使用: " . round($memoryUsage / 1024 / 1024, 2) . " MB");
        $this->line("  峰值使用: " . round($memoryPeak / 1024 / 1024, 2) . " MB");
        $this->line("  内存限制: {$memoryLimit}");
        $this->newLine();

        $this->line('💾 磁盘使用:');
        $this->line("  已使用: " . round($diskUsagePercent, 2) . "%");
        $this->line("  剩余空间: " . round($diskFree / 1024 / 1024 / 1024, 2) . " GB");
        $this->line("  总空间: " . round($diskTotal / 1024 / 1024 / 1024, 2) . " GB");
        $this->newLine();

        // 性能警告
        if ($memoryUsage > 1536 * 1024 * 1024) { // 1.5GB
            $this->warn('⚠️  系统内存使用较高');
        }

        if ($diskUsagePercent > 90) {
            $this->warn('⚠️  磁盘空间不足');
        }
    }

    private function showCacheStatus(): void
    {
        $this->info('💾 缓存状态:');

        $cachedParams = Cache::get(config('backtest.cache_key'));
        $momentumCache = Cache::get('market_momentum_summary');
        $predictionCache = Cache::get('hybrid_prediction:*');

        $this->line('📋 缓存项目:');
        $this->line("  策略参数: " . ($cachedParams ? '✅ 已缓存' : '❌ 未缓存'));
        $this->line("  动能数据: " . ($momentumCache ? '✅ 已缓存' : '❌ 未缓存'));
        $this->line("  预测结果: " . ($predictionCache ? '✅ 已缓存' : '❌ 未缓存'));
        $this->newLine();

        // 缓存配置
        $this->line('⚙️  缓存配置:');
        $this->line("  策略缓存键: " . config('backtest.cache_key'));
        $this->line("  性能缓存TTL: " . config('backtest.performance.cache_ttl') . " 秒");
        $this->line("  预测缓存TTL: " . config('prediction.cache.ttl') . " 秒");
        $this->newLine();
    }

    private function showRealTimeStatus(): void
    {
        $this->info('🔄 实时状态:');

        // 最近的活动
        $recentActivity = $this->getRecentActivity();

        $this->line('📊 最近活动:');
        foreach ($recentActivity as $activity) {
            $this->line("  {$activity['time']} - {$activity['description']}");
        }
        $this->newLine();

        // 系统状态
        $this->line('🔍 系统状态:');
        $this->line("  当前时间: " . now()->format('Y-m-d H:i:s'));
        $this->line("  系统运行: " . $this->getUptime());
        $this->line("  PHP版本: " . PHP_VERSION);
        $this->line("  Laravel版本: " . app()->version());
    }

    private function showMonitoringData(): void
    {
        $this->info('📊 监控数据:');

        // 生成健康报告
        $healthReport = $this->generateHealthReport();

        $this->line('🏥 系统健康报告:');
        foreach ($healthReport as $category => $metrics) {
            $this->line("  {$category}:");
            foreach ($metrics as $metric => $value) {
                $this->line("    {$metric}: {$value}");
            }
        }
        $this->newLine();

        // 异常检测
        $anomalies = $this->detectAnomalies();
        if (!empty($anomalies)) {
            $this->warn('⚠️  检测到异常:');
            foreach ($anomalies as $anomaly) {
                $this->line("  - {$anomaly}");
            }
        } else {
            $this->info('✅ 系统运行正常，未检测到异常');
        }
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

    private function showDetailedInfo(): void
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

        // 显示预测策略配置
        $this->line('🎲 预测策略配置:');
        $strategies = config('prediction.strategies');
        foreach ($strategies as $key => $strategy) {
            $this->line("  {$key}: {$strategy['name']} ({$strategy['description']})");
        }
        $this->newLine();

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
    }

    private function getRecentActivity(): array
    {
        $activities = [];

                // 最近的回测
        $latestBacktest = BacktestResult::latest('created_at')->first();
        if ($latestBacktest && $latestBacktest->created_at) {
            $activities[] = [
                'time' => $latestBacktest->created_at->diffForHumans(),
                'description' => "回测完成: {$latestBacktest->run_id}"
            ];
        }

        // 最近的游戏
        $latestGame = GameRound::latest('created_at')->first();
        if ($latestGame && $latestGame->created_at) {
            $activities[] = [
                'time' => $latestGame->created_at->diffForHumans(),
                'description' => "新游戏: {$latestGame->round_id}"
            ];
        }

        // 最近的价格更新
        $latestPrice = TokenPrice::latest('created_at')->first();
        if ($latestPrice && $latestPrice->created_at) {
            $activities[] = [
                'time' => $latestPrice->created_at->diffForHumans(),
                'description' => "价格更新: {$latestPrice->symbol}"
            ];
        }

        return $activities;
    }

    private function getUptime(): string
    {
        // 简单的运行时间计算
        $startTime = Cache::get('system_start_time');
        if (!$startTime) {
            Cache::put('system_start_time', now(), 86400); // 24小时
            return '未知';
        }

        return Carbon::parse($startTime)->diffForHumans();
    }

    private function generateHealthReport(): array
    {
        $hourlyGames = GameRound::where('created_at', '>=', now()->subHour())->count();
        $activeStrategy = PredictionStrategy::where('status', 'active')->first();
        $recentBacktests = BacktestResult::where('created_at', '>=', now()->subHours(6))->count();
        $backtestingQueueSize = Queue::size('backtesting');
        $failedJobs = DB::table('failed_jobs')->count();
        $memoryUsage = round(memory_get_usage(true) / 1024 / 1024, 2);

        return [
            '市场活跃度' => [
                '小时游戏数' => $hourlyGames,
                '活跃度比例' => round($hourlyGames / 60, 2),
                '状态' => $hourlyGames >= 30 && $hourlyGames <= 90 ? '正常' : '异常'
            ],
            '策略状态' => [
                '活跃策略' => $activeStrategy?->strategy_name ?? '无',
                '策略分数' => $activeStrategy?->score ?? 'N/A',
                '运行时长' => $activeStrategy ? $activeStrategy->activated_at->diffForHumans() : 'N/A'
            ],
            '优化活动' => [
                '6小时回测数' => $recentBacktests,
                '回测队列' => $backtestingQueueSize,
                '失败任务' => $failedJobs
            ],
            '系统健康' => [
                '内存使用' => "{$memoryUsage} MB",
                '磁盘使用' => $this->getDiskUsagePercent() . '%',
                '队列状态' => $backtestingQueueSize > 15 ? '积压' : '正常'
            ]
        ];
    }

    private function detectAnomalies(): array
    {
        $anomalies = [];

        $hourlyGames = GameRound::where('created_at', '>=', now()->subHour())->count();
        if ($hourlyGames < 30) {
            $anomalies[] = "市场活跃度异常低 ({$hourlyGames} 局/小时)";
        } elseif ($hourlyGames > 90) {
            $anomalies[] = "市场活跃度异常高 ({$hourlyGames} 局/小时)";
        }

        $backtestingQueueSize = Queue::size('backtesting');
        if ($backtestingQueueSize > 15) {
            $anomalies[] = "回测队列积压严重 ({$backtestingQueueSize} 个任务)";
        }

        $failedJobs = DB::table('failed_jobs')->count();
        if ($failedJobs > 5) {
            $anomalies[] = "失败任务过多 ({$failedJobs} 个)";
        }

        $memoryUsage = memory_get_usage(true);
        if ($memoryUsage > 1536 * 1024 * 1024) {
            $anomalies[] = "系统内存使用较高 (" . round($memoryUsage / 1024 / 1024, 2) . " MB)";
        }

        return $anomalies;
    }

    private function getDiskUsagePercent(): float
    {
        $storagePath = storage_path();
        $diskFree = disk_free_space($storagePath);
        $diskTotal = disk_total_space($storagePath);

        return $diskTotal > 0 ? round((($diskTotal - $diskFree) / $diskTotal) * 100, 2) : 0;
    }
}
