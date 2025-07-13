<?php

namespace App\Console\Commands;

use App\Models\GameRound;
use App\Models\RoundResult;
use App\Services\Prediction\BacktestService;
use App\Services\Prediction\PredictionServiceFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestBacktestService extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:backtest
                            {--strategy=conservative : 策略標籤}
                            {--rounds=10 : 測試回合數量}
                            {--async : 是否使用非同步模式}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '測試 BacktestService 功能';

    /**
     * Execute the console command.
     */
    public function handle(BacktestService $backtestService): int
    {
        $this->info('開始測試 BacktestService...');

        try {
            $strategy = $this->option('strategy');
            $roundsLimit = (int) $this->option('rounds');
            $isAsync = $this->option('async');

            $this->info("策略: {$strategy}");
            $this->info("回合數量: {$roundsLimit}");
            $this->info("模式: " . ($isAsync ? '非同步' : '同步'));

            // 獲取歷史回合數據
            $this->info('獲取歷史回合數據...');
            $rounds = $backtestService->getHistoricalRounds($roundsLimit);

            if (empty($rounds)) {
                $this->error('未找到歷史回合數據，請先確保有遊戲數據');
                return 1;
            }

            $this->info("找到 {$roundsLimit} 個回合數據");

            // 構建策略配置
            $strategyConfig = [
                'strategy_tag' => $strategy,
            ];

            if ($isAsync) {
                // 非同步模式
                $this->info('啟動非同步回測...');
                $batchId = $backtestService->startBacktest($rounds, $strategyConfig, 1);
                $this->info("批次ID: {$batchId}");
                $this->info('請使用以下命令查詢進度:');
                $this->line("php artisan queue:work --queue=high");
                $this->line("curl -X POST http://localhost/api/v2/backtest/batch-status -H 'Content-Type: application/json' -d '{\"batch_id\":\"{$batchId}\"}'");
            } else {
                // 同步模式
                $this->info('執行同步回測...');
                $result = $backtestService->runBacktest($rounds, $strategyConfig, 1);

                if ($result['success']) {
                    $this->info('回測完成！');
                    $this->displayMetrics($result['metrics']);
                    $this->displaySummary($result['summary']);
                } else {
                    $this->error('回測失敗: ' . ($result['error'] ?? '未知錯誤'));
                    return 1;
                }
            }

            return 0;

        } catch (\Exception $e) {
            $this->error('測試失敗: ' . $e->getMessage());
            Log::error('BacktestService 測試失敗', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        }
    }

    /**
     * 顯示回測指標
     */
    private function displayMetrics(array $metrics): void
    {
        $this->info('回測指標:');
        $this->table(
            ['指標', '數值'],
            [
                ['勝率', number_format($metrics['win_rate'] * 100, 2) . '%'],
                ['保本率', number_format($metrics['breakeven_rate'] * 100, 2) . '%'],
                ['夏普比率', number_format($metrics['sharpe_ratio'], 4)],
                ['Sortino比率', number_format($metrics['sortino_ratio'], 4)],
                ['Calmar比率', number_format($metrics['calmar_ratio'], 4)],
                ['最大回撤', number_format($metrics['max_drawdown'], 4)],
                ['最大盈利', number_format($metrics['max_profit'], 4)],
                ['最大虧損', number_format($metrics['max_loss'], 4)],
                ['平均盈虧比', number_format($metrics['avg_profit_loss_ratio'], 4)],
                ['總盈利', number_format($metrics['total_profit'], 4)],
                ['盈利因子', number_format($metrics['profit_factor'], 4)],
                ['波動率', number_format($metrics['volatility'], 4)],
                ['連續勝場', $metrics['consecutive_wins']],
                ['連續敗場', $metrics['consecutive_losses']],
            ]
        );
    }

    /**
     * 顯示回測摘要
     */
    private function displaySummary(array $summary): void
    {
        $this->info('回測摘要:');
        $this->table(
            ['項目', '數值'],
            [
                ['總回合數', $summary['total_rounds']],
                ['成功回合數', $summary['successful_rounds']],
                ['策略標籤', $summary['strategy_tag']],
            ]
        );
    }
}
