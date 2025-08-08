<?php

namespace App\Services\Prediction\Features;

use App\Contracts\Prediction\FeatureProviderInterface;
use App\Models\TokenRating;

class EloProbDecayedFeatureProvider implements FeatureProviderInterface
{
    public function getKey(): string
    {
        return 'elo_prob_decayed';
    }

    public function extractFeatures(array $snapshots, array $history = []): array
    {
        // 简化版：取 Elo 值映射到 0~1 胜率，后续可引入时间衰减和 pairwise 矩阵
        $out = [];
        foreach ($snapshots as $s) {
            $symbol = is_array($s) ? strtoupper($s['symbol'] ?? '') : strtoupper($s->symbol ?? '');
            if (!$symbol) continue;

            $elo = TokenRating::where('symbol', $symbol)->value('elo') ?? 1500.0;
            // 将 Elo 映射为近似胜率：使用逻辑函数以 1500 为中性
            $p = 1 / (1 + pow(10, (1500 - $elo) / 400)); // 0~1
            $out[$symbol] = [
                'raw' => (float)$p,
                'norm' => (float)$p,
                'meta' => ['mapping' => 'elo_logistic_1500_center'],
            ];
        }
        return $out;
    }
}


