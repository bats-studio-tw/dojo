<?php

namespace App\Listeners;

use App\Events\NewRoundStarted;
use App\Jobs\PredictRoundJob;
use Illuminate\Contracts\Queue\ShouldQueue;
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
        Log::info('🔧 PredictRoundJobDispatcher 监听器已初始化');
    }

    /**
     * Handle the event.
     */
    public function handle(NewRoundStarted $event): void
    {
        Log::info('🎯 PredictRoundJobDispatcher 接收到 NewRoundStarted 事件', [
            'round_id' => $event->roundId,
            'symbols' => $event->symbols,
            'chain_id' => $event->chainId,
            'event_class' => get_class($event),
            'timestamp' => now()->toISOString()
        ]);

        try {
            // 验证事件数据
            if (empty($event->roundId)) {
                Log::error('❌ NewRoundStarted 事件缺少 round_id', [
                    'event_data' => [
                        'round_id' => $event->roundId,
                        'symbols' => $event->symbols,
                        'chain_id' => $event->chainId
                    ]
                ]);
                return;
            }

            if (empty($event->symbols) || !is_array($event->symbols)) {
                Log::error('❌ NewRoundStarted 事件缺少或无效的 symbols', [
                    'symbols' => $event->symbols,
                    'symbols_type' => gettype($event->symbols)
                ]);
                return;
            }

            Log::info('✅ 事件数据验证通过，准备派遣 PredictRoundJob', [
                'round_id' => $event->roundId,
                'symbols_count' => count($event->symbols),
                'symbols' => $event->symbols,
                'chain_id' => $event->chainId
            ]);

            // 派遣 PredictRoundJob
            PredictRoundJob::dispatch($event->roundId, $event->symbols, $event->chainId);
                       // ->onQueue('predictions'); // 使用默认队列

            Log::info('🚀 PredictRoundJob 已派遣到队列', [
                'round_id' => $event->roundId,
                'queue_name' => 'default',
                'dispatch_time' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('❌ PredictRoundJobDispatcher 处理事件失败', [
                'round_id' => $event->roundId ?? 'unknown',
                'symbols' => $event->symbols ?? [],
                'chain_id' => $event->chainId ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
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
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
