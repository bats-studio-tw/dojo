<?php

namespace App\Console\Commands;

use App\Services\GamePredictionService;
use App\Services\GlobalStatistics;
use Illuminate\Console\Command;

class TestColdStartStrategy extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:cold-start-strategy
                            {--tokens=* : 测试的代币符号列表，支持新代币和现有代币}
                            {--show-global : 显示全局统计信息}
                            {--clear-cache : 清除全局统计缓存}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '测试冷启动策略的效果，验证新代币和数据稀疏代币的处理';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 开始测试冷启动策略...');
        $this->newLine();

        // 处理选项
        if ($this->option('clear-cache')) {
            GlobalStatistics::clearCache();
            $this->info('✅ 全局统计缓存已清除');
            $this->newLine();
        }

        // 显示全局统计信息
        if ($this->option('show-global')) {
            $this->showGlobalStatistics();
        }

        // 获取测试代币
        $tokens = $this->option('tokens');
        if (empty($tokens)) {
            // 默认测试代币列表：包含新代币和一些已知代币
            $tokens = [
                'NEWTOKEN1',  // 完全新的代币
                'NEWTOKEN2',  // 完全新的代币
                'PEPE',       // 可能存在的代币
                'DOGE',       // 可能存在的代币
                'SHIB',       // 可能存在的代币
            ];
            $this->info('📝 使用默认测试代币: ' . implode(', ', $tokens));
        } else {
            $this->info('📝 使用指定测试代币: ' . implode(', ', $tokens));
        }
        $this->newLine();

        // 测试预测生成
        $this->testPredictionGeneration($tokens);

        $this->newLine();
        $this->info('✅ 冷启动策略测试完成！');
    }

    /**
     * 显示全局统计信息
     */
    private function showGlobalStatistics(): void
    {
        $this->info('📊 全局统计信息:');
        $this->line('─────────────────────────────');

        try {
            $globalStats = GlobalStatistics::getGlobalStats();
            $averageTop3Rate = GlobalStatistics::averageTop3Rate();

            $this->table(
                ['指标', '数值'],
                [
                    ['全局平均 Top3 Rate', $globalStats['average_top3_rate'] . '%'],
                    ['全局平均胜率', $globalStats['average_win_rate'] . '%'],
                    ['全局平均排名', $globalStats['average_avg_rank']],
                    ['全局中位数排名', $globalStats['global_median_rank']],
                    ['有效代币数量', $globalStats['valid_tokens_count']],
                    ['分析代币总数', $globalStats['total_tokens_analyzed']],
                    ['分析轮次数', $globalStats['analysis_rounds']],
                    ['最小游戏阈值', $globalStats['min_games_threshold']],
                    ['计算时间', $globalStats['calculated_at']],
                    ['是否为默认值', $globalStats['is_default'] ?? false ? '是' : '否'],
                ]
            );

            $this->info("🎯 冷启动策略将使用 {$averageTop3Rate}% 作为默认 top3_rate");

        } catch (\Exception $e) {
            $this->error('❌ 获取全局统计信息失败: ' . $e->getMessage());
        }

        $this->newLine();
    }

    /**
     * 测试预测生成
     */
    private function testPredictionGeneration(array $tokens): void
    {
        $this->info('🧪 测试预测生成...');
        $this->line('─────────────────────────────');

        try {
            $predictionService = app(GamePredictionService::class);
            $testRoundId = 'test_cold_start_' . time();

            // 生成预测
            $success = $predictionService->generateAndCachePrediction($tokens, $testRoundId);

            if ($success) {
                $this->info('✅ 预测生成成功');

                // 获取并分析结果
                $cachedPrediction = $predictionService->getCachedPrediction();
                if ($cachedPrediction && isset($cachedPrediction['analysis_data'])) {
                    $this->analyzePredictionResults($cachedPrediction['analysis_data']);
                }
            } else {
                $this->error('❌ 预测生成失败');
            }

        } catch (\Exception $e) {
            $this->error('❌ 测试预测生成时发生错误: ' . $e->getMessage());
        }
    }

    /**
     * 分析预测结果
     */
    private function analyzePredictionResults(array $analysisData): void
    {
        $this->newLine();
        $this->info('📈 预测结果分析:');
        $this->line('─────────────────────────────');

        $coldStartTokens = [];
        $dataInsufficientTokens = [];
        $normalTokens = [];
        $h2hColdStartTokens = [];

        foreach ($analysisData as $tokenData) {
            $symbol = $tokenData['symbol'];

            // 分类代币
            if (isset($tokenData['cold_start_applied']) && $tokenData['cold_start_applied']) {
                if (isset($tokenData['data_insufficient']) && $tokenData['data_insufficient']) {
                    $dataInsufficientTokens[] = $tokenData;
                } else {
                    $coldStartTokens[] = $tokenData;
                }
            } else {
                $normalTokens[] = $tokenData;
            }

            if (isset($tokenData['h2h_cold_start_applied']) && $tokenData['h2h_cold_start_applied']) {
                $h2hColdStartTokens[] = $symbol;
            }
        }

        // 显示分类结果
        $this->info("🆕 完全新代币 (应用冷启动): " . count($coldStartTokens));
        $this->info("📊 数据不足代币 (应用冷启动): " . count($dataInsufficientTokens));
        $this->info("✅ 数据充足代币 (正常计算): " . count($normalTokens));
        $this->info("🤝 H2H数据不足代币: " . count($h2hColdStartTokens));

        $this->newLine();

        // 详细分析冷启动代币
        if (!empty($coldStartTokens)) {
            $this->info('🆕 完全新代币详情:');
            $headers = ['代币', 'Top3 Rate', '排名', '绝对分数', '最终分数', '冷启动'];
            $rows = [];

            foreach ($coldStartTokens as $token) {
                $rows[] = [
                    $token['symbol'],
                    number_format($token['top3_rate'], 1) . '%',
                    $token['predicted_rank'],
                    number_format($token['absolute_score'] ?? 0, 2),
                    number_format($token['risk_adjusted_score'] ?? 0, 2),
                    $token['cold_start_applied'] ? '✅' : '❌',
                ];
            }

            $this->table($headers, $rows);
        }

        // 详细分析数据不足代币
        if (!empty($dataInsufficientTokens)) {
            $this->newLine();
            $this->info('📊 数据不足代币详情:');
            $headers = ['代币', '游戏数', 'Top3 Rate', '排名', '绝对分数', '冷启动'];
            $rows = [];

            foreach ($dataInsufficientTokens as $token) {
                $rows[] = [
                    $token['symbol'],
                    $token['total_games'] ?? 0,
                    number_format($token['top3_rate'], 1) . '%',
                    $token['predicted_rank'],
                    number_format($token['absolute_score'] ?? 0, 2),
                    $token['cold_start_applied'] ? '✅' : '❌',
                ];
            }

            $this->table($headers, $rows);
        }

        // H2H冷启动信息
        if (!empty($h2hColdStartTokens)) {
            $this->newLine();
            $this->info('🤝 H2H数据不足的代币: ' . implode(', ', $h2hColdStartTokens));
        }

        // 总体统计
        $this->newLine();
        $this->info('📋 总体统计:');
        $totalTokens = count($analysisData);
        $coldStartCount = count($coldStartTokens) + count($dataInsufficientTokens);
        $h2hColdStartCount = count($h2hColdStartTokens);

        $this->line("• 总代币数: {$totalTokens}");
        $this->line("• 应用冷启动策略: {$coldStartCount} ({$this->percentage($coldStartCount, $totalTokens)})");
        $this->line("• H2H冷启动: {$h2hColdStartCount} ({$this->percentage($h2hColdStartCount, $totalTokens)})");

        // 验证默认值是否正确应用
        $globalStats = GlobalStatistics::getGlobalStats();
        $expectedTop3Rate = $globalStats['average_top3_rate'];

        $this->newLine();
        $this->info("🎯 验证冷启动默认值应用:");
        $this->line("• 期望的 Top3 Rate: {$expectedTop3Rate}%");

        foreach (array_merge($coldStartTokens, $dataInsufficientTokens) as $token) {
            $actualTop3Rate = $token['top3_rate'];
            $match = abs($actualTop3Rate - $expectedTop3Rate) < 0.01;
            $status = $match ? '✅' : '❌';
            $this->line("• {$token['symbol']}: {$actualTop3Rate}% {$status}");
        }
    }

    /**
     * 计算百分比
     */
    private function percentage(int $part, int $total): string
    {
        if ($total === 0) {
            return '0%';
        }
        return number_format(($part / $total) * 100, 1) . '%';
    }
}
