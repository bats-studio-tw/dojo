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
                // 使用对数价格回归，降低不同价位代币的量纲差异，提高跨币可比性
                $logPrices = array_map(function ($p) {
                    return $p > 0 ? log($p) : null;
                }, $prices);
                // 若存在非正价格（极端异常），回退到原始价格回归
                if (in_array(null, $logPrices, true)) {
                    $slope = $this->mathUtils->linearRegressionSlope($times, $prices);
                } else {
                    $slope = $this->mathUtils->linearRegressionSlope($times, $logPrices);
                }
                // 放大尺度并裁剪（线性缩放不影响同轮排序，仅用于数值可读性）
                $score = max(-50, min(50, $slope * 1000));
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


