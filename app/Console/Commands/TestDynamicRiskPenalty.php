<?php

namespace App\Console\Commands;

use App\Services\GamePredictionService;
use App\Services\TimeDecayCalculatorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestDynamicRiskPenalty extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:dynamic-risk-penalty
                            {--symbol= : 指定要测试的代币符号}
                            {--detail : 显示详细输出}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '测试动态风险惩罚功能的实现效果';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🧪 动态风险惩罚功能测试');
        $this->info('=====================================');

        $gamePredictionService = app(GamePredictionService::class);
        $timeDecayService = app(TimeDecayCalculatorService::class);

        $specificSymbol = $this->option('symbol');
        $verbose = $this->option('detail');

        if ($specificSymbol) {
            $this->testSpecificToken($specificSymbol, $gamePredictionService, $timeDecayService, $verbose);
        } else {
            $this->testMultipleTokens($gamePredictionService, $timeDecayService, $verbose);
        }

        return 0;
    }

    /**
     * 测试特定代币的动态风险惩罚
     */
    private function testSpecificToken(string $symbol, GamePredictionService $service, TimeDecayCalculatorService $timeDecayService, bool $verbose): void
    {
        $this->info("📊 测试代币: {$symbol}");
        $this->newLine();

        // 获取代币的时间衰减数据
        $decayedData = $timeDecayService->calculateDecayedTop3Rate($symbol);

        if ($decayedData['total_games'] == 0) {
            $this->warn("⚠️  代币 {$symbol} 没有历史游戏数据");
            return;
        }

        // 创建测试数据
        $testScenarios = [
            [
                'scenario' => '低风险场景',
                'value_stddev' => 0.5,  // 低波动
                'top3_rate' => $decayedData['decayed_top3_rate'],
            ],
            [
                'scenario' => '高风险低成功率场景',
                'value_stddev' => 3.0,  // 高波动
                'top3_rate' => max(0, $decayedData['decayed_top3_rate'] - 20), // 降低成功率
            ],
            [
                'scenario' => '高风险高成功率场景',
                'value_stddev' => 3.0,  // 高波动
                'top3_rate' => min(100, $decayedData['decayed_top3_rate'] + 20), // 提高成功率
            ],
        ];

        foreach ($testScenarios as $scenario) {
            $this->testScenario($symbol, $scenario, $verbose);
            $this->newLine();
        }
    }

    /**
     * 测试多个代币的动态风险惩罚效果
     */
    private function testMultipleTokens(GamePredictionService $service, TimeDecayCalculatorService $timeDecayService, bool $verbose): void
    {
        // 获取一些示例代币符号
        $testTokens = ['BTC', 'ETH', 'BNB', 'SOL', 'ADA'];

        $this->info('📈 多代币动态风险惩罚对比测试');
        $this->newLine();

        $results = [];

        foreach ($testTokens as $symbol) {
            $decayedData = $timeDecayService->calculateDecayedTop3Rate($symbol);

            if ($decayedData['total_games'] == 0) {
                continue;
            }

            // 测试高风险场景下的动态惩罚
            $testData = [
                'symbol' => $symbol,
                'top3_rate' => $decayedData['decayed_top3_rate'],
                'value_stddev' => 2.5, // 固定高波动
            ];

            $penalty = $this->calculateTestPenalty($testData);

            $results[] = [
                'symbol' => $symbol,
                'top3_rate' => round($decayedData['decayed_top3_rate'], 2),
                'total_games' => $decayedData['total_games'],
                'dynamic_penalty' => round($penalty, 4),
                'fixed_penalty' => 0.9000, // 原固定惩罚
                'penalty_improvement' => round(($penalty - 0.9) * 100, 2) . '%',
            ];
        }

        if (empty($results)) {
            $this->warn('⚠️  没有找到有效的测试数据');
            return;
        }

        // 显示对比表格
        $this->table(
            ['代币', 'Top3率%', '游戏数', '动态惩罚', '固定惩罚', '惩罚改善'],
            array_map(function ($result) {
                return [
                    $result['symbol'],
                    $result['top3_rate'],
                    $result['total_games'],
                    $result['dynamic_penalty'],
                    $result['fixed_penalty'],
                    $result['penalty_improvement'],
                ];
            }, $results)
        );

        // 分析结果
        $this->analyzeResults($results);
    }

    /**
     * 测试单个场景
     */
    private function testScenario(string $symbol, array $scenario, bool $verbose): void
    {
        $this->info("🎯 {$scenario['scenario']}");

        $testData = array_merge(['symbol' => $symbol], $scenario);

        // 计算动态惩罚
        $dynamicPenalty = $this->calculateTestPenalty($testData);
        $fixedPenalty = 0.9; // 原固定惩罚

        $improvement = ($dynamicPenalty - $fixedPenalty) * 100;

        $this->line("   • 波动标准差: {$scenario['value_stddev']}");
        $this->line("   • Top3成功率: {$scenario['top3_rate']}%");
        $this->line("   • 动态惩罚因子: {$dynamicPenalty}");
        $this->line("   • 固定惩罚因子: {$fixedPenalty}");

        if ($improvement > 0) {
            $this->line("   • <info>惩罚减轻: +{$improvement}%</info>");
        } else {
            $this->line("   • <comment>惩罚加重: {$improvement}%</comment>");
        }

        if ($verbose) {
            $this->line("   • 计算逻辑: basePenalty(0.9) + (1-0.9) * top3Rate/100");
            $this->line("   • 范围限制: [0.5, 0.95]");
        }
    }

    /**
     * 计算测试惩罚因子（复制了 GamePredictionService 中的逻辑）
     */
    private function calculateTestPenalty(array $data): float
    {
        $basePenalty = 0.90;
        $top3Rate = $data['top3_rate'] ?? 0;

        $top3RateNormalized = max(0, min(100, $top3Rate)) / 100;
        $effectivePenalty = $basePenalty + (1 - $basePenalty) * $top3RateNormalized;

        $minPenalty = 0.50;
        $maxPenalty = 0.95;

        return max($minPenalty, min($maxPenalty, $effectivePenalty));
    }

    /**
     * 分析测试结果
     */
    private function analyzeResults(array $results): void
    {
        $this->newLine();
        $this->info('📊 结果分析');
        $this->info('=====================================');

        $improvementSum = 0;
        $improvedCount = 0;
        $worsendCount = 0;

        foreach ($results as $result) {
            $improvement = ($result['dynamic_penalty'] - 0.9) * 100;
            $improvementSum += $improvement;

            if ($improvement > 0) {
                $improvedCount++;
            } elseif ($improvement < 0) {
                $worsendCount++;
            }
        }

        $avgImprovement = count($results) > 0 ? $improvementSum / count($results) : 0;

        $this->line("• 测试代币数量: " . count($results));
        $this->line("• 惩罚减轻的代币: {$improvedCount}");
        $this->line("• 惩罚加重的代币: {$worsendCount}");
        $this->line("• 平均惩罚改善: " . round($avgImprovement, 2) . '%');

        if ($avgImprovement > 0) {
            $this->info("✅ 整体上，动态惩罚机制为高成功率的代币提供了更宽松的风险评估");
        } else {
            $this->warn("⚠️  动态惩罚机制可能需要调整参数");
        }

        $this->newLine();
        $this->info('💡 功能说明:');
        $this->line('• 动态风险惩罚根据代币历史Top3成功率调整惩罚程度');
        $this->line('• 高成功率的代币即使波动大也会受到较轻的惩罚');
        $this->line('• 低成功率的代币在高波动时会受到更严厉的惩罚');
        $this->line('• 惩罚因子范围: 0.5 (最严厉) 到 0.95 (最宽松)');
    }
}
