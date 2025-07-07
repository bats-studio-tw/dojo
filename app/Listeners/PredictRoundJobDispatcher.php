<?php

namespace App\Listeners;

use App\Events\NewRoundStarted;
use App\Jobs\PredictRoundJob;
use App\Services\EloRatingEngine;
use App\Services\ScoreMixer;
use App\Models\HybridRoundPredict;
use App\Models\GameRound;
use App\Repositories\TokenPriceRepository;
use App\Events\HybridPredictionUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

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
     * 修改为直接执行预测计算，避免队列延迟
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

            Log::info('🚀 开始直接执行预测计算', [
                'round_id' => $event->roundId,
                'symbols' => $event->symbols,
                'chain_id' => $event->chainId
            ]);

            // 直接执行预测计算，不使用队列
            $this->executePredictionCalculation($event->roundId, $event->symbols, $event->chainId);

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
     * 直接执行预测计算
     */
    private function executePredictionCalculation(string $roundId, array $symbols, string $chainId): void
    {
        $startTime = microtime(true);

        try {
            // 注入依赖
            $tokenPriceRepository = app(TokenPriceRepository::class);
            $eloRatingEngine = app(EloRatingEngine::class);
            $scoreMixer = app(ScoreMixer::class);

            // 从数据库获取价格数据
            $priceData = $tokenPriceRepository->getLatestPricesForTokens($symbols, 2);

            // 计算动能分数
            $momScore = $this->calculateMomentumScoresFromDatabase($priceData, $symbols);

            // 计算 Elo 机率
            $eloProb = $eloRatingEngine->probabilities($symbols);

            // 混合预测分数
            $predictions = $scoreMixer->mix($eloProb, $momScore);

            if (empty($predictions)) {
                Log::error('[PredictRoundJobDispatcher] 分数混合结果为空，不写入数据库。', [
                    'round_id' => $roundId,
                    'elo_prob' => $eloProb,
                    'mom_score' => $momScore
                ]);
                return;
            }

            // 保存预测结果到数据库
            $savedCount = $this->savePredictionsToDatabase($predictions, $roundId);

            if ($savedCount === 0) {
                Log::error('[PredictRoundJobDispatcher] 所有预测记录保存失败', [
                    'round_id' => $roundId
                ]);
                return;
            }

            // 缓存预测结果
            Cache::put("hybrid_prediction:{$roundId}", $predictions, 30);

            // 广播事件
            $this->broadcastPredictionEvent($predictions, $roundId);

            $endTime = microtime(true);
            $executionTime = round(($endTime - $startTime) * 1000, 2);

            Log::info('[PredictRoundJobDispatcher] 预测计算完成', [
                'round_id' => $roundId,
                'symbols' => $symbols,
                'execution_time_ms' => $executionTime,
                'saved_count' => $savedCount
            ]);

        } catch (\Exception $e) {
            $endTime = microtime(true);
            $executionTime = round(($endTime - $startTime) * 1000, 2);

            Log::error('[PredictRoundJobDispatcher] 预测计算失败', [
                'round_id' => $roundId,
                'symbols' => $symbols,
                'execution_time_ms' => $executionTime,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * 从数据库计算动能分数
     */
    private function calculateMomentumScoresFromDatabase(array $priceData, array $symbols): array
    {
        $momScore = [];
        $validPriceCount = 0;
        $invalidPriceCount = 0;
        $sensitivity = 10.0; // 动能敏感度系数
        $threshold = 0.001; // 微小变化阈值

        foreach ($symbols as $symbol) {
            $symbolUpper = strtoupper($symbol);
            $prices = $priceData[$symbolUpper] ?? null;

            if ($prices && $prices->count() >= 2) {
                // 获取最新的两个价格记录
                $priceP1 = $prices->first()->price_usd; // 最新价格
                $priceP0 = $prices->skip(1)->first()->price_usd; // 前一个价格

                if ($this->isValidPriceData($priceP0, $priceP1)) {
                    $priceDiff = $priceP1 - $priceP0;
                    $priceChangePercent = round((($priceP1 / $priceP0 - 1) * 100), 6);
                    $momentum = ($priceP1 / $priceP0 - 1) * 1000;

                    // 检查是否为微小变化
                    if (abs($momentum) < $threshold) {
                        // 微小变化时，基于历史数据生成更稳定的分数
                        $historicalScore = $this->getHistoricalMomentumScore($symbol);
                        $microChange = $momentum * 10000; // 放大微小变化
                        $baseScore = $historicalScore;
                        $microAdjustment = $microChange * 2.0; // 微小调整系数
                        $momScore[$symbol] = max(45, min(55, $baseScore + $microAdjustment));
                    } else {
                        // 价格有明显变化，使用更敏感的计算
                        $momScore[$symbol] = min(100, max(0, 50 + ($momentum * $sensitivity)));
                    }

                    $validPriceCount++;
                } else {
                    // 使用智能默认值策略
                    $momScore[$symbol] = $this->calculateDefaultMomentumScore($symbol, $priceP0, $priceP1);
                    $invalidPriceCount++;
                }
            } else {
                // 数据库中没有足够的价格记录，使用默认分数
                $momScore[$symbol] = $this->calculateDefaultMomentumScore($symbol, null, null);
                $invalidPriceCount++;
            }
        }

        return $momScore;
    }

    /**
     * 验证价格数据是否有效
     */
    private function isValidPriceData($priceP0, $priceP1): bool
    {
        // 检查价格是否为数值
        if (!is_numeric($priceP0) || !is_numeric($priceP1)) {
            return false;
        }

        // 检查价格是否为正数
        if ($priceP0 <= 0 || $priceP1 <= 0) {
            return false;
        }

        // 检查价格变化是否合理（避免极端变化）
        $priceRatio = $priceP1 / $priceP0;
        if ($priceRatio < 0.01 || $priceRatio > 100) {
            return false;
        }

        return true;
    }

    /**
     * 计算智能默认动能分数
     */
    private function calculateDefaultMomentumScore(string $symbol, $priceP0, $priceP1): float
    {
        // 如果两个价格都无效，使用基于代币符号的差异化默认分数
        if (!is_numeric($priceP0) && !is_numeric($priceP1)) {
            // 基于代币符号生成差异化分数（避免所有代币都是相同分数）
            $hash = crc32($symbol);
            $baseScore = 45.0 + (($hash % 20) / 10.0); // 45.0-47.0之间的随机分数
            return round($baseScore, 1);
        }

        // 如果只有一个价格有效，基于该价格估算
        if (is_numeric($priceP0) && $priceP0 > 0 && (!is_numeric($priceP1) || $priceP1 <= 0)) {
            // 基于初始价格估算（价格越高，可能越稳定）
            $normalizedPrice = min($priceP0, 1.0); // 标准化到0-1
            $baseScore = 45.0 + ($normalizedPrice * 10.0); // 45-55分范围
            return round($baseScore, 1);
        }

        if (is_numeric($priceP1) && $priceP1 > 0 && (!is_numeric($priceP0) || $priceP0 <= 0)) {
            // 基于后续价格估算
            $normalizedPrice = min($priceP1, 1.0);
            $baseScore = 45.0 + ($normalizedPrice * 10.0);
            return round($baseScore, 1);
        }

        // 如果价格变化不合理，使用基于代币符号的保守分数
        $hash = crc32($symbol);
        $baseScore = 48.0 + (($hash % 10) / 10.0); // 48.0-49.0之间的保守分数
        return round($baseScore, 1);
    }

    /**
     * 保存预测结果到数据库
     */
    private function savePredictionsToDatabase(array $predictions, string $roundId): int
    {
        // 保存预测结果到数据库
        $gameRound = GameRound::where('round_id', $roundId)->first();

        if (!$gameRound) {
            $gameRound = GameRound::create([
                'round_id' => $roundId,
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
                Log::error('[PredictRoundJobDispatcher] 保存单个预测记录失败', [
                    'round_id' => $roundId,
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
    private function broadcastPredictionEvent(array $predictions, string $roundId): void
    {
        try {
            event(new HybridPredictionUpdated($predictions, $roundId, 'hybrid_prediction', 'hybrid_edge_v1'));
        } catch (\Exception $broadcastError) {
            Log::error('[PredictRoundJobDispatcher] 事件广播失败', [
                'round_id' => $roundId,
                'broadcast_error' => $broadcastError->getMessage()
            ]);
        }
    }

    /**
     * 基于代币历史表现获取动能分数
     */
    private function getHistoricalMomentumScore(string $symbol): float
    {
        // 基于代币符号的历史表现给予差异化分数
        // 这里可以根据实际需求从数据库获取历史数据
        $historicalScores = [
            'PENGU' => 48.5, // 历史表现较差
            'APT' => 51.2,   // 历史表现中等
            'MOVE' => 52.8,  // 历史表现较好
            'IO' => 49.7,    // 历史表现中等偏下
            'BERA' => 51.5,  // 历史表现中等偏上
        ];

        return $historicalScores[$symbol] ?? 50.0;
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
