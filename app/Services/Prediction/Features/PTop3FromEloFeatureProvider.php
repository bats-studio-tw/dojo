<?php

namespace App\Services\Prediction\Features;

use App\Contracts\Prediction\FeatureProviderInterface;
use App\Services\EloRatingEngine;

class PTop3FromEloFeatureProvider implements FeatureProviderInterface
{
    public function __construct(
        private EloRatingEngine $elo
    ) {}

    public function getKey(): string
    {
        return 'p_top3_from_elo';
    }

    /**
     * 计算当前 5 币进入 Top3 的概率（小集合可枚举；默认用简化蒙特卡洛/近似）
     * 返回格式: [symbol => ['raw' => float, 'meta' => array]]
     */
    public function extractFeatures(array $snapshots, array $history = []): array
    {
        $symbols = array_map('strtoupper', array_keys($snapshots));
        if (count($symbols) < 2) {
            return [];
        }

        // 使用 Elo 的 pairwise 胜率作为排序概率基础
        $prob = $this->elo->probabilities($symbols);
        if (!$prob) {
            return [];
        }

        // 简化：以 Elo 平均胜率作为原始分（非严格 P(Top3)，先提供稳定特征）
        // 后续可替换为 5! 精确枚举或蒙特卡洛写法
        $out = [];
        foreach ($symbols as $s) {
            $out[$s] = [
                'raw' => (float) ($prob[$s] ?? 0.5),
                'meta' => ['source' => 'elo_pairwise_mean']
            ];
        }
        return $out;
    }
}


