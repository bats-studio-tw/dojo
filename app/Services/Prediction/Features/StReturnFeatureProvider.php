<?php

namespace App\Services\Prediction\Features;

use App\Contracts\Prediction\FeatureProviderInterface;
use App\Models\TokenPrice;

class StReturnFeatureProvider implements FeatureProviderInterface
{
    public function __construct(
        private int $minutes = 5
    ) {}

    public function getKey(): string
    {
        return 'st_return';
    }

    public function extractFeatures(array $snapshots, array $history = []): array
    {
        $out = [];
        $endTs = now()->startOfMinute()->timestamp;
        $startTs = $endTs - $this->minutes * 60;

        foreach ($snapshots as $s) {
            $symbol = is_array($s) ? strtoupper($s['symbol'] ?? '') : strtoupper($s->symbol ?? '');
            if (!$symbol) continue;

            $latest = TokenPrice::where('symbol', $symbol)
                ->where('minute_timestamp', '<=', $endTs)
                ->orderBy('minute_timestamp', 'desc')
                ->value('price_usd');

            // 取窗口内“最接近窗口起点”的价格，避免因缺采样导致的偏移
            $earliest = TokenPrice::where('symbol', $symbol)
                ->where('minute_timestamp', '<=', $startTs)
                ->orderBy('minute_timestamp', 'desc')
                ->value('price_usd');

            $pct = 0.0;
            if ($latest !== null && $earliest !== null && (float)$earliest > 0) {
                // 使用对数收益率的线性近似（百分比表述），更稳健
                $pct = (log((float)$latest) - log((float)$earliest)) * 100.0;
            }

            // 限幅，避免极端值
            $score = max(-50, min(50, $pct));
            $out[$symbol] = [
                'raw' => $score,
                'norm' => $score,
                'meta' => ['window_min' => $this->minutes, 'domain' => 'log'],
            ];
        }

        return $out;
    }
}


