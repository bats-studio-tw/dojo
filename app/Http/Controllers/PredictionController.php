<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\Prediction\PredictionServiceFactory;
use App\Services\Prediction\BacktestService;
use App\Models\PredictionResult;
use App\Models\GameRound;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PredictionController extends Controller
{
    public function __construct(
        private PredictionServiceFactory $factory,
        private BacktestService $backtestService
    ) {}

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
                    'code' => 400
                ], 400);
            }

            $validated = $validator->validated();

            // 创建预测服务实例
            $service = $this->factory->create($validated['strategy_tag']);

            // 获取当前游戏轮次
            $currentRound = GameRound::latest()->first();
            if (!$currentRound) {
                return response()->json([
                    'success' => false,
                    'message' => '未找到当前游戏轮次',
                    'code' => 404
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
                'code' => 200
            ]);

        } catch (\Exception $e) {
            Log::error('预测执行失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => '预测执行失败: ' . $e->getMessage(),
                'code' => 500
            ], 500);
        }
    }

    /**
     * 获取可用策略列表
     */
    public function listStrategies(): JsonResponse
    {
        try {
            $strategies = config('prediction.strategies', []);

            $formattedStrategies = collect($strategies)->map(function ($config, $tag) {
                return [
                    'tag' => $tag,
                    'name' => $this->getStrategyName($tag),
                    'description' => $this->getStrategyDescription($tag),
                    'weights' => $config['weights'] ?? [],
                    'normalization' => $config['feature_normalization'] ?? [],
                ];
            })->values()->toArray();

            return response()->json([
                'success' => true,
                'data' => $formattedStrategies,
                'message' => '策略列表获取成功',
                'code' => 200
            ]);

        } catch (\Exception $e) {
            Log::error('获取策略列表失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => '获取策略列表失败: ' . $e->getMessage(),
                'code' => 500
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
                'rounds' => 'nullable|integer|min:10|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '参数验证失败',
                    'errors' => $validator->errors(),
                    'code' => 400
                ], 400);
            }

            $validated = $validator->validated();

            // 执行回测
            $result = $this->backtestService->runBacktest(
                $validated['strategy_tag'],
                $validated['rounds'] ?? 100,
                $validated['start_date'] ?? null,
                $validated['end_date'] ?? null
            );

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => '回测执行成功',
                'code' => 200
            ]);

        } catch (\Exception $e) {
            Log::error('回测执行失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => '回测执行失败: ' . $e->getMessage(),
                'code' => 500
            ], 500);
        }
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
                    'code' => 400
                ], 400);
            }

            $validated = $validator->validated();

            $query = PredictionResult::query()
                ->with('gameRound')
                ->orderBy('created_at', 'desc');

            // 应用筛选条件
            if (!empty($validated['strategy_tag'])) {
                $query->where('strategy_tag', $validated['strategy_tag']);
            }

            if (!empty($validated['start_date'])) {
                $query->where('created_at', '>=', $validated['start_date']);
            }

            if (!empty($validated['end_date'])) {
                $query->where('created_at', '<=', $validated['end_date']);
            }

            $limit = $validated['limit'] ?? 100;
            $results = $query->limit($limit)->get();

            $formattedResults = $results->map(function ($result) {
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
                'message' => '历史数据获取成功',
                'code' => 200
            ]);

        } catch (\Exception $e) {
            Log::error('获取预测历史失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => '获取预测历史失败: ' . $e->getMessage(),
                'code' => 500
            ], 500);
        }
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
                    'code' => 400
                ], 400);
            }

            $validated = $validator->validated();

            $query = PredictionResult::where('strategy_tag', $validated['strategy_tag']);

            if (!empty($validated['days'])) {
                $query->where('created_at', '>=', now()->subDays($validated['days']));
            }

            $results = $query->get();

            if ($results->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => '未找到该策略的预测数据',
                    'code' => 404
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
                'code' => 200
            ]);

        } catch (\Exception $e) {
            Log::error('获取策略性能失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => '获取策略性能失败: ' . $e->getMessage(),
                'code' => 500
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
}
