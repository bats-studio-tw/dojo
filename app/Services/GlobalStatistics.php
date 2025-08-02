<?php

namespace App\Services;

use App\Models\GameRound;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 全局统计服务
 *
 * 提供用于冷启动策略的全局统计数据，
 * 包括所有代币的平均top3_rate等指标。
 */
class GlobalStatistics
{
    // 冷启动策略相关常量
    public const MIN_GAMES_FOR_VALID_STATS = 10;           // 认为统计数据有效的最小游戏数
    public const DEFAULT_H2H_SCORE = 50;                   // H2H无数据时的默认分数
    public const CACHE_DURATION_HOURS = 6;                 // 全局统计缓存时长（小时）
    public const ANALYSIS_ROUNDS_LIMIT = 120;              // 分析历史数据的轮次数量

    /**
     * 获取全局平均top3_rate（用于冷启动策略）
     *
     * @return float 全局平均top3_rate
     */
    public static function averageTop3Rate(): float
    {
        return Cache::remember('global_stats:average_top3_rate', now()->addHours(self::CACHE_DURATION_HOURS), function () {
            return self::calculateAverageTop3Rate();
        });
    }

    /**
     * 获取全局统计信息（包含多个指标）
     *
     * @return array 全局统计数据
     */
    public static function getGlobalStats(): array
    {
        return Cache::remember('global_stats:all_metrics', now()->addHours(self::CACHE_DURATION_HOURS), function () {
            return self::calculateGlobalStats();
        });
    }

    /**
     * 清除全局统计缓存
     */
    public static function clearCache(): void
    {
        Cache::forget('global_stats:average_top3_rate');
        Cache::forget('global_stats:all_metrics');
        Log::info('全局统计缓存已清除');
    }

