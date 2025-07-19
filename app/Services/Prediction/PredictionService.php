<?php

namespace App\Services\Prediction;

use App\Contracts\Prediction\FeatureProviderInterface;
use App\Contracts\Prediction\MarketDataProviderInterface;
use App\Events\NewPredictionMade;
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
     * @param  array  $symbols  代币符号数组
     * @param  int  $timestamp  时间戳
     * @param  array  $history  历史数据
     * @param  int  $gameRoundId  游戏回合ID
     * @return array 预测结果
     */
    public function predict(array $symbols, int $timestamp, array $history, int $gameRoundId): array
    {
        try {
            // 获取市场数据快照
            $snapshots = $this->dataProvider->fetchSnapshots($symbols, $timestamp);

            if (empty($snapshots)) {
                Log::warning('无法获取市场数据快照', [
                    'symbols' => $symbols,
                    'timestamp' => $timestamp,
                ]);

                return [];
            }

            // 计算各特征分数
            $allFeatureScores = [];
            foreach ($this->featureProviders as $featureName => $provider) {
                if ($provider instanceof FeatureProviderInterface) {
                    $featureScores = $provider->extractFeatures($snapshots, $history);
                    $allFeatureScores[$featureName] = $featureScores;
                }
            }

            // 聚合分数
            $finalScores = $this->aggregator->aggregate($allFeatureScores);

            // 按分数排序
            arsort($finalScores);

            $predictionsToReturn = [];
            $rank = 1;

            foreach ($finalScores as $symbol => $scoreDetails) {
                // 创建预测结果记录
                $predictionRecord = PredictionResult::create([
                    'game_round_id' => $gameRoundId,
                    'token' => $symbol,
                    'predict_rank' => $rank,
                    'predict_score' => $scoreDetails['final_score'] ?? 0,
                    'elo_score' => $scoreDetails['elo_score'] ?? 0,
                    'momentum_score' => $scoreDetails['momentum_score'] ?? 0,
                    'volume_score' => $scoreDetails['volume_score'] ?? 0,
                    'norm_elo' => $scoreDetails['norm_elo'] ?? 0,
                    'norm_momentum' => $scoreDetails['norm_momentum'] ?? 0,
                    'norm_volume' => $scoreDetails['norm_volume'] ?? 0,
                    'used_weights' => $scoreDetails['weights'] ?? [],
                    'used_normalization' => $scoreDetails['normalization'] ?? [],
                    'strategy_tag' => $this->strategyTag,
                    'config_snapshot' => [
                        'strategy_tag' => $this->strategyTag,
                        'feature_scores' => $allFeatureScores,
                        'timestamp' => $timestamp,
                    ],
                ]);

                // 广播新预测结果事件
                try {
                    broadcast(new NewPredictionMade(
                        $predictionRecord,
                        (string) $gameRoundId,
                        'new_prediction',
                        'prediction_service'
                    ));

                    Log::info('新预测结果已广播', [
                        'round_id' => $gameRoundId,
                        'symbol' => $symbol,
                        'rank' => $rank,
                        'score' => $scoreDetails['final_score'] ?? 0,
                    ]);
                } catch (\Exception $broadcastError) {
                    Log::error('广播预测结果失败', [
                        'round_id' => $gameRoundId,
                        'symbol' => $symbol,
                        'error' => $broadcastError->getMessage(),
                    ]);
                }

                $predictionsToReturn[] = [
                    'symbol' => $symbol,
                    'rank' => $rank,
                    'score' => $scoreDetails['final_score'] ?? 0,
                    'details' => $scoreDetails,
                    'prediction_id' => $predictionRecord->id,
                ];

                $rank++;
            }

            Log::info('预测完成', [
                'round_id' => $gameRoundId,
                'symbols_count' => count($symbols),
                'predictions_count' => count($predictionsToReturn),
                'strategy' => $this->strategyTag,
            ]);

            return $predictionsToReturn;

        } catch (\Exception $e) {
            Log::error('预测执行失败', [
                'round_id' => $gameRoundId,
                'symbols' => $symbols,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [];
        }
    }
}
