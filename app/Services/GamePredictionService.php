<?php

namespace App\Services;

use App\Events\PredictionUpdated;
use App\Models\GameRound;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 核心遊戲預測服務
 *
 * 演算法版本: v8.3 - h2h_breakeven_prediction
 * 策略: 保本優先，基於歷史對戰關係 (H2H) 和嚴格的風險控制。
 * 核心邏輯:
 * 1.  **絕對分數 (Absolute Score)**: 完全由歷史保本率 (top3_rate) 決定，輔以少量數據可靠性加分。
 * 2.  **相對分數 (Relative Score)**: 基於當前對手組合的 H2H 歷史平均勝率。
 * 3.  **動態權重 (Dynamic Weighting)**: 根據 H2H 數據的覆蓋完整度，智能調整絕對分和相對分的權重。
 * 4.  **【v8.3 新增】動態 H2H 門檻**: 當 H2H 覆蓋率低於可靠性門檻時，進一步降低 H2H 權重，避免被不可靠數據誤導。
 * 5.  **風險調整 (Risk Adjustment)**: 對歷史表現不穩定的代幣施加雙重懲罰，最終排名以此為準。
 */
class GamePredictionService
{
    public function __construct(
        private DexPriceClient $dexPriceClient
    ) {
    }

    /**
     * 从快取中获取当前线上的活跃策略参数。
     * 如果快取不存在，则从数据库读取、写入快取、然后返回。
     */
    private function getActiveStrategyParameters(): array
    {
        return Cache::rememberForever(config('backtest.cache_key'), function () {
            // Log::info("CACHE MISS: Reading prediction strategy from DATABASE.");

            $strategy = \App\Models\PredictionStrategy::where('status', 'active')->latest('activated_at')->first();

            if ($strategy) {
                return $strategy->getParameters();
            }

            // 如果数据库中没有 active 策略，返回一个安全的默认值
            return [
                'elo_weight' => 0.65,
                'momentum_weight' => 0.35,
                'h2h_min_games_threshold' => 5,
                'enhanced_stability_penalty' => 0.25,
            ];
        });
    }

    //================== 核心參數配置 ==================

    // --- 基礎配置 ---
    public const CACHE_DURATION_MINUTES = 10;           // 預測緩存時長（分鐘）
    public const ANALYSIS_ROUNDS_LIMIT = 120;            // 分析歷史數據的輪次數量
    public const API_DELAY_MICROSECONDS = 200000;        // API調用間隔（微秒，0.2秒）

    // --- H2H 演算法核心權重與閾值 ---
    public const H2H_DEFAULT_SCORE = 50;                 // 無法計算H2H分數時的基礎分（通常會被智能回退覆蓋）
    public const MIN_H2H_COVERAGE_WEIGHT = 0.15;         // H2H數據覆蓋率貢獻的最低權重（優化：從0.2降低到0.15）
    public const MAX_H2H_COVERAGE_WEIGHT = 0.45;         // H2H數據覆蓋率貢獻的最高權重（優化：從0.6降低到0.45）

    // --- 動態 H2H 門檻與加權參數 (v8.3 新增) ---
    public const H2H_RELIABILITY_THRESHOLD = 0.5;        // H2H 可靠性門檻：低於此覆蓋率時進一步降低權重
    public const H2H_LOW_COVERAGE_PENALTY = 0.8;         // 低覆蓋率懲罰係數：對不可靠的H2H權重進行折扣

    // --- 風險控制與市場影響 ---
    // 注意：以下参数现在从动态策略中获取，而不是使用常量
    public const STABILITY_THRESHOLD_MULTIPLIER = 1.3;   // 識別為「高風險」的波動性倍數閾值
    public const HIGH_RISK_PENALTY_FACTOR = 0.90;        // 對「高風險」代幣的額外懲罰係數 (乘以0.9)
    public const MARKET_ADJUSTMENT_WEIGHT = 0.2;         // 市場動量分數的影響權重