    /**
     * 计算全局平均top3_rate
     *
     * @return float
     */
    private static function calculateAverageTop3Rate(): float
    {
        try {
            $recentRounds = GameRound::with('roundResults')
                ->orderBy('created_at', 'desc')
                ->limit(self::ANALYSIS_ROUNDS_LIMIT)
                ->get();

            if ($recentRounds->isEmpty()) {
                Log::warning('计算全局平均top3_rate时无历史数据可用');
                return 25.0; // 返回一个保守的默认值（25%保本率）
            }

            // 统计每个代币的表现
            $tokenStats = [];
            foreach ($recentRounds as $round) {
                foreach ($round->roundResults as $result) {
                    $symbol = strtoupper($result->token_symbol);

                    if (!isset($tokenStats[$symbol])) {
                        $tokenStats[$symbol] = [
                            'total_games' => 0,
                            'top3_count' => 0,
                        ];
                    }

                    $tokenStats[$symbol]['total_games']++;
                    if ($result->rank <= 3) {
                        $tokenStats[$symbol]['top3_count']++;
                    }
                }
            }

            // 计算每个代币的top3_rate，并取平均值
            $validTokens = 0;
            $totalTop3Rate = 0;

            foreach ($tokenStats as $symbol => $stats) {
                if ($stats['total_games'] >= self::MIN_GAMES_FOR_VALID_STATS) {
                    $top3Rate = ($stats['top3_count'] / $stats['total_games']) * 100;
                    $totalTop3Rate += $top3Rate;
                    $validTokens++;
                }
            }

            if ($validTokens === 0) {
                Log::warning('计算全局平均top3_rate时没有足够的有效代币数据');
                return 25.0; // 返回保守默认值
            }

            $averageTop3Rate = $totalTop3Rate / $validTokens;

            Log::info('全局平均top3_rate计算完成', [
                'average_top3_rate' => round($averageTop3Rate, 2),
                'valid_tokens_count' => $validTokens,
                'analysis_rounds' => self::ANALYSIS_ROUNDS_LIMIT,
                'min_games_threshold' => self::MIN_GAMES_FOR_VALID_STATS,
            ]);

            return round($averageTop3Rate, 2);

        } catch (\Exception $e) {
            Log::error('计算全局平均top3_rate时发生错误', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 25.0; // 错误时返回保守默认值
        }
    }

    /**
     * 计算完整的全局统计信息
     *
     * @return array
     */
    private static function calculateGlobalStats(): array
    {
        try {
            $recentRounds = GameRound::with('roundResults')
                ->orderBy('created_at', 'desc')
                ->limit(self::ANALYSIS_ROUNDS_LIMIT)
                ->get();

            if ($recentRounds->isEmpty()) {
                return self::getDefaultGlobalStats();
            }

            // 统计每个代币的详细表现
            $tokenStats = [];
            $allRanks = [];
            $allValues = [];

            foreach ($recentRounds as $round) {
                foreach ($round->roundResults as $result) {
                    $symbol = strtoupper($result->token_symbol);

                    if (!isset($tokenStats[$symbol])) {
                        $tokenStats[$symbol] = [
                            'total_games' => 0,
                            'wins' => 0,
                            'top3_count' => 0,
                            'rank_sum' => 0,
                            'value_sum' => 0,
                        ];
                    }

                    $stats = &$tokenStats[$symbol];
                    $stats['total_games']++;
                    $stats['rank_sum'] += $result->rank;
                    $stats['value_sum'] += floatval($result->value);

                    if ($result->rank === 1) {
                        $stats['wins']++;
                    }
                    if ($result->rank <= 3) {
                        $stats['top3_count']++;
                    }

                    // 收集全局数据
                    $allRanks[] = $result->rank;
                    $allValues[] = floatval($result->value);
                }
            }

            // 计算各项全局指标
            $validTokens = array_filter($tokenStats, function($stats) {
                return $stats['total_games'] >= self::MIN_GAMES_FOR_VALID_STATS;
            });

            if (empty($validTokens)) {
                return self::getDefaultGlobalStats();
            }

            $validTokensCount = count($validTokens);
            $totalTop3Rate = 0;
            $totalWinRate = 0;
            $totalAvgRank = 0;

            foreach ($validTokens as $stats) {
                $top3Rate = ($stats['top3_count'] / $stats['total_games']) * 100;
                $winRate = ($stats['wins'] / $stats['total_games']) * 100;
                $avgRank = $stats['rank_sum'] / $stats['total_games'];

                $totalTop3Rate += $top3Rate;
                $totalWinRate += $winRate;
                $totalAvgRank += $avgRank;
            }

            $globalStats = [
                'average_top3_rate' => round($totalTop3Rate / $validTokensCount, 2),
                'average_win_rate' => round($totalWinRate / $validTokensCount, 2),
                'average_avg_rank' => round($totalAvgRank / $validTokensCount, 2),
                'global_median_rank' => !empty($allRanks) ? median($allRanks) : 3,
                'valid_tokens_count' => $validTokensCount,
                'total_tokens_analyzed' => count($tokenStats),
                'analysis_rounds' => count($recentRounds),
                'min_games_threshold' => self::MIN_GAMES_FOR_VALID_STATS,
                'calculated_at' => now()->toISOString(),
            ];

            Log::info('全局统计信息计算完成', $globalStats);

            return $globalStats;

        } catch (\Exception $e) {
            Log::error('计算全局统计信息时发生错误', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::getDefaultGlobalStats();
        }
    }

    /**
     * 获取默认的全局统计信息（当无法计算时使用）
     *
     * @return array
     */
    private static function getDefaultGlobalStats(): array
    {
        return [
            'average_top3_rate' => 25.0,    // 保守的保本率
            'average_win_rate' => 8.33,     // 1/12 ≈ 8.33% (假设12个代币)
            'average_avg_rank' => 6.5,      // 中位数排名
            'global_median_rank' => 3,
            'valid_tokens_count' => 0,
            'total_tokens_analyzed' => 0,
            'analysis_rounds' => 0,
            'min_games_threshold' => self::MIN_GAMES_FOR_VALID_STATS,
            'calculated_at' => now()->toISOString(),
            'is_default' => true,
        ];
    }
}

/**
 * 辅助函数：计算数组中位数
 *
 * @param array $numbers
 * @return float
 */
function median(array $numbers): float
{
    if (empty($numbers)) {
        return 0;
    }

    sort($numbers);
    $count = count($numbers);
    $middle = floor($count / 2);

    if ($count % 2 === 0) {
        return ($numbers[$middle - 1] + $numbers[$middle]) / 2;
    } else {
        return $numbers[$middle];
    }
}
