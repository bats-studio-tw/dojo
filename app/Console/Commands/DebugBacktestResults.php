<?php

namespace App\Console\Commands;

use App\Models\BacktestResult;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DebugBacktestResults extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'debug:backtest-results
                            {--run-id= : 特定的运行ID}
                            {--latest=5 : 显示最新的N个运行ID}
                            {--clear-duplicates : 清除重复数据}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '调试回测结果，查看数据库状态和重复问题';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $runId = $this->option('run-id');
        $latest = (int) $this->option('latest');
        $clearDuplicates = $this->option('clear-duplicates');

        $this->info('🔍 开始调试回测结果数据库...');

        if ($runId) {
            $this->analyzeSpecificRunId($runId);
        } else {
            $this->analyzeOverallStatus($latest);
        }

        if ($clearDuplicates) {
            $this->clearDuplicateData();
        }

        return 0;
    }

    /**
     * 分析特定run_id的详情
     */
    private function analyzeSpecificRunId(string $runId): void
    {
        $this->info("📊 分析运行ID: {$runId}");

        $results = BacktestResult::where('run_id', $runId)
            ->orderBy('score', 'desc')
            ->get();

        if ($results->isEmpty()) {
            $this->warn("❌ 未找到运行ID '{$runId}' 的任何结果");
            return;
        }

        $this->info("✅ 找到 {$results->count()} 个结果");

        // 显示参数分布
        $this->displayParameterDistribution($results);

        // 显示最佳结果
        $this->displayTopResults($results->take(5));

        // 检查重复的参数哈希
        $this->checkDuplicateHashes($results);
    }

    /**
     * 分析整体状态
     */
    private function analyzeOverallStatus(int $latest): void
    {
        $this->info('📈 回测结果整体状态分析');

        // 获取所有运行ID及其统计
        $runStats = BacktestResult::select('run_id', DB::raw('COUNT(*) as result_count'), DB::raw('MAX(created_at) as latest_run'))
            ->groupBy('run_id')
            ->orderBy('latest_run', 'desc')
            ->limit($latest)
            ->get();

        if ($runStats->isEmpty()) {
            $this->warn('❌ 数据库中没有任何回测结果');
            return;
        }

        $headers = ['运行ID', '结果数量', '最新执行时间', '状态'];
        $rows = [];

        foreach ($runStats as $stat) {
            $status = $this->analyzeRunIdStatus($stat->run_id, $stat->result_count);
            $rows[] = [
                $stat->run_id,
                $stat->result_count,
                $stat->latest_run,
                $status
            ];
        }

        $this->table($headers, $rows);

        // 分析ultra-fast的问题
        $this->analyzeUltraFastProblem();
    }

    /**
     * 分析ultra-fast的具体问题
     */
    private function analyzeUltraFastProblem(): void
    {
        $this->newLine();
        $this->info('🔍 分析ultra-fast运行ID的问题...');

        $ultraFastResults = BacktestResult::where('run_id', 'ultra-fast')
            ->orderBy('created_at')
            ->get();

        if ($ultraFastResults->isEmpty()) {
            $this->info('ℹ️ 未找到ultra-fast的回测结果');
            return;
        }

        // 按时间分组，看看是否有重复执行
        $timeGroups = $ultraFastResults->groupBy(function ($item) {
            return $item->created_at ? $item->created_at->format('Y-m-d H:i') : 'unknown';
        });

        $this->info("📅 ultra-fast总共有 {$ultraFastResults->count()} 个结果");
        $this->info("📅 分布在 {$timeGroups->count()} 个时间点");

        // 检查是否有重复的参数哈希
        $hashCounts = $ultraFastResults->groupBy('params_hash')->map->count();
        $duplicateHashes = $hashCounts->filter(function ($count) {
            return $count > 1;
        });

        if ($duplicateHashes->count() > 0) {
            $this->warn("⚠️ 发现 {$duplicateHashes->count()} 个重复的参数哈希");
            foreach ($duplicateHashes as $hash => $count) {
                $this->line("   - 哈希 {$hash}: {$count} 次重复");
            }
        }

        // 显示理论上应该有的参数组合数量
        $expectedCombinations = $this->calculateExpectedCombinations();
        $this->info("🧮 理论参数组合数量: {$expectedCombinations}");
        $this->info("💾 实际数据库记录: {$ultraFastResults->count()}");

        if ($ultraFastResults->count() == $expectedCombinations) {
            $this->info("✅ 参数组合数量正确，问题是重复使用run_id导致跳过执行");
        } else {
            $this->warn("❌ 参数组合数量不匹配，可能存在其他问题");
        }
    }

    /**
     * 计算期望的参数组合数量
     */
    private function calculateExpectedCombinations(): int
    {
        $parameterGrid = config('backtest.parameter_grid', []);

        $eloWeights = $parameterGrid['elo_weight'] ?? [];
        $momentumWeights = $parameterGrid['momentum_weight'] ?? [];
        $thresholds = $parameterGrid['h2h_min_games_threshold'] ?? [];
        $penalties = $parameterGrid['enhanced_stability_penalty'] ?? [];

        // 计算有效的权重组合
        $validWeightCombinations = 0;
        foreach ($eloWeights as $elo) {
            foreach ($momentumWeights as $momentum) {
                if (abs($elo + $momentum - 1.0) < 1e-6) {
                    $validWeightCombinations++;
                }
            }
        }

        return $validWeightCombinations * count($thresholds) * count($penalties);
    }

    /**
     * 分析运行ID状态
     */
    private function analyzeRunIdStatus(string $runId, int $resultCount): string
    {
        if ($runId === 'ultra-fast') {
            $expected = $this->calculateExpectedCombinations();
            return $resultCount == $expected ? '✅ 正常' : "⚠️ 异常 (期望:{$expected})";
        }

        if (str_contains($runId, 'ultra-fast')) {
            return '🟡 可能重复';
        }

        return '✅ 正常';
    }

    /**
     * 显示参数分布
     */
    private function displayParameterDistribution($results): void
    {
        $this->newLine();
        $this->info('📊 参数分布分析:');

        $eloWeights = $results->map(function ($result) {
            $params = is_string($result->parameters) ? json_decode($result->parameters, true) : $result->parameters;
            return $params['elo_weight'] ?? null;
        })->filter()->countBy();

        $this->line('Elo权重分布:');
        foreach ($eloWeights as $weight => $count) {
            $this->line("   - {$weight}: {$count}次");
        }
    }

    /**
     * 显示最佳结果
     */
    private function displayTopResults($results): void
    {
        $this->newLine();
        $this->info('🏆 最佳结果 (前5名):');

        $headers = ['排名', '分数', 'Elo权重', 'Momentum权重', '阈值', '惩罚', '创建时间'];
        $rows = [];

        foreach ($results as $index => $result) {
            $params = is_string($result->parameters) ? json_decode($result->parameters, true) : $result->parameters;
            $rows[] = [
                $index + 1,
                number_format($result->score, 4),
                $params['elo_weight'] ?? 'N/A',
                $params['momentum_weight'] ?? 'N/A',
                $params['h2h_min_games_threshold'] ?? 'N/A',
                $params['enhanced_stability_penalty'] ?? 'N/A',
                $result->created_at ? $result->created_at->format('m-d H:i') : 'N/A'
            ];
        }

        $this->table($headers, $rows);
    }

    /**
     * 检查重复的参数哈希
     */
    private function checkDuplicateHashes($results): void
    {
        $hashCounts = $results->groupBy('params_hash')->map->count();
        $duplicates = $hashCounts->filter(function ($count) {
            return $count > 1;
        });

        if ($duplicates->count() > 0) {
            $this->newLine();
            $this->warn("⚠️ 发现重复的参数哈希:");
            foreach ($duplicates as $hash => $count) {
                $this->line("   - {$hash}: {$count}次重复");
            }
        } else {
            $this->info("✅ 没有发现重复的参数哈希");
        }
    }

    /**
     * 清除重复数据
     */
    private function clearDuplicateData(): void
    {
        $this->newLine();
        $this->info('🧹 开始清除重复数据...');

        if (!$this->confirm('确定要清除重复的回测结果吗？此操作不可逆！')) {
            $this->info('操作已取消');
            return;
        }

        // 只保留每个run_id + params_hash组合的最新记录
        $duplicateQuery = "
            DELETE t1 FROM backtest_results t1
            INNER JOIN backtest_results t2
            WHERE t1.run_id = t2.run_id
            AND t1.params_hash = t2.params_hash
            AND t1.id < t2.id
        ";

        $deletedCount = DB::delete($duplicateQuery);
        $this->info("✅ 已删除 {$deletedCount} 条重复记录");
    }
}
