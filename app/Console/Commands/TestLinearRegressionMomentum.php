<?php

namespace App\Console\Commands;

use App\Repositories\TokenPriceRepository;
use App\Services\EloRatingEngine;
use App\Services\ScoreMixer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestLinearRegressionMomentum extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:linear-regression-momentum {--symbols=BERA,APT,MOVE,IO,PENGU : 测试代币符号，用逗号分隔} {--rounds=5 : 测试轮次数量}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '测试基于线性回归的动能计算算法';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $symbols = explode(',', $this->option('symbols'));
        $rounds = (int) $this->option('rounds');

        $this->info("🚀 开始测试线性回归动能计算算法...");
        $this->info("测试代币: " . implode(', ', $symbols));
        $this->info("测试轮次: {$rounds}");
        $this->newLine();

        $tokenPriceRepository = new TokenPriceRepository();
        $eloRatingEngine = app(EloRatingEngine::class);
        $scoreMixer = app(ScoreMixer::class);

        for ($round = 1; $round <= $rounds; $round++) {
            $this->info("📊 第 {$round} 轮测试");
            $this->line("=" . str_repeat("=", 50));

            // 测试1：获取历史价格序列
            $this->testHistoricalPriceSequences($tokenPriceRepository, $symbols);

            // 测试2：计算线性回归斜率
            $this->testLinearRegressionSlopes($tokenPriceRepository, $symbols);

            // 测试3：完整的动能分数计算
            $this->testCompleteMomentumCalculation($tokenPriceRepository, $eloRatingEngine, $scoreMixer, $symbols, $round);

            $this->newLine();
        }

        $this->info("✅ 线性回归动能计算算法测试完成！");
    }

    /**
     * 测试历史价格序列获取
     */
    private function testHistoricalPriceSequences(TokenPriceRepository $repository, array $symbols): void
    {
        $this->info("🔍 测试历史价格序列获取...");

        $historicalData = $repository->getHistoricalPriceSequences($symbols, 5);

        foreach ($symbols as $symbol) {
            $prices = $historicalData[strtoupper($symbol)] ?? null;

            if ($prices && $prices->isNotEmpty()) {
                $this->line("  ✓ {$symbol}: {$prices->count()} 个数据点");

                // 显示价格序列
                $priceValues = $prices->pluck('price_usd')->toArray();
                $this->line("    价格序列: [" . implode(', ', array_map(fn($p) => number_format($p, 8), $priceValues)) . "]");
            } else {
                $this->line("  ✗ {$symbol}: 无数据");
            }
        }
    }

    /**
     * 测试线性回归斜率计算
     */
    private function testLinearRegressionSlopes(TokenPriceRepository $repository, array $symbols): void
    {
        $this->info("📈 测试线性回归斜率计算...");

        $historicalData = $repository->getHistoricalPriceSequences($symbols, 5);
        $slopes = [];

        foreach ($symbols as $symbol) {
            $prices = $historicalData[strtoupper($symbol)] ?? null;

            if ($prices && $prices->count() >= 3) {
                $slope = $this->calculateLinearRegressionSlope($prices);
                $slopes[$symbol] = $slope;

                if ($slope !== null) {
                    $this->line("  ✓ {$symbol}: 斜率 = " . number_format($slope, 8));
                } else {
                    $this->line("  ✗ {$symbol}: 斜率计算失败");
                }
            } else {
                $this->line("  ✗ {$symbol}: 数据点不足");
            }
        }

        // 显示斜率排名
        if (!empty($slopes)) {
            arsort($slopes);
            $this->info("📊 斜率排名:");
            $rank = 1;
            foreach ($slopes as $symbol => $slope) {
                $this->line("  {$rank}. {$symbol}: " . number_format($slope, 8));
                $rank++;
            }
        }
    }

    /**
     * 测试完整的动能分数计算
     */
    private function testCompleteMomentumCalculation(
        TokenPriceRepository $repository,
        EloRatingEngine $eloEngine,
        ScoreMixer $scoreMixer,
        array $symbols,
        int $round
    ): void {
        $this->info("🎯 测试完整动能分数计算...");

        // 获取历史价格序列
        $historicalData = $repository->getHistoricalPriceSequences($symbols, 5);

        $trendSlopes = [];
        $validSlopes = 0;

        // 计算趋势斜率
        foreach ($symbols as $symbol) {
            $prices = $historicalData[strtoupper($symbol)] ?? null;

            if ($prices && $prices->count() >= 3) {
                $slope = $this->calculateLinearRegressionSlope($prices);

                if ($slope !== null) {
                    $trendSlopes[$symbol] = $slope;
                    $validSlopes++;
                }
            }
        }

        // 映射到分数
        $momentumScores = $this->mapSlopesToScores($trendSlopes, $symbols);

        $this->info("📊 动能分数结果:");
        foreach ($momentumScores as $symbol => $score) {
            $this->line("  {$symbol}: {$score} 分");
        }

        // 计算Elo概率
        $eloProb = $eloEngine->probabilities($symbols);

        $this->info("📊 Elo概率结果:");
        foreach ($eloProb as $symbol => $prob) {
            $this->line("  {$symbol}: " . number_format($prob * 100, 1) . "%");
        }

        // 混合计算
        $predictions = $scoreMixer->mix($eloProb, $momentumScores);

        $this->info("🎯 混合预测结果:");
        foreach ($predictions as $prediction) {
            $this->line("  #{$prediction['predicted_rank']} {$prediction['symbol']}: " .
                       "最终分数 {$prediction['final_score']}, " .
                       "Elo概率 " . number_format($prediction['elo_prob'], 1) . "%, " .
                       "动能分数 {$prediction['mom_score']}, " .
                       "置信度 {$prediction['confidence']}%");
        }

        // 记录到日志
        Log::info('[TestLinearRegressionMomentum] 测试轮次完成', [
            'round' => $round,
            'symbols' => $symbols,
            'valid_slopes' => $validSlopes,
            'trend_slopes' => $trendSlopes,
            'momentum_scores' => $momentumScores,
            'elo_probabilities' => $eloProb,
            'predictions' => $predictions
        ]);
    }

    /**
     * 计算线性回归斜率
     */
    private function calculateLinearRegressionSlope($prices): ?float
    {
        try {
            $n = $prices->count();
            if ($n < 3) {
                return null;
            }

            $xValues = [];
            $yValues = [];

            foreach ($prices as $index => $price) {
                $xValues[] = $index;
                $yValues[] = (float) $price->price_usd;
            }

            $sumX = array_sum($xValues);
            $sumY = array_sum($yValues);
            $sumXY = 0;
            $sumX2 = 0;

            for ($i = 0; $i < $n; $i++) {
                $sumXY += $xValues[$i] * $yValues[$i];
                $sumX2 += $xValues[$i] * $xValues[$i];
            }

            $denominator = ($n * $sumX2) - ($sumX * $sumX);

            if (abs($denominator) < 1e-10) {
                return null;
            }

            $slope = (($n * $sumXY) - ($sumX * $sumY)) / $denominator;

            return $slope;

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 将斜率映射到0-100的分数范围
     */
    private function mapSlopesToScores(array $trendSlopes, array $symbols): array
    {
        if (empty($trendSlopes)) {
            return [];
        }

        arsort($trendSlopes);

        $sortedSymbols = array_keys($trendSlopes);
        $tokenCount = count($sortedSymbols);
        $momentumScores = [];

        foreach ($sortedSymbols as $index => $symbol) {
            if ($tokenCount > 1) {
                $score = 100 - ($index / ($tokenCount - 1)) * 100;
            } else {
                $score = 50;
            }

            $momentumScores[$symbol] = round($score, 1);
        }

        foreach ($symbols as $symbol) {
            if (!isset($momentumScores[$symbol])) {
                $momentumScores[$symbol] = 50.0; // 默认中性分数
            }
        }

        return $momentumScores;
    }
}
