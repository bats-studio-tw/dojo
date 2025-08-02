<?php

use App\Jobs\FetchTokenPricesJob;
use App\Models\GameRound;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| 加密货币市场优化的Console Routes - 高频交易适配版
|--------------------------------------------------------------------------
|
| 基于加密货币市场特性优化：
| 1. 保持价格数据高频更新（每分钟）
| 2. 激进的参数优化策略（快速迭代）
| 3. 智能的市场适应性调度
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ==================== 激进的回测排程（加密货币市场适配） ====================

// 超快速回测 - 每2小时执行（快速市场适应）
Schedule::call(function () {
    $runId = 'ultra-fast-' . now()->format('YmdHi'); // 生成唯一的run_id
    \Artisan::call('backtest:run', [
        '--games' => 120,
        '--queue' => true,
        '--run-id' => $runId
    ]);
})->name('ultra-fast-backtest-2h')
    ->cron('0 */2 * * *') // 每2小时执行一次
    ->withoutOverlapping()
    ->onOneServer()
    ->before(function () {
        // 检查市场波动性，高波动时优先执行
        $recentRounds = GameRound::latest()->limit(60)->count();
        if ($recentRounds < 100) {
            Log::info('数据积累不足，跳过超快速回测', ['recent_rounds' => $recentRounds]);

            return false;
        }

        $queueSize = \Illuminate\Support\Facades\Queue::size('backtesting');
        if ($queueSize > 5) {
            Log::warning('回测队列积压，跳过超快速回测', ['queue_size' => $queueSize]);

            return false;
        }
        Log::info('开始执行超快速回测（2小时间隔）');
    })
    ->after(function () {
        Log::info('超快速回测完成');
    });

// 快速回测 - 每4小时执行（标准优化）
Schedule::call(function () {
    $runId = 'fast-' . now()->format('YmdHi'); // 生成唯一的run_id
    \Artisan::call('backtest:run', [
        '--games' => 240,
        '--queue' => true,
        '--run-id' => $runId
    ]);
})->name('fast-backtest-4h')
    ->cron('0 */4 * * *') // 每4小时执行一次
    ->withoutOverlapping()
    ->onOneServer()
    ->before(function () {
        $queueSize = Queue::size('backtesting');
        if ($queueSize > 8) {
            Log::warning('回测队列积压，跳过快速回测', ['queue_size' => $queueSize]);

            return false;
        }
        Log::info('开始执行快速回测（4小时间隔）');
    })
    ->after(function () {
        $runId = 'fast-' . now()->format('YmdHi');
        Log::info('快速回测完成', ['run_id' => $runId]);

        // 自动触发快速策略评估
        \Artisan::call('strategy:promote-best', [
            '--run-id' => $runId,
            '--min-score' => 50,
            '--force-quick' => true,
        ]);
    });

// 深度回测 - 每8小时执行（稳定基准）
Schedule::call(function () {
    $runId = 'standard-' . now()->format('YmdHi'); // 生成唯一的run_id
    \Artisan::call('backtest:run', [
        '--games' => 480,
        '--queue' => true,
        '--run-id' => $runId
    ]);
})->name('standard-backtest-8h')
    ->cron('0 */8 * * *') // 每8小时执行一次
    ->withoutOverlapping()
    ->onOneServer()
    ->before(function () {
        Log::info('开始执行标准回测（8小时间隔）');
    })
    ->after(function () {
        Log::info('标准回测完成');
    });

// 全面回测 - 每日执行（完整验证）
Schedule::call(function () {
    $runId = 'comprehensive-' . now()->format('YmdHi'); // 生成唯一的run_id
    \Artisan::call('backtest:run', [
        '--games' => 1200,
        '--queue' => true,
        '--run-id' => $runId
    ]);
})->name('comprehensive-backtest-daily')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->before(function () {
        Log::info('开始执行全面回测（每日）');
    })
    ->after(function () {
        $runId = 'comprehensive-' . now()->format('YmdHi');
        Log::info('全面回测完成', ['run_id' => $runId]);

        // 触发严格策略晋升
        \Artisan::call('strategy:promote-best', [
            '--run-id' => $runId,
            '--min-score' => 60
        ]);
    });

// ==================== 激进的策略晋升排程 ====================

