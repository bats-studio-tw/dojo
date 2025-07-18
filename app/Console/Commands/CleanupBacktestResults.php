<?php

namespace App\Console\Commands;

use App\Models\BacktestResult;
use App\Models\PredictionStrategy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupBacktestResults extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backtest:cleanup
                            {--days=30 : 保留最近幾天的數據}
                            {--dry-run : 僅顯示要刪除的數據，不實際刪除}
                            {--force : 強制刪除，不詢問確認}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '清理舊的回測結果和策略數據';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info("開始清理回測數據");
        $this->info("保留天數: {$days} 天");
        $this->info("乾跑模式: " . ($dryRun ? '是' : '否'));

        $cutoffDate = now()->subDays($days);

        try {
            // 統計要刪除的數據
            $backtestResultsToDelete = BacktestResult::where('created_at', '<', $cutoffDate)->count();
            $strategiesToDelete = PredictionStrategy::where('status', '!=', 'active')
                ->where('created_at', '<', $cutoffDate)
                ->count();

            $this->info("將要刪除的回測結果: {$backtestResultsToDelete} 條");
            $this->info("將要刪除的非活躍策略: {$strategiesToDelete} 條");

            if ($backtestResultsToDelete === 0 && $strategiesToDelete === 0) {
                $this->info("沒有需要清理的數據");
                return 0;
            }

            // 確認刪除
            if (!$dryRun && !$force) {
                if (!$this->confirm('確定要刪除這些數據嗎？')) {
                    $this->info("操作已取消");
                    return 0;
                }
            }

            if ($dryRun) {
                $this->info("乾跑模式：顯示要刪除的數據詳情");

                // 顯示要刪除的回測結果詳情
                $oldBacktestResults = BacktestResult::where('created_at', '<', $cutoffDate)
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get();

                if ($oldBacktestResults->isNotEmpty()) {
                    $this->info("最近要刪除的回測結果:");
                    foreach ($oldBacktestResults as $result) {
                        $this->line("- ID: {$result->id}, Run ID: {$result->run_id}, 分數: {$result->score}, 創建時間: {$result->created_at}");
                    }
                }

                // 顯示要刪除的策略詳情
                $oldStrategies = PredictionStrategy::where('status', '!=', 'active')
                    ->where('created_at', '<', $cutoffDate)
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get();

                if ($oldStrategies->isNotEmpty()) {
                    $this->info("最近要刪除的策略:");
                    foreach ($oldStrategies as $strategy) {
                        $this->line("- ID: {$strategy->id}, 名稱: {$strategy->strategy_name}, 狀態: {$strategy->status}, 創建時間: {$strategy->created_at}");
                    }
                }

                return 0;
            }

            // 實際執行刪除
            $deletedBacktestResults = BacktestResult::where('created_at', '<', $cutoffDate)->delete();
            $deletedStrategies = PredictionStrategy::where('status', '!=', 'active')
                ->where('created_at', '<', $cutoffDate)
                ->delete();

            $this->info("清理完成！");
            $this->info("已刪除回測結果: {$deletedBacktestResults} 條");
            $this->info("已刪除策略: {$deletedStrategies} 條");

            Log::info('回測數據清理完成', [
                'days_kept' => $days,
                'deleted_backtest_results' => $deletedBacktestResults,
                'deleted_strategies' => $deletedStrategies,
                'cutoff_date' => $cutoffDate->toDateTimeString(),
            ]);

            return 0;

        } catch (\Exception $e) {
            $this->error("清理失敗: " . $e->getMessage());
            Log::error('回測數據清理失敗', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        }
    }
}
