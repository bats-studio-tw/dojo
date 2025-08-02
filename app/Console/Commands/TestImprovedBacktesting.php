<?php

namespace App\Console\Commands;

use App\Jobs\EvaluateBacktestParameters;
use App\Models\BacktestResult;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestImprovedBacktesting extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:improved-backtesting
                            {--games=100 : 測試的遊戲數量}
                            {--run-id=test_improved : 測試運行ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '測試改進後的回測演算法，驗證加權準確率和提升的信賴度權重';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $gameCount = (int) $this->option('games');
        $runId = $this->option('run-id');

        $this->info("開始測試改進後的回測演算法...");
        $this->info("測試遊戲數量: {$gameCount}");
        $this->info("運行ID: {$runId}");

        // 清理之前的測試結果
        $this->cleanupPreviousResults($runId);

        // 定義幾組測試參數來對比效果
        $testParameters = [
            [
                'name' => '高Elo權重組合',
                'params' => [
                    'elo_weight' => 0.8,
                    'momentum_weight' => 0.2,
                    'h2h_min_games_threshold' => 3,
                    'enhanced_stability_penalty' => 0.3,
                ]
            ],
            [
                'name' => '均衡權重組合',
                'params' => [
                    'elo_weight' => 0.5,
                    'momentum_weight' => 0.5,
                    'h2h_min_games_threshold' => 5,
                    'enhanced_stability_penalty' => 0.5,
                ]
            ],
            [
                'name' => '高動能權重組合',
                'params' => [
                    'elo_weight' => 0.3,
                    'momentum_weight' => 0.7,
                    'h2h_min_games_threshold' => 7,
                    'enhanced_stability_penalty' => 0.7,
                ]
            ],
            [
                'name' => '之前的霸榜組合',
                'params' => [
                    'elo_weight' => 0.7,
                    'momentum_weight' => 0.3,
                    'h2h_min_games_threshold' => 5,
                    'enhanced_stability_penalty' => 0.5,
                ]
            ]
        ];

        $this->info("\n測試參數組合數量: " . count($testParameters));

        // 派遣回測任務
        $jobs = [];
        foreach ($testParameters as $test) {
            $this->line("派遣測試: {$test['name']}");

            $job = new EvaluateBacktestParameters($runId, $test['params'], $gameCount);
            $jobs[] = [
                'name' => $test['name'],
                'job' => $job,
                'params' => $test['params']
            ];

            // 直接執行任務（同步執行以便立即查看結果）
            dispatch_sync($job);
        }

        $this->info("\n所有回測任務已完成！");

        // 分析結果
        $this->analyzeResults($runId);

        return Command::SUCCESS;
    }

    /**
     * 清理之前的測試結果
     */
    private function cleanupPreviousResults(string $runId): void
    {
        $deletedCount = BacktestResult::where('run_id', $runId)->count();

        if ($deletedCount > 0) {
            BacktestResult::where('run_id', $runId)->delete();
            $this->warn("已清理 {$deletedCount} 條之前的測試結果");
        }
    }

    /**
     * 分析測試結果
     */
    private function analyzeResults(string $runId): void
    {
        $results = BacktestResult::where('run_id', $runId)
            ->orderBy('score', 'desc')
            ->get();

        if ($results->isEmpty()) {
            $this->error("沒有找到測試結果！");
            return;
        }

        $this->info("\n==================== 測試結果分析 ====================\n");

        // 顯示排行榜
        $this->displayRankings($results);

        // 顯示權重影響分析
        $this->displayWeightAnalysis($results);

        // 顯示改進效果總結
        $this->displayImprovementSummary($results);
    }

    /**
     * 顯示排行榜
     */
    private function displayRankings($results): void
    {
        $this->info("🏆 參數組合排行榜 (按綜合評分排序):");
        $this->newLine();

        $headers = ['排名', '參數Hash', '綜合評分', '傳統準確率', '加權準確率', '平均信賴度', '參數組合'];
        $rows = [];

        foreach ($results as $index => $result) {
            $params = $result->parameters; // 已经是数组，不需要json_decode
            $paramsStr = sprintf(
                'Elo:%.1f Momentum:%.1f Threshold:%d Penalty:%.1f',
                $params['elo_weight'] ?? 0,
                $params['momentum_weight'] ?? 0,
                $params['h2h_min_games_threshold'] ?? 0,
                $params['enhanced_stability_penalty'] ?? 0
            );

            $rows[] = [
                $index + 1,
                substr($result->params_hash, 0, 8) . '...',
                number_format($result->score, 4),
                number_format($result->accuracy, 2) . '%',
                number_format($result->weighted_accuracy ?? 0, 2) . '%',
                number_format($result->avg_confidence, 2),
                $paramsStr
            ];
        }

        $this->table($headers, $rows);
    }

    /**
     * 顯示權重影響分析
     */
    private function displayWeightAnalysis($results): void
    {
        $this->info("\n📊 權重影響分析:");
        $this->newLine();

        $maxScoreDiff = 0;
        $maxAccuracyDiff = 0;
        $avgConfidenceRange = [];

        foreach ($results as $result) {
            $scoreDiff = $result->score - $results->last()->score;
            $accuracyDiff = abs(($result->weighted_accuracy ?? 0) - $result->accuracy);

            $maxScoreDiff = max($maxScoreDiff, $scoreDiff);
            $maxAccuracyDiff = max($maxAccuracyDiff, $accuracyDiff);
            $avgConfidenceRange[] = $result->avg_confidence;
        }

        $confidenceRange = max($avgConfidenceRange) - min($avgConfidenceRange);

        $this->line("• 最大綜合評分差距: " . number_format($maxScoreDiff, 4) . " 分");
        $this->line("• 加權vs傳統準確率最大差異: " . number_format($maxAccuracyDiff, 2) . "%");
        $this->line("• 信賴度範圍: " . number_format(min($avgConfidenceRange), 2) . " ~ " . number_format(max($avgConfidenceRange), 2) . " (範圍: " . number_format($confidenceRange, 2) . ")");

        // 分析信賴度權重的影響
        if (count($results) >= 2) {
            $top = $results->first();
            $second = $results->skip(1)->first();

            $scoreDiff = $top->score - $second->score;
            $accuracyDiff = $top->accuracy - $second->accuracy;
            $confidenceDiff = $top->avg_confidence - $second->avg_confidence;

            $this->newLine();
            $this->line("🔍 冠軍vs亞軍分析:");
            $this->line("• 評分差距: " . number_format($scoreDiff, 4));
            $this->line("• 準確率差距: " . number_format($accuracyDiff, 2) . "%");
            $this->line("• 信賴度差距: " . number_format($confidenceDiff, 2) . " (權重0.5後貢獻: " . number_format($confidenceDiff * 0.5, 2) . "分)");
        }
    }

    /**
     * 顯示改進效果總結
     */
    private function displayImprovementSummary($results): void
    {
        $this->info("\n✨ 演算法改進效果總結:");
        $this->newLine();

        $this->line("🎯 主要改進:");
        $this->line("  1. 信賴度權重從 0.1 提升到 0.5 (提升5倍影響力)");
        $this->line("  2. 引入時間加權機制 (最新遊戲權重1.5，最舊遊戲權重0.5)");
        $this->line("  3. 新增加權準確率指標，更敏感地反映近期表現");

        $this->newLine();
        $this->line("📈 預期效果:");
        $this->line("  • 系統將更偏好信賴度高的策略");
        $this->line("  • 近期表現差的策略會更快被淘汰");
        $this->line("  • 減少策略僵化，提升自適應能力");

        if (count($results) > 1) {
            $hasWeightedAccuracy = $results->first()->weighted_accuracy !== null;
            if ($hasWeightedAccuracy) {
                $this->line("  • ✅ 加權準確率機制正常運作");
            } else {
                $this->error("  • ❌ 警告：加權準確率為空，可能需要檢查實作");
            }
        }

        $this->newLine();
        $this->info("🚀 下一步建議:");
        $this->line("  1. 觀察接下來幾次自動晉升的結果");
        $this->line("  2. 監控策略多樣性是否提升");
        $this->line("  3. 如有需要，可微調權重參數");

        $this->newLine();
        $this->info("測試完成！可以運行 'php artisan backtest:parameters' 來進行正式回測。");
    }
}
