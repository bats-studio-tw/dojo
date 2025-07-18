<?php
// app/Console/Commands/PromoteBestStrategy.php

namespace App\Console\Commands;

use App\Models\BacktestResult;
use App\Models\PredictionStrategy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PromoteBestStrategy extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'strategy:promote-best
                            {--run-id= : 指定回測執行ID，不指定則使用最新的}
                            {--min-score=0 : 最低分數門檻}
                            {--force : 強制晉升，忽略分數門檻}
                            {--force-quick : 快速晉升模式，降低門檻並跳過重複檢查}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '從回測結果中選出最佳策略並晉升為線上策略';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $runId = $this->option('run-id');
        $minScore = (float) $this->option('min-score');
        $force = $this->option('force');
        $forceQuick = $this->option('force-quick');

        // 快速晉升模式：降低門檻
        if ($forceQuick && $minScore > 40) {
            $minScore = 40;
        }

        $this->info("開始策略晉升流程");
        $this->info("最低分數門檻: {$minScore}");
        $this->info("強制晉升: " . ($force ? '是' : '否'));
        $this->info("快速晉升: " . ($forceQuick ? '是' : '否'));

        try {
            // 獲取要處理的回測執行ID
            $targetRunId = $this->getTargetRunId($runId);
            if (!$targetRunId) {
                $this->error('沒有找到可用的回測結果');
                return 1;
            }

            $this->info("處理回測執行ID: {$targetRunId}");

            // 獲取最佳策略
            $bestStrategy = $this->findBestStrategy($targetRunId, $minScore, $force, $forceQuick);

            if (!$bestStrategy) {
                $this->warn('沒有找到符合條件的策略');
                return 0;
            }

            // 晉升策略
            $promotedStrategy = $this->promoteStrategy($bestStrategy, $targetRunId);

            $this->info("策略晉升成功！");
            $this->info("策略名稱: {$promotedStrategy->strategy_name}");
            $this->info("分數: {$promotedStrategy->score}");
            $this->info("參數: " . json_encode($promotedStrategy->parameters));

            // 清除相關快取
            $this->clearRelatedCaches();

            Log::info('策略晉升完成', [
                'run_id' => $targetRunId,
                'strategy_name' => $promotedStrategy->strategy_name,
                'score' => $promotedStrategy->score,
                'parameters' => $promotedStrategy->parameters,
            ]);

            return 0;

        } catch (\Exception $e) {
            $this->error("策略晉升失敗: " . $e->getMessage());
            Log::error('策略晉升失敗', [
                'run_id' => $runId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        }
    }

    /**
     * 獲取目標回測執行ID
     */
    private function getTargetRunId(?string $specifiedRunId): ?string
    {
        if ($specifiedRunId) {
            // 檢查指定的run_id是否存在
            $exists = BacktestResult::where('run_id', $specifiedRunId)->exists();
            if (!$exists) {
                $this->error("指定的回測執行ID '{$specifiedRunId}' 不存在");
                return null;
            }
            return $specifiedRunId;
        }

        // 獲取最新的回測執行ID
        $latestRun = BacktestResult::select('run_id')
            ->orderBy('created_at', 'desc')
            ->first();

        return $latestRun?->run_id;
    }

    /**
     * 尋找最佳策略
     */
    private function findBestStrategy(string $runId, float $minScore, bool $force, bool $forceQuick = false): ?BacktestResult
    {
        $query = BacktestResult::where('run_id', $runId)
            ->orderBy('score', 'desc');

        if (!$force) {
            $query->where('score', '>=', $minScore);
        }

        $bestResult = $query->first();

        if (!$bestResult) {
            return null;
        }

        // 快速晉升模式跳過重複檢查
        if (!$forceQuick) {
            // 檢查是否已經有相同參數的策略
            $existingStrategy = PredictionStrategy::where('parameters', json_encode($bestResult->parameters))
                ->first();

            if ($existingStrategy) {
                $this->warn("發現相同參數的現有策略: {$existingStrategy->strategy_name}");

                // 如果現有策略分數更高，不進行晉升
                if ($existingStrategy->score >= $bestResult->score) {
                    $this->warn("現有策略分數 ({$existingStrategy->score}) 不低於新策略 ({$bestResult->score})，跳過晉升");
                    return null;
                }
            }
        } else {
            $this->info("快速晉升模式：跳過重複檢查");
        }

        return $bestResult;
    }

    /**
     * 晉升策略
     */
    private function promoteStrategy(BacktestResult $bestResult, string $runId): PredictionStrategy
    {
        // 先將所有現有策略設為非活躍
        PredictionStrategy::where('status', 'active')->update([
            'status' => 'inactive',
            'activated_at' => null,
        ]);

        // 生成策略名稱
        $strategyName = $this->generateStrategyName($bestResult);

        // 使用 updateOrCreate 防止重複插入
        $strategy = PredictionStrategy::updateOrCreate(
            [
                'run_id' => $runId,
                'parameters' => json_encode($bestResult->parameters),
            ],
            [
                'strategy_name' => $strategyName,
                'score' => $bestResult->score,
                'status' => 'active',
                'activated_at' => now(),
            ]
        );

        return $strategy;
    }

    /**
     * 生成策略名稱
     */
    private function generateStrategyName(BacktestResult $result): string
    {
        $params = $result->parameters;
        $score = $result->score;

        // 提取關鍵參數
        $eloWeight = $params['elo_weight'] ?? 0;
        $momentumWeight = $params['momentum_weight'] ?? 0;

        $name = sprintf(
            'Strategy_E%.1f_M%.1f_S%.1f_%s',
            $eloWeight,
            $momentumWeight,
            $score,
            now()->format('Ymd_Hi')
        );

        return $name;
    }

    /**
     * 清除相關快取
     */
    private function clearRelatedCaches(): void
    {
        $cachesToClear = [
            'prediction_strategy_active',
            'prediction_parameters',
            'game_prediction_cache',
        ];

        foreach ($cachesToClear as $cacheKey) {
            Cache::forget($cacheKey);
        }

        $this->info("已清除相關快取");
    }
}
