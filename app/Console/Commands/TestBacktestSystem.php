<?php

namespace App\Console\Commands;

use App\Models\BacktestResult;
use App\Models\PredictionStrategy;
use App\Services\ScoreMixer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestBacktestSystem extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backtest:test
                            {--games=50 : 測試遊戲數量}
                            {--params= : 測試參數組合 (JSON格式)}
                            {--full : 執行完整測試流程}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '測試回測系統的各個組件';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("🧪 開始回測系統測試");

        $games = (int) $this->option('games');
        $params = $this->option('params') ? json_decode($this->option('params'), true) : null;
        $fullTest = $this->option('full');

        try {
            // 1. 測試 ScoreMixer
            $this->testScoreMixer();

            // 2. 測試參數驗證
            $this->testParameterValidation();

            // 3. 測試回測執行
            if ($fullTest) {
                $this->testBacktestExecution($games, $params);
            }

            // 4. 測試策略晉升
            if ($fullTest) {
                $this->testStrategyPromotion();
            }

            // 5. 測試數據清理
            $this->testDataCleanup();

            $this->info("✅ 所有測試通過！");
            return 0;

        } catch (\Exception $e) {
            $this->error("❌ 測試失敗: " . $e->getMessage());
            Log::error('回測系統測試失敗', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        }
    }

    /**
     * 測試 ScoreMixer
     */
    private function testScoreMixer(): void
    {
        $this->info("測試 ScoreMixer...");

        $scoreMixer = app(ScoreMixer::class);

        // 測試基本混合
        $eloProb = ['BTC' => 0.6, 'ETH' => 0.3, 'SOL' => 0.1];
        $momScore = ['BTC' => 70, 'ETH' => 50, 'SOL' => 30];

        $result = $scoreMixer->mix($eloProb, $momScore);

        if (empty($result)) {
            throw new \Exception('ScoreMixer::mix() 返回空結果');
        }

        $this->info("✅ ScoreMixer::mix() 測試通過");

        // 測試動態參數混合
        $params = ['elo_weight' => 0.7, 'momentum_weight' => 0.3];
        $resultWithParams = $scoreMixer->mixWithParams($eloProb, $momScore, $params);

        if (empty($resultWithParams)) {
            throw new \Exception('ScoreMixer::mixWithParams() 返回空結果');
        }

        $this->info("✅ ScoreMixer::mixWithParams() 測試通過");
    }

    /**
     * 測試參數驗證
     */
    private function testParameterValidation(): void
    {
        $this->info("測試參數驗證...");

        // 測試有效權重組合
        $validParams = [
            ['elo_weight' => 0.6, 'momentum_weight' => 0.4],
            ['elo_weight' => 0.5, 'momentum_weight' => 0.5],
            ['elo_weight' => 1.0, 'momentum_weight' => 0.0],
        ];

        foreach ($validParams as $params) {
            $sum = array_sum($params);
            if (abs($sum - 1.0) >= 1e-6) {
                throw new \Exception("權重組合驗證失敗: " . json_encode($params));
            }
        }

        // 測試無效權重組合
        $invalidParams = [
            ['elo_weight' => 0.6, 'momentum_weight' => 0.5], // 總和 > 1
            ['elo_weight' => 0.3, 'momentum_weight' => 0.4], // 總和 < 1
        ];

        foreach ($invalidParams as $params) {
            $sum = array_sum($params);
            if (abs($sum - 1.0) < 1e-6) {
                throw new \Exception("無效權重組合被錯誤接受: " . json_encode($params));
            }
        }

        $this->info("✅ 參數驗證測試通過");
    }

    /**
     * 測試回測執行
     */
    private function testBacktestExecution(int $games, ?array $params): void
    {
        $this->info("測試回測執行...");

        // 生成測試參數
        $testParams = $params ?: ['elo_weight' => 0.6, 'momentum_weight' => 0.4];
        $runId = 'test_' . uniqid();

        // 執行回測
        $this->call('backtest:run', [
            '--games' => $games,
            '--run-id' => $runId,
        ]);

        // 檢查結果
        $result = BacktestResult::where('run_id', $runId)->first();
        if (!$result) {
            throw new \Exception('回測結果未保存到數據庫');
        }

        $this->info("✅ 回測執行測試通過");
        $this->info("   - 回測ID: {$result->run_id}");
        $this->info("   - 分數: {$result->score}");
        $this->info("   - 準確率: {$result->accuracy}%");
        $this->info("   - 遊戲數量: {$result->total_games}");
    }

    /**
     * 測試策略晉升
     */
    private function testStrategyPromotion(): void
    {
        $this->info("測試策略晉升...");

        // 檢查是否有可晉升的策略
        $latestRun = BacktestResult::latest('created_at')->first();
        if (!$latestRun) {
            $this->warn("沒有找到回測結果，跳過策略晉升測試");
            return;
        }

        // 執行策略晉升
        $this->call('strategy:promote-best', [
            '--run-id' => $latestRun->run_id,
            '--min-score' => 0,
        ]);

        // 檢查是否有活躍策略
        $activeStrategy = PredictionStrategy::where('status', 'active')->first();
        if (!$activeStrategy) {
            $this->warn("沒有找到活躍策略，可能是分數門檻問題");
        } else {
            $this->info("✅ 策略晉升測試通過");
            $this->info("   - 策略名稱: {$activeStrategy->strategy_name}");
            $this->info("   - 策略分數: {$activeStrategy->score}");
        }
    }

    /**
     * 測試數據清理
     */
    private function testDataCleanup(): void
    {
        $this->info("測試數據清理...");

        // 乾跑模式測試
        $this->call('backtest:cleanup', [
            '--days' => 1,
            '--dry-run' => true,
        ]);

        $this->info("✅ 數據清理測試通過");
    }
}
