<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GamePredictionService;
use App\Services\ScoreMixer;
use App\Services\EloRatingEngine;

class TestDynamicParameters extends Command
{
    protected $signature = 'test:dynamic-parameters';
    protected $description = 'Test dynamic parameter loading in prediction services';

    public function handle(): int
    {
        $this->info("🧪 Testing Dynamic Parameters...");

        // 测试 GamePredictionService 的参数获取
        $gamePredictionService = app(GamePredictionService::class);
        $reflection = new \ReflectionClass($gamePredictionService);
        $method = $reflection->getMethod('getActiveStrategyParameters');
        $method->setAccessible(true);

        $strategyParams = $method->invoke($gamePredictionService);

        $this->info("📋 Active Strategy Parameters:");
        foreach ($strategyParams as $key => $value) {
            $this->line("  - {$key}: {$value}");
        }

        // 测试 ScoreMixer 的动态参数
        $scoreMixer = app(ScoreMixer::class);
        $eloEngine = app(EloRatingEngine::class);

        // 模拟一些测试数据
        $testSymbols = ['BTC', 'ETH', 'ADA'];
        $eloProb = $eloEngine->probabilities($testSymbols);
        $momScore = ['BTC' => 75, 'ETH' => 60, 'ADA' => 45];

        $this->info("🎯 Testing ScoreMixer with dynamic parameters...");

        // 使用默认参数
        $defaultPredictions = $scoreMixer->mix($eloProb, $momScore);

        // 使用动态参数
        $dynamicPredictions = $scoreMixer->mixWithParams($eloProb, $momScore, $strategyParams);

        $this->info("📊 Comparison Results:");
        $this->info("Default Parameters (first prediction):");
        if (!empty($defaultPredictions)) {
            $this->line("  - Symbol: {$defaultPredictions[0]['symbol']}");
            $this->line("  - Final Score: {$defaultPredictions[0]['final_score']}");
            $this->line("  - Confidence: {$defaultPredictions[0]['confidence']}%");
        }

        $this->info("Dynamic Parameters (first prediction):");
        if (!empty($dynamicPredictions)) {
            $this->line("  - Symbol: {$dynamicPredictions[0]['symbol']}");
            $this->line("  - Final Score: {$dynamicPredictions[0]['final_score']}");
            $this->line("  - Confidence: {$dynamicPredictions[0]['confidence']}%");
        }

        $this->info("✅ Dynamic parameter test completed successfully!");
        return 0;
    }
}
