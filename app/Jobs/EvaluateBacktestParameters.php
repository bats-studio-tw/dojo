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

            // 使用 updateOrInsert 防止重複插入
            BacktestResult::updateOrInsert(
                [
                    'run_id' => $this->runId,
                    'params_hash' => $paramsHash,
                ],
                [
                    'parameters' => json_encode($this->parameters),
                    'score' => $results['score'],
                    'total_games' => $results['total_games'],
                    'correct_predictions' => $results['correct_predictions'],
                    'accuracy' => $results['accuracy'],
                    'avg_confidence' => $results['avg_confidence'],
                    'detailed_results' => json_encode($results['detailed_results']),
                ]
            );

            Log::info('參數評估完成', [
                'run_id' => $this->runId,
                'params_hash' => $paramsHash,
                'score' => $results['score'],
                'accuracy' => $results['accuracy'],
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
     * 評估參數組合
     */
    private function evaluateParameters(
        array $gameIds,
        ScoreMixer $scoreMixer,
        EloRatingEngine $eloEngine,
        TokenPriceRepository $tokenPriceRepo
    ): array {
        $totalGames = 0;
        $correctPredictions = 0;
        $totalConfidence = 0;
        $detailedResults = [];

        // 分批處理遊戲以優化內存
        $batchSize = 50;
        $gameBatches = array_chunk($gameIds, $batchSize);

        foreach ($gameBatches as $batchIndex => $batchGameIds) {
            Log::info('處理遊戲批次', [
                'batch' => $batchIndex + 1,
                'total_batches' => count($gameBatches),
                'batch_size' => count($batchGameIds),
            ]);

            // 獲取當前批次的遊戲數據
            $games = $this->loadGameData($batchGameIds);

            foreach ($games as $game) {
                $result = $this->evaluateSingleGame($game, $scoreMixer, $eloEngine, $tokenPriceRepo);

                if ($result) {
                    $totalGames++;
                    $totalConfidence += $result['confidence'];

                    if ($result['is_correct']) {
                        $correctPredictions++;
                    }

                    $detailedResults[] = [
                        'game_id' => $game['id'],
                        'predicted_rank' => $result['predicted_rank'],
                        'actual_rank' => $result['actual_rank'],
                        'confidence' => $result['confidence'],
                        'is_correct' => $result['is_correct'],
                    ];
                }
            }

            // 清理內存
            unset($games);
            gc_collect_cycles();
        }

        $accuracy = $totalGames > 0 ? ($correctPredictions / $totalGames) * 100 : 0;
        $avgConfidence = $totalGames > 0 ? $totalConfidence / $totalGames : 0;

        // 計算綜合評分
        $score = $this->calculateCompositeScore($accuracy, $avgConfidence, $totalGames);

        return [
            'score' => $score,
            'total_games' => $totalGames,
            'correct_predictions' => $correctPredictions,
            'accuracy' => $accuracy,
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
            $isCorrect = $actualRank === 1;

            return [
                'predicted_rank' => $topPrediction['predicted_rank'],
                'actual_rank' => $actualRank,
                'confidence' => $confidence,
                'is_correct' => $isCorrect,
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
     * 計算綜合評分
     */
    private function calculateCompositeScore(float $accuracy, float $avgConfidence, int $totalGames): float
    {
        // 基礎分數：準確率 * 100
        $baseScore = $accuracy;

        // 信心度獎勵：平均信心度 * 0.1
        $confidenceBonus = $avgConfidence * 0.1;

        // 樣本量獎勵：遊戲數量越多，獎勵越高（但有限制）
        $sampleBonus = min($totalGames / 1000 * 5, 10); // 最多10分獎勵

        return round($baseScore + $confidenceBonus + $sampleBonus, 4);
    }
}
