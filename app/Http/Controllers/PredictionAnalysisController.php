<?php

namespace App\Http\Controllers;

use App\Services\PredictionAnalysisService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\AutoBettingRecord;
use App\Models\GameRound;
use App\Models\RoundResult;
use App\Models\RoundPredict;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PredictionAnalysisController extends Controller
{
    private PredictionAnalysisService $analysisService;

    public function __construct(PredictionAnalysisService $analysisService)
    {
        $this->analysisService = $analysisService;
    }

    /**
     * 获取整体预测准确度分析
     */
    public function getOverallAccuracy(): JsonResponse
    {
        $analysis = $this->analysisService->analyzeOverallAccuracy();

        return response()->json([
            'success' => !isset($analysis['error']),
            'data' => $analysis
        ]);
    }

    /**
     * 获取预测性能摘要
     */
    public function getPerformanceSummary(): JsonResponse
    {
        $summary = $this->analysisService->getPredictionPerformanceSummary();

        return response()->json([
            'success' => !isset($summary['error']),
            'data' => $summary
        ]);
    }

    /**
     * 获取特定代币的预测历史
     */
    public function getTokenHistory(Request $request, string $tokenSymbol): JsonResponse
    {
        $limit = $request->input('limit', 20);
        $limit = min(max($limit, 1), 100); // 限制在1-100之间

        $history = $this->analysisService->getTokenPredictionHistory(strtoupper($tokenSymbol), $limit);

        return response()->json([
            'success' => !isset($history['error']),
            'data' => $history
        ]);
    }

    /**
     * 获取用户的投注表现分析
     * 包括实际保本率、策略表现、盈亏分析等
     */
    public function getUserBettingPerformance(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'uid' => 'required|string',
                'days' => 'integer|min:1|max:365',
                'limit' => 'integer|min:1|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '参数验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            $uid = $request->input('uid');
            $days = $request->input('days', 30); // 默认30天
            $limit = $request->input('limit', 100); // 默认100条记录

            // 获取用户的下注记录（包含实际结果）
            $bettingAnalysis = $this->calculateBettingPerformance($uid, $days, $limit);

            // 获取预测准确度分析
            $predictionAnalysis = $this->calculatePredictionAccuracy($uid, $days);

            // 获取策略表现分析
            $strategyAnalysis = $this->calculateStrategyPerformance($uid, $days);

            // 获取详细的投注记录
            $detailedRecords = $this->getDetailedBettingRecords($uid, $limit);

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => [
                        'analysis_period_days' => $days,
                        'uid' => $uid,
                        'generated_at' => now()->toISOString(),
                    ],
                    'betting_performance' => $bettingAnalysis,
                    'prediction_accuracy' => $predictionAnalysis,
                    'strategy_analysis' => $strategyAnalysis,
                    'detailed_records' => $detailedRecords,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('获取用户投注表现分析失败', [
                'uid' => $uid ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => '获取投注表现分析失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 计算投注表现（实际保本率）
     */
    private function calculateBettingPerformance(string $uid, int $days, int $limit): array
    {
        // 获取指定天数内的下注记录
        $records = AutoBettingRecord::where('uid', $uid)
            ->where('created_at', '>=', now()->subDays($days))
            ->with(['gameRound.roundResults'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        if ($records->isEmpty()) {
            return [
                'total_bets' => 0,
                'message' => '没有找到下注记录'
            ];
        }

        $totalBets = $records->count();
        $totalAmount = $records->sum('bet_amount');
        $successfulBets = $records->where('success', true)->count();

        // 计算实际盈亏
        $actualProfitLoss = 0;
        $winningBets = 0;
        $losingBets = 0;
        $breakEvenBets = 0;
        $settledBets = 0;

        $rankDistribution = [1 => 0, 2 => 0, 3 => 0, 'other' => 0];
        $betsByRank = [];

        foreach ($records as $record) {
            if (!$record->success) continue; // 只分析成功下注的记录

            // 查找该下注对应的实际游戏结果
            $gameRound = GameRound::where('round_id', $record->round_id)->first();
            if (!$gameRound) continue;

            $actualResult = RoundResult::where('game_round_id', $gameRound->id)
                ->where('token_symbol', strtoupper($record->token_symbol))
                ->first();

            if (!$actualResult) continue;

            $settledBets++;
            $actualRank = $actualResult->rank;
            $betAmount = (float) $record->bet_amount;

            // 统计排名分布
            if ($actualRank <= 3) {
                $rankDistribution[$actualRank]++;
            } else {
                $rankDistribution['other']++;
            }

            // 计算实际收益（简化计算，实际可能需要根据游戏规则调整）
            $payout = $this->calculatePayout($actualRank, $betAmount);
            $profit = $payout - $betAmount;
            $actualProfitLoss += $profit;

            if ($profit > 0) {
                $winningBets++;
            } elseif ($profit < 0) {
                $losingBets++;
            } else {
                $breakEvenBets++;
            }

            // 按排名统计下注表现
            if (!isset($betsByRank[$actualRank])) {
                $betsByRank[$actualRank] = [
                    'count' => 0,
                    'total_amount' => 0,
                    'total_profit' => 0
                ];
            }
            $betsByRank[$actualRank]['count']++;
            $betsByRank[$actualRank]['total_amount'] += $betAmount;
            $betsByRank[$actualRank]['total_profit'] += $profit;
        }

        // 计算实际保本率和各种统计
        $actualROI = $totalAmount > 0 ? ($actualProfitLoss / $totalAmount) * 100 : 0;
        $winRate = $settledBets > 0 ? ($winningBets / $settledBets) * 100 : 0;
        $avgProfitPerBet = $settledBets > 0 ? $actualProfitLoss / $settledBets : 0;

        return [
            'total_bets' => $totalBets,
            'successful_bets' => $successfulBets,
            'settled_bets' => $settledBets, // 有实际结果的下注
            'total_amount_invested' => round($totalAmount, 2),
            'actual_profit_loss' => round($actualProfitLoss, 2),
            'actual_roi_percentage' => round($actualROI, 2), // 实际投资回报率
            'break_even_rate' => round($actualROI + 100, 2), // 保本率 = 100% + ROI
            'win_rate_percentage' => round($winRate, 2),
            'average_profit_per_bet' => round($avgProfitPerBet, 2),
            'betting_distribution' => [
                'winning_bets' => $winningBets,
                'losing_bets' => $losingBets,
                'break_even_bets' => $breakEvenBets,
            ],
            'rank_distribution' => $rankDistribution,
            'performance_by_rank' => $betsByRank,
            'daily_average' => [
                'bets_per_day' => round($totalBets / max($days, 1), 2),
                'amount_per_day' => round($totalAmount / max($days, 1), 2),
                'profit_per_day' => round($actualProfitLoss / max($days, 1), 2),
            ]
        ];
    }

    /**
     * 计算预测准确度
     */
    private function calculatePredictionAccuracy(string $uid, int $days): array
    {
        // 获取用户下注对应的预测准确度
        $records = AutoBettingRecord::where('uid', $uid)
            ->where('created_at', '>=', now()->subDays($days))
            ->where('success', true)
            ->get();

        if ($records->isEmpty()) {
            return ['message' => '没有找到预测数据'];
        }

        $totalPredictions = 0;
        $exactMatches = 0;
        $closeMatches = 0;
        $rankDifferenceSum = 0;

        foreach ($records as $record) {
            $gameRound = GameRound::where('round_id', $record->round_id)->first();
            if (!$gameRound) continue;

            // 获取AI预测
            $prediction = RoundPredict::where('game_round_id', $gameRound->id)
                ->where('token_symbol', strtoupper($record->token_symbol))
                ->first();

            // 获取实际结果
            $actualResult = RoundResult::where('game_round_id', $gameRound->id)
                ->where('token_symbol', strtoupper($record->token_symbol))
                ->first();

            if (!$prediction || !$actualResult) continue;

            $totalPredictions++;
            $rankDifference = abs($prediction->predicted_rank - $actualResult->rank);
            $rankDifferenceSum += $rankDifference;

            if ($rankDifference === 0) {
                $exactMatches++;
            }
            if ($rankDifference <= 1) {
                $closeMatches++;
            }
        }

        return [
            'total_predictions_analyzed' => $totalPredictions,
            'exact_matches' => $exactMatches,
            'close_matches' => $closeMatches,
            'exact_accuracy_percentage' => $totalPredictions > 0 ? round(($exactMatches / $totalPredictions) * 100, 2) : 0,
            'close_accuracy_percentage' => $totalPredictions > 0 ? round(($closeMatches / $totalPredictions) * 100, 2) : 0,
            'average_rank_difference' => $totalPredictions > 0 ? round($rankDifferenceSum / $totalPredictions, 2) : 0,
        ];
    }

    /**
     * 计算策略表现分析
     */
    private function calculateStrategyPerformance(string $uid, int $days): array
    {
        // 从prediction_data中提取策略信息进行分析
        $records = AutoBettingRecord::where('uid', $uid)
            ->where('created_at', '>=', now()->subDays($days))
            ->where('success', true)
            ->get();

        $strategyStats = [];

        foreach ($records as $record) {
            $predictionData = $record->prediction_data ?? [];
            $strategy = $predictionData['strategy'] ?? 'unknown';

            if (!isset($strategyStats[$strategy])) {
                $strategyStats[$strategy] = [
                    'strategy_name' => $strategy,
                    'bet_count' => 0,
                    'total_amount' => 0,
                    'total_profit' => 0,
                    'winning_bets' => 0,
                ];
            }

            $strategyStats[$strategy]['bet_count']++;
            $strategyStats[$strategy]['total_amount'] += (float) $record->bet_amount;

            // 计算该笔下注的实际收益
            $gameRound = GameRound::where('round_id', $record->round_id)->first();
            if ($gameRound) {
                $actualResult = RoundResult::where('game_round_id', $gameRound->id)
                    ->where('token_symbol', strtoupper($record->token_symbol))
                    ->first();

                if ($actualResult) {
                    $payout = $this->calculatePayout($actualResult->rank, (float) $record->bet_amount);
                    $profit = $payout - (float) $record->bet_amount;
                    $strategyStats[$strategy]['total_profit'] += $profit;

                    if ($profit > 0) {
                        $strategyStats[$strategy]['winning_bets']++;
                    }
                }
            }
        }

        // 计算每个策略的统计数据
        foreach ($strategyStats as &$stats) {
            $stats['win_rate_percentage'] = $stats['bet_count'] > 0 ?
                round(($stats['winning_bets'] / $stats['bet_count']) * 100, 2) : 0;
            $stats['roi_percentage'] = $stats['total_amount'] > 0 ?
                round(($stats['total_profit'] / $stats['total_amount']) * 100, 2) : 0;
            $stats['average_profit_per_bet'] = $stats['bet_count'] > 0 ?
                round($stats['total_profit'] / $stats['bet_count'], 2) : 0;
        }

        return array_values($strategyStats);
    }

    /**
     * 获取详细的投注记录
     */
    private function getDetailedBettingRecords(string $uid, int $limit): array
    {
        $records = AutoBettingRecord::where('uid', $uid)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        $detailedRecords = [];

        foreach ($records as $record) {
            $gameRound = GameRound::where('round_id', $record->round_id)->first();
            $actualResult = null;
            $prediction = null;
            $actualRank = null;
            $predictedRank = null;
            $actualPayout = 0;
            $actualProfit = 0;

            if ($gameRound) {
                $actualResult = RoundResult::where('game_round_id', $gameRound->id)
                    ->where('token_symbol', strtoupper($record->token_symbol))
                    ->first();

                $prediction = RoundPredict::where('game_round_id', $gameRound->id)
                    ->where('token_symbol', strtoupper($record->token_symbol))
                    ->first();

                if ($actualResult) {
                    $actualRank = $actualResult->rank;
                    $actualPayout = $this->calculatePayout($actualRank, (float) $record->bet_amount);
                    $actualProfit = $actualPayout - (float) $record->bet_amount;
                }

                if ($prediction) {
                    $predictedRank = $prediction->predicted_rank;
                }
            }

            $detailedRecords[] = [
                'id' => $record->id,
                'round_id' => $record->round_id,
                'token_symbol' => $record->token_symbol,
                'bet_amount' => (float) $record->bet_amount,
                'success' => $record->success,
                'created_at' => $record->created_at->toISOString(),
                'predicted_rank' => $predictedRank,
                'actual_rank' => $actualRank,
                'rank_accuracy' => ($predictedRank && $actualRank) ?
                    abs($predictedRank - $actualRank) : null,
                'actual_payout' => round($actualPayout, 2),
                'actual_profit' => round($actualProfit, 2),
                'roi_percentage' => $record->bet_amount > 0 ?
                    round(($actualProfit / (float) $record->bet_amount) * 100, 2) : 0,
                'prediction_data' => $record->prediction_data,
            ];
        }

        return $detailedRecords;
    }

    /**
     * 计算收益（简化版本，实际可能需要根据游戏规则调整）
     */
    private function calculatePayout(int $rank, float $betAmount): float
    {
        // 简化的收益计算规则（实际游戏可能有不同的赔率表）
        switch ($rank) {
            case 1:
                return $betAmount * 5.0; // 第1名 5倍赔率
            case 2:
                return $betAmount * 3.0; // 第2名 3倍赔率
            case 3:
                return $betAmount * 2.0; // 第3名 2倍赔率
            case 4:
                return $betAmount * 1.5; // 第4名 1.5倍赔率
            case 5:
                return $betAmount * 1.2; // 第5名 1.2倍赔率
            default:
                return 0; // 其他排名无收益
        }
    }

    /**
     * 获取用户投注的代币表现统计
     */
    public function getTokenPerformanceStats(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'uid' => 'required|string',
                'days' => 'integer|min:1|max:365',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '参数验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            $uid = $request->input('uid');
            $days = $request->input('days', 30);

            // 按代币统计表现
            $tokenStats = DB::table('auto_betting_records as abr')
                ->leftJoin('game_rounds as gr', 'abr.round_id', '=', 'gr.round_id')
                ->leftJoin('round_results as rr', function($join) {
                    $join->on('gr.id', '=', 'rr.game_round_id')
                         ->on(DB::raw('UPPER(abr.token_symbol)'), '=', DB::raw('UPPER(rr.token_symbol)'));
                })
                ->where('abr.uid', $uid)
                ->where('abr.success', true)
                ->where('abr.created_at', '>=', now()->subDays($days))
                ->select([
                    'abr.token_symbol',
                    DB::raw('COUNT(*) as bet_count'),
                    DB::raw('SUM(abr.bet_amount) as total_invested'),
                    DB::raw('AVG(rr.rank) as avg_actual_rank'),
                    DB::raw('COUNT(CASE WHEN rr.rank = 1 THEN 1 END) as first_place_count'),
                    DB::raw('COUNT(CASE WHEN rr.rank <= 3 THEN 1 END) as top3_count'),
                ])
                ->groupBy('abr.token_symbol')
                ->orderBy('bet_count', 'desc')
                ->get();

            // 计算每个代币的详细统计
            $detailedStats = [];
            foreach ($tokenStats as $stat) {
                $totalProfit = 0;

                // 重新计算实际收益
                $records = AutoBettingRecord::where('uid', $uid)
                    ->where('token_symbol', $stat->token_symbol)
                    ->where('success', true)
                    ->where('created_at', '>=', now()->subDays($days))
                    ->get();

                foreach ($records as $record) {
                    $gameRound = GameRound::where('round_id', $record->round_id)->first();
                    if ($gameRound) {
                        $actualResult = RoundResult::where('game_round_id', $gameRound->id)
                            ->where('token_symbol', strtoupper($record->token_symbol))
                            ->first();

                        if ($actualResult) {
                            $payout = $this->calculatePayout($actualResult->rank, (float) $record->bet_amount);
                            $totalProfit += ($payout - (float) $record->bet_amount);
                        }
                    }
                }

                $detailedStats[] = [
                    'token_symbol' => $stat->token_symbol,
                    'bet_count' => $stat->bet_count,
                    'total_invested' => round((float) $stat->total_invested, 2),
                    'total_profit' => round($totalProfit, 2),
                    'roi_percentage' => $stat->total_invested > 0 ?
                        round(($totalProfit / (float) $stat->total_invested) * 100, 2) : 0,
                    'avg_actual_rank' => round((float) $stat->avg_actual_rank, 2),
                    'first_place_count' => $stat->first_place_count,
                    'top3_count' => $stat->top3_count,
                    'top3_rate_percentage' => $stat->bet_count > 0 ?
                        round(($stat->top3_count / $stat->bet_count) * 100, 2) : 0,
                    'win_rate_percentage' => $stat->bet_count > 0 ?
                        round(($stat->first_place_count / $stat->bet_count) * 100, 2) : 0,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'analysis_period_days' => $days,
                    'total_tokens' => count($detailedStats),
                    'token_performance' => $detailedStats,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('获取代币表现统计失败', [
                'uid' => $uid ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => '获取代币表现统计失败: ' . $e->getMessage()
            ], 500);
        }
    }
}
