<?php

namespace App\Http\Controllers;

use App\Models\PredictionResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PredictionController extends Controller
{
    public function __construct() {}

    /**
     * 获取预测历史
     */
    public function getPredictionHistory(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'strategy_tag' => 'nullable|string|max:50',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after:start_date',
                'limit' => 'nullable|integer|min:1|max:1000',
                'offset' => 'nullable|integer|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '参数验证失败',
                    'errors' => $validator->errors(),
                    'code' => 400,
                ], 400);
            }

            $validated = $validator->validated();
            $limit = $validated['limit'] ?? 300;
            $offset = $validated['offset'] ?? 0;

            // 先查最新 N 局的 game_round_id
            $roundQuery = \App\Models\GameRound::query()
                ->whereHas('roundPredicts');
            if (! empty($validated['start_date'])) {
                $roundQuery->where('created_at', '>=', $validated['start_date']);
            }
            if (! empty($validated['end_date'])) {
                $roundQuery->where('created_at', '<=', $validated['end_date']);
            }
            $roundIds = $roundQuery
                ->orderBy('created_at', 'desc')
                ->offset($offset)
                ->limit($limit)
                ->pluck('id');

            // 查这些局的预测
            $query = \App\Models\RoundPredict::query()
                ->with(['gameRound.roundResults'])
                ->whereIn('game_round_id', $roundIds);

            // 应用筛选条件
            if (! empty($validated['strategy_tag'])) {
                $query->where('strategy_tag', $validated['strategy_tag']);
            }

            $results = $query->get();

            // 按轮次分组数据
            $groupedByRound = $results->groupBy('game_round_id');
            $formattedRounds = [];
            foreach ($groupedByRound as $roundId => $predictions) {
                $gameRound = $predictions->first()->gameRound;

                // 格式化预测数据
                $formattedPredictions = $predictions->map(function ($prediction) {
                    return [
                        'symbol' => $prediction->token_symbol,
                        'predicted_rank' => $prediction->predicted_rank,
                        'prediction_score' => $prediction->prediction_score,
                        'predicted_at' => $prediction->predicted_at?->toISOString(),
                    ];
                })->toArray();

                // 格式化结果数据
                $formattedResults = [];
                if ($gameRound && $gameRound->roundResults) {
                    $formattedResults = $gameRound->roundResults->map(function ($result) {
                        return [
                            'symbol' => $result->token_symbol,
                            'actual_rank' => $result->rank,
                            'value' => $result->value ?? '',
                        ];
                    })->toArray();
                }

                // 计算准确率
                $accuracy = $this->calculateAccuracy($formattedPredictions, $formattedResults);

                $formattedRounds[] = [
                    'id' => $predictions->first()->id,
                    'round_id' => $roundId,
                    'settled_at' => $gameRound ? $gameRound->settled_at?->toISOString() : null,
                    'predictions' => $formattedPredictions,
                    'results' => $formattedResults,
                    'accuracy' => $accuracy,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $formattedRounds,
                'meta' => [
                    'total_rounds' => count($formattedRounds),
                    'limit' => $limit,
                    'offset' => $offset,
                ],
                'message' => '历史数据获取成功',
                'code' => 200,
            ]);

        } catch (\Exception $e) {
            Log::error('获取预测历史失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '获取预测历史失败: '.$e->getMessage(),
                'code' => 500,
            ], 500);
        }
    }

    /**
     * 计算预测准确率
     */
    private function calculateAccuracy(array $predictions, array $results): array
    {
        $totalPredictions = count($predictions);
        $exactMatches = 0;
        $closeMatches = 0;
        $totalRankDifference = 0;
        $details = [];

        foreach ($predictions as $prediction) {
            $actualResult = collect($results)->firstWhere('symbol', $prediction['symbol']);

            if ($actualResult) {
                $rankDifference = abs($prediction['predicted_rank'] - $actualResult['actual_rank']);
                $totalRankDifference += $rankDifference;

                $isExactMatch = $prediction['predicted_rank'] === $actualResult['actual_rank'];
                $isCloseMatch = $rankDifference <= 1;

                if ($isExactMatch) {
                    $exactMatches++;
                }
                if ($isCloseMatch) {
                    $closeMatches++;
                }

                $details[] = [
                    'symbol' => $prediction['symbol'],
                    'predicted_rank' => $prediction['predicted_rank'],
                    'actual_rank' => $actualResult['actual_rank'],
                    'rank_difference' => $rankDifference,
                    'is_exact_match' => $isExactMatch,
                    'is_close_match' => $isCloseMatch,
                ];
            }
        }

        return [
            'total_predictions' => $totalPredictions,
            'exact_matches' => $exactMatches,
            'close_matches' => $closeMatches,
            'exact_accuracy' => $totalPredictions > 0 ? ($exactMatches / $totalPredictions) * 100 : 0,
            'close_accuracy' => $totalPredictions > 0 ? ($closeMatches / $totalPredictions) * 100 : 0,
            'avg_rank_difference' => $totalPredictions > 0 ? $totalRankDifference / $totalPredictions : 0,
            'details' => $details,
        ];
    }

    /**
     * 获取当前分析数据
     */
    public function getCurrentAnalysis(): JsonResponse
    {
        try {
            // 优先从缓存获取预测数据
            $cachedPrediction = Cache::get('game:current_prediction');

            if ($cachedPrediction && is_array($cachedPrediction)) {
                // 确保返回与WebSocket推送一致的数据结构
                $analysisData = $cachedPrediction['analysis_data'] ?? [];
                $roundId = $cachedPrediction['round_id'] ?? 'unknown';
                $generatedAt = $cachedPrediction['generated_at'] ?? now()->toISOString();

                // 构造与WebSocket推送完全一致的数据结构
                return response()->json([
                    'success' => true,
                    'data' => $analysisData,
                    'meta' => [
                        'round_id' => $roundId,
                        'status' => 'current',
                        'current_tokens' => array_column($analysisData, 'symbol'),
                        'analysis_rounds_count' => count($analysisData),
                        'prediction_algorithm' => $cachedPrediction['algorithm'] ?? 'hybrid_edge_v1',
                        'algorithm_description' => $cachedPrediction['algorithm_description'] ?? 'Hybrid Edge v1.0',
                        'timestamp' => $generatedAt,
                        'generated_at' => $generatedAt,
                        'source' => 'api_current_analysis',
                    ],
                    'message' => '当前分析数据获取成功（来自缓存）',
                    'code' => 200,
                ]);
            }

            // 如果缓存中没有数据，尝试从数据库获取
            $latestPredictions = PredictionResult::with('gameRound')
                ->latest()
                ->limit(20)
                ->get()
                ->groupBy('game_round_id')
                ->first();

            if (! $latestPredictions) {
                return response()->json([
                    'success' => false,
                    'message' => '暂无预测数据',
                    'code' => 404,
                ], 404);
            }

            // 格式化数据
            $formattedData = $latestPredictions->map(function ($prediction) {
                return [
                    'symbol' => $prediction->token,
                    'predicted_rank' => $prediction->predict_rank,
                    'prediction_score' => $prediction->predict_score,
                    'elo_score' => $prediction->elo_score,
                    'momentum_score' => $prediction->momentum_score,
                    'volume_score' => $prediction->volume_score,
                    'norm_elo' => $prediction->norm_elo,
                    'norm_momentum' => $prediction->norm_momentum,
                    'norm_volume' => $prediction->norm_volume,
                    'strategy_tag' => $prediction->strategy_tag,
                    'created_at' => $prediction->created_at->toISOString(),
                ];
            })->values()->toArray();

            // 按预测分数排序
            usort($formattedData, function ($a, $b) {
                return $b['prediction_score'] <=> $a['prediction_score'];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedData,
                'meta' => [
                    'round_id' => $latestPredictions->first()->game_round_id,
                    'status' => 'current',
                    'total_predictions' => count($formattedData),
                    'updated_at' => now()->toISOString(),
                ],
                'message' => '当前分析数据获取成功（来自数据库）',
                'code' => 200,
            ]);

        } catch (\Exception $e) {
            Log::error('获取当前分析数据失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '获取当前分析数据失败: '.$e->getMessage(),
                'code' => 500,
            ], 500);
        }
    }

    /**
     * 获取混合预测数据
     */
    public function getHybridPredictions(): JsonResponse
    {
        try {
            // 获取最新的混合预测结果
            $latestHybridPredictions = \App\Models\HybridRoundPredict::with('gameRound')
                ->latest()
                ->limit(20)
                ->get()
                ->groupBy('game_round_id')
                ->first();

            if (! $latestHybridPredictions) {
                return response()->json([
                    'success' => false,
                    'message' => '暂无混合预测数据',
                    'code' => 404,
                ], 404);
            }

            // 格式化数据
            $formattedData = $latestHybridPredictions->map(function ($prediction) {
                return [
                    'symbol' => $prediction->token_symbol,
                    'predicted_rank' => $prediction->predicted_rank,
                    'mom_score' => $prediction->mom_score,
                    'elo_prob' => $prediction->elo_prob,
                    'final_score' => $prediction->final_score,
                    'confidence' => $prediction->confidence,
                    'created_at' => $prediction->created_at->toISOString(),
                ];
            })->values()->toArray();

            // 按最终分数排序
            usort($formattedData, function ($a, $b) {
                return $b['final_score'] <=> $a['final_score'];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedData,
                'meta' => [
                    'round_id' => $latestHybridPredictions->first()->game_round_id,
                    'status' => 'current',
                    'total_predictions' => count($formattedData),
                    'updated_at' => now()->toISOString(),
                ],
                'message' => '混合预测数据获取成功',
                'code' => 200,
            ]);

        } catch (\Exception $e) {
            Log::error('获取混合预测数据失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '获取混合预测数据失败: '.$e->getMessage(),
                'code' => 500,
            ], 500);
        }
    }

    /**
     * 获取动量预测统计
     */
    public function getMomentumPredictionStats(): JsonResponse
    {
        try {
            // 获取动量预测统计数据
            $stats = \App\Models\HybridRoundPredict::selectRaw('
                COUNT(*) as total_predictions,
                AVG(final_score) as avg_final_score,
                AVG(mom_score) as avg_momentum_score,
                AVG(elo_prob) as avg_elo_probability,
                AVG(confidence) as avg_confidence
            ')->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_predictions' => $stats->total_predictions,
                    'avg_final_score' => round($stats->avg_final_score, 4),
                    'avg_momentum_score' => round($stats->avg_momentum_score, 4),
                    'avg_elo_probability' => round($stats->avg_elo_probability, 4),
                    'avg_confidence' => round($stats->avg_confidence, 4),
                ],
                'message' => '动量预测统计获取成功',
                'code' => 200,
            ]);

        } catch (\Exception $e) {
            Log::error('获取动量预测统计失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '获取动量预测统计失败: '.$e->getMessage(),
                'code' => 500,
            ], 500);
        }
    }

    /**
     * 获取动量预测历史
     */
    public function getMomentumPredictionHistory(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'limit' => 'nullable|integer|min:1|max:500',
                'offset' => 'nullable|integer|min:0',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after:start_date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '参数验证失败',
                    'errors' => $validator->errors(),
                    'code' => 400,
                ], 400);
            }

            $validated = $validator->validated();
            $limit = $validated['limit'] ?? 300;
            $offset = $validated['offset'] ?? 0;

            // 先查最新 N 局的 game_round_id
            $roundQuery = \App\Models\GameRound::query()
                ->whereHas('hybridRoundPredicts');
            if (! empty($validated['start_date'])) {
                $roundQuery->where('created_at', '>=', $validated['start_date']);
            }
            if (! empty($validated['end_date'])) {
                $roundQuery->where('created_at', '<=', $validated['end_date']);
            }
            $roundIds = $roundQuery
                ->orderBy('created_at', 'desc')
                ->offset($offset)
                ->limit($limit)
                ->pluck('id');

            // 查这些局的预测
            $history = \App\Models\HybridRoundPredict::with(['gameRound.roundResults'])
                ->whereIn('game_round_id', $roundIds)
                ->get()
                ->groupBy('game_round_id');

            $formattedHistory = [];
            foreach ($history as $roundId => $predictions) {
                $gameRound = $predictions->first()->gameRound;

                // 格式化预测数据
                $formattedPredictions = $predictions->map(function ($prediction) {
                    return [
                        'symbol' => $prediction->token_symbol,
                        'predicted_rank' => $prediction->predicted_rank,
                        'momentum_score' => $prediction->mom_score,
                        'confidence' => $prediction->confidence,
                        'created_at' => $prediction->created_at->toISOString(),
                    ];
                })->values()->toArray();

                // 格式化结果数据
                $formattedResults = [];
                if ($gameRound && $gameRound->roundResults) {
                    $formattedResults = $gameRound->roundResults->map(function ($result) {
                        return [
                            'symbol' => $result->token_symbol,
                            'actual_rank' => $result->rank,
                            'value' => $result->value ?? '',
                        ];
                    })->toArray();
                }

                $formattedHistory[] = [
                    'round_id' => $roundId,
                    'settled_at' => $gameRound ? $gameRound->settled_at?->toISOString() : null,
                    'predictions' => $formattedPredictions,
                    'results' => $formattedResults,
                    'total_predictions' => $predictions->count(),
                    'created_at' => $predictions->first()->created_at->toISOString(),
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $formattedHistory,
                'meta' => [
                    'total_rounds' => count($formattedHistory),
                    'limit' => $limit,
                    'offset' => $offset,
                ],
                'message' => '动量预测历史获取成功',
                'code' => 200,
            ]);

        } catch (\Exception $e) {
            Log::error('获取动量预测历史失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '获取动量预测历史失败: '.$e->getMessage(),
                'code' => 500,
            ], 500);
        }
    }

    /**
     * 获取性能摘要
     */
    public function getPerformanceSummary(): JsonResponse
    {
        try {
            // 获取性能摘要数据
            $performance = PredictionResult::selectRaw('
                strategy_tag,
                COUNT(*) as total_predictions,
                AVG(predict_score) as avg_score,
                SUM(CASE WHEN predict_rank = 1 THEN 1 ELSE 0 END) as wins
            ')
                ->groupBy('strategy_tag')
                ->get()
                ->map(function ($item) {
                    $winRate = $item->total_predictions > 0
                        ? ($item->wins / $item->total_predictions) * 100
                        : 0;

                    return [
                        'strategy_tag' => $item->strategy_tag,
                        'total_predictions' => $item->total_predictions,
                        'wins' => $item->wins,
                        'win_rate' => round($winRate, 2),
                        'avg_score' => round($item->avg_score, 4),
                    ];
                })
                ->values()
                ->toArray();

            return response()->json([
                'success' => true,
                'data' => $performance,
                'message' => '性能摘要获取成功',
                'code' => 200,
            ]);

        } catch (\Exception $e) {
            Log::error('获取性能摘要失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '获取性能摘要失败: '.$e->getMessage(),
                'code' => 500,
            ], 500);
        }
    }

    /**
     * 获取用户投注表现
     */
    public function getUserBettingPerformance(Request $request): JsonResponse
    {
        try {
            // 验证请求参数
            $validator = Validator::make($request->all(), [
                'uid' => 'required|string',
                'filter_type' => 'nullable|in:days,rounds',
                'days' => 'nullable|integer|min:-1',
                'limit_rounds' => 'nullable|integer|min:1|max:10000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '参数验证失败',
                    'errors' => $validator->errors(),
                    'code' => 400,
                ], 400);
            }

            $uid = $request->input('uid');
            $filterType = $request->input('filter_type', 'days');
            $days = $request->input('days', -1);
            $limitRounds = $request->input('limit_rounds', 100);

            // 构建查询
            $query = \App\Models\AutoBettingRecord::where('uid', $uid);

            // 根据筛选方式应用过滤条件
            if ($filterType === 'days') {
                if ($days > 0) {
                    $query->where('created_at', '>=', now()->subDays($days));
                }
                // 如果 days = -1，则不添加时间过滤，显示全部历史
            } else {
                // 按局数筛选：获取最新的N局
                $query->orderBy('created_at', 'desc')->limit($limitRounds);
            }

            // 获取详细记录
            $records = $query->orderBy('created_at', 'desc')->get();

            // 计算统计数据
            $totalBets = $records->count();
            $settledBets = $records->where('success', true)->count();

            // 获取实际排名和预测排名（通过模型方法获取）
            $recordsWithRank = $records->map(function ($record) {
                // 获取预测排名
                $prediction = $record->getPrediction();
                $predictedRank = $prediction ? $prediction->predicted_rank : null;

                // 获取实际排名
                $actualResult = $record->getActualResult();
                $actualRank = $actualResult ? $actualResult->rank : null;

                return [
                    'id' => $record->id,
                    'created_at' => $record->created_at->toISOString(),
                    'round_id' => $record->round_id,
                    'token_symbol' => $record->token_symbol,
                    'predicted_rank' => $predictedRank,
                    'actual_rank' => $actualRank,
                    'success' => $record->success,
                    'bet_amount' => $record->bet_amount,
                    'profit_loss' => $record->profit_loss,
                ];
            });

            // 计算胜率（前三名算成功）
            $settledRecords = $recordsWithRank->where('actual_rank', '!==', null);
            $successfulBets = $settledRecords->where('actual_rank', '<=', 3)->count();
            $failedBets = $settledRecords->where('actual_rank', '>', 3)->count();
            $successRate = $settledRecords->count() > 0 ? ($successfulBets / $settledRecords->count()) * 100 : 0;

            // 计算投注分布
            $bettingDistribution = [
                'winning_bets' => $successfulBets,
                'losing_bets' => $failedBets,
                'break_even_bets' => 0, // 暂时设为0，后续可以根据需要调整
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'betting_performance' => [
                        'total_bets' => $totalBets,
                        'settled_bets' => $settledRecords->count(),
                        'win_rate_percentage' => round($successRate, 2),
                        'betting_distribution' => $bettingDistribution,
                    ],
                    'detailed_records' => $recordsWithRank->values(),
                ],
                'message' => '用户投注表现获取成功',
                'code' => 200,
            ]);

        } catch (\Exception $e) {
            Log::error('获取用户投注表现失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '获取用户投注表现失败: '.$e->getMessage(),
                'code' => 500,
            ], 500);
        }
    }

    /**
     * 获取公共预测分析数据（展示页面用）
     */
    public function getPublicAnalysis(): JsonResponse
    {
        try {
            // 优先从缓存获取预测数据
            $cachedPrediction = Cache::get('game:current_prediction');

            if ($cachedPrediction && is_array($cachedPrediction)) {
                $analysisData = $cachedPrediction['analysis_data'] ?? [];
                $roundId = $cachedPrediction['round_id'] ?? 'unknown';

                return response()->json([
                    'success' => true,
                    'data' => [
                        'analysis' => $analysisData,
                        'meta' => [
                            'strategy' => 'ai_prediction',
                            'timestamp' => now()->toISOString(),
                            'round_id' => $roundId,
                        ]
                    ],
                    'message' => '预测分析获取成功',
                    'code' => 200,
                ]);
            }

            // 如果没有缓存数据，返回空数据
            return response()->json([
                'success' => true,
                'data' => [
                    'analysis' => null,
                    'meta' => [
                        'strategy' => 'ai_prediction',
                        'timestamp' => now()->toISOString(),
                        'round_id' => 'unknown',
                    ]
                ],
                'message' => '暂无预测数据',
                'code' => 200,
            ]);

        } catch (\Exception $e) {
            Log::error('获取公共预测分析失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '获取预测分析失败: '.$e->getMessage(),
                'code' => 500,
            ], 500);
        }
    }

    /**
     * 获取公共混合预测分析数据（展示页面用）
     */
    public function getPublicHybridAnalysis(): JsonResponse
    {
        try {
            // 获取最新的混合预测数据
            $latestHybridPredictions = \App\Models\HybridRoundPredict::query()
                ->with(['gameRound'])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            if ($latestHybridPredictions->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'predictions' => [],
                        'meta' => [
                            'strategy' => 'hybrid_momentum',
                            'timestamp' => now()->toISOString(),
                            'total_predictions' => 0,
                        ]
                    ],
                    'message' => '暂无混合预测数据',
                    'code' => 200,
                ]);
            }

            // 格式化预测数据
            $formattedPredictions = $latestHybridPredictions->map(function ($prediction) {
                return [
                    'token' => $prediction->token_symbol,
                    'rank' => $prediction->predicted_rank,
                    'score' => $prediction->prediction_score,
                    'momentum_score' => $prediction->momentum_score,
                    'elo_score' => $prediction->elo_score,
                    'volume_score' => $prediction->volume_score,
                    'confidence' => $prediction->confidence ?? 0.8,
                    'timestamp' => $prediction->created_at->toISOString(),
                ];
            })->toArray();

            return response()->json([
                'success' => true,
                'data' => [
                    'predictions' => $formattedPredictions,
                    'meta' => [
                        'strategy' => 'hybrid_momentum',
                        'timestamp' => now()->toISOString(),
                        'total_predictions' => count($formattedPredictions),
                        'last_updated' => $latestHybridPredictions->first()->created_at->toISOString(),
                    ]
                ],
                'message' => '混合预测分析获取成功',
                'code' => 200,
            ]);

        } catch (\Exception $e) {
            Log::error('获取公共混合预测分析失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '获取混合预测分析失败: '.$e->getMessage(),
                'code' => 500,
            ], 500);
        }
    }
}
