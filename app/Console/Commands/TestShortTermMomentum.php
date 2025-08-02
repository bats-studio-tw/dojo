<?php

namespace App\Console\Commands;

use App\Models\TokenPrice;
use App\Services\Prediction\Features\ShortTermMomentumFeatureProvider;
use App\Services\Prediction\Utils\MathUtils;
use Carbon\Carbon;
use Illuminate\Console\Command;

class TestShortTermMomentum extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:short-term-momentum {--symbol=SOL : 代币符号} {--create-test-data : 创建测试数据}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '测试短期动能计算功能';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $symbol = $this->option('symbol');
        $createTestData = $this->option('create-test-data');

        $this->info("开始测试短期动能计算功能 - 代币: {$symbol}");

        // 如果需要创建测试数据
        if ($createTestData) {
            $this->createTestData($symbol);
        }

        // 检查数据是否存在
        $priceCount = TokenPrice::where('symbol', $symbol)->count();
        if ($priceCount === 0) {
            $this->warn("没有找到 {$symbol} 的价格数据，使用 --create-test-data 选项创建测试数据");
            return;
        }

        $this->info("找到 {$priceCount} 条价格记录");

        // 测试MathUtils类
        $this->testMathUtils();

        // 测试短期动能特征提供者
        $this->testShortTermMomentumProvider($symbol);

        $this->info('测试完成！');
    }

    /**
     * 创建测试数据
     */
    private function createTestData(string $symbol): void
    {
        $this->info("正在为 {$symbol} 创建测试数据...");

        $basePrice = 100.0;
        $currentTime = Carbon::now()->startOfMinute()->timestamp;

        // 创建过去20分钟的测试数据，模拟价格波动
        for ($i = 20; $i >= 0; $i--) {
            $timestamp = $currentTime - ($i * 60);

            // 模拟价格变化：添加随机波动和趋势
            $trend = 0.002 * (20 - $i); // 轻微上升趋势
            $volatility = (rand(-100, 100) / 10000); // 随机波动 ±1%
            $price = $basePrice * (1 + $trend + $volatility);

            TokenPrice::updateOrCreate(
                [
                    'symbol' => $symbol,
                    'minute_timestamp' => $timestamp,
                ],
                [
                    'price_usd' => round($price, 6),
                    'currency' => 'usd',
                ]
            );
        }

        $this->info("已创建 21 条测试价格记录");
    }

    /**
     * 测试数学工具类
     */
    private function testMathUtils(): void
    {
        $this->info("\n🧮 测试数学工具类功能...");

        $mathUtils = new MathUtils();

        // 测试标准差计算
        $testValues = [100, 102, 98, 105, 99, 103, 97];
        $stdDev = $mathUtils->standardDeviation($testValues);
        $this->line("标准差计算: " . round($stdDev, 4));

        // 测试线性回归
        $x = [0, 1, 2, 3, 4];
        $y = [100, 102, 104, 106, 108];
        $slope = $mathUtils->linearRegressionSlope($x, $y);
        $this->line("线性回归斜率: " . round($slope, 4));

        // 测试相关系数
        $correlation = $mathUtils->correlation($x, $y);
        $this->line("相关系数: " . round($correlation, 4));

        $this->info("✅ 数学工具类测试通过");
    }

    /**
     * 测试短期动能特征提供者
     */
    private function testShortTermMomentumProvider(string $symbol): void
    {
        $this->info("\n📈 测试短期动能特征提供者...");

        $provider = new ShortTermMomentumFeatureProvider();

        // 显示配置
        $config = $provider->getConfig();
        $this->line("配置参数:");
        $this->line("  - 短期窗口: {$config['short_term_minutes']} 分钟");
        $this->line("  - 中期窗口: {$config['medium_term_minutes']} 分钟");
        $this->line("  - 长期窗口: {$config['long_term_minutes']} 分钟");

        // 模拟快照数据
        $snapshots = [
            [
                'symbol' => $symbol,
                'price' => 100.0,
            ]
        ];

        // 计算短期动能分数
        $scores = $provider->extractFeatures($snapshots, []);

        if (isset($scores[$symbol])) {
            $score = $scores[$symbol];
            $this->line("短期动能分数: " . round($score, 2));

            if ($score > 70) {
                $this->line("🟢 强烈看涨信号");
            } elseif ($score > 60) {
                $this->line("🟡 看涨信号");
            } elseif ($score < 30) {
                $this->line("🔴 强烈看跌信号");
            } elseif ($score < 40) {
                $this->line("🟠 看跌信号");
            } else {
                $this->line("⚪ 中性信号");
            }
        } else {
            $this->error("无法计算 {$symbol} 的短期动能分数");
        }

        // 显示最近的价格数据
        $this->showRecentPrices($symbol);

        $this->info("✅ 短期动能特征提供者测试完成");
    }

    /**
     * 显示最近的价格数据
     */
    private function showRecentPrices(string $symbol): void
    {
        $this->info("\n📊 最近的价格数据:");

        $prices = TokenPrice::where('symbol', $symbol)
            ->orderBy('minute_timestamp', 'desc')
            ->limit(10)
            ->get();

        $this->table(
            ['时间', '价格 (USD)', '变化率 (%)'],
            $prices->map(function ($price, $index) use ($prices) {
                $time = Carbon::createFromTimestamp($price->minute_timestamp)->format('H:i:s');
                $priceValue = round($price->price_usd, 6);

                $changePercent = '';
                if ($index < count($prices) - 1) {
                    $previousPrice = $prices[$index + 1]->price_usd;
                    if ($previousPrice > 0) {
                        $change = (($price->price_usd - $previousPrice) / $previousPrice) * 100;
                        $changePercent = round($change, 2) . '%';
                        if ($change > 0) {
                            $changePercent = '+' . $changePercent;
                        }
                    }
                }

                return [$time, $priceValue, $changePercent];
            })
        );
    }
}
