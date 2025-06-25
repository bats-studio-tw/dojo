<?php

namespace App\Console\Commands;

use App\Services\GamePredictionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class TestPredictionOptimization extends Command
{
    protected $signature = 'game:test-prediction';
    protected $description = '测试预测优化功能';

    public function __construct(
        private GamePredictionService $predictionService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('🧪 开始测试预测优化功能...');

        // 模拟一个游戏轮次的代币列表
        $testTokens = ['PEPE', 'SHIB', 'DOGE', 'FLOKI', 'BONK'];
        $testRoundId = 'test_round_' . time();

        $this->info("📋 测试代币: " . implode(', ', $testTokens));
        $this->info("🎯 测试轮次: {$testRoundId}");

        // 测试生成预测数据
        $this->line('');
        $this->info('⚡ 测试预测数据生成...');
        $startTime = microtime(true);

        $success = $this->predictionService->generateAndCachePrediction($testTokens, $testRoundId);

        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2);

        if ($success) {
            $this->info("✅ 预测数据生成成功，耗时: {$duration}ms");
        } else {
            $this->error("❌ 预测数据生成失败");
            return Command::FAILURE;
        }

        // 测试从缓存获取数据
        $this->line('');
        $this->info('📦 测试从缓存获取预测数据...');
        $startTime = microtime(true);

        $cachedData = $this->predictionService->getCachedPrediction();

        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2);

        if ($cachedData) {
            $this->info("✅ 从缓存获取数据成功，耗时: {$duration}ms");
            $this->line("📊 缓存数据包含 " . count($cachedData['analysis_data']) . " 个代币的分析结果");

            // 显示前3个预测结果
            $this->line('');
            $this->info('🏆 前3名预测结果:');
            foreach (array_slice($cachedData['analysis_data'], 0, 3) as $index => $token) {
                $rank = $index + 1;
                $symbol = $token['symbol'];
                $score = $token['prediction_score'];
                $winRate = $token['win_rate'] ?? 0;
                $this->line("  #{$rank}: {$symbol} (得分: {$score}, 胜率: {$winRate}%)");
            }
        } else {
            $this->error("❌ 从缓存获取数据失败");
            return Command::FAILURE;
        }

        // 测试缓存信息
        $this->line('');
        $this->info('ℹ️ 缓存信息:');
        $this->line("  轮次ID: " . ($cachedData['round_id'] ?? 'unknown'));
        $this->line("  生成时间: " . ($cachedData['generated_at'] ?? 'unknown'));
        $this->line("  算法: " . ($cachedData['algorithm'] ?? 'unknown'));

        // 清理测试缓存
        $this->line('');
        $this->info('🧹 清理测试缓存...');
        $this->predictionService->clearCachedPrediction();
        $this->info("✅ 测试完成！");

        return Command::SUCCESS;
    }
}
