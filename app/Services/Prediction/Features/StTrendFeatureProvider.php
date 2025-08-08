<?php

namespace App\Services\Prediction\Features;

use App\Contracts\Prediction\FeatureProviderInterface;
use App\Models\TokenPrice;
use App\Services\Prediction\Utils\MathUtils;

class StTrendFeatureProvider implements FeatureProviderInterface
{
    public function __construct(
        private MathUtils $mathUtils = new MathUtils(),
        private int $minutes = 5
    ) {}

    public function getKey(): string
    {
        return 'st_trend';
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

            if (count($prices) < 3) {
                $score = 0.0;
            } else {
                $times = range(0, count($prices) - 1);
                $slope = $this->mathUtils->linearRegressionSlope($times, $prices);
                // 放大尺度并裁剪
                $score = max(-50, min(50, $slope * 1000));
            }

            $out[$symbol] = [
                'raw' => $score,
                'norm' => $score,
                'meta' => ['window_min' => $this->minutes],
            ];
        }

        return $out;
    }
}