// 实时策略晋升 - 每3小时执行（极速适应）
Schedule::command('strategy:promote-best --min-score=48 --force-quick')
    ->name('realtime-strategy-promotion-3h')
    ->cron('0 */3 * * *') // 每3小时执行一次
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground()
    ->before(function () {
        Log::info('开始执行实时策略晋升（3小时间隔）');

        // 检查最新回测结果
        $latestBacktest = \App\Models\BacktestResult::latest('created_at')->first();
        if (! $latestBacktest || ! $latestBacktest->created_at || $latestBacktest->created_at->diffInHours(now()) > 4) {
            Log::warning('没有找到最近4小时内的回测结果，跳过实时策略晋升');

            return false;
        }

        // 检查市场活跃度（如果最近1小时游戏数少于50局，可能是市场不活跃）
        $recentGames = \App\Models\GameRound::where('created_at', '>=', now()->subHour())->count();
        if ($recentGames < 30) {
            Log::info('市场活跃度较低，跳过实时策略晋升', ['recent_games' => $recentGames]);

            return false;
        }

        // 放宽冷却期限制（加密货币市场变化快）
        $currentStrategy = \App\Models\PredictionStrategy::where('status', 'active')->first();
        if ($currentStrategy && $currentStrategy->activated_at && $currentStrategy->activated_at->diffInHours(now()) < 2) {
            Log::info('当前策略激活时间不足2小时，跳过本次晋升检查');

            return false;
        }
    })
    ->after(function () {
        Log::info('实时策略晋升完成');
    });

// 快速策略晋升 - 每6小时执行（平衡验证）
Schedule::command('strategy:promote-best --min-score=52')
    ->name('fast-strategy-promotion-6h')
    ->cron('0 */6 * * *') // 每6小时执行一次
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground()
    ->before(function () {
        Log::info('开始执行快速策略晋升（6小时间隔）');

        $latestBacktest = \App\Models\BacktestResult::latest('created_at')->first();
        if (! $latestBacktest || ! $latestBacktest->created_at || $latestBacktest->created_at->diffInHours(now()) > 6) {
            Log::warning('没有找到最近6小时内的回测结果，跳过快速策略晋升');

            return false;
        }
    })
    ->after(function () {
        Log::info('快速策略晋升完成');
    });

// 标准策略晋升 - 每12小时执行（稳定验证）
Schedule::command('strategy:promote-best --min-score=58')
    ->name('standard-strategy-promotion-12h')
    ->cron('0 */12 * * *') // 每12小时执行一次
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground()
    ->before(function () {
        Log::info('开始执行标准策略晋升（12小时间隔）');
    })
    ->after(function () {
        Log::info('标准策略晋升完成');
    });

// ==================== 高频数据更新排程 ====================

// 代币价格更新 - 每分钟执行（保持原频率）
Schedule::call(function () {
    FetchTokenPricesJob::dispatch()->onQueue('default');
})
    ->name('token-price-update-1min')
    ->everyMinute() // 保持高频更新
    ->withoutOverlapping()
    ->onOneServer()
    ->before(function () {
        Log::debug('开始更新代币价格数据（1分钟间隔）');
    })
    ->after(function () {
        Log::debug('代币价格数据更新完成');
    });

// 实时动能计算 - 每2分钟执行（加快响应）
Schedule::command('momentum:calculate --realtime')
    ->name('momentum-realtime-2min')
    ->everyTwoMinutes() // 提高频率以适应快节奏
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground()
    ->before(function () {
        Log::info('开始实时计算市场动能指标');
    })
    ->after(function () {
        Log::info('实时市场动能指标计算完成');
    });

// 批量动能计算 - 每5分钟兜底
Schedule::command('momentum:calculate')
    ->name('momentum-batch-5min')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

// ==================== 智能市场监控 ====================

// 市场波动性检测 - 每5分钟执行
Schedule::call(function () {
    // 检测市场异常波动，触发应急回测
    $recentRounds = GameRound::where('created_at', '>=', now()->subMinutes(10))->count();
    $normalRange = [8, 12]; // 正常情况下10分钟应该有10局左右

    if ($recentRounds < $normalRange[0] || $recentRounds > $normalRange[1]) {
        Log::warning('检测到市场异常活跃度', [
            'recent_rounds_10min' => $recentRounds,
            'normal_range' => $normalRange,
        ]);

        // 如果异常活跃，触发应急回测
        if ($recentRounds > $normalRange[1]) {
            Log::info('市场异常活跃，触发应急回测');
            \Artisan::queue('backtest:run', [
                '--games' => 60,
                '--queue' => true,
                '--run-id' => 'emergency-'.time(),
            ]);
        }
    }

    Log::info('市场波动性监控', [
        'recent_rounds_10min' => $recentRounds,
        'status' => $recentRounds >= $normalRange[0] && $recentRounds <= $normalRange[1] ? 'normal' : 'abnormal',
    ]);
})
    ->name('market-volatility-monitor')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// ==================== 高频队列管理 ====================

