<?php

namespace App\Listeners;

use App\Events\NewRoundStarted;
use App\Jobs\ExtractFeaturesJob;
use App\Models\GameRound;
use App\Services\Prediction\PredictionServiceFactory;
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

            // 获取或创建游戏轮次记录
            $gameRound = GameRound::where('round_id', $event->roundId)->first();
            if (! $gameRound) {
                $gameRound = GameRound::create([
                    'round_id' => $event->roundId,
                ]);
            }

            // v3：仅提取面向特征的快照（前端自行聚合排序与下注）
            ExtractFeaturesJob::dispatchSync(
                $event->roundId,
                $event->symbols
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

    // v3 不再执行 PredictionService/CalculateMomentumJob 的综合推荐

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
