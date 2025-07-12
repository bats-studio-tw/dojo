<?php

namespace App\Services\Prediction;

use App\Models\GameRound;
use App\Models\PredictionResult;
use App\Models\RoundResult;
use Illuminate\Support\Facades\Log;

class BacktestService
{
    public function __construct(
        private PredictionService $predictionService
    ) {}

    /**
     * 运行单策略回测
     *
     * @param array $rounds 游戏回合数组
     * @param array $strategyConfig 策略配置
     * @return array 回测报告
     */
    public function runBacktest(array $rounds, array $strategyConfig): array
    {
        $results = [];
        $totalRounds = count($rounds);

        foreach ($rounds as $round) {
            try {
                // 执行预测
                $prediction = $this->predictionService->predict(
                    $round['symbols'] ?? ['ETH', 'DOGE', 'SOL'],
                    $round['timestamp'] ?? time(),
                    $round['history'] ?? [],
                    $round['id'] ?? 0
                );

                if (!empty($prediction)) {
                    $results[] = [
                        'round_id' => $round['id'] ?? 0,
                        'prediction' => $prediction,
                        'actual_result' => $this->getActualResult($round['id'] ?? 0),
                    ];
                }
            } catch (\Exception $e) {
                Log::error("Backtest round failed: " . $e->getMessage(), [
                    'round' => $round,
                    'exception' => $e
                ]);
            }
        }

        // 计算回测指标
        return $this->calculateBacktestMetrics($results);
    }

    /**
     * 网格搜索最优参数
     *
     * @param array $rounds 历史回合数据
     * @param array $paramMatrix 参数矩阵
     * @return array 网格搜索结果
     */
    public function gridSearch(array $rounds, array $paramMatrix): array
    {
        $results = [];

        // 生成所有参数组合
        $combinations = $this->generateParameterCombinations($paramMatrix);

        foreach ($combinations as $index => $params) {
            try {
                // 使用当前参数创建新的预测服务
                $service = $this->createPredictionServiceWithParams($params);
                $backtestService = new BacktestService($service);

                // 运行回测
                $report = $backtestService->runBacktest($rounds, $params);

                $results[] = [
                    'combination_id' => $index,
                    'parameters' => $params,
                    'metrics' => $report,
                ];

            } catch (\Exception $e) {
                Log::error("Grid search combination failed: " . $e->getMessage(), [
                    'params' => $params,
                    'exception' => $e
                ]);
            }
        }

        // 按胜率排序
        usort($results, fn($a, $b) => $b['metrics']['win_rate'] <=> $a['metrics']['win_rate']);

        return $results;
    }

    /**
     * 计算回测指标
     */
    private function calculateBacktestMetrics(array $results): array
    {
        if (empty($results)) {
            return [
                'win_rate' => 0,
                'profit_rate' => 0,
                'sharpe_ratio' => 0,
                'max_drawdown' => 0,
                'max_profit' => 0,
                'max_loss' => 0,
                'avg_profit_loss_ratio' => 0,
                'total_rounds' => 0,
            ];
        }

        $wins = 0;
        $totalProfit = 0;
        $profits = [];
        $maxProfit = 0;
        $maxLoss = 0;
        $cumulativeProfit = 0;
        $maxCumulativeProfit = 0;
        $maxDrawdown = 0;

        foreach ($results as $result) {
            $actualResult = $result['actual_result'];
            $prediction = $result['prediction'];

            if (empty($actualResult) || empty($prediction)) {
                continue;
            }

            // 计算预测准确性
            $predictedWinner = $prediction[0]['symbol'] ?? '';
            $actualWinner = $actualResult['winner'] ?? '';

            if ($predictedWinner === $actualWinner) {
                $wins++;
            }

            // 计算盈亏
            $profit = $this->calculateProfit($prediction, $actualResult);
            $profits[] = $profit;
            $totalProfit += $profit;

            $maxProfit = max($maxProfit, $profit);
            $maxLoss = min($maxLoss, $profit);

            $cumulativeProfit += $profit;
            $maxCumulativeProfit = max($maxCumulativeProfit, $cumulativeProfit);
            $maxDrawdown = min($maxDrawdown, $cumulativeProfit - $maxCumulativeProfit);
        }

        $totalRounds = count($results);
        $winRate = $totalRounds > 0 ? $wins / $totalRounds : 0;
        $profitRate = $totalRounds > 0 ? $totalProfit / $totalRounds : 0;

        // 计算夏普比率
        $sharpeRatio = 0;
        if (!empty($profits)) {
            $avgProfit = array_sum($profits) / count($profits);
            $variance = 0;
            foreach ($profits as $profit) {
                $variance += pow($profit - $avgProfit, 2);
            }
            $stdDev = sqrt($variance / count($profits));
            $sharpeRatio = $stdDev > 0 ? $avgProfit / $stdDev : 0;
        }

        // 计算平均盈亏比
        $positiveProfits = array_filter($profits, fn($p) => $p > 0);
        $negativeProfits = array_filter($profits, fn($p) => $p < 0);

        $avgPositiveProfit = !empty($positiveProfits) ? array_sum($positiveProfits) / count($positiveProfits) : 0;
        $avgNegativeProfit = !empty($negativeProfits) ? array_sum($negativeProfits) / count($negativeProfits) : 0;

        $avgProfitLossRatio = $avgNegativeProfit != 0 ? abs($avgPositiveProfit / $avgNegativeProfit) : 0;

        return [
            'win_rate' => $winRate,
            'profit_rate' => $profitRate,
            'sharpe_ratio' => $sharpeRatio,
            'max_drawdown' => abs($maxDrawdown),
            'max_profit' => $maxProfit,
            'max_loss' => abs($maxLoss),
            'avg_profit_loss_ratio' => $avgProfitLossRatio,
            'total_rounds' => $totalRounds,
        ];
    }

    /**
     * 获取实际结果
     */
    private function getActualResult(int $roundId): ?array
    {
        $roundResult = RoundResult::where('game_round_id', $roundId)->first();

        if (!$roundResult) {
            return null;
        }

        return [
            'winner' => $roundResult->winner,
            'loser' => $roundResult->loser,
            'winner_score' => $roundResult->winner_score,
            'loser_score' => $roundResult->loser_score,
        ];
    }

    /**
     * 计算单次预测的盈亏
     */
    private function calculateProfit(array $prediction, array $actualResult): float
    {
        // 简化的盈亏计算逻辑
        // 这里可以根据实际业务需求调整
        $predictedWinner = $prediction[0]['symbol'] ?? '';
        $actualWinner = $actualResult['winner'] ?? '';

        if ($predictedWinner === $actualWinner) {
            return 1.0; // 预测正确，盈利1
        } else {
            return -1.0; // 预测错误，亏损1
        }
    }

    /**
     * 生成参数组合
     */
    private function generateParameterCombinations(array $paramMatrix): array
    {
        $combinations = [[]];

        foreach ($paramMatrix as $paramName => $values) {
            $newCombinations = [];
            foreach ($combinations as $combination) {
                foreach ($values as $value) {
                    $newCombinations[] = array_merge($combination, [$paramName => $value]);
                }
            }
            $combinations = $newCombinations;
        }

        return $combinations;
    }

    /**
     * 使用指定参数创建预测服务
     */
    private function createPredictionServiceWithParams(array $params): PredictionService
    {
        // 这里需要根据实际架构调整
        // 暂时返回原始服务
        return $this->predictionService;
    }
}
