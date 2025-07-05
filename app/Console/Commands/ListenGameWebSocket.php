<?php

namespace App\Console\Commands;

use App\Services\GameWebSocketService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ListenGameWebSocket extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'game:listen-websocket {--monitor-queues : 监控队列状态} {--check-jobs : 检查队列中的任务}';

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

        $this->info('🚀 开始监听游戏 WebSocket...');
        $this->info('按 Ctrl+C 停止监听');

        $this->setupSignalHandlers();

        // 设置控制台输出回调
        $this->webSocketService->setConsoleOutput(function($message, $level = 'info') {
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

            $this->info("✅ WebSocket 监听器正常停止。");
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ WebSocket 监听器发生致命错误: " . $e->getMessage());
            Log::error("WebSocket监听器致命错误", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }

    private function setupSignalHandlers(): void
    {
        // 检查是否为Windows系统
        if (PHP_OS_FAMILY === 'Windows') {
            $this->info("ℹ️ Windows环境下，使用 Ctrl+C 来停止监听器。");
            return;
        }

        if (!function_exists('pcntl_signal')) {
            $this->info("ℹ️ pcntl扩展未安装，跳过信号处理器设置。使用 Ctrl+C 来停止监听器。");
            return;
        }

        $handler = function (int $signal) {
            $this->info("📡 收到信号 " . $signal . "，正在优雅关闭...");
            $this->gracefulShutdown();
        };

        pcntl_signal(SIGTERM, $handler);
        pcntl_signal(SIGINT, $handler);
        pcntl_async_signals(true);

        $this->info("✅ 信号处理器已设置，支持优雅关闭。");
    }

    private function gracefulShutdown(): void
    {
        if ($this->shouldStop) return; // 防止重複執行

        $this->shouldStop = true;
        $this->webSocketService->stopListening();
    }

    /**
     * 监控队列状态
     */
    private function monitorQueues(): int
    {
        $this->info('📊 队列状态监控');
        $this->info('================');

        // 检查数据库队列
        $this->info('🔍 检查数据库队列...');

        $queueStats = DB::table('jobs')
            ->selectRaw('queue, COUNT(*) as count, MIN(created_at) as oldest_job, MAX(created_at) as newest_job')
            ->groupBy('queue')
            ->get();

        if ($queueStats->isEmpty()) {
            $this->warn('⚠️ 数据库队列中没有待处理的任务');
        } else {
            $this->info('📋 数据库队列状态:');
            foreach ($queueStats as $stat) {
                $this->line("  - {$stat->queue}: {$stat->count} 个任务");
                $this->line("    最早任务: {$stat->oldest_job}");
                $this->line("    最新任务: {$stat->newest_job}");
            }
        }

        // 检查失败的任务
        $failedJobs = DB::table('failed_jobs')->count();
        if ($failedJobs > 0) {
            $this->error("❌ 有 {$failedJobs} 个失败的任务");

            $recentFailed = DB::table('failed_jobs')
                ->orderBy('failed_at', 'desc')
                ->limit(5)
                ->get(['queue', 'payload', 'exception', 'failed_at']);

            $this->info('📋 最近失败的任务:');
            foreach ($recentFailed as $job) {
                $this->line("  - 队列: {$job->queue}");
                $this->line("    失败时间: {$job->failed_at}");
                $this->line("    异常: " . substr($job->exception, 0, 100) . '...');
            }
        } else {
            $this->info('✅ 没有失败的任务');
        }

        // 检查缓存中的预测数据
        $this->info('🔍 检查预测数据缓存...');
        $predictionCache = Cache::get('game:current_prediction');
        if ($predictionCache) {
            $this->info('✅ 找到预测数据缓存');
            $this->line("  - 轮次ID: {$predictionCache['round_id']}");
            $this->line("  - 生成时间: {$predictionCache['generated_at']}");
            $this->line("  - 算法: {$predictionCache['algorithm']}");
        } else {
            $this->warn('⚠️ 没有找到预测数据缓存');
        }

        // 检查当前轮次缓存
        $currentRound = Cache::get('game:current_round');
        if ($currentRound) {
            $this->info('✅ 找到当前轮次缓存');
            $this->line("  - 轮次ID: {$currentRound['round_id']}");
            $this->line("  - 状态: {$currentRound['status']}");
            $this->line("  - 代币数量: {$currentRound['token_count']}");
        } else {
            $this->warn('⚠️ 没有找到当前轮次缓存');
        }

        return 0;
    }

    /**
     * 检查队列中的任务
     */
    private function checkJobs(): int
    {
        $this->info('🔍 检查队列中的任务详情');
        $this->info('========================');

        // 检查各个队列的任务
        $queues = ['default', 'predictions', 'elo_updates', 'low'];

        foreach ($queues as $queue) {
            $jobs = DB::table('jobs')
                ->where('queue', $queue)
                ->orderBy('created_at', 'asc')
                ->limit(10)
                ->get(['id', 'queue', 'payload', 'created_at']);

            if ($jobs->isEmpty()) {
                $this->line("📋 {$queue} 队列: 无任务");
            } else {
                $this->info("📋 {$queue} 队列: {$jobs->count()} 个任务");
                foreach ($jobs as $job) {
                    $payload = json_decode($job->payload, true);
                    $jobClass = $payload['displayName'] ?? 'Unknown';

                    $this->line("  - ID: {$job->id}");
                    $this->line("    任务类: {$jobClass}");
                    $this->line("    创建时间: {$job->created_at}");

                    // 尝试解析任务参数
                    if (isset($payload['data']['command'])) {
                        $command = unserialize($payload['data']['command']);
                        if (method_exists($command, 'getRoundId')) {
                            $this->line("    轮次ID: {$command->getRoundId()}");
                        }
                    }
                }
            }
        }

        return 0;
    }
}
