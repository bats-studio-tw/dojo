<?php

namespace App\Console\Commands;

use App\Models\GameRound;
use App\Services\Prediction\PredictionServiceFactory;
use Illuminate\Console\Command;

class TestShortTermStrategy extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:short-term-strategy {--symbols=SOL,BTC,ETH : 测试的代币符号}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '测试短期动能策略的预测功能';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $symbols = explode(',', $this->option('symbols'));

        $this->info("🚀 测试短期动能策略");
        $this->info("测试代币: " . implode(', ', $symbols));

        // 测试不同的策略
        $strategies = [
            'short_term' => '短期动能策略',
            'hybrid_momentum' => '混合动能策略',
            'balanced' => '平衡型策略（对比）'
        ];

        foreach ($strategies as $strategyName => $strategyDescription) {
            $this->info("\n📊 测试策略: {$strategyDescription}");
            $this->testStrategy($strategyName, $symbols);
        }

        $this->info("\n✅ 所有策略测试完成！");
    }

    /**
     * 获取策略权重配置
     */
    private function getStrategyWeights(string $strategy): array
    {
        $config = config('prediction.strategies');

        if (isset($config[$strategy]['weights'])) {
            return $config[$strategy]['weights'];
        }

        // 默认权重
        return [
            'elo' => 0.0,
            'momentum' => 0.4,
            'short_term_momentum' => 0.6,
        ];
    }

    /**
     * 测试单个策略
     */
    private function testStrategy(string $strategy, array $symbols): void
    {
        try {
            // 创建使用测试数据提供者的预测服务
            $customConfig = [
                'data_provider' => \App\Services\Prediction\Providers\TestDataProvider::class,
                'features' => [
                    'elo' => false, // 禁用Elo以简化测试
                    'momentum' => true,
                    'short_term_momentum' => true,
                ],
                'weights' => $this->getStrategyWeights($strategy),
                'strategy_tag' => $strategy,
            ];

            $predictionService = PredictionServiceFactory::createWithCustomConfig($customConfig);

            // 创建虚拟游戏回合
            $gameRound = GameRound::create([
                'round_id' => 'test_' . time(),
                'start_time' => now(),
                'end_time' => now()->addSeconds(30),
                'status' => 'active',
                'participants' => []
            ]);

            // 执行预测
            $timestamp = time();
            $history = []; // 可以为空，因为我们主要测试短期动能

            $predictions = $predictionService->predict($symbols, $timestamp, $history, $gameRound->id);

            if (empty($predictions)) {
                $this->warn("  ❌ 策略 {$strategy} 没有生成预测结果");
                return;
            }

            $this->line("  ✅ 成功生成 " . count($predictions) . " 个预测");

            // 显示预测结果
            $tableData = [];
            foreach ($predictions as $prediction) {
                $details = $prediction['details'];

                $tableData[] = [
                    $prediction['symbol'],
                    $prediction['rank'],
                    round($prediction['score'], 2),
                    round($details['elo_score'] ?? 0, 2),
                    round($details['momentum_score'] ?? 0, 2),
                    round($details['short_term_momentum_score'] ?? 0, 2),
                ];
            }

            $this->table(
                ['代币', '排名', '总分', 'Elo分数', '动能分数', '短期动能分数'],
                $tableData
            );

            // 显示权重配置
            if (!empty($predictions)) {
                $weights = $predictions[0]['details']['weights'] ?? [];
                $this->line("  权重配置:");
                foreach ($weights as $feature => $weight) {
                    $this->line("    - {$feature}: " . round($weight * 100, 1) . "%");
                }
            }

            // 清理测试数据
            $gameRound->delete();

        } catch (\Exception $e) {
            $this->error("  ❌ 策略 {$strategy} 测试失败: " . $e->getMessage());
            $this->line("  错误详情: " . $e->getTraceAsString());
        }
    }
}
