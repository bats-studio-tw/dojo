<?php

namespace App\Services\Prediction;

use App\Contracts\Prediction\FeatureProviderInterface;
use App\Contracts\Prediction\MarketDataProviderInterface;
use App\Models\PredictionResult;
use Illuminate\Support\Facades\Log;

class PredictionService
{
    public function __construct(
        private MarketDataProviderInterface $dataProvider,
        private array $featureProviders,
        private ScoreAggregator $aggregator,
        private string $strategyTag = 'default'
    ) {}

    /**
     * 执行预测
     *
     * @param array $symbols 代币符号数组
     * @param int $timestamp 时间戳
     * @param array $history 历史数据
     * @param int $gameRoundId 游戏回合ID
     * @return array 预测结果
     */
    public function predict(array $symbols, int $timestamp, array $history, int $gameRoundId): array
    {
        try {
            // 1. 获取行情数据
            $snapshots = $this->dataProvider->fetchSnapshots($symbols, $timestamp);

            if (empty($snapshots)) {
                Log::warning("No market data available for prediction");
                return [];
            }

            // 2. 提取各特征分数
            $allFeatureScores = [];
            $rawFeatureScores = [];

            foreach ($this->featureProviders as $featureName => $provider) {
                if ($provider instanceof FeatureProviderInterface) {
                    $featureScores = $provider->extractFeatures($snapshots, $history);
                    $allFeatureScores[$featureName] = $featureScores;
                    $rawFeatureScores[$featureName] = $featureScores;
                }
            }

            if (empty($allFeatureScores)) {
                Log::warning("No feature scores extracted");
                return [];
            }

            // 3. 聚合分数
            $finalScores = $this->aggregator->aggregate($allFeatureScores);
            $normalizedScores = $this->aggregator->getNormalizedScores($rawFeatureScores);

            // 4. 排序
            arsort($finalScores);

            // 5. 保存预测结果到数据库
            $this->savePredictionResults($gameRoundId, $finalScores, $rawFeatureScores, $normalizedScores);

            // 6. 返回格式化的结果
            return $this->formatResults($finalScores);

        } catch (\Exception $e) {
            Log::error("Prediction failed: " . $e->getMessage(), [
                'symbols' => $symbols,
                'timestamp' => $timestamp,
                'game_round_id' => $gameRoundId,
                'exception' => $e
            ]);

            return [];
        }
    }

    /**
     * 保存预测结果到数据库
     */
    private function savePredictionResults(int $gameRoundId, array $finalScores, array $rawScores, array $normalizedScores): void
    {
        $rank = 1;

        foreach ($finalScores as $symbol => $finalScore) {
            $predictionData = [
                'game_round_id' => $gameRoundId,
                'token' => $symbol,
                'predict_rank' => $rank++,
                'predict_score' => $finalScore,
                'elo_score' => $rawScores['elo'][$symbol] ?? null,
                'momentum_score' => $rawScores['momentum'][$symbol] ?? null,
                'volume_score' => $rawScores['volume'][$symbol] ?? null,
                'norm_elo' => $normalizedScores['elo'][$symbol] ?? null,
                'norm_momentum' => $normalizedScores['momentum'][$symbol] ?? null,
                'norm_volume' => $normalizedScores['volume'][$symbol] ?? null,
                'used_weights' => $this->getAggregatorWeights(),
                'used_normalization' => $this->getAggregatorNormalizations(),
                'strategy_tag' => $this->strategyTag,
                'config_snapshot' => [
                    'feature_weights' => $this->getAggregatorWeights(),
                    'feature_normalizations' => $this->getAggregatorNormalizations(),
                ],
            ];

            PredictionResult::create($predictionData);
        }
    }

    /**
     * 格式化预测结果
     */
    private function formatResults(array $finalScores): array
    {
        $results = [];
        $rank = 1;

        foreach ($finalScores as $symbol => $score) {
            $results[] = [
                'symbol' => $symbol,
                'score' => $score,
                'rank' => $rank++,
            ];
        }

        return $results;
    }

    /**
     * 获取聚合器的权重配置
     */
    private function getAggregatorWeights(): array
    {
        // 通过反射获取私有属性
        $reflection = new \ReflectionClass($this->aggregator);
        $weightsProperty = $reflection->getProperty('featureWeights');
        $weightsProperty->setAccessible(true);
        return $weightsProperty->getValue($this->aggregator) ?? [];
    }

    /**
     * 获取聚合器的标准化策略配置
     */
    private function getAggregatorNormalizations(): array
    {
        // 通过反射获取私有属性
        $reflection = new \ReflectionClass($this->aggregator);
        $normalizationsProperty = $reflection->getProperty('featureNormalizations');
        $normalizationsProperty->setAccessible(true);
        $normalizations = $normalizationsProperty->getValue($this->aggregator) ?? [];

        return array_map(
            fn($strategy) => get_class($strategy),
            $normalizations
        );
    }
}
