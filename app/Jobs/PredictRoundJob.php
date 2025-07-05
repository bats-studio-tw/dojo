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
use App\Models\GameRound;
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

        // Job 实例创建日志已移除，减少日志噪音
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

        try {
            // 步骤1: 获取初始价格 P0
            $pricesP0 = $dexPriceClient->batchPrice($this->symbols);

            // 步骤2: 等待5秒获取价格变化
            sleep(10);

            // 步骤3: 清除缓存并获取后续价格 P1
            // 强制清除缓存以确保获取到不同的价格数据
            $symbols = array_unique(array_map('strtoupper', $this->symbols));
            sort($symbols);
            $cacheKey = "dex_price_batch:" . md5(json_encode($symbols));
            Cache::forget($cacheKey);

            $pricesP1 = $dexPriceClient->batchPrice($this->symbols);

            // 步骤4: 计算动能分数
            $momScore = [];
            foreach ($this->symbols as $symbol) {
                // 添加详细的价格对比日志
                $priceP0 = $pricesP0[$symbol] ?? null;
                $priceP1 = $pricesP1[$symbol] ?? null;

                if ($priceP0 !== null && $priceP1 !== null && $priceP0 > 0) {
                    $priceDiff = $priceP1 - $priceP0;
                    $priceChangePercent = round((($priceP1 / $priceP0 - 1) * 100), 4);

                    Log::info('[PredictRoundJob] 价格对比详情', [
                        'symbol' => $symbol,
                        'price_p0' => $priceP0,
                        'price_p1' => $priceP1,
                        'price_diff' => $priceDiff,
                        'price_change_percent' => $priceChangePercent . '%',
                        'price_change_ratio' => round($priceP1 / $priceP0, 6)
                    ]);
                } else {
                    Log::warning('[PredictRoundJob] 价格数据缺失', [
                        'symbol' => $symbol,
                        'price_p0' => $priceP0,
                        'price_p1' => $priceP1
                    ]);
                }

                if (isset($pricesP0[$symbol]) && isset($pricesP1[$symbol]) && $pricesP0[$symbol] > 0) {
                    $momentum = ($pricesP1[$symbol] / $pricesP0[$symbol] - 1) * 1000;

                    // 改进的动能计算：更敏感的參數調整
                    // 使用更小的阈值来捕捉微小的价格变化
                    $threshold = 0.01; // 降低到0.01% 的变化阈值，更敏感
                    $sensitivity = 5.0; // 增加敏感度到5.0

                    if (abs($momentum) < $threshold) {
                        // 价格变化很小，给予更明显的偏向
                        $momScore[$symbol] = $momentum > 0 ? 55 : 45;
                    } else {
                        // 价格有明显变化，使用更敏感的计算
                        $momScore[$symbol] = min(100, max(0, 50 + ($momentum * $sensitivity)));
                    }

                    Log::info('[PredictRoundJob] 动能分数计算', [
                        'symbol' => $symbol,
                        'price_p0' => $pricesP0[$symbol],
                        'price_p1' => $pricesP1[$symbol],
                        'price_diff' => $pricesP1[$symbol] - $pricesP0[$symbol],
                        'price_change_percent' => round((($pricesP1[$symbol] / $pricesP0[$symbol] - 1) * 100), 4) . '%',
                        'momentum' => $momentum,
                        'mom_score' => $momScore[$symbol],
                        'threshold' => $threshold,
                        'sensitivity' => $sensitivity
                    ]);
                } else {
                    $momScore[$symbol] = 50; // 改为50而不是null，避免在ScoreMixer中被替换
                    Log::warning('[PredictRoundJob] 无法计算动能分数，使用默认值', [
                        'symbol' => $symbol,
                        'price_p0' => $pricesP0[$symbol] ?? 'missing',
                        'price_p1' => $pricesP1[$symbol] ?? 'missing'
                    ]);
                }
            }

            // 步骤5: 计算 Elo 机率
            $eloProb = $eloRatingEngine->probabilities($this->symbols);

            // 步骤6: 混合预测分数
            $predictions = $scoreMixer->mix($eloProb, $momScore);

            if (empty($predictions)) {
                Log::error('[PredictRoundJob] 分数混合结果为空，不写入数据库。', [
                    'round_id' => $this->roundId,
                    'elo_prob' => $eloProb,
                    'mom_score' => $momScore
                ]);
                return;
            }

            // 步骤7: 保存预测结果到数据库
            // 通过字符串 round_id 查找对应的 GameRound 模型，取得它的数字 ID
            $gameRound = GameRound::where('round_id', $this->roundId)->first();

            if (!$gameRound) {
                // 如果找不到对应的 GameRound 记录，自动创建一个
                Log::info('[PredictRoundJob] 找不到对应的 game_rounds 记录，自动创建新记录', [
                    'round_id_string' => $this->roundId
                ]);

                $gameRound = GameRound::create([
                    'round_id' => $this->roundId,
                    // 不设置 settled_at，因为这是预测阶段，游戏还未结算
                ]);

                Log::info('[PredictRoundJob] 已创建新的 GameRound 记录', [
                    'round_id' => $this->roundId,
                    'game_round_id' => $gameRound->id
                ]);
            }

            $gameRoundNumericId = $gameRound->id; // 这是我们需要的数字 ID

            $savedCount = 0;
            foreach ($predictions as $predictionData) {
                try {
                    // 使用查询到的数字 ID ($gameRoundNumericId) 来写入
                    HybridRoundPredict::create(array_merge($predictionData, [
                        'game_round_id' => $gameRoundNumericId, // 使用数字ID
                        'token_symbol' => $predictionData['symbol'],
                    ]));
                    $savedCount++;
                } catch (\Exception $saveError) {
                    Log::error('[PredictRoundJob] 保存单个预测记录失败', [
                        'round_id' => $this->roundId,
                        'game_round_numeric_id' => $gameRoundNumericId,
                        'prediction_data' => $predictionData,
                        'error' => $saveError->getMessage()
                    ]);
                }
            }

            // 步骤8: 缓存预测结果
            Cache::put("hybrid_prediction:{$this->roundId}", $predictions, 30);

            // 步骤9: 广播事件
            // 尝试广播事件，但不让广播失败影响任务执行
            try {
                event(new HybridPredictionUpdated($predictions, $this->roundId, 'hybrid_prediction', 'hybrid_edge_v1'));
            } catch (\Exception $broadcastError) {
                Log::warning('[PredictRoundJob] 事件广播失败', [
                    'round_id' => $this->roundId,
                    'broadcast_error' => $broadcastError->getMessage()
                ]);
            }

            $endTime = microtime(true);
            $executionTime = round(($endTime - $startTime) * 1000, 2);

            Log::info('[PredictRoundJob] 任务执行完成', [
                'round_id' => $this->roundId,
                'execution_time_ms' => $executionTime,
                'saved_predictions' => $savedCount,
                'total_predictions' => count($predictions)
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
