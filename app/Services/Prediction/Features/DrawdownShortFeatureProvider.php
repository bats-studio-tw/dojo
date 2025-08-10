<?php

namespace App\Services\Prediction\Features;

use App\Contracts\Prediction\FeatureProviderInterface;
use App\Models\TokenPrice;

class DrawdownShortFeatureProvider implements FeatureProviderInterface
{
    public function __construct(
        private int $minutes = 15
    ) {}

    public function getKey(): string
    {
        return 'drawdown_short';
    }

    public function extractFeatures(array $snapshots, array $history = []): array
    {
        $out = [];
        $endTs = now()->startOfMinute()->timestamp;
        $startTs = $endTs - $this->minutes * 60;

        foreach ($snapshots as $s) {
            $symbol = is_array($s) ? strtoupper($s['symbol'] ?? '') : strtoupper($s->symbol ?? '');
            if (!$symbol) continue;

            $prices = TokenPrice::where('symbol', $symbol)
                ->where('minute_timestamp', '>=', $startTs)
                ->where('minute_timestamp', '<=', $endTs)
                ->orderBy('minute_timestamp', 'asc')
                ->pluck('price_usd')
                ->map(fn($p) => (float)$p)
                ->values()
                ->toArray();

            $maxDd = 0.0; // 最大回撤（正值，代表下跌比例）
            if (count($prices) >= 2) {
                $peak = $prices[0];
                foreach ($prices as $price) {
                    $peak = max($peak, $price);
                    if ($peak > 0) {
                        $dd = ($peak - $price) / $peak; // 0~1
                        $maxDd = max($maxDd, $dd);
                    }
                }
            }

            // 回撤越小越好，映射为 0-100 稳定性分；再映射为 [-50,50] 以与动量/趋势尺度一致
            $stability0to100 = max(0, 100 - min(100, $maxDd * 100));
            $score = ($stability0to100 - 50); // 范围约 [-50, 50]
            $out[$symbol] = [
                'raw' => $score,
                'norm' => $score,
                'meta' => ['window_min' => $this->minutes, 'domain' => 'linear'],
            ];
        }

        return $out;
    }
}


