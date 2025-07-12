<?php

namespace App\Services\Prediction;

use App\Jobs\RunBacktestJob;
use Illuminate\Support\Facades\Bus;

class BacktestService
{
    public function __construct(private PredictionService $service) {}

    /**
     * 主入口：接收配置並將其分派到隊列中執行
     * @return string Job Batch ID，用於前端查詢進度
     */
    public function startBacktest(array $rounds, array $strategyConfig, ?int $userId = null): string
    {
        $batch = Bus::batch([
            new RunBacktestJob($rounds, $strategyConfig, $userId),
        ])->then(function ($batch) {
            // 任務完成後可通知前端
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
        foreach ($paramMatrix['weights'] as $weights) {
            foreach ($paramMatrix['normalization'] as $normalization) {
                $strategyConfig = [
                    'strategy_tag' => 'grid_search_' . uniqid(),
                    'weights' => $weights,
                    'normalization' => $normalization,
                    'param_matrix' => $paramMatrix,
                ];
                $jobs[] = new RunBacktestJob($rounds, $strategyConfig, $userId);
            }
        }
        $batch = Bus::batch($jobs)
            ->name('Grid Search Backtest')
            ->dispatch();
        return $batch->id;
    }
}
