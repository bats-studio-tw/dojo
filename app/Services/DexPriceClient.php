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
     * @param bool $forceFresh 是否强制刷新缓存
     * @return array [symbol => priceUsd]
     */
    public function batchPrice(array $symbols, bool $forceFresh = false): array
    {
        $symbols = array_unique(array_map('strtoupper', $symbols));

        // 稳定缓存 key
        sort($symbols);
        $cacheKey = "dex_price_batch:" . md5(json_encode($symbols));

        // 如果强制刷新，先清除缓存
        if ($forceFresh) {
            Cache::forget($cacheKey);
        }

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

        try {
            // 验证输入数据
            if (!is_array($pairs)) {
                Log::error("findBestTokenMatch: pairs 参数不是数组", [
                    'target_symbol' => $targetSymbol,
                    'pairs_type' => gettype($pairs)
                ]);
                return null;
            }

            if (empty($pairs)) {
                Log::warning("findBestTokenMatch: pairs 数组为空", [
                    'target_symbol' => $targetSymbol
                ]);
                return null;
            }

            // 过滤和验证有效的交易对
            $validPairs = [];
            foreach ($pairs as $index => $pair) {
                if (!$this->isValidPair($pair, $index)) {
                    continue;
                }
                $validPairs[] = $pair;
            }

            if (empty($validPairs)) {
                Log::warning("findBestTokenMatch: 没有有效的交易对", [
                    'target_symbol' => $targetSymbol,
                    'total_pairs' => count($pairs),
                    'valid_pairs' => 0
                ]);
                return null;
            }

            // 按流动性降序排序所有交易对，确保优先选择高流动性交易对
            usort($validPairs, function ($a, $b) {
                $liquidityA = floatval($a['liquidity']['usd'] ?? 0);
                $liquidityB = floatval($b['liquidity']['usd'] ?? 0);
                return $liquidityB <=> $liquidityA;
            });

            // 记录排序后的前3个交易对的流动性信息（用于调试）
            if (count($validPairs) > 0) {
                $topPairs = array_slice($validPairs, 0, 3);
                $liquidityInfo = [];
                foreach ($topPairs as $index => $pair) {
                    $liquidityInfo[] = sprintf(
                        "#%d: %s (%.2f USD)",
                        $index + 1,
                        $pair['baseToken']['symbol'] ?? 'Unknown',
                        floatval($pair['liquidity']['usd'] ?? 0)
                    );
                }
                Log::info("Token matching for {$targetSymbol} - Top liquidity pairs: " . implode(', ', $liquidityInfo));
            }

            // 第一优先级：精确匹配代币符号（在已排序的高流动性交易对中查找）
            foreach ($validPairs as $pair) {
                $pairSymbol = strtoupper($pair['baseToken']['symbol'] ?? '');
                if ($pairSymbol === $targetSymbol) {
                    Log::info("findBestTokenMatch: 找到精确匹配", [
                        'target_symbol' => $targetSymbol,
                        'matched_symbol' => $pairSymbol,
                        'liquidity' => $pair['liquidity']['usd'] ?? 0
                    ]);
                    return $pair;
                }
            }

            // 第二优先级：代币名称包含目标符号（在已排序的高流动性交易对中查找）
            foreach ($validPairs as $pair) {
                $tokenName = strtoupper($pair['baseToken']['name'] ?? '');
                if (strpos($tokenName, $targetSymbol) !== false) {
                    Log::info("findBestTokenMatch: 找到名称匹配", [
                        'target_symbol' => $targetSymbol,
                        'matched_name' => $tokenName,
                        'liquidity' => $pair['liquidity']['usd'] ?? 0
                    ]);
                    return $pair;
                }
            }

            // 第三优先级：直接返回流动性最高的交易对（已经是排序后的第一个）
            $bestPair = $validPairs[0];
            Log::info("findBestTokenMatch: 使用最高流动性交易对", [
                'target_symbol' => $targetSymbol,
                'selected_symbol' => $bestPair['baseToken']['symbol'] ?? 'Unknown',
                'liquidity' => $bestPair['liquidity']['usd'] ?? 0
            ]);
            return $bestPair;

        } catch (\Throwable $e) {
            Log::error("findBestTokenMatch: 发生未预期的错误", [
                'target_symbol' => $targetSymbol,
                'pairs_count' => count($pairs),
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * 验证交易对数据是否有效
     * @param mixed $pair 交易对数据
     * @param int $index 索引（用于日志）
     * @return bool
     */
    private function isValidPair($pair, int $index): bool
    {
        try {
            // 检查是否为数组
            if (!is_array($pair)) {
                Log::warning("findBestTokenMatch: 交易对不是数组", [
                    'index' => $index,
                    'type' => gettype($pair)
                ]);
                return false;
            }

            // 检查必需字段
            $requiredFields = ['baseToken', 'priceUsd', 'liquidity'];
            foreach ($requiredFields as $field) {
                if (!isset($pair[$field])) {
                    Log::warning("findBestTokenMatch: 交易对缺少必需字段", [
                        'index' => $index,
                        'missing_field' => $field,
                        'available_fields' => array_keys($pair)
                    ]);
                    return false;
                }
            }

            // 检查 baseToken 是否为数组
            if (!is_array($pair['baseToken'])) {
                Log::warning("findBestTokenMatch: baseToken 不是数组", [
                    'index' => $index,
                    'baseToken_type' => gettype($pair['baseToken'])
                ]);
                return false;
            }

            // 检查 liquidity 是否为数组
            if (!is_array($pair['liquidity'])) {
                Log::warning("findBestTokenMatch: liquidity 不是数组", [
                    'index' => $index,
                    'liquidity_type' => gettype($pair['liquidity'])
                ]);
                return false;
            }

            // 检查价格是否有效
            $price = floatval($pair['priceUsd'] ?? 0);
            if ($price <= 0) {
                Log::warning("findBestTokenMatch: 价格无效", [
                    'index' => $index,
                    'price' => $pair['priceUsd'],
                    'symbol' => $pair['baseToken']['symbol'] ?? 'Unknown'
                ]);
                return false;
            }

            // 检查流动性是否有效
            $liquidity = floatval($pair['liquidity']['usd'] ?? 0);
            if ($liquidity <= 0) {
                Log::warning("findBestTokenMatch: 流动性无效", [
                    'index' => $index,
                    'liquidity' => $pair['liquidity']['usd'],
                    'symbol' => $pair['baseToken']['symbol'] ?? 'Unknown'
                ]);
                return false;
            }

            return true;

        } catch (\Throwable $e) {
            Log::error("findBestTokenMatch: 验证交易对时发生错误", [
                'index' => $index,
                'error_message' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 选择流动性最高的交易对（已优化，现在直接在 findBestTokenMatch 中处理）
     * @param array $pairs 交易对数组
     * @return array|null
     */
    private function selectHighestLiquidityPair(array $pairs): ?array
    {
        if (empty($pairs)) {
            return null;
        }

        // 按流动性降序排序
        usort($pairs, function ($a, $b) {
            $liquidityA = floatval($a['liquidity']['usd'] ?? 0);
            $liquidityB = floatval($b['liquidity']['usd'] ?? 0);
            return $liquidityB <=> $liquidityA;
        });

        return $pairs[0];
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
