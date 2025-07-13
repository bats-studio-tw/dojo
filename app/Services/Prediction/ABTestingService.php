<?php

namespace App\Services\Prediction;

use App\Models\PredictionResult;
use App\Models\ABTestConfig;
use App\Models\ABTestResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ABTestingService
{
    /**
     * 啟動A/B測試
     */
    public function startABTest(array $config): array
    {
        try {
            // 驗證配置
            $this->validateABTestConfig($config);

            // 創建A/B測試配置
            $abTestConfig = ABTestConfig::create([
                'name' => $config['name'],
                'description' => $config['description'] ?? '',
                'strategies' => $config['strategies'],
                'traffic_distribution' => $config['traffic_distribution'],
                'start_date' => $config['start_date'],
                'end_date' => $config['end_date'],
                'status' => 'active',
                'created_by' => auth()->id(),
            ]);

            // 緩存配置
            Cache::put("ab_test:{$abTestConfig->id}", $abTestConfig, now()->addDays(30));

            Log::info('A/B測試已啟動', [
                'test_id' => $abTestConfig->id,
                'strategies' => $config['strategies'],
                'traffic_distribution' => $config['traffic_distribution']
            ]);

            return [
                'success' => true,
                'test_id' => $abTestConfig->id,
                'message' => 'A/B測試已成功啟動'
            ];

        } catch (\Exception $e) {
            Log::error('啟動A/B測試失敗', [
                'error' => $e->getMessage(),
                'config' => $config
            ]);

            return [
                'success' => false,
                'message' => '啟動A/B測試失敗: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 根據流量分配選擇策略
     */
    public function selectStrategy(int $testId, string $userId = null): string
    {
        try {
            // 從緩存獲取測試配置
            $config = Cache::get("ab_test:{$testId}");
            if (!$config) {
                $config = ABTestConfig::find($testId);
                if (!$config || $config->status !== 'active') {
                    return 'conservative'; // 默認策略
                }
                Cache::put("ab_test:{$testId}", $config, now()->addDays(30));
            }

            // 檢查測試是否在有效時間範圍內
            $now = now();
            if ($now < $config->start_date || $now > $config->end_date) {
                return 'conservative';
            }

            // 生成用戶特定的隨機數（確保同一用戶始終使用相同策略）
            $userHash = $userId ? crc32($userId) : crc32(request()->ip());
            $randomValue = ($userHash % 100) + 1;

            // 根據流量分配選擇策略
            $cumulativeProbability = 0;
            foreach ($config->traffic_distribution as $strategy => $percentage) {
                $cumulativeProbability += $percentage;
                if ($randomValue <= $cumulativeProbability) {
                    return $strategy;
                }
            }

            // 如果沒有匹配，返回第一個策略
            return array_keys($config->traffic_distribution)[0] ?? 'conservative';

        } catch (\Exception $e) {
            Log::error('選擇A/B測試策略失敗', [
                'test_id' => $testId,
                'error' => $e->getMessage()
            ]);
            return 'conservative';
        }
    }

    /**
     * 記錄A/B測試結果
     */
    public function recordABTestResult(int $testId, string $strategy, array $predictionData, array $actualResult = null): void
    {
        try {
            ABTestResult::create([
                'ab_test_id' => $testId,
                'strategy' => $strategy,
                'prediction_data' => $predictionData,
                'actual_result' => $actualResult,
                'user_id' => auth()->id(),
                'round_id' => $predictionData['round_id'] ?? null,
                'is_correct' => $this->isPredictionCorrect($predictionData, $actualResult),
                'created_at' => now(),
            ]);

        } catch (\Exception $e) {
            Log::error('記錄A/B測試結果失敗', [
                'test_id' => $testId,
                'strategy' => $strategy,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 獲取A/B測試報告
     */
    public function getABTestReport(int $testId, ?string $startDate = null, ?string $endDate = null): array
    {
        try {
            $config = ABTestConfig::find($testId);
            if (!$config) {
                throw new \Exception('A/B測試配置不存在');
            }

            // 構建查詢條件
            $query = ABTestResult::where('ab_test_id', $testId);

            if ($startDate) {
                $query->where('created_at', '>=', $startDate);
            }

            if ($endDate) {
                $query->where('created_at', '<=', $endDate);
            }

            // 按策略分組統計
            $results = $query->select([
                'strategy',
                DB::raw('COUNT(*) as total_predictions'),
                DB::raw('SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) as correct_predictions'),
                DB::raw('AVG(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) as accuracy_rate'),
                DB::raw('COUNT(DISTINCT user_id) as unique_users'),
                DB::raw('COUNT(DISTINCT round_id) as unique_rounds')
            ])
            ->groupBy('strategy')
            ->get();

            // 計算詳細指標
            $detailedResults = [];
            foreach ($results as $result) {
                $strategyResults = ABTestResult::where('ab_test_id', $testId)
                    ->where('strategy', $result->strategy)
                    ->when($startDate, fn($q) => $q->where('created_at', '>=', $startDate))
                    ->when($endDate, fn($q) => $q->where('created_at', '<=', $endDate))
                    ->get();

                $detailedResults[] = [
                    'strategy' => $result->strategy,
                    'total_predictions' => $result->total_predictions,
                    'correct_predictions' => $result->correct_predictions,
                    'accuracy_rate' => $result->accuracy_rate,
                    'unique_users' => $result->unique_users,
                    'unique_rounds' => $result->unique_rounds,
                    'traffic_percentage' => $config->traffic_distribution[$result->strategy] ?? 0,
                    'daily_accuracy' => $this->calculateDailyAccuracy($strategyResults),
                    'hourly_accuracy' => $this->calculateHourlyAccuracy($strategyResults),
                ];
            }

            return [
                'success' => true,
                'test_config' => $config,
                'results' => $detailedResults,
                'summary' => $this->generateSummary($detailedResults),
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ]
            ];

        } catch (\Exception $e) {
            Log::error('獲取A/B測試報告失敗', [
                'test_id' => $testId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => '獲取報告失敗: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 停止A/B測試
     */
    public function stopABTest(int $testId): array
    {
        try {
            $config = ABTestConfig::find($testId);
            if (!$config) {
                throw new \Exception('A/B測試配置不存在');
            }

            $config->update([
                'status' => 'stopped',
                'end_date' => now()
            ]);

            // 清除緩存
            Cache::forget("ab_test:{$testId}");

            Log::info('A/B測試已停止', ['test_id' => $testId]);

            return [
                'success' => true,
                'message' => 'A/B測試已成功停止'
            ];

        } catch (\Exception $e) {
            Log::error('停止A/B測試失敗', [
                'test_id' => $testId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => '停止A/B測試失敗: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 驗證A/B測試配置
     */
    private function validateABTestConfig(array $config): void
    {
        if (empty($config['name'])) {
            throw new \Exception('測試名稱不能為空');
        }

        if (empty($config['strategies']) || !is_array($config['strategies'])) {
            throw new \Exception('策略列表不能為空');
        }

        if (empty($config['traffic_distribution']) || !is_array($config['traffic_distribution'])) {
            throw new \Exception('流量分配不能為空');
        }

        // 檢查流量分配總和是否為100%
        $totalPercentage = array_sum($config['traffic_distribution']);
        if (abs($totalPercentage - 100) > 0.01) {
            throw new \Exception('流量分配總和必須為100%');
        }

        // 檢查策略是否一致
        $strategyKeys = array_keys($config['strategies']);
        $trafficKeys = array_keys($config['traffic_distribution']);

        if (array_diff($strategyKeys, $trafficKeys) || array_diff($trafficKeys, $strategyKeys)) {
            throw new \Exception('策略列表與流量分配不匹配');
        }
    }

    /**
     * 判斷預測是否正確
     */
    private function isPredictionCorrect(array $predictionData, ?array $actualResult): bool
    {
        if (!$actualResult) {
            return false;
        }

        $predictedWinner = $predictionData['predictions'][0]['symbol'] ?? '';
        $actualWinner = $actualResult['winner'] ?? '';

        return $predictedWinner === $actualWinner;
    }

    /**
     * 計算每日準確率
     */
    private function calculateDailyAccuracy($results): array
    {
        return $results->groupBy(function ($result) {
            return $result->created_at->format('Y-m-d');
        })->map(function ($dayResults) {
            return $dayResults->where('is_correct', true)->count() / $dayResults->count();
        })->toArray();
    }

    /**
     * 計算每小時準確率
     */
    private function calculateHourlyAccuracy($results): array
    {
        return $results->groupBy(function ($result) {
            return $result->created_at->format('Y-m-d H');
        })->map(function ($hourResults) {
            return $hourResults->where('is_correct', true)->count() / $hourResults->count();
        })->toArray();
    }

    /**
     * 生成報告摘要
     */
    private function generateSummary(array $results): array
    {
        $totalPredictions = array_sum(array_column($results, 'total_predictions'));
        $totalCorrect = array_sum(array_column($results, 'correct_predictions'));
        $overallAccuracy = $totalPredictions > 0 ? $totalCorrect / $totalPredictions : 0;

        $bestStrategy = collect($results)->sortByDesc('accuracy_rate')->first();
        $worstStrategy = collect($results)->sortBy('accuracy_rate')->first();

        return [
            'total_predictions' => $totalPredictions,
            'total_correct' => $totalCorrect,
            'overall_accuracy' => $overallAccuracy,
            'best_strategy' => $bestStrategy ? $bestStrategy['strategy'] : null,
            'best_accuracy' => $bestStrategy ? $bestStrategy['accuracy_rate'] : 0,
            'worst_strategy' => $worstStrategy ? $worstStrategy['strategy'] : null,
            'worst_accuracy' => $worstStrategy ? $worstStrategy['accuracy_rate'] : 0,
            'strategy_count' => count($results)
        ];
    }
}
