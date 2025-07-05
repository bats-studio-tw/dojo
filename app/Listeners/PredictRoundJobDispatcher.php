<?php

namespace App\Listeners;

use App\Events\NewRoundStarted;
use App\Jobs\PredictRoundJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class PredictRoundJobDispatcher implements ShouldQueue
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
        Log::info('🚀 新轮次开始，派遣预测任务', [
            'round_id' => $event->roundId,
            'symbols' => $event->symbols,
            'chain_id' => $event->chainId
        ]);

        // 派遣 PredictRoundJob
        PredictRoundJob::dispatch($event->roundId, $event->symbols, $event->chainId)
                       ->onQueue('predictions'); // 建议使用独立的队列
    }
}
