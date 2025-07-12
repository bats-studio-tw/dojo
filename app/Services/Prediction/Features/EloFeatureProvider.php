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

    public function extractFeatures(array $snapshots, array $history): array
    {
        $scores = [];

        foreach ($snapshots as $snapshot) {
            $symbol = $snapshot['symbol'] ?? $snapshot->symbol ?? '';

            if (empty($symbol)) {
                continue;
            }

            try {
                // 获取当前Elo评分
                $eloRating = TokenRating::where('token', $symbol)
                    ->orderBy('created_at', 'desc')
                    ->first();

                if ($eloRating) {
                    $scores[$symbol] = $eloRating->elo_rating;
                } else {
                    // 如果没有历史Elo评分，使用默认值
                    $scores[$symbol] = 1500.0;
                }
            } catch (\Exception $e) {
                \Log::warning("Failed to extract Elo feature for {$symbol}: " . $e->getMessage());
                $scores[$symbol] = 1500.0; // 默认值
            }
        }

        return $scores;
    }
}
