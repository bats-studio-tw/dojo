<?php

namespace App\Services\Prediction\Features;

use App\Contracts\Prediction\FeatureProviderInterface;

class MomentumFeatureProvider implements FeatureProviderInterface
{
    public function getKey(): string
    {
        return 'momentum_24h';
    }

    public function extractFeatures(array $snapshots, array $history = []): array
    {
        $out = [];

        foreach ($snapshots as $snapshot) {
            if (is_array($snapshot)) {
                $symbol = strtoupper($snapshot['symbol'] ?? '');
                $priceChange24h = $snapshot['price_change_24h'] ?? 0;
            } else {
                $symbol = strtoupper($snapshot->symbol ?? '');
                $priceChange24h = $snapshot->price_change_24h ?? 0;
            }

            if (empty($symbol)) {
                continue;
            }

            try {
                $mom = $this->calculateMomentumScore($priceChange24h);
                $out[$symbol] = [
                    'raw' => $mom,
                    'norm' => $mom,
                    'meta' => ['source' => 'pct_change_24h']
                ];
            } catch (\Exception $e) {
                \Log::warning("Failed to extract momentum feature for {$symbol}: ".$e->getMessage());
                $out[$symbol] = [
                    'raw' => 0.0,
                    'norm' => 0.0,
                    'meta' => ['fallback' => true]
                ];
            }
        }

        return $out;
    }

    /**
     * 计算动量分数
     */
    private function calculateMomentumScore(float $priceChange24h): float
    {
        // 将百分比变化转换为分数
        // 例如: +10% = 10分, -5% = -5分
        // 限制在合理范围内 (-50 到 +50)
        $score = $priceChange24h * 100; // 转换为百分比（与既有定义保持一致）

        return max(-50, min(50, $score));
    }
}
