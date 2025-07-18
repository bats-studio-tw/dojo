<?php

namespace App\Console\Commands;

use App\Models\TokenPrice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupTokenPricesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'token:cleanup-prices
                            {--days=3 : 保留天數，默認3天}
                            {--dry-run : 乾跑模式，只顯示要刪除的數據}
                            {--force : 強制執行，不需要確認}
                            {--batch-size=1000 : 批量刪除大小}
                            {--symbol=* : 指定代幣符號，不指定則清理所有}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '清理舊的代幣價格數據';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $isDryRun = $this->option('dry-run');
        $force = $this->option('force');
        $batchSize = (int) $this->option('batch-size');
        $symbols = $this->option('symbol');

        $this->info("🧹 開始清理代幣價格數據...");
        $this->info("保留天數: {$days} 天");
        $this->info("模式: " . ($isDryRun ? '乾跑' : '實際執行'));
        $this->info("批量大小: {$batchSize}");

        if (!empty($symbols)) {
            $this->info("指定代幣: " . implode(', ', $symbols));
        }

        try {
            // 計算截止時間
            $cutoffDate = now()->subDays($days);

            // 構建查詢
            $query = TokenPrice::where('created_at', '<', $cutoffDate);

            if (!empty($symbols)) {
                $query->whereIn('symbol', array_map('strtoupper', $symbols));
            }

            // 獲取要刪除的記錄數量
            $totalRecords = $query->count();

            if ($totalRecords === 0) {
                $this->info("✅ 沒有需要清理的數據");
                return 0;
            }

            $this->info("📊 找到 {$totalRecords} 條需要清理的記錄");

            // 計算數據大小
            $dataSize = $this->calculateDataSize($query);
            $this->info("💾 預計釋放空間: " . $this->formatBytes($dataSize));

            // 如果是乾跑模式，只顯示統計信息
            if ($isDryRun) {
                $this->showDryRunStatistics($query, $cutoffDate);
                return 0;
            }

            // 確認執行
            if (!$force && !$this->confirm("確定要刪除 {$totalRecords} 條記錄嗎？")) {
                $this->info("❌ 操作已取消");
                return 0;
            }

            // 執行清理
            $deletedCount = $this->performCleanup($query, $batchSize);

            $this->info("✅ 清理完成，共刪除 {$deletedCount} 條記錄");

            Log::info('代幣價格數據清理完成', [
                'deleted_count' => $deletedCount,
                'retention_days' => $days,
                'cutoff_date' => $cutoffDate->toISOString(),
                'symbols' => $symbols,
            ]);

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ 清理失敗: " . $e->getMessage());
            Log::error('代幣價格數據清理失敗', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 1;
        }
    }

    /**
     * 計算數據大小
     */
    private function calculateDataSize($query): int
    {
        // 估算每條記錄的大小（約 100 字節）
        return $query->count() * 100;
    }

    /**
     * 格式化字節數
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * 顯示乾跑統計信息
     */
    private function showDryRunStatistics($query, $cutoffDate): void
    {
        $this->info("📋 乾跑統計信息:");

        // 按代幣統計
        $symbolStats = $query->select('symbol', DB::raw('count(*) as count'))
            ->groupBy('symbol')
            ->orderByDesc('count')
            ->get();

        $this->info("按代幣統計:");
        foreach ($symbolStats as $stat) {
            $this->line("  {$stat->symbol}: {$stat->count} 條記錄");
        }

        // 按日期統計
        $dateStats = $query->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $this->info("按日期統計:");
        foreach ($dateStats as $stat) {
            $this->line("  {$stat->date}: {$stat->count} 條記錄");
        }

        $this->warn("⚠️ 這是乾跑模式，實際不會刪除任何數據");
    }

    /**
     * 執行清理操作
     */
    private function performCleanup($query, int $batchSize): int
    {
        $totalDeleted = 0;
        $progressBar = $this->output->createProgressBar();
        $progressBar->start();

        do {
            // 分批刪除
            $deleted = $query->limit($batchSize)->delete();
            $totalDeleted += $deleted;

            $progressBar->advance($deleted);

            // 如果刪除的數量少於批量大小，說明已經刪除完畢
            if ($deleted < $batchSize) {
                break;
            }

        } while (true);

        $progressBar->finish();
        $this->newLine();

        return $totalDeleted;
    }
}
