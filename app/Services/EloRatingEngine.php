<?php

namespace App\Services;

use App\Models\TokenRating;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EloRatingEngine
{
    public const K_BASE = 32; // 基本 K 值

    /**
     * 計算 K 值衰減因子
     * K = K_BASE * 200 / (200 + games)
     *
     * @param  int  $games  已玩游戏次数
     * @return float K 值衰減因子
     */
    protected function calculateKFactor(int $games): float
    {
        return self::K_BASE * 200 / (200 + $games);
    }

    /**
     * 更新勝者和負者的 Elo 評分。
     *
     * @param  string  $win  勝者的代幣符號
     * @param  string  $lose  負者的代幣符號
     * @param  float  $kf  K 值調整因子
     */
    public function updateElo(string $win, string $lose, float $kf = 1.0): void
    {
        try {
            DB::transaction(function () use ($win, $lose, $kf) {
                // 獲取或創建 TokenRating 實例
                $winnerRating = TokenRating::firstOrCreate(['symbol' => strtoupper($win)]);
                $loserRating = TokenRating::firstOrCreate(['symbol' => strtoupper($lose)]);

                $Ra = $winnerRating->elo;
                $Rb = $loserRating->elo;

                $Ea = 1 / (1 + 10 ** (($Rb - $Ra) / 400)); // 勝者預期得分

                // 如果使用默认K值因子，则应用衰减逻辑
                if ($kf === 1.0) {
                    $winnerKFactor = $this->calculateKFactor($winnerRating->games ?? 0);
                    $loserKFactor = $this->calculateKFactor($loserRating->games ?? 0);
                    $effectiveKFactor = ($winnerKFactor + $loserKFactor) / 2;
                } else {
                    $effectiveKFactor = self::K_BASE * $kf;
                }

                $delta = $effectiveKFactor * (1 - $Ea); // Elo 變化值

                // 更新勝者 Elo 和遊戲場次
                $winnerRating->elo = $Ra + $delta;
                $winnerRating->games = $winnerRating->games + 1;
                $winnerRating->save();

                // 更新負者 Elo 和遊戲場次
                $loserRating->elo = $Rb - $delta;
                $loserRating->games = $loserRating->games + 1;
                $loserRating->save();

                Log::info('Elo 評分更新成功', [
                    'winner' => $win,
                    'loser' => $lose,
                    'winner_elo' => round($winnerRating->elo, 2),
                    'loser_elo' => round($loserRating->elo, 2),
                    'delta' => round($delta, 2),
                    'k_factor_input' => $kf,
                    'effective_k_factor' => round($effectiveKFactor, 2),
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Elo 評分更新失敗', [
                'winner' => $win,
                'loser' => $lose,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * 批量更新 Elo 評分
     *
     * @param  array  $results  結果陣列 [['winner' => 'BTC', 'loser' => 'ETH'], ...]
     * @param  float  $kf  K 值調整因子
     */
    public function batchUpdateElo(array $results, float $kf = 1.0): void
    {
        try {
            DB::transaction(function () use ($results, $kf) {
                foreach ($results as $result) {
                    $this->updateElo($result['winner'], $result['loser'], $kf);
                }
            });

            Log::info('批量 Elo 評分更新完成', [
                'results_count' => count($results),
                'k_factor' => $kf,
            ]);
        } catch (\Exception $e) {
            Log::error('批量 Elo 評分更新失敗', [
                'results_count' => count($results),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * 計算代幣組中每個代幣擊敗其他代幣的機率。
     *
     * @param  array  $symbols  代幣符號陣列
     * @return array [symbol => 0-1 機率]
     */
    public function probabilities(array $symbols): array
    {
        $symbols = array_unique(array_map('strtoupper', $symbols));

        if (count($symbols) < 2) {
            Log::warning('代幣數量不足，無法計算 Elo 機率', ['symbols' => $symbols]);

            return [];
        }

        try {
            // 從資料庫中獲取現有的 Elo 評分
            $eloRatings = TokenRating::whereIn('symbol', $symbols)
                ->pluck('elo', 'symbol')->toArray();

            // 為新幣設定預設 Elo 1500
            $currentElo = [];
            foreach ($symbols as $s) {
                $currentElo[$s] = $eloRatings[$s] ?? 1500;
            }

            $prob = [];
            foreach ($symbols as $a) {
                $winsAgainstOthers = 0;
                foreach ($symbols as $b) {
                    if ($a == $b) {
                        continue;
                    }
                    // Elo 機率公式：1 / (1 + 10^((對手Elo - 自己Elo) / 400))
                    $winsAgainstOthers += 1 / (1 + 10 ** (($currentElo[$b] - $currentElo[$a]) / 400));
                }
                // 機率是擊敗其他代幣的平均機率
                $prob[$a] = $winsAgainstOthers / (count($symbols) - 1);
            }

            Log::info('Elo 機率計算完成', [
                'symbols' => $symbols,
                'elo_ratings' => $currentElo,
            ]);

            return $prob;
        } catch (\Exception $e) {
            Log::error('Elo 機率計算失敗', [
                'symbols' => $symbols,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * 獲取代幣的當前 Elo 評分
     *
     * @param  string  $symbol  代幣符號
     * @return float Elo 評分
     */
    public function getElo(string $symbol): float
    {
        $rating = TokenRating::where('symbol', strtoupper($symbol))->first();

        return $rating ? $rating->elo : 1500;
    }

    /**
     * 獲取多個代幣的 Elo 評分
     *
     * @param  array  $symbols  代幣符號陣列
     * @return array [symbol => elo]
     */
    public function getMultipleElo(array $symbols): array
    {
        $symbols = array_unique(array_map('strtoupper', $symbols));

        $ratings = TokenRating::whereIn('symbol', $symbols)
            ->pluck('elo', 'symbol')->toArray();

        $result = [];
        foreach ($symbols as $symbol) {
            $result[$symbol] = $ratings[$symbol] ?? 1500;
        }

        return $result;
    }

    /**
     * 重置代幣的 Elo 評分到預設值
     *
     * @param  string  $symbol  代幣符號
     */
    public function resetElo(string $symbol): void
    {
        try {
            $rating = TokenRating::where('symbol', strtoupper($symbol))->first();
            if ($rating) {
                $rating->elo = 1500;
                $rating->games = 0;
                $rating->save();

                Log::info('Elo 評分重置成功', ['symbol' => $symbol]);
            }
        } catch (\Exception $e) {
            Log::error('Elo 評分重置失敗', [
                'symbol' => $symbol,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
