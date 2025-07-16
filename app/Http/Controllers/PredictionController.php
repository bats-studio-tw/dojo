<?php

namespace App\Http\Controllers;

use App\Models\GameRound;
use App\Models\PredictionResult;
use App\Services\Prediction\BacktestService;
use App\Services\Prediction\PredictionServiceFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PredictionController extends Controller
{
    public function __construct(
        private PredictionServiceFactory $factory,
        private BacktestService $backtestService
    ) {
    }

    /**
     * 执行预测
     */
    public function executePrediction(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'strategy_tag' => 'required|string|max:50',
                'symbols' => 'required|array|min:1|max:20',
                'symbols.*' => 'required|string|max:10',
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

            // 创建预测服务实例
            $service = $this->factory->create($validated['strategy_tag']);

            // 获取当前游戏轮次
            $currentRound = GameRound::latest()->first();
            if (! $currentRound) {
                return response()->json([
                    'success' => false,
                    'message' => '未找到当前游戏轮次',
                    'code' => 404,
                ], 404);
            }

            // 执行预测
            $results = $service->predict(
                $validated['symbols'],
                $currentRound->id,
                time()
            );

            // 格式化返回数据
            $formattedResults = collect($results)->map(function ($result) {
                return [
                    'id' => $result->id,
                    'game_round_id' => $result->game_round_id,
                    'token' => $result->token,
                    'predict_rank' => $result->predict_rank,
                    'predict_score' => $result->predict_score,
                    'elo_score' => $result->elo_score,
                    'momentum_score' => $result->momentum_score,
                    'volume_score' => $result->volume_score,
                    'norm_elo' => $result->norm_elo,
                    'norm_momentum' => $result->norm_momentum,
                    'norm_volume' => $result->norm_volume,
                    'used_weights' => $result->used_weights,
                    'used_normalization' => $result->used_normalization,
                    'strategy_tag' => $result->strategy_tag,
                    'config_snapshot' => $result->config_snapshot,
                    'created_at' => $result->created_at->toISOString(),
                ];
            })->toArray();

            return response()->json([
                'success' => true,
                'data' => $formattedResults,
                'message' => '预测执行成功',
                'code' => 200,
            ]);

        } catch (\Exception $e) {
            Log::error('预测执行失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '预测执行失败: ' . $e->getMessage(),
                'code' => 500,
            ], 500);
        }
    }

    /**
     * 获取可用策略列表
     */
    public function listStrategies(): JsonResponse
    {
        try {
            // 使用Redis快取策略列表，提高回應速度
            $cacheKey = 'prediction:strategies:list';
            $formattedStrategies = Cache::remember($cacheKey, now()->addHours(1), function () {
                $strategies = config('prediction.strategies', []);

                return collect($strategies)->map(function ($config, $tag) {
                    return [
                        'tag' => $tag,
                        'name' => $this->getStrategyName($tag),
                        'description' => $this->getStrategyDescription($tag),
                        'weights' => $config['weights'] ?? [],
                        'normalization' => $config['feature_normalization'] ?? [],
                    ];
                })->values()->toArray();
            });

            return response()->json([
                'success' => true,
                'data' => $formattedStrategies,
                'message' => '策略列表获取成功',
                'code' => 200,
            ]);

        } catch (\Exception $e) {
            Log::error('获取策略列表失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '获取策略列表失败: ' . $e->getMessage(),
                'code' => 500,
            ], 500);
        }
    }

    /**
     * 执行回测
     */
    public function runBacktest(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'strategy_tag' => 'required|string|max:50',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after:start_date',
                'rounds_limit' => 'nullable|integer|min:10|max:1000',
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

            // 获取历史回合数据
            $rounds = $this->backtestService->getHistoricalRounds(
                $validated['rounds_limit'] ?? 100,
                $validated['start_date'] ?? null,
                $validated['end_date'] ?? null
            );

            if (empty($rounds)) {
                return response()->json([
                    'success' => false,
                    'message' => '未找到符合条件的回合数据',
                    'code' => 404,
                ], 404);
            }

            // 构建策略配置
            $strategyConfig = [
                'strategy_tag' => $validated['strategy_tag'],
            ];

            // 执行同步回测
            $result = $this->backtestService->runBacktest(
                $rounds,
                $strategyConfig,
                auth()->id()
            );

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => '回测执行成功',
                'code' => 200,
            ]);

        } catch (\Exception $e) {
            Log::error('回测执行失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '回测执行失败: ' . $e->getMessage(),
                'code' => 500,
            ], 500);
        }
    }

    /**
     * 非同步回測（分派到隊列）
     */
    public function asyncBacktest(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'strategy_tag' => 'required|string|max:50',
            'rounds' => 'required|array|min:10',
            'rounds.*.id' => 'required|integer',
            'rounds.*.symbols' => 'required|array',
            'rounds.*.timestamp' => 'required|integer',
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
        $strategyConfig = [
            'strategy_tag' => $validated['strategy_tag'],
        ];
        $batchId = $this->backtestService->startBacktest($validated['rounds'], $strategyConfig, auth()->id());

        return response()->json([
            'success' => true,
            'batch_id' => $batchId,
            'message' => '回测任务已提交',
            'code' => 202,
        ], 202);
    }

    /**
     * Grid Search 非同步回測
     */
    public function gridSearchBacktest(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'param_matrix' => 'required|array',
            'param_matrix.weights' => 'required|array|min:1',
            'param_matrix.normalization' => 'required|array|min:1',
            'rounds' => 'required|array|min:10',
            'rounds.*.id' => 'required|integer',
            'rounds.*.symbols' => 'required|array',
            'rounds.*.timestamp' => 'required|integer',
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
        $batchId = $this->backtestService->startGridSearch($validated['rounds'], $validated['param_matrix'], auth()->id());

        return response()->json([
            'success' => true,
            'batch_id' => $batchId,
            'message' => 'Grid Search 任务已提交',
            'code' => 202,
        ], 202);
    }

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

            $query = PredictionResult::query()
                ->with(['gameRound.roundResults'])
                ->orderBy('created_at', 'desc');

            // 应用筛选条件
            if (! empty($validated['strategy_tag'])) {
                $query->where('strategy_tag', $validated['strategy_tag']);
            }

            if (! empty($validated['start_date'])) {
                $query->where('created_at', '>=', $validated['start_date']);
            }

            if (! empty($validated['end_date'])) {
                $query->where('created_at', '<=', $validated['end_date']);
            }

            $limit = $validated['limit'] ?? 100;
            $results = $query->limit($limit)->get();

            // 按轮次分组数据
            $groupedByRound = $results->groupBy('game_round_id');

            $formattedRounds = [];
            foreach ($groupedByRound as $roundId => $predictions) {
                $gameRound = $predictions->first()->gameRound;

                // 格式化预测数据
                $formattedPredictions = $predictions->map(function ($prediction) {
                    return [
                        'symbol' => $prediction->token,
                        'predicted_rank' => $prediction->predict_rank,
                        'prediction_score' => $prediction->predict_score,
                        'predicted_at' => $prediction->created_at->toISOString(),
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
                'message' => '获取预测历史失败: ' . $e->getMessage(),
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
     * 获取策略性能统计
     */
    public function getStrategyPerformance(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'strategy_tag' => 'required|string|max:50',
                'days' => 'nullable|integer|min:1|max:365',
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

            $query = PredictionResult::where('strategy_tag', $validated['strategy_tag']);

            if (! empty($validated['days'])) {
                $query->where('created_at', '>=', now()->subDays($validated['days']));
            }

            $results = $query->get();

            if ($results->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => '未找到该策略的预测数据',
                    'code' => 404,
                ], 404);
            }

            // 计算性能指标
            $totalPredictions = $results->count();
            $avgScore = $results->avg('predict_score');
            $bestPerformance = $results->max('predict_score');
            $worstPerformance = $results->min('predict_score');

            // 计算准确率（这里需要根据实际业务逻辑调整）
            $accuracyRate = 0.65; // 示例值，实际应该根据历史结果计算

            $performance = [
                'total_predictions' => $totalPredictions,
                'accuracy_rate' => $accuracyRate,
                'avg_score' => $avgScore,
                'best_performance' => $bestPerformance,
                'worst_performance' => $worstPerformance,
            ];

            return response()->json([
                'success' => true,
                'data' => $performance,
                'message' => '性能统计获取成功',
                'code' => 200,
            ]);

        } catch (\Exception $e) {
            Log::error('获取策略性能失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '获取策略性能失败: ' . $e->getMessage(),
                'code' => 500,
            ], 500);
        }
    }

    /**
     * 获取策略名称
     */
    private function getStrategyName(string $tag): string
    {
        $names = [
            'conservative' => '保守型策略',
            'aggressive' => '进攻型策略',
            'balanced' => '平衡型策略',
            'momentum' => '动量策略',
            'elo' => 'Elo评分策略',
        ];

        return $names[$tag] ?? ucfirst($tag);
    }

    /**
     * 获取策略描述
     */
    private function getStrategyDescription(string $tag): string
    {
        $descriptions = [
            'conservative' => '采用70% Elo评分和30%动量分数，适合稳健投资',
            'aggressive' => '采用30% Elo评分和70%动量分数，适合激进投资',
            'balanced' => '采用50% Elo评分和50%动量分数，平衡风险收益',
            'momentum' => '专注于动量指标，适合趋势跟踪',
            'elo' => '专注于Elo评分系统，适合长期投资',
        ];

        return $descriptions[$tag] ?? '自定义策略配置';
    }

    /**
     * 查詢回測任務進度與結果
     */
    public function getBacktestBatchStatus(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'batch_id' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '参数验证失败',
                'errors' => $validator->errors(),
                'code' => 400,
            ], 400);
        }
        $batchId = $request->input('batch_id');
        // 查詢 Laravel Job Batch 狀態
        $batch = \DB::table('job_batches')->where('id', $batchId)->first();
        if (! $batch) {
            return response()->json([
                'success' => false,
                'message' => '未找到對應的回測任務',
                'code' => 404,
            ], 404);
        }
        // 查詢所有該batch下的BacktestReport
        $reports = \App\Models\BacktestReport::where('batch_id', $batchId)->get();

        return response()->json([
            'success' => true,
            'batch' => [
                'id' => $batch->id,
                'name' => $batch->name,
                'total_jobs' => $batch->total_jobs,
                'pending_jobs' => $batch->pending_jobs,
                'failed_jobs' => $batch->failed_jobs,
                'processed_jobs' => $batch->processed_jobs,
                'status' => $batch->finished_at ? 'finished' : ($batch->cancelled_at ? 'cancelled' : 'processing'),
                'created_at' => $batch->created_at,
                'finished_at' => $batch->finished_at,
            ],
            'reports' => $reports,
            'code' => 200,
        ]);
    }

    /**
     * 查詢單一回測報告詳情
     */
    public function getBacktestReportDetail(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '参数验证失败',
                'errors' => $validator->errors(),
                'code' => 400,
            ], 400);
        }
        $id = $request->input('id');
        $report = $this->backtestService->getBacktestReport($id);
        if (! $report) {
            return response()->json([
                'success' => false,
                'message' => '未找到對應的回測報告',
                'code' => 404,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $report,
            'code' => 200,
        ]);
    }

    /**
     * 获取历史回合数据
     */
    public function getHistoricalRounds(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'limit' => 'nullable|integer|min:1|max:1000',
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

            $rounds = $this->backtestService->getHistoricalRounds(
                $validated['limit'] ?? 100,
                $validated['start_date'] ?? null,
                $validated['end_date'] ?? null
            );

            return response()->json([
                'success' => true,
                'data' => $rounds,
                'message' => '历史回合数据获取成功',
                'code' => 200,
            ]);

        } catch (\Exception $e) {
            Log::error('获取历史回合数据失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '获取历史回合数据失败: ' . $e->getMessage(),
                'code' => 500,
            ], 500);
        }
    }

    /**
     * 手动触发预测计算（测试用）
     */
    public function triggerPredictionCalculation(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'round_id' => 'required|string',
                'symbols' => 'required|array|min:1',
                'symbols.*' => 'required|string|max:10',
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
            $roundId = $validated['round_id'];
            $symbols = $validated['symbols'];

            // 手动触发预测计算
            $predictionService = app(\App\Services\GamePredictionService::class);
            $success = $predictionService->generateAndCachePrediction($symbols, $roundId);

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => '预测计算已触发',
                    'data' => [
                        'round_id' => $roundId,
                        'symbols' => $symbols,
                        'status' => 'completed',
                    ],
                    'code' => 200,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => '预测计算失败',
                    'code' => 500,
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('手动触发预测计算失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '手动触发预测计算失败: ' . $e->getMessage(),
                'code' => 500,
            ], 500);
        }
    }

    /**
     * 测试 WebSocket 广播功能
     */
    public function testWebSocketBroadcast(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'round_id' => 'required|string',
                'symbols' => 'required|array|min:1',
                'symbols.*' => 'required|string|max:10',
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
            $roundId = $validated['round_id'];
            $symbols = $validated['symbols'];

            // 创建测试预测结果
            $testPredictions = [];
            foreach ($symbols as $index => $symbol) {
                $predictionResult = PredictionResult::create([
                    'game_round_id' => 999, // 测试轮次ID
                    'token' => $symbol,
                    'predict_rank' => $index + 1,
                    'predict_score' => rand(50, 100) / 100, // 随机分数
                    'elo_score' => rand(50, 100) / 100,
                    'momentum_score' => rand(50, 100) / 100,
                    'volume_score' => rand(50, 100) / 100,
                    'norm_elo' => rand(50, 100) / 100,
                    'norm_momentum' => rand(50, 100) / 100,
                    'norm_volume' => rand(50, 100) / 100,
                    'used_weights' => ['elo' => 0.6, 'momentum' => 0.3, 'volume' => 0.1],
                    'used_normalization' => ['elo' => 'z-score', 'momentum' => 'min-max', 'volume' => 'z-score'],
                    'strategy_tag' => 'test_strategy',
                    'config_snapshot' => [
                        'strategy_tag' => 'test_strategy',
                        'test_mode' => true,
                        'timestamp' => time(),
                    ],
                ]);

                // 广播事件
                broadcast(new \App\Events\NewPredictionMade(
                    $predictionResult,
                    $roundId,
                    'test_prediction',
                    'test_controller'
                ));

                $testPredictions[] = $predictionResult;
            }

            Log::info('WebSocket 测试广播完成', [
                'round_id' => $roundId,
                'predictions_count' => count($testPredictions),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'WebSocket 测试广播完成',
                'data' => [
                    'round_id' => $roundId,
                    'predictions_count' => count($testPredictions),
                    'predictions' => $testPredictions,
                ],
                'code' => 200,
            ]);

        } catch (\Exception $e) {
            Log::error('WebSocket 测试广播失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'WebSocket 测试广播失败: ' . $e->getMessage(),
                'code' => 500,
            ], 500);
        }
    }

    /**
     * 获取当前分析数据
     */
    public function getCurrentAnalysis(): JsonResponse
    {
        try {
                        // 优先从缓存获取预测数据
            $cachedPrediction = Cache::get('game:current_prediction');

            // 添加调试日志
            Log::info('API getCurrentAnalysis 缓存检查', [
                'has_cached_prediction' => !empty($cachedPrediction),
                'cached_prediction_type' => gettype($cachedPrediction),
                'cached_keys' => $cachedPrediction ? array_keys($cachedPrediction) : [],
                'has_analysis_data' => $cachedPrediction && isset($cachedPrediction['analysis_data']),
                'analysis_data_count' => $cachedPrediction && isset($cachedPrediction['analysis_data']) ? count($cachedPrediction['analysis_data']) : 0,
            ]);

            if ($cachedPrediction && is_array($cachedPrediction) && !empty($cachedPrediction['analysis_data'])) {
                // 使用缓存数据
                $analysisData = $cachedPrediction['analysis_data'];

                // 格式化数据以匹配前端期望的格式
                $formattedData = [];
                foreach ($analysisData as $tokenData) {
                    $formattedData[] = [
                        'symbol' => $tokenData['symbol'] ?? '',
                        'predicted_rank' => $tokenData['predicted_rank'] ?? 999,
                        'prediction_score' => $tokenData['predicted_final_value'] ?? $tokenData['absolute_score'] ?? 0,
                        'elo_score' => $tokenData['absolute_score'] ?? 0,
                        'momentum_score' => $tokenData['market_momentum_score'] ?? 0,
                        'volume_score' => $tokenData['final_prediction_score'] ?? 0,
                        'norm_elo' => $tokenData['absolute_score'] ?? 0,
                        'norm_momentum' => $tokenData['market_momentum_score'] ?? 0,
                        'norm_volume' => $tokenData['final_prediction_score'] ?? 0,
                        'strategy_tag' => 'current_analysis',
                        'created_at' => $cachedPrediction['generated_at'] ?? now()->toISOString(),
                    ];
                }

                // 按预测分数排序
                usort($formattedData, function ($a, $b) {
                    return $b['prediction_score'] <=> $a['prediction_score'];
                });

                return response()->json([
                    'success' => true,
                    'data' => $formattedData,
                    'meta' => [
                        'round_id' => $cachedPrediction['round_id'] ?? 'unknown',
                        'status' => 'current',
                        'total_predictions' => count($formattedData),
                        'updated_at' => $cachedPrediction['generated_at'] ?? now()->toISOString(),
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

            if (!$latestPredictions) {
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
                'message' => '获取当前分析数据失败: ' . $e->getMessage(),
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

            if (!$latestHybridPredictions) {
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
                'message' => '获取混合预测数据失败: ' . $e->getMessage(),
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
                'message' => '获取动量预测统计失败: ' . $e->getMessage(),
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
                'limit' => 'nullable|integer|min:1|max:100',
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
            $limit = $validated['limit'] ?? 50;
            $offset = $validated['offset'] ?? 0;

            // 获取动量预测历史
            $history = \App\Models\HybridRoundPredict::with(['gameRound.roundResults'])
                ->orderBy('created_at', 'desc')
                ->offset($offset)
                ->limit($limit)
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
                'message' => '获取动量预测历史失败: ' . $e->getMessage(),
                'code' => 500,
            ], 500);
        }
    }

    /**
     * 获取Token市场数据
     */
    public function getTokenMarketData(): JsonResponse
    {
        try {
            // 获取最新的Token价格数据
            $marketData = \App\Models\TokenPrice::latest()
                ->limit(100)
                ->get()
                ->groupBy('symbol')
                ->map(function ($prices) {
                    return $prices->first();
                })
                ->values()
                ->toArray();

            return response()->json([
                'success' => true,
                'data' => $marketData,
                'meta' => [
                    'total_tokens' => count($marketData),
                    'updated_at' => now()->toISOString(),
                ],
                'message' => 'Token市场数据获取成功',
                'code' => 200,
            ]);

        } catch (\Exception $e) {
            Log::error('获取Token市场数据失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '获取Token市场数据失败: ' . $e->getMessage(),
                'code' => 500,
            ], 500);
        }
    }

    /**
     * 获取整体准确率
     */
    public function getOverallAccuracy(): JsonResponse
    {
        try {
            // 计算整体准确率统计
            $accuracyStats = PredictionResult::selectRaw('
                COUNT(*) as total_predictions,
                SUM(CASE WHEN predict_rank = 1 THEN 1 ELSE 0 END) as correct_predictions,
                AVG(predict_score) as avg_prediction_score
            ')->first();

            $accuracy = $accuracyStats->total_predictions > 0
                ? ($accuracyStats->correct_predictions / $accuracyStats->total_predictions) * 100
                : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'total_predictions' => $accuracyStats->total_predictions,
                    'correct_predictions' => $accuracyStats->correct_predictions,
                    'accuracy_percentage' => round($accuracy, 2),
                    'avg_prediction_score' => round($accuracyStats->avg_prediction_score, 4),
                ],
                'message' => '整体准确率获取成功',
                'code' => 200,
            ]);

        } catch (\Exception $e) {
            Log::error('获取整体准确率失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '获取整体准确率失败: ' . $e->getMessage(),
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
                'message' => '获取性能摘要失败: ' . $e->getMessage(),
                'code' => 500,
            ], 500);
        }
    }

    /**
     * 获取Token历史数据
     */
    public function getTokenHistory(string $tokenSymbol): JsonResponse
    {
        try {
            $validator = Validator::make(['tokenSymbol' => $tokenSymbol], [
                'tokenSymbol' => 'required|string|max:10',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '参数验证失败',
                    'errors' => $validator->errors(),
                    'code' => 400,
                ], 400);
            }

            // 获取Token历史预测数据
            $history = PredictionResult::where('token', $tokenSymbol)
                ->with('gameRound')
                ->orderBy('created_at', 'desc')
                ->limit(100)
                ->get()
                ->map(function ($prediction) {
                    return [
                        'round_id' => $prediction->game_round_id,
                        'predicted_rank' => $prediction->predict_rank,
                        'prediction_score' => $prediction->predict_score,
                        'strategy_tag' => $prediction->strategy_tag,
                        'created_at' => $prediction->created_at->toISOString(),
                    ];
                })
                ->toArray();

            return response()->json([
                'success' => true,
                'data' => [
                    'token_symbol' => $tokenSymbol,
                    'history' => $history,
                    'total_records' => count($history),
                ],
                'message' => 'Token历史数据获取成功',
                'code' => 200,
            ]);

        } catch (\Exception $e) {
            Log::error('获取Token历史数据失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'token_symbol' => $tokenSymbol,
            ]);

            return response()->json([
                'success' => false,
                'message' => '获取Token历史数据失败: ' . $e->getMessage(),
                'code' => 500,
            ], 500);
        }
    }

    /**
     * 获取用户投注表现
     */
    public function getUserBettingPerformance(): JsonResponse
    {
        try {
            // 获取用户投注表现数据
            $performance = \App\Models\AutoBettingRecord::selectRaw('
                COUNT(*) as total_bets,
                SUM(CASE WHEN profit_loss > 0 THEN 1 ELSE 0 END) as winning_bets,
                SUM(profit_loss) as total_profit_loss,
                AVG(profit_loss) as avg_profit_loss
            ')->first();

            $winRate = $performance->total_bets > 0
                ? ($performance->winning_bets / $performance->total_bets) * 100
                : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'total_bets' => $performance->total_bets,
                    'winning_bets' => $performance->winning_bets,
                    'win_rate' => round($winRate, 2),
                    'total_profit_loss' => round($performance->total_profit_loss, 2),
                    'avg_profit_loss' => round($performance->avg_profit_loss, 2),
                ],
                'message' => '用户投注表现获取成功',
                'code' => 200,
            ]);

        } catch (\Exception $e) {
            Log::error('获取用户投注表现失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '获取用户投注表现失败: ' . $e->getMessage(),
                'code' => 500,
            ], 500);
        }
    }
}
