<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\Prediction\ABTestingService;
use App\Models\ABTestConfig;
use App\Models\ABTestResult;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class ABTestingController extends Controller
{
    public function __construct(
        private ABTestingService $abTestingService
    ) {}

    /**
     * 啟動A/B測試
     */
    public function startABTest(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:100',
                'description' => 'nullable|string|max:500',
                'strategies' => 'required|array|min:2',
                'strategies.*' => 'required|string|max:50',
                'traffic_distribution' => 'required|array',
                'traffic_distribution.*' => 'required|numeric|min:0|max:100',
                'start_date' => 'required|date|after:now',
                'end_date' => 'required|date|after:start_date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '參數驗證失敗',
                    'errors' => $validator->errors(),
                    'code' => 400
                ], 400);
            }

            $validated = $validator->validated();

            // 檢查流量分配總和是否為100%
            $totalPercentage = array_sum($validated['traffic_distribution']);
            if (abs($totalPercentage - 100) > 0.01) {
                return response()->json([
                    'success' => false,
                    'message' => '流量分配總和必須為100%',
                    'code' => 400
                ], 400);
            }

            // 檢查策略是否一致
            $strategyKeys = array_keys($validated['strategies']);
            $trafficKeys = array_keys($validated['traffic_distribution']);

            if (array_diff($strategyKeys, $trafficKeys) || array_diff($trafficKeys, $strategyKeys)) {
                return response()->json([
                    'success' => false,
                    'message' => '策略列表與流量分配不匹配',
                    'code' => 400
                ], 400);
            }

            $result = $this->abTestingService->startABTest($validated);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'test_id' => $result['test_id'],
                        'message' => $result['message']
                    ],
                    'code' => 201
                ], 201);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'code' => 500
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('啟動A/B測試失敗', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => '啟動A/B測試失敗: ' . $e->getMessage(),
                'code' => 500
            ], 500);
        }
    }

    /**
     * 獲取A/B測試列表
     */
    public function listABTests(Request $request): JsonResponse
    {
        try {
            $query = ABTestConfig::with('creator')
                ->orderBy('created_at', 'desc');

            // 狀態篩選
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // 分頁
            $perPage = $request->get('per_page', 15);
            $tests = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $tests,
                'code' => 200
            ]);

        } catch (\Exception $e) {
            Log::error('獲取A/B測試列表失敗', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => '獲取A/B測試列表失敗: ' . $e->getMessage(),
                'code' => 500
            ], 500);
        }
    }

    /**
     * 獲取A/B測試報告
     */
    public function getABTestReport(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'test_id' => 'required|integer|exists:a_b_test_configs,id',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after:start_date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '參數驗證失敗',
                    'errors' => $validator->errors(),
                    'code' => 400
                ], 400);
            }

            $validated = $validator->validated();

            $result = $this->abTestingService->getABTestReport(
                $validated['test_id'],
                $validated['start_date'] ?? null,
                $validated['end_date'] ?? null
            );

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'data' => $result,
                    'code' => 200
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'code' => 500
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('獲取A/B測試報告失敗', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => '獲取A/B測試報告失敗: ' . $e->getMessage(),
                'code' => 500
            ], 500);
        }
    }

    /**
     * 停止A/B測試
     */
    public function stopABTest(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'test_id' => 'required|integer|exists:a_b_test_configs,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '參數驗證失敗',
                    'errors' => $validator->errors(),
                    'code' => 400
                ], 400);
            }

            $validated = $validator->validated();

            $result = $this->abTestingService->stopABTest($validated['test_id']);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'message' => $result['message']
                    ],
                    'code' => 200
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'code' => 500
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('停止A/B測試失敗', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => '停止A/B測試失敗: ' . $e->getMessage(),
                'code' => 500
            ], 500);
        }
    }

    /**
     * 獲取活躍的A/B測試
     */
    public function getActiveABTests(): JsonResponse
    {
        try {
            $activeTests = ABTestConfig::getActiveTests();

            return response()->json([
                'success' => true,
                'data' => $activeTests,
                'code' => 200
            ]);

        } catch (\Exception $e) {
            Log::error('獲取活躍A/B測試失敗', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => '獲取活躍A/B測試失敗: ' . $e->getMessage(),
                'code' => 500
            ], 500);
        }
    }

    /**
     * 獲取A/B測試詳細信息
     */
    public function getABTestDetail(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'test_id' => 'required|integer|exists:a_b_test_configs,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '參數驗證失敗',
                    'errors' => $validator->errors(),
                    'code' => 400
                ], 400);
            }

            $validated = $validator->validated();

            $test = ABTestConfig::with(['creator', 'results'])
                ->find($validated['test_id']);

            if (!$test) {
                return response()->json([
                    'success' => false,
                    'message' => 'A/B測試不存在',
                    'code' => 404
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $test,
                'code' => 200
            ]);

        } catch (\Exception $e) {
            Log::error('獲取A/B測試詳細信息失敗', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => '獲取A/B測試詳細信息失敗: ' . $e->getMessage(),
                'code' => 500
            ], 500);
        }
    }
}
