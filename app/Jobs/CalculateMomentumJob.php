<?php

namespace App\Jobs;

use App\Events\HybridPredictionUpdated;
use App\Models\GameRound;
use App\Models\HybridRoundPredict;
use App\Repositories\TokenPriceRepository;
use App\Services\EloRatingEngine;
use App\Services\ScoreMixer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CalculateMomentumJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected $roundId;

    protected $symbols;

    protected $chainId;

    protected $tokenPriceRepository;

    public $tries = 3; // 最大重试次数

    public $backoff = [10, 30, 60]; // 重试延迟（秒）

    /**
     * 创建一个新的 Job 实例。
     *
     * @param  string  $roundId  游戏回合 ID
     * @param  array  $symbols  本局游戏的代币符号数组
     * @param  string  $chainId  链 ID
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
        TokenPriceRepository $tokenPriceRepository,
        EloRatingEngine $eloRatingEngine,
        ScoreMixer $scoreMixer
    ): void {
        $startTime = microtime(true);

        try {
            // 保存TokenPriceRepository实例供后续使用
            $this->tokenPriceRepository = $tokenPriceRepository;

            // 从数据库获取价格数据（保持兼容性）
            $priceData = $tokenPriceRepository->getLatestPricesForTokens($this->symbols, 2);

            // 计算动能分数
            $momScore = $this->calculateMomentumScoresFromDatabase($priceData);

            // 计算 Elo 机率
            $eloProb = $eloRatingEngine->probabilities($this->symbols);

            // 混合预测分数
            $predictions = $scoreMixer->mix($eloProb, $momScore);

            if (empty($predictions)) {
                Log::error('[CalculateMomentumJob] 分数混合结果为空，不写入数据库。', [
                    'round_id' => $this->roundId,
                    'elo_prob' => $eloProb,
                    'mom_score' => $momScore,
                ]);

                // 标记失败
                $this->markPredictionAsFailed();

                return;
            }

            // 保存预测结果到数据库
            $savedCount = $this->savePredictionsToDatabase($predictions);

            if ($savedCount === 0) {
                Log::error('[CalculateMomentumJob] 所有预测记录保存失败', [
                    'round_id' => $this->roundId,
                ]);

                // 标记失败
                $this->markPredictionAsFailed();

                return;
            }

            // 缓存预测结果
            Cache::put("hybrid_prediction:{$this->roundId}", $predictions, 30);

            // 广播事件
            $this->broadcastPredictionEvent($predictions);

        } catch (\Throwable $e) {
            $endTime = microtime(true);
            $executionTime = round(($endTime - $startTime) * 1000, 2);

            Log::error('[CalculateMomentumJob] 任务执行时发生严重错误', [
                'round_id' => $this->roundId,
                'symbols' => $this->symbols,
                'execution_time_ms' => $executionTime,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'attempt' => $this->attempts(),
            ]);

            // 如果是最后一次尝试，标记为失败
            if ($this->attempts() >= $this->tries) {
                $this->markPredictionAsFailed();
            }

            throw $e;
        }
    }

    /**
     * 从数据库计算动能分数（基于线性回归趋势分析）
     *
     * @param  array  $priceData  价格数据数组，格式: ['SYMBOL' => Collection|null, ...]
     * @return array 动能分数数组
     */
    private function calculateMomentumScoresFromDatabase(array $priceData): array
    {
        // 获取历史价格序列用于线性回归计算
        $historicalPriceData = $this->tokenPriceRepository->getHistoricalPriceSequences($this->symbols, 5);

        $trendSlopes = [];
        $validSlopes = 0;
        $invalidSlopes = 0;

        // 步骤一：为每个代币计算趋势斜率
        foreach ($this->symbols as $symbol) {
            $symbolUpper = strtoupper($symbol);
            $prices = $historicalPriceData[$symbolUpper] ?? null;

            if ($prices && $prices->count() >= 3) {
                // 计算线性回归斜率
                $slope = $this->calculateLinearRegressionSlope($prices);

                if ($slope !== null) {
                    $trendSlopes[$symbol] = $slope;
                    $validSlopes++;

                    Log::info('[CalculateMomentumJob] 计算趋势斜率成功', [
                        'symbol' => $symbol,
                        'slope' => $slope,
                        'data_points' => $prices->count(),
                    ]);
                } else {
                    $invalidSlopes++;
                    Log::warning('[CalculateMomentumJob] 趋势斜率计算失败', [
                        'symbol' => $symbol,
                        'data_points' => $prices->count(),
                    ]);
                }
            } else {
                $invalidSlopes++;
                Log::warning('[CalculateMomentumJob] 历史价格数据不足', [
                    'symbol' => $symbol,
                    'data_points' => $prices ? $prices->count() : 0,
                ]);
            }
        }

        // 步骤二：如果没有有效的斜率数据，使用备用方案
        if (empty($trendSlopes)) {
            Log::warning('[CalculateMomentumJob] 没有有效的趋势斜率数据，使用备用方案', [
                'valid_slopes' => $validSlopes,
                'invalid_slopes' => $invalidSlopes,
            ]);

            return $this->calculateFallbackMomentumScores();
        }

        // 步骤三：基于斜率进行排名和分数映射
        $momentumScores = $this->mapSlopesToScores($trendSlopes);

        Log::info('[CalculateMomentumJob] 动能分数计算完成', [
            'valid_slopes' => $validSlopes,
            'invalid_slopes' => $invalidSlopes,
            'momentum_scores' => $momentumScores,
        ]);

        return $momentumScores;
    }

    /**
     * 计算线性回归斜率
     *
     * @param  Collection  $prices  价格数据集合（按时间升序排列）
     * @return float|null 斜率值，如果计算失败返回null
     */
    private function calculateLinearRegressionSlope($prices): ?float
    {
        try {
            $n = $prices->count();
            if ($n < 3) {
                return null; // 至少需要3个数据点
            }

            // 准备数据：x为时间点（0, 1, 2, ...），y为价格
            $xValues = [];
            $yValues = [];

            foreach ($prices as $index => $price) {
                $xValues[] = $index; // 时间点：0, 1, 2, 3, 4
                $yValues[] = (float) $price->price_usd;
            }

            // 计算线性回归斜率：slope = (N * Σ(xy) - Σx * Σy) / (N * Σ(x²) - (Σx)²)
            $sumX = array_sum($xValues);
            $sumY = array_sum($yValues);
            $sumXY = 0;
            $sumX2 = 0;

            for ($i = 0; $i < $n; $i++) {
                $sumXY += $xValues[$i] * $yValues[$i];
                $sumX2 += $xValues[$i] * $xValues[$i];
            }

            $denominator = ($n * $sumX2) - ($sumX * $sumX);

            if (abs($denominator) < 1e-10) {
                // 分母接近零，无法计算斜率
                return null;
            }

            $slope = (($n * $sumXY) - ($sumX * $sumY)) / $denominator;

            return $slope;

        } catch (\Exception $e) {
            Log::error('[CalculateMomentumJob] 线性回归斜率计算失败', [
                'error' => $e->getMessage(),
                'data_points' => $prices->count(),
            ]);

            return null;
        }
    }

    /**
     * 将斜率映射到0-100的分数范围
     *
     * @param  array  $trendSlopes  斜率数组 ['SYMBOL' => slope, ...]
     * @return array 分数数组 ['SYMBOL' => score, ...]
     */
    private function mapSlopesToScores(array $trendSlopes): array
    {
        if (empty($trendSlopes)) {
            return [];
        }

        // 按斜率降序排序
        arsort($trendSlopes);

        $sortedSymbols = array_keys($trendSlopes);
        $tokenCount = count($sortedSymbols);
        $momentumScores = [];

        // 线性映射：第一名100分，最后一名0分
        foreach ($sortedSymbols as $index => $symbol) {
            if ($tokenCount > 1) {
                // 线性插值：100 - (index / (tokenCount - 1)) * 100
                $score = 100 - ($index / ($tokenCount - 1)) * 100;
            } else {
                // 如果只有一个代币，给予中性分数
                $score = 50;
            }

            $momentumScores[$symbol] = round($score, 1);
        }

        // 为没有斜率数据的代币分配默认分数
        foreach ($this->symbols as $symbol) {
            if (! isset($momentumScores[$symbol])) {
                $momentumScores[$symbol] = $this->calculateDefaultMomentumScore($symbol, null, null);
            }
        }

        return $momentumScores;
    }

    /**
     * 备用动能分数计算方案（当线性回归失败时）
     *
     * @return array 动能分数数组
     */
    private function calculateFallbackMomentumScores(): array
    {
        $momentumScores = [];

        foreach ($this->symbols as $symbol) {
            $momentumScores[$symbol] = $this->calculateDefaultMomentumScore($symbol, null, null);
        }

        Log::info('[CalculateMomentumJob] 使用备用动能分数计算方案', [
            'symbols' => $this->symbols,
            'fallback_scores' => $momentumScores,
        ]);

        return $momentumScores;
    }

    /**
     * 验证价格数据是否有效
     *
     * @param  mixed  $priceP0  初始价格
     * @param  mixed  $priceP1  后续价格
     */
    private function isValidPriceData($priceP0, $priceP1): bool
    {
        // 检查价格是否为数值
        if (! is_numeric($priceP0) || ! is_numeric($priceP1)) {
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
     *
     * @param  string  $symbol  代币符号
     * @param  mixed  $priceP0  初始价格
     * @param  mixed  $priceP1  后续价格
     */
    private function calculateDefaultMomentumScore(string $symbol, $priceP0, $priceP1): float
    {
        // 如果两个价格都无效，使用基于代币符号的差异化默认分数
        if (! is_numeric($priceP0) && ! is_numeric($priceP1)) {
            // 基于代币符号生成差异化分数（避免所有代币都是相同分数）
            $hash = crc32($symbol);
            $baseScore = 45.0 + (($hash % 20) / 10.0); // 45.0-47.0之间的随机分数

            return round($baseScore, 1);
        }

        // 如果只有一个价格有效，基于该价格估算
        if (is_numeric($priceP0) && $priceP0 > 0 && (! is_numeric($priceP1) || $priceP1 <= 0)) {
            // 基于初始价格估算（价格越高，可能越稳定）
            $normalizedPrice = min($priceP0, 1.0); // 标准化到0-1
            $baseScore = 45.0 + ($normalizedPrice * 10.0); // 45-55分范围

            return round($baseScore, 1);
        }

        if (is_numeric($priceP1) && $priceP1 > 0 && (! is_numeric($priceP0) || $priceP0 <= 0)) {
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
     * 获取价格无效的原因
     *
     * @param  mixed  $priceP0  初始价格
     * @param  mixed  $priceP1  后续价格
     */
    private function getInvalidPriceReason($priceP0, $priceP1): string
    {
        if (! is_numeric($priceP0) || ! is_numeric($priceP1)) {
            return '价格数据非数值';
        }

        if ($priceP0 <= 0 || $priceP1 <= 0) {
            return '价格为零或负数';
        }

        $priceRatio = $priceP1 / $priceP0;
        if ($priceRatio < 0.01 || $priceRatio > 100) {
            return '价格变化过于极端';
        }

        return '未知原因';
    }

    /**
     * 保存预测结果到数据库
     */
    private function savePredictionsToDatabase(array $predictions): int
    {
        // 保存预测结果到数据库
        $gameRound = GameRound::where('round_id', $this->roundId)->first();

        if (! $gameRound) {
            $gameRound = GameRound::create([
                'round_id' => $this->roundId,
            ]);
        }

        $gameRoundNumericId = $gameRound->id;

        $savedCount = 0;
        foreach ($predictions as $predictionData) {
            try {
                HybridRoundPredict::updateOrCreate(
                    [
                        'game_round_id' => $gameRoundNumericId,
                        'token_symbol' => $predictionData['symbol'],
                    ],
                    array_merge($predictionData, [
                        'game_round_id' => $gameRoundNumericId,
                        'token_symbol' => $predictionData['symbol'],
                    ])
                );
                $savedCount++;
            } catch (\Exception $saveError) {
                Log::error('[CalculateMomentumJob] 保存单个预测记录失败', [
                    'round_id' => $this->roundId,
                    'game_round_numeric_id' => $gameRoundNumericId,
                    'prediction_data' => $predictionData,
                    'error' => $saveError->getMessage(),
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
            Log::error('[CalculateMomentumJob] 事件广播失败', [
                'round_id' => $this->roundId,
                'broadcast_error' => $broadcastError->getMessage(),
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
                'symbols' => $this->symbols,
            ], 300); // 缓存5分钟

            Log::error('[CalculateMomentumJob] 已标记预测失败状态', [
                'round_id' => $this->roundId,
                'cache_key' => "prediction_failed:{$this->roundId}",
            ]);
        } catch (\Exception $e) {
            Log::error('[CalculateMomentumJob] 标记预测失败状态时出错', [
                'round_id' => $this->roundId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 基于代币历史表现获取动能分数
     *
     * @param  string  $symbol  代币符号
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

    public function failed(\Throwable $exception): void
    {
        Log::error('[CalculateMomentumJob] 任务执行失败', [
            'round_id' => $this->roundId,
            'symbols' => $this->symbols,
            'chain_id' => $this->chainId,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'final_attempt' => $this->attempts(),
        ]);

        // 清理缓存
        $cacheKey = "price_p0:{$this->roundId}";
        Cache::forget($cacheKey);

        // 标记预测失败
        $this->markPredictionAsFailed();
    }
}
