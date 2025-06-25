<?php

namespace App\Http\Controllers;

use App\Services\PredictionAnalysisService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

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
}
