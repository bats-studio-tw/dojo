<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\DexPriceClient;
use App\Services\EloRatingEngine;
use App\Services\ScoreMixer;
use App\Models\HybridRoundPredict;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Events\HybridPredictionUpdated;

class PredictRoundJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $roundId;
    protected $symbols;
    protected $chainId;

    /**
     * 創建一個新的 Job 實例。
     * @param string $roundId 遊戲回合 ID
     * @param array $symbols 本局遊戲的代幣符號陣列
     * @param string $chainId 鏈 ID
     */
    public function __construct(string $roundId, array $symbols, string $chainId = 'ethereum')
    {
        $this->roundId = $roundId;
        $this->symbols = array_map('strtoupper', $symbols);
        $this->chainId = $chainId;

        Log::info('🔧 PredictRoundJob 实例已创建', [
            'round_id' => $this->roundId,
            'symbols' => $this->symbols,
            'chain_id' => $this->chainId,
            'job_class' => get_class($this)
        ]);
    }

    /**
     * 執行 Job。
     */
    public function handle(
        DexPriceClient $dexPriceClient,
        EloRatingEngine $eloRatingEngine,
        ScoreMixer $scoreMixer
    ): void
    {
        $startTime = microtime(true);

        Log::info('[PredictRoundJob] 任务开始，处理 Round ID: ' . $this->roundId, [
            'round_id' => $this->roundId,
            'symbols' => $this->symbols,
            'chain_id' => $this->chainId,
            'queue_name' => $this->queue,
            'start_time' => now()->toISOString()
        ]);

        try {
            // 步骤1: 获取初始价格 P0
            Log::info('[PredictRoundJob] 准备获取 P0 价格...', [
                'round_id' => $this->roundId,
                'symbols_count' => count($this->symbols),
                'symbols' => $this->symbols
            ]);

            $pricesP0 = $dexPriceClient->batchPrice($this->symbols);

            Log::info('[PredictRoundJob] P0 价格获取完毕', [
                'round_id' => $this->roundId,
                'p0_data' => $pricesP0,
                'prices_count' => count($pricesP0)
            ]);

            // 步骤2: 等待5秒获取价格变化
            Log::info('[PredictRoundJob] 等待 5 秒获取价格变化...', [
                'round_id' => $this->roundId,
                'wait_start' => now()->toISOString()
            ]);

            sleep(5);

            // 步骤3: 获取后续价格 P1
            Log::info('[PredictRoundJob] 准备获取 P1 价格...', [
                'round_id' => $this->roundId,
                'wait_completed' => now()->toISOString()
            ]);

            $pricesP1 = $dexPriceClient->batchPrice($this->symbols);

            Log::info('[PredictRoundJob] P1 价格获取完毕', [
                'round_id' => $this->roundId,
                'p1_data' => $pricesP1,
                'prices_count' => count($pricesP1)
            ]);

            // 步骤4: 计算动能分数
            Log::info('[PredictRoundJob] 开始计算动能分数...', [
                'round_id' => $this->roundId
            ]);

            $momScore = [];
            foreach ($this->symbols as $symbol) {
                if (isset($pricesP0[$symbol]) && isset($pricesP1[$symbol]) && $pricesP0[$symbol] > 0) {
                    $momentum = ($pricesP1[$symbol] / $pricesP0[$symbol] - 1) * 1000;
                    // 5秒間隔的動能計算：更敏感的參數調整
                    $momScore[$symbol] = min(100, max(0, 50 + ($momentum / 0.1)));

                    Log::info('[PredictRoundJob] 动能计算详情', [
                        'symbol' => $symbol,
                        'price_p0' => $pricesP0[$symbol],
                        'price_p1' => $pricesP1[$symbol],
                        'momentum' => $momentum,
                        'mom_score' => $momScore[$symbol]
                    ]);
                } else {
                    $momScore[$symbol] = null;
                    Log::warning('[PredictRoundJob] 无法计算动能分数', [
                        'symbol' => $symbol,
                        'price_p0' => $pricesP0[$symbol] ?? 'missing',
                        'price_p1' => $pricesP1[$symbol] ?? 'missing'
                    ]);
                }
            }

            Log::info('[PredictRoundJob] 动能分数计算完成', [
                'round_id' => $this->roundId,
                'momentum_scores' => $momScore,
                'valid_scores_count' => count(array_filter($momScore, fn($score) => $score !== null))
            ]);

            // 步骤5: 计算 Elo 机率
            Log::info('[PredictRoundJob] 准备获取 Elo 机率...', [
                'round_id' => $this->roundId
            ]);

            $eloProb = $eloRatingEngine->probabilities($this->symbols);

            Log::info('[PredictRoundJob] Elo 机率获取完毕', [
                'round_id' => $this->roundId,
                'elo_prob' => $eloProb,
                'probabilities_count' => count($eloProb)
            ]);

            // 步骤6: 混合预测分数
            Log::info('[PredictRoundJob] 准备混合分数...', [
                'round_id' => $this->roundId,
                'elo_prob_count' => count($eloProb),
                'mom_score_count' => count($momScore)
            ]);

            $predictions = $scoreMixer->mix($eloProb, $momScore);

            if (empty($predictions)) {
                Log::error('[PredictRoundJob] 分数混合结果为空，不写入数据库。', [
                    'round_id' => $this->roundId,
                    'elo_prob' => $eloProb,
                    'mom_score' => $momScore
                ]);
                return;
            }

            Log::info('[PredictRoundJob] 分数混合完毕', [
                'round_id' => $this->roundId,
                'mixed_scores' => $predictions,
                'predictions_count' => count($predictions),
                'top_prediction' => $predictions[0] ?? null
            ]);

            // 步骤7: 保存预测结果到数据库
            Log::info('[PredictRoundJob] 准备写入数据库...', [
                'round_id' => $this->roundId,
                'predictions_to_save' => count($predictions)
            ]);

            $savedCount = 0;
            foreach ($predictions as $predictionData) {
                try {
                    HybridRoundPredict::create(array_merge($predictionData, [
                        'game_round_id' => $this->roundId,
                        'token_symbol' => $predictionData['symbol'],
                    ]));
                    $savedCount++;
                } catch (\Exception $saveError) {
                    Log::error('[PredictRoundJob] 保存单个预测记录失败', [
                        'round_id' => $this->roundId,
                        'prediction_data' => $predictionData,
                        'error' => $saveError->getMessage()
                    ]);
                }
            }

            Log::info('[PredictRoundJob] 数据库写入成功！', [
                'round_id' => $this->roundId,
                'saved_predictions' => $savedCount,
                'total_predictions' => count($predictions)
            ]);

            // 步骤8: 缓存预测结果
            Log::info('[PredictRoundJob] 开始缓存预测结果...', [
                'round_id' => $this->roundId
            ]);

            Cache::put("hybrid_prediction:{$this->roundId}", $predictions, 30);

            Log::info('[PredictRoundJob] 预测结果已缓存', [
                'round_id' => $this->roundId,
                'cache_key' => "hybrid_prediction:{$this->roundId}",
                'cache_ttl' => 30
            ]);

            // 步骤9: 广播事件
            Log::info('[PredictRoundJob] 开始广播事件...', [
                'round_id' => $this->roundId
            ]);

            // 尝试广播事件，但不让广播失败影响任务执行
            try {
                event(new HybridPredictionUpdated($predictions, $this->roundId, 'hybrid_prediction', 'hybrid_edge_v1'));
                Log::info('[PredictRoundJob] Hybrid-Edge v1.0 预测完成，事件广播成功', [
                    'round_id' => $this->roundId,
                    'top_prediction' => $predictions[0] ?? null,
                    'broadcast_event' => 'HybridPredictionUpdated'
                ]);
            } catch (\Exception $broadcastError) {
                Log::warning('[PredictRoundJob] Hybrid-Edge v1.0 预测完成，但事件广播失败', [
                    'round_id' => $this->roundId,
                    'top_prediction' => $predictions[0] ?? null,
                    'broadcast_error' => $broadcastError->getMessage()
                ]);
            }

            $endTime = microtime(true);
            $executionTime = round(($endTime - $startTime) * 1000, 2);

            Log::info('[PredictRoundJob] 任务执行完成', [
                'round_id' => $this->roundId,
                'execution_time_ms' => $executionTime,
                'total_predictions' => count($predictions),
                'saved_predictions' => $savedCount,
                'end_time' => now()->toISOString()
            ]);

        } catch (\Throwable $e) {
            // 加入一个 catch 区块来捕获任何可能的错误
            $endTime = microtime(true);
            $executionTime = round(($endTime - $startTime) * 1000, 2);

            Log::error('[PredictRoundJob] 任务执行时发生严重错误', [
                'round_id' => $this->roundId,
                'symbols' => $this->symbols,
                'execution_time_ms' => $executionTime,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString()
            ]);
            // 重新抛出异常，让 Worker 知道任务失败了
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[PredictRoundJob] 任务执行失败', [
            'round_id' => $this->roundId,
            'symbols' => $this->symbols,
            'chain_id' => $this->chainId,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
