<?php

namespace App\Services\Prediction;

use App\Jobs\RunBacktestJob;
use App\Models\BacktestReport;
use App\Models\GameRound;
use App\Models\RoundResult;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class BacktestService
{
    public function __construct(private PredictionServiceFactory $factory)
    {
    }

    /**
     * 同步執行回測（用於小規模測試）
     * @param array $rounds 回合數據
     * @param array $strategyConfig 策略配置
     * @param int|null $userId 用戶ID
     * @return array 回測結果
     */
    public function runBacktest(array $rounds, array $strategyConfig, ?int $userId = null): array
    {
        try {
            Log::info('開始同步回測', [
                'rounds_count' => count($rounds),
                'strategy' => $strategyConfig['strategy_tag'] ?? 'unknown',
                'user_id' => $userId,
            ]);

            $results = [];
            $successfulRounds = 0;

            foreach ($rounds as $round) {
                try {
                    $service = $this->factory->create($strategyConfig['strategy_tag'] ?? 'conservative');
                    $prediction = $service->predict(
                        $round['symbols'] ?? ['ETH', 'DOGE', 'SOL'],
                        $round['timestamp'] ?? time(),
                        $round['history'] ?? [],
                        $round['id'] ?? 0
                    );

                    if (! empty($prediction)) {
                        $actualResult = $this->getActualResult($round['id'] ?? 0);
                        if ($actualResult) {
                            $results[] = [
                                'round_id' => $round['id'] ?? 0,
                                'prediction' => $prediction,
                                'actual_result' => $actualResult,
                            ];
                            $successfulRounds++;
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('回測回合失敗', [
                        'round_id' => $round['id'] ?? 0,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $metrics = $this->calculateBacktestMetrics($results);

            Log::info('同步回測完成', [
                'total_rounds' => count($rounds),
                'successful_rounds' => $successfulRounds,
                'win_rate' => $metrics['win_rate'],
                'sharpe_ratio' => $metrics['sharpe_ratio'],
            ]);

            return [
                'success' => true,
                'metrics' => $metrics,
                'results' => $results,
                'summary' => [
                    'total_rounds' => count($rounds),
                    'successful_rounds' => $successfulRounds,
                    'strategy_tag' => $strategyConfig['strategy_tag'] ?? 'unknown',
                ],
            ];

        } catch (\Exception $e) {
            Log::error('同步回測失敗', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'metrics' => [],
                'results' => [],
            ];
        }
    }

    /**
     * 主入口：接收配置並將其分派到隊列中執行
     * @return string Job Batch ID，用於前端查詢進度
     */
    public function startBacktest(array $rounds, array $strategyConfig, ?int $userId = null): string
    {
        $batch = Bus::batch([
            new RunBacktestJob($rounds, $strategyConfig, $userId),
        ])->then(function ($batch) {
            Log::info('回測批次完成', ['batch_id' => $batch->id]);
        })->catch(function ($batch, $e) {
            Log::error('回測批次失敗', [
                'batch_id' => $batch->id,
                'error' => $e->getMessage(),
            ]);
        })->name('Backtest for strategy: ' . ($strategyConfig['strategy_tag'] ?? ''))
          ->dispatch();

        return $batch->id;
    }

    /**
     * Grid Search 的入口
     * @return string Job Batch ID
     */
    public function startGridSearch(array $rounds, array $paramMatrix, ?int $userId = null): string
    {
        $jobs = [];
        $jobCount = 0;

        foreach ($paramMatrix['weights'] as $weights) {
            foreach ($paramMatrix['normalization'] as $normalization) {
                $strategyConfig = [
                    'strategy_tag' => 'grid_search_' . uniqid(),
                    'weights' => $weights,
                    'normalization' => $normalization,
                    'param_matrix' => $paramMatrix,
                ];
                $jobs[] = new RunBacktestJob($rounds, $strategyConfig, $userId);
                $jobCount++;
            }
        }

        Log::info('開始 Grid Search', [
            'total_jobs' => $jobCount,
            'rounds_count' => count($rounds),
            'user_id' => $userId,
        ]);

        $batch = Bus::batch($jobs)
            ->then(function ($batch) {
                Log::info('Grid Search 批次完成', ['batch_id' => $batch->id]);
            })
            ->catch(function ($batch, $e) {
                Log::error('Grid Search 批次失敗', [
                    'batch_id' => $batch->id,
                    'error' => $e->getMessage(),
                ]);
            })
            ->name('Grid Search Backtest')
            ->dispatch();

        return $batch->id;
    }

    /**
     * 獲取歷史回合數據
     * @param int $limit 回合數量限制
     * @param string|null $startDate 開始日期
     * @param string|null $endDate 結束日期
     * @return array 回合數據
     */
    public function getHistoricalRounds(int $limit = 100, ?string $startDate = null, ?string $endDate = null): array
    {
        $query = GameRound::query();

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        $rounds = $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        $roundData = [];
        foreach ($rounds as $round) {
            // 獲取該回合的代幣列表
            $tokens = RoundResult::where('game_round_id', $round->id)
                ->pluck('token_symbol')
                ->toArray();

            // 確保有代幣數據且回合已結算
            if (! empty($tokens) && $round->isSettled()) {
                $roundData[] = [
                    'id' => $round->id,
                    'round_id' => $round->round_id,
                    'symbols' => $tokens,
                    'timestamp' => $round->created_at->timestamp,
                    'history' => [], // 可以根據需要添加歷史數據
                ];
            }
        }

        return $roundData;
    }

    /**
     * 獲取回測報告
     * @param int $reportId 報告ID
     * @return array|null 報告數據
     */
    public function getBacktestReport(int $reportId): ?array
    {
        $report = BacktestReport::find($reportId);

        if (! $report) {
            return null;
        }

        return [
            'id' => $report->id,
            'strategy_tag' => $report->strategy_tag,
            'strategy_config' => $report->strategy_config,
            'total_rounds' => $report->total_rounds,
            'successful_rounds' => $report->successful_rounds,
            'win_rate' => $report->win_rate,
            'breakeven_rate' => $report->breakeven_rate,
            'sharpe_ratio' => $report->sharpe_ratio,
            'sortino_ratio' => $report->sortino_ratio,
            'calmar_ratio' => $report->calmar_ratio,
            'max_drawdown' => $report->max_drawdown,
            'max_profit' => $report->max_profit,
            'max_loss' => $report->max_loss,
            'avg_profit_loss_ratio' => $report->avg_profit_loss_ratio,
            'total_profit' => $report->total_profit,
            'profit_rate' => $report->profit_rate,
            'volatility' => $report->volatility,
            'profit_factor' => $report->profit_factor,
            'consecutive_wins' => $report->consecutive_wins,
            'consecutive_losses' => $report->consecutive_losses,
            'status' => $report->status,
            'started_at' => $report->started_at,
            'completed_at' => $report->completed_at,
            'is_grid_search' => ! empty($report->param_matrix),
        ];
    }

    /**
     * 獲取實際結果數據
     * @param int $roundId 回合ID
     * @return array|null 實際結果
     */
    private function getActualResult(int $roundId): ?array
    {
        $roundResults = RoundResult::where('game_round_id', $roundId)
            ->orderBy('rank')
            ->get();

        if ($roundResults->isEmpty()) {
            return null;
        }

        $winner = $roundResults->first();
        $rankings = [];

        foreach ($roundResults as $result) {
            $rankings[$result->token_symbol] = [
                'rank' => $result->rank,
                'value' => $result->value,
            ];
        }

        return [
            'winner' => $winner->token_symbol,
            'winner_rank' => $winner->rank,
            'winner_value' => $winner->value,
            'rankings' => $rankings,
            'total_tokens' => $roundResults->count(),
        ];
    }

    /**
     * 計算回測指標
     * @param array $results 回測結果
     * @return array 指標數據
     */
    private function calculateBacktestMetrics(array $results): array
    {
        if (empty($results)) {
            return [
                'win_rate' => 0,
                'breakeven_rate' => 0,
                'profit_rate' => 0,
                'sharpe_ratio' => 0,
                'sortino_ratio' => 0,
                'calmar_ratio' => 0,
                'max_drawdown' => 0,
                'max_profit' => 0,
                'max_loss' => 0,
                'avg_profit_loss_ratio' => 0,
                'total_profit' => 0,
                'volatility' => 0,
                'profit_factor' => 0,
                'consecutive_wins' => 0,
                'consecutive_losses' => 0,
            ];
        }

        $wins = 0;
        $breakevens = 0;
        $totalProfit = 0;
        $profits = [];
        $maxProfit = 0;
        $maxLoss = 0;
        $cumulativeProfit = 0;
        $maxDrawdown = 0;
        $peak = 0;
        $maxConsecutiveWins = 0;
        $maxConsecutiveLosses = 0;
        $currentConsecutiveWins = 0;
        $currentConsecutiveLosses = 0;

        foreach ($results as $result) {
            $actualResult = $result['actual_result'];
            $prediction = $result['prediction'];

            if (empty($actualResult) || empty($prediction)) {
                continue;
            }

            $predictedWinner = $prediction[0]['symbol'] ?? '';
            $actualWinner = $actualResult['winner'] ?? '';

            // 計算勝率
            if ($predictedWinner === $actualWinner) {
                $wins++;
                $currentConsecutiveWins++;
                $currentConsecutiveLosses = 0;
                $maxConsecutiveWins = max($maxConsecutiveWins, $currentConsecutiveWins);
            } else {
                $currentConsecutiveLosses++;
                $currentConsecutiveWins = 0;
                $maxConsecutiveLosses = max($maxConsecutiveLosses, $currentConsecutiveLosses);
            }

            // 計算盈虧
            $profit = $this->calculateProfit($prediction, $actualResult);
            $profits[] = $profit;
            $totalProfit += $profit;

            if ($profit >= 0) {
                $breakevens++;
            }

            $maxProfit = max($maxProfit, $profit);
            $maxLoss = min($maxLoss, $profit);

            // 計算累積盈虧和最大回撤
            $cumulativeProfit += $profit;
            if ($cumulativeProfit > $peak) {
                $peak = $cumulativeProfit;
            }

            $drawdown = $peak - $cumulativeProfit;
            $maxDrawdown = max($maxDrawdown, $drawdown);
        }

        $totalRounds = count($results);
        $winRate = $totalRounds > 0 ? $wins / $totalRounds : 0;
        $breakevenRate = $totalRounds > 0 ? $breakevens / $totalRounds : 0;
        $profitRate = $totalRounds > 0 ? $totalProfit / $totalRounds : 0;

        // 計算夏普比率
        $sharpeRatio = $this->calculateSharpeRatio($profits);

        // 計算Sortino比率
        $sortinoRatio = $this->calculateSortinoRatio($profits);

        // 計算Calmar比率
        $calmarRatio = $this->calculateCalmarRatio($profitRate, $maxDrawdown);

        // 計算其他指標
        $volatility = $this->calculateVolatility($profits);
        $profitFactor = $this->calculateProfitFactor($profits);
        $avgProfitLossRatio = $this->calculateAvgProfitLossRatio($profits);

        return [
            'win_rate' => $winRate,
            'breakeven_rate' => $breakevenRate,
            'profit_rate' => $profitRate,
            'sharpe_ratio' => $sharpeRatio,
            'sortino_ratio' => $sortinoRatio,
            'calmar_ratio' => $calmarRatio,
            'max_drawdown' => $maxDrawdown,
            'max_profit' => $maxProfit,
            'max_loss' => abs($maxLoss),
            'avg_profit_loss_ratio' => $avgProfitLossRatio,
            'total_profit' => $totalProfit,
            'volatility' => $volatility,
            'profit_factor' => $profitFactor,
            'consecutive_wins' => $maxConsecutiveWins,
            'consecutive_losses' => $maxConsecutiveLosses,
        ];
    }

    /**
     * 計算夏普比率
     */
    private function calculateSharpeRatio(array $profits): float
    {
        if (empty($profits) || count($profits) < 2) {
            return 0.0;
        }

        $avgProfit = array_sum($profits) / count($profits);
        $variance = 0;

        foreach ($profits as $profit) {
            $variance += pow($profit - $avgProfit, 2);
        }

        $volatility = sqrt($variance / (count($profits) - 1));

        // 假設每輪遊戲間隔1小時，年化收益率
        $annualizedReturn = $avgProfit * 24 * 365;
        $annualizedVolatility = $volatility * sqrt(24 * 365);

        return $annualizedVolatility > 0 ? $annualizedReturn / $annualizedVolatility : 0;
    }

    /**
     * 計算Sortino比率
     */
    private function calculateSortinoRatio(array $profits): float
    {
        if (empty($profits) || count($profits) < 2) {
            return 0.0;
        }

        $avgProfit = array_sum($profits) / count($profits);
        $downsideVariance = 0;
        $downsideCount = 0;

        foreach ($profits as $profit) {
            if ($profit < $avgProfit) {
                $downsideVariance += pow($profit - $avgProfit, 2);
                $downsideCount++;
            }
        }

        if ($downsideCount === 0) {
            return 0.0;
        }

        $downsideDeviation = sqrt($downsideVariance / $downsideCount);
        $annualizedReturn = $avgProfit * 24 * 365;
        $annualizedDownsideDeviation = $downsideDeviation * sqrt(24 * 365);

        return $annualizedDownsideDeviation > 0 ? $annualizedReturn / $annualizedDownsideDeviation : 0;
    }

    /**
     * 計算Calmar比率
     */
    private function calculateCalmarRatio(float $profitRate, float $maxDrawdown): float
    {
        if ($maxDrawdown <= 0) {
            return 0.0;
        }

        $annualizedReturn = $profitRate * 24 * 365;
        return $annualizedReturn / $maxDrawdown;
    }

    /**
     * 計算波動率
     */
    private function calculateVolatility(array $profits): float
    {
        if (empty($profits) || count($profits) < 2) {
            return 0.0;
        }

        $avgProfit = array_sum($profits) / count($profits);
        $variance = 0;

        foreach ($profits as $profit) {
            $variance += pow($profit - $avgProfit, 2);
        }

        return sqrt($variance / (count($profits) - 1));
    }

    /**
     * 計算盈利因子
     */
    private function calculateProfitFactor(array $profits): float
    {
        $positiveProfits = array_filter($profits, fn ($p) => $p > 0);
        $negativeProfits = array_filter($profits, fn ($p) => $p < 0);

        $totalPositiveProfit = array_sum($positiveProfits);
        $totalNegativeProfit = abs(array_sum($negativeProfits));

        return $totalNegativeProfit > 0 ? $totalPositiveProfit / $totalNegativeProfit : 0;
    }

    /**
     * 計算平均盈虧比
     */
    private function calculateAvgProfitLossRatio(array $profits): float
    {
        $positiveProfits = array_filter($profits, fn ($p) => $p > 0);
        $negativeProfits = array_filter($profits, fn ($p) => $p < 0);

        $avgPositiveProfit = ! empty($positiveProfits) ? array_sum($positiveProfits) / count($positiveProfits) : 0;
        $avgNegativeProfit = ! empty($negativeProfits) ? array_sum($negativeProfits) / count($negativeProfits) : 0;

        return $avgNegativeProfit != 0 ? abs($avgPositiveProfit / $avgNegativeProfit) : 0;
    }

    /**
     * 計算盈虧
     */
    private function calculateProfit(array $prediction, array $actualResult): float
    {
        if (empty($prediction) || empty($actualResult)) {
            return 0.0;
        }

        $predictedWinner = $prediction[0]['symbol'] ?? '';
        $actualWinner = $actualResult['winner'] ?? '';

        // 基本勝負判斷：預測第一名是否正確
        if ($predictedWinner === $actualWinner) {
            return 1.0; // 預測正確，獲利1分
        }

        // 如果預測錯誤，根據預測排名給予不同的懲罰
        $rankings = $actualResult['rankings'] ?? [];
        $predictedRank = $rankings[$predictedWinner]['rank'] ?? 999;

        // 根據預測排名給予懲罰：排名越靠後，懲罰越重
        if ($predictedRank <= 3) {
            return -0.5; // 前三名，輕微懲罰
        } elseif ($predictedRank <= 5) {
            return -0.8; // 前五名，中等懲罰
        } else {
            return -1.0; // 其他排名，重懲罰
        }
    }
}
