<?php

namespace App\Repositories;

use App\Models\TokenPrice;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class TokenPriceRepository
{
    /**
     * 获取指定代币的最新价格记录
     *
     * @param string $symbol 代币符号
     * @param int $limit 返回的记录数量，默认2条（最新和前一条）
     * @return Collection|null 价格记录集合，如果找不到记录则返回null
     */
    public function getLatestPricesForToken(string $symbol, int $limit = 2): ?Collection
    {
        try {
            $prices = TokenPrice::where('symbol', strtoupper($symbol))
                ->orderBy('minute_timestamp', 'desc')
                ->limit($limit)
                ->get();

            if ($prices->isEmpty()) {
                Log::warning('[TokenPriceRepository] 未找到代币价格记录', [
                    'symbol' => $symbol,
                    'limit' => $limit
                ]);
                return null;
            }

            if ($prices->count() < $limit) {
                Log::warning('[TokenPriceRepository] 代币价格记录不足', [
                    'symbol' => $symbol,
                    'found_count' => $prices->count(),
                    'required_count' => $limit
                ]);
            }

            return $prices;

        } catch (\Exception $e) {
            Log::error('[TokenPriceRepository] 查询代币价格时发生错误', [
                'symbol' => $symbol,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * 批量获取多个代币的最新价格记录
     *
     * @param array $symbols 代币符号数组
     * @param int $limit 每个代币返回的记录数量
     * @return array 格式: ['SYMBOL' => Collection|null, ...]
     */
    public function getLatestPricesForTokens(array $symbols, int $limit = 2): array
    {
        $results = [];

        foreach ($symbols as $symbol) {
            $results[strtoupper($symbol)] = $this->getLatestPricesForToken($symbol, $limit);
        }

        return $results;
    }

    /**
     * 检查代币是否有足够的价格记录来计算动能
     *
     * @param string $symbol 代币符号
     * @return bool
     */
    public function hasEnoughPriceData(string $symbol): bool
    {
        $prices = $this->getLatestPricesForToken($symbol, 2);
        return $prices !== null && $prices->count() >= 2;
    }
}
