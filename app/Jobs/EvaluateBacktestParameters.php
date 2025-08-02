<?php

// app/Jobs/EvaluateBacktestParameters.php

namespace App\Jobs;

use App\Models\BacktestResult;
use App\Models\GameRound;
use App\Repositories\TokenPriceRepository;
use App\Services\EloRatingEngine;
use App\Services\ScoreMixer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EvaluateBacktestParameters implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5分鐘超時

    public $tries = 3; // 重試3次

    /**
     * Create a new job instance.
     */
    public function __construct(
        private string $runId,
        private array $parameters,
        private int $gameCount = 1000
    ) {
        $this->onQueue('backtesting');
    }

    /**
     * Execute the job.
     */
    public function handle(
        ScoreMixer $scoreMixer,
        EloRatingEngine $eloEngine,
        TokenPriceRepository $tokenPriceRepo
    ): void {
        Log::info('開始評估回測參數', [
            'run_id' => $this->runId,
            'parameters' => $this->parameters,
            'game_count' => $this->gameCount,
        ]);

        try {
            // 計算參數雜湊
            $paramsHash = md5(json_encode($this->parameters));

            // 檢查是否已存在相同參數的結果
            $existingResult = BacktestResult::where('run_id', $this->runId)
                ->where('params_hash', $paramsHash)
                ->first();

            if ($existingResult) {
                Log::info('跳過重複的參數組合', [
                    'run_id' => $this->runId,
                    'params_hash' => $paramsHash,
                ]);

                return;
            }

            // 分批獲取遊戲數據以優化內存使用
            $gameIds = $this->loadGameIdsInBatches();

            if (empty($gameIds)) {
                Log::warning('沒有找到可用的遊戲數據');

                return;
            }

            $results = $this->evaluateParameters($gameIds, $scoreMixer, $eloEngine, $tokenPriceRepo);

            // 使用 Eloquent 的 updateOrCreate 以正确设置 timestamps
            BacktestResult::updateOrCreate(
                [
                    'run_id' => $this->runId,
                    'params_hash' => $paramsHash,
                ],
                [
                    'parameters' => $this->parameters, // 直接传递数组，由模型自动转换
                    'score' => $results['score'],
                    'total_games' => $results['total_games'],
                    'correct_predictions' => $results['correct_predictions'],
                    'top3_correct_predictions' => $results['top3_correct_predictions'], // 新增
                    'accuracy' => $results['accuracy'],
                    'weighted_accuracy' => $results['weighted_accuracy'],
                    'top3_accuracy' => $results['top3_accuracy'], // 新增
                    'top3_weighted_accuracy' => $results['top3_weighted_accuracy'], // 新增
                    'precision_at_3' => $results['precision_at_3'], // 新增
                    'avg_confidence' => $results['avg_confidence'],
                    'detailed_results' => $results['detailed_results'], // 直接传递数组
                ]
            );

            Log::info('參數評估完成', [
                'run_id' => $this->runId,
                'params_hash' => $paramsHash,
                'score' => $results['score'],
                'accuracy' => $results['accuracy'],
                'weighted_accuracy' => $results['weighted_accuracy'],
                'top3_accuracy' => $results['top3_accuracy'], // 新增
                'top3_weighted_accuracy' => $results['top3_weighted_accuracy'], // 新增
                'precision_at_3' => $results['precision_at_3'], // 新增
                'avg_confidence' => $results['avg_confidence'],
                'top3_improvement' => $results['top3_accuracy'] - $results['accuracy'], // 新增：顯示提升幅度
            ]);

        } catch (\Exception $e) {
            Log::error('參數評估失敗', [
                'run_id' => $this->runId,
                'parameters' => $this->parameters,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * 分批獲取遊戲ID以優化內存使用
     */
    private function loadGameIdsInBatches(): array
    {
        $gameIds = [];
        $batchSize = 200; // 每批200個遊戲
        $offset = 0;

        do {
            $batch = GameRound::whereNotNull('settled_at')
                ->orderBy('id', 'desc')
                ->offset($offset)
                ->limit($batchSize)
                ->pluck('id')
                ->toArray();

            $gameIds = array_merge($gameIds, $batch);
            $offset += $batchSize;

            // 如果已經獲取足夠的遊戲數量，停止
            if (count($gameIds) >= $this->gameCount) {
                $gameIds = array_slice($gameIds, 0, $this->gameCount);
                break;
            }

        } while (! empty($batch));

        return $gameIds;
    }

    /**
     * 評估參數組合 - 引入近期數據加權機制
     */
    private function evaluateParameters(
        array $gameIds,
        ScoreMixer $scoreMixer,
        EloRatingEngine $eloEngine,
        TokenPriceRepository $tokenPriceRepo
    ): array {
        $totalGames = 0;
        $correctPredictions = 0;
        $top3CorrectPredictions = 0; // 新增：Top3 正確預測計數
        $totalConfidence = 0;
        $detailedResults = [];

        // 新增：時間加權相關變數
        $totalWeightedScore = 0;
        $top3TotalWeightedScore = 0; // 新增：Top3 加權分數
        $totalWeight = 0;
        $maxRecentWeight = 1.5; // 最新遊戲的權重
        $minOldWeight = 0.5;    // 最舊遊戲的權重

        // 新增：Precision@3 計算相關變數
        $totalPrecisionAt3 = 0;
        $precisionCalculations = 0;

        // 分批處理遊戲以優化內存
        $batchSize = 50;
        $gameBatches = array_chunk($gameIds, $batchSize);

        // 計算總遊戲數以便權重計算
        $totalExpectedGames = count($gameIds);

        foreach ($gameBatches as $batchIndex => $batchGameIds) {
            Log::info('處理遊戲批次', [
                'batch' => $batchIndex + 1,
                'total_batches' => count($gameBatches),
                'batch_size' => count($batchGameIds),
            ]);

            // 獲取當前批次的遊戲數據
            $games = $this->loadGameData($batchGameIds);

            foreach ($games as $gameIndex => $game) {
                $result = $this->evaluateSingleGame($game, $scoreMixer, $eloEngine, $tokenPriceRepo);

                if ($result) {
                    // 計算當前遊戲在整體序列中的索引
                    $globalIndex = ($batchIndex * $batchSize) + $gameIndex;

                    // 計算時間權重：最新的遊戲權重最高，最舊的權重最低
                    $recencyWeight = $maxRecentWeight - (($globalIndex / $totalExpectedGames) * ($maxRecentWeight - $minOldWeight));

                    $totalGames++;
                    $totalConfidence += $result['confidence'];
                    $totalWeight += $recencyWeight;

                    if ($result['is_correct']) {
                        $correctPredictions++;
                        $totalWeightedScore += $recencyWeight; // 加權正確預測
                    }

                    // 新增：處理 Top3 相關統計
                    if ($result['is_top3_correct']) {
                        $top3CorrectPredictions++;
                        $top3TotalWeightedScore += $recencyWeight; // Top3 加權正確預測
                    }

                    // 計算 Precision@3：檢查預測的前3名中有多少在實際前3名中
                    if (isset($result['actual_top3_tokens']) && !empty($result['actual_top3_tokens'])) {
                        // 這裡我們先只考慮預測的第一名，後續可以擴展到預測的前3名
                        $predictedSymbol = null;

                        // 從 game 數據中獲取預測的代幣符號
                        // 這個邏輯需要配合 evaluateSingleGame 的改進
                        $precision = $result['is_top3_correct'] ? 1.0 : 0.0;
                        $totalPrecisionAt3 += $precision;
                        $precisionCalculations++;
                    }

                    $detailedResults[] = [
                        'game_id' => $game['id'],
                        'predicted_rank' => $result['predicted_rank'],
                        'actual_rank' => $result['actual_rank'],
                        'confidence' => $result['confidence'],
                        'is_correct' => $result['is_correct'],
                        'is_top3_correct' => $result['is_top3_correct'], // 新增
                        'recency_weight' => $recencyWeight, // 記錄權重用於調試
                    ];
                }
            }

            // 清理內存
            unset($games);
            gc_collect_cycles();
        }

        // 計算傳統準確率和加權準確率
        $accuracy = $totalGames > 0 ? ($correctPredictions / $totalGames) * 100 : 0;
        $weightedAccuracy = $totalWeight > 0 ? ($totalWeightedScore / $totalWeight) * 100 : 0;

        // 計算 Top3 相關指標
        $top3Accuracy = $totalGames > 0 ? ($top3CorrectPredictions / $totalGames) * 100 : 0;
        $top3WeightedAccuracy = $totalWeight > 0 ? ($top3TotalWeightedScore / $totalWeight) * 100 : 0;
        $precisionAt3 = $precisionCalculations > 0 ? ($totalPrecisionAt3 / $precisionCalculations) * 100 : 0;

        $avgConfidence = $totalGames > 0 ? $totalConfidence / $totalGames : 0;

        Log::info('評估統計', [
            'run_id' => $this->runId,
            'traditional_accuracy' => $accuracy,
            'weighted_accuracy' => $weightedAccuracy,
            'top3_accuracy' => $top3Accuracy,
            'top3_weighted_accuracy' => $top3WeightedAccuracy,
            'precision_at_3' => $precisionAt3,
            'avg_confidence' => $avgConfidence,
            'total_weight' => $totalWeight,
            'weight_difference' => abs($accuracy - $weightedAccuracy),
            'top3_improvement' => $top3Accuracy - $accuracy,
        ]);

        // 計算綜合評分 - 現在主要使用 Top3 加權準確率
        $score = $this->calculateCompositeScore($top3WeightedAccuracy, $avgConfidence, $totalGames, $top3Accuracy);

        return [
            'score' => $score,
            'total_games' => $totalGames,
            'correct_predictions' => $correctPredictions,
            'top3_correct_predictions' => $top3CorrectPredictions, // 新增
            'accuracy' => $accuracy,
            'weighted_accuracy' => $weightedAccuracy,
            'top3_accuracy' => $top3Accuracy, // 新增
            'top3_weighted_accuracy' => $top3WeightedAccuracy, // 新增
            'precision_at_3' => $precisionAt3, // 新增
            'avg_confidence' => $avgConfidence,
            'detailed_results' => $detailedResults,
        ];
    }

    /**
     * 獲取遊戲數據
     */
    private function loadGameData(array $gameIds): array
    {
        return GameRound::whereIn('id', $gameIds)
            ->with(['roundResults' => function ($query) {
                $query->orderBy('rank');
            }])
            ->get()
            ->toArray();
    }

    /**
     * 評估單個遊戲
     */
    private function evaluateSingleGame(
        array $game,
        ScoreMixer $scoreMixer,
        EloRatingEngine $eloEngine,
        TokenPriceRepository $tokenPriceRepo
    ): ?array {
        try {
            // 獲取實際結果
            $actualResults = $this->loadActualResults($game);
            if (empty($actualResults)) {
                return null;
            }

            // 獲取預測數據
            $eloProb = $this->getEloProbabilities($game['id'], $eloEngine);
            $momentumScores = $this->getMomentumScores($game['id'], $tokenPriceRepo);

            // 使用動態參數進行預測
            $predictions = $scoreMixer->mixWithParams($eloProb, $momentumScores, $this->parameters);

            if (empty($predictions)) {
                return null;
            }

            $topPrediction = $predictions[0];
            $predictedSymbol = $topPrediction['symbol'];
            $confidence = $topPrediction['confidence'];

            // 檢查預測是否正確
            $actualRank = $actualResults[$predictedSymbol] ?? null;
            $isCorrect = $actualRank === 1; // 傳統準確率：只有第一名算正確
            $isTop3Correct = $actualRank !== null && $actualRank <= 3; // Top3 準確率：前三名都算正確

            // 獲取實際前三名列表用於 precision@3 計算
            $actualTop3Tokens = array_keys(array_filter($actualResults, fn($rank) => $rank <= 3));

            return [
                'predicted_rank' => $topPrediction['predicted_rank'],
                'actual_rank' => $actualRank,
                'confidence' => $confidence,
                'is_correct' => $isCorrect,
                'is_top3_correct' => $isTop3Correct,
                'actual_top3_tokens' => $actualTop3Tokens, // 用於後續 precision@3 計算
            ];

        } catch (\Exception $e) {
            Log::warning('評估單個遊戲失敗', [
                'game_id' => $game['id'],
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * 獲取實際結果
     */
    private function loadActualResults(array $game): array
    {
        $results = [];
        foreach ($game['round_results'] as $result) {
            $results[$result['token_symbol']] = $result['rank'];
        }

        return $results;
    }

    /**
     * 獲取Elo機率
     */
    private function getEloProbabilities(int $gameId, EloRatingEngine $eloEngine): array
    {
        try {
            // 從遊戲結果中獲取代幣符號
            $game = GameRound::with('roundResults')->find($gameId);
            if (! $game || empty($game->roundResults)) {
                return [];
            }

            $symbols = $game->roundResults->pluck('token_symbol')->toArray();

            return $eloEngine->probabilities($symbols);
        } catch (\Exception $e) {
            Log::warning('獲取Elo機率失敗', [
                'game_id' => $gameId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * 獲取動能分數
     */
    private function getMomentumScores(int $gameId, TokenPriceRepository $tokenPriceRepo): ?array
    {
        try {
            // 暫時返回null，避免動能計算錯誤
            // TODO: 實現完整的動能分數計算邏輯
            Log::info('動能分數計算暫時禁用', ['game_id' => $gameId]);

            return null;
        } catch (\Exception $e) {
            Log::warning('獲取動能分數失敗', [
                'game_id' => $gameId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * 計算綜合評分 - 以 Top3 準確率為核心，權重調整
     */
    private function calculateCompositeScore(float $top3WeightedAccuracy, float $avgConfidence, int $totalGames, float $top3Accuracy): float
    {
        // 主要分數：Top3 加權準確率，權重 60%
        $top3Score = $top3WeightedAccuracy * 0.6;

        // 信心度獎勵：平均信心度，權重 30%
        $confidenceBonus = $avgConfidence * 0.3;

        // 樣本量獎勵：遊戲數量越多，獎勵越高（但有限制），權重 10%
        $sampleBonus = min($totalGames / 1000, 1) * 10; // 最多10分獎勵

        // 額外獎勵：如果 Top3 準確率很高，給予額外獎勵
        $excellenceBonus = 0;
        if ($top3Accuracy >= 70) {
            $excellenceBonus = ($top3Accuracy - 70) * 0.1; // 每超過70%，額外獎勵0.1分/百分點
        }

        $totalScore = $top3Score + $confidenceBonus + $sampleBonus + $excellenceBonus;

        Log::debug('綜合評分計算詳情 (新版本)', [
            'top3_weighted_accuracy' => $top3WeightedAccuracy,
            'top3_score' => $top3Score,
            'confidence_bonus' => $confidenceBonus,
            'sample_bonus' => $sampleBonus,
            'excellence_bonus' => $excellenceBonus,
            'total_score' => $totalScore,
            'scoring_weights' => [
                'top3_weight' => '60%',
                'confidence_weight' => '30%',
                'sample_weight' => '10%',
                'excellence_threshold' => '70%',
            ],
        ]);

        return round($totalScore, 4);
    }
}
