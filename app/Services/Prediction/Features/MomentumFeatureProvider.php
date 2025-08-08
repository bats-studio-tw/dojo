<?php

namespace App\Services\Prediction\Features;

use App\Contracts\Prediction\FeatureProviderInterface;
use App\Models\TokenPrice;

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
            $symbol = is_array($snapshot) ? strtoupper($snapshot['symbol'] ?? '') : strtoupper($snapshot->symbol ?? '');
            if (empty($symbol)) continue;

            try {
                // 使用分钟线直接计算24小时涨跌幅：(最近价 - 24小时前价) / 24小时前价
                $endTs = now()->startOfMinute()->timestamp;
                $startTs = $endTs - 24 * 60 * 60;

                $latest = TokenPrice::where('symbol', $symbol)
                    ->where('minute_timestamp', '<=', $endTs)
                    ->orderBy('minute_timestamp', 'desc')
                    ->value('price_usd');

                $earliest = TokenPrice::where('symbol', $symbol)
                    ->where('minute_timestamp', '<=', $startTs)
                    ->orderBy('minute_timestamp', 'desc')
                    ->value('price_usd');

                $pct = 0.0;
                if ($latest !== null && $earliest !== null && (float)$earliest > 0) {
                    $pct = (($latest - $earliest) / $earliest) * 100; // 百分比
                }

                $mom = $this->calculateMomentumScore($pct);
                $out[$symbol] = [
                    'raw' => $mom,
                    'norm' => $mom,
                    'meta' => ['source' => 'db_24h_pct_change']
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
    private function calculateMomentumScore(float $priceChangePct): float
    {
        // 直接用百分比作为分数，限制范围（-50, 50）
        return max(-50, min(50, $priceChangePct));
    }
}
