<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AlchemyPriceService
{
    private const API_BASE_URL = 'https://api.g.alchemy.com/prices/v1';
    private const API_TIMEOUT = 10;
    private const MAX_SYMBOLS_PER_REQUEST = 25; // 每次最多25个代币

    private DexPriceClient $dexPriceClient;

    public function __construct(DexPriceClient $dexPriceClient)
    {
        $this->dexPriceClient = $dexPriceClient;
    }

    /**
     * 获取API密钥
     */
    private function getApiKey(): string
    {
        return env('ALCHEMY_API_KEY');
    }

    /**
     * 批量获取代币价格数据
     * @param array $symbols 代币符号数组
     * @param bool $forceFresh 是否强制刷新缓存（已废弃，保留参数兼容性）
     * @return array [symbol => priceUsd]
     */
    public function batchPrice(array $symbols, bool $forceFresh = false): array
    {
        $symbols = array_unique(array_map('strtoupper', $symbols));

        return $this->fetchBatchPrices($symbols);
    }

    /**
     * 批量获取代币完整价格数据
     * @param array $symbols 代币符号数组
     * @param bool $forceFresh 是否强制刷新缓存（已废弃，保留参数兼容性）
     * @return array [symbol => priceData]
     */
    public function batchPriceData(array $symbols, bool $forceFresh = false): array
    {
        $symbols = array_unique(array_map('strtoupper', $symbols));

        return $this->fetchBatchPriceData($symbols);
    }

    /**
     * 获取单个代币价格
     * @param string $symbol 代币符号
     * @param bool $forceFresh 是否强制刷新缓存（已废弃，保留参数兼容性）
     * @return float
     */
    public function getTokenPrice(string $symbol, bool $forceFresh = false): float
    {
        $symbol = strtoupper($symbol);

        try {
            $priceData = $this->fetchBatchPrices([$symbol]);

            return $priceData[$symbol] ?? 0.0;
        } catch (Exception $e) {
            Log::error("获取{$symbol}价格失败", ['error' => $e->getMessage()]);

            return 0.0;
        }
    }

    /**
     * 获取单个代币完整价格数据
     * @param string $symbol 代币符号
     * @param bool $forceFresh 是否强制刷新缓存（已废弃，保留参数兼容性）
     * @return array
     */
    public function getTokenPriceData(string $symbol, bool $forceFresh = false): array
    {
        $symbol = strtoupper($symbol);

        try {
            $priceData = $this->fetchBatchPriceData([$symbol]);

            return $priceData[$symbol] ?? $this->getDefaultPriceData($symbol);
        } catch (Exception $e) {
            Log::error("获取{$symbol}价格数据失败", ['error' => $e->getMessage()]);

            return $this->getDefaultPriceData($symbol);
        }
    }

    /**
     * 批量获取价格数据（内部方法）
     * @param array $symbols 代币符号数组
     * @return array [symbol => priceUsd]
     */
    private function fetchBatchPrices(array $symbols): array
    {
        $priceData = [];
        $chunks = array_chunk($symbols, self::MAX_SYMBOLS_PER_REQUEST);

        foreach ($chunks as $chunk) {
            try {
                $response = $this->makeApiRequest($chunk);
                $data = $response['data'] ?? [];

                foreach ($data as $token) {
                    $symbol = strtoupper($token['symbol'] ?? '');
                    $prices = $token['prices'] ?? [];

                    if (! empty($prices) && isset($prices[0]['value'])) {
                        $priceData[$symbol] = (float) $prices[0]['value'];
                    } else {
                        // Alchemy API没有价格数据，尝试使用DexScreener
                        Log::info("Alchemy API未找到{$symbol}价格，尝试使用DexScreener", [
                            'symbol' => $symbol,
                            'error' => $token['error'] ?? null,
                        ]);

                        $dexPrice = $this->getDexPrice($symbol);
                        $priceData[$symbol] = $dexPrice;
                    }
                }
            } catch (Exception $e) {
                Log::error("批量获取价格失败", [
                    'symbols' => $chunk,
                    'error' => $e->getMessage(),
                ]);

                // 为失败的代币尝试使用DexScreener
                foreach ($chunk as $symbol) {
                    $priceData[$symbol] = $this->getDexPrice($symbol);
                }
            }
        }

        return $priceData;
    }

    /**
     * 批量获取完整价格数据（内部方法）
     * @param array $symbols 代币符号数组
     * @return array [symbol => priceData]
     */
    private function fetchBatchPriceData(array $symbols): array
    {
        $priceData = [];
        $chunks = array_chunk($symbols, self::MAX_SYMBOLS_PER_REQUEST);

        foreach ($chunks as $chunk) {
            try {
                $response = $this->makeApiRequest($chunk);
                $data = $response['data'] ?? [];

                foreach ($data as $token) {
                    $symbol = strtoupper($token['symbol'] ?? '');
                    $prices = $token['prices'] ?? [];

                    if (! empty($prices) && isset($prices[0])) {
                        $priceInfo = $prices[0];
                        $priceData[$symbol] = [
                            'symbol' => $symbol,
                            'price_usd' => (float) ($priceInfo['value'] ?? 0),
                            'currency' => $priceInfo['currency'] ?? 'usd',
                            'timestamp' => time(),
                            'source' => 'alchemy',
                        ];
                    } else {
                        // Alchemy API没有价格数据，尝试使用DexScreener
                        Log::info("Alchemy API未找到{$symbol}价格数据，尝试使用DexScreener", [
                            'symbol' => $symbol,
                            'error' => $token['error'] ?? null,
                        ]);

                        $priceData[$symbol] = $this->getDexPriceData($symbol);
                    }
                }
            } catch (Exception $e) {
                Log::error("批量获取价格数据失败", [
                    'symbols' => $chunk,
                    'error' => $e->getMessage(),
                ]);

                // 为失败的代币尝试使用DexScreener
                foreach ($chunk as $symbol) {
                    $priceData[$symbol] = $this->getDexPriceData($symbol);
                }
            }
        }

        return $priceData;
    }

    /**
     * 使用DexScreener获取代币价格
     * @param string $symbol 代币符号
     * @return float
     */
    private function getDexPrice(string $symbol): float
    {
        try {
            $marketData = $this->dexPriceClient->getTokenMarketData($symbol);
            $price = (float) ($marketData['price'] ?? 0);

            Log::info("使用DexScreener获取{$symbol}价格成功", [
                'symbol' => $symbol,
                'price' => $price,
            ]);

            return $price;
        } catch (Exception $e) {
            Log::warning("DexScreener获取{$symbol}价格失败", [
                'symbol' => $symbol,
                'error' => $e->getMessage(),
            ]);

            return 0.0;
        }
    }

    /**
     * 使用DexScreener获取代币完整价格数据
     * @param string $symbol 代币符号
     * @return array
     */
    private function getDexPriceData(string $symbol): array
    {
        try {
            $marketData = $this->dexPriceClient->getTokenMarketData($symbol);

            $priceData = [
                'symbol' => strtoupper($symbol),
                'price_usd' => (float) ($marketData['price'] ?? 0),
                'currency' => 'usd',
                'timestamp' => time(),
                'source' => 'dexscreener',
                'change_5m' => $marketData['change_5m'] ?? null,
                'change_1h' => $marketData['change_1h'] ?? null,
                'change_4h' => $marketData['change_4h'] ?? null,
                'change_24h' => $marketData['change_24h'] ?? null,
                'volume_24h' => $marketData['volume_24h'] ?? null,
                'market_cap' => $marketData['market_cap'] ?? null,
                'liquidity' => $marketData['liquidity'] ?? null,
            ];

            Log::info("使用DexScreener获取{$symbol}价格数据成功", [
                'symbol' => $symbol,
                'price' => $priceData['price_usd'],
            ]);

            return $priceData;
        } catch (Exception $e) {
            Log::warning("DexScreener获取{$symbol}价格数据失败", [
                'symbol' => $symbol,
                'error' => $e->getMessage(),
            ]);

            return $this->getDefaultPriceData($symbol);
        }
    }

    /**
     * 发送API请求
     * @param array $symbols 代币符号数组
     * @return array
     * @throws Exception
     */
    private function makeApiRequest(array $symbols): array
    {
        $url = $this->buildApiUrl($symbols);

        $response = Http::timeout(self::API_TIMEOUT)->get($url);

        if (! $response->successful()) {
            throw new Exception("Alchemy API请求失败: HTTP " . $response->status());
        }

        $data = $response->json();

        if (! isset($data['data']) || ! is_array($data['data'])) {
            throw new Exception("Alchemy API返回数据格式错误");
        }

        return $data;
    }

    /**
     * 构建API URL
     * @param array $symbols 代币符号数组
     * @return string
     */
    private function buildApiUrl(array $symbols): string
    {
        $symbols = array_map('strtoupper', $symbols);
        $symbolParams = [];

        foreach ($symbols as $symbol) {
            $symbolParams[] = "symbols=" . urlencode($symbol);
        }

        return self::API_BASE_URL . '/' . $this->getApiKey() . '/tokens/by-symbol?' . implode('&', $symbolParams);
    }

    /**
     * 获取默认价格数据
     * @param string $symbol 代币符号
     * @return array
     */
    private function getDefaultPriceData(string $symbol): array
    {
        return [
            'symbol' => strtoupper($symbol),
            'price_usd' => 0.0,
            'currency' => 'usd',
            '' => null,
            'timestamp' => time(),
            'source' => 'fallback',
            'error' => true,
        ];
    }
}
