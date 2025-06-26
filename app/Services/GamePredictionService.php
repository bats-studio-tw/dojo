<?php

namespace App\Services;

use App\Models\GameRound;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class GamePredictionService
{
    // 预测算法核心参数配置
    const CACHE_DURATION_MINUTES = 120;           // 预测缓存时长（分钟）
    const ANALYSIS_ROUNDS_LIMIT = 120;            // 分析历史数据的轮次数量
    const API_DELAY_MICROSECONDS = 200000;        // API调用间隔（微秒，0.2秒）

    // 分数计算权重参数
    const RECENT_VALUE_WEIGHT = 0.7;              // 近期平均分数权重
    const HISTORICAL_VALUE_WEIGHT = 0.3;          // 历史平均分数权重
    const MARKET_INFLUENCE_FACTOR = 0.5;          // 市场调整影响系数
    const RISK_WEIGHT_COEFFICIENT = 0.01;         // 风险权重系数

    // 市场动量权重配置
    const MOMENTUM_WEIGHT_5M = 0.4;               // 5分钟变化权重
    const MOMENTUM_WEIGHT_1H = 0.3;               // 1小时变化权重
    const MOMENTUM_WEIGHT_4H = 0.2;               // 4小时变化权重
    const MOMENTUM_WEIGHT_24H = 0.1;              // 24小时变化权重
    const MOMENTUM_SCORE_WEIGHT = 0.7;            // 动量评分权重
    const VOLUME_SCORE_WEIGHT = 0.3;              // 交易量评分权重

    // 置信度计算参数
    const BASE_CONFIDENCE = 50;                   // 基础置信度（%）
    const CONFIDENCE_PER_GAME = 2;                // 每局游戏贡献的置信度（%）
    const MAX_DATA_CONFIDENCE = 40;               // 数据量最大贡献置信度（%）
    const STABILITY_BONUS_THRESHOLD = 10;         // 稳定性奖励阈值
    const MAX_CONSISTENCY_BONUS = 10;             // 一致性最大奖励（%）

    // 默认分数和阈值
    const DEFAULT_BASE_VALUE = 50.0;              // 无历史数据时的基础分数
    const DEFAULT_PREDICTION_SCORE = 50;          // 默认预测评分
    const MIN_VOLUME_SCORE = 30;                  // 最低交易量评分
    const POSITIVE_CHANGE_BONUS = 10;             // 正向变化奖励分数
    const STABILITY_REWARD_MULTIPLIER = 1.2;      // 稳定性奖励倍数

    // 数据质量管理参数 (v5 新增)
    const MIN_DATA_QUALITY_SCORE = 0.3;           // 数据质量最低保证比例
    const TOTAL_MARKET_DATA_POINTS = 5;           // 总市场数据点数量
    const DATA_QUALITY_LOG_THRESHOLD = 0.8;       // 数据质量日志记录阈值

    // 预测算法权重参数 (v7 基于数据分析优化)
    const HISTORICAL_DATA_WEIGHT = 2.0;           // 历史数据整体权重：信任长期稳定性
    const MARKET_DATA_WEIGHT = 0.5;               // 市场数据整体权重：降低短期噪音影响
    const ENHANCED_STABILITY_PENALTY = 1.5;       // 增强稳定性惩罚因子：更严格的风险控制
    /**
     * 为指定代币列表生成预测分析数据并缓存
     */
    public function generateAndCachePrediction(array $tokens, string $roundId): bool
    {
        try {
            Log::info('开始生成预测分析数据', [
                'round_id' => $roundId,
                'tokens' => $tokens,
                'token_count' => count($tokens)
            ]);

            // 生成预测数据
            $analysisData = $this->generatePredictionData($tokens);

            if (empty($analysisData)) {
                Log::warning('生成预测数据失败', ['round_id' => $roundId]);
                return false;
            }

            // 缓存预测结果，设置过期时间为2小时
            $cacheData = [
                'round_id' => $roundId,
                'analysis_data' => $analysisData,
                'generated_at' => now()->toISOString(),
                'algorithm' => 'data_driven_stability_prediction_v7',
                'algorithm_description' => '基于数据分析优化的稳定性优先预测算法：信任历史稳定性',
                'analysis_rounds_count' => $this->getAnalysisRoundsCount()
            ];

            Cache::put('game:current_prediction', $cacheData, now()->addMinutes(self::CACHE_DURATION_MINUTES));

            // 延迟保存策略：bet阶段只缓存，settled阶段再保存到数据库
            // 这样可以确保使用游戏提供的正确时间创建GameRound
            Log::info('预测数据已缓存，等待结算阶段保存到数据库', [
                'round_id' => $roundId,
                'predictions_count' => count($analysisData)
            ]);

            // 添加新算法的详细日志
            $topThreePredictions = array_slice($analysisData, 0, 3);
            $algorithmSummary = [];

            foreach ($topThreePredictions as $prediction) {
                $algorithmSummary[] = [
                    'symbol' => $prediction['symbol'],
                    'predicted_rank' => $prediction['predicted_rank'],
                    'predicted_value' => $prediction['predicted_final_value'],
                    'risk_adjusted_score' => $prediction['risk_adjusted_score'],
                    'confidence' => $prediction['rank_confidence'],
                    'stability' => $prediction['value_stddev']
                ];
            }

            Log::info('✅ 预测分析数据已生成并缓存 (v7 数据驱动优化算法)', [
                'round_id' => $roundId,
                'algorithm' => 'data_driven_stability_prediction_v7',
                'algorithm_description' => '基于数据分析优化的稳定性优先预测算法：信任历史稳定性',
                'tokens_analyzed' => count($analysisData),
                'top_3_predictions' => $algorithmSummary,
                'cache_expires' => now()->addMinutes(self::CACHE_DURATION_MINUTES)->toISOString(),
                'sorting_strategy' => 'risk_adjusted_score (数据驱动的稳定性优先)',
                'v7_optimizations' => [
                    '📊 数据驱动权重调整：历史数据权重×2.0，市场数据权重×0.5',
                    '🎯 信任长期稳定性：基于prediction_analysis.csv回测分析结果',
                    '🛡️ 增强稳定性惩罚：惩罚因子从0.01提升至1.5',
                    '📈 过滤高风险选项：更严格的波动性控制',
                    '⚖️ 降低短期噪音：减少市场热度的误导影响'
                ],
                'weight_parameters' => [
                    'historical_weight' => self::HISTORICAL_DATA_WEIGHT,
                    'market_weight' => self::MARKET_DATA_WEIGHT,
                    'stability_penalty' => self::ENHANCED_STABILITY_PENALTY
                ]
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('生成预测分析数据失败', [
                'round_id' => $roundId,
                'tokens' => $tokens,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 从缓存获取预测数据
     */
    public function getCachedPrediction(): ?array
    {
        try {
            $cachedData = Cache::get('game:current_prediction');

            if (!$cachedData || !is_array($cachedData)) {
                return null;
            }

            return $cachedData;

        } catch (\Exception $e) {
            Log::error('获取缓存预测数据失败', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * 生成预测数据的核心逻辑
     */
    private function generatePredictionData(array $tokens): array
    {
        // 标准化代币符号并去重
        $tokens = array_unique(array_map('strtoupper', $tokens));

        Log::info('处理代币列表', [
            'original_count' => count($tokens),
            'tokens' => array_values($tokens)
        ]);

        // 获取最近的历史数据进行分析
        $recentRounds = GameRound::with('roundResults')
            ->orderBy('created_at', 'desc')
            ->limit(self::ANALYSIS_ROUNDS_LIMIT)
            ->get();

        if ($recentRounds->isEmpty()) {
            Log::warning('没有历史数据可用于预测分析');
            return [];
        }

        // 分析历史数据并计算统计指标（已包含基础评分计算）
        $tokenStats = $this->analyzeHistoricalPerformance($tokens, $recentRounds);

        // 获取市场数据并合并，基于预期分数进行预测
        $analysisData = $this->enrichWithMarketData($tokenStats);

        return $analysisData;
    }

    /**
     * 分析历史表现数据 - 重构为基于 value 分数的分析
     */
    private function analyzeHistoricalPerformance(array $tokens, $recentRounds): array
    {
        $tokenStats = [];

        // 初始化统计数据 - 新增基于 value 的指标
        foreach ($tokens as $symbol) {
            $tokenStats[$symbol] = [
                'symbol' => $symbol,
                'total_games' => 0,
                'wins' => 0,
                'top3' => 0,
                'avg_rank' => 0,
                'rank_sum' => 0,
                'recent_trend' => [], // 最近排名趋势

                // 新增：基于 value 分数的核心指标
                'avg_value' => 0,           // 历史平均分数
                'recent_avg_value' => 0,    // 近期平均分数（最近10局）
                'value_sum' => 0,           // 分数总和
                'value_stddev' => 0,        // 分数标准差（稳定性指标）
                'max_value' => 0,           // 历史最高分
                'min_value' => PHP_FLOAT_MAX, // 历史最低分
                'value_history' => [],      // 分数历史记录
                'recent_value_trend' => [], // 最近10局的分数趋势
            ];
        }

        // 遍历历史数据 - 收集 value 和 rank 数据
        foreach ($recentRounds as $round) {
            foreach ($round->roundResults as $result) {
                $symbol = strtoupper($result->token_symbol);

                // 只统计当前局参与的代币
                if (!isset($tokenStats[$symbol])) {
                    continue;
                }

                $tokenStats[$symbol]['total_games']++;
                $tokenStats[$symbol]['rank_sum'] += $result->rank;

                // 新增：收集 value 分数数据
                $value = floatval($result->value);
                $tokenStats[$symbol]['value_sum'] += $value;
                $tokenStats[$symbol]['value_history'][] = $value;

                // 更新最高/最低分
                $tokenStats[$symbol]['max_value'] = max($tokenStats[$symbol]['max_value'], $value);
                $tokenStats[$symbol]['min_value'] = min($tokenStats[$symbol]['min_value'], $value);

                // 收集最近10局的分数（用于近期趋势分析）
                if (count($tokenStats[$symbol]['recent_value_trend']) < 10) {
                    $tokenStats[$symbol]['recent_value_trend'][] = $value;
                }

                // 传统排名统计
                if ($result->rank === 1) {
                    $tokenStats[$symbol]['wins']++;
                }

                if ($result->rank <= 3) {
                    $tokenStats[$symbol]['top3']++;
                }

                // 记录最近的排名（用于趋势分析）
                if (count($tokenStats[$symbol]['recent_trend']) < 5) {
                    $tokenStats[$symbol]['recent_trend'][] = $result->rank;
                }
            }
        }

        // 计算统计指标
        foreach ($tokenStats as $symbol => &$stats) {
            if ($stats['total_games'] > 0) {
                // 传统排名指标
                $stats['avg_rank'] = $stats['rank_sum'] / $stats['total_games'];
                $stats['win_rate'] = ($stats['wins'] / $stats['total_games']) * 100;
                $stats['top3_rate'] = ($stats['top3'] / $stats['total_games']) * 100;

                // 新增：核心 value 分数指标
                $stats['avg_value'] = $stats['value_sum'] / $stats['total_games'];

                // 计算近期平均分数（最近10局）
                if (!empty($stats['recent_value_trend'])) {
                    $stats['recent_avg_value'] = array_sum($stats['recent_value_trend']) / count($stats['recent_value_trend']);
                } else {
                    $stats['recent_avg_value'] = $stats['avg_value'];
                }

                // 计算分数标准差（稳定性指标）
                if (count($stats['value_history']) > 1) {
                    $variance = 0;
                    foreach ($stats['value_history'] as $value) {
                        $variance += pow($value - $stats['avg_value'], 2);
                    }
                    $stats['value_stddev'] = sqrt($variance / (count($stats['value_history']) - 1));
                } else {
                    $stats['value_stddev'] = 0;
                }

                // 修正最小值（如果没有找到有效的最小值）
                if ($stats['min_value'] === PHP_FLOAT_MAX) {
                    $stats['min_value'] = 0;
                }

                // 计算排名趋势得分
                $trendScore = 0;
                if (!empty($stats['recent_trend'])) {
                    $recentAvg = array_sum($stats['recent_trend']) / count($stats['recent_trend']);
                    $trendScore = ((5 - $recentAvg) / 4) * 100;
                }

                // 保持原有的基础预测评分（基于排名历史）
                $stats['prediction_score'] = (
                    ($stats['win_rate'] * 0.15) +
                    ($stats['top3_rate'] * 0.20) +
                    (((5 - $stats['avg_rank']) / 4) * 100 * 0.20) +
                    ($trendScore * 0.15) +
                    (30) // 预留30%权重给市场数据
                );
            } else {
                // 如果没有历史数据，给予默认值
                $stats['avg_rank'] = 3;
                $stats['win_rate'] = 0;
                $stats['top3_rate'] = 0;
                $stats['avg_value'] = 0;
                $stats['recent_avg_value'] = 0;
                $stats['value_stddev'] = 0;
                $stats['max_value'] = 0;
                $stats['min_value'] = 0;
                $stats['prediction_score'] = self::DEFAULT_PREDICTION_SCORE; // 中等评分
            }

            // 格式化数据
            $stats['avg_rank'] = round($stats['avg_rank'], 2);
            $stats['win_rate'] = round($stats['win_rate'], 1);
            $stats['top3_rate'] = round($stats['top3_rate'], 1);
            $stats['avg_value'] = round($stats['avg_value'], 4);
            $stats['recent_avg_value'] = round($stats['recent_avg_value'], 4);
            $stats['value_stddev'] = round($stats['value_stddev'], 4);
            $stats['max_value'] = round($stats['max_value'], 4);
            $stats['min_value'] = round($stats['min_value'], 4);
            $stats['prediction_score'] = round($stats['prediction_score'], 1);
        }

        return $tokenStats;
    }

    /**
     * 批量获取市场数据并合并到分析结果中
     */
    private function enrichWithMarketData(array $tokenStats): array
    {
        $analysisData = [];

        foreach ($tokenStats as $originalSymbol => $stats) {
            try {
                $marketData = $this->getTokenMarketData($originalSymbol);

                // 确保symbol字段始终为原始代币符号，不被API数据覆盖
                $mergedData = array_merge($stats, $marketData);
                $mergedData['symbol'] = $originalSymbol; // 强制保持原始symbol

                // 重新计算包含市场数据的预测评分
                $mergedData = $this->calculateEnhancedPredictionScore($mergedData);

                $analysisData[] = $mergedData;

                // 延迟避免API限制
                usleep(self::API_DELAY_MICROSECONDS);

            } catch (\Exception $e) {
                Log::warning("获取{$originalSymbol}市场数据失败", ['error' => $e->getMessage()]);

                // 如果API失败，至少返回预测数据
                $defaultData = array_merge($stats, $this->getDefaultMarketData($originalSymbol));
                $defaultData['symbol'] = $originalSymbol; // 确保symbol正确

                // 重要修复：对默认数据也要计算增强评分（包括市场动量）
                $defaultData = $this->calculateEnhancedPredictionScore($defaultData);
                $analysisData[] = $defaultData;
            }
        }

        // 核心排序逻辑：基于风险调整后分数排序（稳定性优先策略）
        usort($analysisData, function ($a, $b) {
            // 主要按风险调整后分数排序（稳定性优先）
            $scoreComparison = $b['risk_adjusted_score'] <=> $a['risk_adjusted_score'];

            // 如果风险调整后分数相同，再按预期分数排序
            if ($scoreComparison === 0) {
                return $b['predicted_final_value'] <=> $a['predicted_final_value'];
            }

            return $scoreComparison;
        });

        // 根据预期分数排序结果分配预测排名
        foreach ($analysisData as $index => &$data) {
            $data['predicted_rank'] = $index + 1;

            // 添加排名置信度分析
            $data['rank_confidence'] = $this->calculateRankConfidence($data, $index + 1);
        }

        return $analysisData;
    }

    /**
     * 计算包含市场数据的增强预测评分 - v7 基于数据分析优化：信任历史稳定性
     */
    private function calculateEnhancedPredictionScore(array $data): array
    {
        // 步骤1：计算预测基础分数（基于历史 value 数据）
        $predictedBaseValue = $this->calculatePredictedBaseValue($data);

        // 步骤2：计算市场调整分数（基于市场动量）
        $marketAdjustmentValue = $this->calculateMarketAdjustmentValue($data);

        // 步骤3：计算最终预期分数 - 应用数据分析优化的权重
        // 历史数据权重提升至2.0，市场数据权重降低至0.5
        $weightedHistoricalScore = $predictedBaseValue * self::HISTORICAL_DATA_WEIGHT;
        $weightedMarketScore = $marketAdjustmentValue * self::MARKET_DATA_WEIGHT;
        $predictedFinalValue = $weightedHistoricalScore + $weightedMarketScore;

        // 步骤4：计算风险调整后分数（更严格的稳定性惩罚）
        $riskAdjustedScore = $this->calculateRiskAdjustedScore($predictedFinalValue, $data);

        // 添加新的预测指标到数据中
        $data['predicted_base_value'] = round($predictedBaseValue, 4);
        $data['market_adjustment_value'] = round($marketAdjustmentValue, 4);
        $data['weighted_historical_score'] = round($weightedHistoricalScore, 4);
        $data['weighted_market_score'] = round($weightedMarketScore, 4);
        $data['predicted_final_value'] = round($predictedFinalValue, 4);
        $data['risk_adjusted_score'] = round($riskAdjustedScore, 2);

        // 保留原有的市场动量评分（用于分析）
        $data['market_momentum_score'] = round($this->calculateMarketMomentumScore($data), 1);

        // 设置最终预测评分为风险调整后分数
        $data['final_prediction_score'] = $data['risk_adjusted_score'];

        // 记录权重应用的详细日志
        Log::info("v7 算法权重应用", [
            'symbol' => $data['symbol'],
            'base_historical_score' => round($predictedBaseValue, 4),
            'base_market_adjustment' => round($marketAdjustmentValue, 4),
            'weighted_historical' => round($weightedHistoricalScore, 4),
            'weighted_market' => round($weightedMarketScore, 4),
            'final_predicted_value' => round($predictedFinalValue, 4),
            'risk_adjusted_score' => round($riskAdjustedScore, 2),
            'historical_weight' => self::HISTORICAL_DATA_WEIGHT,
            'market_weight' => self::MARKET_DATA_WEIGHT,
            'strategy' => 'trust_historical_stability'
        ]);

        return $data;
    }

    /**
     * 计算预测基础分数（基于历史 value 数据）
     */
    private function calculatePredictedBaseValue(array $data): float
    {
        // 优先使用近期平均分数，如果没有则使用历史平均分数
        $recentAvgValue = $data['recent_avg_value'] ?? 0;
        $historicalAvgValue = $data['avg_value'] ?? 0;

        // 如果有近期数据，权重更高
        if ($recentAvgValue > 0) {
            // 近期数据权重更高，历史数据作为补充
            return ($recentAvgValue * self::RECENT_VALUE_WEIGHT) + ($historicalAvgValue * self::HISTORICAL_VALUE_WEIGHT);
        } elseif ($historicalAvgValue > 0) {
            return $historicalAvgValue;
        } else {
            // 如果没有历史数据，返回一个中等的基础分数
            return self::DEFAULT_BASE_VALUE;
        }
    }

    /**
     * 计算市场调整分数（将市场动量转换为分数调整值） - 优化版：信任动态权重调整
     */
    private function calculateMarketAdjustmentValue(array $data): float
    {
        // 计算市场动量评分（已通过动态权重调整处理数据质量）
        $marketMomentumScore = $this->calculateMarketMomentumScore($data);

        // 直接使用动态加权后的市场动量分，不再需要额外的数据质量折扣
        $adjustment = ($marketMomentumScore - 50) * self::MARKET_INFLUENCE_FACTOR;

        Log::info("市场调整值计算", [
            'symbol' => $data['symbol'],
            'market_momentum_score' => round($marketMomentumScore, 2),
            'market_adjustment_value' => round($adjustment, 4),
            'logic' => 'dynamic_weight_adjustment_only'
        ]);

        return $adjustment;
    }

    /**
     * 计算数据质量评分 - 评估市场数据的完整性
     */
    private function calculateDataQualityScore(array $data): float
    {
        $availableDataPoints = 0;

        // 检查价格变化数据
        $priceChangeFields = ['change_5m', 'change_1h', 'change_4h', 'change_24h'];
        foreach ($priceChangeFields as $field) {
            if (isset($data[$field]) && $data[$field] !== null) {
                $availableDataPoints++;
            }
        }

        // 检查交易量数据
        if (isset($data['volume_24h']) && $data['volume_24h'] !== null && $data['volume_24h'] !== '0') {
            $availableDataPoints++;
        }

        // 计算质量评分（0-1之间）
        $qualityScore = $availableDataPoints / self::TOTAL_MARKET_DATA_POINTS;

        // 给予基础质量保证：即使数据缺失，也保留一定的影响力
        $finalQualityScore = max(self::MIN_DATA_QUALITY_SCORE, $qualityScore);

        // 数据质量较低时记录详细日志
        if ($qualityScore < self::DATA_QUALITY_LOG_THRESHOLD) {
            Log::warning("代币市场数据质量较低", [
                'symbol' => $data['symbol'],
                'available_data_points' => $availableDataPoints,
                'total_data_points' => self::TOTAL_MARKET_DATA_POINTS,
                'raw_quality_score' => round($qualityScore, 3),
                'final_quality_score' => round($finalQualityScore, 3),
                'quality_discount' => round((1 - $finalQualityScore) * 100, 1) . '%'
            ]);
        }

        return $finalQualityScore;
    }

    /**
     * 计算风险调整后分数（更严格的稳定性惩罚） - v7 基于数据分析优化
     */
    private function calculateRiskAdjustedScore(float $predictedValue, array $data): float
    {
        $valueStddev = $data['value_stddev'] ?? 0;

        // 如果标准差为0或很小，说明非常稳定，给予高评分
        if ($valueStddev <= 0.01) {
            $stabilityReward = min(100, $predictedValue * self::STABILITY_REWARD_MULTIPLIER);

            Log::debug("极高稳定性奖励", [
                'symbol' => $data['symbol'],
                'stddev' => $valueStddev,
                'predicted_value' => round($predictedValue, 4),
                'stability_reward' => round($stabilityReward, 2)
            ]);

            return $stabilityReward;
        }

        // v7 改进：应用更严格的稳定性惩罚因子
        // 原公式：1 + (标准差 * 0.01)，现在：1 + (标准差 * 1.5)
        $enhancedRiskPenalty = 1 + ($valueStddev * self::ENHANCED_STABILITY_PENALTY);

        $riskAdjustedScore = $predictedValue / $enhancedRiskPenalty;

        // 记录风险调整的详细计算过程
        Log::debug("v7 风险调整计算", [
            'symbol' => $data['symbol'],
            'predicted_value' => round($predictedValue, 4),
            'value_stddev' => round($valueStddev, 4),
            'old_penalty_factor' => self::RISK_WEIGHT_COEFFICIENT, // 0.01
            'new_penalty_factor' => self::ENHANCED_STABILITY_PENALTY, // 1.5
            'enhanced_risk_penalty' => round($enhancedRiskPenalty, 4),
            'risk_adjusted_score' => round($riskAdjustedScore, 2),
            'penalty_improvement' => 'stricter_stability_control'
        ]);

        // 确保评分在合理范围内（0-100）
        return max(0, min(100, $riskAdjustedScore));
    }

    /**
     * 计算排名置信度（基于稳定性和历史数据质量）
     */
    private function calculateRankConfidence(array $data, int $predictedRank): float
    {
        $confidence = self::BASE_CONFIDENCE; // 基础置信度

        // 因子1：历史数据量（更多数据 = 更高置信度）
        $totalGames = $data['total_games'] ?? 0;
        if ($totalGames > 0) {
            $dataConfidence = min(self::MAX_DATA_CONFIDENCE, $totalGames * self::CONFIDENCE_PER_GAME);
            $confidence += $dataConfidence;
        }

        // 因子2：稳定性（标准差越小 = 更高置信度）
        $valueStddev = $data['value_stddev'] ?? 0;
        if ($valueStddev > 0) {
            // 标准差越小，置信度越高
            $stabilityBonus = max(0, self::STABILITY_BONUS_THRESHOLD - $valueStddev);
            $confidence += $stabilityBonus;
        } else {
            $confidence += 5; // 完全稳定给予5%奖励
        }

        // 因子3：预测为前三名的置信度调整
        if ($predictedRank <= 3) {
            $top3Rate = $data['top3_rate'] ?? 0;
            $confidence += ($top3Rate * 0.2); // 历史前三率贡献置信度
        }

        // 因子4：近期表现与历史表现的一致性
        $recentAvg = $data['recent_avg_value'] ?? 0;
        $historicalAvg = $data['avg_value'] ?? 0;
        if ($recentAvg > 0 && $historicalAvg > 0) {
            $consistency = 1 - abs($recentAvg - $historicalAvg) / max($recentAvg, $historicalAvg);
            $confidence += ($consistency * self::MAX_CONSISTENCY_BONUS);
        }

        // 确保置信度在0-100%范围内
        return round(max(0, min(100, $confidence)), 1);
    }

    /**
     * 计算市场动量评分 - 优化版：动态权重调整，信任数据质量处理
     */
    private function calculateMarketMomentumScore(array $data): float
    {
        // 定义各时间段的权重
        $weights = [
            '5m' => self::MOMENTUM_WEIGHT_5M,   // 0.4
            '1h' => self::MOMENTUM_WEIGHT_1H,   // 0.3
            '4h' => self::MOMENTUM_WEIGHT_4H,   // 0.2
            '24h' => self::MOMENTUM_WEIGHT_24H  // 0.1
        ];

        $availableData = [];
        $totalWeight = 0;
        $missingDataCount = 0;

        // 收集可用的数据和权重
        foreach ($weights as $timeframe => $weight) {
            $changeKey = 'change_' . $timeframe;

            if (isset($data[$changeKey]) && $data[$changeKey] !== null) {
                $availableData[$timeframe] = $this->normalizeChange($data[$changeKey]);
                $totalWeight += $weight;

                Log::debug("市场数据可用", [
                    'symbol' => $data['symbol'],
                    'timeframe' => $timeframe,
                    'change' => $data[$changeKey],
                    'normalized_score' => $availableData[$timeframe],
                    'weight' => $weight
                ]);
            } else {
                $missingDataCount++;
                Log::warning("市场数据缺失", [
                    'symbol' => $data['symbol'],
                    'timeframe' => $timeframe,
                    'weight_lost' => $weight
                ]);
            }
        }

        // 计算数据质量评分（仅用于监控和日志记录）
        $dataQualityScore = max(0, (4 - $missingDataCount) / 4);

        // 如果所有数据都缺失，返回中性分（50分）
        if ($totalWeight === 0) {
            Log::warning("所有市场数据缺失，使用默认评分", [
                'symbol' => $data['symbol'],
                'default_score' => 50
            ]);
            return 50;
        }

        // 计算动态权重调整后的动量评分
        $momentumScore = 0;
        foreach ($availableData as $timeframe => $score) {
            // 将权重重新归一化 (re-normalize)
            $adjustedWeight = $weights[$timeframe] / $totalWeight;
            $momentumScore += $score * $adjustedWeight;

            Log::debug("动态权重调整", [
                'symbol' => $data['symbol'],
                'timeframe' => $timeframe,
                'original_weight' => $weights[$timeframe],
                'adjusted_weight' => $adjustedWeight,
                'score' => $score,
                'contribution' => $score * $adjustedWeight
            ]);
        }

        // 计算交易量评分
        $volumeScore = $this->calculateVolumeScore($data['volume_24h'] ?? '0');

        // 综合市场评分：动量 + 交易量（已通过动态权重调整处理数据质量）
        $finalMarketScore = ($momentumScore * self::MOMENTUM_SCORE_WEIGHT) + ($volumeScore * self::VOLUME_SCORE_WEIGHT);

        Log::info("市场动量评分计算完成", [
            'symbol' => $data['symbol'],
            'available_data_count' => count($availableData),
            'missing_data_count' => $missingDataCount,
            'data_quality_info' => round($dataQualityScore, 3) . ' (handled by dynamic weights)',
            'momentum_score' => round($momentumScore, 2),
            'volume_score' => round($volumeScore, 2),
            'final_market_score' => round($finalMarketScore, 2),
            'logic' => 'dynamic_weight_adjustment_only'
        ]);

        // 确保评分在0-100范围内
        return max(0, min(100, $finalMarketScore));
    }

    /**
     * 标准化价格变化为0-100评分
     */
    private function normalizeChange(float $change): float
    {
        if ($change === 0) {
            return 50; // 无变化给中等评分
        }

        // 将-10%到+10%的变化映射到0-100分
        // 正向变化得分更高
        $normalizedChange = ($change + 10) / 20 * 100;

        // 确保在0-100范围内，并给正向变化额外加分
        $score = max(0, min(100, $normalizedChange));

        // 正向趋势加权：正向变化得分更高
        if ($change > 0) {
            $score = min(100, $score + self::POSITIVE_CHANGE_BONUS);
        }

        return $score;
    }

    /**
     * 计算交易量评分
     */
    private function calculateVolumeScore(string $volume): float
    {
        $volumeValue = floatval($volume);

        if ($volumeValue <= 0) {
            return self::MIN_VOLUME_SCORE; // 无交易量数据给低分
        }

        // 对数缩放处理交易量，避免极端值
        $logVolume = log10($volumeValue + 1);

        // 将对数交易量映射到最低分-100分
        // 假设log交易量在3-8之间（1K-100M USD）
        $score = self::MIN_VOLUME_SCORE + (min($logVolume, 8) - 3) / 5 * (100 - self::MIN_VOLUME_SCORE);

        return max(self::MIN_VOLUME_SCORE, min(100, $score));
    }

    /**
     * 获取单个代币的市场数据
     */
    private function getTokenMarketData(string $symbol): array
    {
        try {
            $response = Http::timeout(10)->get("https://api.dexscreener.com/latest/dex/search", [
                'q' => $symbol
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['pairs']) && count($data['pairs']) > 0) {
                    // 尝试找到最匹配的交易对
                    $bestMatch = $this->findBestTokenMatch($data['pairs'], $symbol);

                    if ($bestMatch) {
                        return [
                            'price' => $bestMatch['priceUsd'] ?? '0',
                            'change_5m' => $bestMatch['priceChange']['m5'] ?? null,
                            'change_1h' => $bestMatch['priceChange']['h1'] ?? null,
                            'change_4h' => $bestMatch['priceChange']['h4'] ?? null,
                            'change_24h' => $bestMatch['priceChange']['h24'] ?? null,
                            'volume_24h' => $bestMatch['volume']['h24'] ?? '0',
                            'market_cap' => $bestMatch['marketCap'] ?? null,
                            'logo' => $bestMatch['baseToken']['logoURI'] ?? null,
                            'name' => $bestMatch['baseToken']['name'] ?? $symbol,
                        ];
                    }
                }
            }

            return $this->getDefaultMarketData($symbol);

        } catch (\Exception $e) {
            Log::warning("API调用失败", ['symbol' => $symbol, 'error' => $e->getMessage()]);
            return $this->getDefaultMarketData($symbol);
        }
    }

    /**
     * 从多个交易对中找到最匹配的代币
     */
    private function findBestTokenMatch(array $pairs, string $targetSymbol): ?array
    {
        $targetSymbol = strtoupper($targetSymbol);

        // 优先级1: 精确匹配 baseToken symbol
        foreach ($pairs as $pair) {
            $baseSymbol = strtoupper($pair['baseToken']['symbol'] ?? '');
            if ($baseSymbol === $targetSymbol) {
                Log::info("找到精确匹配的代币", [
                    'target' => $targetSymbol,
                    'matched' => $baseSymbol,
                    'name' => $pair['baseToken']['name'] ?? 'unknown'
                ]);
                return $pair;
            }
        }

        // 优先级2: 部分匹配 baseToken symbol (前缀匹配)
        foreach ($pairs as $pair) {
            $baseSymbol = strtoupper($pair['baseToken']['symbol'] ?? '');
            if (str_starts_with($baseSymbol, $targetSymbol) || str_starts_with($targetSymbol, $baseSymbol)) {
                Log::info("找到部分匹配的代币", [
                    'target' => $targetSymbol,
                    'matched' => $baseSymbol,
                    'name' => $pair['baseToken']['name'] ?? 'unknown'
                ]);
                return $pair;
            }
        }

        // 优先级3: 检查代币名称中是否包含目标符号
        foreach ($pairs as $pair) {
            $tokenName = strtoupper($pair['baseToken']['name'] ?? '');
            if (str_contains($tokenName, $targetSymbol)) {
                Log::info("通过名称匹配找到代币", [
                    'target' => $targetSymbol,
                    'matched_name' => $tokenName,
                    'symbol' => $pair['baseToken']['symbol'] ?? 'unknown'
                ]);
                return $pair;
            }
        }

        // 优先级4: 返回第一个结果（原有逻辑）
        if (!empty($pairs)) {
            Log::warning("使用第一个搜索结果作为备选", [
                'target' => $targetSymbol,
                'fallback_symbol' => $pairs[0]['baseToken']['symbol'] ?? 'unknown',
                'fallback_name' => $pairs[0]['baseToken']['name'] ?? 'unknown'
            ]);
            return $pairs[0];
        }

        return null;
    }

    /**
     * 获取默认市场数据（API失败时使用）
     */
    private function getDefaultMarketData(string $symbol): array
    {
        return [
            'price' => '0',
            'change_5m' => null,
            'change_1h' => null,
            'change_4h' => null,
            'change_24h' => null,
            'volume_24h' => '0',
            'market_cap' => null,
            'logo' => null,
            'name' => $symbol,
        ];
    }

    /**
     * 获取分析使用的轮次数量
     */
    private function getAnalysisRoundsCount(): int
    {
        return GameRound::count();
    }

    /**
     * 清除缓存的预测数据
     */
    public function clearCachedPrediction(): bool
    {
        try {
            Cache::forget('game:current_prediction');
            return true;
        } catch (\Exception $e) {
            Log::error('清除预测缓存失败', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
