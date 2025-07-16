<?php

namespace App\Listeners;

use App\Events\NewRoundStarted;
use App\Jobs\CalculateMomentumJob;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class PredictRoundJobDispatcher
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     * 使用同步执行Job的方式，避免队列延迟的同时保持代码整洁
     */
    public function handle(NewRoundStarted $event): void
    {
        try {
            // 验证事件数据
            if (empty($event->roundId)) {
                Log::error('❌ NewRoundStarted 事件缺少 round_id', [
                    'event_data' => [
                        'round_id' => $event->roundId,
                        'symbols' => $event->symbols,
                        'chain_id' => $event->chainId,
                    ],
                ]);

                return;
            }

            if (empty($event->symbols) || ! is_array($event->symbols)) {
                Log::error('❌ NewRoundStarted 事件缺少或无效的 symbols', [
                    'symbols' => $event->symbols,
                    'symbols_type' => gettype($event->symbols),
                ]);

                return;
            }

            Log::info('🚀 开始同步执行预测计算 Job', [
                'round_id' => $event->roundId,
                'symbols' => $event->symbols,
                'chain_id' => $event->chainId,
            ]);

            // 使用 dispatchSync() 同步执行Job，避免队列延迟
            // 这会立即执行 Job 的 handle 方法，而不是将它推入队列
            CalculateMomentumJob::dispatchSync(
                $event->roundId,
                $event->symbols,
                $event->chainId
            );

            Log::info('✅ 同步执行预测计算 Job 完成', [
                'round_id' => $event->roundId,
            ]);

        } catch (\Exception $e) {
            Log::error('❌ PredictRoundJobDispatcher 在处理事件时失败', [
                'round_id' => $event->roundId ?? 'unknown',
                'symbols' => $event->symbols ?? [],
                'chain_id' => $event->chainId ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }



    /**
     * 监听器失败时的处理
     */
    public function failed(NewRoundStarted $event, \Throwable $exception): void
    {
        Log::error('❌ PredictRoundJobDispatcher 监听器执行失败', [
            'round_id' => $event->roundId ?? 'unknown',
            'symbols' => $event->symbols ?? [],
            'chain_id' => $event->chainId ?? 'unknown',
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
