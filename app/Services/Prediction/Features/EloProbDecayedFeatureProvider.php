<?php

namespace App\Services\Prediction\Features;

use App\Contracts\Prediction\FeatureProviderInterface;
use App\Services\EloRatingEngine;

class EloProbDecayedFeatureProvider implements FeatureProviderInterface
{
    public function __construct(
        private EloRatingEngine $elo
    ) {}

    public function getKey(): string
    {
        return 'elo_prob_decayed';
    }

    public function extractFeatures(array $snapshots, array $history = []): array
    {
        // 基于“当前对手组”的时间衰减 Elo，计算 pairwise 平均胜率（更贴合当局对战）
        $symbols = [];
        if (!empty($snapshots)) {
            $firstKey = array_key_first($snapshots);
            if (is_string($firstKey)) {
                $symbols = array_map('strtoupper', array_keys($snapshots));
            } else {
                foreach ($snapshots as $s) {
                    $sym = is_array($s) ? ($s['symbol'] ?? null) : ($s->symbol ?? null);
                    if ($sym) $symbols[] = strtoupper($sym);
                }
            }
        }
        $symbols = array_values(array_unique(array_filter($symbols)));
        if (count($symbols) < 2) return [];

        $prob = $this->elo->probabilities($symbols, true); // 使用时间衰减
        $out = [];
        foreach ($symbols as $s) {
            $p = (float)($prob[$s] ?? 0.5);
            $out[$s] = [
                'raw' => $p,
                'norm' => $p,
                'meta' => ['source' => 'elo_prob_time_decayed_pairwise_mean'],
            ];
        }
        return $out;
    }
}


