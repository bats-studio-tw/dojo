<?php

namespace App\Http\Controllers;

use App\Services\Prediction\BacktestService;
use App\Services\Prediction\PredictionServiceFactory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PredictionController extends Controller
{
    /**
     * 执行预测
     */
    public function predict(Request $request): JsonResponse
    {
        $request->validate([
            'symbols' => 'required|array|min:1',
            'symbols.*' => 'string',
            'strategy' => 'string|in:' . implode(',', PredictionServiceFactory::getAvailableStrategies()),
            'game_round_id' => 'required|integer',
        ]);

        try {
            $symbols = $request->input('symbols');
            $strategy = $request->input('strategy', 'conservative');
            $gameRoundId = $request->input('game_round_id');
            $timestamp = time();

            // 创建预测服务
            $predictionService = PredictionServiceFactory::create($strategy);

            // 执行预测
            $result = $predictionService->predict(
                $symbols,
                $timestamp,
                [], // 历史数据，可以根据需要扩展
                $gameRoundId
            );

            return response()->json([
                'success' => true,
                'data' => $result,
                'strategy' => $strategy,
                'timestamp' => $timestamp,
            ]);

        } catch (\Exception $e) {
            Log::error('Prediction failed: ' . $e->getMessage(), [
                'request' => $request->all(),
                'exception' => $e
            ]);

            return response()->json([
                'success' => false,
                'message' => '预测失败: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 运行回测
     */
    public function backtest(Request $request): JsonResponse
    {
        $request->validate([
            'rounds' => 'required|array|min:1',
            'strategy' => 'string|in:' . implode(',', PredictionServiceFactory::getAvailableStrategies()),
        ]);

        try {
            $rounds = $request->input('rounds');
            $strategy = $request->input('strategy', 'conservative');

            // 创建预测服务
            $predictionService = PredictionServiceFactory::create($strategy);
            $backtestService = new BacktestService($predictionService);

            // 运行回测
            $report = $backtestService->runBacktest($rounds, [
                'strategy' => $strategy,
            ]);

            return response()->json([
                'success' => true,
                'data' => $report,
                'strategy' => $strategy,
            ]);

        } catch (\Exception $e) {
            Log::error('Backtest failed: ' . $e->getMessage(), [
                'request' => $request->all(),
                'exception' => $e
            ]);

            return response()->json([
                'success' => false,
                'message' => '回测失败: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 网格搜索最优参数
     */
    public function gridSearch(Request $request): JsonResponse
    {
        $request->validate([
            'rounds' => 'required|array|min:1',
            'param_matrix' => 'array',
        ]);

        try {
            $rounds = $request->input('rounds');
            $paramMatrix = $request->input('param_matrix', config('prediction.grid_search'));

            // 创建默认预测服务
            $predictionService = PredictionServiceFactory::create('conservative');
            $backtestService = new BacktestService($predictionService);

            // 运行网格搜索
            $results = $backtestService->gridSearch($rounds, $paramMatrix);

            return response()->json([
                'success' => true,
                'data' => $results,
                'total_combinations' => count($results),
            ]);

        } catch (\Exception $e) {
            Log::error('Grid search failed: ' . $e->getMessage(), [
                'request' => $request->all(),
                'exception' => $e
            ]);

            return response()->json([
                'success' => false,
                'message' => '网格搜索失败: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 获取可用策略列表
     */
    public function getStrategies(): JsonResponse
    {
        try {
            $strategies = PredictionServiceFactory::getAvailableStrategies();
            $config = config('prediction');

            $strategyDetails = [];
            foreach ($strategies as $strategy) {
                $strategyDetails[$strategy] = $config['strategies'][$strategy] ?? [];
            }

            return response()->json([
                'success' => true,
                'data' => $strategyDetails,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get strategies: ' . $e->getMessage(), [
                'exception' => $e
            ]);

            return response()->json([
                'success' => false,
                'message' => '获取策略列表失败: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 获取预测结果历史
     */
    public function getHistory(Request $request): JsonResponse
    {
        $request->validate([
            'game_round_id' => 'integer',
            'strategy' => 'string',
            'token' => 'string',
            'limit' => 'integer|min:1|max:100',
        ]);

        try {
            $query = \App\Models\PredictionResult::query();

            if ($request->has('game_round_id')) {
                $query->byGameRound($request->input('game_round_id'));
            }

            if ($request->has('strategy')) {
                $query->byStrategy($request->input('strategy'));
            }

            if ($request->has('token')) {
                $query->byToken($request->input('token'));
            }

            $limit = $request->input('limit', 50);
            $results = $query->orderBy('created_at', 'desc')->limit($limit)->get();

            return response()->json([
                'success' => true,
                'data' => $results,
                'count' => $results->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get prediction history: ' . $e->getMessage(), [
                'request' => $request->all(),
                'exception' => $e
            ]);

            return response()->json([
                'success' => false,
                'message' => '获取预测历史失败: ' . $e->getMessage(),
            ], 500);
        }
    }
}
