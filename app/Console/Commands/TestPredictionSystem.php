<?php

namespace App\Console\Commands;

use App\Services\Prediction\BacktestService;
use App\Services\Prediction\PredictionServiceFactory;
use Illuminate\Console\Command;

class TestPredictionSystem extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:prediction-system {--strategy=conservative} {--symbols=ETH,DOGE,SOL}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '测试新一代预测系统';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $strategy = $this->option('strategy');
        $symbols = explode(',', $this->option('symbols'));

        $this->info("开始测试预测系统...");
        $this->info("策略: {$strategy}");
        $this->info("代币: " . implode(', ', $symbols));

        try {
            // 1. 测试策略列表
            $this->info("\n1. 测试获取策略列表...");
            $strategies = PredictionServiceFactory::getAvailableStrategies();
            $this->info("可用策略: " . implode(', ', $strategies));

            // 2. 测试预测服务创建
            $this->info("\n2. 测试创建预测服务...");
            $predictionService = PredictionServiceFactory::create($strategy);
            $this->info("预测服务创建成功");

            // 3. 测试单次预测
            $this->info("\n3. 测试单次预测...");
            $result = $predictionService->predict(
                $symbols,
                time(),
                [], // 历史数据
                999 // 测试游戏回合ID
            );

            if (! empty($result)) {
                $this->info("预测成功，结果:");
                foreach ($result as $item) {
                    $this->line("  {$item['rank']}. {$item['symbol']} - 分数: {$item['score']}");
                }
            } else {
                $this->warn("预测结果为空");
            }

            // 4. 测试回测服务
            $this->info("\n4. 测试回测服务...");
            $backtestService = new BacktestService($predictionService);

            // 创建模拟历史数据
            $mockRounds = [];
            for ($i = 1; $i <= 5; $i++) {
                $mockRounds[] = [
                    'id' => $i,
                    'symbols' => $symbols,
                    'timestamp' => time() - ($i * 3600),
                    'history' => [],
                ];
            }

            $backtestReport = $backtestService->runBacktest($mockRounds, [
                'strategy' => $strategy,
            ]);

            $this->info("回测完成，指标:");
            $this->line("  胜率: " . round($backtestReport['win_rate'] * 100, 2) . "%");
            $this->line("  盈亏率: " . round($backtestReport['profit_rate'], 4));
            $this->line("  夏普比率: " . round($backtestReport['sharpe_ratio'], 4));
            $this->line("  最大回撤: " . round($backtestReport['max_drawdown'], 4));
            $this->line("  总回合数: " . $backtestReport['total_rounds']);

            // 5. 测试数据库记录
            $this->info("\n5. 检查数据库记录...");
            $predictionResults = \App\Models\PredictionResult::where('game_round_id', 999)->get();
            $this->info("数据库记录数: " . $predictionResults->count());

            if ($predictionResults->count() > 0) {
                $this->info("最新记录:");
                $latest = $predictionResults->first();
                $this->line("  代币: {$latest->token}");
                $this->line("  排名: {$latest->predict_rank}");
                $this->line("  分数: {$latest->predict_score}");
                $this->line("  策略: {$latest->strategy_tag}");
            }

            $this->info("\n✅ 预测系统测试完成！");

        } catch (\Exception $e) {
            $this->error("测试失败: " . $e->getMessage());
            $this->error("错误详情: " . $e->getTraceAsString());

            return 1;
        }

        return 0;
    }
}
