<?php

namespace App\Services\Prediction;

use App\Contracts\Prediction\NormalizationStrategyInterface;

class ScoreAggregator
{
    public function __construct(
        private array $featureWeights,
        private array $featureNormalizations
    ) {}

    /**
     * 聚合所有特征分数
     *
     * @param  array  $allFeatureScores  格式: ['feature_name' => ['symbol' => score, ...], ...]
     * @return array 返回格式: ['symbol' => ['final_score' => score, 'details' => [...]], ...]
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
            $scoreDetails = [];

            // 收集原始分数
            foreach ($allFeatureScores as $featureName => $featureScores) {
                $scoreDetails[$featureName . '_score'] = $featureScores[$symbol] ?? 0;
            }

            // 收集标准化分数
            foreach ($normalizedScores as $featureName => $scores) {
                $scoreDetails['norm_' . $featureName] = $scores[$symbol] ?? 0;

                if (isset($scores[$symbol])) {
                    $weight = $this->featureWeights[$featureName] ?? 0;
                    $weightedSum += $scores[$symbol] * $weight;
                    $totalWeight += $weight;
                }
            }

            // 计算最终分数
            $finalScore = $totalWeight > 0 ? $weightedSum / $totalWeight : 0;

            $finalScores[$symbol] = [
                'final_score' => $finalScore,
                'weights' => $this->featureWeights,
                'normalization' => array_keys($this->featureNormalizations),
                ...$scoreDetails
            ];
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
