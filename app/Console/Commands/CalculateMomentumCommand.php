<?php

namespace App\Console\Commands;

use App\Models\TokenPrice;
use App\Models\TokenRating;
use App\Services\DexPriceClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CalculateMomentumCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'momentum:calculate
                            {--realtime : 實時模式，使用最新價格數據}
                            {--symbols=* : 指定代幣符號，不指定則使用所有代幣}
                            {--window=5 : 動能計算窗口（分鐘）}
                            {--cache-ttl=300 : 緩存時間（秒）}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '計算代幣市場動能指標';

    /**
     * Execute the console command.
     */
    public function handle(DexPriceClient $dexPriceClient): int
    {
        $startTime = microtime(true);
        $isRealtime = $this->option('realtime');
        $window = (int) $this->option('window');
        $cacheTtl = (int) $this->option('cache-ttl');

        $this->info('🚀 開始計算市場動能指標...');
        $this->info('模式: '.($isRealtime ? '實時' : '歷史'));
        $this->info("窗口: {$window} 分鐘");
        $this->info("緩存: {$cacheTtl} 秒");

        try {
            // 獲取代幣列表
            $symbols = $this->getSymbols();
            if (empty($symbols)) {
                $this->error('❌ 沒有找到可用的代幣');

                return 1;
            }

            $this->info('📊 處理代幣數量: '.count($symbols));

            // 計算動能指標
            $momentumData = $this->calculateMomentumIndicators($symbols, $window, $isRealtime, $dexPriceClient);

            // 緩存結果
            $this->cacheMomentumData($momentumData, $cacheTtl);

            // 輸出統計
            $this->outputStatistics($momentumData);

            $executionTime = round(microtime(true) - $startTime, 2);
            $this->info("✅ 動能指標計算完成，耗時: {$executionTime} 秒");

            Log::info('市場動能指標計算完成', [
                'symbols_count' => count($symbols),
                'execution_time' => $executionTime,
                'momentum_data_count' => count($momentumData),
                'is_realtime' => $isRealtime,
            ]);

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ 動能指標計算失敗: '.$e->getMessage());
            Log::error('市場動能指標計算失敗', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 1;
        }
    }

    /**
     * 獲取代幣列表
     */
    private function getSymbols(): array
    {
        $specifiedSymbols = $this->option('symbols');

        if (! empty($specifiedSymbols)) {
            return array_map('strtoupper', $specifiedSymbols);
        }

        // 從數據庫獲取所有代幣
        return TokenRating::pluck('symbol')->toArray();
    }

    /**
     * 計算動能指標
     */
    private function calculateMomentumIndicators(array $symbols, int $window, bool $isRealtime, DexPriceClient $dexPriceClient): array
    {
        $momentumData = [];
        $progressBar = $this->output->createProgressBar(count($symbols));
        $progressBar->start();

        foreach ($symbols as $symbol) {
            try {
                $momentum = $this->calculateSymbolMomentum($symbol, $window, $isRealtime, $dexPriceClient);

                if ($momentum !== null) {
                    $momentumData[$symbol] = $momentum;
                }

                $progressBar->advance();

            } catch (\Exception $e) {
                Log::warning("計算 {$symbol} 動能指標失敗", ['error' => $e->getMessage()]);

                continue;
            }
        }

        $progressBar->finish();
        $this->newLine();

        return $momentumData;
    }

    /**
     * 計算單個代幣的動能指標
     */
    private function calculateSymbolMomentum(string $symbol, int $window, bool $isRealtime, DexPriceClient $dexPriceClient): ?array
    {
        $cacheKey = "momentum:{$symbol}:{$window}";

        // 如果不是實時模式，嘗試從緩存獲取
        if (! $isRealtime && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        // 獲取價格數據
        $priceData = $this->getPriceData($symbol, $window, $isRealtime, $dexPriceClient);

        if (empty($priceData) || count($priceData) < 2) {
            return null;
        }

        // 計算動能指標
        $momentum = [
            'symbol' => $symbol,
            'current_price' => end($priceData),
            'price_change_1m' => $this->calculatePriceChange($priceData, 1),
            'price_change_5m' => $this->calculatePriceChange($priceData, 5),
            'price_change_15m' => $this->calculatePriceChange($priceData, 15),
            'volatility' => $this->calculateVolatility($priceData),
            'momentum_score' => $this->calculateMomentumScore($priceData),
            'trend_direction' => $this->calculateTrendDirection($priceData),
            'calculated_at' => now()->toISOString(),
        ];

        // 緩存結果
        Cache::put($cacheKey, $momentum, 300); // 5分鐘緩存

        return $momentum;
    }

    /**
     * 獲取價格數據
     */
    private function getPriceData(string $symbol, int $window, bool $isRealtime, DexPriceClient $dexPriceClient): array
    {
        if ($isRealtime) {
            // 實時模式：從API獲取最新價格
            try {
                $marketData = $dexPriceClient->getTokenMarketData($symbol);
                $currentPrice = (float) ($marketData['price'] ?? 0);

                if ($currentPrice > 0) {
                    return [$currentPrice];
                }
            } catch (\Exception $e) {
                Log::warning("實時獲取 {$symbol} 價格失敗", ['error' => $e->getMessage()]);
            }
        }

        // 從數據庫獲取歷史價格數據
        $endTime = now();
        $startTime = $endTime->copy()->subMinutes($window);

        return TokenPrice::where('symbol', $symbol)
            ->whereBetween('created_at', [$startTime, $endTime])
            ->orderBy('created_at')
            ->pluck('price_usd')
            ->toArray();
    }

    /**
     * 計算價格變化
     */
    private function calculatePriceChange(array $prices, int $minutes): float
    {
        if (count($prices) < 2) {
            return 0.0;
        }

        $currentPrice = end($prices);
        $previousPrice = $prices[max(0, count($prices) - $minutes - 1)] ?? $prices[0];

        if ($previousPrice == 0) {
            return 0.0;
        }

        return (($currentPrice - $previousPrice) / $previousPrice) * 100;
    }

    /**
     * 計算波動率
     */
    private function calculateVolatility(array $prices): float
    {
        if (count($prices) < 2) {
            return 0.0;
        }

        $returns = [];
        for ($i = 1; $i < count($prices); $i++) {
            if ($prices[$i - 1] > 0) {
                $returns[] = ($prices[$i] - $prices[$i - 1]) / $prices[$i - 1];
            }
        }

        if (empty($returns)) {
            return 0.0;
        }

        $mean = array_sum($returns) / count($returns);
        $variance = array_sum(array_map(function ($r) use ($mean) {
            return pow($r - $mean, 2);
        }, $returns)) / count($returns);

        return sqrt($variance) * 100; // 轉換為百分比
    }

    /**
     * 計算動能分數
     */
    private function calculateMomentumScore(array $prices): float
    {
        if (count($prices) < 2) {
            return 0.0;
        }

        $currentPrice = end($prices);
        $startPrice = $prices[0];

        if ($startPrice == 0) {
            return 0.0;
        }

        $priceChange = (($currentPrice - $startPrice) / $startPrice) * 100;

        // 標準化到 0-100 分數
        return max(0, min(100, 50 + $priceChange * 2));
    }

    /**
     * 計算趨勢方向
     */
    private function calculateTrendDirection(array $prices): string
    {
        if (count($prices) < 3) {
            return 'neutral';
        }

        $recentPrices = array_slice($prices, -3);
        $trend = 0;

        for ($i = 1; $i < count($recentPrices); $i++) {
            if ($recentPrices[$i] > $recentPrices[$i - 1]) {
                $trend++;
            } elseif ($recentPrices[$i] < $recentPrices[$i - 1]) {
                $trend--;
            }
        }

        if ($trend > 0) {
            return 'up';
        } elseif ($trend < 0) {
            return 'down';
        } else {
            return 'neutral';
        }
    }

    /**
     * 緩存動能數據
     */
    private function cacheMomentumData(array $momentumData, int $cacheTtl): void
    {
        $cacheKey = 'market_momentum_summary';
        $totalSymbols = count($momentumData);

        $summary = [
            'total_symbols' => $totalSymbols,
            'up_trend_count' => count(array_filter($momentumData, fn ($m) => $m['trend_direction'] === 'up')),
            'down_trend_count' => count(array_filter($momentumData, fn ($m) => $m['trend_direction'] === 'down')),
            'neutral_trend_count' => count(array_filter($momentumData, fn ($m) => $m['trend_direction'] === 'neutral')),
            'avg_momentum_score' => $totalSymbols > 0 ? array_sum(array_column($momentumData, 'momentum_score')) / $totalSymbols : 0,
            'calculated_at' => now()->toISOString(),
            'data' => $momentumData,
        ];

        Cache::put($cacheKey, $summary, $cacheTtl);
    }

    /**
     * 輸出統計信息
     */
    private function outputStatistics(array $momentumData): void
    {
        if (empty($momentumData)) {
            $this->warn('⚠️ 沒有可用的動能數據');

            return;
        }

        $upTrend = count(array_filter($momentumData, fn ($m) => $m['trend_direction'] === 'up'));
        $downTrend = count(array_filter($momentumData, fn ($m) => $m['trend_direction'] === 'down'));
        $neutralTrend = count(array_filter($momentumData, fn ($m) => $m['trend_direction'] === 'neutral'));
        $totalSymbols = count($momentumData);
        $avgScore = $totalSymbols > 0 ? array_sum(array_column($momentumData, 'momentum_score')) / $totalSymbols : 0;

        $this->info('📈 市場趨勢統計:');
        $this->line("  上漲趨勢: {$upTrend} 個代幣");
        $this->line("  下跌趨勢: {$downTrend} 個代幣");
        $this->line("  橫盤整理: {$neutralTrend} 個代幣");
        $this->line('  平均動能分數: '.round($avgScore, 2));

        // 顯示前5個動能最高的代幣
        $topMomentum = collect($momentumData)
            ->sortByDesc('momentum_score')
            ->take(5)
            ->toArray();

        $this->info('🏆 動能排名前5:');
        foreach ($topMomentum as $symbol => $data) {
            $this->line("  {$symbol}: ".round($data['momentum_score'], 2)." 分 ({$data['trend_direction']})");
        }
    }
}
