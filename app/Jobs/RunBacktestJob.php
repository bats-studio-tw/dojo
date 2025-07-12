<?php

namespace App\Jobs;

use App\Models\BacktestReport;
use App\Models\RoundResult;
use App\Services\Prediction\PredictionServiceFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunBacktestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600;
    public $tries = 1;

    public function __construct(
        private array $rounds,
        private array $strategyConfig,
        private ?int $userId = null,
        private ?string $batchId = null
    ) {}

    public function handle(): void
    {
        $report = BacktestReport::create([
            'user_id' => $this->userId,
            'batch_id' => $this->batchId,
            'strategy_tag' => $this->strategyConfig['strategy_tag'] ?? 'unknown',
            'strategy_config' => $this->strategyConfig,
            'param_matrix' => $this->strategyConfig['param_matrix'] ?? null,
            'total_rounds' => count($this->rounds),
            'successful_rounds' => 0,
            'status' => 'processing',
            'started_at' => now(),
        ]);

        try {
            $predictionService = PredictionServiceFactory::create(
                $this->strategyConfig['strategy_tag'] ?? 'conservative'
            );
            $results = [];
            $successfulRounds = 0;
            foreach ($this->rounds as $round) {
                try {
                    $prediction = $predictionService->predict(
                        $round['symbols'] ?? ['ETH', 'DOGE', 'SOL'],
                        $round['timestamp'] ?? time(),
                        $round['history'] ?? [],
                        $round['id'] ?? 0
                    );
                    if (!empty($prediction)) {
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
                    Log::error('Backtest round failed', [
                        'round_id' => $round['id'] ?? 0,
                        'error' => $e->getMessage(),
                        'report_id' => $report->id
                    ]);
                }
            }
            $metrics = $this->calculateBacktestMetrics($results);
            $report->update([
                'successful_rounds' => $successfulRounds,
                'win_rate' => $metrics['win_rate'],
                'breakeven_rate' => $metrics['breakeven_rate'],
                'sharpe_ratio' => $metrics['sharpe_ratio'],
                'sortino_ratio' => $metrics['sortino_ratio'],
                'calmar_ratio' => $metrics['calmar_ratio'],
                'max_drawdown' => $metrics['max_drawdown'],
                'max_profit' => $metrics['max_profit'],
                'max_loss' => $metrics['max_loss'],
                'avg_profit_loss_ratio' => $metrics['avg_profit_loss_ratio'],
                'total_profit' => $metrics['total_profit'],
                'profit_rate' => $metrics['profit_rate'],
                'volatility' => $metrics['volatility'],
                'profit_factor' => $metrics['profit_factor'],
                'consecutive_wins' => $metrics['consecutive_wins'],
                'consecutive_losses' => $metrics['consecutive_losses'],
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Backtest job failed', [
                'report_id' => $report->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $report->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
            throw $e;
        }
    }

    private function calculateBacktestMetrics(array $results): array
    {
        if (empty($results)) {
            return [
                'win_rate' => 0,
                'breakeven_rate' => 0,
                'profit_rate' => 0,
                'sharpe_ratio' => 0,
                'max_drawdown' => 0,
                'max_profit' => 0,
                'max_loss' => 0,
                'avg_profit_loss_ratio' => 0,
                'total_profit' => 0,
                'volatility' => 0,
                'calmar_ratio' => 0,
                'sortino_ratio' => 0,
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
        $maxCumulativeProfit = 0;
        $maxDrawdown = 0;
        $peak = 0;
        $consecutiveWins = 0;
        $consecutiveLosses = 0;
        $maxConsecutiveWins = 0;
        $maxConsecutiveLosses = 0;
        $currentConsecutiveWins = 0;
        $currentConsecutiveLosses = 0;

        foreach ($results as $result) {
            $actualResult = $result['actual_result'];
            $prediction = $result['prediction'];

            if (empty($actualResult) || empty($prediction)) continue;

            $predictedWinner = $prediction[0]['symbol'] ?? '';
            $actualWinner = $actualResult['winner'] ?? '';

            // 计算胜率
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

            // 计算盈亏
            $profit = $this->calculateProfit($prediction, $actualResult);
            $profits[] = $profit;
            $totalProfit += $profit;

            if ($profit >= 0) $breakevens++;

            $maxProfit = max($maxProfit, $profit);
            $maxLoss = min($maxLoss, $profit);

            // 计算累积盈亏和最大回撤
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

        // 优化夏普比率计算（使用年化收益率）
        $sharpeRatio = 0;
        $volatility = 0;
        if (!empty($profits) && count($profits) > 1) {
            $avgProfit = array_sum($profits) / count($profits);
            $variance = 0;
            foreach ($profits as $profit) {
                $variance += pow($profit - $avgProfit, 2);
            }
            $volatility = sqrt($variance / (count($profits) - 1));

            // 假设每轮游戏间隔1小时，年化收益率
            $annualizedReturn = $avgProfit * 24 * 365;
            $annualizedVolatility = $volatility * sqrt(24 * 365);

            $sharpeRatio = $annualizedVolatility > 0 ? $annualizedReturn / $annualizedVolatility : 0;
        }

        // 计算Sortino比率（只考虑下行风险）
        $sortinoRatio = 0;
        if (!empty($profits) && count($profits) > 1) {
            $avgProfit = array_sum($profits) / count($profits);
            $downsideVariance = 0;
            $downsideCount = 0;

            foreach ($profits as $profit) {
                if ($profit < $avgProfit) {
                    $downsideVariance += pow($profit - $avgProfit, 2);
                    $downsideCount++;
                }
            }

            if ($downsideCount > 0) {
                $downsideDeviation = sqrt($downsideVariance / $downsideCount);
                $annualizedReturn = $avgProfit * 24 * 365;
                $annualizedDownsideDeviation = $downsideDeviation * sqrt(24 * 365);

                $sortinoRatio = $annualizedDownsideDeviation > 0 ? $annualizedReturn / $annualizedDownsideDeviation : 0;
            }
        }

        // 计算Calmar比率（年化收益率/最大回撤）
        $calmarRatio = 0;
        if ($maxDrawdown > 0) {
            $annualizedReturn = $profitRate * 24 * 365;
            $calmarRatio = $annualizedReturn / $maxDrawdown;
        }

        // 计算盈亏比
        $positiveProfits = array_filter($profits, fn($p) => $p > 0);
        $negativeProfits = array_filter($profits, fn($p) => $p < 0);
        $avgPositiveProfit = !empty($positiveProfits) ? array_sum($positiveProfits) / count($positiveProfits) : 0;
        $avgNegativeProfit = !empty($negativeProfits) ? array_sum($negativeProfits) / count($negativeProfits) : 0;
        $avgProfitLossRatio = $avgNegativeProfit != 0 ? abs($avgPositiveProfit / $avgNegativeProfit) : 0;

        // 计算盈利因子（总盈利/总亏损）
        $totalPositiveProfit = array_sum($positiveProfits);
        $totalNegativeProfit = abs(array_sum($negativeProfits));
        $profitFactor = $totalNegativeProfit > 0 ? $totalPositiveProfit / $totalNegativeProfit : 0;

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

    private function getActualResult(int $roundId): ?array
    {
        $roundResult = RoundResult::where('game_round_id', $roundId)->first();
        if (!$roundResult) return null;
        return [
            'winner' => $roundResult->winner,
            'loser' => $roundResult->loser,
            'winner_score' => $roundResult->winner_score,
            'loser_score' => $roundResult->loser_score,
        ];
    }

    private function calculateProfit(array $prediction, array $actualResult): float
    {
        $predictedWinner = $prediction[0]['symbol'] ?? '';
        $actualWinner = $actualResult['winner'] ?? '';
        return $predictedWinner === $actualWinner ? 1.0 : -1.0;
    }
}
