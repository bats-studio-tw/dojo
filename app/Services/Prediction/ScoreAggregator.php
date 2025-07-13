<?php

namespace App\Services\Prediction;

use App\Contracts\Prediction\NormalizationStrategyInterface;

class ScoreAggregator
{
    public function __construct(
        private array $featureWeights,
        private array $featureNormalizations
    ) {
    }

    /**
     * 聚合所有特征分数
     *
     * @param array $allFeatureScores 格式: ['feature_name' => ['symbol' => score, ...], ...]
     * @return array 返回格式: ['symbol' => final_score, ...]
     */
    public function aggregate(array $allFeatureScores): array
    {
        if (empty($allFeatureScores)) {
            return [];
        }

        // 获取所有代币符号
        $symbols = [];
        foreach ($allFeatureScores as $featureScores) {
            $symbols = array_merge($symbols, array_keys($featureScores));
        }
        $symbols = array_unique($symbols);

        // 标准化每个特征的分数
        $normalizedScores = [];
        foreach ($allFeatureScores as $featureName => $featureScores) {
            $normalizationStrategy = $this->featureNormalizations[$featureName] ?? null;

            if ($normalizationStrategy instanceof NormalizationStrategyInterface) {
                $normalizedScores[$featureName] = $normalizationStrategy->normalize($featureScores);
            } else {
                // 如果没有指定标准化策略，使用原始分数
                $normalizedScores[$featureName] = $featureScores;
            }
        }

        // 计算加权混合分数
        $finalScores = [];
        foreach ($symbols as $symbol) {
            $weightedSum = 0;
            $totalWeight = 0;

            foreach ($normalizedScores as $featureName => $scores) {
                if (isset($scores[$symbol])) {
                    $weight = $this->featureWeights[$featureName] ?? 0;
                    $weightedSum += $scores[$symbol] * $weight;
                    $totalWeight += $weight;
                }
            }

            // 避免除零错误
            if ($totalWeight > 0) {
                $finalScores[$symbol] = $weightedSum / $totalWeight;
            } else {
                $finalScores[$symbol] = 0;
            }
        }

        return $finalScores;
    }

    /**
     * 获取标准化后的分数（用于存储到数据库）
     */
    public function getNormalizedScores(array $allFeatureScores): array
    {
        $normalizedScores = [];

        foreach ($allFeatureScores as $featureName => $featureScores) {
            $normalizationStrategy = $this->featureNormalizations[$featureName] ?? null;

            if ($normalizationStrategy instanceof NormalizationStrategyInterface) {
                $normalizedScores[$featureName] = $normalizationStrategy->normalize($featureScores);
            } else {
                $normalizedScores[$featureName] = $featureScores;
            }
        }

        return $normalizedScores;
    }
}
