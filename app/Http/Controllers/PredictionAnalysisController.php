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
     * 获取用户的投注表现分析
     * 包括实际保本率、策略表现、盈亏分析等
     */
    public function getUserBettingPerformance(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'uid' => 'required|string',
                'days' => 'integer|min:-1|max:365', // 允许-1表示全部历史
                'limit' => 'integer|min:1|max:1000',
                'limit_rounds' => 'integer|min:1|max:10000', // 新增：按局数筛选
                'filter_type' => 'string|in:days,rounds', // 新增：筛选类型
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '参数验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            $uid = $request->input('uid');
            $filterType = $request->input('filter_type', 'days'); // 默认按天数筛选
            $days = $request->input('days');
            $limitRounds = $request->input('limit_rounds');
            $limit = (int) $request->input('limit', 1000); // 默认1000条记录，避免数据截断

            // 根据筛选类型验证参数
            if ($filterType === 'days') {
                $days = (int) ($days ?? 30); // 默认30天
                // 验证days参数：只允许-1或大于0的值
                if ($days !== -1 && $days <= 0) {
                    return response()->json([
                        'success' => false,
                        'message' => '参数验证失败',
                        'errors' => ['days' => ['days参数必须为-1（全部历史）或大于0的天数']]
                    ], 422);
                }
                $limitRounds = null;
            } else if ($filterType === 'rounds') {
                if (!$limitRounds || $limitRounds <= 0) {
                    return response()->json([
                        'success' => false,
                        'message' => '参数验证失败',
                        'errors' => ['limit_rounds' => ['按局数筛选时，limit_rounds参数必须大于0']]
                    ], 422);
                }
                $limitRounds = (int) $limitRounds;
                $days = null;
            }

            // 获取用户的下注记录（包含实际结果）
            $bettingAnalysis = $this->calculateBettingPerformance($uid, $days, $limit, $limitRounds);

            // 获取预测准确度分析
            $predictionAnalysis = $this->calculatePredictionAccuracy($uid, $days, $limitRounds);

            // 获取策略表现分析
            $strategyAnalysis = $this->calculateStrategyPerformance($uid, $days, $limitRounds);

            // 获取详细的投注记录
            $detailedRecords = $this->getDetailedBettingRecords($uid, $limit, $limitRounds);

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => [
                        'filter_type' => $filterType,
                        'analysis_period_days' => $days,
                        'limit_rounds' => $limitRounds,
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
    private function calculateBettingPerformance(string $uid, ?int $days, int $limit, ?int $limitRounds = null): array
    {
        // Eager Loading 预载入 gameRound.roundResults
        $query = AutoBettingRecord::where('uid', $uid)
            ->with(['gameRound.roundResults'])
            ->orderBy('created_at', 'desc');

        if ($limitRounds !== null) {
            $query->limit($limitRounds);
        } else if ($days !== null) {
            if ($days > 0) {
                $query->where('created_at', '>=', now()->subDays($days));
            }
            $query->limit($limit);
        } else {
            $query->limit($limit);
        }

        $records = $query->get();

        if ($records->isEmpty()) {
            return [
                'total_bets' => 0,
                'message' => '没有找到下注记录'
            ];
        }

        $totalBets = $records->count();
        $totalAmount = $records->sum('bet_amount');
        $successfulBets = $records->where('success', true)->count();

        $actualProfitLoss = 0;
        $winningBets = 0;
        $losingBets = 0;
        $breakEvenBets = 0;
        $settledBets = 0;
        $rankDistribution = [1 => 0, 2 => 0, 3 => 0, 'other' => 0];
        $betsByRank = [];

        foreach ($records as $record) {
            if (!$record->success) continue;
            $gameRound = $record->gameRound;
            if (!$gameRound) continue;
            $actualResult = $gameRound->roundResults
                ->where('token_symbol', strtoupper($record->token_symbol))
                ->first();
            if (!$actualResult) continue;
            $settledBets++;
            $actualRank = $actualResult->rank;
            $betAmount = (float) $record->bet_amount;
            if ($actualRank <= 3) {
                $rankDistribution[$actualRank]++;
            } else {
                $rankDistribution['other']++;
            }
            $payout = $this->calculatePayout($actualRank, $betAmount);
            $profit = $payout - $betAmount;
            $actualProfitLoss += $profit;
            if ($actualRank <= 3) {
                $winningBets++;
            } else {
                $losingBets++;
            }
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
        $actualROI = $totalAmount > 0 ? ($actualProfitLoss / $totalAmount) * 100 : 0;
        $winRate = $settledBets > 0 ? ($winningBets / $settledBets) * 100 : 0;
        $avgProfitPerBet = $settledBets > 0 ? $actualProfitLoss / $settledBets : 0;
        return [
            'total_bets' => $totalBets,
            'successful_bets' => $successfulBets,
            'settled_bets' => $settledBets,
            'total_amount_invested' => round($totalAmount, 2),
            'actual_profit_loss' => round($actualProfitLoss, 2),
            'actual_roi_percentage' => round($actualROI, 2),
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
                'bets_per_day' => $days && $days > 0 ? round($totalBets / $days, 2) : null,
                'amount_per_day' => $days && $days > 0 ? round($totalAmount / $days, 2) : null,
                'profit_per_day' => $days && $days > 0 ? round($actualProfitLoss / $days, 2) : null,
            ]
        ];
    }

    /**
     * 计算预测准确度
     */
    private function calculatePredictionAccuracy(string $uid, ?int $days, ?int $limitRounds = null): array
    {
        $query = AutoBettingRecord::where('uid', $uid)
            ->where('success', true)
            ->with(['gameRound.roundResults', 'gameRound.roundPredicts'])
            ->orderBy('created_at', 'desc');
        if ($limitRounds !== null) {
            $query->limit($limitRounds);
        } else if ($days !== null) {
            if ($days > 0) {
                $query->where('created_at', '>=', now()->subDays($days));
            }
        }
        $records = $query->get();
        if ($records->isEmpty()) {
            return ['message' => '没有找到预测数据'];
        }
        $totalPredictions = 0;
        $exactMatches = 0;
        $closeMatches = 0;
        $rankDifferenceSum = 0;
        foreach ($records as $record) {
            $gameRound = $record->gameRound;
            if (!$gameRound) continue;
            $prediction = $gameRound->roundPredicts
                ->where('token_symbol', strtoupper($record->token_symbol))
                ->first();
            $actualResult = $gameRound->roundResults
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
    private function calculateStrategyPerformance(string $uid, ?int $days, ?int $limitRounds = null): array
    {
        $query = AutoBettingRecord::where('uid', $uid)
            ->where('success', true)
            ->with(['gameRound.roundResults'])
            ->orderBy('created_at', 'desc');
        if ($limitRounds !== null) {
            $query->limit($limitRounds);
        } else if ($days !== null) {
            if ($days > 0) {
                $query->where('created_at', '>=', now()->subDays($days));
            }
        }
        $records = $query->get();
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
            $gameRound = $record->gameRound;
            if ($gameRound) {
                $actualResult = $gameRound->roundResults
                    ->where('token_symbol', strtoupper($record->token_symbol))
                    ->first();
                if ($actualResult) {
                    $payout = $this->calculatePayout($actualResult->rank, (float) $record->bet_amount);
                    $profit = $payout - (float) $record->bet_amount;
                    $strategyStats[$strategy]['total_profit'] += $profit;
                    if ($actualResult->rank <= 3) {
                        $strategyStats[$strategy]['winning_bets']++;
                    }
                }
            }
        }
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
    private function getDetailedBettingRecords(string $uid, int $limit, ?int $limitRounds = null): array
    {
        $query = AutoBettingRecord::where('uid', $uid)
            ->orderBy('created_at', 'desc');

        // 如果指定了按局数筛选，使用局数限制，否则使用默认limit
        if ($limitRounds !== null) {
            $query->limit($limitRounds);
        } else {
            $query->limit($limit);
        }

        $records = $query->get();

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


}
