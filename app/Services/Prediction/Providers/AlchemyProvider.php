<?php

namespace App\Services\Prediction\Providers;

use App\Contracts\Prediction\MarketDataProviderInterface;
use App\Models\TokenPrice;
use App\Services\AlchemyPriceService;

class AlchemyProvider implements MarketDataProviderInterface
{
    public function __construct(
        private AlchemyPriceService $alchemyPriceService
    ) {
    }

    public function fetchSnapshots(array $symbols, int $timestamp): array
    {
        $snapshots = [];

        foreach ($symbols as $symbol) {
            try {
                $priceData = $this->alchemyPriceService->getTokenPriceData($symbol);

                if ($priceData) {
                    $snapshots[] = new TokenPrice([
                        'symbol' => $symbol,
                        'price' => $priceData['price_usd'] ?? 0,
                        'volume_24h' => 0, // Alchemy API不提供交易量数据
                        'price_change_24h' => 0, // Alchemy API不提供价格变化数据
                        'timestamp' => $timestamp,
                    ]);
                }
            } catch (\Exception $e) {
                // 记录错误但继续处理其他代币
                \Log::warning("Failed to fetch Alchemy price for {$symbol}: " . $e->getMessage());
            }
        }

        return $snapshots;
    }
}
