<?php

namespace App\Repositories;

use App\Models\TokenPrice;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
                return null;
            }

            return $prices;

        } catch (\Exception $e) {
            Log::error('[TokenPriceRepository] 查询代币价格时发生错误', [
                'symbol' => $symbol,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * 获取指定代币的历史价格序列（用于线性回归计算）
     *
     * @param string $symbol 代币符号
     * @param int $dataPoints 需要的数据点数量，默认5个
     * @return Collection|null 价格记录集合，按时间升序排列
     */
    public function getHistoricalPriceSequence(string $symbol, int $dataPoints = 5): ?Collection
    {
        try {
            $prices = TokenPrice::where('symbol', strtoupper($symbol))
                ->orderBy('minute_timestamp', 'desc')
                ->limit($dataPoints)
                ->get();

            if ($prices->isEmpty()) {
                return null;
            }

            // 按时间升序排列，用于线性回归计算
            return $prices->sortBy('minute_timestamp');

        } catch (\Exception $e) {
            Log::error('[TokenPriceRepository] 查询代币历史价格序列时发生错误', [
                'symbol' => $symbol,
                'data_points' => $dataPoints,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * 批量获取多个代币的历史价格序列（用于线性回归计算）
     *
     * @param array $symbols 代币符号数组
     * @param int $dataPoints 每个代币需要的数据点数量，默认5个
     * @return array 格式: ['SYMBOL' => Collection|null, ...]
     */
    public function getHistoricalPriceSequences(array $symbols, int $dataPoints = 5): array
    {
        if (empty($symbols)) {
            return [];
        }

        try {
            $upperSymbols = array_map('strtoupper', $symbols);
            $groupedResults = [];

            // 如果代币数量较少，使用循环查询（更简单）
            if (count($upperSymbols) <= 5) {
                foreach ($upperSymbols as $symbol) {
                    $prices = $this->getHistoricalPriceSequence($symbol, $dataPoints);
                    $groupedResults[$symbol] = $prices;
                }
            } else {
                // 如果代币数量较多，使用批量查询（性能更好）
                $results = DB::table('token_prices as tp1')
                    ->select([
                        'tp1.symbol',
                        'tp1.price_usd',
                        'tp1.currency',
                        'tp1.minute_timestamp',
                        'tp1.created_at',
                        'tp1.updated_at',
                    ])
                    ->whereIn('tp1.symbol', $upperSymbols)
                    ->whereRaw('(
                        SELECT COUNT(*)
                        FROM token_prices as tp2
                        WHERE tp2.symbol = tp1.symbol
                        AND tp2.minute_timestamp >= tp1.minute_timestamp
                    ) <= ?', [$dataPoints])
                    ->orderBy('tp1.symbol')
                    ->orderBy('tp1.minute_timestamp', 'desc')
                    ->get();

                // 按代币分组结果
                foreach ($upperSymbols as $symbol) {
                    $groupedResults[$symbol] = collect();
                }

                foreach ($results as $row) {
                    $symbol = $row->symbol;
                    if (! isset($groupedResults[$symbol])) {
                        $groupedResults[$symbol] = collect();
                    }

                    // 创建TokenPrice模型实例
                    $tokenPrice = new TokenPrice();
                    $tokenPrice->fill([
                        'symbol' => $row->symbol,
                        'price_usd' => $row->price_usd,
                        'currency' => $row->currency,
                        'minute_timestamp' => $row->minute_timestamp,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ]);

                    $groupedResults[$symbol]->push($tokenPrice);
                }

                // 对每个代币的价格序列按时间升序排列
                foreach ($groupedResults as $symbol => $prices) {
                    if ($prices->isNotEmpty()) {
                        $groupedResults[$symbol] = $prices->sortBy('minute_timestamp');
                    }
                }
            }

            return $groupedResults;

        } catch (\Exception $e) {
            Log::error('[TokenPriceRepository] 批量查询代币历史价格序列时发生错误', [
                'symbols' => $symbols,
                'data_points' => $dataPoints,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // 降级到单个查询方式
            return $this->getHistoricalPriceSequencesFallback($symbols, $dataPoints);
        }
    }

    /**
     * 降级方案：使用单个查询方式获取历史价格序列（当批量查询失败时）
     */
    private function getHistoricalPriceSequencesFallback(array $symbols, int $dataPoints = 5): array
    {
        $results = [];

        foreach ($symbols as $symbol) {
            $results[strtoupper($symbol)] = $this->getHistoricalPriceSequence($symbol, $dataPoints);
        }

        return $results;
    }

    /**
     * 批量获取多个代币的最新价格记录（优化版本）
     * 使用单个查询获取所有代币的最新价格，然后按代币分组
     *
     * @param array $symbols 代币符号数组
     * @param int $limit 每个代币返回的记录数量
     * @return array 格式: ['SYMBOL' => Collection|null, ...]
     */
    public function getLatestPricesForTokens(array $symbols, int $limit = 2): array
    {
        if (empty($symbols)) {
            return [];
        }

        try {
            // 将代币符号转换为大写
            $upperSymbols = array_map('strtoupper', $symbols);

            // 使用优化的批量查询，每个代币只获取需要的记录数
            $groupedResults = [];

            // 如果代币数量较少，使用循环查询（更简单）
            if (count($upperSymbols) <= 5) {
                foreach ($upperSymbols as $symbol) {
                    $prices = TokenPrice::where('symbol', $symbol)
                        ->orderBy('minute_timestamp', 'desc')
                        ->limit($limit)
                        ->get();

                    $groupedResults[$symbol] = $prices->isEmpty() ? null : $prices;
                }
            } else {
                // 如果代币数量较多，使用批量查询（性能更好）
                $results = DB::table('token_prices as tp1')
                    ->select([
                        'tp1.symbol',
                        'tp1.price_usd',
                        'tp1.currency',
                        'tp1.minute_timestamp',
                        'tp1.created_at',
                        'tp1.updated_at',
                    ])
                    ->whereIn('tp1.symbol', $upperSymbols)
                    ->whereRaw('(
                        SELECT COUNT(*)
                        FROM token_prices as tp2
                        WHERE tp2.symbol = tp1.symbol
                        AND tp2.minute_timestamp >= tp1.minute_timestamp
                    ) <= ?', [$limit])
                    ->orderBy('tp1.symbol')
                    ->orderBy('tp1.minute_timestamp', 'desc')
                    ->get();

                // 按代币分组结果
                foreach ($upperSymbols as $symbol) {
                    $groupedResults[$symbol] = null;
                }

                foreach ($results as $row) {
                    $symbol = $row->symbol;
                    if (! isset($groupedResults[$symbol])) {
                        $groupedResults[$symbol] = collect();
                    }

                    // 创建TokenPrice模型实例
                    $tokenPrice = new TokenPrice();
                    $tokenPrice->fill([
                        'symbol' => $row->symbol,
                        'price_usd' => $row->price_usd,
                        'currency' => $row->currency,
                        'minute_timestamp' => $row->minute_timestamp,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ]);

                    $groupedResults[$symbol]->push($tokenPrice);
                }
            }



            return $groupedResults;

        } catch (\Exception $e) {
            Log::error('[TokenPriceRepository] 批量查询代币价格时发生错误', [
                'symbols' => $symbols,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // 降级到单个查询方式
            return $this->getLatestPricesForTokensFallback($symbols, $limit);
        }
    }

    /**
     * 降级方案：使用单个查询方式（当批量查询失败时）
     */
    private function getLatestPricesForTokensFallback(array $symbols, int $limit = 2): array
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
