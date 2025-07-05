<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\EloRatingEngine;
use App\Models\RoundResult; // 假設您有 RoundResult 模型來讀取結算結果
use App\Models\TokenRating;
use Illuminate\Support\Facades\DB; // 用於 DB::raw
use Illuminate\Support\Facades\Log;

class EloUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $gameRoundId;

    /**
     * 創建一個新的 Job 實例。
     * @param int $gameRoundId 已結算的遊戲回合 ID
     */
    public function __construct(int $gameRoundId)
    {
        $this->gameRoundId = $gameRoundId;

        // 设置为低优先级队列
        $this->onQueue('low');
    }

    /**
     * 執行 Job。
     */
    public function handle(EloRatingEngine $eloRatingEngine): void
    {
        try {
            Log::info('开始 Elo 评分更新', ['game_round_id' => $this->gameRoundId]);

            // 1. 讀取結算後的 round_results (rank 1->5)
            $results = RoundResult::where('game_round_id', $this->gameRoundId)
                                  ->orderBy('rank')
                                  ->get();

            if ($results->isEmpty()) {
                Log::warning("No round results found for game_round_id: {$this->gameRoundId}");
                return;
            }

            Log::info('获取到游戏结果', [
                'game_round_id' => $this->gameRoundId,
                'results_count' => $results->count()
            ]);

            // 將結果按排名整理成 [rank => symbol]
            $rankedSymbols = $results->pluck('token_symbol', 'rank')->toArray();

            Log::info('排名结果', [
                'game_round_id' => $this->gameRoundId,
                'ranked_symbols' => $rankedSymbols
            ]);

            $updateCount = 0;
            $eloUpdates = [];

            // 2. 5x4/2 組對戰 → EloRatingEngine::updateElo(win, lose)
            // 遍歷所有可能的勝負對
            for ($i = 1; $i <= count($rankedSymbols); $i++) {
                for ($j = $i + 1; $j <= count($rankedSymbols); $j++) {
                    $winnerSymbol = $rankedSymbols[$i];
                    $loserSymbol = $rankedSymbols[$j];

                    // 在更新前获取当前的games数量来计算K值衰减
                    $winnerRating = TokenRating::firstOrCreate(['symbol' => strtoupper($winnerSymbol)]);
                    $loserRating = TokenRating::firstOrCreate(['symbol' => strtoupper($loserSymbol)]);

                    // 計算衰減後的 K 值
                    $winnerKFactor = $this->calculateKFactor($winnerRating->games);
                    $loserKFactor = $this->calculateKFactor($loserRating->games);

                    // 使用平均 K 值进行更新
                    $averageKFactor = ($winnerKFactor + $loserKFactor) / 2;

                    $eloRatingEngine->updateElo($winnerSymbol, $loserSymbol, $averageKFactor);

                    $updateCount++;
                    $eloUpdates[] = [
                        'winner' => $winnerSymbol,
                        'loser' => $loserSymbol,
                        'k_factor' => $averageKFactor
                    ];
                }
            }

            Log::info('Elo 评分更新完成', [
                'game_round_id' => $this->gameRoundId,
                'total_updates' => $updateCount,
                'elo_updates' => $eloUpdates
            ]);

            // 记录更新后的评分状态
            $finalRatings = [];
            foreach ($rankedSymbols as $rank => $symbol) {
                $rating = TokenRating::where('symbol', strtoupper($symbol))->first();
                if ($rating) {
                    $finalRatings[$symbol] = [
                        'rank' => $rank,
                        'elo' => round($rating->elo, 2),
                        'games' => $rating->games
                    ];
                }
            }

            Log::info('更新后的 Elo 评分状态', [
                'game_round_id' => $this->gameRoundId,
                'final_ratings' => $finalRatings
            ]);

        } catch (\Exception $e) {
            Log::error('Elo 评分更新失败', [
                'game_round_id' => $this->gameRoundId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * 計算 K 值衰減因子
     * K = K_BASE * 200 / (200 + games)
     *
     * @param int $games 已玩游戏次数
     * @return float K 值衰減因子
     */
    private function calculateKFactor(int $games): float
    {
        $kBase = 32; // 基础 K 值
        return $kBase * 200 / (200 + $games);
    }

    /**
     * Job 失败时的处理
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('EloUpdateJob 执行失败', [
            'game_round_id' => $this->gameRoundId,
            'exception' => $exception->getMessage()
        ]);
    }
}
