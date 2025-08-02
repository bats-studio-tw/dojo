<?php

namespace App\Services;

use App\Models\GameRound;
use App\Models\RoundResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class TimeDecayCalculatorService
{
    /**
     * 获取时间衰减配置
     */
    protected function getDecayConfig(): array
    {
        return config('prediction.time_decay', [
            'enabled' => true,
            'decay_rate' => 0.97,
            'min_games_for_decay' => 10,
            'max_decay_rounds' => 1000,
        ]);
    }

    /**
     * 计算时间衰减的 top3_rate
     *
     * @param string $tokenSymbol 代币符号
     * @param int|null $maxRounds 最大考虑的游戏轮数
     * @return array 包含衰减和非衰减的 top3_rate
     */
    public function calculateDecayedTop3Rate(string $tokenSymbol, ?int $maxRounds = null): array
    {
        $config = $this->getDecayConfig();
        $maxRounds = $maxRounds ?? $config['max_decay_rounds'];

        // 获取代币的历史游戏数据，按时间倒序排列（最新的在前）
        $gameResults = $this->getTokenGameHistory($tokenSymbol, $maxRounds);

        if ($gameResults->isEmpty()) {
            return [
                'top3_rate' => 0,
                'decayed_top3_rate' => 0,
                'total_games' => 0,
                'decay_applied' => false,
            ];
        }

        $totalGames = $gameResults->count();

        // 如果游戏数量少于最小值，不应用衰减
        $shouldApplyDecay = $config['enabled'] && $totalGames >= $config['min_games_for_decay'];

        // 计算传统 top3_rate
        $top3Wins = $gameResults->where('rank', '<=', 3)->count();
        $traditionalTop3Rate = ($top3Wins / $totalGames) * 100;

        if (!$shouldApplyDecay) {
            return [
                'top3_rate' => $traditionalTop3Rate,
                'decayed_top3_rate' => $traditionalTop3Rate,
                'total_games' => $totalGames,
                'decay_applied' => false,
            ];
        }

        // 计算时间衰减的 top3_rate
        $decayRate = $config['decay_rate'];
        $weightedTop3Sum = 0;
        $weightedSum = 0;

        foreach ($gameResults as $index => $game) {
            $weight = pow($decayRate, $index);  // 越新的游戏权重越大
            $isTop3 = ($game->rank <= 3) ? 1 : 0;

            $weightedTop3Sum += $weight * $isTop3;
            $weightedSum += $weight;
        }

        $decayedTop3Rate = ($weightedSum > 0)
            ? ($weightedTop3Sum / $weightedSum) * 100
            : 0;

        Log::info('时间衰减 top3_rate 计算完成', [
            'token_symbol' => $tokenSymbol,
            'total_games' => $totalGames,
            'traditional_top3_rate' => round($traditionalTop3Rate, 2),
            'decayed_top3_rate' => round($decayedTop3Rate, 2),
            'decay_rate' => $decayRate,
            'weighted_sum' => round($weightedSum, 2),
        ]);

        return [
            'top3_rate' => $traditionalTop3Rate,
            'decayed_top3_rate' => $decayedTop3Rate,
            'total_games' => $totalGames,
            'decay_applied' => true,
            'decay_rate' => $decayRate,
            'weighted_sum' => $weightedSum,
        ];
    }

    /**
     * 计算时间衰减的 Elo 胜率统计
     *
     * @param string $tokenSymbol 代币符号
     * @param int|null $maxRounds 最大考虑的游戏轮数
     * @return array 包含衰减和非衰减的胜率统计
     */
    public function calculateDecayedEloStats(string $tokenSymbol, ?int $maxRounds = null): array
    {
        $config = $this->getDecayConfig();
        $maxRounds = $maxRounds ?? $config['max_decay_rounds'];

        // 获取代币的历史游戏数据
        $gameResults = $this->getTokenGameHistory($tokenSymbol, $maxRounds);

        if ($gameResults->isEmpty()) {
            return [
                'win_rate' => 0,
                'decayed_win_rate' => 0,
                'avg_rank' => 0,
                'decayed_avg_rank' => 0,
                'total_games' => 0,
                'decay_applied' => false,
            ];
        }

        $totalGames = $gameResults->count();
        $shouldApplyDecay = $config['enabled'] && $totalGames >= $config['min_games_for_decay'];

        // 计算传统指标
        $wins = $gameResults->where('rank', 1)->count();
        $traditionalWinRate = ($wins / $totalGames) * 100;
        $traditionalAvgRank = $gameResults->avg('rank');

        if (!$shouldApplyDecay) {
            return [
                'win_rate' => $traditionalWinRate,
                'decayed_win_rate' => $traditionalWinRate,
                'avg_rank' => $traditionalAvgRank,
                'decayed_avg_rank' => $traditionalAvgRank,
                'total_games' => $totalGames,
                'decay_applied' => false,
            ];
        }

        // 计算时间衰减指标
        $decayRate = $config['decay_rate'];
        $weightedWinSum = 0;
        $weightedRankSum = 0;
        $weightedSum = 0;

        foreach ($gameResults as $index => $game) {
            $weight = pow($decayRate, $index);
            $isWin = ($game->rank === 1) ? 1 : 0;

            $weightedWinSum += $weight * $isWin;
            $weightedRankSum += $weight * $game->rank;
            $weightedSum += $weight;
        }

        $decayedWinRate = ($weightedSum > 0)
            ? ($weightedWinSum / $weightedSum) * 100
            : 0;

        $decayedAvgRank = ($weightedSum > 0)
            ? ($weightedRankSum / $weightedSum)
            : 0;

        return [
            'win_rate' => $traditionalWinRate,
            'decayed_win_rate' => $decayedWinRate,
            'avg_rank' => $traditionalAvgRank,
            'decayed_avg_rank' => $decayedAvgRank,
            'total_games' => $totalGames,
            'decay_applied' => true,
            'decay_rate' => $decayRate,
            'weighted_sum' => $weightedSum,
        ];
    }

    /**
     * 批量计算多个代币的时间衰减指标
     *
     * @param array $tokenSymbols 代币符号数组
     * @param int|null $maxRounds 最大考虑的游戏轮数
     * @return array 所有代币的时间衰减指标
     */
    public function calculateBatchDecayedMetrics(array $tokenSymbols, ?int $maxRounds = null): array
    {
        $results = [];

        foreach ($tokenSymbols as $symbol) {
            $symbol = strtoupper($symbol);
            $top3Data = $this->calculateDecayedTop3Rate($symbol, $maxRounds);
            $eloData = $this->calculateDecayedEloStats($symbol, $maxRounds);

            $results[$symbol] = [
                'top3_metrics' => $top3Data,
                'elo_metrics' => $eloData,
            ];
        }

        return $results;
    }

    /**
     * 获取代币的历史游戏数据，按时间倒序排列
     *
     * @param string $tokenSymbol 代币符号
     * @param int $maxRounds 最大游戏轮数
     * @return Collection 游戏结果集合
     */
    protected function getTokenGameHistory(string $tokenSymbol, int $maxRounds): Collection
    {
        return RoundResult::where('token_symbol', strtoupper($tokenSymbol))
            ->join('game_rounds', 'round_results.game_round_id', '=', 'game_rounds.id')
            ->whereNotNull('game_rounds.settled_at')  // 只考虑已结算的游戏
            ->orderBy('game_rounds.settled_at', 'desc')  // 按时间倒序，最新的在前
            ->limit($maxRounds)
            ->select('round_results.*')
            ->get();
    }

    /**
     * 获取权重分布信息（用于调试和分析）
     *
     * @param int $totalGames 总游戏数
     * @param float $decayRate 衰减率
     * @return array 权重分布信息
     */
    public function getWeightDistribution(int $totalGames, float $decayRate = 0.97): array
    {
        $weights = [];
        $cumulativeWeight = 0;

        for ($i = 0; $i < $totalGames; $i++) {
            $weight = pow($decayRate, $i);
            $weights[] = $weight;
            $cumulativeWeight += $weight;
        }

        // 计算权重百分比
        $weightPercentages = array_map(function($weight) use ($cumulativeWeight) {
            return ($weight / $cumulativeWeight) * 100;
        }, $weights);

        return [
            'weights' => $weights,
            'weight_percentages' => $weightPercentages,
            'cumulative_weight' => $cumulativeWeight,
            'recent_10_percent' => array_sum(array_slice($weightPercentages, 0, 10)),
            'recent_50_percent' => array_sum(array_slice($weightPercentages, 0, 50)),
        ];
    }
}
