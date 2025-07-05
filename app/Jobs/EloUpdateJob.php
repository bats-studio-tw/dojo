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

        Log::info('🔧 EloUpdateJob 实例已创建', [
            'game_round_id' => $this->gameRoundId,
            'queue_name' => 'low',
            'job_class' => get_class($this)
        ]);
    }

    /**
     * 執行 Job。
     */
    public function handle(EloRatingEngine $eloRatingEngine): void
    {
        $startTime = microtime(true);

        Log::info('[EloUpdateJob] 任务开始，处理 Round ID: ' . $this->gameRoundId, [
            'game_round_id' => $this->gameRoundId,
            'queue_name' => $this->queue,
            'start_time' => now()->toISOString()
        ]);

        try {
            // 步骤1: 读取结算后的 round_results
            Log::info('[EloUpdateJob] 开始读取结算结果...', [
                'game_round_id' => $this->gameRoundId
            ]);

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

            Log::info('[EloUpdateJob] 获取到 ' . $results->count() . ' 笔赛果', [
                'game_round_id' => $this->gameRoundId,
                'results_count' => $results->count(),
                'results_data' => $results->toArray()
            ]);

            // 步骤2: 整理排名结果
            Log::info('[EloUpdateJob] 开始整理排名结果...', [
                'game_round_id' => $this->gameRoundId
            ]);

            // 將結果按排名整理成 [rank => symbol]
            $rankedSymbols = $results->pluck('token_symbol', 'rank')->toArray();

            Log::info('[EloUpdateJob] 排名结果整理完成', [
                'game_round_id' => $this->gameRoundId,
                'ranked_symbols' => $rankedSymbols,
                'rank_count' => count($rankedSymbols)
            ]);

            // 步骤3: 开始 Elo 评分更新
            $totalCombinations = count($rankedSymbols) * (count($rankedSymbols) - 1) / 2;
            Log::info('[EloUpdateJob] 开始 Elo 评分更新...', [
                'game_round_id' => $this->gameRoundId,
                'total_combinations' => $totalCombinations
            ]);

            $updateCount = 0;
            $eloUpdates = [];
            $errors = [];

            // 5x4/2 組對戰 → EloRatingEngine::updateElo(win, lose)
            // 遍歷所有可能的勝負對
            for ($i = 1; $i <= count($rankedSymbols); $i++) {
                for ($j = $i + 1; $j <= count($rankedSymbols); $j++) {
                    $winnerSymbol = $rankedSymbols[$i];
                    $loserSymbol = $rankedSymbols[$j];

                    $combinationNumber = $updateCount + 1;
                    Log::info('[EloUpdateJob] 处理对战组合', [
                        'game_round_id' => $this->gameRoundId,
                        'combination' => "{$combinationNumber}",
                        'winner_rank' => $i,
                        'loser_rank' => $j,
                        'winner_symbol' => $winnerSymbol,
                        'loser_symbol' => $loserSymbol
                    ]);

                    try {
                        // 在更新前获取当前的games数量来计算K值衰减
                        $winnerRating = TokenRating::firstOrCreate(['symbol' => strtoupper($winnerSymbol)]);
                        $loserRating = TokenRating::firstOrCreate(['symbol' => strtoupper($loserSymbol)]);

                        Log::info('[EloUpdateJob] 获取当前评分状态', [
                            'winner_symbol' => $winnerSymbol,
                            'winner_elo' => $winnerRating->elo,
                            'winner_games' => $winnerRating->games,
                            'loser_symbol' => $loserSymbol,
                            'loser_elo' => $loserRating->elo,
                            'loser_games' => $loserRating->games
                        ]);

                        // 計算衰減後的 K 值
                        $winnerKFactor = $this->calculateKFactor($winnerRating->games);
                        $loserKFactor = $this->calculateKFactor($loserRating->games);

                        // 使用平均 K 值进行更新
                        $averageKFactor = ($winnerKFactor + $loserKFactor) / 2;

                        Log::info('[EloUpdateJob] K值计算', [
                            'winner_k_factor' => $winnerKFactor,
                            'loser_k_factor' => $loserKFactor,
                            'average_k_factor' => $averageKFactor
                        ]);

                        $eloRatingEngine->updateElo($winnerSymbol, $loserSymbol, $averageKFactor);

                        $updateCount++;
                        $eloUpdates[] = [
                            'winner' => $winnerSymbol,
                            'loser' => $loserSymbol,
                            'k_factor' => $averageKFactor,
                            'winner_old_elo' => $winnerRating->elo,
                            'loser_old_elo' => $loserRating->elo
                        ];

                        Log::info('[EloUpdateJob] 对战组合处理完成', [
                            'combination' => "{$updateCount}",
                            'winner' => $winnerSymbol,
                            'loser' => $loserSymbol,
                            'k_factor' => $averageKFactor
                        ]);

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

            Log::info('[EloUpdateJob] Elo 评分更新完成', [
                'game_round_id' => $this->gameRoundId,
                'total_updates' => $updateCount,
                'successful_updates' => count($eloUpdates),
                'failed_updates' => count($errors),
                'elo_updates' => $eloUpdates
            ]);

            if (!empty($errors)) {
                Log::warning('[EloUpdateJob] 部分对战组合更新失败', [
                    'game_round_id' => $this->gameRoundId,
                    'errors' => $errors
                ]);
            }

            // 步骤4: 记录更新后的评分状态
            Log::info('[EloUpdateJob] 开始记录更新后的评分状态...', [
                'game_round_id' => $this->gameRoundId
            ]);

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

            Log::info('[EloUpdateJob] 更新后的 Elo 评分状态记录完成', [
                'game_round_id' => $this->gameRoundId,
                'final_ratings' => $finalRatings,
                'ratings_count' => count($finalRatings)
            ]);

            $endTime = microtime(true);
            $executionTime = round(($endTime - $startTime) * 1000, 2);

            Log::info('[EloUpdateJob] 任务完成，Round ID: ' . $this->gameRoundId, [
                'game_round_id' => $this->gameRoundId,
                'execution_time_ms' => $executionTime,
                'total_updates' => $updateCount,
                'successful_updates' => count($eloUpdates),
                'failed_updates' => count($errors),
                'end_time' => now()->toISOString()
            ]);

        } catch (\Throwable $e) {
            // 加入一个 catch 区块来捕获任何可能的错误
            $endTime = microtime(true);
            $executionTime = round(($endTime - $startTime) * 1000, 2);

            Log::error('[EloUpdateJob] 任务执行时发生严重错误', [
                'game_round_id' => $this->gameRoundId,
                'execution_time_ms' => $executionTime,
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

        Log::info('[EloUpdateJob] K值计算详情', [
            'games' => $games,
            'k_base' => $kBase,
            'k_factor' => $kFactor
        ]);

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
