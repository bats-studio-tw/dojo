<?php

namespace App\Contracts\Prediction;

use App\Models\TokenPrice;

interface MarketDataProviderInterface
{
    /**
     * 获取指定代币在指定时间点的行情快照
     *
     * @param  array  $symbols  代币符号数组
     * @param  int  $timestamp  时间戳
     * @return array<TokenPrice> 行情数据数组
     */
    public function fetchSnapshots(array $symbols, int $timestamp): array;
}
