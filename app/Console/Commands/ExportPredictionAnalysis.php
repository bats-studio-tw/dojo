<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Models\GameRound;

class ExportPredictionAnalysis extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'analysis:export-predictions';

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

        // 先获取总数，避免一次性加载过多数据
        $totalRounds = GameRound::whereNotNull('settled_at')
            ->whereHas('roundPredicts')
            ->whereHas('roundResults')
            ->count();

        if ($totalRounds === 0) {
            $this->warn('No completed rounds with predictions and results found.');
            return 1;
        }

        $this->info("Found {$totalRounds} rounds to process.");

        // 从 composer.json 获取算法版本
        $composerPath = base_path('composer.json');
        $version = 'dev';
        if (file_exists($composerPath)) {
            $composerData = json_decode(file_get_contents($composerPath), true);
            $gamePredictionConfig = $composerData['extra']['game-prediction'] ?? [];
            $version = $gamePredictionConfig['algorithm-version'] ?? 'dev';
        }

        $datetime = now()->format('Ymd_His');
        $filePath = 'prediction_analysis_' . $version . '_' . $datetime . '.csv';
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
        ];
        fputcsv($fileHandle, $headers);

        $processedPredictions = 0;

        // 使用 Query Builder 的 chunk，每次處理100筆
        GameRound::whereNotNull('settled_at')
            ->whereHas('roundPredicts')
            ->whereHas('roundResults')
            ->with(['roundPredicts', 'roundResults'])
            ->orderBy('id', 'asc')
            ->chunk(100, function ($rounds) use ($fileHandle, &$processedPredictions, $version) {
                foreach ($rounds as $round) {
                    // 建立一個快速查找賽果的 map
                    $resultsMap = $round->roundResults->keyBy('token_symbol');

                    foreach ($round->roundPredicts as $prediction) {
                        $symbol = $prediction->token_symbol;
                        $actualResult = $resultsMap->get($symbol);

                        if (!$actualResult) {
                            continue; // 如果賽果中沒有該代幣，則跳過
                        }

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

                        ];
                        fputcsv($fileHandle, $rowData);
                        $processedPredictions++;
                    }
                }
            });

        fclose($fileHandle);

        $this->info("Export complete! {$processedPredictions} predictions have been saved.");
        $this->comment("File location: " . $fullPath);

        return 0;
    }
}
