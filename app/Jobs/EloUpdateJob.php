<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\EloRatingEngine;
use App\Models\RoundResult;
use App\Models\TokenRating;
use Illuminate\Support\Facades\DB;
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
            // 步骤1: 读取结算后的 round_results
            $results = RoundResult::where('game_round_id', $this->gameRoundId)
                                  ->orderBy('rank')
                                  ->get();

            if ($results->isEmpty()) {
                Log::warning("[EloUpdateJob] 赛果不足，无法更新 Elo。Round ID: " . $this->gameRoundId, [
                    'game_round_id' => $this->gameRoundId,
                    'query_conditions' => [
                        'game_round_id' => $this->gameRoundId,
                        'order_by' => 'rank'
                    ]
                ]);
                return;
            }

            // 步骤2: 整理排名结果
            // 將結果按排名整理成 [rank => symbol]
            $rankedSymbols = $results->pluck('token_symbol', 'rank')->toArray();

            // 步骤3: 开始 Elo 评分更新
            $updateCount = 0;
            $eloUpdates = [];
            $errors = [];

            // 5x4/2 組對戰 → EloRatingEngine::updateElo(win, lose)
            // 遍歷所有可能的勝負對
            for ($i = 1; $i <= count($rankedSymbols); $i++) {
                for ($j = $i + 1; $j <= count($rankedSymbols); $j++) {
                    $winnerSymbol = $rankedSymbols[$i];
                    $loserSymbol = $rankedSymbols[$j];

                    try {
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
                            'k_factor' => $averageKFactor,
                            'winner_old_elo' => $winnerRating->elo,
                            'loser_old_elo' => $loserRating->elo
                        ];

                    } catch (\Exception $updateError) {
                        $errorCombinationNumber = $updateCount + 1;
                        $errorInfo = [
                            'combination' => "{$errorCombinationNumber}",
                            'winner' => $winnerSymbol,
                            'loser' => $loserSymbol,
                            'error' => $updateError->getMessage()
                        ];

                        $errors[] = $errorInfo;
                        Log::error('[EloUpdateJob] 对战组合处理失败', $errorInfo);
                    }
                }
            }

            if (!empty($errors)) {
                Log::warning('[EloUpdateJob] 部分对战组合更新失败', [
                    'game_round_id' => $this->gameRoundId,
                    'errors' => $errors
                ]);
            }

            // 步骤4: 记录更新后的评分状态
            $finalRatings = [];
            foreach ($rankedSymbols as $rank => $symbol) {
                $rating = TokenRating::where('symbol', strtoupper($symbol))->first();
                if ($rating) {
                    $finalRatings[$symbol] = [
                        'rank' => $rank,
                        'elo' => round($rating->elo, 2),
                        'games' => $rating->games
                    ];
                } else {
                    Log::warning('[EloUpdateJob] 未找到代币评分记录', [
                        'symbol' => $symbol,
                        'rank' => $rank
                    ]);
                }
            }

        } catch (\Throwable $e) {
            Log::error('[EloUpdateJob] 任务执行时发生严重错误', [
                'game_round_id' => $this->gameRoundId,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString()
            ]);
            // 重新抛出异常，让 Worker 知道任务失败了
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
        $kFactor = $kBase * 200 / (200 + $games);

        return $kFactor;
    }

    /**
     * Job 失败时的处理
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('[EloUpdateJob] 任务执行失败', [
            'game_round_id' => $this->gameRoundId,
            'queue_name' => $this->queue,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
