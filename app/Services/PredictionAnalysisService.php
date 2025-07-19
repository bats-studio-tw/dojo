<?php

namespace App\Services;

use App\Models\GameRound;
use App\Models\RoundPredict;
use Illuminate\Support\Facades\Log;

class PredictionAnalysisService
{
    /**
     * 分析所有轮次的预测准确度
     */
    public function analyzeOverallAccuracy(): array
    {
        try {
            // 获取所有有预测和结果的轮次
            $rounds = GameRound::with(['roundPredicts', 'roundResults'])
                ->whereHas('roundPredicts')
                ->whereHas('roundResults')
                ->settled()
                ->get();

            if ($rounds->isEmpty()) {
                return [
                    'total_rounds' => 0,
                    'message' => '暂无可分析的预测数据',
                ];
            }

            $totalRounds = $rounds->count();
            $totalPredictions = 0;
            $exactMatches = 0;
            $closeMatches = 0; // 排名差距 <= 1
            $rankDifferenceSum = 0;

            $tokenAccuracy = [];
            $roundAccuracy = [];

            foreach ($rounds as $round) {
                $roundStats = $this->analyzeRoundAccuracy($round);
                $roundAccuracy[] = $roundStats;

                $totalPredictions += $roundStats['total_predictions'];
                $exactMatches += $roundStats['exact_matches'];
                $closeMatches += $roundStats['close_matches'];
                $rankDifferenceSum += $roundStats['total_rank_difference'];

                // 累计代币统计
                foreach ($roundStats['token_stats'] as $tokenStat) {
                    $symbol = $tokenStat['symbol'];
                    if (! isset($tokenAccuracy[$symbol])) {
                        $tokenAccuracy[$symbol] = [
                            'symbol' => $symbol,
                            'total_predictions' => 0,
                            'exact_matches' => 0,
                            'close_matches' => 0,
                            'rank_difference_sum' => 0,
                        ];
                    }

                    $tokenAccuracy[$symbol]['total_predictions']++;
                    $tokenAccuracy[$symbol]['exact_matches'] += $tokenStat['is_exact_match'] ? 1 : 0;
                    $tokenAccuracy[$symbol]['close_matches'] += $tokenStat['is_close_match'] ? 1 : 0;
                    $tokenAccuracy[$symbol]['rank_difference_sum'] += $tokenStat['rank_difference'];
                }
            }

            // 计算整体统计
            $overallStats = [
                'total_rounds' => $totalRounds,
                'total_predictions' => $totalPredictions,
                'exact_match_rate' => $totalPredictions > 0 ? round(($exactMatches / $totalPredictions) * 100, 2) : 0,
                'close_match_rate' => $totalPredictions > 0 ? round(($closeMatches / $totalPredictions) * 100, 2) : 0,
                'average_rank_difference' => $totalPredictions > 0 ? round($rankDifferenceSum / $totalPredictions, 2) : 0,
            ];

            // 计算代币准确度统计
            foreach ($tokenAccuracy as &$stat) {
                $total = $stat['total_predictions'];
                $stat['exact_match_rate'] = round(($stat['exact_matches'] / $total) * 100, 2);
                $stat['close_match_rate'] = round(($stat['close_matches'] / $total) * 100, 2);
                $stat['average_rank_difference'] = round($stat['rank_difference_sum'] / $total, 2);
            }

            // 按准确率排序
            uasort($tokenAccuracy, function ($a, $b) {
                return $b['exact_match_rate'] <=> $a['exact_match_rate'];
            });

            return [
                'overall_stats' => $overallStats,
                'token_accuracy' => array_values($tokenAccuracy),
                'recent_rounds' => array_slice(array_reverse($roundAccuracy), 0, 10), // 最近10轮
                'analysis_time' => now()->toISOString(),
            ];

        } catch (\Exception $e) {
            Log::error('分析预测准确度失败', ['error' => $e->getMessage()]);

            return [
                'error' => '分析失败：'.$e->getMessage(),
            ];
        }
    }

