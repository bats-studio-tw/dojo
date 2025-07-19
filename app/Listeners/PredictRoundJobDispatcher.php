<?php

namespace App\Listeners;

use App\Events\NewRoundStarted;
use App\Jobs\CalculateMomentumJob;
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

            // 1. 执行原有的 CalculateMomentumJob（存储到 hybrid_round_predicts）
            CalculateMomentumJob::dispatchSync(
                $event->roundId,
                $event->symbols,
                $event->chainId
            );

            // 2. 新增：执行 PredictionService（存储到 prediction_results）
            $this->executePredictionService($event->roundId, $event->symbols, $gameRound->id);

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
     * 执行 PredictionService 预测计算
     */
    private function executePredictionService(string $roundId, array $symbols, int $gameRoundId): void
    {
        try {
            Log::info('🧠 开始执行 PredictionService 预测计算', [
                'round_id' => $roundId,
                'symbols' => $symbols,
                'game_round_id' => $gameRoundId,
            ]);

            // 创建保守策略的预测服务
            $conservativeService = PredictionServiceFactory::create('conservative');

            // 执行预测（这会自动存储到 prediction_results 表）
            $predictions = $conservativeService->predict(
                $symbols,
                time(),
                [], // 历史数据（暂时为空）
                $gameRoundId
            );

            Log::info('✅ PredictionService 预测计算完成', [
                'round_id' => $roundId,
                'predictions_count' => count($predictions),
                'strategy' => 'conservative',
            ]);

        } catch (\Exception $e) {
            Log::error('❌ PredictionService 预测计算失败', [
                'round_id' => $roundId,
                'symbols' => $symbols,
                'game_round_id' => $gameRoundId,
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