    // --- 置信度計算參數 ---
    public const BASE_CONFIDENCE = 50;                   // 基礎置信度 (%)
    public const CONFIDENCE_PER_GAME = 1.5;              // 每局遊戲貢獻的置信度 (%)
    public const MAX_DATA_CONFIDENCE = 35;               // 數據量最大貢獻置信度 (%)
    public const STABILITY_BONUS_THRESHOLD = 10;         // 穩定性獎勵閾值
    public const MAX_CONSISTENCY_BONUS = 5;              // 一致性最大獎勵 (%)

    /**
     * 從 composer.json 獲取演算法版本資訊
     */
    private function getAlgorithmInfo(): array
    {
        $composerPath = base_path('composer.json');
        if (! file_exists($composerPath)) {
            // 如果 composer.json 不存在，返回開發模式的預設值
            return [
                'version' => 'dev',
                'name' => 'h2h_breakeven_prediction',
                'description' => '保本優先策略：基於H2H對戰關係的終極穩定型預測算法',
            ];
        }

        $composerData = json_decode(file_get_contents($composerPath), true);
        $gamePredictionConfig = $composerData['extra']['game-prediction'] ?? [];

        return [
            'version' => $gamePredictionConfig['algorithm-version'] ?? 'dev', // 未設定時也返回 'dev'
            'name' => $gamePredictionConfig['algorithm-name'] ?? 'h2h_breakeven_prediction',
            'description' => $gamePredictionConfig['algorithm-description'] ?? '保本優先策略：基於H2H對戰關係的終極穩定型預測算法',
        ];
    }

