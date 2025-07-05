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

class CalculateMomentumJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $roundId;
    protected $symbols;
    protected $chainId;
    public $tries = 3; // 最大重试次数
    public $backoff = [10, 30, 60]; // 重试延迟（秒）

    /**
     * 创建一个新的 Job 实例。
     * @param string $roundId 游戏回合 ID
     * @param array $symbols 本局游戏的代币符号数组
     * @param string $chainId 链 ID
     */
    public function __construct(string $roundId, array $symbols, string $chainId = 'ethereum')
    {
        $this->roundId = $roundId;
        $this->symbols = array_map('strtoupper', $symbols);
        $this->chainId = $chainId;
    }

    /**
     * 执行 Job。
     */
    public function handle(
        DexPriceClient $dexPriceClient,
        EloRatingEngine $eloRatingEngine,
        ScoreMixer $scoreMixer
    ): void
    {
        $startTime = microtime(true);
        $cacheKey = "price_p0:{$this->roundId}";

        try {
            Log::info('[CalculateMomentumJob] 开始计算动能分数', [
                'round_id' => $this->roundId,
                'symbols' => $this->symbols,
                'attempt' => $this->attempts()
            ]);

            // 从缓存中获取初始价格 P0，带重试逻辑
            $pricesP0 = $this->getInitialPricesWithRetry($cacheKey);

            if (!$pricesP0) {
                // 标记该回合预测为失败状态
                $this->markPredictionAsFailed();

                Log::error('[CalculateMomentumJob] 无法获取初始价格，任务失败', [
                    'round_id' => $this->roundId,
                    'cache_key' => $cacheKey,
                    'attempts' => $this->attempts(),
                    'max_attempts' => $this->tries
                ]);

                // 清理可能存在的缓存
                Cache::forget($cacheKey);
                return;
            }

            Log::info('[CalculateMomentumJob] 从缓存获取初始价格', [
                'round_id' => $this->roundId,
                'prices_p0' => $pricesP0
            ]);

            // 获取后续价格 P1（强制刷新缓存）
            $pricesP1 = $dexPriceClient->batchPrice($this->symbols, true);

            Log::info('[CalculateMomentumJob] 后续价格获取完成', [
                'round_id' => $this->roundId,
                'prices_p1' => $pricesP1
            ]);

            // 计算动能分数
            $momScore = $this->calculateMomentumScores($pricesP0, $pricesP1);

            // 计算 Elo 机率
            $eloProb = $eloRatingEngine->probabilities($this->symbols);

            // 混合预测分数
            $predictions = $scoreMixer->mix($eloProb, $momScore);

            if (empty($predictions)) {
                Log::error('[CalculateMomentumJob] 分数混合结果为空，不写入数据库。', [
                    'round_id' => $this->roundId,
                    'elo_prob' => $eloProb,
                    'mom_score' => $momScore
                ]);

                // 清理缓存并标记失败
                Cache::forget($cacheKey);
                $this->markPredictionAsFailed();
                return;
            }

            // 保存预测结果到数据库
            $savedCount = $this->savePredictionsToDatabase($predictions);

            if ($savedCount === 0) {
                Log::error('[CalculateMomentumJob] 所有预测记录保存失败', [
                    'round_id' => $this->roundId
                ]);

                // 清理缓存并标记失败
                Cache::forget($cacheKey);
                $this->markPredictionAsFailed();
                return;
            }

            // 缓存预测结果
            Cache::put("hybrid_prediction:{$this->roundId}", $predictions, 30);

            // 广播事件
            $this->broadcastPredictionEvent($predictions);

            // 清理初始价格缓存
            Cache::forget($cacheKey);

            $endTime = microtime(true);
            $executionTime = round(($endTime - $startTime) * 1000, 2);

            Log::info('[CalculateMomentumJob] 任务执行完成', [
                'round_id' => $this->roundId,
                'execution_time_ms' => $executionTime,
                'saved_predictions' => $savedCount,
                'total_predictions' => count($predictions)
            ]);

        } catch (\Throwable $e) {
            $endTime = microtime(true);
            $executionTime = round(($endTime - $startTime) * 1000, 2);

            Log::error('[CalculateMomentumJob] 任务执行时发生严重错误', [
                'round_id' => $this->roundId,
                'symbols' => $this->symbols,
                'execution_time_ms' => $executionTime,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'attempt' => $this->attempts()
            ]);

            // 清理缓存
            Cache::forget($cacheKey);

            // 如果是最后一次尝试，标记为失败
            if ($this->attempts() >= $this->tries) {
                $this->markPredictionAsFailed();
            }

            throw $e;
        }
    }

    /**
     * 带重试逻辑的初始价格获取
     */
    private function getInitialPricesWithRetry(string $cacheKey): ?array
    {
        $maxRetries = 3;
        $retryDelay = 2; // 秒

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $pricesP0 = Cache::get($cacheKey);

            if ($pricesP0) {
                return $pricesP0;
            }

            if ($attempt < $maxRetries) {
                Log::warning('[CalculateMomentumJob] 缓存读取失败，准备重试', [
                    'round_id' => $this->roundId,
                    'cache_key' => $cacheKey,
                    'attempt' => $attempt,
                    'max_retries' => $maxRetries,
                    'retry_delay' => $retryDelay
                ]);

                sleep($retryDelay);
                $retryDelay *= 2; // 指数退避
            }
        }

        return null;
    }

    /**
     * 计算动能分数
     */
    private function calculateMomentumScores(array $pricesP0, array $pricesP1): array
    {
        $momScore = [];

        foreach ($this->symbols as $symbol) {
            $priceP0 = $pricesP0[$symbol] ?? null;
            $priceP1 = $pricesP1[$symbol] ?? null;

            if ($priceP0 !== null && $priceP1 !== null && $priceP0 > 0) {
                $priceDiff = $priceP1 - $priceP0;
                $priceChangePercent = round((($priceP1 / $priceP0 - 1) * 100), 4);

                Log::info('[CalculateMomentumJob] 价格对比详情', [
                    'symbol' => $symbol,
                    'price_p0' => $priceP0,
                    'price_p1' => $priceP1,
                    'price_diff' => $priceDiff,
                    'price_change_percent' => $priceChangePercent . '%',
                    'price_change_ratio' => round($priceP1 / $priceP0, 6)
                ]);

                $momentum = ($priceP1 / $priceP0 - 1) * 1000;

                // 改进的动能计算：更敏感的參數調整
                $threshold = 0.01; // 降低到0.01% 的变化阈值，更敏感
                $sensitivity = 5.0; // 增加敏感度到5.0

                if (abs($momentum) < $threshold) {
                    // 价格变化很小，给予更明显的偏向
                    $momScore[$symbol] = $momentum > 0 ? 55 : 45;
                } else {
                    // 价格有明显变化，使用更敏感的计算
                    $momScore[$symbol] = min(100, max(0, 50 + ($momentum * $sensitivity)));
                }

                Log::info('[CalculateMomentumJob] 动能分数计算', [
                    'symbol' => $symbol,
                    'price_p0' => $priceP0,
                    'price_p1' => $priceP1,
                    'price_diff' => $priceDiff,
                    'price_change_percent' => $priceChangePercent . '%',
                    'momentum' => $momentum,
                    'mom_score' => $momScore[$symbol],
                    'threshold' => $threshold,
                    'sensitivity' => $sensitivity
                ]);
            } else {
                $momScore[$symbol] = 50; // 改为50而不是null，避免在ScoreMixer中被替换
                Log::warning('[CalculateMomentumJob] 无法计算动能分数，使用默认值', [
                    'symbol' => $symbol,
                    'price_p0' => $priceP0,
                    'price_p1' => $priceP1
                ]);
            }
        }

        return $momScore;
    }

    /**
     * 保存预测结果到数据库
     */
    private function savePredictionsToDatabase(array $predictions): int
    {
        // 保存预测结果到数据库
        $gameRound = GameRound::where('round_id', $this->roundId)->first();

        if (!$gameRound) {
            Log::info('[CalculateMomentumJob] 找不到对应的 game_rounds 记录，自动创建新记录', [
                'round_id_string' => $this->roundId
            ]);

            $gameRound = GameRound::create([
                'round_id' => $this->roundId,
            ]);

            Log::info('[CalculateMomentumJob] 已创建新的 GameRound 记录', [
                'round_id' => $this->roundId,
                'game_round_id' => $gameRound->id
            ]);
        }

        $gameRoundNumericId = $gameRound->id;

        $savedCount = 0;
        foreach ($predictions as $predictionData) {
            try {
                HybridRoundPredict::create(array_merge($predictionData, [
                    'game_round_id' => $gameRoundNumericId,
                    'token_symbol' => $predictionData['symbol'],
                ]));
                $savedCount++;
            } catch (\Exception $saveError) {
                Log::error('[CalculateMomentumJob] 保存单个预测记录失败', [
                    'round_id' => $this->roundId,
                    'game_round_numeric_id' => $gameRoundNumericId,
                    'prediction_data' => $predictionData,
                    'error' => $saveError->getMessage()
                ]);
            }
        }

        return $savedCount;
    }

    /**
     * 广播预测事件
     */
    private function broadcastPredictionEvent(array $predictions): void
    {
        try {
            event(new HybridPredictionUpdated($predictions, $this->roundId, 'hybrid_prediction', 'hybrid_edge_v1'));
        } catch (\Exception $broadcastError) {
            Log::warning('[CalculateMomentumJob] 事件广播失败', [
                'round_id' => $this->roundId,
                'broadcast_error' => $broadcastError->getMessage()
            ]);
        }
    }

    /**
     * 标记预测为失败状态
     */
    private function markPredictionAsFailed(): void
    {
        try {
            // 在缓存中标记该回合预测失败
            Cache::put("prediction_failed:{$this->roundId}", [
                'failed_at' => now()->toISOString(),
                'reason' => 'CalculateMomentumJob failed',
                'attempts' => $this->attempts(),
                'symbols' => $this->symbols
            ], 300); // 缓存5分钟

            Log::error('[CalculateMomentumJob] 已标记预测失败状态', [
                'round_id' => $this->roundId,
                'cache_key' => "prediction_failed:{$this->roundId}"
            ]);
        } catch (\Exception $e) {
            Log::error('[CalculateMomentumJob] 标记预测失败状态时出错', [
                'round_id' => $this->roundId,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[CalculateMomentumJob] 任务执行失败', [
            'round_id' => $this->roundId,
            'symbols' => $this->symbols,
            'chain_id' => $this->chainId,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'final_attempt' => $this->attempts()
        ]);

        // 清理缓存
        $cacheKey = "price_p0:{$this->roundId}";
        Cache::forget($cacheKey);

        // 标记预测失败
        $this->markPredictionAsFailed();
    }
}
