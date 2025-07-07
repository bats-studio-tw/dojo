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
use Illuminate\Support\Facades\Log;

class FetchTokenPricesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
        try {
            // 获取当前分钟时间戳
            $currentMinuteTimestamp = TokenPrice::getCurrentMinuteTimestamp();

            // 从token_ratings表获取所有代币符号
            $symbols = TokenRating::pluck('symbol')->toArray();

            if (empty($symbols)) {
                Log::warning("token_ratings表中没有找到代币数据");
                return;
            }

            Log::info("开始获取代币价格数据", [
                'symbols_count' => count($symbols),
                'minute_timestamp' => $currentMinuteTimestamp
            ]);

            // 批量获取价格数据
            $priceData = $alchemyService->batchPriceData($symbols);

            // 使用upsert进行批量更新或插入
            $upsertData = [];
            foreach ($priceData as $symbol => $data) {
                $upsertData[] = [
                    'symbol' => $symbol,
                    'price_usd' => $data['price_usd'],
                    'currency' => $data['currency'],
                    'minute_timestamp' => $currentMinuteTimestamp,
                    'updated_at' => now()
                ];
            }

            // 批量upsert数据
            if (!empty($upsertData)) {
                TokenPrice::upsert(
                    $upsertData,
                    ['symbol', 'minute_timestamp'], // 唯一键
                    ['price_usd', 'currency', 'updated_at'] // 需要更新的字段
                );

                Log::info("成功更新代币价格数据", [
                    'records_count' => count($upsertData),
                    'minute_timestamp' => $currentMinuteTimestamp,
                    'symbols' => array_keys($priceData)
                ]);
            }

        } catch (\Exception $e) {
            Log::error("获取代币价格数据失败", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // 重新抛出异常，让队列系统处理
            throw $e;
        }
    }
}