// 队列健康监控 - 每5分钟执行（提高频率）
Schedule::call(function () {
    $backtestingQueueSize = Queue::size('backtesting');
    $highQueueSize = Queue::size('high');
    $failedJobs = DB::table('failed_jobs')->count();

    Log::info('高频队列健康检查', [
        'backtesting_queue_size' => $backtestingQueueSize,
        'high_queue_size' => $highQueueSize,
        'failed_jobs_count' => $failedJobs,
        'timestamp' => now()->toISOString(),
    ]);

    // 更严格的队列管理
    if ($backtestingQueueSize > 15) {
        Log::warning('回测队列积压严重，暂停新任务', ['queue_size' => $backtestingQueueSize]);

        // 可以考虑暂停非紧急的回测任务
        \Illuminate\Support\Facades\Cache::put('pause_non_urgent_backtest', true, now()->addMinutes(30));
    }

    if ($highQueueSize > 30) {
        Log::warning('高优先级队列积压', ['queue_size' => $highQueueSize]);
    }

    if ($failedJobs > 5) {
        Log::warning('失败任务较多', ['failed_jobs' => $failedJobs]);
    }
})
    ->name('high-frequency-queue-monitoring')
    ->everyFiveMinutes() // 提高监控频率
    ->withoutOverlapping()
    ->onOneServer();

// ==================== 系统维护排程 ====================

// 清理回测结果 - 保留更多数据
Schedule::command('backtest:cleanup --days=10 --force')
    ->name('weekly-backtest-cleanup')
    ->weekly()
    ->sundays()
    ->at('01:00')
    ->withoutOverlapping()
    ->onOneServer();

// 清理价格数据 - 延长保留期
Schedule::command('token:cleanup-prices --days=5 --force')
    ->name('daily-token-price-cleanup')
    ->dailyAt('00:30')
    ->withoutOverlapping()
    ->onOneServer();

// ==================== 性能监控（适度频率） ====================

// 系统性能监控 - 每30分钟
Schedule::call(function () {
    $memoryUsage = memory_get_usage(true);
    $memoryPeak = memory_get_peak_usage(true);

    $storagePath = storage_path();
    $diskFree = disk_free_space($storagePath);
    $diskTotal = disk_total_space($storagePath);
    $diskUsagePercent = $diskTotal > 0 ? ($diskFree / $diskTotal) * 100 : 0;

    Log::info('系统性能监控', [
        'memory_usage_mb' => round($memoryUsage / 1024 / 1024, 2),
        'memory_peak_mb' => round($memoryPeak / 1024 / 1024, 2),
        'disk_free_percent' => round($diskUsagePercent, 2),
        'timestamp' => now()->toISOString(),
    ]);

    if ($memoryUsage > 1536 * 1024 * 1024) { // 1.5GB
        Log::warning('系统内存使用较高', [
            'memory_mb' => round($memoryUsage / 1024 / 1024, 2),
        ]);
    }

    if ($diskUsagePercent < 10) {
        Log::warning('磁盘空间不足', [
            'free_percent' => round($diskUsagePercent, 2),
        ]);
    }
})
    ->name('system-monitoring-30min')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// ==================== 智能健康报告 ====================

// 加密货币市场专用健康报告
Schedule::call(function () {
    $hourlyGames = \App\Models\GameRound::where('created_at', '>=', now()->subHour())->count();
    $dailyGames = \App\Models\GameRound::whereDate('created_at', today())->count();
    $activeStrategy = \App\Models\PredictionStrategy::where('status', 'active')->first();
    $recentBacktests = \App\Models\BacktestResult::where('created_at', '>=', now()->subHours(6))->count();

    $report = [
        'timestamp' => now()->toISOString(),
        'market_activity' => [
            'games_last_hour' => $hourlyGames,
            'games_today' => $dailyGames,
            'expected_hourly_rate' => 60,
            'activity_ratio' => round($hourlyGames / 60, 2),
        ],
        'strategy_status' => [
            'active_strategy' => $activeStrategy?->strategy_name ?? 'none',
            'strategy_activated_at' => $activeStrategy?->activated_at?->toISOString(),
            'strategy_score' => $activeStrategy?->score,
        ],
        'optimization_activity' => [
            'backtests_last_6h' => $recentBacktests,
            'queue_backtesting' => Queue::size('backtesting'),
            'queue_high' => Queue::size('high'),
        ],
        'system_health' => [
            'failed_jobs' => DB::table('failed_jobs')->count(),
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
        ],
    ];

    Log::info('加密货币市场健康报告', $report);

    // 异常活跃度告警
    if ($hourlyGames < 30) {
        Log::warning('市场活跃度异常低', ['games_last_hour' => $hourlyGames]);
    } elseif ($hourlyGames > 90) {
        Log::warning('市场活跃度异常高', ['games_last_hour' => $hourlyGames]);
    }
})
    ->name('crypto-market-health-report')
    ->hourly() // 每小时生成报告
    ->withoutOverlapping()
    ->onOneServer();
