<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class QueueMonitor extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:monitor {--interval=5 : 监控间隔（秒）} {--once : 只检查一次}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '实时监控队列状态和任务执行情况';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $interval = (int) $this->option('interval');
        $once = $this->option('once');

        $this->info('📊 队列监控器启动');
        $this->info("监控间隔: {$interval} 秒");
        $this->info('按 Ctrl+C 停止监控');
        $this->info('');

        do {
            $this->displayQueueStatus();

            if (!$once) {
                sleep($interval);
                $this->output->write("\033[2J\033[H"); // 清屏
            }
        } while (!$once && !$this->shouldStop());

        return 0;
    }

    /**
     * 显示队列状态
     */
    private function displayQueueStatus(): void
    {
        $timestamp = now()->format('Y-m-d H:i:s');
        $this->info("🕐 {$timestamp}");
        $this->info('=' . str_repeat('=', 50));

        // 1. 队列统计
        $this->displayQueueStats();

        // 2. 失败任务
        $this->displayFailedJobs();

        // 3. 缓存状态
        $this->displayCacheStatus();

        // 4. 最近任务
        $this->displayRecentJobs();

        $this->info('');
    }

    /**
     * 显示队列统计
     */
    private function displayQueueStats(): void
    {
        $this->info('📋 队列统计:');

        $queueStats = DB::table('jobs')
            ->selectRaw('queue, COUNT(*) as count, MIN(created_at) as oldest_job, MAX(created_at) as newest_job')
            ->groupBy('queue')
            ->get();

        if ($queueStats->isEmpty()) {
            $this->warn('  ⚠️ 所有队列都为空');
        } else {
            foreach ($queueStats as $stat) {
                $oldestTime = $stat->oldest_job ? now()->diffForHumans($stat->oldest_job) : 'N/A';
                $newestTime = $stat->newest_job ? now()->diffForHumans($stat->newest_job) : 'N/A';

                $this->line("  📦 {$stat->queue}: {$stat->count} 个任务");
                $this->line("     最早: {$oldestTime} | 最新: {$newestTime}");
            }
        }
    }

    /**
     * 显示失败任务
     */
    private function displayFailedJobs(): void
    {
        $failedCount = DB::table('failed_jobs')->count();

        if ($failedCount > 0) {
            $this->error("❌ 失败任务: {$failedCount} 个");

            $recentFailed = DB::table('failed_jobs')
                ->orderBy('failed_at', 'desc')
                ->limit(3)
                ->get(['queue', 'exception', 'failed_at']);

            foreach ($recentFailed as $job) {
                $exception = substr($job->exception, 0, 50) . '...';
                $failedTime = now()->diffForHumans($job->failed_at);

                $this->line("  💥 {$job->queue}: {$exception}");
                $this->line("     失败时间: {$failedTime}");
            }
        } else {
            $this->info('✅ 没有失败任务');
        }
    }

    /**
     * 显示缓存状态
     */
    private function displayCacheStatus(): void
    {
        $this->info('💾 缓存状态:');

        // 预测数据缓存
        $predictionCache = Cache::get('game:current_prediction');
        if ($predictionCache) {
            $generatedTime = now()->diffForHumans($predictionCache['generated_at']);
            $this->line("  🧠 预测数据: {$predictionCache['round_id']} ({$generatedTime})");
        } else {
            $this->warn('  ⚠️ 无预测数据缓存');
        }

        // 当前轮次缓存
        $currentRound = Cache::get('game:current_round');
        if ($currentRound) {
            $this->line("  🎮 当前轮次: {$currentRound['round_id']} ({$currentRound['status']})");
        } else {
            $this->warn('  ⚠️ 无当前轮次缓存');
        }

        // Elo 更新任务派遣状态
        $eloUpdateKeys = [];
        for ($i = 1; $i <= 10; $i++) {
            $key = "elo_update_dispatched:{$i}";
            if (Cache::has($key)) {
                $eloUpdateKeys[] = $i;
            }
        }

        if (!empty($eloUpdateKeys)) {
            $this->line("  🏆 Elo更新已派遣: " . implode(', ', $eloUpdateKeys));
        }
    }

    /**
     * 显示最近任务
     */
    private function displayRecentJobs(): void
    {
        $this->info('🔄 最近任务:');

        $recentJobs = DB::table('jobs')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get(['id', 'queue', 'payload', 'created_at']);

        if ($recentJobs->isEmpty()) {
            $this->warn('  ⚠️ 没有最近任务');
        } else {
            foreach ($recentJobs as $job) {
                $payload = json_decode($job->payload, true);
                $jobClass = $payload['displayName'] ?? 'Unknown';
                $createdTime = now()->diffForHumans($job->created_at);

                $this->line("  📝 {$job->queue}: {$jobClass}");
                $this->line("     ID: {$job->id} | 创建: {$createdTime}");

                // 尝试解析任务参数
                if (isset($payload['data']['command'])) {
                    try {
                        $command = unserialize($payload['data']['command']);
                        if (method_exists($command, 'getRoundId')) {
                            $this->line("     轮次: {$command->getRoundId()}");
                        }
                    } catch (\Exception $e) {
                        // 忽略反序列化错误
                    }
                }
            }
        }
    }

    /**
     * 检查是否应该停止
     */
    private function shouldStop(): bool
    {
        return false; // 持续运行，直到用户按 Ctrl+C
    }
}
