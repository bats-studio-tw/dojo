<?php

namespace App\Console\Commands;

use App\Repositories\TokenPriceRepository;
use Illuminate\Console\Command;

class TestTokenPricePerformance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:token-price-performance {--iterations=10 : 测试迭代次数}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '测试TokenPriceRepository的性能，对比单个查询和批量查询的速度';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $symbols = ['BERA', 'BNB', 'BONK', 'BTC', 'CHILLGUY'];
        $iterations = (int) $this->option('iterations');

        $this->info("开始测试TokenPriceRepository性能...");
        $this->info("测试代币: " . implode(', ', $symbols));
        $this->info("迭代次数: {$iterations}");
        $this->newLine();

        $repository = new TokenPriceRepository();

        // 测试单个查询性能
        $this->testIndividualQueries($repository, $symbols, $iterations);

        $this->newLine();

        // 测试批量查询性能
        $this->testBatchQueries($repository, $symbols, $iterations);

        $this->newLine();
        $this->info("性能测试完成！");
    }

    /**
     * 测试单个查询性能
     */
    private function testIndividualQueries(TokenPriceRepository $repository, array $symbols, int $iterations)
    {
        $this->info("🔍 测试单个查询性能...");

        $totalTime = 0;
        $totalQueries = 0;

        for ($i = 0; $i < $iterations; $i++) {
            $startTime = microtime(true);

            foreach ($symbols as $symbol) {
                $result = $repository->getLatestPricesForToken($symbol, 2);
                $totalQueries++;
            }

            $endTime = microtime(true);
            $executionTime = ($endTime - $startTime) * 1000; // 转换为毫秒
            $totalTime += $executionTime;

            $this->line("第" . ($i + 1) . "次迭代: " . number_format($executionTime, 2) . "ms");
        }

        $avgTime = $totalTime / $iterations;
        $avgTimePerQuery = $totalTime / $totalQueries;

        $this->info("📊 单个查询性能统计:");
        $this->info("  总执行时间: " . number_format($totalTime, 2) . "ms");
        $this->info("  平均每次迭代: " . number_format($avgTime, 2) . "ms");
        $this->info("  平均每次查询: " . number_format($avgTimePerQuery, 2) . "ms");
        $this->info("  总查询次数: {$totalQueries}");
    }

    /**
     * 测试批量查询性能
     */
    private function testBatchQueries(TokenPriceRepository $repository, array $symbols, int $iterations)
    {
        $this->info("🚀 测试批量查询性能...");

        $totalTime = 0;
        $totalQueries = 0;

        for ($i = 0; $i < $iterations; $i++) {
            $startTime = microtime(true);

            $result = $repository->getLatestPricesForTokens($symbols, 2);
            $totalQueries++;

            $endTime = microtime(true);
            $executionTime = ($endTime - $startTime) * 1000; // 转换为毫秒
            $totalTime += $executionTime;

            $this->line("第" . ($i + 1) . "次迭代: " . number_format($executionTime, 2) . "ms");
        }

        $avgTime = $totalTime / $iterations;
        $avgTimePerQuery = $totalTime / $totalQueries;

        $this->info("📊 批量查询性能统计:");
        $this->info("  总执行时间: " . number_format($totalTime, 2) . "ms");
        $this->info("  平均每次迭代: " . number_format($avgTime, 2) . "ms");
        $this->info("  平均每次查询: " . number_format($avgTimePerQuery, 2) . "ms");
        $this->info("  总查询次数: {$totalQueries}");
    }
}
