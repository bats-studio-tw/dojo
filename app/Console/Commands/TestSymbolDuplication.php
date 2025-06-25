<?php

namespace App\Console\Commands;

use App\Services\GamePredictionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class TestSymbolDuplication extends Command
{
    protected $signature = 'game:test-symbol-fix';
    protected $description = '测试symbol重复问题的修复';

    public function __construct(
        private GamePredictionService $predictionService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('🔍 测试symbol重复问题修复...');

        // 测试场景1：正常的代币列表
        $this->line('');
        $this->info('📋 测试场景1: 正常代币列表');
        $normalTokens = ['AAVE', 'AVAX', 'XRP', 'GOAT', 'NEAR'];
        $this->testTokenProcessing($normalTokens, '正常代币列表');

        // 测试场景2：包含重复的代币列表
        $this->line('');
        $this->info('📋 测试场景2: 包含重复的代币列表');
        $duplicatedTokens = ['AAVE', 'AVAX', 'XRP', 'AVAX', 'NEAR']; // AVAX重复
        $this->testTokenProcessing($duplicatedTokens, '重复代币列表');

        // 测试场景3：大小写混合的代币列表
        $this->line('');
        $this->info('📋 测试场景3: 大小写混合的代币列表');
        $mixedCaseTokens = ['aave', 'AVAX', 'xrp', 'Goat', 'NEAR'];
        $this->testTokenProcessing($mixedCaseTokens, '大小写混合列表');

        $this->line('');
        $this->info('✅ 所有测试完成！');

        return Command::SUCCESS;
    }

    private function testTokenProcessing(array $tokens, string $scenarioName): void
    {
        $roundId = 'test_' . time() . '_' . rand(1000, 9999);

        $this->line("🎯 场景: {$scenarioName}");
        $this->line("📥 输入代币: " . implode(', ', $tokens) . " (数量: " . count($tokens) . ")");

        // 测试预测服务
        $success = $this->predictionService->generateAndCachePrediction($tokens, $roundId);

        if ($success) {
            $cachedData = $this->predictionService->getCachedPrediction();

            if ($cachedData && isset($cachedData['analysis_data'])) {
                $analysisData = $cachedData['analysis_data'];
                $symbols = array_column($analysisData, 'symbol');
                $uniqueSymbols = array_unique($symbols);

                $this->line("📤 输出代币: " . implode(', ', $symbols) . " (数量: " . count($symbols) . ")");
                $this->line("🔢 去重后数量: " . count($uniqueSymbols));

                if (count($symbols) === count($uniqueSymbols)) {
                    $this->info("✅ 无重复symbol - 测试通过");
                } else {
                    $this->error("❌ 发现重复symbol - 测试失败");
                    $this->line("   重复的symbol: " . implode(', ', array_diff_assoc($symbols, $uniqueSymbols)));
                }

                // 显示实际的代币mapping
                $this->line("📊 代币详情:");
                foreach ($analysisData as $index => $token) {
                    $rank = $index + 1;
                    $symbol = $token['symbol'];
                    $name = $token['name'] ?? 'unknown';
                    $score = $token['prediction_score'] ?? 0;
                    $this->line("   #{$rank}: {$symbol} - {$name} (评分: {$score})");
                }
            } else {
                $this->error("❌ 获取缓存数据失败");
            }
        } else {
            $this->error("❌ 生成预测数据失败");
        }

        // 清理测试缓存
        $this->predictionService->clearCachedPrediction();
    }
}
