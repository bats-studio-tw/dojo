<?php

namespace App\Jobs;

use App\Models\TokenPrice;
use App\Models\TokenRating;
use App\Services\AlchemyPriceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FetchTokenPricesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(AlchemyPriceService $alchemyService): void
    {
        // 步骤1: 先获取必要的基础数据（快速数据库读取）
        $currentMinuteTimestamp = TokenPrice::getCurrentMinuteTimestamp();
        $symbols = TokenRating::pluck('symbol')->toArray();

        if (empty($symbols)) {
            Log::warning("token_ratings表中没有找到代币数据");

            return;
        }

        Log::info("开始获取代币价格数据", [
            'symbols_count' => count($symbols),
            'minute_timestamp' => $currentMinuteTimestamp,
        ]);

        // 步骤2: 完成所有耗时的网络操作，这里不涉及数据库事务
        // 这是整个流程中最耗时、最不稳定的部分
        $priceData = null;

        try {
            // 执行外部API调用，这可能需要几秒到几十秒的时间
            $priceData = $alchemyService->batchPriceData($symbols);

            Log::info("成功获取外部API价格数据", [
                'symbols_fetched' => count($priceData),
                'minute_timestamp' => $currentMinuteTimestamp,
            ]);

        } catch (\Exception $e) {
            // 网络请求失败，记录错误并提前退出
            Log::error("从外部API获取代币价格失败", [
                'error' => $e->getMessage(),
                'symbols_count' => count($symbols),
                'minute_timestamp' => $currentMinuteTimestamp,
            ]);

            // 重新抛出异常，让队列系统处理重试
            throw $e;
        }

        // 如果没有获取到有效的价格数据，直接返回
        if (empty($priceData)) {
            Log::warning("外部API返回空的价格数据", [
                'symbols_count' => count($symbols),
                'minute_timestamp' => $currentMinuteTimestamp,
            ]);

            return;
        }

        // 步骤3: 准备数据库写入的数据结构
        $upsertData = [];
        foreach ($priceData as $symbol => $data) {
            $upsertData[] = [
                'symbol' => $symbol,
                'price_usd' => $data['price_usd'],
                'currency' => $data['currency'],
                'minute_timestamp' => $currentMinuteTimestamp,
                'updated_at' => now(),
            ];
        }

        // 步骤4: 只针对快速的数据库写入操作开启事务
        // 这样可以最大程度地缩短数据库锁定时间
        try {
            DB::transaction(function () use ($upsertData, $currentMinuteTimestamp) {
                // 这里的操作应该在毫秒级完成，最小化锁定时间
                TokenPrice::upsert(
                    $upsertData,
                    ['symbol', 'minute_timestamp'], // 唯一键
                    ['price_usd', 'currency', 'updated_at'] // 需要更新的字段
                );
            });

            Log::info("成功写入代币价格数据到数据库", [
                'records_count' => count($upsertData),
                'minute_timestamp' => $currentMinuteTimestamp,
                'symbols' => array_keys($priceData),
            ]);

        } catch (\Exception $e) {
            Log::error("写入代币价格数据到数据库失败", [
                'error' => $e->getMessage(),
                'records_count' => count($upsertData),
                'minute_timestamp' => $currentMinuteTimestamp,
            ]);

            // 重新抛出异常，让队列系统处理
            throw $e;
        }
    }
}
