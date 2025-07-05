<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Exception;

class DexPriceClient
{
    private const API_BASE_URL = 'https://api.dexscreener.com/latest/dex/search';
    private const API_TIMEOUT = 10;
    private const API_DELAY_MICROSECONDS = 200000; // 0.2秒延迟
    private const CACHE_DURATION = 1; // 减少到1秒缓存，确保能获取到价格变化

    /**
     * 批量获取代币价格数据
     * @param array $symbols 代币符号数组
     * @return array [symbol => priceUsd]
     */
    public function batchPrice(array $symbols): array
    {
        $symbols = array_unique(array_map('strtoupper', $symbols));

        // 稳定缓存 key
        sort($symbols);
        $cacheKey = "dex_price_batch:" . md5(json_encode($symbols));

        return Cache::remember($cacheKey, self::CACHE_DURATION, function () use ($symbols) {
            $priceData = [];

            foreach ($symbols as $symbol) {
                try {
                    $marketData = $this->getTokenMarketData($symbol);
                    if (!empty($marketData)) {
                        $priceData[$symbol] = (float) ($marketData['price'] ?? 0);
                    }

                    // 延迟避免API限制
                    usleep(self::API_DELAY_MICROSECONDS);
                } catch (Exception $e) {
                    Log::warning("获取{$symbol}价格失败", ['error' => $e->getMessage()]);
                    $priceData[$symbol] = 0;
                }
            }

            return $priceData;
        });
    }

    /**
     * 批量获取代币完整市场数据
     * @param array $symbols 代币符号数组
     * @return array [symbol => marketData]
     */
    public function batchMarketData(array $symbols): array
    {
        $symbols = array_unique(array_map('strtoupper', $symbols));

        // 稳定缓存 key
        sort($symbols);
        $cacheKey = "dex_market_batch:" . md5(json_encode($symbols));

        return Cache::remember($cacheKey, self::CACHE_DURATION, function () use ($symbols) {
            $marketData = [];

            foreach ($symbols as $symbol) {
                try {
                    $data = $this->getTokenMarketData($symbol);
                    $marketData[$symbol] = $data;

                    // 延迟避免API限制
                    usleep(self::API_DELAY_MICROSECONDS);
                } catch (Exception $e) {
                    Log::warning("获取{$symbol}市场数据失败", ['error' => $e->getMessage()]);
                    $marketData[$symbol] = $this->getDefaultMarketData($symbol);
                }
            }

            return $marketData;
        });
    }

    /**
     * 获取单个代币的完整市场数据
     * @param string $symbol 代币符号
     * @return array
     */
    public function getTokenMarketData(string $symbol): array
    {
        try {
            $response = Http::timeout(self::API_TIMEOUT)->get(self::API_BASE_URL, [
                'q' => $symbol
            ]);

            if (!$response->successful()) {
                throw new Exception("DexScreener API returned error: " . $response->status());
            }

            $data = $response->json();

            // 确保返回的数据结构符合预期
            if (!isset($data['pairs']) || !is_array($data['pairs']) || empty($data['pairs'])) {
                throw new Exception("Invalid response format from DexScreener API or no pairs found.");
            }

            // 使用智能匹配找到最适合的代币
            $bestMatch = $this->findBestTokenMatch($data['pairs'], $symbol);

            if (!$bestMatch) {
                throw new Exception("No suitable token match found for {$symbol}");
            }

            return [
                'symbol' => strtoupper($symbol),
                'name' => $bestMatch['baseToken']['name'] ?? $symbol,
                'price' => $bestMatch['priceUsd'] ?? '0',
                'change_5m' => $bestMatch['priceChange']['m5'] ?? null,
                'change_1h' => $bestMatch['priceChange']['h1'] ?? null,
                'change_4h' => $bestMatch['priceChange']['h4'] ?? null,
                'change_24h' => $bestMatch['priceChange']['h24'] ?? null,
                'volume_24h' => $bestMatch['volume']['h24'] ?? '0',
                'market_cap' => $bestMatch['marketCap'] ?? null,
                'logo' => $bestMatch['baseToken']['logoURI'] ?? null,
                'liquidity' => $bestMatch['liquidity']['usd'] ?? null,
                'fdv' => $bestMatch['fdv'] ?? null,
            ];

        } catch (Exception $e) {
            Log::error("Error fetching market data from DexScreener for {$symbol}: " . $e->getMessage());
            return $this->getDefaultMarketData($symbol);
        }
    }

    /**
     * 智能匹配最适合的代币交易对
     * @param array $pairs 交易对数组
     * @param string $targetSymbol 目标代币符号
     * @return array|null
     */
    private function findBestTokenMatch(array $pairs, string $targetSymbol): ?array
    {
        $targetSymbol = strtoupper($targetSymbol);

        // 第一优先级：精确匹配代币符号
        foreach ($pairs as $pair) {
            if (strtoupper($pair['baseToken']['symbol'] ?? '') === $targetSymbol) {
                return $pair;
            }
        }

        // 第二优先级：代币名称包含目标符号
        foreach ($pairs as $pair) {
            $tokenName = strtoupper($pair['baseToken']['name'] ?? '');
            if (strpos($tokenName, $targetSymbol) !== false) {
                return $pair;
            }
        }

        // 第三优先级：选择流动性最高的交易对
        return $this->selectHighestLiquidityPair($pairs);
    }

    /**
     * 选择流动性最高的交易对
     * @param array $pairs 交易对数组
     * @return array|null
     */
    private function selectHighestLiquidityPair(array $pairs): ?array
    {
        if (empty($pairs)) {
            return null;
        }

        $bestPair = null;
        $highestLiquidity = 0;

        foreach ($pairs as $pair) {
            $liquidity = floatval($pair['liquidity']['usd'] ?? 0);
            if ($liquidity > $highestLiquidity) {
                $highestLiquidity = $liquidity;
                $bestPair = $pair;
            }
        }

        return $bestPair ?: $pairs[0]; // 如果没有流动性数据，返回第一个
    }

    /**
     * 获取默认市场数据（API失败时的备用数据）
     * @param string $symbol 代币符号
     * @return array
     */
    private function getDefaultMarketData(string $symbol): array
    {
        return [
            'symbol' => strtoupper($symbol),
            'name' => $symbol,
            'price' => '0',
            'change_5m' => null,
            'change_1h' => null,
            'change_4h' => null,
            'change_24h' => null,
            'volume_24h' => '0',
            'market_cap' => null,
            'logo' => null,
            'liquidity' => null,
            'fdv' => null,
        ];
    }

    /**
     * 获取单个代币的价格变化数据
     * @param string $symbol 代币符号
     * @return array
     */
    public function getTokenPriceChanges(string $symbol): array
    {
        $marketData = $this->getTokenMarketData($symbol);

        return [
            'symbol' => $marketData['symbol'],
            'change_5m' => $marketData['change_5m'],
            'change_1h' => $marketData['change_1h'],
            'change_4h' => $marketData['change_4h'],
            'change_24h' => $marketData['change_24h'],
        ];
    }

    /**
     * 检查API服务状态
     * @return bool
     */
    public function checkApiStatus(): bool
    {
        try {
            $response = Http::timeout(5)->get(self::API_BASE_URL, ['q' => 'BTC']);
            return $response->successful();
        } catch (Exception $e) {
            Log::error("DexScreener API status check failed: " . $e->getMessage());
            return false;
        }
    }
}