    /**
     * 分析单个轮次的预测准确度
     */
    public function analyzeRoundAccuracy(GameRound $round): array
    {
        $predictions = $round->roundPredicts;
        $results = $round->roundResults;

        if ($predictions->isEmpty() || $results->isEmpty()) {
            return [
                'round_id' => $round->round_id,
                'total_predictions' => 0,
                'message' => '该轮次缺少预测或结果数据',
            ];
        }

        // 创建结果查找表
        $resultMap = $results->keyBy('token_symbol');

        $tokenStats = [];
        $exactMatches = 0;
        $closeMatches = 0;
        $totalRankDifference = 0;

        foreach ($predictions as $prediction) {
            $symbol = $prediction->token_symbol;
            $actualResult = $resultMap->get($symbol);

            if (! $actualResult) {
                continue; // 跳过没有实际结果的预测
            }

            $accuracy = $prediction->getAccuracy($actualResult->rank);
            $tokenStats[] = array_merge($accuracy, ['symbol' => $symbol]);

            if ($accuracy['is_exact_match']) {
                $exactMatches++;
            }
            if ($accuracy['is_close_match']) {
                $closeMatches++;
            }
            $totalRankDifference += $accuracy['rank_difference'];
        }

        $totalPredictions = count($tokenStats);

        return [
            'round_id' => $round->round_id,
            'total_predictions' => $totalPredictions,
            'exact_matches' => $exactMatches,
            'close_matches' => $closeMatches,
            'exact_match_rate' => $totalPredictions > 0 ? round(($exactMatches / $totalPredictions) * 100, 2) : 0,
            'close_match_rate' => $totalPredictions > 0 ? round(($closeMatches / $totalPredictions) * 100, 2) : 0,
            'average_rank_difference' => $totalPredictions > 0 ? round($totalRankDifference / $totalPredictions, 2) : 0,
            'total_rank_difference' => $totalRankDifference,
            'token_stats' => $tokenStats,
            'predicted_at' => $predictions->first()?->predicted_at?->toISOString(),
            'settled_at' => $round->settled_at?->toISOString(),
        ];
    }

    /**
     * 获取特定代币的预测历史
     */
    public function getTokenPredictionHistory(string $tokenSymbol, int $limit = 20): array
    {
        try {
            $predictions = RoundPredict::with(['gameRound.roundResults'])
                ->byToken($tokenSymbol)
                ->orderBy('predicted_at', 'desc')
                ->limit($limit)
                ->get();

            $history = [];
            foreach ($predictions as $prediction) {
                $gameRound = $prediction->gameRound;
                $actualResult = $gameRound->roundResults()
                    ->where('token_symbol', $tokenSymbol)
                    ->first();

                $historyItem = [
                    'round_id' => $gameRound->round_id,
                    'predicted_rank' => $prediction->predicted_rank,
                    'prediction_score' => $prediction->prediction_score,
                    'predicted_at' => $prediction->predicted_at->toISOString(),
                ];

                if ($actualResult) {
                    $accuracy = $prediction->getAccuracy($actualResult->rank);
                    $historyItem = array_merge($historyItem, $accuracy);
                    $historyItem['settled_at'] = $gameRound->settled_at?->toISOString();
                } else {
                    $historyItem['actual_rank'] = null;
                    $historyItem['status'] = '未结算或无结果数据';
                }

                $history[] = $historyItem;
            }

            return [
                'token_symbol' => $tokenSymbol,
                'total_predictions' => count($history),
                'history' => $history,
            ];

        } catch (\Exception $e) {
            Log::error('获取代币预测历史失败', [
                'token_symbol' => $tokenSymbol,
                'error' => $e->getMessage(),
            ]);

            return [
                'error' => '获取预测历史失败：'.$e->getMessage(),
            ];
        }
    }

    /**
     * 获取预测性能摘要
     */
    public function getPredictionPerformanceSummary(): array
    {
        try {
            $recentAnalysis = $this->analyzeOverallAccuracy();

            if (isset($recentAnalysis['error'])) {
                return $recentAnalysis;
            }

            $overallStats = $recentAnalysis['overall_stats'];
            $tokenStats = $recentAnalysis['token_accuracy'];

            // 获取最佳和最差预测代币
            $bestToken = ! empty($tokenStats) ? $tokenStats[0] : null;
            $worstToken = ! empty($tokenStats) ? end($tokenStats) : null;

            return [
                'prediction_performance' => [
                    'overall_accuracy' => $overallStats['exact_match_rate'].'%',
                    'close_accuracy' => $overallStats['close_match_rate'].'%',
                    'average_error' => $overallStats['average_rank_difference'].' 个排名',
                    'total_analyzed' => $overallStats['total_rounds'].' 轮次',
                ],
                'best_predicted_token' => $bestToken ? [
                    'symbol' => $bestToken['symbol'],
                    'accuracy' => $bestToken['exact_match_rate'].'%',
                    'total_predictions' => $bestToken['total_predictions'],
                ] : null,
                'worst_predicted_token' => $worstToken ? [
                    'symbol' => $worstToken['symbol'],
                    'accuracy' => $worstToken['exact_match_rate'].'%',
                    'total_predictions' => $worstToken['total_predictions'],
                ] : null,
                'recent_performance' => array_slice($recentAnalysis['recent_rounds'], 0, 5),
                'generated_at' => now()->toISOString(),
            ];

        } catch (\Exception $e) {
            Log::error('获取预测性能摘要失败', ['error' => $e->getMessage()]);

            return [
                'error' => '获取性能摘要失败：'.$e->getMessage(),
            ];
        }
    }
}
