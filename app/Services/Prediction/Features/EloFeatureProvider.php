<?php

namespace App\Services\Prediction\Features;

use App\Contracts\Prediction\FeatureProviderInterface;
use App\Models\TokenRating;
use App\Services\EloRatingEngine;

class EloFeatureProvider implements FeatureProviderInterface
{
    public function __construct(
        private EloRatingEngine $eloRatingEngine
    ) {}

    public function getKey(): string
    {
        return 'elo_rating';
    }

    public function extractFeatures(array $snapshots, array $history = []): array
    {
        $out = [];
        // $snapshots 可为空，这里只需要symbol集合
        $symbols = [];
        foreach ($snapshots as $k => $v) {
            // 兼容两种输入：列表或 token=>data
            if (is_string($k)) {
                $symbols[] = strtoupper($k);
            } else {
                $symbol = is_array($v) ? ($v['symbol'] ?? null) : ($v->symbol ?? null);
                if ($symbol) $symbols[] = strtoupper($symbol);
            }
        }
        $symbols = array_unique(array_filter($symbols));
        foreach ($symbols as $symbol) {
            try {
                $elo = TokenRating::where('symbol', $symbol)->value('elo') ?? 1500.0;
                $out[$symbol] = [
                    'raw' => (float)$elo,
                    'norm' => (float)$elo,
                    'meta' => [],
                ];
            } catch (\Throwable $e) {
                \Log::warning("Failed to extract Elo feature for {$symbol}: ".$e->getMessage());
                $out[$symbol] = [
                    'raw' => 1500.0,
                    'norm' => 1500.0,
                    'meta' => ['fallback' => true],
                ];
            }
        }
        return $out;
    }
}
