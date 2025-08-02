<?php

namespace App\Services\Prediction\Features;

use App\Contracts\Prediction\FeatureProviderInterface;

class MomentumFeatureProvider implements FeatureProviderInterface
{
    public function extractFeatures(array $snapshots, array $history): array
    {
        $scores = [];

        foreach ($snapshots as $snapshot) {
            // 处理数组和对象两种格式
            if (is_array($snapshot)) {
                $symbol = $snapshot['symbol'] ?? '';
                $priceChange24h = $snapshot['price_change_24h'] ?? 0;
            } else {
                $symbol = $snapshot->symbol ?? '';
                $priceChange24h = $snapshot->price_change_24h ?? 0;
            }

            if (empty($symbol)) {
                continue;
            }

            try {
                // 计算动量分数 - 基于24小时价格变化

                // 将价格变化转换为动量分数 (0-100)
                // 正变化为正分数，负变化为负分数
                $momentumScore = $this->calculateMomentumScore($priceChange24h);

                $scores[$symbol] = $momentumScore;
            } catch (\Exception $e) {
                \Log::warning("Failed to extract momentum feature for {$symbol}: ".$e->getMessage());
                $scores[$symbol] = 0.0; // 默认值
            }
        }

        return $scores;
    }

    /**
     * 计算动量分数
     */
    private function calculateMomentumScore(float $priceChange24h): float
    {
        // 将百分比变化转换为分数
        // 例如: +10% = 10分, -5% = -5分
        // 限制在合理范围内 (-50 到 +50)
        $score = $priceChange24h * 100; // 转换为百分比

        return max(-50, min(50, $score));
    }
}