    /**
     * 為指定代幣列表生成預測分析數據並快取
     */
    public function generateAndCachePrediction(array $tokens, string $roundId): bool
    {
        try {
            $analysisData = $this->generatePredictionData($tokens);

            if (empty($analysisData)) {
                Log::warning('生成預測數據失敗，分析數據為空', ['round_id' => $roundId]);

                return false;
            }

            $algorithmInfo = $this->getAlgorithmInfo();
            $cacheData = [
                'round_id' => $roundId,
                'analysis_data' => $analysisData,
                'generated_at' => now()->toISOString(),
                'algorithm' => $algorithmInfo['name'] . '_' . $algorithmInfo['version'],
                'algorithm_description' => $algorithmInfo['description'],
                'analysis_rounds_count' => self::ANALYSIS_ROUNDS_LIMIT,
            ];

            Cache::put('game:current_prediction', $cacheData, now()->addMinutes(self::CACHE_DURATION_MINUTES));

            // 同时保存到 PredictionResult 表
            $this->saveToPredictionResultTable($analysisData, $roundId);

            Log::info("預測分析完成並已快取", [
                'round_id' => $roundId,
                'algorithm' => $cacheData['algorithm'],
                'tokens_count' => count($analysisData),
            ]);

            // 广播预测数据更新事件到WebSocket客户端
            // 构造与current-analysis API相同的数据结构
            try {
                // 获取当前游戏轮次信息用于构造meta数据
                $roundInfo = Cache::get('game:current_round');

                $broadcastData = [
                    'success' => true,
                    'data' => $analysisData,
                    'meta' => [
                        'round_id' => $roundId,
                        'status' => $roundInfo['status'] ?? 'bet',
                        'current_tokens' => array_column($analysisData, 'symbol'),
                        'analysis_rounds_count' => self::ANALYSIS_ROUNDS_LIMIT,
                        'prediction_algorithm' => $algorithmInfo['name'] . '_' . $algorithmInfo['version'],
                        'algorithm_description' => $algorithmInfo['description'],
                        'timestamp' => $roundInfo['timestamp'] ?? now()->toISOString(),
                        'generated_at' => now()->toISOString(),
                        'source' => 'websocket_prediction',
                    ],
                ];

                // 使用PredictionUpdated事件广播完整的数据结构
                broadcast(new PredictionUpdated($broadcastData, $roundId, 'current_analysis', 'game_prediction_service'));
                Log::info('📡 预测数据已广播到WebSocket客户端（与API结构一致）', [
                    'round_id' => $roundId,
                    'tokens_count' => count($analysisData),
                    'data_structure' => 'current_analysis_compatible',
                ]);
            } catch (\Exception $broadcastError) {
                Log::error('广播预测数据失败', [
                    'round_id' => $roundId,
                    'error' => $broadcastError->getMessage(),
                ]);
            }

            // 记录分析结果（Service分析）
            // Log::info('[GamePredictionService] Service分析分数', [
            //     'analysis_data' => $analysisData
            // ]);

            return true;
        } catch (\Exception $e) {
            Log::error('生成預測分析數據時發生嚴重錯誤', [
                'round_id' => $roundId,
                'tokens' => $tokens,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * 從快取獲取預測數據
     */
    public function getCachedPrediction(): ?array
    {
        try {
            return Cache::get('game:current_prediction');
        } catch (\Exception $e) {
            Log::error('獲取快取預測數據失敗', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * 生成預測數據的核心邏輯
     */
    private function generatePredictionData(array $tokens): array
    {
        $tokens = array_unique(array_map('strtoupper', $tokens));

        $recentRounds = GameRound::with('roundResults')
            ->orderBy('created_at', 'desc')
            ->limit(self::ANALYSIS_ROUNDS_LIMIT)
            ->get();

        if ($recentRounds->isEmpty()) {
            Log::warning('資料庫中沒有歷史數據可用於預測分析');

            return [];
        }

        $tokenStats = $this->analyzeHistoricalPerformance($tokens, $recentRounds);
        $this->calculateHeadToHeadScores($tokenStats);
        $h2hCoverageRatio = $this->calculateH2HCoverageRatio($tokenStats);
        $analysisData = $this->enrichWithMarketData($tokenStats, $h2hCoverageRatio);

        return $analysisData;
    }

    /**
     * 保存预测数据到 PredictionResult 表
     */
    private function saveToPredictionResultTable(array $analysisData, string $roundId): void
    {
        try {
            // 获取或创建游戏轮次记录
            $gameRound = \App\Models\GameRound::where('round_id', $roundId)->first();
            if (!$gameRound) {
                $gameRound = \App\Models\GameRound::create([
                    'round_id' => $roundId,
                ]);
            }

            // 删除该轮次的旧预测数据（如果存在）
            \App\Models\PredictionResult::where('game_round_id', $gameRound->id)->delete();

            // 批量插入新的预测数据
            $predictionRecords = [];
            foreach ($analysisData as $tokenData) {
                $predictionRecords[] = [
                    'game_round_id' => $gameRound->id,
                    'token' => $tokenData['symbol'],
                    'predict_rank' => $tokenData['predicted_rank'],
                    'predict_score' => $tokenData['predicted_final_value'] ?? $tokenData['absolute_score'] ?? 0,
                    'elo_score' => $tokenData['absolute_score'] ?? 0,
                    'momentum_score' => $tokenData['market_momentum_score'] ?? 0,
                    'volume_score' => $tokenData['final_prediction_score'] ?? 0,
                    'norm_elo' => $tokenData['absolute_score'] ?? 0,
                    'norm_momentum' => $tokenData['market_momentum_score'] ?? 0,
                    'norm_volume' => $tokenData['final_prediction_score'] ?? 0,
                    'used_weights' => ['elo' => 0.6, 'momentum' => 0.3, 'volume' => 0.1], // 默认权重
                    'used_normalization' => ['elo' => 'z-score', 'momentum' => 'min-max', 'volume' => 'z-score'], // 默认标准化
                    'strategy_tag' => 'game_prediction_service',
                    'config_snapshot' => [
                        'strategy_tag' => 'game_prediction_service',
                        'round_id' => $roundId,
                        'timestamp' => now()->toISOString(),
                    ],
                ];
            }

            // 使用 create 方法而不是 insert，确保触发 Eloquent 的 casts 转换
            foreach ($predictionRecords as $record) {
                \App\Models\PredictionResult::create($record);
            }

            Log::info('✅ 预测数据已保存到 PredictionResult 表', [
                'round_id' => $roundId,
                'game_round_id' => $gameRound->id,
                'predictions_count' => count($predictionRecords),
            ]);

        } catch (\Exception $e) {
            Log::error('保存预测数据到 PredictionResult 表失败', [
                'round_id' => $roundId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 分析歷史表現數據 (包含回測所需指標)
     */
    private function analyzeHistoricalPerformance(array $tokens, $recentRounds): array
    {
        $tokenStats = [];
        foreach ($tokens as $symbol) {
            $tokenStats[$symbol] = [
                'symbol' => $symbol,
                'total_games' => 0,
                'wins' => 0,
                'top3' => 0,
                'rank_sum' => 0,
                'value_sum' => 0,
                'value_history' => [],
                'h2h_stats' => [],
            ];
        }

        foreach ($recentRounds as $round) {
            $historicalTokensInRound = $round->roundResults->pluck('token_symbol')->map('strtoupper')->all();
            $historicalResultsMap = $round->roundResults->keyBy(fn ($result) => strtoupper($result->token_symbol));
            $competingTokensInHistory = array_intersect($tokens, $historicalTokensInRound);

            if (count($competingTokensInHistory) > 1) {
                foreach ($competingTokensInHistory as $tokenA) {
                    foreach ($competingTokensInHistory as $tokenB) {
                        if ($tokenA === $tokenB) {
                            continue;
                        }

                        if (! isset($tokenStats[$tokenA]['h2h_stats'][$tokenB])) {
                            $tokenStats[$tokenA]['h2h_stats'][$tokenB] = ['wins' => 0, 'losses' => 0, 'games' => 0];
                        }
                        if ($historicalResultsMap[$tokenA]->rank < $historicalResultsMap[$tokenB]->rank) {
                            $tokenStats[$tokenA]['h2h_stats'][$tokenB]['wins']++;
                        } else {
                            $tokenStats[$tokenA]['h2h_stats'][$tokenB]['losses']++;
                        }
                        $tokenStats[$tokenA]['h2h_stats'][$tokenB]['games']++;
                    }
                }
            }

            foreach ($round->roundResults as $result) {
                $symbol = strtoupper($result->token_symbol);
                if (! isset($tokenStats[$symbol])) {
                    continue;
                }

                $stats = &$tokenStats[$symbol];
                $stats['total_games']++;
                $stats['rank_sum'] += $result->rank;
                $stats['value_sum'] += floatval($result->value);
                $stats['value_history'][] = floatval($result->value);

                if ($result->rank === 1) {
                    $stats['wins']++;
                }
                if ($result->rank <= 3) {
                    $stats['top3']++;
                }
            }
        }

        // 計算並格式化所有指標
        foreach ($tokenStats as $symbol => &$stats) {
            if ($stats['total_games'] > 0) {
                $stats['win_rate'] = ($stats['wins'] / $stats['total_games']) * 100;
                $stats['top3_rate'] = ($stats['top3'] / $stats['total_games']) * 100;
                $stats['avg_rank'] = $stats['rank_sum'] / $stats['total_games'];
                $avg_value = $stats['value_sum'] / $stats['total_games'];
                $stats['avg_value'] = $avg_value;

                if (count($stats['value_history']) > 1) {
                    $variance = array_reduce($stats['value_history'], function ($carry, $item) use ($avg_value) {
                        return $carry + pow($item - $avg_value, 2);
                    }, 0);
                    $stats['value_stddev'] = sqrt($variance / (count($stats['value_history']) - 1));
                } else {
                    $stats['value_stddev'] = 0;
                }
            } else {
                // 如果沒有歷史數據，給予所有指標預設值
                $stats['win_rate'] = 0;
                $stats['top3_rate'] = 0;
                $stats['avg_rank'] = 3; // 中間排名
                $stats['avg_value'] = 0;
                $stats['value_stddev'] = 0;
            }

            // 格式化輸出，方便查看和使用
            $stats['win_rate'] = round($stats['win_rate'], 1);
            $stats['top3_rate'] = round($stats['top3_rate'], 1);
            $stats['avg_rank'] = round($stats['avg_rank'], 2);
            $stats['avg_value'] = round($stats['avg_value'], 4);
            $stats['value_stddev'] = round($stats['value_stddev'], 4);
        }

        return $tokenStats;
    }

    /**
     * 計算 H2H 相對強度分數
     */
    private function calculateHeadToHeadScores(array &$tokenStats): void
    {
        $currentTokenSymbols = array_keys($tokenStats);
        $strategyParams = $this->getActiveStrategyParameters();
        $h2hMinGamesThreshold = $strategyParams['h2h_min_games_threshold'] ?? 5;

        foreach ($tokenStats as $symbol => &$stats) {
            $totalWinRate = 0;
            $validOpponentCount = 0;

            foreach ($currentTokenSymbols as $opponent) {
                if ($symbol === $opponent) {
                    continue;
                }
                $h2hData = $stats['h2h_stats'][$opponent] ?? null;
                if ($h2hData && $h2hData['games'] >= $h2hMinGamesThreshold) {
                    $totalWinRate += $h2hData['wins'] / $h2hData['games'];
                    $validOpponentCount++;
                }
            }

            if ($validOpponentCount > 0) {
                $stats['h2h_score'] = ($totalWinRate / $validOpponentCount) * 100;
            } else {
                // H2H數據不足時，基於絕對實力智能回退
                $absoluteScore = $this->calculateAbsoluteScore($stats);
                $fallbackScore = ($absoluteScore / 105) * 50 + 25; // 將0-105分的absolute_score映射到25-75分區間
                $stats['h2h_score'] = max(25, min(75, $fallbackScore));
            }
        }
    }

    /**
     * 計算H2H數據覆蓋率
     */
    private function calculateH2HCoverageRatio(array $tokenStats): float
    {
        $totalPossiblePairs = 0;
        $validH2HPairs = 0;
        $tokens = array_keys($tokenStats);
        $tokenCount = count($tokens);
        $strategyParams = $this->getActiveStrategyParameters();
        $h2hMinGamesThreshold = $strategyParams['h2h_min_games_threshold'] ?? 5;

        if ($tokenCount < 2) {
            return 0;
        }

        for ($i = 0; $i < $tokenCount; $i++) {
            for ($j = $i + 1; $j < $tokenCount; $j++) {
                $totalPossiblePairs++;
                $h2hDataA = $tokenStats[$tokens[$i]]['h2h_stats'][$tokens[$j]] ?? null;
                $h2hDataB = $tokenStats[$tokens[$j]]['h2h_stats'][$tokens[$i]] ?? null;
                if ($h2hDataA && $h2hDataB && $h2hDataA['games'] >= $h2hMinGamesThreshold && $h2hDataB['games'] >= $h2hMinGamesThreshold) {
                    $validH2HPairs++;
                }
            }
        }

        return $totalPossiblePairs > 0 ? $validH2HPairs / $totalPossiblePairs : 0;
    }

    /**
     * 獲取市場數據並合併到分析結果
     */
    private function enrichWithMarketData(array $tokenStats, float $h2hCoverageRatio): array
    {
        // 批量获取市场数据，提高效率
        $tokenSymbols = array_keys($tokenStats);
        $batchPriceChanges = [];

        try {
            $batchMarketData = $this->dexPriceClient->batchMarketData($tokenSymbols);

            // 转换为价格变化数据格式
            foreach ($batchMarketData as $symbol => $data) {
                $batchPriceChanges[$symbol] = [
                    'change_5m' => $data['change_5m'] ?? null,
                    'change_1h' => $data['change_1h'] ?? null,
                    'change_4h' => $data['change_4h'] ?? null,
                    'change_24h' => $data['change_24h'] ?? null,
                ];
            }
        } catch (\Exception $e) {
            Log::warning("批量獲取市場數據失敗，將使用預設值", ['error' => $e->getMessage()]);
        }

        $analysisData = [];
        foreach ($tokenStats as $symbol => $stats) {
            $symbolUpper = strtoupper($symbol);
            $marketData = $batchPriceChanges[$symbolUpper] ?? [];

            $mergedData = array_merge($stats, $marketData, ['symbol' => $symbol]);
            $analysisData[] = $this->calculateEnhancedPredictionScore($mergedData, $tokenStats, $h2hCoverageRatio);
        }

        // 核心排序邏輯：基於風險調整後分數排序（穩定性優先）
        usort($analysisData, function ($a, $b) {
            $scoreComparison = $b['risk_adjusted_score'] <=> $a['risk_adjusted_score'];

            return $scoreComparison === 0 ? ($b['predicted_final_value'] <=> $a['predicted_final_value']) : $scoreComparison;
        });

        foreach ($analysisData as $index => &$data) {
            $data['predicted_rank'] = $index + 1;

            // --- 新增此行 ---
            $data['rank_confidence'] = $this->calculateRankConfidence($data);
        }

        // 记录分析结果（Service分析）
        // Log::info('[GamePredictionService] Service分析分数', [
        //     'analysis_data' => $analysisData
        // ]);

        return $analysisData;
    }

    /**
     * 計算最終的增強預測評分
     */
    private function calculateEnhancedPredictionScore(array $data, array $allTokenStats, float $h2hCoverageRatio): array
    {
        // 核心預測分數計算
        $absoluteScore = $this->calculateAbsoluteScore($data);
        $relativeScore = $data['h2h_score'] ?? self::H2H_DEFAULT_SCORE;

        // 基礎動態權重計算
        $originalRelativeWeight = self::MIN_H2H_COVERAGE_WEIGHT + ($h2hCoverageRatio * (self::MAX_H2H_COVERAGE_WEIGHT - self::MIN_H2H_COVERAGE_WEIGHT));
        $dynamicRelativeWeight = $originalRelativeWeight;

        // 【v8.3 新增】動態 H2H 門檻與加權：當覆蓋率不足時進一步降低 H2H 權重
        $h2hReliabilityAdjusted = false;
        if ($h2hCoverageRatio < self::H2H_RELIABILITY_THRESHOLD) {
            $dynamicRelativeWeight *= self::H2H_LOW_COVERAGE_PENALTY;
            $h2hReliabilityAdjusted = true;
        }

        $dynamicAbsoluteWeight = 1.0 - $dynamicRelativeWeight;

        $predictedFinalValue = ($absoluteScore * $dynamicAbsoluteWeight) + ($relativeScore * $dynamicRelativeWeight);

        $marketMomentumScore = $this->calculateMarketMomentumScore($data);
        $marketAdjustment = ($marketMomentumScore - 50) * self::MARKET_ADJUSTMENT_WEIGHT;
        $marketAdjustedValue = $predictedFinalValue + $marketAdjustment;

        $riskAdjustedScore = $this->calculateRiskAdjustedScore($marketAdjustedValue, $data, $allTokenStats);

        // 返回所有數據：包含核心預測分數和供分析用的歷史指標
        return array_merge($data, [
            'absolute_score' => round($absoluteScore, 2),
            'relative_score' => round($relativeScore, 2),
            'predicted_final_value' => round($predictedFinalValue, 2),
            'risk_adjusted_score' => round($riskAdjustedScore, 2),
            'market_momentum_score' => round($marketMomentumScore, 2),
            // 【v8.3 調試信息】權重分配與 H2H 可靠性分析
            'h2h_coverage_ratio' => round($h2hCoverageRatio, 3),
            'original_h2h_weight' => round($originalRelativeWeight, 3),
            'final_h2h_weight' => round($dynamicRelativeWeight, 3),
            'h2h_reliability_adjusted' => $h2hReliabilityAdjusted,
        ]);
    }

    /**
     * 計算絕對分數（保本優先策略）
     */
    private function calculateAbsoluteScore(array $data): float
    {
        $top3Rate = $data['top3_rate'] ?? 0;
        $totalGames = $data['total_games'] ?? 0;
        $baseScore = $top3Rate;
        // 每場比賽貢獻 0.1 分的數據可靠性，最多加 5 分
        $dataReliabilityBonus = min(5, $totalGames * 0.1);
        $finalScore = $baseScore + $dataReliabilityBonus;

        return max(0, min(105, $finalScore)); // 最高分可能因可靠性加分超過100
    }

    /**
     * 計算風險調整後分數
     */
    private function calculateRiskAdjustedScore(float $predictedValue, array $data, array $allTokenStats): float
    {
        $valueStddev = $data['value_stddev'] ?? 0;
        if ($valueStddev <= 0.01) {
            return min(100, $predictedValue * 1.1); // 對極度穩定者給予輕微獎勵
        }

        $totalStddev = 0;
        $validCount = 0;
        foreach ($allTokenStats as $tokenData) {
            if (isset($tokenData['value_stddev']) && $tokenData['value_stddev'] > 0) {
                $totalStddev += $tokenData['value_stddev'];
                $validCount++;
            }
        }
        $avgStddev = $validCount > 0 ? $totalStddev / $validCount : 0;

        // --- 優化點2：調整風險因子計算為穩定性獎勵機制 ---
        // 讓波動性懲罰/獎勵的影響力更大，穩定的代幣獲得獎勵
        if ($avgStddev > 0) {
            $riskFactor = (($valueStddev - $avgStddev) / $avgStddev) * 15; // 波動高於平均越多，扣分越多；低於平均越多，加分越多
            $riskAdjustedScore = $predictedValue - $riskFactor;
        } else {
            // 如果無法計算平均波動，使用原有的基礎懲罰
            $strategyParams = $this->getActiveStrategyParameters();
            $enhancedStabilityPenalty = $strategyParams['enhanced_stability_penalty'] ?? 1.5;
            $riskAdjustedScore = $predictedValue / (1 + ($valueStddev * $enhancedStabilityPenalty));
        }

        // 高風險額外懲罰
        if ($avgStddev > 0 && $valueStddev > ($avgStddev * self::STABILITY_THRESHOLD_MULTIPLIER)) {
            $riskAdjustedScore *= self::HIGH_RISK_PENALTY_FACTOR;
        }

        // --- 優化點1：直接給予保本率獎勵 ---
        $top3RateBonus = ($data['top3_rate'] ?? 0) / 2; // 將 top3_rate 的一半作為額外加分
        $riskAdjustedScore += $top3RateBonus;
        // --- 優化結束 ---

        return max(0, min(100, $riskAdjustedScore));
    }

    /**
     * 計算市場動量評分
     */
    private function calculateMarketMomentumScore(array $data): float
    {
        $weights = ['5m' => 0.4, '1h' => 0.3, '4h' => 0.2, '24h' => 0.1];
        $totalWeight = 0;
        $weightedScore = 0;

        foreach ($weights as $tf => $weight) {
            if (isset($data['change_' . $tf]) && is_numeric($data['change_' . $tf])) {
                $change = $data['change_' . $tf];
                // 簡化映射：+/-20% 的變化大致對應 0/100 分
                $score = 50 + ($change * 2.5);
                $weightedScore += $score * $weight;
                $totalWeight += $weight;
            }
        }

        // 如果有可用數據，返回加權平均分；否則返回中性分50
        return $totalWeight > 0 ? $weightedScore / $totalWeight : 50;
    }

    /**
     * 獲取單個代幣的市場數據
     */
    private function getTokenMarketData(string $symbol): array
    {
        $priceChanges = $this->dexPriceClient->getTokenPriceChanges($symbol);

        return [
            'change_5m' => $priceChanges['change_5m'] ?? null,
            'change_1h' => $priceChanges['change_1h'] ?? null,
            'change_4h' => $priceChanges['change_4h'] ?? null,
            'change_24h' => $priceChanges['change_24h'] ?? null,
        ];
    }

    /**
     * 計算排名置信度（基於穩定性和歷史數據質量）
     */
    private function calculateRankConfidence(array $data): float
    {
        $confidence = self::BASE_CONFIDENCE; // 基礎置信度

        // 因子1：歷史數據量
        $totalGames = $data['total_games'] ?? 0;
        if ($totalGames > 0) {
            $dataConfidence = min(self::MAX_DATA_CONFIDENCE, $totalGames * self::CONFIDENCE_PER_GAME);
            $confidence += $dataConfidence;
        }

        // 因子2：歷史表現穩定性 (波動率)
        $valueStddev = $data['value_stddev'] ?? 0;
        if ($valueStddev > 0) {
            // 標準差越小，獎勵越高
            $stabilityBonus = max(0, self::STABILITY_BONUS_THRESHOLD - ($valueStddev * 10)); // 放大stddev影響
            $confidence += $stabilityBonus;
        } elseif ($totalGames > 0) {
            $confidence += 5; // 如果有比賽紀錄且波動為0，給予少量獎勵
        }

        // 因子3：預測為前三名的置信度，由保本率貢獻
        if (($data['predicted_rank'] ?? 4) <= 3) {
            $confidence += ($data['top3_rate'] ?? 0) * 0.1; // top3_rate貢獻最多10%
        }

        // 確保置信度在 0-100% 範圍內
        return round(max(0, min(100, $confidence)), 1);
    }
}
