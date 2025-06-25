<?php

namespace App\Services;

use App\Models\GameRound;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class GamePredictionService
{
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
                'algorithm' => 'enhanced_market_momentum_v2',
                'analysis_rounds_count' => $this->getAnalysisRoundsCount()
            ];

            Cache::put('game:current_prediction', $cacheData, now()->addMinutes(120));

            // 延迟保存策略：bet阶段只缓存，settled阶段再保存到数据库
            // 这样可以确保使用游戏提供的正确时间创建GameRound
            Log::info('预测数据已缓存，等待结算阶段保存到数据库', [
                'round_id' => $roundId,
                'predictions_count' => count($analysisData)
            ]);

            Log::info('✅ 预测分析数据已生成并缓存', [
                'round_id' => $roundId,
                'tokens_analyzed' => count($analysisData),
                'cache_expires' => now()->addMinutes(120)->toISOString()
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

        // 获取最近20局的数据进行分析
        $recentRounds = GameRound::with('roundResults')
            ->orderBy('created_at', 'desc')
            ->limit(120)
            ->get();

        if ($recentRounds->isEmpty()) {
            Log::warning('没有历史数据可用于预测分析');
            return [];
        }

        // 分析历史数据并计算统计指标
        $tokenStats = $this->analyzeHistoricalPerformance($tokens, $recentRounds);

        // 计算预测评分并排序
        $this->calculatePredictionScores($tokenStats);

        // 获取市场数据并合并
        $analysisData = $this->enrichWithMarketData($tokenStats);

        return $analysisData;
    }

    /**
     * 分析历史表现数据
     */
    private function analyzeHistoricalPerformance(array $tokens, $recentRounds): array
    {
        $tokenStats = [];

        // 初始化统计数据
        foreach ($tokens as $symbol) {
            $tokenStats[$symbol] = [
                'symbol' => $symbol,
                'total_games' => 0,
                'wins' => 0,
                'top3' => 0,
                'avg_rank' => 0,
                'rank_sum' => 0,
                'recent_trend' => [], // 最近5局的排名
            ];
        }

        // 遍历历史数据
        foreach ($recentRounds as $round) {
            foreach ($round->roundResults as $result) {
                $symbol = strtoupper($result->token_symbol);

                // 只统计当前局参与的代币
                if (!isset($tokenStats[$symbol])) {
                    continue;
                }

                $tokenStats[$symbol]['total_games']++;
                $tokenStats[$symbol]['rank_sum'] += $result->rank;

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

        return $tokenStats;
    }

    /**
     * 计算预测评分
     */
    private function calculatePredictionScores(array &$tokenStats): void
    {
        foreach ($tokenStats as $symbol => &$stats) {
            if ($stats['total_games'] > 0) {
                $stats['avg_rank'] = $stats['rank_sum'] / $stats['total_games'];
                $stats['win_rate'] = ($stats['wins'] / $stats['total_games']) * 100;
                $stats['top3_rate'] = ($stats['top3'] / $stats['total_games']) * 100;

                // 计算最近趋势得分（最近表现更好的得分更高）
                $trendScore = 0;
                if (!empty($stats['recent_trend'])) {
                    $recentAvg = array_sum($stats['recent_trend']) / count($stats['recent_trend']);
                    $trendScore = ((5 - $recentAvg) / 4) * 100;
                }

                // 改进的预测评分算法 - 降低历史数据权重，后续会加入市场数据
                $stats['prediction_score'] = (
                    ($stats['win_rate'] * 0.15) +                // 降低胜率权重从30%到15%
                    ($stats['top3_rate'] * 0.20) +               // 降低前三率权重从25%到20%
                    (((5 - $stats['avg_rank']) / 4) * 100 * 0.20) + // 降低平均排名权重从25%到20%
                    ($trendScore * 0.15) +                       // 降低趋势权重从20%到15%
                    (30)                                          // 预留30%权重给市场数据
                );
            } else {
                // 如果没有历史数据，给予中等评分
                $stats['avg_rank'] = 3;
                $stats['win_rate'] = 0;
                $stats['top3_rate'] = 0;
                $stats['prediction_score'] = 50; // 中等评分
            }

            // 格式化数据
            $stats['avg_rank'] = round($stats['avg_rank'], 2);
            $stats['win_rate'] = round($stats['win_rate'], 1);
            $stats['top3_rate'] = round($stats['top3_rate'], 1);
            $stats['prediction_score'] = round($stats['prediction_score'], 1);
        }

        // 按预测评分排序（评分越高，预测排名越靠前）
        uasort($tokenStats, function ($a, $b) {
            return $b['prediction_score'] <=> $a['prediction_score'];
        });

        // 添加预测排名
        $rank = 1;
        foreach ($tokenStats as &$stats) {
            $stats['predicted_rank'] = $rank++;
        }
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
                usleep(200000); // 0.2秒

            } catch (\Exception $e) {
                Log::warning("获取{$originalSymbol}市场数据失败", ['error' => $e->getMessage()]);

                // 如果API失败，至少返回预测数据
                $defaultData = array_merge($stats, $this->getDefaultMarketData($originalSymbol));
                $defaultData['symbol'] = $originalSymbol; // 确保symbol正确
                $analysisData[] = $defaultData;
            }
        }

        // 重新排序包含市场数据的结果
        usort($analysisData, function ($a, $b) {
            return $b['final_prediction_score'] <=> $a['final_prediction_score'];
        });

        // 重新分配预测排名
        foreach ($analysisData as $index => &$data) {
            $data['predicted_rank'] = $index + 1;
        }

        return $analysisData;
    }

    /**
     * 计算包含市场数据的增强预测评分
     */
    private function calculateEnhancedPredictionScore(array $data): array
    {
        // 基础历史评分（已经计算过，占70%权重）
        $historicalScore = $data['prediction_score'];

        // 计算市场动量评分（占30%权重）
        $marketMomentumScore = $this->calculateMarketMomentumScore($data);

        // 最终预测评分
        $data['market_momentum_score'] = round($marketMomentumScore, 1);
        $data['final_prediction_score'] = round(($historicalScore * 0.7) + ($marketMomentumScore * 0.3), 1);

        return $data;
    }

    /**
     * 计算市场动量评分
     */
    private function calculateMarketMomentumScore(array $data): float
    {
        // 获取价格变化数据
        $change5m = $this->normalizeChange($data['change_5m'] ?? 0);
        $change1h = $this->normalizeChange($data['change_1h'] ?? 0);
        $change4h = $this->normalizeChange($data['change_4h'] ?? 0);
        $change24h = $this->normalizeChange($data['change_24h'] ?? 0);

        // 计算交易量评分（相对交易量越高越好）
        $volumeScore = $this->calculateVolumeScore($data['volume_24h'] ?? '0');

        // 计算动量评分 - 近期变化权重更高
        $momentumScore = (
            ($change5m * 0.4) +   // 5分钟变化权重40%（最重要）
            ($change1h * 0.3) +   // 1小时变化权重30%
            ($change4h * 0.2) +   // 4小时变化权重20%
            ($change24h * 0.1)    // 24小时变化权重10%
        );

        // 综合市场评分：动量70% + 交易量30%
        $marketScore = ($momentumScore * 0.7) + ($volumeScore * 0.3);

        // 确保评分在0-100范围内
        return max(0, min(100, $marketScore));
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
            $score = min(100, $score + 10); // 正向变化额外加10分
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
            return 30; // 无交易量数据给低分
        }

        // 对数缩放处理交易量，避免极端值
        $logVolume = log10($volumeValue + 1);

        // 将对数交易量映射到30-100分（保证最低30分）
        // 假设log交易量在3-8之间（1K-100M USD）
        $score = 30 + (min($logVolume, 8) - 3) / 5 * 70;

        return max(30, min(100, $score));
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
