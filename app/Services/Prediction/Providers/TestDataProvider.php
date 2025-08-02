<?php

namespace App\Services\Prediction\Providers;

use App\Contracts\Prediction\MarketDataProviderInterface;
use App\Models\TokenPrice;
use Carbon\Carbon;

/**
 * 测试数据提供者
 * 用于从本地数据库的TokenPrice模型获取测试数据
 */
class TestDataProvider implements MarketDataProviderInterface
{
    public function fetchSnapshots(array $symbols, int $timestamp): array
    {
        $snapshots = [];

        foreach ($symbols as $symbol) {
            try {
                // 获取最新的价格记录
                $latestPrice = TokenPrice::where('symbol', $symbol)
                    ->orderBy('minute_timestamp', 'desc')
                    ->first();

                if (!$latestPrice) {
                    continue;
                }

                // 获取24小时前的价格（用于计算24小时变化）
                $oneDayAgo = $timestamp - (24 * 3600);
                $historicalPrice = TokenPrice::where('symbol', $symbol)
                    ->where('minute_timestamp', '<=', $oneDayAgo)
                    ->orderBy('minute_timestamp', 'desc')
                    ->first();

                // 计算24小时价格变化
                $priceChange24h = 0;
                if ($historicalPrice && $historicalPrice->price_usd > 0) {
                    $priceChange24h = (($latestPrice->price_usd - $historicalPrice->price_usd) / $historicalPrice->price_usd) * 100;
                }

                // 创建快照对象
                $snapshots[] = (object) [
                    'symbol' => $symbol,
                    'price' => (float) $latestPrice->price_usd,
                    'price_usd' => (float) $latestPrice->price_usd,
                    'price_change_24h' => $priceChange24h,
                    'timestamp' => $latestPrice->minute_timestamp,
                    'currency' => 'usd',
                ];

            } catch (\Exception $e) {
                \Log::warning("Failed to fetch test data for {$symbol}: " . $e->getMessage());
            }
        }

        return $snapshots;
    }


}
