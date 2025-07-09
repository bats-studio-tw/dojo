<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Models\GameRound;
use App\Models\HybridRoundPredict;

class ExportPredictionAnalysis extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'analysis:export-predictions {--limit= : 限制輸出最新多少局的數據，不指定則輸出全部}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export all prediction and result data into a CSV file for analysis.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting prediction data export...');

        // 獲取限制參數
        $limit = $this->option('limit');
        if ($limit && (!is_numeric($limit) || $limit < 1)) {
            $this->error('Limit parameter must be a positive integer.');
            return 1;
        }

        // 建立基本查詢
        $query = GameRound::whereNotNull('settled_at')
            ->whereHas('roundPredicts')
            ->whereHas('roundResults')
            ->orderBy('settled_at', 'desc'); // 按結算時間降序排列，獲取最新的

        // 先获取总数
        $totalRounds = $query->count();

        if ($totalRounds === 0) {
            $this->warn('No completed rounds with predictions and results found.');
            return 1;
        }

        // 應用限制
        if ($limit) {
            $query->limit($limit);
            $actualRounds = min($limit, $totalRounds);
            $this->info("Found {$totalRounds} total rounds. Processing latest {$actualRounds} rounds.");
        } else {
            $actualRounds = $totalRounds;
            $this->info("Found {$totalRounds} rounds to process.");
        }

        // 从 composer.json 获取算法版本
        $composerPath = base_path('composer.json');
        $version = 'dev';
        if (file_exists($composerPath)) {
            $composerData = json_decode(file_get_contents($composerPath), true);
            $gamePredictionConfig = $composerData['extra']['game-prediction'] ?? [];
            $version = $gamePredictionConfig['algorithm-version'] ?? 'dev';
        }

        $datetime = now()->format('Ymd_His');
        $limitSuffix = $limit ? '_latest' . $limit : '_all';
        $filePath = 'prediction_analysis_' . $version . $limitSuffix . '_' . $datetime . '.csv';
        // 使用 Storage Facade 來處理檔案，更安全
        Storage::disk('local')->put($filePath, ''); // 建立或清空檔案
        $fullPath = Storage::disk('local')->path($filePath);
        $fileHandle = fopen($fullPath, 'w');

        // 定義 CSV 標頭 - 与算法实际输出匹配
        $headers = [
            // 基础信息
            'round_id', 'settled_at', 'token_symbol',
            // 预测vs实际结果
            'predicted_rank', 'actual_rank', 'rank_difference', 'is_breakeven',
            'prediction_score',

            // 算法核心评分 (来自算法的直接输出)
            'absolute_score', 'relative_score', 'predicted_final_value', 'risk_adjusted_score',

            // 历史统计数据
            'total_games', 'wins', 'top3', 'win_rate', 'top3_rate', 'avg_rank', 'avg_value', 'value_stddev',

            // 市场变化数据 (算法实际获取的字段)
            'change_5m', 'change_1h', 'change_4h', 'change_24h',

            // H2H相关 (重要的算法特色)
            'h2h_score', 'h2h_data_available',

            // Hybrid 预测数据
            'hybrid_predicted_rank', 'hybrid_rank_difference', 'hybrid_final_score', 'hybrid_elo_prob', 'hybrid_mom_score', 'hybrid_confidence',
        ];
        fputcsv($fileHandle, $headers);

        $processedPredictions = 0;
        $processedRounds = 0;

        // 使用 Query Builder 的 chunk，每次處理100筆
        $query->with(['roundPredicts', 'roundResults'])
            ->chunk(100, function ($rounds) use ($fileHandle, &$processedPredictions, &$processedRounds, $version) {
                // 批量獲取這批回合的 Hybrid 預測數據
                $roundIds = $rounds->pluck('id')->toArray();
                $hybridPredictionsCollection = HybridRoundPredict::whereIn('game_round_id', $roundIds)
                    ->get()
                    ->groupBy('game_round_id');

                foreach ($rounds as $round) {
                    // 建立一個快速查找賽果的 map
                    $resultsMap = $round->roundResults->keyBy('token_symbol');

                    // 獲取該回合的 Hybrid 預測數據
                    $hybridPredictions = $hybridPredictionsCollection->get($round->id, collect())->keyBy('token_symbol');

                    $roundHasPredictions = false;
                    foreach ($round->roundPredicts as $prediction) {
                        $symbol = $prediction->token_symbol;
                        $actualResult = $resultsMap->get($symbol);

                        if (!$actualResult) {
                            continue; // 如果賽果中沒有該代幣，則跳過
                        }

                        // 獲取對應的 Hybrid 預測
                        $hybridPrediction = $hybridPredictions->get($symbol);

                        // 從 prediction_data JSON 中提取詳細數據
                        $predictionData = $prediction->prediction_data ?? [];

                        // 检查是否有H2H数据
                        $h2hDataAvailable = isset($predictionData['h2h_stats']) &&
                                          is_array($predictionData['h2h_stats']) &&
                                          !empty($predictionData['h2h_stats']) ? '1' : '0';

                        $rowData = [
                            // 基础信息
                            $round->round_id,
                            $round->settled_at->toDateTimeString(),
                            $symbol,

                            // 预测vs实际结果
                            $prediction->predicted_rank,
                            $actualResult->rank,
                            abs($prediction->predicted_rank - $actualResult->rank),
                            $actualResult->rank <= 3 ? '1' : '0', // 1 代表保本, 0 代表虧本
                            $prediction->prediction_score,

                            // 算法核心评分
                            $predictionData['absolute_score'] ?? null,
                            $predictionData['relative_score'] ?? null,
                            $predictionData['predicted_final_value'] ?? null,
                            $predictionData['risk_adjusted_score'] ?? null,

                            // 历史统计数据
                            $predictionData['total_games'] ?? null,
                            $predictionData['wins'] ?? null,
                            $predictionData['top3'] ?? null,
                            $predictionData['win_rate'] ?? null,
                            $predictionData['top3_rate'] ?? null,
                            $predictionData['avg_rank'] ?? null,
                            $predictionData['avg_value'] ?? null,
                            $predictionData['value_stddev'] ?? null,

                            // 市场变化数据
                            $predictionData['change_5m'] ?? null,
                            $predictionData['change_1h'] ?? null,
                            $predictionData['change_4h'] ?? null,
                            $predictionData['change_24h'] ?? null,

                            // H2H相关
                            $predictionData['h2h_score'] ?? null,
                            $h2hDataAvailable,

                            // Hybrid 预测数据
                            $hybridPrediction?->predicted_rank ?? null,
                            $hybridPrediction ? abs($hybridPrediction->predicted_rank - $actualResult->rank) : null,
                            $hybridPrediction?->final_score ?? null,
                            $hybridPrediction?->elo_prob ?? null,
                            $hybridPrediction?->mom_score ?? null,
                            $hybridPrediction?->confidence ?? null,
                        ];
                        fputcsv($fileHandle, $rowData);
                        $processedPredictions++;
                        $roundHasPredictions = true;
                    }

                    if ($roundHasPredictions) {
                        $processedRounds++;
                    }
                }
            });

        fclose($fileHandle);

        $this->info("Export complete! {$processedPredictions} predictions from {$processedRounds} rounds have been saved.");
        $this->comment("File location: " . $fullPath);
        if ($limit && $totalRounds > $limit) {
            $this->comment("Note: Only the latest {$limit} rounds were exported out of {$totalRounds} total rounds.");
        }

        return 0;
    }
}
