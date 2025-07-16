<?php

namespace App\Console\Commands;

use App\Services\GameWebSocketService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ListenGameWebSocket extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'game:listen-websocket {--monitor-queues : 监控队列状态} {--check-jobs : 检查队列中的任务} {--monitor-connection : 监控WebSocket连接状态}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '监听游戏 WebSocket 连接并处理游戏数据';

    private bool $shouldStop = false;

    public function __construct(
        private GameWebSocketService $webSocketService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('monitor-queues')) {
            return $this->monitorQueues();
        }

        if ($this->option('check-jobs')) {
            return $this->checkJobs();
        }

        if ($this->option('monitor-connection')) {
            return $this->monitorConnection();
        }

        $this->info('🚀 开始监听游戏 WebSocket...');

        $this->setupSignalHandlers();

        // 设置控制台输出回调
        $this->webSocketService->setConsoleOutput(function ($message, $level = 'info') {
            match($level) {
                'error' => $this->error($message),
                'warn' => $this->warn($message),
                default => $this->info($message)
            };
        });

        try {
            Log::info("游戏WebSocket监听器启动", ['pid' => getmypid()]);

            // 直接启动服务，服务内部会处理循环和重连
            $this->webSocketService->startListening();

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ WebSocket 监听器发生致命错误: " . $e->getMessage());
            Log::error("WebSocket监听器致命错误", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }

    private function setupSignalHandlers(): void
    {
        // 检查是否为Windows系统
        if (PHP_OS_FAMILY === 'Windows') {
            return;
        }

        if (! function_exists('pcntl_signal')) {
            return;
        }

        $handler = function (int $signal) {
            $this->info("📡 收到信号 " . $signal . "，正在优雅关闭...");
            $this->gracefulShutdown();
        };

        pcntl_signal(SIGTERM, $handler);
        pcntl_signal(SIGINT, $handler);
        pcntl_async_signals(true);
    }

    private function gracefulShutdown(): void
    {
        if ($this->shouldStop) {
            return;
        } // 防止重複執行

        $this->shouldStop = true;
        $this->webSocketService->stopListening();
    }

    /**
     * 监控队列状态
     */
    private function monitorQueues(): int
    {
        $this->info('📊 队列状态监控');

        // 检查数据库队列
        $queueStats = DB::table('jobs')
            ->selectRaw('queue, COUNT(*) as count, MIN(created_at) as oldest_job, MAX(created_at) as newest_job')
            ->groupBy('queue')
            ->get();

        if ($queueStats->isEmpty()) {
            $this->warn('⚠️ 数据库队列中没有待处理的任务');
        } else {
            foreach ($queueStats as $stat) {
                $this->line("  - {$stat->queue}: {$stat->count} 个任务");
            }
        }

        // 检查失败的任务
        $failedJobs = DB::table('failed_jobs')->count();
        if ($failedJobs > 0) {
            $this->error("❌ 有 {$failedJobs} 个失败的任务");
        }

        // 检查缓存中的预测数据
        $predictionCache = Cache::get('game:current_prediction');
        if ($predictionCache) {
            $this->info("✅ 预测数据缓存: 轮次 {$predictionCache['round_id']}");
        }

        // 检查当前轮次缓存
        $currentRound = Cache::get('game:current_round');
        if ($currentRound) {
            $this->info("✅ 当前轮次缓存: {$currentRound['round_id']} ({$currentRound['status']})");
        }

        return 0;
    }

    /**
     * 检查队列中的任务
     */
    private function checkJobs(): int
    {
        $this->info('🔍 检查队列中的任务详情');

        // 检查各个队列的任务
        $queues = ['default'];

        foreach ($queues as $queue) {
            $jobs = DB::table('jobs')
                ->where('queue', $queue)
                ->orderBy('created_at', 'asc')
                ->limit(5)
                ->get(['id', 'queue', 'payload', 'created_at']);

            if ($jobs->isEmpty()) {
                $this->line("📋 {$queue} 队列: 无任务");
            } else {
                $this->info("📋 {$queue} 队列: {$jobs->count()} 个任务");
                foreach ($jobs as $job) {
                    $payload = json_decode($job->payload, true);
                    $jobClass = $payload['displayName'] ?? 'Unknown';
                    $this->line("  - {$jobClass} (ID: {$job->id})");
                }
            }
        }

        return 0;
    }

    /**
     * 🔧 新增：监控WebSocket连接状态
     */
    private function monitorConnection(): int
    {
        $this->info('🔍 WebSocket连接状态监控');
        $this->info('按 Ctrl+C 停止监控');

        $this->setupSignalHandlers();

        // 设置控制台输出回调
        $this->webSocketService->setConsoleOutput(function ($message, $level = 'info') {
            match($level) {
                'error' => $this->error($message),
                'warn' => $this->warn($message),
                default => $this->info($message)
            };
        });

        try {
            // 启动WebSocket服务
            $this->webSocketService->startListening();
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ 连接监控发生错误: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
