<?php

namespace App\Services\Prediction\Providers;

use App\Contracts\Prediction\MarketDataProviderInterface;
use App\Models\TokenPrice;
use App\Services\DexPriceClient;

class DexScreenerProvider implements MarketDataProviderInterface
{
    public function __construct(
        private DexPriceClient $dexPriceClient
    ) {}

    public function fetchSnapshots(array $symbols, int $timestamp): array
    {
        $snapshots = [];

        foreach ($symbols as $symbol) {
            try {
                $marketData = $this->dexPriceClient->getTokenMarketData($symbol);

                if ($marketData) {
                    $snapshots[] = new TokenPrice([
                        'symbol' => $symbol,
                        'price' => $marketData['price'] ?? 0,
                        'volume_24h' => $marketData['volume_24h'] ?? 0,
                        'price_change_24h' => $marketData['change_24h'] ?? 0,
                        'timestamp' => $timestamp,
                    ]);
                }
            } catch (\Exception $e) {
                // 记录错误但继续处理其他代币
                \Log::warning("Failed to fetch price for {$symbol}: ".$e->getMessage());
            }
        }

        return $snapshots;
    }
}
