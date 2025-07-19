<?php

// app/Console/Commands/BacktestPredictionParameters.php

namespace App\Console\Commands;

use App\Jobs\EvaluateBacktestParameters;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BacktestPredictionParameters extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backtest:run
                            {--games=1000 : 回測遊戲數量}
                            {--queue : 是否使用隊列執行}
                            {--run-id= : 自定義回測執行ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '執行預測參數回測，評估不同參數組合的表現';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $gameCount = (int) $this->option('games');
        $useQueue = $this->option('queue') === true; // 修复布尔选项处理
        $runId = $this->option('run-id') ?: uniqid('backtest_', true);

        $this->info('開始執行預測參數回測');
        $this->info("回測ID: {$runId}");
        $this->info("遊戲數量: {$gameCount}");
        $this->info('使用隊列: '.($useQueue ? '是' : '否'));

        // 獲取參數網格
        $parameterGrid = config('backtest.parameter_grid', []);

        if (empty($parameterGrid)) {
            $this->error('未找到參數網格配置');

            return 1;
        }

        // 生成所有參數組合
        $combinations = $this->generateParameterCombinations($parameterGrid);

        // 過濾有效的權重組合
        $validCombinations = $this->filterValidWeightCombinations($combinations);

        $this->info('生成了 '.count($validCombinations).' 個有效參數組合');

        if (empty($validCombinations)) {
            $this->error('沒有有效的參數組合');

            return 1;
        }

        // 記錄開始時間
        $startTime = microtime(true);
        $jobCount = 0;

        foreach ($validCombinations as $params) {
            $job = new EvaluateBacktestParameters($runId, $params, $gameCount);

            if ($useQueue) {
                dispatch($job->onQueue('backtesting'));
                $this->line('已排程參數組合 #'.(++$jobCount).': '.json_encode($params));
            } else {
                // 使用依賴注入容器解析依賴
                $job->handle(
                    app(\App\Services\ScoreMixer::class),
                    app(\App\Services\EloRatingEngine::class),
                    app(\App\Repositories\TokenPriceRepository::class)
                );
                $this->line('已完成參數組合 #'.(++$jobCount).': '.json_encode($params));
            }
        }

        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);

        if ($useQueue) {
            $this->info('所有回測任務已排程到隊列，預計處理時間: '.count($validCombinations).' 個任務');
        } else {
            $this->info("回測完成，總耗時: {$duration} 秒");
        }

        Log::info('回測參數執行完成', [
            'run_id' => $runId,
            'total_combinations' => count($validCombinations),
            'game_count' => $gameCount,
            'use_queue' => $useQueue,
            'duration_seconds' => $duration,
        ]);

        return 0;
    }

    /**
     * 生成所有參數組合
     */
    private function generateParameterCombinations(array $parameterGrid): array
    {
        $combinations = [[]];

        foreach ($parameterGrid as $param => $values) {
            $temp = [];
            foreach ($combinations as $combination) {
                foreach ($values as $value) {
                    $temp[] = array_merge($combination, [$param => $value]);
                }
            }
            $combinations = $temp;
        }

        return $combinations;
    }

    /**
     * 過濾有效的權重組合
     */
    private function filterValidWeightCombinations(array $combinations): array
    {
        $validCombinations = [];

        // 定義需要檢查的權重字段
        $weightFields = ['elo_weight', 'momentum_weight', 'volume_weight'];

        foreach ($combinations as $params) {
            // 檢查是否有權重字段
            $hasWeights = false;
            $weightSum = 0;

            foreach ($weightFields as $field) {
                if (isset($params[$field])) {
                    $hasWeights = true;
                    $weightSum += $params[$field];
                }
            }

            // 如果有權重字段，檢查權重和是否接近1.0
            if ($hasWeights) {
                if (abs($weightSum - 1.0) < 1e-6) {
                    $validCombinations[] = $params;
                }
            } else {
                // 沒有權重字段的組合直接通過
                $validCombinations[] = $params;
            }
        }

        return $validCombinations;
    }
}
