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
        //
    }

    /**
     * Handle the event.
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

            // 派遣 PredictRoundJob
            PredictRoundJob::dispatch($event->roundId, $event->symbols, $event->chainId);

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
