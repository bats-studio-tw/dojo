<?php

namespace App\Services\Prediction\Features;

use App\Contracts\Prediction\FeatureProviderInterface;
use App\Models\TokenPrice;
use App\Services\Prediction\Utils\MathUtils;

class StVolatilityFeatureProvider implements FeatureProviderInterface
{
    public function __construct(
        private MathUtils $mathUtils = new MathUtils(),
        private int $minutes = 5
    ) {}

    public function getKey(): string
    {
        return 'st_volatility';
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

            $score = 0.0;
            if (count($prices) >= 2) {
                $returns = [];
                for ($i = 1; $i < count($prices); $i++) {
                    $prev = $prices[$i - 1];
                    if ($prev > 0) {
                        // 使用对数收益，鲁棒
                        $returns[] = log($prices[$i]) - log($prev);
                    }
                }
                $vol = $this->mathUtils->standardDeviation($returns);
                // 波动越小越好：将其映射为 [-50,50] 的“稳定性分”，保持与其它短窗特征尺度一致
                $stability0to100 = max(0, 100 - min(100, $vol * 10_000));
                $score = $stability0to100 - 50;
            }

            $out[$symbol] = [
                'raw' => $score,
                'norm' => $score,
                'meta' => ['window_min' => $this->minutes, 'domain' => 'log'],
            ];
        }

        return $out;
    }
}


