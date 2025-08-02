<?php

namespace App\Console\Commands;

use App\Models\TokenRating;
use App\Services\EloRatingEngine;
use App\Services\TimeDecayCalculatorService;
use Illuminate\Console\Command;

class TestTimeDecayCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:time-decay {--symbol=BTC : 测试的代币符号} {--show-weights : 显示权重分布} {--compare-elo : 比较Elo概率}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '测试时间衰减的 top3_rate 与 Elo 功能';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🎯 时间衰减功能测试');
        $this->info('===================');

        $symbolToTest = $this->option('symbol');

        // 测试时间衰减计算服务
        $this->testTimeDecayService($symbolToTest);

        // 测试TokenRating模型方法
        $this->testTokenRatingMethods($symbolToTest);

        // 显示权重分布（如果开启）
        if ($this->option('show-weights')) {
            $this->showWeightDistribution();
        }

        // 比较Elo概率（如果开启）
        if ($this->option('compare-elo')) {
            $this->compareEloProbabilities();
        }

        $this->info('✅ 测试完成');
    }

    /**
     * 测试时间衰减计算服务
     */
    private function testTimeDecayService(string $symbol): void
    {
        $this->info("\n📊 测试时间衰减计算服务");
        $this->line("测试代币: {$symbol}");

        $calculator = app(TimeDecayCalculatorService::class);

        // 测试 top3_rate 计算
        $top3Data = $calculator->calculateDecayedTop3Rate($symbol);
        $this->displayTop3Results($top3Data);

        // 测试 Elo 统计计算
        $eloData = $calculator->calculateDecayedEloStats($symbol);
        $this->displayEloResults($eloData);

        // 测试批量计算
        $symbols = [$symbol, 'ETH', 'SOL'];
        $batchData = $calculator->calculateBatchDecayedMetrics($symbols);
        $this->info("\n🎯 批量计算结果:");
        foreach ($batchData as $sym => $data) {
            $this->line("  {$sym}: Top3率={$data['top3_metrics']['decayed_top3_rate']}%, 衰减应用={$data['top3_metrics']['decay_applied']}");
        }
    }

    /**
     * 测试TokenRating模型方法
     */
    private function testTokenRatingMethods(string $symbol): void
    {
        $this->info("\n📈 测试TokenRating模型方法");

        $tokenRating = TokenRating::where('symbol', $symbol)->first();
        if (!$tokenRating) {
            $this->warn("代币 {$symbol} 不存在于数据库中");
            return;
        }

        $allMetrics = $tokenRating->getAllDecayedMetrics();
        $this->line("完整指标数据:");
        $this->line("  当前Elo: {$allMetrics['current_elo']}");
        $this->line("  总游戏数: {$allMetrics['total_games']}");
        $this->line("  衰减应用: " . ($allMetrics['decay_applied'] ? '是' : '否'));
    }

    /**
     * 显示top3计算结果
     */
    private function displayTop3Results(array $data): void
    {
        $this->info("\n🎯 Top3率计算结果:");
        $this->line("  传统Top3率: {$data['top3_rate']}%");
        $this->line("  时间衰减Top3率: {$data['decayed_top3_rate']}%");
        $this->line("  总游戏数: {$data['total_games']}");
        $this->line("  衰减应用: " . ($data['decay_applied'] ? '是' : '否'));

        if ($data['decay_applied']) {
            $improvement = $data['decayed_top3_rate'] - $data['top3_rate'];
            $this->line("  衰减影响: " . ($improvement > 0 ? '+' : '') . round($improvement, 2) . '%');
        }
    }

    /**
     * 显示Elo统计结果
     */
    private function displayEloResults(array $data): void
    {
        $this->info("\n⚡ Elo统计结果:");
        $this->line("  传统胜率: {$data['win_rate']}%");
        $this->line("  时间衰减胜率: {$data['decayed_win_rate']}%");
        $this->line("  传统平均排名: {$data['avg_rank']}");
        $this->line("  时间衰减平均排名: {$data['decayed_avg_rank']}");

        if ($data['decay_applied']) {
            $winRateImprovement = $data['decayed_win_rate'] - $data['win_rate'];
            $rankImprovement = $data['avg_rank'] - $data['decayed_avg_rank'];
            $this->line("  胜率变化: " . ($winRateImprovement > 0 ? '+' : '') . round($winRateImprovement, 2) . '%');
            $this->line("  排名改善: " . ($rankImprovement > 0 ? '+' : '') . round($rankImprovement, 2));
        }
    }

    /**
     * 显示权重分布
     */
    private function showWeightDistribution(): void
    {
        $this->info("\n📊 权重分布分析");

        $calculator = app(TimeDecayCalculatorService::class);
        $distribution = $calculator->getWeightDistribution(100, 0.97);

        $this->line("前10局游戏权重占比: " . round($distribution['recent_10_percent'], 2) . '%');
        $this->line("前50局游戏权重占比: " . round($distribution['recent_50_percent'], 2) . '%');
        $this->line("累计权重: " . round($distribution['cumulative_weight'], 2));

        $this->info("\n前10局权重详情:");
        for ($i = 0; $i < 10; $i++) {
            $weight = $distribution['weights'][$i] ?? 0;
            $percent = $distribution['weight_percentages'][$i] ?? 0;
            $this->line("  第" . ($i + 1) . "局: 权重=" . round($weight, 4) . ", 占比=" . round($percent, 2) . '%');
        }
    }

    /**
     * 比较Elo概率
     */
    private function compareEloProbabilities(): void
    {
        $this->info("\n⚔️  Elo概率比较");

        $symbols = ['BTC', 'ETH', 'SOL', 'DOGE'];
        $eloEngine = app(EloRatingEngine::class);

        // 传统Elo概率
        $traditionalProbs = $eloEngine->probabilities($symbols, false);
        $this->info("传统Elo概率:");
        foreach ($traditionalProbs as $symbol => $prob) {
            $this->line("  {$symbol}: " . round($prob * 100, 2) . '%');
        }

        // 时间衰减Elo概率
        $decayedProbs = $eloEngine->probabilities($symbols, true);
        $this->info("\n时间衰减Elo概率:");
        foreach ($decayedProbs as $symbol => $prob) {
            $this->line("  {$symbol}: " . round($prob * 100, 2) . '%');
        }

        // 比较差异
        $this->info("\n概率变化:");
        foreach ($symbols as $symbol) {
            $traditional = ($traditionalProbs[$symbol] ?? 0) * 100;
            $decayed = ($decayedProbs[$symbol] ?? 0) * 100;
            $change = $decayed - $traditional;
            $this->line("  {$symbol}: " . ($change > 0 ? '+' : '') . round($change, 2) . '%');
        }
    }
}
